<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    if (!str_starts_with($path, '/')) {
        $path = '/' . $path;
    }
    header('Location: ' . APP_BASE_PATH . $path);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post_value(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function flash(string $key, ?string $value = null): ?string
{
    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }
    $message = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return is_string($message) ? $message : null;
}

function app_error_log(string $message, array $context = []): void
{
    error_log('[ASTROPOP] ' . $message . ($context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : ''));
}
