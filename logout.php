<?php
require_once __DIR__ . '/config.php';

session_name(SESSION_NAME);
session_set_cookie_params([
  'httponly' => true,
  'secure' => COOKIE_SECURE,
  'samesite' => 'Lax',
]);
session_start();

$_SESSION = [];
session_destroy();

header('Location: ' . APP_BASE_URL . '');
exit;
