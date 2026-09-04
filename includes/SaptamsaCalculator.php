<?php
declare(strict_types=1);

final class SaptamsaCalculator
{
    private const SIGNS = ['Aries','Taurus','Gemini','Cancer','Leo','Virgo','Libra','Scorpio','Sagittarius','Capricorn','Aquarius','Pisces'];
    private const NAMES_ODD = ['Kshara','Kshira','Dadhi','Ghrita','Ikshu-rasa','Madhya','Shuddha-jala'];
    private const NAMES_EVEN = ['Shuddha-jala','Madhya','Ikshu-rasa','Ghrita','Dadhi','Kshira','Kshara'];

    public function calculate(array $planets, array $chartData, ?string $d1Lagna): array
    {
        $positions = [];
        $ascendant = is_array($chartData['ascendant'] ?? null) ? $chartData['ascendant'] : [];
        $ascDegree = $this->degreeFromSignLocal($d1Lagna, $ascendant);
        if ($ascDegree === null) {
            $ascDegree = $this->firstNumeric($ascendant, ['global_degree','longitude','sidereal_longitude','absolute_degree']);
        }
        $d7Lagna = $ascDegree !== null ? $this->saptamsaSign($ascDegree) : null;

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

            $d7Sign = $this->saptamsaSign($global);
            $part = $this->partNumber($global);
            $positions[] = [
                'name' => $name,
                'd1_sign' => $d1Sign,
                'd1_degree' => $this->degreeInSign($global),
                'part' => $part,
                'd7_sign' => $d7Sign,
                'd7_degree' => $this->saptamsaDegree($global),
                'quality' => $this->quality($d1Sign, $part),
                'global_degree' => $global,
            ];
        }

        $houses = [];
        if ($d7Lagna !== null) {
            $lagnaNo = $this->signNumber($d7Lagna);
            foreach ($positions as &$position) {
                $signNo = $this->signNumber((string) $position['d7_sign']);
                if ($signNo < 1) continue;
                $house = (($signNo - $lagnaNo + 12) % 12) + 1;
                $position['house'] = $house;
                $houses[$house] ??= [];
                $houses[$house][] = (string) $position['name'];
            }
            unset($position);
            ksort($houses, SORT_NUMERIC);
        }

        return ['lagna' => $d7Lagna, 'positions' => $positions, 'houses' => $houses];
    }

    private function saptamsaSign(float $longitude): string
    {
        $degree = fmod($longitude, 360.0);
        if ($degree < 0) $degree += 360.0;
        $d1SignNo = (int) floor($degree / 30.0) + 1;
        $within = $degree - (($d1SignNo - 1) * 30.0);
        $part = min(6, (int) floor(($within + 1e-10) / (30.0 / 7.0)));
        $start = ($d1SignNo % 2 === 1) ? $d1SignNo : (($d1SignNo - 1 + 6) % 12) + 1;
        return self::SIGNS[($start - 1 + $part) % 12];
    }

    private function partNumber(float $longitude): int
    {
        $degree = fmod($longitude, 30.0);
        if ($degree < 0) $degree += 30.0;
        return min(7, (int) floor(($degree + 1e-10) / (30.0 / 7.0)) + 1);
    }

    private function quality(string $d1Sign, int $part): string
    {
        $signNo = $this->signNumber($d1Sign);
        $index = max(0, min(6, $part - 1));
        return ($signNo % 2 === 1 ? self::NAMES_ODD : self::NAMES_EVEN)[$index];
    }

    private function saptamsaDegree(float $longitude): float
    {
        $degree = fmod($longitude, 30.0);
        if ($degree < 0) $degree += 30.0;
        return fmod($degree, 30.0 / 7.0) * 7.0;
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
