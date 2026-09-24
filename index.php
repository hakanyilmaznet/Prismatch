<?php
require_once __DIR__ . '/bootstrap.php';
if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
  error_reporting(E_ALL);
  @ini_set('display_errors', '1');
  @ini_set('display_startup_errors', '1');
}

require_once __DIR__ . '/i18n.php';

$lang = function_exists('get_lang') ? get_lang() : 'en';
$dir  = function_exists('lang_dir') ? lang_dir($lang) : 'ltr';
$userEmail = $_SESSION['user_email'] ?? null;
$showLangPicker = true;

function tt(string $key, string $fallback = ''): string {
  $v = t($key);
  if ($v === $key) return $fallback !== '' ? $fallback : $key;
  return $v;
}

$seoTitle = tt('home_meta_title', 'Prismatch - Color Memory Game');
$seoDescription = tt(
  'home_meta_description',
  'Prismatch is a fast color memory game that trains focus and short-term recall with quick, progressive rounds.'
);
$seoLangs = function_exists('supported_languages') ? array_keys(supported_languages()) : [];
?>
<!doctype html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= htmlspecialchars($dir) ?>">
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
  <title><?= htmlspecialchars($seoTitle) ?></title>
  <link rel="icon" type="image/svg+xml" href="logo.svg" />
  <?= seo_meta([
    'title' => $seoTitle,
    'description' => $seoDescription,
    'url' => seo_current_url(),
    'image' => '/logo.png',
    'type' => 'website',
    'robots' => 'index,follow',
    'lang' => $lang,
    'site_name' => tt('app_name', 'Prismatch'),
  ]) ?>
  <?= seo_alternate_links($seoLangs, seo_current_url()) ?>
    <style>
    :root{ color-scheme: light dark; }
    :root,
    [data-bs-theme="dark"]{
      --bg: #0a0f1b;
      --text: #f7f3ff;
      --muted: rgba(229,234,255,0.7);
      --panel: rgba(255,255,255,0.12);
      --panel-soft: rgba(255,255,255,0.08);
      --surface: rgba(12,16,28,0.7);
      --accent: #ff6b5b;
      --accent2: #49f2b2;
      --accent3: #ffd36b;
      --accent4: #7c89ff;
      --shadow: 0 36px 70px rgba(0,0,0,0.55);
      --radius: 24px;
      --grid: rgba(255,255,255,0.08);
    }
    [data-bs-theme="light"]{
      --bg: #fff4e8;
      --text: #1f1b2b;
      --muted: rgba(31,27,43,0.68);
      --panel: rgba(255,255,255,0.95);
      --panel-soft: rgba(255,255,255,0.75);
      --surface: rgba(255,255,255,0.92);
      --accent: #ff6b5b;
      --accent2: #20b77d;
      --accent3: #ffb24b;
      --accent4: #6c79ff;
      --shadow: 0 28px 56px rgba(40,29,12,0.18);
      --radius: 24px;
      --grid: rgba(31,27,43,0.1);
    }
    *{ box-sizing:border-box; }
    body{
      margin:0;
      font-family: "Rubik", "Segoe UI", "Helvetica Neue", sans-serif;
      color: var(--text);
      background: var(--bg);
      min-height:100vh;
      padding: 26px;
      padding-bottom: calc(100px + env(safe-area-inset-bottom));
    }
    body::after{
      content:"";
      position:fixed;
      inset:0;
      background:
        radial-gradient(700px 700px at 10% 10%, rgba(255,107,91,0.22), transparent 60%),
        radial-gradient(640px 640px at 90% 15%, rgba(124,137,255,0.2), transparent 60%),
        radial-gradient(520px 520px at 50% 85%, rgba(73,242,178,0.18), transparent 60%),
        radial-gradient(var(--grid) 1px, transparent 1px);
      background-size: auto, auto, auto, 26px 26px;
      opacity:.6;
      pointer-events:none;
      z-index:-1;
    }
    a{ color: inherit; text-decoration:none; }
    .page{ max-width: 1160px; margin:0 auto; display:flex; flex-direction:column; gap:30px; }

    .hero{
      position:relative;
      display:grid;
      gap:22px;
      background: var(--surface);
      border: 2px solid var(--grid);
      border-radius: calc(var(--radius) + 6px);
      padding: 28px;
      box-shadow: var(--shadow);
      overflow:hidden;
    }
    .hero::before,
    .hero::after{
      content:"";
      position:absolute;
      border-radius: 999px;
      filter: blur(2px);
      opacity:.8;
      z-index:0;
    }
    .hero::before{
      width: 180px; height: 180px;
      background: radial-gradient(circle, rgba(255,178,75,0.8), transparent 70%);
      top: -40px; right: -30px;
    }
    .hero::after{
      width: 210px; height: 210px;
      background: radial-gradient(circle, rgba(73,242,178,0.7), transparent 70%);
      bottom: -60px; left: -40px;
    }
    .hero > *{ position:relative; z-index:1; }
    .hero h1{
      margin:0 0 10px 0;
      font-family:"Baloo 2","Rubik","Segoe UI","Helvetica Neue",sans-serif;
      font-size: clamp(32px, 6vw, 52px);
      letter-spacing:.2px;
    }
    .hero p{ margin:0; color: var(--muted); line-height:1.6; }
    .hero .cta{ display:flex; flex-wrap:wrap; gap:10px; margin-top: 16px; }
    .hero .eyebrow{
      display:inline-flex; align-items:center; gap:8px;
      font-size:12px; letter-spacing:.3px; text-transform:uppercase; font-weight:700;
      color: #1b1511;
      background: linear-gradient(135deg, #ffd36b, #ffb24b);
      border-radius: 999px;
      padding: 6px 12px;
      margin-bottom: 12px;
    }
    [data-bs-theme="dark"] .hero .eyebrow{ color: #1b1511; }
    .hero .quick{ display:flex; flex-wrap:wrap; gap:8px; margin-top: 18px; }
    .hero .lead{ font-size:15px; }
    .hero .icon-row{ display:flex; flex-wrap:wrap; gap:8px; margin-top: 14px; }
    .icon-pill{
      display:inline-flex; align-items:center; gap:6px;
      padding:6px 10px;
      border-radius: 999px;
      border:2px solid var(--grid);
      background: var(--panel-soft);
      font-size:12px;
      font-weight:600;
    }
    .icon-pill img{ width:16px; height:16px; opacity:.9; }
    .icon-pill.pop{ background: linear-gradient(135deg, rgba(255,211,107,0.9), rgba(255,107,91,0.75)); color:#1b1511; }
    .icon-pill.chill{ background: linear-gradient(135deg, rgba(124,137,255,0.2), rgba(73,242,178,0.25)); }

    .btn{
      display:inline-flex; align-items:center; justify-content:center; gap:8px;
      padding: 11px 18px;
      border-radius: 999px;
      border:2px solid var(--grid);
      background: var(--panel);
      color: var(--text);
      font-weight: 700;
      letter-spacing:.2px;
      transition: transform 140ms ease, border-color 140ms ease, box-shadow 140ms ease, background 140ms ease;
      white-space: nowrap;
    }
    .btn:hover{ transform: translateY(-2px); border-color: rgba(255,107,91,0.6); box-shadow: 0 18px 34px rgba(255,107,91,0.22); }
    .btn.primary{
      background: linear-gradient(135deg, #ff6b5b, #ffd36b);
      border-color: rgba(255,107,91,0.6);
      color:#14111b;
      box-shadow: 0 20px 38px rgba(255,107,91,0.3);
    }

    .chip{
      background: var(--panel);
      border:2px solid var(--grid);
      padding:6px 12px;
      border-radius: 999px;
      font-size:12px;
      color: var(--text);
    }

    .showcase{
      border-radius: calc(var(--radius) + 10px);
      border:2px dashed var(--grid);
      background:
        radial-gradient(180px 180px at 20% 20%, rgba(73,242,178,0.25), transparent 70%),
        radial-gradient(220px 220px at 80% 20%, rgba(255,107,91,0.2), transparent 70%),
        radial-gradient(260px 260px at 50% 80%, rgba(255,211,107,0.25), transparent 70%),
        var(--panel-soft);
      min-height: 220px;
      padding: 20px;
      display:flex;
      flex-direction:column;
      justify-content:space-between;
      gap:10px;
    }
    .showcase .badge{
      align-self:flex-start;
      font-size:12px;
      padding:6px 12px;
      border-radius:999px;
      border:2px solid var(--grid);
      background: rgba(12,14,20,0.4);
      color: var(--text);
    }
    [data-bs-theme="light"] .showcase .badge{ background: rgba(255,255,255,0.7); }
    .showcase strong{ font-size:18px; }

    .section-title{
      font-family:"Baloo 2","Rubik","Segoe UI","Helvetica Neue",sans-serif;
      margin: 8px 0 12px 0;
      font-size: 20px;
      letter-spacing:.2px;
    }
    .grid{ display:grid; gap:14px; }
    .card{
      background: var(--surface);
      border:2px solid var(--grid);
      border-radius: 20px;
      padding: 18px;
      box-shadow: var(--shadow);
    }
    .card h3{ margin:0 0 8px 0; font-size:16px; font-weight:700; }
    .card p{ margin:0; color: var(--muted); line-height:1.5; }

    .steps{ display:grid; gap:10px; }
    .step{ display:flex; gap:12px; align-items:flex-start; }
    .step .num{
      width:32px; height:32px;
      border-radius: 12px;
      background: linear-gradient(135deg, rgba(255,211,107,0.9), rgba(255,107,91,0.8));
      display:flex; align-items:center; justify-content:center;
      font-weight:700;
      color: #1b1511;
    }

    .fun-grid{ display:grid; gap:14px; }
    .fun-card{
      background: var(--surface);
      border:2px solid var(--grid);
      border-radius: 22px;
      padding: 18px;
      box-shadow: var(--shadow);
      display:flex;
      flex-direction:column;
      gap:10px;
      position:relative;
      overflow:hidden;
    }
    .fun-card .ribbon{
      position:absolute;
      top:12px; right:12px;
      padding:4px 10px;
      border-radius: 999px;
      font-size:11px;
      font-weight:700;
      color:#1b1511;
      background: linear-gradient(135deg, #ffd36b, #ffb24b);
      border:1px solid rgba(0,0,0,0.08);
    }
    .fun-card .ribbon.fast{ background: linear-gradient(135deg, #ff8a6b, #ffd36b); }
    .fun-card .ribbon.daily{ background: linear-gradient(135deg, #7c89ff, #49f2b2); color:#10131b; }
    .fun-card .ribbon.bonus{ background: linear-gradient(135deg, #ffb24b, #ff6b5b); }
    .fun-card .spark{
      width:40px; height:40px;
      border-radius: 14px;
      display:grid; place-items:center;
      font-weight:800;
      color:#1b1511;
      background: linear-gradient(135deg, #49f2b2, #ffd36b);
    }
    .fun-card p{ margin:0; color: var(--muted); line-height:1.5; }
    .fun-card .spark{ animation: pulse 2.2s ease-in-out infinite; }
    @keyframes pulse{
      0%,100%{ transform: scale(1); box-shadow: 0 0 0 rgba(255,211,107,0.0); }
      50%{ transform: scale(1.06); box-shadow: 0 0 20px rgba(255,211,107,0.35); }
    }

    .reveal{ animation: fadeUp .6s ease both; }
    @keyframes fadeUp{ from{ opacity:0; transform: translateY(8px);} to{ opacity:1; transform: translateY(0);} }

    @media (min-width: 900px){
      .hero{ grid-template-columns: 1.1fr 0.9fr; align-items:center; }
      .grid.cols-3{ grid-template-columns: repeat(3, minmax(0,1fr)); }
      .grid.cols-2{ grid-template-columns: repeat(2, minmax(0,1fr)); }
      .fun-grid{ grid-template-columns: repeat(3, minmax(0,1fr)); }
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
</style>
</head>
<body>
  <div class="page">
    <?php include __DIR__ . '/header.php'; ?>
        <section class="hero reveal" style="animation-delay:.04s">
      <div>
        <span class="eyebrow"><?= htmlspecialchars(tt('home_showcase_badge', 'Mini eğitim • 3 adım')) ?></span>
        <h1><?= htmlspecialchars(tt('home_hero_title', 'Renkleri hatırla, doğru tonu yakala.')) ?></h1>
        <p><?= htmlspecialchars(tt('home_hero_subtitle', 'Prismatch, hızla değişen renkleri kısa süreli hafızanda tutmanı ister. Her turda süre kısalır, grid yoğunlaşır ve tek bir doğru renk seni bir sonraki aşamaya taşır.')) ?></p>
        <p class="lead"><?= htmlspecialchars(tt('home_creative', 'Renklerin hafızada şiir gibi kaldığı bir ritme gir: İpucu kaybolur, zihnin tonu yakalar. Her doğru seçim, bir sonraki sahneyi açar.')) ?></p>
          <div class="cta">
            <a class="btn primary" href="play.php">
              <img class="bi-icon" src="bootstrap-icons/play-fill.svg" alt="" aria-hidden="true" />
              <?= htmlspecialchars(tt('home_cta_play', 'Hemen oyna')) ?>
            </a>
            <a class="btn" href="rooms.php">
              <img class="bi-icon" src="bootstrap-icons/people-fill.svg" alt="" aria-hidden="true" />
              <?= htmlspecialchars(tt('rooms_title', 'Çok Oyunculu')) ?>
            </a>
            <a class="btn" href="play.php?daily=1">
              <img class="bi-icon" src="bootstrap-icons/calendar2-check.svg" alt="" aria-hidden="true" />
              <?= htmlspecialchars(tt('home_cta_daily', 'Günlük')) ?>
            </a>
            <a class="btn" href="daily_leaderboard.php">
              <img class="bi-icon" src="bootstrap-icons/trophy-fill.svg" alt="" aria-hidden="true" />
              <?= htmlspecialchars(tt('daily_leaderboard_title', 'Sıralama')) ?>
            </a>
          </div>
        <div class="quick">
          <span class="chip"><?= htmlspecialchars(tt('home_benefit_1_title', 'Zihin açan kısa turlar')) ?></span>
          <span class="chip"><?= htmlspecialchars(tt('home_benefit_2_title', 'Günlük meydan okuma')) ?></span>
          <span class="chip"><?= htmlspecialchars(tt('home_benefit_3_title', 'Çok dil ve istatistik')) ?></span>
        </div>
        <div class="icon-row" aria-hidden="true">
          <span class="icon-pill pop"><img src="bootstrap-icons/lightning-charge-fill.svg" alt="" />Hız</span>
          <span class="icon-pill chill"><img src="bootstrap-icons/palette-fill.svg" alt="" />Renk</span>
          <span class="icon-pill"><img src="bootstrap-icons/stars.svg" alt="" />Bonus</span>
        </div>
      </div>
      <div class="showcase">
        <strong><?= htmlspecialchars(tt('home_showcase_title', 'Bir tur nasıl işler?')) ?></strong>
        <div><?= htmlspecialchars(tt('home_showcase_body', 'Hedef rengi gör, hafızanda tut ve 5 saniye içinde gridde bul.')) ?></div>
        <div class="steps" style="margin-top:8px">
          <div class="step">
            <div class="num">1</div>
            <div>
              <div style="font-weight:700"><?= htmlspecialchars(tt('home_step_1_title', 'Hedef rengi izle')) ?></div>
              <div style="color:var(--muted)"><?= htmlspecialchars(tt('home_step_1_body', 'Ekranda kısa süre gösterilen rengi dikkatle aklında tut.')) ?></div>
            </div>
          </div>
          <div class="step">
            <div class="num">2</div>
            <div>
              <div style="font-weight:700"><?= htmlspecialchars(tt('home_step_2_title', 'Gridde doğru rengi seç')) ?></div>
              <div style="color:var(--muted)"><?= htmlspecialchars(tt('home_step_2_body', 'Zaman bitmeden, hatırladığın rengi 9 seçenek arasında bul.')) ?></div>
            </div>
          </div>
          <div class="step">
            <div class="num">3</div>
            <div>
              <div style="font-weight:700"><?= htmlspecialchars(tt('home_step_3_title', 'Aşamaları geç, skoru yükselt')) ?></div>
              <div style="color:var(--muted)"><?= htmlspecialchars(tt('home_step_3_body', 'Her doğru eşleşme seni bir üst aşamaya taşır.')) ?></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="reveal" style="animation-delay:.1s">
      <div class="section-title"><?= htmlspecialchars(tt('home_intro_title', 'Proje ne sağlıyor?')) ?></div>
      <div class="grid cols-2">
        <div class="card">
          <h3><?= htmlspecialchars(tt('home_intro_headline', 'Hızlı odak & hafıza egzersizi')) ?></h3>
          <p><?= htmlspecialchars(tt('home_intro_body', 'Kısa süreli hatırlama ve dikkat kontrolünü ölçen mikro turlar, gün içinde pratik yapmak için ideal.')) ?></p>
        </div>
        <div class="card">
          <h3><?= htmlspecialchars(tt('home_intro_headline2', 'Geri bildirim ve ilerleme')) ?></h3>
          <p><?= htmlspecialchars(tt('home_intro_body2', 'Aşama, doğru eşleşme ve süre kayıtlarıyla gelişimini takip edebilir, günlük hedef koyabilirsin.')) ?></p>
        </div>
      </div>
    </section>

    <section class="reveal" style="animation-delay:.16s">
      <div class="section-title"><?= htmlspecialchars(tt('home_benefits_title', 'Öne çıkan faydalar')) ?></div>
      <div class="grid cols-3">
        <div class="card">
          <h3><?= htmlspecialchars(tt('home_benefit_1_title', 'Zihin açan kısa turlar')) ?></h3>
          <p><?= htmlspecialchars(tt('home_benefit_1_body', 'Her tur birkaç saniye sürer; kısa molalarda bile kolayca oynanır.')) ?></p>
        </div>
        <div class="card">
          <h3><?= htmlspecialchars(tt('home_benefit_2_title', 'Günlük meydan okuma')) ?></h3>
          <p><?= htmlspecialchars(tt('home_benefit_2_body', 'Her gün tek deneme hakkıyla odak ve süre yönetimini geliştirir.')) ?></p>
        </div>
        <div class="card">
          <h3><?= htmlspecialchars(tt('home_benefit_3_title', 'Çok dil ve istatistik')) ?></h3>
          <p><?= htmlspecialchars(tt('home_benefit_3_body', 'Farklı dillerde oynar, performansını kayıt altına alırsın.')) ?></p>
        </div>
      </div>
    </section>

        <section class="reveal" style="animation-delay:.22s">
      <div class="section-title"><?= htmlspecialchars(tt('home_fun_title', 'Eğlenceli modlar ve sürprizler')) ?></div>
      <div class="fun-grid">
        <div class="fun-card">
          <span class="ribbon fast">Hızlı</span>
          <div class="spark">POP</div>
          <strong><?= htmlspecialchars(tt('home_fun_1_title', 'Mini turlar')) ?></strong>
          <p><?= htmlspecialchars(tt('home_fun_1_body', '60 saniye, 3 seviye, tek amaç: rengi yakala!')) ?></p>
        </div>
        <div class="fun-card">
          <span class="ribbon daily">Günlük</span>
          <div class="spark">GO</div>
          <strong><?= htmlspecialchars(tt('home_fun_2_title', 'Günlük meydan okuma')) ?></strong>
          <p><?= htmlspecialchars(tt('home_fun_2_body', 'Bugünlük tek şans. Herkes aynı renklerle yarışır.')) ?></p>
        </div>
        <div class="fun-card">
          <span class="ribbon bonus">Bonus</span>
          <div class="spark">WOW</div>
          <strong><?= htmlspecialchars(tt('home_fun_3_title', 'Sürpriz ödüller')) ?></strong>
          <p><?= htmlspecialchars(tt('home_fun_3_body', 'Seri doğru seçim = rozet, yeni tema, gizli bonus.')) ?></p>
        </div>
      </div>
    </section>


    <?php if (is_file(__DIR__ . '/footer.php')) include __DIR__ . '/footer.php'; ?>
  </div>


</body>
</html>



