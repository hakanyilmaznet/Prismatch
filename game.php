<?php
declare(strict_types=1);

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

$userEmail = $_SESSION['user_email'] ?? null;
$userId = $_SESSION['user_id'] ?? null;
if (!$userEmail || !$userId) {
  $next = 'game.php?id=' . rawurlencode((string)($_GET['id'] ?? ''));
  header('Location: login.php?next=' . rawurlencode($next));
  exit;
}
header('X-Robots-Tag: noindex, nofollow', true);

$id = (string)($_GET['id'] ?? '');
if ($id === '' || !preg_match('/^[a-f0-9-]{36}$/i', $id)) {
  http_response_code(400);
  $msg = 'Invalid session id.';
  echo "<!doctype html><html><head><meta charset=\"utf-8\" /><title>{$msg}</title></head><body style=\"font-family:system-ui;padding:20px\"><h1>{$msg}</h1><p><a href=\"games.php\">Back</a></p></body></html>";
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

$game = get_game($userId, $id);
if (!$game) {
  http_response_code(404);
  $msg = h(t('msg_game_not_found'));
  echo "<!doctype html><html><head><meta charset=\"utf-8\" /><title>{$msg}</title></head><body style=\"font-family:system-ui;padding:20px\"><h1>{$msg}</h1><p><a href=\"games.php\">".h(t('btn_back_to_history'))."</a></p></body></html>";
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
  <script>
    (function(){
      const key = 'pm-theme';
      const stored = localStorage.getItem(key);
      const prefers = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
      const theme = stored || prefers;
      document.documentElement.setAttribute('data-bs-theme', theme);
    })();
  </script>
  <link href="css/style.css" rel="stylesheet" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700;800&family=Baloo+2:wght@500;600;700&display=swap" rel="stylesheet" />
  <title><?= h(t('game_details_title')) ?> - <?= h(t('app_name')) ?></title>
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
    body {
      padding-top: calc(var(--pm-header-offset, 0px) + 20px);
      padding-bottom: calc(var(--pm-footer-offset, 0px) + 24px);
    }
    .grid2 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
    .k { color: var(--pm-text-muted); font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
    .v { font-weight: 700; font-size: 15px; margin-top: 2px; }
    .sw { display: inline-flex; align-items: center; gap: 8px; }
    .dot { width: 18px; height: 18px; border-radius: 6px; border: 1px solid var(--pm-border); display: inline-block; }
    .pill { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: var(--pm-radius-pill); font-size: 12px; font-weight: 600; }
    .pill-daily { border: 1px solid rgba(245, 158, 11, 0.4); background: rgba(245, 158, 11, 0.15); color: var(--pm-amber); }
    .pill-normal { border: 1px solid rgba(16, 185, 129, 0.35); background: rgba(16, 185, 129, 0.15); color: var(--pm-emerald); }
    .flagIcon { width: 18px; height: 18px; border-radius: 50%; object-fit: cover; border: 1px solid var(--pm-border); vertical-align: middle; }
    @media (max-width: 768px) {
      .grid2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 480px) {
      .grid2 { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body class="pm-has-fixed-header">
  <?php include __DIR__ . '/header.php'; ?>
  <main class="wrap">
    <div class="cardx">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
          <h1><?= h(t_safe('game_details_title','Session Details')) ?></h1>
          <div class="muted"><?= h(t_safe('game_details_sub','Detailed results for this session.')) ?></div>
        </div>
        <a class="btn btn-outline-secondary" href="games.php"><?= h(t_safe('btn_back_to_history','Back to history')) ?></a>
      </div>
    </div>

    <div class="cardx">
      <?php
        $isDaily = is_daily_game($game);
        $durationMs = (int)($game['duration_ms'] ?? 0);
        $durationLabel = mmss($durationMs);
        $score = (int)($game['score'] ?? 0);
        $created = $game['created_at'] ?? null;
        $finished = $game['finished_at'] ?? null;
        $country = (string)($game['country'] ?? '');
        $flagUrl = $country && function_exists('country_flag_icon_url') ? country_flag_icon_url($country) : '';
      ?>
      <div class="grid2">
        <div><div class="k"><?= h(t_safe('th_date','Date')) ?></div><div class="v"><?= h(fmt_dt($created, $lang)) ?></div></div>
        <div><div class="k"><?= h(t_safe('th_duration','Duration')) ?></div><div class="v"><?= h($durationLabel) ?></div></div>
        <div><div class="k"><?= h(t_safe('th_score','Score')) ?></div><div class="v"><?= h(fmt_num($score, $lang, 0)) ?></div></div>
        <div><div class="k"><?= h(t_safe('th_level','Level')) ?></div><div class="v"><?= h(fmt_num((int)($game['reached_level'] ?? 0), $lang, 0)) ?></div></div>
        <div><div class="k"><?= h(t_safe('th_correct','Correct')) ?></div><div class="v"><?= h(fmt_num((int)($game['total_correct'] ?? 0), $lang, 0)) ?></div></div>
        <div><div class="k"><?= h(t_safe('th_status','Status')) ?></div><div class="v">
          <span class="pill <?= $isDaily ? 'pill-daily' : 'pill-normal' ?>"><?= h($isDaily ? t_safe('daily_once','Daily') : t_safe('mode_normal','Normal')) ?></span>
        </div></div>
        <div><div class="k"><?= h(t_safe('th_finished','Finished')) ?></div><div class="v"><?= h(fmt_dt($finished, $lang)) ?></div></div>
        <div><div class="k"><?= h(t_safe('th_country','Country')) ?></div><div class="v">
          <?php if ($flagUrl): ?><img class="flagIcon" src="<?= h($flagUrl) ?>" alt="" aria-hidden="true" /><?php endif; ?>
          <?= h($country ?: '-') ?>
        </div></div>
        <div><div class="k"><?= h(t_safe('th_language','Language')) ?></div><div class="v"><?= h((string)($game['language'] ?? '-')) ?></div></div>
      </div>
    </div>

    <div class="cardx">
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th><?= h(t_safe('th_level','Level')) ?></th>
              <th><?= h(t_safe('th_target','Target')) ?></th>
              <th><?= h(t_safe('th_grid','Grid')) ?></th>
              <th><?= h(t_safe('th_picked','Picked')) ?></th>
              <th><?= h(t_safe('th_time','Time')) ?></th>
              <th><?= h(t_safe('th_result','Result')) ?></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rounds as $r):
            $gridArr = [];
            if (!empty($r['grid_colors_json'])) {
              $tmp = json_decode((string)$r['grid_colors_json'], true);
              if (is_array($tmp)) $gridArr = $tmp;
            }
            $ok = (int)$r['is_correct'] === 1;
          ?>
            <tr>
              <td><?= h(fmt_num((int)$r['level'], $lang, 0)) ?></td>
              <td><span class="sw"><span class="dot" style="background:<?= h($r['target_color']) ?>"></span><span><?= h($r['target_color']) ?></span></span></td>
              <td>
                <?php if ($gridArr): ?>
                  <div style="display:flex; flex-wrap:wrap; gap:6px">
                    <?php foreach ($gridArr as $c): ?>
                      <span class="dot" title="<?= h($c) ?>" style="background:<?= h($c) ?>"></span>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <span class="muted">-</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($r['picked_color'])): ?>
                  <span class="sw"><span class="dot" style="background:<?= h($r['picked_color']) ?>"></span><span><?= h($r['picked_color']) ?></span></span>
                <?php else: ?>
                  <span class="muted">-</span>
                <?php endif; ?>
              </td>
              <td><?= h(fmt_num(((int)$r['response_ms'])/1000, $lang, 2)) ?><?= h(t_safe('unit_seconds_short','s')) ?></td>
              <td><span class="pill"><?= h($ok ? t('pill_correct') : t('pill_wrong')) ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
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

