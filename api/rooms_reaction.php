<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../pusher.php';

use Prismatch\Core\Logger;
use Prismatch\Services\RoomGameService;

header('Content-Type: application/json; charset=utf-8');

$email = $_SESSION['user_email'] ?? null;
$userId = $_SESSION['user_id'] ?? null;
if (!$email || !$userId) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'login_required']);
    exit;
}

$raw = (string)file_get_contents('php://input');
$input = json_decode($raw, true) ?? [];
$guid = trim((string)($input['guid'] ?? ($_POST['guid'] ?? '')));
$emoji = trim((string)($input['emoji'] ?? ($_POST['emoji'] ?? '🔥')));
$sound = trim((string)($input['sound'] ?? ($_POST['sound'] ?? 'pop')));
$text = isset($input['text']) ? trim((string)$input['text']) : (isset($_POST['text']) ? trim((string)$_POST['text']) : null);

if ($guid === '' || $emoji === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_request']);
    exit;
}

// Throttle to prevent spam: max 1 reaction per 200ms per user session
$now = microtime(true);
$lastReaction = (float)($_SESSION['last_reaction_time'] ?? 0.0);
if (($now - $lastReaction) < 0.20) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'rate_limited']);
    exit;
}
$_SESSION['last_reaction_time'] = $now;

$userName = (string)($_SESSION['user_name'] ?? '');
if ($userName === '') {
    $userName = user_display_name((string)$userId);
}
if ($userName === '') {
    $userName = user_display_name_from_row(['email' => $email]);
}

$service = new RoomGameService();
$result = $service->broadcastReaction($guid, (string)$userId, (string)$email, $userName, $emoji, $sound, $text);

if (!($result['ok'] ?? false)) {
    $code = (int)($result['code'] ?? 400);
    http_response_code($code);
    echo json_encode($result);
    exit;
}

echo json_encode($result);
