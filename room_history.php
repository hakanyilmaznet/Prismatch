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

function room_status_label($status) {
    switch ((string)$status) {
        case 'waiting': return tt('room_status_waiting', 'Waiting');
        case 'active': return tt('room_status_active', 'Active');
        case 'finished': return tt('room_status_finished', 'Finished');
        case 'eliminated': return tt('room_status_eliminated', 'Eliminated');
        default: return (string)$status;
    }
}

if (!$userEmail || !$userId) {
    $next = 'room_history.php?guid=' . rawurlencode((string)($_GET['guid'] ?? ''));
    header('Location: login.php?next=' . rawurlencode($next));
    exit;
}

$guid = trim((string)($_GET['guid'] ?? ''));
$room = $guid ? get_room_by_guid($guid) : null;
if (!$room) {
    http_response_code(404);
    echo htmlspecialchars(tt('room_not_found', 'Room not found'));
    exit;
}

$players = list_room_players((string)$room['id']);
$pdo = db();
$roundsStmt = $pdo->prepare("SELECT round_index, question_json, started_at, ended_at FROM room_rounds WHERE room_id=:rid ORDER BY round_index ASC");
$roundsStmt->execute([':rid' => (string)$room['id']]);
$rounds = $roundsStmt->fetchAll() ?: [];

$eventsStmt = $pdo->prepare("SELECT round_index, email, event_type, payload_json, created_at FROM room_events WHERE room_id=:rid ORDER BY id ASC");
$eventsStmt->execute([':rid' => (string)$room['id']]);
$events = $eventsStmt->fetchAll() ?: [];

$byRound = [];
foreach ($events as $e) {
    $r = (int)$e['round_index'];
    if (!isset($byRound[$r])) $byRound[$r] = [];
    $payload = json_decode($e['payload_json'], true);
    $email = (string)($e['email'] ?? '');
    $displayName = user_display_name_from_row(['email' => $email]);
    $byRound[$r][] = [
        'email' => $email,
        'display_name' => $displayName !== '' ? $displayName : $email,
        'type' => $e['event_type'],
        'payload' => $payload ?: [],
        'created_at' => $e['created_at'],
    ];
}

$seoTitle = ($room['name'] ?: tt('room_history_title', 'Room History')) . ' - ' . tt('app_name', 'Prismatch');
$seoDescription = tt('room_history_desc', 'Room match round-by-round events and leaderboards.');
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
  <link href="css/style.css" rel="stylesheet" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700;800&family=Baloo+2:wght@500;600;700;800&display=swap" rel="stylesheet" />
  <title><?= htmlspecialchars($seoTitle) ?></title>
  <link rel="icon" type="image/svg+xml" href="logo.svg" />
  <?= seo_meta([
    'title' => $seoTitle,
    'description' => $seoDescription,
    'url' => seo_current_url(),
    'image' => '/logo.png',
    'type' => 'website',
    'robots' => 'noindex,follow',
    'lang' => $lang,
    'site_name' => tt('app_name', 'Prismatch'),
  ]) ?>
  <style>
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
      max-width: 980px;
      margin: 0 auto;
      padding: 0 16px;
      display: flex;
      flex-direction: column;
      gap: 20px;
    }
    .card-modern {
      background: var(--bs-card-bg, rgba(255, 255, 255, 0.85));
      border: 1px solid rgba(0, 0, 0, 0.08);
      border-radius: 20px;
      padding: 24px;
      backdrop-filter: blur(16px);
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
    }
    [data-bs-theme="dark"] .card-modern {
      background: rgba(18, 22, 34, 0.7);
      border-color: rgba(255, 255, 255, 0.08);
      box-shadow: 0 16px 40px rgba(0, 0, 0, 0.35);
    }
    .color-swatch {
      width: 22px;
      height: 22px;
      border-radius: 6px;
      display: inline-block;
      vertical-align: middle;
      border: 2px solid rgba(255, 255, 255, 0.3);
      box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    }
    .round-card {
      background: rgba(255,255,255,0.03);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 14px;
      padding: 16px;
      margin-bottom: 12px;
    }
    [data-bs-theme="light"] .round-card {
      background: rgba(0,0,0,0.02);
      border-color: rgba(0,0,0,0.06);
    }
  </style>
</head>
<body>
  <?php include __DIR__ . '/header.php'; ?>

  <main class="wrap">
    <div class="card-modern d-flex flex-wrap align-items-center justify-content-between gap-3">
      <div>
        <h1 class="h4 fw-bold mb-1 d-flex align-items-center gap-2">
          <span>📜</span> <?= htmlspecialchars($room['name'] ?: tt('room_history_title', 'Room Match History')) ?>
        </h1>
        <div class="small text-secondary">
          GUID: <code class="text-secondary"><?= htmlspecialchars($room['guid']) ?></code> · 
          Status: <span class="badge bg-secondary-subtle text-secondary rounded-pill"><?= htmlspecialchars(room_status_label($room['status'] ?? '')) ?></span>
        </div>
      </div>
      <div>
        <a class="btn btn-outline-primary rounded-pill px-4" href="rooms.php">← <?= htmlspecialchars(tt('rooms_title', 'All Rooms')) ?></a>
      </div>
    </div>

    <!-- Leaderboard -->
    <div class="card-modern">
      <h2 class="h5 fw-bold mb-3 d-flex align-items-center gap-2">
        <span>🏆</span> <?= htmlspecialchars(tt('room_leaderboard', 'Final Leaderboard')) ?>
      </h2>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr class="text-secondary small">
              <th>#</th>
              <th><?= htmlspecialchars(tt('room_player', 'Player')) ?></th>
              <th><?= htmlspecialchars(tt('room_score', 'Score')) ?></th>
              <th><?= htmlspecialchars(tt('room_correct', 'Correct Answers')) ?></th>
              <th><?= htmlspecialchars(tt('room_status', 'Status')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($players as $i => $p): ?>
              <tr class="<?= $i === 0 ? 'table-active fw-bold' : '' ?>">
                <td><?= $i === 0 ? '👑 1' : $i + 1 ?></td>
                <td><?= htmlspecialchars($p['email']) ?></td>
                <td><span class="badge bg-primary-subtle text-primary rounded-pill"><?= (int)$p['score'] ?></span></td>
                <td><?= (int)$p['correct'] ?></td>
                <td>
                  <span class="badge <?= $p['status'] === 'eliminated' ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' ?> rounded-pill">
                    <?= htmlspecialchars(room_status_label($p['status'] ?? '')) ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Rounds breakdown -->
    <div class="card-modern">
      <h2 class="h5 fw-bold mb-3 d-flex align-items-center gap-2">
        <span>🎯</span> <?= htmlspecialchars(tt('room_rounds', 'Round Breakdown')) ?>
      </h2>
      <?php if (!$rounds): ?>
        <div class="text-center py-4 text-secondary"><?= htmlspecialchars(tt('room_no_rounds', 'No rounds recorded.')) ?></div>
      <?php else: ?>
        <?php foreach ($rounds as $r):
          $q = json_decode($r['question_json'], true);
          $target = $q['target'] ?? '';
          $roundNum = (int)$r['round_index'];
        ?>
          <div class="round-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="fw-bold fs-6">Round <?= $roundNum ?></span>
              <?php if ($target): ?>
                <div class="small d-flex align-items-center gap-2">
                  <span class="text-secondary"><?= htmlspecialchars(tt('room_target_label', 'Target Color')) ?>:</span>
                  <span class="color-swatch" style="background:<?= htmlspecialchars($target) ?>"></span>
                  <code><?= htmlspecialchars($target) ?></code>
                </div>
              <?php endif; ?>
            </div>
            
            <div class="d-flex flex-column gap-1 mt-2">
              <?php foreach (($byRound[$roundNum] ?? []) as $ev): ?>
                <?php if ($ev['type'] === 'eliminate' || $ev['type'] === 'timeout'): ?>
                  <div class="small text-danger d-flex align-items-center gap-1">
                    <span>❌</span> <strong><?= htmlspecialchars($ev['display_name']) ?></strong> was eliminated <?= $ev['type'] === 'timeout' ? '(Time up)' : '' ?>
                  </div>
                <?php elseif ($ev['type'] === 'answer'): ?>
                  <?php $score = (int)($ev['payload']['score_delta'] ?? 0); ?>
                  <div class="small text-success d-flex align-items-center gap-1">
                    <span>✅</span> <strong><?= htmlspecialchars($ev['display_name']) ?></strong> correct (+<?= $score ?> pts)
                  </div>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </main>

  <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
