<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/NorthIndianChart.php';
require_once __DIR__ . '/../includes/NavamsaCalculator.php';
requireLogin();

$uid = current_user_id();
$profileId = (int) ($_GET['profile_id'] ?? 0);
if ($profileId <= 0) {
    $stmt = db()->prepare('SELECT id FROM birth_profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('i', $uid); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    $profileId = (int) ($row['id'] ?? 0);
}

$stmt = db()->prepare('SELECT id, profile_name, full_name, date_of_birth, time_of_birth, location_name, birth_place, latitude, longitude, timezone FROM birth_profiles WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->bind_param('ii', $profileId, $uid); $stmt->execute(); $profile = $stmt->get_result()->fetch_assoc(); $stmt->close();

$calculation = null;
if ($profile) {
    $stmt = db()->prepare('SELECT lagna, rashi, nakshatra, planetary_data, chart_data FROM kundli_calculations WHERE birth_profile_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('i', $profileId); $stmt->execute(); $calculation = $stmt->get_result()->fetch_assoc(); $stmt->close();
}

if (!$profile || !$calculation) {
    http_response_code(404);
    exit('Kundli not found. Generate a Kundli from your birth profile first.');
}

$planetary = json_decode((string) $calculation['planetary_data'], true) ?: [];
$chartData = json_decode((string) $calculation['chart_data'], true) ?: [];
$d9 = (new NavamsaCalculator())->calculate($planetary, $chartData, $calculation['lagna'] !== null ? (string) $calculation['lagna'] : null);

function d9Value(mixed $value): string {
    return $value === null || $value === '' ? '—' : e(is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
function d9Deg(float $degree): string {
    $d = floor($degree); $minutes = round(($degree - $d) * 60);
    if ($minutes >= 60) { $d++; $minutes = 0; }
    return sprintf('%02d°%02d′', $d, $minutes);
}
$houses = $d9['houses'];
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>D9 Navamsa Chart — ASTROPOP</title><link rel="stylesheet" href="<?= e(APP_BASE_PATH) ?>/assets/css/app.css"></head>
<body><main class="app-shell"><header class="topbar"><a class="brand" href="<?= e(APP_BASE_PATH) ?>/dashboard.php">✦ ASTROPOP</a><nav><a href="<?= e(APP_BASE_PATH) ?>/kundli.php?profile_id=<?= $profileId ?>">Kundli</a><a href="<?= e(APP_BASE_PATH) ?>/d1-chart.php?profile_id=<?= $profileId ?>">D1 Rashi</a><a href="<?= e(APP_BASE_PATH) ?>/logout.php">Log out</a></nav></header>
<section class="content-wrap"><div class="section-heading"><p class="eyebrow">VEDIC ASTROLOGY · D9</p><h1>Navamsa Chart</h1><p class="muted"><?= e((string) $profile['full_name']) ?> · <?= e((string) ($profile['location_name'] ?: $profile['birth_place'])) ?> · North Indian format</p></div>
<section class="card chart-card"><div class="row-between"><div><p class="eyebrow">NAVAMSA KUNDALI</p><h2>Navamsa Chart (D9)</h2></div><span class="pill pill-success">Calculated from D1 degrees</span></div><?= renderNorthIndianChart($houses, $d9['lagna'], 'D9 · NAVAMSA') ?><p class="muted chart-note">Each Rashi sign is divided into nine 3°20′ Navamsas. D9 signs are calculated from the stored sidereal planetary longitudes.</p></section>
<section class="grid-2"><article class="card"><p class="eyebrow">D9 BASICS</p><div class="detail-grid detail-grid-3"><div><span>D9 Lagna</span><strong><?= d9Value($d9['lagna']) ?></strong></div><div><span>D1 Lagna</span><strong><?= d9Value($calculation['lagna']) ?></strong></div><div><span>D1 Moon Rashi</span><strong><?= d9Value($calculation['rashi']) ?></strong></div></div></article><article class="card"><p class="eyebrow">BIRTH DATA</p><div class="detail-grid detail-grid-3"><div><span>Date</span><strong><?= e((string) $profile['date_of_birth']) ?></strong></div><div><span>Time</span><strong><?= e((string) $profile['time_of_birth']) ?></strong></div><div><span>UTC</span><strong><?= d9Value($profile['timezone']) ?></strong></div></div></article></section>
<section class="card"><div class="row-between"><div><p class="eyebrow">NAVAMSA PLACEMENT</p><h2>Planetary D1 → D9</h2></div><a class="button button-secondary" href="<?= e(APP_BASE_PATH) ?>/d1-chart.php?profile_id=<?= $profileId ?>">D1 Rashi</a></div><div class="table-wrap"><table><thead><tr><th>Planet</th><th>D1 Rashi</th><th>D1 Degree</th><th>D9 Rashi</th><th>D9 Degree</th><th>House</th><th>Vargottama</th></tr></thead><tbody><?php foreach ($d9['positions'] as $position): ?><tr><td><strong><?= e((string) $position['name']) ?></strong></td><td><?= d9Value($position['d1_sign']) ?></td><td><?= d9Deg((float) $position['d1_degree']) ?></td><td><?= d9Value($position['d9_sign']) ?></td><td><?= d9Deg((float) $position['d9_degree']) ?></td><td><?= isset($position['house']) ? (int) $position['house'] : '—' ?></td><td><?= !empty($position['vargottama']) ? '<span class="pill pill-success">Yes</span>' : '—' ?></td></tr><?php endforeach; ?></tbody></table></div></section>
</section></main></body></html>
