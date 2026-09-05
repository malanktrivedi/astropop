<?php
declare(strict_types=1);

/**
 * Classical Parashari D45 (Akshavedamsa / Panchachatvarimsamsa) calculator.
 * Each 30° Rashi is divided into 45 equal portions of 0°40′.
 * Movable signs count from Aries, fixed signs from Leo, dual signs from Sagittarius.
 * The deity triad rotates by modality and repeats fifteen times.
 */
final class AkshavedamsaCalculator
{
    private const SIGNS = ['Aries','Taurus','Gemini','Cancer','Leo','Virgo','Libra','Scorpio','Sagittarius','Capricorn','Aquarius','Pisces'];
    private const MODALITY = [
        'Aries' => 'movable', 'Cancer' => 'movable', 'Libra' => 'movable', 'Capricorn' => 'movable',
        'Taurus' => 'fixed', 'Leo' => 'fixed', 'Scorpio' => 'fixed', 'Aquarius' => 'fixed',
        'Gemini' => 'dual', 'Virgo' => 'dual', 'Sagittarius' => 'dual', 'Pisces' => 'dual',
    ];
    private const START = ['movable' => 1, 'fixed' => 5, 'dual' => 9];
    private const DEITIES = [
        'movable' => ['Brahma','Shiva','Vishnu'],
        'fixed' => ['Shiva','Vishnu','Brahma'],
        'dual' => ['Vishnu','Brahma','Shiva'],
    ];

    public function calculate(array $planets, array $chartData, ?string $d1Lagna): array
    {
        $positions = [];
        $ascendant = is_array($chartData['ascendant'] ?? null) ? $chartData['ascendant'] : [];
        $ascDegree = $this->degreeFromSignLocal($d1Lagna, $ascendant);
        if ($ascDegree === null) $ascDegree = $this->firstNumeric($ascendant, ['global_degree','longitude','sidereal_longitude','absolute_degree']);
        $d45Lagna = $ascDegree !== null ? $this->akshavedamsaSign($ascDegree) : null;

        foreach ($planets as $planet) {
            if (!is_array($planet)) continue;
            $name = trim((string) ($planet['name'] ?? ''));
            if ($name === '') continue;
            $d1Sign = $this->normalizeSign((string) ($planet['rashi'] ?? ''));
            $global = null;
            if ($d1Sign !== null && is_numeric($planet['local_degree'] ?? null)) {
                $global = (($this->signNumber($d1Sign) - 1) * 30.0) + (float) $planet['local_degree'];
            }
            if ($global === null && is_numeric($planet['global_degree'] ?? null)) $global = (float) $planet['global_degree'];
            if ($global === null) continue;
            if ($d1Sign === null) $d1Sign = $this->signFromLongitude($global);

            $division = $this->division($global);
            $positions[] = [
                'name' => $name,
                'd1_sign' => $d1Sign,
                'd1_degree' => $this->degreeInSign($global),
                'part' => $division['part'],
                'band' => $division['range'],
                'd45_sign' => $division['sign'],
                'house' => null,
                'deity' => $division['deity'],
                'modality' => $division['modality'],
                'global_degree' => $global,
            ];
        }

        $houses = [];
        if ($d45Lagna !== null) {
            $lagnaNo = $this->signNumber($d45Lagna);
            foreach ($positions as &$position) {
                $signNo = $this->signNumber((string) $position['d45_sign']);
                if ($signNo < 1) continue;
                $house = (($signNo - $lagnaNo + 12) % 12) + 1;
                $position['house'] = $house;
                $houses[$house] ??= [];
                $houses[$house][] = (string) $position['name'];
            }
            unset($position);
            ksort($houses, SORT_NUMERIC);
        }

        return ['lagna' => $d45Lagna, 'positions' => $positions, 'houses' => $houses];
    }

    private function division(float $longitude): array
    {
        $degree = fmod($longitude, 360.0);
        if ($degree < 0) $degree += 360.0;
        $signNo = (int) floor($degree / 30.0) + 1;
        $within = $degree - (($signNo - 1) * 30.0);
        $sign = self::SIGNS[$signNo - 1];
        $modality = self::MODALITY[$sign];
        $part = min(45, (int) floor($within / (2.0 / 3.0)) + 1);
        $start = self::START[$modality];
        $d45SignNo = (($start - 1 + $part - 1) % 12) + 1;
        $deity = self::DEITIES[$modality][($part - 1) % 3];
        $bandStart = ($part - 1) * (2.0 / 3.0);
        $bandEnd = $part * (2.0 / 3.0);
        return [
            'part' => $part,
            'range' => $this->formatRange($bandStart, $bandEnd),
            'sign' => self::SIGNS[$d45SignNo - 1],
            'deity' => $deity,
            'modality' => $modality,
        ];
    }

    private function akshavedamsaSign(float $longitude): string { return $this->division($longitude)['sign']; }

    private function formatRange(float $start, float $end): string
    {
        $startMin = (int) round($start * 60);
        $endMin = (int) round($end * 60);
        return sprintf('%02d°%02d′–%02d°%02d′', intdiv($startMin, 60), $startMin % 60, intdiv($endMin, 60), $endMin % 60);
    }

    private function degreeFromSignLocal(?string $sign, array $object): ?float
    {
        $local = null;
        foreach (['local_degree','degree_in_sign','sign_degree','degree'] as $key) {
            if (isset($object[$key]) && is_numeric($object[$key])) { $local = (float) $object[$key]; break; }
        }
        if ($local === null || $sign === null) return null;
        $number = $this->signNumber($sign);
        return $number ? (($number - 1) * 30.0) + max(0.0, min(29.999999, $local)) : null;
    }

    private function degreeInSign(float $longitude): float
    {
        $degree = fmod($longitude, 30.0);
        if ($degree < 0) $degree += 30.0;
        return $degree;
    }

    private function signFromLongitude(float $longitude): string
    {
        $degree = fmod($longitude, 360.0);
        if ($degree < 0) $degree += 360.0;
        return self::SIGNS[(int) floor($degree / 30.0)];
    }

    private function signNumber(string $sign): int
    {
        $normalized = strtolower(trim($sign));
        foreach (self::SIGNS as $i => $value) if (strtolower($value) === $normalized) return $i + 1;
        return 0;
    }

    private function normalizeSign(string $sign): ?string
    {
        $number = $this->signNumber($sign);
        return $number ? self::SIGNS[$number - 1] : null;
    }

    private function firstNumeric(mixed $value, array $keys): ?float
    {
        if (!is_array($value)) return null;
        foreach ($keys as $key) if (isset($value[$key]) && is_numeric($value[$key])) return (float) $value[$key];
        foreach ($value as $child) { $found = $this->firstNumeric($child, $keys); if ($found !== null) return $found; }
        return null;
    }
}
