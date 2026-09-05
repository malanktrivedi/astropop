<?php
declare(strict_types=1);

require_once __DIR__ . '/OpenAIChatProvider.php';
require_once __DIR__ . '/AstrologyChatContext.php';
require_once __DIR__ . '/ChatCreditService.php';

/** ASTROPOP AI-chat orchestration, persistence, OpenAI integration and billing boundary. */
final class AiChatService
{
    private AiProviderInterface $provider;
    private AstrologyChatContext $context;
    private ChatCreditService $credits;
    private float $coinsPerMessage;

    public function __construct(?AiProviderInterface $provider = null, ?AstrologyChatContext $context = null, ?ChatCreditService $credits = null)
    {
        $this->provider = $provider ?? new OpenAIChatProvider();
        $this->context = $context ?? new AstrologyChatContext();
        $this->credits = $credits ?? new ChatCreditService();
        $this->coinsPerMessage = max(0, (float) env_value('AI_CHAT_COINS_PER_MESSAGE', '1'));
    }

    public function balance(int $userId): string { return $this->credits->balance($userId); }

    /** @return array<string,mixed> */
    public function buildContext(int $userId, int $profileId, ?string $topic = null): array
    {
        $this->assertProfileOwnership($userId, $profileId);
        return $this->context->build($userId, $profileId, $topic);
    }

    public function getOrCreateThread(int $userId, int $profileId): int
    {
        $this->assertProfileOwnership($userId, $profileId);
        $stmt = db()->prepare("SELECT id FROM chat_threads WHERE user_id=? AND birth_profile_id=? AND mode='ai' AND status IN ('open','active') ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('ii', $userId, $profileId); $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($row) return (int)$row['id'];

        $stmt = db()->prepare('SELECT id FROM kundli_calculations WHERE user_id=? AND birth_profile_id=? ORDER BY calculated_at DESC, id DESC LIMIT 1');
        $stmt->bind_param('ii', $userId, $profileId); $stmt->execute();
        $kundli = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$kundli) throw new RuntimeException('A completed Kundli is required before starting AI chat.');

        $kundliId = (int)$kundli['id']; $contextVersion = 'kundli-'.$kundliId;
        $stmt = db()->prepare("INSERT INTO chat_threads (user_id,mode,birth_profile_id,kundli_calculation_id,status,context_version,started_at,last_message_at) VALUES (?, 'ai', ?, ?, 'open', ?, NOW(), NOW())");
        $stmt->bind_param('iiis', $userId, $profileId, $kundliId, $contextVersion);
        if (!$stmt->execute()) { $stmt->close(); throw new RuntimeException('AI chat thread could not be created.'); }
        $id=(int)db()->insert_id; $stmt->close(); return $id;
    }

    /** @return array<int,array<string,mixed>> */
    public function history(int $userId, int $profileId, int $limit = 50): array
    {
        $this->assertProfileOwnership($userId, $profileId); $limit=max(1,min(100,$limit));
        $stmt=db()->prepare("SELECT id FROM chat_threads WHERE user_id=? AND birth_profile_id=? AND mode='ai' AND status IN ('open','active') ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('ii',$userId,$profileId); $stmt->execute(); $thread=$stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$thread) return [];
        $threadId=(int)$thread['id'];
        $stmt=db()->prepare("SELECT id,sender_type,message_type,body,metadata,created_at FROM chat_messages WHERE thread_id=? ORDER BY id DESC LIMIT {$limit}");
        $stmt->bind_param('i',$threadId); $stmt->execute(); $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        return array_reverse($rows);
    }

    public function recordMessage(int $userId,int $profileId,string $senderType,string $body,string $messageType='text',array $metadata=[]): int
    {
        if (!in_array($senderType,['user','ai','system'],true)) throw new InvalidArgumentException('Invalid AI chat sender type.');
        $body=trim($body); if ($body==='') throw new InvalidArgumentException('Chat message cannot be empty.');
        $threadId=$this->getOrCreateThread($userId,$profileId);
        $metaJson=$metadata?json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null;
        $senderUserId=$senderType==='user'?$userId:null;
        $stmt=db()->prepare('INSERT INTO chat_messages (thread_id,sender_type,sender_user_id,message_type,body,metadata) VALUES (?,?,?,?,?,?)');
        $stmt->bind_param('isisss',$threadId,$senderType,$senderUserId,$messageType,$body,$metaJson);
        if (!$stmt->execute()){ $stmt->close(); throw new RuntimeException('Chat message could not be saved.'); }
        $id=(int)db()->insert_id; $stmt->close();
        $stmt=db()->prepare("UPDATE chat_threads SET status='active',last_message_at=NOW() WHERE id=?"); $stmt->bind_param('i',$threadId); $stmt->execute(); $stmt->close();
        return $id;
    }

    /** Send one message through OpenAI and charge ASTRO_COIN only after a successful provider response. */
    public function send(int $userId,int $profileId,string $message,array $history=[],?string $topic=null): array
    {
        $this->assertProfileOwnership($userId,$profileId); $message=trim($message);
        if ($message==='') throw new InvalidArgumentException('Message cannot be empty.');
        if (mb_strlen($message)>4000) throw new InvalidArgumentException('Message is too long.');
        $context=$this->buildContext($userId,$profileId,$topic); if (!$context) throw new RuntimeException('Astrology chat context is unavailable.');
        if ($history===[]) $history=$this->history($userId,$profileId,50);

        $threadId=$this->getOrCreateThread($userId,$profileId); $usageId=$this->createUsage($threadId); $started=microtime(true); $userMessageId=0;
        try {
            $userMessageId=$this->recordMessage($userId,$profileId,'user',$message);
            $result=$this->provider->chat($context,$history,$message);
            $latency=(int)round((microtime(true)-$started)*1000);
            $aiMessageId=$this->recordMessage($userId,$profileId,'ai',$result['reply'],'text',['provider'=>'openai','model'=>$result['model'],'usage_id'=>$usageId]);

            $ledgerId=null;
            if ($this->coinsPerMessage>0) {
                $charge=$this->credits->debit($userId,$this->coinsText(),'ai_chat',$usageId,'ASTROPOP AI chat message',['thread_id'=>$threadId,'message_id'=>$aiMessageId]);
                $ledgerId=$charge['ledger_id'];
            }
            $this->completeUsage($usageId,$result,$ledgerId,$latency,$aiMessageId);
            return ['reply'=>$result['reply'],'message_id'=>$aiMessageId,'user_message_id'=>$userMessageId,'usage_id'=>$usageId,'ledger_id'=>$ledgerId,'balance'=>$this->balance($userId),'model'=>$result['model']];
        } catch (Throwable $e) {
            $this->failUsage($usageId,(int)round((microtime(true)-$started)*1000)); throw $e;
        }
    }

    private function assertProfileOwnership(int $userId,int $profileId): void
    {
        if($userId<=0||$profileId<=0) throw new InvalidArgumentException('Invalid astrology profile.');
        $stmt=db()->prepare('SELECT id FROM birth_profiles WHERE id=? AND user_id=? LIMIT 1'); $stmt->bind_param('ii',$profileId,$userId); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc(); $stmt->close();
        if(!$row) throw new RuntimeException('Astrology profile was not found.');
    }

    private function coinsText(): string { return number_format($this->coinsPerMessage,4,'.',''); }

    private function createUsage(int $threadId): int
    {
        $provider='openai'; $coins=$this->coinsText();
        $stmt=db()->prepare("INSERT INTO ai_chat_usage (thread_id,provider,user_coins_charged,status) VALUES (?, ?, ?, 'pending')"); $stmt->bind_param('iss',$threadId,$provider,$coins);
        if(!$stmt->execute()){ $stmt->close(); throw new RuntimeException('AI usage record could not be created.'); }
        $id=(int)db()->insert_id; $stmt->close(); return $id;
    }

    /** @param array<string,mixed> $result */
    private function completeUsage(int $usageId,array $result,?int $ledgerId,int $latencyMs,int $messageId): void
    {
        $responseId=isset($result['raw']['id'])?(string)$result['raw']['id']:null; $input=$result['input_tokens']; $output=$result['output_tokens']; $status='completed';
        $metadata=json_encode(['model'=>(string)$result['model'],'message_id'=>$messageId],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $stmt=db()->prepare('UPDATE ai_chat_usage SET provider_request_id=?,provider_response_id=?,input_tokens=?,output_tokens=?,wallet_ledger_id=?,latency_ms=?,status=?,metadata=? WHERE id=?');
        $stmt->bind_param('ssiiiissi',$responseId,$responseId,$input,$output,$ledgerId,$latencyMs,$status,$metadata,$usageId); $stmt->execute(); $stmt->close();
    }

    private function failUsage(int $usageId,int $latencyMs): void
    {
        $status='failed'; $stmt=db()->prepare('UPDATE ai_chat_usage SET latency_ms=?,status=? WHERE id=?'); $stmt->bind_param('isi',$latencyMs,$status,$usageId); $stmt->execute(); $stmt->close();
    }
}
