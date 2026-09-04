<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/NorthIndianChart.php';
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
    $stmt = db()->prepare('SELECT lagna, rashi, nakshatra, planetary_data, house_data FROM kundli_calculations WHERE birth_profile_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('i', $profileId); $stmt->execute(); $calculation = $stmt->get_result()->fetch_assoc(); $stmt->close();
}

if (!$profile || !$calculation) {
    http_response_code(404);
    exit('Kundli not found. Generate a Kundli from your birth profile first.');
}

$planetary = json_decode((string) $calculation['planetary_data'], true) ?: [];
$houses = json_decode((string) $calculation['house_data'], true) ?: [];
function d1Value(mixed $value): string { return $value === null || $value === '' ? '—' : e(is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); }
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>D1 Rashi Chart — ASTROPOP</title><link rel="stylesheet" href="<?= e(APP_BASE_PATH) ?>/assets/css/app.css"></head>
<body><main class="app-shell"><header class="topbar"><a class="brand" href="<?= e(APP_BASE_PATH) ?>/dashboard.php">✦ ASTROPOP</a><nav><a href="<?= e(APP_BASE_PATH) ?>/kundli.php?profile_id=<?= $profileId ?>">Kundli</a><a href="<?= e(APP_BASE_PATH) ?>/birth/">Birth profile</a><a href="<?= e(APP_BASE_PATH) ?>/logout.php">Log out</a></nav></header>
<section class="content-wrap"><div class="section-heading"><p class="eyebrow">VEDIC ASTROLOGY · D1</p><h1>Rashi / Lagna Chart</h1><p class="muted"><?= e((string) $profile['full_name']) ?> · <?= e((string) ($profile['location_name'] ?: $profile['birth_place'])) ?> · North Indian format</p></div>
<section class="card chart-card"><div class="row-between"><div><p class="eyebrow">JANMA KUNDALI</p><h2>Birth Chart (D1)</h2></div><span class="pill pill-success">API normalized</span></div><?= renderNorthIndianChart($houses, $calculation['lagna']) ?></section>
<section class="grid-2"><article class="card"><p class="eyebrow">CHART BASICS</p><div class="detail-grid detail-grid-3"><div><span>Lagna</span><strong><?= d1Value($calculation['lagna']) ?></strong></div><div><span>Moon Rashi</span><strong><?= d1Value($calculation['rashi']) ?></strong></div><div><span>Janma Nakshatra</span><strong><?= d1Value($calculation['nakshatra']) ?></strong></div></div></article><article class="card"><p class="eyebrow">BIRTH DATA</p><div class="detail-grid detail-grid-3"><div><span>Date</span><strong><?= e((string) $profile['date_of_birth']) ?></strong></div><div><span>Time</span><strong><?= e((string) $profile['time_of_birth']) ?></strong></div><div><span>UTC</span><strong><?= d1Value($profile['timezone']) ?></strong></div></div></article></section>
<section class="card"><div class="row-between"><div><p class="eyebrow">GRAHA PLACEMENT</p><h2>Planets by house</h2></div><a class="button button-secondary" href="<?= e(APP_BASE_PATH) ?>/kundli.php?profile_id=<?= $profileId ?>">Full Kundli</a></div><div class="table-wrap"><table><thead><tr><th>House</th><th>Rashi</th><th>Planets</th></tr></thead><tbody><?php $signs=['Aries','Taurus','Gemini','Cancer','Leo','Virgo','Libra','Scorpio','Sagittarius','Capricorn','Aquarius','Pisces']; $signNoMap=array_flip(array_map('strtolower',$signs)); $lagnaNo=$signNoMap[strtolower((string)$calculation['lagna'])] ?? 0; for($h=1;$h<=12;$h++): $signNo=(($lagnaNo+$h-1)%12)+1; $items=$houses[(string)$h]??$houses[$h]??[]; ?><tr><td><strong><?= $h ?></strong></td><td><?= e($signs[$signNo-1]) ?> <span class="muted">(<?= $signNo ?>)</span></td><td><?= $items ? e(implode(', ', array_map('strval', $items))) : '<span class="muted">Empty</span>' ?></td></tr><?php endfor; ?></tbody></table></div></section>
</section></main></body></html>
