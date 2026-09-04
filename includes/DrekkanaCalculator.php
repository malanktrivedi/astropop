<?php
declare(strict_types=1);

/**
 * Classical Parashari D3 (Drekkana) calculator.
 * Each 30° Rashi is divided into three equal portions of 10°.
 * The three parts map to the natal sign itself, the 5th sign, and the 9th sign.
 * The presiding rishis are assigned by the natal sign modality:
 * movable = Narada, fixed = Agastya, dual = Durvasa.
 */
final class DrekkanaCalculator
{
    private const SIGNS = ['Aries','Taurus','Gemini','Cancer','Leo','Virgo','Libra','Scorpio','Sagittarius','Capricorn','Aquarius','Pisces'];
    private const MODALITY = [
        'Aries' => 'movable', 'Cancer' => 'movable', 'Libra' => 'movable', 'Capricorn' => 'movable',
        'Taurus' => 'fixed', 'Leo' => 'fixed', 'Scorpio' => 'fixed', 'Aquarius' => 'fixed',
        'Gemini' => 'dual', 'Virgo' => 'dual', 'Sagittarius' => 'dual', 'Pisces' => 'dual',
    ];
    private const RISHIS = ['movable' => 'Narada', 'fixed' => 'Agastya', 'dual' => 'Durvasa'];

    public function calculate(array $planets, array $chartData, ?string $d1Lagna): array
    {
        $positions = [];
        $ascendant = is_array($chartData['ascendant'] ?? null) ? $chartData['ascendant'] : [];
        $ascDegree = $this->degreeFromSignLocal($d1Lagna, $ascendant);
        if ($ascDegree === null) $ascDegree = $this->firstNumeric($ascendant, ['global_degree','longitude','sidereal_longitude','absolute_degree']);
        $d3Lagna = $ascDegree !== null ? $this->division($ascDegree)['sign'] : null;

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
                'd3_sign' => $division['sign'],
                'house' => null,
                'rishi' => $division['rishi'],
                'modality' => $division['modality'],
                'global_degree' => $global,
            ];
        }

        $houses = [];
        if ($d3Lagna !== null) {
            $lagnaNo = $this->signNumber($d3Lagna);
            foreach ($positions as &$position) {
                $signNo = $this->signNumber((string) $position['d3_sign']);
                if ($signNo < 1) continue;
                $house = (($signNo - $lagnaNo + 12) % 12) + 1;
                $position['house'] = $house;
                $houses[$house] ??= [];
                $houses[$house][] = (string) $position['name'];
            }
            unset($position);
            ksort($houses, SORT_NUMERIC);
        }

        return ['lagna' => $d3Lagna, 'positions' => $positions, 'houses' => $houses];
    }

    private function division(float $longitude): array
    {
        $degree = fmod($longitude, 360.0);
        if ($degree < 0) $degree += 360.0;
        $signNo = (int) floor($degree / 30.0) + 1;
        $within = $degree - (($signNo - 1) * 30.0);
        $sign = self::SIGNS[$signNo - 1];
        $modality = self::MODALITY[$sign];
        $part = min(3, (int) floor($within / 10.0) + 1);
        $offset = [1 => 0, 2 => 4, 3 => 8][$part];
        $d3SignNo = (($signNo - 1 + $offset) % 12) + 1;
        return [
            'part' => $part,
            'range' => $this->formatRange(($part - 1) * 10.0, $part * 10.0),
            'sign' => self::SIGNS[$d3SignNo - 1],
            'rishi' => self::RISHIS[$modality],
            'modality' => $modality,
        ];
    }

    private function formatRange(float $start, float $end): string
    {
        return sprintf('%02d°%02d′–%02d°%02d′', (int) $start, 0, (int) $end, 0);
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
