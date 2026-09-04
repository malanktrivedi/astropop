<?php
declare(strict_types=1);

function current_user_id(): ?int
{
    $id = $_SESSION['user_id'] ?? null;
    return is_numeric($id) ? (int) $id : null;
}

function is_authenticated(): bool
{
    return current_user_id() !== null;
}

function requireLogin(): void
{
    if (!is_authenticated()) {
        redirect('/login.php');
    }
}

function login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['authenticated_at'] = time();
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}
