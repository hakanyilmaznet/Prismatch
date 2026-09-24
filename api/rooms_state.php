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

$guid = trim((string)($_GET['guid'] ?? ''));
if ($guid === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_request']);
    exit;
}

$room = get_room_by_guid($guid);
if (!$room) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'not_found']);
    exit;
}

echo json_encode([
    'ok' => true,
    'room' => [
        'guid' => $room['guid'],
        'name' => $room['name'],
        'status' => $room['status'],
        'rounds_total' => (int)$room['rounds_total'],
        'current_round' => (int)$room['current_round'],
        'owner_email' => $room['owner_email'],
        'owner_id' => $room['owner_id'] ?? null,
    ],
    'players' => list_room_players((string)$room['id']),
]);
