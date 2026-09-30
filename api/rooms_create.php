<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

$email = $_SESSION['user_email'] ?? null;
$userId = $_SESSION['user_id'] ?? null;
if (!$email || !$userId) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'login_required']);
    exit;
}

$input = json_decode((string)file_get_contents('php://input'), true);
$rounds = 25;
$name = (string)($input['name'] ?? ($_POST['name'] ?? ''));
if (trim($name) === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'name_required']);
    exit;
}

$isPrivate = !empty($input['is_private']) || !empty($_POST['is_private']);
$gameMode = (string)($input['game_mode'] ?? ($_POST['game_mode'] ?? 'elimination'));
if (!in_array($gameMode, ['elimination', 'points', 'flags'], true)) {
    $gameMode = 'elimination';
}

try {
    $room = create_room((string)$userId, (string)$email, $rounds, $name, $isPrivate, $gameMode);
    echo json_encode([
        'ok' => true,
        'guid' => $room['guid'],
        'is_private' => !empty($room['is_private']),
        'game_mode' => $room['game_mode'],
        'rounds_total' => $rounds,
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'create_failed']);
}
