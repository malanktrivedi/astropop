<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$dbOk = false;
try {
    db()->query('SELECT 1');
    $dbOk = true;
} catch (Throwable $e) {
    app_error_log('Health check database exception');
}

http_response_code($dbOk ? 200 : 503);
echo json_encode([
    'ok' => $dbOk,
    'application' => APP_NAME,
    'php' => PHP_VERSION,
    'database' => $dbOk ? 'ok' : 'error',
    'vedic_api_configured' => VEDIC_API_KEY !== '',
], JSON_UNESCAPED_SLASHES);
