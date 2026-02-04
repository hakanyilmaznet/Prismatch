<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/db.php';

$lang = get_lang();
$dir  = lang_dir($lang);

$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$userDisplay = (isset($_SESSION['user_name']) ? $_SESSION['user_name'] : ($userId ? user_display_name($userId)) : null);
if (!$userId) {
  header('Location: index.php?session=expired');
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
  $c = trim('pill '.$class);
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
  $id = (int)(isset($g['id']) ? $g['id'] : 0);
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
    'status_class' => $won ? 'pill-won' : 'pill-fin',
    'country' => $country,
    'flag_url' => safe_flag_url($country),
    'detail_url' => 'game.php?id=' . $id,
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
  <style>
    :root{ color-scheme: light dark; }
    :root,
    [data-bs-theme="dark"]{
      --bg:#0b0d12;
      --card:rgba(255,255,255,.08);
      --bd:rgba(255,255,255,.16);
      --mut:rgba(237,242,255,.68);
      --link:#ff7d5d;
      --accent:#3dd6a0;
      --accent2:#ffd08a;
      --ink:#ffffff;
    }
    [data-bs-theme="light"]{
      --bg:#f6f3ee;
      --card:rgba(255,255,255,.9);
      --bd:rgba(27,31,42,.12);
      --mut:rgba(27,31,42,.65);
      --link:#e4573f;
      --accent:#1e9b79;
      --accent2:#f4b66a;
      --ink:#1b1f2a;
    }
    *{ box-sizing:border-box; }
    body{
      margin:0;
      font-family:"Plus Jakarta Sans","Segoe UI","Helvetica Neue",sans-serif;
      background:var(--bg);
      color:var(--ink);
      padding:18px;
      padding-bottom:84px;
    }
    body::after{ display:none; }
    a{ color:var(--link); text-decoration:none; }
    .wrap{ max-width:1080px; margin:0 auto; }
    .top{ display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; }
    h1{ font-family:"Space Grotesk","Segoe UI","Helvetica Neue",sans-serif; letter-spacing:.2px; margin:0; }
    .sub{ color:var(--mut); font-size:12px; }
    .actions{ display:flex; gap:10px; flex-wrap:wrap; }
    .btn{
      display:inline-flex; align-items:center; gap:8px;
      padding:10px 14px; border-radius:999px;
      background:linear-gradient(180deg, rgba(255,255,255,.12), rgba(255,255,255,.06));
      border:1px solid rgba(255,255,255,.18);
      color:#fff;
    }
    .btn:hover{ border-color: rgba(255,255,255,.28); }
    .btn-detail{
      background:#ff8b5c;
      border-color:#ffb48f;
      color:#081017;
      font-weight:800;
      text-transform:uppercase;
      letter-spacing:.4px;
      box-shadow:0 10px 22px rgba(255,139,92,.35);
      min-width:98px;
      justify-content:center;
      white-space:nowrap;
    }
    .card{
      background:linear-gradient(160deg, rgba(255,255,255,.10), rgba(255,255,255,.04));
      border:1px solid var(--bd);
      border-radius:18px;
      padding:14px;
      margin:14px 0;
      box-shadow:0 26px 60px rgba(0,0,0,.35);
    }
    table{ width:100%; border-collapse:collapse; }
    th,td{ padding:10px 8px; border-bottom:1px solid rgba(255,255,255,.10); text-align:left; vertical-align:top; font-size:14px; }
    th{
      font-family:"Fraunces","Times New Roman",serif;
      color:rgba(255,255,255,.86);
      font-weight:700;
    }
    .muted{ color:var(--mut); font-size:12px; }
    .right{ text-align:right; }
    .pill{ display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; border:1px solid rgba(255,255,255,.18); background:rgba(8,16,23,.35); font-size:12px; white-space:nowrap; }
    .pill-won{ border-color:rgba(53,208,186,.35); background:rgba(53,208,186,.12); }
    .pill-fin{ border-color:rgba(255,255,255,.20); background:rgba(8,16,23,.35); }
    .pill-meta{ border-color:rgba(255,139,92,.35); background:rgba(255,139,92,.12); }
    .flagIcon{ width:16px; height:16px; border-radius:50%; object-fit:cover; border:1px solid rgba(255,255,255,.25); box-shadow:0 2px 6px rgba(0,0,0,.35); }
    .metaRow{ display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
    .grid-cards{ display:none; gap:12px; }
    .gameCard{
      border:1px solid rgba(255,255,255,.15);
      border-radius:16px;
      padding:12px;
      background:rgba(8,16,23,.35);
      box-shadow:0 18px 40px rgba(0,0,0,.35);
      animation: rise .35s ease both;
    }
    .cardHead{ display:flex; align-items:flex-start; justify-content:space-between; gap:10px; }
    .cardTitle{ font-weight:800; }
    .cardGrid{ display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:8px 12px; margin-top:10px; }
    .label{ color:var(--mut); font-size:11px; }
    .value{ font-weight:700; }

    @keyframes rise{ from{ opacity:0; transform:translateY(6px);} to{ opacity:1; transform:translateY(0);} }

    @media (max-width: 900px){
      table{ display:none; }
      .grid-cards{ display:grid; }
    }
    @media (max-width: 720px){
      .actions{ width:100%; }
      .actions .btn{ flex:1 1 auto; justify-content:center; }
    }
    html[dir="rtl"] th, html[dir="rtl"] td{ text-align:right; }
    html[dir="rtl"] .right{ text-align:left; }
  </style>
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>
  <div class="wrap">
    <div class="top">
      <div>
        <h1><?= h(TT('games_title','My Games')) ?></h1>
        <div class="sub"><?= h(t('logged_in_as', ['email'=>$userDisplay])) ?></div>
      </div>
        <div class="actions">
          <a class="btn" href="play.php">
            <img class="bi-icon" src="bootstrap-icons/arrow-left.svg" alt="" aria-hidden="true" />
            <?= h(TT('btn_back_to_game','Back to game')) ?>
          </a>
          <a class="btn" href="logout.php">
            <img class="bi-icon" src="bootstrap-icons/box-arrow-right.svg" alt="" aria-hidden="true" />
            <?= h(TT('logout','Logout')) ?>
          </a>
        </div>
    </div>

    <div class="card">
      <?php if (!$games): ?>
        <div class="muted"><?= h(TT('msg_no_games','No games yet.')) ?></div>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th><?= h(TT('label_date','Date')) ?></th>
              <th><?= h(TT('label_duration','Duration')) ?></th>
              <th><?= h(TT('th_score','Score')) ?></th>
              <th><?= h(TT('label_reached_level','Stage')) ?></th>
              <th><?= h(TT('label_total_correct','Correct')) ?></th>
              <th><?= h(TT('label_status','Status')) ?></th>
              <th><?= h(TT('label_country','Country')) ?></th>
              <th class="right" style="width:140px"><?= h(TT('btn_details','Details')) ?></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($games as $g): ?>
            <tr>
              <td>
                <div><?= h($g['date_label']) ?></div>
                <div class="muted">#<?= h((string)$g['index']) ?> · <?= h(TT('label_id','ID')) ?> <?= h((string)$g['id']) ?></div>
              </td>
              <td><?= h($g['duration_label']) ?></td>
              <td><?= h(fmt_num($g['score'], $lang, 0)) ?></td>
              <td><?= h(fmt_num($g['reached_level'], $lang, 0)) ?></td>
              <td><?= h(fmt_num($g['total_correct'], $lang, 0)) ?></td>
              <td><?= pill($g['status_text'], $g['status_class']) ?></td>
              <td>
                <div class="metaRow">
                  <?php if ($g['country'] !== ''): ?>
                    <span class="pill pill-meta" title="<?= h(TT('label_country','Country')) ?>: <?= h($g['country']) ?>">
                      <?php if ($g['flag_url'] !== ''): ?>
                        <img class="flagIcon" src="<?= h($g['flag_url']) ?>" alt="<?= h($g['country']) ?>" />
                      <?php else: ?>
                        <span><?= h($g['country']) ?></span>
                      <?php endif; ?>
                    </span>
                  <?php endif; ?>
                </div>
              </td>
              <td class="right" style="white-space:nowrap">
                <a class="btn btn-detail" href="<?= h($g['detail_url']) ?>">
                  <img class="bi-icon" src="bootstrap-icons/info-circle.svg" alt="" aria-hidden="true" />
                  <?= h(TT('btn_details','Details')) ?>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>

        <div class="grid-cards">
          <?php foreach ($games as $g): ?>
            <div class="gameCard">
              <div class="cardHead">
                <div>
                  <div class="cardTitle"><?= h($g['date_label']) ?></div>
                  <div class="muted">#<?= h((string)$g['index']) ?> · <?= h(TT('label_id','ID')) ?> <?= h((string)$g['id']) ?></div>
                </div>
                <?= pill($g['status_text'], $g['status_class']) ?>
              </div>
              <div class="cardGrid">
                <div>
                  <div class="label"><?= h(TT('label_duration','Duration')) ?></div>
                  <div class="value"><?= h($g['duration_label']) ?></div>
                </div>
                <div>
                  <div class="label"><?= h(TT('th_score','Score')) ?></div>
                  <div class="value"><?= h(fmt_num($g['score'], $lang, 0)) ?></div>
                </div>
                <div>
                  <div class="label"><?= h(TT('label_reached_level','Stage')) ?></div>
                  <div class="value"><?= h(fmt_num($g['reached_level'], $lang, 0)) ?></div>
                </div>
                <div>
                  <div class="label"><?= h(TT('label_total_correct','Correct')) ?></div>
                  <div class="value"><?= h(fmt_num($g['total_correct'], $lang, 0)) ?></div>
                </div>
                <div>
                  <div class="label"><?= h(TT('label_country','Country')) ?></div>
                  <div class="value">
                    <span class="metaRow">
                      <?php if ($g['country'] !== ''): ?>
                        <span class="pill pill-meta">
                          <?php if ($g['flag_url'] !== ''): ?>
                            <img class="flagIcon" src="<?= h($g['flag_url']) ?>" alt="<?= h($g['country']) ?>" />
                          <?php else: ?>
                            <span><?= h($g['country']) ?></span>
                          <?php endif; ?>
                        </span>
                      <?php endif; ?>
                    </span>
                  </div>
                </div>
              </div>
                <div style="margin-top:10px">
                  <a class="btn btn-detail" href="<?= h($g['detail_url']) ?>">
                    <img class="bi-icon" src="bootstrap-icons/info-circle.svg" alt="" aria-hidden="true" />
                    <?= h(TT('btn_details','Details')) ?>
                  </a>
                </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
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
  setTimezoneIfNeeded();
  </script>
</body>
</html>





