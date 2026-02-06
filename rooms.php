<?php
require_once __DIR__ . '/bootstrap.php';
if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
  error_reporting(E_ALL);
  @ini_set('display_errors', '1');
  @ini_set('display_startup_errors', '1');
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/i18n.php';

$lang = function_exists('get_lang') ? get_lang() : 'en';
$dir  = function_exists('lang_dir') ? lang_dir($lang) : 'ltr';

$userEmail = $_SESSION['user_email'] ?? null;
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

function room_winner_name(int $roomId): ?string {
  $email = room_winner_email($roomId);
  if (!$email) return null;
  return user_display_name_from_row(['email' => $email]);
}

if (!$userEmail) {
  header('Location: login.php?next=' . rawurlencode('rooms.php'));
  exit;
}

$rooms = list_user_rooms($userEmail, 100);
$seoTitle = tt('rooms_title', 'Rooms');
$seoDescription = tt('rooms_desc', 'Create or join rooms to compete live.');
$seoLangs = function_exists('supported_languages') ? array_keys(supported_languages()) : [];
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
    .wrap{ max-width: 980px; margin:0 auto; padding: 18px; display:grid; gap:18px; }
    .cardx{ background: rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.12); border-radius: 16px; padding:16px; }
    [data-bs-theme="light"] .cardx{ background: rgba(255,255,255,0.9); border-color: rgba(0,0,0,0.08); }
    .grid{ display:grid; gap:12px; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
    .muted{ opacity:.7; }
    .form-msg{
      font-size: 12px;
      margin-top: 6px;
      line-height: 1.35;
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<main class="wrap">
  <div class="cardx">
    <h1 class="h4 m-0"><?= htmlspecialchars(tt('rooms_title', 'Rooms')) ?></h1>
    <div class="muted"><?= htmlspecialchars(tt('rooms_desc', 'Create or join rooms to compete live.')) ?></div>
  </div>

  <div class="grid">
    <div class="cardx">
      <h2 class="h6"><?= htmlspecialchars(tt('rooms_create', 'Create room')) ?></h2>
      <div class="mb-2 muted"><?= htmlspecialchars(tt('rooms_name_label', 'Room name')) ?></div>
      <input type="text" id="roomNameInput" class="form-control mb-2" maxlength="80"
             placeholder="<?= htmlspecialchars(tt('rooms_name_placeholder', 'Give your room a name')) ?>" />
      <div class="input-group mb-2">
        <button class="btn btn-primary" id="createRoomBtn"><?= htmlspecialchars(tt('rooms_create_btn', 'Create')) ?></button>
      </div>
      <div id="createMsg" class="form-msg"></div>
      <div id="shareWrap" class="mt-3" hidden>
        <div class="small muted mb-2"><?= htmlspecialchars(tt('rooms_share_label', 'Share room link')) ?></div>
        <div class="input-group">
          <input type="text" id="shareLink" class="form-control" readonly />
          <button class="btn btn-outline-primary" id="copyLinkBtn"><?= htmlspecialchars(tt('rooms_share_copy', 'Copy link')) ?></button>
        </div>
        <div id="shareMsg" class="form-msg mt-1"></div>
      </div>
    </div>

    <div class="cardx">
      <h2 class="h6"><?= htmlspecialchars(tt('rooms_join', 'Join room')) ?></h2>
      <div class="muted"><?= htmlspecialchars(tt('rooms_join_hint', 'Join by opening the room link.')) ?></div>
      <a class="btn btn-outline-primary mt-3" id="openRoomBtn" href="#" hidden>
        <?= htmlspecialchars(tt('rooms_open', 'Open')) ?>
      </a>
    </div>
  </div>

  <div class="cardx">
    <h2 class="h6"><?= htmlspecialchars(tt('rooms_recent', 'Your rooms')) ?></h2>
    <?php if (!$rooms): ?>
      <div class="muted"><?= htmlspecialchars(tt('rooms_empty', 'No rooms yet.')) ?></div>
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
                  $winner = room_winner_name((int)$r['id']);
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
    el.classList.remove('pm-error', 'pm-success');
    if (text && type) el.classList.add(`pm-${type}`);
  };
  const STR = {
    shareCopied: <?= json_encode(tt('rooms_share_copied', 'Link copied.')) ?>,
    shareFailed: <?= json_encode(tt('rooms_share_failed', 'Copy failed.')) ?>,
    nameRequired: <?= json_encode(tt('rooms_name_required', 'Room name required')) ?>,
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
        if (shareWrap && shareLink) {
          shareWrap.hidden = false;
          shareLink.value = new URL(url, window.location.href).toString();
          setMsg(createMsg);
          return;
        }
        window.location.href = url;
      } else {
        setMsg(createMsg, STR.errorGeneric, 'error');
      }
    } catch(e){
      setMsg(createMsg, STR.errorGeneric, 'error');
    }
  });

  copyLinkBtn?.addEventListener('click', async () => {
    if (!shareLink || !shareLink.value) return;
    try {
      await navigator.clipboard.writeText(shareLink.value);
      setMsg(shareMsg, STR.shareCopied, 'success');
    } catch (e) {
      try {
        shareLink.select();
        document.execCommand('copy');
        setMsg(shareMsg, STR.shareCopied, 'success');
      } catch (err) {
        setMsg(shareMsg, STR.shareFailed, 'error');
      }
    }
  });
</script>
</body>
</html>



