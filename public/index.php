<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
if (is_authenticated()) { redirect('/dashboard.php'); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>ASTROPOP</title><link rel="stylesheet" href="<?= e(APP_BASE_PATH) ?>/assets/css/app.css"></head><body><main class="page-shell hero-shell"><section class="hero-card"><div class="brand-mark">✦</div><p class="eyebrow">PERSONAL ASTROLOGY COMPANION</p><h1>Your stars.<br>Your questions.<br>Your answers.</h1><p class="hero-copy">A modern, conversational Vedic astrology experience built around your birth chart.</p><div class="hero-actions"><a class="button button-primary" href="<?= e(APP_BASE_PATH) ?>/register.php">Get started</a><a class="button button-secondary" href="<?= e(APP_BASE_PATH) ?>/login.php">Log in</a></div></section></main></body></html>
