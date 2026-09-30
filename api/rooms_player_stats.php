<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../db.php';

use Prismatch\Core\Database;
use Prismatch\Repositories\RoomRepository;

header('Content-Type: application/json; charset=utf-8');

$email = $_SESSION['user_email'] ?? null;
$userId = $_SESSION['user_id'] ?? null;
if (!$email || !$userId) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'login_required']);
    exit;
}

$guid = trim((string)($_GET['guid'] ?? ($_POST['guid'] ?? '')));
if ($guid === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_request']);
    exit;
}

$roomRepo = new RoomRepository();
$room = $roomRepo->getRoomByGuid($guid);
if (!$room) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'not_found']);
    exit;
}

$roomId = (string)$room['id'];
$pdo = Database::getConnection();

// Fetch events for this player in this room
$stmt = $pdo->prepare("
    SELECT round_index, event_type, payload_json, created_at
    FROM room_events
    WHERE room_id = :rid
      AND user_id = :uid
      AND event_type IN ('answer', 'timeout', 'eliminate')
    ORDER BY round_index ASC
");
$stmt->execute([':rid' => $roomId, ':uid' => (string)$userId]);
$rows = $stmt->fetchAll() ?: [];

$stats = [];
$totalMs = 0;
$answeredCount = 0;
$fastestMs = null;
$correctCount = 0;
$wrongCount = 0;
$timeoutCount = 0;
$totalScoreDelta = 0;

$roundStats = [];

foreach ($rows as $row) {
    $payload = json_decode((string)$row['payload_json'], true) ?: [];
    $round = (int)$row['round_index'];
    if ($round < 1) continue;
    $eventType = (string)$row['event_type'];
    $correct = !empty($payload['correct']);
    $responseMs = (int)($payload['response_ms'] ?? 0);
    $scoreDelta = (int)($payload['score_delta'] ?? 0);
    $target = (string)($payload['target'] ?? '');
    $picked = isset($payload['picked']) && $payload['picked'] !== null ? (string)$payload['picked'] : null;

    $isTimeout = ($eventType === 'timeout' || $picked === null);

    $roundStats[$round] = [
        'round' => $round,
        'event_type' => $eventType,
        'target' => $target,
        'picked' => $picked,
        'is_timeout' => $isTimeout,
        'correct' => $correct,
        'response_ms' => $responseMs,
        'score_delta' => $scoreDelta,
        'created_at' => $row['created_at'],
    ];
}

ksort($roundStats);
$stats = array_values($roundStats);

foreach ($stats as $st) {
    if ($st['correct']) {
        $correctCount++;
    } elseif ($st['is_timeout']) {
        $timeoutCount++;
    } else {
        $wrongCount++;
    }

    if (!$st['is_timeout'] && $st['response_ms'] > 0) {
        $totalMs += $st['response_ms'];
        $answeredCount++;
        if ($fastestMs === null || $st['response_ms'] < $fastestMs) {
            $fastestMs = $st['response_ms'];
        }
    }

    $totalScoreDelta += $st['score_delta'];
}

$avgMs = $answeredCount > 0 ? (int)round($totalMs / $answeredCount) : 0;
$totalRounds = count($stats);
$accuracyPercent = $totalRounds > 0 ? round(($correctCount / $totalRounds) * 100, 1) : 0;

echo json_encode([
    'ok' => true,
    'guid' => $guid,
    'game_mode' => (string)($room['game_mode'] ?? 'elimination'),
    'summary' => [
        'total_rounds' => $totalRounds,
        'correct_count' => $correctCount,
        'wrong_count' => $wrongCount,
        'timeout_count' => $timeoutCount,
        'accuracy_percent' => $accuracyPercent,
        'avg_response_ms' => $avgMs,
        'fastest_response_ms' => $fastestMs ?? 0,
        'total_score_delta' => $totalScoreDelta,
    ],
    'stats' => $stats,
]);
