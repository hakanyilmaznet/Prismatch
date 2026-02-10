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
  <link href="theme.css" rel="stylesheet" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <title><?= htmlspecialchars(t('home_cta_login')) ?> - <?= htmlspecialchars(t('app_name')) ?></title>
  <link rel="icon" type="image/svg+xml" href="logo.svg" />
</head>
<body class="bg-body">
<?php include __DIR__ . '/header.php'; ?>
<main class="container py-4">
  <div class="row justify-content-center">
    <div class="col-lg-8 d-grid gap-3">
      <div class="card">
        <div class="card-body">
          <h1 class="h4 mb-1"><?= htmlspecialchars(t('home_cta_login')) ?></h1>
          <div class="text-body-secondary small"><?= htmlspecialchars(t('subtitle_guest')) ?></div>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <h2 class="h6 mb-2"><?= htmlspecialchars(t('save_with_google')) ?></h2>
          <div class="text-body-secondary small mb-3">Google ile giriþ yaptýðýnýzda sadece e-posta adresiniz kaydedilir.</div>
          <a class="btn btn-primary d-inline-flex align-items-center gap-2" href="login.php?provider=google">
            <img src="google.svg" alt="" aria-hidden="true" width="16" height="16" />
            Google ile giriþ yap
          </a>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <h2 class="h6 mb-2">Kullanýcý adý ile giriþ</h2>
          <div class="text-body-secondary small mb-3">Sadece kullanýcý adý girin. Bu yöntemle e-posta kaydý tutulmaz.</div>
          <form id="localLoginForm">
            <label class="form-label" for="usernameInput">Kullanýcý adý</label>
            <input id="usernameInput" class="form-control" type="text" maxlength="30" required />
            <button class="btn btn-outline-primary mt-3" type="submit">Giriþ yap</button>
            <div class="form-text mt-2" id="localLoginMsg"></div>
          </form>
        </div>
      </div>
    </div>
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
  msg.classList.remove('text-danger', 'text-success');
  if (type === 'error') msg.classList.add('text-danger');
  if (type === 'success') msg.classList.add('text-success');
}

form?.addEventListener('submit', async (e) => {
  e.preventDefault();
  setMsg('');
  const username = (input?.value || '').trim();
  if (!username) {
    setMsg('Kullanýcý adý gerekli.', 'error');
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
    setMsg((data && data.error) ? data.error : 'Giriþ baþarýsýz.', 'error');
  } catch (err) {
    setMsg('Giriþ baþarýsýz.', 'error');
  }
});
</script>
</body>
</html>