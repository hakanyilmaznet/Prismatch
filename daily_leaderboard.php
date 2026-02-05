<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/db.php';

$lang = function_exists('get_lang') ? get_lang() : 'en';
$dir  = function_exists('lang_dir') ? lang_dir($lang) : 'ltr';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function mmss($ms) {
  $sec = (int)round(max(0,$ms)/1000);
  $m = (int)floor($sec / 60);
  $s = $sec % 60;
  return sprintf('%d:%02d', $m, $s);
}
function mask_email($email) {
  $p = explode('@', $email, 2);
  if (count($p) !== 2) return $email;
  $name = $p[0];
  $dom  = $p[1];
  $head = function_exists('mb_substr') ? mb_substr($name, 0, 2) : substr($name, 0, 2);
  return $head . '***@' . $dom;
}

$seoTitle = t('daily_leaderboard_title') . ' - ' . t('app_name');
$seoDescription = t('daily_leaderboard_meta_description');
if ($seoDescription === 'daily_leaderboard_meta_description') {
  $seoDescription = 'See today\'s Prismatch daily challenge leaderboard and compare top scores.';
}
$seoLangs = function_exists('supported_languages') ? array_keys(supported_languages()) : [];

$todayUtc = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d');
$challengeDate = isset($_GET['d']) ? $_GET['d'] : $todayUtc;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $challengeDate)) $challengeDate = $todayUtc;

$countryFilter = isset($_GET['country']) ? $_GET['country'] : '';
$countryFilter = strtoupper(trim((string)$countryFilter));
if ($countryFilter !== '' && !preg_match('/^[A-Z]{2}$/', $countryFilter)) $countryFilter = '';

$rows = daily_leaderboard($challengeDate, $countryFilter ?: null, 100);
$viewerCountry = cf_country();
?>
<!doctype html>
<html lang="<?= h($lang) ?>" dir="<?= h($dir) ?>">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link href="css/bootstrap.min.css" rel="stylesheet" />
  <title><?= h(t('daily_leaderboard_title')) ?> — <?= h(t('app_name')) ?></title>
  <link rel="icon" type="image/svg+xml" href="favicon.svg" />
  <?= seo_meta([
    'title' => $seoTitle,
    'description' => $seoDescription,
    'url' => seo_current_url(),
    'image' => '/logo.png',
    'type' => 'website',
    'robots' => 'index,follow',
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
      <h1 class="h3 mb-1"><?= h(t('daily_leaderboard_title')) ?></h1>
      <div class="d-flex flex-wrap gap-2">
        <span class="badge text-bg-light">📅 <span id="dailyDate" data-utc-date="<?= h($challengeDate) ?>"><?= h($challengeDate) ?></span> (<?= h(t('label_utc')) ?>)</span>
      </div>
      <div class="text-muted small mt-1"><?= h(t('daily_leaderboard_note')) ?></div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="play.php">
        <img class="bi-icon" src="bootstrap-icons/arrow-left.svg" alt="" aria-hidden="true" />
        <?= h(t('btn_back_to_game')) ?>
      </a>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-12 col-md-4">
          <label class="form-label" for="leaderboardDate"><?= h(t('label_date','Date')) ?></label>
          <input id="leaderboardDate" type="date" name="d" value="<?= h($challengeDate) ?>" class="form-control" />
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label" for="leaderboardCountry"><?= h(t('label_country')) ?></label>
          <select id="leaderboardCountry" name="country" class="form-select" aria-label="<?= h(t('label_country')) ?>">
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
        <div class="col-12 col-md-4">
          <button class="btn btn-primary" type="submit">
            <img class="bi-icon" src="bootstrap-icons/search.svg" alt="" aria-hidden="true" />
            <?= h(t('btn_apply')) ?>
          </button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped mb-0">
          <thead class="table-light">
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
            <tr><td colspan="6" class="text-muted"><?= h(t('msg_no_scores_yet')) ?></td></tr>
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
                      <img src="<?= h($flagUrl) ?>" alt="<?= h($c) ?>" width="20" height="14" />
                    <?php endif; ?>
                    <span class="text-muted"><?= h($c) ?></span>
                  <?php else: ?>
                    <span class="text-muted">-</span>
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
  const d = new Date(utc + 'T00:00:00Z');
  if (isNaN(d.getTime())) return;
  el.textContent = d.toLocaleDateString();
}

setTimezoneIfNeeded();
applyLocalDate();
</script>

</body>
</html>
