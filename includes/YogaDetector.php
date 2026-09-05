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
            if (!is_array($planet)) {
                continue;
            }

            $name = $this->planetName($planet);
            $sign = $this->normalizeSign((string) ($planet['rashi'] ?? ''));
            $house = is_numeric($planet['house'] ?? null) ? (int) $planet['house'] : null;

            if ($name === '' || $sign === null || $house === null) {
                continue;
            }

            $degree = is_numeric($planet['local_degree'] ?? null)
                ? (float) $planet['local_degree']
                : null;

            $byName[$name] = [
                'name' => $name,
                'sign' => $sign,
                'house' => $house,
                'degree' => $degree,
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
                $status = $this->isCombust($planet, $byName)
                    ? 'Formed but weakened'
                    : 'Formed';
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
            $formed = in_array(
                $this->houseDistance($moon['house'], $jupiter['house']),
                self::KENDRA,
                true
            );
            $weak = $this->isDebilitated('Jupiter', $jupiter['sign'])
                || $this->isCombust($jupiter, $byName);

            $status = !$formed ? 'Not formed' : ($weak ? 'Formed but weakened' : 'Formed');
            $reason = $formed
                ? 'Jupiter is in a kendra from Moon.'
                : 'Jupiter is not in a kendra (1/4/7/10) from Moon.';

            if ($formed && $weak) {
                $reason .= ' Jupiter has a dignity/combustion weakening flag.';
            }

            $yogas[] = $this->result(
                'Gaja Kesari',
                'Lunar-Jupiter',
                $status,
                $reason,
                ['Moon', 'Jupiter']
            );
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
        $yogas[] = $this->result(
            'Kendra–Trikona Raja Yoga',
            'Raja Yoga',
            $raja['status'],
            $raja['reason'],
            $raja['planets']
        );

        $dhana = $this->connectionYoga($lords, $byName, [2, 11], [1, 2, 5, 9, 11]);
        $yogas[] = $this->result(
            'Dhana Yoga',
            'Wealth',
            $dhana['status'],
            $dhana['reason'],
            $dhana['planets']
        );

        $viparita = $this->viparita($lords, $byName);
        $yogas[] = $this->result(
            'Vipareeta Raja Yoga',
            'Dusthana',
            $viparita['status'],
            $viparita['reason'],
            $viparita['planets']
        );

        foreach ($this->yogakaraka($lagna, $byName) as $item) {
            $yogas[] = $item;
        }

        foreach ($this->neechaBhanga($byName, $d9) as $item) {
            $yogas[] = $item;
        }

        return [
            'lagna' => $lagna,
            'yogas' => $yogas,
            'house_lords' => $lords,
            'house_occupancy' => $byHouse
        ];
    }

    private function result(
        string $name,
        string $family,
        string $status,
        string $reason,
        array $planets
    ): array {
        return [
            'name' => $name,
            'family' => $family,
            'status' => $status,
            'reason' => $reason,
            'planets' => array_values(array_unique($planets))
        ];
    }

    private function connectionYoga(
        array $lords,
        array $byName,
        array $firstHouses,
        array $secondHouses
    ): array {
        $connections = [];
        $planets = [];

        foreach ($firstHouses as $firstHouse) {
            foreach ($secondHouses as $secondHouse) {
                if ($firstHouse === $secondHouse) {
                    continue;
                }

                $firstLord = $lords[$firstHouse]['lord'] ?? null;
                $secondLord = $lords[$secondHouse]['lord'] ?? null;

                if (!$firstLord || !$secondLord || $firstLord === $secondLord) {
                    continue;
                }

                if (!isset($byName[$firstLord], $byName[$secondLord])) {
                    continue;
                }

                if ($this->connected($byName[$firstLord], $byName[$secondLord])) {
                    $connections[] = $firstLord . ' (H' . $firstHouse . ') ↔ '
                        . $secondLord . ' (H' . $secondHouse . ')';
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
            if (!$planet) {
                continue;
            }

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
        if (!$lagna) {
            return [];
        }

        $start = $this->signNumber($lagna);
        $results = [];

        foreach (self::OWN as $planetName => $ownedSigns) {
            $ownedHouses = [];

            foreach ($ownedSigns as $sign) {
                $signNumber = $this->signNumber($sign);
                if ($signNumber) {
                    $ownedHouses[] = (($signNumber - $start + 12) % 12) + 1;
                }
            }

            // Yogakaraka requires ownership of a non-Lagna kendra (4/7/10)
            // and a trinal house (5/9). House 1 is deliberately not treated
            // as sufficient for this classification.
            $hasKendra = (bool) array_intersect($ownedHouses, self::NON_LAGNA_KENDRA);
            $hasTrikona = (bool) array_intersect($ownedHouses, self::TRIKONA);

            if (!$hasKendra || !$hasTrikona) {
                continue;
            }

            $planet = $byName[$planetName] ?? null;
            if (!$planet) {
                $results[] = $this->result(
                    'Yogakaraka — ' . $planetName,
                    'Yogakaraka',
                    'Not assessable',
                    $planetName . ' owns both a kendra and a trikona from ' . $lagna
                        . ', but is missing from normalized D1 data.',
                    [$planetName]
                );
                continue;
            }

            $status = $this->isDebilitated($planetName, $planet['sign'])
                ? 'Formed but weakened'
                : 'Formed';

            $reason = $planetName . ' owns both a kendra and a trikona from ' . $lagna
                . ' (houses ' . implode(', ', $ownedHouses) . ').';

            if ($status === 'Formed but weakened') {
                $reason .= ' Its natal dignity is debilitated.';
            }

            $results[] = $this->result(
                'Yogakaraka — ' . $planetName,
                'Yogakaraka',
                $status,
                $reason,
                [$planetName]
            );
        }

        return $results;
    }

    private function viparita(array $lords, array $byName): array
    {
        $found = [];
        $planets = [];

        foreach (self::DUSTHANA as $dusthanaHouse) {
            $lord = $lords[$dusthanaHouse]['lord'] ?? null;
            if (!$lord || !isset($byName[$lord])) {
                continue;
            }

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
            if (!$planet || $planet['sign'] !== $debilitationSign) {
                continue;
            }

            $conditions = [];
            $debilitationLord = self::LORDS[$debilitationSign] ?? null;
            $exaltedPlanet = null;

            foreach (self::EXALTATION as $candidate => $sign) {
                if ($sign === $debilitationSign) {
                    $exaltedPlanet = $candidate;
                    break;
                }
            }

            if (
                $debilitationLord
                && isset($byName[$debilitationLord])
                && in_array($byName[$debilitationLord]['house'], self::KENDRA, true)
            ) {
                $conditions[] = $debilitationLord . ', lord of ' . $debilitationSign . ', is in a kendra';
            }

            if (
                $exaltedPlanet
                && isset($byName[$exaltedPlanet])
                && in_array($byName[$exaltedPlanet]['house'], self::KENDRA, true)
            ) {
                $conditions[] = $exaltedPlanet . ', exalted in ' . $debilitationSign . ', is in a kendra';
            }

            $d9Position = $this->findD9($d9, $planetName);
            if (
                $d9Position
                && ($d9Position['d9_sign'] ?? null) === (self::EXALTATION[$planetName] ?? null)
            ) {
                $conditions[] = $planetName . ' is exalted in D9';
            }

            $status = $conditions
                ? 'Potentially cancelled'
                : 'Debilitated; no implemented cancellation condition matched';

            $reason = 'Debilitated in ' . $debilitationSign . '.';
            if ($conditions) {
                $reason .= ' Conditions matched: ' . implode('; ', $conditions) . '.';
            }

            $results[] = $this->result(
                'Neecha Bhanga — ' . $planetName,
                'Debilitation',
                $status,
                $reason,
                [$planetName]
            );
        }

        return $results;
    }

    private function connected(array $first, array $second): bool
    {
        if ($first['house'] === $second['house']) {
            return true;
        }

        if ($this->planetAspects((string) $first['name'], (int) $first['house'], (int) $second['house'])) {
            return true;
        }

        if ($this->planetAspects((string) $second['name'], (int) $second['house'], (int) $first['house'])) {
            return true;
        }

        return $this->lordsExchange($first, $second);
    }

    private function planetAspects(string $planet, int $fromHouse, int $toHouse): bool
    {
        $distance = $this->houseDistance($fromHouse, $toHouse);
        $targets = [7];

        if ($planet === 'Mars') {
            $targets = [4, 7, 8];
        } elseif ($planet === 'Jupiter') {
            $targets = [5, 7, 9];
        } elseif ($planet === 'Saturn') {
            $targets = [3, 7, 10];
        }

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

        if (is_bool($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            $normalized = strtolower(trim((string) $value));
            if (in_array($normalized, ['true', '1', 'yes'], true)) {
                return true;
            }
            if (in_array($normalized, ['false', '0', 'no', ''], true)) {
                return false;
            }
        }

        $sun = $allPlanets['Sun'] ?? null;
        if (!$sun || $planet['name'] === 'Sun') {
            return false;
        }

        if ($planet['sign'] !== $sun['sign']) {
            return false;
        }

        if (!is_numeric($planet['degree']) || !is_numeric($sun['degree'])) {
            return false;
        }

        return abs((float) $planet['degree'] - (float) $sun['degree']) <= 8.0;
    }

    private function houseDistance(int $fromHouse, int $toHouse): int
    {
        return (($toHouse - $fromHouse + 12) % 12) + 1;
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
        if (!$lagna) {
            return [];
        }

        $lagnaNumber = $this->signNumber($lagna);
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
        if (!is_array($d9)) {
            return null;
        }

        foreach (($d9['positions'] ?? []) as $position) {
            if (
                is_array($position)
                && strcasecmp((string) ($position['name'] ?? ''), $planetName) === 0
            ) {
                return $position;
            }
        }

        return null;
    }

    private function planetName(array $planet): string
    {
        $name = trim((string) ($planet['name'] ?? $planet['full_name'] ?? ''));
        $aliases = [
            'sun' => 'Sun',
            'moon' => 'Moon',
            'mars' => 'Mars',
            'mercury' => 'Mercury',
            'jupiter' => 'Jupiter',
            'venus' => 'Venus',
            'saturn' => 'Saturn',
            'rahu' => 'Rahu',
            'ketu' => 'Ketu'
        ];

        return $aliases[strtolower($name)] ?? $name;
    }

    private function normalizeSign(?string $sign): ?string
    {
        foreach (self::SIGNS as $knownSign) {
            if (strcasecmp($knownSign, trim((string) $sign)) === 0) {
                return $knownSign;
            }
        }

        return null;
    }

    private function signNumber(string $sign): int
    {
        foreach (self::SIGNS as $index => $knownSign) {
            if (strcasecmp($knownSign, trim($sign)) === 0) {
                return $index + 1;
            }
        }

        return 0;
    }
}
