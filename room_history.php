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
    case 'eliminated': return tt('room_status_eliminated', 'Eliminated');
    default: return (string)$status;
  }
}

if (!$userEmail) {
  header('Location: login.php');
  exit;
}

$guid = trim((string)($_GET['guid'] ?? ''));
$room = $guid ? get_room_by_guid($guid) : null;
if (!$room) {
  http_response_code(404);
  echo htmlspecialchars(tt('room_not_found', 'Room not found'));
  exit;
}

$players = list_room_players((int)$room['id']);
$pdo = db();
$rounds = $pdo->prepare("SELECT round_index, question_json, started_at, ended_at FROM room_rounds WHERE room_id=:rid ORDER BY round_index ASC");
$rounds->execute([':rid' => (int)$room['id']]);
$rounds = $rounds->fetchAll() ?: [];

$eventsStmt = $pdo->prepare("SELECT round_index, email, event_type, payload_json, created_at FROM room_events WHERE room_id=:rid ORDER BY id ASC");
$eventsStmt->execute([':rid' => (int)$room['id']]);
$events = $eventsStmt->fetchAll() ?: [];

$byRound = [];
foreach ($events as $e) {
  $r = (int)$e['round_index'];
  if (!isset($byRound[$r])) $byRound[$r] = [];
  $payload = json_decode($e['payload_json'], true);
  $byRound[$r][] = [
    'email' => $e['email'],
    'type' => $e['event_type'],
    'payload' => $payload ?: [],
    'created_at' => $e['created_at'],
  ];
}

$seoTitle = tt('room_history_title', 'Room History');
$seoDescription = tt('room_history_desc', 'Room match history and eliminations.');
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
    'robots' => 'noindex,follow',
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
      <div class="h5 mb-1"><?= htmlspecialchars(tt('room_history_title', 'Room History')) ?></div>
      <div class="text-muted small">
        <?= htmlspecialchars($room['name'] ?: tt('rooms_name', 'Room')) ?> • <?= htmlspecialchars(tt('room_guid', 'GUID')) ?>: <?= htmlspecialchars($room['guid']) ?> • <?= htmlspecialchars(room_status_label($room['status'] ?? '')) ?>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <div class="h6 mb-2"><?= htmlspecialchars(tt('room_leaderboard', 'Leaderboard')) ?></div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>#</th>
              <th><?= htmlspecialchars(tt('room_player', 'Player')) ?></th>
              <th><?= htmlspecialchars(tt('room_score', 'Score')) ?></th>
              <th><?= htmlspecialchars(tt('room_correct', 'Correct')) ?></th>
              <th><?= htmlspecialchars(tt('room_status', 'Status')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($players as $i => $p): ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($p['email']) ?></td>
                <td><?= (int)$p['score'] ?></td>
                <td><?= (int)$p['correct'] ?></td>
                <td><?= htmlspecialchars(room_status_label($p['status'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <div class="h6 mb-2"><?= htmlspecialchars(tt('room_rounds', 'Rounds')) ?></div>
      <?php if (!$rounds): ?>
        <div class="text-muted"><?= htmlspecialchars(tt('room_no_rounds', 'No rounds recorded.')) ?></div>
      <?php else: ?>
        <?php foreach ($rounds as $r): ?>
          <?php $q = json_decode($r['question_json'], true); $target = $q['target'] ?? ''; ?>
          <div class="mb-3">
            <?php
              $roundLabel = tt('room_round_label', 'Round {n}');
              $roundLabel = str_replace('{n}', (string)(int)$r['round_index'], $roundLabel);
            ?>
            <div class="fw-semibold"><?= htmlspecialchars($roundLabel) ?></div>
            <?php if ($target): ?>
              <div class="text-muted small">
                <?= htmlspecialchars(tt('room_target_label', 'Target')) ?>:
                <span class="d-inline-block rounded-circle border" style="width:12px;height:12px;background:<?= htmlspecialchars($target) ?>"></span>
                <?= htmlspecialchars($target) ?>
              </div>
            <?php endif; ?>
            <div class="mt-2">
              <?php foreach (($byRound[(int)$r['round_index']] ?? []) as $ev): ?>
                <?php if ($ev['type'] === 'eliminate'): ?>
                  <?php
                    $picked = (string)($ev['payload']['picked'] ?? '');
                    $msg = $picked !== ''
                      ? tt('room_event_eliminated_pick', 'eliminated (picked {color})')
                      : tt('room_event_eliminated', 'eliminated');
                    $msg = str_replace('{color}', $picked, $msg);
                  ?>
                  <div class="small text-danger">✖ <?= htmlspecialchars($ev['email']) ?> <?= htmlspecialchars($msg) ?></div>
                <?php elseif ($ev['type'] === 'answer'): ?>
                  <?php
                    $score = (int)($ev['payload']['score_delta'] ?? 0);
                    $msg = tt('room_event_correct', 'correct (+{score})');
                    $msg = str_replace('{score}', (string)$score, $msg);
                  ?>
                  <div class="small">✓ <?= htmlspecialchars($ev['email']) ?> <?= htmlspecialchars($msg) ?></div>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</main>
<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
