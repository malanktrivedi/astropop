<?php
declare(strict_types=1);

/**
 * Deterministic D1 yoga formation detector.
 * Formation is separated from contextual weakening.
 */
final class YogaDetector
{
    private const SIGNS = [
        'Aries', 'Taurus', 'Gemini', 'Cancer', 'Leo', 'Virgo',
        'Libra', 'Scorpio', 'Sagittarius', 'Capricorn', 'Aquarius', 'Pisces'
    ];

    private const LORDS = [
        'Aries' => 'Mars', 'Taurus' => 'Venus', 'Gemini' => 'Mercury',
        'Cancer' => 'Moon', 'Leo' => 'Sun', 'Virgo' => 'Mercury',
        'Libra' => 'Venus', 'Scorpio' => 'Mars', 'Sagittarius' => 'Jupiter',
        'Capricorn' => 'Saturn', 'Aquarius' => 'Saturn', 'Pisces' => 'Jupiter'
    ];

    private const EXALTATION = [
        'Sun' => 'Aries', 'Moon' => 'Taurus', 'Mars' => 'Capricorn',
        'Mercury' => 'Virgo', 'Jupiter' => 'Cancer', 'Venus' => 'Pisces',
        'Saturn' => 'Libra'
    ];

    private const DEBILITATION = [
        'Sun' => 'Libra', 'Moon' => 'Scorpio', 'Mars' => 'Cancer',
        'Mercury' => 'Pisces', 'Jupiter' => 'Capricorn', 'Venus' => 'Virgo',
        'Saturn' => 'Aries'
    ];

    private const OWN = [
        'Sun' => ['Leo'],
        'Moon' => ['Cancer'],
        'Mars' => ['Aries', 'Scorpio'],
        'Mercury' => ['Gemini', 'Virgo'],
        'Jupiter' => ['Sagittarius', 'Pisces'],
        'Venus' => ['Taurus', 'Libra'],
        'Saturn' => ['Capricorn', 'Aquarius']
    ];

    private const KENDRA = [1, 4, 7, 10];
    private const NON_LAGNA_KENDRA = [4, 7, 10];
    private const TRIKONA = [5, 9];
    private const DUSTHANA = [6, 8, 12];

    public function detect(array $planets, ?string $lagna, ?array $d9 = null): array
    {
        $lagna = $this->normalizeSign($lagna);
        $byName = [];
        $byHouse = [];

        foreach ($planets as $planet) {
            if (!is_array($planet)) continue;

            $name = $this->planetName($planet);
            if ($name === '') continue;

            $global = $this->numericValue($planet, [
                'global_degree', 'longitude', 'sidereal_longitude', 'absolute_degree'
            ]);
            $local = $this->numericValue($planet, [
                'local_degree', 'degree_in_sign', 'sign_degree', 'degree'
            ]);

            $sign = $this->normalizeSign(
                $planet['rashi'] ?? $planet['rasi'] ?? $planet['zodiac'] ?? $planet['zodiac_name'] ?? null,
                $planet['rashi_no'] ?? $planet['rasi_no'] ?? $planet['zodiac_no'] ?? $planet['sign_no'] ?? null,
                $global
            );
            if ($sign === null && $global !== null) {
                $sign = $this->signFromLongitude($global);
            }

            $house = $this->normalizeHouse($planet['house'] ?? $planet['house_no'] ?? $planet['bhava'] ?? $planet['bhava_no'] ?? null);
            if ($house === null && $sign !== null && $lagna !== null) {
                $house = $this->houseFromSigns($lagna, $sign);
            }

            if ($sign === null || $house === null) continue;

            if ($local === null && $global !== null) {
                $local = fmod($global + 360.0, 30.0);
            }

            $byName[$name] = [
                'name' => $name,
                'sign' => $sign,
                'house' => $house,
                'degree' => $local,
                'combust' => $planet['combust'] ?? null
            ];
            $byHouse[$house][] = $name;
        }

        $lords = $this->houseLords($lagna);
        $yogas = [];

        foreach ([
            ['Ruchaka', 'Mars'],
            ['Bhadra', 'Mercury'],
            ['Hamsa', 'Jupiter'],
            ['Malavya', 'Venus'],
            ['Sasa', 'Saturn']
        ] as [$yoga, $planetName]) {
            $planet = $byName[$planetName] ?? null;
            $formed = $planet !== null
                && in_array($planet['house'], self::KENDRA, true)
                && ($this->isOwn($planetName, $planet['sign']) || $this->isExalted($planetName, $planet['sign']));

            $status = 'Not formed';
            if ($formed) {
                $status = $this->isCombust($planet, $byName) ? 'Formed but weakened' : 'Formed';
            }

            $reason = $formed
                ? $planetName . ' is in a kendra in its own or exaltation sign.'
                : $planetName . ' does not meet both the kendra and own/exaltation-sign conditions.';

            if ($formed && $status === 'Formed but weakened') {
                $reason .= ' Combustion is reported as a weakening context.';
            }

            $yogas[] = $this->result($yoga, 'Pancha Mahapurusha', $status, $reason, [$planetName]);
        }

        $moon = $byName['Moon'] ?? null;
        $jupiter = $byName['Jupiter'] ?? null;
        if ($moon && $jupiter) {
            $formed = in_array($this->houseDistance($moon['house'], $jupiter['house']), self::KENDRA, true);
            $weak = $this->isDebilitated('Jupiter', $jupiter['sign']) || $this->isCombust($jupiter, $byName);
            $status = !$formed ? 'Not formed' : ($weak ? 'Formed but weakened' : 'Formed');
            $reason = $formed
                ? 'Jupiter is in a kendra from Moon.'
                : 'Jupiter is not in a kendra (1/4/7/10) from Moon.';
            if ($formed && $weak) $reason .= ' Jupiter has a dignity/combustion weakening flag.';
            $yogas[] = $this->result('Gaja Kesari', 'Lunar-Jupiter', $status, $reason, ['Moon', 'Jupiter']);
        } else {
            $yogas[] = $this->result(
                'Gaja Kesari',
                'Lunar-Jupiter',
                'Not formed',
                'Moon and/or Jupiter is missing from normalized D1 data.',
                ['Moon', 'Jupiter']
            );
        }

        $raja = $this->connectionYoga($lords, $byName, self::KENDRA, self::TRIKONA);
        $yogas[] = $this->result('Kendra–Trikona Raja Yoga', 'Raja Yoga', $raja['status'], $raja['reason'], $raja['planets']);

        $dhana = $this->connectionYoga($lords, $byName, [2, 11], [1, 2, 5, 9, 11]);
        $yogas[] = $this->result('Dhana Yoga', 'Wealth', $dhana['status'], $dhana['reason'], $dhana['planets']);

        $viparita = $this->viparita($lords, $byName);
        $yogas[] = $this->result('Vipareeta Raja Yoga', 'Dusthana', $viparita['status'], $viparita['reason'], $viparita['planets']);

        foreach ($this->yogakaraka($lagna, $byName) as $item) $yogas[] = $item;
        foreach ($this->neechaBhanga($byName, $d9) as $item) $yogas[] = $item;

        return [
            'lagna' => $lagna,
            'yogas' => $yogas,
            'house_lords' => $lords,
            'house_occupancy' => $byHouse
        ];
    }

    private function result(string $name, string $family, string $status, string $reason, array $planets): array
    {
        return [
            'name' => $name,
            'family' => $family,
            'status' => $status,
            'reason' => $reason,
            'planets' => array_values(array_unique($planets))
        ];
    }

    private function connectionYoga(array $lords, array $byName, array $firstHouses, array $secondHouses): array
    {
        $connections = [];
        $planets = [];

        foreach ($firstHouses as $firstHouse) {
            foreach ($secondHouses as $secondHouse) {
                if ($firstHouse === $secondHouse) continue;
                $firstLord = $lords[$firstHouse]['lord'] ?? null;
                $secondLord = $lords[$secondHouse]['lord'] ?? null;
                if (!$firstLord || !$secondLord || $firstLord === $secondLord) continue;
                if (!isset($byName[$firstLord], $byName[$secondLord])) continue;

                if ($this->connected($byName[$firstLord], $byName[$secondLord])) {
                    $connections[] = $firstLord . ' (H' . $firstHouse . ') ↔ ' . $secondLord . ' (H' . $secondHouse . ')';
                    $planets[] = $firstLord;
                    $planets[] = $secondLord;
                }
            }
        }

        if (!$connections) {
            return [
                'status' => 'Not formed',
                'reason' => 'No conjunction, qualifying Parashari mutual aspect, or sign exchange was found among the relevant lords.',
                'planets' => []
            ];
        }

        $planets = array_values(array_unique($planets));
        $weakened = false;
        $notes = [];
        foreach ($planets as $planetName) {
            $planet = $byName[$planetName] ?? null;
            if (!$planet) continue;
            if (in_array($planet['house'], self::DUSTHANA, true)) {
                $weakened = true;
                $notes[] = $planetName . ' in H' . $planet['house'];
            }
            if ($this->isDebilitated($planetName, $planet['sign'])) {
                $weakened = true;
                $notes[] = $planetName . ' debilitated';
            }
        }

        return [
            'status' => $weakened ? 'Formed but weakened' : 'Formed',
            'reason' => 'Connected lords: ' . implode('; ', $connections)
                . ($notes ? '. Contextual weakening: ' . implode(', ', $notes) . '.' : '.'),
            'planets' => $planets
        ];
    }

    private function yogakaraka(?string $lagna, array $byName): array
    {
        if (!$lagna) return [];
        $start = $this->signNumber($lagna);
        $results = [];

        foreach (self::OWN as $planetName => $ownedSigns) {
            $ownedHouses = [];
            foreach ($ownedSigns as $sign) {
                $signNumber = $this->signNumber($sign);
                if ($signNumber) $ownedHouses[] = (($signNumber - $start + 12) % 12) + 1;
            }

            $hasKendra = (bool) array_intersect($ownedHouses, self::NON_LAGNA_KENDRA);
            $hasTrikona = (bool) array_intersect($ownedHouses, self::TRIKONA);
            if (!$hasKendra || !$hasTrikona) continue;

            $planet = $byName[$planetName] ?? null;
            if (!$planet) {
                $results[] = $this->result(
                    'Yogakaraka — ' . $planetName,
                    'Yogakaraka',
                    'Not assessable',
                    $planetName . ' owns both a kendra and a trikona from ' . $lagna . ', but is missing from normalized D1 data.',
                    [$planetName]
                );
                continue;
            }

            $status = $this->isDebilitated($planetName, $planet['sign']) ? 'Formed but weakened' : 'Formed';
            $reason = $planetName . ' owns both a kendra and a trikona from ' . $lagna . ' (houses ' . implode(', ', $ownedHouses) . ').';
            if ($status === 'Formed but weakened') $reason .= ' Its natal dignity is debilitated.';
            $results[] = $this->result('Yogakaraka — ' . $planetName, 'Yogakaraka', $status, $reason, [$planetName]);
        }
        return $results;
    }

    private function viparita(array $lords, array $byName): array
    {
        $found = [];
        $planets = [];
        foreach (self::DUSTHANA as $dusthanaHouse) {
            $lord = $lords[$dusthanaHouse]['lord'] ?? null;
            if (!$lord || !isset($byName[$lord])) continue;
            $placedHouse = $byName[$lord]['house'];
            if (in_array($placedHouse, self::DUSTHANA, true) && $placedHouse !== $dusthanaHouse) {
                $found[] = $lord . ': H' . $dusthanaHouse . ' lord in H' . $placedHouse;
                $planets[] = $lord;
            }
        }

        if (!$found) {
            return [
                'status' => 'Not formed',
                'reason' => 'No 6th/8th/12th lord is placed in another dusthana in the normalized D1 chart.',
                'planets' => []
            ];
        }
        return [
            'status' => 'Formed',
            'reason' => 'Dusthana lord(s) occupy another dusthana: ' . implode('; ', $found) . '.',
            'planets' => array_values(array_unique($planets))
        ];
    }

    private function neechaBhanga(array $byName, ?array $d9): array
    {
        $results = [];
        foreach (self::DEBILITATION as $planetName => $debilitationSign) {
            $planet = $byName[$planetName] ?? null;
            if (!$planet || $planet['sign'] !== $debilitationSign) continue;

            $conditions = [];
            $debilitationLord = self::LORDS[$debilitationSign] ?? null;
            $exaltedPlanet = null;
            foreach (self::EXALTATION as $candidate => $sign) {
                if ($sign === $debilitationSign) {
                    $exaltedPlanet = $candidate;
                    break;
                }
            }

            if ($debilitationLord && isset($byName[$debilitationLord]) && in_array($byName[$debilitationLord]['house'], self::KENDRA, true)) {
                $conditions[] = $debilitationLord . ', lord of ' . $debilitationSign . ', is in a kendra';
            }
            if ($exaltedPlanet && isset($byName[$exaltedPlanet]) && in_array($byName[$exaltedPlanet]['house'], self::KENDRA, true)) {
                $conditions[] = $exaltedPlanet . ', exalted in ' . $debilitationSign . ', is in a kendra';
            }

            $d9Position = $this->findD9($d9, $planetName);
            if ($d9Position && ($d9Position['d9_sign'] ?? null) === (self::EXALTATION[$planetName] ?? null)) {
                $conditions[] = $planetName . ' is exalted in D9';
            }

            $status = $conditions ? 'Potentially cancelled' : 'Debilitated; no implemented cancellation condition matched';
            $reason = 'Debilitated in ' . $debilitationSign . '.';
            if ($conditions) $reason .= ' Conditions matched: ' . implode('; ', $conditions) . '.';

            $results[] = $this->result('Neecha Bhanga — ' . $planetName, 'Debilitation', $status, $reason, [$planetName]);
        }
        return $results;
    }

    private function connected(array $first, array $second): bool
    {
        if ($first['house'] === $second['house']) return true;
        if ($this->planetAspects((string) $first['name'], (int) $first['house'], (int) $second['house'])) return true;
        if ($this->planetAspects((string) $second['name'], (int) $second['house'], (int) $first['house'])) return true;
        return $this->lordsExchange($first, $second);
    }

    private function planetAspects(string $planet, int $fromHouse, int $toHouse): bool
    {
        $distance = $this->houseDistance($fromHouse, $toHouse);
        $targets = [7];
        if ($planet === 'Mars') $targets = [4, 7, 8];
        elseif ($planet === 'Jupiter') $targets = [5, 7, 9];
        elseif ($planet === 'Saturn') $targets = [3, 7, 10];
        return in_array($distance, $targets, true);
    }

    private function lordsExchange(array $first, array $second): bool
    {
        return (self::LORDS[$first['sign']] ?? null) === $second['name']
            && (self::LORDS[$second['sign']] ?? null) === $first['name'];
    }

    private function isCombust(array $planet, array $allPlanets): bool
    {
        $value = $planet['combust'] ?? null;
        if (is_bool($value)) return $value;
        if (is_scalar($value)) {
            $normalized = strtolower(trim((string) $value));
            if (in_array($normalized, ['true', '1', 'yes'], true)) return true;
            if (in_array($normalized, ['false', '0', 'no', ''], true)) return false;
        }

        $sun = $allPlanets['Sun'] ?? null;
        if (!$sun || $planet['name'] === 'Sun' || $planet['sign'] !== $sun['sign']) return false;
        if (!is_numeric($planet['degree']) || !is_numeric($sun['degree'])) return false;
        return abs((float) $planet['degree'] - (float) $sun['degree']) <= 8.0;
    }

    private function houseDistance(int $fromHouse, int $toHouse): int
    {
        return (($toHouse - $fromHouse + 12) % 12) + 1;
    }

    private function houseFromSigns(string $lagna, string $sign): ?int
    {
        $lagnaNo = $this->signNumber($lagna);
        $signNo = $this->signNumber($sign);
        if ($lagnaNo < 1 || $signNo < 1) return null;
        return (($signNo - $lagnaNo + 12) % 12) + 1;
    }

    private function isOwn(string $planet, string $sign): bool
    {
        return in_array($sign, self::OWN[$planet] ?? [], true);
    }

    private function isExalted(string $planet, string $sign): bool
    {
        return (self::EXALTATION[$planet] ?? null) === $sign;
    }

    private function isDebilitated(string $planet, string $sign): bool
    {
        return (self::DEBILITATION[$planet] ?? null) === $sign;
    }

    private function houseLords(?string $lagna): array
    {
        if (!$lagna) return [];
        $lagnaNumber = $this->signNumber($lagna);
        if ($lagnaNumber < 1) return [];
        $lords = [];
        for ($house = 1; $house <= 12; $house++) {
            $sign = self::SIGNS[($lagnaNumber - 1 + $house - 1) % 12];
            $lords[$house] = [
                'house' => $house,
                'sign' => $sign,
                'lord' => self::LORDS[$sign]
            ];
        }
        return $lords;
    }

    private function findD9(?array $d9, string $planetName): ?array
    {
        if (!is_array($d9)) return null;
        foreach (($d9['positions'] ?? []) as $position) {
            if (is_array($position) && strcasecmp((string) ($position['name'] ?? ''), $planetName) === 0) return $position;
        }
        return null;
    }

    private function planetName(array $planet): string
    {
        $name = trim((string) ($planet['name'] ?? $planet['full_name'] ?? ''));
        $aliases = [
            'sun' => 'Sun', 'moon' => 'Moon', 'mars' => 'Mars', 'mercury' => 'Mercury',
            'jupiter' => 'Jupiter', 'venus' => 'Venus', 'saturn' => 'Saturn',
            'rahu' => 'Rahu', 'ketu' => 'Ketu'
        ];
        return $aliases[strtolower($name)] ?? $name;
    }

    private function normalizeSign(mixed $sign, mixed $number = null, ?float $globalDegree = null): ?string
    {
        if (is_numeric($sign)) $number = $sign;
        if ($number !== null && is_numeric($number)) {
            $n = (int) $number;
            if ($n >= 1 && $n <= 12) return self::SIGNS[$n - 1];
        }

        $text = strtolower(trim((string) $sign));
        $aliases = [
            'mesha'=>'Aries','aries'=>'Aries', 'vrishabha'=>'Taurus','vrishabh'=>'Taurus','taurus'=>'Taurus',
            'mithuna'=>'Gemini','gemini'=>'Gemini', 'karka'=>'Cancer','cancer'=>'Cancer',
            'simha'=>'Leo','leo'=>'Leo', 'kanya'=>'Virgo','virgo'=>'Virgo', 'tula'=>'Libra','libra'=>'Libra',
            'vrishchika'=>'Scorpio','scorpio'=>'Scorpio', 'dhanu'=>'Sagittarius','sagittarius'=>'Sagittarius',
            'makara'=>'Capricorn','capricorn'=>'Capricorn', 'kumbha'=>'Aquarius','aquarius'=>'Aquarius',
            'meena'=>'Pisces','pisces'=>'Pisces'
        ];
        if (isset($aliases[$text])) return $aliases[$text];

        foreach (self::SIGNS as $knownSign) {
            if (strcasecmp($knownSign, trim((string) $sign)) === 0) return $knownSign;
        }

        return $globalDegree !== null ? $this->signFromLongitude($globalDegree) : null;
    }

    private function normalizeHouse(mixed $value): ?int
    {
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric(trim($value)))) {
            $house = (int) $value;
            return ($house >= 1 && $house <= 12) ? $house : null;
        }
        $text = strtolower(trim((string) $value));
        if ($text === '') return null;
        if (preg_match('/(?:house|bhava|h)?\s*(1[0-2]|[1-9])(?:st|nd|rd|th)?/i', $text, $m)) return (int) $m[1];
        $words = [
            'first'=>1,'second'=>2,'third'=>3,'fourth'=>4,'fifth'=>5,'sixth'=>6,
            'seventh'=>7,'eighth'=>8,'ninth'=>9,'tenth'=>10,'eleventh'=>11,'twelfth'=>12
        ];
        return $words[$text] ?? null;
    }

    private function numericValue(array $value, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $value) && is_numeric($value[$key])) return (float) $value[$key];
        }
        return null;
    }

    private function signNumber(string $sign): int
    {
        foreach (self::SIGNS as $index => $knownSign) {
            if (strcasecmp($knownSign, trim($sign)) === 0) return $index + 1;
        }
        return 0;
    }

    private function signFromLongitude(float $longitude): string
    {
        $degree = fmod($longitude + 360.0, 360.0);
        return self::SIGNS[(int) floor($degree / 30.0)];
    }
}
