<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
requireLogin();

$errors = [];
$profileName = post_value('profile_name');
$fullName = post_value('full_name');
$dobValue = post_value('date_of_birth');
$timeValue = post_value('time_of_birth');
$birthPlace = post_value('birth_place');
$gender = post_value('gender');

if (is_post()) {
    verify_csrf();

    if ($profileName === '' || mb_strlen($profileName) > 120) $errors[] = 'Enter a profile name.';
    if ($fullName === '' || mb_strlen($fullName) > 160) $errors[] = 'Enter your full name.';

    $dob = DateTime::createFromFormat('Y-m-d', $dobValue);
    $dobErrors = DateTime::getLastErrors();
    $dobValid = $dob && ($dobErrors === false || ($dobErrors['warning_count'] === 0 && $dobErrors['error_count'] === 0)) && $dob->format('Y-m-d') === $dobValue;
    if (!$dobValid || $dob > new DateTime('today')) $errors[] = 'Enter a valid date of birth.';

    if ($timeValue !== '') {
        $time = DateTime::createFromFormat('H:i', $timeValue);
        $timeErrors = DateTime::getLastErrors();
        $timeValid = $time && ($timeErrors === false || ($timeErrors['warning_count'] === 0 && $timeErrors['error_count'] === 0)) && $time->format('H:i') === $timeValue;
        if (!$timeValid) $errors[] = 'Enter a valid birth time.';
    }

    if ($birthPlace === '' || mb_strlen($birthPlace) > 255) $errors[] = 'Enter your birth place.';
    if ($gender !== '' && !in_array($gender, ['Male', 'Female', 'Other'], true)) $errors[] = 'Select a valid gender.';

    if (!$errors) {
        $uid = current_user_id();
        $stmt = db()->prepare('INSERT INTO birth_profiles (user_id, profile_name, full_name, date_of_birth, time_of_birth, birth_place, gender) VALUES (?, ?, ?, ?, NULLIF(?, \'\'), ?, NULLIF(?, \'\'))');
        $stmt->bind_param('issssss', $uid, $profileName, $fullName, $dobValue, $timeValue, $birthPlace, $gender);
        if ($stmt->execute()) {
            $stmt->close();
            flash('success', 'Birth profile saved. Location resolution and Kundli generation are the next astrology-core step.');
            redirect('/birth/');
        }
        app_error_log('Birth profile insert failed', ['db_error' => db()->error]);
        $stmt->close();
        $errors[] = 'We could not save the birth profile. Please try again.';
    }
}

$success = flash('success');
$uid = current_user_id();
$stmt = db()->prepare('SELECT profile_name, full_name, date_of_birth, time_of_birth, birth_place, latitude, longitude, timezone FROM birth_profiles WHERE user_id = ? ORDER BY id DESC');
$stmt->bind_param('i', $uid);
$stmt->execute();
$profiles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Birth profile — ASTROPOP</title><link rel="stylesheet" href="<?= e(APP_BASE_PATH) ?>/assets/css/app.css"></head><body><main class="app-shell"><header class="topbar"><a class="brand" href="<?= e(APP_BASE_PATH) ?>/dashboard.php">✦ ASTROPOP</a><nav><a href="<?= e(APP_BASE_PATH) ?>/dashboard.php">Dashboard</a><a href="<?= e(APP_BASE_PATH) ?>/logout.php">Log out</a></nav></header><section class="content-wrap"><div class="section-heading"><p class="eyebrow">MY ASTROLOGY</p><h1>Birth profile</h1><p class="muted">Accurate birth information is the foundation for your personalized Kundli.</p></div><?php if ($errors): ?><div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?><?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?><form method="post" class="card form-grid" novalidate><?= csrf_field() ?><div><label for="profile_name">Profile name</label><input id="profile_name" name="profile_name" value="<?= e($profileName) ?>" placeholder="My profile" required></div><div><label for="full_name">Full name</label><input id="full_name" name="full_name" value="<?= e($fullName) ?>" required></div><div><label for="date_of_birth">Date of birth</label><input id="date_of_birth" name="date_of_birth" type="date" value="<?= e($dobValue) ?>" required></div><div><label for="time_of_birth">Time of birth</label><input id="time_of_birth" name="time_of_birth" type="time" value="<?= e($timeValue) ?>"><small>Exact time is preferred for accurate chart calculations.</small></div><div class="full-span"><label for="birth_place">Birth place</label><input id="birth_place" name="birth_place" value="<?= e($birthPlace) ?>" placeholder="City, State, Country" required><small>Coordinates and timezone will be resolved before Kundli calculation.</small></div><div><label for="gender">Gender</label><select id="gender" name="gender"><option value="">Prefer not to say</option><option value="Male" <?= $gender === 'Male' ? 'selected' : '' ?>>Male</option><option value="Female" <?= $gender === 'Female' ? 'selected' : '' ?>>Female</option><option value="Other" <?= $gender === 'Other' ? 'selected' : '' ?>>Other</option></select></div><div class="full-span"><button class="button button-primary" type="submit">Save birth profile</button></div></form><?php if ($profiles): ?><section class="stack"><h2>Saved profiles</h2><?php foreach ($profiles as $profile): ?><article class="card"><div class="row-between"><strong><?= e((string) $profile['profile_name']) ?></strong><span class="pill"><?= $profile['latitude'] !== null ? 'Location resolved' : 'Location pending' ?></span></div><p><?= e((string) $profile['full_name']) ?> · <?= e((string) $profile['date_of_birth']) ?> · <?= e((string) ($profile['time_of_birth'] ?? 'Time unknown')) ?></p><p class="muted"><?= e((string) $profile['birth_place']) ?></p></article><?php endforeach; ?></section><?php endif; ?></section></main></body></html>
