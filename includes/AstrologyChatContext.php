<?php
declare(strict_types=1);

require_once __DIR__ . '/KundliRepository.php';
require_once __DIR__ . '/NavamsaCalculator.php';
require_once __DIR__ . '/PlanetaryAnalyzer.php';

/**
 * Builds a bounded astrology context for AI chat from saved data.
 * It never calls VedicAstroAPI.
 */
final class AstrologyChatContext
{
    public function __construct(private KundliRepository $repository = new KundliRepository()) {}

    /** @return array<string,mixed> */
    public function build(int $userId, int $profileId, ?string $topic = null): array
    {
        $calculation = $this->repository->findLatest($userId, $profileId);
        if (!$calculation) {
            throw new RuntimeException('No saved Kundli calculation exists for this profile.');
        }
        $decoded = $this->repository->decode($calculation);
        $planetary = $decoded['planetary'];
        $chart = $decoded['chart'];
        $lagna = (string) ($calculation['lagna'] ?? '');
        $d9 = (new NavamsaCalculator())->calculate($planetary, $chart, $lagna);

        $context = [
            'source' => 'ASTROPOP_CANONICAL_DB',
            'calculation_id' => (int) $calculation['id'],
            'profile_id' => $profileId,
            'calculated_at' => $calculation['calculated_at'] ?? null,
            'lagna' => $calculation['lagna'] ?? null,
            'rashi' => $calculation['rashi'] ?? null,
            'nakshatra' => $calculation['nakshatra'] ?? null,
            'd9_lagna' => $d9['lagna'] ?? null,
            'topic' => $topic,
            'planetary_data' => $planetary,
            'dasha_data' => $decoded['dasha'],
        ];

        if ($topic !== null && $topic !== '') {
            $analysis = (new PlanetaryAnalyzer())->analyze($planetary, $lagna, $d9);
            $context['topic_analysis'] = $this->topicSlice($analysis, strtolower(trim($topic)));
        }
        return $context;
    }

    /** @param array<string,mixed> $analysis */
    private function topicSlice(array $analysis, string $topic): array
    {
        $keywords = match (true) {
            str_contains($topic, 'career') || str_contains($topic, 'job') || str_contains($topic, 'business') => ['10','6','2'],
            str_contains($topic, 'marriage') || str_contains($topic, 'love') || str_contains($topic, 'relationship') => ['7','5','2'],
            str_contains($topic, 'money') || str_contains($topic, 'finance') || str_contains($topic, 'wealth') => ['2','11','5','9'],
            str_contains($topic, 'education') || str_contains($topic, 'study') => ['4','5','9'],
            default => ['1','5','7','9','10'],
        };
        $rows = [];
        foreach (($analysis['house_lords'] ?? []) as $row) {
            if (in_array((string) ($row['house'] ?? ''), $keywords, true)) $rows[] = $row;
        }
        return ['relevant_house_lords'=>$rows, 'analysis_version'=>'local-1'];
    }
}
