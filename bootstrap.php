<?php
// bootstrap.php
require_once __DIR__ . '/config.php';
if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
  error_reporting(E_ALL);
  @ini_set('display_errors', '1');
  @ini_set('display_startup_errors', '1');
}

require_once __DIR__ . '/seo.php';

if (!defined('DEBUG_MODE')) {
  define('DEBUG_MODE', false);
}

function pm_debug_enabled(): bool {
  return defined('DEBUG_MODE') && DEBUG_MODE;
}

if (pm_debug_enabled()) {
  error_reporting(E_ALL);
  @ini_set('display_errors', '1');
  @ini_set('display_startup_errors', '1');
}

function pm_should_output_debug(): bool {
  if (php_sapi_name() === 'cli') return false;
  $uri = $_SERVER['REQUEST_URI'] ?? '';
  if (strpos($uri, '/api/') !== false) return false;
  $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
  if ($accept && stripos($accept, 'text/html') === false && stripos($accept, '*/*') === false) return false;
  return true;
}

if (pm_debug_enabled()) {
  error_reporting(E_ALL);
  ini_set('display_errors', '0');
  ini_set('display_startup_errors', '0');
  $GLOBALS['PM_DEBUG_ERRORS'] = [];

  set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    $GLOBALS['PM_DEBUG_ERRORS'][] = [
      'type' => $errno,
      'message' => $errstr,
      'file' => $errfile,
      'line' => $errline,
    ];
    error_log("PHP [$errno] $errstr in $errfile:$errline");
    return true;
  });

  register_shutdown_function(function () {
    $error = error_get_last();
    if ($error) {
      $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
      if (in_array($error['type'], $fatalTypes, true)) {
        $GLOBALS['PM_DEBUG_ERRORS'][] = [
          'type' => $error['type'],
          'message' => $error['message'],
          'file' => $error['file'],
          'line' => $error['line'],
        ];
        error_log("PHP [FATAL] {$error['message']} in {$error['file']}:{$error['line']}");
      }
    }

    if (!pm_should_output_debug() || headers_sent()) return;
    if (!empty($GLOBALS['PM_DEBUG_LOGGED'])) return;
    if (empty($GLOBALS['PM_DEBUG_ERRORS'])) return;
    $payload = json_encode($GLOBALS['PM_DEBUG_ERRORS'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!$payload) return;
    echo "<script>try{(window.__pmDebugErrors=" . $payload . ").forEach(function(e){console.error('[PHP]',e.message,'@',e.file+':'+e.line,'(type '+e.type+')');});}catch(_){}</script>";
  });
}

// --- Canonical host redirect (www) ---
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
$uri  = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';

// Local dev exemptions
$isLocal = ($host === 'localhost' || preg_match('/^\d{1,3}(\.\d{1,3}){3}(:\d+)?$/', $host));

// If host has no "www." prefix and looks like a root domain, redirect to www.
if (!$isLocal && $host && stripos($host, 'www.') !== 0) {
  // If already a subdomain (e.g., api.), you may want to skip redirect.
  // Here: redirect only for single-dot domains like prismatch.online
  $dotCount = substr_count(preg_replace('/:\d+$/', '', $host), '.');
  if ($dotCount === 1) {
    $target = 'https://www.' . $host . $uri;
    header('Location: ' . $target, true, 301);
    exit;
  }
}

// --- Session ---
session_name(SESSION_NAME);
// PHP 5.6 compatible cookie params (no samesite support here)
session_set_cookie_params(0, '/', '', COOKIE_SECURE, true);
session_start();

// --- i18n ---
require_once __DIR__ . '/i18n.php';
get_lang(); // session/cookie/browser -> resolved

// --- Session upgrade (legacy email-only sessions) ---
if (empty($_SESSION['user_id']) && !empty($_SESSION['user_email'])) {
  require_once __DIR__ . '/db.php';
  $user = ensure_user_by_email((string)$_SESSION['user_email']);
  $_SESSION['user_id'] = $user['id'];
  $_SESSION['user_name'] = user_display_name_from_row($user);
  $_SESSION['login_provider'] = $_SESSION['login_provider'] ?? 'google';
}


