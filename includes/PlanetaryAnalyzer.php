<?php
declare(strict_types=1);

/**
 * Deterministic, source-transparent planetary analysis for the D1 chart.
 * This class reports chart facts; it deliberately does not create a composite
 * "strength score" or make predictive claims.
 */
final class PlanetaryAnalyzer
{
    private const SIGNS = ['Aries','Taurus','Gemini','Cancer','Leo','Virgo','Libra','Scorpio','Sagittarius','Capricorn','Aquarius','Pisces'];
    private const SIGN_LORDS = [
        'Aries' => 'Mars', 'Taurus' => 'Venus', 'Gemini' => 'Mercury', 'Cancer' => 'Moon',
        'Leo' => 'Sun', 'Virgo' => 'Mercury', 'Libra' => 'Venus', 'Scorpio' => 'Mars',
        'Sagittarius' => 'Jupiter', 'Capricorn' => 'Saturn', 'Aquarius' => 'Saturn', 'Pisces' => 'Jupiter',
    ];
    private const OWN_SIGNS = [
        'Sun' => ['Leo'], 'Moon' => ['Cancer'], 'Mars' => ['Aries','Scorpio'],
        'Mercury' => ['Gemini','Virgo'], 'Jupiter' => ['Sagittarius','Pisces'],
        'Venus' => ['Taurus','Libra'], 'Saturn' => ['Capricorn','Aquarius'],
    ];
    private const EXALTATION = [
        'Sun' => 'Aries', 'Moon' => 'Taurus', 'Mars' => 'Capricorn', 'Mercury' => 'Virgo',
        'Jupiter' => 'Cancer', 'Venus' => 'Pisces', 'Saturn' => 'Libra',
    ];
    private const DEBILITATION = [
        'Sun' => 'Libra', 'Moon' => 'Scorpio', 'Mars' => 'Cancer', 'Mercury' => 'Pisces',
        'Jupiter' => 'Capricorn', 'Venus' => 'Virgo', 'Saturn' => 'Aries',
    ];

    /** @return array<string,mixed> */
    public function analyze(array $planets, ?string $lagna, ?array $d9 = null): array
    {
        $lagna = $this->normalizeSign($lagna ?? '');
        $rows = [];
        $byHouse = [];
        foreach ($planets as $planet) {
            if (!is_array($planet)) continue;
            $name = $this->planetName($planet);
            if ($name === '') continue;
            $sign = $this->normalizeSign((string) ($planet['rashi'] ?? ''));
            $house = is_numeric($planet['house'] ?? null) ? (int) $planet['house'] : null;
            $d9Row = $this->findD9($d9, $name);
            $rows[] = [
                'name' => $name,
                'sign' => $sign,
                'degree' => is_numeric($planet['local_degree'] ?? null) ? (float) $planet['local_degree'] : null,
                'house' => $house,
                'sign_lord' => $sign !== null ? (self::SIGN_LORDS[$sign] ?? null) : null,
                'house_lordships' => $this->lordshipsForPlanet($name, $lagna),
                'dignity' => $this->dignity($name, $sign),
                'nakshatra' => $planet['nakshatra'] ?? null,
                'pada' => $planet['nakshatra_pada'] ?? null,
                'retrograde' => $planet['retrograde'] ?? null,
                'combust' => $planet['combust'] ?? null,
                'lord_status' => $planet['lord_status'] ?? null,
                'd9_sign' => $d9Row['d9_sign'] ?? null,
                'd9_house' => isset($d9Row['house']) ? (int) $d9Row['house'] : null,
                'vargottama' => (bool) ($d9Row['vargottama'] ?? false),
            ];
            if ($house !== null) $byHouse[$house][] = $name;
        }

        $conjunctions = [];
        foreach ($byHouse as $house => $names) {
            if (count($names) > 1) $conjunctions[] = ['house' => (int) $house, 'planets' => $names];
        }

        return [
            'lagna' => $lagna,
            'rows' => $rows,
            'house_lords' => $this->houseLords($lagna),
            'conjunctions' => $conjunctions,
        ];
    }

    private function dignity(string $planet, ?string $sign): string
    {
        if ($sign === null) return 'Unknown';
        if (isset(self::EXALTATION[$planet]) && self::EXALTATION[$planet] === $sign) return 'Exalted';
        if (isset(self::DEBILITATION[$planet]) && self::DEBILITATION[$planet] === $sign) return 'Debilitated';
        if (in_array($sign, self::OWN_SIGNS[$planet] ?? [], true)) return 'Own sign';
        if (in_array($planet, ['Rahu','Ketu'], true)) return 'No classical dignity table applied';
        return 'Other sign';
    }

    /** @return list<int> */
    private function lordshipsForPlanet(string $planet, ?string $lagna): array
    {
        if ($lagna === null || !isset(self::OWN_SIGNS[$planet])) return [];
        $lagnaNo = $this->signNumber($lagna);
        $houses = [];
        foreach (self::OWN_SIGNS[$planet] as $sign) {
            $signNo = $this->signNumber($sign);
            if ($signNo > 0 && $lagnaNo > 0) $houses[] = (($signNo - $lagnaNo + 12) % 12) + 1;
        }
        sort($houses);
        return $houses;
    }

    /** @return array<int,array{house:int,sign:string,lord:string}> */
    private function houseLords(?string $lagna): array
    {
        if ($lagna === null) return [];
        $start = $this->signNumber($lagna);
        if ($start < 1) return [];
        $out = [];
        for ($house = 1; $house <= 12; $house++) {
            $sign = self::SIGNS[($start - 1 + $house - 1) % 12];
            $out[] = ['house' => $house, 'sign' => $sign, 'lord' => self::SIGN_LORDS[$sign]];
        }
        return $out;
    }

    private function findD9(?array $d9, string $name): ?array
    {
        if (!is_array($d9)) return null;
        foreach (($d9['positions'] ?? []) as $row) {
            if (is_array($row) && strcasecmp((string) ($row['name'] ?? ''), $name) === 0) return $row;
        }
        return null;
    }

    private function planetName(array $planet): string
    {
        $name = trim((string) ($planet['name'] ?? $planet['full_name'] ?? ''));
        if ($name === '') return '';
        $aliases = ['sun'=>'Sun','moon'=>'Moon','mars'=>'Mars','mercury'=>'Mercury','jupiter'=>'Jupiter','venus'=>'Venus','saturn'=>'Saturn','rahu'=>'Rahu','ketu'=>'Ketu'];
        return $aliases[strtolower($name)] ?? $name;
    }

    private function normalizeSign(string $sign): ?string
    {
        foreach (self::SIGNS as $value) if (strcasecmp(trim($value), trim($sign)) === 0) return $value;
        return null;
    }

    private function signNumber(string $sign): int
    {
        foreach (self::SIGNS as $i => $value) if (strcasecmp($value, trim($sign)) === 0) return $i + 1;
        return 0;
    }
}
