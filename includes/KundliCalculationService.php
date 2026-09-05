<?php
declare(strict_types=1);

require_once __DIR__ . '/VedicAstroAPI.php';
require_once __DIR__ . '/AstrologyService.php';
require_once __DIR__ . '/KundliNormalizer.php';

final class KundliCalculationService
{
    public const ENGINE_VERSION = '1.1.0';
    public const API_VERSION = 'v3-json';

    public function __construct(private AstrologyService $astro) {}

    /** @param array<string,mixed> $profile */
    public function calculateAndStore(int $userId, array $profile): array
    {
        $profileId = (int) ($profile['id'] ?? 0);
        if ($profileId <= 0) return ['ok'=>false,'calculation_id'=>null,'hash'=>null,'warnings'=>[],'error'=>'Birth profile not found.'];
        if (($profile['time_of_birth'] ?? null) === null) return ['ok'=>false,'calculation_id'=>null,'hash'=>null,'warnings'=>[],'error'=>'An exact birth time is required for this Kundli calculation.'];
        foreach (['latitude','longitude','timezone'] as $field) {
            if (($profile[$field] ?? null) === null) return ['ok'=>false,'calculation_id'=>null,'hash'=>null,'warnings'=>[],'error'=>'Birth location must be resolved before calculating the Kundli.'];
        }

        $dobApi = (new DateTime((string) $profile['date_of_birth']))->format('d/m/Y');
        $tobApi = substr((string) $profile['time_of_birth'], 0, 5);
        $lat = (float) $profile['latitude']; $lon = (float) $profile['longitude']; $tz = (float) $profile['timezone'];
        // Source-cache identity contains only source inputs + API version. Local engine changes must not cause paid API recalculation.
        $hash = hash('sha256', implode('|', [$profileId, $dobApi, $tobApi, $lat, $lon, $tz, self::API_VERSION]));

        $planet = $this->astro->planetDetails($dobApi, $tobApi, $lat, $lon, $tz);
        if (!$planet['ok']) return ['ok'=>false,'calculation_id'=>null,'hash'=>$hash,'warnings'=>[],'error'=>'Kundli planet calculation failed: ' . (string) $planet['error']];
        $ascendant = $this->astro->ascendantReport($dobApi, $tobApi, $lat, $lon, $tz);
        if (!$ascendant['ok']) return ['ok'=>false,'calculation_id'=>null,'hash'=>$hash,'warnings'=>[],'error'=>'Ascendant calculation failed: ' . (string) $ascendant['error']];

        $normalized = (new KundliNormalizer())->normalize($planet['data'] ?? [], $ascendant['data'] ?? []);
        $planetary = is_array($normalized['planetary_data'] ?? null) ? $normalized['planetary_data'] : [];
        $chart = is_array($normalized['chart_data'] ?? null) ? $normalized['chart_data'] : [];
        if (count($planetary) < 5 || !is_array($chart['ascendant'] ?? null)) return ['ok'=>false,'calculation_id'=>null,'hash'=>$hash,'warnings'=>[],'error'=>'The astrology service returned incomplete canonical chart data.'];

        $dasha = null; $warnings = [];
        $dashaResult = $this->astro->mahaDasha($dobApi, $tobApi, $lat, $lon, $tz);
        if ($dashaResult['ok']) {
            $response = $dashaResult['data']['response'] ?? null;
            if (is_array($response) && !empty($response['mahadasha']) && !empty($response['mahadasha_order'])) {
                $dasha = ['mahadasha'=>array_values($response['mahadasha']),'mahadasha_order'=>array_values($response['mahadasha_order']),'start_year'=>$response['start_year'] ?? null,'dasha_start_date'=>$response['dasha_start_date'] ?? null,'dasha_remaining_at_birth'=>$response['dasha_remaining_at_birth'] ?? null];
            } else $warnings[] = 'Maha Dasha response was returned but did not contain the expected timeline.';
        } else $warnings[] = 'Maha Dasha could not be cached: ' . (string) $dashaResult['error'];

        $apiResponse = ['planet_details'=>$planet['data'] ?? [],'ascendant_report'=>$ascendant['data'] ?? []];
        if ($dashaResult['ok']) $apiResponse['maha_dasha'] = $dashaResult['data'] ?? [];
        $planetaryJson = json_encode($planetary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $houseJson = json_encode($normalized['house_data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $dashaJson = $dasha === null ? null : json_encode($dasha, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $chartJson = json_encode($chart, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $apiJson = json_encode($apiResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $lagna = $normalized['lagna'] === null ? null : (string) $normalized['lagna'];
        $rashi = $normalized['rashi'] === null ? null : (string) $normalized['rashi'];
        $nakshatra = $normalized['nakshatra'] === null ? null : (string) $normalized['nakshatra'];
        $engineVersion = self::ENGINE_VERSION; $apiVersion = self::API_VERSION;

        $stmt = db()->prepare('SELECT id FROM kundli_calculations WHERE birth_profile_id=? AND calculation_hash=? LIMIT 1');
        $stmt->bind_param('is', $profileId, $hash); $stmt->execute(); $existing = $stmt->get_result()->fetch_assoc(); $stmt->close();

        if ($existing) {
            $id = (int) $existing['id'];
            $stmt = db()->prepare('UPDATE kundli_calculations SET lagna=?,rashi=?,nakshatra=?,planetary_data=?,house_data=?,dasha_data=?,chart_data=?,api_response=?,engine_version=?,api_version=?,calculated_at=CURRENT_TIMESTAMP WHERE id=? AND user_id=?');
            $stmt->bind_param('ssssssssssii', $lagna, $rashi, $nakshatra, $planetaryJson, $houseJson, $dashaJson, $chartJson, $apiJson, $engineVersion, $apiVersion, $id, $userId);
        } else {
            $stmt = db()->prepare('INSERT INTO kundli_calculations (user_id,birth_profile_id,lagna,rashi,nakshatra,planetary_data,house_data,dasha_data,chart_data,api_response,calculation_hash,engine_version,api_version,calculated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP)');
            $stmt->bind_param('iisssssssssss', $userId, $profileId, $lagna, $rashi, $nakshatra, $planetaryJson, $houseJson, $dashaJson, $chartJson, $apiJson, $hash, $engineVersion, $apiVersion);
        }
        if (!$stmt->execute()) {
            $error = db()->error; $stmt->close();
            return ['ok'=>false,'calculation_id'=>null,'hash'=>$hash,'warnings'=>$warnings,'error'=>'The calculation was completed but could not be saved: ' . $error];
        }
        if (!$existing) $id = (int) db()->insert_id;
        $stmt->close();
        return ['ok'=>true,'calculation_id'=>$id,'hash'=>$hash,'warnings'=>$warnings,'error'=>null];
    }
}
