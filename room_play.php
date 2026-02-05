<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/i18n.php';

session_name(SESSION_NAME);
if (PHP_VERSION_ID >= 70300) {
  session_set_cookie_params([
    'httponly' => true,
    'secure' => COOKIE_SECURE,
    'samesite' => 'Lax',
  ]);
} else {
  @ini_set('session.cookie_httponly', '1');
  if (COOKIE_SECURE) @ini_set('session.cookie_secure', '1');
  session_set_cookie_params(0, '/', '', COOKIE_SECURE, true);
}
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$lang = function_exists('get_lang') ? get_lang() : 'en';
$dir  = function_exists('lang_dir') ? lang_dir($lang) : 'ltr';
$userEmail = $_SESSION['user_email'] ?? null;
$showLangPicker = true;

function tt(string $key, string $fallback = ''): string {
  $v = t($key);
  if ($v === $key) return $fallback !== '' ? $fallback : $key;
  return $v;
}

if (!$userEmail) {
  $next = 'room_play.php?guid=' . rawurlencode($guid);
  header('Location: login.php?next=' . rawurlencode($next));
  exit;
}

$guid = trim((string)($_GET['guid'] ?? ''));
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
  <link href="css/bootstrap.min.css" rel="stylesheet" />
  <link href="css/theme.css" rel="stylesheet" />
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
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<main class="container py-4" aria-label="<?= htmlspecialchars(tt('room_live_title', 'Room Match')) ?>">
  <section class="row g-2 mb-3" aria-label="<?= htmlspecialchars(tt('room_round', 'Round')) ?>">
    <div class="col-6 col-md-3">
      <div class="card text-center">
        <div class="card-body py-2">
          <div class="small text-muted"><?= htmlspecialchars(tt('hud_stage', 'Stage')) ?></div>
          <div class="fw-semibold" id="hudLevel">-</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center">
        <div class="card-body py-2">
          <div class="small text-muted"><?= htmlspecialchars(tt('hud_answer_time', 'Answer Time')) ?></div>
          <div class="fw-semibold" id="hudTime">-</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center">
        <div class="card-body py-2">
          <div class="small text-muted"><?= htmlspecialchars(tt('hud_correct', 'Perfect Matches')) ?></div>
          <div class="fw-semibold" id="hudCorrect">0</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center">
        <div class="card-body py-2">
          <div class="small text-muted"><?= htmlspecialchars(tt('hud_target_show', 'Target Show')) ?></div>
          <div class="fw-semibold" id="hudShow">-</div>
        </div>
      </div>
    </div>
  </section>

  <section class="card mb-3" id="stage" aria-label="<?= htmlspecialchars(tt('room_live_title', 'Room Match')) ?>">
    <div class="card-body position-relative">
      <div class="alert alert-info py-2 px-3 d-none" id="toast" aria-live="polite"></div>
      <div class="d-flex flex-column align-items-center text-center gap-3" id="center">
        <img src="logo.svg" alt="<?= htmlspecialchars(tt('app_name', 'Prismatch')) ?> logo" width="84" height="84" class="rounded" />
        <div class="h4 fw-bold"><?= htmlspecialchars(tt('room_live_title', 'Room Match')) ?></div>
        <div class="text-muted" id="roomInfo"><?= htmlspecialchars(tt('room_waiting', 'Waiting for host...')) ?></div>
        <div class="d-flex flex-wrap gap-2">
          <button class="btn btn-primary" id="startBtn" type="button"><?= htmlspecialchars(tt('room_start', 'Start')) ?></button>
          <a class="btn btn-outline-secondary" href="rooms.php"><?= htmlspecialchars(tt('rooms_back', 'Back to rooms')) ?></a>
        </div>
      </div>
    </div>
  </section>

  <section class="d-flex justify-content-between align-items-center flex-wrap gap-2" aria-label="<?= htmlspecialchars(tt('controls', 'Controls')) ?>">
    <span class="badge text-bg-secondary" id="statusBadge"><?= htmlspecialchars(tt('status_ready', 'Ready.')) ?></span>
    <div class="d-flex flex-wrap gap-2">
      <span class="badge text-bg-success" id="selfStatusBadge"><?= htmlspecialchars(tt('room_active', 'Active')) ?></span>
      <span class="badge text-bg-secondary" id="playersBadge"></span>
    </div>
  </section>
</main>

<div class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-dark bg-opacity-75" id="answerOverlay" hidden role="dialog" aria-modal="true" aria-live="polite">
  <div class="card text-center" id="answerCard">
    <div class="card-body">
      <div class="mb-2">
        <img id="answerIcon" src="success-checkmark.svg" alt="" aria-hidden="true" width="72" height="72" />
      </div>
      <div class="h5 mb-1" id="answerText"></div>
      <p class="text-muted mb-0"><?= htmlspecialchars(tt('badge_answer', 'Pick the match!')) ?></p>
    </div>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>

<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
<script>
const GUID = <?= json_encode($guid) ?>;
const ME = <?= json_encode($userEmail) ?>;
const P_KEY = <?= json_encode(PUSHER_KEY) ?>;
const P_CLUSTER = <?= json_encode(PUSHER_CLUSTER) ?>;

const STR = {
  appName: <?= json_encode(tt('app_name', 'Prismatch')) ?>,
  badgeReady: <?= json_encode(tt('badge_ready', 'Ready. Countdown…')) ?>,
  badgeShow: <?= json_encode(tt('badge_showing_target', 'Showing target…')) ?>,
  badgeAnswer: <?= json_encode(tt('badge_answer', 'Pick the match!')) ?>,
  badgeCorrect: <?= json_encode(tt('badge_correct', 'Perfect match! Next stage…')) ?>,
  badgeWrong: <?= json_encode(tt('badge_wrong', 'Wrong match. Game over.')) ?>,
  badgeTimeUp: <?= json_encode(tt('badge_timeup', 'Time’s up. Game over.')) ?>,
  toastCorrect: <?= json_encode(tt('toast_correct', '✅ Perfect Match!')) ?>,
  toastWrong: <?= json_encode(tt('toast_wrong', '❌ Wrong Match!')) ?>,
  toastTimeUp: <?= json_encode(tt('toast_timeup', '⏰ Time’s up!')) ?>,
  toastPick: <?= json_encode(tt('toast_pick', '⏱️ Pick within 5 seconds')) ?>,
  countdownHelp: <?= json_encode(tt('countdown_help', 'Remember the shown color, then pick it from the grid.')) ?>,
  rememberThis: <?= json_encode(tt('remember_this', 'Remember this color.')) ?>,
  questionTitle: <?= json_encode(tt('question_pick_target', 'Which color was shown? Pick the target.')) ?>,
  questionHint: <?= json_encode(tt('question_hint', 'Use Tab/Shift+Tab and Enter/Space to pick.')) ?>,
  a11yColorOption: <?= json_encode(tt('a11y_color_option', 'Color option {n}')) ?>,
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

function updateHUD(answerLeftMs = null){
  hudLevel.textContent = `${state.round || '-'}`;
  hudCorrect.textContent = String(state.correct);
  hudShow.textContent = `${(state.targetShowMs / 1000).toFixed(2)}s`;

  const shown = (answerLeftMs == null ? state.answerMs : Math.max(0, answerLeftMs));
  hudTime.textContent = `${(shown / 1000).toFixed(1)}s`;
}

function setBadge(text, type = ''){
  elBadge.textContent = text;
  elBadge.classList.remove('text-bg-danger', 'text-bg-success', 'text-bg-secondary');
  if (type === 'error') elBadge.classList.add('text-bg-danger');
  else if (type === 'success') elBadge.classList.add('text-bg-success');
  else elBadge.classList.add('text-bg-secondary');
}

function toast(msg, autoHideMs = 1100){
  if (!msg) {
    elToast.textContent = "";
    elToast.classList.add("d-none");
    return;
  }
  elToast.textContent = msg;
  elToast.classList.remove("d-none");
  setSafeTimeout(() => elToast.classList.add("d-none"), autoHideMs);
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
  answerCard.classList.remove('border-success', 'border-danger', 'border-3');
  if (isRight) {
    answerCard.classList.add('border-success', 'border-3');
  } else {
    answerCard.classList.add('border-danger', 'border-3');
  }
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
  wrap.className = "d-flex flex-column align-items-center text-center gap-3";
  nodes.forEach(n => wrap.appendChild(n));
  return wrap;
}
function h1(text){
  const d = document.createElement("div");
  d.className = "h4 fw-bold";
  d.textContent = text;
  return d;
}
function p(text){
  const d = document.createElement("div");
  d.className = "text-muted";
  d.textContent = text;
  return d;
}

function buildLeaderboardCard(players){
  const wrap = document.createElement('div');
  wrap.className = 'card w-100';
  const body = document.createElement('div');
  body.className = 'card-body';

  const title = document.createElement('div');
  title.className = 'h6 mb-2';
  title.textContent = STR.roomLeaderboard || 'Leaderboard';
  body.appendChild(title);

  const result = buildLeaderboardResult();
  if (result) body.appendChild(result);

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
    tr.className = '';
    if (p.email === ME) {
      tr.classList.add('table-active');
    }
    tr.dataset.email = p.email;
    const statusLabel = p.status === 'eliminated' ? STR.roomEliminated : STR.roomActive;
    const statusClass = p.status === 'eliminated' ? 'secondary' : 'success';
    const statusHtml = `<span class="badge text-bg-${statusClass}">${statusLabel}</span>`;
    tr.innerHTML = `<td>${idx+1}</td><td>${p.email}</td><td>${p.score}</td><td>${statusHtml}</td>`;
    const prevRow = state.leaderboardPrev.get(p.email);
    if (!prevRow || prevRow.rank !== idx || prevRow.score !== p.score || prevRow.status !== p.status) {
      tr.classList.add('table-warning');
    }
    next.set(p.email, {rank: idx, score: p.score, status: p.status});
    tbody.appendChild(tr);
  });
  state.leaderboardPrev = next;

  table.appendChild(thead);
  table.appendChild(tbody);
  tableWrap.appendChild(table);
  body.appendChild(tableWrap);
  wrap.appendChild(body);
  return wrap;
}

function buildLeaderboardResult(){
  if (state.lastAnswerCorrect === true) {
    const row = document.createElement('div');
    row.className = 'alert alert-success py-2 px-3';
    row.innerHTML = `<img src="success-checkmark.svg" alt="" aria-hidden="true" width="20" height="20" class="me-2" /><span>${STR.badgeCorrect}</span>`;
    return row;
  }
  if (state.lastAnswerCorrect === false) {
    const row = document.createElement('div');
    row.className = 'alert alert-danger py-2 px-3';
    const msg = state.eliminated ? STR.badgeWrong : STR.badgeTimeUp;
    row.innerHTML = `<img src="error-x.svg" alt="" aria-hidden="true" width="20" height="20" class="me-2" /><span>${msg}</span>`;
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
  iconWrap.className = 'mb-2';
  iconWrap.innerHTML = `<img src="success-checkmark.svg" alt="${STR.roomFinishedIconAlt}" width="72" height="72" />`;
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
  selfStatusBadge.classList.remove('text-bg-danger', 'text-bg-success', 'text-bg-secondary');
  if (stateLabel === 'eliminated') {
    selfStatusBadge.textContent = STR.roomEliminated;
    selfStatusBadge.classList.add('text-bg-danger');
    return;
  }
  if (stateLabel === 'finished') {
    selfStatusBadge.textContent = STR.roomFinished;
    selfStatusBadge.classList.add('text-bg-success');
    return;
  }
  selfStatusBadge.textContent = STR.roomActive;
  selfStatusBadge.classList.add('text-bg-success');
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

  let t = Math.max(1, Math.round((state.countdownMs || 3000) / 1000));
  const cd = document.createElement("div");
  cd.className = "display-1 fw-bold";
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
  card.className = "rounded border w-100";
  card.style.background = state.targetColor || '#000';
  card.style.aspectRatio = "16 / 9";

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
  qWrap.className = "text-center";

  const q = document.createElement("div");
  q.className = "fw-semibold";
  q.textContent = STR.questionTitle;
  const hint = document.createElement("div");
  hint.className = "text-muted small";
  hint.textContent = STR.questionHint;
  qWrap.appendChild(q);
  qWrap.appendChild(hint);

  const grid = document.createElement("div");
  grid.className = "d-grid gap-2 w-100";
  const gridCount = state.gridColors.length;
  const gridSize = Math.round(Math.sqrt(gridCount));
  if (gridSize * gridSize === gridCount) {
    grid.style.gridTemplateColumns = `repeat(${gridSize}, 1fr)`;
  }

  const buttons = [];
  state.gridColors.forEach((c, idx) => {
    const btn = document.createElement("button");
    btn.className = "btn p-0 border border-2 rounded-3 w-100";
    btn.type = "button";
    btn.style.background = c;
    btn.style.aspectRatio = "1 / 1";
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
  if (state.answered || state.eliminated) return;
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
    btn.classList.add("border-success", "border-3");
    setBadge(STR.badgeCorrect);
    state.correct += 1;
    state.lastAnswerCorrect = true;
  } else {
    btn.classList.add("border-danger", "border-3");
    setBadge(STR.badgeWrong);
    state.eliminated = true;
    setSelfStatus('eliminated');
    state.lastAnswerCorrect = false;
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
  renderIntermission();
});

channel.bind('room:finished', () => {
  setBadge(STR.statusFinished);
  setSelfStatus('finished');
  renderFinished();
});

joinRoom().catch(() => setBadge(STR.joinFailed, 'error'));
setInterval(() => {
  fetch('api/rooms_tick.php?guid=' + encodeURIComponent(GUID)).catch(() => {});
}, 2000);
</script>
</body>
</html>


