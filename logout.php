<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Clear persistent user cookie
setcookie('pm_persistent_uid', '', time() - 42000, '/', '', COOKIE_SECURE, true);

session_destroy();

header('Location: ' . APP_BASE_URL);
exit;
