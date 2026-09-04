<?php
declare(strict_types=1);

final class KundliNormalizer
{
    /** @return array<string,mixed> */
    public function normalize(array $planetPayload, array $ascendantPayload): array
    {
        $planetObjects = $this->findPlanetObjects($planetPayload);
        $ascendant = $this->findFirstObjectWithKey($ascendantPayload, 'ascendant');

        $planets = [];
        $moon = null;
        foreach ($planetObjects as $planet) {
            $name = (string) ($planet['name'] ?? $planet['full_name'] ?? '');
            if ($name === '' || strcasecmp($name, 'ascendant') === 0) {
                continue;
            }
            $normalized = [
                'name' => $name,
                'full_name' => $planet['full_name'] ?? $name,
                'local_degree' => $planet['local_degree'] ?? null,
                'global_degree' => $planet['global_degree'] ?? null,
                'rashi_no' => $planet['rashi_no'] ?? null,
                'rashi' => $planet['rashi'] ?? null,
                'house' => $planet['house'] ?? null,
                'nakshatra' => $planet['nakshatra'] ?? null,
                'nakshatra_no' => $planet['nakshatra_no'] ?? null,
                'nakshatra_pada' => $planet['nakshatra_pada'] ?? null,
                'lord' => $planet['lord'] ?? null,
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
            $this->findFirstScalar($ascendantPayload, ['ascendant']),
        ]);

        $rashi = $moon['rashi'] ?? $this->findFirstScalar($planetPayload, ['rashi']);
        $nakshatra = $moon['nakshatra'] ?? $this->findFirstScalar($planetPayload, ['nakshatra']);

        $houses = [];
        foreach ($planets as $planet) {
            $house = $planet['house'];
            if ($house === null || $house === '') continue;
            $key = (string) $house;
            $houses[$key] ??= [];
            $houses[$key][] = $planet['name'];
        }
        ksort($houses, SORT_NATURAL);

        $dasha = $this->findFirstValueByKey($planetPayload, ['dasha', 'dasa', 'birth_dasha', 'current_dasha']);
        if ($dasha === null) {
            $dasha = $this->findFirstValueByKey($ascendantPayload, ['dasha', 'dasa', 'birth_dasha', 'current_dasha']);
        }

        return [
            'lagna' => $lagna,
            'rashi' => $rashi,
            'nakshatra' => $nakshatra,
            'planetary_data' => $planets,
            'house_data' => $houses,
            'dasha_data' => $dasha,
            'chart_data' => [
                'ascendant' => $ascendant,
                'planet_count' => count($planets),
            ],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function findPlanetObjects(array $payload): array
    {
        $found = [];
        $walk = function (mixed $value) use (&$walk, &$found): void {
            if (!is_array($value)) return;
            if (isset($value['name']) && is_string($value['name']) && (array_key_exists('local_degree', $value) || array_key_exists('global_degree', $value))) {
                $found[] = $value;
            }
            foreach ($value as $child) $walk($child);
        };
        $walk($payload);
        return $found;
    }

    /** @return array<string,mixed>|null */
    private function findFirstObjectWithKey(array $payload, string $key): ?array
    {
        $found = null;
        $walk = function (mixed $value) use (&$walk, &$found, $key): void {
            if ($found !== null || !is_array($value)) return;
            if (array_key_exists($key, $value) && is_array($value)) {
                $found = $value;
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

    private function firstNonEmpty(array $values): mixed
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') return $value;
        }
        return null;
    }
}
