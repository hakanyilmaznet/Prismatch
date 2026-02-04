<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';
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

$lang = function_exists('get_lang') ? get_lang() : 'en';
$dir  = function_exists('lang_dir') ? lang_dir($lang) : 'ltr';

$email = $_SESSION['user_email'] ?? null;
$showLangPicker = true;

function tt(string $key, string $fallback = ''): string {
  $v = t($key);
  if ($v === $key) return $fallback !== '' ? $fallback : $key;
  return $v;
}

function room_status_label($status) {
  switch ((string)$status) {
    case 'waiting': return tt('room_status_waiting', 'Waiting');
    case 'active': return tt('room_status_active', 'Active');
    case 'finished': return tt('room_status_finished', 'Finished');
    default: return (string)$status;
  }
}

function format_room_dt($utcIso) {
  if (!$utcIso) return '-';
  try {
    $dt = new DateTimeImmutable($utcIso, new DateTimeZone('UTC'));
    return $dt->format('d/m/Y H:i');
  } catch (Exception $e) {
    return '-';
  }
}

if (!$email) {
  header('Location: login.php');
  exit;
}

$rooms = list_user_rooms($email, 100);
$seoTitle = tt('rooms_title', 'Rooms');
$seoDescription = tt('rooms_desc', 'Create or join rooms to compete live.');
$seoLangs = function_exists('supported_languages') ? array_keys(supported_languages()) : [];
?>
<!doctype html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= htmlspecialchars($dir) ?>">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link href="css/bootstrap.min.css" rel="stylesheet" />
  <title><?= htmlspecialchars($seoTitle) ?></title>
  <link rel="icon" type="image/svg+xml" href="logo.svg" />
  <?= seo_meta([
    'title' => $seoTitle,
    'description' => $seoDescription,
    'url' => seo_current_url(),
    'image' => '/logo.png',
    'type' => 'website',
    'robots' => 'index,follow',
    'lang' => $lang,
    'site_name' => tt('app_name', 'Prismatch'),
  ]) ?>
  <?= seo_alternate_links($seoLangs, seo_current_url()) ?>
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<main class="container py-4">
  <div class="card mb-3">
    <div class="card-body">
      <h1 class="h4 mb-1"><?= htmlspecialchars(tt('rooms_title', 'Rooms')) ?></h1>
      <div class="text-muted"><?= htmlspecialchars(tt('rooms_desc', 'Create or join rooms to compete live.')) ?></div>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-12 col-lg-6">
      <div class="card h-100">
        <div class="card-body">
          <h2 class="h6"><?= htmlspecialchars(tt('rooms_create', 'Create room')) ?></h2>
          <label class="form-label" for="roomNameInput"><?= htmlspecialchars(tt('rooms_name_label', 'Room name')) ?></label>
          <input type="text" id="roomNameInput" class="form-control mb-2" maxlength="80"
                 placeholder="<?= htmlspecialchars(tt('rooms_name_placeholder', 'Give your room a name')) ?>" />
          <button class="btn btn-primary" id="createRoomBtn"><?= htmlspecialchars(tt('rooms_create_btn', 'Create')) ?></button>
          <div id="createMsg" class="form-text mt-2"></div>
          <div id="shareWrap" class="mt-3" hidden>
            <div class="small text-muted mb-2"><?= htmlspecialchars(tt('rooms_share_label', 'Share room link')) ?></div>
            <div class="input-group">
              <input type="text" id="shareLink" class="form-control" readonly />
              <button class="btn btn-outline-primary" id="copyLinkBtn"><?= htmlspecialchars(tt('rooms_share_copy', 'Copy link')) ?></button>
            </div>
            <div id="shareMsg" class="form-text mt-1"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-6">
      <div class="card h-100">
        <div class="card-body">
          <h2 class="h6"><?= htmlspecialchars(tt('rooms_join', 'Join room')) ?></h2>
          <div class="text-muted mb-2"><?= htmlspecialchars(tt('rooms_join_hint', 'Join by opening the room link.')) ?></div>
          <a class="btn btn-outline-primary" id="openRoomBtn" href="#" hidden>
            <?= htmlspecialchars(tt('rooms_open', 'Open')) ?>
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <h2 class="h6"><?= htmlspecialchars(tt('rooms_recent', 'Your rooms')) ?></h2>
      <?php if (!$rooms): ?>
        <div class="text-muted"><?= htmlspecialchars(tt('rooms_empty', 'No rooms yet.')) ?></div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th><?= htmlspecialchars(tt('rooms_name', 'Name')) ?></th>
                <th><?= htmlspecialchars(tt('rooms_status', 'Status')) ?></th>
                <th><?= htmlspecialchars(tt('rooms_rounds', 'Rounds')) ?></th>
                <th><?= htmlspecialchars(tt('rooms_winner', 'Winner')) ?></th>
                <th><?= htmlspecialchars(tt('rooms_created', 'Created')) ?></th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rooms as $r): ?>
                <?php
                  $winner = null;
                  if (($r['status'] ?? '') === 'finished') {
                    $winner = room_winner_email((int)$r['id']);
                  }
                ?>
                <tr>
                  <td><?= htmlspecialchars($r['name'] ?: '-') ?></td>
                  <td><?= htmlspecialchars(room_status_label($r['status'] ?? '')) ?></td>
                  <td><?= (int)$r['rounds_total'] ?></td>
                  <td><?= htmlspecialchars($winner ?: '-') ?></td>
                  <td><?= htmlspecialchars(format_room_dt($r['created_at'])) ?></td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm" role="group">
                      <a class="btn btn-outline-primary" href="room_play.php?guid=<?= urlencode($r['guid']) ?>"><?= htmlspecialchars(tt('rooms_open', 'Open')) ?></a>
                      <a class="btn btn-outline-secondary" href="room_history.php?guid=<?= urlencode($r['guid']) ?>"><?= htmlspecialchars(tt('rooms_history', 'History')) ?></a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>

<script>
  const createBtn = document.getElementById('createRoomBtn');
  const roomNameInput = document.getElementById('roomNameInput');
  const createMsg = document.getElementById('createMsg');
  const shareWrap = document.getElementById('shareWrap');
  const shareLink = document.getElementById('shareLink');
  const copyLinkBtn = document.getElementById('copyLinkBtn');
  const shareMsg = document.getElementById('shareMsg');
  const openRoomBtn = document.getElementById('openRoomBtn');
  const setMsg = (el, text = '', type = '') => {
    if (!el) return;
    el.textContent = text || '';
    el.classList.remove('text-danger', 'text-success');
    if (text && type === 'error') el.classList.add('text-danger');
    if (text && type === 'success') el.classList.add('text-success');
  };
  const STR = {
    shareCopied: <?= json_encode(tt('rooms_share_copied', 'Link copied.')) ?>,
    shareFailed: <?= json_encode(tt('rooms_share_failed', 'Copy failed.')) ?>,
    nameRequired: <?= json_encode(tt('rooms_name_required', 'Room name required')) ?>,
    roomCreated: <?= json_encode(tt('rooms_created', 'Room created.')) ?>,
    errorGeneric: <?= json_encode(tt('error_generic', 'Error')) ?>,
  };
  createBtn?.addEventListener('click', async () => {
    setMsg(createMsg);
    setMsg(shareMsg);
      const name = (roomNameInput.value || '').trim();
      if (!name) {
        setMsg(createMsg, STR.nameRequired, 'error');
        return;
      }
      try {
        const res = await fetch('api/rooms_create.php', {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify({rounds_total: 50, name})
        });
      const data = await res.json();
      if (data.ok && data.guid) {
        const url = 'room_play.php?guid=' + encodeURIComponent(data.guid);
        if (openRoomBtn) {
          openRoomBtn.hidden = false;
          openRoomBtn.href = url;
        }
        if (shareWrap) shareWrap.hidden = false;
        if (shareLink) shareLink.value = location.origin + '/' + url;
        setMsg(createMsg, STR.roomCreated, 'success');
        return;
      }
      setMsg(createMsg, data.error || STR.errorGeneric, 'error');
    } catch (e) {
      setMsg(createMsg, STR.errorGeneric, 'error');
    }
  });
  copyLinkBtn?.addEventListener('click', async () => {
    if (!shareLink) return;
    try {
      await navigator.clipboard.writeText(shareLink.value || '');
      setMsg(shareMsg, STR.shareCopied, 'success');
    } catch (e) {
      setMsg(shareMsg, STR.shareFailed, 'error');
    }
  });
</script>
</body>
</html>
