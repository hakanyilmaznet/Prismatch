<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../pusher.php';

header('Content-Type: application/json; charset=utf-8');

$email = $_SESSION['user_email'] ?? null;
$userId = $_SESSION['user_id'] ?? null;
if (!$email || !$userId) {
    http_response_code(403);
    echo json_encode(['error' => 'login_required']);
    exit;
}

$socketId = (string)($_POST['socket_id'] ?? '');
$channel = (string)($_POST['channel_name'] ?? '');
if ($socketId === '' || $channel === '') {
    http_response_code(400);
    echo json_encode(['error' => 'bad_request']);
    exit;
}

echo pusher_auth_response($socketId, $channel, (string)$userId, ['email' => $email]);
