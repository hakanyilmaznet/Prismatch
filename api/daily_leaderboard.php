<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

$day = isset($_GET['day']) ? $_GET['day'] : gmdate('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
  $day = gmdate('Y-m-d');
}

$rows = daily_leaderboard($day, null, 200);
$out = [];
foreach ($rows as $r) {
  $out[] = [
    'score' => (int)(isset($r['score']) ? $r['score'] : 0),
    'level' => (int)(isset($r['reached_level']) ? $r['reached_level'] : 0),
    'correct' => (int)(isset($r['total_correct']) ? $r['total_correct'] : 0),
    'durationMs' => (int)(isset($r['duration_ms']) ? $r['duration_ms'] : 0),
    'user' => (string)(isset($r['email']) ? $r['email'] : ''),
  ];
}

echo json_encode(['ok' => true, 'rows' => $out], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
