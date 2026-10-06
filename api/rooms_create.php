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
if (!in_array($gameMode, ['elimination', 'points', 'flags', 'teams'], true)) {
    $gameMode = 'elimination';
}

$settingsJson = null;
if ($gameMode === 'teams') {
    $rawTeams = $input['teams'] ?? ($_POST['teams'] ?? null);
    $defaultDefs = [
        'red'    => ['id' => 'red',    'name' => 'Red',    'color' => '#ef4444'],
        'blue'   => ['id' => 'blue',   'name' => 'Blue',   'color' => '#3b82f6'],
        'green'  => ['id' => 'green',  'name' => 'Green',  'color' => '#10b981'],
        'yellow' => ['id' => 'yellow', 'name' => 'Yellow', 'color' => '#f59e0b'],
    ];
    $teamKeys = ['red', 'blue', 'green', 'yellow'];
    $teamsConfig = [];

    if (is_array($rawTeams)) {
        // Could be indexed list of names, or list of objects, or assoc by id
        $count = count($rawTeams);
        $count = max(2, min(4, $count));
        $idx = 0;
        foreach ($rawTeams as $key => $item) {
            if ($idx >= 4) break;
            $teamId = $teamKeys[$idx];
            $defaultDef = $defaultDefs[$teamId];
            
            $teamName = '';
            if (is_array($item)) {
                $teamName = trim((string)($item['name'] ?? ''));
            } elseif (is_string($item)) {
                $teamName = trim($item);
            }
            
            if ($teamName === '') {
                $teamName = $defaultDef['name'];
            } else {
                $teamName = mb_substr(strip_tags($teamName), 0, 40);
            }

            $teamsConfig[] = [
                'id' => $teamId,
                'name' => $teamName,
                'color' => $defaultDef['color']
            ];
            $idx++;
            if ($idx >= $count) break;
        }
    }

    if (count($teamsConfig) < 2) {
        $teamsConfig = [
            $defaultDefs['red'],
            $defaultDefs['blue'],
        ];
    }

    $settingsJson = json_encode(['teams' => $teamsConfig], JSON_UNESCAPED_UNICODE);
}

try {
    $room = create_room((string)$userId, (string)$email, $rounds, $name, $isPrivate, $gameMode, $settingsJson);
    echo json_encode([
        'ok' => true,
        'guid' => $room['guid'],
        'is_private' => !empty($room['is_private']),
        'game_mode' => $room['game_mode'],
        'rounds_total' => $rounds,
        'settings_json' => $room['settings_json'] ?? null,
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'create_failed']);
}
