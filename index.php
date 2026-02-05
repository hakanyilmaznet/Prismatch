<?php
require_once __DIR__ . '/bootstrap.php';
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
  <link href="css/bootstrap.min.css" rel="stylesheet" />
  <link href="css/theme.css" rel="stylesheet" />
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
<body>
<?php include __DIR__ . '/header.php'; ?>

<main class="container py-4">
  <section class="row align-items-center g-4 mb-4">
    <div class="col-12 col-lg-7">
      <span class="badge text-bg-secondary mb-2"><?= htmlspecialchars(tt('home_showcase_badge', 'Mini eðitim • 3 adým')) ?></span>
      <h1 class="display-5 fw-bold mb-2"><?= htmlspecialchars(tt('home_hero_title', 'Renkleri hatýrla, doðru tonu yakala.')) ?></h1>
      <p class="lead mb-2"><?= htmlspecialchars(tt('home_hero_subtitle', 'Prismatch, hýzla deðiþen renkleri kýsa süreli hafýzanda tutmaný ister. Her turda süre kýsalýr, grid yoðunlaþýr ve tek bir doðru renk seni bir sonraki aþamaya taþýr.')) ?></p>
      <p class="text-muted mb-3"><?= htmlspecialchars(tt('home_creative', 'Renklerin hafýzada þiir gibi kaldýðý bir ritme gir: Ýpucu kaybolur, zihnin tonu yakalar. Her doðru seçim, bir sonraki sahneyi açar.')) ?></p>
      <div class="d-flex flex-wrap gap-2 mb-3">
        <a class="btn btn-primary" href="play.php"><?= htmlspecialchars(tt('home_cta_play', 'Hemen oyna')) ?></a>
        <a class="btn btn-outline-secondary" href="play.php?daily=1"><?= htmlspecialchars(tt('home_cta_daily', 'Günlük Meydan Okuma')) ?></a>
        <a class="btn btn-outline-secondary" href="daily_leaderboard.php"><?= htmlspecialchars(tt('daily_leaderboard_title', 'Leaderboard')) ?></a>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <span class="badge text-bg-light"><?= htmlspecialchars(tt('home_benefit_1_title', 'Zihin açan kýsa turlar')) ?></span>
        <span class="badge text-bg-light"><?= htmlspecialchars(tt('home_benefit_2_title', 'Günlük meydan okuma')) ?></span>
        <span class="badge text-bg-light"><?= htmlspecialchars(tt('home_benefit_3_title', 'Çok dil ve istatistik')) ?></span>
      </div>
    </div>
    <div class="col-12 col-lg-5">
      <div class="card">
        <div class="card-body">
          <h2 class="h5 mb-2"><?= htmlspecialchars(tt('home_showcase_title', 'Bir tur nasýl iþler?')) ?></h2>
          <p class="text-muted mb-3"><?= htmlspecialchars(tt('home_showcase_body', 'Hedef rengi gör, hafýzanda tut ve 5 saniye içinde gridde bul.')) ?></p>
          <ol class="list-group list-group-numbered">
            <li class="list-group-item"><?= htmlspecialchars(tt('home_step_1_title', 'Hedef rengi izle')) ?> — <?= htmlspecialchars(tt('home_step_1_body', 'Ekranda kýsa süre gösterilen rengi dikkatle aklýnda tut.')) ?></li>
            <li class="list-group-item"><?= htmlspecialchars(tt('home_step_2_title', 'Gridde doðru rengi seç')) ?> — <?= htmlspecialchars(tt('home_step_2_body', 'Zaman bitmeden, hatýrladýðýn rengi 9 seçenek arasýnda bul.')) ?></li>
            <li class="list-group-item"><?= htmlspecialchars(tt('home_step_3_title', 'Aþamalarý geç, skoru yükselt')) ?> — <?= htmlspecialchars(tt('home_step_3_body', 'Her doðru eþleþme seni bir üst aþamaya taþýr.')) ?></li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="mb-4">
    <h2 class="h4 mb-3"><?= htmlspecialchars(tt('home_intro_title', 'Proje ne saðlýyor?')) ?></h2>
    <div class="row row-cols-1 row-cols-md-2 g-3">
      <div class="col">
        <div class="card h-100">
          <div class="card-body">
            <h3 class="h6"><?= htmlspecialchars(tt('home_intro_headline', 'Hýzlý odak & hafýza egzersizi')) ?></h3>
            <p class="text-muted mb-0"><?= htmlspecialchars(tt('home_intro_body', 'Kýsa süreli hatýrlama ve dikkat kontrolünü ölçen mikro turlar, gün içinde pratik yapmak için ideal.')) ?></p>
          </div>
        </div>
      </div>
      <div class="col">
        <div class="card h-100">
          <div class="card-body">
            <h3 class="h6"><?= htmlspecialchars(tt('home_intro_headline2', 'Geri bildirim ve ilerleme')) ?></h3>
            <p class="text-muted mb-0"><?= htmlspecialchars(tt('home_intro_body2', 'Aþama, doðru eþleþme ve süre kayýtlarýyla geliþimini takip edebilir, günlük hedef koyabilirsin.')) ?></p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="mb-4">
    <h2 class="h4 mb-3"><?= htmlspecialchars(tt('home_benefits_title', 'Öne çýkan faydalar')) ?></h2>
    <div class="row row-cols-1 row-cols-md-3 g-3">
      <div class="col">
        <div class="card h-100">
          <div class="card-body">
            <h3 class="h6"><?= htmlspecialchars(tt('home_benefit_1_title', 'Zihin açan kýsa turlar')) ?></h3>
            <p class="text-muted mb-0"><?= htmlspecialchars(tt('home_benefit_1_body', 'Her tur birkaç saniye sürer; kýsa molalarda bile kolayca oynanýr.')) ?></p>
          </div>
        </div>
      </div>
      <div class="col">
        <div class="card h-100">
          <div class="card-body">
            <h3 class="h6"><?= htmlspecialchars(tt('home_benefit_2_title', 'Günlük meydan okuma')) ?></h3>
            <p class="text-muted mb-0"><?= htmlspecialchars(tt('home_benefit_2_body', 'Her gün tek deneme hakkýyla odak ve süre yönetimini geliþtirir.')) ?></p>
          </div>
        </div>
      </div>
      <div class="col">
        <div class="card h-100">
          <div class="card-body">
            <h3 class="h6"><?= htmlspecialchars(tt('home_benefit_3_title', 'Çok dil ve istatistik')) ?></h3>
            <p class="text-muted mb-0"><?= htmlspecialchars(tt('home_benefit_3_body', 'Farklý dillerde oynar, performansýný kayýt altýna alýrsýn.')) ?></p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="mb-4">
    <h2 class="h4 mb-3"><?= htmlspecialchars(tt('home_score_title', 'Scoring algorithm')) ?></h2>
    <div class="row row-cols-1 row-cols-md-3 g-3">
      <div class="col">
        <div class="card h-100">
          <div class="card-body">
            <strong class="d-block mb-2"><?= htmlspecialchars(tt('home_score_rule_1', 'Level impact (70%)')) ?></strong>
            <p class="text-muted mb-2"><?= htmlspecialchars(tt('home_score_rule_1_body', 'The higher the stage you reach, the higher your score.')) ?></p>
            <code class="d-block">levelFactor = reachedLevel / 50</code>
          </div>
        </div>
      </div>
      <div class="col">
        <div class="card h-100">
          <div class="card-body">
            <strong class="d-block mb-2"><?= htmlspecialchars(tt('home_score_rule_2', 'Speed impact (30%)')) ?></strong>
            <p class="text-muted mb-2"><?= htmlspecialchars(tt('home_score_rule_2_body', 'For correct answers, we use target show time / response time ratio.')) ?></p>
            <code class="d-block">timeFactor = min(1, avgRatio / 1.5)</code>
          </div>
        </div>
      </div>
      <div class="col">
        <div class="card h-100">
          <div class="card-body">
            <strong class="d-block mb-2"><?= htmlspecialchars(tt('home_score_rule_3', 'Final formula')) ?></strong>
            <p class="text-muted mb-2"><?= htmlspecialchars(tt('home_score_rule_3_body', 'Level and speed are scaled to a 0–1000 score.')) ?></p>
            <code class="d-block">score = round(1000 * (0.7*levelFactor + 0.3*timeFactor))</code>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<?php if (is_file(__DIR__ . '/footer.php')) include __DIR__ . '/footer.php'; ?>
</body>
</html>


