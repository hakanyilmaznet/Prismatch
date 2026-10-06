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

$faqItems = [
  [
    'q' => tt('faq_q1', 'Prismatch nedir?'),
    'a' => tt('faq_a1', 'Prismatch, ekranda kısa bir süre gösterilen hedef rengi veya ülke bayrağını zihninizde tutarak renk ızgarası içinden doğru tonu en hızlı şekilde seçtiğiniz, odak ve görsel hafızayı güçlendiren ücretsiz bir bulmaca oyunudur.'),
  ],
  [
    'q' => tt('faq_q2', 'Prismatch nasıl oynanır?'),
    'a' => tt('faq_a2', 'Her turun başında hedef bir renk veya bayrak gösterilir. Geri sayım bittiğinde benzer tonlardan oluşan bir ızgara açılır. Doğru rengi ne kadar hızlı bulursanız o kadar yüksek puan kazanırsınız.'),
  ],
  [
    'q' => tt('faq_q_teams', 'Takım Savaşı (Devs vs QAs) modu nasıl çalışır?'),
    'a' => tt('faq_a_teams', 'Takım Savaşı modunda oyuncular lobide 🔴 Kırmızı (Devs) ve 🔵 Mavi (QAs) takımlarına ayrılır. Her oyuncunun topladığı puan doğrudan takımının ortak havuzuna eklenir. Oyun ekranındaki canlı halat çekme (tug-of-war) barı anlık skoru gösterir ve maç sonunda en çok puanı toplayan takım zaferi kazanır!'),
  ],
  [
    'q' => tt('faq_q_sabotage', 'Jokerler ve Mürekkep Sabotajı nasıl kullanılır?'),
    'a' => tt('faq_a_sabotage', 'Çok oyunculu odalarda 5 tur üst üste doğru cevap vererek 50/50 jokeri ve Mürekkep Sabotajı kartlarını açabilirsiniz! 50/50 jokeri iki yanlış seçeneği eler; Mürekkep Sabotajı ise seçtiğiniz rakibin ekranına mürekkep lekeleri fırlatarak dikkatini dağıtır. Kullanıldıktan sonra her 5 doğru serisinde yenilenir!'),
  ],
  [
    'q' => tt('faq_q_awards', "Maçın En'leri rozetleri (Hız Şeytanı, Aşırı Düşünen vb.) nedir?"),
    'a' => tt('faq_a_awards', 'Maç bittiğinde sadece birinciye değil, farklı oyun stillerine göre mizahi ödüller dağıtılır: Işık hızında karar veren "Hız Şeytanı ⚡", son ana kadar bekleyen "Aşırı Düşünen 🧘", seri yakalayan "Alev Alan 🔥" ve maçın yıldızı "👑 Maçın MVP\'si" unvanını alır.'),
  ],
  [
    'q' => tt('faq_q3', 'Prismatch oynamak ücretsiz mi?'),
    'a' => tt('faq_a3', 'Evet! Prismatch web tarayıcınız üzerinden tamamen ücretsiz oynanabilir. İster misafir olarak anında oynayabilir, isterseniz Google hesabınızla giriş yaparak skorlarınızı ve rekorlarınızı liderlik tablosuna kaydedebilirsiniz.'),
  ],
  [
    'q' => tt('faq_q4', 'Çok oyunculu odalar (Multiplayer) nasıl çalışır?'),
    'a' => tt('faq_a4', 'Çok oyunculu modda kendi genel veya gizli odanızı kurabilir ya da açık odalara katılabilirsiniz. Arkadaşlarınıza davet linki gönderip Takım Savaşı, Eleme veya Puan modunda canlı yarışabilirsiniz.'),
  ],
  [
    'q' => tt('faq_q5', 'Günlük Meydan Okuma (Daily Challenge) nedir?'),
    'a' => tt('faq_a5', 'Günde bir kez oynanabilen özel bir yarışmadır. Tüm dünyadaki oyuncular aynı soru kombinasyonuyla yarışır ve günün en iyileri küresel Günlük Liderlik Tablosunda yerini alır.'),
  ],
];

$howToData = [
  'name' => tt('home_how_section_title', 'Prismatch Nasıl Oynanır?'),
  'description' => tt('home_how_section_desc', '3 basit adımda renk hafızanızı test edin ve geliştirin.'),
  'steps' => [
    [
      'name' => tt('home_step_1_title', '1. Hedef Rengi Gör'),
      'text' => tt('home_step_1_desc', 'Her tur başında ekranda birkaç saniyeliğine beliren hedef rengin tonunu ve parlaklığını dikkatlice inceleyin.'),
    ],
    [
      'name' => tt('home_step_2_title', '2. Zihninde Tut'),
      'text' => tt('home_step_2_desc', 'Hedef renk kaybolduğunda ve seçenekler gelene kadar rengin zihninizdeki canlılığını koruyun.'),
    ],
    [
      'name' => tt('home_step_3_title', '3. Doğru Tonu Yakala'),
      'text' => tt('home_step_3_desc', 'Birbirine benzeyen renk seçenekleri arasından doğru tonu seçin. Hızlı seçim ekstra bonus puan kazandırır.'),
    ],
  ],
];
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
    'faq' => $faqItems,
    'howTo' => $howToData,
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

    /* FAQ Section */
    .pm-faq-item[open] {
      border-color: rgba(255, 107, 91, 0.4) !important;
      background: var(--pm-bg-elevated, rgba(255, 255, 255, 0.09)) !important;
    }
    .pm-faq-item[open] .pm-faq-icon {
      transform: rotate(180deg);
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
    /* ==========================================================================
       NEW SQUAD BATTLE & MULTIPLAYER FUN SHOWCASE STYLES
       ========================================================================== */
    .pm-btn-rooms-glow {
      background: linear-gradient(135deg, #f43f5e, #fb7185 50%, #38bdf8) !important;
      color: #ffffff !important;
      border: none !important;
      box-shadow: 0 8px 30px rgba(244, 63, 94, 0.45) !important;
      padding: 13px 26px !important;
      font-size: 16px !important;
      font-weight: 800 !important;
      border-radius: 999px !important;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      text-decoration: none;
      transition: transform 0.15s ease, box-shadow 0.15s ease;
      position: relative;
    }
    .pm-btn-rooms-glow:hover {
      transform: translateY(-2px) scale(1.03);
      box-shadow: 0 14px 40px rgba(244, 63, 94, 0.65) !important;
      color: #fff !important;
    }

    .pm-features-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 20px;
    }
    @media (min-width: 768px) {
      .pm-features-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }
    .pm-fun-card {
      background: var(--pm-bg-card, #121826);
      border: 1px solid var(--pm-border, rgba(255, 255, 255, 0.12));
      border-radius: 24px;
      padding: 26px 24px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      gap: 16px;
      position: relative;
      overflow: hidden;
      transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
      box-shadow: 0 12px 32px rgba(0, 0, 0, 0.2);
    }
    .pm-fun-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 20px 48px rgba(0, 0, 0, 0.35);
    }
    .pm-fun-card.card-teams {
      background: linear-gradient(145deg, rgba(244, 63, 94, 0.12), rgba(56, 189, 248, 0.08)), var(--pm-bg-card, #121826);
      border-color: rgba(244, 63, 94, 0.35);
    }
    .pm-fun-card.card-teams:hover {
      border-color: rgba(244, 63, 94, 0.7);
      box-shadow: 0 20px 48px rgba(244, 63, 94, 0.25);
    }
    .pm-fun-card.card-sabotage {
      background: linear-gradient(145deg, rgba(168, 85, 247, 0.14), rgba(236, 72, 153, 0.08)), var(--pm-bg-card, #121826);
      border-color: rgba(168, 85, 247, 0.35);
    }
    .pm-fun-card.card-sabotage:hover {
      border-color: rgba(168, 85, 247, 0.7);
      box-shadow: 0 20px 48px rgba(168, 85, 247, 0.25);
    }
    .pm-fun-card.card-reactions {
      background: linear-gradient(145deg, rgba(245, 158, 11, 0.12), rgba(239, 68, 68, 0.08)), var(--pm-bg-card, #121826);
      border-color: rgba(245, 158, 11, 0.35);
    }
    .pm-fun-card.card-reactions:hover {
      border-color: rgba(245, 158, 11, 0.7);
      box-shadow: 0 20px 48px rgba(245, 158, 11, 0.25);
    }
    .pm-fun-card.card-awards {
      background: linear-gradient(145deg, rgba(16, 185, 129, 0.12), rgba(59, 130, 246, 0.08)), var(--pm-bg-card, #121826);
      border-color: rgba(16, 185, 129, 0.35);
    }
    .pm-fun-card.card-awards:hover {
      border-color: rgba(16, 185, 129, 0.7);
      box-shadow: 0 20px 48px rgba(16, 185, 129, 0.25);
    }

    .pm-card-top-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 5px 12px;
      border-radius: 999px;
      font-size: 11.5px;
      font-weight: 800;
      letter-spacing: 0.4px;
      text-transform: uppercase;
      margin-bottom: 8px;
    }
    .pill-teams { background: rgba(244, 63, 94, 0.18); color: #fb7185; border: 1px solid rgba(244, 63, 94, 0.4); }
    .pill-sabotage { background: rgba(168, 85, 247, 0.18); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.4); }
    .pill-reactions { background: rgba(245, 158, 11, 0.18); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.4); }
    .pill-awards { background: rgba(16, 185, 129, 0.18); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.4); }

    .pm-fun-title {
      font-family: var(--pm-font-display, "Baloo 2", sans-serif);
      font-size: 22px;
      font-weight: 800;
      margin: 0 0 6px 0;
      line-height: 1.25;
    }
    .pm-fun-desc {
      font-size: 14px;
      color: var(--pm-text-muted, #94a3b8);
      line-height: 1.55;
      margin: 0 0 14px 0;
    }

    /* Tug of war interactive mini visual */
    .pm-tug-visual {
      background: rgba(0, 0, 0, 0.35);
      border: 1px solid var(--pm-border, rgba(255, 255, 255, 0.1));
      border-radius: 16px;
      padding: 12px 14px;
      display: flex;
      flex-direction: column;
      gap: 8px;
      margin-bottom: 8px;
    }
    .pm-tug-labels {
      display: flex;
      justify-content: space-between;
      font-size: 12px;
      font-weight: 800;
    }
    .pm-tug-bar {
      height: 10px;
      background: rgba(0, 0, 0, 0.4);
      border-radius: 999px;
      overflow: hidden;
      display: flex;
    }
    .pm-tug-red {
      width: 55%;
      height: 100%;
      background: linear-gradient(90deg, #f43f5e, #fb7185);
      animation: tugPulseRed 3.6s infinite ease-in-out;
    }
    .pm-tug-blue {
      width: 45%;
      height: 100%;
      background: linear-gradient(90deg, #38bdf8, #0284c7);
      animation: tugPulseBlue 3.6s infinite ease-in-out;
    }
    @keyframes tugPulseRed {
      0%, 100% { width: 55%; }
      50% { width: 64%; }
    }
    @keyframes tugPulseBlue {
      0%, 100% { width: 45%; }
      50% { width: 36%; }
    }

    /* Sabotage visual */
    .pm-sabotage-visual {
      background: rgba(0, 0, 0, 0.35);
      border: 1px solid var(--pm-border, rgba(255, 255, 255, 0.1));
      border-radius: 16px;
      padding: 12px 14px;
      display: flex;
      align-items: center;
      justify-content: space-around;
      gap: 10px;
      margin-bottom: 8px;
    }
    .pm-powerup-mini-badge {
      display: flex;
      align-items: center;
      gap: 8px;
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.15);
      border-radius: 12px;
      padding: 8px 12px;
      font-size: 13px;
      font-weight: 700;
    }

    /* Reactions visual with clickable preview pills */
    .pm-reactions-visual {
      background: rgba(0, 0, 0, 0.35);
      border: 1px solid var(--pm-border, rgba(255, 255, 255, 0.1));
      border-radius: 16px;
      padding: 12px 14px;
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
      margin-bottom: 8px;
    }
    .pm-reaction-pill-btn {
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.16);
      border-radius: 999px;
      padding: 6px 12px;
      color: inherit;
      font-size: 12px;
      font-weight: 700;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.15s ease;
      user-select: none;
    }
    .pm-reaction-pill-btn:hover {
      background: rgba(255, 255, 255, 0.2);
      transform: translateY(-2px) scale(1.04);
      border-color: rgba(255, 255, 255, 0.35);
    }
    .pm-reaction-pill-btn:active {
      transform: scale(0.96);
    }

    /* Awards mini cards */
    .pm-awards-visual {
      background: rgba(0, 0, 0, 0.35);
      border: 1px solid var(--pm-border, rgba(255, 255, 255, 0.1));
      border-radius: 16px;
      padding: 10px 12px;
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 8px;
      margin-bottom: 8px;
    }
    .pm-award-mini-chip {
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 10px;
      padding: 6px 10px;
      display: flex;
      align-items: center;
      gap: 7px;
      font-size: 11.5px;
      font-weight: 700;
    }

    /* Floating feedback toast for homepage */
    .pm-home-toast {
      position: fixed;
      bottom: 24px;
      left: 50%;
      transform: translateX(-50%) translateY(30px);
      background: rgba(18, 24, 38, 0.95);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 999px;
      padding: 10px 22px;
      color: #fff;
      font-size: 14px;
      font-weight: 700;
      box-shadow: 0 12px 36px rgba(0, 0, 0, 0.5);
      opacity: 0;
      pointer-events: none;
      transition: all 0.25s ease;
      z-index: 9999;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .pm-home-toast.show {
      transform: translateX(-50%) translateY(0);
      opacity: 1;
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
          <span>🔥</span>
          <span><?= htmlspecialchars(tt('home_hero_new_tag', 'YENİ: Takım Savaşı · Mürekkep Sabotajı · Canlı Tepkiler')) ?></span>
        </div>
        <h1 class="pm-hero-title">
          <?= htmlspecialchars(tt('home_hero_title_part1', 'Remember the Colors,')) ?> <span class="prism-shimmer"><?= htmlspecialchars(tt('home_hero_title_squad', 'Battle Your Squad!')) ?></span>
        </h1>
        <p class="pm-hero-desc">
          <?= htmlspecialchars(tt('home_hero_subtitle_fun', 'Kahve molalarını, ekip toplantılarını ve arkadaş buluşmalarını yüksek tempolu bir rekabete dönüştürün. Hedef rengi aklında tut, rakibin ekranına mürekkep fırlat, takımını zafere taşı!')) ?>
        </p>

        <div class="pm-hero-actions">
          <a class="pm-btn-rooms-glow" href="rooms.php">
            <span class="pulse-dot" style="background:#fff; box-shadow:0 0 0 0 rgba(255,255,255,0.7)"></span>
            <span>⚔️ <?= htmlspecialchars(tt('home_hero_rooms_btn_featured', 'Oda Kur & Takımını Topla')) ?></span>
          </a>
          <a class="pm-btn-secondary" href="play.php">
            <img class="bi-icon" src="bootstrap-icons/play-fill.svg" alt="" aria-hidden="true" style="width:18px;height:18px;" />
            <span><?= htmlspecialchars(tt('home_hero_play_btn_solo', 'Tek Başına Oyna')) ?></span>
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
          <div class="pm-chip" style="border-color: rgba(244, 63, 94, 0.4); color: #fb7185;">
            <span>⚔️</span>
            <span><?= htmlspecialchars(tt('rooms_mode_teams_short', 'Takım Savaşı (Devs vs QAs)')) ?></span>
          </div>
          <div class="pm-chip" style="border-color: rgba(168, 85, 247, 0.4); color: #c084fc;">
            <span>🦑</span>
            <span><?= htmlspecialchars(tt('home_chip_sabotage', 'Mürekkep Sabotajı')) ?></span>
          </div>
          <div class="pm-chip" style="border-color: rgba(245, 158, 11, 0.4); color: #fbbf24;">
            <span>🐞</span>
            <span><?= htmlspecialchars(tt('home_chip_banter', 'Canlı "Bug var!" Tepkileri')) ?></span>
          </div>
          <div class="pm-chip" style="border-color: rgba(16, 185, 129, 0.4); color: #34d399;">
            <span>👑</span>
            <span><?= htmlspecialchars(tt('home_chip_mvp', 'Maçın MVP\'si & Rozetler')) ?></span>
          </div>
          <div class="pm-chip">
            <span>🚩</span>
            <span><?= htmlspecialchars(tt('rooms_mode_flags_short', 'Bayrak Modu')) ?></span>
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

    <!-- 2. FEATURE SPOTLIGHT: SQUAD BATTLE & MULTIPLAYER FUN -->
    <section>
      <div class="pm-section-head">
        <div class="pm-card-top-pill pill-teams" style="margin-bottom:12px;">
          <span>🔥</span> <?= htmlspecialchars(tt('home_spotlight_badge', 'OFİS & EKİP EĞLENCESİ')) ?>
        </div>
        <h2 class="pm-section-title"><?= htmlspecialchars(tt('home_spotlight_title', 'Kahve Molasını Arenaya Dönüştürün!')) ?></h2>
        <p class="pm-section-sub"><?= htmlspecialchars(tt('home_spotlight_sub', 'Monoton toplantı aralarını, sprint kutlamalarını ve arkadaş buluşmalarını kahkaha dolu canlı bir rekabete çevirin.')) ?></p>
      </div>

      <div class="pm-features-grid">
        <!-- Feature 1: Squad Battle / Devs vs QAs -->
        <div class="pm-fun-card card-teams">
          <div>
            <div class="pm-card-top-pill pill-teams">
              <span>⚔️</span> <?= htmlspecialchars(tt('home_feat_teams_pill', 'Takım Savaşı: Devs vs QAs')) ?>
            </div>
            <h3 class="pm-fun-title"><?= htmlspecialchars(tt('home_feat_teams_title', '🔴 Kırmızı vs 🔵 Mavi Takım')) ?></h3>
            <p class="pm-fun-desc"><?= htmlspecialchars(tt('home_feat_teams_desc', 'Ekibini ikiye böl, takımını seç! Bireysel puanların ortak takım havuzuna aktığı bu modda kıyasıya halat çekin, kazanan takım kutlamasını yapın.')) ?></p>
            
            <div class="pm-tug-visual">
              <div class="pm-tug-labels">
                <span style="color:#f43f5e">🔴 Devs: 4.850 pts</span>
                <span style="color:#38bdf8">QAs: 5.120 pts 🔵</span>
              </div>
              <div class="pm-tug-bar">
                <div class="pm-tug-red"></div>
                <div class="pm-tug-blue"></div>
              </div>
            </div>
          </div>
          <a class="pm-btn-secondary" href="rooms.php" style="justify-content:center; width:100%; box-sizing:border-box; border-color:rgba(244,63,94,0.4);">
            <span>⚔️ <?= htmlspecialchars(tt('home_feat_teams_btn', 'Takım Savaşı Başlat')) ?> →</span>
          </a>
        </div>

        <!-- Feature 2: Ink Sabotage & 50/50 -->
        <div class="pm-fun-card card-sabotage">
          <div>
            <div class="pm-card-top-pill pill-sabotage">
              <span>🦑</span> <?= htmlspecialchars(tt('home_feat_sabotage_pill', 'Jokerler & Sabotaj Kartları')) ?>
            </div>
            <h3 class="pm-fun-title"><?= htmlspecialchars(tt('home_feat_sabotage_title', 'Ekranı Mürekkeple Kapla!')) ?></h3>
            <p class="pm-fun-desc"><?= htmlspecialchars(tt('home_feat_sabotage_desc', 'Liderliği kimseye kaptırma! Öndeki rakibin ekranına 3 saniye mürekkep fırlatarak dikkatini dağıt veya 50/50 jokeriyle iki yanlış rengi anında haritadan sil.')) ?></p>
            
            <div class="pm-sabotage-visual">
              <div class="pm-powerup-mini-badge" style="border-color:rgba(168,85,247,0.4); color:#c084fc;">
                <span>🎯</span>
                <span><?= htmlspecialchars(tt('home_powerup_5050', '50/50 Jokeri')) ?></span>
              </div>
              <span style="font-size:18px; opacity:0.4;">⚡</span>
              <div class="pm-powerup-mini-badge" style="border-color:rgba(236,72,153,0.4); color:#f472b6;">
                <span>🦑</span>
                <span><?= htmlspecialchars(tt('home_powerup_ink', 'Mürekkep Fırlat')) ?></span>
              </div>
            </div>
          </div>
          <a class="pm-btn-secondary" href="rooms.php" style="justify-content:center; width:100%; box-sizing:border-box; border-color:rgba(168,85,247,0.4);">
            <span>🦑 <?= htmlspecialchars(tt('home_feat_sabotage_btn', 'Sabotajları Keşfet')) ?> →</span>
          </a>
        </div>

        <!-- Feature 3: Live Audio & Banter -->
        <div class="pm-fun-card card-reactions">
          <div>
            <div class="pm-card-top-pill pill-reactions">
              <span>🔥</span> <?= htmlspecialchars(tt('home_feat_reactions_pill', 'Sesli Tepkiler & Çevik Sloganlar')) ?>
            </div>
            <h3 class="pm-fun-title"><?= htmlspecialchars(tt('home_feat_reactions_title', '"Bug var! 🐞" & "PO Haklı! 👑"')) ?></h3>
            <p class="pm-fun-desc"><?= htmlspecialchars(tt('home_feat_reactions_desc', 'Oyun sırasında uçuşan canlı emojiler ve retro synth sesleriyle odayı karnavala çevirin. Aşağıdaki butonlara tıklayarak canlı tepkileri test edin:')) ?></p>
            
            <div class="pm-reactions-visual">
              <button type="button" class="pm-reaction-pill-btn" data-sound="bug" data-emoji="🐞" data-text="<?= htmlspecialchars(tt('home_reaction_bug', '🐞 Bug var!')) ?>">
                <span><?= htmlspecialchars(tt('home_reaction_bug', '🐞 Bug var!')) ?></span>
              </button>
              <button type="button" class="pm-reaction-pill-btn" data-sound="po" data-emoji="👑" data-text="<?= htmlspecialchars(tt('home_reaction_po', '👑 PO Haklı!')) ?>">
                <span><?= htmlspecialchars(tt('home_reaction_po', '👑 PO Haklı!')) ?></span>
              </button>
              <button type="button" class="pm-reaction-pill-btn" data-sound="fire" data-emoji="🔥" data-text="<?= htmlspecialchars(tt('home_reaction_fire_text', 'Alev! 🔥')) ?>">
                <span>🔥</span>
              </button>
              <button type="button" class="pm-reaction-pill-btn" data-sound="laugh" data-emoji="😂" data-text="<?= htmlspecialchars(tt('home_reaction_laugh_text', 'Haha! 😂')) ?>">
                <span>😂</span>
              </button>
              <button type="button" class="pm-reaction-pill-btn" data-sound="party" data-emoji="🎉" data-text="<?= htmlspecialchars(tt('home_reaction_gg_text', 'GG! 🏆')) ?>">
                <span><?= htmlspecialchars(tt('home_reaction_gg_text', '🏆 GG!')) ?></span>
              </button>
            </div>
          </div>
          <a class="pm-btn-secondary" href="rooms.php" style="justify-content:center; width:100%; box-sizing:border-box; border-color:rgba(245,158,11,0.4);">
            <span>💬 <?= htmlspecialchars(tt('home_feat_reactions_btn', 'Canlı Odalara Katıl')) ?> →</span>
          </a>
        </div>

        <!-- Feature 4: Humorous Accolades & MVP -->
        <div class="pm-fun-card card-awards">
          <div>
            <div class="pm-card-top-pill pill-awards">
              <span>🏅</span> <?= htmlspecialchars(tt('home_feat_awards_pill', 'Mizahi Rozetler & Podyum')) ?>
            </div>
            <h3 class="pm-fun-title"><?= htmlspecialchars(tt('home_feat_awards_title', "Maçın En'leri & MVP Unvanı")) ?></h3>
            <p class="pm-fun-desc"><?= htmlspecialchars(tt('home_feat_awards_desc', 'Sadece birinci değil; en hızlı karar veren, en çok hesap yapan filozof veya son anda tutturan cambaz da maç sonunda kendi unvanıyla onurlandırılır.')) ?></p>
            
            <div class="pm-awards-visual">
              <div class="pm-award-mini-chip">
                <span>⚡</span>
                <span><?= htmlspecialchars(tt('award_speed_demon_title', 'Hız Şeytanı')) ?></span>
              </div>
              <div class="pm-award-mini-chip">
                <span>🧘</span>
                <span><?= htmlspecialchars(tt('award_overthinker_title', 'Aşırı Düşünen')) ?></span>
              </div>
              <div class="pm-award-mini-chip">
                <span>🐢</span>
                <span><?= htmlspecialchars(tt('award_clutch_title', 'Son Saniye')) ?></span>
              </div>
              <div class="pm-award-mini-chip">
                <span>👑</span>
                <span><?= htmlspecialchars(tt('award_mvp_title', "Maçın MVP'si")) ?></span>
              </div>
            </div>
          </div>
          <a class="pm-btn-secondary" href="rooms.php" style="justify-content:center; width:100%; box-sizing:border-box; border-color:rgba(16,185,129,0.4);">
            <span>🏅 <?= htmlspecialchars(tt('home_feat_awards_btn', 'Rozetleri İncele')) ?> →</span>
          </a>
        </div>
      </div>
    </section>

    <!-- 3. GAME MODES SHOWCASE -->
    <section>
      <div class="pm-section-head">
        <h2 class="pm-section-title"><?= htmlspecialchars(tt('home_modes_section_title', 'Heyecan Dolu Oyun Modları')) ?></h2>
        <p class="pm-section-sub"><?= htmlspecialchars(tt('home_modes_section_subtitle', 'İster tek başına rekor kır, ister arkadaşlarınla canlı odalarda yarış veya günlük turnuvaya katıl.')) ?></p>
      </div>

      <div class="pm-cards-grid">
        <!-- Mode 1: Squad Battle -->
        <div class="pm-mode-card pm-mode-multi" style="border-color: rgba(244, 63, 94, 0.4);">
          <div>
            <div style="display:flex; align-items:center; justify-content:space-between;">
              <div class="pm-mode-icon" style="background: linear-gradient(135deg, rgba(244,63,94,0.2), rgba(56,189,248,0.2));">⚔️</div>
              <span class="pm-mode-pill pm-pill-multi" style="background: linear-gradient(90deg, #f43f5e, #38bdf8); color:#fff; border:none;"><?= htmlspecialchars(tt('badge_teams_pop', 'TAKIM SAVAŞI')) ?></span>
            </div>
            <h3 class="pm-mode-title"><?= htmlspecialchars(tt('rooms_mode_teams_title', 'Takım Savaşı (Devs vs QAs)')) ?></h3>
            <p class="pm-mode-desc"><?= htmlspecialchars(tt('rooms_mode_teams_desc', '🔴 Kırmızı vs 🔵 Mavi Takım! Bireysel puanlar takım havuzuna yazılır, en çok puanı toplayan takım kazanır.')) ?></p>
          </div>
          <a class="pm-btn-rooms-glow" href="rooms.php" style="justify-content:center; width:100%; box-sizing:border-box; padding:10px 18px !important; font-size:14px !important;">
            <span>⚔️ <?= htmlspecialchars(tt('home_mode_teams_btn', 'Takım Odası Kur')) ?> →</span>
          </a>
        </div>

        <!-- Mode 2: Elimination -->
        <div class="pm-mode-card pm-mode-multi" style="border-color: rgba(239, 68, 68, 0.35);">
          <div>
            <div style="display:flex; align-items:center; justify-content:space-between;">
              <div class="pm-mode-icon" style="background: rgba(239,68,68,0.15);">💀</div>
              <span class="pm-mode-pill pm-pill-multi" style="background: rgba(239,68,68,0.2); color:#f87171; border: 1px solid rgba(239,68,68,0.3);"><?= htmlspecialchars(tt('badge_elim_short', 'ELEME')) ?></span>
            </div>
            <h3 class="pm-mode-title"><?= htmlspecialchars(tt('rooms_mode_elim_title', 'Hayatta Kalma (Eleme)')) ?></h3>
            <p class="pm-mode-desc"><?= htmlspecialchars(tt('rooms_mode_elim_desc', 'Hata kabul etmez! Yanlış rengi seçen oyuncu anında elenir. Son ayakta kalan kupayı kaldırır.')) ?></p>
          </div>
          <a class="pm-btn-secondary" href="rooms.php" style="justify-content:center; width:100%; box-sizing:border-box;">
            <span>💀 <?= htmlspecialchars(tt('home_mode_elim_btn', 'Eleme Odası Aç')) ?> →</span>
          </a>
        </div>

        <!-- Mode 3: Points Race -->
        <div class="pm-mode-card pm-mode-multi" style="border-color: rgba(245, 158, 11, 0.35);">
          <div>
            <div style="display:flex; align-items:center; justify-content:space-between;">
              <div class="pm-mode-icon" style="background: rgba(245,158,11,0.15);">⚡</div>
              <span class="pm-mode-pill pm-pill-multi" style="background: rgba(245,158,11,0.2); color:#fbbf24; border: 1px solid rgba(245,158,11,0.3);"><?= htmlspecialchars(tt('badge_points_race', 'PUAN YARIŞI')) ?></span>
            </div>
            <h3 class="pm-mode-title"><?= htmlspecialchars(tt('rooms_mode_points_title', 'Puan Maratonu')) ?></h3>
            <p class="pm-mode-desc"><?= htmlspecialchars(tt('rooms_mode_points_desc', 'Kimse elenmez! 25 tur boyunca ardışık kombo serileri ve hızlı seçimlerle en yüksek puanı toplayan kazanır.')) ?></p>
          </div>
          <a class="pm-btn-secondary" href="rooms.php" style="justify-content:center; width:100%; box-sizing:border-box;">
            <span>⚡ <?= htmlspecialchars(tt('home_mode_points_btn', 'Maraton Başlat')) ?> →</span>
          </a>
        </div>

        <!-- Mode 4: Flags Memory -->
        <div class="pm-mode-card pm-mode-multi" style="border-color: rgba(56, 189, 248, 0.35);">
          <div>
            <div style="display:flex; align-items:center; justify-content:space-between;">
              <div class="pm-mode-icon" style="background: rgba(56,189,248,0.15);">🚩</div>
              <span class="pm-mode-pill pm-pill-multi" style="background: rgba(56,189,248,0.2); color:#38bdf8; border: 1px solid rgba(56,189,248,0.3);"><?= htmlspecialchars(tt('badge_flags_world', '250+ BAYRAK')) ?></span>
            </div>
            <h3 class="pm-mode-title"><?= htmlspecialchars(tt('rooms_mode_flags_title', 'Dünya Bayrakları Hafızası')) ?></h3>
            <p class="pm-mode-desc"><?= htmlspecialchars(tt('rooms_mode_flags_desc', 'Renklerin ötesine geçin! Ekranda beliren dünya bayraklarını zihninizde tutun, coğrafya bilginizi test edin.')) ?></p>
          </div>
          <a class="pm-btn-secondary" href="rooms.php" style="justify-content:center; width:100%; box-sizing:border-box;">
            <span>🚩 <?= htmlspecialchars(tt('home_mode_flags_btn', 'Bayraklarla Yarış')) ?> →</span>
          </a>
        </div>
      </div>

      <!-- Quick Solo & Daily Teaser Bar -->
      <div style="margin-top:20px; background:var(--pm-bg-elevated, rgba(255,255,255,0.04)); border:1px dashed var(--pm-border, rgba(255,255,255,0.14)); border-radius:18px; padding:16px 20px; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px;">
        <div style="display:flex; align-items:center; gap:10px;">
          <span style="font-size:22px;">🎯</span>
          <div>
            <strong style="font-size:14.5px;"><?= htmlspecialchars(tt('home_solo_teaser_title', 'Tek Başına Pratik Yapmak veya Günün Turnuvasına Katılmak mı İstiyorsun?')) ?></strong>
            <div style="font-size:13px; color:var(--pm-text-muted);"><?= htmlspecialchars(tt('home_solo_teaser_desc', '60 saniyelik Hızlı Tur veya günde 1 deneme hakkı olan küresel Günlük Meydan Okuma seni bekliyor.')) ?></div>
          </div>
        </div>
        <div style="display:flex; gap:10px;">
          <a class="pm-btn-secondary" href="play.php" style="padding:8px 16px; font-size:13.5px;">⚡ <?= htmlspecialchars(tt('home_hero_play_btn_solo', 'Tek Başına Oyna')) ?></a>
          <a class="pm-btn-secondary" href="play.php?daily=1" style="padding:8px 16px; font-size:13.5px;">🎯 <?= htmlspecialchars(tt('home_hero_daily_btn', 'Günlük Turnuva')) ?></a>
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

    <!-- 6. FREQUENTLY ASKED QUESTIONS (FAQ) -->
    <section class="pm-section pm-faq-section" aria-labelledby="faqSectionTitle">
      <div class="pm-section-head">
        <div class="pm-section-pill">💡 <?= htmlspecialchars(tt('faq_badge', 'Rehber & Bilgi')) ?></div>
        <h2 id="faqSectionTitle" class="pm-section-title"><?= htmlspecialchars(tt('faq_title', 'Sıkça Sorulan Sorular')) ?></h2>
        <p class="pm-section-desc"><?= htmlspecialchars(tt('faq_desc', 'Prismatch, oyun modları ve kurallar hakkında merak edilenler.')) ?></p>
      </div>
      <div class="pm-faq-list" style="display:flex; flex-direction:column; gap:14px; max-width:860px; margin:0 auto; width:100%;">
        <?php foreach ($faqItems as $idx => $faq): ?>
          <details class="pm-faq-item" style="background:var(--pm-bg-card, rgba(255,255,255,0.06)); border:1px solid var(--pm-border, rgba(255,255,255,0.1)); border-radius:16px; padding:18px 22px; cursor:pointer; transition:all 0.2s ease;">
            <summary style="font-weight:700; font-size:16px; list-style:none; display:flex; justify-content:space-between; align-items:center; user-select:none; gap:12px;">
              <span>❓ <?= htmlspecialchars($faq['q']) ?></span>
              <span class="pm-faq-icon" style="opacity:0.6; font-size:13px; transition:transform 0.2s ease;">▼</span>
            </summary>
            <div style="margin-top:12px; font-size:14.5px; line-height:1.65; color:var(--pm-text-muted, #94a3b8); border-top:1px solid rgba(255,255,255,0.08); padding-top:12px; cursor:text;">
              <?= htmlspecialchars($faq['a']) ?>
            </div>
          </details>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- 7. BOTTOM CALL-TO-ACTION BANNER -->
    <section class="pm-cta-banner">
      <div class="pm-card-top-pill pill-teams" style="margin-bottom:4px;">
        <span>🚀</span> <?= htmlspecialchars(tt('home_cta_badge_squad', 'EKİBİNİ TOPLA')) ?>
      </div>
      <h2 class="pm-cta-title"><?= htmlspecialchars(tt('home_cta_banner_title_squad', 'Ekibini Topla, Takımını Seç ve Arenaya Çık!')) ?></h2>
      <p class="pm-cta-desc"><?= htmlspecialchars(tt('home_cta_banner_desc_squad', 'İster tek başına rekor kır, ister arkadaşlarınla Devs vs QAs savaşı başlat. Prismatch tamamen ücretsiz ve tarayıcında anında hazır!')) ?></p>
      <div style="display:flex; flex-wrap:wrap; gap:12px; justify-content:center; margin-top:8px;">
        <a class="pm-btn-rooms-glow" href="rooms.php">
          <span class="pulse-dot" style="background:#fff; box-shadow:0 0 0 0 rgba(255,255,255,0.7)"></span>
          <span>⚔️ <?= htmlspecialchars(tt('home_hero_rooms_btn_featured', 'Oda Kur & Takımını Topla')) ?></span>
        </a>
        <a class="pm-btn-secondary" href="play.php">
          <img class="bi-icon" src="bootstrap-icons/play-fill.svg" alt="" aria-hidden="true" style="width:18px;height:18px;" />
          <span><?= htmlspecialchars(tt('home_hero_play_btn_solo', 'Tek Başına Oyna')) ?></span>
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

    // Wire up homepage reaction buttons for interactive fun
    const reactionToast = document.getElementById('pmHomeToast');
    let toastTimer = null;
    function showHomeToast(msg) {
      if (!reactionToast) return;
      const msgEl = reactionToast.querySelector('.toast-msg');
      if (msgEl) msgEl.textContent = msg;
      reactionToast.classList.add('show');
      if (toastTimer) clearTimeout(toastTimer);
      toastTimer = setTimeout(() => {
        reactionToast.classList.remove('show');
      }, 2600);
    }

    document.querySelectorAll('.pm-reaction-pill-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        ensureAudio();
        const sound = btn.dataset.sound;
        const emoji = btn.dataset.emoji || '🔥';
        const text = btn.dataset.text || '';
        
        if (sound === 'bug') {
          playTone(420, 0.08, 'sawtooth', 0.08);
          setTimeout(() => playTone(320, 0.12, 'sawtooth', 0.08), 70);
        } else if (sound === 'po') {
          playTone(523, 0.08, 'sine', 0.08);
          setTimeout(() => playTone(659, 0.1, 'triangle', 0.08), 80);
          setTimeout(() => playTone(784, 0.14, 'triangle', 0.09), 160);
        } else if (sound === 'fire') {
          playTone(350, 0.06, 'square', 0.06);
          setTimeout(() => playTone(500, 0.1, 'square', 0.06), 50);
        } else if (sound === 'laugh') {
          playTone(600, 0.06, 'triangle', 0.07);
          setTimeout(() => playTone(750, 0.06, 'triangle', 0.07), 60);
          setTimeout(() => playTone(600, 0.06, 'triangle', 0.07), 120);
        } else if (sound === 'party') {
          playTone(587, 0.08, 'triangle', 0.08);
          setTimeout(() => playTone(880, 0.16, 'triangle', 0.09), 90);
        }

        const reactDesc = <?= json_encode(tt('home_toast_reaction_desc', 'Canlı çok oyunculu odalarda tüm ekibin ekranında patlar!')) ?>;
        showHomeToast(`${emoji} "${text}" — ${reactDesc}`);
      });
    });

    // Start game
    initRound();
  })();
  </script>

  <!-- Interactive Demo Feedback Toast -->
  <div id="pmHomeToast" class="pm-home-toast" role="status" aria-live="polite">
    <span class="toast-icon">⚡</span>
    <span class="toast-msg"></span>
  </div>
</body>
</html>
