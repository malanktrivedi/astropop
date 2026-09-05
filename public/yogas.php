<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/NavamsaCalculator.php';
require_once __DIR__ . '/../includes/KundliNormalizer.php';
require_once __DIR__ . '/../includes/YogaDetector.php';
requireLogin();

$uid = current_user_id();
$requestedProfileId = (int) ($_GET['profile_id'] ?? 0);

/* Resolve the selected/current birth profile first. Never choose an unrelated
 * latest calculation when no profile_id is supplied. */
if ($requestedProfileId <= 0) {
    $stmt = db()->prepare('SELECT id FROM birth_profiles WHERE user_id=? ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $requestedProfileId = (int) ($row['id'] ?? 0);
}

$profile = null;
$calc = null;
if ($requestedProfileId > 0) {
    $stmt = db()->prepare('SELECT p.*, c.id AS calculation_id,c.lagna,c.rashi,c.nakshatra,c.planetary_data,c.chart_data,c.api_response FROM birth_profiles p LEFT JOIN kundli_calculations c ON c.birth_profile_id=p.id AND c.user_id=? WHERE p.id=? AND p.user_id=? ORDER BY c.id DESC LIMIT 1');
    $stmt->bind_param('iii', $uid, $requestedProfileId, $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) { $profile=$row; if (!empty($row['calculation_id'])) $calc=$row; }
}

if (!$profile || !$calc) {
    http_response_code(404);
    exit('Kundli not found for the selected birth profile. Generate a Kundli for this profile first.');
}

$profileId=(int)$profile['id'];
$planetary=json_decode((string)($calc['planetary_data']??'[]'),true); if(!is_array($planetary))$planetary=[];
$chartData=json_decode((string)($calc['chart_data']??'{}'),true); if(!is_array($chartData))$chartData=[];

$rawApi=json_decode((string)($calc['api_response']??'{}'),true); if(!is_array($rawApi))$rawApi=[];
$rawPlanetDetails=is_array($rawApi['planet_details']??null)?$rawApi['planet_details']:[];
$rawAscendant=is_array($rawApi['ascendant_report']??null)?$rawApi['ascendant_report']:[];

/* Older cached rows may have an incomplete chart_data object. Recover only the
 * missing Ascendant/chart metadata. Never overwrite a valid planetary_data
 * array with a failed/empty re-normalization of an older raw API shape. */
$needsRecovery=empty($chartData['ascendant']) || count($planetary)<5;
if($needsRecovery && ($rawPlanetDetails||$rawAscendant)){
    $recovered=(new KundliNormalizer())->normalize($rawPlanetDetails,$rawAscendant);
    if(empty($planetary) && !empty($recovered['planetary_data']) && count($recovered['planetary_data'])>=5){
        $planetary=$recovered['planetary_data'];
    }
    if(empty($chartData['ascendant']) && !empty($recovered['chart_data']['ascendant'])){
        $chartData['ascendant']=$recovered['chart_data']['ascendant'];
    }
}

/* If an older cache still lacks chart_data.ascendant, recover the Ascendant
 * directly from the raw API response so D9 can still be calculated. */
if(empty($chartData['ascendant']) && $rawAscendant){
    $recovered=(new KundliNormalizer())->normalize([], $rawAscendant);
    if(!empty($recovered['chart_data']['ascendant'])){
        $chartData['ascendant']=$recovered['chart_data']['ascendant'];
    }
}

try{
    $d9=(new NavamsaCalculator())->calculate($planetary,$chartData,(string)$calc['lagna']);
    $report=(new YogaDetector())->detect($planetary,(string)$calc['lagna'],$d9);
    $analysisError=null;
}catch(Throwable $ex){
    $d9=['lagna'=>'—','positions'=>[]];
    $report=['lagna'=>$calc['lagna']??'—','yogas'=>[]];
    $analysisError=$ex->getMessage();
}
function yg(string $v):string{return e($v);} function statusClass(string $s):string{return $s==='Formed'?'pill-success':($s==='Formed but weakened'?'pill':'');}
$detected=count(array_filter($report['yogas'],fn($x)=>in_array($x['status'],['Formed','Formed but weakened'],true)));
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Yoga Report — ASTROPOP</title><link rel="stylesheet" href="<?=e(APP_BASE_PATH)?>/assets/css/app.css"></head><body><main class="app-shell"><header class="topbar"><a class="brand" href="<?=e(APP_BASE_PATH)?>/dashboard.php">✦ ASTROPOP</a><nav><a href="<?=e(APP_BASE_PATH)?>/dashboard.php">Dashboard</a><a href="<?=e(APP_BASE_PATH)?>/kundli.php?profile_id=<?=$profileId?>">Kundli</a><a href="<?=e(APP_BASE_PATH)?>/planetary-analysis.php?profile_id=<?=$profileId?>">Planetary Analysis</a><a href="<?=e(APP_BASE_PATH)?>/charts.php?profile_id=<?=$profileId?>">Charts</a><a href="<?=e(APP_BASE_PATH)?>/logout.php">Log out</a></nav></header><section class="content-wrap"><div class="section-heading"><p class="eyebrow">ASTROPOP INTELLIGENCE</p><h1>Yoga Report</h1><p class="muted"><?=yg((string)$profile['full_name'])?> · Deterministic D1 yoga formation analysis.</p></div><?php if($analysisError!==null): ?><div class="alert alert-error"><strong>Yoga analysis error:</strong> <?=yg($analysisError)?></div><?php endif; ?><section class="grid-2"><article class="card"><p class="eyebrow">CHART FRAME</p><div class="detail-grid"><div><span>D1 Lagna</span><strong><?=yg((string)$report['lagna'])?></strong></div><div><span>Moon Rashi</span><strong><?=yg((string)($calc['rashi']??'—'))?></strong></div><div><span>D9 Lagna</span><strong><?=yg((string)($d9['lagna']??'—'))?></strong></div><div><span>Detected</span><strong><?=$detected?></strong></div></div></article><article class="card"><p class="eyebrow">METHODOLOGY</p><h2>Formation, then context</h2><p class="muted">ASTROPOP first checks whether the documented planetary configuration exists. It then reports contextual weakening separately. A detected yoga is not treated as a guaranteed life outcome or an arbitrary strength score.</p></article></section><section class="card"><div class="row-between"><div><p class="eyebrow">YOGA FAMILIES</p><h2>Classical formations</h2></div><span class="pill pill-success">Deterministic</span></div><div class="chart-hub-grid"><?php foreach($report['yogas'] as $y): ?><article class="chart-hub-item"><span class="chart-code"><?=yg($y['status']==='Formed'?'✓':($y['status']==='Formed but weakened'?'!':'—'))?></span><div><strong><?=yg($y['name'])?></strong><span><?=yg($y['family'])?> · <?=yg($y['reason'])?><?php if(!empty($y['planets'])): ?> · Planets: <?=yg(implode(', ',$y['planets']))?><?php endif; ?></span></div><span class="pill <?=statusClass($y['status'])?>"><?=yg($y['status'])?></span></article><?php endforeach; ?></div></section><section class="card"><p class="eyebrow">INTERPRETATION POLICY</p><h2>How ASTROPOP should read yogas</h2><div class="detail-grid-3"><div><span>1 · Formation</span><strong>Does the exact configuration exist?</strong></div><div><span>2 · Context</span><strong>Is the involved planet dignified and supported?</strong></div><div><span>3 · Timing</span><strong>Which Dasha period activates it?</strong></div></div><p class="muted">The next intelligence layer will connect verified yoga formations to Vimshottari Dasha timing and topic-specific interpretation.</p><div class="hero-actions"><a class="button button-primary" href="<?=e(APP_BASE_PATH)?>/kundli.php?profile_id=<?=$profileId?>">Back to Kundli</a><a class="button button-secondary" href="<?=e(APP_BASE_PATH)?>/planetary-analysis.php?profile_id=<?=$profileId?>">Planetary Analysis</a></div></section></section></main></body></html>
