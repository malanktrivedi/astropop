<?php
declare(strict_types=1);

/**
 * Classical Parashari D5 (Panchamsa) calculator.
 * Each 30° Rashi is divided into five equal portions of 6°.
 * Odd signs map the five portions to Aries, Aquarius, Sagittarius, Gemini, Libra.
 * Even signs map the five portions to Taurus, Virgo, Pisces, Capricorn, Scorpio.
 */
final class PanchamsaCalculator
{
    private const SIGNS = ['Aries','Taurus','Gemini','Cancer','Leo','Virgo','Libra','Scorpio','Sagittarius','Capricorn','Aquarius','Pisces'];
    private const ODD_MAP = [1, 11, 9, 3, 7];
    private const EVEN_MAP = [2, 6, 12, 10, 8];

    public function calculate(array $planets, array $chartData, ?string $d1Lagna): array
    {
        $positions = [];
        $ascendant = is_array($chartData['ascendant'] ?? null) ? $chartData['ascendant'] : [];
        $ascDegree = $this->degreeFromSignLocal($d1Lagna, $ascendant);
        if ($ascDegree === null) $ascDegree = $this->firstNumeric($ascendant, ['global_degree','longitude','sidereal_longitude','absolute_degree']);
        $d5Lagna = $ascDegree !== null ? $this->division($ascDegree)['sign'] : null;

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
                'd5_sign' => $division['sign'],
                'house' => null,
                'global_degree' => $global,
            ];
        }

        $houses = [];
        if ($d5Lagna !== null) {
            $lagnaNo = $this->signNumber($d5Lagna);
            foreach ($positions as &$position) {
                $signNo = $this->signNumber((string) $position['d5_sign']);
                if ($signNo < 1) continue;
                $house = (($signNo - $lagnaNo + 12) % 12) + 1;
                $position['house'] = $house;
                $houses[$house] ??= [];
                $houses[$house][] = (string) $position['name'];
            }
            unset($position);
            ksort($houses, SORT_NUMERIC);
        }

        return ['lagna' => $d5Lagna, 'positions' => $positions, 'houses' => $houses];
    }

    private function division(float $longitude): array
    {
        $degree = fmod($longitude, 360.0);
        if ($degree < 0) $degree += 360.0;
        $signNo = (int) floor($degree / 30.0) + 1;
        $within = $degree - (($signNo - 1) * 30.0);
        $sign = self::SIGNS[$signNo - 1];
        $part = min(5, (int) floor($within / 6.0) + 1);
        $map = ($signNo % 2 === 1) ? self::ODD_MAP : self::EVEN_MAP;
        return [
            'part' => $part,
            'range' => $this->formatRange(($part - 1) * 6.0, $part * 6.0),
            'sign' => self::SIGNS[$map[$part - 1] - 1],
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
