<?php
// Shared site header
if (!function_exists('tt')) {
  function tt(string $key, string $fallback = ''): string {
    if (function_exists('t')) {
      $v = t($key);
      if ($v !== $key) return $v;
    }
    return $fallback !== '' ? $fallback : $key;
  }
}

$showLangPicker = isset($showLangPicker) ? (bool)$showLangPicker : false;
if (!isset($userEmail)) {
  $userEmail = $_SESSION['user_email'] ?? null;
}
$currentPage = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$isPlay = ($currentPage === 'play.php' && empty($_GET['daily']));
$isDaily = ($currentPage === 'play.php' && !empty($_GET['daily']));
$isLeaderboard = ($currentPage === 'daily_leaderboard.php');
$isSessions = ($currentPage === 'games.php' || $currentPage === 'game.php');
?>
<style>
  :root{ --pm-header-offset: 0px; --pm-icon-filter: none; }
  body.pm-has-fixed-header{ padding-top: var(--pm-header-offset); }
  .pm-header{
    display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:nowrap;
    background: linear-gradient(180deg, rgba(255,255,255,0.96), rgba(247,247,247,0.86));
    border: 1px solid rgba(0,0,0,0.08);
    border-radius: 22px;
    padding: 10px 14px;
    box-shadow: 0 22px 70px rgba(12,14,20,0.18);
    position: fixed;
    top: max(12px, env(safe-area-inset-top));
    left: 50%;
    transform: translateX(-50%);
    width: min(1180px, calc(100% - 28px));
    z-index: 1000;
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
  }
  [data-bs-theme="dark"] .pm-header{
    background: linear-gradient(180deg, rgba(10,12,18,0.94), rgba(10,12,18,0.82));
    border-color: rgba(255,255,255,0.10);
    box-shadow: 0 26px 80px rgba(0,0,0,0.6);
  }
  [data-bs-theme="dark"]{ --pm-icon-filter: invert(1) brightness(1.1); }
  .pm-header::after{
    content:"";
    position:absolute;
    inset:0;
    border-radius: 22px;
    pointer-events:none;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,0.2);
    opacity:.35;
  }
  [data-bs-theme="dark"] .pm-header::after{ box-shadow: inset 0 0 0 1px rgba(255,255,255,0.08); opacity:.5; }
  .pm-brand{ display:flex; align-items:center; gap:12px; min-width:0; text-decoration:none; color: inherit; }
  .pm-brand img{
    width:42px; height:42px; border-radius: 12px;
    border:1px solid rgba(0,0,0,0.1);
    box-shadow: 0 10px 24px rgba(10,12,18,0.18);
  }
  [data-bs-theme="dark"] .pm-brand img{ border-color: rgba(255,255,255,0.14); box-shadow: 0 12px 30px rgba(0,0,0,0.45); }
  .pm-brand .pm-name{ font-family:"Space Grotesk","Segoe UI","Helvetica Neue",sans-serif; font-size: 18px; font-weight: 700; letter-spacing:.2px; }
  .pm-brand .pm-tag{ color: rgba(30,35,50,0.62); font-size: 12px; }
  [data-bs-theme="dark"] .pm-brand .pm-tag{ color: rgba(230,234,245,0.75); }

  .pm-nav{ gap:10px; }
  .pm-nav .nav-link{
    display:inline-flex; align-items:center; justify-content:center; gap:8px;
    padding: 9px 14px;
    border-radius: 999px;
    border:1px solid rgba(0,0,0,0.1);
    background: rgba(0,0,0,0.04);
    color: #101318;
    font-weight: 600;
    letter-spacing:.2px;
    transition: transform 140ms ease, border-color 140ms ease, box-shadow 140ms ease, background 140ms ease;
    white-space: nowrap;
    text-decoration:none;
  }
  .pm-nav .nav-link:hover{ transform: translateY(-1px); border-color: rgba(0,0,0,0.2); box-shadow: 0 10px 20px rgba(10,12,18,0.12); }
  .pm-nav .nav-link.active{
    border-color: rgba(20,120,92,0.45);
    box-shadow: 0 0 0 2px rgba(20,120,92,0.18), 0 10px 20px rgba(10,12,18,0.12);
  }
  .pm-nav .nav-link.pm-primary{
    padding: 10px 18px;
    background: linear-gradient(135deg, #ff6f52 0%, #ffd08a 100%);
    border-color: rgba(255,111,82,0.7);
    color:#101318;
    box-shadow: 0 18px 36px rgba(255,111,82,0.35), 0 0 0 2px rgba(255,111,82,0.18) inset;
  }
  [data-bs-theme="dark"] .pm-nav .nav-link{
    border-color: rgba(255,255,255,0.16);
    background: rgba(255,255,255,0.08);
    color: #f6f7fb;
  }
  [data-bs-theme="dark"] .pm-nav .nav-link:hover{ border-color: rgba(255,255,255,0.28); box-shadow: 0 12px 24px rgba(0,0,0,0.35); }
  .navbar-toggler{
    border:1px solid rgba(0,0,0,0.1);
    background: rgba(0,0,0,0.04);
    color:#0f1117;
    border-radius: 12px;
    padding: 8px 10px;
  }
  .navbar-toggler:focus{ box-shadow: 0 0 0 2px rgba(20,120,92,0.22); }
  [data-bs-theme="dark"] .navbar-toggler{
    border-color: rgba(255,255,255,0.16);
    background: rgba(255,255,255,0.08);
    color:#fff;
  }
  .navbar-toggler-icon{
    width:18px; height:18px; background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='18' height='18' fill='none' stroke='%2310181f' stroke-linecap='round' stroke-width='2' viewBox='0 0 24 24'><path d='M4 7h16'/><path d='M4 12h16'/><path d='M4 17h16'/></svg>");
  }
  [data-bs-theme="dark"] .navbar-toggler-icon{
    background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='18' height='18' fill='none' stroke='white' stroke-linecap='round' stroke-width='2' viewBox='0 0 24 24'><path d='M4 7h16'/><path d='M4 12h16'/><path d='M4 17h16'/></svg>");
  }
  .bi-icon{
    width:16px;
    height:16px;
    display:inline-block;
    filter: var(--pm-icon-filter);
  }
  .pm-nav .nav-link.pm-primary .bi-icon,
  .btn.primary .bi-icon{ filter: none; }
  .pm-theme-toggle{ padding: 8px 12px; border-radius: 999px; }
  .pm-theme-toggle svg{ width:16px; height:16px; }

  .langWrap{ position:relative; display:inline-flex; width: 200px; max-width: 100%; }
  .visually-hidden{ position:absolute!important; width:1px;height:1px; padding:0;margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
  .langBtn{
    appearance:none; -webkit-appearance:none;
    display:flex; align-items:center; justify-content:space-between; gap:10px;
    width:100%;
    padding:10px 14px;
    border-radius:12px;
    border:1px solid rgba(0,0,0,0.12);
    background: rgba(0,0,0,0.04);
    color:#0f1117;
    cursor:pointer;
    outline:none;
  }
  .langBtn:hover{ border-color: rgba(0,0,0,0.22); }
  .langBtn:focus{ border-color: rgba(20,120,92,.6); box-shadow: 0 0 0 3px rgba(20,120,92,.18); }
  [data-bs-theme="dark"] .langBtn{
    border-color: rgba(255,255,255,.18);
    background: rgba(255,255,255,0.08);
    color:#fff;
  }
  [data-bs-theme="dark"] .langBtn:hover{ border-color: rgba(255,255,255,.28); }
  .langBtnLeft{ display:flex; align-items:center; gap:10px; min-width:0; }
  .langBtnText{ font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .langCaret{ width:16px; height:16px; background:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='black' opacity='0.6' viewBox='0 0 24 24'><path d='M7 10l5 5 5-5z'/></svg>") no-repeat center/16px 16px; flex:0 0 16px; }
  [data-bs-theme="dark"] .langCaret{ background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='white' opacity='0.75' viewBox='0 0 24 24'><path d='M7 10l5 5 5-5z'/></svg>"); }
  .langPop{ position:fixed; z-index:30000; width: min(92vw, 360px); border-radius:18px; border:1px solid rgba(0,0,0,.12);
    background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,248,248,.95)); backdrop-filter: blur(16px);
    box-shadow: 0 18px 60px rgba(10,12,18,.2); overflow:hidden; pointer-events:auto; }
  [data-bs-theme="dark"] .langPop{
    border-color: rgba(255,255,255,.18);
    background: linear-gradient(180deg, rgba(8,16,23,.96), rgba(10,18,26,.94));
    box-shadow: 0 18px 60px rgba(0,0,0,.55);
  }
  .langPop[hidden]{ display:none !important; pointer-events:none !important; }
  .langPopHeader{ padding:12px; border-bottom:1px solid rgba(0,0,0,.08); display:grid; gap:10px; }
  .langTitle{ font-size:13px; opacity:.7; font-weight:700; letter-spacing:.2px; color:#0f1117; }
  .langSearch{ width:100%; padding:10px 12px; border-radius:12px; border:1px solid rgba(0,0,0,.12); background: rgba(0,0,0,.03); color:#0f1117; outline:none; }
  .langSearch:focus{ border-color: rgba(20,120,92,.65); box-shadow: 0 0 0 3px rgba(20,120,92,.16); }
  [data-bs-theme="dark"] .langPopHeader{ border-bottom-color: rgba(255,255,255,.10); }
  [data-bs-theme="dark"] .langTitle{ color:#fff; opacity:.85; }
  [data-bs-theme="dark"] .langSearch{ border-color: rgba(255,255,255,.18); background: rgba(255,255,255,.08); color:#fff; }
  .langList{ max-height: 340px; overflow:auto; padding:6px; }
  .langItem{ width:100%; display:grid; grid-template-columns: 1fr auto; align-items:center; gap:10px; padding:10px 10px; border-radius:14px; border:1px solid transparent; background:transparent; color:#0f1117; cursor:pointer; text-align:left; }
  html[dir="rtl"] .langItem{ text-align:right; }
  .langItem:hover{ background: rgba(20,120,92,.08); border-color: rgba(20,120,92,.18); }
  .langItem[aria-selected="true"]{ background: rgba(20,120,92,.14); border-color: rgba(20,120,92,.30); }
  .langLabelCell{ min-width:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-weight:650; }
  .langMetaCell{ opacity:.7; font-size:12px; display:flex; align-items:center; gap:8px; }
  .langCheck{ opacity:.9; }
  .langNoRes{ padding:12px; color: rgba(15,17,23,.6); font-size:13px; }
  [data-bs-theme="dark"] .langItem{ color:#fff; }
  [data-bs-theme="dark"] .langNoRes{ color: rgba(255,255,255,.72); }
  @media (max-width: 991.98px){
    .pm-header{ border-radius: 16px; padding: 10px 12px; }
    .pm-nav{ flex-direction:column; align-items:stretch; gap:10px; padding: 14px 0 6px; }
    .pm-nav .nav-link, .pm-theme-toggle, .langWrap, .langBtn{ width:100%; }
  }
</style>

<nav class="pm-header navbar navbar-expand-lg">
  <a class="pm-brand" href="index.php">
    <img src="logo.svg" alt="<?= htmlspecialchars(tt('app_name', 'Prismatch')) ?>" width="44" height="44" />
    <div>
      <div class="pm-name"><?= htmlspecialchars(tt('app_name', 'Prismatch')) ?></div>
    </div>
  </a>
  <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#pmNav" aria-controls="pmNav" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
  </button>
  <div class="collapse navbar-collapse" id="pmNav">
    <ul class="navbar-nav pm-nav me-auto gap-2">
      <li class="nav-item">
        <a class="nav-link pm-primary<?= $isPlay ? ' active' : '' ?>" href="play.php">
          <img class="bi-icon" src="bootstrap-icons/play-fill.svg" alt="" aria-hidden="true" />
          <?= htmlspecialchars(tt('home_cta_play', 'Play now')) ?>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link<?= $isDaily ? ' active' : '' ?>" href="play.php?daily=1">
          <img class="bi-icon" src="bootstrap-icons/calendar2-check.svg" alt="" aria-hidden="true" />
          <?= htmlspecialchars(tt('home_cta_daily', 'Daily Challenge')) ?>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link<?= $isLeaderboard ? ' active' : '' ?>" href="daily_leaderboard.php">
          <img class="bi-icon" src="bootstrap-icons/trophy-fill.svg" alt="" aria-hidden="true" />
          <?= htmlspecialchars(tt('daily_leaderboard_title', 'Leaderboard')) ?>
        </a>
      </li>
      <?php if (!empty($userEmail)): ?>
        <li class="nav-item">
          <a class="nav-link<?= $isSessions ? ' active' : '' ?>" href="games.php">
            <img class="bi-icon" src="bootstrap-icons/clock-history.svg" alt="" aria-hidden="true" />
            <?= htmlspecialchars(tt('btn_view_history', 'My Sessions')) ?>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="logout.php">
            <img class="bi-icon" src="bootstrap-icons/box-arrow-right.svg" alt="" aria-hidden="true" />
            <?= htmlspecialchars(tt('btn_logout', 'Logout')) ?>
          </a>
        </li>
      <?php else: ?>
        <li class="nav-item">
          <a class="nav-link" href="login.php">
            <img class="bi-icon" src="bootstrap-icons/box-arrow-in-right.svg" alt="" aria-hidden="true" />
            <?= htmlspecialchars(tt('home_cta_login', 'Sign in')) ?>
          </a>
        </li>
      <?php endif; ?>
    </ul>

    <div class="d-flex align-items-center gap-2 ms-lg-3">
      <?php if ($currentPage === 'index.php'): ?>
        <button class="btn btn-outline-secondary btn-sm pm-theme-toggle" type="button" id="themeToggle"
                aria-label="<?= htmlspecialchars(tt('theme_toggle', 'Toggle theme')) ?>"
                data-label-light="<?= htmlspecialchars(tt('theme_light', 'Light')) ?>"
                data-label-dark="<?= htmlspecialchars(tt('theme_dark', 'Dark')) ?>"
                data-icon-light="bootstrap-icons/brightness-high-fill.svg"
                data-icon-dark="bootstrap-icons/moon-stars-fill.svg">
          <img class="bi-icon" id="themeToggleIcon" src="bootstrap-icons/moon-stars-fill.svg" alt="" aria-hidden="true" />
          <span id="themeToggleText"><?= htmlspecialchars(tt('theme_light', 'Light')) ?></span>
        </button>
      <?php endif; ?>

      <?php if ($showLangPicker): ?>
        <div class="langWrap" id="langPicker" data-align="end" aria-label="<?= htmlspecialchars(tt('language', 'Language')) ?>">
          <select id="langSelect" class="visually-hidden" aria-label="<?= htmlspecialchars(tt('language', 'Language')) ?>">
            <?php
              $sel = $lang ?: 'en';
              foreach (supported_languages() as $code => $name) {
                $selected = ($code === $sel) ? ' selected' : '';
                echo "<option value='".htmlspecialchars($code, ENT_QUOTES)."'{$selected}>".htmlspecialchars($name, ENT_QUOTES)."</option>";
              }
            ?>
          </select>

          <button type="button" class="langBtn" id="langBtn" aria-haspopup="listbox" aria-expanded="false" aria-controls="langList">
            <span class="langBtnLeft">
              <img class="bi-icon" src="bootstrap-icons/translate.svg" alt="" aria-hidden="true" />
              <span class="langBtnText" id="langBtnText">-</span>
            </span>
            <span class="langCaret" aria-hidden="true"></span>
          </button>

          <div class="langPop" id="langPop" role="dialog" aria-modal="false" hidden>
            <div class="langPopHeader">
              <div class="langTitle" id="langTitle"><?= htmlspecialchars(tt('language', 'Language')) ?></div>
              <input id="langSearch" class="langSearch" type="search" autocomplete="off"
                     placeholder="<?= htmlspecialchars(tt('search_language', 'Search...')) ?>"
                     aria-label="<?= htmlspecialchars(tt('search_language', 'Search language')) ?>" />
            </div>
            <div class="langList" id="langList" role="listbox" tabindex="-1" aria-labelledby="langTitle"></div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</nav>
<script>
(function(){
  const header = document.querySelector('.pm-header');
  if (!header || !document.body) return;

  function setOffset(){
    const rect = header.getBoundingClientRect();
    const extra = 12;
    const offset = Math.max(0, Math.ceil(rect.height + extra));
    document.documentElement.style.setProperty('--pm-header-offset', offset + 'px');
  }

  document.body.classList.add('pm-has-fixed-header');
  setOffset();
  window.addEventListener('resize', setOffset, {passive:true});

  const themeBtn = document.getElementById('themeToggle');
  const themeTxt = document.getElementById('themeToggleText');
  const themeIcon = document.getElementById('themeToggleIcon');
  if (themeBtn){
    const labelLight = themeBtn.getAttribute('data-label-light') || 'Light';
    const labelDark = themeBtn.getAttribute('data-label-dark') || 'Dark';
    const iconLight = themeBtn.getAttribute('data-icon-light') || '';
    const iconDark = themeBtn.getAttribute('data-icon-dark') || '';
    const key = 'pm-theme';
    const applyTheme = (t) => {
      document.documentElement.setAttribute('data-bs-theme', t);
      if (themeTxt) themeTxt.textContent = (t === 'dark') ? labelLight : labelDark;
      if (themeIcon) themeIcon.src = (t === 'dark') ? iconLight : iconDark;
    };
    const stored = localStorage.getItem(key);
    const current = stored || document.documentElement.getAttribute('data-bs-theme') || 'dark';
    applyTheme(current);
    themeBtn.addEventListener('click', () => {
      const next = (document.documentElement.getAttribute('data-bs-theme') === 'dark') ? 'light' : 'dark';
      localStorage.setItem(key, next);
      applyTheme(next);
    });
  }
})();
</script>
