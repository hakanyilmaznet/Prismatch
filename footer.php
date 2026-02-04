<?php
declare(strict_types=1);

$lang = function_exists('get_lang') ? get_lang() : 'en';
$dir  = function_exists('lang_dir') ? lang_dir($lang) : 'ltr';

function _L_foot(string $k): string {
  global $lang;
  $d = [
    'en' => ['privacy'=>'Privacy','terms'=>'Terms','copyright'=>'© {y} Prismatch'],
    'tr' => ['privacy'=>'Gizlilik','terms'=>'Şartlar','copyright'=>'© {y} Prismatch'],
  ];
  $bundle = $d[$lang] ?? $d['en'];
  $s = $bundle[$k] ?? ($d['en'][$k] ?? $k);
  return str_replace('{y}', (string)date('Y'), $s);
}

function _h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<footer class="border-top bg-body-tertiary mt-4" dir="<?= _h($dir) ?>">
  <div class="container py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
    <small class="text-muted"><?= _h(_L_foot('copyright')) ?></small>
    <div class="d-flex gap-3">
      <a class="link-secondary" href="privacy.php"><?= _h(_L_foot('privacy')) ?></a>
      <a class="link-secondary" href="terms.php"><?= _h(_L_foot('terms')) ?></a>
    </div>
  </div>
</footer>
<script src="js/bootstrap.bundle.min.js"></script>
<?php if (function_exists('pm_debug_enabled') && pm_debug_enabled() && function_exists('pm_should_output_debug') && pm_should_output_debug()): ?>
  <?php $GLOBALS['PM_DEBUG_LOGGED'] = true; ?>
  <script>
  (function(){
    try{
      var errs = window.__pmDebugErrors || <?=
        json_encode($GLOBALS['PM_DEBUG_ERRORS'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
      ?>;
      if (Array.isArray(errs) && errs.length){
        errs.forEach(function(e){
          console.error('[PHP]', e.message, '@', e.file + ':' + e.line, '(type ' + e.type + ')');
        });
      }
    }catch(_){ }
  })();
  </script>
<?php endif; ?>
