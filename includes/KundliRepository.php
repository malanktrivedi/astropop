<?php
declare(strict_types=1);

final class KundliRepository
{
    public function findLatest(int $userId, int $profileId): ?array
    {
        $stmt = db()->prepare('SELECT * FROM kundli_calculations WHERE user_id=? AND birth_profile_id=? ORDER BY id DESC LIMIT 1');
        $stmt->bind_param('ii', $userId, $profileId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function findByHash(int $userId, int $profileId, string $hash): ?array
    {
        $stmt = db()->prepare('SELECT * FROM kundli_calculations WHERE user_id=? AND birth_profile_id=? AND calculation_hash=? LIMIT 1');
        $stmt->bind_param('iis', $userId, $profileId, $hash);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function decode(array $calculation): array
    {
        $decode = static function ($value, $fallback) {
            $decoded = json_decode((string) ($value ?? ''), true);
            return is_array($decoded) ? $decoded : $fallback;
        };
        return [
            'planetary' => $decode($calculation['planetary_data'] ?? null, []),
            'houses' => $decode($calculation['house_data'] ?? null, []),
            'dasha' => json_decode((string) ($calculation['dasha_data'] ?? 'null'), true),
            'chart' => $decode($calculation['chart_data'] ?? null, []),
        ];
    }

    public static function hashForProfile(array $profile, string $engineVersion, string $apiVersion): string
    {
        return hash('sha256', implode('|', [
            (int) ($profile['id'] ?? 0),
            (new DateTime((string) $profile['date_of_birth']))->format('d/m/Y'),
            substr((string) ($profile['time_of_birth'] ?? ''), 0, 5),
            (float) ($profile['latitude'] ?? 0),
            (float) ($profile['longitude'] ?? 0),
            (float) ($profile['timezone'] ?? 0),
            $engineVersion,
            $apiVersion,
        ]));
    }
}
