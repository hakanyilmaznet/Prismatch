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
$userEmail = $_SESSION['user_email'] ?? null;
$showLangPicker = true;

if (!function_exists('tt')) {
  function tt(string $key, string $fallback = ''): string {
    $v = t($key);
    if ($v === $key) return $fallback !== '' ? $fallback : $key;
    return $v;
  }
}

$seoTitle = tt('home_meta_title', 'Prismatch - Renk Hafıza Oyunu');
$seoDescription = tt(
  'home_meta_description',
  'Prismatch, hızlı ve aşamalı turlarla odak ve kısa süreli hafızayı güçlendiren bir renk hafıza oyunudur.'
);
$seoLangs = function_exists('supported_languages') ? array_keys(supported_languages()) : [];

// Fetch today's top 3 daily leaders for live podium teaser
$topLeaders = [];
try {
  $todayUtc = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d');
  $topLeaders = daily_leaderboard($todayUtc, null, 3);
} catch (\Throwable $e) {
  $topLeaders = [];
}

$cssVersion = is_file(__DIR__ . '/css/style.css') ? filemtime(__DIR__ . '/css/style.css') : time();
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
  <link href="css/style.css?v=<?= $cssVersion ?>" rel="stylesheet" />
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

  <style>
    /* ==========================================================================
       PRISMATCH HERO & HOMEPAGE CORE STYLES (Self-Contained & Cache-Safe)
       ========================================================================== */
    :root {
      --pm-prism-1: #ff5e62;
      --pm-prism-2: #ff9966;
      --pm-prism-3: #ffd166;
      --pm-prism-4: #06d6a0;
      --pm-prism-5: #118ab2;
      --pm-prism-6: #7c89ff;
    }

    body.pm-has-fixed-header {
      padding-top: calc(var(--pm-header-offset, 76px) + 20px) !important;
      padding-bottom: calc(var(--pm-footer-offset, 60px) + 24px + env(safe-area-inset-bottom));
    }

    .pm-homepage-wrap {
      width: 100%;
      max-width: 1180px;
      margin: 0 auto;
      padding: 0 16px;
      display: flex;
      flex-direction: column;
      gap: 36px;
      box-sizing: border-box;
    }

    @media (min-width: 768px) {
      .pm-homepage-wrap {
        padding: 0 24px;
        gap: 48px;
      }
    }

    /* Prism Shimmer Text */
    .prism-shimmer {
      background: linear-gradient(135deg, #ff6b5b 0%, #ffa534 25%, #49f2b2 50%, #7c89ff 75%, #ff6b5b 100%);
      background-size: 200% auto;
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      animation: pmShimmer 6s linear infinite;
    }

    @keyframes pmShimmer {
      0% { background-position: 0% center; }
      100% { background-position: 200% center; }
    }

    /* Hero Section */
    .pm-hero-section {
      background: linear-gradient(145deg, rgba(255, 107, 91, 0.08), rgba(124, 137, 255, 0.08)), var(--pm-bg-card, #121826);
      border: 1px solid var(--pm-border, rgba(255, 255, 255, 0.1));
      border-radius: 28px;
      padding: 28px 20px;
      box-shadow: 0 24px 64px rgba(0, 0, 0, 0.28);
      display: grid;
      grid-template-columns: 1fr;
      gap: 32px;
      position: relative;
      overflow: hidden;
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
    }

    @media (min-width: 992px) {
      .pm-hero-section {
        grid-template-columns: 1.12fr 0.88fr;
        align-items: center;
        padding: 44px 40px;
        gap: 40px;
      }
    }

    .pm-hero-eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 14px;
      border-radius: 999px;
      background: linear-gradient(135deg, rgba(255, 107, 91, 0.16), rgba(255, 165, 52, 0.16));
      border: 1px solid rgba(255, 107, 91, 0.35);
      color: #ff6b5b;
      font-size: 12.5px;
      font-weight: 700;
      letter-spacing: 0.3px;
      margin-bottom: 14px;
    }

    .pm-hero-title {
      font-family: var(--pm-font-display, "Baloo 2", sans-serif);
      font-size: clamp(30px, 5.5vw, 48px);
      font-weight: 800;
      line-height: 1.15;
      margin: 0 0 16px 0;
      letter-spacing: -0.02em;
    }

    .pm-hero-desc {
      font-size: clamp(15px, 2vw, 16.5px);
      color: var(--pm-text-muted, #94a3b8);
      line-height: 1.6;
      margin: 0 0 24px 0;
      max-width: 580px;
    }

    .pm-hero-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      align-items: center;
      margin-bottom: 24px;
    }

    .pm-btn-play {
      background: linear-gradient(135deg, #ff6b5b, #ff8c42) !important;
      color: #ffffff !important;
      border: none !important;
      box-shadow: 0 8px 28px rgba(255, 107, 91, 0.45) !important;
      padding: 13px 26px !important;
      font-size: 16px !important;
      font-weight: 800 !important;
      border-radius: 999px !important;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      text-decoration: none;
      transition: transform 0.15s ease, box-shadow 0.15s ease;
    }

    .pm-btn-play:hover {
      transform: translateY(-2px) scale(1.02);
      box-shadow: 0 12px 36px rgba(255, 107, 91, 0.6) !important;
      color: #fff !important;
    }

    .pm-btn-secondary {
      background: var(--pm-bg-elevated, rgba(255, 255, 255, 0.07));
      border: 1px solid var(--pm-border, rgba(255, 255, 255, 0.15));
      color: var(--pm-text, #f8fafc);
      padding: 13px 22px;
      font-size: 15px;
      font-weight: 700;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      transition: transform 0.15s ease, background 0.15s ease, border-color 0.15s ease;
    }

    .pm-btn-secondary:hover {
      transform: translateY(-2px);
      background: rgba(255, 255, 255, 0.12);
      border-color: rgba(255, 255, 255, 0.25);
      color: inherit;
    }

    .pm-hero-chips {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }

    .pm-chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 14px;
      border-radius: 999px;
      background: var(--pm-bg-elevated, rgba(255, 255, 255, 0.05));
      border: 1px solid var(--pm-border, rgba(255, 255, 255, 0.1));
      font-size: 12.5px;
      font-weight: 600;
      color: var(--pm-text-muted, #94a3b8);
    }

    /* Playable Mini Game Card in Hero */
    .pm-mini-arena {
      background: var(--pm-bg-elevated, rgba(18, 24, 38, 0.95));
      border: 1px solid var(--pm-border, rgba(255, 255, 255, 0.12));
      border-radius: 24px;
      padding: 22px;
      box-shadow: 0 16px 48px rgba(0, 0, 0, 0.35);
      display: flex;
      flex-direction: column;
      gap: 16px;
      position: relative;
    }

    .pm-mini-top {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .pm-live-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 12px;
      font-weight: 800;
      letter-spacing: 0.6px;
      text-transform: uppercase;
      color: #10b981;
    }

    .pm-live-stats {
      font-size: 13px;
      font-weight: 700;
      color: var(--pm-text-muted, #94a3b8);
    }

    .pm-target-banner {
      background: rgba(0, 0, 0, 0.06);
      border: 1px dashed var(--pm-border, rgba(255, 255, 255, 0.14));
      border-radius: 16px;
      padding: 14px;
      text-align: center;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
    }

    [data-bs-theme="dark"] .pm-target-banner {
      background: rgba(255, 255, 255, 0.03);
    }

    .pm-target-tag {
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 1px;
      text-transform: uppercase;
      color: var(--pm-text-muted, #94a3b8);
    }

    .pm-target-box {
      width: min(240px, 85%);
      height: 48px;
      border-radius: 12px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
      border: 2px solid rgba(255, 255, 255, 0.4);
      transition: transform 0.2s ease, background-color 0.3s ease;
    }

    .pm-mini-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
      width: 100%;
    }

    .pm-mini-tile {
      aspect-ratio: 1;
      border-radius: 14px;
      border: 2px solid rgba(255, 255, 255, 0.16);
      cursor: pointer;
      outline: none;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
      -webkit-tap-highlight-color: transparent;
    }

    .pm-mini-tile:hover {
      transform: scale(1.06);
      box-shadow: 0 10px 24px rgba(0, 0, 0, 0.3);
      border-color: rgba(255, 255, 255, 0.65);
    }

    .pm-mini-tile:active {
      transform: scale(0.96);
    }

    .pm-mini-feedback {
      text-align: center;
      font-size: 13px;
      font-weight: 700;
      min-height: 20px;
      color: var(--pm-text-muted, #94a3b8);
      transition: color 0.15s ease;
    }

    /* Section Headings */
    .pm-section-head {
      text-align: center;
      margin-bottom: 20px;
    }

    .pm-section-title {
      font-family: var(--pm-font-display, "Baloo 2", sans-serif);
      font-size: clamp(24px, 4vw, 34px);
      font-weight: 800;
      margin: 0 0 6px 0;
      letter-spacing: -0.01em;
    }

    .pm-section-sub {
      color: var(--pm-text-muted, #94a3b8);
      font-size: 15px;
      max-width: 620px;
      margin: 0 auto;
      line-height: 1.5;
    }

    /* 3-Column Card Grids */
    .pm-cards-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 20px;
      width: 100%;
    }

    @media (min-width: 768px) {
      .pm-cards-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
      }
    }

    /* Mode Cards */
    .pm-mode-card {
      background: var(--pm-bg-card, #121826);
      border: 1px solid var(--pm-border, rgba(255, 255, 255, 0.1));
      border-radius: 20px;
      padding: 26px 22px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      gap: 18px;
      position: relative;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
      transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
    }

    .pm-mode-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 16px 40px rgba(0, 0, 0, 0.25);
      border-color: rgba(255, 255, 255, 0.25);
    }

    .pm-mode-solo { border-top: 4px solid #ff6b5b; }
    .pm-mode-multi { border-top: 4px solid #7c89ff; }
    .pm-mode-daily { border-top: 4px solid #10b981; }

    .pm-mode-icon {
      width: 52px;
      height: 52px;
      border-radius: 16px;
      display: grid;
      place-items: center;
      font-size: 26px;
      background: var(--pm-bg-elevated, rgba(255, 255, 255, 0.08));
      border: 1px solid var(--pm-border, rgba(255, 255, 255, 0.1));
    }

    .pm-mode-pill {
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.6px;
      text-transform: uppercase;
      padding: 4px 10px;
      border-radius: 999px;
    }

    .pm-pill-solo { background: rgba(255, 107, 91, 0.18); color: #ff6b5b; }
    .pm-pill-multi { background: rgba(124, 137, 255, 0.18); color: #7c89ff; }
    .pm-pill-daily { background: rgba(16, 185, 129, 0.18); color: #10b981; }

    .pm-mode-title {
      font-size: 20px;
      font-weight: 800;
      margin: 12px 0 6px 0;
    }

    .pm-mode-desc {
      font-size: 14px;
      color: var(--pm-text-muted, #94a3b8);
      line-height: 1.55;
      margin: 0;
    }

    /* Step Cards */
    .pm-step-card {
      background: var(--pm-bg-card, #121826);
      border: 1px solid var(--pm-border, rgba(255, 255, 255, 0.1));
      border-radius: 20px;
      padding: 26px 22px;
      display: flex;
      flex-direction: column;
      gap: 12px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
      transition: transform 0.2s ease, border-color 0.2s ease;
    }

    .pm-step-card:hover {
      transform: translateY(-4px);
      border-color: rgba(255, 255, 255, 0.25);
    }

    .pm-step-badge {
      width: 44px;
      height: 44px;
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: var(--pm-font-display, "Baloo 2", sans-serif);
      font-size: 18px;
      font-weight: 800;
      background: linear-gradient(135deg, rgba(255, 107, 91, 0.2), rgba(255, 165, 52, 0.2));
      border: 1px solid rgba(255, 107, 91, 0.35);
      color: #ff6b5b;
    }

    .pm-step-title {
      font-size: 18px;
      font-weight: 800;
      margin: 0;
    }

    .pm-step-desc {
      font-size: 14px;
      color: var(--pm-text-muted, #94a3b8);
      line-height: 1.55;
      margin: 0;
    }

    /* Benefit Cards */
    .pm-benefit-card {
      background: var(--pm-bg-card, #121826);
      border: 1px solid var(--pm-border, rgba(255, 255, 255, 0.1));
      border-radius: 20px;
      padding: 22px;
      display: flex;
      gap: 16px;
      align-items: flex-start;
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
      transition: transform 0.2s ease;
    }

    .pm-benefit-card:hover {
      transform: translateY(-3px);
    }

    .pm-benefit-icon {
      font-size: 24px;
      min-width: 48px;
      width: 48px;
      height: 48px;
      border-radius: 14px;
      display: grid;
      place-items: center;
      background: var(--pm-bg-elevated, rgba(255, 255, 255, 0.08));
      border: 1px solid var(--pm-border, rgba(255, 255, 255, 0.1));
      flex-shrink: 0;
    }

    /* Leaderboard Podium Teaser */
    .pm-podium-card {
      background: linear-gradient(135deg, rgba(255, 209, 102, 0.12), rgba(255, 107, 91, 0.08)), var(--pm-bg-card, #121826);
      border: 1px solid rgba(255, 209, 102, 0.3);
      border-radius: 24px;
      padding: 28px 22px;
      box-shadow: 0 16px 40px rgba(0, 0, 0, 0.2);
    }

    .pm-podium-list {
      display: grid;
      grid-template-columns: 1fr;
      gap: 12px;
      margin-top: 18px;
    }

    @media (min-width: 768px) {
      .pm-podium-list {
        grid-template-columns: repeat(3, minmax(0, 1fr));
      }
    }

    .pm-podium-item {
      background: var(--pm-bg-elevated, rgba(255, 255, 255, 0.07));
      border: 1px solid var(--pm-border, rgba(255, 255, 255, 0.12));
      border-radius: 16px;
      padding: 16px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
    }

    /* Bottom CTA Banner */
    .pm-cta-banner {
      background: linear-gradient(135deg, rgba(255, 107, 91, 0.16), rgba(124, 137, 255, 0.14)), var(--pm-bg-card, #121826);
      border: 1px solid rgba(255, 107, 91, 0.4);
      border-radius: 28px;
      padding: 40px 24px;
      text-align: center;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 16px;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
    }

    .pm-cta-title {
      font-family: var(--pm-font-display, "Baloo 2", sans-serif);
      font-size: clamp(24px, 4.5vw, 36px);
      font-weight: 800;
      margin: 0;
    }

    .pm-cta-desc {
      font-size: 15.5px;
      color: var(--pm-text-muted, #94a3b8);
      max-width: 600px;
      margin: 0;
      line-height: 1.55;
    }
  </style>
</head>
<body class="pm-has-fixed-header">
  <?php include __DIR__ . '/header.php'; ?>

  <main class="pm-homepage-wrap">
    <!-- 1. HERO SECTION -->
    <section class="pm-hero-section">
      <div>
        <div class="pm-hero-eyebrow">
          <span>✨</span>
          <span><?= htmlspecialchars(tt('home_hero_tag', 'Bilişsel Hız & Renk Hafıza Arenası')) ?></span>
        </div>
        <h1 class="pm-hero-title">
          <?= htmlspecialchars(tt('home_hero_title_part1', 'Remember the Colors,')) ?> <span class="prism-shimmer"><?= htmlspecialchars(tt('home_hero_title_part2', 'Race the Clock!')) ?></span>
        </h1>
        <p class="pm-hero-desc">
          <?= htmlspecialchars(tt('home_hero_subtitle', 'Görsel hafızanı ve reflekslerini test et. Gösterilen hedef rengi aklında tut, grid içinden doğru tonu yakala ve skor tablosunun zirvesine çık!')) ?>
        </p>

        <div class="pm-hero-actions">
          <a class="pm-btn-play" href="play.php">
            <img class="bi-icon" src="bootstrap-icons/play-fill.svg" alt="" aria-hidden="true" style="width:20px;height:20px;" />
            <span><?= htmlspecialchars(tt('home_hero_play_btn', 'Hemen Oyna')) ?></span>
          </a>
          <a class="pm-btn-secondary" href="rooms.php">
            <span class="pulse-dot"></span>
            <img class="bi-icon" src="bootstrap-icons/people-fill.svg" alt="" aria-hidden="true" style="width:18px;height:18px;" />
            <span><?= htmlspecialchars(tt('home_hero_rooms_btn', 'Çok Oyunculu')) ?></span>
          </a>
          <a class="pm-btn-secondary" href="play.php?daily=1">
            <img class="bi-icon" src="bootstrap-icons/calendar2-check.svg" alt="" aria-hidden="true" style="width:18px;height:18px;" />
            <span><?= htmlspecialchars(tt('home_hero_daily_btn', 'Günlük')) ?></span>
          </a>
          <a class="pm-btn-secondary" href="daily_leaderboard.php">
            <img class="bi-icon" src="bootstrap-icons/trophy-fill.svg" alt="" aria-hidden="true" style="width:18px;height:18px;" />
            <span><?= htmlspecialchars(tt('home_hero_leaderboard_btn', 'Sıralama')) ?></span>
          </a>
        </div>

        <div class="pm-hero-chips">
          <div class="pm-chip">
            <span>⚡</span>
            <span><?= htmlspecialchars(tt('home_stat_speed', '60sn Hızlı Turlar')) ?></span>
          </div>
          <div class="pm-chip">
            <span>🎯</span>
            <span><?= htmlspecialchars(tt('home_stat_focus', 'Keskin Renk Algısı')) ?></span>
          </div>
          <div class="pm-chip">
            <span>🏆</span>
            <span><?= htmlspecialchars(tt('home_stat_ranking', 'Küresel Sıralama')) ?></span>
          </div>
        </div>
      </div>

      <!-- INTERACTIVE PLAYABLE GAME DEMO CARD -->
      <div class="pm-mini-arena" aria-label="Interactive Game Demo">
        <div class="pm-mini-top">
          <div class="pm-live-badge">
            <span class="pulse-dot"></span>
            <span><?= htmlspecialchars(tt('home_interactive_tag', 'Canlı Önizleme')) ?></span>
          </div>
          <div class="pm-live-stats">
            <?= htmlspecialchars(tt('home_interactive_stage', 'Aşama')) ?> <span id="demoStage" style="color:#ff6b5b; font-weight:800;">1</span> · 
            <span id="demoScore" style="color:#10b981; font-weight:800;">0</span> XP
          </div>
        </div>

        <div class="pm-target-banner">
          <div class="pm-target-tag"><?= htmlspecialchars(tt('home_interactive_target_label', 'HEDEF RENK')) ?></div>
          <div id="demoTargetSwatch" class="pm-target-box" style="background-color: #ff6b5b;"></div>
        </div>

        <div id="demoGrid" class="pm-mini-grid" role="region" aria-label="Demo color grid">
          <!-- 9 dynamic color tiles injected by script -->
        </div>

        <div id="demoFeedback" class="pm-mini-feedback">
          <?= htmlspecialchars(tt('home_interactive_hint', 'Aşağıdaki renklerden doğru olana tıkla!')) ?>
        </div>
      </div>
    </section>

    <!-- 2. GAME MODES SHOWCASE -->
    <section>
      <div class="pm-section-head">
        <h2 class="pm-section-title"><?= htmlspecialchars(tt('home_modes_section_title', 'Heyecan Dolu Oyun Modları')) ?></h2>
        <p class="pm-section-sub"><?= htmlspecialchars(tt('home_modes_section_subtitle', 'İster tek başına rekor kır, ister arkadaşlarınla canlı odalarda yarış veya günlük turnuvaya katıl.')) ?></p>
      </div>

      <div class="pm-cards-grid">
        <!-- Solo Blitz Mode -->
        <div class="pm-mode-card pm-mode-solo">
          <div>
            <div style="display:flex; align-items:center; justify-content:space-between;">
              <div class="pm-mode-icon">⚡</div>
              <span class="pm-mode-pill pm-pill-solo"><?= htmlspecialchars(tt('badge_popular', 'POPULAR')) ?></span>
            </div>
            <h3 class="pm-mode-title"><?= htmlspecialchars(tt('home_mode_solo_title', 'Tek Oyunculu Hızlı Tur')) ?></h3>
            <p class="pm-mode-desc"><?= htmlspecialchars(tt('home_mode_solo_desc', '60 saniyelik mikro turlar. Aşamalar ilerledikçe renk tonları birbirine yaklaşır ve süren kısalır. Kendi rekorunu egale et.')) ?></p>
          </div>
          <a class="pm-btn-play" href="play.php" style="justify-content:center; width:100%; box-sizing:border-box;">
            <span><?= htmlspecialchars(tt('home_mode_solo_btn', 'Hemen Başla')) ?> →</span>
          </a>
        </div>

        <!-- Live Multiplayer Rooms -->
        <div class="pm-mode-card pm-mode-multi">
          <div>
            <div style="display:flex; align-items:center; justify-content:space-between;">
              <div class="pm-mode-icon">👥</div>
              <span class="pm-mode-pill pm-pill-multi"><?= htmlspecialchars(tt('badge_live_race', 'LIVE BATTLE')) ?></span>
            </div>
            <h3 class="pm-mode-title"><?= htmlspecialchars(tt('home_mode_multi_title', 'Canlı Çok Oyunculu Odalar')) ?></h3>
            <p class="pm-mode-desc"><?= htmlspecialchars(tt('home_mode_multi_desc', 'Arkadaşlarınla genel veya özel odalar kur. Aynı renk diziliminde gerçek zamanlı yarış, son ayakta kalan kazanır!')) ?></p>
          </div>
          <a class="pm-btn-secondary" href="rooms.php" style="justify-content:center; width:100%; box-sizing:border-box;">
            <span><?= htmlspecialchars(tt('home_mode_multi_btn', 'Odalara Git')) ?> →</span>
          </a>
        </div>

        <!-- Daily Challenge -->
        <div class="pm-mode-card pm-mode-daily">
          <div>
            <div style="display:flex; align-items:center; justify-content:space-between;">
              <div class="pm-mode-icon">🎯</div>
              <span class="pm-mode-pill pm-pill-daily"><?= htmlspecialchars(tt('badge_daily_once', '1 TRY / DAY')) ?></span>
            </div>
            <h3 class="pm-mode-title"><?= htmlspecialchars(tt('home_mode_daily_title', 'Günlük Meydan Okuma')) ?></h3>
            <p class="pm-mode-desc"><?= htmlspecialchars(tt('home_mode_daily_desc', 'Her gün tüm dünyadaki oyuncular için tek bir deneme hakkı. Aynı renk serisinde yarış ve küresel podyumda yerini al.')) ?></p>
          </div>
          <a class="pm-btn-secondary" href="play.php?daily=1" style="justify-content:center; width:100%; box-sizing:border-box;">
            <span><?= htmlspecialchars(tt('home_mode_daily_btn', 'Günün Turuna Katıl')) ?> →</span>
          </a>
        </div>
      </div>
    </section>

    <!-- 3. HOW IT WORKS (3 STEPS) -->
    <section>
      <div class="pm-section-head">
        <h2 class="pm-section-title"><?= htmlspecialchars(tt('home_how_section_title', 'Prismatch Nasıl Oynanır?')) ?></h2>
        <p class="pm-section-sub"><?= htmlspecialchars(tt('home_how_section_subtitle', 'Üç basit ve akıcı adımda zihnini eğit, hızını artır.')) ?></p>
      </div>

      <div class="pm-cards-grid">
        <div class="pm-step-card">
          <div class="pm-step-badge">1</div>
          <h3 class="pm-step-title"><?= htmlspecialchars(tt('home_step_1_title', '1. Hedef Rengi Gör')) ?></h3>
          <p class="pm-step-desc"><?= htmlspecialchars(tt('home_step_1_body', 'Ekranda birkaç saniye beliren rengin tonunu ve parlaklığını dikkatlice görsel hafızana kazı.')) ?></p>
        </div>

        <div class="pm-step-card">
          <div class="pm-step-badge">2</div>
          <h3 class="pm-step-title"><?= htmlspecialchars(tt('home_step_2_title', '2. Zihninde Tut')) ?></h3>
          <p class="pm-step-desc"><?= htmlspecialchars(tt('home_step_2_body', 'Hedef kaybolduğunda 9 farklı renk seçeneği belirecek. Odaklan ve hedef rengi anımsa.')) ?></p>
        </div>

        <div class="pm-step-card">
          <div class="pm-step-badge">3</div>
          <h3 class="pm-step-title"><?= htmlspecialchars(tt('home_step_3_title', '3. Doğru Tonu Yakala')) ?></h3>
          <p class="pm-step-desc"><?= htmlspecialchars(tt('home_step_3_body', 'Süre tükenmeden doğru karoya dokun. Hızlı cevaplar ek bonus puan kazandırır ve yeni aşamaları açar!')) ?></p>
        </div>
      </div>
    </section>

    <!-- 4. LIVE DAILY PODIUM PREVIEW (IF AVAILABLE) -->
    <section class="pm-podium-card">
      <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px;">
        <div>
          <h2 style="font-family:var(--pm-font-display); font-size:22px; font-weight:800; margin:0 0 4px 0;">
            🏆 <?= htmlspecialchars(tt('home_podium_title', 'Daily Podium (Live Rankings)')) ?>
          </h2>
          <div style="font-size:13.5px; color:var(--pm-text-muted);"><?= htmlspecialchars(tt('home_podium_subtitle', 'Today’s top color memory masters')) ?></div>
        </div>
        <a class="pm-btn-secondary" href="daily_leaderboard.php" style="padding:8px 18px; font-size:13.5px;">
          <?= htmlspecialchars(tt('home_podium_view_all', 'View Full Table')) ?> →
        </a>
      </div>

      <?php if (!empty($topLeaders)): ?>
        <div class="pm-podium-list">
          <?php foreach ($topLeaders as $idx => $leader): 
            $medal = $idx === 0 ? '🥇' : ($idx === 1 ? '🥈' : '🥉');
            $country = strtoupper((string)($leader['country'] ?? ''));
            $flagUrl = function_exists('country_flag_icon_url') ? country_flag_icon_url($country) : '';
            $pName = !empty($leader['email']) ? substr($leader['email'], 0, 3) . '***' : tt('th_player', 'Player');
          ?>
            <div class="pm-podium-item">
              <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size:24px;"><?= $medal ?></span>
                <div>
                  <div style="font-weight:700; font-size:14.5px;"><?= htmlspecialchars($pName) ?></div>
                  <div style="font-size:12px; color:var(--pm-text-muted);">
                    <?php if ($flagUrl): ?>
                      <img src="<?= htmlspecialchars($flagUrl) ?>" alt="" style="width:16px; height:12px; vertical-align:middle; border-radius:2px;" />
                    <?php endif; ?>
                    <?= htmlspecialchars($country ?: 'GLOBAL') ?> · <?= htmlspecialchars(tt('hud_stage', 'Stage')) ?> <?= (int)($leader['reached_level'] ?? 0) ?>
                  </div>
                </div>
              </div>
              <div style="font-family:var(--pm-font-display); font-size:18px; font-weight:800; color:#ff6b5b;">
                <?= (int)($leader['score'] ?? 0) ?> <span style="font-size:12px; font-weight:600; color:var(--pm-text-muted);">XP</span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div style="margin-top:16px; padding:18px; border-radius:14px; background:rgba(0,0,0,0.05); text-align:center; color:var(--pm-text-muted); font-size:14px;">
          <?= htmlspecialchars(tt('home_podium_empty', 'No daily challenge scores recorded yet today.')) ?>
          <a href="play.php?daily=1" style="color:#ff6b5b; font-weight:700; text-decoration:underline; margin-left:6px;"><?= htmlspecialchars(tt('home_podium_empty_cta', 'Be the first to record a score!')) ?></a>
        </div>
      <?php endif; ?>
    </section>

    <!-- 5. WHY PRISMATCH SECTION -->
    <section>
      <div class="pm-section-head">
        <h2 class="pm-section-title"><?= htmlspecialchars(tt('home_why_section_title', 'Neden Prismatch?')) ?></h2>
      </div>

      <div class="pm-cards-grid">
        <div class="pm-benefit-card">
          <div class="pm-benefit-icon">🎯</div>
          <div>
            <h3 style="font-size:16.5px; font-weight:700; margin:0 0 4px 0;"><?= htmlspecialchars(tt('home_why_1_title', 'Görsel Odak & Nöroplastisite')) ?></h3>
            <p style="font-size:13.5px; color:var(--pm-text-muted); line-height:1.5; margin:0;"><?= htmlspecialchars(tt('home_why_1_desc', 'Hızlı mikro turlarla görsel hafızanı ve renk ayırt etme reflekslerini yorulmadan canlı tut.')) ?></p>
          </div>
        </div>

        <div class="pm-benefit-card">
          <div class="pm-benefit-icon">📊</div>
          <div>
            <h3 style="font-size:16.5px; font-weight:700; margin:0 0 4px 0;"><?= htmlspecialchars(tt('home_why_2_title', 'Detaylı İstatistikler & İlerleme')) ?></h3>
            <p style="font-size:13.5px; color:var(--pm-text-muted); line-height:1.5; margin:0;"><?= htmlspecialchars(tt('home_why_2_desc', 'Milisaniye cinsinden tepki sürelerini, doğruluk yüzdeni ve kariyer rekorlarını şeffafça incele.')) ?></p>
          </div>
        </div>

        <div class="pm-benefit-card">
          <div class="pm-benefit-icon">🌍</div>
          <div>
            <h3 style="font-size:16.5px; font-weight:700; margin:0 0 4px 0;"><?= htmlspecialchars(tt('home_why_3_title', 'Küresel Rekabet & 19 Farklı Dil')) ?></h3>
            <p style="font-size:13.5px; color:var(--pm-text-muted); line-height:1.5; margin:0;"><?= htmlspecialchars(tt('home_why_3_desc', 'Ülke bayrakları, anlık liderlik tablosu ve 19 tam yerelleştirilmiş dille dünya çapında yarış.')) ?></p>
          </div>
        </div>
      </div>
    </section>

    <!-- 6. BOTTOM CALL-TO-ACTION BANNER -->
    <section class="pm-cta-banner">
      <h2 class="pm-cta-title"><?= htmlspecialchars(tt('home_cta_banner_title', 'Renk Hafızana Güveniyor musun?')) ?></h2>
      <p class="pm-cta-desc"><?= htmlspecialchars(tt('home_cta_banner_desc', 'Hemen ücretsiz oynamaya başla veya oturum açarak başarılarını ve rekorlarını kalıcı hale getir.')) ?></p>
      <div style="display:flex; flex-wrap:wrap; gap:12px; justify-content:center; margin-top:8px;">
        <a class="pm-btn-play" href="play.php">
          <img class="bi-icon" src="bootstrap-icons/play-fill.svg" alt="" aria-hidden="true" style="width:20px;height:20px;" />
          <span><?= htmlspecialchars(tt('home_cta_banner_play', 'Ücretsiz Oyna')) ?></span>
        </a>
        <?php if (!empty($userEmail)): ?>
          <a class="pm-btn-secondary" href="games.php">
            <img class="bi-icon" src="bootstrap-icons/journal-text.svg" alt="" aria-hidden="true" style="width:18px;height:18px;" />
            <span><?= htmlspecialchars(tt('games_title', 'Geçmişim')) ?></span>
          </a>
        <?php else: ?>
          <a class="pm-btn-secondary" href="login.php">
            <img class="bi-icon" src="bootstrap-icons/box-arrow-in-right.svg" alt="" aria-hidden="true" style="width:18px;height:18px;" />
            <span><?= htmlspecialchars(tt('home_cta_banner_login', 'Giriş Yap / Hesabım')) ?></span>
          </a>
        <?php endif; ?>
      </div>
    </section>

    <?php if (is_file(__DIR__ . '/footer.php')) include __DIR__ . '/footer.php'; ?>
  </main>

  <!-- Interactive Playable Demo Script -->
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

    const msgPerfect = <?= json_encode(tt('home_interactive_perfect', '✨ Harika Seçim! +100 Puan')) ?>;
    const msgTryAgain = <?= json_encode(tt('home_interactive_try_again', '❌ Yanlış ton! Tekrar dene')) ?>;
    const msgHint = <?= json_encode(tt('home_interactive_hint', 'Aşağıdaki renklerden doğru olana tıkla!')) ?>;

    function hslToCss(h, s, l) {
      return `hsl(${h}, ${s}%, ${l}%)`;
    }

    function initRound() {
      const baseH = Math.floor(Math.random() * 360);
      const baseS = 70 + Math.floor(Math.random() * 20);
      const baseL = 48 + Math.floor(Math.random() * 18);

      targetSwatch.style.backgroundColor = hslToCss(baseH, baseS, baseL);
      targetSwatch.style.transform = 'scale(1.04)';
      setTimeout(() => { targetSwatch.style.transform = 'scale(1)'; }, 180);

      const correctIdx = Math.floor(Math.random() * 9);
      grid.innerHTML = '';

      for (let i = 0; i < 9; i++) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pm-mini-tile';
        const optionLabel = <?= json_encode(tt('a11y_color_option', 'Color option {n}')) ?>;
        btn.setAttribute('aria-label', optionLabel.replace('{n}', i + 1));

        let cellH, cellS, cellL;
        if (i === correctIdx) {
          cellH = baseH;
          cellS = baseS;
          cellL = baseL;
          btn.dataset.correct = 'true';
        } else {
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
            btn.style.borderColor = '#10b981';
            btn.style.boxShadow = '0 0 0 4px rgba(16, 185, 129, 0.4), 0 8px 24px rgba(16, 185, 129, 0.5)';
            feedback.textContent = msgPerfect;
            feedback.style.color = '#10b981';
            
            setTimeout(() => {
              feedback.textContent = msgHint;
              feedback.style.color = 'var(--pm-text-muted, #94a3b8)';
              initRound();
            }, 550);
          } else {
            btn.style.transform = 'scale(0.92)';
            btn.style.opacity = '0.35';
            btn.disabled = true;
            feedback.textContent = msgTryAgain;
            feedback.style.color = '#ef4444';
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
