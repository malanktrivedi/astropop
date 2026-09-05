<?php
declare(strict_types=1);

/**
 * Application-side coin ledger for AI and human chat.
 *
 * Coins are ASTROPOP's internal billing unit. Payment gateways only fund the
 * wallet; chat usage debits are recorded here so the balance is auditable.
 */
final class ChatCreditService
{
    public function ensureWallet(int $userId): int
    {
        $stmt = db()->prepare("INSERT IGNORE INTO wallet_accounts (user_id,wallet_type,balance,status) VALUES (?, 'ASTRO_COIN', 0, 'active')");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();

        $stmt = db()->prepare("SELECT id FROM wallet_accounts WHERE user_id=? AND wallet_type='ASTRO_COIN' LIMIT 1");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            throw new RuntimeException('ASTRO_COIN wallet could not be created.');
        }
        return (int) $row['id'];
    }

    public function balance(int $userId): string
    {
        $walletId = $this->ensureWallet($userId);
        $stmt = db()->prepare('SELECT balance FROM wallet_accounts WHERE id=? LIMIT 1');
        $stmt->bind_param('i', $walletId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (string) ($row['balance'] ?? '0.0000');
    }

    /** @return array{ledger_id:int,balance:string} */
    public function credit(int $userId, string $amount, string $referenceType, ?int $referenceId, string $description, array $metadata = []): array
    {
        if ((float) $amount <= 0) throw new InvalidArgumentException('Credit amount must be greater than zero.');
        return $this->mutate($userId, $amount, 'credit', $referenceType, $referenceId, $description, $metadata);
    }

    /** @return array{ledger_id:int,balance:string} */
    public function debit(int $userId, string $amount, string $referenceType, ?int $referenceId, string $description, array $metadata = []): array
    {
        if ((float) $amount <= 0) throw new InvalidArgumentException('Debit amount must be greater than zero.');
        return $this->mutate($userId, '-' . ltrim($amount, '+-'), 'debit', $referenceType, $referenceId, $description, $metadata);
    }

    /** @return array{ledger_id:int,balance:string} */
    private function mutate(int $userId, string $delta, string $entryType, string $referenceType, ?int $referenceId, string $description, array $metadata): array
    {
        $db = db();
        $walletId = $this->ensureWallet($userId);
        $db->begin_transaction();
        try {
            $stmt = $db->prepare('SELECT balance,status FROM wallet_accounts WHERE id=? FOR UPDATE');
            $stmt->bind_param('i', $walletId);
            $stmt->execute();
            $wallet = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$wallet || $wallet['status'] !== 'active') throw new RuntimeException('ASTRO_COIN wallet is not active.');

            $current = (float) $wallet['balance'];
            $change = (float) $delta;
            $newBalance = $current + $change;
            if ($newBalance < -0.0000001) throw new RuntimeException('Insufficient ASTRO_COIN balance.');
            $newBalanceText = number_format(max(0, $newBalance), 4, '.', '');

            $stmt = $db->prepare('UPDATE wallet_accounts SET balance=? WHERE id=?');
            $stmt->bind_param('si', $newBalanceText, $walletId);
            if (!$stmt->execute()) throw new RuntimeException('Wallet balance update failed.');
            $stmt->close();

            $metaJson = $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null;
            $amountText = number_format(abs($change), 4, '.', '');
            $stmt = $db->prepare('INSERT INTO wallet_ledger (wallet_id,user_id,entry_type,amount,balance_after,reference_type,reference_id,description,metadata) VALUES (?,?,?,?,?,?,?,?,?)');
            $stmt->bind_param('iissssiss', $walletId, $userId, $entryType, $amountText, $newBalanceText, $referenceType, $referenceId, $description, $metaJson);
            if (!$stmt->execute()) throw new RuntimeException('Wallet ledger entry failed.');
            $ledgerId = (int) $db->insert_id;
            $stmt->close();
            $db->commit();
            return ['ledger_id'=>$ledgerId, 'balance'=>$newBalanceText];
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }
}
