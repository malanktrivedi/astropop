<?php
declare(strict_types=1);

/**
 * Classical D16 (Shodasamsa / Shodashamsa / Kalamsa) calculator.
 * Each 30° Rashi is divided into sixteen 1°52′30″ parts.
 * Movable signs count from Aries, fixed signs from Leo, and dual signs from Sagittarius.
 * The four presiding deities repeat four times; the order reverses for even signs.
 */
final class ShodasamsaCalculator
{
    private const SIGNS = ['Aries','Taurus','Gemini','Cancer','Leo','Virgo','Libra','Scorpio','Sagittarius','Capricorn','Aquarius','Pisces'];
    private const PART_SIZE = 30.0 / 16.0;
    private const DEITIES_ODD = ['Brahma','Vishnu','Shiva','Surya','Brahma','Vishnu','Shiva','Surya','Brahma','Vishnu','Shiva','Surya','Brahma','Vishnu','Shiva','Surya'];
    private const DEITIES_EVEN = ['Surya','Shiva','Vishnu','Brahma','Surya','Shiva','Vishnu','Brahma','Surya','Shiva','Vishnu','Brahma','Surya','Shiva','Vishnu','Brahma'];

    public function calculate(array $planets, array $chartData, ?string $d1Lagna): array
    {
        $positions = [];
        $ascendant = is_array($chartData['ascendant'] ?? null) ? $chartData['ascendant'] : [];

        $ascDegree = $this->degreeFromSignLocal($d1Lagna, $ascendant);
        if ($ascDegree === null) {
            $ascDegree = $this->firstNumeric($ascendant, ['global_degree','longitude','sidereal_longitude','absolute_degree']);
        }
        $d16Lagna = $ascDegree !== null ? $this->shodasamsaSign($ascDegree) : null;

        foreach ($planets as $planet) {
            if (!is_array($planet)) continue;
            $name = trim((string) ($planet['name'] ?? ''));
            if ($name === '') continue;

            $d1Sign = $this->normalizeSign((string) ($planet['rashi'] ?? ''));
            $global = null;
            if ($d1Sign !== null && is_numeric($planet['local_degree'] ?? null)) {
                $global = (($this->signNumber($d1Sign) - 1) * 30.0) + (float) $planet['local_degree'];
            }
            if ($global === null && is_numeric($planet['global_degree'] ?? null)) {
                $global = (float) $planet['global_degree'];
            }
            if ($global === null) continue;
            if ($d1Sign === null) $d1Sign = $this->signFromLongitude($global);

            $d16Sign = $this->shodasamsaSign($global);
            $part = $this->partNumber($global);
            $positions[] = [
                'name' => $name,
                'd1_sign' => $d1Sign,
                'd1_degree' => $this->degreeInSign($global),
                'part' => $part,
                'd16_sign' => $d16Sign,
                'd16_degree' => $this->shodasamsaDegree($global),
                'deity' => $this->deity($d1Sign, $part),
                'global_degree' => $global,
            ];
        }

        $houses = [];
        if ($d16Lagna !== null) {
            $lagnaNo = $this->signNumber($d16Lagna);
            foreach ($positions as &$position) {
                $signNo = $this->signNumber((string) $position['d16_sign']);
                if ($signNo < 1) continue;
                $house = (($signNo - $lagnaNo + 12) % 12) + 1;
                $position['house'] = $house;
                $houses[$house] ??= [];
                $houses[$house][] = (string) $position['name'];
            }
            unset($position);
            ksort($houses, SORT_NUMERIC);
        }

        return ['lagna' => $d16Lagna, 'positions' => $positions, 'houses' => $houses];
    }

    private function shodasamsaSign(float $longitude): string
    {
        $degree = fmod($longitude, 360.0);
        if ($degree < 0) $degree += 360.0;
        $d1SignNo = (int) floor($degree / 30.0) + 1;
        $within = $degree - (($d1SignNo - 1) * 30.0);
        $part = min(15, (int) floor(($within + 1e-10) / self::PART_SIZE));
        $start = $this->startSign($d1SignNo);
        return self::SIGNS[($start - 1 + $part) % 12];
    }

    private function startSign(int $signNo): int
    {
        // Movable: Aries; Fixed: Leo; Dual: Sagittarius.
        if (in_array($signNo, [1,4,7,10], true)) return 1;
        if (in_array($signNo, [2,5,8,11], true)) return 5;
        return 9;
    }

    private function partNumber(float $longitude): int
    {
        $degree = fmod($longitude, 30.0);
        if ($degree < 0) $degree += 30.0;
        return min(16, (int) floor(($degree + 1e-10) / self::PART_SIZE) + 1);
    }

    private function shodasamsaDegree(float $longitude): float
    {
        $degree = fmod($longitude, self::PART_SIZE);
        if ($degree < 0) $degree += self::PART_SIZE;
        return $degree * 16.0;
    }

    private function deity(string $d1Sign, int $part): string
    {
        $signNo = $this->signNumber($d1Sign);
        $index = max(0, min(15, $part - 1));
        return ($signNo % 2 === 1 ? self::DEITIES_ODD : self::DEITIES_EVEN)[$index];
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
