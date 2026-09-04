<?php
declare(strict_types=1);

final class KundliNormalizer
{
    /** @return array<string,mixed> */
    public function normalize(array $planetPayload, array $ascendantPayload): array
    {
        $planetObjects = $this->findPlanetObjects($planetPayload);
        $ascendant = $this->findFirstObjectWithKey($ascendantPayload, 'ascendant');
        if ($ascendant === null) $ascendant = $this->findNamedPlanet($planetObjects, 'ascendant');

        $planets = [];
        $moon = null;
        foreach ($planetObjects as $planet) {
            $name = (string) ($planet['name'] ?? $planet['full_name'] ?? '');
            if ($name === '' || strcasecmp($name, 'ascendant') === 0) continue;

            $rashi = $this->firstNonEmpty([$planet['rashi'] ?? null, $planet['rasi'] ?? null, $planet['zodiac'] ?? null, $planet['zodiac_name'] ?? null, $planet['rashi_name'] ?? null, $planet['rasi_name'] ?? null]);
            $rashiNo = $this->firstNonEmpty([$planet['rashi_no'] ?? null, $planet['rasi_no'] ?? null, $planet['zodiac_no'] ?? null, $planet['sign_no'] ?? null]);
            if ($rashi === null && $rashiNo !== null) $rashi = $this->zodiacFromNumber($rashiNo);
            if ($rashi === null) $rashi = $this->zodiacFromGlobalDegree($planet['global_degree'] ?? null);

            $nakshatra = $this->firstNonEmpty([$planet['nakshatra'] ?? null, $planet['nakshatra_name'] ?? null]);
            $normalized = [
                'name' => $name,
                'full_name' => $planet['full_name'] ?? $name,
                'local_degree' => $planet['local_degree'] ?? null,
                'global_degree' => $planet['global_degree'] ?? null,
                'rashi_no' => $rashiNo,
                'rashi' => $rashi,
                'house' => $planet['house'] ?? $planet['house_no'] ?? null,
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
            if (stripos($name, 'moon') !== false || stripos((string) ($planet['full_name'] ?? ''), 'moon') !== false) $moon = $normalized;
        }

        $lagna = $this->firstNonEmpty([$ascendant['ascendant'] ?? null, $ascendant['rashi'] ?? null, $ascendant['rasi'] ?? null, $ascendant['zodiac'] ?? null, $this->findFirstScalar($ascendantPayload, ['ascendant'])]);
        $rashi = $moon['rashi'] ?? $this->findFirstScalar($planetPayload, ['rashi', 'rasi', 'zodiac', 'zodiac_name']);
        $nakshatra = $moon['nakshatra'] ?? $this->findFirstScalar($planetPayload, ['nakshatra', 'nakshatra_name']);

        /* Persist a stable, normalized Ascendant object for D9/D10 and future varga charts. */
        $ascendantData = $ascendant;
        if ($ascendantData !== null) {
            $ascendantRashi = $this->firstNonEmpty([
                $ascendantData['rashi'] ?? null,
                $ascendantData['rasi'] ?? null,
                $ascendantData['zodiac'] ?? null,
                $ascendantData['zodiac_name'] ?? null,
                is_string($lagna) ? $lagna : null,
            ]);
            $ascendantRashiNo = $this->firstNonEmpty([
                $ascendantData['rashi_no'] ?? null,
                $ascendantData['rasi_no'] ?? null,
                $ascendantData['zodiac_no'] ?? null,
                $ascendantData['sign_no'] ?? null,
            ]);
            if ($ascendantRashi === null && $ascendantRashiNo !== null) $ascendantRashi = $this->zodiacFromNumber($ascendantRashiNo);

            $ascendantData = [
                'name' => 'Ascendant',
                'rashi' => $ascendantRashi,
                'rashi_no' => $ascendantRashiNo,
                'local_degree' => $this->findFirstNumeric($ascendant, ['local_degree','degree_in_sign','sign_degree','degree']),
                'global_degree' => $this->findFirstNumeric($ascendant, ['global_degree','longitude','sidereal_longitude','absolute_degree']),
                'raw' => $ascendant,
            ];
        }

        $houses = [];
        foreach ($planets as $planet) {
            $house = $planet['house'];
            if ($house === null || $house === '') continue;
            $key = (string) $house;
            $houses[$key] ??= [];
            $houses[$key][] = $planet['name'];
        }
        ksort($houses, SORT_NATURAL);

        $dasha = $this->findFirstValueByKey($planetPayload, ['dasha', 'dasa', 'birth_dasha', 'current_dasha', 'maha_dasha', 'mahadasa']);
        if ($dasha === null) $dasha = $this->findFirstValueByKey($ascendantPayload, ['dasha', 'dasa', 'birth_dasha', 'current_dasha', 'maha_dasha', 'mahadasa']);

        return [
            'lagna' => $lagna,
            'rashi' => $rashi,
            'nakshatra' => $nakshatra,
            'planetary_data' => $planets,
            'house_data' => $houses,
            'dasha_data' => $dasha,
            'chart_data' => ['ascendant' => $ascendantData, 'planet_count' => count($planets)],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function findPlanetObjects(array $payload): array
    {
        $found = [];
        $walk = function (mixed $value) use (&$walk, &$found): void {
            if (!is_array($value)) return;
            if (isset($value['name']) && is_string($value['name']) && (array_key_exists('local_degree', $value) || array_key_exists('global_degree', $value))) $found[] = $value;
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
            if (array_key_exists($key, $value) && is_array($value)) { $found = $value; return; }
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
                if (in_array(strtolower((string) $key), $wanted, true) && $child !== null && $child !== '') { $found = $child; return; }
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

    private function zodiacFromNumber(mixed $value): ?string
    {
        if (!is_numeric($value)) return null;
        $number = (int) $value;
        if ($number < 1 || $number > 12) return null;
        return ['Aries','Taurus','Gemini','Cancer','Leo','Virgo','Libra','Scorpio','Sagittarius','Capricorn','Aquarius','Pisces'][$number - 1];
    }

    private function zodiacFromGlobalDegree(mixed $value): ?string
    {
        if (!is_numeric($value)) return null;
        $degree = fmod((float) $value + 360.0, 360.0);
        return ['Aries','Taurus','Gemini','Cancer','Leo','Virgo','Libra','Scorpio','Sagittarius','Capricorn','Aquarius','Pisces'][(int) floor($degree / 30)];
    }
}
