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
<nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2 text-decoration-none" href="index.php">
      <img src="logo.svg" alt="<?= htmlspecialchars(tt('app_name', 'Prismatch')) ?>" width="36" height="36" class="rounded" />
      <span class="fw-semibold"><?= htmlspecialchars(tt('app_name', 'Prismatch')) ?></span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#pmNav" aria-controls="pmNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="pmNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0 align-items-lg-center gap-lg-2">
        <li class="nav-item">
          <a class="btn btn-primary btn-sm d-flex align-items-center gap-2" href="play.php">
            <img src="bootstrap-icons/play-fill.svg" alt="" aria-hidden="true" width="16" height="16" />
            <?= htmlspecialchars(tt('home_cta_play', 'Play now')) ?>
          </a>
        </li>
        <li class="nav-item">
          <a class="btn btn-outline-primary btn-sm d-flex align-items-center gap-2" href="play.php?daily=1">
            <img src="bootstrap-icons/calendar2-check.svg" alt="" aria-hidden="true" width="16" height="16" />
            <?= htmlspecialchars(tt('home_cta_daily', 'Daily Challenge')) ?>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link d-flex align-items-center gap-2<?= $isLeaderboard ? ' active' : '' ?>" href="daily_leaderboard.php">
            <img src="bootstrap-icons/trophy-fill.svg" alt="" aria-hidden="true" width="16" height="16" />
            <?= htmlspecialchars(tt('daily_leaderboard_title', 'Leaderboard')) ?>
          </a>
        </li>
        <?php if (!empty($userEmail)): ?>
        <li class="nav-item">
          <a class="nav-link d-flex align-items-center gap-2<?= $isRooms ? ' active' : '' ?>" href="rooms.php">
            <img src="bootstrap-icons/people-fill.svg" alt="" aria-hidden="true" width="16" height="16" />
            <?= htmlspecialchars(tt('rooms_title', 'Rooms')) ?>
          </a>
        </li>
        <?php endif; ?>
        <?php if (!empty($userEmail)): ?>
          <li class="nav-item">
            <a class="nav-link d-flex align-items-center gap-2<?= $isSessions ? ' active' : '' ?>" href="games.php">
              <img src="bootstrap-icons/clock-history.svg" alt="" aria-hidden="true" width="16" height="16" />
              <?= htmlspecialchars(tt('btn_view_history', 'My Sessions')) ?>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link d-flex align-items-center gap-2" href="logout.php">
              <img src="bootstrap-icons/box-arrow-right.svg" alt="" aria-hidden="true" width="16" height="16" />
              <?= htmlspecialchars(tt('btn_logout', 'Logout')) ?>
            </a>
          </li>
        <?php else: ?>
          <li class="nav-item">
            <a class="nav-link d-flex align-items-center gap-2" href="login.php">
              <img src="bootstrap-icons/box-arrow-in-right.svg" alt="" aria-hidden="true" width="16" height="16" />
              <?= htmlspecialchars(tt('home_cta_login', 'Sign in')) ?>
            </a>
          </li>
        <?php endif; ?>
      </ul>

      <div class="d-flex align-items-center gap-2">
        <button class="btn btn-outline-secondary btn-sm" type="button" id="themeToggle"
                aria-label="<?= htmlspecialchars(tt('theme_toggle', 'Toggle theme')) ?>"
                data-icon-light="bootstrap-icons/brightness-high-fill.svg"
                data-icon-dark="bootstrap-icons/moon-stars-fill.svg">
          <img id="themeToggleIcon" src="bootstrap-icons/moon-stars-fill.svg" alt="" aria-hidden="true" width="16" height="16" />
        </button>

        <?php if ($showLangPicker): ?>
          <div class="dropdown" id="langPicker">
            <select id="langSelect" class="d-none" aria-label="<?= htmlspecialchars(tt('language', 'Language')) ?>">
              <?php
                $sel = $lang ?: 'en';
                foreach (supported_languages() as $code => $name) {
                  $selected = ($code === $sel) ? ' selected' : '';
                  echo "<option value='".htmlspecialchars($code, ENT_QUOTES)."'{$selected}>".htmlspecialchars($name, ENT_QUOTES)."</option>";
                }
              ?>
            </select>
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle d-flex align-items-center gap-2" type="button"
                    id="langBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
              <img src="bootstrap-icons/translate.svg" alt="" aria-hidden="true" width="16" height="16" />
              <span id="langBtnText"><?= htmlspecialchars(tt('language', 'Language')) ?></span>
            </button>
            <div class="dropdown-menu dropdown-menu-end p-2 shadow" aria-labelledby="langBtn">
              <div class="pb-2 border-bottom">
                <input id="langSearch" class="form-control form-control-sm" type="search" autocomplete="off"
                       placeholder="<?= htmlspecialchars(tt('search_language', 'Search...')) ?>"
                       aria-label="<?= htmlspecialchars(tt('search_language', 'Search language')) ?>" />
              </div>
              <div class="list-group list-group-flush mt-2" id="langList" role="listbox"></div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
<script>
(function(){
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
    const btn = document.getElementById('langBtn');
    const btnTxt = document.getElementById('langBtnText');
    const list = document.getElementById('langList');
    const search = document.getElementById('langSearch');
    const menu = btn ? btn.nextElementSibling : null;
    if (!select || !btn || !btnTxt || !list || !search || !menu) return;

    const items = Array.from(select.options).map(o => {
      const name = (o.textContent || '').trim().replace(/\s+/g,' ') || o.value;
      return { code: o.value, name };
    });

    function escapeHtml(s){
      return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    }

    function renderList(filtered){
      list.innerHTML = '';
      if (!filtered.length) {
        const d = document.createElement('div');
        d.className = 'text-body-secondary small px-2 py-1';
        d.textContent = STR.noResults;
        list.appendChild(d);
        return;
      }
      filtered.forEach((it) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
        b.setAttribute('role', 'option');
        b.setAttribute('aria-selected', it.code === select.value ? 'true' : 'false');
        b.innerHTML = `<span>${escapeHtml(it.name)}</span><small class="text-body-secondary">${escapeHtml(it.code.toUpperCase())}</small>`;
        if (it.code === select.value) b.classList.add('active');
        b.addEventListener('click', () => choose(it.code));
        list.appendChild(b);
      });
    }

    function applyFilter(q){
      const s = q.trim().toLowerCase();
      if(!s) return items.slice();
      return items.filter(it =>
        it.code.toLowerCase().includes(s) ||
        it.name.toLowerCase().includes(s)
      );
    }

    function updateButtonText(){
      const current = select.value || items[0]?.code || 'en';
      const it = items.find(x => x.code === current) || items[0];
      if (it) btnTxt.textContent = `${it.name}`;
    }

    function hideDropdown(){
      if (window.bootstrap && bootstrap.Dropdown) {
        const dd = bootstrap.Dropdown.getOrCreateInstance(btn);
        dd.hide();
      } else {
        btn.setAttribute('aria-expanded','false');
        menu.classList.remove('show');
      }
    }

    function choose(code){
      select.value = code;
      updateButtonText();
      hideDropdown();

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

    updateButtonText();
    renderList(items.slice());

    btn.addEventListener('click', () => {
      search.value = '';
      renderList(items.slice());
      setTimeout(() => search.focus(), 0);
    });

    search.addEventListener('input', () => {
      renderList(applyFilter(search.value));
    });
  })();
})();
</script>