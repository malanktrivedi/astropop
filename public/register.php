<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
if (is_authenticated()) { redirect('/dashboard.php'); }
$errors = [];
$name = '';
$email = '';
if (is_post()) {
    verify_csrf();
    $name = post_value('name');
    $email = strtolower(post_value('email'));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirmation'] ?? '');
    if ($name === '' || mb_strlen($name) > 160) $errors[] = 'Enter your name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) $errors[] = 'Enter a valid email address.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';
    if (!$errors) {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email); $stmt->execute(); $exists = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($exists) $errors[] = 'An account with this email already exists.';
    }
    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = db()->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
        $stmt->bind_param('sss', $name, $email, $hash); $stmt->execute(); $stmt->close();
        flash('success', 'Account created. Please log in.'); redirect('/login.php');
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Create account — ASTROPOP</title><link rel="stylesheet" href="<?= e(APP_BASE_PATH) ?>/assets/css/app.css"></head><body><main class="page-shell"><section class="auth-card"><a class="brand" href="<?= e(APP_BASE_PATH) ?>/">✦ ASTROPOP</a><p class="eyebrow">YOUR ASTROLOGY JOURNEY</p><h1>Create your account</h1><p class="muted">Start with your personal astrology profile.</p><?php if ($errors): ?><div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?><form method="post" class="form-stack"><?= csrf_field() ?><label>Name<input name="name" value="<?= e($name) ?>" autocomplete="name" required></label><label>Email<input type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required></label><label>Password<input type="password" name="password" autocomplete="new-password" required><small>At least 8 characters.</small></label><label>Confirm password<input type="password" name="password_confirmation" autocomplete="new-password" required></label><button class="button button-primary" type="submit">Create account</button></form><p class="auth-footer">Already have an account? <a href="<?= e(APP_BASE_PATH) ?>/login.php">Log in</a></p></section></main></body></html>
