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
  <style>
    :root{ color-scheme: light dark; }
    :root,
    [data-bs-theme="dark"]{
      --bg: #0b0d12;
      --text: #f7f7f4;
      --muted: rgba(237,242,255,0.68);
      --panel: rgba(255,255,255,0.08);
      --panel-soft: rgba(255,255,255,0.05);
      --surface: rgba(14,16,22,0.7);
      --accent: #ff7d5d;
      --accent2: #3dd6a0;
      --accent3: #ffd08a;
      --shadow: 0 32px 70px rgba(0,0,0,0.55);
      --radius: 20px;
      --grid: rgba(255,255,255,0.06);
    }
    [data-bs-theme="light"]{
      --bg: #f6f3ee;
      --text: #1b1f2a;
      --muted: rgba(27,31,42,0.65);
      --panel: rgba(255,255,255,0.9);
      --panel-soft: rgba(255,255,255,0.6);
      --surface: rgba(255,255,255,0.86);
      --accent: #e4573f;
      --accent2: #1e9b79;
      --accent3: #f4b66a;
      --shadow: 0 26px 60px rgba(26,28,35,0.16);
      --radius: 20px;
      --grid: rgba(27,31,42,0.08);
    }
    *{ box-sizing:border-box; }
    body{
      margin:0;
      font-family: "Plus Jakarta Sans", "Segoe UI", "Helvetica Neue", sans-serif;
      color: var(--text);
      background: var(--bg);
      min-height:100vh;
      padding: 24px;
      padding-bottom: calc(100px + env(safe-area-inset-bottom));
    }
    body::after{
      content:"";
      position:fixed;
      inset:0;
      background-image: radial-gradient(var(--grid) 1px, transparent 1px);
      background-size: 24px 24px;
      opacity:.35;
      pointer-events:none;
      z-index:-1;
    }
    a{ color: inherit; text-decoration:none; }
    .page{ max-width: 1120px; margin:0 auto; display:flex; flex-direction:column; gap:28px; }

    .hero{
      display:grid;
      gap:18px;
      background: var(--surface);
      border: 1px solid var(--grid);
      border-radius: var(--radius);
      padding: 26px;
      box-shadow: var(--shadow);
    }
    .hero h1{
      margin:0 0 10px 0;
      font-family:"Space Grotesk","Segoe UI","Helvetica Neue",sans-serif;
      font-size: clamp(30px, 6vw, 46px);
      letter-spacing:.2px;
    }
    .hero p{ margin:0; color: var(--muted); line-height:1.6; }
    .hero .cta{ display:flex; flex-wrap:wrap; gap:10px; margin-top: 16px; }
    .hero .eyebrow{
      display:inline-flex; align-items:center; gap:8px;
      font-size:12px; letter-spacing:.3px; text-transform:uppercase; font-weight:700;
      color: var(--text);
      background: var(--panel-soft);
      border-radius: 999px;
      padding: 6px 12px;
      margin-bottom: 12px;
    }
    .hero .quick{ display:flex; flex-wrap:wrap; gap:8px; margin-top: 18px; }
    .hero .lead{ font-size:15px; }

    .btn{
      display:inline-flex; align-items:center; justify-content:center; gap:8px;
      padding: 10px 16px;
      border-radius: 999px;
      border:1px solid var(--grid);
      background: var(--panel);
      color: var(--text);
      font-weight: 600;
      letter-spacing:.2px;
      transition: transform 140ms ease, border-color 140ms ease, box-shadow 140ms ease, background 140ms ease;
      white-space: nowrap;
    }
    .btn:hover{ transform: translateY(-1px); border-color: rgba(255,125,93,0.55); box-shadow: 0 16px 32px rgba(255,125,93,0.18); }
    .btn.primary{
      background: linear-gradient(135deg, #ff7d5d, #ffd08a);
      border-color: rgba(255,125,93,0.55);
      color:#101318;
      box-shadow: 0 18px 36px rgba(255,125,93,0.25);
    }

    .chip{
      background: var(--panel-soft);
      border:1px solid var(--grid);
      padding:6px 10px;
      border-radius: 999px;
      font-size:12px;
      color: var(--text);
    }

    .showcase{
      border-radius: calc(var(--radius) + 6px);
      border:1px dashed var(--grid);
      background:
        radial-gradient(180px 180px at 20% 20%, rgba(61,214,160,0.2), transparent 70%),
        radial-gradient(220px 220px at 80% 20%, rgba(255,125,93,0.18), transparent 70%),
        radial-gradient(260px 260px at 50% 80%, rgba(255,208,138,0.2), transparent 70%),
        var(--panel-soft);
      min-height: 220px;
      padding: 20px;
      display:flex;
      flex-direction:column;
      justify-content:space-between;
      gap:10px;
    }
    .showcase .badge{
      align-self:flex-start;
      font-size:12px;
      padding:6px 12px;
      border-radius:999px;
      border:1px solid var(--grid);
      background: rgba(12,14,20,0.4);
      color: var(--text);
    }
    [data-bs-theme="light"] .showcase .badge{ background: rgba(255,255,255,0.7); }
    .showcase strong{ font-size:18px; }

    .section-title{
      font-family:"Space Grotesk","Segoe UI","Helvetica Neue",sans-serif;
      margin: 8px 0 12px 0;
      font-size: 20px;
      letter-spacing:.2px;
    }
    .grid{ display:grid; gap:14px; }
    .card{
      background: var(--surface);
      border:1px solid var(--grid);
      border-radius: 18px;
      padding: 18px;
      box-shadow: var(--shadow);
    }
    .card h3{ margin:0 0 8px 0; font-size:16px; font-weight:700; }
    .card p{ margin:0; color: var(--muted); line-height:1.5; }

    .steps{ display:grid; gap:10px; }
    .step{ display:flex; gap:12px; align-items:flex-start; }
    .step .num{
      width:32px; height:32px;
      border-radius: 12px;
      background: var(--panel-soft);
      display:flex; align-items:center; justify-content:center;
      font-weight:700;
    }

    .score-grid{ display:grid; gap:14px; }
    .score-card{
      background: var(--surface);
      border:1px solid var(--grid);
      border-radius: 18px;
      padding: 18px;
      box-shadow: var(--shadow);
    }
    .score-card code{
      display:inline-block;
      background: var(--panel-soft);
      border:1px solid var(--grid);
      padding:4px 8px;
      border-radius: 8px;
      font-family: "Space Grotesk", "Segoe UI", sans-serif;
      font-size: 12px;
      color: var(--text);
    }

    .reveal{ animation: fadeUp .6s ease both; }
    @keyframes fadeUp{ from{ opacity:0; transform: translateY(8px);} to{ opacity:1; transform: translateY(0);} }

    @media (min-width: 900px){
      .hero{ grid-template-columns: 1.1fr 0.9fr; align-items:center; }
      .grid.cols-3{ grid-template-columns: repeat(3, minmax(0,1fr)); }
      .grid.cols-2{ grid-template-columns: repeat(2, minmax(0,1fr)); }
      .score-grid{ grid-template-columns: repeat(3, minmax(0,1fr)); }
    }
  </style>
</head>
<body>
  <div class="page">
    <?php include __DIR__ . '/header.php'; ?>

        <section class="hero reveal" style="animation-delay:.04s">
      <div>
        <span class="eyebrow"><?= htmlspecialchars(tt('home_showcase_badge', 'Mini eğitim • 3 adım')) ?></span>
        <h1><?= htmlspecialchars(tt('home_hero_title', 'Renkleri hatırla, doğru tonu yakala.')) ?></h1>
        <p><?= htmlspecialchars(tt('home_hero_subtitle', 'Prismatch, hızla değişen renkleri kısa süreli hafızanda tutmanı ister. Her turda süre kısalır, grid yoğunlaşır ve tek bir doğru renk seni bir sonraki aşamaya taşır.')) ?></p>
        <p class="lead"><?= htmlspecialchars(tt('home_creative', 'Renklerin hafızada şiir gibi kaldığı bir ritme gir: İpucu kaybolur, zihnin tonu yakalar. Her doğru seçim, bir sonraki sahneyi açar.')) ?></p>
          <div class="cta">
            <a class="btn primary" href="play.php">
              <img class="bi-icon" src="bootstrap-icons/play-fill.svg" alt="" aria-hidden="true" />
              <?= htmlspecialchars(tt('home_cta_play', 'Hemen oyna')) ?>
            </a>
            <a class="btn" href="play.php?daily=1">
              <img class="bi-icon" src="bootstrap-icons/calendar2-check.svg" alt="" aria-hidden="true" />
              <?= htmlspecialchars(tt('home_cta_daily', 'Günlük Meydan Okuma')) ?>
            </a>
            <a class="btn" href="daily_leaderboard.php">
              <img class="bi-icon" src="bootstrap-icons/trophy-fill.svg" alt="" aria-hidden="true" />
              <?= htmlspecialchars(tt('daily_leaderboard_title', 'Leaderboard')) ?>
            </a>
          </div>
        <div class="quick">
          <span class="chip"><?= htmlspecialchars(tt('home_benefit_1_title', 'Zihin açan kısa turlar')) ?></span>
          <span class="chip"><?= htmlspecialchars(tt('home_benefit_2_title', 'Günlük meydan okuma')) ?></span>
          <span class="chip"><?= htmlspecialchars(tt('home_benefit_3_title', 'Çok dil ve istatistik')) ?></span>
        </div>
      </div>
      <div class="showcase">
        <strong><?= htmlspecialchars(tt('home_showcase_title', 'Bir tur nasıl işler?')) ?></strong>
        <div><?= htmlspecialchars(tt('home_showcase_body', 'Hedef rengi gör, hafızanda tut ve 5 saniye içinde gridde bul.')) ?></div>
        <div class="steps" style="margin-top:8px">
          <div class="step">
            <div class="num">1</div>
            <div>
              <div style="font-weight:700"><?= htmlspecialchars(tt('home_step_1_title', 'Hedef rengi izle')) ?></div>
              <div style="color:var(--muted)"><?= htmlspecialchars(tt('home_step_1_body', 'Ekranda kısa süre gösterilen rengi dikkatle aklında tut.')) ?></div>
            </div>
          </div>
          <div class="step">
            <div class="num">2</div>
            <div>
              <div style="font-weight:700"><?= htmlspecialchars(tt('home_step_2_title', 'Gridde doğru rengi seç')) ?></div>
              <div style="color:var(--muted)"><?= htmlspecialchars(tt('home_step_2_body', 'Zaman bitmeden, hatırladığın rengi 9 seçenek arasında bul.')) ?></div>
            </div>
          </div>
          <div class="step">
            <div class="num">3</div>
            <div>
              <div style="font-weight:700"><?= htmlspecialchars(tt('home_step_3_title', 'Aşamaları geç, skoru yükselt')) ?></div>
              <div style="color:var(--muted)"><?= htmlspecialchars(tt('home_step_3_body', 'Her doğru eşleşme seni bir üst aşamaya taşır.')) ?></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="reveal" style="animation-delay:.1s">
      <div class="section-title"><?= htmlspecialchars(tt('home_intro_title', 'Proje ne sağlıyor?')) ?></div>
      <div class="grid cols-2">
        <div class="card">
          <h3><?= htmlspecialchars(tt('home_intro_headline', 'Hızlı odak & hafıza egzersizi')) ?></h3>
          <p><?= htmlspecialchars(tt('home_intro_body', 'Kısa süreli hatırlama ve dikkat kontrolünü ölçen mikro turlar, gün içinde pratik yapmak için ideal.')) ?></p>
        </div>
        <div class="card">
          <h3><?= htmlspecialchars(tt('home_intro_headline2', 'Geri bildirim ve ilerleme')) ?></h3>
          <p><?= htmlspecialchars(tt('home_intro_body2', 'Aşama, doğru eşleşme ve süre kayıtlarıyla gelişimini takip edebilir, günlük hedef koyabilirsin.')) ?></p>
        </div>
      </div>
    </section>

    <section class="reveal" style="animation-delay:.16s">
      <div class="section-title"><?= htmlspecialchars(tt('home_benefits_title', 'Öne çıkan faydalar')) ?></div>
      <div class="grid cols-3">
        <div class="card">
          <h3><?= htmlspecialchars(tt('home_benefit_1_title', 'Zihin açan kısa turlar')) ?></h3>
          <p><?= htmlspecialchars(tt('home_benefit_1_body', 'Her tur birkaç saniye sürer; kısa molalarda bile kolayca oynanır.')) ?></p>
        </div>
        <div class="card">
          <h3><?= htmlspecialchars(tt('home_benefit_2_title', 'Günlük meydan okuma')) ?></h3>
          <p><?= htmlspecialchars(tt('home_benefit_2_body', 'Her gün tek deneme hakkıyla odak ve süre yönetimini geliştirir.')) ?></p>
        </div>
        <div class="card">
          <h3><?= htmlspecialchars(tt('home_benefit_3_title', 'Çok dil ve istatistik')) ?></h3>
          <p><?= htmlspecialchars(tt('home_benefit_3_body', 'Farklı dillerde oynar, performansını kayıt altına alırsın.')) ?></p>
        </div>
      </div>
    </section>

    <section class="reveal" style="animation-delay:.22s">
      <div class="section-title"><?= htmlspecialchars(tt('home_score_title', 'Scoring algorithm')) ?></div>
      <div class="score-grid">
        <div class="score-card">
          <strong><?= htmlspecialchars(tt('home_score_rule_1', 'Level impact (70%)')) ?></strong>
          <p><?= htmlspecialchars(tt('home_score_rule_1_body', 'The higher the stage you reach, the higher your score.')) ?></p>
          <code>levelFactor = reachedLevel / 50</code>
        </div>
        <div class="score-card">
          <strong><?= htmlspecialchars(tt('home_score_rule_2', 'Speed impact (30%)')) ?></strong>
          <p><?= htmlspecialchars(tt('home_score_rule_2_body', 'For correct answers, we use target show time / response time ratio.')) ?></p>
          <code>timeFactor = min(1, avgRatio / 1.5)</code>
        </div>
        <div class="score-card">
          <strong><?= htmlspecialchars(tt('home_score_rule_3', 'Final formula')) ?></strong>
          <p><?= htmlspecialchars(tt('home_score_rule_3_body', 'Level and speed are scaled to a 0–1000 score.')) ?></p>
          <code>score = round(1000 * (0.7*levelFactor + 0.3*timeFactor))</code>
        </div>
      </div>
    </section>


    <?php if (is_file(__DIR__ . '/footer.php')) include __DIR__ . '/footer.php'; ?>
  </div>

<script>
(function(){
  const STR = {
    noResults: <?= json_encode(tt('no_results', 'No results')) ?>,
  };

  const select = document.getElementById('langSelect');
  const root   = document.getElementById('langPicker');
  const btn    = document.getElementById('langBtn');
  const btnTxt = document.getElementById('langBtnText');
  const pop    = document.getElementById('langPop');
  const list   = document.getElementById('langList');
  const search = document.getElementById('langSearch');
  const title  = document.getElementById('langTitle');

  if(!select || !root || !btn || !btnTxt || !pop || !list || !search || !title) return;

  const items = Array.from(select.options).map(o => {
    const name = (o.textContent || '').trim().replace(/\s+/g,' ') || o.value;
    return { code: o.value, name };
  });

  let open = false;
  let activeIndex = -1;
  let filtered = items.slice();

  let popWasPortaled = false;
  let popHomeParent = null;
  let popHomeNext = null;

  function escapeHtml(s){
    return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  }

  function renderList(){
    list.innerHTML = '';
    if (!filtered.length) {
      const d = document.createElement('div');
      d.className = 'langNoRes';
      d.textContent = STR.noResults;
      list.appendChild(d);
      return;
    }
    filtered.forEach((it, idx) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'langItem';
      b.setAttribute('role','option');
      b.dataset.code = it.code;
      b.dataset.index = String(idx);

      const selected = (select.value === it.code);
      b.setAttribute('aria-selected', selected ? 'true' : 'false');

      b.innerHTML = `
        <span class="langLabelCell">${escapeHtml(it.name)}</span>
        <span class="langMetaCell"><span>${escapeHtml(it.code)}</span>${selected ? '<span class="langCheck" aria-hidden="true">OK</span>' : ''}</span>
      `;

      b.addEventListener('mouseenter', () => setActive(idx));
      b.addEventListener('click', () => choose(it.code));
      list.appendChild(b);
    });
    syncActiveClass();
  }

  function setActive(idx){
    activeIndex = Math.max(0, Math.min(idx, filtered.length-1));
    syncActiveClass();
    scrollActiveIntoView();
  }
  function syncActiveClass(){
    Array.from(list.querySelectorAll('.langItem')).forEach((el, i) => {
      el.classList.toggle('isActive', i === activeIndex);
    });
  }
  function scrollActiveIntoView(){
    const el = list.querySelectorAll('.langItem')[activeIndex];
    if(!el) return;
    const r = el.getBoundingClientRect();
    const pr = list.getBoundingClientRect();
    if (r.top < pr.top) el.scrollIntoView({block:'nearest'});
    if (r.bottom > pr.bottom) el.scrollIntoView({block:'nearest'});
  }

  function placePopover() {
    if (!popWasPortaled) {
      popHomeParent = pop.parentNode;
      popHomeNext = pop.nextSibling;
      document.body.appendChild(pop);
      popWasPortaled = true;
    }
    const r = btn.getBoundingClientRect();
    const margin = 10;

    pop.hidden = false;

    const popRect = pop.getBoundingClientRect();
    const alignEnd = root.getAttribute('data-align') !== 'start';

    let top = Math.round(r.bottom + margin);
    let left = alignEnd ? Math.round(r.right - popRect.width) : Math.round(r.left);

    const minLeft = 12;
    const maxLeft = window.innerWidth - popRect.width - 12;
    left = Math.max(minLeft, Math.min(left, maxLeft));

    const maxTop = window.innerHeight - popRect.height - 12;
    if (top > maxTop) top = Math.max(12, Math.round(r.top - popRect.height - margin));

    pop.style.top = top + 'px';
    pop.style.left = left + 'px';

    const selIdx = filtered.findIndex(x => x.code === select.value);
    if (selIdx >= 0) setActive(selIdx);
  }

  function unportalPopover() {
    if (popWasPortaled && popHomeParent) {
      if (popHomeNext && popHomeNext.parentNode === popHomeParent) popHomeParent.insertBefore(pop, popHomeNext);
      else popHomeParent.appendChild(pop);
    }
    popWasPortaled = false;
    popHomeParent = null;
    popHomeNext = null;
    pop.style.top = '';
    pop.style.left = '';
  }

  function setOpen(v){
    open = v;
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');

    if(open){
      search.value = '';
      filtered = items.slice();
      renderList();
      placePopover();
      setTimeout(()=> search.focus(), 0);
    } else {
      pop.hidden = true;
      activeIndex = -1;
      unportalPopover();
    }
  }

  function choose(code){
    select.value = code;
    const it = items.find(x => x.code === code);
    btnTxt.textContent = it ? `${it.name}` : code;

    setOpen(false);

    const url = new URL(window.location.href);
    fetch('api/set_lang.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({lang: code}),
      credentials: 'same-origin'
    }).then(() => location.reload())
      .catch(() => {
        url.searchParams.set('lang', code);
        window.location.href = url.toString();
      });
  }

  function applyFilter(q){
    const s = q.trim().toLowerCase();
    if(!s) filtered = items.slice();
    else filtered = items.filter(it =>
      it.code.toLowerCase().includes(s) ||
      it.name.toLowerCase().includes(s)
    );
    renderList();
    if (filtered.length) setActive(0);
  }

  (function initSelected(){
    const current = select.value || items[0]?.code || 'en';
    const it = items.find(x => x.code === current) || items[0];
    if (it) {
      select.value = it.code;
      btnTxt.textContent = `${it.name}`;
    }
  })();

  btn.addEventListener('click', () => setOpen(!open));
  document.addEventListener('pointerdown', (e) => {
    if(!open) return;
    if(root.contains(e.target) || pop.contains(e.target)) return;
    setOpen(false);
  });

  document.addEventListener('keydown', (e) => {
    if(!open) return;
    if(e.key === 'Escape'){
      e.preventDefault();
      setOpen(false);
      btn.focus();
    }
  });

  search.addEventListener('input', () => applyFilter(search.value));
  search.addEventListener('keydown', (e) => {
    if(!open) return;
    if(e.key === 'ArrowDown'){ e.preventDefault(); if(filtered.length) setActive((activeIndex + 1) % filtered.length); }
    if(e.key === 'ArrowUp'){ e.preventDefault(); if(filtered.length) setActive((activeIndex - 1 + filtered.length) % filtered.length); }
    if(e.key === 'Enter'){ e.preventDefault(); if(activeIndex >= 0 && filtered[activeIndex]) choose(filtered[activeIndex].code); }
  });

  window.addEventListener('resize', () => { if(open) placePopover(); }, {passive:true});
  window.addEventListener('scroll',  () => { if(open) placePopover(); }, {passive:true});
})();
</script>
</body>
</html>
