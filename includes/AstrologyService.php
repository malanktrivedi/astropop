<?php
declare(strict_types=1);

require_once __DIR__ . '/VedicAstroAPI.php';

final class AstrologyService
{
    public function __construct(private VedicAstroAPI $api) {}

    /** @return array{ok:bool,status:int,results:list<array<string,mixed>>,error:string|null} */
    public function resolveBirthPlace(string $city): array
    {
        $result = $this->api->request('/utilities/geo-search', 'GET', ['city' => $city, 'lang' => 'en']);
        if (!$result['ok']) {
            return ['ok' => false, 'status' => $result['status'], 'results' => [], 'error' => $result['error']];
        }
        $results = $this->extractGeoResults($result['data'] ?? []);
        if (!$results) {
            return ['ok' => false, 'status' => $result['status'], 'results' => [], 'error' => 'No matching birth location was returned by the astrology service.'];
        }
        return ['ok' => true, 'status' => $result['status'], 'results' => $results, 'error' => null];
    }

    /** @return array{ok:bool,status:int,data:array<string,mixed>|null,error:string|null} */
    public function planetDetails(string $dob, string $tob, float $lat, float $lon, float $tz): array
    {
        return $this->birthRequest('/horoscope/planet-details', $dob, $tob, $lat, $lon, $tz);
    }

    /** @return array{ok:bool,status:int,data:array<string,mixed>|null,error:string|null} */
    public function ascendantReport(string $dob, string $tob, float $lat, float $lon, float $tz): array
    {
        return $this->birthRequest('/horoscope/ascendant-report', $dob, $tob, $lat, $lon, $tz);
    }

    /** @return list<array<string,mixed>> */
    private function extractGeoResults(array $payload): array
    {
        $candidates = [];
        $walk = function (mixed $value) use (&$walk, &$candidates): void {
            if (!is_array($value)) return;
            if (isset($value['coordinates']) && is_array($value['coordinates']) && count($value['coordinates']) >= 2
                && (isset($value['name']) || isset($value['full_name']) || isset($value['alternate_name']))) {
                $candidates[] = $value;
            }
            foreach ($value as $child) $walk($child);
        };
        $walk($payload);

        $unique = [];
        foreach ($candidates as $item) {
            $coordinates = $item['coordinates'];
            $lat = filter_var($coordinates[0], FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
            $lon = filter_var($coordinates[1], FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
            if ($lat === null || $lon === null) continue;
            $offset = filter_var($item['tz'] ?? $item['tz_dst'] ?? null, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
            $key = number_format((float) $lat, 7, '.', '') . ':' . number_format((float) $lon, 7, '.', '');
            $unique[$key] ??= [
                'name' => (string) ($item['name'] ?? $item['full_name'] ?? $item['alternate_name'] ?? 'Unknown'),
                'full_name' => (string) ($item['full_name'] ?? $item['name'] ?? ''),
                'latitude' => (float) $lat,
                'longitude' => (float) $lon,
                'timezone' => $item['timezone'] ?? $item['time_zone'] ?? $item['tzone'] ?? null,
                'timezone_offset' => $offset,
            ];
        }
        return array_values($unique);
    }

    /** @return array{ok:bool,status:int,data:array<string,mixed>|null,error:string|null} */
    private function birthRequest(string $route, string $dob, string $tob, float $lat, float $lon, float $tz): array
    {
        return $this->api->request($route, 'GET', [
            'dob' => $dob,
            'tob' => $tob,
            'lat' => $this->formatNumber($lat),
            'lon' => $this->formatNumber($lon),
            'tz' => $this->formatNumber($tz),
            'lang' => 'en',
        ]);
    }

    private function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 7, '.', ''), '0'), '.');
    }
}
