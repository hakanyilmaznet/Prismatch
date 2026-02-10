<?php
declare(strict_types=1);

$lang = function_exists('get_lang') ? get_lang() : 'en';
$dir  = function_exists('lang_dir') ? lang_dir($lang) : 'ltr';

function _L_foot(string $k): string {
  global $lang;
  $d = [
    'en' => ['privacy'=>'Privacy','terms'=>'Terms','copyright'=>'© {y} Prismatch'],
    'tr' => ['privacy'=>'Gizlilik','terms'=>'Þartlar','copyright'=>'© {y} Prismatch'],
  ];
  $bundle = $d[$lang] ?? $d['en'];
  $s = $bundle[$k] ?? ($d['en'][$k] ?? $k);
  return str_replace('{y}', (string)date('Y'), $s);
}

function _h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<footer class="border-top bg-body-tertiary mt-5" dir="<?= _h($dir) ?>">
  <div class="container py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="text-body-secondary small"><?= _h(_L_foot('copyright')) ?></div>
    <div class="d-flex gap-3 small">
      <a class="link-secondary text-decoration-none" href="privacy.php"><?= _h(_L_foot('privacy')) ?></a>
      <a class="link-secondary text-decoration-none" href="terms.php"><?= _h(_L_foot('terms')) ?></a>
    </div>
  </div>
</footer>
<script src="js/bootstrap.bundle.min.js"></script>
<?php if (function_exists('pm_debug_enabled') && pm_debug_enabled() && function_exists('pm_should_output_debug') && pm_should_output_debug()): ?>
  <?php $GLOBALS['PM_DEBUG_LOGGED'] = true; ?>
  <?php $pmErrs = $GLOBALS['PM_DEBUG_ERRORS'] ?? []; ?>
  <script>
  (function(){
    try{
      var errs = window.__pmDebugErrors || <?=
        json_encode($pmErrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
      ?>;
      if (Array.isArray(errs) && errs.length){
        errs.forEach(function(e){
          console.error('[PHP]', e.message, '@', e.file + ':' + e.line, '(type ' + e.type + ')');
        });
      }
    }catch(_){ }
  })();
  </script>
  <?php if (!empty($pmErrs)): ?>
    <div class="offcanvas offcanvas-end" tabindex="-1" id="pmDebugPanel" aria-labelledby="pmDebugTitle">
      <div class="offcanvas-header border-bottom">
        <div>
          <h5 class="offcanvas-title text-danger" id="pmDebugTitle">Debug</h5>
          <div class="small text-body-secondary"><?= _h($_SERVER['REQUEST_URI'] ?? '') ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close" id="pmDebugClose"></button>
      </div>
      <div class="offcanvas-body">
        <div class="d-flex flex-wrap gap-2 mb-3">
          <button class="btn btn-outline-secondary btn-sm" type="button" id="pmDebugCopy">Kopyala</button>
          <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#pmDebugBody" id="pmDebugMin">Küçült</button>
        </div>
        <div id="pmDebugBody" class="collapse show">
          <div class="list-group small">
            <?php foreach ($pmErrs as $e): ?>
              <div class="list-group-item">
                <div class="fw-semibold text-break"><?= _h($e['message'] ?? 'Unknown error') ?></div>
                <div class="text-body-secondary text-break"><?= _h(($e['file'] ?? '') . ':' . ($e['line'] ?? '')) ?> (type <?= _h((string)($e['type'] ?? '')) ?>)</div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
    <button class="btn btn-danger btn-sm position-fixed bottom-0 end-0 m-3" type="button" id="pmDebugPill"
            data-bs-toggle="offcanvas" data-bs-target="#pmDebugPanel" aria-controls="pmDebugPanel">
      Debug (<?= count($pmErrs) ?>)
    </button>
    <script>
    (function(){
      const panel = document.getElementById('pmDebugPanel');
      const closeBtn = document.getElementById('pmDebugClose');
      const copyBtn = document.getElementById('pmDebugCopy');
      const minBtn = document.getElementById('pmDebugMin');
      const pill = document.getElementById('pmDebugPill');
      const body = document.getElementById('pmDebugBody');
      if (!panel || !pill) return;

      const key = 'pm-debug-hidden';
      const minKey = 'pm-debug-min';
      const items = Array.from(panel.querySelectorAll('.list-group-item'));

      const offcanvas = window.bootstrap ? bootstrap.Offcanvas.getOrCreateInstance(panel) : null;
      if (localStorage.getItem(key) !== '1' && offcanvas) {
        offcanvas.show();
      }

      const summary = items.slice(0, 6).map((item, idx) => {
        const msg = item.querySelector('.fw-semibold')?.textContent || '';
        const loc = item.querySelector('.text-body-secondary')?.textContent || '';
        return `${idx + 1}) ${msg}<br>${loc}`;
      }).join('<br><br>');

      if (summary && window.bootstrap && bootstrap.Tooltip) {
        pill.setAttribute('data-bs-toggle', 'tooltip');
        pill.setAttribute('data-bs-html', 'true');
        pill.setAttribute('data-bs-title', summary);
        bootstrap.Tooltip.getOrCreateInstance(pill);
      }

      if (localStorage.getItem(minKey) === '1' && body) {
        body.classList.remove('show');
        if (minBtn) minBtn.textContent = 'Aç';
      }

      if (closeBtn && offcanvas) {
        closeBtn.addEventListener('click', () => {
          try{ localStorage.setItem(key, '1'); }catch(_){ }
          offcanvas.hide();
        });
      }

      if (copyBtn){
        copyBtn.addEventListener('click', async () => {
          try{
            let text = '';
            const url = (panel.querySelector('.text-body-secondary')?.textContent || '').trim();
            text += `URL: ${url}\n`;
            text += `Errors: ${items.length}\n`;
            items.forEach((item, idx) => {
              const msg = item.querySelector('.fw-semibold')?.textContent || '';
              const loc = item.querySelector('.text-body-secondary')?.textContent || '';
              text += `\n${idx + 1}) ${msg}\n${loc}\n`;
            });
            await navigator.clipboard.writeText(text);
            copyBtn.textContent = 'Kopyalandý';
            setTimeout(() => { copyBtn.textContent = 'Kopyala'; }, 1200);
          }catch(_){
            copyBtn.textContent = 'Kopyalanamadý';
            setTimeout(() => { copyBtn.textContent = 'Kopyala'; }, 1200);
          }
        });
      }

      if (minBtn && body){
        minBtn.addEventListener('click', () => {
          const isMin = !body.classList.contains('show');
          minBtn.textContent = isMin ? 'Küçült' : 'Aç';
          try{ localStorage.setItem(minKey, isMin ? '0' : '1'); }catch(_){ }
        });
      }

      panel.addEventListener('hidden.bs.offcanvas', () => {
        try{ localStorage.setItem(key, '1'); }catch(_){ }
      });

      panel.addEventListener('shown.bs.offcanvas', () => {
        try{ localStorage.setItem(key, '0'); }catch(_){ }
      });
    })();
    </script>
  <?php endif; ?>
<?php endif; ?>