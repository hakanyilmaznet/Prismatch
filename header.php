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
  :root { --pm-header-offset: 76px; --pm-icon-filter: none; }
  body.pm-has-fixed-header { padding-top: var(--pm-header-offset); }

  .pm-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    background: linear-gradient(180deg, rgba(255,255,255,0.96), rgba(247,247,247,0.88));
    border: 1px solid rgba(0,0,0,0.08);
    border-radius: 20px;
    padding: 8px 16px;
    box-shadow: 0 16px 48px rgba(12,14,20,0.12);
    position: fixed;
    top: max(10px, env(safe-area-inset-top));
    left: 50%;
    transform: translateX(-50%);
    width: min(1180px, calc(100% - 24px));
    z-index: 1000;
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    box-sizing: border-box;
  }

  [data-bs-theme="dark"] .pm-header {
    background: linear-gradient(180deg, rgba(14,18,30,0.94), rgba(10,14,24,0.85));
    border-color: rgba(255,255,255,0.10);
    box-shadow: 0 20px 60px rgba(0,0,0,0.6);
  }
  [data-bs-theme="dark"] { --pm-icon-filter: invert(1) brightness(1.1); }

  .pm-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    color: inherit;
    flex-shrink: 0;
  }
  .pm-brand img {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    border: 1px solid rgba(0,0,0,0.1);
    box-shadow: 0 6px 16px rgba(10,12,18,0.12);
  }
  [data-bs-theme="dark"] .pm-brand img {
    border-color: rgba(255,255,255,0.12);
    box-shadow: 0 8px 20px rgba(0,0,0,0.4);
  }
  .pm-brand .pm-name {
    font-family: var(--pm-font-display, "Baloo 2", sans-serif);
    font-size: 20px;
    font-weight: 800;
    letter-spacing: -0.2px;
    line-height: 1;
  }

  .navbar-toggler {
    display: none;
    background: rgba(0,0,0,0.04);
    border: 1px solid rgba(0,0,0,0.1);
    border-radius: 12px;
    padding: 8px 10px;
    cursor: pointer;
    color: inherit;
    align-items: center;
    justify-content: center;
  }
  [data-bs-theme="dark"] .navbar-toggler {
    background: rgba(255,255,255,0.06);
    border-color: rgba(255,255,255,0.14);
  }

  .navbar-collapse {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex: 1;
    gap: 12px;
  }

  .pm-nav {
    display: flex;
    align-items: center;
    flex-direction: row;
    gap: 6px;
    list-style: none;
    margin: 0;
    padding: 0;
  }

  .nav-item {
    list-style: none;
  }

  .nav-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: inherit;
    text-decoration: none;
    font-size: 13.5px;
    font-weight: 600;
    padding: 7px 13px;
    border-radius: 999px;
    transition: all 0.15s ease;
    white-space: nowrap;
  }
  .nav-link:hover {
    background: rgba(255, 107, 91, 0.12);
    color: #ff6b5b;
  }
  .nav-link.active {
    background: linear-gradient(135deg, rgba(255,107,91,0.22), rgba(255,178,75,0.18));
    border: 1px solid rgba(255, 107, 91, 0.4);
    color: #ff6b5b;
  }

  .header-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-left: auto;
    flex-shrink: 0;
  }

  .bi-icon {
    width: 16px;
    height: 16px;
    display: inline-block;
    filter: var(--pm-icon-filter);
  }
  .btn.primary .bi-icon, .btn-primary .bi-icon { filter: none; }
  .pm-theme-toggle {
    padding: 8px 12px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 38px;
  }

  .langWrap { position: relative; display: inline-flex; width: 170px; max-width: 100%; }
  .visually-hidden { position: absolute !important; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
  .langBtn {
    appearance: none;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    width: 100%;
    padding: 7px 12px;
    border-radius: 12px;
    border: 1px solid rgba(0,0,0,0.12);
    background: rgba(0,0,0,0.04);
    color: inherit;
    cursor: pointer;
    font-size: 13px;
    min-height: 38px;
  }
  [data-bs-theme="dark"] .langBtn {
    border-color: rgba(255,255,255,0.14);
    background: rgba(255,255,255,0.06);
    color: #fff;
  }
  .langBtnLeft { display: flex; align-items: center; gap: 8px; min-width: 0; }
  .langBtnText { font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 13px; }
  .langCaret { width: 16px; height: 16px; background: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='black' opacity='0.6' viewBox='0 0 24 24'><path d='M7 10l5 5 5-5z'/></svg>") no-repeat center/16px 16px; flex: 0 0 16px; }
  [data-bs-theme="dark"] .langCaret { background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='white' opacity='0.75' viewBox='0 0 24 24'><path d='M7 10l5 5 5-5z'/></svg>"); }
  .langPop { position: fixed; z-index: 30000; width: min(92vw, 360px); border-radius: 18px; border: 1px solid rgba(0,0,0,.12); }

  @media (max-width: 991.98px) {
    .pm-header {
      border-radius: 18px;
      padding: 10px 14px;
      flex-wrap: wrap;
      max-height: calc(100vh - 20px);
      overflow-y: auto;
    }
    .navbar-toggler {
      display: inline-flex;
    }
    .navbar-collapse {
      display: none;
      width: 100%;
      flex-direction: column;
      align-items: stretch;
      padding-top: 12px;
      border-top: 1px solid rgba(255,255,255,0.08);
      gap: 12px;
    }
    .navbar-collapse.show {
      display: flex !important;
    }
    .pm-nav {
      flex-direction: column;
      align-items: stretch;
      gap: 6px;
      width: 100%;
    }
    .pm-nav .nav-link {
      width: 100%;
      min-height: 44px;
      padding: 10px 16px;
      border-radius: 14px;
      font-size: 14px;
    }
    .header-actions {
      width: 100%;
      justify-content: space-between;
      margin-left: 0;
      padding-top: 8px;
      border-top: 1px dashed rgba(255,255,255,0.08);
    }
    .langWrap {
      flex: 1;
    }
  }
</style>

<nav class="pm-header navbar">
  <a class="pm-brand" href="index.php">
    <img src="logo.svg" alt="<?= htmlspecialchars(tt('app_name', 'Prismatch')) ?>" width="40" height="40" />
    <span class="pm-name"><?= htmlspecialchars(tt('app_name', 'Prismatch')) ?></span>
  </a>
  <button class="navbar-toggler" type="button" id="pmNavToggle" aria-controls="pmNav" aria-expanded="false" aria-label="Toggle navigation">
    <img class="bi-icon" src="bootstrap-icons/list.svg" alt="" width="22" height="22" />
  </button>
  <div class="navbar-collapse" id="pmNav">
    <ul class="navbar-nav pm-nav">
      <li class="nav-item">
        <a class="nav-link<?= $isPlay ? ' active' : '' ?>" href="play.php">
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
        <a class="nav-link<?= $isRooms ? ' active' : '' ?>" href="rooms.php">
          <img class="bi-icon" src="bootstrap-icons/people-fill.svg" alt="" aria-hidden="true" />
          <?= htmlspecialchars(tt('rooms_title', 'Rooms')) ?>
        </a>
      </li>
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

    <div class="header-actions">
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
    const isMobile = window.innerWidth <= 991.98;
    if (isMobile) {
      document.documentElement.style.setProperty('--pm-header-offset', '76px');
      return;
    }
    const rect = header.getBoundingClientRect();
    const extra = 14;
    const offset = Math.max(68, Math.ceil(rect.height + extra));
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



