<?php
declare(strict_types=1);

final class NavamsaCalculator
{
    private const SIGNS = ['Aries','Taurus','Gemini','Cancer','Leo','Virgo','Libra','Scorpio','Sagittarius','Capricorn','Aquarius','Pisces'];

    /**
     * Calculate D9/Navamsa positions from sidereal absolute zodiac degrees.
     * Each navamsa is 3°20' (10/3 degrees). The resulting sign is the
     * classical Parashari mapping: movable starts from itself, fixed from
     * the 9th sign, dual from the 5th sign.
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
        $ascGlobal = $this->firstNumeric($ascendant, ['global_degree','longitude','sidereal_longitude','degree']);
        if ($ascGlobal === null) {
            $ascLocal = $this->firstNumeric($ascendant, ['local_degree','degree_in_sign','sign_degree']);
            $ascGlobal = $this->absoluteFromSignAndLocal($d1Lagna, $ascLocal);
        }
        if ($ascGlobal !== null) {
            $d9Lagna = $this->navamsaSignFromLongitude($ascGlobal);
        }

        foreach ($planets as $planet) {
            if (!is_array($planet)) continue;
            $name = (string) ($planet['name'] ?? '');
            if ($name === '') continue;
            $global = is_numeric($planet['global_degree'] ?? null) ? (float) $planet['global_degree'] : null;
            if ($global === null) {
                $local = is_numeric($planet['local_degree'] ?? null) ? (float) $planet['local_degree'] : null;
                $global = $this->absoluteFromSignAndLocal(isset($planet['rashi']) ? (string) $planet['rashi'] : null, $local);
            }
            if ($global === null) continue;

            $d9Sign = $this->navamsaSignFromLongitude($global);
            $d1Sign = $this->normalizeSign((string) ($planet['rashi'] ?? ''));
            $positions[] = [
                'name' => $name,
                'd1_sign' => $d1Sign,
                'd1_degree' => $this->degreeInSign($global),
                'd9_sign' => $d9Sign,
                'd9_degree' => $this->navamsaDegree($global),
                'vargottama' => $d1Sign !== null && $d1Sign === $d9Sign,
                'global_degree' => $global,
            ];
        }

        $houses = [];
        if ($d9Lagna !== null) {
            $lagnaNo = $this->signNumber($d9Lagna);
            foreach ($positions as $position) {
                $signNo = $this->signNumber((string) $position['d9_sign']);
                $house = (($signNo - $lagnaNo + 12) % 12) + 1;
                $houses[$house] ??= [];
                $houses[$house][] = (string) $position['name'];
                $position['house'] = $house;
            }
            $byName = [];
            foreach ($positions as $position) $byName[$position['name']] = $position;
            $positions = array_values($byName);
            ksort($houses, SORT_NUMERIC);
        }

        return [
            'lagna' => $d9Lagna,
            'positions' => $positions,
            'houses' => $houses,
        ];
    }

    private function navamsaSignFromLongitude(float $longitude): string
    {
        $degree = fmod($longitude, 360.0);
        if ($degree < 0) $degree += 360.0;
        $part = (int) floor(($degree + 1e-10) / (10.0 / 3.0));
        $signNo = $part % 12;
        return self::SIGNS[$signNo];
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

    private function firstNumeric(array $data, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_numeric($data[$key])) return (float) $data[$key];
        }
        return null;
    }
}
