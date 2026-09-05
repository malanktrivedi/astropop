<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$uid = current_user_id();
$profileId = (int) ($_GET['profile_id'] ?? 0);
if ($profileId <= 0) {
    $stmt = db()->prepare('SELECT id FROM birth_profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('i', $uid); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    $profileId = (int) ($row['id'] ?? 0);
}
$stmt = db()->prepare('SELECT id, profile_name, full_name, location_name, birth_place FROM birth_profiles WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->bind_param('ii', $profileId, $uid); $stmt->execute(); $profile = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$profile) { http_response_code(404); exit('Birth profile not found.'); }
$pid = (int) $profile['id'];
$core = [
    ['D1','Rashi','Overall life, personality and the foundation of the birth chart.','d1-chart.php'],
    ['D9','Navamsa','Marriage, dharma and deeper planetary strength.','d9-chart.php'],
    ['D10','Dasamsa','Career, profession and public work.','d10-chart.php'],
    ['D7','Saptamsa','Children, progeny and creative continuation.','d7-chart.php'],
    ['D2','Hora','Wealth, resources and material sustenance.','d2-chart.php'],
    ['D4','Chaturthamsa','Property, fortune and foundations.','d4-chart.php'],
];
$advanced = [
    ['D3','Drekkana','Siblings, courage and initiative.','d3-chart.php'],
    ['D5','Panchamsa','Specialist varga for focused analysis.','d5-chart.php'],
    ['D12','Dwadasamsa','Parents, ancestry and lineage.','d12-chart.php'],
    ['D16','Shodasamsa','Vehicles, comforts and experiences of ease.','d16-chart.php'],
    ['D20','Vimsamsa','Spiritual practice and religious inclination.','d20-chart.php'],
    ['D24','Siddhamsa','Education, learning and knowledge.','d24-chart.php'],
    ['D27','Bhamsa','Strengths, weaknesses and resilience.','d27-chart.php'],
    ['D30','Trimsamsa','Difficulties, vulnerabilities and adverse influences.','d30-chart.php'],
    ['D40','Khavedamsa','Advanced lineage and auspicious-influence analysis.','d40-chart.php'],
    ['D45','Akshavedamsa','Advanced character and lineage analysis.','d45-chart.php'],
    ['D60','Shashtiamsa','Fine-grained karmic/root-level analysis.','d60-chart.php'],
];
function chart_link(array $chart, int $pid): string {
    return e(APP_BASE_PATH . '/' . $chart[3] . '?profile_id=' . $pid);
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Astrology Charts — ASTROPOP</title><link rel="stylesheet" href="<?=e(APP_BASE_PATH)?>/assets/css/app.css"></head><body><main class="app-shell"><header class="topbar"><a class="brand" href="<?=e(APP_BASE_PATH)?>/dashboard.php">✦ ASTROPOP</a><nav><a href="<?=e(APP_BASE_PATH)?>/dashboard.php">Dashboard</a><a href="<?=e(APP_BASE_PATH)?>/kundli.php?profile_id=<?=$pid?>">Kundli</a><a href="<?=e(APP_BASE_PATH)?>/birth/">Birth profile</a><a href="<?=e(APP_BASE_PATH)?>/logout.php">Log out</a></nav></header><section class="content-wrap"><div class="section-heading"><p class="eyebrow">VARGA CHARTS</p><h1>Astrology charts</h1><p class="muted"><?=e((string)$profile['full_name'])?> · <?=e((string)($profile['location_name'] ?: $profile['birth_place']))?></p></div><section class="card"><div class="row-between"><div><p class="eyebrow">START HERE</p><h2>Core charts</h2></div><a class="button button-primary" href="<?=e(APP_BASE_PATH)?>/kundli.php?profile_id=<?=$pid?>">Back to Kundli</a></div><p class="muted">These are the charts most users need for practical astrology. ASTROPOP will use them as the foundation for interpretation.</p><div class="chart-hub-grid"><?php foreach($core as $chart): ?><a class="chart-hub-item" href="<?=chart_link($chart,$pid)?>"><span class="chart-code"><?=e($chart[0])?></span><div><strong><?=e($chart[1])?></strong><span><?=e($chart[2])?></span></div><span class="chart-arrow">→</span></a><?php endforeach; ?></div></section><section class="card"><div class="row-between"><div><p class="eyebrow">ADVANCED</p><h2>Advanced Vargas</h2></div><span class="pill">For deeper analysis</span></div><p class="muted">These charts remain available, but they are intentionally separated from the primary astrology experience.</p><div class="chart-hub-grid"><?php foreach($advanced as $chart): ?><a class="chart-hub-item" href="<?=chart_link($chart,$pid)?>"><span class="chart-code"><?=e($chart[0])?></span><div><strong><?=e($chart[1])?></strong><span><?=e($chart[2])?></span></div><span class="chart-arrow">→</span></a><?php endforeach; ?></div></section></section></main></body></html>
