<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/i18n.php';

$lang = function_exists('get_lang') ? get_lang() : 'en';
$dir  = function_exists('lang_dir') ? lang_dir($lang) : 'ltr';
$userEmail = $_SESSION['user_email'] ?? null;
$userId = $_SESSION['user_id'] ?? null;
$showLangPicker = true;

function tt(string $key, string $fallback = ''): string {
    $v = t($key);
    if ($v === $key) return $fallback !== '' ? $fallback : $key;
    return $v;
}

$guid = trim((string)($_GET['guid'] ?? ''));
if (!$userEmail || !$userId) {
    $next = 'room_play.php?guid=' . rawurlencode($guid);
    header('Location: login.php?next=' . rawurlencode($next));
    exit;
}

$room = get_room_by_guid($guid);
if (!$room) {
    header('Location: rooms.php');
    exit;
}

$seoTitle = ($room['name'] ?: tt('room_play_title', 'Multiplayer Match')) . ' - ' . tt('app_name', 'Prismatch');
$seoDescription = tt('room_play_desc', 'Compete live in real time against other players.');
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
      const stored = localStorage.getItem('pm-theme');
      const prefers = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
      document.documentElement.setAttribute('data-bs-theme', stored || prefers);
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
    'robots' => 'noindex,follow',
    'lang' => $lang,
    'site_name' => tt('app_name', 'Prismatch'),
  ]) ?>
  <style>
    :root {
      --arena-bg: #090d16;
      --arena-panel: rgba(255, 255, 255, 0.08);
      --arena-border: rgba(255, 255, 255, 0.12);
      --arena-text: #f8fafc;
      --arena-muted: rgba(248, 250, 252, 0.65);
      --arena-accent: #ff6b5b;
      --arena-accent2: #20b77d;
      --arena-accent3: #ffb020;
      --arena-radius: 24px;
    }
    [data-bs-theme="light"] {
      --arena-bg: #f8fafc;
      --arena-panel: rgba(255, 255, 255, 0.95);
      --arena-border: rgba(0, 0, 0, 0.08);
      --arena-text: #0f172a;
      --arena-muted: rgba(15, 23, 42, 0.65);
    }
    body {
      margin: 0;
      font-family: "Rubik", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background: var(--arena-bg);
      color: var(--arena-text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      padding-top: calc(var(--pm-header-offset, 0px) + 16px);
      padding-bottom: calc(var(--pm-footer-offset, 0px) + 20px);
    }
    .arena-container {
      width: min(920px, 100%);
      margin: 0 auto;
      padding: 0 16px;
      display: flex;
      flex-direction: column;
      gap: 14px;
      flex: 1;
    }
    /* Modern Arena Header & HUD */
    .arena-hud {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
      gap: 10px;
    }
    .hud-chip {
      background: var(--arena-panel);
      border: 1px solid var(--arena-border);
      border-radius: 16px;
      padding: 10px 14px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      backdrop-filter: blur(12px);
      box-shadow: 0 4px 16px rgba(0,0,0,0.06);
    }
    .hud-label {
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--arena-muted);
      font-weight: 600;
    }
    .hud-val {
      font-size: 17px;
      font-weight: 800;
      font-family: "Baloo 2", sans-serif;
    }
    /* Main Arena Stage */
    .arena-stage {
      flex: 1;
      min-height: 420px;
      background: var(--arena-panel);
      border: 1px solid var(--arena-border);
      border-radius: var(--arena-radius);
      backdrop-filter: blur(16px);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 24px 18px 20px;
      position: relative;
      overflow: hidden;
      box-shadow: 0 20px 60px rgba(0,0,0,0.15);
    }
    .stage-center {
      width: 100%;
      max-width: 620px;
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      gap: 14px;
      animation: fadeIn 0.3s ease;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(6px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .stage-title {
      font-family: "Baloo 2", sans-serif;
      font-size: 24px;
      font-weight: 800;
      line-height: 1.2;
      margin: 0;
    }
    .stage-subtitle {
      font-size: 14px;
      color: var(--arena-muted);
      max-width: 480px;
    }
    /* Countdown Display */
    .big-countdown {
      font-family: "Baloo 2", sans-serif;
      font-size: clamp(72px, 12vw, 110px);
      font-weight: 900;
      color: var(--arena-accent);
      line-height: 1;
      text-shadow: 0 8px 30px rgba(255, 107, 91, 0.4);
      animation: pop 0.9s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
    }
    @keyframes pop {
      0% { transform: scale(0.6); opacity: 0; }
      50% { transform: scale(1.1); }
      100% { transform: scale(1); opacity: 1; }
    }
    /* Target Color Card */
    .target-box {
      width: min(440px, 92%);
      aspect-ratio: 16/9;
      border-radius: 20px;
      box-shadow: 0 20px 50px rgba(0,0,0,0.4);
      border: 3px solid rgba(255, 255, 255, 0.2);
      animation: pop 0.4s ease both;
      position: relative;
    }
    /* Question Grid */
    .choice-grid {
      width: min(440px, 100%, calc(100vh - 440px));
      min-width: min(260px, 100%);
      aspect-ratio: 1 / 1;
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
      margin: 6px auto 0 auto;
    }
    .choice-cell {
      appearance: none;
      border: 3px solid rgba(255,255,255,0.15);
      border-radius: 18px;
      aspect-ratio: 1 / 1;
      cursor: pointer;
      box-shadow: 0 10px 25px rgba(0,0,0,0.25);
      transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
      outline: none;
    }
    .choice-cell:hover:not(:disabled) {
      transform: translateY(-4px) scale(1.02);
      box-shadow: 0 16px 36px rgba(0,0,0,0.35);
      border-color: rgba(255,255,255,0.7);
    }
    .choice-cell:active:not(:disabled) {
      transform: scale(0.97);
    }
    .choice-cell.correct {
      border-color: #20b77d !important;
      box-shadow: 0 0 0 5px rgba(32, 183, 125, 0.4), 0 16px 36px rgba(0,0,0,0.35);
    }
    .choice-cell.wrong {
      border-color: #ff4757 !important;
      box-shadow: 0 0 0 5px rgba(255, 71, 87, 0.4), 0 16px 36px rgba(0,0,0,0.35);
      opacity: 0.6;
    }
    /* Player Lobby / Presence Bar */
    .players-bar {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 8px;
      padding: 10px 16px;
      background: var(--arena-panel);
      border: 1px solid var(--arena-border);
      border-radius: 16px;
      backdrop-filter: blur(12px);
    }
    .player-tag {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      border-radius: 999px;
      font-size: 13px;
      font-weight: 600;
      background: rgba(255,255,255,0.06);
      border: 1px solid var(--arena-border);
    }
    .player-tag.self {
      border-color: var(--arena-accent);
      background: rgba(255, 107, 91, 0.12);
    }
    .player-tag.eliminated {
      opacity: 0.5;
      text-decoration: line-through;
    }
    .live-dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: var(--arena-accent2);
    }
    /* Answer Overlay Toast */
    .feedback-toast {
      position: fixed;
      top: 90px;
      left: 50%;
      transform: translateX(-50%) translateY(-20px);
      padding: 14px 28px;
      border-radius: 999px;
      font-size: 16px;
      font-weight: 700;
      color: #fff;
      display: flex;
      align-items: center;
      gap: 10px;
      z-index: 1050;
      opacity: 0;
      pointer-events: none;
      transition: all 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      box-shadow: 0 18px 40px rgba(0,0,0,0.35);
    }
    .feedback-toast.show {
      opacity: 1;
      transform: translateX(-50%) translateY(0);
    }
    .feedback-toast.correct { background: linear-gradient(135deg, #10b981, #059669); }
    .feedback-toast.wrong { background: linear-gradient(135deg, #ef4444, #dc2626); }
    .btn-action {
      background: linear-gradient(135deg, #ff6b5b, #ff8c42);
      border: none;
      color: #fff;
      font-weight: 700;
      padding: 12px 28px;
      border-radius: 14px;
      box-shadow: 0 8px 24px rgba(255, 107, 91, 0.4);
      transition: all 0.2s ease;
    }
    .btn-action:hover {
      background: linear-gradient(135deg, #ff5744, #ff7e2e);
      color: #fff;
      transform: translateY(-2px);
    }

    /* Victory & Final Standings Showcase */
    .victory-container {
      width: 100%;
      max-width: 560px;
      margin: 0 auto;
      display: flex;
      flex-direction: column;
      align-items: center;
      animation: fadeIn 0.4s ease;
    }
    .victory-header {
      text-align: center;
      margin-bottom: 12px;
    }
    .victory-trophy {
      font-size: 48px;
      line-height: 1;
      margin-bottom: 4px;
      filter: drop-shadow(0 4px 18px rgba(255, 193, 7, 0.45));
      animation: trophyFloat 1.8s ease-in-out infinite alternate;
    }
    @keyframes trophyFloat {
      from { transform: translateY(0) scale(1); }
      to { transform: translateY(-5px) scale(1.05); }
    }
    .victory-title {
      font-size: 26px;
      font-weight: 800;
      color: #fff;
      margin-bottom: 4px;
    }
    .victory-subtitle {
      font-size: 13px;
      color: var(--arena-muted);
      margin: 0;
    }
    .champion-card {
      position: relative;
      width: 100%;
      border-radius: 18px;
      padding: 16px 20px;
      background: linear-gradient(135deg, rgba(255, 193, 7, 0.16), rgba(255, 107, 91, 0.1));
      border: 2px solid rgba(255, 193, 7, 0.45);
      box-shadow: 0 10px 30px rgba(255, 193, 7, 0.16), inset 0 1px 0 rgba(255, 255, 255, 0.25);
      backdrop-filter: blur(10px);
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      gap: 6px;
      margin-bottom: 14px;
    }
    .champion-card--me {
      background: linear-gradient(135deg, rgba(32, 183, 125, 0.16), rgba(255, 193, 7, 0.16));
      border-color: rgba(32, 183, 125, 0.6);
      box-shadow: 0 10px 32px rgba(32, 183, 125, 0.2);
    }
    .champion-badge-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 4px 12px;
      border-radius: 999px;
      background: rgba(255, 193, 7, 0.25);
      border: 1px solid rgba(255, 193, 7, 0.55);
      color: #ffd166;
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.5px;
      text-transform: uppercase;
    }
    .champion-you-tag {
      background: #20b77d;
      color: #fff;
      padding: 2px 8px;
      border-radius: 999px;
      font-size: 10px;
      font-weight: 800;
    }
    .champion-name {
      font-family: "Baloo 2", sans-serif;
      font-size: 26px;
      font-weight: 900;
      color: #fff;
      text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
      display: flex;
      align-items: center;
      gap: 6px;
      word-break: break-all;
    }
    .champion-score {
      display: inline-flex;
      align-items: baseline;
      gap: 5px;
      color: #ffd166;
      font-weight: 800;
    }
    .champion-score .score-num {
      font-size: 22px;
      line-height: 1;
    }
    .champion-score .score-label {
      font-size: 13px;
      color: var(--arena-muted);
      text-transform: uppercase;
      font-weight: 600;
    }
    .victory-standings {
      width: 100%;
      background: rgba(0, 0, 0, 0.25);
      border: 1px solid var(--arena-border);
      border-radius: 16px;
      overflow: hidden;
      margin-bottom: 8px;
    }
    .standings-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px 14px;
      background: rgba(255, 255, 255, 0.04);
      border-bottom: 1px solid var(--arena-border);
      font-size: 12px;
      font-weight: 700;
      color: var(--arena-muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .standings-table-wrap {
      width: 100%;
      max-height: 240px;
      overflow-y: auto;
    }
    .standings-table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
      font-size: 13px;
      margin: 0;
    }
    .standings-table th {
      padding: 8px 12px;
      font-size: 11px;
      font-weight: 700;
      color: var(--arena-muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      background: rgba(0, 0, 0, 0.15);
      border-bottom: 1px solid var(--arena-border);
    }
    .standings-table td {
      padding: 9px 12px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.05);
      vertical-align: middle;
      color: var(--arena-text);
    }
    .standings-table tr:last-child td {
      border-bottom: none;
    }
    .standings-table tr.row-winner {
      background: rgba(255, 193, 7, 0.07);
    }
    .standings-table tr.row-me {
      background: rgba(255, 107, 91, 0.1);
      font-weight: 600;
    }
    .rank-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 24px;
      height: 24px;
      font-size: 14px;
      font-weight: 800;
    }
    .rank-badge.rank-other {
      font-size: 12px;
      color: var(--arena-muted);
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.06);
    }
    .player-cell {
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    .badge-you {
      font-size: 10px;
      padding: 2px 6px;
      border-radius: 999px;
      background: rgba(255, 107, 91, 0.25);
      border: 1px solid rgba(255, 107, 91, 0.5);
      color: #ff8c42;
      font-weight: 700;
    }
    .score-badge {
      font-weight: 700;
      color: #ffd166;
    }
    .score-pts {
      font-size: 11px;
      font-weight: 500;
      color: var(--arena-muted);
    }
    .status-pill {
      display: inline-flex;
      align-items: center;
      gap: 3px;
      font-size: 11px;
      padding: 2px 8px;
      border-radius: 999px;
      font-weight: 600;
      white-space: nowrap;
    }
    .status-pill.elim {
      background: rgba(239, 68, 68, 0.15);
      color: #f87171;
      border: 1px solid rgba(239, 68, 68, 0.3);
    }
    .status-pill.active {
      background: rgba(32, 183, 125, 0.15);
      color: #34d399;
      border: 1px solid rgba(32, 183, 125, 0.3);
    }

    @media (max-width: 600px) {
      .arena-stage {
        padding: 20px 12px;
        min-height: 380px;
        border-radius: 18px;
      }
      .choice-grid {
        gap: 8px;
      }
      .choice-cell {
        border-radius: 12px;
        border-width: 2px;
      }
      .arena-hud {
        gap: 8px;
      }
      .hud-chip {
        padding: 8px 12px;
      }
      .hud-val {
        font-size: 15px;
      }
      .champion-card {
        padding: 14px 16px;
      }
      .champion-name {
        font-size: 22px;
      }
      .champion-score .score-num {
        font-size: 19px;
      }
    }
  </style>
</head>
<body class="pm-has-fixed-header">
  <?php include __DIR__ . '/header.php'; ?>

  <div class="arena-container">
    <!-- Top HUD Bar -->
    <div class="arena-hud">
      <div class="hud-chip">
        <span class="hud-label"><?= htmlspecialchars(tt('hud_round', 'Round')) ?></span>
        <span id="hudRound" class="hud-val">-</span>
      </div>
      <div class="hud-chip">
        <span class="hud-label"><?= htmlspecialchars(tt('hud_score', 'Score')) ?></span>
        <span id="hudScore" class="hud-val">0</span>
      </div>
      <div class="hud-chip">
        <span class="hud-label"><?= htmlspecialchars(tt('hud_time', 'Time')) ?></span>
        <span id="hudTimer" class="hud-val">-</span>
      </div>
      <div class="hud-chip">
        <span class="hud-label"><?= htmlspecialchars(tt('room_status', 'Status')) ?></span>
        <span id="hudStatus" class="hud-val text-warning"><?= htmlspecialchars(tt('status_connecting', 'Connecting...')) ?></span>
      </div>
    </div>

    <!-- Main Live Game Stage -->
    <main class="arena-stage" id="arenaStage">
      <div class="stage-center" id="stageContent">
        <!-- Default: Waiting Room / Lobby -->
        <div class="fs-1">⏳</div>
        <h1 class="stage-title"><?= htmlspecialchars($room['name'] ?: 'Match Room') ?></h1>
        <p class="stage-subtitle"><?= htmlspecialchars(tt('room_waiting_desc', 'Waiting for players to gather. The room host can launch round 1 whenever ready!')) ?></p>
        
        <div id="hostControls" class="d-none">
          <button id="startMatchBtn" class="btn btn-action btn-lg">
            🚀 <?= htmlspecialchars(tt('room_start_btn', 'Start Match')) ?>
          </button>
        </div>
      </div>
    </main>

    <!-- Player Roster & Presence -->
    <div class="players-bar">
      <div class="small fw-bold text-uppercase text-secondary me-2"><?= htmlspecialchars(tt('room_players', 'Players')) ?>:</div>
      <div id="playerList" class="d-flex flex-wrap gap-2 align-items-center flex-1">
        <!-- Live pills dynamically populated -->
      </div>
    </div>
  </div>

  <!-- Feedback Toast -->
  <div id="feedbackToast" class="feedback-toast">
    <span id="toastIcon"></span>
    <span id="toastText"></span>
  </div>

  <?php include __DIR__ . '/footer.php'; ?>

  <!-- Pusher JS -->
  <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
  <script>
    const ME_EMAIL = <?= json_encode($userEmail) ?>;
    const ME_ID = <?= json_encode((string)$userId) ?>;
    const GUID = <?= json_encode($guid) ?>;
    const PUSHER_KEY = <?= json_encode(defined('PUSHER_KEY') ? PUSHER_KEY : '') ?>;
    const PUSHER_CLUSTER = <?= json_encode(defined('PUSHER_CLUSTER') ? PUSHER_CLUSTER : 'eu') ?>;

    const STR = {
      correct: <?= json_encode(tt('badge_correct', 'Correct!')) ?>,
      wrong: <?= json_encode(tt('badge_wrong', 'Wrong color!')) ?>,
      timeUp: <?= json_encode(tt('badge_timeup', 'Time Up!')) ?>,
      eliminated: <?= json_encode(tt('room_eliminated', 'Eliminated')) ?>,
      spectating: <?= json_encode(tt('room_spectating', 'Spectator Mode')) ?>,
      ready: <?= json_encode(tt('status_ready', 'Ready')) ?>,
      waiting: <?= json_encode(tt('room_waiting', 'Waiting for Host...')) ?>,
      active: <?= json_encode(tt('room_active', 'Active')) ?>,
      finished: <?= json_encode(tt('status_finished', 'Finished')) ?>,
      rememberColor: <?= json_encode(tt('remember_this', 'Remember this color!')) ?>,
      pickColor: <?= json_encode(tt('question_pick_target', 'Which color was shown?')) ?>,
      nextRoundIn: <?= json_encode(tt('room_next_in', 'Next round starting soon...')) ?>,
      winner: <?= json_encode(tt('room_winner_announcement', 'Winner!')) ?>,
      getReady: <?= json_encode(tt('status_get_ready', 'Get Ready!')) ?>,
      watchScreen: <?= json_encode(tt('room_watch_screen', 'Watch the screen carefully...')) ?>,
      memorize: <?= json_encode(tt('status_memorize', 'Memorize!')) ?>,
      showingTarget: <?= json_encode(tt('badge_showing_target', 'Showing target color...')) ?>,
      pickColorUpper: <?= json_encode(tt('badge_pick_color', 'PICK COLOR!')) ?>,
      eliminatedSubtitle: <?= json_encode(tt('room_eliminated_subtitle', 'You are eliminated. Spectating alive players...')) ?>,
      chooseFast: <?= json_encode(tt('room_choose_fast', 'Choose fast for maximum score!')) ?>,
      roundComplete: <?= json_encode(tt('room_round_complete', 'Round {round} Complete')) ?>,
      winnerEveryone: <?= json_encode(tt('room_winner_everyone', 'Everyone')) ?>,
      concludedDesc: <?= json_encode(tt('room_concluded_desc', 'The match has concluded. Great performance by all players!')) ?>,
      backToRooms: <?= json_encode(tt('rooms_back', 'Back to Rooms')) ?>,
      errorJoining: <?= json_encode(tt('room_error_joining', 'Error Joining')) ?>,
      connError: <?= json_encode(tt('room_conn_error', 'Connection Error')) ?>,
      starting: <?= json_encode(tt('room_starting', 'Starting...')) ?>,
      you: <?= json_encode(tt('you_parentheses', '(You)')) ?>,
      pts: <?= json_encode(tt('unit_points', 'pts')) ?>,
      player: <?= json_encode(tt('room_player', 'Player')) ?>,
      score: <?= json_encode(tt('room_score', 'Score')) ?>,
      status: <?= json_encode(tt('room_status', 'Status')) ?>,
      roomLeaderboard: <?= json_encode(tt('room_leaderboard', 'Leaderboard')) ?>,
      players: <?= json_encode(tt('room_players', 'Players')) ?>,
      youWon: <?= json_encode(tt('room_you_won', 'Congratulations, You Won!')) ?>,
      rank: <?= json_encode(tt('leaderboard_col_rank', 'Rank')) ?>,
    };

    const state = {
      round: 0,
      roundsTotal: 50,
      score: 0,
      phase: 'lobby', // 'lobby', 'countdown', 'show', 'question', 'intermission', 'finished'
      targetColor: null,
      gridColors: [],
      showMs: 3000,
      answerMs: 5000,
      countdownMs: 3000,
      eliminated: false,
      answered: false,
      isHost: false,
      questionStartTs: 0,
      activeTimer: null,
      countdownInterval: null,
      players: [],
    };

    // UI Elements
    const hudRound = document.getElementById('hudRound');
    const hudScore = document.getElementById('hudScore');
    const hudTimer = document.getElementById('hudTimer');
    const hudStatus = document.getElementById('hudStatus');
    const stageContent = document.getElementById('stageContent');
    const playerList = document.getElementById('playerList');
    const hostControls = document.getElementById('hostControls');
    const startMatchBtn = document.getElementById('startMatchBtn');
    const feedbackToast = document.getElementById('feedbackToast');
    const toastIcon = document.getElementById('toastIcon');
    const toastText = document.getElementById('toastText');

    // Web Audio Sound Engine
    let audioCtx = null;
    function playTone(freq, dur = 0.12, type = 'sine', gain = 0.08) {
      try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return;
        audioCtx = audioCtx || new Ctx();
        if (audioCtx.state === 'suspended') audioCtx.resume();
        const osc = audioCtx.createOscillator();
        const g = audioCtx.createGain();
        osc.type = type;
        osc.frequency.value = freq;
        g.gain.setValueAtTime(gain, audioCtx.currentTime);
        g.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + dur);
        osc.connect(g);
        g.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + dur);
      } catch(e) {}
    }

    function showToast(text, isCorrect) {
      toastIcon.textContent = isCorrect ? '✅' : '❌';
      toastText.textContent = text;
      feedbackToast.className = 'feedback-toast show ' + (isCorrect ? 'correct' : 'wrong');
      setTimeout(() => { feedbackToast.classList.remove('show'); }, 2200);
    }

    function renderPlayers(players) {
      if (Array.isArray(players) && players.length > 0) {
        state.players = players;
      }
      if (!playerList) return;
      playerList.innerHTML = '';
      (players || []).forEach(p => {
        const isMe = p.email === ME_EMAIL || p.user_id === ME_ID;
        const isElim = p.status === 'eliminated';
        const tag = document.createElement('div');
        tag.className = 'player-tag' + (isMe ? ' self' : '') + (isElim ? ' eliminated' : '');
        const dot = document.createElement('span');
        dot.className = 'live-dot';
        if (isElim) dot.style.background = '#64748b';
        tag.appendChild(dot);

        const nameSpan = document.createElement('span');
        nameSpan.textContent = p.email.split('@')[0] + (isMe ? ' ' + STR.you : '') + (isElim ? ' 💀' : '');
        tag.appendChild(nameSpan);

        if (p.score > 0) {
          const scoreBadge = document.createElement('span');
          scoreBadge.className = 'badge bg-dark-subtle text-dark-emphasis ms-1';
          scoreBadge.textContent = p.score + ' ' + STR.pts;
          tag.appendChild(scoreBadge);
        }

        playerList.appendChild(tag);
      });
    }

    // Step 1: Countdown Phase
    function runCountdown() {
      state.phase = 'countdown';
      hudStatus.textContent = STR.getReady;
      hudStatus.className = 'hud-val text-info';
      playTone(520, 0.08, 'square');

      let remaining = Math.max(1, Math.round(state.countdownMs / 1000));
      stageContent.innerHTML = `
        <h2 class="stage-title">${STR.rememberColor}</h2>
        <div class="big-countdown" id="countdownNum">${remaining}</div>
        <div class="stage-subtitle">${STR.watchScreen}</div>
      `;

      if (state.countdownInterval) clearInterval(state.countdownInterval);
      state.countdownInterval = setInterval(() => {
        remaining -= 1;
        const el = document.getElementById('countdownNum');
        if (remaining > 0) {
          playTone(remaining === 1 ? 760 : 520, 0.08, 'square');
          if (el) el.textContent = String(remaining);
        } else {
          clearInterval(state.countdownInterval);
          runTargetShow();
        }
      }, 1000);
    }

    // Step 2: Target Color Show
    function runTargetShow() {
      state.phase = 'show';
      hudStatus.textContent = STR.memorize;
      hudStatus.className = 'hud-val text-warning';

      stageContent.innerHTML = `
        <h2 class="stage-title">${STR.rememberColor}</h2>
        <div class="target-box" style="background: ${state.targetColor}"></div>
        <div class="stage-subtitle">${STR.showingTarget}</div>
      `;

      setTimeout(() => {
        runQuestion();
      }, state.showMs);
    }

    // Step 3: Question & Interactive Grid Phase
    function runQuestion() {
      state.phase = 'question';
      state.answered = false;
      state.questionStartTs = performance.now();
      hudStatus.textContent = state.eliminated ? STR.spectating : STR.pickColorUpper;
      hudStatus.className = 'hud-val ' + (state.eliminated ? 'text-secondary' : 'text-success');

      stageContent.innerHTML = `
        <h2 class="stage-title">${STR.pickColor}</h2>
        <div class="stage-subtitle">${state.eliminated ? STR.eliminatedSubtitle : STR.chooseFast}</div>
        <div class="choice-grid" id="choiceGrid"></div>
      `;

      const gridEl = document.getElementById('choiceGrid');
      const cells = [];

      state.gridColors.forEach(color => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'choice-cell';
        btn.style.background = color;
        btn.disabled = state.eliminated;
        btn.onclick = () => onChoicePick(color, btn, cells);
        gridEl.appendChild(btn);
        cells.push(btn);
      });

      // Timer countdown for answering
      let remaining = state.answerMs;
      hudTimer.textContent = (remaining / 1000).toFixed(1) + 's';

      if (state.activeTimer) clearInterval(state.activeTimer);
      state.activeTimer = setInterval(() => {
        remaining -= 100;
        if (remaining >= 0) {
          hudTimer.textContent = (remaining / 1000).toFixed(1) + 's';
        }
        if (remaining <= 0) {
          clearInterval(state.activeTimer);
          if (!state.answered && !state.eliminated) {
            onTimeOut(cells);
          }
        }
      }, 100);
    }

    async function onChoicePick(color, btn, cells) {
      if (state.answered || state.eliminated || state.phase !== 'question') return;
      state.answered = true;
      cells.forEach(c => c.disabled = true);
      if (state.activeTimer) clearInterval(state.activeTimer);

      const isCorrect = color === state.targetColor;
      const responseMs = Math.round(performance.now() - state.questionStartTs);

      if (isCorrect) {
        btn.classList.add('correct');
        playTone(660, 0.1, 'triangle');
        setTimeout(() => playTone(880, 0.12, 'triangle'), 100);
        showToast(STR.correct, true);
      } else {
        btn.classList.add('wrong');
        playTone(220, 0.2, 'sawtooth');
        showToast(STR.wrong, false);
        state.eliminated = true;
      }

      try {
        const res = await fetch('api/rooms_answer.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            guid: GUID,
            round: state.round,
            picked: color,
            response_ms: responseMs,
            timeout: false
          })
        });
        const data = await res.json();
        if (data.ok && data.score_delta) {
          state.score += data.score_delta;
          hudScore.textContent = String(state.score);
        }
      } catch (e) {}
    }

    async function onTimeOut(cells) {
      if (state.answered || state.eliminated) return;
      state.answered = true;
      state.eliminated = true;
      cells.forEach(c => c.disabled = true);
      playTone(200, 0.25, 'sawtooth');
      showToast(STR.timeUp, false);

      try {
        await fetch('api/rooms_answer.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            guid: GUID,
            round: state.round,
            picked: '',
            response_ms: state.answerMs,
            timeout: true
          })
        });
      } catch (e) {}
    }

    function sortPlayers(list) {
      if (!Array.isArray(list)) return [];
      return [...list].sort((a, b) => {
        const scoreA = Number(a.score) || 0;
        const scoreB = Number(b.score) || 0;
        if (scoreB !== scoreA) return scoreB - scoreA;
        const corrA = Number(a.correct) || 0;
        const corrB = Number(b.correct) || 0;
        if (corrB !== corrA) return corrB - corrA;
        const actA = a.status === 'active' ? 1 : 0;
        const actB = b.status === 'active' ? 1 : 0;
        return actB - actA;
      });
    }

    function renderLeaderboard(players) {
      if (Array.isArray(players) && players.length > 0) {
        state.players = players;
      }
      const sorted = sortPlayers(players || state.players || []);
      stageContent.innerHTML = `
        <div class="fs-1">🏆</div>
        <h2 class="stage-title">${STR.roundComplete.replace('{round}', state.round)}</h2>
        <p class="stage-subtitle">${STR.nextRoundIn}</p>
        <div class="table-responsive w-100 mt-2">
          <table class="table table-sm align-middle text-start">
            <thead>
              <tr class="text-secondary small">
                <th>#</th>
                <th>${STR.player}</th>
                <th>${STR.score}</th>
                <th>${STR.status}</th>
              </tr>
            </thead>
            <tbody>
              ${sorted.map((p, idx) => `
                <tr class="${(p.email === ME_EMAIL || String(p.user_id) === String(ME_ID)) ? 'table-active fw-bold' : ''}">
                  <td>${idx === 0 ? '👑 1' : idx + 1}</td>
                  <td>${p.email ? p.email.split('@')[0] : (p.nickname || 'Player')}</td>
                  <td>${Number(p.score) || 0}</td>
                  <td><span class="badge ${p.status === 'eliminated' ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success'} rounded-pill">${p.status === 'eliminated' ? STR.eliminated : STR.active}</span></td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      `;
    }

    async function renderFinalVictory(players) {
      state.phase = 'finished';
      hudStatus.textContent = STR.finished;
      hudStatus.className = 'hud-val text-success';

      let list = Array.isArray(players) && players.length > 0 ? players : state.players;

      // Listenin boş kalmaması için gerekirse sunucudan son durumu çek
      if (!list || list.length === 0) {
        try {
          const res = await fetch('api/rooms_join.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({guid: GUID})
          });
          const data = await res.json();
          if (data && Array.isArray(data.players) && data.players.length > 0) {
            list = data.players;
          }
        } catch(e) {}
      }

      if (list && list.length > 0) {
        state.players = list;
        renderPlayers(list);
      }

      const sorted = sortPlayers(list || []);
      const winner = sorted.length > 0 ? sorted[0] : null;
      const isWinnerMe = winner ? (winner.email === ME_EMAIL || String(winner.user_id) === String(ME_ID)) : false;
      const winnerName = winner ? (winner.email ? winner.email.split('@')[0] : (winner.nickname || 'Player')) : STR.winnerEveryone;
      const winnerScore = winner ? (Number(winner.score) || 0) : 0;

      stageContent.innerHTML = `
        <div class="victory-container">
          <div class="victory-header">
            <div class="victory-trophy">👑</div>
            <h1 class="stage-title victory-title">${STR.winner}</h1>
            <p class="stage-subtitle victory-subtitle">${STR.concludedDesc}</p>
          </div>

          <div class="champion-card ${isWinnerMe ? 'champion-card--me' : ''}">
            <div class="champion-badge-pill">
              <span>🏆 1. ${STR.rank}</span>
              ${isWinnerMe ? `<span class="champion-you-tag">🎉 ${STR.youWon}</span>` : ''}
            </div>
            <div class="champion-name">
              <span>${winnerName}</span>
              ${isWinnerMe ? `<span class="badge bg-warning text-dark fs-6 ms-1">${STR.you}</span>` : ''}
            </div>
            <div class="champion-score">
              <span class="score-num">${winnerScore.toLocaleString()}</span>
              <span class="score-label">${STR.pts}</span>
            </div>
          </div>

          <div class="victory-standings">
            <div class="standings-header">
              <span class="standings-title">📊 ${STR.roomLeaderboard}</span>
              <span class="standings-count">${sorted.length} ${STR.players}</span>
            </div>
            <div class="standings-table-wrap">
              <table class="standings-table">
                <thead>
                  <tr>
                    <th class="col-rank">#</th>
                    <th class="col-player">${STR.player}</th>
                    <th class="col-score text-end">${STR.score}</th>
                    <th class="col-status text-center">${STR.status}</th>
                  </tr>
                </thead>
                <tbody>
                  ${sorted.map((p, idx) => {
                    const isMe = p.email === ME_EMAIL || String(p.user_id) === String(ME_ID);
                    const isElim = p.status === 'eliminated';
                    const pName = p.email ? p.email.split('@')[0] : (p.nickname || 'Player');
                    const pScore = Number(p.score) || 0;
                    let rankBadge = '';
                    if (idx === 0) rankBadge = '<span class="rank-badge rank-1">🥇</span>';
                    else if (idx === 1) rankBadge = '<span class="rank-badge rank-2">🥈</span>';
                    else if (idx === 2) rankBadge = '<span class="rank-badge rank-3">🥉</span>';
                    else rankBadge = `<span class="rank-badge rank-other">${idx + 1}</span>`;

                    return `
                      <tr class="${isMe ? 'row-me' : ''} ${idx === 0 ? 'row-winner' : ''}">
                        <td class="col-rank">${rankBadge}</td>
                        <td class="col-player">
                          <div class="player-cell">
                            <span class="player-name">${pName}</span>
                            ${isMe ? `<span class="badge-you">${STR.you}</span>` : ''}
                          </div>
                        </td>
                        <td class="col-score text-end">
                          <span class="score-badge">${pScore.toLocaleString()} <span class="score-pts">${STR.pts}</span></span>
                        </td>
                        <td class="col-status text-center">
                          ${isElim 
                            ? `<span class="status-pill elim">💀 ${STR.eliminated}</span>`
                            : `<span class="status-pill active">✅ ${STR.finished}</span>`}
                        </td>
                      </tr>
                    `;
                  }).join('')}
                </tbody>
              </table>
            </div>
          </div>

          <div class="d-flex justify-content-center mt-3">
            <a class="btn btn-action" href="rooms.php">← ${STR.backToRooms}</a>
          </div>
        </div>
      `;
    }

    // Join room & bind realtime
    async function initRoom() {
      try {
        const res = await fetch('api/rooms_join.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({guid: GUID})
        });
        const data = await res.json();
        if (!data.ok) {
          hudStatus.textContent = STR.errorJoining;
          return;
        }

        const room = data.room;
        state.isHost = (room.owner_id && ME_ID) ? (String(room.owner_id) === String(ME_ID)) : (String(room.owner_email || '').toLowerCase() === String(ME_EMAIL || '').toLowerCase());
        if (state.isHost && room.status === 'waiting') {
          hostControls?.classList.remove('d-none');
        }

        renderPlayers(data.players || []);
        hudStatus.textContent = room.status === 'waiting' ? STR.waiting : STR.active;
        hudStatus.className = 'hud-val text-info';
      } catch (e) {
        hudStatus.textContent = STR.connError;
      }
    }

    // Pusher Setup
    if (PUSHER_KEY) {
      const pusher = new Pusher(PUSHER_KEY, {
        cluster: PUSHER_CLUSTER,
        authEndpoint: 'api/pusher_auth.php',
        forceTLS: true
      });

      const channel = pusher.subscribe('presence-room-' + GUID);

      channel.bind('room:update', (data) => {
        renderPlayers(data.players || []);
        const meRow = (data.players || []).find(p => p.email === ME_EMAIL);
        if (meRow && meRow.status === 'eliminated') {
          state.eliminated = true;
        }
      });

      channel.bind('room:round', (data) => {
        state.round = data.round;
        hudRound.textContent = `${data.round} / ${data.rounds_total || 50}`;
        state.targetColor = data.question?.target;
        state.gridColors = data.question?.grid || [];
        state.showMs = data.show_ms || 3000;
        state.answerMs = data.answer_ms || 5000;
        state.countdownMs = data.countdown_ms || 3000;
        runCountdown();
      });

      channel.bind('room:leaderboard', (data) => {
        renderLeaderboard(data.players || []);
      });

      channel.bind('room:finished', (data) => {
        renderFinalVictory(data.players || []);
      });
    }

    // Host Start Button Trigger
    startMatchBtn?.addEventListener('click', async () => {
      startMatchBtn.disabled = true;
      startMatchBtn.textContent = STR.starting;
      try {
        await fetch('api/rooms_next_round.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({guid: GUID})
        });
      } catch (e) {}
    });

    // Periodic Keep-Alive Tick
    setInterval(async () => {
      if (state.phase !== 'finished') {
        try {
          const res = await fetch('api/rooms_tick.php?guid=' + encodeURIComponent(GUID));
          const data = await res.json();
          if (data && data.finished) {
            renderFinalVictory(data.players || []);
          }
        } catch(e) {}
      }
    }, 1500);

    initRoom();
  </script>
</body>
</html>
