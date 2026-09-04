<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/VedicAstroAPI.php';
require_once __DIR__ . '/../../includes/AstrologyService.php';
requireLogin();

$errors = [];
$success = flash('success');
$locationResults = [];
$selectedProfileId = (int) ($_POST['profile_id'] ?? $_GET['profile_id'] ?? 0);
$action = post_value('action');

if (is_post()) {
    verify_csrf();
    $uid = current_user_id();
    $api = new VedicAstroAPI(VEDIC_API_BASE_URL, VEDIC_API_KEY, VEDIC_API_TIMEOUT, VEDIC_API_CONNECT_TIMEOUT);
    $astro = new AstrologyService($api);

    if ($action === 'create') {
        $profileName = post_value('profile_name');
        $fullName = post_value('full_name');
        $dobValue = post_value('date_of_birth');
        $timeValue = post_value('time_of_birth');
        $birthPlace = post_value('birth_place');
        $gender = post_value('gender');
        if ($profileName === '' || mb_strlen($profileName) > 120) $errors[] = 'Enter a profile name.';
        if ($fullName === '' || mb_strlen($fullName) > 160) $errors[] = 'Enter your full name.';
        $dob = DateTime::createFromFormat('Y-m-d', $dobValue);
        $dobErrors = DateTime::getLastErrors();
        if (!$dob || ($dobErrors !== false && ($dobErrors['warning_count'] || $dobErrors['error_count'])) || $dob->format('Y-m-d') !== $dobValue || $dob > new DateTime('today')) $errors[] = 'Enter a valid date of birth.';
        if ($timeValue !== '') {
            $time = DateTime::createFromFormat('H:i', $timeValue);
            $timeErrors = DateTime::getLastErrors();
            if (!$time || ($timeErrors !== false && ($timeErrors['warning_count'] || $timeErrors['error_count'])) || $time->format('H:i') !== $timeValue) $errors[] = 'Enter a valid time of birth.';
        }
        if ($birthPlace === '' || mb_strlen($birthPlace) > 255) $errors[] = 'Enter your birth place.';
        if ($gender !== '' && !in_array($gender, ['Male', 'Female', 'Other'], true)) $errors[] = 'Select a valid gender.';
        if (!$errors) {
            $stmt = db()->prepare('INSERT INTO birth_profiles (user_id, profile_name, full_name, date_of_birth, time_of_birth, birth_place, gender) VALUES (?, ?, ?, ?, NULLIF(?, \'\'), ?, NULLIF(?, \'\'))');
            $stmt->bind_param('issssss', $uid, $profileName, $fullName, $dobValue, $timeValue, $birthPlace, $gender);
            if ($stmt->execute()) {
                $newId = db()->insert_id;
                $stmt->close();
                flash('success', 'Birth profile saved. Resolve the birth location to continue.');
                redirect('/birth/?profile_id=' . $newId);
            }
            app_error_log('Birth profile insert failed', ['db_error' => db()->error]);
            $stmt->close();
            $errors[] = 'We could not save the birth profile. Please try again.';
        }
    } elseif ($action === 'resolve') {
        $profileId = (int) ($_POST['profile_id'] ?? 0);
        $stmt = db()->prepare('SELECT birth_place FROM birth_profiles WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->bind_param('ii', $profileId, $uid);
        $stmt->execute();
        $profile = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$profile) {
            $errors[] = 'Birth profile not found.';
        } elseif (!$api->isConfigured()) {
            $errors[] = 'VedicAstroAPI is not configured. Add VEDIC_API_KEY to your environment first.';
        } else {
            $resolved = $astro->resolveBirthPlace((string) $profile['birth_place']);
            if ($resolved['ok']) $locationResults = $resolved['results'];
            else $errors[] = (string) $resolved['error'];
            $selectedProfileId = $profileId;
        }
    } elseif ($action === 'select_location') {
        $profileId = (int) ($_POST['profile_id'] ?? 0);
        $lat = filter_var($_POST['latitude'] ?? null, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
        $lon = filter_var($_POST['longitude'] ?? null, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
        $tz = filter_var($_POST['timezone_offset'] ?? null, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
        $name = trim((string) ($_POST['location_name'] ?? ''));
        if (!$lat || $lat < -90 || $lat > 90 || !$lon || $lon < -180 || $lon > 180 || $tz === null || $tz < -14 || $tz > 14 || $name === '') {
            $errors[] = 'Select a valid location returned by the astrology service.';
        } else {
            $stmt = db()->prepare('UPDATE birth_profiles SET location_name = ?, latitude = ?, longitude = ?, timezone = ? WHERE id = ? AND user_id = ?');
            $tzString = (string) $tz;
            $stmt->bind_param('sddsii', $name, $lat, $lon, $tzString, $profileId, $uid);
            if ($stmt->execute() && $stmt->affected_rows >= 0) {
                $stmt->close();
                flash('success', 'Birth location resolved. You can now generate the Kundli.');
                redirect('/birth/?profile_id=' . $profileId);
            }
            $stmt->close();
            $errors[] = 'We could not save the selected location.';
        }
        $selectedProfileId = $profileId;
    } elseif ($action === 'generate') {
        $profileId = (int) ($_POST['profile_id'] ?? 0);
        $stmt = db()->prepare('SELECT id, date_of_birth, time_of_birth, latitude, longitude, timezone FROM birth_profiles WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->bind_param('ii', $profileId, $uid);
        $stmt->execute();
        $profile = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$profile || $profile['latitude'] === null || $profile['longitude'] === null || $profile['timezone'] === null) {
            $errors[] = 'Resolve the birth location before generating the Kundli.';
        } elseif ($profile['time_of_birth'] === null) {
            $errors[] = 'An exact birth time is required for this Kundli calculation.';
        } elseif (!$api->isConfigured()) {
            $errors[] = 'VedicAstroAPI is not configured.';
        } else {
            $dobApi = (new DateTime((string) $profile['date_of_birth']))->format('d/m/Y');
            $tobApi = substr((string) $profile['time_of_birth'], 0, 5);
            $lat = (float) $profile['latitude'];
            $lon = (float) $profile['longitude'];
            $tz = (float) $profile['timezone'];
            $hash = hash('sha256', implode('|', [$profileId, $dobApi, $tobApi, $lat, $lon, $tz]));
            $stmt = db()->prepare('SELECT id FROM kundli_calculations WHERE birth_profile_id = ? AND calculation_hash = ? LIMIT 1');
            $stmt->bind_param('is', $profileId, $hash);
            $stmt->execute();
            $cached = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$cached) {
                $planet = $astro->planetDetails($dobApi, $tobApi, $lat, $lon, $tz);
                $ascendant = $astro->ascendantReport($dobApi, $tobApi, $lat, $lon, $tz);
                if (!$planet['ok']) {
                    $errors[] = 'Kundli calculation failed: ' . (string) $planet['error'];
                } elseif (!$ascendant['ok']) {
                    $errors[] = 'Ascendant calculation failed: ' . (string) $ascendant['error'];
                } else {
                    $apiResponse = json_encode(['planet_details' => $planet['data'], 'ascendant_report' => $ascendant['data']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $stmt = db()->prepare('INSERT INTO kundli_calculations (user_id, birth_profile_id, api_response, calculation_hash) VALUES (?, ?, ?, ?)');
                    $stmt->bind_param('iiss', $uid, $profileId, $apiResponse, $hash);
                    if (!$stmt->execute()) {
                        app_error_log('Kundli cache insert failed', ['db_error' => db()->error]);
                        $errors[] = 'Kundli was calculated but could not be cached.';
                    }
                    $stmt->close();
                }
            }
            if (!$errors) {
                flash('success', 'Kundli calculation completed and cached successfully.');
                redirect('/dashboard.php');
            }
        }
        $selectedProfileId = $profileId;
    }
}

$uid = current_user_id();
$stmt = db()->prepare('SELECT id, profile_name, full_name, date_of_birth, time_of_birth, birth_place, location_name, latitude, longitude, timezone FROM birth_profiles WHERE user_id = ? ORDER BY id DESC');
$stmt->bind_param('i', $uid);
$stmt->execute();
$profiles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Birth profile — ASTROPOP</title><link rel="stylesheet" href="<?= e(APP_BASE_PATH) ?>/assets/css/app.css"></head><body><main class="app-shell"><header class="topbar"><a class="brand" href="<?= e(APP_BASE_PATH) ?>/dashboard.php">✦ ASTROPOP</a><nav><a href="<?= e(APP_BASE_PATH) ?>/dashboard.php">Dashboard</a><a href="<?= e(APP_BASE_PATH) ?>/logout.php">Log out</a></nav></header><section class="content-wrap"><div class="section-heading"><p class="eyebrow">ASTROLOGY ENGINE</p><h1>Birth profile</h1><p class="muted">Save your birth details, resolve the exact location, then generate your Kundli from VedicAstroAPI.</p></div><?php if ($errors): ?><div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?><?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<form method="post" class="card form-grid" novalidate><?= csrf_field() ?><input type="hidden" name="action" value="create"><div><label for="profile_name">Profile name</label><input id="profile_name" name="profile_name" placeholder="My profile" required></div><div><label for="full_name">Full name</label><input id="full_name" name="full_name" required></div><div><label for="date_of_birth">Date of birth</label><input id="date_of_birth" name="date_of_birth" type="date" required></div><div><label for="time_of_birth">Exact time of birth</label><input id="time_of_birth" name="time_of_birth" type="time" required><small>Exact time is required for Kundli generation.</small></div><div class="full-span"><label for="birth_place">Birth place</label><input id="birth_place" name="birth_place" placeholder="Hyderabad, Telangana, India" required></div><div><label for="gender">Gender</label><select id="gender" name="gender"><option value="">Prefer not to say</option><option>Male</option><option>Female</option><option>Other</option></select></div><div class="full-span"><button class="button button-primary" type="submit">Save birth profile</button></div></form>
<?php if ($profiles): ?><section class="stack"><h2>Your profiles</h2><?php foreach ($profiles as $profile): ?><article class="card"><div class="row-between"><strong><?= e((string) $profile['profile_name']) ?></strong><span class="pill"><?= $profile['latitude'] !== null ? 'Location resolved' : 'Location pending' ?></span></div><p><?= e((string) $profile['full_name']) ?> · <?= e((string) $profile['date_of_birth']) ?> · <?= e((string) ($profile['time_of_birth'] ?? 'Time unknown')) ?></p><p class="muted"><?= e((string) $profile['birth_place']) ?><?= $profile['location_name'] ? ' · ' . e((string) $profile['location_name']) : '' ?></p><div class="hero-actions"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="resolve"><input type="hidden" name="profile_id" value="<?= (int) $profile['id'] ?>"><button class="button button-secondary" type="submit">Resolve location</button></form><?php if ($profile['latitude'] !== null): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="generate"><input type="hidden" name="profile_id" value="<?= (int) $profile['id'] ?>"><button class="button button-primary" type="submit">Generate Kundli</button></form><?php endif; ?></div></article><?php if ($selectedProfileId === (int) $profile['id'] && $locationResults): ?><div class="card"><h3>Select birth location</h3><?php foreach ($locationResults as $location): ?><form method="post" class="location-option"><?= csrf_field() ?><input type="hidden" name="action" value="select_location"><input type="hidden" name="profile_id" value="<?= (int) $profile['id'] ?>"><input type="hidden" name="location_name" value="<?= e((string) $location['full_name']) ?>"><input type="hidden" name="latitude" value="<?= e((string) $location['latitude']) ?>"><input type="hidden" name="longitude" value="<?= e((string) $location['longitude']) ?>"><input type="hidden" name="timezone_offset" value="<?= e((string) ($location['timezone_offset'] ?? '')) ?>"><button class="location-button" type="submit"><strong><?= e((string) $location['name']) ?></strong><span><?= e((string) $location['full_name']) ?></span><small><?= e((string) $location['latitude']) ?>, <?= e((string) $location['longitude']) ?> · UTC <?= e((string) ($location['timezone_offset'] ?? 'unknown')) ?></small></button></form><?php endforeach; ?></div><?php endif; ?><?php endforeach; ?></section><?php endif; ?></section></main></body></html>
