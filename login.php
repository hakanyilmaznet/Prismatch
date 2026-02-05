<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/i18n.php';

session_name(SESSION_NAME);
if (PHP_VERSION_ID >= 70300) {
  session_set_cookie_params([
    'httponly' => true,
    'secure' => COOKIE_SECURE,
    'samesite' => 'Lax',
  ]);
} else {
  @ini_set('session.cookie_httponly', '1');
  if (COOKIE_SECURE) @ini_set('session.cookie_secure', '1');
  session_set_cookie_params(0, '/', '', COOKIE_SECURE, true);
}
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Surface OAuth errors from callback
$loginError = $_SESSION['login_error'] ?? '';
if ($loginError !== '') {
  unset($_SESSION['login_error']);
}

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
  <link href="css/bootstrap.min.css" rel="stylesheet" />
  <link href="css/theme.css" rel="stylesheet" />
  <title><?= htmlspecialchars(t('home_cta_login')) ?> - <?= htmlspecialchars(t('app_name')) ?></title>
  <link rel="icon" type="image/svg+xml" href="logo.svg" />
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>
<main class="container py-4">
  <div class="row g-3">
    <div class="col-12">
      <div class="card">
        <div class="card-body">
          <h1 class="h4 mb-2"><?= htmlspecialchars(t('home_cta_login')) ?></h1>
          <p class="text-muted mb-0"><?= htmlspecialchars(t('subtitle_guest')) ?></p>
        </div>
      </div>
    </div>

    <?php if ($loginError !== ''): ?>
      <div class="col-12">
        <div class="alert alert-danger mb-0" role="alert"><?= htmlspecialchars($loginError) ?></div>
      </div>
    <?php endif; ?>

    <div class="col-12 col-lg-6">
      <div class="card h-100">
        <div class="card-body">
          <h2 class="h6"><?= htmlspecialchars(t('save_with_google')) ?></h2>
          <p class="text-muted">Google ile giriþ yaptýðýnýzda sadece e-posta adresiniz kaydedilir.</p>
          <a class="btn btn-primary" href="login.php?provider=google">
            <img class="bi-icon" src="google.svg" alt="" aria-hidden="true" />
            Google ile giriþ yap
          </a>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-6">
      <div class="card h-100">
        <div class="card-body">
          <h2 class="h6">Kullanýcý adý ile giriþ</h2>
          <p class="text-muted">Sadece kullanýcý adý girin. Bu yöntemle e-posta kaydý tutulmaz.</p>
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


