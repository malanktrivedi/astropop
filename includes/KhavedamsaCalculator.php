<?php
declare(strict_types=1);

/**
 * Classical Parashari D40 (Khavedamsa / Chatvarimsamsa) calculator.
 * Each 30° Rashi is divided into forty equal portions of 0°45′.
 * Odd signs count successively from Aries; even signs count successively from Libra.
 * The twelve presiding deities repeat in the same order for all signs; they do not reverse.
 */
final class KhavedamsaCalculator
{
    private const SIGNS = ['Aries','Taurus','Gemini','Cancer','Leo','Virgo','Libra','Scorpio','Sagittarius','Capricorn','Aquarius','Pisces'];
    private const DEITIES = ['Vishnu','Chandra','Marichi','Tvashta','Dhata','Shiva','Ravi','Yama','Yaksha','Gandharva','Kala','Varuna'];
    private const PART_SIZE = 0.75;

    public function calculate(array $planets, array $chartData, ?string $d1Lagna): array
    {
        $positions = [];
        $ascendant = is_array($chartData['ascendant'] ?? null) ? $chartData['ascendant'] : [];
        $ascDegree = $this->degreeFromSignLocal($d1Lagna, $ascendant);
        if ($ascDegree === null) $ascDegree = $this->firstNumeric($ascendant, ['global_degree','longitude','sidereal_longitude','absolute_degree']);
        $d40Lagna = $ascDegree !== null ? $this->khavedamsaSign($ascDegree) : null;

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

            $part = $this->part($global);
            $d40Sign = $this->divisionSign($global, $part['part']);
            $positions[] = [
                'name' => $name,
                'd1_sign' => $d1Sign,
                'd1_degree' => $this->degreeInSign($global),
                'part' => $part['part'],
                'band' => $part['range'],
                'd40_sign' => $d40Sign,
                'deity' => self::DEITIES[($part['part'] - 1) % 12],
                'global_degree' => $global,
            ];
        }

        $houses = [];
        if ($d40Lagna !== null) {
            $lagnaNo = $this->signNumber($d40Lagna);
            foreach ($positions as &$position) {
                $signNo = $this->signNumber((string) $position['d40_sign']);
                if ($signNo < 1) continue;
                $house = (($signNo - $lagnaNo + 12) % 12) + 1;
                $position['house'] = $house;
                $houses[$house] ??= [];
                $houses[$house][] = (string) $position['name'];
            }
            unset($position);
            ksort($houses, SORT_NUMERIC);
        }

        return ['lagna' => $d40Lagna, 'positions' => $positions, 'houses' => $houses];
    }

    private function part(float $longitude): array
    {
        $degree = fmod($longitude, 360.0);
        if ($degree < 0) $degree += 360.0;
        $within = $degree - (floor($degree / 30.0) * 30.0);
        $part = min(40, (int) floor($within / self::PART_SIZE) + 1);
        $start = ($part - 1) * self::PART_SIZE;
        $end = $part * self::PART_SIZE;
        return ['part' => $part, 'range' => $this->formatRange($start, $end)];
    }

    private function divisionSign(float $longitude, int $part): string
    {
        $degree = fmod($longitude, 360.0);
        if ($degree < 0) $degree += 360.0;
        $signNo = (int) floor($degree / 30.0) + 1;
        $startSign = ($signNo % 2 === 1) ? 1 : 7;
        return self::SIGNS[($startSign - 1 + $part - 1) % 12];
    }

    private function khavedamsaSign(float $longitude): string
    {
        $part = $this->part($longitude);
        return $this->divisionSign($longitude, $part['part']);
    }

    private function formatRange(float $start, float $end): string
    {
        $sDeg = (int) floor($start);
        $sMin = (int) round(($start - $sDeg) * 60);
        $eDeg = (int) floor($end);
        $eMin = (int) round(($end - $eDeg) * 60);
        if ($sMin >= 60) { $sDeg++; $sMin = 0; }
        if ($eMin >= 60) { $eDeg++; $eMin = 0; }
        return sprintf('%02d°%02d′–%02d°%02d′', $sDeg, $sMin, $eDeg, $eMin);
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
