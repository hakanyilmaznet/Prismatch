<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
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

$input = json_decode((string)file_get_contents('php://input'), true);
$guid = trim((string)($input['guid'] ?? ($_POST['guid'] ?? '')));
if ($guid === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_request']);
    exit;
}

$service = new RoomGameService();
$result = $service->startOrNextRound($guid, (string)$userId, true);

if (!($result['ok'] ?? false)) {
    http_response_code($result['code'] ?? 400);
    echo json_encode($result);
    exit;
}

echo json_encode($result);
