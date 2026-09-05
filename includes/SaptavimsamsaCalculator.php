<?php
declare(strict_types=1);

/**
 * Classical Parashari D27 (Saptavimsamsa / Bhamsa / Nakshatramsa) calculator.
 * Each 30° Rashi is divided into twenty-seven 1°06′40″ parts.
 * Parashari grouping: Aries/Leo/Sagittarius start Aries; Taurus/Virgo/Capricorn
 * start Cancer; Gemini/Libra/Aquarius start Libra; Cancer/Scorpio/Pisces start Capricorn.
 * The 27 presiding deities follow the classical nakshatra-deity sequence;
 * even signs use the reverse order.
 */
final class SaptavimsamsaCalculator
{
    private const SIGNS = ['Aries','Taurus','Gemini','Cancer','Leo','Virgo','Libra','Scorpio','Sagittarius','Capricorn','Aquarius','Pisces'];
    private const PART_SIZE = 30.0 / 27.0;
    private const DEITIES = [
        'Aswini Kumar','Yama','Agni','Brahma','Chandra','Rudra','Aditi','Brihaspati','Sarpa',
        'Pitri','Bhaga','Aryama','Savitur','Tvashta','Vayu','Indra-Agni','Mitra','Indra',
        'Nirriti','Jala','Viswadeva','Vishnu','Vasu','Varuna','Ajaikapada','Ahirbudhnya','Pushan'
    ];

    public function calculate(array $planets, array $chartData, ?string $d1Lagna): array
    {
        $positions = [];
        $ascendant = is_array($chartData['ascendant'] ?? null) ? $chartData['ascendant'] : [];
        $ascDegree = $this->degreeFromSignLocal($d1Lagna, $ascendant);
        if ($ascDegree === null) $ascDegree = $this->firstNumeric($ascendant, ['global_degree','longitude','sidereal_longitude','absolute_degree']);
        $d27Lagna = $ascDegree !== null ? $this->saptavimsamsaSign($ascDegree) : null;

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
            $part = $this->partNumber($global);
            $d27Sign = $this->saptavimsamsaSign($global);
            $positions[] = [
                'name' => $name,
                'd1_sign' => $d1Sign,
                'd1_degree' => $this->degreeInSign($global),
                'part' => $part,
                'd27_sign' => $d27Sign,
                'd27_degree' => $this->saptavimsamsaDegree($global),
                'deity' => $this->deity($this->signNumber($d1Sign), $part),
                'global_degree' => $global,
            ];
        }

        $houses = [];
        if ($d27Lagna !== null) {
            $lagnaNo = $this->signNumber($d27Lagna);
            foreach ($positions as &$position) {
                $signNo = $this->signNumber((string) $position['d27_sign']);
                if ($signNo < 1) continue;
                $house = (($signNo - $lagnaNo + 12) % 12) + 1;
                $position['house'] = $house;
                $houses[$house] ??= [];
                $houses[$house][] = (string) $position['name'];
            }
            unset($position);
            ksort($houses, SORT_NUMERIC);
        }
        return ['lagna' => $d27Lagna, 'positions' => $positions, 'houses' => $houses];
    }

    private function saptavimsamsaSign(float $longitude): string
    {
        $degree = fmod($longitude, 360.0);
        if ($degree < 0) $degree += 360.0;
        $signNo = (int) floor($degree / 30.0) + 1;
        $within = $degree - (($signNo - 1) * 30.0);
        $part = min(26, (int) floor(($within + 1e-10) / self::PART_SIZE));
        $start = $this->startSign($signNo);
        return self::SIGNS[($start - 1 + $part) % 12];
    }

    private function startSign(int $signNo): int
    {
        if (in_array($signNo, [1,5,9], true)) return 1;      // Aries, Leo, Sagittarius
        if (in_array($signNo, [2,6,10], true)) return 4;     // Taurus, Virgo, Capricorn
        if (in_array($signNo, [3,7,11], true)) return 7;     // Gemini, Libra, Aquarius
        return 10;                                            // Cancer, Scorpio, Pisces
    }

    private function partNumber(float $longitude): int
    {
        $degree = fmod($longitude, 30.0);
        if ($degree < 0) $degree += 30.0;
        return min(27, (int) floor(($degree + 1e-10) / self::PART_SIZE) + 1);
    }

    private function saptavimsamsaDegree(float $longitude): float
    {
        $degree = fmod($longitude, self::PART_SIZE);
        if ($degree < 0) $degree += self::PART_SIZE;
        return $degree * 27.0;
    }

    private function deity(int $signNo, int $part): string
    {
        $sequence = ($signNo % 2 === 1) ? self::DEITIES : array_reverse(self::DEITIES);
        return $sequence[$part - 1];
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
