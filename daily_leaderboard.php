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
  // show first 2 chars + domain, mask middle
  $p = explode('@', $email, 2);
  if (count($p) !== 2) return $email;
  $name = $p[0];
  $dom  = $p[1];
  $head = function_exists('mb_substr') ? mb_substr($name, 0, 2) : substr($name, 0, 2);
  return $head . '***@' . $dom;
}
function display_player_name($row) {
  $username = trim((string)((isset($row['username']) ? $row['username'] : '')));
  if ($username !== '') return $username;
  $email = (string)((isset($row['email']) ? $row['email'] : ''));
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
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet" />
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
    :root,
    [data-bs-theme="dark"]{
      --bg:#0b0d12;
      --card:rgba(255,255,255,.08);
      --bd:rgba(255,255,255,.18);
      --mut:rgba(237,242,255,.68);
      --accent:#3dd6a0;
      --accent2:#ff7d5d;
      --ink:#ffffff;
    }
    [data-bs-theme="light"]{
      --bg:#f6f3ee;
      --card:rgba(255,255,255,.9);
      --bd:rgba(27,31,42,.12);
      --mut:rgba(27,31,42,.65);
      --accent:#1e9b79;
      --accent2:#e4573f;
      --ink:#1b1f2a;
    }
    body{
      margin:0;
      font-family:"Plus Jakarta Sans","Segoe UI","Helvetica Neue",sans-serif;
      background:var(--bg);
      color:var(--ink);
      padding:18px;
      padding-bottom:84px;
    }
    body::after{ display:none; }
    a{ color:var(--accent2); text-decoration:none; }
    .wrap{ max-width:980px; margin:0 auto; }
    .top{ display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; }
    h1{ font-family:"Space Grotesk","Segoe UI","Helvetica Neue",sans-serif; letter-spacing:.2px; }
    .btn{
      display:inline-flex; align-items:center; gap:8px;
      padding:10px 14px; border-radius:999px;
      background:linear-gradient(180deg, rgba(255,255,255,.12), rgba(255,255,255,.06));
      border:1px solid rgba(255,255,255,.18);
      color:#fff;
    }
    .btn:hover{ border-color: rgba(255,255,255,.28); }
    .card{
      background:linear-gradient(160deg, rgba(255,255,255,.10), rgba(255,255,255,.04));
      border:1px solid var(--bd);
      border-radius:16px;
      padding:14px;
      margin:12px 0;
      box-shadow: 0 26px 60px rgba(0,0,0,.35);
    }
    .table-wrap{ width:100%; overflow-x:auto; border-radius: 12px; }
    .muted{ color:var(--mut); font-size:12px; }
    table{ width:100%; border-collapse:collapse; }
    th,td{ padding:10px 8px; border-bottom:1px solid rgba(255,255,255,.10); text-align:left; vertical-align:top; font-size:14px; }
    th{
      font-family:"Space Grotesk","Segoe UI","Helvetica Neue",sans-serif;
      color:rgba(255,255,255,.86);
      font-weight:700;
    }
    .pill{
      display:inline-flex; align-items:center; gap:8px;
      padding:6px 10px; border-radius:999px;
      border:1px solid rgba(255,255,255,.18);
      background:rgba(8,16,23,.35);
      font-size:12px;
    }
    .flagIcon{
      width:18px;
      height:18px;
      border-radius:50%;
      object-fit:cover;
      box-shadow: 0 2px 6px rgba(0,0,0,.35);
      border:1px solid rgba(255,255,255,.25);
    }
    .rowTop{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
    .select{
      appearance:none; -webkit-appearance:none;
      padding:10px 40px 10px 14px; border-radius:999px;
      border:1px solid rgba(255,255,255,.18);
      background:linear-gradient(180deg, rgba(255,255,255,.12), rgba(255,255,255,.05));
      color:#fff; outline:none; cursor:pointer;
      background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='white' opacity='0.7' viewBox='0 0 24 24'><path d='M7 10l5 5 5-5z'/></svg>");
      background-repeat:no-repeat; background-position:right 14px center;
    }
    .right{ text-align:right; }
    html[dir="rtl"] th, html[dir="rtl"] td{ text-align:right; }
    html[dir="rtl"] .right{ text-align:left; }
    @media (max-width: 780px){
      .top{ align-items:flex-start; }
      .rowTop{ align-items:flex-start; }
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>
  <div class="wrap">
    <div class="top">
      <div>
        <h1 style="margin:0 0 6px 0"><?= h(t('daily_leaderboard_title')) ?></h1>
        <div class="rowTop">
          <span class="pill">🗓️ <span id="dailyDate" data-utc-date="<?= h($challengeDate) ?>"><?= h($challengeDate) ?></span> (<?= h(t('label_utc')) ?>)</span>
          <?php if ($viewerCountry): ?>
            <?php $viewerFlag = country_flag_icon_url($viewerCountry); ?>
            <!-- <span class="pill">
              <?php if ($viewerFlag !== ''): ?>
                <img class="flagIcon" src="<?= h($viewerFlag) ?>" alt="<?= h($viewerCountry) ?>" />
              <?php endif; ?>
              <span><?= h($viewerCountry) ?></span>
            </span> -->
          <?php endif; ?>
        </div>
        <div class="muted"><?= h(t('daily_leaderboard_note')) ?></div>
      </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap">
          <a class="btn" href="play.php">
            <img class="bi-icon" src="bootstrap-icons/arrow-left.svg" alt="" aria-hidden="true" />
            <?= h(t('btn_back_to_game')) ?>
          </a>
        </div>
    </div>

    <div class="card">
      <form method="get" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center">
        <input type="date" name="d" value="<?= h($challengeDate) ?>" class="select" style="padding-right:14px;background-image:none" />
        <select name="country" class="select" aria-label="<?= h(t('label_country')) ?>">
          <option value=""><?= h(t('filter_all_countries')) ?></option>
          <?php
            // Build countries present in result set (for quick filter)
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
          <button class="btn" type="submit">
            <img class="bi-icon" src="bootstrap-icons/search.svg" alt="" aria-hidden="true" />
            <?= h(t('btn_apply')) ?>
          </button>
      </form>
    </div>

    <div class="card">
      <div class="table-wrap">
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
        <?php if (!$rows): ?>
          <tr><td colspan="6" class="muted"><?= h(t('msg_no_scores_yet')) ?></td></tr>
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
                    <img class="flagIcon" src="<?= h($flagUrl) ?>" alt="<?= h($c) ?>" />
                  <?php endif; ?>
                  <span class="muted"><?= h($c) ?></span>
                <?php else: ?>
                  <span class="muted">-</span>
                <?php endif; ?>
              </td>
              <td><?= h(display_player_name($r)) ?></td>
              <td class="right"><?= (int)$r['score'] ?></td>
              <td class="right"><?= h(mmss((int)$r['duration_ms'])) ?></td>
              <td class="right"><?= (int)$r['reached_level'] ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        </table>
      </div>
    </div>

    <?php if (is_file(__DIR__ . '/footer.php')) include __DIR__ . '/footer.php'; ?>
  </div>

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
