<?php
require_once __DIR__ . '/bootstrap.php';
if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
  error_reporting(E_ALL);
  @ini_set('display_errors', '1');
  @ini_set('display_startup_errors', '1');
}

require_once __DIR__ . '/db.php';

if (!defined('DEBUG_MODE') || DEBUG_MODE !== true) {
  http_response_code(404);
  exit;
}

header('X-Robots-Tag: noindex, nofollow', true);

$result = null;
$error = null;

$tables = [
  'users' => 'users',
  'games' => 'games',
  'rounds' => 'rounds',
  'daily_scores' => 'daily_scores',
  'rooms' => 'rooms',
  'room_players' => 'room_players',
  'room_rounds' => 'room_rounds',
  'room_events' => 'room_events',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $confirm = isset($_POST['confirm']) ? trim((string)$_POST['confirm']) : '';
  $selected = isset($_POST['tables']) && is_array($_POST['tables']) ? $_POST['tables'] : [];

  if ($confirm !== 'DELETE') {
    $error = 'Onay metni yanlış. Lütfen kutuya DELETE yazın.';
  } elseif (empty($selected)) {
    $error = 'Lütfen en az bir tablo seçin.';
  } else {
    $safe = [];
    foreach ($selected as $t) {
      $t = (string)$t;
      if (isset($tables[$t])) $safe[] = $tables[$t];
    }

    if (empty($safe)) {
      $error = 'Seçilen tablolar geçersiz.';
    } else {
      try {
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

        $ordered = [
          'room_events',
          'room_rounds',
          'room_players',
          'rooms',
          'rounds',
          'games',
          'daily_scores',
          'users',
        ];
        $toClear = array_values(array_intersect($ordered, $safe));

        foreach ($toClear as $t) {
          $pdo->exec('TRUNCATE TABLE `' . $t . '`');
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        $pdo->commit();
        $result = 'Seçilen tablolar başarıyla temizlendi.';
      } catch (Throwable $e) {
        if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
        $error = 'İşlem başarısız: ' . $e->getMessage();
      }
    }
  }
}
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <meta name="robots" content="noindex,nofollow" />
  <link href="css/bootstrap.min.css" rel="stylesheet" />
  <link href="css/theme.css" rel="stylesheet" />
  <title>DB Temizleme - Prismatch</title>
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>
<main class="container py-4">
  <div class="row g-3">
    <div class="col-12">
      <div class="card border-danger">
        <div class="card-body">
          <h1 class="h4 mb-2 text-danger">Veritabanı Tablolarını Temizle</h1>
          <p class="text-muted mb-0">
            Bu işlem geri alınamaz. Tüm tablolar boşaltılacaktır.
          </p>
        </div>
      </div>
    </div>

    <?php if ($result): ?>
      <div class="col-12">
        <div class="alert alert-success mb-0" role="alert"><?= htmlspecialchars($result) ?></div>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="col-12">
        <div class="alert alert-danger mb-0" role="alert"><?= htmlspecialchars($error) ?></div>
      </div>
    <?php endif; ?>

    <div class="col-12 col-lg-6">
      <form method="post" class="card">
        <div class="card-body">
          <div class="mb-3">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="selectAll" />
              <label class="form-check-label" for="selectAll">Tümünü seç</label>
            </div>
          </div>
          <div class="mb-3 d-flex flex-wrap gap-3">
            <?php foreach ($tables as $key => $label): ?>
              <div class="form-check">
                <input class="form-check-input table-check" type="checkbox" name="tables[]" id="t_<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($key) ?>" />
                <label class="form-check-label" for="t_<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></label>
              </div>
            <?php endforeach; ?>
          </div>
          <label class="form-label" for="confirmInput">Onay için <strong>DELETE</strong> yazın</label>
          <input class="form-control" id="confirmInput" name="confirm" type="text" autocomplete="off" />
          <button class="btn btn-danger mt-3" type="submit">Tüm Tabloları Boşalt</button>
        </div>
      </form>
    </div>
  </div>
</main>
<?php include __DIR__ . '/footer.php'; ?>
<script>
(function(){
  const selectAll = document.getElementById('selectAll');
  const checks = Array.from(document.querySelectorAll('.table-check'));
  if (!selectAll || !checks.length) return;

  function syncSelectAll(){
    const allChecked = checks.every(c => c.checked);
    const noneChecked = checks.every(c => !c.checked);
    selectAll.checked = allChecked;
    selectAll.indeterminate = !allChecked && !noneChecked;
  }

  selectAll.addEventListener('change', () => {
    checks.forEach(c => { c.checked = selectAll.checked; });
    syncSelectAll();
  });

  checks.forEach(c => {
    c.addEventListener('change', syncSelectAll);
  });

  syncSelectAll();
})();
</script>
</body>
</html>



