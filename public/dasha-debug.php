<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/VedicAstroAPI.php';
require_once __DIR__ . '/../includes/AstrologyService.php';
requireLogin();

$uid = current_user_id();
$profileId = (int) ($_GET['profile_id'] ?? 0);
$stmt = db()->prepare('SELECT id, profile_name, full_name, date_of_birth, time_of_birth, location_name, latitude, longitude, timezone FROM birth_profiles WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->bind_param('ii', $profileId, $uid); $stmt->execute(); $profile = $stmt->get_result()->fetch_assoc(); $stmt->close();

$result = null;
if ($profile && $profile['time_of_birth'] !== null && $profile['latitude'] !== null && $profile['longitude'] !== null && $profile['timezone'] !== null && VEDIC_API_KEY !== '') {
    $dob = (new DateTime((string) $profile['date_of_birth']))->format('d/m/Y');
    $tob = substr((string) $profile['time_of_birth'], 0, 5);
    $api = new VedicAstroAPI(VEDIC_API_BASE_URL, VEDIC_API_KEY, VEDIC_API_TIMEOUT, VEDIC_API_CONNECT_TIMEOUT);
    $result = (new AstrologyService($api))->mahaDasha($dob, $tob, (float) $profile['latitude'], (float) $profile['longitude'], (float) $profile['timezone']);
}

function out_value(mixed $value): string { return $value === null || $value === '' ? '—' : e(is_scalar($value) ? (string) $value : json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Maha Dasha Debug — ASTROPOP</title><link rel="stylesheet" href="<?= e(APP_BASE_PATH) ?>/assets/css/app.css"></head><body><main class="app-shell"><header class="topbar"><a class="brand" href="<?= e(APP_BASE_PATH) ?>/dashboard.php">✦ ASTROPOP</a><nav><a href="<?= e(APP_BASE_PATH) ?>/kundli.php?profile_id=<?= $profileId ?>">Kundli</a><a href="<?= e(APP_BASE_PATH) ?>/dashboard.php">Dashboard</a></nav></header><section class="content-wrap"><div class="section-heading"><p class="eyebrow">DEVELOPMENT</p><h1>Maha Dasha Debug</h1><p class="muted">Tests the documented <code>/dashas/maha-dasha</code> endpoint using the selected birth profile.</p></div><?php if (!$profile): ?><div class="alert alert-error">Birth profile not found.</div><?php elseif ($result === null): ?><div class="alert alert-error">Profile needs an exact birth time, resolved coordinates, timezone, and configured API key.</div><?php else: ?><section class="card"><div class="row-between"><div><strong><?= e((string) $profile['profile_name']) ?></strong><p class="muted"><?= e((string) $profile['full_name']) ?> · <?= e((string) $profile['date_of_birth']) ?> · <?= e((string) $profile['time_of_birth']) ?></p></div><span class="pill <?= $result['ok'] ? 'pill-success' : '' ?>"><?= $result['ok'] ? 'Success · HTTP ' . (int) $result['status'] : 'Failed · HTTP ' . (int) $result['status'] ?></span></div></section><section class="card"><h2>API response</h2><pre class="api-output"><?= e(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre></section><?php endif; ?></section></main></body></html>
