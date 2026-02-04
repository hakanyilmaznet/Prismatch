<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/i18n.php';

session_name(SESSION_NAME);
session_set_cookie_params([
  'httponly' => true,
  'secure' => COOKIE_SECURE,
  'samesite' => 'Lax',
]);
session_start();

function fail(string $msg, int $code = 400): void {
  http_response_code($code);
  $title = t('callback_error_title');
  $back = t('callback_back');
  echo "<h1>" . htmlspecialchars($title) . "</h1><p>" . htmlspecialchars($msg) . "</p><p><a href='index.php'>" . htmlspecialchars($back) . "</a></p>";
  exit;
}

function is_safe_next_path(string $path): bool {
  if ($path === '') return false;
  $parts = parse_url($path);
  if ($parts === false) return false;
  if (isset($parts['scheme']) || isset($parts['host'])) return false;
  if (str_starts_with($path, '//')) return false;
  return true;
}

if (!isset($_GET['state'], $_SESSION['oauth_state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
  fail(t('callback_err_invalid_state'));
}
unset($_SESSION['oauth_state']);

if (!isset($_GET['code'])) {
  fail(t('callback_err_no_code'));
}

$code = $_GET['code'];

// 1) Token al
$tokenEndpoint = 'https://oauth2.googleapis.com/token';
$postFields = [
  'code' => $code,
  'client_id' => GOOGLE_CLIENT_ID,
  'client_secret' => GOOGLE_CLIENT_SECRET,
  'redirect_uri' => GOOGLE_REDIRECT_URI,
  'grant_type' => 'authorization_code',
];

$ch = curl_init($tokenEndpoint);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => http_build_query($postFields),
  CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
]);
$tokenResp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($tokenResp === false || $httpCode >= 400) {
  fail(t('callback_err_token_http', ['code' => $httpCode]));
}

$tokenJson = json_decode($tokenResp, true);
if (!is_array($tokenJson) || empty($tokenJson['access_token'])) {
  fail(t('callback_err_token_invalid'));
}

$accessToken = $tokenJson['access_token'];

// 2) Userinfo al (email)
$userInfoEndpoint = 'https://openidconnect.googleapis.com/v1/userinfo';
$ch = curl_init($userInfoEndpoint);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
]);
$userResp = curl_exec($ch);
$uHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($userResp === false || $uHttp >= 400) {
  fail(t('callback_err_userinfo_http', ['code' => $uHttp]));
}

$userJson = json_decode($userResp, true);
if (!is_array($userJson) || empty($userJson['email'])) {
  fail(t('callback_err_no_email'));
}

$email = strtolower(trim($userJson['email']));

// If user is linking a local account, attach email to that user id
$linkUserId = $_SESSION['link_user_id'] ?? null;
unset($_SESSION['link_user_id']);

if ($linkUserId) {
  $user = link_user_email((string)$linkUserId, $email);
} else {
  $user = ensure_user_by_email($email);
}

// Session: user id + provider info
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_email'] = $email;
$_SESSION['login_provider'] = 'google';
$_SESSION['user_name'] = user_display_name_from_row($user);

$next = $_SESSION['login_next'] ?? '';
unset($_SESSION['login_next']);
if ($next && is_safe_next_path($next)) {
  header('Location: ' . $next);
  exit;
}

// Ana sayfaya dön
header('Location: ' . APP_BASE_URL . '');
exit;
