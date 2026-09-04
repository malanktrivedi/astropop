<?php
declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $provided = (string) ($_POST['csrf_token'] ?? '');
    $stored = (string) ($_SESSION['_csrf'] ?? '');

    if ($provided === '' || $stored === '' || !hash_equals($stored, $provided)) {
        http_response_code(419);
        exit('Your form session expired. Please try again.');
    }
}
