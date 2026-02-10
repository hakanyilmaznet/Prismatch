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

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function t_safe($k, $fallback){ $v = t($k); return $v === $k ? $fallback : $v; }

function mmss($ms){
  $sec = (int)round(((int)$ms) / 1000);
  $m = (int)floor(max(0, $sec) / 60);
  $s = max(0, $sec) % 60;
  return sprintf('%d:%02d', $m, $s);
}

$challengeDate = $_GET['d'] ?? (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d');
$countryFilter = strtoupper(trim((string)($_GET['country'] ?? '')));
if ($countryFilter !== '' && !preg_match('/^[A-Z]{2}$/', $countryFilter)) $countryFilter = '';

$rows = daily_leaderboard($challengeDate, $countryFilter, 200);
$viewerCountry = detect_country_code();
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
  <title><?= h(t('daily_leaderboard_title')) ?> — <?= h(t('app_name')) ?></title>
  <link rel="icon" type="image/svg+xml" href="favicon.svg" />
  <?= seo_meta([
    'title' => t('daily_leaderboard_title'),
    'description' => t_safe('daily_leaderboard_description', 'Daily Prismatch leaderboard'),
    'url' => seo_current_url(),
    'image' => '/logo.png',
    'type' => 'website',
    'robots' => 'index,follow',
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
      <h1 class="h4 mb-1"><?= h(t('daily_leaderboard_title')) ?></h1>
      <div class="d-flex flex-wrap gap-2 align-items-center">
        <span class="badge text-bg-light border text-dark">??? <span id="dailyDate" data-utc-date="<?= h($challengeDate) ?>"><?= h($challengeDate) ?></span> (<?= h(t('label_utc')) ?>)</span>
      </div>
      <div class="text-body-secondary small mt-1"><?= h(t('daily_leaderboard_note')) ?></div>
    </div>
    <div>
      <a class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2" href="play.php">
        <img src="bootstrap-icons/arrow-left.svg" alt="" aria-hidden="true" width="16" height="16" />
        <?= h(t('btn_back_to_game')) ?>
      </a>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <form method="get" class="row gy-2 gx-2 align-items-end">
        <div class="col-sm-4">
          <label class="form-label" for="dateInput"><?= h(t('label_date','Date')) ?></label>
          <input id="dateInput" type="date" name="d" value="<?= h($challengeDate) ?>" class="form-control" />
        </div>
        <div class="col-sm-4">
          <label class="form-label" for="countrySelect"><?= h(t('label_country')) ?></label>
          <select id="countrySelect" name="country" class="form-select" aria-label="<?= h(t('label_country')) ?>">
            <option value=""><?= h(t('filter_all_countries')) ?></option>
            <?php
              $seen = [];
              foreach (daily_leaderboard($challengeDate, null, 200) as $r) {
                $c = strtoupper((string)(isset($r['country']) ? $r['country'] : ''));
                if ($c && preg_match('/^[A-Z]{2}$/',$c)) $seen[$c] = true;
              }
              ksort($seen);
              foreach (array_keys($seen) as $c):
                $sel = ($countryFilter === $c) ? 'selected' : '';
            ?>
              <option value="<?= h($c) ?>" <?= $sel ?>><?= h($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-sm-4">
          <button class="btn btn-primary d-inline-flex align-items-center gap-2" type="submit">
            <img src="bootstrap-icons/search.svg" alt="" aria-hidden="true" width="16" height="16" />
            <?= h(t('btn_apply')) ?>
          </button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th>#</th>
              <th><?= h(t('th_country')) ?></th>
              <th><?= h(t('th_player')) ?></th>
              <th class="text-end"><?= h(t('th_score')) ?></th>
              <th class="text-end"><?= h(t('th_duration')) ?></th>
              <th class="text-end"><?= h(t('th_level')) ?></th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="6" class="text-body-secondary"><?= h(t('msg_no_scores_yet')) ?></td></tr>
          <?php else: ?>
            <?php foreach ($rows as $i => $r):
              $c = strtoupper((string)(isset($r['country']) ? $r['country'] : ''));
            ?>
              <tr>
                <td><?= (int)($i+1) ?></td>
                <td>
                  <?php if ($c): ?>
                    <?php $flagUrl = country_flag_icon_url($c); ?>
                    <?php if ($flagUrl !== ''): ?>
                      <img src="<?= h($flagUrl) ?>" alt="<?= h($c) ?>" width="16" height="16" class="rounded-circle me-1" />
                    <?php endif; ?>
                    <span class="text-body-secondary"><?= h($c) ?></span>
                  <?php else: ?>
                    <span class="text-body-secondary">-</span>
                  <?php endif; ?>
                </td>
                <td><?= h(mask_email((string)$r['email'])) ?></td>
                <td class="text-end"><?= (int)$r['score'] ?></td>
                <td class="text-end"><?= h(mmss((int)$r['duration_ms'])) ?></td>
                <td class="text-end"><?= (int)$r['reached_level'] ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
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

function applyLocalDate(){
  const el = document.getElementById('dailyDate');
  if (!el) return;
  const utc = el.getAttribute('data-utc-date');
  if (!utc) return;
  try {
    const d = new Date(utc + 'T00:00:00Z');
    const pad = (n) => String(n).padStart(2, '0');
    const local = `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
    el.textContent = local;
  } catch(e){}
}

setTimezoneIfNeeded();
applyLocalDate();
</script>
</body>
</html>