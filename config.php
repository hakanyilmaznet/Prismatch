<?php

// Google OAuth 2.0
define('GOOGLE_CLIENT_ID',     '360261225488-2uuq84kim3lol71tkrupu5cuafq4itkc.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');
define('GOOGLE_REDIRECT_URI',  'https://www.prismatch.online/callback.php');
define('APP_BASE_URL', 'https://www.prismatch.online/');

// Session
define('SESSION_NAME', 'colorcatch_sess');
define('COOKIE_SECURE', true);
define('DEBUG_MODE', true);

// MySQL
define('DB_HOST', 'localhost');
define('DB_NAME', 'mukerre_colorcatch');
define('DB_USER', 'mukerre_colorcatch');
define('DB_PASS', ')R$pN[pusK&PpSOl');
define('DB_CHARSET', 'utf8mb4');

// Pusher (Room mode realtime)
define('PUSHER_APP_ID', '2110554');
define('PUSHER_KEY', '016ee80e7fbe5c66b878');
define('PUSHER_SECRET', 'f515a114b9557941cc0c');
define('PUSHER_CLUSTER', 'eu');

if (!function_exists('random_bytes')) {
  function random_bytes($length) {
    if (function_exists('openssl_random_pseudo_bytes')) {
      $bytes = openssl_random_pseudo_bytes($length, $strong);
      if ($bytes !== false && $strong) return $bytes;
    }
    if (function_exists('mcrypt_create_iv')) {
      $bytes = mcrypt_create_iv($length, MCRYPT_DEV_URANDOM);
      if ($bytes !== false) return $bytes;
    }
    $bytes = '';
    for ($i = 0; $i < $length; $i++) {
      $bytes .= chr(mt_rand(0, 255));
    }
    return $bytes;
  }
}
