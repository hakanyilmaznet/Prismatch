<?php
require_once __DIR__ . '/bootstrap.php';
if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
  error_reporting(E_ALL);
  @ini_set('display_errors', '1');
  @ini_set('display_startup_errors', '1');
}

require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/db.php';

$lang = function_exists('get_lang') ? get_lang() : 'en';
$dir  = function_exists('lang_dir') ? lang_dir($lang) : 'ltr';

if (!function_exists('tt')) {
  function tt(string $key, string $fallback = ''): string {
    $val = function_exists('t') ? t($key) : '';
    return ($val === $key || $val === '') ? $fallback : $val;
  }
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function mmss($ms) {
  $sec = (int)round(max(0, $ms) / 1000);
  $m = (int)floor($sec / 60);
  $s = $sec % 60;
  return sprintf('%d:%02d', $m, $s);
}

function mask_email($email) {
  $p = explode('@', $email, 2);
  if (count($p) !== 2) return $email;
  $name = $p[0];
  $dom  = $p[1];
  $head = function_exists('mb_substr') ? mb_substr($name, 0, 2) : substr($name, 0, 2);
  return $head . '***@' . $dom;
}

function display_player_name($row) {
  $username = trim((string)($row['username'] ?? ''));
  if ($username !== '') return $username;
  $email = (string)($row['email'] ?? '');
  if ($email === '') return '-';
  $lower = strtolower($email);
  if (str_ends_with($lower, '@prismatch') || str_ends_with($lower, '@local.player')) {
    $p = explode('@', $email, 2);
    return $p[0];
  }
  return mask_email($email);
}

function player_initial($row) {
  $name = display_player_name($row);
  if ($name === '-' || $name === '') return '?';
  return strtoupper(function_exists('mb_substr') ? mb_substr($name, 0, 1, 'UTF-8') : substr($name, 0, 1));
}

function get_country_name(string $code, string $lang = 'en'): string {
  $code = strtoupper(trim($code));
  if ($code === '') return '';

  if ($lang === 'tr') {
    static $trMap = [
      'TR' => 'Türkiye', 'US' => 'Amerika Birleşik Devletleri', 'GB' => 'Birleşik Krallık',
      'DE' => 'Almanya', 'FR' => 'Fransa', 'IT' => 'İtalya', 'ES' => 'İspanya',
      'NL' => 'Hollanda', 'RU' => 'Rusya', 'AZ' => 'Azerbaycan', 'UA' => 'Ukrayna',
      'BE' => 'Belçika', 'AT' => 'Avusturya', 'CH' => 'İsviçre', 'SE' => 'İsveç',
      'NO' => 'Norveç', 'DK' => 'Danimarka', 'FI' => 'Finlandiya', 'PL' => 'Polonya',
      'GR' => 'Yunanistan', 'PT' => 'Portekiz', 'BR' => 'Brezilya', 'CA' => 'Kanada',
      'AU' => 'Avustralya', 'JP' => 'Japonya', 'KR' => 'Güney Kore', 'CN' => 'Çin',
      'IN' => 'Hindistan', 'ID' => 'Endonezya', 'SA' => 'Suudi Arabistan', 'AE' => 'Birleşik Arap Emirlikleri',
      'EG' => 'Mısır', 'MX' => 'Meksika', 'AR' => 'Arjantin', 'CL' => 'Şili', 'CO' => 'Kolombiya',
      'ZA' => 'Güney Afrika', 'IR' => 'İran', 'IQ' => 'Irak', 'SY' => 'Suriye', 'KZ' => 'Kazakistan',
      'UZ' => 'Özbekistan', 'TM' => 'Türkmenistan', 'KG' => 'Kırgızistan', 'PK' => 'Pakistan',
      'MA' => 'Fas', 'DZ' => 'Cezayir', 'TN' => 'Tunus', 'NG' => 'Nijerya', 'NZ' => 'Yeni Zelanda',
      'IE' => 'İrlanda', 'CZ' => 'Çekya', 'HU' => 'Macaristan', 'RO' => 'Romanya', 'BG' => 'Bulgaristan',
      'RS' => 'Sırbistan', 'HR' => 'Hırvatistan', 'BA' => 'Bosna-Hersek', 'AL' => 'Arnavutluk',
      'MK' => 'Kuzey Makedonya', 'ME' => 'Karadağ', 'XK' => 'Kosova', 'GE' => 'Gürcistan',
      'AM' => 'Ermenistan', 'IL' => 'İsrail', 'LB' => 'Lübnan', 'JO' => 'Ürdün', 'KW' => 'Kuveyt',
      'QA' => 'Katar', 'BH' => 'Bahreyn', 'OM' => 'Umman', 'SG' => 'Singapur', 'MY' => 'Malezya',
      'TH' => 'Tayland', 'VN' => 'Vietnam', 'PH' => 'Filipinler', 'TW' => 'Tayvan', 'HK' => 'Hong Kong',
      'CY' => 'Kıbrıs'
    ];
    if (isset($trMap[$code])) return $trMap[$code];
  }

  if (class_exists('Locale') && method_exists('Locale', 'getDisplayRegion')) {
    try {
      $name = \Locale::getDisplayRegion('-' . $code, $lang);
      if (!empty($name) && $name !== '-' . $code && $name !== $code) {
        return $name;
      }
    } catch (\Throwable $e) {}
  }

  static $flagMap = null;
  if ($flagMap === null) {
    $flagFile = __DIR__ . '/flags/iso2_to_flag.php';
    $flagMap = is_file($flagFile) ? (require $flagFile) : [];
  }
  if (isset($flagMap[$code])) {
    $base = pathinfo($flagMap[$code], PATHINFO_FILENAME);
    return ucwords(str_replace(['-', '_'], ' ', $base));
  }

  return $code;
}

$seoTitle = t('daily_leaderboard_title') . ' - ' . t('app_name');
$seoDescription = t('daily_leaderboard_meta_description');
if ($seoDescription === 'daily_leaderboard_meta_description') {
  $seoDescription = 'See today\'s Prismatch daily challenge leaderboard and compare top scores.';
}
$seoLangs = function_exists('supported_languages') ? array_keys(supported_languages()) : [];

$todayUtc = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d');
$challengeDate = isset($_GET['d']) ? $_GET['d'] : $todayUtc;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $challengeDate)) $challengeDate = $todayUtc;

$currDt = new DateTimeImmutable($challengeDate, new DateTimeZone('UTC'));
$prevDate = $currDt->modify('-1 day')->format('Y-m-d');
$nextDate = $currDt->modify('+1 day')->format('Y-m-d');
$isToday = ($challengeDate === $todayUtc);
$isFuture = ($challengeDate > $todayUtc);

$countryFilter = isset($_GET['country']) ? $_GET['country'] : '';
$countryFilter = strtoupper(trim((string)$countryFilter));
if ($countryFilter !== '' && !preg_match('/^[A-Z]{2}$/', $countryFilter)) $countryFilter = '';

$rows = daily_leaderboard($challengeDate, $countryFilter ?: null, 100);

// Viewer country and user info
$viewerCountry = function_exists('cf_country') ? cf_country() : null;
$sessionEmail = $_SESSION['user_email'] ?? null;
$sessionUserId = $_SESSION['user_id'] ?? null;

// Collect country mapping
$isoMap = is_file(__DIR__ . '/flags/iso2_to_flag.php') ? (require __DIR__ . '/flags/iso2_to_flag.php') : [];
$allCountries = [];
foreach ($isoMap as $code => $flagPng) {
  $allCountries[$code] = [
    'code' => $code,
    'name' => get_country_name($code, $lang),
    'flag' => 'flags/' . $flagPng,
  ];
}

// Active countries today from rows or db
$activeCountriesToday = [];
foreach ($rows as $r) {
  $c = strtoupper(trim((string)($r['country'] ?? '')));
  if ($c !== '' && isset($allCountries[$c])) {
    $activeCountriesToday[$c] = true;
  }
}

// Sort all countries by localized name
if (class_exists('Collator')) {
  try {
    $col = new \Collator($lang);
    uasort($allCountries, function($a, $b) use ($col) {
      return $col->compare($a['name'], $b['name']);
    });
  } catch (\Throwable $e) {
    uasort($allCountries, fn($a, $b) => strcasecmp($a['name'], $b['name']));
  }
} else {
  uasort($allCountries, fn($a, $b) => strcasecmp($a['name'], $b['name']));
}

$selectedCountryName = $countryFilter !== '' ? ($allCountries[$countryFilter]['name'] ?? $countryFilter) : '';
$selectedCountryFlag = $countryFilter !== '' ? ($allCountries[$countryFilter]['flag'] ?? country_flag_icon_url($countryFilter)) : '';
?>
<!doctype html>
<html lang="<?= h($lang) ?>" dir="<?= h($dir) ?>">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <script>
    (function(){
      const key = 'pm-theme';
      const stored = localStorage.getItem(key);
      const prefers = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
      const theme = stored || prefers;
      document.documentElement.setAttribute('data-bs-theme', theme);
    })();
  </script>
  <link href="css/style.css" rel="stylesheet" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700;800&family=Baloo+2:wght@500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet" />
  <title><?= h(t('daily_leaderboard_title')) ?> - <?= h(t('app_name')) ?></title>
  <link rel="icon" type="image/svg+xml" href="favicon.svg" />
  <?= seo_meta([
    'title' => $seoTitle,
    'description' => $seoDescription,
    'url' => seo_current_url(),
    'image' => '/logo.png',
    'type' => 'website',
    'robots' => 'index,follow',
    'lang' => $lang,
    'site_name' => t('app_name'),
  ]) ?>
  <?= seo_alternate_links($seoLangs, seo_current_url()) ?>
  <style>
    body {
      padding-top: calc(var(--pm-header-offset, 0px) + 20px);
      padding-bottom: calc(var(--pm-footer-offset, 0px) + 36px);
      min-height: 100vh;
    }

    .wrap {
      max-width: 1080px;
      margin: 0 auto;
      padding: 0 16px;
    }

    /* Hero Card */
    .pm-lb-hero {
      position: relative;
      border-radius: var(--pm-radius-lg, 20px);
      padding: 24px 28px;
      background: var(--pm-bg-card, rgba(255, 255, 255, 0.9));
      border: 1px solid var(--pm-border, rgba(0, 0, 0, 0.08));
      box-shadow: 0 12px 36px rgba(0, 0, 0, 0.04);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      margin-bottom: 20px;
      overflow: hidden;
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 20px;
    }
    [data-bs-theme="dark"] .pm-lb-hero {
      background: rgba(18, 22, 34, 0.85);
      border-color: rgba(255, 255, 255, 0.08);
      box-shadow: 0 20px 48px rgba(0, 0, 0, 0.4);
    }
    .pm-lb-hero::after {
      content: '';
      position: absolute;
      top: -60px;
      right: -60px;
      width: 220px;
      height: 220px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(255, 107, 91, 0.18) 0%, rgba(255, 178, 75, 0) 70%);
      pointer-events: none;
    }

    .pm-lb-hero-main {
      display: flex;
      align-items: center;
      gap: 18px;
      min-width: 260px;
    }
    .pm-lb-hero-icon-box {
      width: 58px;
      height: 58px;
      border-radius: 18px;
      background: linear-gradient(135deg, #ff6b5b, #ff9f43);
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 8px 24px rgba(255, 107, 91, 0.35);
      flex-shrink: 0;
    }
    .pm-lb-hero-icon-box img {
      width: 30px;
      height: 30px;
      filter: brightness(0) invert(1);
    }

    .pm-lb-badge-row {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 6px;
      flex-wrap: wrap;
    }
    .pm-pill-live {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 11px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.6px;
      padding: 3px 10px;
      border-radius: 999px;
      background: rgba(16, 185, 129, 0.12);
      color: #10b981;
      border: 1px solid rgba(16, 185, 129, 0.3);
    }
    .pm-live-dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: #10b981;
      box-shadow: 0 0 8px #10b981;
      animation: pmPulse 1.8s infinite;
    }
    @keyframes pmPulse {
      0%, 100% { opacity: 1; transform: scale(1); }
      50% { opacity: 0.4; transform: scale(0.85); }
    }

    .pm-pill-filter {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 12px;
      font-weight: 700;
      padding: 3px 10px;
      border-radius: 999px;
      background: rgba(255, 107, 91, 0.12);
      color: #ff6b5b;
      border: 1px solid rgba(255, 107, 91, 0.3);
    }
    .pm-clear-filter {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 16px;
      height: 16px;
      border-radius: 50%;
      background: rgba(255, 107, 91, 0.2);
      color: #ff6b5b;
      text-decoration: none;
      font-weight: 900;
      font-size: 13px;
      line-height: 1;
      margin-left: 2px;
      transition: background 0.15s ease;
    }
    .pm-clear-filter:hover {
      background: #ff6b5b;
      color: #fff;
    }

    .pm-lb-title {
      font-family: var(--pm-font-display, "Baloo 2", sans-serif);
      font-size: clamp(24px, 4vw, 32px);
      font-weight: 800;
      line-height: 1.1;
      margin: 0 0 4px 0;
      letter-spacing: -0.02em;
    }
    .pm-lb-subtitle {
      font-size: 13.5px;
      color: var(--pm-text-muted, #64748b);
      margin: 0;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .pm-dot-sep { opacity: 0.4; }

    /* Interactive Controls Toolbar */
    .pm-toolbar-card {
      border-radius: var(--pm-radius-lg, 20px);
      padding: 14px 18px;
      background: var(--pm-bg-card, rgba(255, 255, 255, 0.9));
      border: 1px solid var(--pm-border, rgba(0, 0, 0, 0.08));
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.03);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      margin-bottom: 24px;
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
    }
    [data-bs-theme="dark"] .pm-toolbar-card {
      background: rgba(18, 22, 34, 0.85);
      border-color: rgba(255, 255, 255, 0.08);
      box-shadow: 0 12px 32px rgba(0, 0, 0, 0.35);
    }

    /* Date Stepper */
    .pm-date-stepper {
      display: flex;
      align-items: center;
      gap: 6px;
      flex-wrap: wrap;
    }
    .pm-btn-icon-nav {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 38px;
      height: 38px;
      border-radius: 999px;
      border: 1px solid rgba(0, 0, 0, 0.09);
      background: rgba(0, 0, 0, 0.03);
      color: inherit;
      text-decoration: none;
      transition: all 0.18s cubic-bezier(0.4, 0, 0.2, 1);
      flex-shrink: 0;
    }
    [data-bs-theme="dark"] .pm-btn-icon-nav {
      border-color: rgba(255, 255, 255, 0.1);
      background: rgba(255, 255, 255, 0.05);
    }
    .pm-btn-icon-nav:hover:not(.disabled) {
      border-color: #ff6b5b;
      background: rgba(255, 107, 91, 0.1);
      color: #ff6b5b;
      transform: translateY(-1px);
    }
    .pm-btn-icon-nav.disabled {
      opacity: 0.35;
      pointer-events: none;
      cursor: not-allowed;
    }
    .pm-btn-icon-nav img {
      width: 16px;
      height: 16px;
      filter: var(--pm-icon-filter);
    }
    .pm-btn-icon-nav:hover:not(.disabled) img {
      filter: invert(48%) sepia(82%) saturate(1914%) hue-rotate(334deg) brightness(101%) contrast(101%);
    }

    .pm-date-input-wrap {
      position: relative;
      display: flex;
      align-items: center;
    }
    .pm-date-input-wrap img {
      position: absolute;
      left: 12px;
      width: 16px;
      height: 16px;
      pointer-events: none;
      filter: var(--pm-icon-filter);
      opacity: 0.6;
    }
    .pm-date-input {
      appearance: none;
      border: 1px solid rgba(0, 0, 0, 0.1);
      background: rgba(0, 0, 0, 0.03);
      border-radius: 999px;
      padding: 7px 14px 7px 36px;
      font-size: 13.5px;
      font-weight: 600;
      color: inherit;
      height: 38px;
      outline: none;
      transition: all 0.18s ease;
      font-family: inherit;
      box-sizing: border-box;
    }
    [data-bs-theme="dark"] .pm-date-input {
      border-color: rgba(255, 255, 255, 0.12);
      background: rgba(255, 255, 255, 0.05);
    }
    .pm-date-input:focus {
      border-color: #ff6b5b;
      box-shadow: 0 0 0 3px rgba(255, 107, 91, 0.2);
    }

    .pm-btn-today {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      height: 38px;
      padding: 0 14px;
      border-radius: 999px;
      border: 1px solid rgba(0, 0, 0, 0.1);
      background: rgba(0, 0, 0, 0.03);
      color: inherit;
      text-decoration: none;
      font-size: 13px;
      font-weight: 700;
      transition: all 0.18s ease;
      white-space: nowrap;
    }
    [data-bs-theme="dark"] .pm-btn-today {
      border-color: rgba(255, 255, 255, 0.1);
      background: rgba(255, 255, 255, 0.05);
    }
    .pm-btn-today:hover {
      border-color: #ff6b5b;
      background: rgba(255, 107, 91, 0.08);
      color: #ff6b5b;
    }
    .pm-btn-today.active {
      background: linear-gradient(135deg, rgba(255, 107, 91, 0.18), rgba(255, 178, 75, 0.14));
      border-color: rgba(255, 107, 91, 0.4);
      color: #ff6b5b;
    }

    /* REDESIGNED COUNTRY COMBOBOX */
    .countryWrap {
      position: relative;
      display: inline-flex;
      width: 250px;
      max-width: 100%;
    }
    .countryBtn {
      appearance: none;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      width: 100%;
      height: 40px;
      padding: 6px 14px;
      border-radius: 999px;
      border: 1px solid rgba(0, 0, 0, 0.1);
      background: rgba(0, 0, 0, 0.03);
      color: inherit;
      cursor: pointer;
      font-size: 13.5px;
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      box-sizing: border-box;
    }
    [data-bs-theme="dark"] .countryBtn {
      border-color: rgba(255, 255, 255, 0.12);
      background: rgba(255, 255, 255, 0.05);
      color: #fff;
    }
    .countryBtn:hover {
      border-color: rgba(255, 107, 91, 0.45);
      background: rgba(255, 107, 91, 0.06);
      box-shadow: 0 4px 16px rgba(255, 107, 91, 0.12);
      transform: translateY(-1px);
    }
    [data-bs-theme="dark"] .countryBtn:hover {
      border-color: rgba(255, 107, 91, 0.5);
      background: rgba(255, 107, 91, 0.1);
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
    }
    .countryBtn[aria-expanded="true"] {
      border-color: #ff6b5b;
      box-shadow: 0 0 0 3px rgba(255, 107, 91, 0.25);
    }
    .countryBtnLeft {
      display: flex;
      align-items: center;
      gap: 9px;
      min-width: 0;
      flex: 1;
    }
    .countryBtnFlag {
      width: 22px;
      height: 22px;
      border-radius: 50%;
      object-fit: cover;
      border: 1.5px solid rgba(255, 255, 255, 0.5);
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
      flex-shrink: 0;
    }
    .countryBtnText {
      font-weight: 700;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      font-size: 13.5px;
      letter-spacing: -0.01em;
    }
    .countryBtnCode {
      font-size: 10.5px;
      font-weight: 800;
      padding: 1px 6px;
      border-radius: 6px;
      background: rgba(0, 0, 0, 0.06);
      color: var(--pm-text-muted, #64748b);
      margin-left: auto;
      flex-shrink: 0;
    }
    [data-bs-theme="dark"] .countryBtnCode {
      background: rgba(255, 255, 255, 0.08);
      color: #94a3b8;
    }
    .countryCaret {
      width: 16px;
      height: 16px;
      background: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2364748b' viewBox='0 0 24 24'><path d='M7 10l5 5 5-5z'/></svg>") no-repeat center/16px 16px;
      flex: 0 0 16px;
      transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    [data-bs-theme="dark"] .countryCaret {
      background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2394a3b8' viewBox='0 0 24 24'><path d='M7 10l5 5 5-5z'/></svg>");
    }
    .countryBtn[aria-expanded="true"] .countryCaret {
      transform: rotate(180deg);
    }

    /* Popover Floating Panel */
    .countryPop {
      position: fixed;
      z-index: 35000;
      width: min(92vw, 360px);
      border-radius: 20px;
      border: 1px solid rgba(0, 0, 0, 0.08);
      background: rgba(255, 255, 255, 0.96);
      backdrop-filter: blur(24px);
      -webkit-backdrop-filter: blur(24px);
      box-shadow: 0 24px 60px rgba(15, 23, 42, 0.2), 0 4px 16px rgba(15, 23, 42, 0.06);
      padding: 12px;
      box-sizing: border-box;
      animation: countryPopIn 0.18s cubic-bezier(0.16, 1, 0.3, 1);
    }
    [data-bs-theme="dark"] .countryPop {
      border-color: rgba(255, 255, 255, 0.12);
      background: rgba(14, 18, 30, 0.96);
      box-shadow: 0 24px 70px rgba(0, 0, 0, 0.7);
    }
    @keyframes countryPopIn {
      from { opacity: 0; transform: translateY(-6px) scale(0.97); }
      to { opacity: 1; transform: translateY(0) scale(1); }
    }

    .countryPopHeader {
      display: flex;
      flex-direction: column;
      gap: 8px;
      padding-bottom: 8px;
      border-bottom: 1px solid rgba(0, 0, 0, 0.06);
    }
    [data-bs-theme="dark"] .countryPopHeader {
      border-bottom-color: rgba(255, 255, 255, 0.08);
    }
    .countryTitleWrap {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 2px 4px;
    }
    .countryTitle {
      font-size: 12px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      color: var(--pm-text-muted, #94a3b8);
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .countryCountBadge {
      font-size: 11px;
      font-weight: 700;
      padding: 2px 8px;
      border-radius: 999px;
      background: rgba(255, 107, 91, 0.12);
      color: #ff6b5b;
    }
    .countrySearchWrap {
      position: relative;
      width: 100%;
      display: flex;
      align-items: center;
    }
    .countrySearchIcon {
      position: absolute;
      left: 11px;
      width: 15px;
      height: 15px;
      opacity: 0.5;
      pointer-events: none;
      filter: var(--pm-icon-filter);
    }
    .countrySearch {
      width: 100%;
      box-sizing: border-box;
      appearance: none;
      border: 1px solid rgba(0, 0, 0, 0.1);
      background: rgba(0, 0, 0, 0.03);
      border-radius: 12px;
      padding: 8px 12px 8px 34px;
      font-size: 13px;
      color: inherit;
      outline: none;
      transition: all 0.15s ease;
      font-family: inherit;
    }
    [data-bs-theme="dark"] .countrySearch {
      border-color: rgba(255, 255, 255, 0.12);
      background: rgba(255, 255, 255, 0.05);
      color: #fff;
    }
    .countrySearch:focus {
      border-color: #ff6b5b;
      box-shadow: 0 0 0 3px rgba(255, 107, 91, 0.2);
    }

    .countryList {
      max-height: 290px;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 3px;
      padding: 8px 2px 2px 2px;
      margin: 0;
      scrollbar-width: thin;
      scrollbar-color: rgba(255, 107, 91, 0.4) transparent;
    }
    .countryList::-webkit-scrollbar { width: 5px; }
    .countryList::-webkit-scrollbar-thumb {
      background: rgba(255, 107, 91, 0.4);
      border-radius: 999px;
    }
    .countryItem {
      appearance: none;
      border: 1px solid transparent;
      background: transparent;
      width: 100%;
      padding: 8px 10px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      cursor: pointer;
      text-align: left;
      color: inherit;
      font-size: 13.5px;
      transition: all 0.15s ease;
      box-sizing: border-box;
    }
    .countryItem:hover, .countryItem.isActive {
      background: rgba(255, 107, 91, 0.1);
      color: #ff6b5b;
      border-color: rgba(255, 107, 91, 0.2);
      transform: translateX(2px);
    }
    .countryItem[aria-selected="true"] {
      background: linear-gradient(135deg, rgba(255, 107, 91, 0.16), rgba(255, 178, 75, 0.12));
      border-color: rgba(255, 107, 91, 0.35);
      color: #ff6b5b;
      font-weight: 700;
    }
    .countryItemLeft {
      display: flex;
      align-items: center;
      gap: 10px;
      min-width: 0;
    }
    .countryItemFlag {
      width: 22px;
      height: 22px;
      border-radius: 50%;
      object-fit: cover;
      border: 1px solid rgba(0, 0, 0, 0.1);
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.12);
      flex-shrink: 0;
    }
    [data-bs-theme="dark"] .countryItemFlag {
      border-color: rgba(255, 255, 255, 0.2);
    }
    .countryItemAllIcon {
      width: 22px;
      height: 22px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      flex-shrink: 0;
    }
    .countryLabelCell {
      font-weight: 600;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 190px;
    }
    .countryItemRight {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-shrink: 0;
    }
    .countryBadge {
      font-size: 11px;
      font-weight: 700;
      font-family: var(--pm-font-mono, monospace);
      color: var(--pm-text-muted, #94a3b8);
      background: rgba(0, 0, 0, 0.05);
      padding: 1px 6px;
      border-radius: 6px;
    }
    [data-bs-theme="dark"] .countryBadge {
      background: rgba(255, 255, 255, 0.08);
      color: #94a3b8;
    }
    .countryCheck {
      display: none;
      width: 14px;
      height: 14px;
      color: #ff6b5b;
      font-weight: 900;
    }
    .countryItem[aria-selected="true"] .countryCheck {
      display: inline-block;
    }
    .countrySectionHeader {
      font-size: 10.5px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      color: var(--pm-text-muted, #64748b);
      padding: 8px 10px 4px 10px;
    }

    /* TOP 3 PODIUM */
    .pm-podium-wrap {
      display: grid;
      grid-template-columns: 1fr 1.12fr 1fr;
      gap: 16px;
      margin-bottom: 24px;
      align-items: end;
    }
    @media (max-width: 768px) {
      .pm-podium-wrap {
        grid-template-columns: 1fr;
        gap: 12px;
      }
    }
    .pm-podium-card {
      position: relative;
      border-radius: var(--pm-radius-lg, 20px);
      padding: 22px 18px 20px 18px;
      background: var(--pm-bg-card, rgba(255, 255, 255, 0.9));
      border: 1px solid var(--pm-border, rgba(0, 0, 0, 0.08));
      box-shadow: 0 12px 32px rgba(0, 0, 0, 0.04);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      text-align: center;
      display: flex;
      flex-direction: column;
      align-items: center;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
      overflow: hidden;
    }
    [data-bs-theme="dark"] .pm-podium-card {
      background: rgba(18, 22, 34, 0.85);
      border-color: rgba(255, 255, 255, 0.08);
      box-shadow: 0 16px 40px rgba(0, 0, 0, 0.35);
    }
    .pm-podium-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 16px 44px rgba(0, 0, 0, 0.08);
    }
    [data-bs-theme="dark"] .pm-podium-card:hover {
      box-shadow: 0 20px 48px rgba(0, 0, 0, 0.5);
    }

    /* 1st Place Gold Highlights */
    .pm-podium-1st {
      border: 2px solid rgba(255, 178, 75, 0.6);
      background: linear-gradient(180deg, rgba(255, 178, 75, 0.08) 0%, var(--pm-bg-card, rgba(255,255,255,0.9)) 40%);
      box-shadow: 0 16px 48px rgba(255, 178, 75, 0.16);
      z-index: 2;
    }
    [data-bs-theme="dark"] .pm-podium-1st {
      background: linear-gradient(180deg, rgba(255, 178, 75, 0.12) 0%, rgba(18, 22, 34, 0.95) 45%);
      box-shadow: 0 20px 54px rgba(255, 178, 75, 0.18);
    }
    .pm-podium-crown {
      font-size: 26px;
      line-height: 1;
      margin-bottom: -6px;
      filter: drop-shadow(0 4px 10px rgba(255, 178, 75, 0.5));
      animation: crownBob 2.5s ease-in-out infinite;
    }
    @keyframes crownBob {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-4px); }
    }

    /* Podium Avatar */
    .pm-podium-avatar-wrap {
      position: relative;
      margin: 8px 0 12px 0;
    }
    .pm-podium-avatar {
      width: 58px;
      height: 58px;
      border-radius: 50%;
      background: linear-gradient(135deg, rgba(255, 107, 91, 0.15), rgba(255, 178, 75, 0.25));
      border: 2px solid #fff;
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      font-weight: 800;
      color: #ff6b5b;
      font-family: var(--pm-font-display, "Baloo 2", sans-serif);
    }
    .pm-podium-1st .pm-podium-avatar {
      width: 68px;
      height: 68px;
      font-size: 26px;
      border-color: #ffd159;
      box-shadow: 0 6px 20px rgba(255, 178, 75, 0.35);
    }
    .pm-podium-flag-badge {
      position: absolute;
      bottom: -2px;
      right: -2px;
      width: 22px;
      height: 22px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid #fff;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
    }
    [data-bs-theme="dark"] .pm-podium-flag-badge {
      border-color: #121622;
    }

    .pm-podium-rank-badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-size: 11.5px;
      font-weight: 800;
      padding: 3px 10px;
      border-radius: 999px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 6px;
    }
    .pm-rank-badge-1st {
      background: linear-gradient(135deg, #ffd159, #f59e0b);
      color: #78350f;
      box-shadow: 0 2px 10px rgba(245, 158, 11, 0.3);
    }
    .pm-rank-badge-2nd {
      background: linear-gradient(135deg, #e2e8f0, #94a3b8);
      color: #1e293b;
    }
    .pm-rank-badge-3rd {
      background: linear-gradient(135deg, #fed7aa, #d97706);
      color: #78350f;
    }

    .pm-podium-name {
      font-size: 15px;
      font-weight: 700;
      margin: 0 0 6px 0;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 100%;
    }
    .pm-podium-score {
      font-family: var(--pm-font-display, "Baloo 2", sans-serif);
      font-size: 24px;
      font-weight: 800;
      line-height: 1;
      color: #ff6b5b;
      margin-bottom: 8px;
    }
    .pm-podium-1st .pm-podium-score {
      font-size: 28px;
      color: #f59e0b;
    }
    .pm-podium-stats {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 12px;
      color: var(--pm-text-muted, #64748b);
      font-weight: 600;
    }

    /* LEADERBOARD TABLE CARD */
    .pm-table-card {
      border-radius: var(--pm-radius-lg, 20px);
      background: var(--pm-bg-card, rgba(255, 255, 255, 0.9));
      border: 1px solid var(--pm-border, rgba(0, 0, 0, 0.08));
      box-shadow: 0 12px 36px rgba(0, 0, 0, 0.04);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      overflow: hidden;
    }
    [data-bs-theme="dark"] .pm-table-card {
      background: rgba(18, 22, 34, 0.85);
      border-color: rgba(255, 255, 255, 0.08);
      box-shadow: 0 16px 44px rgba(0, 0, 0, 0.4);
    }
    .pm-table-responsive {
      width: 100%;
      overflow-x: auto;
      scrollbar-width: thin;
      scrollbar-color: rgba(255, 107, 91, 0.3) transparent;
    }
    .pm-table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      font-size: 14px;
      text-align: left;
    }
    .pm-table th {
      background: rgba(0, 0, 0, 0.02);
      color: var(--pm-text-muted, #64748b);
      font-size: 12px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.6px;
      padding: 14px 18px;
      border-bottom: 1px solid rgba(0, 0, 0, 0.06);
      white-space: nowrap;
    }
    [data-bs-theme="dark"] .pm-table th {
      background: rgba(255, 255, 255, 0.02);
      border-bottom-color: rgba(255, 255, 255, 0.06);
    }
    .pm-table td {
      padding: 14px 18px;
      border-bottom: 1px solid rgba(0, 0, 0, 0.04);
      vertical-align: middle;
      transition: background 0.15s ease;
    }
    [data-bs-theme="dark"] .pm-table td {
      border-bottom-color: rgba(255, 255, 255, 0.04);
    }
    .pm-table tr:last-child td {
      border-bottom: none;
    }
    .pm-table tr:hover td {
      background: rgba(255, 107, 91, 0.04);
    }
    [data-bs-theme="dark"] .pm-table tr:hover td {
      background: rgba(255, 107, 91, 0.08);
    }
    .pm-table tr.isCurrentViewer td {
      background: rgba(255, 107, 91, 0.08);
      font-weight: 700;
    }

    .pm-rank-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 28px;
      height: 28px;
      border-radius: 8px;
      font-weight: 800;
      font-size: 13px;
      padding: 0 6px;
      box-sizing: border-box;
      font-family: var(--pm-font-mono, monospace);
    }
    .pm-rank-1 {
      background: linear-gradient(135deg, #ffd159, #f59e0b);
      color: #78350f;
      box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
    }
    .pm-rank-2 {
      background: linear-gradient(135deg, #e2e8f0, #94a3b8);
      color: #1e293b;
    }
    .pm-rank-3 {
      background: linear-gradient(135deg, #fed7aa, #d97706);
      color: #78350f;
    }
    .pm-rank-other {
      background: rgba(0, 0, 0, 0.04);
      color: var(--pm-text-muted, #64748b);
    }
    [data-bs-theme="dark"] .pm-rank-other {
      background: rgba(255, 255, 255, 0.06);
      color: #94a3b8;
    }

    .pm-player-cell {
      display: flex;
      align-items: center;
      gap: 12px;
      min-width: 170px;
    }
    .pm-player-avatar {
      width: 34px;
      height: 34px;
      border-radius: 50%;
      background: linear-gradient(135deg, rgba(255, 107, 91, 0.15), rgba(255, 178, 75, 0.25));
      border: 1px solid rgba(255, 107, 91, 0.2);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 800;
      font-size: 14px;
      color: #ff6b5b;
      flex-shrink: 0;
    }
    .pm-player-name {
      font-weight: 700;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .pm-badge-you {
      font-size: 10px;
      font-weight: 800;
      text-transform: uppercase;
      padding: 2px 7px;
      border-radius: 999px;
      background: #ff6b5b;
      color: #fff;
      margin-left: 6px;
    }

    .pm-country-cell {
      display: flex;
      align-items: center;
      gap: 8px;
      white-space: nowrap;
    }
    .pm-table-flag {
      width: 22px;
      height: 22px;
      border-radius: 50%;
      object-fit: cover;
      border: 1px solid rgba(0, 0, 0, 0.1);
      box-shadow: 0 1px 4px rgba(0, 0, 0, 0.12);
      flex-shrink: 0;
    }
    [data-bs-theme="dark"] .pm-table-flag {
      border-color: rgba(255, 255, 255, 0.2);
    }

    .pm-score-cell {
      font-family: var(--pm-font-display, "Baloo 2", sans-serif);
      font-size: 17px;
      font-weight: 800;
      color: #ff6b5b;
      text-align: right;
      white-space: nowrap;
    }
    .pm-level-pill {
      display: inline-flex;
      align-items: center;
      padding: 3px 9px;
      border-radius: 8px;
      font-size: 12px;
      font-weight: 700;
      background: rgba(124, 137, 255, 0.12);
      color: #7c89ff;
      border: 1px solid rgba(124, 137, 255, 0.25);
    }
    .pm-time-pill {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      font-size: 13px;
      font-family: var(--pm-font-mono, monospace);
      color: var(--pm-text-muted, #64748b);
      white-space: nowrap;
    }

    /* EMPTY STATE */
    .pm-empty-card {
      border-radius: var(--pm-radius-lg, 20px);
      padding: 56px 24px;
      text-align: center;
      background: var(--pm-bg-card, rgba(255, 255, 255, 0.9));
      border: 1px solid var(--pm-border, rgba(0, 0, 0, 0.08));
      box-shadow: 0 12px 36px rgba(0, 0, 0, 0.04);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }
    [data-bs-theme="dark"] .pm-empty-card {
      background: rgba(18, 22, 34, 0.85);
      border-color: rgba(255, 255, 255, 0.08);
      box-shadow: 0 16px 44px rgba(0, 0, 0, 0.4);
    }
    .pm-empty-icon-wrap {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      background: linear-gradient(135deg, rgba(255, 107, 91, 0.12), rgba(255, 178, 75, 0.18));
      border: 1px solid rgba(255, 107, 91, 0.25);
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 20px;
      box-shadow: 0 8px 24px rgba(255, 107, 91, 0.15);
    }
    .pm-empty-icon-wrap img {
      width: 38px;
      height: 38px;
      filter: invert(48%) sepia(82%) saturate(1914%) hue-rotate(334deg) brightness(101%) contrast(101%);
    }
    .pm-empty-title {
      font-family: var(--pm-font-display, "Baloo 2", sans-serif);
      font-size: 22px;
      font-weight: 800;
      margin: 0 0 8px 0;
    }
    .pm-empty-desc {
      font-size: 14.5px;
      color: var(--pm-text-muted, #64748b);
      max-width: 440px;
      margin: 0 0 24px 0;
      line-height: 1.5;
    }
    .pm-empty-actions {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      justify-content: center;
    }

    .pm-btn-primary {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 22px;
      border-radius: 999px;
      background: linear-gradient(135deg, #ff6b5b, #ff8c7a);
      color: #fff !important;
      text-decoration: none;
      font-weight: 700;
      font-size: 14px;
      box-shadow: 0 6px 20px rgba(255, 107, 91, 0.35);
      transition: all 0.2s ease;
      border: none;
    }
    .pm-btn-primary:hover {
      background: linear-gradient(135deg, #ff5240, #ff7a66);
      transform: translateY(-2px);
      box-shadow: 0 8px 26px rgba(255, 107, 91, 0.45);
    }
    .pm-btn-secondary {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 20px;
      border-radius: 999px;
      background: rgba(0, 0, 0, 0.04);
      border: 1px solid rgba(0, 0, 0, 0.1);
      color: inherit;
      text-decoration: none;
      font-weight: 700;
      font-size: 14px;
      transition: all 0.2s ease;
    }
    [data-bs-theme="dark"] .pm-btn-secondary {
      background: rgba(255, 255, 255, 0.06);
      border-color: rgba(255, 255, 255, 0.12);
    }
    .pm-btn-secondary:hover {
      border-color: #ff6b5b;
      color: #ff6b5b;
      background: rgba(255, 107, 91, 0.08);
      transform: translateY(-2px);
    }
  </style>
</head>
<body class="pm-has-fixed-header">
  <?php include __DIR__ . '/header.php'; ?>

  <main class="wrap">
    <!-- HERO HEADER -->
    <div class="pm-lb-hero">
      <div class="pm-lb-hero-main">
        <div class="pm-lb-hero-icon-box">
          <img src="bootstrap-icons/trophy-fill.svg" alt="" aria-hidden="true" />
        </div>
        <div>
          <div class="pm-lb-badge-row">
            <span class="pm-pill-live">
              <span class="pm-live-dot"></span>
              <?= $isToday ? h(tt('daily_live_today', 'Bugün Canlı')) : h(tt('label_utc', 'UTC Günlük')) ?>
            </span>
            <?php if ($countryFilter !== ''): ?>
              <span class="pm-pill-filter">
                <?php if ($selectedCountryFlag !== ''): ?>
                  <img src="<?= h($selectedCountryFlag) ?>" class="pm-mini-flag" style="width:16px;height:16px;border-radius:50%;object-fit:cover;" alt="" />
                <?php endif; ?>
                <?= h($selectedCountryName ?: $countryFilter) ?>
                <a href="?d=<?= h($challengeDate) ?>" class="pm-clear-filter" title="<?= h(tt('filter_all_countries', 'Tüm Ülkeler')) ?>">×</a>
              </span>
            <?php endif; ?>
          </div>
          <h1 class="pm-lb-title"><?= h(t('daily_leaderboard_title')) ?></h1>
          <p class="pm-lb-subtitle">
            <span id="dailyDate" data-utc-date="<?= h($challengeDate) ?>"><?= h($challengeDate) ?></span>
            <span class="pm-dot-sep">•</span>
            <span><?= (int)count($rows) ?> <?= h(tt('th_player', 'Oyuncu')) ?></span>
          </p>
        </div>
      </div>
      <div>
        <a href="play.php?daily=1" class="pm-btn-primary">
          <img src="bootstrap-icons/play-fill.svg" style="width:16px;height:16px;filter:brightness(0) invert(1);" alt="" />
          <?= h(tt('home_cta_daily', 'Günlük Meydan Okuma')) ?>
        </a>
      </div>
    </div>

    <!-- CONTROLS TOOLBAR -->
    <div class="pm-toolbar-card">
      <!-- Date Stepper -->
      <form id="leaderboardDateForm" class="pm-date-stepper" method="get">
        <?php if ($countryFilter !== ''): ?>
          <input type="hidden" name="country" value="<?= h($countryFilter) ?>" />
        <?php endif; ?>

        <!-- Prev Day -->
        <a href="?d=<?= h($prevDate) ?><?= $countryFilter !== '' ? '&country=' . h($countryFilter) : '' ?>" class="pm-btn-icon-nav" title="<?= h(tt('prev_day', 'Önceki Gün')) ?>">
          <img src="bootstrap-icons/chevron-left.svg" alt="←" />
        </a>

        <!-- Date Input -->
        <div class="pm-date-input-wrap">
          <img src="bootstrap-icons/calendar3.svg" alt="" aria-hidden="true" />
          <input class="pm-date-input" type="date" name="d" id="dateInput" value="<?= h($challengeDate) ?>" max="<?= h($todayUtc) ?>" onchange="document.getElementById('leaderboardDateForm').submit();" />
        </div>

        <!-- Next Day -->
        <a href="?d=<?= h($nextDate) ?><?= $countryFilter !== '' ? '&country=' . h($countryFilter) : '' ?>" class="pm-btn-icon-nav <?= ($isToday || $isFuture) ? 'disabled' : '' ?>" title="<?= h(tt('next_day', 'Sonraki Gün')) ?>">
          <img src="bootstrap-icons/chevron-right.svg" alt="→" />
        </a>

        <!-- Today Shortcut -->
        <a href="?d=<?= h($todayUtc) ?><?= $countryFilter !== '' ? '&country=' . h($countryFilter) : '' ?>" class="pm-btn-today <?= $isToday ? 'active' : '' ?>">
          <span class="pm-live-dot" style="width:6px;height:6px;"></span>
          <?= h(tt('today', 'Bugün')) ?>
        </a>
      </form>

      <!-- Redesigned Country Combobox -->
      <div class="countryWrap" id="countryWrap">
        <button type="button" class="countryBtn" id="countryBtn" aria-haspopup="listbox" aria-expanded="false">
          <span class="countryBtnLeft">
            <?php if ($countryFilter !== '' && $selectedCountryFlag !== ''): ?>
              <img id="countryBtnFlag" class="countryBtnFlag" src="<?= h($selectedCountryFlag) ?>" alt="<?= h($countryFilter) ?>" />
              <span id="countryBtnText" class="countryBtnText"><?= h($selectedCountryName) ?></span>
              <span id="countryBtnCode" class="countryBtnCode"><?= h($countryFilter) ?></span>
            <?php else: ?>
              <span id="countryBtnFlag" class="countryItemAllIcon">🌍</span>
              <span id="countryBtnText" class="countryBtnText"><?= h(tt('filter_all_countries', 'Tüm Ülkeler')) ?></span>
              <span id="countryBtnCode" class="countryBtnCode">ALL</span>
            <?php endif; ?>
          </span>
          <span class="countryCaret" aria-hidden="true"></span>
        </button>

        <!-- Popover Floating Dropdown -->
        <div class="countryPop" id="countryPop" hidden role="listbox">
          <div class="countryPopHeader">
            <div class="countryTitleWrap">
              <span class="countryTitle">
                <img src="bootstrap-icons/globe2.svg" style="width:14px;height:14px;opacity:0.7;filter:var(--pm-icon-filter);" alt="" />
                <?= h(tt('th_country', 'Ülke')) ?>
              </span>
              <span class="countryCountBadge" id="countryCountBadge"><?= count($allCountries) ?></span>
            </div>
            <div class="countrySearchWrap">
              <img src="bootstrap-icons/search.svg" class="countrySearchIcon" alt="" aria-hidden="true" />
              <input type="text" class="countrySearch" id="countrySearch" placeholder="<?= h(tt('search_country', 'Ülke ara...')) ?>" autocomplete="off" />
            </div>
          </div>
          <div class="countryList" id="countryList">
            <!-- JS populated for instant filtering -->
          </div>
        </div>
      </div>
    </div>

    <!-- MAIN LEADERBOARD CONTENT -->
    <?php if (empty($rows)): ?>
      <!-- EMPTY STATE -->
      <div class="pm-empty-card">
        <div class="pm-empty-icon-wrap">
          <img src="bootstrap-icons/trophy.svg" alt="" />
        </div>
        <h2 class="pm-empty-title"><?= h(tt('msg_no_scores_yet', 'Bu tarih için henüz skor kaydedilmemiş.')) ?></h2>
        <p class="pm-empty-desc">
          <?= h(tt('first_to_play_cta', 'Günün lideri olmak ve zirveye adını yazdırmak için meydan okumayı tamamlayan ilk kişi sen ol!')) ?>
        </p>
        <div class="pm-empty-actions">
          <a href="play.php?daily=1" class="pm-btn-primary">
            <img src="bootstrap-icons/play-fill.svg" style="width:16px;height:16px;filter:brightness(0) invert(1);" alt="" />
            <?= h(tt('home_cta_daily', 'Günlük Meydan Okuma')) ?>
          </a>
          <?php if ($countryFilter !== ''): ?>
            <a href="?d=<?= h($challengeDate) ?>" class="pm-btn-secondary">
              <img src="bootstrap-icons/globe2.svg" style="width:15px;height:15px;filter:var(--pm-icon-filter);" alt="" />
              <?= h(tt('filter_all_countries', 'Tüm Ülkeleri Gör')) ?>
            </a>
          <?php endif; ?>
          <?php if (!$isToday): ?>
            <a href="?d=<?= h($todayUtc) ?><?= $countryFilter !== '' ? '&country=' . h($countryFilter) : '' ?>" class="pm-btn-secondary">
              <img src="bootstrap-icons/calendar2-check.svg" style="width:15px;height:15px;filter:var(--pm-icon-filter);" alt="" />
              <?= h(tt('today', 'Bugünün Tablosuna Git')) ?>
            </a>
          <?php endif; ?>
        </div>
      </div>
    <?php else: ?>

      <!-- TOP 3 PODIUM (If at least 1 player exists) -->
      <?php if (count($rows) >= 3): ?>
        <div class="pm-podium-wrap">
          <!-- 2nd Place -->
          <?php
            $r2 = $rows[1];
            $c2 = strtoupper((string)($r2['country'] ?? ''));
            $flag2 = $c2 !== '' ? country_flag_icon_url($c2) : '';
          ?>
          <div class="pm-podium-card pm-podium-2nd">
            <span class="pm-podium-rank-badge pm-rank-badge-2nd">🥈 <?= h(tt('rank_silver', '2. Sıra')) ?></span>
            <div class="pm-podium-avatar-wrap">
              <div class="pm-podium-avatar"><?= h(player_initial($r2)) ?></div>
              <?php if ($flag2 !== ''): ?>
                <img src="<?= h($flag2) ?>" class="pm-podium-flag-badge" alt="<?= h($c2) ?>" title="<?= h(get_country_name($c2, $lang)) ?>" />
              <?php endif; ?>
            </div>
            <div class="pm-podium-name" title="<?= h(display_player_name($r2)) ?>">
              <?= h(display_player_name($r2)) ?>
            </div>
            <div class="pm-podium-score"><?= number_format((int)($r2['score'] ?? 0)) ?></div>
            <div class="pm-podium-stats">
              <span class="pm-level-pill"><?= h(tt('hud_stage', 'Aşama')) ?> <?= (int)($r2['reached_level'] ?? 1) ?></span>
              <span class="pm-time-pill">⏱ <?= h(mmss((int)($r2['duration_ms'] ?? 0))) ?></span>
            </div>
          </div>

          <!-- 1st Place (Gold Champion) -->
          <?php
            $r1 = $rows[0];
            $c1 = strtoupper((string)($r1['country'] ?? ''));
            $flag1 = $c1 !== '' ? country_flag_icon_url($c1) : '';
          ?>
          <div class="pm-podium-card pm-podium-1st">
            <div class="pm-podium-crown">👑</div>
            <span class="pm-podium-rank-badge pm-rank-badge-1st">🥇 <?= h(tt('rank_gold', '1. Sıra')) ?></span>
            <div class="pm-podium-avatar-wrap">
              <div class="pm-podium-avatar"><?= h(player_initial($r1)) ?></div>
              <?php if ($flag1 !== ''): ?>
                <img src="<?= h($flag1) ?>" class="pm-podium-flag-badge" alt="<?= h($c1) ?>" title="<?= h(get_country_name($c1, $lang)) ?>" />
              <?php endif; ?>
            </div>
            <div class="pm-podium-name" title="<?= h(display_player_name($r1)) ?>">
              <?= h(display_player_name($r1)) ?>
            </div>
            <div class="pm-podium-score"><?= number_format((int)($r1['score'] ?? 0)) ?></div>
            <div class="pm-podium-stats">
              <span class="pm-level-pill"><?= h(tt('hud_stage', 'Aşama')) ?> <?= (int)($r1['reached_level'] ?? 1) ?></span>
              <span class="pm-time-pill">⏱ <?= h(mmss((int)($r1['duration_ms'] ?? 0))) ?></span>
            </div>
          </div>

          <!-- 3rd Place -->
          <?php
            $r3 = $rows[2];
            $c3 = strtoupper((string)($r3['country'] ?? ''));
            $flag3 = $c3 !== '' ? country_flag_icon_url($c3) : '';
          ?>
          <div class="pm-podium-card pm-podium-3rd">
            <span class="pm-podium-rank-badge pm-rank-badge-3rd">🥉 <?= h(tt('rank_bronze', '3. Sıra')) ?></span>
            <div class="pm-podium-avatar-wrap">
              <div class="pm-podium-avatar"><?= h(player_initial($r3)) ?></div>
              <?php if ($flag3 !== ''): ?>
                <img src="<?= h($flag3) ?>" class="pm-podium-flag-badge" alt="<?= h($c3) ?>" title="<?= h(get_country_name($c3, $lang)) ?>" />
              <?php endif; ?>
            </div>
            <div class="pm-podium-name" title="<?= h(display_player_name($r3)) ?>">
              <?= h(display_player_name($r3)) ?>
            </div>
            <div class="pm-podium-score"><?= number_format((int)($r3['score'] ?? 0)) ?></div>
            <div class="pm-podium-stats">
              <span class="pm-level-pill"><?= h(tt('hud_stage', 'Aşama')) ?> <?= (int)($r3['reached_level'] ?? 1) ?></span>
              <span class="pm-time-pill">⏱ <?= h(mmss((int)($r3['duration_ms'] ?? 0))) ?></span>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- RANKINGS TABLE -->
      <div class="pm-table-card">
        <div class="pm-table-responsive">
          <table class="pm-table">
            <thead>
              <tr>
                <th style="width: 60px;">#</th>
                <th><?= h(tt('th_country', 'Ülke')) ?></th>
                <th><?= h(tt('th_player', 'Oyuncu')) ?></th>
                <th style="text-align: right;"><?= h(tt('th_score', 'Skor')) ?></th>
                <th style="text-align: center; width: 120px;"><?= h(tt('hud_stage', 'Aşama')) ?></th>
                <th style="text-align: right; width: 120px;"><?= h(tt('th_duration', 'Süre')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $i => $r):
                $c = strtoupper(trim((string)($r['country'] ?? '')));
                $flagUrl = $c !== '' ? country_flag_icon_url($c) : '';
                $countryName = $c !== '' ? get_country_name($c, $lang) : '';
                $isViewer = false;
                if (!empty($sessionEmail) && !empty($r['email']) && strtolower((string)$r['email']) === strtolower((string)$sessionEmail)) {
                  $isViewer = true;
                }
                $rank = $i + 1;
              ?>
              <tr class="<?= $isViewer ? 'isCurrentViewer' : '' ?>">
                <td>
                  <?php if ($rank === 1): ?>
                    <span class="pm-rank-badge pm-rank-1">🥇 1</span>
                  <?php elseif ($rank === 2): ?>
                    <span class="pm-rank-badge pm-rank-2">🥈 2</span>
                  <?php elseif ($rank === 3): ?>
                    <span class="pm-rank-badge pm-rank-3">🥉 3</span>
                  <?php else: ?>
                    <span class="pm-rank-badge pm-rank-other">#<?= $rank ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="pm-country-cell">
                    <?php if ($flagUrl !== ''): ?>
                      <img class="pm-table-flag" src="<?= h($flagUrl) ?>" alt="<?= h($c) ?>" title="<?= h($countryName) ?>" />
                      <span class="countryBadge"><?= h($c) ?></span>
                    <?php else: ?>
                      <span class="countryBadge">-</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <div class="pm-player-cell">
                    <div class="pm-player-avatar"><?= h(player_initial($r)) ?></div>
                    <span class="pm-player-name"><?= h(display_player_name($r)) ?></span>
                    <?php if ($isViewer): ?>
                      <span class="pm-badge-you"><?= h(tt('badge_you', 'Sen')) ?></span>
                    <?php endif; ?>
                  </div>
                </td>
                <td class="pm-score-cell">
                  <?= number_format((int)($r['score'] ?? 0)) ?>
                </td>
                <td style="text-align: center;">
                  <span class="pm-level-pill"><?= (int)($r['reached_level'] ?? 1) ?></span>
                </td>
                <td style="text-align: right;">
                  <span class="pm-time-pill">
                    <img src="bootstrap-icons/clock.svg" style="width:13px;height:13px;filter:var(--pm-icon-filter);opacity:0.6;" alt="" />
                    <?= h(mmss((int)($r['duration_ms'] ?? 0))) ?>
                  </span>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </main>

  <?php if (is_file(__DIR__ . '/footer.php')) include __DIR__ . '/footer.php'; ?>

  <!-- PASS COUNTRY DATA TO JS -->
  <script>
    const COUNTRY_DATA = <?= json_encode(array_values($allCountries), JSON_UNESCAPED_UNICODE) ?>;
    const ACTIVE_COUNTRIES = <?= json_encode(array_keys($activeCountriesToday)) ?>;
    const CURRENT_COUNTRY = <?= json_encode($countryFilter) ?>;
    const CURRENT_DATE = <?= json_encode($challengeDate) ?>;
    const ALL_COUNTRIES_LABEL = <?= json_encode(tt('filter_all_countries', 'Tüm Ülkeler'), JSON_UNESCAPED_UNICODE) ?>;
    const ACTIVE_TODAY_LABEL = <?= json_encode(tt('active_countries_title', 'Bugün Aktif Olanlar'), JSON_UNESCAPED_UNICODE) ?>;
  </script>

  <script>
  (function initCountryCombobox(){
    const root = document.getElementById('countryWrap');
    const btn = document.getElementById('countryBtn');
    const pop = document.getElementById('countryPop');
    const search = document.getElementById('countrySearch');
    const list = document.getElementById('countryList');
    const countBadge = document.getElementById('countryCountBadge');
    if (!root || !btn || !pop || !list) return;

    let open = false;
    let activeIndex = -1;
    let filtered = [];
    let popWasPortaled = false;
    let popHomeParent = null;
    let popHomeNext = null;

    function renderList() {
      list.innerHTML = '';

      // Option: All countries
      const allItem = document.createElement('button');
      allItem.type = 'button';
      allItem.className = 'countryItem' + (CURRENT_COUNTRY === '' ? ' isActive' : '');
      allItem.setAttribute('role', 'option');
      allItem.setAttribute('aria-selected', CURRENT_COUNTRY === '' ? 'true' : 'false');
      allItem.innerHTML = `
        <span class="countryItemLeft">
          <span class="countryItemAllIcon">🌍</span>
          <span class="countryLabelCell">${escapeHtml(ALL_COUNTRIES_LABEL)}</span>
        </span>
        <span class="countryItemRight">
          <span class="countryBadge">ALL</span>
          <span class="countryCheck">✓</span>
        </span>
      `;
      allItem.addEventListener('click', () => chooseCountry(''));
      list.appendChild(allItem);

      // If active today items exist and query is empty, show active group
      const q = (search.value || '').trim();
      if (!q && ACTIVE_COUNTRIES.length > 0) {
        const header = document.createElement('div');
        header.className = 'countrySectionHeader';
        header.textContent = ACTIVE_TODAY_LABEL;
        list.appendChild(header);

        ACTIVE_COUNTRIES.forEach(code => {
          const item = COUNTRY_DATA.find(x => x.code === code);
          if (item) {
            list.appendChild(createCountryItem(item, 'active_'));
          }
        });

        const divider = document.createElement('div');
        divider.className = 'countrySectionHeader';
        divider.textContent = ALL_COUNTRIES_LABEL;
        list.appendChild(divider);
      }

      // Filtered countries
      filtered.forEach(it => {
        list.appendChild(createCountryItem(it));
      });

      if (countBadge) countBadge.textContent = filtered.length;
    }

    function createCountryItem(it, idPrefix = '') {
      const el = document.createElement('button');
      el.type = 'button';
      el.className = 'countryItem' + (CURRENT_COUNTRY === it.code ? ' isActive' : '');
      el.setAttribute('role', 'option');
      el.setAttribute('aria-selected', CURRENT_COUNTRY === it.code ? 'true' : 'false');
      el.innerHTML = `
        <span class="countryItemLeft">
          <img class="countryItemFlag" src="${escapeHtml(it.flag)}" alt="${escapeHtml(it.code)}" />
          <span class="countryLabelCell">${escapeHtml(it.name)}</span>
        </span>
        <span class="countryItemRight">
          <span class="countryBadge">${escapeHtml(it.code)}</span>
          <span class="countryCheck">✓</span>
        </span>
      `;
      el.addEventListener('click', () => chooseCountry(it.code));
      return el;
    }

    function chooseCountry(code) {
      setOpen(false);
      const url = new URL(window.location.href);
      if (code) {
        url.searchParams.set('country', code);
      } else {
        url.searchParams.delete('country');
      }
      url.searchParams.set('d', CURRENT_DATE);
      window.location.href = url.toString();
    }

    function placePopover() {
      if (!popWasPortaled) {
        popHomeParent = pop.parentNode;
        popHomeNext = pop.nextSibling;
        document.body.appendChild(pop);
        popWasPortaled = true;
      }

      pop.hidden = false;
      const r = btn.getBoundingClientRect();
      const popRect = pop.getBoundingClientRect();
      const margin = 8;

      let top = Math.round(r.bottom + margin);
      let left = Math.round(r.right - popRect.width);

      const minLeft = 12;
      const maxLeft = window.innerWidth - popRect.width - 12;
      left = Math.max(minLeft, Math.min(left, maxLeft));

      const maxTop = window.innerHeight - popRect.height - 12;
      if (top > maxTop) {
        top = Math.max(12, Math.round(r.top - popRect.height - margin));
      }

      pop.style.top = top + 'px';
      pop.style.left = left + 'px';
    }

    function unportalPopover() {
      if (popWasPortaled && popHomeParent) {
        if (popHomeNext && popHomeNext.parentNode === popHomeParent) {
          popHomeParent.insertBefore(pop, popHomeNext);
        } else {
          popHomeParent.appendChild(pop);
        }
      }
      popWasPortaled = false;
      popHomeParent = null;
      popHomeNext = null;
      pop.style.top = '';
      pop.style.left = '';
    }

    function setOpen(v) {
      open = v;
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) {
        search.value = '';
        filtered = COUNTRY_DATA.slice();
        renderList();
        placePopover();
        setTimeout(() => search.focus(), 0);
      } else {
        pop.hidden = true;
        unportalPopover();
      }
    }

    function filterCountries(q) {
      const s = q.trim().toLowerCase();
      if (!s) {
        filtered = COUNTRY_DATA.slice();
      } else {
        filtered = COUNTRY_DATA.filter(it =>
          it.name.toLowerCase().includes(s) ||
          it.code.toLowerCase().includes(s)
        );
      }
      renderList();
    }

    btn.addEventListener('click', () => setOpen(!open));

    document.addEventListener('pointerdown', (e) => {
      if (!open) return;
      if (root.contains(e.target) || pop.contains(e.target)) return;
      setOpen(false);
    });

    document.addEventListener('keydown', (e) => {
      if (!open) return;
      if (e.key === 'Escape') {
        e.preventDefault();
        setOpen(false);
        btn.focus();
      }
    });

    search.addEventListener('input', () => filterCountries(search.value));

    function escapeHtml(str) {
      if (!str) return '';
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }
  })();

  function setTimezoneIfNeeded(){
    if (document.cookie.includes('tz=')) return;
    try {
      const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
      if (!tz) return;
      fetch('api/set_timezone.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({timezone: tz}),
        credentials: 'same-origin'
      }).then(() => location.reload()).catch(()=>{});
    } catch(e){}
  }

  function applyLocalDate(){
    const el = document.getElementById('dailyDate');
    if (!el) return;
    const utc = el.getAttribute('data-utc-date');
    if (!utc) return;
    const d = new Date(utc + 'T00:00:00Z');
    if (isNaN(d.getTime())) return;
    el.textContent = d.toLocaleDateString();
  }

  setTimezoneIfNeeded();
  applyLocalDate();
  </script>
</body>
</html>
