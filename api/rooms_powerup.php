<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../pusher.php';

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
$type = trim((string)($input['type'] ?? ($_POST['type'] ?? '')));
$targetUserId = isset($input['target_user_id']) ? trim((string)$input['target_user_id']) : (isset($_POST['target_user_id']) ? trim((string)$_POST['target_user_id']) : null);

if ($guid === '' || $type === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_request']);
    exit;
}

$userName = (string)($_SESSION['user_name'] ?? '');
if ($userName === '') {
    $userName = user_display_name((string)$userId);
}
if ($userName === '') {
    $userName = user_display_name_from_row(['email' => $email]);
}

$service = new RoomGameService();
$result = $service->usePowerup($guid, (string)$userId, (string)$email, $userName, $type, $targetUserId);

if (!($result['ok'] ?? false)) {
    $code = (int)($result['code'] ?? 400);
    http_response_code($code);
    echo json_encode($result);
    exit;
}

echo json_encode($result);
