<?php
require_once __DIR__ . '/bootstrap.php';
if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
  error_reporting(E_ALL);
  @ini_set('display_errors', '1');
  @ini_set('display_startup_errors', '1');
}

require_once __DIR__ . '/i18n.php';

$lang = function_exists('get_lang') ? get_lang() : 'en';
$dir  = function_exists('lang_dir') ? lang_dir($lang) : 'ltr';
$userEmail = $_SESSION['user_email'] ?? null;
$showLangPicker = true;

function tt(string $key, string $fallback = ''): string {
  $v = t($key);
  if ($v === $key) return $fallback !== '' ? $fallback : $key;
  return $v;
}

$guid = trim((string)($_GET['guid'] ?? ''));
if (!$userEmail) {
  $next = 'room_play.php?guid=' . rawurlencode($guid);
  header('Location: login.php?next=' . rawurlencode($next));
  exit;
}
$seoTitle = tt('room_play_title', 'Room Match');
$seoDescription = tt('room_play_desc', 'Compete live in a room.');
$seoLangs = function_exists('supported_languages') ? array_keys(supported_languages()) : [];
$dict = translations();
$rightAnswerMessages = $dict[$lang]['right_answer_messages'] ?? ($dict['en']['right_answer_messages'] ?? []);
$wrongAnswerMessages = $dict[$lang]['wrong_answer_messages'] ?? ($dict['en']['wrong_answer_messages'] ?? []);
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
  <link href="css/bootstrap.min.css" rel="stylesheet" />
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
    'robots' => 'noindex,follow',
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
      z-index: 32000;
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

    .leaderboard-card{
      width: min(520px, 92vw);
      background: linear-gradient(160deg, rgba(255,255,255,0.12), rgba(255,255,255,0.04));
      border: 1px solid rgba(255,255,255,0.2);
      border-radius: 20px;
      padding: 16px;
      box-shadow: 0 24px 60px rgba(0,0,0,0.45);
    }
    .leaderboard-result{
      display:flex;
      align-items:center;
      gap:10px;
      font-size: 13px;
      color: var(--muted);
      margin-bottom: 10px;
    }
    .leaderboard-result img{
      width:20px;
      height:20px;
    }
    .finish-icon{
      width: 120px;
      height: 120px;
      border-radius: 28px;
      margin: 6px auto 8px auto;
      display:flex;
      align-items:center;
      justify-content:center;
      background: rgba(255,255,255,0.12);
      border: 1px solid rgba(255,255,255,0.18);
      box-shadow: inset 0 0 0 1px rgba(255,255,255,0.06), 0 16px 36px rgba(0,0,0,0.35);
    }
    .finish-icon img{
      width: 72px;
      height: 72px;
      animation: popIn 320ms ease;
    }
    .rank-row{ transition: transform 200ms ease, background 200ms ease; }
    .rank-row.flash{ background: rgba(46, 204, 113, 0.12); animation: flash 0.6s ease; }
    .rank-row.self{
      background: rgba(255,255,255,0.08);
      box-shadow: inset 3px 0 0 rgba(255, 208, 138, 0.7);
    }
    .rank-row.self.active{
      box-shadow: inset 3px 0 0 rgba(61, 214, 160, 0.8);
    }
    .rank-row.self.eliminated{
      box-shadow: inset 3px 0 0 rgba(255, 77, 77, 0.8);
      opacity: 0.85;
    }
    .rank-row.eliminated{
      color: rgba(255,255,255,0.72);
      opacity: 0.75;
    }
    .rank-row .status-pill{
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding: 2px 8px;
      border-radius: 999px;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.2px;
      text-transform: uppercase;
      background: rgba(255,255,255,0.1);
    }
    .rank-row .status-pill.eliminated{
      color: #ff9c9c;
      background: rgba(255, 77, 77, 0.15);
      border: 1px solid rgba(255, 77, 77, 0.35);
    }
    .rank-row .status-pill.active{
      color: #6ae6c0;
      background: rgba(61, 214, 160, 0.15);
      border: 1px solid rgba(61, 214, 160, 0.35);
    }
    @keyframes flash{ from{ transform: translateY(-4px); } to{ transform: translateY(0); } }

    .room-meta{
      display:flex;
      gap:10px;
      align-items:center;
      flex-wrap:wrap;
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
}')) ?>,
  rightMessages: <?= json_encode($rightAnswerMessages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
  wrongMessages: <?= json_encode($wrongAnswerMessages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
  statusReady: <?= json_encode(tt('status_ready', 'Ready.')) ?>,
  statusFinished: <?= json_encode(tt('status_finished', 'Finished')) ?>,
  waiting: <?= json_encode(tt('room_waiting', 'Waiting for host...')) ?>,
  intermission: <?= json_encode(tt('room_intermission', 'Next stage...')) ?>,
  roomActive: <?= json_encode(tt('room_active', 'Active')) ?>,
  roomEliminated: <?= json_encode(tt('room_eliminated', 'Eliminated')) ?>,
  roomFinished: <?= json_encode(tt('status_finished', 'Finished')) ?>,
  roomLeaderboard: <?= json_encode(tt('room_leaderboard', 'Leaderboard')) ?>,
  roomPlayer: <?= json_encode(tt('room_player', 'Player')) ?>,
  roomScore: <?= json_encode(tt('room_score', 'Score')) ?>,
  roomStatus: <?= json_encode(tt('room_status', 'Status')) ?>,
  roomFinishedTitle: <?= json_encode(tt('room_finished_title', 'Game finished')) ?>,
  roomFinishedDesc: <?= json_encode(tt('room_finished_desc', 'All players are eliminated. Final leaderboard is below.')) ?>,
  roomFinishedIconAlt: <?= json_encode(tt('room_finished_icon_alt', 'Finish badge')) ?>,
  roomTitle: <?= json_encode(tt('room_live_title', 'Room Match')) ?>,
  joinFailed: <?= json_encode(tt('error_generic', 'Error')) ?>,
  soundOn: <?= json_encode(tt('sound_on', 'Sound On')) ?>,
  soundOff: <?= json_encode(tt('sound_off', 'Sound Off')) ?>,
};

const elCenter = document.getElementById('center');
const elToast = document.getElementById('toast');
const elBadge = document.getElementById('statusBadge');
const hudLevel = document.getElementById('hudLevel');
const hudTime = document.getElementById('hudTime');
const hudCorrect = document.getElementById('hudCorrect');
const hudShow = document.getElementById('hudShow');
const playersBadge = document.getElementById('playersBadge');
const selfStatusBadge = document.getElementById('selfStatusBadge');
const roomInfo = document.getElementById('roomInfo');
const startBtn = document.getElementById('startBtn');

const state = {
  round: 0,
  roundsTotal: 0,
  correct: 0,
  targetColor: null,
  gridColors: [],
  targetShowMs: 3000,
  answerMs: 5000,
  countdownMs: 3000,
  phase: 'idle',
  isLocked: false,
  eliminated: false,
  answered: false,
  lastAnswerCorrect: null,
  finished: false,
  roundQuestionStartTs: 0,
  leaderboardPrev: new Map(),
  lastLeaderboardPlayers: [],
  timers: { timeoutIds: new Set(), intervalIds: new Set() },
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
function finishRoom(){
  state.finished = true;
  state.isLocked = true;
  clearAllTimers();
  setBadge(STR.statusFinished);
  setSelfStatus('finished');
  renderFinished();
  playSound('finish');
}

function updateHUD(answerLeftMs = null){
  hudLevel.textContent = `${state.round || '-'}`;
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
function toastQuick(msg){ toast(msg, 1100); }

const RIGHT_MESSAGES = Array.isArray(STR.rightMessages) ? STR.rightMessages : [];
const WRONG_MESSAGES = Array.isArray(STR.wrongMessages) ? STR.wrongMessages : [];
const answerOverlay = document.getElementById('answerOverlay');
const answerCard = document.getElementById('answerCard');
const answerIcon = document.getElementById('answerIcon');
const answerText = document.getElementById('answerText');
let answerPopupTimer = null;
let answerPopupResolve = null;
let answerPopupPromise = Promise.resolve();
const btnMute = document.getElementById('btnMute');
const muteLabel = document.getElementById('muteLabel');

const SOUND_KEY = 'pm-sound-enabled';
let soundEnabled = true;
try {
  const storedSound = localStorage.getItem(SOUND_KEY);
  if (storedSound === '0') soundEnabled = false;
} catch (e) {}

let audioCtx = null;
let audioUnlocked = false;
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

function updateMuteUI(){
  if (!btnMute || !muteLabel) return;
  btnMute.setAttribute('aria-pressed', soundEnabled ? 'false' : 'true');
  btnMute.classList.toggle('is-muted', !soundEnabled);
  muteLabel.textContent = soundEnabled ? STR.soundOn : STR.soundOff;
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
  const msg = pickRandomMessage(isRight ? RIGHT_MESSAGES : WRONG_MESSAGES) || (isRight ? STR.toastCorrect : STR.toastWrong);
  answerText.textContent = msg;
  answerIcon.src = isRight ? 'success-checkmark.svg' : 'error-x.svg';
  answerCard.classList.toggle('success', isRight);
  answerCard.classList.toggle('error', !isRight);
  answerOverlay.hidden = false;

  answerPopupPromise = new Promise((resolve) => {
    answerPopupResolve = resolve;
    answerPopupTimer = setTimeout(() => {
      answerOverlay.hidden = true;
      answerPopupResolve && answerPopupResolve();
      answerPopupResolve = null;
    }, 3000);
  });
  return answerPopupPromise;
}

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

function buildLeaderboardCard(players){
  const wrap = document.createElement('div');
  wrap.className = 'leaderboard-card';

  const title = document.createElement('div');
  title.className = 'h6';
  title.textContent = STR.roomLeaderboard || 'Leaderboard';
  wrap.appendChild(title);

  const result = buildLeaderboardResult();
  if (result) wrap.appendChild(result);

  const tableWrap = document.createElement('div');
  tableWrap.className = 'table-responsive';
  const table = document.createElement('table');
  table.className = 'table table-sm align-middle mb-0';
  const thead = document.createElement('thead');
  thead.innerHTML = `
    <tr>
      <th>#</th>
      <th>${STR.roomPlayer || 'Player'}</th>
      <th>${STR.roomScore || 'Score'}</th>
      <th>${STR.roomStatus || 'Status'}</th>
    </tr>
  `;
  const tbody = document.createElement('tbody');
  const next = new Map();
  const ordered = (players || []).map((p, idx) => ({...p, __idx: idx}))
    .sort((a, b) => {
      const aElim = a.status === 'eliminated' ? 1 : 0;
      const bElim = b.status === 'eliminated' ? 1 : 0;
      if (aElim !== bElim) return aElim - bElim;
      return a.__idx - b.__idx;
    });
  ordered.forEach((p, idx) => {
    const tr = document.createElement('tr');
    tr.className = 'rank-row';
    if (p.email === ME) {
      tr.classList.add('self');
      tr.classList.add(p.status === 'eliminated' ? 'eliminated' : 'active');
    }
    tr.dataset.email = p.email;
    const statusLabel = p.status === 'eliminated' ? STR.roomEliminated : STR.roomActive;
    const statusClass = p.status === 'eliminated' ? 'eliminated' : 'active';
    const statusHtml = `<span class="status-pill ${statusClass}">${statusLabel}</span>`;
    tr.innerHTML = `<td>${idx+1}</td><td>${p.email}</td><td>${p.score}</td><td>${statusHtml}</td>`;
    if (p.status === 'eliminated') tr.classList.add('eliminated');
    const prevRow = state.leaderboardPrev.get(p.email);
    if (!prevRow || prevRow.rank !== idx || prevRow.score !== p.score || prevRow.status !== p.status) {
      tr.classList.add('flash');
    }
    next.set(p.email, {rank: idx, score: p.score, status: p.status});
    tbody.appendChild(tr);
  });
  state.leaderboardPrev = next;

  table.appendChild(thead);
  table.appendChild(tbody);
  tableWrap.appendChild(table);
  wrap.appendChild(tableWrap);
  return wrap;
}

function buildLeaderboardResult(){
  if (state.lastAnswerCorrect === true) {
    const row = document.createElement('div');
    row.className = 'leaderboard-result';
    row.innerHTML = `<img src="success-checkmark.svg" alt="" aria-hidden="true" /><span>${STR.badgeCorrect}</span>`;
    return row;
  }
  if (state.lastAnswerCorrect === false) {
    const row = document.createElement('div');
    row.className = 'leaderboard-result';
    const msg = state.eliminated ? STR.badgeWrong : STR.badgeTimeUp;
    row.innerHTML = `<img src="error-x.svg" alt="" aria-hidden="true" /><span>${msg}</span>`;
    return row;
  }
  return null;
}

function renderIntermission(){
  const nodes = [
    h1(STR.roomTitle),
    p(STR.intermission),
  ];
  if (state.lastLeaderboardPlayers && state.lastLeaderboardPlayers.length) {
    nodes.push(buildLeaderboardCard(state.lastLeaderboardPlayers));
  }
  render(makeStack(...nodes));
}

function renderFinished(){
  const iconWrap = document.createElement('div');
  iconWrap.className = 'finish-icon';
  iconWrap.innerHTML = `<img src="success-checkmark.svg" alt="${STR.roomFinishedIconAlt}" />`;
  const nodes = [
    iconWrap,
    h1(STR.roomFinishedTitle),
    p(STR.roomFinishedDesc),
  ];
  if (state.lastLeaderboardPlayers && state.lastLeaderboardPlayers.length) {
    nodes.push(buildLeaderboardCard(state.lastLeaderboardPlayers));
  }
  render(makeStack(...nodes));
}

function setSelfStatus(stateLabel){
  if (!selfStatusBadge) return;
  selfStatusBadge.classList.remove('pm-error', 'pm-success');
  if (stateLabel === 'eliminated') {
    selfStatusBadge.textContent = STR.roomEliminated;
    selfStatusBadge.classList.add('pm-error');
    return;
  }
  if (stateLabel === 'finished') {
    selfStatusBadge.textContent = STR.roomFinished;
    selfStatusBadge.classList.add('pm-success');
    return;
  }
  selfStatusBadge.textContent = STR.roomActive;
  selfStatusBadge.classList.add('pm-success');
}

function tf(template, vars){
  let out = template || '';
  Object.keys(vars || {}).forEach((k) => {
    out = out.replace(new RegExp('\\{' + k + '\\}', 'g'), String(vars[k]));
  });
  return out;
}

function runCountdown(){
  clearAllTimers();
  state.phase = "countdown";
  state.isLocked = true;
  ensureAudio();
  playSound('start');

  let t = Math.max(1, Math.round((state.countdownMs || 3000) / 1000));
  const cd = document.createElement("div");
  cd.className = "countdown";
  cd.textContent = String(t);

  render(makeStack(
    h1(STR.roomTitle),
    p(STR.countdownHelp),
    cd
  ));

  updateHUD(state.answerMs);
  setBadge(STR.badgeReady);

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

  const card = document.createElement("div");
  card.className = "target-card";
  card.style.background = state.targetColor || '#000';

  render(makeStack(
    h1(`${STR.roomTitle} · ${state.round}`),
    p(STR.rememberThis),
    card
  ));

  updateHUD(state.answerMs);
  setBadge(STR.badgeShow);

  setSafeTimeout(() => runQuestionGrid(), state.targetShowMs);
}

function runQuestionGrid(){
  clearAllTimers();
  state.phase = "question";
  state.isLocked = false;

  const qWrap = document.createElement("div");
  qWrap.className = "question";

  const q = document.createElement("div");
  q.className = "q";
  q.textContent = STR.questionTitle;
  const hint = document.createElement("div");
  hint.className = "hint";
  hint.textContent = STR.questionHint;
  qWrap.appendChild(q);
  qWrap.appendChild(hint);

  const grid = document.createElement("div");
  grid.className = "grid";
  const gridCount = state.gridColors.length;
  const gridSize = Math.round(Math.sqrt(gridCount));
  if (gridSize * gridSize === gridCount) {
    grid.style.gridTemplateColumns = `repeat(${gridSize}, 1fr)`;
  }

  const buttons = [];
  state.gridColors.forEach((c, idx) => {
    const btn = document.createElement("button");
    btn.className = "cell";
    btn.type = "button";
    btn.style.background = c;
    btn.disabled = state.eliminated;
    btn.setAttribute("aria-label", tf(STR.a11yColorOption, {n: idx + 1}));
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
    h1(`${STR.roomTitle} · ${state.round}`),
    qWrap,
    grid
  ));

  setBadge(STR.badgeAnswer);
  toast(STR.toastPick);

  state.roundQuestionStartTs = performance.now();
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

async function submitAnswer(picked, timeout = false){
  if (state.answered || state.eliminated || state.finished) return;
  state.answered = true;
  const responseMs = Math.max(0, Math.round(performance.now() - state.roundQuestionStartTs));
  await fetch('api/rooms_answer.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({guid: GUID, round: state.round, picked, response_ms: responseMs, timeout})
  });
}

async function onPick(btn, buttons){
  if (state.phase !== "question" || state.isLocked || state.eliminated) return;
  state.isLocked = true;
  lockButtons(buttons, true);

  const picked = btn.dataset.color;
  const isCorrect = picked === state.targetColor;

  if (isCorrect){
    btn.classList.add("correct");
    setBadge(STR.badgeCorrect);
    state.correct += 1;
    state.lastAnswerCorrect = true;
    playSound('correct');
  } else {
    btn.classList.add("wrong");
    setBadge(STR.badgeWrong);
    state.eliminated = true;
    setSelfStatus('eliminated');
    state.lastAnswerCorrect = false;
    playSound('wrong');
  }
  submitAnswer(picked, false).catch(() => {});
  await showAnswerPopup(isCorrect ? 'right' : 'wrong');
  renderIntermission();
}

async function onTimeUp(buttons){
  if (state.phase !== "question" || state.isLocked || state.eliminated) return;
  state.isLocked = true;
  lockButtons(buttons, true);
  setBadge(STR.badgeTimeUp);
  state.eliminated = true;
  setSelfStatus('eliminated');
  state.lastAnswerCorrect = false;
  playSound('wrong');
  submitAnswer('', true).catch(() => {});
  await showAnswerPopup('wrong');
  renderIntermission();
}

function renderPlayers(players){
  if (!playersBadge) return;
  const names = players.map(p => p.email + (p.status === 'eliminated' ? ' ✕' : '')).join(' • ');
  playersBadge.textContent = names || '';
}

async function joinRoom(){
  const res = await fetch('api/rooms_join.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({guid: GUID})
  });
  const data = await res.json();
  if (!data.ok) {
    setBadge(STR.joinFailed, 'error');
    return;
  }
  const room = data.room;
  const isHost = room.owner_email === ME;
  if (startBtn) startBtn.style.display = isHost ? 'inline-flex' : 'none';
  if (roomInfo) roomInfo.textContent = room.name ? room.name : STR.roomTitle;
  if (room.current_round > 0) state.round = room.current_round;
  state.roundsTotal = room.rounds_total || state.roundsTotal;
  renderPlayers(data.players || []);
  const meRow = (data.players || []).find(p => p.email === ME);
  if (meRow && meRow.status === 'eliminated') {
    state.eliminated = true;
    setSelfStatus('eliminated');
  } else {
    setSelfStatus('active');
  }
  updateHUD(state.answerMs);
}

async function startNextRound(){
  await fetch('api/rooms_next_round.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({guid: GUID})
  });
}

startBtn?.addEventListener('click', startNextRound);
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

const pusher = new Pusher(P_KEY, {
  cluster: P_CLUSTER,
  authEndpoint: 'api/pusher_auth.php',
  forceTLS: true
});

const channel = pusher.subscribe('presence-room-' + GUID);

channel.bind('pusher:subscription_succeeded', () => {
  setBadge(STR.statusReady);
});

channel.bind('room:round', async (data) => {
  if (state.finished) return;
  state.answered = false;
  state.lastAnswerCorrect = null;
  state.round = data.round;
  state.roundsTotal = data.rounds_total || state.roundsTotal;
  state.targetColor = data.question?.target || null;
  state.gridColors = data.question?.grid || [];
  state.targetShowMs = data.show_ms || 3000;
  state.answerMs = data.answer_ms || 5000;
  state.countdownMs = data.countdown_ms || 3000;
  updateHUD(state.answerMs);
  await answerPopupPromise;
  runCountdown();
});

channel.bind('room:update', (data) => {
  if (state.finished) return;
  renderPlayers(data.players || []);
  const meRow = (data.players || []).find(p => p.email === ME);
  if ((meRow && meRow.status === 'eliminated') || data.eliminated === ME) {
    state.eliminated = true;
    setBadge(STR.badgeWrong);
    setSelfStatus('eliminated');
  } else {
    setSelfStatus('active');
  }
});

channel.bind('room:leaderboard', (data) => {
  state.lastLeaderboardPlayers = data.players || [];
  if (!state.finished) renderIntermission();
});

channel.bind('room:finished', () => {
  finishRoom();
});

joinRoom().catch(() => setBadge(STR.joinFailed, 'error'));
setInterval(() => {
  if (!state.finished) {
    fetch('api/rooms_tick.php?guid=' + encodeURIComponent(GUID)).catch(() => {});
  }
}, 2000);
</script>
</body>
</html>



