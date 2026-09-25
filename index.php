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

    .pulse-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background-color: #10b981;
      display: inline-block;
      box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
      animation: pmPulse 1.8s infinite cubic-bezier(0.66, 0, 0, 1);
    }

    @keyframes pmPulse {
      0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
      70% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
      100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }

    .pm-live-stats {
      font-size: 13px;
      font-weight: 700;
      color: var(--pm-text-muted, #94a3b8);
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .pm-mini-icon-btn {
      background: rgba(255, 255, 255, 0.07);
      border: 1px solid var(--pm-border, rgba(255, 255, 255, 0.12));
      cursor: pointer;
      font-size: 13px;
      line-height: 1;
      padding: 4px 7px;
      border-radius: 8px;
      color: var(--pm-text-muted, #94a3b8);
      transition: background 0.15s ease, transform 0.1s ease;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    .pm-mini-icon-btn:hover {
      background: rgba(255, 255, 255, 0.15);
      transform: scale(1.08);
    }

    .pm-mini-icon-btn.is-muted {
      opacity: 0.55;
    }

    .pm-target-banner {
      background: rgba(0, 0, 0, 0.06);
      border: 1px dashed var(--pm-border, rgba(255, 255, 255, 0.14));
      border-radius: 16px;
      padding: 14px 12px;
      text-align: center;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 10px;
      position: relative;
      transition: border-color 0.25s ease, background 0.25s ease;
    }

    [data-bs-theme="dark"] .pm-target-banner {
      background: rgba(255, 255, 255, 0.03);
    }

    .pm-target-tag {
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.8px;
      text-transform: uppercase;
      color: var(--pm-text-muted, #94a3b8);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      min-height: 18px;
      transition: color 0.2s ease;
    }

    .pm-target-box {
      width: min(240px, 85%);
      height: 48px;
      border-radius: 12px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
      border: 2px solid rgba(255, 255, 255, 0.4);
      transition: transform 0.2s ease, background-color 0.25s ease, border-color 0.2s ease, box-shadow 0.2s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
      overflow: hidden;
    }

    .pm-target-box.is-hidden {
      background-color: var(--pm-bg-card, rgba(255, 255, 255, 0.05)) !important;
      border: 2px dashed rgba(255, 255, 255, 0.35) !important;
      box-shadow: inset 0 2px 10px rgba(0, 0, 0, 0.25);
    }

    .pm-target-mystery {
      font-size: 26px;
      font-weight: 900;
      color: var(--pm-text-muted, #94a3b8);
      animation: pmMysteryPulse 1.2s infinite ease-in-out;
      user-select: none;
      line-height: 1;
    }

    @keyframes pmMysteryPulse {
      0%, 100% { transform: scale(0.9); opacity: 0.6; }
      50% { transform: scale(1.15); opacity: 1; color: #ff6b5b; }
    }

    .pm-target-box.is-revealed {
      border-color: #10b981 !important;
      box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.4), 0 8px 24px rgba(16, 185, 129, 0.3) !important;
    }

    .pm-target-revealed-icon {
      font-size: 24px;
      font-weight: 900;
      color: #ffffff;
      text-shadow: 0 2px 8px rgba(0, 0, 0, 0.6);
      animation: pmTilePop 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      line-height: 1;
    }

    .pm-timer-bar-wrap {
      width: min(240px, 85%);
      height: 4px;
      background: rgba(255, 255, 255, 0.08);
      border-radius: 999px;
      overflow: hidden;
    }

    .pm-timer-bar {
      height: 100%;
      width: 100%;
      background: linear-gradient(90deg, #ff6b5b, #ffd166);
      border-radius: 999px;
      transform-origin: left;
      transition: width 0.08s linear;
    }

    .pm-timer-bar.recall {
      background: linear-gradient(90deg, #10b981, #3b82f6);
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
      transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease, opacity 0.2s ease;
      -webkit-tap-highlight-color: transparent;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
    }

    .pm-mini-tile:hover:not(:disabled):not(.is-covered) {
      transform: scale(1.06);
      box-shadow: 0 10px 24px rgba(0, 0, 0, 0.3);
      border-color: rgba(255, 255, 255, 0.65);
    }

    .pm-mini-tile:active:not(:disabled):not(.is-covered) {
      transform: scale(0.96);
    }

    .pm-mini-tile.is-covered {
      background: var(--pm-bg-card, rgba(255, 255, 255, 0.04)) !important;
      border: 1px dashed rgba(255, 255, 255, 0.14) !important;
      cursor: default;
      pointer-events: none;
      box-shadow: none !important;
    }

    .pm-mini-tile.is-covered::after {
      content: "";
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: var(--pm-text-muted, #64748b);
      opacity: 0.35;
      animation: pmDotPulse 1.6s infinite ease-in-out;
    }

    @keyframes pmDotPulse {
      0%, 100% { opacity: 0.25; transform: scale(0.8); }
      50% { opacity: 0.7; transform: scale(1.3); }
    }

    .pm-mini-tile.is-revealing {
      animation: pmTilePop 0.28s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
    }

    @keyframes pmTilePop {
      0% { opacity: 0; transform: scale(0.7); }
      100% { opacity: 1; transform: scale(1); }
    }

    @keyframes pmShake {
      0%, 100% { transform: translateX(0); }
      20% { transform: translateX(-6px); }
      40% { transform: translateX(6px); }
      60% { transform: translateX(-4px); }
      80% { transform: translateX(4px); }
    }

    .pm-mini-tile.shake {
      animation: pmShake 0.35s ease;
    }

    .pm-mini-tile.is-correct {
      border-color: #10b981 !important;
      box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.4), 0 8px 24px rgba(16, 185, 129, 0.5) !important;
      transform: scale(1.06) !important;
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
            <span><?= htmlspecialchars(tt('home_interactive_stage', 'Aşama')) ?> <span id="demoStage" style="color:#ff6b5b; font-weight:800;">1</span> · 
            <span id="demoScore" style="color:#10b981; font-weight:800;">0</span> XP</span>
            <button id="demoMuteBtn" class="pm-mini-icon-btn" type="button" aria-label="Toggle Sound" title="Sound">
              <span id="demoMuteIcon">🔊</span>
            </button>
            <button id="demoRestartBtn" class="pm-mini-icon-btn" type="button" aria-label="Restart Demo" title="<?= htmlspecialchars(tt('btn_restart', 'Restart')) ?>">
              <span>🔄</span>
            </button>
          </div>
        </div>

        <div class="pm-target-banner">
          <div id="demoTargetTag" class="pm-target-tag"><?= htmlspecialchars(tt('remember_this', 'Bu rengi aklında tut.')) ?></div>
          <div id="demoTargetSwatch" class="pm-target-box" style="background-color: #ff6b5b;"></div>
          <div class="pm-timer-bar-wrap" aria-hidden="true">
            <div id="demoTimerBar" class="pm-timer-bar"></div>
          </div>
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
    const targetTag = document.getElementById('demoTargetTag');
    const timerBar = document.getElementById('demoTimerBar');
    const grid = document.getElementById('demoGrid');
    const feedback = document.getElementById('demoFeedback');
    const scoreEl = document.getElementById('demoScore');
    const stageEl = document.getElementById('demoStage');
    const muteBtn = document.getElementById('demoMuteBtn');
    const muteIcon = document.getElementById('demoMuteIcon');
    const restartBtn = document.getElementById('demoRestartBtn');

    if (!targetSwatch || !grid || !feedback) return;

    let score = 0;
    let stage = 1;
    let currentTargetColor = '';
    let currentTiles = [];
    let phase = 'idle'; // 'memorize' | 'recall' | 'result'
    let timerIds = [];
    let recallStartTime = 0;
    let wrongAttempts = 0;

    const msgRememberTag = <?= json_encode(tt('remember_this', 'Bu rengi aklında tut.')) ?>;
    const msgRememberHint = <?= json_encode(tt('home_step_1_body', 'Ekranda beliren rengi dikkatlice görsel hafızana kazı.')) ?>;
    const msgWhichColorTag = <?= json_encode(tt('question_pick_target', 'Gösterilen renk hangisiydi? Hedefi seç.')) ?>;
    const msgRecallHint = <?= json_encode(tt('toast_pick', '5 saniye içinde doğru renge dokun!')) ?>;
    const msgPerfect = <?= json_encode(tt('home_interactive_perfect', '✨ Harika Seçim! +100 Puan')) ?>;
    const msgTryAgain = <?= json_encode(tt('home_interactive_try_again', '❌ Yanlış ton! Tekrar dene')) ?>;
    const msgTimeUp = <?= json_encode(tt('reason_timeup', '⏰ Süre doldu! Doğru renk gösteriliyor...')) ?>;
    const msgShowAnswer = <?= json_encode(tt('gameover_body', 'Doğru renk buydu! Yeni tur başlıyor...')) ?>;
    const msgTargetLabel = <?= json_encode(tt('home_interactive_target_label', 'HEDEF RENK')) ?>;
    const optionLabelPattern = <?= json_encode(tt('a11y_color_option', 'Color option {n}')) ?>;

    // --- Web Audio Synthesis (No external assets, zero lag) ---
    const SOUND_KEY = 'pm-sound-enabled';
    let soundEnabled = true;
    try {
      if (localStorage.getItem(SOUND_KEY) === '0') soundEnabled = false;
    } catch(e) {}

    function updateMuteBtnUI() {
      if (!muteIcon) return;
      muteIcon.textContent = soundEnabled ? '🔊' : '🔇';
      if (muteBtn) {
        muteBtn.classList.toggle('is-muted', !soundEnabled);
        muteBtn.setAttribute('aria-pressed', soundEnabled ? 'false' : 'true');
      }
    }
    updateMuteBtnUI();

    if (muteBtn) {
      muteBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        soundEnabled = !soundEnabled;
        try { localStorage.setItem(SOUND_KEY, soundEnabled ? '1' : '0'); } catch(e) {}
        updateMuteBtnUI();
        if (soundEnabled) ensureAudio();
      });
    }

    if (restartBtn) {
      restartBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        ensureAudio();
        score = 0;
        stage = 1;
        if (scoreEl) scoreEl.textContent = '0';
        if (stageEl) stageEl.textContent = '1';
        initRound();
      });
    }

    let audioCtx = null;
    function ensureAudio() {
      if (!soundEnabled) return;
      const Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) return;
      try {
        audioCtx = audioCtx || new Ctx();
        if (audioCtx.state === 'suspended') audioCtx.resume().catch(() => {});
      } catch(e) {}
    }

    function playTone(freq, duration = 0.12, type = 'sine', gain = 0.08, attack = 0.01, decay = 0.08) {
      if (!soundEnabled || !audioCtx) return;
      try {
        const osc = audioCtx.createOscillator();
        const g = audioCtx.createGain();
        osc.type = type;
        osc.frequency.value = freq;
        const now = audioCtx.currentTime;
        g.gain.setValueAtTime(0.0001, now);
        g.gain.exponentialRampToValueAtTime(gain, now + attack);
        g.gain.exponentialRampToValueAtTime(0.0001, now + attack + decay);
        osc.connect(g);
        g.connect(audioCtx.destination);
        osc.start(now);
        osc.stop(now + duration);
      } catch(e) {}
    }

    function playSound(type) {
      if (!soundEnabled) return;
      ensureAudio();
      if (!audioCtx) return;
      if (type === 'target') {
        playTone(520, 0.09, 'sine', 0.05);
      } else if (type === 'whoosh') {
        playTone(380, 0.08, 'sine', 0.04);
        setTimeout(() => playTone(540, 0.08, 'sine', 0.04), 40);
      } else if (type === 'correct') {
        playTone(660, 0.1, 'triangle', 0.09);
        setTimeout(() => playTone(880, 0.14, 'triangle', 0.08), 80);
      } else if (type === 'wrong') {
        playTone(220, 0.16, 'sawtooth', 0.06);
      }
    }

    // --- Helpers ---
    function hslToCss(h, s, l) {
      return `hsl(${Math.round(h)}, ${Math.round(s)}%, ${Math.round(l)}%)`;
    }

    function clearAllTimers() {
      timerIds.forEach(id => {
        clearTimeout(id);
        clearInterval(id);
      });
      timerIds = [];
    }

    function addTimeout(fn, ms) {
      const id = setTimeout(fn, ms);
      timerIds.push(id);
      return id;
    }

    function addInterval(fn, ms) {
      const id = setInterval(fn, ms);
      timerIds.push(id);
      return id;
    }

    // --- Core Round Lifecycle ---
    function initRound() {
      clearAllTimers();
      phase = 'memorize';
      wrongAttempts = 0;

      // Progressive duration: from 2200ms down to 1000ms as stage increases
      const targetShowMs = Math.max(1000, 2200 - (stage - 1) * 180);

      // Base target color
      const baseH = Math.floor(Math.random() * 360);
      const baseS = 68 + Math.floor(Math.random() * 22);
      const baseL = 46 + Math.floor(Math.random() * 18);
      currentTargetColor = hslToCss(baseH, baseS, baseL);

      // Progressive difficulty: angular sectors tighten at higher stages
      let baseAngles = [40, 80, 120, 160, 200, 240, 280, 320];
      if (stage >= 4) {
        baseAngles = [20, 40, 60, 80, -20, -40, -60, -80];
      } else if (stage >= 2) {
        baseAngles = [30, 60, 90, 120, 150, 180, 210, 240];
      }

      for (let i = baseAngles.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [baseAngles[i], baseAngles[j]] = [baseAngles[j], baseAngles[i]];
      }

      currentTiles = [{
        color: currentTargetColor,
        isCorrect: true
      }];

      const existingCss = new Set([currentTargetColor]);
      const maxJitter = stage >= 4 ? 6 : (stage >= 2 ? 10 : 16);

      for (let i = 0; i < 8; i++) {
        const jitter = Math.floor(Math.random() * (maxJitter * 2)) - maxJitter;
        const h = (baseH + baseAngles[i] + jitter + 360) % 360;
        const s = Math.max(50, Math.min(90, baseS + (Math.floor(Math.random() * 16) - 8)));
        const l = Math.max(40, Math.min(68, baseL + (Math.floor(Math.random() * 14) - 7)));
        const css = hslToCss(h, s, l);

        if (!existingCss.has(css)) {
          existingCss.add(css);
          currentTiles.push({ color: css, isCorrect: false });
        } else {
          const fallbackCss = hslToCss((h + 24) % 360, s, l);
          existingCss.add(fallbackCss);
          currentTiles.push({ color: fallbackCss, isCorrect: false });
        }
      }

      // Shuffle tiles
      for (let i = currentTiles.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [currentTiles[i], currentTiles[j]] = [currentTiles[j], currentTiles[i]];
      }

      // 1. Setup Target Swatch (VISIBLE)
      targetSwatch.className = 'pm-target-box';
      targetSwatch.style.backgroundColor = currentTargetColor;
      targetSwatch.innerHTML = '';
      targetSwatch.style.transform = 'scale(1.06)';
      addTimeout(() => { targetSwatch.style.transform = 'scale(1)'; }, 180);

      playSound('target');

      // 2. Setup Labels
      if (targetTag) {
        targetTag.innerHTML = `👀 <span>${msgRememberTag}</span>`;
      }
      feedback.textContent = msgRememberHint;
      feedback.style.color = 'var(--pm-text-muted, #94a3b8)';

      // 3. Grid: Placeholders / Covered tiles during memorization
      grid.innerHTML = '';
      for (let i = 0; i < 9; i++) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pm-mini-tile is-covered';
        btn.disabled = true;
        btn.setAttribute('aria-label', optionLabelPattern.replace('{n}', i + 1));
        grid.appendChild(btn);
      }

      // 4. Timer bar for memorization phase
      if (timerBar) {
        timerBar.className = 'pm-timer-bar';
        timerBar.style.width = '100%';
        const startTs = performance.now();
        const animInterval = addInterval(() => {
          const elapsed = performance.now() - startTs;
          const pct = Math.max(0, 100 - (elapsed / targetShowMs) * 100);
          timerBar.style.width = pct.toFixed(1) + '%';
          if (elapsed >= targetShowMs) {
            clearInterval(animInterval);
          }
        }, 30);
      }

      // 5. Schedule transition to Recall phase when targetShowMs ends
      addTimeout(startRecallPhase, targetShowMs);
    }

    function startRecallPhase() {
      phase = 'recall';
      recallStartTime = performance.now();
      const answerWindowMs = 5000;

      // 1. TARGET COLOR DISAPPEARS (Hidden with Mystery '?')
      targetSwatch.className = 'pm-target-box is-hidden';
      targetSwatch.style.backgroundColor = '';
      targetSwatch.innerHTML = '<span class="pm-target-mystery" aria-hidden="true">?</span>';

      playSound('whoosh');

      // 2. Update Banner and Feedback
      if (targetTag) {
        targetTag.innerHTML = `🎯 <span>${msgWhichColorTag}</span>`;
      }
      feedback.textContent = msgRecallHint;
      feedback.style.color = '#ff9966';

      // 3. Reveal the 9 color tiles in the grid with pop animation
      grid.innerHTML = '';
      currentTiles.forEach((tile, idx) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pm-mini-tile is-revealing';
        btn.style.animationDelay = (idx * 25) + 'ms';
        btn.style.backgroundColor = tile.color;
        btn.dataset.correct = tile.isCorrect ? 'true' : 'false';
        btn.setAttribute('aria-label', optionLabelPattern.replace('{n}', idx + 1));

        btn.addEventListener('click', function() {
          handleTileClick(btn, tile);
        });

        grid.appendChild(btn);
      });

      // 4. Answer countdown progress bar (5s)
      if (timerBar) {
        timerBar.className = 'pm-timer-bar recall';
        timerBar.style.width = '100%';
        const recallStartTs = performance.now();
        const animInterval = addInterval(() => {
          const elapsed = performance.now() - recallStartTs;
          const pct = Math.max(0, 100 - (elapsed / answerWindowMs) * 100);
          timerBar.style.width = pct.toFixed(1) + '%';
          if (elapsed >= answerWindowMs) {
            clearInterval(animInterval);
          }
        }, 40);
      }

      // 5. Time up expiration
      addTimeout(onTimeUp, answerWindowMs);
    }

    function handleTileClick(btn, tile) {
      if (phase !== 'recall') return;
      ensureAudio();

      if (tile.isCorrect) {
        // --- CORRECT MATCH ---
        phase = 'result';
        clearAllTimers();

        btn.classList.add('is-correct');

        // Reveal target in swatch with checkmark
        targetSwatch.className = 'pm-target-box is-revealed';
        targetSwatch.style.backgroundColor = currentTargetColor;
        targetSwatch.innerHTML = '<span class="pm-target-revealed-icon">✓</span>';

        playSound('correct');

        // Speed bonus calculation
        const elapsed = performance.now() - recallStartTime;
        const speedBonus = Math.max(0, Math.round((5000 - elapsed) / 100));
        const pts = 100 + speedBonus;
        score += pts;
        stage += 1;

        if (scoreEl) scoreEl.textContent = score;
        if (stageEl) stageEl.textContent = stage;

        feedback.textContent = msgPerfect.replace('+100', `+${pts}`);
        feedback.style.color = '#10b981';

        // Disable all tiles
        const allBtns = grid.querySelectorAll('button');
        allBtns.forEach(b => b.disabled = true);

        // Advance to next round smoothly
        addTimeout(initRound, 850);

      } else {
        // --- WRONG SELECTION ---
        btn.classList.add('shake');
        btn.style.opacity = '0.35';
        btn.disabled = true;

        playSound('wrong');
        wrongAttempts += 1;

        feedback.textContent = msgTryAgain;
        feedback.style.color = '#ef4444';

        if (wrongAttempts >= 2) {
          // If 2 mistakes: reveal target and reset round
          phase = 'result';
          clearAllTimers();

          targetSwatch.className = 'pm-target-box';
          targetSwatch.style.backgroundColor = currentTargetColor;
          targetSwatch.innerHTML = '<span class="pm-target-revealed-icon" style="color:#f59e0b;">!</span>';

          feedback.textContent = msgShowAnswer;
          feedback.style.color = '#f59e0b';

          const allBtns = grid.querySelectorAll('button');
          allBtns.forEach(b => b.disabled = true);

          addTimeout(initRound, 1400);
        }
      }
    }

    function onTimeUp() {
      if (phase !== 'recall') return;
      phase = 'result';
      clearAllTimers();

      playSound('wrong');

      targetSwatch.className = 'pm-target-box';
      targetSwatch.style.backgroundColor = currentTargetColor;
      targetSwatch.innerHTML = '<span class="pm-target-revealed-icon" style="color:#ef4444;">⏰</span>';

      feedback.textContent = msgTimeUp;
      feedback.style.color = '#ef4444';

      const allBtns = grid.querySelectorAll('button');
      allBtns.forEach(b => b.disabled = true);

      addTimeout(initRound, 1400);
    }

    // Start game
    initRound();
  })();
  </script>
</body>
</html>
