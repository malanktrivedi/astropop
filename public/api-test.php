<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/VedicAstroAPI.php';
requireLogin();

$api = new VedicAstroAPI(VEDIC_API_BASE_URL, VEDIC_API_KEY, VEDIC_API_TIMEOUT, VEDIC_API_CONNECT_TIMEOUT);
$result = null;

if (is_post()) {
    verify_csrf();
    // Verified from the supplied VedicAstroAPI MCP documentation:
    // GET /v3-json/prediction/daily-sun
    // date=DD/MM/YYYY, zodiac=1..12, split=true|false, type=big|small, lang.
    $result = $api->request('/prediction/daily-sun', 'GET', [
        'date' => date('d/m/Y'),
        'zodiac' => 1,
        'split' => true,
        'type' => 'small',
        'lang' => 'en',
    ]);
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>API test — ASTROPOP</title><link rel="stylesheet" href="<?= e(APP_BASE_PATH) ?>/assets/css/app.css"></head><body><main class="app-shell"><header class="topbar"><a class="brand" href="<?= e(APP_BASE_PATH) ?>/dashboard.php">✦ ASTROPOP</a><nav><a href="<?= e(APP_BASE_PATH) ?>/dashboard.php">Dashboard</a></nav></header><section class="content-wrap"><div class="section-heading"><p class="eyebrow">DEVELOPMENT</p><h1>VedicAstroAPI test</h1><p class="muted">Server-side cURL test for a documented endpoint. The API key is never rendered.</p></div><div class="card"><form method="post"><?= csrf_field() ?><button class="button button-primary" type="submit">Run API test</button></form><?php if ($result !== null): ?><hr><p><strong>HTTP status:</strong> <?= e((string) $result['status']) ?></p><p><strong>Result:</strong> <?= $result['ok'] ? 'Success' : e((string) $result['error']) ?></p><?php if ($result['data'] !== null): ?><pre class="api-output"><?= e(json_encode($result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre><?php endif; ?><?php endif; ?></div></section></main></body></html>
