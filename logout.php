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

$target = APP_BASE_URL . '';
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Logout</title>
</head>
<body>
  <script>
    try { localStorage.removeItem('prismatchUserId'); } catch(e) {}
    window.location.href = <?= json_encode($target) ?>;
  </script>
  <noscript>
    <meta http-equiv="refresh" content="0;url=<?= htmlspecialchars($target, ENT_QUOTES, 'UTF-8') ?>">
  </noscript>
</body>
</html>
<?php
exit;
