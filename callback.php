<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

session_name(SESSION_NAME);
session_set_cookie_params([
  'httponly' => true,
  'secure' => COOKIE_SECURE,
  'samesite' => 'Lax',
]);
session_start();

function fail(string $msg, int $code = 400): void {
  http_response_code($code);
  echo "<h1>Giriş Hatası</h1><p>" . htmlspecialchars($msg) . "</p><p><a href='index.php'>Geri dön</a></p>";
  exit;
}

if (!isset($_GET['state'], $_SESSION['oauth_state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
  fail('Geçersiz state (CSRF koruması).');
}
unset($_SESSION['oauth_state']);

if (!isset($_GET['code'])) {
  fail('Authorization code alınamadı.');
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
  fail('Token alınamadı. HTTP: ' . $httpCode);
}

$tokenJson = json_decode($tokenResp, true);
if (!is_array($tokenJson) || empty($tokenJson['access_token'])) {
  fail('Token yanıtı geçersiz.');
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
  fail('Userinfo alınamadı. HTTP: ' . $uHttp);
}

$userJson = json_decode($userResp, true);
if (!is_array($userJson) || empty($userJson['email'])) {
  fail('E-posta bilgisi alınamadı.');
}

$email = strtolower(trim($userJson['email']));

// DB login upsert
upsert_user_login($email);

// Session'a sadece email koy
$_SESSION['user_email'] = $email;

// Ana sayfaya dön
header('Location: ' . APP_BASE_URL . '');
exit;
