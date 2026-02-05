<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
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
  return '<span class="badge text-bg-light"><img src="google.svg" class="align-text-bottom me-1" width="16" height="16" alt="Google" />Google</span>';
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
  <link href="css/bootstrap.min.css" rel="stylesheet" />
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
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>
<main class="container py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
      <h1 class="h3 mb-1"><?= h(L('title')) ?></h1>
      <div class="text-muted small"><?= h(L('last_updated')) ?>: <?= h($updated) ?></div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="play.php">
        <img class="bi-icon" src="bootstrap-icons/arrow-left.svg" alt="" aria-hidden="true" />
        <?= h(L('back_home')) ?>
      </a>
      <a class="btn btn-outline-secondary btn-sm" href="privacy.php">
        <img class="bi-icon" src="bootstrap-icons/shield-lock.svg" alt="" aria-hidden="true" />
        <?= h(L('privacy')) ?>
      </a>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <p class="mb-3"><?= h(L('intro')) ?></p>

      <h2><?= h(L('s1')) ?></h2>
      <p><?= h(L('s1_body')) ?></p>

      <h2><?= h(L('s2')) ?></h2>
      <p><?= h(L('s2_body')) ?></p>

      <h2><?= h(L('s3')) ?></h2>
      <p><?= h(L('s3_body')) ?></p>

      <h2><?= h(L('s4')) ?></h2>
      <p><?= h(L('s4_body')) ?></p>

      <h2><?= h(L('s5')) ?></h2>
      <p><?= h(L('s5_body')) ?></p>

      <h2><?= h(L('s6')) ?></h2>
      <p><?= h(L('s6_body')) ?></p>

      <h2><?= h(L('s7')) ?></h2>
      <p><?= h(L('s7_body')) ?></p>

      <h2><?= h(L('s8')) ?></h2>
      <p><?= h(L('s8_body')) ?></p>

      <h2><?= h(L('s9')) ?></h2>
      <p><?= h(L('s9_body')) ?></p>

      <h2><?= h(L('s10')) ?></h2>
      <p><?= h(L('s10_body')) ?></p>

      <h2><?= h(L('s11')) ?></h2>
      <p class="mb-0"><?= h(L('s11_body')) ?></p>
    </div>
  </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
