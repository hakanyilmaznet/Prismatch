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

$showLangPicker = isset($showLangPicker) ? (bool)$showLangPicker : true;
$userEmail = $_SESSION['user_email'] ?? null;
$userId = $_SESSION['user_id'] ?? ($userEmail ?: null);
$userName = $_SESSION['user_name'] ?? null;
$loginProvider = $_SESSION['login_provider'] ?? null;
$isLoggedIn = !empty($userEmail);
$lang = function_exists('get_lang') ? get_lang() : 'en';
$sel = $lang ?: 'en';
$currentPage = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$isPlay = ($currentPage === 'play.php' && empty($_GET['daily']));
$isDaily = ($currentPage === 'play.php' && !empty($_GET['daily']));
$isLeaderboard = ($currentPage === 'daily_leaderboard.php');
$isSessions = ($currentPage === 'games.php' || $currentPage === 'game.php');
$isRooms = ($currentPage === 'rooms.php' || $currentPage === 'room_play.php' || $currentPage === 'room_history.php');
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
  .pm-header .navbar-collapse{ width:100%; }
  .pm-header .navbar-nav{ width:100%; }
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
    width:50px; height:50px; border-radius: 14px;
    border:1px solid rgba(0,0,0,0.1);
    box-shadow: 0 10px 24px rgba(10,12,18,0.18);
  }
  [data-bs-theme="dark"] .pm-brand img{ border-color: rgba(255,255,255,0.14); box-shadow: 0 12px 30px rgba(0,0,0,0.45); }
  .pm-brand .pm-name{ font-family:"Baloo 2","Rubik","Segoe UI","Helvetica Neue",sans-serif; font-size: 19px; font-weight: 700; letter-spacing:.2px; }
  .pm-brand .pm-tag{ color: rgba(30,35,50,0.62); font-size: 12px; }
  [data-bs-theme="dark"] .pm-brand .pm-tag{ color: rgba(230,234,245,0.75); }
  .pm-error{
    color: #dc3545 !important;
    font-weight: 600;
  }
  .pm-success{
    color: #198754 !important;
    font-weight: 600;
  }

  .pm-nav{ gap:10px; }
  .navbar-toggler{
    display: none;
    border:1px solid rgba(0,0,0,0.1);
    background: rgba(0,0,0,0.04);
    color:#0f1117;
    border-radius: 12px;
    padding: 8px 10px;
    cursor: pointer;
  }
  .navbar-collapse {
    display: flex;
    align-items: center;
    width: 100%;
  }
  .navbar-nav {
    display: flex;
    list-style: none;
    margin: 0;
    padding: 0;
  }
  .nav-item {
    list-style: none;
  }
  .nav-link {
    color: inherit;
    text-decoration: none;
    padding: 8px 14px;
    border-radius: 999px;
    transition: all 0.15s ease;
  }
  .nav-link:hover {
    background: rgba(255, 107, 91, 0.1);
    color: #ff6b5b;
  }
  .nav-link.active {
    background: linear-gradient(135deg, rgba(255,107,91,0.2), rgba(255,178,75,0.15));
    border: 1px solid rgba(255, 107, 91, 0.3);
    color: #ff6b5b;
  }
  @media (max-width: 991.98px){
    .navbar-toggler{ display: inline-flex; align-items:center; justify-content:center; }
    .navbar-collapse{ display: none; width: 100%; flex-direction: column; margin-top: 12px; }
    .navbar-collapse.show{ display: flex !important; }
    .pm-header{ border-radius: 16px; padding: 10px 14px; flex-wrap: wrap; }
    .pm-nav{ flex-direction:column; align-items:stretch; gap:8px; padding: 10px 0; width:100%; }
    .pm-nav .nav-link, .pm-theme-toggle, .langWrap, .langBtn{ width:100%; justify-content:center; }
  }
</style>

<nav class="pm-header navbar">
  <a class="pm-brand" href="index.php">
    <img src="logo.svg" alt="<?= htmlspecialchars(tt('app_name', 'Prismatch')) ?>" width="44" height="44" />
    <div>
      <div class="pm-name"><?= htmlspecialchars(tt('app_name', 'Prismatch')) ?></div>
    </div>
  </a>
  <button class="navbar-toggler" type="button" id="pmNavToggle" aria-controls="pmNav" aria-expanded="false" aria-label="Toggle navigation">
    <img class="bi-icon" src="bootstrap-icons/list.svg" alt="" width="24" height="24" style="display:block" />
  </button>
  <div class="navbar-collapse" id="pmNav">
    <ul class="navbar-nav pm-nav gap-2 flex-column flex-lg-row flex-lg-wrap justify-content-center mx-lg-auto">
      <li class="nav-item">
        <a class="nav-link d-flex align-items-center gap-2 text-nowrap fw-semibold bg-body-tertiary border rounded-pill px-3 py-2 shadow-sm<?= $isPlay ? ' active' : '' ?>" href="play.php">
          <img class="bi-icon" src="bootstrap-icons/play-fill.svg" alt="" aria-hidden="true" />
          <?= htmlspecialchars(tt('home_cta_play', 'Play now')) ?>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link d-flex align-items-center gap-2 text-nowrap fw-semibold bg-body-tertiary border rounded-pill px-3 py-2 shadow-sm<?= $isDaily ? ' active' : '' ?>" href="play.php?daily=1">
          <img class="bi-icon" src="bootstrap-icons/calendar2-check.svg" alt="" aria-hidden="true" />
          <?= htmlspecialchars(tt('home_cta_daily', 'Daily Challenge')) ?>
        </a>
      </li>
      <li class="w-100 d-none d-lg-block"></li>
      <li class="nav-item">
        <a class="nav-link d-flex align-items-center gap-2 text-nowrap<?= $isLeaderboard ? ' active' : '' ?>" href="daily_leaderboard.php">
          <img class="bi-icon" src="bootstrap-icons/trophy-fill.svg" alt="" aria-hidden="true" />
          <?= htmlspecialchars(tt('daily_leaderboard_title', 'Leaderboard')) ?>
        </a>
      </li>
      <?php if (!empty($userEmail)): ?>
      <li class="nav-item">
        <a class="nav-link d-flex align-items-center gap-2 text-nowrap<?= $isRooms ? ' active' : '' ?>" href="rooms.php">
          <img class="bi-icon" src="bootstrap-icons/people-fill.svg" alt="" aria-hidden="true" />
          <?= htmlspecialchars(tt('rooms_title', 'Rooms')) ?>
        </a>
      </li>
      <?php endif; ?>
      <?php if (!empty($userEmail)): ?>
        <li class="nav-item">
          <a class="nav-link d-flex align-items-center gap-2 text-nowrap<?= $isSessions ? ' active' : '' ?>" href="games.php">
            <img class="bi-icon" src="bootstrap-icons/clock-history.svg" alt="" aria-hidden="true" />
            <?= htmlspecialchars(tt('btn_view_history', 'My Sessions')) ?>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link d-flex align-items-center gap-2 text-nowrap" href="logout.php">
            <img class="bi-icon" src="bootstrap-icons/box-arrow-right.svg" alt="" aria-hidden="true" />
            <?= htmlspecialchars(tt('btn_logout', 'Logout')) ?>
          </a>
        </li>
      <?php else: ?>
        <li class="nav-item">
          <a class="nav-link d-flex align-items-center gap-2 text-nowrap" href="login.php">
            <img class="bi-icon" src="bootstrap-icons/box-arrow-in-right.svg" alt="" aria-hidden="true" />
            <?= htmlspecialchars(tt('home_cta_login', 'Sign in')) ?>
          </a>
        </li>
      <?php endif; ?>
    </ul>

    <div class="d-flex align-items-center gap-2 ms-auto">
      <button class="btn btn-outline-secondary btn-sm pm-theme-toggle" type="button" id="themeToggle"
              aria-label="<?= htmlspecialchars(tt('theme_toggle', 'Toggle theme')) ?>"
              data-icon-light="bootstrap-icons/brightness-high-fill.svg"
              data-icon-dark="bootstrap-icons/moon-stars-fill.svg">
        <img class="bi-icon" id="themeToggleIcon" src="bootstrap-icons/moon-stars-fill.svg" alt="" aria-hidden="true" />
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
  const themeIcon = document.getElementById('themeToggleIcon');
  if (themeBtn){
    const iconLight = themeBtn.getAttribute('data-icon-light') || '';
    const iconDark = themeBtn.getAttribute('data-icon-dark') || '';
    const key = 'pm-theme';
    const applyTheme = (t) => {
      document.documentElement.setAttribute('data-bs-theme', t);
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

  (function initLangPicker(){
    const STR = { noResults: <?= json_encode(tt('no_results', 'No results')) ?> };
    const select = document.getElementById('langSelect');
    const root   = document.getElementById('langPicker');
    const btn    = document.getElementById('langBtn');
    const btnTxt = document.getElementById('langBtnText');
    const pop    = document.getElementById('langPop');
    const list   = document.getElementById('langList');
    const search = document.getElementById('langSearch');
    const title  = document.getElementById('langTitle');
    if(!select || !root || !btn || !btnTxt || !pop || !list || !search || !title) return;

    const items = Array.from(select.options).map(o => {
      const name = (o.textContent || '').trim().replace(/\s+/g,' ') || o.value;
      return { code: o.value, name };
    });

    let open = false;
    let activeIndex = -1;
    let filtered = items.slice();

    let popWasPortaled = false;
    let popHomeParent = null;
    let popHomeNext = null;

    function escapeHtml(s){
      return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    }

    function renderList(){
      list.innerHTML = '';
      if (!filtered.length) {
        const d = document.createElement('div');
        d.className = 'langNoRes';
        d.textContent = STR.noResults;
        list.appendChild(d);
        return;
      }
      filtered.forEach((it, idx) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'langItem';
        b.setAttribute('role','option');
        b.dataset.code = it.code;
        b.dataset.index = String(idx);

        const selected = (select.value === it.code);
        b.setAttribute('aria-selected', selected ? 'true' : 'false');

        b.innerHTML = `
          <span class="langLabelCell">${escapeHtml(it.name)}</span>
          <span class="langMetaCell"><span>${escapeHtml(it.code)}</span>${selected ? '<span class="langCheck" aria-hidden="true">OK</span>' : ''}</span>
        `;

        b.addEventListener('mouseenter', () => setActive(idx));
        b.addEventListener('click', () => choose(it.code));
        list.appendChild(b);
      });
      syncActiveClass();
    }

    function setActive(idx){
      activeIndex = Math.max(0, Math.min(idx, filtered.length-1));
      syncActiveClass();
      scrollActiveIntoView();
    }
    function syncActiveClass(){
      Array.from(list.querySelectorAll('.langItem')).forEach((el, i) => {
        el.classList.toggle('isActive', i === activeIndex);
      });
    }
    function scrollActiveIntoView(){
      const el = list.querySelectorAll('.langItem')[activeIndex];
      if(!el) return;
      const r = el.getBoundingClientRect();
      const pr = list.getBoundingClientRect();
      if (r.top < pr.top) el.scrollIntoView({block:'nearest'});
      if (r.bottom > pr.bottom) el.scrollIntoView({block:'nearest'});
    }

    function placePopover() {
      if (!popWasPortaled) {
        popHomeParent = pop.parentNode;
        popHomeNext = pop.nextSibling;
        document.body.appendChild(pop);
        popWasPortaled = true;
      }
      const r = btn.getBoundingClientRect();
      const margin = 10;

      pop.hidden = false;

      const popRect = pop.getBoundingClientRect();
      const alignEnd = root.getAttribute('data-align') !== 'start';

      let top = Math.round(r.bottom + margin);
      let left = alignEnd ? Math.round(r.right - popRect.width) : Math.round(r.left);

      const minLeft = 12;
      const maxLeft = window.innerWidth - popRect.width - 12;
      left = Math.max(minLeft, Math.min(left, maxLeft));

      const maxTop = window.innerHeight - popRect.height - 12;
      if (top > maxTop) top = Math.max(12, Math.round(r.top - popRect.height - margin));

      pop.style.top = top + 'px';
      pop.style.left = left + 'px';

      const selIdx = filtered.findIndex(x => x.code === select.value);
      if (selIdx >= 0) setActive(selIdx);
    }

    function unportalPopover() {
      if (popWasPortaled && popHomeParent) {
        if (popHomeNext && popHomeNext.parentNode === popHomeParent) popHomeParent.insertBefore(pop, popHomeNext);
        else popHomeParent.appendChild(pop);
      }
      popWasPortaled = false;
      popHomeParent = null;
      popHomeNext = null;
      pop.style.top = '';
      pop.style.left = '';
    }

    function setOpen(v){
      open = v;
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');

      if(open){
        search.value = '';
        filtered = items.slice();
        renderList();
        placePopover();
        setTimeout(()=> search.focus(), 0);
      } else {
        pop.hidden = true;
        activeIndex = -1;
        unportalPopover();
      }
    }

    function choose(code){
      select.value = code;
      const it = items.find(x => x.code === code);
      btnTxt.textContent = it ? `${it.name}` : code;

      setOpen(false);

      const url = new URL(window.location.href);
      fetch('api/set_lang.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({lang: code}),
        credentials: 'same-origin'
      }).then(() => location.reload())
        .catch(() => {
          url.searchParams.set('lang', code);
          window.location.href = url.toString();
        });
    }

    function applyFilter(q){
      const s = q.trim().toLowerCase();
      if(!s) filtered = items.slice();
      else filtered = items.filter(it =>
        it.code.toLowerCase().includes(s) ||
        it.name.toLowerCase().includes(s)
      );
      renderList();
      if (filtered.length) setActive(0);
    }

    (function initSelected(){
      const current = select.value || items[0]?.code || 'en';
      const it = items.find(x => x.code === current) || items[0];
      if (it) {
        select.value = it.code;
        btnTxt.textContent = `${it.name}`;
      }
    })();

    btn.addEventListener('click', () => setOpen(!open));
    document.addEventListener('pointerdown', (e) => {
      if(!open) return;
      if(root.contains(e.target) || pop.contains(e.target)) return;
      setOpen(false);
    });

    document.addEventListener('keydown', (e) => {
      if(!open) return;
      if(e.key === 'Escape'){
        e.preventDefault();
        setOpen(false);
        btn.focus();
      }
    });

    search.addEventListener('input', () => applyFilter(search.value));
    search.addEventListener('keydown', (e) => {
      if(!open) return;
      if(e.key === 'ArrowDown'){ e.preventDefault(); if(filtered.length) setActive((activeIndex + 1) % filtered.length); }
      if(e.key === 'ArrowUp'){ e.preventDefault(); if(filtered.length) setActive((activeIndex - 1 + filtered.length) % filtered.length); }
      if(e.key === 'Enter'){ e.preventDefault(); if(activeIndex >= 0 && filtered[activeIndex]) choose(filtered[activeIndex].code); }
    });

    window.addEventListener('resize', () => { if(open) placePopover(); }, {passive:true});
    window.addEventListener('scroll',  () => { if(open) placePopover(); }, {passive:true});
  })();
})();
</script>



