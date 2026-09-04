<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/VedicAstroAPI.php';
require_once __DIR__ . '/../includes/AstrologyService.php';
requireLogin();

$uid = current_user_id();
$profileId = (int) ($_GET['profile_id'] ?? 0);
if ($profileId <= 0) {
    $stmt = db()->prepare('SELECT id FROM birth_profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('i', $uid); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    $profileId = (int) ($row['id'] ?? 0);
}

$stmt = db()->prepare('SELECT id, profile_name, full_name, date_of_birth, time_of_birth, birth_place, location_name, latitude, longitude, timezone FROM birth_profiles WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->bind_param('ii', $profileId, $uid); $stmt->execute(); $profile = $stmt->get_result()->fetch_assoc(); $stmt->close();

$calculation = null;
if ($profile) {
    $stmt = db()->prepare('SELECT * FROM kundli_calculations WHERE birth_profile_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('i', $profileId); $stmt->execute(); $calculation = $stmt->get_result()->fetch_assoc(); $stmt->close();
}

if (!$profile || !$calculation) {
    http_response_code(404);
    exit('Kundli not found. Generate a Kundli from your birth profile first.');
}

$planetary = json_decode((string) ($calculation['planetary_data'] ?? '[]'), true) ?: [];
$houses = json_decode((string) ($calculation['house_data'] ?? '{}'), true) ?: [];
$dasha = json_decode((string) ($calculation['dasha_data'] ?? 'null'), true);
$chart = json_decode((string) ($calculation['chart_data'] ?? '{}'), true) ?: [];

/*
 * Maha Dasha is a separate documented endpoint. Older cached calculations were
 * created before that endpoint was integrated, so hydrate dasha_data lazily here.
 * This keeps existing calculations usable without forcing a duplicate Kundli row.
 */
if ($dasha === null && $profile['time_of_birth'] !== null && $profile['latitude'] !== null && $profile['longitude'] !== null && $profile['timezone'] !== null && VEDIC_API_KEY !== '') {
    $dobApi = (new DateTime((string) $profile['date_of_birth']))->format('d/m/Y');
    $tobApi = substr((string) $profile['time_of_birth'], 0, 5);
    $api = new VedicAstroAPI(VEDIC_API_BASE_URL, VEDIC_API_KEY, VEDIC_API_TIMEOUT, VEDIC_API_CONNECT_TIMEOUT);
    $dashaResult = (new AstrologyService($api))->mahaDasha($dobApi, $tobApi, (float) $profile['latitude'], (float) $profile['longitude'], (float) $profile['timezone']);

    if ($dashaResult['ok']) {
        $response = $dashaResult['data']['response'] ?? null;
        if (is_array($response) && !empty($response['mahadasha']) && !empty($response['mahadasha_order'])) {
            $dasha = [
                'mahadasha' => array_values($response['mahadasha']),
                'mahadasha_order' => array_values($response['mahadasha_order']),
                'start_year' => $response['start_year'] ?? null,
                'dasha_start_date' => $response['dasha_start_date'] ?? null,
                'dasha_remaining_at_birth' => $response['dasha_remaining_at_birth'] ?? null,
            ];

            $rawApi = json_decode((string) ($calculation['api_response'] ?? '{}'), true);
            if (!is_array($rawApi)) $rawApi = [];
            $rawApi['maha_dasha'] = $dashaResult['data'];
            $rawApiJson = json_encode($rawApi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $dashaJson = json_encode($dasha, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $stmt = db()->prepare('UPDATE kundli_calculations SET dasha_data = ?, api_response = ? WHERE id = ? AND user_id = ?');
            $stmt->bind_param('ssii', $dashaJson, $rawApiJson, $calculation['id'], $uid);
            $stmt->execute(); $stmt->close();
        }
    }
}

function display_value(mixed $value): string { return $value === null || $value === '' ? '—' : e(is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); }

$mahaNames = is_array($dasha['mahadasha'] ?? null) ? array_values($dasha['mahadasha']) : [];
$mahaEnds = is_array($dasha['mahadasha_order'] ?? null) ? array_values($dasha['mahadasha_order']) : [];
$currentMahaIndex = null;
$today = new DateTimeImmutable('today');
foreach ($mahaEnds as $index => $endDateText) {
    try {
        $endDate = new DateTimeImmutable((string) $endDateText);
        if ($today <= $endDate) { $currentMahaIndex = $index; break; }
    } catch (Throwable) {
        continue;
    }
}
$mahaStarts = [];
if ($mahaNames) {
    try { $mahaStarts[0] = new DateTimeImmutable((string) ($dasha['dasha_start_date'] ?? '')); } catch (Throwable) { $mahaStarts[0] = null; }
    for ($i = 1; $i < count($mahaNames); $i++) {
        try { $mahaStarts[$i] = new DateTimeImmutable((string) ($mahaEnds[$i - 1] ?? '')); } catch (Throwable) { $mahaStarts[$i] = null; }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Kundli — ASTROPOP</title><link rel="stylesheet" href="<?= e(APP_BASE_PATH) ?>/assets/css/app.css"></head><body><main class="app-shell"><header class="topbar"><a class="brand" href="<?= e(APP_BASE_PATH) ?>/dashboard.php">✦ ASTROPOP</a><nav><a href="<?= e(APP_BASE_PATH) ?>/dashboard.php">Dashboard</a><a href="<?= e(APP_BASE_PATH) ?>/birth/">Birth profile</a><a href="<?= e(APP_BASE_PATH) ?>/logout.php">Log out</a></nav></header><section class="content-wrap"><div class="section-heading"><p class="eyebrow">VEDIC ASTROLOGY</p><h1><?= e((string) $profile['profile_name']) ?>'s Kundli</h1><p class="muted"><?= e((string) $profile['full_name']) ?> · Generated from the configured VedicAstroAPI response.</p></div>
<section class="grid-2"><article class="card"><p class="eyebrow">BIRTH DETAILS</p><div class="detail-grid"><div><span>Date</span><strong><?= e((string) $profile['date_of_birth']) ?></strong></div><div><span>Time</span><strong><?= e((string) ($profile['time_of_birth'] ?? '—')) ?></strong></div><div><span>Place</span><strong><?= e((string) ($profile['location_name'] ?: $profile['birth_place'])) ?></strong></div><div><span>Coordinates / TZ</span><strong><?= display_value($profile['latitude']) ?>, <?= display_value($profile['longitude']) ?> / UTC <?= display_value($profile['timezone']) ?></strong></div></div></article><article class="card"><p class="eyebrow">CORE CHART</p><div class="detail-grid"><div><span>Lagna</span><strong><?= display_value($calculation['lagna']) ?></strong></div><div><span>Rashi</span><strong><?= display_value($calculation['rashi']) ?></strong></div><div><span>Nakshatra</span><strong><?= display_value($calculation['nakshatra']) ?></strong></div><div><span>Planets</span><strong><?= count($planetary) ?></strong></div></div></article></section>
<section class="card"><div class="row-between"><div><p class="eyebrow">PLANETARY POSITIONS</p><h2>Graha details</h2></div><span class="pill pill-success">API normalized</span></div><div class="table-wrap"><table><thead><tr><th>Planet</th><th>Degree</th><th>Rashi</th><th>House</th><th>Nakshatra</th><th>Lord</th><th>Retro</th></tr></thead><tbody><?php foreach ($planetary as $planet): ?><tr><td><strong><?= display_value($planet['full_name'] ?? $planet['name'] ?? null) ?></strong></td><td><?= display_value($planet['local_degree'] ?? null) ?></td><td><?= display_value($planet['rashi'] ?? null) ?></td><td><?= display_value($planet['house'] ?? null) ?></td><td><?= display_value($planet['nakshatra'] ?? null) ?><?= isset($planet['nakshatra_pada']) ? ' · Pada ' . display_value($planet['nakshatra_pada']) : '' ?></td><td><?= display_value($planet['lord'] ?? null) ?></td><td><?= display_value($planet['retrograde'] ?? null) ?></td></tr><?php endforeach; ?><?php if (!$planetary): ?><tr><td colspan="7">No normalized planetary records were returned.</td></tr><?php endif; ?></tbody></table></div></section>
<section class="card"><p class="eyebrow">HOUSES</p><h2>House occupancy</h2><div class="house-grid"><?php for ($i = 1; $i <= 12; $i++): $items = $houses[(string) $i] ?? $houses[$i] ?? []; ?><article><span>House <?= $i ?></span><strong><?= $items ? e(implode(', ', array_map('strval', $items))) : 'Empty' ?></strong></article><?php endfor; ?></div></section>
<section class="card"><div class="row-between"><div><p class="eyebrow">VIMSHOTTARI DASHA</p><h2>Maha Dasha timeline</h2></div><?php if ($currentMahaIndex !== null): ?><span class="pill pill-success">Current: <?= e((string) $mahaNames[$currentMahaIndex]) ?></span><?php endif; ?></div><?php if ($mahaNames && $mahaEnds): ?><div class="table-wrap"><table><thead><tr><th>Maha Dasha</th><th>Start</th><th>End</th><th>Status</th></tr></thead><tbody><?php foreach ($mahaNames as $index => $name): $isCurrent = $currentMahaIndex === $index; ?><tr<?= $isCurrent ? ' class="dasha-current"' : '' ?>><td><strong><?= e((string) $name) ?></strong></td><td><?= isset($mahaStarts[$index]) && $mahaStarts[$index] instanceof DateTimeImmutable ? e($mahaStarts[$index]->format('d M Y')) : '—' ?></td><td><?= isset($mahaEnds[$index]) ? e((string) $mahaEnds[$index]) : '—' ?></td><td><?= $isCurrent ? '<span class="pill pill-success">Current</span>' : '—' ?></td></tr><?php endforeach; ?></tbody></table></div><div class="detail-grid"><div><span>Dasha start</span><strong><?= display_value($dasha['dasha_start_date'] ?? null) ?></strong></div><div><span>Start year</span><strong><?= display_value($dasha['start_year'] ?? null) ?></strong></div><div><span>Balance at birth</span><strong><?= display_value($dasha['dasha_remaining_at_birth'] ?? null) ?></strong></div></div><?php else: ?><p class="muted">Maha Dasha data is not available for this calculation. Generate the Kundli again after configuring the astrology API.</p><?php endif; ?></section>
<details class="card"><summary><strong>Developer: normalized chart payload</strong></summary><pre class="api-output"><?= e(json_encode(['lagna'=>$calculation['lagna'],'rashi'=>$calculation['rashi'],'nakshatra'=>$calculation['nakshatra'],'planetary_data'=>$planetary,'house_data'=>$houses,'dasha_data'=>$dasha,'chart_data'=>$chart], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre></details>
</section></main></body></html>
