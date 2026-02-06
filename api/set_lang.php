<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../i18n.php';

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$lang = is_array($data) ? (string)($data['lang'] ?? '') : '';

set_lang($lang);

echo json_encode(['ok' => true, 'lang' => get_lang()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);



