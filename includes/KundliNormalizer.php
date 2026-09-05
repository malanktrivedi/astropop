<?php
declare(strict_types=1);

final class KundliNormalizer
{
    private const SIGNS = [
        'Aries', 'Taurus', 'Gemini', 'Cancer', 'Leo', 'Virgo',
        'Libra', 'Scorpio', 'Sagittarius', 'Capricorn', 'Aquarius', 'Pisces'
    ];

    /** @return array<string,mixed> */
    public function normalize(array $planetPayload, array $ascendantPayload): array
    {
        $planetObjects = $this->findPlanetObjects($planetPayload);
        $ascendant = $this->findFirstObjectWithKey($ascendantPayload, 'ascendant');
        if ($ascendant === null) {
            $ascendant = $this->findNamedPlanet($planetObjects, 'ascendant');
        }

        $planets = [];
        $moon = null;
        foreach ($planetObjects as $planet) {
            $name = (string) ($planet['name'] ?? $planet['full_name'] ?? '');
            if ($name === '' || strcasecmp($name, 'ascendant') === 0) {
                continue;
            }

            $rashiNo = $this->firstNonEmpty([
                $planet['rashi_no'] ?? null,
                $planet['rasi_no'] ?? null,
                $planet['zodiac_no'] ?? null,
                $planet['sign_no'] ?? null,
            ]);
            $rashi = $this->firstNonEmpty([
                $planet['rashi'] ?? null,
                $planet['rasi'] ?? null,
                $planet['zodiac'] ?? null,
                $planet['zodiac_name'] ?? null,
                $planet['rashi_name'] ?? null,
                $planet['rasi_name'] ?? null,
            ]);
            $rashi = $this->normalizeSign($rashi, $rashiNo, $planet['global_degree'] ?? null);

            $localDegree = $this->findFirstNumeric($planet, [
                'local_degree', 'degree_in_sign', 'sign_degree', 'degree'
            ]);
            $globalDegree = $this->findFirstNumeric($planet, [
                'global_degree', 'longitude', 'sidereal_longitude', 'absolute_degree'
            ]);
            if ($rashi === null && $globalDegree !== null) {
                $rashi = $this->zodiacFromGlobalDegree($globalDegree);
            }

            $house = $this->normalizeHouse($planet['house'] ?? $planet['house_no'] ?? null);
            if ($house === null) {
                $house = $this->normalizeHouse($planet['bhava'] ?? $planet['bhava_no'] ?? null);
            }

            $nakshatra = $this->firstNonEmpty([
                $planet['nakshatra'] ?? null,
                $planet['nakshatra_name'] ?? null
            ]);

            $normalized = [
                'name' => $name,
                'full_name' => $planet['full_name'] ?? $name,
                'local_degree' => $localDegree,
                'global_degree' => $globalDegree,
                'rashi_no' => $rashiNo !== null ? (int) $rashiNo : ($rashi !== null ? $this->signNumber($rashi) : null),
                'rashi' => $rashi,
                'house' => $house,
                'nakshatra' => $nakshatra,
                'nakshatra_no' => $planet['nakshatra_no'] ?? null,
                'nakshatra_pada' => $planet['nakshatra_pada'] ?? $planet['pada'] ?? null,
                'lord' => $planet['lord'] ?? $planet['nakshatra_lord'] ?? null,
                'lord_status' => $planet['lord_status'] ?? null,
                'retrograde' => $planet['retrograde'] ?? $planet['retro'] ?? null,
                'combust' => $planet['combust'] ?? null,
                'raw' => $planet,
            ];
            $planets[] = $normalized;

            if (stripos($name, 'moon') !== false || stripos((string) ($planet['full_name'] ?? ''), 'moon') !== false) {
                $moon = $normalized;
            }
        }

        $lagna = $this->firstNonEmpty([
            $ascendant['ascendant'] ?? null,
            $ascendant['rashi'] ?? null,
            $ascendant['rasi'] ?? null,
            $ascendant['zodiac'] ?? null,
            $ascendant['zodiac_name'] ?? null,
            $this->findFirstScalar($ascendantPayload, ['ascendant'])
        ]);
        $lagna = $this->normalizeSign($lagna, $ascendant['rashi_no'] ?? $ascendant['zodiac_no'] ?? null, null);

        $rashi = $moon['rashi'] ?? $this->findFirstScalar($planetPayload, ['rashi', 'rasi', 'zodiac', 'zodiac_name']);
        $nakshatra = $moon['nakshatra'] ?? $this->findFirstScalar($planetPayload, ['nakshatra', 'nakshatra_name']);

        $ascendantData = null;
        if ($ascendant !== null) {
            $ascendantRashi = $this->normalizeSign(
                $ascendant['rashi'] ?? $ascendant['rasi'] ?? $ascendant['zodiac'] ?? $ascendant['zodiac_name'] ?? $lagna,
                $ascendant['rashi_no'] ?? $ascendant['rasi_no'] ?? $ascendant['zodiac_no'] ?? $ascendant['sign_no'] ?? null,
                $this->findFirstNumeric($ascendant, ['global_degree', 'longitude', 'sidereal_longitude', 'absolute_degree'])
            );
            $ascendantData = [
                'name' => 'Ascendant',
                'rashi' => $ascendantRashi,
                'rashi_no' => $ascendantRashi !== null ? $this->signNumber($ascendantRashi) : null,
                'local_degree' => $this->findFirstNumeric($ascendant, ['local_degree', 'degree_in_sign', 'sign_degree', 'degree']),
                'global_degree' => $this->findFirstNumeric($ascendant, ['global_degree', 'longitude', 'sidereal_longitude', 'absolute_degree']),
                'raw' => $ascendant,
            ];
        }

        $houses = [];
        foreach ($planets as $planet) {
            $house = $planet['house'];
            if ($house === null) {
                continue;
            }
            $key = (string) $house;
            $houses[$key] ??= [];
            $houses[$key][] = $planet['name'];
        }
        ksort($houses, SORT_NATURAL);

        $dasha = $this->findFirstValueByKey($planetPayload, [
            'dasha', 'dasa', 'birth_dasha', 'current_dasha', 'maha_dasha', 'mahadasa'
        ]);
        if ($dasha === null) {
            $dasha = $this->findFirstValueByKey($ascendantPayload, [
                'dasha', 'dasa', 'birth_dasha', 'current_dasha', 'maha_dasha', 'mahadasa'
            ]);
        }

        return [
            'lagna' => $lagna,
            'rashi' => $rashi,
            'nakshatra' => $nakshatra,
            'planetary_data' => $planets,
            'house_data' => $houses,
            'dasha_data' => $dasha,
            'chart_data' => [
                'ascendant' => $ascendantData,
                'planet_count' => count($planets)
            ],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function findPlanetObjects(array $payload): array
    {
        $found = [];
        $walk = function (mixed $value) use (&$walk, &$found): void {
            if (!is_array($value)) return;
            if (isset($value['name']) && is_string($value['name']) && (
                array_key_exists('local_degree', $value)
                || array_key_exists('global_degree', $value)
                || array_key_exists('longitude', $value)
                || array_key_exists('rashi', $value)
                || array_key_exists('rasi', $value)
            )) {
                $found[] = $value;
            }
            foreach ($value as $child) $walk($child);
        };
        $walk($payload);
        return $found;
    }

    /** @param list<array<string,mixed>> $objects */
    private function findNamedPlanet(array $objects, string $name): ?array
    {
        foreach ($objects as $object) {
            if (strcasecmp(trim((string) ($object['name'] ?? '')), $name) === 0) return $object;
        }
        return null;
    }

    /** @return array<string,mixed>|null */
    private function findFirstObjectWithKey(array $payload, string $key): ?array
    {
        $found = null;
        $walk = function (mixed $value) use (&$walk, &$found, $key): void {
            if ($found !== null || !is_array($value)) return;
            if (array_key_exists($key, $value) && is_array($value[$key])) {
                $found = $value[$key];
                return;
            }
            foreach ($value as $child) $walk($child);
        };
        $walk($payload);
        return $found;
    }

    private function findFirstScalar(array $payload, array $keys): mixed
    {
        $value = $this->findFirstValueByKey($payload, $keys);
        return is_scalar($value) ? $value : null;
    }

    private function findFirstValueByKey(array $payload, array $keys): mixed
    {
        $wanted = array_map('strtolower', $keys);
        $found = null;
        $walk = function (mixed $value) use (&$walk, &$found, $wanted): void {
            if ($found !== null || !is_array($value)) return;
            foreach ($value as $key => $child) {
                if (in_array(strtolower((string) $key), $wanted, true) && $child !== null && $child !== '') {
                    $found = $child;
                    return;
                }
                $walk($child);
            }
        };
        $walk($payload);
        return $found;
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

    private function firstNonEmpty(array $values): mixed
    {
        foreach ($values as $value) if ($value !== null && $value !== '') return $value;
        return null;
    }

    private function normalizeSign(mixed $value, mixed $number = null, mixed $globalDegree = null): ?string
    {
        if (is_numeric($value)) {
            $number = $value;
        }
        if ($number !== null && is_numeric($number)) {
            $number = (int) $number;
            if ($number >= 1 && $number <= 12) return self::SIGNS[$number - 1];
        }

        $text = strtolower(trim((string) $value));
        $aliases = [
            'mesha' => 'Aries', 'aries' => 'Aries',
            'vrishabha' => 'Taurus', 'vrishabh' => 'Taurus', 'taurus' => 'Taurus',
            'mithuna' => 'Gemini', 'gemini' => 'Gemini',
            'karka' => 'Cancer', 'cancer' => 'Cancer',
            'simha' => 'Leo', 'leo' => 'Leo',
            'kanya' => 'Virgo', 'virgo' => 'Virgo',
            'tula' => 'Libra', 'libra' => 'Libra',
            'vrishchika' => 'Scorpio', 'scorpio' => 'Scorpio',
            'dhanu' => 'Sagittarius', 'sagittarius' => 'Sagittarius',
            'makara' => 'Capricorn', 'capricorn' => 'Capricorn',
            'kumbha' => 'Aquarius', 'aquarius' => 'Aquarius',
            'meena' => 'Pisces', 'pisces' => 'Pisces',
        ];
        if (isset($aliases[$text])) return $aliases[$text];

        foreach (self::SIGNS as $sign) {
            if (strcasecmp($sign, trim((string) $value)) === 0) return $sign;
        }

        if ($globalDegree !== null && is_numeric($globalDegree)) {
            return $this->zodiacFromGlobalDegree((float) $globalDegree);
        }
        return null;
    }

    private function normalizeHouse(mixed $value): ?int
    {
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric(trim($value)))) {
            $house = (int) $value;
            return ($house >= 1 && $house <= 12) ? $house : null;
        }
        $text = strtolower(trim((string) $value));
        if ($text === '') return null;
        if (preg_match('/(?:house|bhava|h)?\s*(1[0-2]|[1-9])(?:st|nd|rd|th)?/i', $text, $m)) {
            return (int) $m[1];
        }
        $words = [
            'first'=>1,'second'=>2,'third'=>3,'fourth'=>4,'fifth'=>5,'sixth'=>6,
            'seventh'=>7,'eighth'=>8,'ninth'=>9,'tenth'=>10,'eleventh'=>11,'twelfth'=>12
        ];
        return $words[$text] ?? null;
    }

    private function signNumber(mixed $value): ?int
    {
        $normalized = $this->normalizeSign($value);
        if ($normalized === null) return null;
        $index = array_search($normalized, self::SIGNS, true);
        return $index === false ? null : $index + 1;
    }

    private function zodiacFromGlobalDegree(float $value): ?string
    {
        $degree = fmod($value + 360.0, 360.0);
        return self::SIGNS[(int) floor($degree / 30)];
    }
}
