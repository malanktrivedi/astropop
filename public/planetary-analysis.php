<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/NavamsaCalculator.php';
require_once __DIR__ . '/../includes/PlanetaryAnalyzer.php';
requireLogin();

$uid = current_user_id();
$profileId = (int) ($_GET['profile_id'] ?? 0);
if ($profileId <= 0) {
    $stmt = db()->prepare('SELECT id FROM birth_profiles WHERE user_id=? ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('i', $uid); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    $profileId = (int) ($row['id'] ?? 0);
}
$stmt = db()->prepare('SELECT id,profile_name,full_name,date_of_birth,time_of_birth,location_name,birth_place,timezone FROM birth_profiles WHERE id=? AND user_id=? LIMIT 1');
$stmt->bind_param('ii', $profileId, $uid); $stmt->execute(); $profile = $stmt->get_result()->fetch_assoc(); $stmt->close();
$stmt = db()->prepare('SELECT id,lagna,rashi,nakshatra,planetary_data,chart_data FROM kundli_calculations WHERE birth_profile_id=? ORDER BY id DESC LIMIT 1');
$stmt->bind_param('i', $profileId); $stmt->execute(); $calculation = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$profile || !$calculation) { http_response_code(404); exit('Kundli not found. Generate a Kundli first.'); }

$planetary = json_decode((string) $calculation['planetary_data'], true) ?: [];
$chartData = json_decode((string) $calculation['chart_data'], true) ?: [];
$d9 = (new NavamsaCalculator())->calculate($planetary, $chartData, (string) ($calculation['lagna'] ?? ''));
$analysis = (new PlanetaryAnalyzer())->analyze($planetary, (string) ($calculation['lagna'] ?? ''), $d9);
function paValue(mixed $v): string { return $v === null || $v === '' ? '—' : e(is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); }
function paDeg(mixed $v): string { if (!is_numeric($v)) return '—'; $d=floor((float)$v); $m=round(((float)$v-$d)*60); if($m>=60){$d++;$m=0;} return sprintf('%02d°%02d′',$d,$m); }
function paStatus(mixed $v): string { if ($v===true || strtolower((string)$v)==='true' || (string)$v==='1') return 'Yes'; if ($v===false || strtolower((string)$v)==='false' || (string)$v==='0') return 'No'; return $v===null||$v===''?'—':(string)$v; }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Planetary Analysis — ASTROPOP</title><link rel="stylesheet" href="<?=e(APP_BASE_PATH)?>/assets/css/app.css"></head><body><main class="app-shell"><header class="topbar"><a class="brand" href="<?=e(APP_BASE_PATH)?>/dashboard.php">✦ ASTROPOP</a><nav><a href="<?=e(APP_BASE_PATH)?>/dashboard.php">Dashboard</a><a href="<?=e(APP_BASE_PATH)?>/kundli.php?profile_id=<?=$profileId?>">Kundli</a><a href="<?=e(APP_BASE_PATH)?>/charts.php?profile_id=<?=$profileId?>">Charts</a><a href="<?=e(APP_BASE_PATH)?>/logout.php">Log out</a></nav></header><section class="content-wrap"><div class="section-heading"><p class="eyebrow">ASTROPOP INTELLIGENCE</p><h1>Planetary Analysis</h1><p class="muted"><?=e((string)$profile['full_name'])?> · D1 facts cross-checked with D9 Navamsa.</p></div>
<section class="grid-2"><article class="card"><p class="eyebrow">CORE FRAME</p><div class="detail-grid"><div><span>D1 Lagna</span><strong><?=paValue($analysis['lagna'])?></strong></div><div><span>Moon Rashi</span><strong><?=paValue($calculation['rashi'])?></strong></div><div><span>Moon Nakshatra</span><strong><?=paValue($calculation['nakshatra'])?></strong></div><div><span>D9 Lagna</span><strong><?=paValue($d9['lagna'])?></strong></div></div></article><article class="card"><p class="eyebrow">METHOD</p><h2>Facts before interpretation</h2><p class="muted">This layer reports sign dignity, house lordship, nakshatra, motion flags, D9 placement and conjunctions. It intentionally does not invent a single planetary “strength score”.</p></article></section>
<section class="card"><div class="row-between"><div><p class="eyebrow">PLANETARY PROFILE</p><h2>D1 → D9 analysis</h2></div><span class="pill pill-success">Deterministic</span></div><div class="table-wrap"><table><thead><tr><th>Planet</th><th>D1 Sign</th><th>Degree</th><th>House</th><th>Sign Lord</th><th>Lordships</th><th>Dignity</th><th>Nakshatra</th><th>Retro</th><th>Combust</th><th>D9</th></tr></thead><tbody><?php foreach($analysis['rows'] as $row): ?><tr><td><strong><?=paValue($row['name'])?></strong></td><td><?=paValue($row['sign'])?></td><td><?=paDeg($row['degree'])?></td><td><?=paValue($row['house'])?></td><td><?=paValue($row['sign_lord'])?></td><td><?= $row['house_lordships'] ? e(implode(', ', array_map(fn($h)=>(string)$h, $row['house_lordships']))) : '—' ?></td><td><?=paValue($row['dignity'])?></td><td><?=paValue($row['nakshatra'])?><?= $row['pada']!==null && $row['pada']!=='' ? ' · P'.paValue($row['pada']) : '' ?></td><td><?=paStatus($row['retrograde'])?></td><td><?=paStatus($row['combust'])?></td><td><?=paValue($row['d9_sign'])?><?= $row['d9_house'] ? ' · H'.(int)$row['d9_house'] : '' ?><?= $row['vargottama'] ? ' · Vargottama' : '' ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="grid-2"><article class="card"><p class="eyebrow">HOUSE LORDS</p><h2>D1 lordship map</h2><div class="house-grid"><?php foreach($analysis['house_lords'] as $h): ?><article><span>House <?= (int)$h['house'] ?></span><strong><?=e($h['sign'])?> · <?=e($h['lord'])?></strong></article><?php endforeach; ?></div></article><article class="card"><p class="eyebrow">CONJUNCTIONS</p><h2>Shared houses</h2><?php if($analysis['conjunctions']): ?><div class="house-grid"><?php foreach($analysis['conjunctions'] as $c): ?><article><span>House <?= (int)$c['house'] ?></span><strong><?=e(implode(', ', $c['planets']))?></strong></article><?php endforeach; ?></div><?php else: ?><p class="muted">No multi-planet houses detected in the normalized D1 data.</p><?php endif; ?></article></section>
<section class="card"><p class="eyebrow">WHAT COMES NEXT</p><h2>Yoga Detection</h2><p class="muted">The next intelligence layer can use these verified facts to classify documented yoga families—without turning combinations into guaranteed outcomes.</p><div class="hero-actions"><a class="button button-primary" href="<?=e(APP_BASE_PATH)?>/kundli.php?profile_id=<?=$profileId?>">Back to Kundli</a><a class="button button-secondary" href="<?=e(APP_BASE_PATH)?>/charts.php?profile_id=<?=$profileId?>">Explore Vargas</a></div></section></section></main></body></html>
