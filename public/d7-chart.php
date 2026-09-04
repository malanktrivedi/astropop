<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/NorthIndianChart.php';
require_once __DIR__ . '/../includes/SaptamsaCalculator.php';
requireLogin();

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
if (!d7HasLongitude($chartAscendant)) {
    $apiAscendant = d7FindAscendantObject($apiResponse);
    if ($apiAscendant !== null) { $chartAscendant = $apiAscendant; $chartData['ascendant'] = $apiAscendant; }
}
if (!d7HasLongitude($chartAscendant)) {
    $ascLocal = d7FindNumericByKey($apiResponse, ['local_degree','degree_in_sign','sign_degree','degree']);
    if ($ascLocal !== null && $calculation['lagna'] !== null) {
        $chartData['ascendant'] = ['name' => 'Ascendant', 'rashi' => (string) $calculation['lagna'], 'local_degree' => $ascLocal];
    }
}

$d7 = (new SaptamsaCalculator())->calculate($planetary, $chartData, $calculation['lagna'] !== null ? (string) $calculation['lagna'] : null);

function d7HasLongitude(mixed $value): bool { if (!is_array($value)) return false; foreach (['global_degree','longitude','sidereal_longitude','absolute_degree','local_degree','degree_in_sign','sign_degree','degree'] as $key) if (isset($value[$key]) && is_numeric($value[$key])) return true; return false; }
function d7FindAscendantObject(mixed $value): ?array {
    if (!is_array($value)) return null;
    foreach ($value as $key => $child) {
        if (strcasecmp((string) $key, 'ascendant') === 0) {
            if (is_array($child) && d7HasLongitude($child)) return $child;
            if (is_numeric($child)) return ['name' => 'Ascendant', 'local_degree' => (float) $child];
        }
        if (is_array($child)) {
            if (isset($child['name']) && is_string($child['name']) && strcasecmp(trim($child['name']), 'ascendant') === 0 && d7HasLongitude($child)) return $child;
            $found = d7FindAscendantObject($child); if ($found !== null) return $found;
        }
    }
    return null;
}
function d7FindNumericByKey(mixed $value, array $keys): ?float {
    if (!is_array($value)) return null; $wanted = array_map('strtolower', $keys);
    foreach ($value as $key => $child) {
        if (in_array(strtolower((string) $key), $wanted, true) && is_numeric($child)) return (float) $child;
        if (is_array($child)) { $found = d7FindNumericByKey($child, $keys); if ($found !== null) return $found; }
    }
    return null;
}
function d7Value(mixed $value): string { return $value === null || $value === '' ? '—' : e(is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); }
function d7Deg(float $degree): string { $d = floor($degree); $minutes = round(($degree - $d) * 60); if ($minutes >= 60) { $d++; $minutes = 0; } return sprintf('%02d°%02d′', $d, $minutes); }
$houses = $d7['houses'];
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>D7 Saptamsa Chart — ASTROPOP</title><link rel="stylesheet" href="<?= e(APP_BASE_PATH) ?>/assets/css/app.css"></head>
<body><main class="app-shell"><header class="topbar"><a class="brand" href="<?= e(APP_BASE_PATH) ?>/dashboard.php">✦ ASTROPOP</a><nav><a href="<?= e(APP_BASE_PATH) ?>/kundli.php?profile_id=<?= $profileId ?>">Kundli</a><a href="<?= e(APP_BASE_PATH) ?>/d1-chart.php?profile_id=<?= $profileId ?>">D1 Rashi</a><a href="<?= e(APP_BASE_PATH) ?>/d9-chart.php?profile_id=<?= $profileId ?>">D9 Navamsa</a><a href="<?= e(APP_BASE_PATH) ?>/d10-chart.php?profile_id=<?= $profileId ?>">D10 Dasamsa</a><a href="<?= e(APP_BASE_PATH) ?>/logout.php">Log out</a></nav></header>
<section class="content-wrap"><div class="section-heading"><p class="eyebrow">VEDIC ASTROLOGY · D7</p><h1>Saptamsa Chart</h1><p class="muted"><?= e((string) $profile['full_name']) ?> · <?= e((string) ($profile['location_name'] ?: $profile['birth_place'])) ?> · North Indian format</p></div>
<section class="card chart-card"><div class="row-between"><div><p class="eyebrow">SAPTAMSHA KUNDALI</p><h2>Saptamsa Chart (D7)</h2></div><span class="pill pill-success">Calculated from D1 degrees</span></div><?= renderNorthIndianChart($houses, $d7['lagna'], 'D7 · SAPTAMSA') ?><p class="muted chart-note">Each Rashi is divided into seven 4°17′09″ Saptamsas. Odd signs count from themselves; even signs count from the 7th sign.</p></section>
<section class="grid-2"><article class="card"><p class="eyebrow">D7 BASICS</p><div class="detail-grid detail-grid-3"><div><span>D7 Lagna</span><strong><?= d7Value($d7['lagna']) ?></strong></div><div><span>D1 Lagna</span><strong><?= d7Value($calculation['lagna']) ?></strong></div><div><span>D1 Moon Rashi</span><strong><?= d7Value($calculation['rashi']) ?></strong></div></div></article><article class="card"><p class="eyebrow">BIRTH DATA</p><div class="detail-grid detail-grid-3"><div><span>Date</span><strong><?= e((string) $profile['date_of_birth']) ?></strong></div><div><span>Time</span><strong><?= e((string) $profile['time_of_birth']) ?></strong></div><div><span>UTC</span><strong><?= d7Value($profile['timezone']) ?></strong></div></div></article></section>
<section class="card"><div class="row-between"><div><p class="eyebrow">SAPTAMSA PLACEMENT</p><h2>Planetary D1 → D7</h2></div><a class="button button-secondary" href="<?= e(APP_BASE_PATH) ?>/d10-chart.php?profile_id=<?= $profileId ?>">D10 Dasamsa</a></div><div class="table-wrap"><table><thead><tr><th>Planet</th><th>D1 Rashi</th><th>D1 Degree</th><th>Part</th><th>D7 Rashi</th><th>D7 Degree</th><th>House</th><th>Saptamsa</th></tr></thead><tbody><?php foreach ($d7['positions'] as $position): ?><tr><td><strong><?= e((string) $position['name']) ?></strong></td><td><?= d7Value($position['d1_sign']) ?></td><td><?= d7Deg((float) $position['d1_degree']) ?></td><td><?= (int) $position['part'] ?></td><td><?= d7Value($position['d7_sign']) ?></td><td><?= d7Deg((float) $position['d7_degree']) ?></td><td><?= isset($position['house']) ? (int) $position['house'] : '—' ?></td><td><?= d7Value($position['quality']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
</section></main></body></html>
