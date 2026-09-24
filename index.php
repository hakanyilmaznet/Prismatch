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
    body {
      padding-top: calc(var(--pm-header-offset, 0px) + 16px);
      padding-bottom: calc(var(--pm-footer-offset, 0px) + 24px);
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
          <span class="icon-pill pop"><img src="bootstrap-icons/lightning-charge-fill.svg" alt="" /><?= htmlspecialchars(t('pill_speed')) ?></span>
          <span class="icon-pill chill"><img src="bootstrap-icons/palette-fill.svg" alt="" /><?= htmlspecialchars(t('pill_color')) ?></span>
          <span class="icon-pill"><img src="bootstrap-icons/stars.svg" alt="" /><?= htmlspecialchars(t('pill_bonus')) ?></span>
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
          <span class="ribbon fast"><?= htmlspecialchars(t('ribbon_fast')) ?></span>
          <div class="spark">POP</div>
          <strong><?= htmlspecialchars(tt('home_fun_1_title', 'Mini turlar')) ?></strong>
          <p><?= htmlspecialchars(tt('home_fun_1_body', '60 saniye, 3 seviye, tek amaç: rengi yakala!')) ?></p>
        </div>
        <div class="fun-card">
          <span class="ribbon daily"><?= htmlspecialchars(t('ribbon_daily')) ?></span>
          <div class="spark">GO</div>
          <strong><?= htmlspecialchars(tt('home_fun_2_title', 'Günlük meydan okuma')) ?></strong>
          <p><?= htmlspecialchars(tt('home_fun_2_body', 'Bugünlük tek şans. Herkes aynı renklerle yarışır.')) ?></p>
        </div>
        <div class="fun-card">
          <span class="ribbon bonus"><?= htmlspecialchars(t('ribbon_bonus')) ?></span>
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



