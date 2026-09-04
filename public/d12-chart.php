<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/NorthIndianChart.php';
require_once __DIR__ . '/../includes/DwadasamsaCalculator.php';
requireLogin();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$uid = current_user_id();
$profileId = (int) ($_GET['profile_id'] ?? 0);
if ($profileId <= 0) {
    $stmt = db()->prepare('SELECT id FROM birth_profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('i', $uid); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    $profileId = (int) ($row['id'] ?? 0);
}

$stmt = db()->prepare('SELECT id, profile_name, full_name, date_of_birth, time_of_birth, location_name, birth_place, timezone FROM birth_profiles WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->bind_param('ii', $profileId, $uid); $stmt->execute(); $profile = $stmt->get_result()->fetch_assoc(); $stmt->close();

$calculation = null;
if ($profile) {
    $stmt = db()->prepare('SELECT id, lagna, rashi, planetary_data, chart_data, api_response FROM kundli_calculations WHERE birth_profile_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('i', $profileId); $stmt->execute(); $calculation = $stmt->get_result()->fetch_assoc(); $stmt->close();
}
if (!$profile || !$calculation) { http_response_code(404); exit('Kundli not found. Generate a Kundli from your birth profile first.'); }

$planetary = json_decode((string) $calculation['planetary_data'], true) ?: [];
$chartData = json_decode((string) $calculation['chart_data'], true) ?: [];
$apiResponse = json_decode((string) $calculation['api_response'], true) ?: [];

$chartAscendant = is_array($chartData['ascendant'] ?? null) ? $chartData['ascendant'] : null;
if (!d12HasLongitude($chartAscendant)) {
    $apiAscendant = d12FindAscendantObject($apiResponse);
    if ($apiAscendant !== null) { $chartAscendant = $apiAscendant; $chartData['ascendant'] = $apiAscendant; }
}
if (!d12HasLongitude($chartAscendant)) {
    $ascLocal = d12FindNumericByKey($apiResponse, ['local_degree','degree_in_sign','sign_degree','degree']);
    if ($ascLocal !== null && $calculation['lagna'] !== null) {
        $chartData['ascendant'] = ['name' => 'Ascendant', 'rashi' => (string) $calculation['lagna'], 'local_degree' => $ascLocal];
    }
}

$d12 = (new DwadasamsaCalculator())->calculate($planetary, $chartData, $calculation['lagna'] !== null ? (string) $calculation['lagna'] : null);
$houses = $d12['houses'];

function d12HasLongitude(mixed $value): bool {
    if (!is_array($value)) return false;
    foreach (['global_degree','longitude','sidereal_longitude','absolute_degree','local_degree','degree_in_sign','sign_degree','degree'] as $key) {
        if (isset($value[$key]) && is_numeric($value[$key])) return true;
    }
    return false;
}
function d12FindAscendantObject(mixed $value): ?array {
    if (!is_array($value)) return null;
    foreach ($value as $key => $child) {
        if (strcasecmp((string) $key, 'ascendant') === 0) {
            if (is_array($child) && d12HasLongitude($child)) return $child;
            if (is_numeric($child)) return ['name' => 'Ascendant', 'local_degree' => (float) $child];
        }
        if (is_array($child)) {
            if (isset($child['name']) && is_string($child['name']) && strcasecmp(trim($child['name']), 'ascendant') === 0 && d12HasLongitude($child)) return $child;
            $found = d12FindAscendantObject($child);
            if ($found !== null) return $found;
        }
    }
    return null;
}
function d12FindNumericByKey(mixed $value, array $keys): ?float {
    if (!is_array($value)) return null;
    $wanted = array_map('strtolower', $keys);
    foreach ($value as $key => $child) {
        if (in_array(strtolower((string) $key), $wanted, true) && is_numeric($child)) return (float) $child;
        if (is_array($child)) {
            $found = d12FindNumericByKey($child, $keys);
            if ($found !== null) return $found;
        }
    }
    return null;
}
function d12Value(mixed $value): string { return $value === null || $value === '' ? '—' : e(is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); }
function d12Deg(float $degree): string { $d = floor($degree); $minutes = round(($degree - $d) * 60); if ($minutes >= 60) { $d++; $minutes = 0; } return sprintf('%02d°%02d′', $d, $minutes); }
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>D12 Dwadasamsa Chart — ASTROPOP</title><link rel="stylesheet" href="<?= e(APP_BASE_PATH) ?>/assets/css/app.css"></head>
<body><main class="app-shell"><header class="topbar"><a class="brand" href="<?= e(APP_BASE_PATH) ?>/dashboard.php">✦ ASTROPOP</a><nav><a href="<?= e(APP_BASE_PATH) ?>/kundli.php?profile_id=<?= $profileId ?>">Kundli</a><a href="<?= e(APP_BASE_PATH) ?>/d1-chart.php?profile_id=<?= $profileId ?>">D1 Rashi</a><a href="<?= e(APP_BASE_PATH) ?>/d7-chart.php?profile_id=<?= $profileId ?>">D7 Saptamsa</a><a href="<?= e(APP_BASE_PATH) ?>/d9-chart.php?profile_id=<?= $profileId ?>">D9 Navamsa</a><a href="<?= e(APP_BASE_PATH) ?>/d10-chart.php?profile_id=<?= $profileId ?>">D10 Dasamsa</a><a href="<?= e(APP_BASE_PATH) ?>/logout.php">Log out</a></nav></header>
<section class="content-wrap"><div class="section-heading"><p class="eyebrow">VEDIC ASTROLOGY · D12</p><h1>Dwadasamsa Chart</h1><p class="muted"><?= e((string) $profile['full_name']) ?> · <?= e((string) ($profile['location_name'] ?: $profile['birth_place'])) ?> · North Indian format</p></div>
<section class="card chart-card"><div class="row-between"><div><p class="eyebrow">DWADASHAMSHA KUNDALI</p><h2>Dwadasamsa Chart (D12)</h2></div><span class="pill pill-success">Calculated from D1 degrees</span></div><?= renderNorthIndianChart($houses, $d12['lagna'], 'D12 · DWADASAMSA') ?><p class="muted chart-note">Each Rashi is divided into twelve 2°30′ Dwadasamsas. The divisions run successively from the D1 sign itself.</p></section>
<section class="grid-2"><article class="card"><p class="eyebrow">D12 BASICS</p><div class="detail-grid detail-grid-3"><div><span>D12 Lagna</span><strong><?= d12Value($d12['lagna']) ?></strong></div><div><span>D1 Lagna</span><strong><?= d12Value($calculation['lagna']) ?></strong></div><div><span>D1 Moon Rashi</span><strong><?= d12Value($calculation['rashi']) ?></strong></div></div></article><article class="card"><p class="eyebrow">BIRTH DATA</p><div class="detail-grid detail-grid-3"><div><span>Date</span><strong><?= e((string) $profile['date_of_birth']) ?></strong></div><div><span>Time</span><strong><?= e((string) $profile['time_of_birth']) ?></strong></div><div><span>UTC</span><strong><?= d12Value($profile['timezone']) ?></strong></div></div></article></section>
<section class="card"><div class="row-between"><div><p class="eyebrow">DWADASAMSA PLACEMENT</p><h2>Planetary D1 → D12</h2></div><a class="button button-secondary" href="<?= e(APP_BASE_PATH) ?>/d10-chart.php?profile_id=<?= $profileId ?>">D10 Dasamsa</a></div><div class="table-wrap"><table><thead><tr><th>Planet</th><th>D1 Rashi</th><th>D1 Degree</th><th>Part</th><th>D12 Rashi</th><th>D12 Degree</th><th>House</th></tr></thead><tbody><?php foreach ($d12['positions'] as $position): ?><tr><td><strong><?= e((string) $position['name']) ?></strong></td><td><?= d12Value($position['d1_sign']) ?></td><td><?= d12Deg((float) $position['d1_degree']) ?></td><td><?= (int) $position['part'] ?></td><td><?= d12Value($position['d12_sign']) ?></td><td><?= d12Deg((float) $position['d12_degree']) ?></td><td><?= isset($position['house']) ? (int) $position['house'] : '—' ?></td></tr><?php endforeach; ?></tbody></table></div></section>
</section></main></body></html>
