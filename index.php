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

if (!function_exists('tt')) {
  function tt(string $key, string $fallback = ''): string {
    $v = t($key);
    if ($v === $key) return $fallback !== '' ? $fallback : $key;
    return $v;
  }
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
  <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700;800;900&family=Baloo+2:wght@500;600;700;800&display=swap" rel="stylesheet" />
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
</head>
<body class="pm-has-fixed-header">
  <?php include __DIR__ . '/header.php'; ?>

  <main class="page">
    <!-- HERO SECTION -->
    <section class="home-hero">
      <div>
        <div class="home-hero-badge">
          <span>✨</span>
          <span><?= htmlspecialchars(tt('home_hero_tag', 'Bilişsel Hız & Renk Hafıza Arenası')) ?></span>
        </div>
        <h1 class="home-hero-title">
          <?= htmlspecialchars(tt('home_hero_title', 'Renkleri Hatırla, Zamanla Yarış!')) ?>
        </h1>
        <p class="home-hero-desc">
          <?= htmlspecialchars(tt('home_hero_subtitle', 'Görsel hafızanı ve reflekslerini test et. Gösterilen hedef rengi aklında tut, grid içinden doğru tonu yakala ve skor tablosunun zirvesine çık!')) ?>
        </p>

        <div class="home-hero-actions">
          <a class="btn btn-primary btn-lg" href="play.php">
            <img class="bi-icon" src="bootstrap-icons/play-fill.svg" alt="" aria-hidden="true" />
            <span><?= htmlspecialchars(tt('home_hero_play_btn', 'Hemen Oyna')) ?></span>
          </a>
          <a class="btn btn-lg" href="rooms.php">
            <span class="pulse-dot"></span>
            <img class="bi-icon" src="bootstrap-icons/people-fill.svg" alt="" aria-hidden="true" />
            <span><?= htmlspecialchars(tt('home_hero_rooms_btn', 'Çok Oyunculu')) ?></span>
          </a>
          <a class="btn btn-lg" href="play.php?daily=1">
            <img class="bi-icon" src="bootstrap-icons/calendar2-check.svg" alt="" aria-hidden="true" />
            <span><?= htmlspecialchars(tt('home_hero_daily_btn', 'Günlük')) ?></span>
          </a>
          <a class="btn btn-lg" href="daily_leaderboard.php">
            <img class="bi-icon" src="bootstrap-icons/trophy-fill.svg" alt="" aria-hidden="true" />
            <span><?= htmlspecialchars(tt('home_hero_leaderboard_btn', 'Sıralama')) ?></span>
          </a>
        </div>

        <div class="home-hero-highlights">
          <div class="highlight-chip">
            <span>⚡</span>
            <span><?= htmlspecialchars(tt('home_stat_speed', '60sn Hızlı Turlar')) ?></span>
          </div>
          <div class="highlight-chip">
            <span>🎯</span>
            <span><?= htmlspecialchars(tt('home_stat_focus', 'Keskin Renk Algısı')) ?></span>
          </div>
          <div class="highlight-chip">
            <span>🏆</span>
            <span><?= htmlspecialchars(tt('home_stat_ranking', 'Küresel Sıralama')) ?></span>
          </div>
        </div>
      </div>

      <!-- INTERACTIVE GAME DEMO CARD -->
      <div class="home-demo-card" aria-label="Interactive Game Demo">
        <div class="demo-header">
          <div class="demo-tag">
            <span class="pulse-dot"></span>
            <span><?= htmlspecialchars(tt('home_interactive_tag', 'Canlı Önizleme')) ?></span>
          </div>
          <div class="small text-muted fw-bold">
            <?= htmlspecialchars(tt('home_interactive_stage', 'Aşama')) ?> <span id="demoStage" class="text-primary fw-bolder">1</span> · 
            <span id="demoScore" class="text-primary fw-bolder">0</span> XP
          </div>
        </div>

        <div class="demo-target-wrap">
          <div class="demo-target-label"><?= htmlspecialchars(tt('home_interactive_target_label', 'HEDEF RENK')) ?></div>
          <div id="demoTargetSwatch" class="demo-target-swatch" style="background-color: #ff6b5b;"></div>
        </div>

        <div id="demoGrid" class="demo-grid" role="region" aria-label="Demo color grid">
          <!-- 9 dynamic color tiles injected by script -->
        </div>

        <div id="demoFeedback" class="demo-status text-muted">
          <?= htmlspecialchars(tt('home_interactive_hint', 'Aşağıdaki renklerden doğru olana tıkla!')) ?>
        </div>
      </div>
    </section>

    <!-- GAME MODES SECTION -->
    <section>
      <div class="section-header">
        <h2 class="section-title"><?= htmlspecialchars(tt('home_modes_section_title', 'Heyecan Dolu Oyun Modları')) ?></h2>
        <p class="section-subtitle"><?= htmlspecialchars(tt('home_modes_section_subtitle', 'İster tek başına rekor kır, ister arkadaşlarınla canlı odalarda yarış veya günlük turnuvaya katıl.')) ?></p>
      </div>

      <div class="grid cols-3 mt-3">
        <!-- Solo Blitz Mode -->
        <div class="mode-card mode-solo">
          <div>
            <div class="mode-top mb-3">
              <div class="mode-icon">⚡</div>
              <span class="mode-badge badge-solo">POPÜLER</span>
            </div>
            <h3 class="mode-title"><?= htmlspecialchars(tt('home_mode_solo_title', 'Tek Oyunculu Hızlı Tur')) ?></h3>
            <p class="mode-desc"><?= htmlspecialchars(tt('home_mode_solo_desc', '60 saniyelik mikro turlar. Aşamalar ilerledikçe renk tonları birbirine yaklaşır ve süren kısalır. Kendi rekorunu egale et.')) ?></p>
          </div>
          <a class="btn btn-primary w-100" href="play.php">
            <img class="bi-icon" src="bootstrap-icons/play-fill.svg" alt="" aria-hidden="true" />
            <span><?= htmlspecialchars(tt('home_mode_solo_btn', 'Hemen Başla')) ?></span>
          </a>
        </div>

        <!-- Live Multiplayer Rooms -->
        <div class="mode-card mode-multi">
          <div>
            <div class="mode-top mb-3">
              <div class="mode-icon">👥</div>
              <span class="mode-badge badge-multi">CANLI YARIŞ</span>
            </div>
            <h3 class="mode-title"><?= htmlspecialchars(tt('home_mode_multi_title', 'Canlı Çok Oyunculu Odalar')) ?></h3>
            <p class="mode-desc"><?= htmlspecialchars(tt('home_mode_multi_desc', 'Arkadaşlarınla genel veya özel odalar kur. Aynı renk diziliminde gerçek zamanlı yarış, son ayakta kalan kazanır!')) ?></p>
          </div>
          <a class="btn w-100" href="rooms.php">
            <img class="bi-icon" src="bootstrap-icons/people-fill.svg" alt="" aria-hidden="true" />
            <span><?= htmlspecialchars(tt('home_mode_multi_btn', 'Odalara Git')) ?></span>
          </a>
        </div>

        <!-- Daily Challenge -->
        <div class="mode-card mode-daily">
          <div>
            <div class="mode-top mb-3">
              <div class="mode-icon">🎯</div>
              <span class="mode-badge badge-daily">GÜNDE 1 HAK</span>
            </div>
            <h3 class="mode-title"><?= htmlspecialchars(tt('home_mode_daily_title', 'Günlük Meydan Okuma')) ?></h3>
            <p class="mode-desc"><?= htmlspecialchars(tt('home_mode_daily_desc', 'Her gün tüm dünyadaki oyuncular için tek bir deneme hakkı. Aynı renk serisinde yarış ve küresel podyumda yerini al.')) ?></p>
          </div>
          <a class="btn w-100" href="play.php?daily=1">
            <img class="bi-icon" src="bootstrap-icons/calendar2-check.svg" alt="" aria-hidden="true" />
            <span><?= htmlspecialchars(tt('home_mode_daily_btn', 'Günün Turuna Katıl')) ?></span>
          </a>
        </div>
      </div>
    </section>

    <!-- HOW IT WORKS SECTION -->
    <section>
      <div class="section-header">
        <h2 class="section-title"><?= htmlspecialchars(tt('home_how_section_title', 'Prismatch Nasıl Oynanır?')) ?></h2>
        <p class="section-subtitle"><?= htmlspecialchars(tt('home_how_section_subtitle', 'Üç basit ve akıcı adımda zihnini eğit, hızını artır.')) ?></p>
      </div>

      <div class="grid cols-3 mt-3">
        <div class="step-card">
          <div class="step-badge">1</div>
          <h3><?= htmlspecialchars(tt('home_step_1_title', '1. Hedef Rengi Gör')) ?></h3>
          <p><?= htmlspecialchars(tt('home_step_1_body', 'Ekranda birkaç saniye beliren rengin tonunu ve parlaklığını dikkatlice görsel hafızana kazı.')) ?></p>
        </div>

        <div class="step-card">
          <div class="step-badge">2</div>
          <h3><?= htmlspecialchars(tt('home_step_2_title', '2. Zihninde Tut')) ?></h3>
          <p><?= htmlspecialchars(tt('home_step_2_body', 'Hedef kaybolduğunda 9 farklı renk seçeneği belirecek. Odaklan ve hedef rengi anımsa.')) ?></p>
        </div>

        <div class="step-card">
          <div class="step-badge">3</div>
          <h3><?= htmlspecialchars(tt('home_step_3_title', '3. Doğru Tonu Yakala')) ?></h3>
          <p><?= htmlspecialchars(tt('home_step_3_body', 'Süre tükenmeden doğru karoya dokun. Hızlı cevaplar ek bonus puan kazandırır ve yeni aşamaları açar!')) ?></p>
        </div>
      </div>
    </section>

    <!-- WHY PRISMATCH SECTION -->
    <section>
      <div class="section-header">
        <h2 class="section-title"><?= htmlspecialchars(tt('home_why_section_title', 'Neden Prismatch?')) ?></h2>
      </div>

      <div class="grid cols-3 mt-3">
        <div class="benefit-card">
          <div class="benefit-icon">🎯</div>
          <div>
            <h3><?= htmlspecialchars(tt('home_why_1_title', 'Görsel Odak & Nöroplastisite')) ?></h3>
            <p><?= htmlspecialchars(tt('home_why_1_desc', 'Hızlı mikro turlarla görsel hafızanı ve renk ayırt etme reflekslerini yorulmadan canlı tut.')) ?></p>
          </div>
        </div>

        <div class="benefit-card">
          <div class="benefit-icon">📊</div>
          <div>
            <h3><?= htmlspecialchars(tt('home_why_2_title', 'Detaylı İstatistikler & İlerleme')) ?></h3>
            <p><?= htmlspecialchars(tt('home_why_2_desc', 'Milisaniye cinsinden tepki sürelerini, doğruluk yüzdeni ve kariyer rekorlarını şeffafça incele.')) ?></p>
          </div>
        </div>

        <div class="benefit-card">
          <div class="benefit-icon">🌍</div>
          <div>
            <h3><?= htmlspecialchars(tt('home_why_3_title', 'Küresel Rekabet & 19 Farklı Dil')) ?></h3>
            <p><?= htmlspecialchars(tt('home_why_3_desc', 'Ülke bayrakları, anlık liderlik tablosu ve 19 tam yerelleştirilmiş dille dünya çapında yarış.')) ?></p>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA FOOTER BANNER -->
    <section class="cta-banner">
      <h2 class="cta-banner-title"><?= htmlspecialchars(tt('home_cta_banner_title', 'Renk Hafızana Güveniyor musun?')) ?></h2>
      <p class="cta-banner-desc"><?= htmlspecialchars(tt('home_cta_banner_desc', 'Hemen ücretsiz oynamaya başla veya oturum açarak başarılarını ve rekorlarını kalıcı hale getir.')) ?></p>
      <div class="d-flex flex-wrap gap-3 justify-content-center mt-2">
        <a class="btn btn-primary btn-lg" href="play.php">
          <img class="bi-icon" src="bootstrap-icons/play-fill.svg" alt="" aria-hidden="true" />
          <span><?= htmlspecialchars(tt('home_cta_banner_play', 'Ücretsiz Oyna')) ?></span>
        </a>
        <?php if (!empty($userEmail)): ?>
          <a class="btn btn-lg" href="games.php">
            <img class="bi-icon" src="bootstrap-icons/journal-text.svg" alt="" aria-hidden="true" />
            <span><?= htmlspecialchars(tt('games_title', 'Geçmişim')) ?></span>
          </a>
        <?php else: ?>
          <a class="btn btn-lg" href="login.php">
            <img class="bi-icon" src="bootstrap-icons/box-arrow-in-right.svg" alt="" aria-hidden="true" />
            <span><?= htmlspecialchars(tt('home_cta_banner_login', 'Giriş Yap / Hesabım')) ?></span>
          </a>
        <?php endif; ?>
      </div>
    </section>

    <?php if (is_file(__DIR__ . '/footer.php')) include __DIR__ . '/footer.php'; ?>
  </main>

  <!-- Interactive Demo Logic -->
  <script>
  (function(){
    const targetSwatch = document.getElementById('demoTargetSwatch');
    const grid = document.getElementById('demoGrid');
    const feedback = document.getElementById('demoFeedback');
    const scoreEl = document.getElementById('demoScore');
    const stageEl = document.getElementById('demoStage');

    if (!targetSwatch || !grid || !feedback) return;

    let score = 0;
    let stage = 1;
    let targetHsl = { h: 12, s: 85, l: 60 };

    const msgPerfect = <?= json_encode(tt('home_interactive_perfect', '✨ Harika Seçim! +100 Puan')) ?>;
    const msgTryAgain = <?= json_encode(tt('home_interactive_try_again', '❌ Yanlış ton! Tekrar dene')) ?>;
    const msgHint = <?= json_encode(tt('home_interactive_hint', 'Aşağıdaki renklerden doğru olana tıkla!')) ?>;

    function hslToCss(h, s, l) {
      return `hsl(${h}, ${s}%, ${l}%)`;
    }

    function initRound() {
      // Pick random base hue
      const baseH = Math.floor(Math.random() * 360);
      const baseS = 70 + Math.floor(Math.random() * 20);
      const baseL = 48 + Math.floor(Math.random() * 18);
      targetHsl = { h: baseH, s: baseS, l: baseL };

      targetSwatch.style.backgroundColor = hslToCss(baseH, baseS, baseL);
      targetSwatch.style.transform = 'scale(1.05)';
      setTimeout(() => { targetSwatch.style.transform = 'scale(1)'; }, 200);

      // Create 9 options with one exact match
      const correctIdx = Math.floor(Math.random() * 9);
      grid.innerHTML = '';

      for (let i = 0; i < 9; i++) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'demo-cell';
        btn.setAttribute('aria-label', 'Color option ' + (i + 1));

        let cellH, cellS, cellL;
        if (i === correctIdx) {
          cellH = baseH;
          cellS = baseS;
          cellL = baseL;
          btn.dataset.correct = 'true';
        } else {
          // Distractor with controlled offset
          const offsetH = (Math.random() > 0.5 ? 1 : -1) * (20 + Math.floor(Math.random() * 35));
          cellH = (baseH + offsetH + 360) % 360;
          cellS = Math.max(40, Math.min(90, baseS + (Math.random() * 20 - 10)));
          cellL = Math.max(35, Math.min(75, baseL + (Math.random() * 20 - 10)));
          btn.dataset.correct = 'false';
        }

        btn.style.backgroundColor = hslToCss(cellH, cellS, cellL);

        btn.addEventListener('click', function() {
          if (btn.dataset.correct === 'true') {
            score += 100;
            stage += 1;
            scoreEl.textContent = score;
            stageEl.textContent = stage;
            btn.style.boxShadow = '0 0 0 4px #10b981, 0 8px 24px rgba(16,185,129,0.5)';
            feedback.textContent = msgPerfect;
            feedback.className = 'demo-status text-success';
            
            // Advance to next round smoothly
            setTimeout(() => {
              feedback.textContent = msgHint;
              feedback.className = 'demo-status text-muted';
              initRound();
            }, 600);
          } else {
            btn.style.transform = 'scale(0.9)';
            btn.style.opacity = '0.4';
            btn.disabled = true;
            feedback.textContent = msgTryAgain;
            feedback.className = 'demo-status text-danger';
          }
        });

        grid.appendChild(btn);
      }
    }

    initRound();
  })();
  </script>
</body>
</html>
