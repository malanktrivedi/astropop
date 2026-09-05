<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/AiChatService.php';
requireLogin();

$userId = current_user_id();
$profileId = (int) ($_GET['profile_id'] ?? 0);
$service = new AiChatService();
$profiles = [];
$stmt = db()->prepare('SELECT id, profile_name, full_name FROM birth_profiles WHERE user_id=? ORDER BY id DESC');
$stmt->bind_param('i', $userId);
$stmt->execute();
$profiles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$contextReady = false;
$context = null;
$history = [];
if ($profileId > 0) {
    try {
        $context = $service->buildContext($userId, $profileId);
        $contextReady = true;
        $history = $service->history($userId, $profileId, 50);
    } catch (Throwable $e) {
        $context = null;
        $history = [];
    }
}
$balance = $service->balance($userId);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>AI Astrology Chat — ASTROPOP</title>
    <link rel="stylesheet" href="<?= e(APP_BASE_PATH) ?>/assets/css/app.css">
    <style>
        .chat-shell{max-width:980px;margin:0 auto;padding:32px 20px}.chat-header{display:flex;justify-content:space-between;gap:20px;align-items:flex-start}.chat-balance{padding:10px 14px;border:1px solid #ddd;border-radius:999px}.chat-window{min-height:480px;margin-top:24px;border:1px solid #ddd;border-radius:20px;background:#fff;padding:24px;display:flex;flex-direction:column;gap:14px}.chat-empty{text-align:center;padding:100px 20px;color:#667085}.chat-message{max-width:78%;padding:12px 15px;border-radius:16px;white-space:pre-wrap;word-break:break-word}.chat-message-user{align-self:flex-end;background:#e9f7f6}.chat-message-ai{align-self:flex-start;background:#f4f5f7}.chat-message-system{align-self:center;background:#fff7e6;color:#7a4b00;font-size:13px}.chat-meta{display:block;margin-top:6px;font-size:11px;opacity:.6}.chat-compose{display:flex;gap:12px;margin-top:18px}.chat-compose textarea{flex:1;min-height:56px;resize:vertical}.chat-notice{margin-top:14px;padding:12px 14px;border-radius:12px;background:#fff7e6;color:#7a4b00}.chat-context{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}.chat-chip{padding:7px 10px;border-radius:999px;background:#f3f5f7;font-size:13px}.profile-picker{margin-top:20px;max-width:420px}.profile-picker select{width:100%}
    </style>
</head>
<body>
<main class="app-shell">
<header class="topbar">
    <a class="brand" href="<?= e(APP_BASE_PATH) ?>/dashboard.php">✦ ASTROPOP</a>
    <nav><a href="<?= e(APP_BASE_PATH) ?>/dashboard.php">Dashboard</a><a href="<?= e(APP_BASE_PATH) ?>/logout.php">Log out</a></nav>
</header>
<section class="chat-shell">
    <div class="chat-header">
        <div><p class="eyebrow">ASTROPOP AI</p><h1>Ask your chart</h1><p class="muted">AI chat uses your saved Kundli context. Normal chat does not recalculate your birth chart.</p></div>
        <div class="chat-balance">🪙 <?= e($balance) ?> ASTRO_COIN</div>
    </div>

    <form class="profile-picker" method="get">
        <label for="profile_id">Birth profile</label>
        <select id="profile_id" name="profile_id" onchange="this.form.submit()">
            <option value="0">Select a profile</option>
            <?php foreach ($profiles as $profile): ?>
                <option value="<?= (int)$profile['id'] ?>" <?= $profileId === (int)$profile['id'] ? 'selected' : '' ?>><?= e((string)$profile['profile_name']) ?> — <?= e((string)$profile['full_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if ($contextReady): ?>
        <div class="chat-context">
            <span class="chat-chip">Lagna: <?= e((string)($context['lagna'] ?? '—')) ?></span>
            <span class="chat-chip">Rashi: <?= e((string)($context['rashi'] ?? '—')) ?></span>
            <span class="chat-chip">Nakshatra: <?= e((string)($context['nakshatra'] ?? '—')) ?></span>
            <span class="chat-chip">D9 Lagna: <?= e((string)($context['d9_lagna'] ?? '—')) ?></span>
        </div>
    <?php endif; ?>

    <div class="chat-window">
        <?php if (!$profileId): ?>
            <div class="chat-empty"><h2>Select your birth profile</h2><p>Choose a saved profile above to start an astrology conversation.</p></div>
        <?php elseif (!$contextReady): ?>
            <div class="chat-empty"><h2>Kundli not ready</h2><p>Generate a complete Kundli for this profile before starting AI chat.</p><a class="button button-primary" href="<?= e(APP_BASE_PATH) ?>/birth/?profile_id=<?= $profileId ?>">Open birth profile</a></div>
        <?php elseif (!$history): ?>
            <div class="chat-empty"><h2>What would you like to know?</h2><p>Career, marriage, money, education, timing, yogas, dashas and more.</p></div>
        <?php else: ?>
            <?php foreach ($history as $message): ?>
                <?php $sender = (string)($message['sender_type'] ?? 'system'); ?>
                <div class="chat-message chat-message-<?= e(in_array($sender, ['user','ai','system'], true) ? $sender : 'system') ?>">
                    <?= e((string)$message['body']) ?>
                    <span class="chat-meta"><?= e(ucfirst($sender)) ?> · <?= e((string)$message['created_at']) ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <form class="chat-compose" method="post" action="<?= e(APP_BASE_PATH) ?>/chat/send.php">
        <?= csrf_field() ?>
        <input type="hidden" name="profile_id" value="<?= $profileId ?>">
        <textarea name="message" maxlength="4000" placeholder="Ask a question about your birth chart..." <?= $contextReady ? '' : 'disabled' ?> required></textarea>
        <button class="button button-primary" type="submit" <?= $contextReady ? '' : 'disabled' ?>>Send</button>
    </form>
    <div class="chat-notice">AI provider integration is intentionally gated until the exact VedicAstroAPI Postman request/response contract is wired. No guessed provider request is sent and no provider credits are consumed.</div>
</section>
</main>
</body>
</html>
