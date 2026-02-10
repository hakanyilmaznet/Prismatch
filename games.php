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
  header('Location: login.php?next=' . rawurlencode('games.php'));
  exit;
}
header('X-Robots-Tag: noindex, nofollow', true);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function TT($key, $fallback) {
  $v = t($key);
  return ($v === $key) ? $fallback : $v;
}

function fmt_dt($iso, $lang) {
  if (function_exists('format_dt_local')) return (string)format_dt_local($iso, $lang);
  if (!$iso) return '-';
  try { return (new DateTimeImmutable($iso))->format('d/m/Y H:i'); } catch (Exception $e) { return '-'; }
}

function fmt_num($v, $lang, $dec = 0) {
  if (function_exists('format_num_local')) return (string)format_num_local($v, $lang, $dec);
  return number_format((float)$v, $dec, '.', ',');
}

function seconds_to_mmss($sec) {
  $sec = (int)$sec;
  $m = (int)floor(max(0,$sec) / 60);
  $s = max(0,$sec) % 60;
  return sprintf('%d:%02d', $m, $s);
}

function safe_flag_url($cc) {
  $cc = strtoupper(trim((string)$cc));
  if ($cc === '' || !preg_match('/^[A-Z]{2}$/', $cc)) return '';
  if (function_exists('country_flag_icon_url')) return (string)country_flag_icon_url($cc);
  return '';
}

function pill($text, $class='') {
  $c = trim('badge rounded-pill '.$class);
  return '<span class="'.h($c).'">'.h($text).'</span>';
}

$seoTitle = TT('games_meta_title', 'My Sessions - Prismatch');
$seoDescription = TT('games_meta_description', 'Your Prismatch session history and detailed results.');
$seoLangs = function_exists('supported_languages') ? array_keys(supported_languages()) : [];

$rows = list_games($userId, 200);
$games = [];
$idx = 0;
foreach ($rows as $g) {
  $idx++;
  $id = (string)(isset($g['id']) ? $g['id'] : '');
  $created = isset($g['created_at']) ? $g['created_at'] : null;
  $durationMs = (int)(isset($g['duration_ms']) ? $g['duration_ms'] : 0);
  $durationSec = (int)round($durationMs / 1000);
  $won = ((int)(isset($g['won']) ? $g['won'] : 0) === 1);
  $score = (int)(isset($g['score']) ? $g['score'] : 0);
  $country = strtoupper((string)(isset($g['country']) ? $g['country'] : ''));

  $games[] = [
    'index' => $idx,
    'id' => $id,
    'created' => $created,
    'date_label' => fmt_dt($created, $lang),
    'duration_label' => seconds_to_mmss($durationSec),
    'score' => $score,
    'reached_level' => (int)(isset($g['reached_level']) ? $g['reached_level'] : 0),
    'total_correct' => (int)(isset($g['total_correct']) ? $g['total_correct'] : 0),
    'won' => $won,
    'status_text' => $won ? TT('status_won','Won') : TT('status_finished','Finished'),
    'status_class' => $won ? 'text-bg-success' : 'text-bg-secondary',
    'country' => $country,
    'flag_url' => safe_flag_url($country),
    'detail_url' => 'game.php?id=' . rawurlencode($id),
  ];
}
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
  <title><?= h(TT('games_title','My Games')) ?> — <?= h(TT('app_name','Prismatch')) ?></title>
  <link rel="icon" type="image/svg+xml" href="favicon.svg" />
  <?= seo_meta([
    'title' => $seoTitle,
    'description' => $seoDescription,
    'url' => seo_current_url(),
    'image' => '/logo.png',
    'type' => 'website',
    'robots' => 'noindex,nofollow',
    'lang' => $lang,
    'site_name' => TT('app_name','Prismatch'),
  ]) ?>
  <?= seo_alternate_links($seoLangs, seo_current_url()) ?>
</head>
<body class="bg-body">
<?php include __DIR__ . '/header.php'; ?>
<main class="container py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      <h1 class="h4 mb-1"><?= h(TT('games_title','My Games')) ?></h1>
      <div class="text-body-secondary small"><?= h(t('logged_in_as', ['email'=>$userEmail])) ?></div>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2" href="play.php">
        <img src="bootstrap-icons/arrow-left.svg" alt="" aria-hidden="true" width="16" height="16" />
        <?= h(TT('btn_back_to_game','Back to game')) ?>
      </a>
      <a class="btn btn-outline-danger btn-sm d-flex align-items-center gap-2" href="logout.php">
        <img src="bootstrap-icons/box-arrow-right.svg" alt="" aria-hidden="true" width="16" height="16" />
        <?= h(TT('logout','Logout')) ?>
      </a>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <?php if (!$games): ?>
        <div class="alert alert-info mb-0"><?= h(TT('msg_no_games','No games yet.')) ?></div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead>
              <tr>
                <th><?= h(TT('label_date','Date')) ?></th>
                <th><?= h(TT('label_duration','Duration')) ?></th>
                <th><?= h(TT('th_score','Score')) ?></th>
                <th><?= h(TT('label_reached_level','Stage')) ?></th>
                <th><?= h(TT('label_total_correct','Correct')) ?></th>
                <th><?= h(TT('label_status','Status')) ?></th>
                <th><?= h(TT('label_country','Country')) ?></th>
                <th class="text-end"><?= h(TT('btn_details','Details')) ?></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($games as $g): ?>
              <tr>
                <td>
                  <div><?= h($g['date_label']) ?></div>
                  <div class="text-body-secondary small">#<?= h((string)$g['index']) ?> · <?= h(TT('label_id','ID')) ?> <?= h((string)$g['id']) ?></div>
                </td>
                <td><?= h($g['duration_label']) ?></td>
                <td><?= h(fmt_num($g['score'], $lang, 0)) ?></td>
                <td><?= h(fmt_num($g['reached_level'], $lang, 0)) ?></td>
                <td><?= h(fmt_num($g['total_correct'], $lang, 0)) ?></td>
                <td><?= pill($g['status_text'], $g['status_class']) ?></td>
                <td>
                  <?php if ($g['country'] !== ''): ?>
                    <span class="badge text-bg-light border text-dark">
                      <?php if ($g['flag_url'] !== ''): ?>
                        <img src="<?= h($g['flag_url']) ?>" alt="<?= h($g['country']) ?>" width="16" height="16" class="rounded-circle me-1" />
                      <?php endif; ?>
                      <?= h($g['country']) ?>
                    </span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-2" href="<?= h($g['detail_url']) ?>">
                    <img src="bootstrap-icons/info-circle.svg" alt="" aria-hidden="true" width="16" height="16" />
                    <?= h(TT('btn_details','Details')) ?>
                  </a>
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