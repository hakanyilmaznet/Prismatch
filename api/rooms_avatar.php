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
$avatar = trim((string)($input['avatar'] ?? ($_POST['avatar'] ?? '')));

if ($guid === '' || $avatar === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_request']);
    exit;
}

$service = new RoomGameService();
$result = $service->chooseAvatar($guid, (string)$userId, $avatar);

if (!($result['ok'] ?? false)) {
    $code = (int)($result['code'] ?? 400);
    http_response_code($code);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
