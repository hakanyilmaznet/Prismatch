<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/db.php';

$lang = function_exists('get_lang') ? get_lang() : 'en';
$dir  = function_exists('lang_dir') ? lang_dir($lang) : 'ltr';

$userEmail = $_SESSION['user_email'] ?? null;
if (!$userEmail) {
  header('Location: index.php?session=expired');
  exit;
}
header('X-Robots-Tag: noindex, nofollow', true);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  echo "<!doctype html><html><head><meta charset='utf-8'><title>".htmlspecialchars(t('msg_invalid_id'))."</title></head><body style='font-family:system-ui;padding:20px'>".
       "<h1>".htmlspecialchars(t('msg_invalid_id'))."</h1>".
       "<p><a href='games.php'>".htmlspecialchars(t('btn_back_to_history'))."</a></p></body></html>";
  exit;
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function mmss(int $ms): string {
  $sec = (int)round(max(0,$ms)/1000);
  return sprintf('%d:%02d', intdiv($sec,60), $sec%60);
}
function fmt_dt(?string $iso, string $lang): string {
  if (function_exists('format_dt_local')) return (string)format_dt_local($iso, $lang);
  if (!$iso) return '-';
  try { return (new DateTimeImmutable($iso))->format('d/m/Y H:i'); } catch (Throwable $e) { return '-'; }
}
function fmt_num($v, string $lang, int $dec=0): string {
  if (function_exists('format_num_local')) return (string)format_num_local($v, $lang, $dec);
  return number_format((float)$v, $dec, '.', ',');
}
function is_daily_game(array $g): bool {
  if (isset($g['is_daily'])) return (int)$g['is_daily'] === 1;
  if (isset($g['mode'])) return (string)$g['mode'] === 'daily';
  if (isset($g['daily_key']) && $g['daily_key']) return true;
  if (isset($g['challenge_date']) && $g['challenge_date']) return true;
  return false;
}
function t_safe(string $key, string $fallback): string {
  $v = t($key);
  return ($v === $key) ? $fallback : $v;
}

$seoTitle = t_safe('game_meta_title', 'Session Details - Prismatch');
$seoDescription = t_safe('game_meta_description', 'Detailed results for your Prismatch session.');
$seoLangs = function_exists('supported_languages') ? array_keys(supported_languages()) : [];

$game = get_game($userEmail, $id);
if (!$game) {
  http_response_code(404);
  echo "<!doctype html><html><head><meta charset='utf-8'><title>".htmlspecialchars(t('msg_game_not_found'))."</title></head><body style='font-family:system-ui;padding:20px'>".
       "<h1>".htmlspecialchars(t('msg_game_not_found'))."</h1>".
       "<p><a href='games.php'>".htmlspecialchars(t('btn_back_to_history'))."</a></p></body></html>";
  exit;
}

$rounds = list_rounds($id);
$gameCountry = isset($game['country']) ? (string)$game['country'] : '';
?>
<!doctype html>
<html lang="<?= h($lang) ?>" dir="<?= h($dir) ?>">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link href="css/bootstrap.min.css" rel="stylesheet" />
  <link href="css/theme.css" rel="stylesheet" />
  <title><?= h(t('game_details_title')) ?> — <?= h(t('app_name')) ?></title>
  <link rel="icon" type="image/svg+xml" href="favicon.svg" />
  <?= seo_meta([
    'title' => $seoTitle,
    'description' => $seoDescription,
    'url' => seo_current_url(),
    'image' => '/logo.png',
    'type' => 'website',
    'robots' => 'noindex,nofollow',
    'lang' => $lang,
    'site_name' => t('app_name'),
  ]) ?>
  <?= seo_alternate_links($seoLangs, seo_current_url()) ?>
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>
<main class="container py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
      <div class="d-flex flex-wrap align-items-center gap-2">
        <h1 class="h3 mb-0"><?= h(t('game_details_title')) ?></h1>
        <?php if (is_daily_game($game)): ?>
          <span class="badge text-bg-warning">?? <?= h(t_safe('daily_title','Daily Challenge')) ?></span>
        <?php else: ?>
          <span class="badge text-bg-secondary">?? <?= h(t('app_name')) ?></span>
        <?php endif; ?>
      </div>
      <div class="text-muted small"><?= h(t('logged_in_as', ['email'=>$userEmail])) ?></div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="games.php">
        <img class="bi-icon" src="bootstrap-icons/arrow-left.svg" alt="" aria-hidden="true" />
        <?= h(t('btn_back_to_history')) ?>
      </a>
      <a class="btn btn-outline-secondary btn-sm" href="play.php">
        <img class="bi-icon" src="bootstrap-icons/play-fill.svg" alt="" aria-hidden="true" />
        <?= h(t('btn_back_to_game')) ?>
      </a>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <div class="row row-cols-1 row-cols-md-3 g-2">
        <div class="col"><div class="border rounded p-2"><div class="small text-muted"><?= h(t('label_start')) ?></div><div class="fw-semibold"><?= h(fmt_dt($game['created_at'] ?? null, $lang)) ?></div></div></div>
        <div class="col"><div class="border rounded p-2"><div class="small text-muted"><?= h(t('label_end')) ?></div><div class="fw-semibold"><?= h(fmt_dt($game['finished_at'] ?? null, $lang)) ?></div></div></div>
        <div class="col"><div class="border rounded p-2"><div class="small text-muted"><?= h(t('label_duration')) ?></div><div class="fw-semibold"><?= h(mmss((int)$game['duration_ms'])) ?></div></div></div>
        <div class="col"><div class="border rounded p-2"><div class="small text-muted"><?= h(t_safe('th_score','Score')) ?></div><div class="fw-semibold"><?= h(fmt_num((int)($game['score'] ?? 0), $lang, 0)) ?></div></div></div>
        <div class="col"><div class="border rounded p-2"><div class="small text-muted"><?= h(t('label_reached_level')) ?></div><div class="fw-semibold"><?= h(fmt_num((int)$game['reached_level'], $lang, 0)) ?></div></div></div>
        <div class="col"><div class="border rounded p-2"><div class="small text-muted"><?= h(t('label_total_correct')) ?></div><div class="fw-semibold"><?= h(fmt_num((int)$game['total_correct'], $lang, 0)) ?></div></div></div>
        <div class="col"><div class="border rounded p-2"><div class="small text-muted"><?= h(t('label_status')) ?></div><div class="fw-semibold"><?= h(((int)$game['won'] === 1) ? t('status_won') : t('status_finished')) ?></div></div></div>
      </div>

      <?php if ($gameCountry): ?>
        <div class="mt-2">
          <?php $flagUrl = country_flag_icon_url(strtoupper($gameCountry)); ?>
          <span class="badge text-bg-light" title="<?= h(t_safe('label_country','Country')) ?>: <?= h($gameCountry) ?>">
            <?php if ($flagUrl !== ''): ?>
              <img src="<?= h($flagUrl) ?>" alt="<?= h(strtoupper($gameCountry)) ?>" width="20" height="14" />
            <?php endif; ?>
            <span><?= h(strtoupper($gameCountry)) ?></span>
          </span>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <h2 class="h5 mb-3"><?= h(t('round_details_title')) ?></h2>
      <div class="table-responsive">
        <table class="table table-striped">
          <thead class="table-light">
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
                  <span class="d-inline-block rounded-circle border" style="width:12px;height:12px;background:<?= h($r['target_color']) ?>"></span>
                  <span><?= h($r['target_color']) ?></span>
                </span>
              </td>
              <td>
                <?php if ($gridArr): ?>
                  <div class="d-flex flex-wrap gap-1">
                    <?php foreach ($gridArr as $c): ?>
                      <span class="d-inline-block rounded-circle border" title="<?= h($c) ?>" style="width:12px;height:12px;background:<?= h($c) ?>"></span>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <span class="text-muted">-</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($r['picked_color'])): ?>
                  <span class="d-inline-flex align-items-center gap-2">
                    <span class="d-inline-block rounded-circle border" style="width:12px;height:12px;background:<?= h($r['picked_color']) ?>"></span>
                    <span><?= h($r['picked_color']) ?></span>
                  </span>
                <?php else: ?>
                  <span class="text-muted">-</span>
                <?php endif; ?>
              </td>
              <td><?= h(fmt_num(((int)$r['response_ms'])/1000, $lang, 2)) ?><?= h(t_safe('unit_seconds_short','s')) ?></td>
              <td>
                <span class="badge text-bg-<?= $ok ? 'success' : 'danger' ?>">
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
</body>
</html>


