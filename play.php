<?php
require_once __DIR__ . '/bootstrap.php';
if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
  error_reporting(E_ALL);
  @ini_set('display_errors', '1');
  @ini_set('display_startup_errors', '1');
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/i18n.php';

if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
  error_reporting(E_ALL);
  @ini_set('display_errors', '1');
  @ini_set('display_startup_errors', '1');
  register_shutdown_function(function(){
    $e = error_get_last();
    if ($e) {
      echo "<pre>FATAL: " . htmlspecialchars($e['message']) . " @ " . htmlspecialchars($e['file']) . ":" . (int)$e['line'] . "</pre>";
    }
  });
}

$lang = function_exists('get_lang') ? get_lang() : 'en';
$dir  = function_exists('lang_dir') ? lang_dir($lang) : 'ltr';

$userId = $_SESSION['user_id'] ?? null;
$userEmail = $_SESSION['user_email'] ?? null;
$isLoggedIn = !empty($userId) && !empty($userEmail);

$userDisplay = $_SESSION['user_name'] ?? ($userId ? user_display_name($userId) : null);
$isDailyMode = isset($_GET['daily']) && $_GET['daily'] !== '0';
$dailyDateUtc = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d');

$initialMode = isset($_GET['mode']) ? (string)$_GET['mode'] : 'elimination';
$initialMode = in_array($initialMode, ['elimination', 'points', 'flags'], true) ? $initialMode : 'elimination';

function tt(string $key, string $fallback = ''): string {
  $v = t($key);
  if ($v === $key) return $fallback !== '' ? $fallback : $key;
  return $v;
}

function google_badge_html(): string {
  return '<span class="google-badge"><img src="google.svg" class="google-icon" alt="Google" /><span class="google-text">Google</span></span>';
}

function render_google_label(string $text): string {
  $safe = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
  return str_replace('{google}', google_badge_html(), $safe);
}

$seoTitle = $isDailyMode
  ? tt('daily_meta_title', 'Daily Challenge - Prismatch')
  : tt('play_meta_title', 'Play Prismatch');
$seoDescription = $isDailyMode
  ? tt('daily_meta_description', 'Play the daily Prismatch challenge: test your memory with elimination, points or world flag mode.')
  : tt('play_meta_description', 'Play Prismatch and test your short-term memory with fast, progressive rounds.');
$seoLangs = function_exists('supported_languages') ? array_keys(supported_languages()) : [];
$dict = translations();
$rightAnswerMessages = $dict[$lang]['right_answer_messages'] ?? ($dict['en']['right_answer_messages'] ?? []);
$wrongAnswerMessages = $dict[$lang]['wrong_answer_messages'] ?? ($dict['en']['wrong_answer_messages'] ?? []);

// Login sonrası bekleyen sonuç varsa DB'ye commit et (MySQL)
$flash = null;
if ($userEmail && !empty($_SESSION['pending_result']) && is_array($_SESSION['pending_result'])) {
  $userId = $_SESSION['user_id'] ?? null;
  if ($userId) {
    record_full_game($userId, $userEmail, $_SESSION['pending_result']);
  }
  unset($_SESSION['pending_result']);
  $flash = tt('flash_saved', '✅ Result saved.');
}

$stats = $userEmail ? get_user_stats($userEmail) : null;
$allFlags = \Prismatch\Services\RoomGameService::getFlagPalette();
?>
<!doctype html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= htmlspecialchars($dir) ?>">
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
  <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700;800&family=Baloo+2:wght@500;600;700;800&display=swap" rel="stylesheet" />
  <title><?= htmlspecialchars($seoTitle) ?></title>

  <link rel="icon" type="image/svg+xml" href="logo.svg" />
  <?= seo_meta([
    'title' => $seoTitle,
    'description' => $seoDescription,
    'url' => seo_current_url(),
    'image' => '/logo.png',
    'type' => 'website',
    'robots' => 'index,follow',
    'lang' => $lang,
    'site_name' => tt('app_name', 'Prismatch'),
    'breadcrumbs' => [
      ['name' => tt('nav_home', 'Home'), 'url' => '/'],
      ['name' => $isDailyMode ? tt('daily_title', 'Daily Challenge') : tt('play_title', 'Play Game'), 'url' => $isDailyMode ? '/play.php?daily=1' : '/play.php'],
    ],
  ]) ?>
  <?= seo_alternate_links($seoLangs, seo_current_url()) ?>

  <style>
    :root{ color-scheme: light dark; }
    :root,
    [data-bs-theme="dark"]{
      --bg: #0a0f1b;
      --panel: rgba(255,255,255,0.12);
      --panel2: rgba(255,255,255,0.16);
      --text: #f7f3ff;
      --muted: rgba(229,234,255,0.7);
      --accent: #ff6b5b;
      --accent2: #49f2b2;
      --accent3: #ffd36b;
      --shadow: 0 36px 70px rgba(0,0,0,0.55);
      --radius: 24px;
    }
    [data-bs-theme="light"]{
      --bg: #fff4e8;
      --panel: rgba(255,255,255,0.95);
      --panel2: rgba(255,255,255,0.8);
      --text: #1f1b2b;
      --muted: rgba(31,27,43,0.68);
      --accent: #ff6b5b;
      --accent2: #20b77d;
      --accent3: #ffb24b;
      --shadow: 0 28px 56px rgba(40,29,12,0.18);
      --radius: 24px;
    }
    * { box-sizing: border-box; }
    html, body {
      min-height: 100%;
      height: auto;
    }
    body{
      margin:0;
      font-family: "Rubik", "Segoe UI", "Helvetica Neue", sans-serif;
      background: var(--bg);
      color: var(--text);
      display:flex;
      flex-direction: column;
      align-items:center;
      justify-content:flex-start;
      min-height: 100vh;
      box-sizing: border-box;
      padding: 16px;
      padding-top: calc(var(--pm-header-offset, 76px) + 16px) !important;
      padding-bottom: calc(var(--pm-footer-offset, 60px) + 24px + env(safe-area-inset-bottom));
      overflow-x: hidden;
      overflow-y: auto;
    }
    body::before,
    body::after{ display:none; }

    .app{
      width: min(760px, 100%);
      display:flex;
      flex-direction:column;
      gap: 12px;
      margin: 0 auto;
    }

    .hud{
      display:flex;
      gap: 10px;
      flex-wrap:wrap;
      align-items:stretch;
      justify-content:space-between;
    }
    .hud .chip{
      flex: 1 1 140px;
      background: linear-gradient(135deg, rgba(255,255,255,0.10), rgba(255,255,255,0.04));
      border: 1px solid rgba(255,255,255,0.14);
      border-radius: 999px;
      padding: 10px 14px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap: 10px;
      backdrop-filter: blur(10px);
    }
    .hud .label{ color: var(--muted); font-size: 12px; letter-spacing: 0.3px; }
    .hud .value{ font-weight: 700; font-size: 14px; }
    .hud-score-chip {
      background: linear-gradient(135deg, rgba(255, 211, 107, 0.22), rgba(255, 107, 91, 0.15)) !important;
      border-color: rgba(255, 211, 107, 0.4) !important;
    }
    .hud-score-val {
      color: #ffd36b;
      font-weight: 800 !important;
      font-size: 15px !important;
    }

    .stage{
      background: linear-gradient(160deg, rgba(255,255,255,0.10), rgba(255,255,255,0.04));
      border: 1px solid rgba(255,255,255,0.14);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      backdrop-filter: blur(12px);
      display:flex;
      flex-direction:column;
      align-items:center;
      justify-content:center;
      padding: 24px 20px 22px;
      position:relative;
      overflow:hidden;
      min-height: 380px;
      width: 100%;
      box-sizing: border-box;
    }
    .stage::before{
      content:"";
      position:absolute;
      inset:auto -20% -35% -20%;
      height: 55%;
      background: radial-gradient(closest-side, rgba(53,208,186,0.18), transparent 70%);
      pointer-events:none;
    }

    .center{
      width: 100%;
      display:flex;
      flex-direction:column;
      align-items:center;
      justify-content:center;
      gap: 14px;
    }

    .title{
      font-family: "Baloo 2", "Rubik", "Segoe UI", "Helvetica Neue", sans-serif;
      font-size: 22px;
      font-weight: 800;
      letter-spacing: 0.2px;
      text-align: center;
    }
    .subtitle{
      color: var(--muted);
      font-size: 13.5px;
      text-align:center;
      max-width: 560px;
      line-height: 1.4;
    }

    /* Mode Selector Cards */
    .mode-select-wrap {
      width: 100%;
      max-width: 660px;
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
      margin: 4px 0 10px 0;
    }
    @media (max-width: 680px) {
      .mode-select-wrap {
        grid-template-columns: 1fr;
        gap: 8px;
      }
    }
    .mode-card {
      background: rgba(255, 255, 255, 0.05);
      border: 2px solid rgba(255, 255, 255, 0.12);
      border-radius: 16px;
      padding: 13px 12px;
      cursor: pointer;
      text-align: left;
      transition: all 0.2s ease;
      position: relative;
      display: flex;
      flex-direction: column;
      gap: 6px;
      user-select: none;
    }
    .mode-card:hover {
      background: rgba(255, 255, 255, 0.09);
      border-color: rgba(255, 255, 255, 0.25);
      transform: translateY(-2px);
    }
    .mode-card.active {
      background: rgba(255, 107, 91, 0.12);
      border-color: #ff6b5b;
      box-shadow: 0 0 24px rgba(255, 107, 91, 0.25);
    }
    .mode-card.mode-points.active {
      background: rgba(255, 211, 107, 0.12);
      border-color: #ffd36b;
      box-shadow: 0 0 24px rgba(255, 211, 107, 0.25);
    }
    .mode-card.mode-flags.active {
      background: rgba(13, 202, 240, 0.12);
      border-color: #0dcaf0;
      box-shadow: 0 0 24px rgba(13, 202, 240, 0.25);
    }
    .mode-card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 6px;
    }
    .mode-card-title {
      font-weight: 700;
      font-size: 14.5px;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .mode-card-badge {
      font-size: 10.5px;
      font-weight: 700;
      padding: 2px 7px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.1);
      color: var(--muted);
      white-space: nowrap;
    }
    .mode-card-desc {
      font-size: 11.5px;
      color: var(--muted);
      line-height: 1.35;
    }
    .mode-played-badge {
      font-size: 11px;
      font-weight: 700;
      color: #10b981;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      margin-top: 2px;
    }

    .countdown{
      font-size: clamp(56px, 9vw, 96px);
      font-weight: 900;
      letter-spacing: 1px;
      text-shadow: 0 10px 32px rgba(0,0,0,0.45);
      transform: translateZ(0);
      animation: pop 1s ease both;
      user-select:none;
    }
    @keyframes pop{
      0%{ opacity:0; transform: scale(0.85); }
      55%{ opacity:1; transform: scale(1.03); }
      100%{ opacity:1; transform: scale(1.0); }
    }

    .target-card{
      width: min(420px, 90%);
      aspect-ratio: 16/9;
      border-radius: calc(var(--radius) + 6px);
      box-shadow: 0 16px 48px rgba(0,0,0,0.45);
      border: 2px solid rgba(255,255,255,0.10);
      transform: translateZ(0);
      animation: fadeScale 260ms ease both;
    }
    @keyframes fadeScale{
      from{ opacity:0; transform: scale(0.96); }
      to{ opacity:1; transform: scale(1); }
    }

    /* Flag Target Card */
    .target-box-flag {
      background-color: #151922;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 12px;
      overflow: hidden;
    }
    .target-flag-img {
      max-width: 100%;
      max-height: 100%;
      width: auto;
      height: auto;
      object-fit: contain;
      border-radius: 12px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.45);
    }

    .question{
      display:flex;
      flex-direction:column;
      gap: 4px;
      align-items:center;
      text-align:center;
      margin-bottom: 4px;
    }
    .question .q{
      font-size: 15px;
      font-weight: 700;
    }
    .question .hint{
      color: var(--muted);
      font-size: 12px;
    }

    .grid{
      width: min(440px, 92vw, calc(100dvh - 300px));
      min-width: min(260px, 100%);
      max-width: 100%;
      aspect-ratio: 1 / 1;
      display:grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
      margin: 0 auto;
    }
    .grid-loading {
      opacity: 0;
      pointer-events: none;
    }
    .grid-ready {
      opacity: 1;
      transition: opacity 0.15s ease-in;
    }

    .cell{
      appearance:none;
      border:none;
      padding:0;
      border-radius: 16px;
      aspect-ratio: 1 / 1;
      cursor:pointer;
      outline:none;
      box-shadow: 0 14px 34px rgba(0,0,0,0.35);
      border: 2px solid rgba(255,255,255,0.10);
      transform: translateZ(0);
      transition: transform 120ms ease, box-shadow 120ms ease, border-color 120ms ease, filter 120ms ease;
    }
    .cell:hover{ transform: translateY(-2px); box-shadow: 0 18px 44px rgba(0,0,0,0.42); }
    .cell:active{ transform: translateY(0px) scale(0.99); }
    .cell:focus-visible{
      border-color: rgba(255,255,255,0.65);
      box-shadow: 0 0 0 4px rgba(255,255,255,0.18), 0 18px 44px rgba(0,0,0,0.42);
    }
    .cell[disabled]{ cursor:not-allowed; opacity: 0.70; filter: saturate(0.85); }
    .cell.correct{
      border-color: rgba(34,197,94,0.9);
      box-shadow: 0 0 0 4px rgba(34,197,94,0.22), 0 18px 44px rgba(0,0,0,0.42);
    }
    .cell.wrong{
      border-color: rgba(255,77,77,0.9);
      box-shadow: 0 0 0 4px rgba(255,77,77,0.18), 0 18px 44px rgba(0,0,0,0.42);
    }

    /* Flag Option Cells */
    .choice-cell-flag {
      background-color: #171c26 !important;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 6px;
      overflow: hidden;
    }
    .choice-cell-flag .choice-flag-img {
      max-width: 100%;
      max-height: 100%;
      width: auto;
      height: auto;
      object-fit: contain;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.3);
      pointer-events: none;
    }

    .toast{
      position:absolute;
      top: 14px;
      left: 50%;
      transform: translateX(-50%);
      background: rgba(8,16,23,0.75);
      border: 1px solid rgba(255,255,255,0.22);
      padding: 10px 14px;
      border-radius: 999px;
      font-size: 13px;
      font-weight: 700;
      color: var(--text);
      backdrop-filter: blur(10px);
      box-shadow: 0 16px 50px rgba(0,0,0,0.35);
      opacity:0;
      pointer-events:none;
      transition: opacity 140ms ease, transform 140ms ease;
      display:flex;
      align-items:center;
      gap: 8px;
      max-width: min(560px, calc(100% - 28px));
      text-overflow: ellipsis;
      white-space: nowrap;
      overflow:hidden;
      z-index: 100;
    }
    .toast.show{
      opacity:1;
      transform: translateX(-50%) translateY(2px);
    }

    .answerOverlay{
      position:fixed;
      inset:0;
      background: radial-gradient(circle at top, rgba(61,214,160,0.15), transparent 45%), rgba(8,16,23,0.7);
      display:flex;
      align-items:center;
      justify-content:center;
      padding: 20px;
      z-index: 42000;
      backdrop-filter: blur(10px);
    }
    .answerOverlay[hidden]{ display:none; }
    .answerCard{
      width: min(520px, 92vw);
      border-radius: 28px;
      padding: 24px 22px;
      text-align:center;
      border: 1px solid rgba(255,255,255,0.2);
      background:
        radial-gradient(240px 240px at 15% 15%, rgba(255,255,255,0.16), transparent 60%),
        linear-gradient(160deg, rgba(255,255,255,0.12), rgba(255,255,255,0.04));
      box-shadow: 0 32px 80px rgba(0,0,0,0.55);
      position:relative;
      overflow:hidden;
    }
    .answerCard.success{
      border-color: rgba(46, 204, 113, 0.6);
      background:
        radial-gradient(240px 240px at 15% 15%, rgba(46, 204, 113, 0.18), transparent 60%),
        linear-gradient(160deg, rgba(255,255,255,0.12), rgba(255,255,255,0.04));
    }
    .answerCard.error{
      border-color: rgba(255,77,77,0.7);
      background:
        radial-gradient(240px 240px at 15% 15%, rgba(255,77,77,0.18), transparent 60%),
        linear-gradient(160deg, rgba(255,255,255,0.12), rgba(255,255,255,0.04));
    }
    .answerCard::after{
      content:"";
      position:absolute;
      inset:-40% -20% auto auto;
      width: 220px;
      height: 220px;
      border-radius: 999px;
      background: radial-gradient(circle, rgba(255,208,138,0.35), transparent 70%);
      opacity: 0.9;
      pointer-events:none;
    }
    .answerIconWrap{
      width: 120px;
      height: 120px;
      border-radius: 32px;
      margin: 0 auto 14px auto;
      display:flex;
      align-items:center;
      justify-content:center;
      background: rgba(255,255,255,0.12);
      border: 1px solid rgba(255,255,255,0.18);
      box-shadow: inset 0 0 0 1px rgba(255,255,255,0.06), 0 16px 36px rgba(0,0,0,0.35);
    }
    .answerIcon{
      width: 78px;
      height: 78px;
      animation: popIn 320ms ease;
    }
    .answerMessage{
      font-family:"Baloo 2","Rubik","Segoe UI","Helvetica Neue",sans-serif;
      font-size: clamp(18px, 3.8vw, 22px);
      font-weight: 700;
      line-height: 1.4;
      margin: 6px 0 2px 0;
    }
    .answerSub{
      font-size: 12px;
      color: var(--muted);
      margin: 0;
    }
    .answerCard.success .answerIcon{ animation: popIn 320ms ease, floaty 4s ease-in-out infinite; }
    .answerCard.error .answerIcon{ animation: popIn 320ms ease, shake 420ms ease; }
    @keyframes popIn{ from{ transform: scale(0.75); opacity:0; } to{ transform: scale(1); opacity:1; } }
    @keyframes shake{
      0%,100%{ transform: translateX(0); }
      20%{ transform: translateX(-6px); }
      40%{ transform: translateX(6px); }
      60%{ transform: translateX(-4px); }
      80%{ transform: translateX(4px); }
    }

    .modalOverlay{
      position:fixed;
      inset:0;
      background: rgba(8,16,23,0.62);
      display:flex;
      align-items:center;
      justify-content:center;
      padding: 18px;
      z-index: 40000;
      backdrop-filter: blur(8px);
    }
    .modalOverlay[hidden]{
      display:none;
    }
    .modalCard{
      width: min(420px, 92vw);
      background: linear-gradient(160deg, rgba(255,255,255,0.12), rgba(255,255,255,0.04));
      border: 1px solid rgba(255,255,255,0.2);
      border-radius: 20px;
      padding: 16px;
      box-shadow: 0 24px 60px rgba(0,0,0,0.45);
    }
    .modalTitle{
      margin: 0 0 6px 0;
      font-family: "Baloo 2", "Rubik", "Segoe UI", "Helvetica Neue", sans-serif;
      font-size: 18px;
    }
    .modalText{
      margin: 0 0 14px 0;
      color: var(--muted);
      line-height: 1.45;
      font-size: 13px;
    }
    .modalActions{
      display:flex;
      gap: 10px;
      justify-content:flex-end;
      flex-wrap:wrap;
    }

    .footer{
      display:flex;
      gap: 10px;
      justify-content:space-between;
      align-items:center;
      flex-wrap:wrap;
    }
    .mute-toggle{
      gap: 8px;
      padding: 8px 12px;
      font-size: 12px;
      border-radius: 999px;
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.18);
    }
    .mute-toggle .mute-dot{
      width: 10px;
      height: 10px;
      border-radius: 999px;
      background: rgba(61,214,160,0.95);
      box-shadow: 0 0 0 3px rgba(61,214,160,0.15);
    }
    .mute-toggle.is-muted .mute-dot{
      background: rgba(255,77,77,0.95);
      box-shadow: 0 0 0 3px rgba(255,77,77,0.18);
    }

    .btn{
      appearance:none;
      border:none;
      cursor:pointer;
      color: var(--text);
      background: linear-gradient(180deg, rgba(255,255,255,0.12), rgba(255,255,255,0.06));
      border: 1px solid rgba(255,255,255,0.18);
      border-radius: 999px;
      padding: 10px 14px;
      font-weight: 700;
      letter-spacing: 0.2px;
      transition: transform 120ms ease, background 120ms ease, border-color 120ms ease, box-shadow 120ms ease;
      backdrop-filter: blur(12px);
      text-decoration:none;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap: 8px;
    }
    .google-badge{
      display:inline-flex;
      align-items:center;
      gap:6px;
      font-weight:700;
    }
    .google-icon{
      width:16px;
      height:16px;
      display:inline-block;
    }
    .google-text{ line-height:1; }
    .btn:hover{ transform: translateY(-1px); border-color: rgba(255,255,255,0.28); box-shadow: 0 10px 26px rgba(0,0,0,0.35); }
    .btn:active{ transform: translateY(0px); }
    .btn.primary{
      background: linear-gradient(135deg, rgba(255,139,92,0.95), rgba(247,195,82,0.95));
      border-color: rgba(255,139,92,0.6);
      color: #101318;
      box-shadow: 0 16px 34px rgba(255,139,92,0.35);
    }
    .btn[disabled]{ opacity:0.5; cursor:not-allowed; transform:none !important; }

    .badge{
      font-size: 12px;
      color: var(--muted);
    }
    .pm-error{ color: #dc3545; font-weight: 600; }
    .pm-success{ color: #198754; font-weight: 600; }
    .bi-icon{ width:16px; height:16px; display:inline-block; }

    .stats{
      width: 100%;
      display:grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 10px;
      margin-top: 4px;
    }
    .stat{
      background: rgba(255,255,255,0.06);
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 14px;
      padding: 10px 14px;
      display:flex;
      flex-direction:column;
      gap: 3px;
    }
    .stat .k{ color: var(--muted); font-size: 11px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .stat .v{ font-weight: 800; font-size: 15px; }

    .action-row{
      width: min(680px, 100%);
      display:grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 10px;
      align-items:stretch;
    }
    .action-row .btn{ width:100%; }

    @media (max-width: 600px){
      .stats{ grid-template-columns: repeat(2, 1fr); gap: 8px; }
      .stage{ padding: 20px 12px 18px; border-radius: 18px; min-height: 320px; }
      .grid{ gap: 8px; width: min(440px, 92vw, calc(100dvh - 280px)); }
      .cell{ border-radius: 12px; }
      .hud .chip{ padding: 8px 12px; flex: 1 1 120px; }
      .action-row{ grid-template-columns: 1fr; }
    }

    @media (prefers-reduced-motion: reduce){
      * { animation: none !important; transition: none !important; }
    }
    
    .logo{
      width:76px; height:76px;
      border-radius: 20px;
      box-shadow: 0 18px 60px rgba(0,0,0,0.45);
      border: 1px solid rgba(255,255,255,0.14);
      backdrop-filter: blur(10px);
      animation: floaty 5s ease-in-out infinite;
    }
    @keyframes floaty{
      0%,100%{ transform: translateY(0); }
      50%{ transform: translateY(-6px); }
    }
  </style>
</head>
<body class="pm-has-fixed-header">
  <?php include __DIR__ . '/header.php'; ?>
  <div class="app">
    <div class="hud">
      <div class="chip">
        <span class="label"><?= htmlspecialchars(tt('hud_stage', 'Stage')) ?></span>
        <span class="value" id="hudLevel">-</span>
      </div>
      <div class="chip">
        <span class="label"><?= htmlspecialchars(tt('hud_correct', 'Correct')) ?></span>
        <span class="value" id="hudCorrect">0</span>
      </div>
      <div class="chip hud-score-chip" id="chipScore" style="display:none;">
        <span class="label"><?= htmlspecialchars(tt('th_score', 'Score')) ?></span>
        <span class="value hud-score-val" id="hudScore">0</span>
      </div>
      <div class="chip">
        <span class="label"><?= htmlspecialchars(tt('hud_show', 'Show')) ?></span>
        <span class="value" id="hudShow">-</span>
      </div>
      <div class="chip">
        <span class="label"><?= htmlspecialchars(tt('hud_time', 'Time')) ?></span>
        <span class="value" id="hudTime">-</span>
      </div>
    </div>

    <div class="stage">
      <div id="toast" class="toast" role="status" aria-live="polite"></div>
      <div id="center" class="center">
        <img class="logo" src="logo.svg" alt="<?= htmlspecialchars(tt('app_name', 'Prismatch')) ?>" />
        <h1 class="title">
          <?= htmlspecialchars($isDailyMode ? tt('daily_title', 'Daily Challenge') : tt('play_title', 'Ready to play?')) ?>
        </h1>
        <div class="subtitle" id="lobbySubtitle">
          <?= htmlspecialchars($isDailyMode ? tt('daily_mode_intro', 'Günün meydan okuma modunu seç ve küresel liderlik tablosunda yerini al!') : tt('play_subtitle', 'Remember the shown color, then pick it from the grid.')) ?>
        </div>

        <!-- Game Mode Selection Cards -->
        <div class="mode-select-wrap" id="modeSelectWrap">
          <div class="mode-card <?= $initialMode === 'elimination' ? 'active' : '' ?>" data-mode="elimination" id="modeCardElim">
            <div class="mode-card-header">
              <span class="mode-card-title">⚡ <?= htmlspecialchars(tt('rooms_mode_elim_title', 'Eleme Modu')) ?></span>
              <span class="mode-card-badge">50 <?= htmlspecialchars(tt('hud_stage', 'Stage')) ?></span>
            </div>
            <div class="mode-card-desc"><?= htmlspecialchars(tt('rooms_mode_elim_desc', 'Yanlış yapan veya süresi dolan elenir. En yüksek aşamaya ulaş!')) ?></div>
            <div class="mode-played-badge" id="playedBadgeElim" style="display:none;">✅ <?= htmlspecialchars(tt('daily_badge_played', 'Oynandı')) ?></div>
          </div>
          <div class="mode-card mode-points <?= $initialMode === 'points' ? 'active' : '' ?>" data-mode="points" id="modeCardPoints">
            <div class="mode-card-header">
              <span class="mode-card-title">🎯 <?= htmlspecialchars(tt('rooms_mode_points_title', 'Puan Modu')) ?></span>
              <span class="mode-card-badge">25 <?= htmlspecialchars(tt('hud_stage', 'Stage')) ?></span>
            </div>
            <div class="mode-card-desc"><?= htmlspecialchars(tt('rooms_mode_points_desc', 'Elenme yok! Doğru cevap puan kazandırır, yanlış seçim puan düşürür.')) ?></div>
            <div class="mode-played-badge" id="playedBadgePoints" style="display:none;">✅ <?= htmlspecialchars(tt('daily_badge_played', 'Oynandı')) ?></div>
          </div>
          <div class="mode-card mode-flags <?= $initialMode === 'flags' ? 'active' : '' ?>" data-mode="flags" id="modeCardFlags">
            <div class="mode-card-header">
              <span class="mode-card-title">🚩 <?= htmlspecialchars(tt('rooms_mode_flags_title', 'Bayrak Modu')) ?></span>
              <span class="mode-card-badge">25 <?= htmlspecialchars(tt('hud_stage', 'Stage')) ?></span>
            </div>
            <div class="mode-card-desc"><?= htmlspecialchars(tt('rooms_mode_flags_desc', 'Renkler yerine 250+ ülke bayrağı! Elenme yok, doğru bayrak puan kazandırır.')) ?></div>
            <div class="mode-played-badge" id="playedBadgeFlags" style="display:none;">✅ <?= htmlspecialchars(tt('daily_badge_played', 'Oynandı')) ?></div>
          </div>
        </div>

        <div id="dailyNotice" class="badge pm-error" style="display:none; font-size:13px; font-weight:600;"></div>
        <div id="localBestBadge" class="badge" style="display:none; font-size:13px; font-weight:700; color: #ffb84d; border-color: rgba(255,184,77,0.3); background: rgba(255,184,77,0.08); margin-bottom: 8px;">
          ⭐ <?= htmlspecialchars(tt('personal_best', 'Personal Best')) ?>: <span id="localBestVal">0</span>
        </div>

        <div class="action-row">
          <button id="btnStart" class="btn primary" type="button">
            <img class="bi-icon" src="bootstrap-icons/play-fill.svg" alt="" aria-hidden="true" />
            <?= htmlspecialchars(tt('btn_start', 'Start')) ?>
          </button>
          <?php if ($isDailyMode): ?>
            <a class="btn" href="daily_leaderboard.php">
              <img class="bi-icon" src="bootstrap-icons/trophy.svg" alt="" aria-hidden="true" />
              <?= htmlspecialchars(tt('daily_leaderboard_title', 'Daily Leaderboard')) ?>
            </a>
          <?php elseif ($userEmail): ?>
            <a class="btn" href="games.php">
              <img class="bi-icon" src="bootstrap-icons/clock-history.svg" alt="" aria-hidden="true" />
              <?= htmlspecialchars(tt('btn_view_history', 'My Sessions')) ?>
            </a>
          <?php endif; ?>
        </div>
      </div>
      <div id="statusBadge" class="badge"><?= htmlspecialchars(tt('status_ready', 'Ready.')) ?></div>
    </div>

    <?php if (!empty($stats) && !$isDailyMode): ?>
      <div class="stats">
        <div class="stat">
          <div class="k"><?= htmlspecialchars(tt('stats_total_plays', 'Total plays')) ?></div>
          <div class="v"><?= (int)($stats['total_plays'] ?? 0) ?></div>
        </div>
        <div class="stat">
          <div class="k"><?= htmlspecialchars(tt('stats_total_wins', 'Total wins')) ?></div>
          <div class="v"><?= (int)($stats['total_wins'] ?? 0) ?></div>
        </div>
        <div class="stat">
          <div class="k"><?= htmlspecialchars(tt('stats_best_level', 'Best level')) ?></div>
          <div class="v"><?= (int)($stats['best_level'] ?? 0) ?></div>
        </div>
        <div class="stat">
          <div class="k"><?= htmlspecialchars(tt('stats_total_correct', 'Total correct')) ?></div>
          <div class="v"><?= (int)($stats['total_correct'] ?? 0) ?></div>
        </div>
      </div>
    <?php endif; ?>

    <div class="footer">
      <div class="badge">
        <?= htmlspecialchars($isDailyMode ? tt('daily_once', 'Daily challenge: one attempt per day.') : tt('play_hint', 'Answer quickly to earn a higher score.')) ?>
      </div>
      <button id="btnMute" class="btn mute-toggle" type="button" aria-pressed="false">
        <span class="mute-dot" aria-hidden="true"></span>
        <span id="muteLabel"><?= htmlspecialchars(tt('sound_on', 'Sound: On')) ?></span>
      </button>
    </div>
  </div>

  <div id="answerOverlay" class="answerOverlay" hidden>
    <div id="answerCard" class="answerCard" role="dialog" aria-modal="true" aria-label="<?= htmlspecialchars(tt('answer_popup', 'Answer')) ?>">
      <div class="answerIconWrap">
        <img id="answerIcon" class="answerIcon" src="success-checkmark.svg" alt="" aria-hidden="true" />
      </div>
      <div id="answerText" class="answerMessage">-</div>
      <p class="answerSub"><?= htmlspecialchars(tt('answer_sub', 'Keep going!')) ?></p>
    </div>
  </div>

  <div id="dailyCompletedModal" class="modalOverlay" hidden>
    <div class="modalCard" role="dialog" aria-modal="true" aria-label="<?= htmlspecialchars(tt('daily_completed_title', 'Daily complete')) ?>">
      <h2 class="modalTitle"><?= htmlspecialchars(tt('daily_completed_title', 'Daily complete')) ?></h2>
      <p id="dailyCompletedText" class="modalText"><?= htmlspecialchars(tt('daily_completed', 'You already played today. Come back tomorrow!')) ?></p>
      <div class="modalActions">
        <a class="btn primary" id="dailyCompletedLeaderboardBtn" href="daily_leaderboard.php">
          <img class="bi-icon" src="bootstrap-icons/trophy.svg" alt="" aria-hidden="true" />
          <?= htmlspecialchars(tt('daily_leaderboard_title', 'Daily Leaderboard')) ?>
        </a>
        <button id="dailyCompletedOk" class="btn" type="button">
          <img class="bi-icon" src="bootstrap-icons/arrow-left.svg" alt="" aria-hidden="true" />
          <?= htmlspecialchars(tt('btn_back_home', 'Back to home')) ?>
        </button>
      </div>
    </div>
  </div>

  <div id="dailyLoginModal" class="modalOverlay" hidden>
    <div class="modalCard" role="dialog" aria-modal="true" aria-label="<?= htmlspecialchars(tt('daily_login_title', 'Login required')) ?>">
      <h2 class="modalTitle"><?= htmlspecialchars(tt('daily_login_title', 'Login required')) ?></h2>
      <p class="modalText"><?= htmlspecialchars(tt('daily_login_required', 'Log in to play the daily challenge.')) ?></p>
      <div class="modalActions">
        <a class="btn primary" href="login.php?next=<?= rawurlencode('play.php?daily=1') ?>">
          <img class="bi-icon" src="bootstrap-icons/box-arrow-in-right.svg" alt="" aria-hidden="true" />
          <?= htmlspecialchars(tt('home_cta_login', 'Sign in')) ?>
        </a>
        <button id="dailyLoginBack" class="btn" type="button">
          <img class="bi-icon" src="bootstrap-icons/arrow-left.svg" alt="" aria-hidden="true" />
          <?= htmlspecialchars(tt('btn_back_home', 'Back to home')) ?>
        </button>
      </div>
    </div>
  </div>

  <script>
  const I18N = <?= json_encode([
    'app_name' => tt('app_name', 'Prismatch'),
    'countdown_help' => tt('countdown_help', 'Remember the shown color, then pick it from the grid.'),
    'hud_stage' => tt('hud_stage', 'Stage'),
    'remember_this' => tt('remember_this', 'Remember this color.'),
    'remember_flag' => tt('remember_flag', 'Remember this flag!'),
    'question_pick_target' => tt('question_pick_target', 'Which color was shown? Pick the target.'),
    'question_pick_target_flag' => tt('question_pick_target_flag', 'Which flag was shown? Pick the target.'),
    'question_hint' => tt('question_hint', 'Use Tab/Shift+Tab and Enter/Space to pick.'),
    'badge_ready' => tt('badge_ready', 'Ready. Countdown...'),
    'badge_showing_target' => tt('badge_showing_target', 'Showing target...'),
    'badge_showing_target_flag' => tt('badge_showing_target_flag', 'Showing target flag...'),
    'badge_answer' => tt('badge_answer', 'Answer now!'),
    'badge_pick_flag' => tt('badge_pick_flag', 'PICK FLAG!'),
    'toast_pick' => tt('toast_pick', 'Pick within 5 seconds'),
    'badge_correct' => tt('badge_correct', 'Perfect match! Next stage...'),
    'badge_wrong' => tt('badge_wrong', 'Wrong match. Game over.'),
    'toast_correct' => tt('toast_correct', 'Perfect Match!'),
    'toast_wrong' => tt('toast_wrong', 'Wrong Match!'),
    'toast_timeup_no_points' => tt('toast_timeup_no_points', '0 pts (Time Up)'),
    'win_title' => tt('win_title', 'You matched them all!'),
    'win_body' => tt('win_body', 'You reached stage {level}. Total perfect matches: {correct}'),
    'points_finish_title' => tt('points_finish_title', 'Challenge Complete!'),
    'points_finish_body' => tt('points_finish_body', 'You completed all {level} stages! Total score: {score}'),
    'gameover_title' => tt('gameover_title', 'Game over'),
    'gameover_body' => tt('gameover_body', '{reason} Tap to try again.'),
    'reason_wrong' => tt('reason_wrong', 'Wrong match.'),
    'reason_timeup' => tt('reason_timeup', 'Time\'s up.'),
    'status_ready' => tt('status_ready', 'Ready.'),
    'status_finished' => tt('status_finished', 'Finished'),
    'btn_play_again' => tt('btn_play_again', 'Play again'),
    'btn_restart' => tt('btn_restart', 'Restart'),
    'btn_view_history' => tt('btn_view_history', 'My Sessions'),
    'daily_leaderboard_title' => tt('daily_leaderboard_title', 'Daily Leaderboard'),
    'sound_on' => tt('sound_on', 'Sound: On'),
    'sound_off' => tt('sound_off', 'Sound: Off'),
    'th_score' => tt('th_score', 'Score'),
    'daily_once' => tt('daily_once', 'Daily challenge: one attempt per day.'),
    'daily_login_required' => tt('daily_login_required', 'Log in to play the daily challenge.'),
    'daily_completed' => tt('daily_completed', 'You already played this mode today. Come back tomorrow or try another mode!'),
    'save_after_title' => tt('save_after_title', 'Save your score?'),
    'save_after_body' => tt('save_after_body', 'Log in with {google} to save this session and view detailed stats.'),
    'save_with_google' => tt('save_with_google', 'Save with {google}'),
    'continue_without_saving' => tt('continue_without_saving', 'Continue without saving'),
    'save_pending' => tt('save_pending', 'Save queued. It will sync on next load.'),
    'save_offline_queued' => tt('save_offline_queued', 'Skor cihazınıza kaydedildi. İnternet bağlantısı sağlandığında sunucuyla senkronize edilecektir.'),
    'personal_best' => tt('personal_best', 'Kişisel En İyi'),
    'a11y_color_option' => tt('a11y_color_option', 'Color option {n}'),
    'a11y_flag_option' => tt('a11y_flag_option', 'Flag option {n}'),
    'no_results' => tt('no_results', 'No results'),
    'err_palette' => tt('err_palette', 'Insufficient item pool.'),
    'right_answer_messages' => $rightAnswerMessages,
    'wrong_answer_messages' => $wrongAnswerMessages,
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

  const tjs = (key, fallback = '') => {
    const v = I18N[key];
    if (v === undefined || v === null || v === '') return fallback || key;
    return v;
  };
  const tf = (key, vars = {}, fallback = '') => {
    let s = tjs(key, fallback);
    for (const k in vars) s = s.replaceAll('{' + k + '}', String(vars[k]));
    return s;
  };

  const RIGHT_MESSAGES = Array.isArray(I18N.right_answer_messages) ? I18N.right_answer_messages : [];
  const WRONG_MESSAGES = Array.isArray(I18N.wrong_answer_messages) ? I18N.wrong_answer_messages : [];
  const answerOverlay = document.getElementById('answerOverlay');
  const answerCard = document.getElementById('answerCard');
  const answerIcon = document.getElementById('answerIcon');
  const answerText = document.getElementById('answerText');
  let answerPopupTimer = null;
  let answerPopupResolve = null;
  const btnMute = document.getElementById('btnMute');
  const muteLabel = document.getElementById('muteLabel');

  function updateMuteUI(){
    if (!btnMute || !muteLabel) return;
    btnMute.setAttribute('aria-pressed', soundEnabled ? 'false' : 'true');
    btnMute.classList.toggle('is-muted', !soundEnabled);
    muteLabel.textContent = soundEnabled ? tjs('sound_on', 'Sound On') : tjs('sound_off', 'Sound Off');
  }

  let audioCtx = null;
  let audioUnlocked = false;
  const SOUND_KEY = 'pm-sound-enabled';
  let soundEnabled = true;
  try {
    const storedSound = localStorage.getItem(SOUND_KEY);
    if (storedSound === '0') soundEnabled = false;
  } catch (e) {}
  function ensureAudio(){
    if (audioUnlocked || !soundEnabled) return;
    const Ctx = window.AudioContext || window.webkitAudioContext;
    if (!Ctx) return;
    try {
      audioCtx = audioCtx || new Ctx();
      if (audioCtx.state === 'suspended') audioCtx.resume().catch(() => {});
      audioUnlocked = true;
    } catch (e) {}
  }
  function playTone(freq, duration = 0.12, type = 'sine', gain = 0.08, attack = 0.01, decay = 0.08){
    if (!audioCtx) return;
    const osc = audioCtx.createOscillator();
    const g = audioCtx.createGain();
    osc.type = type;
    osc.frequency.value = freq;
    const now = audioCtx.currentTime;
    g.gain.setValueAtTime(0.0001, now);
    g.gain.exponentialRampToValueAtTime(gain, now + attack);
    g.gain.exponentialRampToValueAtTime(0.0001, now + attack + decay);
    osc.connect(g);
    g.connect(audioCtx.destination);
    osc.start(now);
    osc.stop(now + duration);
  }
  function playSound(name){
    if (!soundEnabled || !audioCtx) return;
    switch (name){
      case 'start':
        playTone(440, 0.12, 'sine', 0.1);
        playTone(660, 0.12, 'sine', 0.08);
        break;
      case 'countdown':
        playTone(520, 0.08, 'square', 0.05);
        break;
      case 'countdown_last':
        playTone(760, 0.12, 'square', 0.06);
        break;
      case 'correct':
        playTone(660, 0.1, 'triangle', 0.08);
        playTone(880, 0.12, 'triangle', 0.06);
        break;
      case 'wrong':
        playTone(220, 0.18, 'sawtooth', 0.07);
        break;
      case 'finish':
        playTone(523.25, 0.12, 'sine', 0.08);
        playTone(659.25, 0.12, 'sine', 0.08);
        playTone(783.99, 0.14, 'sine', 0.07);
        break;
      default:
        break;
    }
  }

  function pickRandomMessage(list){
    if (!list || !list.length) return '';
    const idx = Math.floor(Math.random() * list.length);
    return list[idx] || '';
  }

  function showAnswerPopup(type, customText = ''){
    if (!answerOverlay || !answerCard || !answerIcon || !answerText) return Promise.resolve();
    if (answerPopupTimer) clearTimeout(answerPopupTimer);
    if (answerPopupResolve) {
      answerPopupResolve();
      answerPopupResolve = null;
    }

    const isRight = type === 'right';
    const msg = customText || pickRandomMessage(isRight ? RIGHT_MESSAGES : WRONG_MESSAGES) || (isRight ? tjs('toast_correct', '✅ Perfect Match!') : tjs('toast_wrong', '❌ Wrong Match!'));
    answerText.textContent = msg;
    answerIcon.src = isRight ? 'success-checkmark.svg' : 'error-x.svg';
    answerCard.classList.toggle('success', isRight);
    answerCard.classList.toggle('error', !isRight);
    answerOverlay.hidden = false;

    return new Promise((resolve) => {
      answerPopupResolve = resolve;
      answerPopupTimer = setTimeout(() => {
        answerOverlay.hidden = true;
        answerPopupResolve && answerPopupResolve();
        answerPopupResolve = null;
      }, 1500);
    });
  }

  const IS_LOGGED_IN = <?= $userEmail ? 'true' : 'false' ?>;
  const IS_DAILY_MODE = <?= $isDailyMode ? 'true' : 'false' ?>;
  const DAILY_UTC_DATE = <?= json_encode($dailyDateUtc) ?>;
  const FLASH_MSG = <?= json_encode($flash ?? "") ?>;
  const INITIAL_MODE = <?= json_encode($initialMode) ?>;
  const ALL_FLAGS = <?= json_encode($allFlags) ?>;

  const PALETTE = [
    "#000000","#FFFFFF","#FF0000","#00FF00","#0000FF","#FFFF00","#00FFFF","#FF00FF",
    "#800000","#008000","#000080","#808000","#008080","#800080","#C0C0C0","#808080",
    "#9999FF","#993366","#FFFFCC","#CCFFFF","#660066","#FF8080","#0066CC","#CCCCFF",
    "#000080","#FF00FF","#FFFF00","#00FFFF","#800080","#800000","#008080","#0000FF",
    "#00CCFF","#CCFFFF","#CCFFCC","#FFFF99","#99CCFF","#FF99CC","#CC99FF","#FFCC99",
    "#3366FF","#33CCCC","#99CC00","#FFCC00","#FF9900","#FF6600","#666699","#969696",
    "#003366","#339966","#003300","#333300","#993300","#993366","#333399","#333333",
    "#1ABC9C","#2ECC71","#3498DB","#9B59B6","#E67E22","#E74C3C","#F1C40F","#95A5A6",
    "#16A085","#27AE60","#2980B9","#8E44AD","#D35400","#C0392B","#F39C12","#7F8C8D",
    "#2C3E50","#E67E22","#E74C3C","#ECF0F1","#BDC3C7","#95A5A6","#7F8C8D","#34495E",
    "#FFADAD","#FFD6A5","#FDFFB6","#CAFFBF","#9BF6FF","#A0C4FF","#BDB2FF","#FFC6FF",
    "#E0E0E0","#F5F5F5","#FAFAFA","#D1D5DB","#9CA3AF","#6B7280","#4B5563","#374151",
    "#0F172A","#1E293B","#334155","#475569","#1E1B4B","#312E81","#3730A3","#4338CA",
    "#064E3B","#065F46","#047857","#059669","#7C2D12","#9A3412","#B45309","#D97706",
    "#57E32C","#00FF7F","#10B981","#3B82F6","#6366F1","#8B5CF6","#D946EF","#F43F5E",
    "#FB7185","#FDA4AF","#FFF1F2","#FFF7ED","#FEF3C7","#ECFCCB","#D1FAE5","#E0F2FE"
  ];

  const COUNTDOWN_START = 3;
  const START_TARGET_SHOW_MS = 3000;
  const ANSWER_WINDOW_MS = 5000;
  const SHOW_DECAY_FACTOR = 0.9;
  const MIN_TARGET_SHOW_MS = 250;
  const DEFAULT_GRID_COUNT = 9;
  const TOAST_HIDE_MS = 600;

  function getMaxRoundsForMode(mode){
    return (mode === 'points' || mode === 'flags') ? 25 : 50;
  }

  function targetShowMsForLevel(level, mode){
    const maxLvl = getMaxRoundsForMode(mode);
    const lvl = Math.max(1, Math.min(maxLvl, level));
    if (lvl === 21 || lvl === 41) return 5000;
    const ms = Math.floor(START_TARGET_SHOW_MS * Math.pow(SHOW_DECAY_FACTOR, lvl - 1));
    return Math.max(MIN_TARGET_SHOW_MS, ms);
  }

  function gridCountForLevel(level, mode){
    if (mode === 'points' || mode === 'flags') {
      if (level <= 10) return 9;
      if (level <= 20) return 16;
      return 25;
    }
    if (level <= 20) return 9;
    if (level <= 40) return 16;
    return 25;
  }

  // Preloader & Cache for Flag Images
  const flagImageCache = new Map();
  function preloadFlagImage(src){
    if (!src) return Promise.resolve(src);
    if (flagImageCache.has(src)) return flagImageCache.get(src);
    const p = new Promise((resolve) => {
      const img = new Image();
      img.onload = () => {
        if (typeof img.decode === 'function') {
          img.decode().then(() => resolve(src)).catch(() => resolve(src));
        } else {
          resolve(src);
        }
      };
      img.onerror = () => resolve(src);
      img.src = src;
    });
    flagImageCache.set(src, p);
    return p;
  }
  function preloadFlagImages(urls){
    if (!Array.isArray(urls) || urls.length === 0) return Promise.resolve([]);
    return Promise.all(urls.map(u => preloadFlagImage(u)));
  }

  function computeScoreFromPayload(payload){
    const mode = payload.game_mode || payload.gameMode || state.gameMode || 'elimination';
    if (mode === 'points' || mode === 'flags') {
      return state.score;
    }

    const reached = Math.max(1, Math.min(50, (payload.reachedLevel || 1)));
    const rounds = Array.isArray(payload.rounds) ? payload.rounds : [];
    let sumRatio = 0;
    let count = 0;

    for (const r of rounds){
      if (!r || typeof r !== 'object') continue;
      const level = Math.max(1, Math.min(50, r.level || 0));
      if (!r.isCorrect) continue;
      const responseMs = Math.max(1, (r.responseMs || 0));
      const showMs = targetShowMsForLevel(level, mode);
      let ratio = showMs / responseMs;
      if (ratio < 0) ratio = 0;
      if (ratio > 1.5) ratio = 1.5;
      sumRatio += ratio;
      count++;
    }

    const avgRatio = count > 0 ? (sumRatio / count) : 0;
    const timeFactor = Math.min(1.0, avgRatio / 1.5);
    const levelFactor = reached / 50;
    let score = Math.round(1000 * ((0.7 * levelFactor) + (0.3 * timeFactor)));
    if (score < 0) score = 0;
    if (score > 1000) score = 1000;
    return score;
  }

  // DOM Elements
  const elCenter = document.getElementById("center");
  const elToast = document.getElementById("toast");
  const elBadge = document.getElementById("statusBadge");

  const hudLevel = document.getElementById("hudLevel");
  const hudTime  = document.getElementById("hudTime");
  const hudCorrect = document.getElementById("hudCorrect");
  const hudShow = document.getElementById("hudShow");
  const chipScore = document.getElementById("chipScore");
  const hudScore = document.getElementById("hudScore");

  const btnStart = document.getElementById("btnStart");
  const dailyNotice = document.getElementById("dailyNotice");

  const modeCardElim = document.getElementById("modeCardElim");
  const modeCardPoints = document.getElementById("modeCardPoints");
  const modeCardFlags = document.getElementById("modeCardFlags");

  const playedBadgeElim = document.getElementById("playedBadgeElim");
  const playedBadgePoints = document.getElementById("playedBadgePoints");
  const playedBadgeFlags = document.getElementById("playedBadgeFlags");

  const dailyModal = document.getElementById("dailyCompletedModal");
  const dailyModalText = document.getElementById("dailyCompletedText");
  const dailyModalOk = document.getElementById("dailyCompletedOk");
  const dailyCompletedLeaderboardBtn = document.getElementById("dailyCompletedLeaderboardBtn");
  const dailyLoginModal = document.getElementById("dailyLoginModal");
  const dailyLoginBack = document.getElementById("dailyLoginBack");

  // State
  const state = {
    gameMode: INITIAL_MODE,
    level: 1,
    correct: 0,
    score: 0,
    targetColor: null,
    targetShowMs: START_TARGET_SHOW_MS,
    answerMs: ANSWER_WINDOW_MS,
    isLocked: false,
    phase: "idle",
    timers: { timeoutIds: new Set(), intervalIds: new Set() },
    dailyLocked: false,
    dailyLoginShown: false,
    gridCount: DEFAULT_GRID_COUNT,
    dailyModesStatus: { elimination: false, points: false, flags: false },

    // telemetry
    gameStartedAt: null,
    rounds: [],
    roundQuestionStartTs: 0,
    currentGridColors: [],
  };

  function setSafeTimeout(fn, ms){
    const id = setTimeout(() => { state.timers.timeoutIds.delete(id); fn(); }, ms);
    state.timers.timeoutIds.add(id);
    return id;
  }
  function setSafeInterval(fn, ms){
    const id = setInterval(fn, ms);
    state.timers.intervalIds.add(id);
    return id;
  }
  function clearAllTimers(){
    for (const id of state.timers.timeoutIds) clearTimeout(id);
    for (const id of state.timers.intervalIds) clearInterval(id);
    state.timers.timeoutIds.clear();
    state.timers.intervalIds.clear();
  }

  function updateHUD(answerLeftMs = null){
    const maxLvl = getMaxRoundsForMode(state.gameMode);
    hudLevel.textContent = `${state.level} / ${maxLvl}`;
    hudCorrect.textContent = String(state.correct);
    hudShow.textContent = `${(state.targetShowMs / 1000).toFixed(2)}s`;

    if (state.gameMode === 'points' || state.gameMode === 'flags') {
      chipScore.style.display = 'flex';
      hudScore.textContent = String(state.score);
    } else {
      chipScore.style.display = 'none';
    }

    const shown = (answerLeftMs == null ? state.answerMs : Math.max(0, answerLeftMs));
    hudTime.textContent = `${(shown / 1000).toFixed(1)}s`;
  }

  function setBadge(text, type = ''){
    elBadge.textContent = text;
    elBadge.classList.remove('pm-error', 'pm-success');
    if (type) elBadge.classList.add(`pm-${type}`);
  }

  function toast(msg, autoHideMs = 1100){
    if (!msg) {
      elToast.textContent = "";
      elToast.classList.remove("show");
      return;
    }
    elToast.textContent = msg;
    elToast.classList.add("show");
    setSafeTimeout(() => elToast.classList.remove("show"), autoHideMs);
  }
  function toastQuick(msg){ toast(msg, TOAST_HIDE_MS); }

  function render(node){
    elCenter.innerHTML = "";
    elCenter.appendChild(node);
  }

  function makeStack(...nodes){
    const wrap = document.createElement("div");
    wrap.className = "center";
    nodes.forEach(n => wrap.appendChild(n));
    return wrap;
  }
  function h1(text){
    const d = document.createElement("div");
    d.className = "title";
    d.textContent = text;
    return d;
  }
  function p(text){
    const d = document.createElement("div");
    d.className = "subtitle";
    d.textContent = text;
    return d;
  }

  function randInt(min, maxExclusive){
    return Math.floor(Math.random() * (maxExclusive - min)) + min;
  }
  function shuffle(arr){
    const copy = arr.slice();
    for (let i = copy.length - 1; i > 0; i--){
      const j = randInt(0, i + 1);
      [copy[i], copy[j]] = [copy[j], copy[i]];
    }
    return copy;
  }

  function pickUniqueColors(count, excludeSet = new Set()){
    const pool = PALETTE.filter(c => !excludeSet.has(c));
    if (count > pool.length) throw new Error(tjs('err_palette', 'Insufficient item pool.'));
    const tmp = pool.slice();
    for (let i = tmp.length - 1; i > 0; i--){
      const j = randInt(0, i + 1);
      [tmp[i], tmp[j]] = [tmp[j], tmp[i]];
    }
    return tmp.slice(0, count);
  }

  function pickUniqueFlags(count, excludeSet = new Set()){
    const pool = ALL_FLAGS.filter(f => !excludeSet.has(f));
    if (count > pool.length) throw new Error(tjs('err_palette', 'Insufficient flag pool.'));
    return shuffle(pool).slice(0, count);
  }

  function updateLocalBestUI(){
    const badge = document.getElementById('localBestBadge');
    const val = document.getElementById('localBestVal');
    if (!badge || !val || !window.PrismatchOfflineStore) return;
    const best = window.PrismatchOfflineStore.getLocalBest(state.gameMode);
    if (best && (best.score > 0 || best.level > 0)) {
      badge.style.display = 'inline-flex';
      val.textContent = (state.gameMode === 'elimination') ? `Level ${best.level}` : `${best.score} pts`;
    } else {
      badge.style.display = 'none';
    }
  }

  function setMode(mode){
    if (state.phase !== 'idle' && state.phase !== 'finished' && state.phase !== 'gameover' && state.phase !== 'win') {
      return;
    }
    state.gameMode = mode;
    [modeCardElim, modeCardPoints, modeCardFlags].forEach(c => c?.classList.remove('active'));
    if (mode === 'elimination') modeCardElim?.classList.add('active');
    if (mode === 'points') modeCardPoints?.classList.add('active');
    if (mode === 'flags') modeCardFlags?.classList.add('active');

    // Update URL without reload
    try {
      const url = new URL(window.location);
      url.searchParams.set('mode', mode);
      window.history.replaceState({}, '', url);
    } catch(e) {}

    checkModeDailyStatus();
    updateLocalBestUI();
    updateHUD(ANSWER_WINDOW_MS);
  }

  function checkModeDailyStatus(){
    if (!IS_DAILY_MODE) return;
    const isPlayed = !!state.dailyModesStatus[state.gameMode];
    if (isPlayed) {
      if (btnStart) btnStart.disabled = true;
      if (dailyNotice) {
        dailyNotice.textContent = tjs('daily_completed', 'You already played this mode today. Come back tomorrow or try another mode!');
        dailyNotice.style.display = 'block';
      }
    } else {
      if (btnStart) btnStart.disabled = false;
      if (dailyNotice) {
        dailyNotice.style.display = 'none';
      }
    }
  }

  modeCardElim?.addEventListener('click', () => setMode('elimination'));
  modeCardPoints?.addEventListener('click', () => setMode('points'));
  modeCardFlags?.addEventListener('click', () => setMode('flags'));

  async function storePendingAndLogin(payload){
    await fetch("api/store_pending.php", {
      method: "POST",
      headers: {"Content-Type":"application/json"},
      body: JSON.stringify(payload),
      credentials: "same-origin"
    });
    window.location.href = "login.php";
  }

  function showSavePromptModal(payload){
    const existing = document.getElementById("saveLoginModal");
    if (existing) existing.remove();

    const overlay = document.createElement("div");
    overlay.id = "saveLoginModal";
    overlay.className = "modalOverlay";
    overlay.tabIndex = -1;

    const modal = document.createElement("div");
    modal.className = "modalCard";
    modal.setAttribute("role", "dialog");
    modal.setAttribute("aria-modal", "true");
    modal.setAttribute("aria-label", tjs('save_after_title', 'Save your score?'));

    const title = document.createElement("h2");
    title.className = "modalTitle";
    title.textContent = tjs('save_after_title', 'Save your score?');

    const googleBadgeHtml = <?= json_encode(google_badge_html()) ?>;
    const msg = document.createElement("p");
    msg.className = "modalText";
    msg.innerHTML = tjs('save_after_body', 'Log in with {google} to save this session and view detailed stats.').replace('{google}', googleBadgeHtml);

    const actions = document.createElement("div");
    actions.className = "modalActions";

    const btnSave = document.createElement("button");
    btnSave.className = "btn primary";
    btnSave.type = "button";
    btnSave.innerHTML = '<img class="bi-icon" src="bootstrap-icons/cloud-arrow-up.svg" alt="" aria-hidden="true" /> ' +
      tjs('save_with_google', 'Save with {google}').replace('{google}', googleBadgeHtml);
    btnSave.addEventListener("click", () => storePendingAndLogin(payload));

    const btnSkip = document.createElement("button");
    btnSkip.className = "btn";
    btnSkip.type = "button";
    btnSkip.innerHTML = '<img class="bi-icon" src="bootstrap-icons/arrow-right.svg" alt="" aria-hidden="true" /> ' + tjs('continue_without_saving', 'Continue without saving');
    btnSkip.addEventListener("click", () => overlay.remove());

    actions.appendChild(btnSave);
    actions.appendChild(btnSkip);

    modal.appendChild(title);
    modal.appendChild(msg);
    modal.appendChild(actions);
    overlay.appendChild(modal);

    overlay.addEventListener("click", (e) => {
      if (e.target === overlay) overlay.remove();
    });

    document.addEventListener("keydown", function escOnce(e){
      if (e.key === "Escape") {
        overlay.remove();
        document.removeEventListener("keydown", escOnce);
      }
    });

    document.body.appendChild(overlay);
    setTimeout(() => { try { btnSave.focus(); } catch(e) {} }, 0);
  }

  async function postResultIfLoggedIn(payload){
    if (!IS_LOGGED_IN){
      return { ok: false, reason: 'not_logged_in' };
    }
    if (!navigator.onLine) {
      return { ok: false, reason: 'network' };
    }
    const endpoint = IS_DAILY_MODE ? "api/daily_record.php" : "api/record.php";
    try {
      const res = await fetch(endpoint, {
        method: "POST",
        headers: {"Content-Type":"application/json"},
        body: JSON.stringify(payload),
        credentials: "same-origin"
      });

      if (!IS_DAILY_MODE) return { ok: res.ok, reason: res.ok ? 'ok' : 'http' };

      let j = null;
      try { j = await res.json(); } catch(e) {}

      if (!res.ok || !j || !j.ok){
        if (j && j.error === 'already_played'){
          state.dailyModesStatus[state.gameMode] = true;
          if (window.PrismatchOfflineStore) {
            window.PrismatchOfflineStore.setDailyPlayed(DAILY_UTC_DATE, state.gameMode);
          }
          lockDailyAlreadyPlayed();
          return { ok: false, reason: 'already_played' };
        }
        return { ok: false, reason: 'http' };
      }

      state.dailyModesStatus[state.gameMode] = true;
      if (window.PrismatchOfflineStore) {
        window.PrismatchOfflineStore.setDailyPlayed(DAILY_UTC_DATE, state.gameMode);
      }
      checkModeDailyStatus();
      return { ok: true, reason: 'ok' };
    } catch (e) {
      return { ok: false, reason: 'network' };
    }
  }

  async function storePendingSilently(payload){
    try {
      await fetch("api/store_pending.php", {
        method: "POST",
        headers: {"Content-Type":"application/json"},
        body: JSON.stringify(payload),
        credentials: "same-origin"
      });
      return true;
    } catch (e) {
      return false;
    }
  }

  async function postResultOrPrompt(payload){
    // Update local personal best in client-side storage
    try {
      if (window.PrismatchOfflineStore) {
        window.PrismatchOfflineStore.saveLocalBest(state.gameMode, payload.score || 0, payload);
        updateLocalBestUI();
      }
    } catch(e) {}

    if (IS_DAILY_MODE){
      if (!IS_LOGGED_IN){
        lockDaily(tjs('daily_login_required', 'Log in to play the daily challenge.'));
        return false;
      }
      const res = await postResultIfLoggedIn(payload);
      if (res.ok) {
        return true;
      }
      // If offline or network error, queue offline and mark played
      if (res.reason === 'network' || !navigator.onLine) {
        if (window.PrismatchOfflineStore) {
          await window.PrismatchOfflineStore.queueScore(payload, true);
          window.PrismatchOfflineStore.setDailyPlayed(DAILY_UTC_DATE, state.gameMode);
          state.dailyModesStatus[state.gameMode] = true;
          checkModeDailyStatus();
          toastQuick(tjs('save_offline_queued', 'Skor cihazınıza kaydedildi. Bağlantı kurulduğunda sunucuyla eşitlenecektir.'));
        }
        return false;
      }
      return false;
    }

    const res = await postResultIfLoggedIn(payload);
    if (res.ok) return true;

    if (IS_LOGGED_IN){
      if (!navigator.onLine || res.reason === 'network') {
        if (window.PrismatchOfflineStore) {
          await window.PrismatchOfflineStore.queueScore(payload, false);
          toastQuick(tjs('save_offline_queued', 'Skor cihazınıza kaydedildi. Bağlantı kurulduğunda sunucuyla eşitlenecektir.'));
          return false;
        }
      }
      const queued = await storePendingSilently(payload);
      if (queued) toastQuick(tjs('save_pending', 'Save queued. It will sync on next load.'));
      return false;
    }

    if (!navigator.onLine) {
      if (window.PrismatchOfflineStore) {
        await window.PrismatchOfflineStore.queueScore(payload, false);
        toastQuick(tjs('save_offline_queued', 'Skor cihazınıza kaydedildi.'));
        return false;
      }
    }

    showSavePromptModal(payload);
    return false;
  }

  function lockDaily(message, showModal = false){
    state.dailyLocked = true;
    if (btnStart) btnStart.disabled = true;
    if (message){
      try { toastQuick(message); } catch(e) {}
      setBadge(message, 'error');
    }
    if (showModal) showDailyCompletedModal(message || tjs('daily_completed', 'You already played today. Come back tomorrow!'));
  }

  function lockDailyAlreadyPlayed(){
    lockDaily(tjs('daily_completed', 'You already played today. Come back tomorrow!'), true);
  }

  function showDailyCompletedModal(message){
    if (!dailyModal) return;
    if (dailyModalText && message) dailyModalText.textContent = message;
    if (dailyCompletedLeaderboardBtn) {
      dailyCompletedLeaderboardBtn.href = `daily_leaderboard.php?mode=${encodeURIComponent(state.gameMode)}`;
    }
    dailyModal.hidden = false;
    setTimeout(() => { try { dailyModalOk?.focus(); } catch(e) {} }, 0);
  }

  function showDailyLoginModal(){
    if (state.dailyLoginShown) return;
    if (!dailyLoginModal) return;
    state.dailyLoginShown = true;
    dailyLoginModal.hidden = false;
    setTimeout(() => { try { dailyLoginBack?.focus(); } catch(e) {} }, 0);
  }

  async function checkDailyStatus(){
    if (!IS_DAILY_MODE) return;
    if (!IS_LOGGED_IN){
      lockDaily(tjs('daily_login_required', 'Log in to play the daily challenge.'));
      showDailyLoginModal();
      return;
    }

    // Check client offline store first
    if (window.PrismatchOfflineStore && window.PrismatchOfflineStore.isDailyPlayed(DAILY_UTC_DATE, state.gameMode)) {
      state.dailyModesStatus[state.gameMode] = true;
      checkModeDailyStatus();
      if (state.dailyModesStatus[state.gameMode]) {
        lockDailyAlreadyPlayed();
      }
    }

    if (!navigator.onLine) return;

    try{
      const r = await fetch(`api/daily_status.php?day=${encodeURIComponent(DAILY_UTC_DATE)}&mode=${encodeURIComponent(state.gameMode)}`, {
        credentials: "same-origin"
      });
      const j = await r.json();
      if (j.ok && j.modes){
        state.dailyModesStatus = j.modes;
        if (window.PrismatchOfflineStore) {
          Object.keys(j.modes).forEach(m => {
            if (j.modes[m]) window.PrismatchOfflineStore.setDailyPlayed(DAILY_UTC_DATE, m);
          });
        }
        if (playedBadgeElim) playedBadgeElim.style.display = j.modes.elimination ? 'inline-flex' : 'none';
        if (playedBadgePoints) playedBadgePoints.style.display = j.modes.points ? 'inline-flex' : 'none';
        if (playedBadgeFlags) playedBadgeFlags.style.display = j.modes.flags ? 'inline-flex' : 'none';
        checkModeDailyStatus();
      }
    }catch(e){}
  }

  function buildGamePayload(reachedLevel, won){
    const endedAt = Date.now();
    const startedAt = state.gameStartedAt ?? endedAt;

    const payload = {
      reachedLevel,
      reached_level: reachedLevel,
      correct: state.correct,
      total_correct: state.correct,
      won,
      startedAt: new Date(startedAt).toISOString(),
      created_at: new Date(startedAt).toISOString(),
      endedAt: new Date(endedAt).toISOString(),
      finished_at: new Date(endedAt).toISOString(),
      durationMs: Math.max(0, endedAt - startedAt),
      duration_ms: Math.max(0, endedAt - startedAt),
      rounds: state.rounds.slice(),
      gridCount: state.gridCount,
      game_mode: state.gameMode,
      gameMode: state.gameMode,
    };
    payload.score = computeScoreFromPayload(payload);
    return payload;
  }

  function startGame(){
    ensureAudio();
    playSound('start');
    if (IS_DAILY_MODE && state.dailyModesStatus[state.gameMode]) {
      showDailyCompletedModal(tjs('daily_completed', 'You already played this mode today. Come back tomorrow or try another mode!'));
      return;
    }
    if (IS_DAILY_MODE && !IS_LOGGED_IN){
      lockDaily(tjs('daily_login_required', 'Log in to play the daily challenge.'));
      showDailyLoginModal();
      return;
    }
    clearAllTimers();

    state.level = 1;
    state.correct = 0;
    state.score = 0;
    state.targetShowMs = START_TARGET_SHOW_MS;
    state.targetColor = null;
    state.isLocked = false;
    state.phase = "idle";
    state.gridCount = gridCountForLevel(1, state.gameMode);

    // telemetry reset
    state.gameStartedAt = Date.now();
    state.rounds = [];
    state.roundQuestionStartTs = 0;
    state.currentGridColors = [];

    updateHUD(state.answerMs);
    setBadge(tjs('badge_ready', 'Ready. Countdown…'));
    runCountdown();
  }

  function runCountdown(){
    clearAllTimers();
    state.phase = "countdown";
    state.isLocked = true;

    let t = COUNTDOWN_START;

    const cd = document.createElement("div");
    cd.className = "countdown";
    cd.textContent = String(t);

    const helpText = state.gameMode === 'flags'
      ? tjs('remember_flag', 'Remember this flag!')
      : tjs('countdown_help', 'Remember the shown color, then pick it from the grid.');

    render(makeStack(
      h1(tjs('app_name', 'Prismatch')),
      p(helpText),
      cd
    ));

    updateHUD(state.answerMs);

    const interval = setSafeInterval(() => {
      t -= 1;
      if (t <= 0){
        clearInterval(interval);
        state.timers.intervalIds.delete(interval);
        runTargetShow();
        return;
      }
      playSound(t === 1 ? 'countdown_last' : 'countdown');
      cd.textContent = String(t);
      cd.style.animation = "none";
      void cd.offsetHeight;
      cd.style.animation = "";
    }, 1000);
  }

  async function runTargetShow(){
    clearAllTimers();
    state.phase = "showTarget";
    state.isLocked = true;

    const maxLvl = getMaxRoundsForMode(state.gameMode);

    if (state.gameMode === 'flags') {
      state.targetColor = ALL_FLAGS[randInt(0, ALL_FLAGS.length)];
      await preloadFlagImage(state.targetColor);

      const card = document.createElement("div");
      card.className = "target-card target-box-flag";
      card.innerHTML = `<img src="${state.targetColor}" class="target-flag-img" alt="Target Flag" loading="eager">`;

      render(makeStack(
        h1(`${tjs('hud_stage', 'Stage')} ${state.level} / ${maxLvl}`),
        p(tjs('remember_flag', 'Remember this flag!')),
        card
      ));
      setBadge(tjs('badge_showing_target_flag', 'Showing target flag...'));
    } else {
      state.targetColor = PALETTE[randInt(0, PALETTE.length)];

      const card = document.createElement("div");
      card.className = "target-card";
      card.style.background = state.targetColor;

      render(makeStack(
        h1(`${tjs('hud_stage', 'Stage')} ${state.level} / ${maxLvl}`),
        p(tjs('remember_this', 'Remember this color.')),
        card
      ));
      setBadge(tjs('badge_showing_target', 'Showing target…'));
    }

    updateHUD(state.answerMs);
    setSafeTimeout(() => runQuestionGrid(), state.targetShowMs);
  }

  async function runQuestionGrid(){
    clearAllTimers();
    state.phase = "question";
    state.isLocked = false;

    const gridCount = gridCountForLevel(state.level, state.gameMode);
    const exclude = new Set([state.targetColor]);
    let items = [];

    if (state.gameMode === 'flags') {
      const others = pickUniqueFlags(gridCount - 1, exclude);
      items = shuffle([state.targetColor, ...others]);
      await preloadFlagImages(items);
    } else {
      const others = pickUniqueColors(gridCount - 1, exclude);
      items = shuffle([state.targetColor, ...others]);
    }

    state.roundQuestionStartTs = performance.now();
    state.currentGridColors = items.slice();

    const qWrap = document.createElement("div");
    qWrap.className = "question";

    const q = document.createElement("div");
    q.className = "q";
    q.textContent = state.gameMode === 'flags'
      ? tjs('question_pick_target_flag', 'Which flag was shown? Pick the target.')
      : tjs('question_pick_target', 'Which color was shown? Pick the target.');
    const hint = document.createElement("div");
    hint.className = "hint";
    hint.textContent = tjs('question_hint', 'Use Tab/Shift+Tab and Enter/Space to pick.');
    qWrap.appendChild(q);
    qWrap.appendChild(hint);

    const grid = document.createElement("div");
    grid.className = "grid";
    if (state.gameMode === 'flags') grid.classList.add('grid-ready');

    const gridSize = Math.round(Math.sqrt(gridCount));
    if (gridSize * gridSize === gridCount) {
      grid.style.gridTemplateColumns = `repeat(${gridSize}, 1fr)`;
      if (gridSize >= 4) grid.style.gap = gridSize >= 5 ? '6px' : '8px';
    }

    const buttons = [];

    items.forEach((c, idx) => {
      const btn = document.createElement("button");
      btn.className = "cell";
      btn.type = "button";

      if (state.gameMode === 'flags') {
        btn.classList.add("choice-cell-flag");
        btn.innerHTML = `<img src="${c}" class="choice-flag-img" alt="Flag" loading="eager">`;
        btn.setAttribute("aria-label", tf('a11y_flag_option', {n: idx + 1}, `Flag option ${idx + 1}`));
      } else {
        btn.style.background = c;
        btn.setAttribute("aria-label", tf('a11y_color_option', {n: idx + 1}, `Color option ${idx + 1}`));
      }

      btn.dataset.item = c;

      btn.addEventListener("click", () => onPick(btn, buttons));
      btn.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          btn.click();
        }
      });

      buttons.push(btn);
      grid.appendChild(btn);
    });

    const maxLvl = getMaxRoundsForMode(state.gameMode);
    render(makeStack(
      h1(`${tjs('hud_stage', 'Stage')} ${state.level} / ${maxLvl}`),
      qWrap,
      grid
    ));

    setBadge(state.gameMode === 'flags' ? tjs('badge_pick_flag', 'PICK FLAG!') : tjs('badge_answer', 'Answer now!'));
    toast(tjs('toast_pick', '⏱️ Pick within 5 seconds'));

    let remaining = state.answerMs;
    updateHUD(remaining);

    const interval = setSafeInterval(() => {
      remaining -= 100;
      updateHUD(remaining);
      if (remaining <= 0){
        clearInterval(interval);
        state.timers.intervalIds.delete(interval);
        onTimeUp(buttons);
      }
    }, 100);

    setSafeTimeout(() => {
      if (state.phase === "question" && !state.isLocked){
        onTimeUp(buttons);
      }
    }, state.answerMs);

    setSafeTimeout(() => { if (buttons[0]) buttons[0].focus(); }, 0);
  }

  function lockButtons(buttons, locked = true){
    buttons.forEach(b => b.disabled = locked);
  }

  async function onPick(btn, buttons){
    if (state.phase !== "question" || state.isLocked) return;

    state.isLocked = true;
    lockButtons(buttons, true);

    const responseMs = Math.max(0, Math.round(performance.now() - state.roundQuestionStartTs));
    const picked = btn.dataset.item;
    const isCorrect = picked === state.targetColor;

    // Telemetry
    state.rounds.push({
      level: state.level,
      targetColor: state.targetColor,
      target_color: state.targetColor,
      gridColors: state.currentGridColors.slice(),
      grid_colors: state.currentGridColors.slice(),
      pickedColor: picked,
      picked_color: picked,
      responseMs,
      response_ms: responseMs,
      isCorrect,
      is_correct: isCorrect ? 1 : 0
    });

    const isNonElim = (state.gameMode === 'points' || state.gameMode === 'flags');
    const maxLvl = getMaxRoundsForMode(state.gameMode);

    if (isNonElim) {
      if (isCorrect){
        btn.classList.add("correct");
        const scoreDelta = Math.max(50, 1000 - Math.floor(responseMs / 10));
        state.score += scoreDelta;
        state.correct += 1;
        playSound('correct');
        toastQuick(`+${scoreDelta} pts`);
        await showAnswerPopup('right', `+${scoreDelta} pts`);
      } else {
        btn.classList.add("wrong");
        const penalty = Math.max(50, 1000 - Math.floor(responseMs / 10));
        state.score = Math.max(0, state.score - penalty);
        playSound('wrong');
        toastQuick(`-${penalty} pts`);
        await showAnswerPopup('wrong', `-${penalty} pts`);
      }
      updateHUD();
      if (state.level >= maxLvl) {
        win();
      } else {
        advanceLevel();
      }
    } else {
      // Classic Elimination Mode
      if (isCorrect){
        btn.classList.add("correct");
        setBadge(tjs('badge_correct', 'Perfect match! Next stage…'));
        state.correct += 1;
        playSound('correct');
        await showAnswerPopup('right');
        advanceLevel();
      } else {
        btn.classList.add("wrong");
        setBadge(tjs('badge_wrong', 'Wrong match. Game over.'));
        playSound('wrong');
        await showAnswerPopup('wrong');
        gameOver(tjs('reason_wrong', 'Wrong match.'));
      }
    }
  }

  async function onTimeUp(buttons){
    if (state.phase !== "question" || state.isLocked) return;

    state.isLocked = true;
    lockButtons(buttons, true);

    state.rounds.push({
      level: state.level,
      targetColor: state.targetColor,
      target_color: state.targetColor,
      gridColors: state.currentGridColors.slice(),
      grid_colors: state.currentGridColors.slice(),
      pickedColor: null,
      picked_color: null,
      responseMs: state.answerMs,
      response_ms: state.answerMs,
      isCorrect: false,
      is_correct: 0
    });

    const isNonElim = (state.gameMode === 'points' || state.gameMode === 'flags');
    const maxLvl = getMaxRoundsForMode(state.gameMode);

    if (isNonElim) {
      playSound('wrong');
      toastQuick(tjs('toast_timeup_no_points', '0 pts (Time Up)'));
      await showAnswerPopup('wrong', '0 pts');
      updateHUD();
      if (state.level >= maxLvl) {
        win();
      } else {
        advanceLevel();
      }
    } else {
      setBadge(tjs('badge_wrong', 'Wrong match. Game over.'));
      playSound('wrong');
      await showAnswerPopup('wrong');
      gameOver(tjs('reason_timeup', 'Time’s up.'));
    }
  }

  function advanceLevel(){
    clearAllTimers();
    const maxLvl = getMaxRoundsForMode(state.gameMode);

    if (state.level >= maxLvl){
      win();
      return;
    }

    state.level += 1;
    state.gridCount = gridCountForLevel(state.level, state.gameMode);

    if (state.level === 21 || state.level === 41) {
      state.targetShowMs = 5000;
    } else {
      state.targetShowMs = Math.max(MIN_TARGET_SHOW_MS, Math.floor(state.targetShowMs * SHOW_DECAY_FACTOR));
    }
    updateHUD(state.answerMs);
    runCountdown();
  }

  async function win(){
    clearAllTimers();
    state.phase = "win";
    state.isLocked = true;
    playSound('finish');

    const maxLvl = getMaxRoundsForMode(state.gameMode);
    const payload = buildGamePayload(maxLvl, true);

    let titleText = tjs('win_title', '🏆 You matched them all!');
    let bodyText = tf('win_body', {correct: state.correct, level: maxLvl}, `You reached stage ${maxLvl}. Total perfect matches: ${state.correct}`);

    if (state.gameMode === 'points' || state.gameMode === 'flags') {
      titleText = tjs('points_finish_title', '🎉 Challenge Complete!');
      bodyText = tf('points_finish_body', {score: payload.score, level: maxLvl}, `All ${maxLvl} stages completed! Total score: ${payload.score}`);
    }

    const wrap = makeStack(
      h1(titleText),
      p(bodyText),
      p(`${tjs('th_score', 'Score')}: ${payload.score}`)
    );

    const row = document.createElement("div");
    row.className = "action-row";

    if (!IS_DAILY_MODE){
      const btn = document.createElement("button");
      btn.className = "btn primary";
      btn.type = "button";
      btn.innerHTML = '<img class="bi-icon" src="bootstrap-icons/arrow-repeat.svg" alt="" aria-hidden="true" /> ' + tjs('btn_play_again', 'Play again');
      btn.addEventListener("click", () => {
        window.location.reload();
      });
      row.appendChild(btn);
    } else {
      const btnLb = document.createElement("a");
      btnLb.className = "btn primary";
      btnLb.href = `daily_leaderboard.php?mode=${encodeURIComponent(state.gameMode)}`;
      btnLb.innerHTML = '<img class="bi-icon" src="bootstrap-icons/trophy.svg" alt="" aria-hidden="true" /> ' + tjs('daily_leaderboard_title', 'Daily Leaderboard');
      row.appendChild(btnLb);
    }

    if (IS_LOGGED_IN && !IS_DAILY_MODE){
      const a = document.createElement("a");
      a.className = "btn";
      a.href = "games.php";
      a.innerHTML = '<img class="bi-icon" src="bootstrap-icons/clock-history.svg" alt="" aria-hidden="true" /> ' + tjs('btn_view_history', 'My Sessions');
      row.appendChild(a);
    }

    wrap.appendChild(row);
    render(wrap);

    await postResultOrPrompt(payload);

    setBadge(tjs('status_finished', 'Finished'));
    updateHUD(state.answerMs);
  }

  async function gameOver(reason){
    clearAllTimers();
    state.phase = "gameover";
    state.isLocked = true;
    playSound('finish');

    const payload = buildGamePayload(state.level, false);

    const wrap = makeStack(
      h1(tjs('gameover_title', 'Game over')),
      p(tf('gameover_body', {reason}, `${reason} Tap the button to try again.`)),
      p(`${tjs('th_score', 'Score')}: ${payload.score}`)
    );

    const row = document.createElement("div");
    row.className = "action-row";

    if (!IS_DAILY_MODE){
      const btn = document.createElement("button");
      btn.className = "btn primary";
      btn.type = "button";
      btn.innerHTML = '<img class="bi-icon" src="bootstrap-icons/arrow-clockwise.svg" alt="" aria-hidden="true" /> ' + tjs('btn_restart', 'Restart');
      btn.addEventListener("click", () => {
        window.location.reload();
      });
      row.appendChild(btn);
    } else {
      const btnLb = document.createElement("a");
      btnLb.className = "btn primary";
      btnLb.href = `daily_leaderboard.php?mode=${encodeURIComponent(state.gameMode)}`;
      btnLb.innerHTML = '<img class="bi-icon" src="bootstrap-icons/trophy.svg" alt="" aria-hidden="true" /> ' + tjs('daily_leaderboard_title', 'Daily Leaderboard');
      row.appendChild(btnLb);
    }

    if (IS_LOGGED_IN && !IS_DAILY_MODE){
      const a = document.createElement("a");
      a.className = "btn";
      a.href = "games.php";
      a.innerHTML = '<img class="bi-icon" src="bootstrap-icons/clock-history.svg" alt="" aria-hidden="true" /> ' + tjs('btn_view_history', 'My Sessions');
      row.appendChild(a);
    }

    wrap.appendChild(row);
    render(wrap);

    await postResultOrPrompt(payload);

    updateHUD(state.answerMs);
    setBadge(tjs('status_finished', 'Finished'));
  }

  // Events
  btnStart.addEventListener("click", startGame);
  if (btnMute){
    updateMuteUI();
    btnMute.addEventListener("click", () => {
      soundEnabled = !soundEnabled;
      try { localStorage.setItem(SOUND_KEY, soundEnabled ? '1' : '0'); } catch (e) {}
      if (soundEnabled){
        ensureAudio();
        playSound('start');
      }
      updateMuteUI();
    });
  }

  if (dailyModalOk){
    dailyModalOk.addEventListener("click", () => {
      window.location.href = "index.php";
    });
  }
  if (dailyLoginBack){
    dailyLoginBack.addEventListener("click", () => {
      window.location.href = "index.php";
    });
  }

  // Init
  setMode(INITIAL_MODE);
  checkDailyStatus();
  updateHUD(ANSWER_WINDOW_MS);
  window.addEventListener('pm:offline-ready', () => {
    updateLocalBestUI();
    checkDailyStatus();
  });
  if (FLASH_MSG) toastQuick(FLASH_MSG);
  </script>

  <?php if (is_file(__DIR__ . '/footer.php')) include __DIR__ . '/footer.php'; ?>

</body>
</html>
