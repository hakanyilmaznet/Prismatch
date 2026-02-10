<?php
require_once __DIR__ . '/bootstrap.php';
if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
  error_reporting(E_ALL);
  @ini_set('display_errors', '1');
  @ini_set('display_startup_errors', '1');
}

require_once __DIR__ . '/i18n.php';


// Session is already started in bootstrap.php

function is_safe_next_path(string $path): bool {
  if ($path === '') return false;
  $parts = parse_url($path);
  if ($parts === false) return false;
  if (isset($parts['scheme']) || isset($parts['host'])) return false;
  if (str_starts_with($path, '//')) return false;
  return true;
}

if (isset($_GET['next'])) {
  $next = trim((string)$_GET['next']);
  if (is_safe_next_path($next)) {
    $_SESSION['login_next'] = $next;
  } else {
    unset($_SESSION['login_next']);
  }
}

// Direct Google login
$provider = $_GET['provider'] ?? '';
if ($provider === 'google') {
  $state = bin2hex(random_bytes(16));
  $_SESSION['oauth_state'] = $state;

$params = [
  'client_id' => GOOGLE_CLIENT_ID,
  'redirect_uri' => GOOGLE_REDIRECT_URI,
  'response_type' => 'code',
  'scope' => 'openid email',
  'include_granted_scopes' => 'true',
  'access_type' => 'online',
  'prompt' => 'select_account',
  'state' => $state,
];

  $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
  header('Location: ' . $authUrl);
  exit;
}

$lang = function_exists('get_lang') ? get_lang() : 'en';
$dir  = function_exists('lang_dir') ? lang_dir($lang) : 'ltr';
$nextUrl = $_SESSION['login_next'] ?? '';
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
  <title><?= htmlspecialchars(t('home_cta_login')) ?> - <?= htmlspecialchars(t('app_name')) ?></title>
  <link rel="icon" type="image/svg+xml" href="logo.svg" />
  <style>
    :root{ color-scheme: light dark; }
    body{
      margin:0;
      font-family: "Rubik", "Segoe UI", "Helvetica Neue", sans-serif;
      background: var(--bs-body-bg);
      color: var(--bs-body-color);
      padding-top: calc(var(--pm-header-offset, 0px) + 18px);
      padding-bottom: calc(var(--pm-footer-offset, 0px) + 18px);
    }
    .wrap{ max-width: 720px; margin:0 auto; padding: 18px; display:grid; gap:18px; }
    .cardx{ background: rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.12); border-radius: 16px; padding:16px; }
    [data-bs-theme="light"] .cardx{ background: rgba(255,255,255,0.9); border-color: rgba(0,0,0,0.08); }
    .muted{ opacity:.7; font-size: 13px; }
    .login-actions{ display:flex; gap:10px; flex-wrap:wrap; }
  
    
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
  </style>
</head>
<body>
  <?php include __DIR__ . '/header.php'; ?>

  <main class="wrap">
    <div class="cardx">
      <h1><?= htmlspecialchars(t('home_cta_login')) ?></h1>
      <p class="muted"><?= htmlspecialchars(t('login_intro') !== 'login_intro' ? t('login_intro') : 'Sign in to save your results and continue on any device.') ?></p>
      <div class="login-actions">
        <a class="btn btn-primary" href="login.php?provider=google<?= $nextUrl ? '&next=' . rawurlencode($nextUrl) : '' ?>">
          <?= htmlspecialchars(t('login_google') !== 'login_google' ? t('login_google') : 'Continue with Google') ?>
        </a>
        <a class="btn btn-outline-secondary" href="index.php">
          <?= htmlspecialchars(t('btn_back_home') !== 'btn_back_home' ? t('btn_back_home') : 'Back to home') ?>
        </a>
      </div>
    </div>

    <div class="cardx">
      <h2><?= htmlspecialchars(t('login_local_title') !== 'login_local_title' ? t('login_local_title') : 'Local login') ?></h2>
      <p class="muted"><?= htmlspecialchars(t('login_local_desc') !== 'login_local_desc' ? t('login_local_desc') : 'Use a display name without creating an account.') ?></p>
      <form id="localLoginForm">
        <label class="form-label" for="localUsername"><?= htmlspecialchars(t('username') !== 'username' ? t('username') : 'Username') ?></label>
        <input id="localUsername" class="form-control" type="text" minlength="2" maxlength="30" autocomplete="off" />
        <div class="login-actions" style="margin-top:12px">
          <button class="btn btn-primary" type="submit"><?= htmlspecialchars(t('btn_continue') !== 'btn_continue' ? t('btn_continue') : 'Continue') ?></button>
        </div>
        <div id="localMsg" class="muted" role="status" aria-live="polite" style="margin-top:8px"></div>
      </form>
    </div>
  </main>

  <script>
  const NEXT_URL = <?= json_encode($nextUrl) ?>;
  const form = document.getElementById('localLoginForm');
  const input = document.getElementById('localUsername');
  const msg = document.getElementById('localMsg');

  function setMsg(text, type = ''){
    if (!msg) return;
    msg.textContent = text || '';
    msg.classList.remove('pm-error', 'pm-success');
    if (type === 'error') msg.classList.add('pm-error');
    if (type === 'success') msg.classList.add('pm-success');
  }

  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    setMsg('');
    const username = (input?.value || '').trim();
    if (!username) {
      setMsg('Username is required.', 'error');
      return;
    }
    try {
      let existingId = '';
      try { existingId = localStorage.getItem('prismatchUserId') || ''; } catch(e) {}
      const res = await fetch('api/local_login.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({username, user_id: existingId || undefined})
      });
      const data = await res.json();
      if (data && data.ok && data.user_id) {
        try { localStorage.setItem('prismatchUserId', data.user_id); } catch(e) {}
        window.location.href = NEXT_URL || 'index.php';
        return;
      }
      setMsg((data && data.error) ? data.error : 'Login failed.', 'error');
    } catch (err) {
      setMsg('Login failed.', 'error');
    }
  });
  </script>
</body>
</html>
