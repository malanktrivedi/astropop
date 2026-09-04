<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
if (is_authenticated()) { redirect('/dashboard.php'); }
$errors = [];
$email = '';
$success = flash('success');
if (is_post()) {
    verify_csrf();
    $email = strtolower(post_value('email'));
    $password = (string) ($_POST['password'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if ($password === '') $errors[] = 'Enter your password.';
    if (!$errors) {
        $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email); $stmt->execute(); $user = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            $errors[] = 'Invalid email or password.';
        } else {
            login_user((int) $user['id']);
            redirect('/dashboard.php');
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Log in — ASTROPOP</title><link rel="stylesheet" href="<?= e(APP_BASE_PATH) ?>/assets/css/app.css"></head><body><main class="page-shell"><section class="auth-card"><a class="brand" href="<?= e(APP_BASE_PATH) ?>/">✦ ASTROPOP</a><p class="eyebrow">WELCOME BACK</p><h1>Log in</h1><p class="muted">Continue your astrology journey.</p><?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?><?php if ($errors): ?><div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?><form method="post" class="form-stack"><?= csrf_field() ?><label>Email<input type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required></label><label>Password<input type="password" name="password" autocomplete="current-password" required></label><button class="button button-primary" type="submit">Continue</button></form><p class="auth-footer">New to ASTROPOP? <a href="<?= e(APP_BASE_PATH) ?>/register.php">Create an account</a></p></section></main></body></html>
