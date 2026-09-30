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

$startImmediately = !empty($input['start_immediately']);
Logger::info('API:rooms_restart', "Incoming request for guid={$guid} from user={$userId} (start_immediately=" . ($startImmediately ? '1' : '0') . ")");

$service = new RoomGameService();
$result = $service->restartRoom($guid, (string)$userId, $startImmediately);

if (!($result['ok'] ?? false)) {
    $code = (int)($result['code'] ?? 400);
    http_response_code($code);
    Logger::warning('API:rooms_restart', "Failed restart for guid={$guid}, code={$code}, error=" . ($result['error'] ?? 'unknown'));
    echo json_encode($result);
    exit;
}

Logger::info('API:rooms_restart', "Success restart for guid={$guid}, round=" . ($result['round'] ?? 0));
echo json_encode($result);
