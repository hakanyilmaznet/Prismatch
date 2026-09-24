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
      min-height: 480px;
      background: var(--arena-panel);
      border: 1px solid var(--arena-border);
      border-radius: var(--arena-radius);
      backdrop-filter: blur(16px);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 32px 20px;
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
      gap: 18px;
      animation: fadeIn 0.3s ease;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(6px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .stage-title {
      font-family: "Baloo 2", sans-serif;
      font-size: 26px;
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
      width: min(540px, 100%);
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
      margin-top: 8px;
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
    }
  </style>
</head>
<body>
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
        nameSpan.textContent = p.email.split('@')[0] + (isMe ? ' (You)' : '') + (isElim ? ' 💀' : '');
        tag.appendChild(nameSpan);

        if (p.score > 0) {
          const scoreBadge = document.createElement('span');
          scoreBadge.className = 'badge bg-dark-subtle text-dark-emphasis ms-1';
          scoreBadge.textContent = p.score + ' pts';
          tag.appendChild(scoreBadge);
        }

        playerList.appendChild(tag);
      });
    }

    // Step 1: Countdown Phase
    function runCountdown() {
      state.phase = 'countdown';
      hudStatus.textContent = 'Get Ready!';
      hudStatus.className = 'hud-val text-info';
      playTone(520, 0.08, 'square');

      let remaining = Math.max(1, Math.round(state.countdownMs / 1000));
      stageContent.innerHTML = `
        <h2 class="stage-title">${STR.rememberColor}</h2>
        <div class="big-countdown" id="countdownNum">${remaining}</div>
        <div class="stage-subtitle">Watch the screen carefully...</div>
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
      hudStatus.textContent = 'Memorize!';
      hudStatus.className = 'hud-val text-warning';

      stageContent.innerHTML = `
        <h2 class="stage-title">${STR.rememberColor}</h2>
        <div class="target-box" style="background: ${state.targetColor}"></div>
        <div class="stage-subtitle">Showing target color...</div>
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
      hudStatus.textContent = state.eliminated ? STR.spectating : 'PICK COLOR!';
      hudStatus.className = 'hud-val ' + (state.eliminated ? 'text-secondary' : 'text-success');

      stageContent.innerHTML = `
        <h2 class="stage-title">${STR.pickColor}</h2>
        <div class="stage-subtitle">${state.eliminated ? 'You are eliminated. Spectating alive players...' : 'Choose fast for maximum score!'}</div>
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

    function renderLeaderboard(players) {
      stageContent.innerHTML = `
        <div class="fs-1">🏆</div>
        <h2 class="stage-title">Round ${state.round} Complete</h2>
        <p class="stage-subtitle">${STR.nextRoundIn}</p>
        <div class="table-responsive w-100 mt-2">
          <table class="table table-sm align-middle text-start">
            <thead>
              <tr class="text-secondary small">
                <th>#</th>
                <th>Player</th>
                <th>Score</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              ${(players || []).map((p, idx) => `
                <tr class="${p.email === ME_EMAIL ? 'table-active fw-bold' : ''}">
                  <td>${idx === 0 ? '👑 1' : idx + 1}</td>
                  <td>${p.email.split('@')[0]}</td>
                  <td>${p.score}</td>
                  <td><span class="badge ${p.status === 'eliminated' ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success'} rounded-pill">${p.status}</span></td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      `;
    }

    function renderFinalVictory(players) {
      state.phase = 'finished';
      hudStatus.textContent = STR.finished;
      hudStatus.className = 'hud-val text-success';
      const winner = players && players[0] ? players[0] : null;

      stageContent.innerHTML = `
        <div class="display-3 mb-2">🎉👑🎉</div>
        <h1 class="stage-title">${STR.winner}</h1>
        <div class="fs-4 fw-bold text-warning mb-2">${winner ? winner.email.split('@')[0] : 'Everyone'}</div>
        <p class="stage-subtitle">The match has concluded. Great performance by all players!</p>
        <div class="d-flex gap-2 mt-3">
          <a class="btn btn-action" href="rooms.php">← Back to Rooms</a>
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
          hudStatus.textContent = 'Error Joining';
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
        hudStatus.textContent = 'Connection Error';
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
      startMatchBtn.textContent = 'Starting...';
      try {
        await fetch('api/rooms_next_round.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({guid: GUID})
        });
      } catch (e) {}
    });

    // Periodic Keep-Alive Tick
    setInterval(() => {
      if (state.phase !== 'finished') {
        fetch('api/rooms_tick.php?guid=' + encodeURIComponent(GUID)).catch(() => {});
      }
    }, 1500);

    initRoom();
  </script>
</body>
</html>
