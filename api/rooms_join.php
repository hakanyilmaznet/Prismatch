<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
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

$input = json_decode((string)file_get_contents('php://input'), true);
$guid = trim((string)($input['guid'] ?? ($_POST['guid'] ?? '')));
if ($guid === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_request']);
    exit;
}

Logger::info('API:rooms_join', "Player join request for guid={$guid} by user={$userId}, email={$email}");
$service = new RoomGameService();
$result = $service->joinRoom($guid, (string)$userId, (string)$email);

if (!($result['ok'] ?? false)) {
    $code = (int)($result['code'] ?? 400);
    http_response_code($code);
    Logger::warning('API:rooms_join', "Failed join for guid={$guid}, code={$code}, error=" . ($result['error'] ?? 'unknown'));
    echo json_encode($result);
    exit;
}

Logger::info('API:rooms_join', "Player joined successfully for guid={$guid}");
echo json_encode($result);
