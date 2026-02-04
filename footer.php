<?php
declare(strict_types=1);

$lang = function_exists('get_lang') ? get_lang() : 'en';
$dir  = function_exists('lang_dir') ? lang_dir($lang) : 'ltr';

function _L_foot($k){
  global $lang;
  $d = [
    'en' => ['privacy'=>'Privacy','terms'=>'Terms','copyright'=>'© {y} Prismatch'],
    'tr' => ['privacy'=>'Gizlilik','terms'=>'Şartlar','copyright'=>'© {y} Prismatch'],
  ];
  $bundle = $d[$lang] ?? $d['en'];
  $s = $bundle[$k] ?? ($d['en'][$k] ?? $k);
  return str_replace('{y}', (string)date('Y'), $s);
}

function _h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<style>
  :root{ --pm-footer-offset: 0px; }
  body.pm-has-fixed-footer{ padding-bottom: var(--pm-footer-offset); }
  /* Fixed, centered footer — avoids "left floating" look and works with the game card layout */
  .pm-footer{
    position:fixed; left:0; right:0; bottom:0;
    padding:10px 16px;
    z-index: 50;
    pointer-events:none;
  }
  .pm-footer__inner{
    max-width: 1120px;
    margin: 0 auto;
    background: linear-gradient(180deg, rgba(255,255,255,.9), rgba(255,255,255,.76));
    border: 1px solid rgba(0,0,0,.08);
    border-radius: 999px;
    padding: 10px 14px;
    display:flex;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
    align-items:center;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    box-shadow: 0 16px 40px rgba(10,12,18,.16);
    pointer-events:auto;
  }
  [data-bs-theme="dark"] .pm-footer__inner{
    background: linear-gradient(180deg, rgba(12,14,20,.92), rgba(12,14,20,.8));
    border-color: rgba(255,255,255,.12);
    box-shadow: 0 18px 44px rgba(0,0,0,.45);
  }
  .pm-footer__links{ display:flex; gap:14px; flex-wrap:wrap; font-size:12px; }
  .pm-footer__links a{ color:#ff7d5d; text-decoration:none; font-weight:600; }
  [data-bs-theme="dark"] .pm-footer__links a{ color:#ffd08a; }
  .pm-footer__copy{ opacity:.75; font-size:12px; white-space:nowrap; color:#1d222f; }
  [data-bs-theme="dark"] .pm-footer__copy{ color: rgba(255,255,255,.78); }
</style>

<div class="pm-footer" dir="<?= _h($dir) ?>">
  <div class="pm-footer__inner">
    <div class="pm-footer__copy"><?= _h(_L_foot('copyright')) ?></div>
    <div class="pm-footer__links">
      <a href="privacy.php"><?= _h(_L_foot('privacy')) ?></a>
      <a href="terms.php"><?= _h(_L_foot('terms')) ?></a>
    </div>
  </div>
</div>
<script src="js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  const footer = document.querySelector('.pm-footer');
  if (!footer || !document.body) return;

  function setOffset(){
    const rect = footer.getBoundingClientRect();
    const extra = 12;
    const offset = Math.max(0, Math.ceil(rect.height + extra));
    document.documentElement.style.setProperty('--pm-footer-offset', offset + 'px');
  }

  document.body.classList.add('pm-has-fixed-footer');
  setOffset();
  window.addEventListener('resize', setOffset, {passive:true});
})();
</script>
<?php if (function_exists('pm_debug_enabled') && pm_debug_enabled() && function_exists('pm_should_output_debug') && pm_should_output_debug()): ?>
  <?php $GLOBALS['PM_DEBUG_LOGGED'] = true; ?>
  <script>
  (function(){
    try{
      var errs = window.__pmDebugErrors || <?=
        json_encode((isset($GLOBALS['PM_DEBUG_ERRORS']) ? $GLOBALS['PM_DEBUG_ERRORS'] : []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
      ?>;
      if (Array.isArray(errs) && errs.length){
        errs.forEach(function(e){
          console.error('[PHP]', e.message, '@', e.file + ':' + e.line, '(type ' + e.type + ')');
        });
      }
    }catch(_){}
  })();
  </script>
<?php endif; ?>
