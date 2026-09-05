<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/NavamsaCalculator.php';
require_once __DIR__ . '/../includes/KundliNormalizer.php';
require_once __DIR__ . '/../includes/YogaDetector.php';
requireLogin();

$uid = current_user_id();
$requestedProfileId = (int) ($_GET['profile_id'] ?? 0);

/* Resolve a profile together with an actual cached Kundli calculation. */
$profile = null;
$calc = null;

if ($requestedProfileId > 0) {
    $s = db()->prepare('SELECT p.id,p.profile_name,p.full_name,p.location_name,p.birth_place,c.id AS calculation_id,c.lagna,c.rashi,c.nakshatra,c.planetary_data,c.chart_data,c.api_response FROM birth_profiles p INNER JOIN kundli_calculations c ON c.birth_profile_id=p.id WHERE p.id=? AND p.user_id=? ORDER BY c.id DESC LIMIT 1');
    $s->bind_param('ii', $requestedProfileId, $uid);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();
    if ($row) { $profile = $row; $calc = $row; }
}

if (!$calc) {
    $s = db()->prepare('SELECT p.id,p.profile_name,p.full_name,p.location_name,p.birth_place,c.id AS calculation_id,c.lagna,c.rashi,c.nakshatra,c.planetary_data,c.chart_data,c.api_response FROM kundli_calculations c INNER JOIN birth_profiles p ON p.id=c.birth_profile_id WHERE c.user_id=? AND p.user_id=? ORDER BY c.id DESC LIMIT 1');
    $s->bind_param('ii', $uid, $uid);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();
    if ($row) { $profile = $row; $calc = $row; }
}

if (!$profile || !$calc) {
    http_response_code(404);
    exit('Kundli not found. Generate a Kundli first.');
}

$profileId = (int) $profile['id'];
$planetary = json_decode((string) ($calc['planetary_data'] ?? '[]'), true) ?: [];
$chartData = json_decode((string) ($calc['chart_data'] ?? '{}'), true) ?: [];

/* Older cached calculations may contain incomplete normalized data. Recover the
 * original API payload and normalize it with the same engine used at generation. */
$rawApi = json_decode((string) ($calc['api_response'] ?? '{}'), true);
if (!is_array($rawApi)) $rawApi = [];
$rawPlanetDetails = is_array($rawApi['planet_details'] ?? null) ? $rawApi['planet_details'] : [];
$rawAscendant = is_array($rawApi['ascendant_report'] ?? null) ? $rawApi['ascendant_report'] : [];

if (count($planetary) < 5 && ($rawPlanetDetails || $rawAscendant)) {
    $recovered = (new KundliNormalizer())->normalize($rawPlanetDetails, $rawAscendant);
    if (!empty($recovered['planetary_data'])) $planetary = $recovered['planetary_data'];
    if (empty($chartData['ascendant']) && !empty($recovered['chart_data']['ascendant'])) {
        $chartData['ascendant'] = $recovered['chart_data']['ascendant'];
    }
}

/* Recover the ascendant directly from the raw API response if chart_data was
 * created by an older version and does not contain a persisted ascendant. */
if (empty($chartData['ascendant']) && $rawAscendant) {
    $recovered = (new KundliNormalizer())->normalize($planetary, $rawAscendant);
    if (!empty($recovered['chart_data']['ascendant'])) {
        $chartData['ascendant'] = $recovered['chart_data']['ascendant'];
    }
}

try {
    $d9 = (new NavamsaCalculator())->calculate($planetary, $chartData, (string) $calc['lagna']);
    $report = (new YogaDetector())->detect($planetary, (string) $calc['lagna'], $d9);
    $analysisError = null;
} catch (Throwable $ex) {
    $d9 = ['lagna' => '—', 'positions' => []];
    $report = ['lagna' => $calc['lagna'] ?? '—', 'yogas' => []];
    $analysisError = $ex->getMessage();
}

function yg(string $v): string { return e($v); }
function statusClass(string $s): string { return $s === 'Formed' ? 'pill-success' : ($s === 'Formed but weakened' ? 'pill' : ''); }
$detected = count(array_filter($report['yogas'], fn($x) => in_array($x['status'], ['Formed', 'Formed but weakened'], true)));
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Yoga Report — ASTROPOP</title><link rel="stylesheet" href="<?=e(APP_BASE_PATH)?>/assets/css/app.css"></head><body><main class="app-shell"><header class="topbar"><a class="brand" href="<?=e(APP_BASE_PATH)?>/dashboard.php">✦ ASTROPOP</a><nav><a href="<?=e(APP_BASE_PATH)?>/dashboard.php">Dashboard</a><a href="<?=e(APP_BASE_PATH)?>/kundli.php?profile_id=<?=$profileId?>">Kundli</a><a href="<?=e(APP_BASE_PATH)?>/planetary-analysis.php?profile_id=<?=$profileId?>">Planetary Analysis</a><a href="<?=e(APP_BASE_PATH)?>/charts.php?profile_id=<?=$profileId?>">Charts</a><a href="<?=e(APP_BASE_PATH)?>/logout.php">Log out</a></nav></header><section class="content-wrap"><div class="section-heading"><p class="eyebrow">ASTROPOP INTELLIGENCE</p><h1>Yoga Report</h1><p class="muted"><?=yg((string)$profile['full_name'])?> · Deterministic D1 yoga formation analysis.</p></div>
<?php if($analysisError!==null): ?><div class="alert alert-error"><strong>Yoga analysis error:</strong> <?=yg($analysisError)?><br><span class="muted">The chart data is intact; this message identifies the calculation-layer failure so it can be corrected without changing your Kundli.</span></div><?php endif; ?>
<section class="grid-2"><article class="card"><p class="eyebrow">CHART FRAME</p><div class="detail-grid"><div><span>D1 Lagna</span><strong><?=yg((string)$report['lagna'])?></strong></div><div><span>Moon Rashi</span><strong><?=yg((string)$calc['rashi'])?></strong></div><div><span>D9 Lagna</span><strong><?=yg((string)$d9['lagna'])?></strong></div><div><span>Detected</span><strong><?=$detected?></strong></div></div></article><article class="card"><p class="eyebrow">METHODOLOGY</p><h2>Formation, then context</h2><p class="muted">ASTROPOP first checks whether the documented planetary configuration exists. It then reports contextual weakening separately. A detected yoga is not treated as a guaranteed life outcome or an arbitrary strength score.</p></article></section>
<section class="card"><div class="row-between"><div><p class="eyebrow">YOGA FAMILIES</p><h2>Classical formations</h2></div><span class="pill pill-success">Deterministic</span></div><div class="chart-hub-grid"><?php foreach($report['yogas'] as $y): ?><article class="chart-hub-item"><span class="chart-code"><?=yg($y['status']==='Formed'?'✓':($y['status']==='Formed but weakened'?'!':'—'))?></span><div><strong><?=yg($y['name'])?></strong><span><?=yg($y['family'])?> · <?=yg($y['reason'])?><?php if(!empty($y['planets'])): ?> · Planets: <?=yg(implode(', ',$y['planets']))?><?php endif; ?></span></div><span class="pill <?=statusClass($y['status'])?>"><?=yg($y['status'])?></span></article><?php endforeach; ?></div></section>
<section class="card"><p class="eyebrow">INTERPRETATION POLICY</p><h2>How ASTROPOP should read yogas</h2><div class="detail-grid-3"><div><span>1 · Formation</span><strong>Does the exact configuration exist?</strong></div><div><span>2 · Context</span><strong>Is the involved planet dignified and supported?</strong></div><div><span>3 · Timing</span><strong>Which Dasha period activates it?</strong></div></div><p class="muted">The next intelligence layer will connect verified yoga formations to Vimshottari Dasha timing and topic-specific interpretation.</p><div class="hero-actions"><a class="button button-primary" href="<?=e(APP_BASE_PATH)?>/kundli.php?profile_id=<?=$profileId?>">Back to Kundli</a><a class="button button-secondary" href="<?=e(APP_BASE_PATH)?>/planetary-analysis.php?profile_id=<?=$profileId?>">Planetary Analysis</a></div></section></section></main></body></html>
