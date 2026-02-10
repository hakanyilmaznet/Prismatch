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
    'status_class' => $won ? 'pill-won' : 'pill-fin',
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
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700;800&family=Baloo+2:wght@500;600;700&display=swap" rel="stylesheet" />
  <title><?= h(TT('games_title','My Sessions')) ?> — <?= h(TT('app_name','Prismatch')) ?></title>
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
    .wrap{ max-width:1080px; margin:0 auto; padding:18px; display:grid; gap:18px; }
    .cardx{ background: rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.12); border-radius:16px; padding:16px; }
    [data-bs-theme="light"] .cardx{ background: rgba(255,255,255,0.95); border-color: rgba(0,0,0,0.08); }
    h1{ margin:0 0 6px 0; font-family:"Baloo 2","Rubik","Segoe UI","Helvetica Neue",sans-serif; }
    .muted{ opacity:.7; font-size:12px; }
    table{ width:100%; border-collapse:collapse; }
    th,td{ padding:10px 8px; border-bottom:1px solid rgba(255,255,255,.10); text-align:left; vertical-align:top; font-size:14px; }
    th{ font-weight:700; }
    .right{ text-align:right; }
    .pill{ display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; border:1px solid rgba(255,255,255,.18); background:rgba(8,16,23,.35); font-size:12px; white-space:nowrap; }
    .pill-won{ border-color:rgba(53,208,186,.35); background:rgba(53,208,186,.12); }
    .pill-fin{ border-color:rgba(255,255,255,.20); background:rgba(8,16,23,.35); }
    .flagIcon{ width:16px; height:16px; border-radius:50%; object-fit:cover; border:1px solid rgba(255,255,255,.25); box-shadow:0 2px 6px rgba(0,0,0,.35); }
    .metaRow{ display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/header.php'; ?>
  <main class="wrap">
    <div class="cardx">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
          <h1><?= h(TT('games_title','My Sessions')) ?></h1>
          <div class="muted"><?= h(TT('games_subtitle','Your recent game sessions.')) ?></div>
        </div>
        <a class="btn btn-outline-secondary" href="play.php">
          <?= h(TT('btn_play_again','Play again')) ?>
        </a>
      </div>
    </div>

    <div class="cardx">
      <?php if (!$games): ?>
        <div class="muted"><?= h(TT('no_results','No results')) ?></div>
      <?php else: ?>
        <div class="table-responsive">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th><?= h(TT('th_date','Date')) ?></th>
                <th><?= h(TT('th_score','Score')) ?></th>
                <th><?= h(TT('th_level','Level')) ?></th>
                <th><?= h(TT('th_correct','Correct')) ?></th>
                <th><?= h(TT('th_duration','Duration')) ?></th>
                <th><?= h(TT('th_status','Status')) ?></th>
                <th class="right"><?= h(TT('th_action','Action')) ?></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($games as $g): ?>
              <tr>
                <td><?= (int)$g['index'] ?></td>
                <td><?= h($g['date_label']) ?></td>
                <td><?= h(fmt_num($g['score'], $lang, 0)) ?></td>
                <td><?= h(fmt_num($g['reached_level'], $lang, 0)) ?></td>
                <td><?= h(fmt_num($g['total_correct'], $lang, 0)) ?></td>
                <td><?= h($g['duration_label']) ?></td>
                <td><?= pill($g['status_text'], $g['status_class']) ?></td>
                <td class="right">
                  <div class="metaRow">
                    <?php if (!empty($g['flag_url'])): ?>
                      <img class="flagIcon" src="<?= h($g['flag_url']) ?>" alt="" aria-hidden="true" />
                    <?php endif; ?>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= h($g['detail_url']) ?>">
                      <?= h(TT('btn_view_details','View')) ?>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <?php if (is_file(__DIR__ . '/footer.php')) include __DIR__ . '/footer.php'; ?>
</body>
</html>
