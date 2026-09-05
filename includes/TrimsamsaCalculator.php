<?php
declare(strict_types=1);

/**
 * Classical Parashari D30 (Trimsamsa / Trimshamsha) calculator.
 * Each 30° Rashi is divided into five unequal portions: 5°, 5°, 8°, 7°, 5°.
 * Odd signs: Mars, Saturn, Jupiter, Mercury, Venus.
 * Even signs: Venus, Mercury, Jupiter, Saturn, Mars.
 * Each lord maps to its classical same-parity sign: Aries/Aquarius/Sagittarius/
 * Gemini/Libra for odd signs and Taurus/Virgo/Pisces/Capricorn/Scorpio for even signs.
 */
final class TrimsamsaCalculator
{
    private const SIGNS = ['Aries','Taurus','Gemini','Cancer','Leo','Virgo','Libra','Scorpio','Sagittarius','Capricorn','Aquarius','Pisces'];
    private const ODD = [
        ['end' => 5.0, 'lord' => 'Mars',    'sign' => 'Aries',     'deity' => 'Agni'],
        ['end' => 10.0,'lord' => 'Saturn',  'sign' => 'Aquarius',  'deity' => 'Vayu'],
        ['end' => 18.0,'lord' => 'Jupiter', 'sign' => 'Sagittarius','deity' => 'Indra'],
        ['end' => 25.0,'lord' => 'Mercury', 'sign' => 'Gemini',    'deity' => 'Kubera'],
        ['end' => 30.0,'lord' => 'Venus',   'sign' => 'Libra',     'deity' => 'Varuna'],
    ];
    private const EVEN = [
        ['end' => 5.0, 'lord' => 'Venus',   'sign' => 'Taurus',    'deity' => 'Varuna'],
        ['end' => 12.0,'lord' => 'Mercury', 'sign' => 'Virgo',     'deity' => 'Kubera'],
        ['end' => 20.0,'lord' => 'Jupiter', 'sign' => 'Pisces',    'deity' => 'Indra'],
        ['end' => 25.0,'lord' => 'Saturn',  'sign' => 'Capricorn', 'deity' => 'Vayu'],
        ['end' => 30.0,'lord' => 'Mars',    'sign' => 'Scorpio',   'deity' => 'Agni'],
    ];

    public function calculate(array $planets, array $chartData, ?string $d1Lagna): array
    {
        $positions = [];
        $ascendant = is_array($chartData['ascendant'] ?? null) ? $chartData['ascendant'] : [];
        $ascDegree = $this->degreeFromSignLocal($d1Lagna, $ascendant);
        if ($ascDegree === null) $ascDegree = $this->firstNumeric($ascendant, ['global_degree','longitude','sidereal_longitude','absolute_degree']);
        $d30Lagna = $ascDegree !== null ? $this->trimsamsaSign($ascDegree) : null;

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
            $band = $this->band($global);
            $positions[] = [
                'name' => $name,
                'd1_sign' => $d1Sign,
                'd1_degree' => $this->degreeInSign($global),
                'part' => $band['part'],
                'band' => $band['range'],
                'lord' => $band['lord'],
                'd30_sign' => $band['sign'],
                'deity' => $band['deity'],
                'global_degree' => $global,
            ];
        }

        $houses = [];
        if ($d30Lagna !== null) {
            $lagnaNo = $this->signNumber($d30Lagna);
            foreach ($positions as &$position) {
                $signNo = $this->signNumber((string) $position['d30_sign']);
                if ($signNo < 1) continue;
                $house = (($signNo - $lagnaNo + 12) % 12) + 1;
                $position['house'] = $house;
                $houses[$house] ??= [];
                $houses[$house][] = (string) $position['name'];
            }
            unset($position);
            ksort($houses, SORT_NUMERIC);
        }
        return ['lagna' => $d30Lagna, 'positions' => $positions, 'houses' => $houses];
    }

    private function band(float $longitude): array
    {
        $degree = fmod($longitude, 360.0);
        if ($degree < 0) $degree += 360.0;
        $signNo = (int) floor($degree / 30.0) + 1;
        $within = $degree - (($signNo - 1) * 30.0);
        $bands = ($signNo % 2 === 1) ? self::ODD : self::EVEN;
        $start = 0.0;
        foreach ($bands as $index => $band) {
            if ($within < $band['end'] || $index === 4) {
                $end = (float) $band['end'];
                return [
                    'part' => $index + 1,
                    'range' => $this->formatRange($start, $end),
                    'lord' => $band['lord'],
                    'sign' => $band['sign'],
                    'deity' => $band['deity'],
                ];
            }
            $start = (float) $band['end'];
        }
        return ['part'=>5,'range'=>'25°00′–30°00′','lord'=>'Venus','sign'=>'Libra','deity'=>'Varuna'];
    }

    private function trimsamsaSign(float $longitude): string
    {
        return $this->band($longitude)['sign'];
    }

    private function formatRange(float $start, float $end): string
    {
        return sprintf('%02d°00′–%02d°00′', (int)$start, (int)$end);
    }

    private function degreeFromSignLocal(?string $sign, array $object): ?float
    {
        $local = null;
        foreach (['local_degree','degree_in_sign','sign_degree','degree'] as $key) {
            if (isset($object[$key]) && is_numeric($object[$key])) { $local = (float)$object[$key]; break; }
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
        foreach ($keys as $key) if (isset($value[$key]) && is_numeric($value[$key])) return (float)$value[$key];
        foreach ($value as $child) { $found = $this->firstNumeric($child, $keys); if ($found !== null) return $found; }
        return null;
    }
}
