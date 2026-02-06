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
 */
$lang = function_exists('get_lang') ? get_lang() : 'en';
$dir  = function_exists('lang_dir') ? lang_dir($lang) : 'ltr';

function L(string $k, array $vars = []): string {
  global $lang;
  static $dict = null;

  if ($dict === null) {
    $dict = [
      'en' => [
        'title' => 'Terms of Service',
        'last_updated' => 'Last updated',
        'app' => 'Prismatch',
        'back_home' => 'Back to game',
        'privacy' => 'Privacy Policy',
        'intro' => "By accessing or using Prismatch on prismatch.online, you agree to these Terms of Service.",
        's1' => '1. Service Description',
        's1_body' => "Prismatch is a color-matching memory/reaction game. You can play as a guest. If you choose to sign in with {google}, you can save your results and view your history.",
        's2' => '2. Eligibility',
        's2_body' => "The service is not intended for children under 13.",
        's3' => '3. Accounts & Login',
        's3_body' => "Login is optional. If you sign in via {google}, we store only your email address. You are responsible for maintaining the security of your account session on your device.",
        's4' => '4. Acceptable Use',
        's4_body' => "You agree not to misuse the service, attempt to disrupt it, reverse engineer, or use automated methods to manipulate leaderboards or results.",
        's5' => '5. Daily Challenge & Leaderboards',
        's5_body' => "Daily Challenge may be limited to one attempt per day. We may apply anti-abuse measures. Leaderboards are provided as-is and may be adjusted or reset if abuse is detected.",
        's6' => '6. Intellectual Property',
        's6_body' => "All content and branding of Prismatch are owned by the project owner. You may not copy or redistribute without permission.",
        's7' => '7. Disclaimer',
        's7_body' => "The service is provided “as is” without warranties. We do not guarantee uninterrupted availability or error-free operation.",
        's8' => '8. Limitation of Liability',
        's8_body' => "To the maximum extent permitted by law, we are not liable for indirect or consequential damages arising from use of the service.",
        's9' => '9. Termination',
        's9_body' => "We may suspend or terminate access if we reasonably believe you violated these terms or abused the service.",
        's10' => '10. Changes',
        's10_body' => "We may update these Terms. Continued use after changes indicates acceptance.",
        's11' => '11. Contact',
        's11_body' => "Email: support@prismatch.online\nWebsite: https://prismatch.online",
      ],
      'tr' => [
        'title' => 'Kullanım Şartları',
        'last_updated' => 'Son güncelleme',
        'app' => 'Prismatch',
        'back_home' => 'Oyuna dön',
        'privacy' => 'Gizlilik Politikası',
        'intro' => "prismatch.online üzerinden Prismatch’i kullanarak bu Kullanım Şartları’nı kabul etmiş olursunuz.",
        's1' => '1. Hizmet Tanımı',
        's1_body' => "Prismatch, renk eşleştirme tabanlı bir hafıza/reaksiyon oyunudur. Misafir olarak oynayabilirsiniz. {google} ile giriş yapmayı seçerseniz sonuçlarınızı kaydedebilir ve geçmişinizi görüntüleyebilirsiniz.",
        's2' => '2. Uygunluk',
        's2_body' => "Hizmet 13 yaş altı çocuklara yönelik değildir.",
        's3' => '3. Hesaplar ve Giriş',
        's3_body' => "Giriş zorunlu değildir. {google} ile giriş yaparsanız yalnızca e‑posta adresiniz saklanır. Kullandığınız cihazdaki oturum güvenliğinden siz sorumlusunuz.",
        's4' => '4. Kabul Edilebilir Kullanım',
        's4_body' => "Hizmeti kötüye kullanmayacağınızı; hizmeti bozma girişiminde bulunmayacağınızı, tersine mühendislik yapmayacağınızı veya leaderboard/sonuçları manipüle etmeye yönelik otomasyon kullanmayacağınızı kabul edersiniz.",
        's5' => '5. Daily Challenge ve Leaderboard',
        's5_body' => "Daily Challenge günde 1 hak ile sınırlı olabilir. Kötüye kullanımı önleyici önlemler uygulayabiliriz. Leaderboard ‘olduğu gibi’ sunulur; suistimal tespitinde düzenleme/sıfırlama yapılabilir.",
        's6' => '6. Fikri Mülkiyet',
        's6_body' => "Prismatch içeriği ve markası proje sahibine aittir. İzin olmadan kopyalayamaz veya yeniden dağıtamazsınız.",
        's7' => '7. Sorumluluk Reddi',
        's7_body' => "Hizmet “olduğu gibi” sunulur; kesintisiz erişim veya hatasız çalışma garantisi verilmez.",
        's8' => '8. Sorumluluğun Sınırlandırılması',
        's8_body' => "Kanunun izin verdiği azami ölçüde, hizmetin kullanımından doğan dolaylı/sonuçsal zararlardan sorumlu olmayız.",
        's9' => '9. Sonlandırma',
        's9_body' => "Şartların ihlal edildiğini veya hizmetin suistimal edildiğini makul şekilde düşünürsek erişimi askıya alabilir veya sonlandırabiliriz.",
        's10' => '10. Değişiklikler',
        's10_body' => "Bu şartları güncelleyebiliriz. Değişikliklerden sonra hizmeti kullanmaya devam etmeniz kabul anlamına gelir.",
        's11' => '11. İletişim',
        's11_body' => "E‑posta: support@prismatch.online\nWeb: https://prismatch.online",
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
$seoDescription = 'Review the Prismatch terms of service and acceptable use guidelines.';
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
  <link href="css/bootstrap.min.css" rel="stylesheet" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <title><?= h(L('title')) ?> — <?= h(L('app')) ?></title>
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
    :root,
    [data-bs-theme="dark"]{ --bg:#0b0d12; --card:rgba(255,255,255,.08); --bd:rgba(255,255,255,.18); --mut:rgba(237,242,255,.68); --link:#ff7d5d; --text:#ffffff; }
    [data-bs-theme="light"]{ --bg:#f6f3ee; --card:rgba(255,255,255,.9); --bd:rgba(27,31,42,.12); --mut:rgba(27,31,42,.65); --link:#e4573f; --text:#1b1f2a; }
    body{
      margin:0;
      font-family:"Plus Jakarta Sans","Segoe UI","Helvetica Neue",sans-serif;
      background:
        radial-gradient(1100px 640px at 12% 12%, rgba(61,214,160,.18), transparent 60%),
        radial-gradient(900px 520px at 85% 25%, rgba(255,125,93,.20), transparent 62%),
        radial-gradient(900px 700px at 50% 90%, rgba(244,182,106,.14), transparent 65%),
        var(--bg);
      color:var(--text, #fff);
      padding:18px;
    }
    body::after{
      content:"";
      position:fixed;
      inset:0;
      background-image: radial-gradient(rgba(255,255,255,0.05) 1px, transparent 1px);
      background-size: 22px 22px;
      opacity:.18;
      pointer-events:none;
      z-index:-1;
    }
    a{ color:var(--link); text-decoration:none; }
    .wrap{ max-width:980px; margin:0 auto; }
    .card{
      background:linear-gradient(160deg, rgba(255,255,255,.10), rgba(255,255,255,.04));
      border:1px solid var(--bd);
      border-radius:18px;
      padding:16px;
      margin:14px 0;
      box-shadow: 0 26px 60px rgba(0,0,0,.35);
    }
    .top{ display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; }
    .btn{
      display:inline-flex; align-items:center; gap:8px;
      padding:10px 14px; border-radius:999px;
      background:linear-gradient(180deg, rgba(255,255,255,.12), rgba(255,255,255,.06));
      border:1px solid rgba(255,255,255,.18);
      color:#fff;
    }
    .google-badge{ display:inline-flex; align-items:center; gap:6px; font-weight:600; }
    .google-icon{ width:16px; height:16px; display:inline-block; }
    .google-text{ line-height:1; }
    h1{ margin:0 0 6px 0; font-size:24px; font-family:"Space Grotesk","Segoe UI","Helvetica Neue",sans-serif; }
    h2{ margin:18px 0 10px 0; font-size:16px; font-family:"Space Grotesk","Segoe UI","Helvetica Neue",sans-serif; }
    .muted{ color:var(--mut); font-size:12px; }
    .pre{ white-space:pre-line; line-height:1.55; }
    .links{ display:flex; gap:10px; flex-wrap:wrap; }
    hr{ border:0; border-top:1px solid rgba(255,255,255,.12); margin:14px 0; }
    @media (max-width: 640px){
      .links{ width:100%; }
      .links .btn{ width:100%; justify-content:center; }
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>
  <div class="wrap">
    <div class="top">
      <div>
        <h1><?= h(L('title')) ?></h1>
        <div class="muted"><?= h(L('last_updated')) ?>: <?= h($updated) ?></div>
      </div>
        <div class="links">
        <a class="btn" href="play.php">
          <img class="bi-icon" src="bootstrap-icons/arrow-left.svg" alt="" aria-hidden="true" />
          <?= h(L('back_home')) ?>
        </a>
        <a class="btn" href="privacy.php">
          <img class="bi-icon" src="bootstrap-icons/shield-lock.svg" alt="" aria-hidden="true" />
          <?= h(L('privacy')) ?>
        </a>
        </div>
    </div>

    <div class="card">
      <div class="pre"><?= h(L('intro')) ?></div>

      <h2><?= h(L('s1')) ?></h2>
      <div class="pre"><?= h(L('s1_body')) ?></div>

      <h2><?= h(L('s2')) ?></h2>
      <div class="pre"><?= h(L('s2_body')) ?></div>

      <h2><?= h(L('s3')) ?></h2>
      <div class="pre"><?= h(L('s3_body')) ?></div>

      <h2><?= h(L('s4')) ?></h2>
      <div class="pre"><?= h(L('s4_body')) ?></div>

      <h2><?= h(L('s5')) ?></h2>
      <div class="pre"><?= h(L('s5_body')) ?></div>

      <h2><?= h(L('s6')) ?></h2>
      <div class="pre"><?= h(L('s6_body')) ?></div>

      <h2><?= h(L('s7')) ?></h2>
      <div class="pre"><?= h(L('s7_body')) ?></div>

      <h2><?= h(L('s8')) ?></h2>
      <div class="pre"><?= h(L('s8_body')) ?></div>

      <h2><?= h(L('s9')) ?></h2>
      <div class="pre"><?= h(L('s9_body')) ?></div>

      <h2><?= h(L('s10')) ?></h2>
      <div class="pre"><?= h(L('s10_body')) ?></div>

      <h2><?= h(L('s11')) ?></h2>
      <div class="pre"><?= h(L('s11_body')) ?></div>
    </div>

    <?php include __DIR__ . '/footer.php'; ?>
  </div>
</body>
</html>



