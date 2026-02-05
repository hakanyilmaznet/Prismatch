<?php include __DIR__ . '/footer.php'; ?>
<?php
require_once __DIR__ . '/config.php';

session_name(SESSION_NAME);
session_set_cookie_params([
  'httponly' => true,
  'secure' => COOKIE_SECURE,
  'samesite' => 'Lax',
]);
session_start();

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
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <title><?= htmlspecialchars(t('home_cta_login')) ?> - <?= htmlspecialchars(t('app_name')) ?></title>
  <link rel="icon" type="image/svg+xml" href="logo.svg" />
  <style>
    :root{ color-scheme: light dark; }
    body{
      margin:0;
      font-family: "Plus Jakarta Sans", "Segoe UI", "Helvetica Neue", sans-serif;
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
  </style>
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>
<main class="wrap">
  <div class="cardx">
    <h1 class="h4 m-0"><?= htmlspecialchars(t('home_cta_login')) ?></h1>
    <div class="muted"><?= htmlspecialchars(t('subtitle_guest')) ?></div>
  </div>

  <div class="cardx">
    <h2 class="h6"><?= htmlspecialchars(t('save_with_google')) ?></h2>
    <div class="muted mb-3">Google ile giriş yaptığınızda sadece e-posta adresiniz kaydedilir.</div>
    <div class="login-actions">
      <a class="btn btn-primary" href="login.php?provider=google">
        <img class="bi-icon" src="google.svg" alt="" aria-hidden="true" />
        Google ile giriş yap
      </a>
    </div>
  </div>

  <div class="cardx">
    <h2 class="h6">Kullanıcı adı ile giriş</h2>
    <div class="muted mb-3">Sadece kullanıcı adı girin. Bu yöntemle e-posta kaydı tutulmaz.</div>
    <form id="localLoginForm">
      <label class="form-label" for="usernameInput">Kullanıcı adı</label>
      <input id="usernameInput" class="form-control" type="text" maxlength="30" required />
      <button class="btn btn-outline-primary mt-3" type="submit">Giriş yap</button>
      <div class="form-text mt-2" id="localLoginMsg"></div>
    </form>
  </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>

<script>
const NEXT_URL = <?= json_encode($nextUrl) ?>;
const form = document.getElementById('localLoginForm');
const input = document.getElementById('usernameInput');
const msg = document.getElementById('localLoginMsg');

function setMsg(text = '', type = ''){
  if (!msg) return;
  msg.textContent = text;
  msg.classList.remove('pm-error', 'pm-success');
  if (type) msg.classList.add(`pm-${type}`);
}

form?.addEventListener('submit', async (e) => {
  e.preventDefault();
  setMsg('');
  const username = (input?.value || '').trim();
  if (!username) {
    setMsg('Kullanıcı adı gerekli.', 'error');
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
    setMsg((data && data.error) ? data.error : 'Giriş başarısız.', 'error');
  } catch (err) {
    setMsg('Giriş başarısız.', 'error');
  }
});
</script>
</body>
</html>
