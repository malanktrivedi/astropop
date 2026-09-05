<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/VedicAstroAPI.php';
require_once __DIR__ . '/../includes/AstrologyService.php';
requireLogin();

$uid = current_user_id();
$errors = [];
$results = [];
$profileId = (int) ($_GET['profile_id'] ?? $_POST['profile_id'] ?? 0);

$stmt = db()->prepare('SELECT id, profile_name, full_name, date_of_birth, time_of_birth, birth_place, location_name, latitude, longitude, timezone FROM birth_profiles WHERE user_id = ? AND (? = 0 OR id = ?) ORDER BY id DESC LIMIT 1');
$stmt->bind_param('iii', $uid, $profileId, $profileId);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$profile) {
    $errors[] = 'No birth profile was found. Create and resolve a birth profile first.';
} elseif ($profile['time_of_birth'] === null || $profile['latitude'] === null || $profile['longitude'] === null || $profile['timezone'] === null) {
    $errors[] = 'The selected profile needs an exact birth time and resolved location before these endpoints can be tested.';
}

if (is_post() && !$errors) {
    verify_csrf();
    $action = post_value('action');
    $api = new VedicAstroAPI(VEDIC_API_BASE_URL, VEDIC_API_KEY, VEDIC_API_TIMEOUT, VEDIC_API_CONNECT_TIMEOUT);
    $astro = new AstrologyService($api);

    $dobApi = (new DateTime((string) $profile['date_of_birth']))->format('d/m/Y');
    $tobApi = substr((string) $profile['time_of_birth'], 0, 5);
    $lat = (float) $profile['latitude'];
    $lon = (float) $profile['longitude'];
    $tz = (float) $profile['timezone'];

    if ($action === 'planet' || $action === 'both') {
        $results['planet-details'] = $astro->planetDetails($dobApi, $tobApi, $lat, $lon, $tz);
    }
    if ($action === 'ascendant' || $action === 'both') {
        $results['ascendant-report'] = $astro->ascendantReport($dobApi, $tobApi, $lat, $lon, $tz);
    }
}

function debug_json(mixed $value): string
{
    return (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Astrology API Debug — ASTROPOP</title>
<link rel="stylesheet" href="<?= e(APP_BASE_PATH) ?>/assets/css/app.css">
</head>
<body>
<main class="app-shell">
<header class="topbar">
<a class="brand" href="<?= e(APP_BASE_PATH) ?>/dashboard.php">✦ ASTROPOP</a>
<nav><a href="<?= e(APP_BASE_PATH) ?>/dashboard.php">Dashboard</a><a href="<?= e(APP_BASE_PATH) ?>/birth/">Birth profile</a><a href="<?= e(APP_BASE_PATH) ?>/logout.php">Log out</a></nav>
</header>
<section class="content-wrap">
<div class="section-heading">
<p class="eyebrow">DEVELOPMENT</p>
<h1>Astrology API Debug</h1>
<p class="muted">Test the exact birth-data endpoints used by ASTROPOP. The API key stays server-side and is never displayed.</p>
</div>
<?php if ($errors): ?><div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<?php if ($profile): ?>
<section class="card">
<div class="row-between"><div><p class="eyebrow">TEST PROFILE</p><h2><?= e((string) $profile['profile_name']) ?></h2></div><span class="pill <?= !$errors ? 'pill-success' : '' ?>"><?= !$errors ? 'Ready' : 'Incomplete' ?></span></div>
<div class="detail-grid">
<div><span>Name</span><strong><?= e((string) $profile['full_name']) ?></strong></div>
<div><span>Date / Time</span><strong><?= e((string) $profile['date_of_birth']) ?> <?= e((string) ($profile['time_of_birth'] ?? '')) ?></strong></div>
<div><span>Location</span><strong><?= e((string) ($profile['location_name'] ?? $profile['birth_place'])) ?></strong></div>
<div><span>Coordinates / TZ</span><strong><?= $profile['latitude'] !== null ? e((string) $profile['latitude']) . ', ' . e((string) $profile['longitude']) . ' / UTC ' . e((string) $profile['timezone']) : 'Pending' ?></strong></div>
</div>
<?php if (!$errors): ?>
<form method="post" class="hero-actions">
<?= csrf_field() ?><input type="hidden" name="profile_id" value="<?= (int) $profile['id'] ?>">
<button class="button button-primary" type="submit" name="action" value="planet">Test Planet Details</button>
<button class="button button-secondary" type="submit" name="action" value="ascendant">Test Ascendant Report</button>
<button class="button button-secondary" type="submit" name="action" value="both">Test Both</button>
</form>
<?php endif; ?>
</section>
<?php endif; ?>
<?php foreach ($results as $endpoint => $result): ?>
<section class="card">
<div class="row-between"><h2><?= e($endpoint) ?></h2><span class="pill <?= $result['ok'] ? 'pill-success' : '' ?>"><?= $result['ok'] ? 'Success' : 'Failed' ?> · HTTP <?= (int) $result['status'] ?></span></div>
<?php if (!$result['ok']): ?><div class="alert alert-error"><?= e((string) $result['error']) ?></div><?php endif; ?>
<?php if ($result['data'] !== null): ?><pre class="api-output"><?= e(debug_json($result['data'])) ?></pre><?php endif; ?>
</section>
<?php endforeach; ?>
<section class="card"><p class="muted"><strong>Development note:</strong> This page is for local/API contract testing. Restrict or remove it before production.</p></section>
</section>
</main>
</body>
</html>
