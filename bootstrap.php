<?php
// bootstrap.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/seo.php';

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
