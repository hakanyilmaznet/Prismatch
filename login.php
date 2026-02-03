<?php include __DIR__ . '/footer.php'; ?>
<?php
require_once __DIR__ . '/config.php';

session_name(SESSION_NAME);
session_set_cookie_params([
  'httponly' => true,
  'secure' => COOKIE_SECURE,
  'samesite' => 'Lax',
]);
session_start();

// CSRF state
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

$params = [
  'client_id' => GOOGLE_CLIENT_ID,
  'redirect_uri' => GOOGLE_REDIRECT_URI,
  'response_type' => 'code',
  'scope' => 'openid email',
  'include_granted_scopes' => 'true',
  'access_type' => 'online',
  'prompt' => 'select_account',
  'state' => $state,
];

$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
header('Location: ' . $authUrl);
exit;
