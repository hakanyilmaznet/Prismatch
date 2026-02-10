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
    }catch(_){}
  })();
  </script>
  <?php if (!empty($pmErrs)): ?>
    <style>
      .pm-debug-panel{
        position: fixed;
        right: 16px;
        bottom: calc(var(--pm-footer-offset, 0px) + 16px);
        z-index: 1200;
        width: min(520px, calc(100vw - 32px));
        max-height: 45vh;
        overflow: hidden;
        border-radius: 14px;
        border: 1px solid rgba(0,0,0,.15);
        background: rgba(255,255,255,.96);
        box-shadow: 0 12px 30px rgba(0,0,0,.2);
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
        font-size: 12px;
      }
      [data-bs-theme="dark"] .pm-debug-panel{
        background: rgba(10,12,18,.96);
        border-color: rgba(255,255,255,.12);
        box-shadow: 0 16px 40px rgba(0,0,0,.55);
      }
      .pm-debug-head{
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:10px;
        padding:10px 12px;
        border-bottom: 1px solid rgba(0,0,0,.08);
        background: rgba(248,248,248,.9);
      }
      [data-bs-theme="dark"] .pm-debug-head{
        background: rgba(18,22,34,.9);
        border-bottom-color: rgba(255,255,255,.08);
      }
      .pm-debug-title{ font-weight:700; color:#dc3545; }
      [data-bs-theme="dark"] .pm-debug-title{ color:#ffb3a6; }
      .pm-debug-meta{ opacity:.7; font-size:11px; }
      .pm-debug-body{
        max-height: 36vh;
        overflow:auto;
        padding: 8px 12px;
      }
      .pm-debug-panel.pm-debug-minimized .pm-debug-body{
        display: none;
      }
      .pm-debug-panel.pm-debug-minimized{
        width: fit-content;
        max-width: calc(100vw - 32px);
      }
      .pm-debug-min-count{
        display: none;
        margin-left: 8px;
        padding: 2px 6px;
        border-radius: 999px;
        background: #dc3545;
        color: #fff;
        font-size: 10px;
        font-weight: 700;
      }
      .pm-debug-panel.pm-debug-minimized .pm-debug-min-count{
        display: inline-flex;
        align-items: center;
      }
      .pm-debug-tooltip{
        position: fixed;
        z-index: 1300;
        max-width: 360px;
        background: rgba(20,22,30,.95);
        color: #fff;
        border: 1px solid rgba(255,255,255,.2);
        border-radius: 12px;
        padding: 8px 10px;
        font-size: 12px;
        line-height: 1.35;
        box-shadow: 0 12px 28px rgba(0,0,0,.35);
        white-space: pre-wrap;
        display: none;
      }
      [data-bs-theme="dark"] .pm-debug-tooltip{
        background: rgba(10,12,18,.98);
        border-color: rgba(255,255,255,.12);
      }
      .pm-debug-pill{
        position: fixed;
        right: 16px;
        bottom: calc(var(--pm-footer-offset, 0px) + 16px);
        z-index: 1199;
        border-radius: 999px;
        border: 1px solid rgba(0,0,0,.15);
        background: rgba(255,255,255,.9);
        padding: 6px 12px;
        font-size: 12px;
        font-weight: 700;
        display: inline-flex;
        gap: 6px;
        align-items: center;
        cursor: pointer;
      }
      [data-bs-theme="dark"] .pm-debug-pill{
        border-color: rgba(255,255,255,.2);
        background: rgba(20,24,36,.9);
        color: #fff;
      }
      .pm-debug-close{
        border: 1px solid rgba(0,0,0,.15);
        background: rgba(255,255,255,.8);
        border-radius: 10px;
        padding: 4px 8px;
        font-size: 11px;
        cursor: pointer;
      }
      .pm-debug-min{
        border: 1px solid rgba(0,0,0,.15);
        background: rgba(255,255,255,.8);
        border-radius: 10px;
        padding: 4px 8px;
        font-size: 11px;
        cursor: pointer;
      }
      .pm-debug-copy{
        border: 1px solid rgba(0,0,0,.15);
        background: rgba(255,255,255,.8);
        border-radius: 10px;
        padding: 4px 8px;
        font-size: 11px;
        cursor: pointer;
      }
      [data-bs-theme="dark"] .pm-debug-close{
        border-color: rgba(255,255,255,.2);
        background: rgba(20,24,36,.8);
        color: #fff;
      }
      [data-bs-theme="dark"] .pm-debug-min{
        border-color: rgba(255,255,255,.2);
        background: rgba(20,24,36,.8);
        color: #fff;
      }
      [data-bs-theme="dark"] .pm-debug-copy{
        border-color: rgba(255,255,255,.2);
        background: rgba(20,24,36,.8);
        color: #fff;
      }
      .pm-debug-item{ margin-bottom: 8px; }
      .pm-debug-item:last-child{ margin-bottom:0; }
      .pm-debug-msg{ font-weight:600; }
      .pm-debug-loc{ opacity:.7; }
    </style>
    <div class="pm-debug-panel" role="region" aria-label="PHP Debug Panel" id="pmDebugPanel">
      <div class="pm-debug-head">
        <div class="pm-debug-title">
          PHP Debug <span class="pm-debug-head-count" id="pmDebugHeadCount">(<?= count($pmErrs) ?>)</span>
          <span class="pm-debug-min-count" id="pmDebugMinCount"><?= count($pmErrs) ?></span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <div class="pm-debug-meta"><?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '') ?></div>
          <button class="pm-debug-copy" type="button" id="pmDebugCopy">Kopyala</button>
          <button class="pm-debug-min" type="button" id="pmDebugMin">Küçült</button>
          <button class="pm-debug-close" type="button" id="pmDebugClose">Kapat</button>
        </div>
      </div>
      <div class="pm-debug-body">
        <?php foreach ($pmErrs as $e): ?>
          <div class="pm-debug-item">
            <div class="pm-debug-msg"><?= _h($e['message'] ?? 'Unknown error') ?></div>
            <div class="pm-debug-loc"><?= _h(($e['file'] ?? '') . ':' . ($e['line'] ?? '')) ?> (type <?= _h((string)($e['type'] ?? '')) ?>)</div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <button class="pm-debug-pill" type="button" id="pmDebugPill" aria-label="Debug panelini göster">
      Debug <span class="pm-debug-count">(<?= count($pmErrs) ?>)</span>
    </button>
    <div class="pm-debug-tooltip" id="pmDebugTooltip" role="tooltip" aria-hidden="true"></div>
    <script>
    (function(){
      const panel = document.getElementById('pmDebugPanel');
      const closeBtn = document.getElementById('pmDebugClose');
      const copyBtn = document.getElementById('pmDebugCopy');
      const minBtn = document.getElementById('pmDebugMin');
      const pill = document.getElementById('pmDebugPill');
      const minCount = document.getElementById('pmDebugMinCount');
      const tooltip = document.getElementById('pmDebugTooltip');
      const key = 'pm-debug-hidden';
      const minKey = 'pm-debug-min';
      if (!panel || !closeBtn) return;

      const items = Array.from(panel.querySelectorAll('.pm-debug-item'));
      const errorCount = items.length;

      // Auto-hide if no errors (defensive)
      if (errorCount === 0){
        panel.style.display = 'none';
        if (pill) pill.style.display = 'none';
        return;
      }

      if (localStorage.getItem(key) === '1') {
        panel.style.display = 'none';
        if (pill) pill.style.display = 'inline-flex';
      } else {
        if (pill) pill.style.display = 'none';
      }

      if (localStorage.getItem(minKey) === '1') {
        panel.classList.add('pm-debug-minimized');
        if (minBtn) minBtn.textContent = 'Aç';
      }

      // Tooltip summary (multi-line)
      const summary = items.slice(0, 6).map((item, idx) => {
        const msg = item.querySelector('.pm-debug-msg')?.textContent || '';
        const loc = item.querySelector('.pm-debug-loc')?.textContent || '';
        return `${idx + 1}) ${msg}\n${loc}`;
      }).join('\n\n');

      function showTooltip(anchor){
        if (!tooltip || !summary || !anchor) return;
        tooltip.textContent = summary;
        tooltip.style.display = 'block';
        tooltip.setAttribute('aria-hidden','false');
        const r = anchor.getBoundingClientRect();
        const tr = tooltip.getBoundingClientRect();
        const top = Math.max(12, r.top - tr.height - 10);
        const left = Math.min(window.innerWidth - tr.width - 12, Math.max(12, r.left));
        tooltip.style.top = `${top}px`;
        tooltip.style.left = `${left}px`;
      }
      function hideTooltip(){
        if (!tooltip) return;
        tooltip.style.display = 'none';
        tooltip.setAttribute('aria-hidden','true');
      }
      if (minCount){
        minCount.addEventListener('mouseenter', () => showTooltip(minCount));
        minCount.addEventListener('mouseleave', hideTooltip);
      }
      if (pill){
        pill.addEventListener('mouseenter', () => showTooltip(pill));
        pill.addEventListener('mouseleave', hideTooltip);
      }

      closeBtn.addEventListener('click', () => {
        panel.style.display = 'none';
        if (pill) pill.style.display = 'inline-flex';
        try{ localStorage.setItem(key, '1'); }catch(_){}
      });

      if (copyBtn){
        copyBtn.addEventListener('click', async () => {
          try{
            let text = '';
            const url = (panel.querySelector('.pm-debug-meta')?.textContent || '').trim();
            text += `URL: ${url}\n`;
            text += `Errors: ${items.length}\n`;
            items.forEach((item, idx) => {
              const msg = item.querySelector('.pm-debug-msg')?.textContent || '';
              const loc = item.querySelector('.pm-debug-loc')?.textContent || '';
              text += `\n${idx + 1}) ${msg}\n${loc}\n`;
            });
            await navigator.clipboard.writeText(text);
            copyBtn.textContent = 'Kopyalandı';
            setTimeout(() => { copyBtn.textContent = 'Kopyala'; }, 1200);
          }catch(_){
            copyBtn.textContent = 'Kopyalanamadı';
            setTimeout(() => { copyBtn.textContent = 'Kopyala'; }, 1200);
          }
        });
      }

      if (minBtn){
        minBtn.addEventListener('click', () => {
          const isMin = panel.classList.toggle('pm-debug-minimized');
          minBtn.textContent = isMin ? 'Aç' : 'Küçült';
          try{ localStorage.setItem(minKey, isMin ? '1' : '0'); }catch(_){}
        });
      }

      if (pill){
        pill.addEventListener('click', () => {
          panel.style.display = 'block';
          pill.style.display = 'none';
          try{ localStorage.removeItem(key); }catch(_){}
        });
      }
    })();
    </script>
  <?php endif; ?>
<?php endif; ?>



