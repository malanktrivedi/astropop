<?php
declare(strict_types=1);

function load_dotenv(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
        }
    }
}

load_dotenv(dirname(__DIR__) . '/.env');

function env_value(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return $value === false || $value === '' ? $default : $value;
}

const APP_NAME = 'ASTROPOP';
const APP_TIMEZONE = 'Asia/Kolkata';
const APP_ENV = 'development';

date_default_timezone_set((string) env_value('APP_TIMEZONE', APP_TIMEZONE));

$basePath = rtrim((string) env_value('APP_BASE_PATH', '/astropop/public'), '/');
define('APP_BASE_PATH', $basePath);
define('DB_HOST', (string) env_value('DB_HOST', '127.0.0.1'));
define('DB_PORT', (int) env_value('DB_PORT', '3306'));
define('DB_NAME', (string) env_value('DB_NAME', 'astropop'));
define('DB_USER', (string) env_value('DB_USER', 'root'));
define('DB_PASSWORD', (string) env_value('DB_PASSWORD', ''));
define('VEDIC_API_BASE_URL', rtrim((string) env_value('VEDIC_API_BASE_URL', 'https://api.vedicastroapi.com/v3-json'), '/'));
define('VEDIC_API_KEY', (string) env_value('VEDIC_API_KEY', ''));
define('VEDIC_API_TIMEOUT', max(5, (int) env_value('VEDIC_API_TIMEOUT', '15')));
define('VEDIC_API_CONNECT_TIMEOUT', max(2, (int) env_value('VEDIC_API_CONNECT_TIMEOUT', '5')));

if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

error_reporting(APP_ENV === 'development' ? E_ALL : 0);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
