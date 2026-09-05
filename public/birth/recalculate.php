<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/VedicAstroAPI.php';
require_once __DIR__ . '/../../includes/AstrologyService.php';
require_once __DIR__ . '/../../includes/KundliCalculationService.php';
requireLogin();

$uid = current_user_id();
$profileId = (int) ($_GET['profile_id'] ?? $_POST['profile_id'] ?? 0);
if ($profileId <= 0) redirect('/birth/');

$stmt = db()->prepare('SELECT * FROM birth_profiles WHERE id=? AND user_id=? LIMIT 1');
$stmt->bind_param('ii', $profileId, $uid); $stmt->execute(); $profile = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$profile) { http_response_code(404); exit('Birth profile not found.'); }

$errors = [];
$warnings = [];
if (is_post()) {
    verify_csrf();
    if (VEDIC_API_KEY === '') $errors[] = 'VedicAstroAPI is not configured. Add VEDIC_API_KEY to your local environment.';
    else {
        try {
            $api = new VedicAstroAPI(VEDIC_API_BASE_URL, VEDIC_API_KEY, VEDIC_API_TIMEOUT, VEDIC_API_CONNECT_TIMEOUT);
            $service = new KundliCalculationService(new AstrologyService($api));
            $result = $service->calculateAndStore($uid, $profile);
            $warnings = $result['warnings'] ?? [];
            if ($result['ok']) {
                flash('success', $warnings ? 'Kundli recalculated and saved. ' . implode(' ', $warnings) : 'Kundli recalculated and saved to the database.');
                redirect('/kundli.php?profile_id=' . $profileId);
            }
            $errors[] = (string) ($result['error'] ?? 'Kundli recalculation failed.');
        } catch (Throwable $ex) {
            app_error_log('Kundli recalculation failed', ['message'=>$ex->getMessage()]);
            $errors[] = 'Kundli recalculation failed. Check the PHP error log for details.';
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Recalculate Kundli — ASTROPOP</title><link rel="stylesheet" href="<?=e(APP_BASE_PATH)?>/assets/css/app.css"></head><body><main class="app-shell"><header class="topbar"><a class="brand" href="<?=e(APP_BASE_PATH)?>/dashboard.php">✦ ASTROPOP</a><nav><a href="<?=e(APP_BASE_PATH)?>/birth/?profile_id=<?=$profileId?>">Birth profile</a><a href="<?=e(APP_BASE_PATH)?>/kundli.php?profile_id=<?=$profileId?>">Kundli</a><a href="<?=e(APP_BASE_PATH)?>/logout.php">Log out</a></nav></header><section class="content-wrap"><div class="section-heading"><p class="eyebrow">ASTROLOGY ENGINE</p><h1>Recalculate Kundli</h1><p class="muted">This is the only action that refreshes source astrology data from VedicAstroAPI. The result is saved and all report pages use the saved calculation.</p></div><?php if($errors): ?><div class="alert alert-error"><?=e(implode(' ', $errors))?></div><?php endif; ?><?php if($warnings): ?><div class="alert alert-warning"><?=e(implode(' ', $warnings))?></div><?php endif; ?><section class="card"><div class="detail-grid"><div><span>Profile</span><strong><?=e((string)$profile['profile_name'])?></strong></div><div><span>Name</span><strong><?=e((string)$profile['full_name'])?></strong></div><div><span>Date / Time</span><strong><?=e((string)$profile['date_of_birth'])?> · <?=e((string)($profile['time_of_birth'] ?? '—'))?></strong></div><div><span>Location</span><strong><?=e((string)($profile['location_name'] ?: $profile['birth_place']))?></strong></div></div><p class="muted">Recalculation refreshes planetary positions, Ascendant, normalized houses/chart data, and Maha Dasha. It does not happen automatically when viewing a report.</p><div class="hero-actions"><form method="post"><?=csrf_field()?><input type="hidden" name="profile_id" value="<?=$profileId?>"><button class="button button-primary" type="submit">Recalculate and Save</button></form><a class="button button-secondary" href="<?=e(APP_BASE_PATH)?>/kundli.php?profile_id=<?=$profileId?>">Cancel</a></div></section></section></main></body></html>
