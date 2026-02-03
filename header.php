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
  :root{ --pm-header-offset: 0px; }
  body.pm-has-fixed-header{ padding-top: var(--pm-header-offset); }
  .pm-header{
    display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;
    background: linear-gradient(180deg, rgba(255,255,255,0.86), rgba(255,255,255,0.7));
    border: 1px solid rgba(0,0,0,0.06);
    border-radius: 18px;
    padding: 10px 14px;
    box-shadow: 0 22px 60px rgba(10,12,18,0.18);
    position: fixed;
    top: max(12px, env(safe-area-inset-top));
    left: 50%;
    transform: translateX(-50%);
    width: min(1120px, calc(100% - 32px));
    z-index: 1000;
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
  }
  [data-bs-theme="dark"] .pm-header{
    background: linear-gradient(180deg, rgba(12,14,20,0.92), rgba(12,14,20,0.78));
    border-color: rgba(255,255,255,0.08);
    box-shadow: 0 26px 70px rgba(0,0,0,0.55);
  }
  .pm-brand{ display:flex; align-items:center; gap:12px; min-width:0; text-decoration:none; color: inherit; }
  .pm-brand img{ width:42px; height:42px; border-radius: 12px; border:1px solid rgba(0,0,0,0.08); box-shadow: 0 10px 24px rgba(10,12,18,0.18); }
  [data-bs-theme="dark"] .pm-brand img{ border-color: rgba(255,255,255,0.12); box-shadow: 0 12px 30px rgba(0,0,0,0.45); }
  .pm-brand .pm-name{ font-family:"Space Grotesk","Segoe UI","Helvetica Neue",sans-serif; font-size: 18px; font-weight: 700; letter-spacing:.2px; }
  .pm-brand .pm-tag{ color: rgba(30,35,50,0.6); font-size: 12px; }
  [data-bs-theme="dark"] .pm-brand .pm-tag{ color: rgba(230,234,245,0.7); }

  .pm-nav{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
  .pm-menu-btn{
    display:none;
    appearance:none;
    border:1px solid rgba(0,0,0,0.1);
    background: rgba(0,0,0,0.04);
    color:#0f1117;
    border-radius: 12px;
    padding: 10px 12px;
    cursor:pointer;
  }
  [data-bs-theme="dark"] .pm-menu-btn{
    border-color: rgba(255,255,255,0.16);
    background: rgba(255,255,255,0.08);
    color:#fff;
  }
  .pm-menu-btn svg{ width:18px; height:18px; }
  .pm-btn{
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
  .pm-btn:hover{ transform: translateY(-1px); border-color: rgba(0,0,0,0.2); box-shadow: 0 10px 20px rgba(10,12,18,0.14); }
  .pm-btn.primary{
    background: linear-gradient(135deg, #ff7d5d, #ffd08a);
    border-color: rgba(255,125,93,0.6);
    color:#101318;
    box-shadow: 0 16px 36px rgba(255,125,93,0.35);
  }
  .pm-btn.active{
    border-color: rgba(20,120,92,0.45);
    box-shadow: 0 0 0 2px rgba(20,120,92,0.18), 0 10px 20px rgba(10,12,18,0.14);
  }
  [data-bs-theme="dark"] .pm-btn{
    border-color: rgba(255,255,255,0.16);
    background: rgba(255,255,255,0.08);
    color: #f6f7fb;
  }
  [data-bs-theme="dark"] .pm-btn:hover{ border-color: rgba(255,255,255,0.28); box-shadow: 0 12px 24px rgba(0,0,0,0.35); }
  .pm-theme-toggle{
    padding: 8px 12px;
    border-radius: 999px;
  }
  .pm-theme-toggle svg{ width:16px; height:16px; }

  .langWrap{ position:relative; display:inline-flex; width: 220px; max-width: 100%; }
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
  @media (max-width: 700px){
    .pm-header{ border-radius: 14px; }
    .pm-menu-btn{ display:inline-flex; align-items:center; justify-content:center; }
    .pm-nav{
      position:absolute;
      top: calc(100% + 10px);
      left: 0;
      right: 0;
      width:100%;
      justify-content:flex-start;
      flex-direction:column;
      align-items:stretch;
      gap: 8px;
      padding: 12px;
      background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(248,248,248,.94));
      border: 1px solid rgba(0,0,0,.08);
      border-radius: 14px;
      box-shadow: 0 18px 60px rgba(10,12,18,.2);
      display:none;
    }
    [data-bs-theme="dark"] .pm-nav{
      background: linear-gradient(180deg, rgba(8,16,23,.96), rgba(10,18,26,.94));
      border-color: rgba(255,255,255,.12);
      box-shadow: 0 18px 60px rgba(0,0,0,.55);
    }
    .pm-header.is-open .pm-nav{ display:flex; }
  }
</style>

<header class="pm-header">
  <a class="pm-brand" href="index.php">
    <img src="logo.svg" alt="<?= htmlspecialchars(tt('app_name', 'Prismatch')) ?>" width="44" height="44" />
    <div>
      <div class="pm-name"><?= htmlspecialchars(tt('app_name', 'Prismatch')) ?></div>
    </div>
  </a>
  <button class="pm-menu-btn" type="button" aria-label="Menu" aria-expanded="false" aria-controls="pmNav">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <line x1="3" y1="6" x2="21" y2="6"></line>
      <line x1="3" y1="12" x2="21" y2="12"></line>
      <line x1="3" y1="18" x2="21" y2="18"></line>
    </svg>
  </button>
  <div class="pm-nav" id="pmNav">
    <a class="pm-btn primary<?= $isPlay ? ' active' : '' ?>" href="play.php"><?= htmlspecialchars(tt('home_cta_play', 'Play now')) ?></a>
    <a class="pm-btn<?= $isDaily ? ' active' : '' ?>" href="play.php?daily=1"><?= htmlspecialchars(tt('home_cta_daily', 'Daily Challenge')) ?></a>
    <a class="pm-btn<?= $isLeaderboard ? ' active' : '' ?>" href="daily_leaderboard.php"><?= htmlspecialchars(tt('daily_leaderboard_title', 'Leaderboard')) ?></a>
    <?php if (!empty($userEmail)): ?>
      <a class="pm-btn<?= $isSessions ? ' active' : '' ?>" href="games.php"><?= htmlspecialchars(tt('btn_view_history', 'My Sessions')) ?></a>
      <a class="pm-btn" href="logout.php"><?= htmlspecialchars(tt('btn_logout', 'Logout')) ?></a>
    <?php else: ?>
      <a class="pm-btn" href="login.php"><?= htmlspecialchars(tt('home_cta_login', 'Sign in')) ?></a>
    <?php endif; ?>

    <button class="pm-btn pm-theme-toggle" type="button" id="themeToggle" aria-label="Toggle theme">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M12 3a1 1 0 0 1 1 1v1"></path>
        <path d="M12 19a1 1 0 0 1 1 1v1"></path>
        <path d="M4 12a1 1 0 0 1 1-1h1"></path>
        <path d="M18 12a1 1 0 0 1 1-1h1"></path>
        <path d="M5.6 5.6a1 1 0 0 1 1.4 0l.7.7"></path>
        <path d="M16.3 16.3a1 1 0 0 1 1.4 0l.7.7"></path>
        <path d="M5.6 18.4a1 1 0 0 1 1.4 0l.7-.7"></path>
        <path d="M16.3 7.7a1 1 0 0 1 1.4 0l.7-.7"></path>
        <circle cx="12" cy="12" r="3.5"></circle>
      </svg>
      <span id="themeToggleText">Theme</span>
    </button>

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
</header>
<script>
(function(){
  const header = document.querySelector('.pm-header');
  const menuBtn = header ? header.querySelector('.pm-menu-btn') : null;
  const nav = document.getElementById('pmNav');
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

  function closeMenu(){
    header.classList.remove('is-open');
    if (menuBtn) menuBtn.setAttribute('aria-expanded', 'false');
  }
  if (menuBtn && nav){
    menuBtn.addEventListener('click', () => {
      const isOpen = header.classList.toggle('is-open');
      menuBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
    document.addEventListener('click', (e) => {
      if (!header.contains(e.target)) closeMenu();
    });
    window.addEventListener('resize', () => {
      if (window.innerWidth > 700) closeMenu();
    }, {passive:true});
  }

  const themeBtn = document.getElementById('themeToggle');
  const themeTxt = document.getElementById('themeToggleText');
  if (themeBtn){
    const key = 'pm-theme';
    const applyTheme = (t) => {
      document.documentElement.setAttribute('data-bs-theme', t);
      if (themeTxt) themeTxt.textContent = (t === 'dark') ? 'Light' : 'Dark';
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
