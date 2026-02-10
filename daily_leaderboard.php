<?php
require_once __DIR__ . '/bootstrap.php';
if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
  error_reporting(E_ALL);
  @ini_set('display_errors', '1');
  @ini_set('display_startup_errors', '1');
}

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
  // show first 2 chars + domain, mask middle
  $p = explode('@', $email, 2);
  if (count($p) !== 2) return $email;
  $name = $p[0];
  $dom  = $p[1];
  $head = function_exists('mb_substr') ? mb_substr($name, 0, 2) : substr($name, 0, 2);
  return $head . '***@' . $dom;
}
function display_player_name($row) {
  $username = trim((string)($row['username'] ?? ''));
  if ($username !== '') return $username;
  $email = (string)($row['email'] ?? '');
  return $email !== '' ? mask_email($email) : '-';
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

// Try to show viewer country flag (Cloudflare)
$viewerCountry = cf_country();
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
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700;800&family=Baloo+2:wght@500;600;700&display=swap" rel="stylesheet" />
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
  <style>
    :root{ color-scheme: light dark; }
    body{
      margin:0;
      font-family:"Rubik","Segoe UI","Helvetica Neue",sans-serif;
      background: var(--bs-body-bg);
      color: var(--bs-body-color);
      padding-top: calc(var(--pm-header-offset, 0px) + 18px);
      padding-bottom: calc(var(--pm-footer-offset, 0px) + 18px);
    }
    .wrap{ max-width:980px; margin:0 auto; padding:18px; display:grid; gap:18px; }
    .cardx{ background: rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.12); border-radius:16px; padding:16px; }
    [data-bs-theme="light"] .cardx{ background: rgba(255,255,255,0.95); border-color: rgba(0,0,0,0.08); }
    h1{ margin:0 0 6px 0; font-family:"Baloo 2","Rubik","Segoe UI","Helvetica Neue",sans-serif; }
    .muted{ opacity:.7; font-size:12px; }
    table{ width:100%; border-collapse:collapse; }
    th,td{ padding:10px 8px; border-bottom:1px solid rgba(255,255,255,.10); text-align:left; vertical-align:top; font-size:14px; }
    th{ font-weight:700; }
    .right{ text-align:right; }
    .flagIcon{ width:18px; height:18px; border-radius:50%; object-fit:cover; box-shadow:0 2px 6px rgba(0,0,0,.35); border:1px solid rgba(255,255,255,.25); }
    .filters{ display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/header.php'; ?>
  <main class="wrap">
    <?php
      $countries = [];
      foreach ($rows as $r) {
        $c = strtoupper((string)($r['country'] ?? ''));
        if ($c !== '' && preg_match('/^[A-Z]{2}$/', $c)) $countries[$c] = true;
      }
      if ($viewerCountry && preg_match('/^[A-Z]{2}$/', $viewerCountry)) $countries[$viewerCountry] = true;
      ksort($countries);
    ?>
    <div class="cardx">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
          <h1><?= h(t('daily_leaderboard_title')) ?></h1>
          <div class="muted" id="dailyDate" data-utc-date="<?= h($challengeDate) ?>"><?= h($challengeDate) ?></div>
        </div>
        <form class="filters" method="get">
          <input class="form-control" type="date" name="d" value="<?= h($challengeDate) ?>" />
          <select class="form-select" name="country">
            <option value=""><?= h(t('filter_country_all') !== 'filter_country_all' ? t('filter_country_all') : 'All countries') ?></option>
            <?php foreach (array_keys($countries) as $c): ?>
              <option value="<?= h($c) ?>" <?= $countryFilter === $c ? 'selected' : '' ?>><?= h($c) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-primary" type="submit"><?= h(t('btn_apply') !== 'btn_apply' ? t('btn_apply') : 'Apply') ?></button>
        </form>
      </div>
    </div>

    <div class="cardx">
      <?php if (!$rows): ?>
        <div class="muted"><?= h(t('msg_no_scores_yet')) ?></div>
      <?php else: ?>
        <div class="table-responsive">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th><?= h(t('th_country')) ?></th>
                <th><?= h(t('th_player')) ?></th>
                <th class="right"><?= h(t('th_score')) ?></th>
                <th class="right"><?= h(t('th_duration')) ?></th>
                <th class="right"><?= h(t('th_level')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $i => $r):
                $c = strtoupper((string)($r['country'] ?? ''));
              ?>
              <tr>
                <td><?= (int)($i+1) ?></td>
                <td>
                  <?php if ($c): ?>
                    <?php $flagUrl = country_flag_icon_url($c); ?>
                    <?php if ($flagUrl !== ''): ?>
                      <img class="flagIcon" src="<?= h($flagUrl) ?>" alt="<?= h($c) ?>" />
                    <?php endif; ?>
                    <span class="muted"><?= h($c) ?></span>
                  <?php else: ?>
                    <span class="muted">-</span>
                  <?php endif; ?>
                </td>
                <td><?= h(display_player_name($r)) ?></td>
                <td class="right"><?= h((string)($r['score'] ?? '-')) ?></td>
                <td class="right"><?= h(mmss((int)($r['duration_ms'] ?? 0))) ?></td>
                <td class="right"><?= h((string)($r['reached_level'] ?? '-')) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
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
