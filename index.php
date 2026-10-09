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
    'a' => tt('faq_a1', 'Prismatch, ekranda kısa bir süre gösterilen hedef rengi veya ülke bayrağını zihninizde tutarak renk ızgarası içinden doğru tonu en hızlı şekilde seçtiğiniz, odak ve görsel hafızayı güçlendiren ücretsiz ve çok oyunculu bir bulmaca oyunudur.'),
  ],
  [
    'q' => tt('faq_q_modes', 'Prismatch\'te hangi oyun modları bulunur?'),
    'a' => tt('faq_a_modes', 'Prismatch\'te tam 7 çok oyunculu mod vardır: ⚡ Puan Yarışı (elenmesiz kombo yarışı), 💀 Eleme / Hayatta Kalma (yanlış yapan elenir), ⚔️ Takım Savaşı (2-4 takım ortak havuz), 🚩 Dünya Bayrakları (250+ ülke bayrağı), 💣 Sıcak Patates / Renk Bombası (patlamadan bombayı pasla), ⚡ Flaş Hafıza (0.4 saniye flaş), ve 🧪 Renk Simyası (2 rengi karıştırarak hedefi oluştur). Ayrıca her gün 1 kez oynanan Günlük Meydan Okuma ve sonsuz solo mod mevcuttur.'),
  ],
  [
    'q' => tt('faq_q_sabotage', 'Taktiksel Jokerler ve Sabotaj Güçleri nasıl kullanılır?'),
    'a' => tt('faq_a_sabotage', 'Çok oyunculu odalarda üst üste doğru cevap serisi yakaladıkça 6 özel güç açılır: 🎯 50/50 Jokeri (2 yanlış rengi eler), 🦑 Mürekkep Fırlatma (rakip ekranını karalar), 🪞 Ayna (rakip ekranını baş aşağı çevirir), 🧊 Donma (butonları dondurur), 🔦 Karartma (ekranı zifiri karanlık yapar, sadece fener kalır) ve 🛡️ Kalkan (tüm sabotajları engeller).'),
  ],
  [
    'q' => tt('faq_q_teams', 'Takım Savaşı modu nasıl çalışır?'),
    'a' => tt('faq_a_teams', 'Takım Savaşı modunda oyuncular 2, 3 veya 4 takıma ayrılır (Kırmızı, Mavi, Yeşil, Sarı). Her oyuncunun topladığı puan ortak takım havuzuna eklenir. Canlı yarış çubuğu anlık skoru gösterir ve maç sonunda en çok puanı toplayan takım zaferi kazanır!'),
  ],
  [
    'q' => tt('faq_q_spectator', 'Kahin / Seyirci Bahis Modu nedir?'),
    'a' => tt('faq_a_spectator', 'Eleme veya Bomba modunda erken elenen oyuncular oyundan kopmaz! Seyirci moduna geçerek hayatta kalan oyuncuların maçını canlı izler ve kimin kazanacağına tahmin/bahis oynayarak ekstra XP toplar.'),
  ],
  [
    'q' => tt('faq_q_avatars', 'Avatarlar ve canlı tepkiler nasıl kullanılır?'),
    'a' => tt('faq_a_avatars', 'Lobide ve zafer ekranında onlarca sevimli karakter avatarı arasından seçim yapabilirsiniz. Oyun esnasında ise tek tıkla uçuşan canlı emojiler (🐞 Olamaz, 👑 Harikasın, 🔥 Alev, 😂 Kahkaha, 👏 Tezahürat) ve retro synth sesleriyle odayı karnavala çevirebilirsiniz.'),
  ],
  [
    'q' => tt('faq_q_awards', "Maçın En'leri rozetleri (Hız Şeytanı, Aşırı Düşünen vb.) nedir?"),
    'a' => tt('faq_a_awards', 'Maç bittiğinde sadece birinciye değil, farklı oyun stillerine göre ödüller dağıtılır: Işık hızında karar veren "Hız Şeytanı ⚡", son ana kadar bekleyen "Aşırı Düşünen 🧘", seri yakalayan "Alev Alan 🔥", keskin nişancı "🎯" ve maçın yıldızı "👑 MVP" unvanını alır.'),
  ],
  [
    'q' => tt('faq_q3', 'Prismatch oynamak ücretsiz mi?'),
    'a' => tt('faq_a3', 'Evet! Prismatch web tarayıcınız üzerinden tamamen ücretsiz oynanabilir. İster misafir olarak anında oynayabilir, isterseniz Google hesabınızla giriş yaparak skorlarınızı ve rekorlarınızı liderlik tablosuna kaydedebilirsiniz.'),
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
      max-width: 100vw;
      overflow-x: hidden;
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
       ALL FEATURES SHOWCASE: 7 MODES, 6 POWERS, SOCIAL, STATS & EXPANDED STYLES
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

    /* Section Pills */
    .pm-section-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 5px 14px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 800;
      letter-spacing: 0.5px;
      text-transform: uppercase;
      background: rgba(255, 107, 91, 0.15);
      border: 1px solid rgba(255, 107, 91, 0.35);
      color: #ff6b5b;
      margin-bottom: 12px;
    }

    /* 7 Game Modes Responsive Grid */
    .pm-modes-7-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 18px;
    }
    @media (min-width: 576px) {
      .pm-modes-7-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }
    @media (min-width: 992px) {
      .pm-modes-7-grid {
        grid-template-columns: repeat(3, 1fr);
      }
      .pm-modes-7-grid > .pm-mode-card:last-child:nth-child(7) {
        grid-column: 1 / -1;
      }
    }
    @media (min-width: 1200px) {
      .pm-modes-7-grid {
        grid-template-columns: repeat(4, 1fr);
      }
      .pm-modes-7-grid > .pm-mode-card:last-child:nth-child(7) {
        grid-column: span 2;
      }
    }

    /* Rich Mode Card Personalities */
    .pm-mode-card {
      background: var(--pm-bg-card, #121826);
      border: 1px solid var(--pm-border, rgba(255, 255, 255, 0.1));
      border-radius: 22px;
      padding: 24px 20px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      gap: 16px;
      position: relative;
      overflow: hidden;
      box-shadow: 0 10px 28px rgba(0, 0, 0, 0.18);
      transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease;
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
    }
    .pm-mode-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 18px 44px rgba(0, 0, 0, 0.3);
    }
    .pm-mode-card.card-points {
      border-top: 4px solid #f59e0b;
    }
    .pm-mode-card.card-points:hover { border-color: rgba(245, 158, 11, 0.7); }
    
    .pm-mode-card.card-elimination {
      border-top: 4px solid #ef4444;
    }
    .pm-mode-card.card-elimination:hover { border-color: rgba(239, 68, 68, 0.7); }

    .pm-mode-card.card-teams {
      border-top: 4px solid #f43f5e;
      background: linear-gradient(145deg, rgba(244, 63, 94, 0.08), rgba(56, 189, 248, 0.06)), var(--pm-bg-card, #121826);
    }
    .pm-mode-card.card-teams:hover { border-color: rgba(244, 63, 94, 0.7); }

    .pm-mode-card.card-flags {
      border-top: 4px solid #06b6d4;
    }
    .pm-mode-card.card-flags:hover { border-color: rgba(6, 182, 212, 0.7); }

    .pm-mode-card.card-hotpotato {
      border-top: 4px solid #ff5722;
      background: linear-gradient(145deg, rgba(255, 87, 34, 0.08), rgba(239, 68, 68, 0.04)), var(--pm-bg-card, #121826);
    }
    .pm-mode-card.card-hotpotato:hover { border-color: rgba(255, 87, 34, 0.7); }

    .pm-mode-card.card-flash {
      border-top: 4px solid #00e5ff;
      background: linear-gradient(145deg, rgba(0, 229, 255, 0.08), rgba(59, 130, 246, 0.04)), var(--pm-bg-card, #121826);
    }
    .pm-mode-card.card-flash:hover { border-color: rgba(0, 229, 255, 0.7); }

    .pm-mode-card.card-alchemy {
      border-top: 4px solid #a855f7;
      background: linear-gradient(145deg, rgba(168, 85, 247, 0.08), rgba(236, 72, 153, 0.04)), var(--pm-bg-card, #121826);
    }
    .pm-mode-card.card-alchemy:hover { border-color: rgba(168, 85, 247, 0.7); }

    .pm-mode-pill {
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.5px;
      text-transform: uppercase;
      padding: 4px 10px;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }
    .pm-mode-title {
      font-family: var(--pm-font-display, "Baloo 2", sans-serif);
      font-size: 20px;
      font-weight: 800;
      margin: 12px 0 6px 0;
      line-height: 1.25;
    }
    .pm-mode-desc {
      font-size: 13.5px;
      color: var(--pm-text-muted, #94a3b8);
      line-height: 1.5;
      margin: 0;
    }

    /* 6 Powers / Sabotages Grid */
    .pm-powers-6-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 16px;
    }
    @media (min-width: 576px) {
      .pm-powers-6-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }
    @media (min-width: 992px) {
      .pm-powers-6-grid {
        grid-template-columns: repeat(3, 1fr);
      }
    }

    .pm-power-card {
      background: var(--pm-bg-card, #121826);
      border: 1px solid var(--pm-border, rgba(255, 255, 255, 0.12));
      border-radius: 20px;
      padding: 22px 18px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      gap: 14px;
      position: relative;
      overflow: hidden;
      transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.16);
    }
    .pm-power-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 16px 36px rgba(0, 0, 0, 0.28);
    }
    .pm-power-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
    }
    .pm-power-icon {
      width: 44px;
      height: 44px;
      border-radius: 14px;
      display: grid;
      place-items: center;
      font-size: 22px;
    }
    .pm-power-test-btn {
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.18);
      border-radius: 999px;
      padding: 5px 12px;
      font-size: 11.5px;
      font-weight: 700;
      color: inherit;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      transition: all 0.15s ease;
    }
    .pm-power-test-btn:hover {
      background: rgba(255, 255, 255, 0.2);
      transform: scale(1.05);
    }
    .pm-power-test-btn:active {
      transform: scale(0.95);
    }

    /* Social Showcase 4-Card Grid */
    .pm-social-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 20px;
    }
    @media (min-width: 768px) {
      .pm-social-grid {
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
    .pm-fun-card.card-spectator {
      background: linear-gradient(145deg, rgba(99, 102, 241, 0.14), rgba(168, 85, 247, 0.08)), var(--pm-bg-card, #121826);
      border-color: rgba(99, 102, 241, 0.35);
    }
    .pm-fun-card.card-spectator:hover {
      border-color: rgba(99, 102, 241, 0.7);
      box-shadow: 0 20px 48px rgba(99, 102, 241, 0.25);
    }
    .pm-fun-card.card-avatars {
      background: linear-gradient(145deg, rgba(236, 72, 153, 0.14), rgba(245, 158, 11, 0.08)), var(--pm-bg-card, #121826);
      border-color: rgba(236, 72, 153, 0.35);
    }
    .pm-fun-card.card-avatars:hover {
      border-color: rgba(236, 72, 153, 0.7);
      box-shadow: 0 20px 48px rgba(236, 72, 153, 0.25);
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
    .pill-spectator { background: rgba(99, 102, 241, 0.18); color: #a5b4fc; border: 1px solid rgba(99, 102, 241, 0.4); }
    .pill-avatars { background: rgba(236, 72, 153, 0.18); color: #f472b6; border: 1px solid rgba(236, 72, 153, 0.4); }

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

    /* Tug of war interactive visual */
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

    /* Avatars teaser visual */
    .pm-avatars-preview-strip {
      background: rgba(0, 0, 0, 0.35);
      border: 1px solid var(--pm-border, rgba(255, 255, 255, 0.1));
      border-radius: 16px;
      padding: 10px 14px;
      display: flex;
      align-items: center;
      justify-content: space-around;
      gap: 6px;
      font-size: 26px;
      margin-bottom: 8px;
      overflow-x: auto;
    }
    .pm-avatar-mini-bubble {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.08);
      border: 2px solid rgba(255, 255, 255, 0.2);
      display: grid;
      place-items: center;
      font-size: 22px;
      transition: transform 0.2s ease, border-color 0.2s ease;
      cursor: default;
    }
    .pm-avatar-mini-bubble:hover {
      transform: scale(1.15) rotate(6deg);
      border-color: #f472b6;
    }

    /* Spectator predictor teaser visual */
    .pm-spectator-visual {
      background: rgba(0, 0, 0, 0.35);
      border: 1px solid var(--pm-border, rgba(255, 255, 255, 0.1));
      border-radius: 16px;
      padding: 10px 14px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      margin-bottom: 8px;
    }

    /* Stats showcase banner */
    .pm-stats-banner {
      background: linear-gradient(135deg, rgba(56, 189, 248, 0.1), rgba(168, 85, 247, 0.08)), var(--pm-bg-card, #121826);
      border: 1px solid rgba(56, 189, 248, 0.3);
      border-radius: 24px;
      padding: 28px 24px;
      display: grid;
      grid-template-columns: 1fr;
      gap: 20px;
      align-items: center;
    }
    @media (min-width: 768px) {
      .pm-stats-banner {
        grid-template-columns: 1.2fr 1.8fr;
        gap: 32px;
      }
    }
    .pm-stats-grid-mini {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
    }
    .pm-stat-box {
      background: rgba(0, 0, 0, 0.3);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 16px;
      padding: 14px 12px;
      text-align: center;
    }
    .pm-stat-num {
      font-family: var(--pm-font-display, "Baloo 2", sans-serif);
      font-size: 26px;
      font-weight: 800;
      color: #38bdf8;
      line-height: 1;
      margin-bottom: 4px;
    }
    .pm-stat-lbl {
      font-size: 11.5px;
      font-weight: 600;
      color: var(--pm-text-muted, #94a3b8);
      text-transform: uppercase;
      letter-spacing: 0.4px;
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
          <span><?= htmlspecialchars(tt('home_hero_new_tag', '7 Oyun Modu · 6 Sabotaj Gücü · Canlı Takım Savaşları · Kahin Modu')) ?></span>
        </div>
        <h1 class="pm-hero-title">
          <?= htmlspecialchars(tt('home_hero_title_part1', 'Remember the Colors,')) ?> <span class="prism-shimmer"><?= htmlspecialchars(tt('home_hero_title_squad', 'Battle Your Squad!')) ?></span>
        </h1>
        <p class="pm-hero-desc">
          <?= htmlspecialchars(tt('home_hero_subtitle_fun', 'İster tek başına görsel hafızanı güçlendir, ister arkadaşlarınla canlı oda kurup kapış! 7 farklı oyun modunda yarış, rakiplerine mürekkep veya buz fırlat, takımını zafere taşı!')) ?>
        </p>

        <div class="pm-hero-actions">
          <a class="pm-btn-rooms-glow" href="rooms.php">
            <span class="pulse-dot" style="background:#fff; box-shadow:0 0 0 0 rgba(255,255,255,0.7)"></span>
            <span>🚀 <?= htmlspecialchars(tt('home_hero_rooms_btn_featured', 'Oda Kur & Canlı Yarış')) ?></span>
          </a>
          <a class="pm-btn-secondary" href="play.php">
            <img class="bi-icon" src="bootstrap-icons/play-fill.svg" alt="" aria-hidden="true" style="width:18px;height:18px;" />
            <span><?= htmlspecialchars(tt('home_hero_play_btn_solo', 'Tek Başına Oyna')) ?></span>
          </a>
          <a class="pm-btn-secondary" href="play.php?daily=1">
            <img class="bi-icon" src="bootstrap-icons/calendar2-check.svg" alt="" aria-hidden="true" style="width:18px;height:18px;" />
            <span><?= htmlspecialchars(tt('home_hero_daily_btn', 'Günün Turnuvası')) ?></span>
          </a>
          <a class="pm-btn-secondary" href="daily_leaderboard.php">
            <img class="bi-icon" src="bootstrap-icons/trophy-fill.svg" alt="" aria-hidden="true" style="width:18px;height:18px;" />
            <span><?= htmlspecialchars(tt('home_hero_leaderboard_btn', 'Sıralama')) ?></span>
          </a>
        </div>

        <div class="pm-hero-chips">
          <div class="pm-chip" style="border-color: rgba(244, 63, 94, 0.4); color: #fb7185;">
            <span>⚔️</span>
            <span><?= htmlspecialchars(tt('rooms_mode_teams_short', 'Takım Savaşı')) ?></span>
          </div>
          <div class="pm-chip" style="border-color: rgba(255, 87, 34, 0.4); color: #ff8a65;">
            <span>💣</span>
            <span><?= htmlspecialchars(tt('rooms_mode_hotpotato_short', 'Renk Bombası')) ?></span>
          </div>
          <div class="pm-chip" style="border-color: rgba(0, 229, 255, 0.4); color: #38bdf8;">
            <span>⚡</span>
            <span><?= htmlspecialchars(tt('rooms_mode_flash_short', 'Flaş Hafıza')) ?></span>
          </div>
          <div class="pm-chip" style="border-color: rgba(168, 85, 247, 0.4); color: #c084fc;">
            <span>🧪</span>
            <span><?= htmlspecialchars(tt('rooms_mode_alchemy_short', 'Renk Simyası')) ?></span>
          </div>
          <div class="pm-chip" style="border-color: rgba(16, 185, 129, 0.4); color: #34d399;">
            <span>🦑</span>
            <span><?= htmlspecialchars(tt('home_chip_sabotage', '6 Sabotaj Gücü')) ?></span>
          </div>
          <div class="pm-chip" style="border-color: rgba(99, 102, 241, 0.4); color: #a5b4fc;">
            <span>🔮</span>
            <span><?= htmlspecialchars(tt('home_chip_spectator', 'Kahin Modu')) ?></span>
          </div>
          <div class="pm-chip" style="border-color: rgba(236, 72, 153, 0.4); color: #f472b6;">
            <span>🎭</span>
            <span><?= htmlspecialchars(tt('home_chip_avatars', 'Özel Avatarlar')) ?></span>
          </div>
          <div class="pm-chip">
            <span>🚩</span>
            <span><?= htmlspecialchars(tt('rooms_mode_flags_short', '250+ Bayrak')) ?></span>
          </div>
        </div>
      </div>

      <!-- INTERACTIVE PLAYABLE GAME DEMO CARD -->
      <div class="pm-mini-arena" aria-label="<?= htmlspecialchars(tt('a11y_demo_game', 'Interactive Game Demo')) ?>">
        <div class="pm-mini-top">
          <div class="pm-live-badge">
            <span class="pulse-dot"></span>
            <span><?= htmlspecialchars(tt('home_interactive_tag', 'Canlı Önizleme')) ?></span>
          </div>
          <div class="pm-live-stats">
            <span><?= htmlspecialchars(tt('home_interactive_stage', 'Aşama')) ?> <span id="demoStage" style="color:#ff6b5b; font-weight:800;">1</span> · 
            <span id="demoScore" style="color:#10b981; font-weight:800;">0</span> XP</span>
            <button id="demoMuteBtn" class="pm-mini-icon-btn" type="button" aria-label="<?= htmlspecialchars(tt('a11y_toggle_sound', 'Toggle Sound')) ?>" title="<?= htmlspecialchars(tt('a11y_toggle_sound', 'Sound')) ?>">
              <span id="demoMuteIcon">🔊</span>
            </button>
            <button id="demoRestartBtn" class="pm-mini-icon-btn" type="button" aria-label="<?= htmlspecialchars(tt('a11y_restart_demo', 'Restart Demo')) ?>" title="<?= htmlspecialchars(tt('btn_restart', 'Restart')) ?>">
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

        <div id="demoGrid" class="pm-mini-grid" role="region" aria-label="<?= htmlspecialchars(tt('a11y_demo_grid', 'Demo color grid')) ?>">
          <!-- 9 dynamic color tiles injected by script -->
        </div>

        <div id="demoFeedback" class="pm-mini-feedback">
          <?= htmlspecialchars(tt('home_interactive_hint', 'Aşağıdaki renklerden doğru olana tıkla!')) ?>
        </div>
      </div>
    </section>

    <!-- 2. SECTION: 7 MULTIPLAYER GAME MODES SHOWCASE -->
    <section>
      <div class="pm-section-head">
        <div class="pm-section-pill">
          <span>🎮</span> <?= htmlspecialchars(tt('home_modes_pill', 'ÇOK OYUNCULU ARENASI')) ?>
        </div>
        <h2 class="pm-section-title"><?= htmlspecialchars(tt('home_modes_showcase_title', 'Tek Bir Oyunda 7 Efsanevi Rekabet Modu')) ?></h2>
        <p class="pm-section-sub"><?= htmlspecialchars(tt('home_modes_showcase_sub', 'Monoton oyunları unutun! İster takımla halat çekin, ister patatesi başkasına fırlatın, ister simya laboratuvarında renkleri karıştırın.')) ?></p>
      </div>

      <div class="pm-modes-7-grid">
        <!-- Mode 1: Points Race -->
        <div class="pm-mode-card card-points">
          <div>
            <div class="d-flex align-items-center justify-content-between">
              <div class="pm-mode-icon" style="background: rgba(245,158,11,0.18); font-size:24px;">⚡</div>
              <span class="pm-mode-pill" style="background: rgba(245,158,11,0.2); color:#fbbf24; border: 1px solid rgba(245,158,11,0.4);">
                <?= htmlspecialchars(tt('rooms_mode_points_short', 'Puan Yarışı')) ?>
              </span>
            </div>
            <h3 class="pm-mode-title">⚡ <?= htmlspecialchars(tt('rooms_mode_points_title', 'Puan Yarışı (Elenmesiz)')) ?></h3>
            <p class="pm-mode-desc"><?= htmlspecialchars(tt('rooms_mode_points_desc', 'Elenme yok! Doğru cevap puan kazandırır, yanlışta kazanılacak puan toplamdan düşülür. Pas geçen 0 puan alır. 25 tur boyunca kombo serisi yakala!')) ?></p>
          </div>
          <a class="pm-btn-secondary" href="rooms.php" style="justify-content:center; width:100%; border-color:rgba(245,158,11,0.4);">
            <span>⚡ <?= htmlspecialchars(tt('home_play_mode_btn', 'Bu Modu Oyna')) ?> →</span>
          </a>
        </div>

        <!-- Mode 2: Elimination -->
        <div class="pm-mode-card card-elimination">
          <div>
            <div class="d-flex align-items-center justify-content-between">
              <div class="pm-mode-icon" style="background: rgba(239,68,68,0.18); font-size:24px;">💀</div>
              <span class="pm-mode-pill" style="background: rgba(239,68,68,0.2); color:#f87171; border: 1px solid rgba(239,68,68,0.4);">
                <?= htmlspecialchars(tt('badge_elim_short', 'Hayatta Kalma')) ?>
              </span>
            </div>
            <h3 class="pm-mode-title">💀 <?= htmlspecialchars(tt('rooms_mode_elim_title', 'Eleme Modu (Hayatta Kalma)')) ?></h3>
            <p class="pm-mode-desc"><?= htmlspecialchars(tt('rooms_mode_elim_desc', 'Yanlış yapan veya süresi dolan anında elenir! Hata payı sıfır. Ayakta kalan son oyuncu veya süre bittiğinde en yüksek puanlı şampiyon olur.')) ?></p>
          </div>
          <a class="pm-btn-secondary" href="rooms.php" style="justify-content:center; width:100%; border-color:rgba(239,68,68,0.4);">
            <span>💀 <?= htmlspecialchars(tt('home_play_mode_btn', 'Bu Modu Oyna')) ?> →</span>
          </a>
        </div>

        <!-- Mode 3: Team Battle -->
        <div class="pm-mode-card card-teams">
          <div>
            <div class="d-flex align-items-center justify-content-between">
              <div class="pm-mode-icon" style="background: linear-gradient(135deg, rgba(244,63,94,0.2), rgba(56,189,248,0.2)); font-size:24px;">⚔️</div>
              <span class="pm-mode-pill" style="background: linear-gradient(90deg, #f43f5e, #38bdf8); color:#fff;">
                <?= htmlspecialchars(tt('rooms_mode_teams_short', 'Takım Savaşı')) ?>
              </span>
            </div>
            <h3 class="pm-mode-title">⚔️ <?= htmlspecialchars(tt('rooms_mode_teams_title', 'Takım Savaşı (2-4 Takım)')) ?></h3>
            <p class="pm-mode-desc"><?= htmlspecialchars(tt('rooms_mode_teams_desc', '🔴 Kırmızı, 🔵 Mavi, 🟢 Yeşil ve 🟡 Sarı Takımlar! Bireysel puanlar ortak havuzda birleşir, canlı yarış çubuğunda halat çekilir.')) ?></p>
          </div>
          <a class="pm-btn-rooms-glow" href="rooms.php" style="justify-content:center; width:100%; padding:10px 18px !important; font-size:14px !important;">
            <span>⚔️ <?= htmlspecialchars(tt('home_play_mode_btn', 'Bu Modu Oyna')) ?> →</span>
          </a>
        </div>

        <!-- Mode 4: Flags of the World -->
        <div class="pm-mode-card card-flags">
          <div>
            <div class="d-flex align-items-center justify-content-between">
              <div class="pm-mode-icon" style="background: rgba(6,182,212,0.18); font-size:24px;">🚩</div>
              <span class="pm-mode-pill" style="background: rgba(6,182,212,0.2); color:#22d3ee; border: 1px solid rgba(6,182,212,0.4);">
                <?= htmlspecialchars(tt('rooms_mode_flags_short', 'Bayrak Modu')) ?>
              </span>
            </div>
            <h3 class="pm-mode-title">🚩 <?= htmlspecialchars(tt('rooms_mode_flags_title', 'Dünya Bayrakları Modu')) ?></h3>
            <p class="pm-mode-desc"><?= htmlspecialchars(tt('rooms_mode_flags_desc', 'Renkler yerine 250+ ülke bayrağı! Elenme yok, doğru bayrak puan kazandırır, yanlış seçim puan düşürür. Görsel hafıza ve coğrafya bir arada.')) ?></p>
          </div>
          <a class="pm-btn-secondary" href="rooms.php" style="justify-content:center; width:100%; border-color:rgba(6,182,212,0.4);">
            <span>🚩 <?= htmlspecialchars(tt('home_play_mode_btn', 'Bu Modu Oyna')) ?> →</span>
          </a>
        </div>

        <!-- Mode 5: Hot Potato / Bomb -->
        <div class="pm-mode-card card-hotpotato">
          <div>
            <div class="d-flex align-items-center justify-content-between">
              <div class="pm-mode-icon" style="background: rgba(255,87,34,0.18); font-size:24px;">💣</div>
              <span class="pm-mode-pill" style="background: rgba(255,87,34,0.2); color:#ff8a65; border: 1px solid rgba(255,87,34,0.4);">
                <?= htmlspecialchars(tt('rooms_mode_hotpotato_short', 'Renk Bombası')) ?>
              </span>
            </div>
            <h3 class="pm-mode-title">💣 <?= htmlspecialchars(tt('rooms_mode_hotpotato_title', 'Sıcak Patates / Renk Bombası')) ?></h3>
            <p class="pm-mode-desc"><?= htmlspecialchars(tt('rooms_mode_hotpotato_desc', 'Bomba rastgele bir oyuncuda başlar! Doğru bilen bombayı diğerine fırlatır; sürede elinde patlayan elenir. Zamanla yarışan saf adrenalin!')) ?></p>
          </div>
          <a class="pm-btn-secondary" href="rooms.php" style="justify-content:center; width:100%; border-color:rgba(255,87,34,0.4);">
            <span>💣 <?= htmlspecialchars(tt('home_play_mode_btn', 'Bu Modu Oyna')) ?> →</span>
          </a>
        </div>

        <!-- Mode 6: Flash Memory -->
        <div class="pm-mode-card card-flash">
          <div>
            <div class="d-flex align-items-center justify-content-between">
              <div class="pm-mode-icon" style="background: rgba(0,229,255,0.18); font-size:24px;">⚡</div>
              <span class="pm-mode-pill" style="background: rgba(0,229,255,0.2); color:#38bdf8; border: 1px solid rgba(0,229,255,0.4);">
                <?= htmlspecialchars(tt('rooms_mode_flash_short', 'Flaş Hafıza')) ?>
              </span>
            </div>
            <h3 class="pm-mode-title">⚡ <?= htmlspecialchars(tt('rooms_mode_flash_title', 'Flaş Hafıza (Körlemece)')) ?></h3>
            <p class="pm-mode-desc"><?= htmlspecialchars(tt('rooms_mode_flash_desc', 'Hedef renk ekranda sadece 0.4 saniye parlayıp kaybolur! Gözüne ve fotografik hafızana güvenip eşleşeni yıldırım hızında bulmalısın.')) ?></p>
          </div>
          <a class="pm-btn-secondary" href="rooms.php" style="justify-content:center; width:100%; border-color:rgba(0,229,255,0.4);">
            <span>⚡ <?= htmlspecialchars(tt('home_play_mode_btn', 'Bu Modu Oyna')) ?> →</span>
          </a>
        </div>

        <!-- Mode 7: Color Alchemy -->
        <div class="pm-mode-card card-alchemy">
          <div>
            <div class="d-flex align-items-center justify-content-between">
              <div class="pm-mode-icon" style="background: rgba(168,85,247,0.18); font-size:24px;">🧪</div>
              <span class="pm-mode-pill" style="background: rgba(168,85,247,0.2); color:#c084fc; border: 1px solid rgba(168,85,247,0.4);">
                <?= htmlspecialchars(tt('rooms_mode_alchemy_short', 'Renk Simyası')) ?>
              </span>
            </div>
            <h3 class="pm-mode-title">🧪 <?= htmlspecialchars(tt('rooms_mode_alchemy_title', 'Renk Simyası (Karışım)')) ?></h3>
            <p class="pm-mode-desc"><?= htmlspecialchars(tt('rooms_mode_alchemy_desc', 'Hedef rengi elde etmek için seçeneklerden 2 doğru rengi karıştır! (Mavi + Sarı = Yeşil, Kırmızı + Mavi = Mor). Hem renk teorisi hem strateji zekası.')) ?></p>
          </div>
          <a class="pm-btn-secondary" href="rooms.php" style="justify-content:center; width:100%; border-color:rgba(168,85,247,0.4);">
            <span>🧪 <?= htmlspecialchars(tt('home_play_mode_btn', 'Bu Modu Oyna')) ?> →</span>
          </a>
        </div>
      </div>
    </section>

    <!-- 3. SECTION: 6 TACTICAL JOKERS & SABOTAGES ARSENAL -->
    <section>
      <div class="pm-section-head">
        <div class="pm-section-pill" style="background:rgba(168,85,247,0.15); border-color:rgba(168,85,247,0.35); color:#c084fc;">
          <span>💥</span> <?= htmlspecialchars(tt('home_powers_pill', 'TAKTIKSEL CEPHANELİK')) ?>
        </div>
        <h2 class="pm-section-title"><?= htmlspecialchars(tt('home_powers_title', 'Rakiplerini Tuş Edecek 6 Özel Güç')) ?></h2>
        <p class="pm-section-sub"><?= htmlspecialchars(tt('home_powers_sub', 'Çok oyunculu odalarda kombo serileri yakalayarak kartları aç. Rakiplerinin ekranına mürekkep fırlat, dondur veya kalkanla korun!')) ?></p>
      </div>

      <div class="pm-powers-6-grid">
        <!-- Power 1: 50/50 -->
        <div class="pm-power-card" style="border-top: 3px solid #10b981;">
          <div>
            <div class="pm-power-header">
              <div class="pm-power-icon" style="background:rgba(16,185,129,0.18); color:#34d399;">🎯</div>
              <button type="button" class="pm-power-test-btn" data-power="5050" data-icon="🎯" data-title="<?= htmlspecialchars(tt('power_5050_title', '50/50 Jokeri')) ?>" data-desc="<?= htmlspecialchars(tt('power_5050_toast', 'İki yanlış renk elendi! Doğru rengi bulmak kolaylaştı.')) ?>">
                <span>🔊</span> <span><?= htmlspecialchars(tt('home_test_power', 'Dene')) ?></span>
              </button>
            </div>
            <h3 class="pm-fun-title" style="font-size:18px; margin:12px 0 4px 0;">🎯 <?= htmlspecialchars(tt('power_5050_title', '50/50 Jokeri')) ?></h3>
            <p class="pm-fun-desc" style="font-size:13px; margin:0;"><?= htmlspecialchars(tt('power_5050_desc', 'Ekranda birbirine çok benzeyen karolar arasında kararsız kaldığında iki yanlış seçeneği anında siler.')) ?></p>
          </div>
        </div>

        <!-- Power 2: Ink Splat -->
        <div class="pm-power-card" style="border-top: 3px solid #8b5cf6;">
          <div>
            <div class="pm-power-header">
              <div class="pm-power-icon" style="background:rgba(139,92,246,0.18); color:#a78bfa;">🦑</div>
              <button type="button" class="pm-power-test-btn" data-power="ink" data-icon="🦑" data-title="<?= htmlspecialchars(tt('power_ink_title', 'Mürekkep Sabotajı')) ?>" data-desc="<?= htmlspecialchars(tt('power_ink_toast', 'Rakibin ekranına dev mürekkep lekeleri fırlatıldı!')) ?>">
                <span>🔊</span> <span><?= htmlspecialchars(tt('home_test_power', 'Dene')) ?></span>
              </button>
            </div>
            <h3 class="pm-fun-title" style="font-size:18px; margin:12px 0 4px 0;">🦑 <?= htmlspecialchars(tt('power_ink_title', 'Mürekkep Fırlatma')) ?></h3>
            <p class="pm-fun-desc" style="font-size:13px; margin:0;"><?= htmlspecialchars(tt('power_ink_desc', 'Seçtiğin rakibin ekranına gerçekçi siyah mürekkep lekeleri fırlatarak 3 saniye boyunca renkleri görmesini engeller.')) ?></p>
          </div>
        </div>

        <!-- Power 3: Mirror Flip -->
        <div class="pm-power-card" style="border-top: 3px solid #f59e0b;">
          <div>
            <div class="pm-power-header">
              <div class="pm-power-icon" style="background:rgba(245,158,11,0.18); color:#fbbf24;">🪞</div>
              <button type="button" class="pm-power-test-btn" data-power="mirror" data-icon="🪞" data-title="<?= htmlspecialchars(tt('power_mirror_title', 'Ayna / Ters Ekran')) ?>" data-desc="<?= htmlspecialchars(tt('power_mirror_toast', 'Rakibin ekranı 180° ters döndürüldü!')) ?>">
                <span>🔊</span> <span><?= htmlspecialchars(tt('home_test_power', 'Dene')) ?></span>
              </button>
            </div>
            <h3 class="pm-fun-title" style="font-size:18px; margin:12px 0 4px 0;">🪞 <?= htmlspecialchars(tt('power_mirror_title', 'Ayna / Ters Ekran')) ?></h3>
            <p class="pm-fun-desc" style="font-size:13px; margin:0;"><?= htmlspecialchars(tt('power_mirror_desc', 'Rakibin tüm oyun ekranını 180 derece baş aşağı döndürür, yön algısını ve el-göz koordinasyonunu bozar.')) ?></p>
          </div>
        </div>

        <!-- Power 4: Freeze -->
        <div class="pm-power-card" style="border-top: 3px solid #06b6d4;">
          <div>
            <div class="pm-power-header">
              <div class="pm-power-icon" style="background:rgba(6,182,212,0.18); color:#22d3ee;">🧊</div>
              <button type="button" class="pm-power-test-btn" data-power="freeze" data-icon="🧊" data-title="<?= htmlspecialchars(tt('power_freeze_title', 'Donma Sabotajı')) ?>" data-desc="<?= htmlspecialchars(tt('power_freeze_toast', 'Rakibin butonları buz kütlesine hapsedildi!')) ?>">
                <span>🔊</span> <span><?= htmlspecialchars(tt('home_test_power', 'Dene')) ?></span>
              </button>
            </div>
            <h3 class="pm-fun-title" style="font-size:18px; margin:12px 0 4px 0;">🧊 <?= htmlspecialchars(tt('power_freeze_title', 'Buzla Dondurma')) ?></h3>
            <p class="pm-fun-desc" style="font-size:13px; margin:0;"><?= htmlspecialchars(tt('power_freeze_desc', 'Rakibin seçenek butonlarını buz tabakasıyla kaplayıp tıklamasını 2 saniye kilitler; kritik zaman kaybettirir.')) ?></p>
          </div>
        </div>

        <!-- Power 5: Blackout / Flashlight -->
        <div class="pm-power-card" style="border-top: 3px solid #64748b;">
          <div>
            <div class="pm-power-header">
              <div class="pm-power-icon" style="background:rgba(100,116,139,0.18); color:#cbd5e1;">🔦</div>
              <button type="button" class="pm-power-test-btn" data-power="blackout" data-icon="🔦" data-title="<?= htmlspecialchars(tt('power_blackout_title', 'Karartma & El Feneri')) ?>" data-desc="<?= htmlspecialchars(tt('power_blackout_toast', 'Rakibin ekranı karartıldı, sadece dar fener ışığı kaldı!')) ?>">
                <span>🔊</span> <span><?= htmlspecialchars(tt('home_test_power', 'Dene')) ?></span>
              </button>
            </div>
            <h3 class="pm-fun-title" style="font-size:18px; margin:12px 0 4px 0;">🔦 <?= htmlspecialchars(tt('power_blackout_title', 'Karartma & Fener')) ?></h3>
            <p class="pm-fun-desc" style="font-size:13px; margin:0;"><?= htmlspecialchars(tt('power_blackout_desc', 'Rakibin ekranını zifiri karanlığa boğar! Sadece parmağının/imlecinin aydınlattığı dar fener ışığı kalır.')) ?></p>
          </div>
        </div>

        <!-- Power 6: Shield -->
        <div class="pm-power-card" style="border-top: 3px solid #f43f5e;">
          <div>
            <div class="pm-power-header">
              <div class="pm-power-icon" style="background:rgba(244,63,94,0.18); color:#fb7185;">🛡️</div>
              <button type="button" class="pm-power-test-btn" data-power="shield" data-icon="🛡️" data-title="<?= htmlspecialchars(tt('power_shield_title', 'Kalkan Koruması')) ?>" data-desc="<?= htmlspecialchars(tt('power_shield_toast', 'Kalkan aktif! Gelen tüm sabotajlar engellenir.')) ?>">
                <span>🔊</span> <span><?= htmlspecialchars(tt('home_test_power', 'Dene')) ?></span>
              </button>
            </div>
            <h3 class="pm-fun-title" style="font-size:18px; margin:12px 0 4px 0;">🛡️ <?= htmlspecialchars(tt('power_shield_title', 'Kalkan Koruması')) ?></h3>
            <p class="pm-fun-desc" style="font-size:13px; margin:0;"><?= htmlspecialchars(tt('power_shield_desc', 'Sana doğru fırlatılan mürekkep, donma veya ters ekran sabotajlarını anında bloke eder ve savuşturur.')) ?></p>
          </div>
        </div>
      </div>
    </section>

    <!-- 4. SECTION: SOCIAL FUN, AVATARS, SPECTATOR BETS & ACCOLADES -->
    <section>
      <div class="pm-section-head">
        <div class="pm-section-pill" style="background:rgba(236,72,153,0.15); border-color:rgba(236,72,153,0.35); color:#f472b6;">
          <span>🎉</span> <?= htmlspecialchars(tt('home_social_pill', 'SOSYAL ARENA & EĞLENCE')) ?>
        </div>
        <h2 class="pm-section-title"><?= htmlspecialchars(tt('home_social_title', 'Avatarlar, Canlı Tepkiler ve Seyirci Kahini!')) ?></h2>
        <p class="pm-section-sub"><?= htmlspecialchars(tt('home_social_sub', 'Oyun bittiğinde bile heyecan devam eder! Maçın MVP\'si ol, uçuşan emojilerle tezahürat yap veya elendiğinde kahin olarak tahmin yürüt.')) ?></p>
      </div>

      <div class="pm-social-grid">
        <!-- Social Card 1: Custom Avatars -->
        <div class="pm-fun-card card-avatars">
          <div>
            <div class="pm-card-top-pill pill-avatars">
              <span>🎭</span> <?= htmlspecialchars(tt('home_avatars_pill', 'Özelleştirilebilir Avatarlar')) ?>
            </div>
            <h3 class="pm-fun-title"><?= htmlspecialchars(tt('home_avatars_title', 'Lobide ve Podyumda Tarzını Göster')) ?></h3>
            <p class="pm-fun-desc"><?= htmlspecialchars(tt('home_avatars_desc', 'Onlarca sevimli, renkli karakter avatarı arasından dilediğini seç! Hem bekleme lobisinde hem de maç sonu zafer ekranında anında değiştir.')) ?></p>
            
            <div class="pm-avatars-preview-strip">
              <div class="pm-avatar-mini-bubble" title="Avatar">🦊</div>
              <div class="pm-avatar-mini-bubble" title="Avatar">🐼</div>
              <div class="pm-avatar-mini-bubble" title="Avatar">🦁</div>
              <div class="pm-avatar-mini-bubble" title="Avatar">🤖</div>
              <div class="pm-avatar-mini-bubble" title="Avatar">🦄</div>
              <div class="pm-avatar-mini-bubble" title="Avatar">👾</div>
              <div class="pm-avatar-mini-bubble" title="Avatar">🧙</div>
            </div>
          </div>
          <a class="pm-btn-secondary" href="rooms.php" style="justify-content:center; width:100%; border-color:rgba(236,72,153,0.4);">
            <span>🎭 <?= htmlspecialchars(tt('home_avatars_btn', 'Lobiye Gir & Avatarını Seç')) ?> →</span>
          </a>
        </div>

        <!-- Social Card 2: Spectator Prediction / Oracle -->
        <div class="pm-fun-card card-spectator">
          <div>
            <div class="pm-card-top-pill pill-spectator">
              <span>🔮</span> <?= htmlspecialchars(tt('home_spectator_pill', 'Kahin / Seyirci Bahis Modu')) ?>
            </div>
            <h3 class="pm-fun-title"><?= htmlspecialchars(tt('home_spectator_title', 'Elensen Bile Oyundan Kopma!')) ?></h3>
            <p class="pm-fun-desc"><?= htmlspecialchars(tt('home_spectator_desc', 'Eleme modunda erken elenen oyuncular için eğlence bitmez! Canlı izleyici moduna geçerek kimin kazanacağını tahmin et ve ekstra XP kazan.')) ?></p>
            
            <div class="pm-spectator-visual">
              <div style="display:flex; align-items:center; gap:8px;">
                <span style="font-size:24px;">🔮</span>
                <div>
                  <div style="font-weight:700; font-size:13px; color:#a5b4fc;"><?= htmlspecialchars(tt('home_spectator_tag', 'Canlı Maç Tahmini')) ?></div>
                  <div style="font-size:11.5px; color:var(--pm-text-muted);"><?= htmlspecialchars(tt('home_spectator_subtag', 'Doğru tahminde +150 XP bonus!')) ?></div>
                </div>
              </div>
              <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1.5 fw-bold">
                <?= htmlspecialchars(tt('home_spectator_badge', 'Aktif')) ?>
              </span>
            </div>
          </div>
          <a class="pm-btn-secondary" href="rooms.php" style="justify-content:center; width:100%; border-color:rgba(99,102,241,0.4);">
            <span>🔮 <?= htmlspecialchars(tt('home_spectator_btn', 'Canlı Maçları İzle')) ?> →</span>
          </a>
        </div>

        <!-- Social Card 3: Live Audio & Banter -->
        <div class="pm-fun-card card-reactions">
          <div>
            <div class="pm-card-top-pill pill-reactions">
              <span>🔥</span> <?= htmlspecialchars(tt('home_feat_reactions_pill', 'Sesli Tepkiler & Canlı Sloganlar')) ?>
            </div>
            <h3 class="pm-fun-title"><?= htmlspecialchars(tt('home_feat_reactions_title', '"Olamaz! 🐞" & "Harikasın! 👑"')) ?></h3>
            <p class="pm-fun-desc"><?= htmlspecialchars(tt('home_feat_reactions_desc', 'Oyun sırasında uçuşan canlı emojiler, alkışlar ve retro synth sesleriyle odayı karnavala çevir. Butonlara basarak test et:')) ?></p>
            
            <div class="pm-reactions-visual">
              <button type="button" class="pm-reaction-pill-btn" data-sound="bug" data-emoji="🐞" data-text="<?= htmlspecialchars(tt('home_reaction_bug', '🐞 Olamaz!')) ?>">
                <span><?= htmlspecialchars(tt('home_reaction_bug', '🐞 Olamaz!')) ?></span>
              </button>
              <button type="button" class="pm-reaction-pill-btn" data-sound="po" data-emoji="👑" data-text="<?= htmlspecialchars(tt('home_reaction_po', '👑 Harikasın!')) ?>">
                <span><?= htmlspecialchars(tt('home_reaction_po', '👑 Harikasın!')) ?></span>
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
          <a class="pm-btn-secondary" href="rooms.php" style="justify-content:center; width:100%; border-color:rgba(245,158,11,0.4);">
            <span>💬 <?= htmlspecialchars(tt('home_feat_reactions_btn', 'Canlı Odalara Katıl')) ?> →</span>
          </a>
        </div>

        <!-- Social Card 4: Accolades & MVP -->
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
                <span>🔥</span>
                <span><?= htmlspecialchars(tt('award_streak_title', 'Alev Alan')) ?></span>
              </div>
              <div class="pm-award-mini-chip">
                <span>👑</span>
                <span><?= htmlspecialchars(tt('award_mvp_title', "Maçın MVP'si")) ?></span>
              </div>
            </div>
          </div>
          <a class="pm-btn-secondary" href="rooms.php" style="justify-content:center; width:100%; border-color:rgba(16,185,129,0.4);">
            <span>🏅 <?= htmlspecialchars(tt('home_feat_awards_btn', 'Rozetleri İncele')) ?> →</span>
          </a>
        </div>
      </div>
    </section>

    <!-- 5. SECTION: PERFORMANCE STATS & ANALYTICS -->
    <section>
      <div class="pm-stats-banner">
        <div>
          <div class="pm-section-pill" style="margin-bottom:8px;">📊 <?= htmlspecialchars(tt('home_stats_pill', 'PERFORMANS & ANALİZ')) ?></div>
          <h2 style="font-family:var(--pm-font-display); font-size:24px; font-weight:800; margin:0 0 8px 0;"><?= htmlspecialchars(tt('home_stats_title', 'Reflekslerini Milisaniye Seviyesinde Takip Et')) ?></h2>
          <p style="font-size:14px; color:var(--pm-text-muted); margin:0 0 16px 0; line-height:1.55;"><?= htmlspecialchars(tt('home_stats_desc', 'Görsel hafıza gelişimini, ortalama karar verme hızını ve galibiyet serilerini şeffaf istatistiklerle incele.')) ?></p>
          <div style="display:flex; gap:10px;">
            <a class="pm-btn-secondary" href="play.php" style="font-size:13.5px; padding:8px 16px;">⚡ <?= htmlspecialchars(tt('home_stats_solo_btn', 'Pratik Yap')) ?></a>
            <?php if (!empty($userEmail)): ?>
              <a class="pm-btn-secondary" href="games.php" style="font-size:13.5px; padding:8px 16px;">📜 <?= htmlspecialchars(tt('games_title', 'Geçmişim')) ?></a>
            <?php endif; ?>
          </div>
        </div>

        <div class="pm-stats-grid-mini">
          <div class="pm-stat-box">
            <div class="pm-stat-num">~380ms</div>
            <div class="pm-stat-lbl"><?= htmlspecialchars(tt('stat_reaction_time', 'Ort. Tepki')) ?></div>
          </div>
          <div class="pm-stat-box">
            <div class="pm-stat-num" style="color:#10b981;">94.2%</div>
            <div class="pm-stat-lbl"><?= htmlspecialchars(tt('stat_accuracy', 'Doğruluk')) ?></div>
          </div>
          <div class="pm-stat-box">
            <div class="pm-stat-num" style="color:#f59e0b;">25+</div>
            <div class="pm-stat-lbl"><?= htmlspecialchars(tt('stat_max_streak', 'Maks Seri')) ?></div>
          </div>
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
      <p class="pm-cta-desc"><?= htmlspecialchars(tt('home_cta_banner_desc_squad', 'İster tek başına rekor kır, ister arkadaşlarınla takım savaşı başlat. Prismatch tamamen ücretsiz ve tarayıcında anında hazır!')) ?></p>
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

    // Wire up power test buttons for interactive preview
    document.querySelectorAll('.pm-power-test-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        ensureAudio();
        const power = btn.dataset.power;
        const icon = btn.dataset.icon || '💥';
        const title = btn.dataset.title || '';
        const desc = btn.dataset.desc || '';

        if (power === '5050') {
          playTone(660, 0.08, 'sine', 0.08);
          setTimeout(() => playTone(880, 0.12, 'sine', 0.09), 80);
        } else if (power === 'ink') {
          playTone(180, 0.12, 'sawtooth', 0.1);
          setTimeout(() => playTone(120, 0.15, 'triangle', 0.08), 70);
        } else if (power === 'mirror') {
          playTone(400, 0.06, 'sine', 0.07);
          setTimeout(() => playTone(300, 0.06, 'sine', 0.07), 50);
          setTimeout(() => playTone(500, 0.1, 'sine', 0.08), 100);
        } else if (power === 'freeze') {
          playTone(900, 0.05, 'triangle', 0.06);
          setTimeout(() => playTone(1200, 0.07, 'sine', 0.07), 40);
          setTimeout(() => playTone(1500, 0.1, 'sine', 0.08), 80);
        } else if (power === 'blackout') {
          playTone(110, 0.18, 'sawtooth', 0.08);
          setTimeout(() => playTone(80, 0.22, 'square', 0.06), 90);
        } else if (power === 'shield') {
          playTone(520, 0.08, 'triangle', 0.09);
          setTimeout(() => playTone(780, 0.1, 'sine', 0.09), 60);
          setTimeout(() => playTone(1040, 0.16, 'sine', 0.09), 120);
        }

        showHomeToast(`${icon} ${title}: ${desc}`);
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
