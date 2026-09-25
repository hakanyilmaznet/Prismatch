<?php
require_once __DIR__ . '/bootstrap.php';
if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
  error_reporting(E_ALL);
  @ini_set('display_errors', '1');
  @ini_set('display_startup_errors', '1');
}

require_once __DIR__ . '/i18n.php';

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
$seoTitle = t('home_cta_login') . ' - ' . t('app_name');
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
      document.documentElement.setAttribute('data-bs-theme', stored || prefers);
    })();
  </script>
  <link href="css/style.css" rel="stylesheet" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700;800&family=Baloo+2:wght@500;600;700;800&display=swap" rel="stylesheet" />
  <title><?= htmlspecialchars($seoTitle) ?></title>
  <link rel="icon" type="image/svg+xml" href="logo.svg" />
  <style>
    body {
      padding-top: calc(var(--pm-header-offset, 0px) + 20px);
      padding-bottom: calc(var(--pm-footer-offset, 0px) + 24px);
    }
    .auth-container {
      max-width: 520px;
      margin: 0 auto;
      padding: 16px;
      display: flex;
      flex-direction: column;
      gap: 20px;
    }
    .auth-card {
      background: var(--pm-bg-card);
      border: 1px solid var(--pm-border);
      border-radius: var(--pm-radius-xl);
      padding: 32px 28px;
      box-shadow: var(--pm-shadow-lg);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    @media (max-width: 480px) {
      .auth-card {
        padding: 24px 18px;
        border-radius: var(--pm-radius-lg);
      }
    }
    .auth-badge {
      width: 64px;
      height: 64px;
      border-radius: 20px;
      margin: 0 auto 16px auto;
      background: linear-gradient(135deg, rgba(255,107,91,0.2), rgba(255,211,107,0.25));
      border: 1px solid var(--pm-border);
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 8px 24px var(--pm-primary-glow);
    }
    .auth-badge img {
      width: 36px;
      height: 36px;
    }
    .auth-title {
      font-size: 26px;
      font-weight: 800;
      margin-bottom: 8px;
      font-family: var(--pm-font-display);
    }
    .auth-subtitle {
      color: var(--pm-text-muted);
      font-size: 14px;
      line-height: 1.5;
      margin-bottom: 24px;
    }
    .auth-btn-google {
      width: 100%;
      min-height: 48px;
      background: var(--pm-bg-elevated);
      border: 1px solid var(--pm-border);
      color: var(--pm-text);
      font-weight: 700;
      font-size: 15px;
      border-radius: var(--pm-radius-pill);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      transition: var(--pm-transition);
      box-shadow: var(--pm-shadow-sm);
    }
    .auth-btn-google:hover {
      border-color: var(--pm-border-hover);
      transform: translateY(-2px);
      box-shadow: var(--pm-shadow-md);
    }
    .auth-divider {
      display: flex;
      align-items: center;
      gap: 14px;
      margin: 24px 0;
      color: var(--pm-text-muted);
      font-size: 12px;
      text-transform: uppercase;
      font-weight: 600;
      letter-spacing: 0.8px;
    }
    .auth-divider::before, .auth-divider::after {
      content: "";
      flex: 1;
      height: 1px;
      background: var(--pm-border);
    }
    .auth-form {
      text-align: left;
      display: flex;
      flex-direction: column;
      gap: 14px;
    }
    .auth-btn-submit {
      width: 100%;
      min-height: 48px;
      font-size: 15px;
    }
  </style>
</head>
<body class="pm-has-fixed-header">
  <?php include __DIR__ . '/header.php'; ?>

  <main class="auth-container">
    <div class="auth-card">
      <div class="auth-badge">
        <img src="logo.svg" alt="<?= htmlspecialchars(t('app_name')) ?>" />
      </div>
      <h1 class="auth-title"><?= htmlspecialchars(t('home_cta_login')) ?></h1>
      <p class="auth-subtitle"><?= htmlspecialchars(t('login_intro')) ?></p>

      <a class="auth-btn-google" href="login.php?provider=google<?= $nextUrl ? '&next=' . rawurlencode($nextUrl) : '' ?>">
        <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true">
          <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z"/>
          <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.24v3.15C3.26 21.36 7.33 24 12 24z"/>
          <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.24C.45 8.15 0 9.92 0 12s.45 3.85 1.24 5.42l4.04-3.15z"/>
          <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.26 2.64 1.24 6.58l4.04 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
        </svg>
        <?= htmlspecialchars(t('login_google')) ?>
      </a>

      <div class="auth-divider">
        <span><?= htmlspecialchars(t('or')) ?></span>
      </div>

      <div class="auth-local-header" style="text-align: left; margin-bottom: 12px;">
        <h2 style="font-size: 17px; margin-bottom: 4px; font-family: var(--pm-font-display);"><?= htmlspecialchars(t('login_local_title')) ?></h2>
        <p style="color: var(--pm-text-muted); font-size: 13px; margin: 0;"><?= htmlspecialchars(t('login_local_desc')) ?></p>
      </div>

      <form id="localLoginForm" class="auth-form">
        <div>
          <label class="form-label" for="localUsername"><?= htmlspecialchars(t('username')) ?></label>
          <input id="localUsername" class="form-control form-control-lg" type="text" minlength="2" maxlength="30" autocomplete="off" placeholder="<?= htmlspecialchars(t('login_username_placeholder')) ?>" required />
        </div>
        <button class="btn btn-primary auth-btn-submit" type="submit">
          <?= htmlspecialchars(t('btn_continue')) ?>
        </button>
        <div id="localMsg" class="muted" role="status" aria-live="polite" style="margin-top:6px; font-size: 13px;"></div>
      </form>

      <div style="margin-top: 20px;">
        <a class="btn btn-sm btn-outline-secondary" href="index.php">
          <img class="bi-icon" src="bootstrap-icons/arrow-left.svg" alt="" aria-hidden="true" />
          <?= htmlspecialchars(t('btn_back_home')) ?>
        </a>
      </div>
    </div>
  </main>

  <?php if (is_file(__DIR__ . '/footer.php')) include __DIR__ . '/footer.php'; ?>

  <script>
  const NEXT_URL = <?= json_encode($nextUrl) ?>;
  const MSG_REQUIRED = <?= json_encode(t('login_username_required')) ?>;
  const MSG_FAILED = <?= json_encode(t('login_failed')) ?>;

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
      setMsg(MSG_REQUIRED, 'error');
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
      setMsg((data && data.error) ? data.error : MSG_FAILED, 'error');
    } catch (err) {
      setMsg(MSG_FAILED, 'error');
    }
  });
  </script>
</body>
</html>
