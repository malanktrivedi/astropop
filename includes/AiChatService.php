<?php
declare(strict_types=1);

require_once __DIR__ . '/VedicAstroAPI.php';
require_once __DIR__ . '/AstrologyChatContext.php';
require_once __DIR__ . '/ChatCreditService.php';

/** ASTROPOP AI-chat orchestration and persistence boundary. */
final class AiChatService
{
    private VedicAstroAPI $api;
    private AstrologyChatContext $context;
    private ChatCreditService $credits;

    public function __construct(?VedicAstroAPI $api = null, ?AstrologyChatContext $context = null, ?ChatCreditService $credits = null)
    {
        $this->api = $api ?? new VedicAstroAPI(VEDIC_API_BASE_URL, VEDIC_API_KEY, VEDIC_API_TIMEOUT, VEDIC_API_CONNECT_TIMEOUT);
        $this->context = $context ?? new AstrologyChatContext();
        $this->credits = $credits ?? new ChatCreditService();
    }

    public function balance(int $userId): string
    {
        return $this->credits->balance($userId);
    }

    private function assertProfileOwnership(int $userId, int $profileId): void
    {
        if ($userId <= 0 || $profileId <= 0) {
            throw new InvalidArgumentException('Invalid astrology profile.');
        }

        $stmt = db()->prepare('SELECT id FROM birth_profiles WHERE id=? AND user_id=? LIMIT 1');
        $stmt->bind_param('ii', $profileId, $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            throw new RuntimeException('Astrology profile was not found.');
        }
    }

    /** @return array<string,mixed> */
    public function buildContext(int $userId, int $profileId, ?string $topic = null): array
    {
        $this->assertProfileOwnership($userId, $profileId);
        return $this->context->build($userId, $profileId, $topic);
    }

    /** Find or create the active AI thread for a user/profile pair. */
    public function getOrCreateThread(int $userId, int $profileId): int
    {
        $this->assertProfileOwnership($userId, $profileId);

        $stmt = db()->prepare("SELECT id FROM chat_threads WHERE user_id=? AND birth_profile_id=? AND mode='ai' AND status IN ('open','active') ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('ii', $userId, $profileId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) return (int) $row['id'];

        $stmt = db()->prepare('SELECT id FROM kundli_calculations WHERE user_id=? AND birth_profile_id=? ORDER BY calculated_at DESC, id DESC LIMIT 1');
        $stmt->bind_param('ii', $userId, $profileId);
        $stmt->execute();
        $kundli = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$kundli) throw new RuntimeException('A completed Kundli is required before starting AI chat.');

        $kundliId = (int) $kundli['id'];
        $contextVersion = 'kundli-' . $kundliId;
        $stmt = db()->prepare("INSERT INTO chat_threads (user_id,mode,birth_profile_id,kundli_calculation_id,status,context_version,started_at,last_message_at) VALUES (?, 'ai', ?, ?, 'open', ?, NOW(), NOW())");
        $stmt->bind_param('iiis', $userId, $profileId, $kundliId, $contextVersion);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('AI chat thread could not be created.');
        }
        $threadId = (int) db()->insert_id;
        $stmt->close();
        return $threadId;
    }

    /** @return array<int,array<string,mixed>> */
    public function history(int $userId, int $profileId, int $limit = 50): array
    {
        $this->assertProfileOwnership($userId, $profileId);
        $limit = max(1, min(100, $limit));
        $stmt = db()->prepare("SELECT id FROM chat_threads WHERE user_id=? AND birth_profile_id=? AND mode='ai' AND status IN ('open','active') ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('ii', $userId, $profileId);
        $stmt->execute();
        $thread = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$thread) return [];

        $threadId = (int) $thread['id'];
        $stmt = db()->prepare("SELECT id,sender_type,message_type,body,metadata,created_at FROM chat_messages WHERE thread_id=? ORDER BY id DESC LIMIT {$limit}");
        $stmt->bind_param('i', $threadId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return array_reverse($rows);
    }

    /** Persist a message after a successful provider operation. */
    public function recordMessage(int $userId, int $profileId, string $senderType, string $body, string $messageType = 'text', array $metadata = []): int
    {
        if (!in_array($senderType, ['user', 'ai', 'system'], true)) throw new InvalidArgumentException('Invalid AI chat sender type.');
        $body = trim($body);
        if ($body === '') throw new InvalidArgumentException('Chat message cannot be empty.');

        $threadId = $this->getOrCreateThread($userId, $profileId);
        $metaJson = $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $senderUserId = $senderType === 'user' ? $userId : null;
        $stmt = db()->prepare('INSERT INTO chat_messages (thread_id,sender_type,sender_user_id,message_type,body,metadata) VALUES (?,?,?,?,?,?)');
        $stmt->bind_param('isisss', $threadId, $senderType, $senderUserId, $messageType, $body, $metaJson);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Chat message could not be saved.');
        }
        $messageId = (int) db()->insert_id;
        $stmt->close();

        $stmt = db()->prepare("UPDATE chat_threads SET status='active',last_message_at=NOW() WHERE id=?");
        $stmt->bind_param('i', $threadId);
        $stmt->execute();
        $stmt->close();
        return $messageId;
    }

    /** Provider call remains gated until the exact Postman AI-chat contract is confirmed. */
    public function send(int $userId, int $profileId, string $message, array $history = [], ?string $topic = null): array
    {
        $message = trim($message);
        if ($message === '') throw new InvalidArgumentException('Message cannot be empty.');
        if (mb_strlen($message) > 4000) throw new InvalidArgumentException('Message is too long.');
        $chatContext = $this->buildContext($userId, $profileId, $topic);
        if (!$chatContext) throw new RuntimeException('Astrology chat context is unavailable.');
        $route = trim((string) env_value('VEDIC_AI_CHAT_ROUTE', ''));
        if ($route === '') throw new RuntimeException('AI chat provider contract is not configured yet.');
        throw new RuntimeException('AI chat provider adapter is awaiting the documented Postman request/response contract.');
    }
}
