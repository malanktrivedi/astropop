<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/AiChatService.php';
requireLogin();

if (!is_post()) {
    http_response_code(405);
    exit('Method not allowed.');
}
verify_csrf();

$userId = current_user_id();
$profileId = (int) ($_POST['profile_id'] ?? 0);
$message = trim((string) ($_POST['message'] ?? ''));

if ($profileId <= 0 || $message === '') {
    http_response_code(422);
    exit('A birth profile and message are required.');
}

try {
    $stmt = db()->prepare('SELECT id FROM birth_profiles WHERE id=? AND user_id=? LIMIT 1');
    $stmt->bind_param('ii', $profileId, $userId);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$profile) {
        http_response_code(404);
        exit('Birth profile not found.');
    }

    $service = new AiChatService();
    $service->send($userId, $profileId, $message);
    header('Location: ' . APP_BASE_PATH . '/chat/?profile_id=' . $profileId);
    exit;
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    exit(e($e->getMessage()));
} catch (RuntimeException $e) {
    app_error_log('AI chat request failed', ['message' => $e->getMessage(), 'user_id' => $userId, 'profile_id' => $profileId]);
    http_response_code(503);
    exit('AI chat could not be completed. Please check your ASTRO_COIN balance and try again.');
} catch (Throwable $e) {
    app_error_log('AI chat unexpected failure', ['message' => $e->getMessage(), 'user_id' => $userId, 'profile_id' => $profileId]);
    http_response_code(500);
    exit('AI chat encountered an unexpected error.');
}
