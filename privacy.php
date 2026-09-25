<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
  error_reporting(E_ALL);
  @ini_set('display_errors', '1');
  @ini_set('display_startup_errors', '1');
}

require_once __DIR__ . '/i18n.php';

/**
 * Minimal i18n for legal pages.
 * - Full TR + EN
 * - Other languages fall back to EN
 * You can expand translations safely here without touching global i18n.php.
 */
$lang = function_exists('get_lang') ? get_lang() : 'en';
$dir  = function_exists('lang_dir') ? lang_dir($lang) : 'ltr';

function L(string $k, array $vars = []): string {
  global $lang;
  static $dict = null;

  if ($dict === null) {
    $dict = [
      'en' => [
        'title' => 'Privacy Policy',
        'last_updated' => 'Last updated',
        'app' => 'Prismatch',
        'back_home' => 'Back to game',
        'section_1' => '1. Information We Collect',
        's1_1' => '1.1 Personal Data',
        's1_1_body' => "When you choose to sign in using {google} Login, we collect:\n- Email address\n\nWe do not collect your name, profile photo, contacts, or any private {google} data beyond email.\n\nLogin is optional. You can play without signing in.",
        's1_2' => '1.2 Game & Usage Data',
        's1_2_body' => "When you play, we may store:\n- Game results (levels reached, correct/incorrect answers)\n- Reaction times\n- Color selections during rounds\n- Daily Challenge participation\n- Timestamps (start/end time)\n\nWe use this only to display your history, show detailed stats, and support Daily Challenge leaderboards.",
        's1_3' => '1.3 Technical Data',
        's1_3_body' => "We may collect limited technical data:\n- Browser language (for UI language)\n- Timezone (for correct date/time display)\n\nWe do not perform IP-based tracking or fingerprinting.",
        'section_2' => '2. How We Use Your Data',
        's2_body' => "We use data strictly for:\n- Authentication ({google} Login)\n- Saving and displaying game results\n- Providing statistics\n- Enabling Daily Challenge\n\nWe do not sell your data, share it with third parties, or use it for ads/profiling.",
        'section_3' => '3. Data Storage & Security',
        's3_body' => "Data is stored on secure servers. Databases are not publicly accessible and access is restricted to application logic. We use standard security practices to prevent unauthorized access.",
        'section_4' => '4. Cookies & Local Storage',
        's4_body' => "We use:\n- Session cookies (login state)\n- LocalStorage (Daily Challenge participation for guests)\n\nNo advertising or tracking cookies are used.",
        'section_5' => '5. Third-Party Services',
        's5_body' => "{google} Login uses {google} OAuth 2.0. Authentication is handled by {google} and we receive only your email address. {google}’s privacy policy applies independently.",
        'section_6' => '6. Your Rights',
        's6_body' => "Depending on your jurisdiction (GDPR / KVKK), you may request access or deletion of your data and withdraw consent at any time. Contact us using the email below.",
        'section_7' => '7. Data Retention',
        's7_body' => "Game data is retained until you request deletion. If you never sign in, no personal data is stored permanently. Deleted accounts have their associated game data removed.",
        'section_8' => "8. Children’s Privacy",
        's8_body' => "Prismatch is not intended for children under 13. We do not knowingly collect data from children.",
        'section_9' => '9. Changes to This Policy',
        's9_body' => "We may update this Privacy Policy. Any changes will be reflected on this page with an updated date.",
        'section_10' => '10. Contact',
        'contact_body' => "Email: support@prismatch.online\nWebsite: https://prismatch.online",
        'google_note_title' => '{google} OAuth Compliance Note',
        'google_note_body' => "This application’s use and transfer of information received from {google} APIs complies with the {google} API Services User Data Policy, including the Limited Use requirements.",
        'google_privacy' => '{google} Privacy Policy',
        'tos' => 'Terms of Service',
      ],
      'tr' => [
        'title' => 'Gizlilik Politikası',
        'last_updated' => 'Son güncelleme',
        'app' => 'Prismatch',
        'back_home' => 'Oyuna dön',
        'section_1' => '1. Topladığımız Bilgiler',
        's1_1' => '1.1 Kişisel Veriler',
        's1_1_body' => "{google} ile giriş yapmayı seçtiğinizde yalnızca şunu toplarız:\n- E‑posta adresi\n\nAdınız, profil fotoğrafınız, kişileriniz veya e‑posta dışında {google} hesabınıza ait özel verileri toplamayız.\n\nGiriş zorunlu değildir. Giriş yapmadan da oynayabilirsiniz.",
        's1_2' => '1.2 Oyun ve Kullanım Verileri',
        's1_2_body' => "Oynadığınızda şunları kaydedebiliriz:\n- Oyun sonuçları (ulaşılan aşama, doğru/yanlış sayıları)\n- Tepki süreleri\n- Turlarda seçilen renkler\n- Daily Challenge katılımı\n- Zaman damgaları (başlangıç/bitiş)\n\nBu verileri yalnızca oyun geçmişinizi göstermek, detaylı istatistik sunmak ve Daily Challenge liderlik tablosunu sağlamak için kullanırız.",
        's1_3' => '1.3 Teknik Veriler',
        's1_3_body' => "Sınırlı bazı teknik verileri otomatik toplayabiliriz:\n- Tarayıcı dili (arayüz dili için)\n- Zaman dilimi (tarih/saatin doğru gösterimi için)\n\nIP tabanlı takip veya parmak izi (fingerprinting) yapmayız.",
        'section_2' => '2. Verilerinizi Nasıl Kullanırız',
        's2_body' => "Veriler yalnızca şu amaçlarla kullanılır:\n- Kimlik doğrulama ({google} Login)\n- Oyun sonuçlarını kaydetme ve gösterme\n- İstatistik sunma\n- Daily Challenge işlevleri\n\nVerilerinizi satmayız, üçüncü taraflarla paylaşmayız ve reklam/profil amaçlı kullanmayız.",
        'section_3' => '3. Saklama ve Güvenlik',
        's3_body' => "Veriler güvenli sunucularda saklanır. Veritabanı herkese açık değildir; erişim uygulama mantığıyla sınırlıdır. Yetkisiz erişimi engellemek için standart güvenlik önlemleri uygularız.",
        'section_4' => '4. Çerezler ve Local Storage',
        's4_body' => "Şunları kullanırız:\n- Oturum çerezleri (giriş durumu)\n- LocalStorage (misafir kullanıcılar için Daily Challenge tek hak yönetimi)\n\nReklam/izleme çerezleri kullanılmaz.",
        'section_5' => '5. Üçüncü Taraf Hizmetler',
        's5_body' => "{google} ile giriş, {google} OAuth 2.0 üzerinden sağlanır. Kimlik doğrulama {google} tarafından yapılır ve bize yalnızca e‑posta adresiniz iletilir. {google}’ın gizlilik politikası ayrıca geçerlidir.",
        'section_6' => '6. Haklarınız',
        's6_body' => "Bulunduğunuz ülkeye göre (GDPR / KVKK) verilerinize erişim veya silme talep edebilir, rızanızı dilediğiniz zaman geri çekebilirsiniz. Aşağıdaki e‑posta ile bize ulaşın.",
        'section_7' => '7. Saklama Süresi',
        's7_body' => "Oyun verileri siz silme talep edene kadar saklanır. Hiç giriş yapmazsanız kalıcı kişisel veri saklanmaz. Hesap silindiğinde ilişkilendirilmiş oyun verileri de kaldırılır.",
        'section_8' => '8. Çocukların Gizliliği',
        's8_body' => "Prismatch 13 yaş altı çocuklara yönelik değildir. Bilerek çocuklardan veri toplamıyoruz.",
        'section_9' => '9. Politika Değişiklikleri',
        's9_body' => "Bu politika zaman zaman güncellenebilir. Değişiklikler bu sayfada ve güncelleme tarihinde gösterilir.",
        'section_10' => '10. İletişim',
        'contact_body' => "E‑posta: support@prismatch.online\nWeb: https://prismatch.online",
        'google_note_title' => '{google} OAuth Uyum Notu',
        'google_note_body' => "Bu uygulama, {google} API’lerinden alınan verilerin kullanımı ve aktarımında {google} API Services User Data Policy (Limited Use dahil) hükümlerine uygundur.",
        'google_privacy' => '{google} Gizlilik Politikası',
        'tos' => 'Kullanım Şartları',
      ],
    ];
  }

  $bundle = $dict[$lang] ?? $dict['en'];
  $s = $bundle[$k] ?? ($dict['en'][$k] ?? $k);

  foreach ($vars as $kk => $vv) {
    $s = str_replace('{'.$kk.'}', (string)$vv, $s);
  }
  return $s;
}

function google_badge_html(): string {
  return '<span class="google-badge"><img src="google.svg" class="google-icon" alt="Google" /><span class="google-text">Google</span></span>';
}

function h($s): string {
  $safe = htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  return str_replace('{google}', google_badge_html(), $safe);
}

$updated = '2026-01-30';
$seoTitle = L('title') . ' - ' . L('app');
$seoDescription = 'Read the Prismatch privacy policy, data usage, and your rights.';
$seoLangs = function_exists('supported_languages') ? array_keys(supported_languages()) : [];
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
  <title><?= h(L('title')) ?> � <?= h(L('app')) ?></title>
  <link rel="icon" type="image/svg+xml" href="favicon.svg" />
  <?= seo_meta([
    'title' => $seoTitle,
    'description' => $seoDescription,
    'url' => seo_current_url(),
    'image' => '/logo.png',
    'type' => 'article',
    'robots' => 'index,follow',
    'lang' => $lang,
    'site_name' => L('app'),
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
    .wrap{ max-width:980px; margin:0 auto; padding:18px; display:grid; gap:18px; }
    .cardx{ background: rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.12); border-radius:16px; padding:16px; }
    [data-bs-theme="light"] .cardx{ background: rgba(255,255,255,0.95); border-color: rgba(0,0,0,0.08); }
    h1{ margin:0 0 6px 0; font-family:"Baloo 2","Rubik","Segoe UI","Helvetica Neue",sans-serif; }
    h2{ margin:18px 0 10px 0; font-family:"Baloo 2","Rubik","Segoe UI","Helvetica Neue",sans-serif; font-size:16px; }
    .muted{ opacity:.7; font-size:12px; }
    .pre{ white-space:pre-line; line-height:1.55; }
    .links{ display:flex; gap:10px; flex-wrap:wrap; }
    .google-badge{ display:inline-flex; align-items:center; gap:6px; font-weight:600; }
    .google-icon{ width:16px; height:16px; display:inline-block; }
    .google-text{ line-height:1; }
  </style>
</head>
<body class="pm-has-fixed-header">
  <?php include __DIR__ . '/header.php'; ?>
  <main class="wrap">
    <?php $google = '{google}'; ?>
    <div class="cardx">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
          <h1><?= h(L('title')) ?></h1>
          <div class="muted"><?= h(L('last_updated')) ?>: <?= h($updated) ?></div>
        </div>
        <div class="links">
          <a class="btn btn-outline-secondary" href="index.php"><?= h(L('back_home')) ?></a>
          <a class="btn btn-outline-secondary" href="terms.php"><?= h(L('tos')) ?></a>
        </div>
      </div>
    </div>

    <div class="cardx">
      <h2><?= h(L('section_1')) ?></h2>
      <div class="pre">
        <strong><?= h(L('s1_1')) ?></strong>
        <?= "\n" . h(L('s1_1_body', ['google' => $google])) ?>
      </div>
      <div class="pre" style="margin-top:10px">
        <strong><?= h(L('s1_2')) ?></strong>
        <?= "\n" . h(L('s1_2_body')) ?>
      </div>
      <div class="pre" style="margin-top:10px">
        <strong><?= h(L('s1_3')) ?></strong>
        <?= "\n" . h(L('s1_3_body')) ?>
      </div>
    </div>

    <div class="cardx">
      <h2><?= h(L('section_2')) ?></h2>
      <div class="pre"><?= h(L('s2_body', ['google' => $google])) ?></div>
    </div>

    <div class="cardx">
      <h2><?= h(L('section_3')) ?></h2>
      <div class="pre"><?= h(L('s3_body')) ?></div>
    </div>

    <div class="cardx">
      <h2><?= h(L('section_4')) ?></h2>
      <div class="pre"><?= h(L('s4_body')) ?></div>
    </div>

    <div class="cardx">
      <h2><?= h(L('section_5')) ?></h2>
      <div class="pre"><?= h(L('s5_body', ['google' => $google])) ?></div>
    </div>

    <div class="cardx">
      <h2><?= h(L('section_6')) ?></h2>
      <div class="pre"><?= h(L('s6_body')) ?></div>
    </div>

    <div class="cardx">
      <h2><?= h(L('section_7')) ?></h2>
      <div class="pre"><?= h(L('s7_body')) ?></div>
    </div>

    <div class="cardx">
      <h2><?= h(L('section_8')) ?></h2>
      <div class="pre"><?= h(L('s8_body')) ?></div>
    </div>

    <div class="cardx">
      <h2><?= h(L('section_9')) ?></h2>
      <div class="pre"><?= h(L('s9_body')) ?></div>
    </div>

    <div class="cardx">
      <h2><?= h(L('section_10')) ?></h2>
      <div class="pre"><?= h(L('contact_body')) ?></div>
    </div>

    <div class="cardx">
      <h2><?= h(L('google_note_title', ['google' => $google])) ?></h2>
      <div class="pre"><?= h(L('google_note_body', ['google' => $google])) ?></div>
      <div style="margin-top:10px">
        <a class="btn btn-outline-secondary" href="https://policies.google.com/privacy" rel="noopener" target="_blank"><?= h(L('google_privacy', ['google' => $google])) ?></a>
      </div>
    </div>
  </main>

  <?php if (is_file(__DIR__ . '/footer.php')) include __DIR__ . '/footer.php'; ?>
</body>
</html>

