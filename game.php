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

$game = get_game($userId, $id);
if (!$game) {
  http_response_code(404);
  echo "<!doctype html><html><head><meta charset='utf-8'><title>".htmlspecialchars(t('msg_game_not_found'))."</title></head><body style='font-family:system-ui;padding:20px'>".
       "<h1>".htmlspecialchars(t('msg_game_not_found'))."</h1>".
       "<p><a href='games.php'>".htmlspecialchars(t('btn_back_to_history'))."</a></p></body></html>";
  exit;
}

$rounds = list_rounds($id);

// New schema fields (optional)
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
  <link href="css/bootstrap.min.css" rel="stylesheet" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700;800&family=Baloo+2:wght@500;600;700&display=swap" rel="stylesheet" />
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
  <style>
    :root{ color-scheme: light dark; }
    :root,
    [data-bs-theme="dark"]{
      --bg:#0a0f1b;
      --card:rgba(255,255,255,.08);
      --bd:rgba(255,255,255,.18);
      --mut:rgba(229,234,255,.7);
      --accent:#ff6b5b;
      --accent2:#49f2b2;
      --ink:#ffffff;
    }
    [data-bs-theme="light"]{
      --bg:#fff4e8;
      --card:rgba(255,255,255,.9);
      --bd:rgba(27,31,42,.12);
      --mut:rgba(31,27,43,.68);
      --accent:#ff6b5b;
      --accent2:#20b77d;
      --ink:#1f1b2b;
    }
    body{
      margin:0;
      font-family:"Rubik","Segoe UI","Helvetica Neue",sans-serif;
      background:var(--bg);
      color:var(--ink);
      padding:18px;
      padding-bottom:84px;
    } /* footer space */
    body::after{ display:none; }
    a{ color:var(--accent); text-decoration:none; }
    .wrap{ max-width:1080px; margin:0 auto; }
    .top{ display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; }
    h1{ font-family:"Baloo 2","Rubik","Segoe UI","Helvetica Neue",sans-serif; letter-spacing:.2px; }
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
    .grid2{ display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:10px; }
    .k{ color:var(--mut); font-size:12px; }
    .v{ font-weight:700; }
    table{ width:100%; border-collapse:collapse; }
    th,td{ padding:10px 8px; border-bottom:1px solid rgba(255,255,255,.10); text-align:left; vertical-align:top; font-size:14px; }
    th{
      font-family:"Baloo 2","Rubik","Segoe UI","Helvetica Neue",sans-serif;
      color:rgba(255,255,255,.86);
      font-weight:700;
    }
    .muted{ color:var(--mut); font-size:12px; }
    .sw{ display:inline-flex; align-items:center; gap:6px; }
    .dot{ width:16px; height:16px; border-radius:6px; border:1px solid rgba(255,255,255,.22); }
    .pill{ display:inline-flex; align-items:center; padding:4px 10px; border-radius:999px; border:1px solid rgba(255,255,255,.18); background:rgba(8,16,23,.35); font-size:12px; }
    .pill-daily{ border-color:rgba(247,195,82,.45); background:rgba(247,195,82,.14); }
    .pill-normal{ border-color:rgba(53,208,186,.35); background:rgba(53,208,186,.12); }
    .flagIcon{
      width:16px;
      height:16px;
      border-radius:50%;
      object-fit:cover;
      border:1px solid rgba(255,255,255,.25);
      box-shadow: 0 2px 6px rgba(0,0,0,.35);
    }
    .right{ text-align:right; }
    html[dir="rtl"] th, html[dir="rtl"] td{ text-align:right; }
    html[dir="rtl"] .right{ text-align:left; }
    .metaRow{ display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
    .metaKey{ color:var(--mut); font-size:12px; }
    @media (max-width: 860px){
      .grid2{ grid-template-columns: 1fr; }
      .top{ align-items:flex-start; }
    }
  
    
    /* fun-bg */
    :root{ --grid: rgba(255,255,255,0.08); }
    [data-bs-theme="light"]{ --grid: rgba(31,27,43,0.1); }
    body::before,
    body::after{
      content:"";
      position:fixed;
      inset:0;
      pointer-events:none;
      z-index:-1;
    }
    body::before{
      background:
        radial-gradient(640px 640px at 12% 12%, rgba(255,107,91,0.16), transparent 60%),
        radial-gradient(600px 600px at 88% 18%, rgba(124,137,255,0.14), transparent 60%),
        radial-gradient(520px 520px at 50% 85%, rgba(73,242,178,0.12), transparent 60%);
      opacity:0.6;
    }
    body::after{
      background: radial-gradient(var(--grid) 1px, transparent 1px);
      background-size: 28px 28px;
      opacity:0.32;
    }
    h1, h2, h3{
      position: relative;
      display: inline-block;
      font-family: "Baloo 2", "Rubik", "Segoe UI", "Helvetica Neue", sans-serif;
      letter-spacing:.2px;
    }
    h1::after, h2::after, h3::after{
      content:"";
      position:absolute;
      left: 0;
      bottom: -6px;
      width: 100%;
      height: 10px;
      border-radius: 999px;
      background: linear-gradient(135deg, rgba(255,211,107,0.7), rgba(255,107,91,0.35));
      z-index:-1;
    }
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



