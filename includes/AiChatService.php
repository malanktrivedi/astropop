<?php
declare(strict_types=1);

require_once __DIR__ . '/VedicAstroAPI.php';
require_once __DIR__ . '/AstrologyChatContext.php';
require_once __DIR__ . '/ChatCreditService.php';

/**
 * ASTROPOP AI-chat orchestration layer.
 *
 * The provider contract is intentionally isolated here. VedicAstroAPI's
 * public AI-chat page confirms a POST-based conversational API, but the
 * exact request/response field names must come from the supplied Postman
 * contract and are therefore not guessed in this class.
 */
final class AiChatService
{
    private VedicAstroAPI $api;
    private AstrologyChatContext $context;
    private ChatCreditService $credits;

    public function __construct(
        ?VedicAstroAPI $api = null,
        ?AstrologyChatContext $context = null,
        ?ChatCreditService $credits = null
    ) {
        $this->api = $api ?? new VedicAstroAPI(
            VEDIC_API_BASE_URL,
            VEDIC_API_KEY,
            VEDIC_API_TIMEOUT,
            VEDIC_API_CONNECT_TIMEOUT
        );
        $this->context = $context ?? new AstrologyChatContext();
        $this->credits = $credits ?? new ChatCreditService();
    }

    /**
     * Return the current ASTRO_COIN balance for the authenticated user.
     */
    public function balance(int $userId): string
    {
        return $this->credits->balance($userId);
    }

    /**
     * Build provider-independent context for a chat request.
     * No astrology API call is made here.
     *
     * @return array<string,mixed>
     */
    public function buildContext(int $userId, int $profileId, ?string $topic = null): array
    {
        return $this->context->build($userId, $profileId, $topic);
    }

    /**
     * Provider call is deliberately blocked until the exact Postman AI-chat
     * request/response contract is configured. This prevents accidental use
     * of an invented endpoint or payload and protects provider credits.
     *
     * @param array<string,mixed> $chatContext
     * @param array<int,array<string,mixed>> $history
     * @return array<string,mixed>
     */
    public function send(int $userId, int $profileId, string $message, array $history = [], ?string $topic = null): array
    {
        $message = trim($message);
        if ($message === '') {
            throw new InvalidArgumentException('Message cannot be empty.');
        }
        if (mb_strlen($message) > 4000) {
            throw new InvalidArgumentException('Message is too long.');
        }

        $chatContext = $this->buildContext($userId, $profileId, $topic);

        $route = trim((string) env_value('VEDIC_AI_CHAT_ROUTE', ''));
        if ($route === '') {
            throw new RuntimeException('AI chat provider contract is not configured yet.');
        }

        /**
         * Do not add guessed provider fields here. Once the Postman contract
         * is confirmed, this block becomes the single provider adapter.
         */
        throw new RuntimeException('AI chat provider adapter is awaiting the documented Postman request/response contract.');
    }
}
