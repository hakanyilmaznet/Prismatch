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
$userId = $_SESSION['user_id'] ?? null;
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

function room_winner_name(string $roomId): ?string {
  $email = room_winner_email($roomId);
  if (!$email) return null;
  return user_display_name_from_row(['email' => $email]);
}

if (!$userEmail || !$userId) {
  header('Location: login.php?next=' . rawurlencode('rooms.php'));
  exit;
}

$rooms = list_user_rooms($userId, 100);
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
  <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700;800&family=Baloo+2:wght@500;600;700&display=swap" rel="stylesheet" />
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
      font-family: "Rubik", "Segoe UI", "Helvetica Neue", sans-serif;
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
    .form-msg{ font-size: 12px; margin-top: 6px; line-height: 1.35; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/header.php'; ?>
  <main class="wrap">
    <div class="cardx">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
          <h1><?= htmlspecialchars(tt('rooms_title', 'Rooms')) ?></h1>
          <div class="muted"><?= htmlspecialchars(tt('rooms_desc', 'Create or join rooms to compete live.')) ?></div>
        </div>
      </div>
    </div>

    <div class="cardx">
      <h2 class="h5 mb-2"><?= htmlspecialchars(tt('rooms_create_title', 'Create a room')) ?></h2>
      <div class="grid">
        <div>
          <label class="form-label" for="roomNameInput"><?= htmlspecialchars(tt('rooms_name_label', 'Room name')) ?></label>
          <input id="roomNameInput" class="form-control" type="text" maxlength="80" />
          <div id="createMsg" class="form-msg muted"></div>
        </div>
        <div>
          <button id="createRoomBtn" class="btn btn-primary" type="button"><?= htmlspecialchars(tt('rooms_create_btn', 'Create room')) ?></button>
          <a id="openRoomBtn" class="btn btn-outline-secondary" href="#" hidden><?= htmlspecialchars(tt('rooms_open', 'Open')) ?></a>
          <div id="shareWrap" class="mt-2" hidden>
            <label class="form-label" for="shareLink"><?= htmlspecialchars(tt('rooms_share_link', 'Share link')) ?></label>
            <div class="input-group">
              <input id="shareLink" class="form-control" type="text" readonly />
              <button id="copyLinkBtn" class="btn btn-outline-secondary" type="button"><?= htmlspecialchars(tt('rooms_copy', 'Copy')) ?></button>
            </div>
            <div id="shareMsg" class="form-msg muted"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="cardx">
      <h2 class="h5 mb-2"><?= htmlspecialchars(tt('rooms_history', 'History')) ?></h2>
      <?php if (!$rooms): ?>
        <div class="muted"><?= htmlspecialchars(tt('no_results', 'No results')) ?></div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th><?= htmlspecialchars(tt('room_name', 'Name')) ?></th>
                <th><?= htmlspecialchars(tt('room_status', 'Status')) ?></th>
                <th><?= htmlspecialchars(tt('room_rounds', 'Rounds')) ?></th>
                <th><?= htmlspecialchars(tt('room_winner', 'Winner')) ?></th>
                <th><?= htmlspecialchars(tt('room_created', 'Created')) ?></th>
                <th class="text-end"><?= htmlspecialchars(tt('room_action', 'Action')) ?></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($rooms as $r):
              $winner = room_winner_name((string)($r['id'] ?? ''));
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
