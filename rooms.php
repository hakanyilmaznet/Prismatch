<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
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

function room_status_badge(string $status): string {
    switch ($status) {
        case 'waiting':
            return '<span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1 rounded-pill">' . htmlspecialchars(tt('room_status_waiting', 'Waiting')) . '</span>';
        case 'active':
            return '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill"><span class="pulse-dot"></span> ' . htmlspecialchars(tt('room_status_active', 'Active')) . '</span>';
        case 'finished':
            return '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 rounded-pill">' . htmlspecialchars(tt('room_status_finished', 'Finished')) . '</span>';
        default:
            return '<span class="badge bg-secondary px-2 py-1 rounded-pill">' . htmlspecialchars($status) . '</span>';
    }
}

function format_room_dt(?string $utcIso): string {
    if (!$utcIso) return '-';
    try {
        $dt = new DateTimeImmutable($utcIso, new DateTimeZone('UTC'));
        return $dt->format('d/m/Y H:i');
    } catch (\Exception $e) {
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

$rooms = list_user_rooms((string)$userId, 100);
$seoTitle = tt('rooms_title', 'Multiplayer Rooms');
$seoDescription = tt('rooms_desc', 'Create or join multiplayer rooms to compete live in real time.');
$seoLangs = function_exists('supported_languages') ? array_keys(supported_languages()) : [];
?>
<!doctype html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= htmlspecialchars($dir) ?>">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <script>
    (function(){
      const stored = localStorage.getItem('pm-theme');
      const prefers = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
      document.documentElement.setAttribute('data-bs-theme', stored || prefers);
    })();
  </script>
  <link href="css/bootstrap.min.css" rel="stylesheet" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700;800&family=Baloo+2:wght@500;600;700;800&display=swap" rel="stylesheet" />
  <title><?= htmlspecialchars($seoTitle) ?> - <?= htmlspecialchars(tt('app_name', 'Prismatch')) ?></title>
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
    :root {
      --pm-accent: #ff6b5b;
      --pm-accent-glow: rgba(255, 107, 91, 0.25);
      --pm-radius: 20px;
    }
    body {
      margin: 0;
      font-family: "Rubik", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background: var(--bs-body-bg);
      color: var(--bs-body-color);
      min-height: 100vh;
      padding-top: calc(var(--pm-header-offset, 0px) + 24px);
      padding-bottom: calc(var(--pm-footer-offset, 0px) + 32px);
    }
    .wrap {
      max-width: 1040px;
      margin: 0 auto;
      padding: 0 16px;
      display: flex;
      flex-direction: column;
      gap: 24px;
    }
    .hero-card {
      background: linear-gradient(135deg, rgba(255, 107, 91, 0.12), rgba(73, 242, 178, 0.08));
      border: 1px solid rgba(255, 255, 255, 0.15);
      border-radius: var(--pm-radius);
      padding: 28px 24px;
      backdrop-filter: blur(12px);
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 18px;
      box-shadow: 0 16px 40px rgba(0, 0, 0, 0.06);
    }
    [data-bs-theme="dark"] .hero-card {
      background: linear-gradient(135deg, rgba(255, 107, 91, 0.18), rgba(73, 242, 178, 0.06));
      border-color: rgba(255, 255, 255, 0.08);
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.45);
    }
    .hero-title {
      font-family: "Baloo 2", sans-serif;
      font-weight: 800;
      font-size: 28px;
      margin: 0 0 6px 0;
      line-height: 1.2;
    }
    .card-modern {
      background: var(--bs-card-bg, rgba(255, 255, 255, 0.85));
      border: 1px solid rgba(0, 0, 0, 0.08);
      border-radius: var(--pm-radius);
      padding: 24px;
      backdrop-filter: blur(16px);
      box-shadow: 0 12px 36px rgba(0, 0, 0, 0.04);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    [data-bs-theme="dark"] .card-modern {
      background: rgba(18, 22, 34, 0.7);
      border-color: rgba(255, 255, 255, 0.08);
      box-shadow: 0 16px 40px rgba(0, 0, 0, 0.35);
    }
    .pulse-dot {
      display: inline-block;
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: #20b77d;
      margin-right: 4px;
      box-shadow: 0 0 0 rgba(32, 183, 125, 0.7);
      animation: pulse 1.8s infinite;
    }
    @keyframes pulse {
      0% { box-shadow: 0 0 0 0 rgba(32, 183, 125, 0.7); }
      70% { box-shadow: 0 0 0 8px rgba(32, 183, 125, 0); }
      100% { box-shadow: 0 0 0 0 rgba(32, 183, 125, 0); }
    }
    .btn-create {
      background: linear-gradient(135deg, #ff6b5b, #ff8c42);
      border: none;
      color: #fff;
      font-weight: 600;
      padding: 10px 22px;
      border-radius: 12px;
      box-shadow: 0 6px 18px rgba(255, 107, 91, 0.35);
      transition: all 0.2s ease;
    }
    .btn-create:hover {
      background: linear-gradient(135deg, #ff5744, #ff7e2e);
      color: #fff;
      transform: translateY(-1px);
      box-shadow: 0 8px 22px rgba(255, 107, 91, 0.45);
    }
    .table-modern {
      margin-bottom: 0;
    }
    .table-modern th {
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--bs-secondary-color);
      border-bottom-width: 1px;
      padding: 12px 14px;
    }
    .table-modern td {
      padding: 14px;
      vertical-align: middle;
    }
    .winner-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-weight: 600;
      color: #ffb020;
    }
  </style>
</head>
<body>
  <?php include __DIR__ . '/header.php'; ?>

  <main class="wrap">
    <!-- Hero / Intro -->
    <div class="hero-card">
      <div>
        <h1 class="hero-title"><?= htmlspecialchars(tt('rooms_title', 'Multiplayer Rooms')) ?></h1>
        <div class="text-secondary"><?= htmlspecialchars(tt('rooms_desc', 'Create or join rooms to compete with friends live in real time.')) ?></div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2">
          50 Rounds Max · Instant Elimination
        </span>
      </div>
    </div>

    <!-- Create Room Card -->
    <div class="card-modern">
      <h2 class="h5 fw-bold mb-3 d-flex align-items-center gap-2">
        <span>🎮</span> <?= htmlspecialchars(tt('rooms_create_title', 'Create a New Room')) ?>
      </h2>
      <div class="row g-3 align-items-end">
        <div class="col-md-7 col-lg-8">
          <label class="form-label small fw-semibold text-secondary" for="roomNameInput">
            <?= htmlspecialchars(tt('rooms_name_label', 'Room Name')) ?>
          </label>
          <input id="roomNameInput" class="form-control form-control-lg rounded-3" type="text" maxlength="80" placeholder="e.g. Arena Champions #1" />
          <div id="createMsg" class="small mt-2"></div>
        </div>
        <div class="col-md-5 col-lg-4 d-flex gap-2">
          <button id="createRoomBtn" class="btn btn-create btn-lg w-100" type="button">
            <?= htmlspecialchars(tt('rooms_create_btn', 'Create Room')) ?>
          </button>
        </div>
      </div>

      <!-- Share Box (Revealed after creation) -->
      <div id="shareWrap" class="mt-4 pt-3 border-top" hidden>
        <div class="alert alert-success d-flex flex-column gap-2 rounded-3 mb-0">
          <div class="fw-semibold">🎉 Room created successfully! Share this link with players:</div>
          <div class="input-group">
            <input id="shareLink" class="form-control" type="text" readonly />
            <button id="copyLinkBtn" class="btn btn-dark" type="button"><?= htmlspecialchars(tt('rooms_copy', 'Copy Link')) ?></button>
            <a id="openRoomBtn" class="btn btn-primary" href="#"><?= htmlspecialchars(tt('rooms_open', 'Enter Room')) ?> →</a>
          </div>
          <div id="shareMsg" class="small text-success"></div>
        </div>
      </div>
    </div>

    <!-- Room History Card -->
    <div class="card-modern">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="h5 fw-bold mb-0 d-flex align-items-center gap-2">
          <span>📜</span> <?= htmlspecialchars(tt('rooms_history', 'Your Match History')) ?>
        </h2>
        <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2.5 py-1">
          <?= count($rooms) ?> <?= htmlspecialchars(tt('rooms_total', 'rooms')) ?>
        </span>
      </div>

      <?php if (!$rooms): ?>
        <div class="text-center py-5 text-secondary">
          <div class="fs-1 mb-2">🏟️</div>
          <div class="fw-semibold"><?= htmlspecialchars(tt('no_results', 'No matches found.')) ?></div>
          <div class="small"><?= htmlspecialchars(tt('rooms_empty_hint', 'Create your first multiplayer room above to start competing!')) ?></div>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-modern align-middle">
            <thead>
              <tr>
                <th><?= htmlspecialchars(tt('room_name', 'Room Name')) ?></th>
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
              $guid = (string)($r['guid'] ?? '');
              $status = (string)($r['status'] ?? 'waiting');
            ?>
              <tr>
                <td class="fw-semibold text-break">
                  <?= htmlspecialchars($r['name'] ?: 'Room #' . substr($guid, 0, 8)) ?>
                </td>
                <td><?= room_status_badge($status) ?></td>
                <td><span class="fw-semibold"><?= (int)$r['current_round'] ?></span> / <?= (int)$r['rounds_total'] ?></td>
                <td>
                  <?php if ($winner): ?>
                    <span class="winner-badge">👑 <?= htmlspecialchars($winner) ?></span>
                  <?php else: ?>
                    <span class="text-secondary">-</span>
                  <?php endif; ?>
                </td>
                <td class="small text-secondary"><?= htmlspecialchars(format_room_dt($r['created_at'])) ?></td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm">
                    <a class="btn btn-primary" href="room_play.php?guid=<?= urlencode($guid) ?>">
                      <?= htmlspecialchars(tt('rooms_open', 'Open')) ?>
                    </a>
                    <a class="btn btn-outline-secondary" href="room_history.php?guid=<?= urlencode($guid) ?>">
                      <?= htmlspecialchars(tt('rooms_history', 'Details')) ?>
                    </a>
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
      el.className = 'small mt-2 ' + (type === 'error' ? 'text-danger fw-semibold' : 'text-success fw-semibold');
    };

    const STR = {
      shareCopied: <?= json_encode(tt('rooms_share_copied', 'Link copied to clipboard!')) ?>,
      shareFailed: <?= json_encode(tt('rooms_share_failed', 'Could not copy link.')) ?>,
      nameRequired: <?= json_encode(tt('rooms_name_required', 'Please enter a room name.')) ?>,
      errorGeneric: <?= json_encode(tt('error_generic', 'An error occurred. Please try again.')) ?>,
    };

    createBtn?.addEventListener('click', async () => {
      setMsg(createMsg, '');
      const name = (roomNameInput.value || '').trim();
      if (!name) {
        setMsg(createMsg, STR.nameRequired, 'error');
        roomNameInput.focus();
        return;
      }

      createBtn.disabled = true;
      createBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Creating...';

      try {
        const res = await fetch('api/rooms_create.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({rounds_total: 50, name})
        });
        const data = await res.json();
        if (data.ok && data.guid) {
          const url = new URL('room_play.php?guid=' + encodeURIComponent(data.guid), window.location.href).toString();
          if (shareWrap && shareLink) {
            shareWrap.hidden = false;
            shareLink.value = url;
            if (openRoomBtn) openRoomBtn.href = url;
            shareWrap.scrollIntoView({behavior: 'smooth'});
          } else {
            window.location.href = url;
          }
        } else {
          setMsg(createMsg, data.error || STR.errorGeneric, 'error');
        }
      } catch (e) {
        setMsg(createMsg, STR.errorGeneric, 'error');
      } finally {
        createBtn.disabled = false;
        createBtn.textContent = <?= json_encode(tt('rooms_create_btn', 'Create Room')) ?>;
      }
    });

    copyLinkBtn?.addEventListener('click', async () => {
      if (!shareLink || !shareLink.value) return;
      try {
        await navigator.clipboard.writeText(shareLink.value);
        copyLinkBtn.textContent = 'Copied!';
        setTimeout(() => { copyLinkBtn.textContent = <?= json_encode(tt('rooms_copy', 'Copy Link')) ?>; }, 2000);
      } catch (e) {
        shareLink.select();
        document.execCommand('copy');
        copyLinkBtn.textContent = 'Copied!';
        setTimeout(() => { copyLinkBtn.textContent = <?= json_encode(tt('rooms_copy', 'Copy Link')) ?>; }, 2000);
      }
    });
  </script>
</body>
</html>
