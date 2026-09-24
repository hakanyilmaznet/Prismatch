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

// Require user login before playing
if (!$userId || !$userEmail) {
  $next = 'play.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
  header('Location: login.php?next=' . rawurlencode($next));
  exit;
}

$userDisplay = $_SESSION['user_name'] ?? ($userId ? user_display_name($userId) : null);
$isDailyMode = isset($_GET['daily']) && $_GET['daily'] !== '0';
$dailyDateUtc = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d');

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
  ? tt('daily_meta_description', 'Play the daily Prismatch challenge: one attempt per day to test your color memory.')
  : tt('play_meta_description', 'Play Prismatch and test your short-term color memory with fast, progressive rounds.');
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
  <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700;800&family=Baloo+2:wght@500;600;700&display=swap" rel="stylesheet" />
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
    html, body { height: 100%; }
    body{
      margin:0;
      font-family: "Rubik", "Segoe UI", "Helvetica Neue", sans-serif;
      background: var(--bg);
      color: var(--text);
      display:flex;
      align-items:center;
      justify-content:center;
      padding: 18px;
      padding-top: calc(var(--pm-header-offset, 0px) + 18px);
      padding-bottom: calc(var(--pm-footer-offset, 0px) + 18px + env(safe-area-inset-bottom));
    }
    body::before,
    body::after{ display:none; }

    .app{
      width: min(880px, 100%);
      min-height: min(720px, 100%);
      display:flex;
      flex-direction:column;
      gap: 14px;
    }

    .hud{
      display:flex;
      gap: 10px;
      flex-wrap:wrap;
      align-items:stretch;
      justify-content:space-between;
    }
    .hud .chip{
      flex: 1 1 180px;
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

    .stage{
      flex:1;
      background: linear-gradient(160deg, rgba(255,255,255,0.10), rgba(255,255,255,0.04));
      border: 1px solid rgba(255,255,255,0.14);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      backdrop-filter: blur(12px);
      display:flex;
      flex-direction:column;
      align-items:center;
      justify-content:center;
      padding: 54px 18px 18px;
      position:relative;
      overflow:hidden;
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
      font-size: 20px;
      font-weight: 800;
      letter-spacing: 0.2px;
    }
    .subtitle{
      color: var(--muted);
      font-size: 13px;
      text-align:center;
      max-width: 560px;
      line-height: 1.35;
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

    .question{
      display:flex;
      flex-direction:column;
      gap: 6px;
      align-items:center;
      text-align:center;
      margin-bottom: 6px;
    }
    .question .q{
      font-size: 16px;
      font-weight: 750;
    }
    .question .hint{
      color: var(--muted);
      font-size: 12px;
    }

    .grid{
      width: min(560px, 100%);
      display:grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
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

    .badge{
      font-size: 12px;
      color: var(--muted);
    }
    .pm-error{ color: #dc3545; font-weight: 600; }
    .pm-success{ color: #198754; font-weight: 600; }
    .bi-icon{ width:16px; height:16px; display:inline-block; }

    .stats{
      width: min(640px, 100%);
      display:grid;
      grid-template-columns: repeat(2, minmax(0,1fr));
      gap: 10px;
      margin-top: 8px;
    }
    .stat{
      background: rgba(255,255,255,0.06);
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 16px;
      padding: 12px 14px;
      display:flex;
      flex-direction:column;
      gap: 4px;
    }
    .stat .k{ color: var(--muted); font-size: 12px; }
    .stat .v{ font-weight: 800; font-size: 14px; }

    .action-row{
      width: min(680px, 100%);
      display:grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 10px;
      align-items:stretch;
    }
    .action-row .btn{ width:100%; }

    @media (max-width: 520px){
      .stats{ grid-template-columns: 1fr; }
      .stage{ padding: 36px 12px 14px; border-radius: 18px; }
      .grid{ gap: 8px; }
      .cell{ border-radius: 12px; }
      .hud .chip{ padding: 8px 12px; }
      .action-row{ grid-template-columns: 1fr; }
    }

    @media (prefers-reduced-motion: reduce){
      * { animation: none !important; transition: none !important; }
    }
    
    .logo{
      width:84px; height:84px;
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
  
    
    /* fun-bg */
    :root{ --grid: rgba(255,255,255,0.08); }
    [data-bs-theme="light"]{ --grid: rgba(31,27,43,0.1); }
    body::before,
    body::after{
      content:"";
      position:fixed;
      inset:0;
      pointer-events:none;
      z-index:-1;
    }
    body::before{
      background:
        radial-gradient(640px 640px at 12% 12%, rgba(255,107,91,0.16), transparent 60%),
        radial-gradient(600px 600px at 88% 18%, rgba(124,137,255,0.14), transparent 60%),
        radial-gradient(520px 520px at 50% 85%, rgba(73,242,178,0.12), transparent 60%);
      opacity:0.6;
    }
    body::after{
      background: radial-gradient(var(--grid) 1px, transparent 1px);
      background-size: 28px 28px;
      opacity:0.32;
    }
    h1, h2, h3{
      position: relative;
      display: inline-block;
      font-family: "Baloo 2", "Rubik", "Segoe UI", "Helvetica Neue", sans-serif;
      letter-spacing:.2px;
    }
    h1::after, h2::after, h3::after{
      content:"";
      position:absolute;
      left: 0;
      bottom: -6px;
      width: 100%;
      height: 10px;
      border-radius: 999px;
      background: linear-gradient(135deg, rgba(255,211,107,0.7), rgba(255,107,91,0.35));
      z-index:-1;
    }
  </style>
</head>
<body>
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
        <div class="title">
          <?= htmlspecialchars($isDailyMode ? tt('daily_title', 'Daily Challenge') : tt('play_title', 'Ready to play?')) ?>
        </div>
        <div class="subtitle">
          <?= htmlspecialchars($isDailyMode ? tt('daily_once', 'Daily challenge: one attempt per day.') : tt('play_subtitle', 'Remember the shown color, then pick it from the grid.')) ?>
        </div>
        <div class="action-row">
          <button id="btnStart" class="btn primary" type="button">
            <img class="bi-icon" src="bootstrap-icons/play-fill.svg" alt="" aria-hidden="true" />
            <?= htmlspecialchars(tt('btn_start', 'Start')) ?>
          </button>
          <?php if ($userEmail): ?>
            <a class="btn" href="games.php">
              <img class="bi-icon" src="bootstrap-icons/clock-history.svg" alt="" aria-hidden="true" />
              <?= htmlspecialchars(tt('btn_view_history', 'My Sessions')) ?>
            </a>
          <?php endif; ?>
        </div>
      </div>
      <div id="statusBadge" class="badge"><?= htmlspecialchars(tt('status_ready', 'Ready.')) ?></div>
    </div>

    <?php if (!empty($stats)): ?>
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
        <button id="dailyCompletedOk" class="btn primary" type="button">
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
    'question_pick_target' => tt('question_pick_target', 'Which color was shown? Pick the target.'),
    'question_hint' => tt('question_hint', 'Use Tab/Shift+Tab and Enter/Space to pick.'),
    'badge_ready' => tt('badge_ready', 'Ready. Countdown...'),
    'badge_showing_target' => tt('badge_showing_target', 'Showing target...'),
    'badge_answer' => tt('badge_answer', 'Answer now!'),
    'toast_pick' => tt('toast_pick', 'Pick within 5 seconds'),
    'badge_correct' => tt('badge_correct', 'Perfect match! Next stage...'),
    'badge_wrong' => tt('badge_wrong', 'Wrong match. Game over.'),
    'toast_correct' => tt('toast_correct', 'Perfect Match!'),
    'toast_wrong' => tt('toast_wrong', 'Wrong Match!'),
    'win_title' => tt('win_title', 'You matched them all!'),
    'win_body' => tt('win_body', 'You reached stage {level}. Total perfect matches: {correct}'),
    'gameover_title' => tt('gameover_title', 'Game over'),
    'gameover_body' => tt('gameover_body', '{reason} Tap to try again.'),
    'reason_wrong' => tt('reason_wrong', 'Wrong match.'),
    'reason_timeup' => tt('reason_timeup', 'Time\'s up.'),
    'status_ready' => tt('status_ready', 'Ready.'),
    'status_finished' => tt('status_finished', 'Finished'),
    'btn_play_again' => tt('btn_play_again', 'Play again'),
    'btn_restart' => tt('btn_restart', 'Restart'),
    'btn_view_history' => tt('btn_view_history', 'My Sessions'),
    'sound_on' => tt('sound_on', 'Sound: On'),
    'sound_off' => tt('sound_off', 'Sound: Off'),
    'th_score' => tt('th_score', 'Score'),
    'daily_once' => tt('daily_once', 'Daily challenge: one attempt per day.'),
    'daily_login_required' => tt('daily_login_required', 'Log in to play the daily challenge.'),
    'daily_completed' => tt('daily_completed', 'You already played today. Come back tomorrow!'),
    'save_after_title' => tt('save_after_title', 'Save your score?'),
    'save_after_body' => tt('save_after_body', 'Log in with {google} to save this session and view detailed stats.'),
    'save_with_google' => tt('save_with_google', 'Save with {google}'),
    'continue_without_saving' => tt('continue_without_saving', 'Continue without saving'),
    'save_pending' => tt('save_pending', 'Save queued. It will sync on next load.'),
    'a11y_color_option' => tt('a11y_color_option', 'Color option {n}'),
    'no_results' => tt('no_results', 'No results'),
    'err_palette' => tt('err_palette', 'Insufficient color pool.'),
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

function showAnswerPopup(type){
  if (!answerOverlay || !answerCard || !answerIcon || !answerText) return Promise.resolve();
  if (answerPopupTimer) clearTimeout(answerPopupTimer);
  if (answerPopupResolve) {
    answerPopupResolve();
    answerPopupResolve = null;
  }

  const isRight = type === 'right';
  const msg = pickRandomMessage(isRight ? RIGHT_MESSAGES : WRONG_MESSAGES) || (isRight ? tjs('toast_correct', '✅ Perfect Match!') : tjs('toast_wrong', '❌ Wrong Match!'));
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
    }, 3000);
  });
}


/**
 * Color Catch - Frontend Game Logic (9 unique colors per round, full telemetry payload)
 */

const IS_LOGGED_IN = <?= $userEmail ? 'true' : 'false' ?>;
const IS_DAILY_MODE = <?= $isDailyMode ? 'true' : 'false' ?>;
const DAILY_UTC_DATE = <?= json_encode($dailyDateUtc) ?>;
const FLASH_MSG = <?= json_encode($flash ?? "") ?>;

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

const MAX_LEVEL = 50;
const COUNTDOWN_START = 3;

const START_TARGET_SHOW_MS = 3000;
const ANSWER_WINDOW_MS = 5000;
const SHOW_DECAY_FACTOR = 0.9;
const MIN_TARGET_SHOW_MS = 250;

const DEFAULT_GRID_COUNT = 9; // 3x3
const TOAST_HIDE_MS = 500;    // doğru/yanlış 500ms

function targetShowMsForLevel(level){
  const lvl = Math.max(1, Math.min(MAX_LEVEL, level));
  if (lvl === 21 || lvl === 41) return 5000;
  const ms = Math.floor(START_TARGET_SHOW_MS * Math.pow(SHOW_DECAY_FACTOR, lvl - 1));
  return Math.max(MIN_TARGET_SHOW_MS, ms);
}

function gridCountForLevel(level){
  if (level <= 20) return 9;
  if (level <= 40) return 16;
  return 25;
}

function computeScoreFromPayload(payload){
  const reached = Math.max(1, Math.min(MAX_LEVEL, (payload.reachedLevel || 1)));
  const rounds = Array.isArray(payload.rounds) ? payload.rounds : [];
  let sumRatio = 0;
  let count = 0;

  for (const r of rounds){
    if (!r || typeof r !== 'object') continue;
    const level = Math.max(1, Math.min(MAX_LEVEL, r.level || 0));
    if (!r.isCorrect) continue;
    const responseMs = Math.max(1, (r.responseMs || 0));
    const showMs = targetShowMsForLevel(level);
    let ratio = showMs / responseMs;
    if (ratio < 0) ratio = 0;
    if (ratio > 1.5) ratio = 1.5;
    sumRatio += ratio;
    count++;
  }

  const avgRatio = count > 0 ? (sumRatio / count) : 0;
  const timeFactor = Math.min(1.0, avgRatio / 1.5);
  const levelFactor = reached / MAX_LEVEL;
  let score = Math.round(1000 * ((0.7 * levelFactor) + (0.3 * timeFactor)));
  if (score < 0) score = 0;
  if (score > 1000) score = 1000;
  return score;
}

// DOM
const elCenter = document.getElementById("center");
const elToast = document.getElementById("toast");
const elBadge = document.getElementById("statusBadge");

const hudLevel = document.getElementById("hudLevel");
const hudTime  = document.getElementById("hudTime");
const hudCorrect = document.getElementById("hudCorrect");
const hudShow = document.getElementById("hudShow");

const btnStart = document.getElementById("btnStart");

const dailyModal = document.getElementById("dailyCompletedModal");
const dailyModalText = document.getElementById("dailyCompletedText");
const dailyModalOk = document.getElementById("dailyCompletedOk");
const dailyLoginModal = document.getElementById("dailyLoginModal");
const dailyLoginBack = document.getElementById("dailyLoginBack");

// State
const state = {
  level: 1,
  correct: 0,
  targetColor: null,
  targetShowMs: START_TARGET_SHOW_MS,
  answerMs: ANSWER_WINDOW_MS,
  isLocked: false,
  phase: "idle",
  timers: { timeoutIds: new Set(), intervalIds: new Set() },
  dailyLocked: false,
  dailyLoginShown: false,
  gridCount: DEFAULT_GRID_COUNT,

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
  hudLevel.textContent = `${state.level} / ${MAX_LEVEL}`;
  hudCorrect.textContent = String(state.correct);
  hudShow.textContent = `${(state.targetShowMs / 1000).toFixed(2)}s`;

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
  for (let i = arr.length - 1; i > 0; i--){
    const j = randInt(0, i + 1);
    [arr[i], arr[j]] = [arr[j], arr[i]];
  }
  return arr;
}
function pickUniqueColors(count, excludeSet = new Set()){
  const pool = PALETTE.filter(c => !excludeSet.has(c));
  if (count > pool.length) throw new Error(tjs('err_palette', 'Insufficient color pool.'));
  const tmp = pool.slice();
  for (let i = tmp.length - 1; i > 0; i--){
    const j = randInt(0, i + 1);
    [tmp[i], tmp[j]] = [tmp[j], tmp[i]];
  }
  return tmp.slice(0, count);
}
function assertUnique(arr){
  return new Set(arr).size === arr.length;
}

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
  // Prevent duplicate overlays
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
    // Do not block UI; caller decides when to show modal
    return { ok: false, reason: 'not_logged_in' };
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
        lockDailyAlreadyPlayed();
        return { ok: false, reason: 'already_played' };
      }
      return { ok: false, reason: 'http' };
    }

    lockDaily();
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
  if (IS_DAILY_MODE){
    if (!IS_LOGGED_IN){
      lockDaily(tjs('daily_login_required', 'Log in to play the daily challenge.'));
      return false;
    }
    const res = await postResultIfLoggedIn(payload);
    return !!res.ok;
  }

  const res = await postResultIfLoggedIn(payload);
  if (res.ok) return true;

  if (IS_LOGGED_IN){
    const queued = await storePendingSilently(payload);
    if (queued) toastQuick(tjs('save_pending', 'Save queued. It will sync on next load.'));
    return false;
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
  try{
    const r = await fetch(`api/daily_status.php?day=${encodeURIComponent(DAILY_UTC_DATE)}`, {
      credentials: "same-origin"
    });
    const j = await r.json();
    if (j.ok && j.played){
      lockDailyAlreadyPlayed();
    }
  }catch(e){}
}

function buildGamePayload(reachedLevel, won){
  const endedAt = Date.now();
  const startedAt = state.gameStartedAt ?? endedAt;

  const payload = {
    reachedLevel,
    correct: state.correct,
    won,
    startedAt: new Date(startedAt).toISOString(),
    endedAt: new Date(endedAt).toISOString(),
    durationMs: Math.max(0, endedAt - startedAt),
    rounds: state.rounds.slice(),
    gridCount: state.gridCount
  };
  payload.score = computeScoreFromPayload(payload);
  return payload;
}

function startGame(){
  ensureAudio();
  playSound('start');
  if (IS_DAILY_MODE && state.dailyLocked) return;
  if (IS_DAILY_MODE && !IS_LOGGED_IN){
    lockDaily(tjs('daily_login_required', 'Log in to play the daily challenge.'));
    showDailyLoginModal();
    return;
  }
  clearAllTimers();

  state.level = 1;
  state.correct = 0;
  state.targetShowMs = START_TARGET_SHOW_MS;
  state.targetColor = null;
  state.isLocked = false;
  state.phase = "idle";
  state.gridCount = gridCountForLevel(1);

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

  render(makeStack(
    h1(tjs('app_name', 'Prismatch')),
    p(tjs('countdown_help', 'Remember the shown color, then pick it from the grid.')),
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

function runTargetShow(){
  clearAllTimers();
  state.phase = "showTarget";
  state.isLocked = true;

  state.targetColor = PALETTE[randInt(0, PALETTE.length)];

  const card = document.createElement("div");
  card.className = "target-card";
  card.style.background = state.targetColor;

  render(makeStack(
    h1(`${tjs('hud_stage', 'Stage')} ${state.level}`),
    p(tjs('remember_this', 'Remember this color.')),
    card
  ));

  updateHUD(state.answerMs);
  setBadge(tjs('badge_showing_target', 'Showing target…'));

  setSafeTimeout(() => runQuestionGrid(), state.targetShowMs);
}

function runQuestionGrid(){
  clearAllTimers();
  state.phase = "question";
  state.isLocked = false;

  const gridCount = gridCountForLevel(state.level);
  // benzersiz renkler: target + (gridCount - 1) diger
  const exclude = new Set([state.targetColor]);
  const others = pickUniqueColors(gridCount - 1, exclude);
  const colors = shuffle([state.targetColor, ...others]);

  // ekstra garanti
  if (!assertUnique(colors)) {
    return runQuestionGrid();
  }

  state.roundQuestionStartTs = performance.now();
  state.currentGridColors = colors.slice();

  const qWrap = document.createElement("div");
  qWrap.className = "question";

  const q = document.createElement("div");
  q.className = "q";
  q.textContent = tjs('question_pick_target', 'Which color was shown? Pick the target.');
  const hint = document.createElement("div");
  hint.className = "hint";
  hint.textContent = tjs('question_hint', 'Use Tab/Shift+Tab and Enter/Space to pick.');
  qWrap.appendChild(q);
  qWrap.appendChild(hint);

  const grid = document.createElement("div");
  grid.className = "grid";
  const gridSize = Math.round(Math.sqrt(gridCount));
  if (gridSize * gridSize === gridCount) {
    grid.style.gridTemplateColumns = `repeat(${gridSize}, 1fr)`;
  }

  const buttons = [];

  colors.forEach((c, idx) => {
    const btn = document.createElement("button");
    btn.className = "cell";
    btn.type = "button";
    btn.style.background = c;
    btn.setAttribute("aria-label", tf('a11y_color_option', {n: idx + 1}, `Color option ${idx + 1}`));
    btn.dataset.color = c;

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

  render(makeStack(
    h1(`${tjs('hud_stage', 'Stage')} ${state.level}`),
    qWrap,
    grid
  ));

  setBadge(tjs('badge_answer', 'Answer now!'));
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
  const picked = btn.dataset.color;
  const isCorrect = picked === state.targetColor;

  // telemetry push (her tur)
  state.rounds.push({
    level: state.level,
    targetColor: state.targetColor,
    gridColors: state.currentGridColors.slice(),
    pickedColor: picked,
    responseMs,
    isCorrect
  });

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

async function onTimeUp(buttons){
  if (state.phase !== "question" || state.isLocked) return;

  state.isLocked = true;
  lockButtons(buttons, true);

  // timeout telemetry
  state.rounds.push({
    level: state.level,
    targetColor: state.targetColor,
    gridColors: state.currentGridColors.slice(),
    pickedColor: null,
    responseMs: state.answerMs,
    isCorrect: false
  });

  setBadge(tjs('badge_wrong', 'Wrong match. Game over.'));
  playSound('wrong');
  await showAnswerPopup('wrong');
  gameOver(tjs('reason_timeup', 'Time’s up.'));
}

function advanceLevel(){
  clearAllTimers();

  if (state.level >= MAX_LEVEL){
    win();
    return;
  }

  state.level += 1;
  state.gridCount = gridCountForLevel(state.level);
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

  const payload = buildGamePayload(MAX_LEVEL, true);

  const wrap = makeStack(
    h1(tjs('win_title', '🏆 You matched them all!')),
    p(tf('win_body', {correct: state.correct, level: MAX_LEVEL}, `You reached stage ${MAX_LEVEL}. Total perfect matches: ${state.correct}`)),
    p(`${tjs('th_score', 'Score')}: ${payload.score}`)
  );

  const row = document.createElement("div");
  row.className = "action-row";

  if (!IS_DAILY_MODE){
    const btn = document.createElement("button");
    btn.className = "btn primary";
    btn.type = "button";
      btn.innerHTML = '<img class="bi-icon" src="bootstrap-icons/arrow-repeat.svg" alt="" aria-hidden="true" /> ' + tjs('btn_play_again', 'Play again');
    btn.addEventListener("click", startGame);
    row.appendChild(btn);
  }

  if (IS_LOGGED_IN){
    const a = document.createElement("a");
    a.className = "btn";
    a.href = "games.php";
    a.innerHTML = '<img class="bi-icon" src="bootstrap-icons/clock-history.svg" alt="" aria-hidden="true" /> ' + tjs('btn_view_history', 'My Sessions');
    row.appendChild(a);
  }

  wrap.appendChild(row);
  render(wrap);

  // If user is guest, offer login-to-save as a modal on top
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
    btn.addEventListener("click", startGame);
    row.appendChild(btn);
  }

  if (IS_LOGGED_IN){
    const a = document.createElement("a");
    a.className = "btn";
    a.href = "games.php";
    a.innerHTML = '<img class="bi-icon" src="bootstrap-icons/clock-history.svg" alt="" aria-hidden="true" /> ' + tjs('btn_view_history', 'My Sessions');
    row.appendChild(a);
  }

  wrap.appendChild(row);
  render(wrap);

  // If user is guest, offer login-to-save as a modal on top
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
checkDailyStatus();
updateHUD(ANSWER_WINDOW_MS);
if (FLASH_MSG) toastQuick(FLASH_MSG);
</script>



<?php if (is_file(__DIR__ . '/footer.php')) include __DIR__ . '/footer.php'; ?>

</body>
</html>




