<?php
require_once __DIR__ . '/bootstrap.php';
if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
  error_reporting(E_ALL);
  @ini_set('display_errors', '1');
  @ini_set('display_startup_errors', '1');
}

require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/db.php';

$lang = get_lang();
$dir  = lang_dir($lang);

$userEmail = $_SESSION['user_email'] ?? null;
$userId = $_SESSION['user_id'] ?? null;
if (!$userEmail || !$userId) {
  header('Location: login.php?next=' . rawurlencode('game.php?id=' . ($_GET['id'] ?? '')));
  exit;
}
header('X-Robots-Tag: noindex, nofollow', true);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function t_safe($k, $fallback){ $v = t($k); return $v === $k ? $fallback : $v; }

function fmt_dt($iso, $lang){
  if (function_exists('format_dt_local')) return (string)format_dt_local($iso, $lang);
  if (!$iso) return '-';
  try { return (new DateTimeImmutable($iso))->format('d/m/Y H:i'); } catch (Exception $e) { return '-'; }
}

function fmt_num($v, $lang, $dec = 0){
  if (function_exists('format_num_local')) return (string)format_num_local($v, $lang, $dec);
  return number_format((float)$v, $dec, '.', ',');
}

function mmss($ms){
  $sec = (int)round(((int)$ms) / 1000);
  $m = (int)floor(max(0, $sec) / 60);
  $s = max(0, $sec) % 60;
  return sprintf('%d:%02d', $m, $s);
}

$id = $_GET['id'] ?? '';
$game = get_game_by_id($id);
if (!$game || (int)$game['user_id'] !== (int)$userId) {
  header('Location: games.php');
  exit;
}

$rounds = list_game_rounds($id);
$gameCountry = $game['country'] ?? '';
$seoLangs = function_exists('supported_languages') ? array_keys(supported_languages()) : [];
?>
<!doctype html>
<html lang="<?= h($lang) ?>" dir="<?= h($dir) ?>">
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
  <title><?= h(t('game_details_title')) ?> — <?= h(t('app_name')) ?></title>
  <link rel="icon" type="image/svg+xml" href="favicon.svg" />
  <?= seo_meta([
    'title' => t('game_details_title'),
    'description' => t_safe('game_details_description', 'Prismatch game details'),
    'url' => seo_current_url(),
    'image' => '/logo.png',
    'type' => 'website',
    'robots' => 'noindex,nofollow',
    'lang' => $lang,
    'site_name' => t('app_name'),
  ]) ?>
  <?= seo_alternate_links($seoLangs, seo_current_url()) ?>
</head>
<body class="bg-body">
<?php include __DIR__ . '/header.php'; ?>
<main class="container py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <h1 class="h4 mb-0"><?= h(t('game_details_title')) ?></h1>
        <?php if (is_daily_game($game)): ?>
          <span class="badge text-bg-warning">?? <?= h(t_safe('daily_title','Daily Challenge')) ?></span>
        <?php else: ?>
          <span class="badge text-bg-primary">?? <?= h(t('app_name')) ?></span>
        <?php endif; ?>
      </div>
      <div class="text-body-secondary small"><?= h(t('logged_in_as', ['email'=>$userEmail])) ?></div>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2" href="games.php">
        <img src="bootstrap-icons/arrow-left.svg" alt="" aria-hidden="true" width="16" height="16" />
        <?= h(t('btn_back_to_history')) ?>
      </a>
      <a class="btn btn-outline-primary btn-sm d-flex align-items-center gap-2" href="play.php">
        <img src="bootstrap-icons/play-fill.svg" alt="" aria-hidden="true" width="16" height="16" />
        <?= h(t('btn_back_to_game')) ?>
      </a>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-4"><div class="text-body-secondary small"><?= h(t('label_start')) ?></div><div class="fw-semibold"><?= h(fmt_dt($game['created_at'] ?? null, $lang)) ?></div></div>
        <div class="col-md-4"><div class="text-body-secondary small"><?= h(t('label_end')) ?></div><div class="fw-semibold"><?= h(fmt_dt($game['finished_at'] ?? null, $lang)) ?></div></div>
        <div class="col-md-4"><div class="text-body-secondary small"><?= h(t('label_duration')) ?></div><div class="fw-semibold"><?= h(mmss((int)$game['duration_ms'])) ?></div></div>
        <div class="col-md-4"><div class="text-body-secondary small"><?= h(t_safe('th_score','Score')) ?></div><div class="fw-semibold"><?= h(fmt_num((int)($game['score'] ?? 0), $lang, 0)) ?></div></div>
        <div class="col-md-4"><div class="text-body-secondary small"><?= h(t('label_reached_level')) ?></div><div class="fw-semibold"><?= h(fmt_num((int)$game['reached_level'], $lang, 0)) ?></div></div>
        <div class="col-md-4"><div class="text-body-secondary small"><?= h(t('label_total_correct')) ?></div><div class="fw-semibold"><?= h(fmt_num((int)$game['total_correct'], $lang, 0)) ?></div></div>
        <div class="col-md-4"><div class="text-body-secondary small"><?= h(t('label_status')) ?></div><div class="fw-semibold"><?= h(((int)$game['won'] === 1) ? t('status_won') : t('status_finished')) ?></div></div>
      </div>

      <?php if ($gameCountry): ?>
        <div class="mt-3">
          <?php $flagUrl = country_flag_icon_url(strtoupper($gameCountry)); ?>
          <span class="badge text-bg-light border text-dark">
            <span class="text-body-secondary me-1"><?= h(t_safe('label_country','Country')) ?>:</span>
            <?php if ($flagUrl !== ''): ?>
              <img src="<?= h($flagUrl) ?>" alt="<?= h(strtoupper($gameCountry)) ?>" width="16" height="16" class="rounded-circle me-1" />
            <?php endif; ?>
            <?= h(strtoupper($gameCountry)) ?>
          </span>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <h2 class="h6 mb-3"><?= h(t('round_details_title')) ?></h2>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
          <thead>
            <tr>
              <th><?= h(t('th_level')) ?></th>
              <th><?= h(t('th_target')) ?></th>
              <th><?= h(t_safe('hint_grid_9','Grid (9)')) ?></th>
              <th><?= h(t('th_pick')) ?></th>
              <th><?= h(t('th_response_time')) ?></th>
              <th><?= h(t('th_result')) ?></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rounds as $r):
            $gridArr = [];
            if (!empty($r['grid_colors_json']) && is_string($r['grid_colors_json'])) {
              $tmp = json_decode($r['grid_colors_json'], true);
              if (is_array($tmp)) $gridArr = $tmp;
            }
            $ok = (int)$r['is_correct'] === 1;
          ?>
            <tr>
              <td><?= h(fmt_num((int)$r['level'], $lang, 0)) ?></td>
              <td>
                <span class="d-inline-flex align-items-center gap-2">
                  <span class="rounded-circle border d-inline-block" style="width:12px;height:12px;background:<?= h($r['target_color']) ?>"></span>
                  <span><?= h($r['target_color']) ?></span>
                </span>
              </td>
              <td>
                <?php if ($gridArr): ?>
                  <div class="d-flex flex-wrap gap-1">
                    <?php foreach ($gridArr as $c): ?>
                      <span class="rounded-circle border d-inline-block" title="<?= h($c) ?>" style="width:12px;height:12px;background:<?= h($c) ?>"></span>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <span class="text-body-secondary">-</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($r['picked_color'])): ?>
                  <span class="d-inline-flex align-items-center gap-2">
                    <span class="rounded-circle border d-inline-block" style="width:12px;height:12px;background:<?= h($r['picked_color']) ?>"></span>
                    <span><?= h($r['picked_color']) ?></span>
                  </span>
                <?php else: ?>
                  <span class="text-body-secondary">-</span>
                <?php endif; ?>
              </td>
              <td><?= h(fmt_num(((int)$r['response_ms'])/1000, $lang, 2)) ?><?= h(t_safe('unit_seconds_short','s')) ?></td>
              <td>
                <span class="badge <?= $ok ? 'text-bg-success' : 'text-bg-danger' ?>">
                  <?= h($ok ? t('pill_correct') : t('pill_wrong')) ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<?php if (is_file(__DIR__ . '/footer.php')) include __DIR__ . '/footer.php'; ?>
<script>
function setTimezoneIfNeeded(){
  if (document.cookie.includes('tz=')) return;
  try {
    const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
    if (!tz) return;
    fetch('api/set_timezone.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({timezone: tz}),
      credentials: 'same-origin'
    }).then(() => location.reload()).catch(()=>{});
  } catch(e){}
}
setTimezoneIfNeeded();
</script>
</body>
</html>