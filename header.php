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
if (!isset($userEmail)) {
  $userEmail = $_SESSION['user_email'] ?? null;
}
$currentPage = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$isPlay = ($currentPage === 'play.php' && empty($_GET['daily']));
$isDaily = ($currentPage === 'play.php' && !empty($_GET['daily']));
$isLeaderboard = ($currentPage === 'daily_leaderboard.php');
$isSessions = ($currentPage === 'games.php' || $currentPage === 'game.php');
$isRooms = ($currentPage === 'rooms.php' || $currentPage === 'room_play.php' || $currentPage === 'room_history.php');
$lang = function_exists('get_lang') ? get_lang() : 'en';
$selLang = $lang ?: 'en';
?>
<script>
(function(){
  try{
    const stored = localStorage.getItem('pm-theme');
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    const theme = stored || (prefersDark ? 'dark' : 'light');
    document.documentElement.setAttribute('data-bs-theme', theme);
  }catch(_){}
})();
</script>
<nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom mb-3">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
      <img src="logo.svg" alt="<?= htmlspecialchars(tt('app_name', 'Prismatch')) ?>" width="32" height="32" class="rounded" />
      <span><?= htmlspecialchars(tt('app_name', 'Prismatch')) ?></span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#pmNav" aria-controls="pmNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="pmNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link<?= $isPlay ? ' active' : '' ?>" href="play.php"><?= htmlspecialchars(tt('home_cta_play', 'Play now')) ?></a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= $isDaily ? ' active' : '' ?>" href="play.php?daily=1"><?= htmlspecialchars(tt('home_cta_daily', 'Daily Challenge')) ?></a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= $isLeaderboard ? ' active' : '' ?>" href="daily_leaderboard.php"><?= htmlspecialchars(tt('daily_leaderboard_title', 'Leaderboard')) ?></a>
        </li>
        <?php if (!empty($userEmail)): ?>
        <li class="nav-item">
          <a class="nav-link<?= $isRooms ? ' active' : '' ?>" href="rooms.php"><?= htmlspecialchars(tt('rooms_title', 'Rooms')) ?></a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= $isSessions ? ' active' : '' ?>" href="games.php"><?= htmlspecialchars(tt('btn_view_history', 'My Sessions')) ?></a>
        </li>
        <?php endif; ?>
      </ul>

      <div class="d-flex align-items-center gap-2">
        <?php if ($showLangPicker): ?>
          <select id="langSelect" class="form-select form-select-sm" aria-label="<?= htmlspecialchars(tt('language', 'Language')) ?>">
            <?php
              foreach (supported_languages() as $code => $name) {
                $selected = ($code === $selLang) ? ' selected' : '';
                echo "<option value='".htmlspecialchars($code, ENT_QUOTES)."'{$selected}>".htmlspecialchars($name, ENT_QUOTES)."</option>";
              }
            ?>
          </select>
        <?php endif; ?>

        <button class="btn btn-outline-secondary btn-sm" id="themeToggle" type="button" aria-label="Tema değiştir" title="Tema değiştir">
          <img class="bi-icon" src="bootstrap-icons/moon-stars.svg" alt="" aria-hidden="true" />
        </button>

        <?php if (!empty($userEmail)): ?>
          <a class="btn btn-outline-secondary btn-sm" href="logout.php"><?= htmlspecialchars(tt('btn_logout', 'Logout')) ?></a>
        <?php else: ?>
          <a class="btn btn-outline-primary btn-sm" href="login.php"><?= htmlspecialchars(tt('home_cta_login', 'Sign in')) ?></a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
<script>
(function(){
  const select = document.getElementById('langSelect');
  if (!select) return;
  select.addEventListener('change', () => {
    const code = select.value;
    fetch('api/set_lang.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({lang: code}),
      credentials: 'same-origin'
    }).then(() => location.reload())
      .catch(() => {
        const url = new URL(window.location.href);
        url.searchParams.set('lang', code);
        window.location.href = url.toString();
      });
  });
})();
</script>
<script>
(function(){
  const btn = document.getElementById('themeToggle');
  if (!btn) return;
  const icon = btn.querySelector('img');

  function currentTheme(){
    return document.documentElement.getAttribute('data-bs-theme') || 'light';
  }

  function setIcon(theme){
    if (!icon) return;
    icon.src = theme === 'dark' ? 'bootstrap-icons/sun.svg' : 'bootstrap-icons/moon-stars.svg';
  }

  setIcon(currentTheme());
  btn.addEventListener('click', () => {
    const next = currentTheme() === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-bs-theme', next);
    try{ localStorage.setItem('pm-theme', next); }catch(_){}
    setIcon(next);
  });
})();
</script>
