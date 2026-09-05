<?php
declare(strict_types=1);

final class NavamsaCalculator
{
    private const SIGNS = ['Aries','Taurus','Gemini','Cancer','Leo','Virgo','Libra','Scorpio','Sagittarius','Capricorn','Aquarius','Pisces'];

    /**
     * Calculate D9/Navamsa positions from sidereal absolute zodiac degrees.
     * Each navamsa is 3°20'. The cyclic mapping is equivalent to the
     * classical Parashari movable/fixed/dual starting-sign rule.
     *
     * @param array<int|string,array<string,mixed>> $planets
     * @param array<string,mixed> $chartData
     * @return array<string,mixed>
     */
    public function calculate(array $planets, array $chartData, ?string $d1Lagna): array
    {
        $positions = [];
        $d9Lagna = null;

        $ascendant = is_array($chartData['ascendant'] ?? null) ? $chartData['ascendant'] : [];
        $ascGlobal = $this->findFirstNumeric($ascendant, ['global_degree','longitude','sidereal_longitude','absolute_degree']);
        if ($ascGlobal === null) {
            $ascLocal = $this->findFirstNumeric($ascendant, ['local_degree','degree_in_sign','sign_degree','degree']);
            $ascGlobal = $this->absoluteFromSignAndLocal($d1Lagna, $ascLocal);
        }
        if ($ascGlobal !== null) $d9Lagna = $this->navamsaSignFromLongitude($ascGlobal);

        foreach ($planets as $planet) {
            if (!is_array($planet)) continue;
            $name = trim((string) ($planet['name'] ?? ''));
            if ($name === '') continue;

            $global = is_numeric($planet['global_degree'] ?? null) ? (float) $planet['global_degree'] : null;
            if ($global === null) {
                $local = is_numeric($planet['local_degree'] ?? null) ? (float) $planet['local_degree'] : null;
                $global = $this->absoluteFromSignAndLocal(isset($planet['rashi']) ? (string) $planet['rashi'] : null, $local);
            }
            if ($global === null) continue;

            $d9Sign = $this->navamsaSignFromLongitude($global);
            $d1Sign = $this->normalizeSign((string) ($planet['rashi'] ?? ''));
            if ($d1Sign === null) $d1Sign = $this->signFromLongitude($global);

            $positions[] = [
                'name' => $name,
                'd1_sign' => $d1Sign,
                'd1_degree' => $this->degreeInSign($global),
                'd9_sign' => $d9Sign,
                'd9_degree' => $this->navamsaDegree($global),
                'vargottama' => $d1Sign === $d9Sign,
                'global_degree' => $global,
            ];
        }

        $houses = [];
        if ($d9Lagna !== null) {
            $lagnaNo = $this->signNumber($d9Lagna);
            foreach ($positions as &$position) {
                $signNo = $this->signNumber((string) $position['d9_sign']);
                if ($signNo < 1) continue;
                $house = (($signNo - $lagnaNo + 12) % 12) + 1;
                $position['house'] = $house;
                $houses[$house] ??= [];
                $houses[$house][] = (string) $position['name'];
            }
            unset($position);
            ksort($houses, SORT_NUMERIC);
        }

        return ['lagna' => $d9Lagna, 'positions' => $positions, 'houses' => $houses];
    }

    private function navamsaSignFromLongitude(float $longitude): string
    {
        $degree = fmod($longitude, 360.0);
        if ($degree < 0) $degree += 360.0;
        $part = (int) floor(($degree + 1e-10) / (10.0 / 3.0));
        return self::SIGNS[$part % 12];
    }

    private function navamsaDegree(float $longitude): float
    {
        $degree = fmod($longitude, 360.0);
        if ($degree < 0) $degree += 360.0;
        $partSize = 10.0 / 3.0;
        $within = fmod($degree, $partSize);
        if ($within < 0) $within += $partSize;
        return $within * 9.0;
    }

    private function degreeInSign(float $longitude): float
    {
        $degree = fmod($longitude, 30.0);
        if ($degree < 0) $degree += 30.0;
        return $degree;
    }

    private function absoluteFromSignAndLocal(?string $sign, ?float $local): ?float
    {
        if ($sign === null || $local === null) return null;
        $number = $this->signNumber($sign);
        if ($number < 1 || $number > 12) return null;
        return (($number - 1) * 30.0) + max(0.0, min(29.999999, $local));
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
        foreach (self::SIGNS as $i => $value) {
            if (strtolower($value) === $normalized) return $i + 1;
        }
        return 0;
    }

    private function normalizeSign(string $sign): ?string
    {
        $number = $this->signNumber($sign);
        return $number ? self::SIGNS[$number - 1] : null;
    }

    private function findFirstNumeric(mixed $value, array $keys): ?float
    {
        if (!is_array($value)) return null;
        foreach ($keys as $key) {
            if (array_key_exists($key, $value) && is_numeric($value[$key])) return (float) $value[$key];
        }
        foreach ($value as $child) {
            $found = $this->findFirstNumeric($child, $keys);
            if ($found !== null) return $found;
        }
        return null;
    }
}
