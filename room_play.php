<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/i18n.php';

$lang = function_exists('get_lang') ? get_lang() : 'en';
$dir  = function_exists('lang_dir') ? lang_dir($lang) : 'ltr';
$userEmail = $_SESSION['user_email'] ?? null;
$userId = $_SESSION['user_id'] ?? null;
$canViewLogs = strtolower(trim((string)$userEmail)) === 'yilmazmukerrem@gmail.com';
$showLangPicker = true;

function tt(string $key, string $fallback = ''): string {
    $v = t($key);
    if ($v === $key) return $fallback !== '' ? $fallback : $key;
    return $v;
}

$guid = trim((string)($_GET['guid'] ?? ''));
if (!$userEmail || !$userId) {
    $next = 'room_play.php?guid=' . rawurlencode($guid);
    header('Location: login.php?next=' . rawurlencode($next));
    exit;
}

$room = get_room_by_guid($guid);
if (!$room) {
    header('Location: rooms.php');
    exit;
}

$seoTitle = ($room['name'] ?: tt('room_play_title', 'Multiplayer Match')) . ' - ' . tt('app_name', 'Prismatch');
$seoDescription = tt('room_play_desc', 'Compete live in real time against other players.');
$seoLangs = function_exists('supported_languages') ? array_keys(supported_languages()) : [];
$dict = translations();
$rightAnswerMessages = $dict[$lang]['right_answer_messages'] ?? ($dict['en']['right_answer_messages'] ?? []);
$wrongAnswerMessages = $dict[$lang]['wrong_answer_messages'] ?? ($dict['en']['wrong_answer_messages'] ?? []);
?>
<!doctype html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= htmlspecialchars($dir) ?>">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <script>
    (function(){
      const stored = localStorage.getItem('pm-theme');
      const prefers = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
      document.documentElement.setAttribute('data-bs-theme', stored || prefers);
    })();
  </script>
  <link href="css/style.css" rel="stylesheet" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700;800&family=Baloo+2:wght@500;600;700;800&display=swap" rel="stylesheet" />
  <title><?= htmlspecialchars($seoTitle) ?></title>
  <link rel="icon" type="image/svg+xml" href="logo.svg" />
  <?= seo_meta([
    'title' => $seoTitle,
    'description' => $seoDescription,
    'url' => seo_current_url(),
    'image' => '/logo.png',
    'type' => 'website',
    'robots' => 'noindex,follow',
    'lang' => $lang,
    'site_name' => tt('app_name', 'Prismatch'),
  ]) ?>
  <style>
    :root {
      --arena-bg: #090d16;
      --arena-panel: rgba(255, 255, 255, 0.08);
      --arena-border: rgba(255, 255, 255, 0.12);
      --arena-text: #f8fafc;
      --arena-muted: rgba(248, 250, 252, 0.65);
      --arena-accent: #ff6b5b;
      --arena-accent2: #20b77d;
      --arena-accent3: #ffb020;
      --arena-radius: 24px;
    }
    [data-bs-theme="light"] {
      --arena-bg: #f8fafc;
      --arena-panel: rgba(255, 255, 255, 0.95);
      --arena-border: rgba(0, 0, 0, 0.08);
      --arena-text: #0f172a;
      --arena-muted: rgba(15, 23, 42, 0.65);
    }
    body {
      margin: 0;
      font-family: "Rubik", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background: var(--arena-bg);
      color: var(--arena-text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      padding-top: calc(var(--pm-header-offset, 0px) + 16px);
      padding-bottom: calc(var(--pm-footer-offset, 0px) + 20px);
    }
    .arena-container {
      width: min(920px, 100%);
      margin: 0 auto;
      padding: 0 16px;
      display: flex;
      flex-direction: column;
      gap: 14px;
      flex: 1;
    }
    /* Modern Arena Header & HUD */
    .arena-hud {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
      gap: 10px;
    }
    .hud-chip {
      background: var(--arena-panel);
      border: 1px solid var(--arena-border);
      border-radius: 16px;
      padding: 10px 14px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      backdrop-filter: blur(12px);
      box-shadow: 0 4px 16px rgba(0,0,0,0.06);
    }
    .hud-label {
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--arena-muted);
      font-weight: 600;
    }
    .hud-val {
      font-size: 17px;
      font-weight: 800;
      font-family: "Baloo 2", sans-serif;
    }
    /* Main Arena Stage */
    .arena-stage {
      flex: 1;
      min-height: 420px;
      background: var(--arena-panel);
      border: 1px solid var(--arena-border);
      border-radius: var(--arena-radius);
      backdrop-filter: blur(16px);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 24px 18px 20px;
      position: relative;
      overflow: hidden;
      box-shadow: 0 20px 60px rgba(0,0,0,0.15);
    }
    .stage-center {
      width: 100%;
      max-width: 620px;
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      gap: 14px;
      animation: fadeIn 0.3s ease;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(6px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .stage-title {
      font-family: "Baloo 2", sans-serif;
      font-size: 24px;
      font-weight: 800;
      line-height: 1.2;
      margin: 0;
    }
    .stage-subtitle {
      font-size: 14px;
      color: var(--arena-muted);
      max-width: 480px;
    }
    /* Countdown Display */
    .big-countdown {
      font-family: "Baloo 2", sans-serif;
      font-size: clamp(72px, 12vw, 110px);
      font-weight: 900;
      color: var(--arena-accent);
      line-height: 1;
      text-shadow: 0 8px 30px rgba(255, 107, 91, 0.4);
      animation: pop 0.9s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
    }
    @keyframes pop {
      0% { transform: scale(0.6); opacity: 0; }
      50% { transform: scale(1.1); }
      100% { transform: scale(1); opacity: 1; }
    }
    /* Target Color Card */
    .target-box {
      width: min(440px, 92%);
      aspect-ratio: 16/9;
      border-radius: 20px;
      box-shadow: 0 20px 50px rgba(0,0,0,0.4);
      border: 3px solid rgba(255, 255, 255, 0.2);
      animation: pop 0.4s ease both;
      position: relative;
    }
    .target-box-flag {
      background-color: #151922;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 12px;
      overflow: hidden;
    }
    .target-flag-img {
      max-width: 100%;
      max-height: 100%;
      width: auto;
      height: auto;
      object-fit: contain;
      border-radius: 12px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.45);
    }
    .choice-cell-flag {
      background-color: #171c26;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 6px;
      overflow: hidden;
    }
    .choice-cell-flag .choice-flag-img {
      max-width: 100%;
      max-height: 100%;
      width: auto;
      height: auto;
      object-fit: contain;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.3);
      pointer-events: none;
    }
    .flag-swatch-wrap {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      max-width: 100%;
    }
    .flag-swatch-img {
      width: 28px;
      height: 18px;
      object-fit: cover;
      border-radius: 4px;
      border: 1px solid rgba(255,255,255,0.25);
      box-shadow: 0 2px 6px rgba(0,0,0,0.3);
      flex-shrink: 0;
    }
    .flag-name-text {
      font-size: 13px;
      font-weight: 600;
      color: #f1f5f9;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 130px;
    }
    /* Question Grid */
    .choice-grid {
      width: min(440px, 100%, calc(100vh - 440px));
      min-width: min(260px, 100%);
      aspect-ratio: 1 / 1;
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
      margin: 6px auto 0 auto;
    }
    .choice-cell {
      appearance: none;
      border: 3px solid rgba(255,255,255,0.15);
      border-radius: 18px;
      aspect-ratio: 1 / 1;
      cursor: pointer;
      box-shadow: 0 10px 25px rgba(0,0,0,0.25);
      transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
      outline: none;
    }
    .choice-cell:hover:not(:disabled) {
      transform: translateY(-4px) scale(1.02);
      box-shadow: 0 16px 36px rgba(0,0,0,0.35);
      border-color: rgba(255,255,255,0.7);
    }
    .choice-cell:active:not(:disabled) {
      transform: scale(0.97);
    }
    .choice-cell.correct {
      border-color: #20b77d !important;
      box-shadow: 0 0 0 5px rgba(32, 183, 125, 0.4), 0 16px 36px rgba(0,0,0,0.35);
    }
    .choice-cell.wrong {
      border-color: #ff4757 !important;
      box-shadow: 0 0 0 5px rgba(255, 71, 87, 0.4), 0 16px 36px rgba(0,0,0,0.35);
      opacity: 0.6;
    }
    /* Player Lobby / Presence Bar */
    .players-bar {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 8px;
      padding: 10px 16px;
      background: var(--arena-panel);
      border: 1px solid var(--arena-border);
      border-radius: 16px;
      backdrop-filter: blur(12px);
    }
    .player-tag {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      border-radius: 999px;
      font-size: 13px;
      font-weight: 600;
      background: rgba(255,255,255,0.06);
      border: 1px solid var(--arena-border);
    }
    .player-tag.self {
      border-color: var(--arena-accent);
      background: rgba(255, 107, 91, 0.12);
    }
    .player-tag.eliminated {
      opacity: 0.5;
      text-decoration: line-through;
    }
    .player-tag.offline {
      opacity: 0.5;
      border-style: dashed;
      background: rgba(100, 116, 139, 0.1);
    }
    .player-tag.offline .live-dot {
      background: #64748b !important;
      box-shadow: none !important;
    }
    .live-dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: var(--arena-accent2);
    }
    /* Answer Overlay Toast */
    .feedback-toast {
      position: fixed;
      top: 90px;
      left: 50%;
      transform: translateX(-50%) translateY(-20px);
      padding: 14px 28px;
      border-radius: 999px;
      font-size: 16px;
      font-weight: 700;
      color: #fff;
      display: flex;
      align-items: center;
      gap: 10px;
      z-index: 1050;
      opacity: 0;
      pointer-events: none;
      transition: all 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      box-shadow: 0 18px 40px rgba(0,0,0,0.35);
    }
    .feedback-toast.show {
      opacity: 1;
      transform: translateX(-50%) translateY(0);
    }
    .feedback-toast.correct { background: linear-gradient(135deg, #10b981, #059669); }
    .feedback-toast.wrong { background: linear-gradient(135deg, #ef4444, #dc2626); }
    .btn-action {
      background: linear-gradient(135deg, #ff6b5b, #ff8c42);
      border: none;
      color: #fff;
      font-weight: 700;
      padding: 12px 28px;
      border-radius: 14px;
      box-shadow: 0 8px 24px rgba(255, 107, 91, 0.4);
      transition: all 0.2s ease;
    }
    .btn-action:hover {
      background: linear-gradient(135deg, #ff5744, #ff7e2e);
      color: #fff;
      transform: translateY(-2px);
    }

    /* Victory & Final Standings Showcase */
    .victory-container {
      width: 100%;
      max-width: 760px;
      margin: 0 auto;
      display: flex;
      flex-direction: column;
      align-items: center;
      animation: fadeIn 0.4s ease;
      box-sizing: border-box;
      padding: 0 4px;
    }
    .victory-header {
      text-align: center;
      margin-bottom: 12px;
    }
    .victory-trophy {
      font-size: 44px;
      line-height: 1;
      margin-bottom: 4px;
      filter: drop-shadow(0 4px 18px rgba(255, 193, 7, 0.45));
      animation: trophyFloat 1.8s ease-in-out infinite alternate;
    }
    @keyframes trophyFloat {
      from { transform: translateY(0) scale(1); }
      to { transform: translateY(-5px) scale(1.05); }
    }
    .victory-title {
      font-size: 26px;
      font-weight: 800;
      color: #fff;
      margin-bottom: 4px;
    }
    .victory-subtitle {
      font-size: 13px;
      color: var(--arena-muted);
      margin: 0;
    }

    /* Olympic Ranking Podium */
    .podium-wrapper {
      width: 100%;
      margin: 10px 0 20px;
      display: flex;
      justify-content: center;
      align-items: flex-end;
    }
    .podium-stage {
      display: flex;
      align-items: flex-end;
      justify-content: center;
      gap: 12px;
      width: 100%;
      max-width: 580px;
    }
    .podium-col {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      min-width: 0;
    }
    .podium-player-info {
      display: flex;
      flex-direction: column;
      align-items: center;
      margin-bottom: 8px;
      width: 100%;
      min-height: 96px;
      justify-content: flex-end;
    }
    .podium-crown {
      font-size: 24px;
      line-height: 1;
      margin-bottom: -2px;
      animation: trophyFloat 1.8s ease-in-out infinite alternate;
    }
    .podium-avatar {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      font-weight: 800;
      margin-bottom: 5px;
      box-shadow: 0 4px 14px rgba(0,0,0,0.35);
      position: relative;
    }
    .podium-col-1 .podium-avatar {
      width: 54px;
      height: 54px;
      font-size: 28px;
      background: radial-gradient(circle at 35% 35%, #fff6cc, #ffd700, #b8860b);
      border: 3px solid #ffea79;
      box-shadow: 0 0 22px rgba(255, 215, 0, 0.5);
    }
    .podium-col-2 .podium-avatar {
      background: radial-gradient(circle at 35% 35%, #ffffff, #cfd8dc, #78909c);
      border: 2px solid #eceff1;
      box-shadow: 0 0 14px rgba(207, 216, 220, 0.4);
    }
    .podium-col-3 .podium-avatar {
      background: radial-gradient(circle at 35% 35%, #fde0c5, #cd7f32, #7a3c10);
      border: 2px solid #e0a370;
      box-shadow: 0 0 14px rgba(205, 127, 50, 0.4);
    }
    .podium-name {
      font-family: "Baloo 2", sans-serif;
      font-size: 14px;
      font-weight: 700;
      color: #fff;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 100%;
      display: flex;
      align-items: center;
      gap: 4px;
      justify-content: center;
      line-height: 1.2;
    }
    .podium-col-1 .podium-name {
      font-size: 16px;
      font-weight: 800;
    }
    .podium-score {
      font-size: 12px;
      font-weight: 700;
      color: #ffd166;
      display: flex;
      align-items: baseline;
      gap: 3px;
      margin-top: 2px;
    }
    .podium-col-1 .podium-score {
      font-size: 14px;
    }
    .podium-pedestal {
      width: 100%;
      border-radius: 14px 14px 4px 4px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-start;
      padding-top: 10px;
      position: relative;
      box-shadow: 0 8px 24px rgba(0,0,0,0.4), inset 0 2px 0 rgba(255,255,255,0.4);
    }
    .pedestal-1 {
      height: 120px;
      background: linear-gradient(180deg, #ffc837 0%, #ff8008 100%);
      border: 2px solid #ffe57f;
      border-bottom: none;
    }
    .pedestal-2 {
      height: 88px;
      background: linear-gradient(180deg, #b0bec5 0%, #455a64 100%);
      border: 2px solid #cfd8dc;
      border-bottom: none;
    }
    .pedestal-3 {
      height: 64px;
      background: linear-gradient(180deg, #b87333 0%, #5d2e0c 100%);
      border: 2px solid #d79a6d;
      border-bottom: none;
    }
    .podium-num {
      font-size: 34px;
      font-weight: 900;
      line-height: 1;
      font-family: "Rubik", sans-serif;
      color: #fff;
      text-shadow: 0 2px 8px rgba(0,0,0,0.4);
    }
    .podium-col-1 .podium-num {
      font-size: 42px;
    }
    .podium-bonus-tag {
      margin-top: 4px;
      font-size: 9px;
      font-weight: 800;
      background: rgba(0,0,0,0.3);
      padding: 2px 6px;
      border-radius: 6px;
      color: #fff;
      white-space: nowrap;
    }
    .podium-empty {
      opacity: 0.35;
      font-style: italic;
    }

    /* Personal Round-by-Round Stats */
    .personal-stats-section {
      width: 100%;
      background: rgba(0, 0, 0, 0.28);
      border: 1px solid var(--arena-border);
      border-radius: 18px;
      overflow: hidden;
      margin-top: 20px;
      margin-bottom: 8px;
    }
    .stats-header-bar {
      padding: 12px 16px;
      background: rgba(255, 255, 255, 0.04);
      border-bottom: 1px solid var(--arena-border);
      text-align: left;
    }
    .stats-header-bar h3 {
      font-size: 15px;
      font-weight: 800;
      color: #fff;
      margin: 0 0 2px 0;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .stats-header-bar p {
      font-size: 12px;
      color: var(--arena-muted);
      margin: 0;
    }
    .stats-summary-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 8px;
      padding: 12px 16px;
      background: rgba(0,0,0,0.15);
      border-bottom: 1px solid var(--arena-border);
    }
    .stat-metric-card {
      background: rgba(255, 255, 255, 0.04);
      border: 1px solid rgba(255, 255, 255, 0.06);
      border-radius: 12px;
      padding: 8px 10px;
      text-align: center;
    }
    .stat-metric-val {
      font-size: 16px;
      font-weight: 800;
      color: #fff;
      line-height: 1.2;
    }
    .stat-metric-lbl {
      font-size: 11px;
      color: var(--arena-muted);
      margin-top: 2px;
    }
    .round-stats-table-wrap {
      width: 100%;
      max-height: 340px;
      overflow-y: auto;
    }
    .round-stats-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 12px;
      text-align: left;
      margin: 0;
    }
    .round-stats-table th {
      position: sticky;
      top: 0;
      background: #0f172a;
      padding: 9px 12px;
      font-size: 11px;
      font-weight: 700;
      color: var(--arena-muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      border-bottom: 1px solid var(--arena-border);
      z-index: 1;
    }
    .round-stats-table td {
      padding: 8px 12px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.05);
      vertical-align: middle;
      color: var(--arena-text);
    }
    .round-stats-table tr:hover {
      background: rgba(255, 255, 255, 0.03);
    }
    .color-swatch-cell {
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    .color-swatch {
      width: 18px;
      height: 18px;
      border-radius: 5px;
      border: 1px solid rgba(255, 255, 255, 0.35);
      box-shadow: 0 1px 4px rgba(0,0,0,0.3);
      flex-shrink: 0;
    }
    .swatch-hex {
      font-family: monospace;
      font-size: 11px;
      color: #e2e8f0;
    }
    .victory-standings {
      width: 100%;
      background: rgba(0, 0, 0, 0.25);
      border: 1px solid var(--arena-border);
      border-radius: 16px;
      overflow: hidden;
      margin-bottom: 8px;
    }
    .standings-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px 14px;
      background: rgba(255, 255, 255, 0.04);
      border-bottom: 1px solid var(--arena-border);
      font-size: 12px;
      font-weight: 700;
      color: var(--arena-muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .standings-table-wrap {
      width: 100%;
      max-height: 240px;
      overflow-y: auto;
    }
    .standings-table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
      font-size: 13px;
      margin: 0;
    }
    .standings-table th {
      padding: 8px 12px;
      font-size: 11px;
      font-weight: 700;
      color: var(--arena-muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      background: rgba(0, 0, 0, 0.15);
      border-bottom: 1px solid var(--arena-border);
    }
    .standings-table td {
      padding: 9px 12px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.05);
      vertical-align: middle;
      color: var(--arena-text);
    }
    .standings-table tr:last-child td {
      border-bottom: none;
    }
    .standings-table tr.row-winner {
      background: rgba(255, 193, 7, 0.07);
    }
    .standings-table tr.row-me {
      background: rgba(255, 107, 91, 0.1);
      font-weight: 600;
    }
    .rank-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 24px;
      height: 24px;
      font-size: 14px;
      font-weight: 800;
    }
    .rank-badge.rank-other {
      font-size: 12px;
      color: var(--arena-muted);
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.06);
    }
    .player-cell {
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    .badge-you {
      font-size: 10px;
      padding: 2px 6px;
      border-radius: 999px;
      background: rgba(255, 107, 91, 0.25);
      border: 1px solid rgba(255, 107, 91, 0.5);
      color: #ff8c42;
      font-weight: 700;
    }
    .score-badge {
      font-weight: 700;
      color: #ffd166;
    }
    .score-pts {
      font-size: 11px;
      font-weight: 500;
      color: var(--arena-muted);
    }
    .status-pill {
      display: inline-flex;
      align-items: center;
      gap: 3px;
      font-size: 11px;
      padding: 2px 8px;
      border-radius: 999px;
      font-weight: 600;
      white-space: nowrap;
    }
    .status-pill.elim {
      background: rgba(239, 68, 68, 0.15);
      color: #f87171;
      border: 1px solid rgba(239, 68, 68, 0.3);
    }
    .status-pill.active {
      background: rgba(32, 183, 125, 0.15);
      color: #34d399;
      border: 1px solid rgba(32, 183, 125, 0.3);
    }

    @media (max-width: 600px) {
      .arena-stage {
        padding: 20px 12px;
        min-height: 380px;
        border-radius: 18px;
      }
      .choice-grid {
        gap: 8px;
      }
      .choice-cell {
        border-radius: 12px;
        border-width: 2px;
      }
      .arena-hud {
        gap: 8px;
      }
      .hud-chip {
        padding: 8px 12px;
      }
      .hud-val {
        font-size: 15px;
      }
      .stats-summary-grid {
        grid-template-columns: repeat(2, 1fr);
      }
      .podium-stage {
        gap: 6px;
      }
      .pedestal-1 { height: 95px; }
      .pedestal-2 { height: 72px; }
      .pedestal-3 { height: 52px; }
      .podium-col-1 .podium-num { font-size: 32px; }
      .podium-num { font-size: 26px; }
      .podium-col-1 .podium-avatar { width: 44px; height: 44px; font-size: 22px; }
      .podium-avatar { width: 36px; height: 36px; font-size: 18px; }
      .podium-name { font-size: 12px; }
    }

    /* In-Game Debug Log Drawer & Waiting Notice Styles */
    .waiting-others-box {
      background: rgba(245, 158, 11, 0.12);
      border: 1px solid rgba(245, 158, 11, 0.3);
      border-radius: 12px;
      padding: 10px 16px;
      color: #fbbf24;
      font-size: 14px;
      animation: pulseGlow 1.8s infinite ease-in-out;
    }
    @keyframes pulseGlow {
      0%, 100% { opacity: 0.85; transform: scale(1); }
      50% { opacity: 1; transform: scale(1.01); }
    }
    .debug-log-toggle {
      position: fixed;
      bottom: 12px;
      right: 12px;
      z-index: 1050;
      background: rgba(15, 23, 42, 0.88);
      border: 1px solid rgba(255, 255, 255, 0.18);
      color: #94a3b8;
      font-size: 12px;
      font-weight: 600;
      padding: 5px 12px;
      border-radius: 999px;
      cursor: pointer;
      backdrop-filter: blur(10px);
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.35);
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    .debug-log-toggle:hover {
      color: #38bdf8;
      border-color: rgba(56, 189, 248, 0.5);
      transform: translateY(-2px);
    }
    .debug-log-drawer {
      position: fixed;
      bottom: 50px;
      right: 12px;
      width: min(640px, 94vw);
      height: 340px;
      z-index: 1050;
      background: rgba(15, 23, 42, 0.96);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 14px;
      box-shadow: 0 16px 40px rgba(0, 0, 0, 0.65);
      backdrop-filter: blur(16px);
      display: flex;
      flex-direction: column;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      font-size: 11px;
    }
    .debug-log-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 8px 14px;
      background: rgba(30, 41, 59, 0.85);
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 14px 14px 0 0;
      color: #cbd5e1;
      font-weight: bold;
    }
    .debug-log-body {
      flex: 1;
      overflow-y: auto;
      padding: 8px 12px;
      display: flex;
      flex-direction: column;
      gap: 5px;
      color: #e2e8f0;
      white-space: pre-wrap;
      word-break: break-all;
    }
    .debug-log-line {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      align-items: baseline;
      line-height: 1.4;
      padding: 2px 4px;
      border-radius: 4px;
    }
    .debug-log-line:hover {
      background: rgba(255, 255, 255, 0.04);
    }
    .debug-log-error { color: #f87171; }
    .debug-log-warn { color: #facc15; }
    .debug-log-info { color: #38bdf8; }
  </style>
</head>
<body class="pm-has-fixed-header">
  <?php include __DIR__ . '/header.php'; ?>

  <div class="arena-container">
    <!-- Top HUD Bar -->
    <div class="arena-hud">
      <div class="hud-chip">
        <span class="hud-label"><?= htmlspecialchars(tt('hud_round', 'Round')) ?></span>
        <span id="hudRound" class="hud-val">-</span>
      </div>
      <div class="hud-chip">
        <span class="hud-label"><?= htmlspecialchars(tt('hud_score', 'Score')) ?></span>
        <span id="hudScore" class="hud-val">0</span>
      </div>
      <div class="hud-chip">
        <span class="hud-label"><?= htmlspecialchars(tt('hud_time', 'Time')) ?></span>
        <span id="hudTimer" class="hud-val">-</span>
      </div>
      <div class="hud-chip">
        <span class="hud-label"><?= htmlspecialchars(tt('room_status', 'Status')) ?></span>
        <span id="hudStatus" class="hud-val text-warning"><?= htmlspecialchars(tt('status_connecting', 'Connecting...')) ?></span>
      </div>
    </div>

    <!-- Main Live Game Stage -->
    <main class="arena-stage" id="arenaStage">
      <div class="stage-center" id="stageContent">
        <!-- Default: Waiting Room / Lobby -->
        <div class="fs-1">⏳</div>
        <h1 class="stage-title">
          <span><?= htmlspecialchars($room['name'] ?: tt('room_default_name', 'Match Room')) ?></span>
          <?php if (($room['game_mode'] ?? 'elimination') === 'points'): ?>
            <span class="badge bg-warning-subtle text-warning fs-6 text-nowrap align-middle border border-warning-subtle ms-1">
              ⚡ <?= htmlspecialchars(tt('rooms_mode_points_short', 'Puan Yarışı')) ?>
            </span>
          <?php elseif (($room['game_mode'] ?? 'elimination') === 'flags'): ?>
            <span class="badge bg-info-subtle text-info fs-6 text-nowrap align-middle border border-info-subtle ms-1">
              🚩 <?= htmlspecialchars(tt('rooms_mode_flags_short', 'Bayrak Modu')) ?>
            </span>
          <?php else: ?>
            <span class="badge bg-danger-subtle text-danger fs-6 text-nowrap align-middle border border-danger-subtle ms-1">
              💀 <?= htmlspecialchars(tt('rooms_mode_elim_short', 'Eleme Modu')) ?>
            </span>
          <?php endif; ?>
          <?php if (!empty($room['is_private'])): ?>
            <span class="badge bg-secondary-subtle text-secondary fs-6 text-nowrap align-middle border border-secondary-subtle ms-1">
              🔒 <?= htmlspecialchars(tt('rooms_private_badge', 'Private')) ?>
            </span>
          <?php endif; ?>
        </h1>
        <p class="stage-subtitle">
          <?= htmlspecialchars(
            !empty($room['is_private'])
              ? tt('room_waiting_desc_private', 'Private room: Only players with the link can join. Waiting for players to gather!')
              : tt('room_waiting_desc', 'Waiting for players to gather. The room host can launch round 1 whenever ready!')
          ) ?>
        </p>
        
        <div class="d-flex flex-wrap gap-2 justify-content-center mt-2">
          <button id="copyInviteLinkBtn" class="btn btn-outline-light" type="button">
            🔗 <?= htmlspecialchars(tt('room_copy_invite', 'Copy Invite Link')) ?>
          </button>
          <a id="shareWhatsappLobbyBtn" class="btn btn-success d-inline-flex align-items-center gap-1" href="#" target="_blank" rel="noopener noreferrer">
            <span>💬</span> <?= htmlspecialchars(tt('rooms_share_whatsapp', 'WhatsApp ile Gönder')) ?>
          </a>
          <div id="hostControls" class="d-none">
            <button id="startMatchBtn" class="btn btn-action btn-lg">
              🚀 <?= htmlspecialchars(tt('room_start_btn', 'Start Match')) ?>
            </button>
            <div id="minPlayersNotice" class="small text-warning mt-2 fw-semibold text-center"></div>
          </div>
        </div>
      </div>
    </main>

    <!-- Player Roster & Presence -->
    <div class="players-bar">
      <div class="small fw-bold text-uppercase text-secondary me-2"><?= htmlspecialchars(tt('room_players', 'Players')) ?>:</div>
      <div id="playerList" class="d-flex flex-wrap gap-2 align-items-center flex-1">
        <!-- Live pills dynamically populated -->
      </div>
    </div>
  </div>

  <!-- Feedback Toast -->
  <div id="feedbackToast" class="feedback-toast">
    <span id="toastIcon"></span>
    <span id="toastText"></span>
  </div>

  <?php if ($canViewLogs): ?>
  <!-- In-Game Debug / Log Panel Toggle & Drawer (yilmazmukerrem@gmail.com only) -->
  <div id="debugLogToggleBtn" class="debug-log-toggle" title="Debug / Hata Ayıklama">
    🐞 <?= htmlspecialchars(tt('room_debug_logs', 'Logs')) ?>
  </div>
  <div id="debugLogDrawer" class="debug-log-drawer d-none">
    <div class="debug-log-header">
      <span>🐞 <?= htmlspecialchars(tt('room_debug_logs', 'Oda Oyunu Logları / Debug Console')) ?></span>
      <div class="d-flex gap-2">
        <button id="debugCopyLogsBtn" class="btn btn-sm btn-outline-light py-0">📋 <?= htmlspecialchars(tt('room_copy_logs', 'Kopyala')) ?></button>
        <button id="debugClearLogsBtn" class="btn btn-sm btn-outline-secondary py-0">🧹 <?= htmlspecialchars(tt('room_clear_logs', 'Temizle')) ?></button>
        <button id="debugCloseLogsBtn" class="btn btn-sm btn-outline-danger py-0">✕</button>
      </div>
    </div>
    <div id="debugLogDrawerBody" class="debug-log-body"></div>
  </div>
  <?php endif; ?>

  <?php include __DIR__ . '/footer.php'; ?>

  <!-- Pusher JS -->
  <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
  <script>
    const CAN_VIEW_LOGS = <?= json_encode($canViewLogs) ?>;
    const ME_EMAIL = <?= json_encode($userEmail) ?>;
    const ME_ID = <?= json_encode((string)$userId) ?>;
    const GUID = <?= json_encode($guid) ?>;
    const PUSHER_KEY = <?= json_encode(defined('PUSHER_KEY') ? PUSHER_KEY : '') ?>;
    const PUSHER_CLUSTER = <?= json_encode(defined('PUSHER_CLUSTER') ? PUSHER_CLUSTER : 'eu') ?>;
    const ROOM_GAME_MODE = <?= json_encode($room['game_mode'] ?? 'elimination') ?>;

    const STR = {
      correct: <?= json_encode(tt('badge_correct', 'Correct!')) ?>,
      wrong: <?= json_encode(tt('badge_wrong', 'Wrong color!')) ?>,
      wrongPenalty: <?= json_encode(tt('room_wrong_points', 'Wrong pick! Points deducted.')) ?>,
      timeUp: <?= json_encode(tt('badge_timeup', 'Time Up!')) ?>,
      timeUpZeroPoints: <?= json_encode(tt('room_timeout_points', 'Time up! 0 points.')) ?>,
      modePoints: <?= json_encode(tt('rooms_mode_points_short', 'Points Race')) ?>,
      modeElimination: <?= json_encode(tt('rooms_mode_elim_short', 'Elimination')) ?>,
      eliminated: <?= json_encode(tt('room_eliminated', 'Eliminated')) ?>,
      spectating: <?= json_encode(tt('room_spectating', 'Spectator Mode')) ?>,
      ready: <?= json_encode(tt('status_ready', 'Ready')) ?>,
      waiting: <?= json_encode(tt('room_waiting', 'Waiting for Host...')) ?>,
      active: <?= json_encode(tt('room_active', 'Active')) ?>,
      finished: <?= json_encode(tt('status_finished', 'Finished')) ?>,
      rememberColor: <?= json_encode(tt('remember_this', 'Remember this color!')) ?>,
      rememberFlag: <?= json_encode(tt('remember_flag', 'Remember this flag!')) ?>,
      pickColor: <?= json_encode(tt('question_pick_target', 'Which color was shown?')) ?>,
      pickFlag: <?= json_encode(tt('question_pick_target_flag', 'Which flag was shown?')) ?>,
      nextRoundIn: <?= json_encode(tt('room_next_in', 'Next round starting soon...')) ?>,
      winner: <?= json_encode(tt('room_winner_announcement', 'Winner!')) ?>,
      getReady: <?= json_encode(tt('status_get_ready', 'Get Ready!')) ?>,
      watchScreen: <?= json_encode(tt('room_watch_screen', 'Watch the screen carefully...')) ?>,
      memorize: <?= json_encode(tt('status_memorize', 'Memorize!')) ?>,
      showingTarget: <?= json_encode(tt('badge_showing_target', 'Showing target color...')) ?>,
      showingTargetFlag: <?= json_encode(tt('badge_showing_target_flag', 'Showing target flag...')) ?>,
      pickColorUpper: <?= json_encode(tt('badge_pick_color', 'PICK COLOR!')) ?>,
      pickFlagUpper: <?= json_encode(tt('badge_pick_flag', 'PICK FLAG!')) ?>,
      eliminatedSubtitle: <?= json_encode(tt('room_eliminated_subtitle', 'You are eliminated. Spectating alive players...')) ?>,
      chooseFast: <?= json_encode(tt('room_choose_fast', 'Choose fast for maximum score!')) ?>,
      roundComplete: <?= json_encode(tt('room_round_complete', 'Round {round} Complete')) ?>,
      winnerEveryone: <?= json_encode(tt('room_winner_everyone', 'Everyone')) ?>,
      concludedDesc: <?= json_encode(tt('room_concluded_desc', 'The match has concluded. Great performance by all players!')) ?>,
      backToRooms: <?= json_encode(tt('rooms_back', 'Back to Rooms')) ?>,
      errorJoining: <?= json_encode(tt('room_error_joining', 'Error Joining')) ?>,
      connError: <?= json_encode(tt('room_conn_error', 'Connection Error')) ?>,
      starting: <?= json_encode(tt('room_starting', 'Starting...')) ?>,
      you: <?= json_encode(tt('you_parentheses', '(You)')) ?>,
      pts: <?= json_encode(tt('unit_points', 'pts')) ?>,
      player: <?= json_encode(tt('room_player', 'Player')) ?>,
      score: <?= json_encode(tt('room_score', 'Score')) ?>,
      status: <?= json_encode(tt('room_status', 'Status')) ?>,
      roomLeaderboard: <?= json_encode(tt('room_leaderboard', 'Leaderboard')) ?>,
      players: <?= json_encode(tt('room_players', 'Players')) ?>,
      youWon: <?= json_encode(tt('room_you_won', 'Congratulations, You Won!')) ?>,
      rank: <?= json_encode(tt('leaderboard_col_rank', 'Rank')) ?>,
      restartMatch: <?= json_encode(tt('room_restart_btn', 'Restart Match')) ?>,
      backToLobby: <?= json_encode(tt('room_back_to_lobby', 'Back to Lobby')) ?>,
      restarting: <?= json_encode(tt('room_restarting', 'Restarting...')) ?>,
      roomRestarted: <?= json_encode(tt('room_restarted', 'The match was restarted by the host!')) ?>,
      waitingDesc: <?= json_encode(tt('room_waiting_desc', 'Waiting for players to gather. The room host can launch round 1 whenever ready!')) ?>,
      waitingDescPrivate: <?= json_encode(tt('room_waiting_desc_private', 'Private room: Only players with the link can join. Waiting for players to gather!')) ?>,
      startMatch: <?= json_encode(tt('room_start_btn', 'Start Match')) ?>,
      copyInvite: <?= json_encode(tt('room_copy_invite', 'Copy Invite Link')) ?>,
      inviteCopied: <?= json_encode(tt('room_invite_copied', 'Invite link copied to clipboard! Share it with your friends.')) ?>,
      shareWhatsapp: <?= json_encode(tt('rooms_share_whatsapp', 'WhatsApp ile Gönder')) ?>,
      shareWhatsappText: <?= json_encode(tt('rooms_share_whatsapp_text', "Prismatch'te benimle oda oyununa katıl! 🎮 Bağlantı:")) ?>,
      privateBadge: <?= json_encode(tt('rooms_private_badge', 'Private')) ?>,
      errorGeneric: <?= json_encode(tt('error_generic', 'An error occurred. Please try again.')) ?>,
      minPlayersRequired: <?= json_encode(tt('room_min_players', 'Room games can only be started when at least 2 players have joined.')) ?>,
      waitingMinPlayers: <?= json_encode(tt('room_waiting_min_players', 'Waiting for at least 2 players to start...')) ?>,
      winBonus: <?= json_encode(tt('room_win_bonus', 'Win Bonus')) ?>,
      waitingOthers: <?= json_encode(tt('room_waiting_others', 'Seçiminiz kaydedildi. Diğer oyuncular bekleniyor...')) ?>,
      offline: <?= json_encode(tt('room_player_offline', 'Ayrıldı')) ?>,
      online: <?= json_encode(tt('room_player_online', 'Çevrimiçi')) ?>,
      personalStatsTitle: <?= json_encode(tt('room_personal_stats', 'Tur İstatistikleriniz')) ?>,
      personalStatsDesc: <?= json_encode(tt('room_personal_stats_desc', 'Her turdaki hedef renk, yaptığınız seçim, tepki süreniz ve puan değişiminiz')) ?>,
      personalStatsDescFlag: <?= json_encode(tt('room_personal_stats_desc_flag', 'Her turdaki hedef bayrak, yaptığınız seçim, tepki süreniz ve puan değişiminiz')) ?>,
      statAvgSpeed: <?= json_encode(tt('room_stat_avg_speed', 'Ortalama Süre')) ?>,
      statFastest: <?= json_encode(tt('room_stat_fastest', 'En Hızlı')) ?>,
      statAccuracy: <?= json_encode(tt('room_stat_accuracy', 'İsabet Oranı')) ?>,
      statTotalDelta: <?= json_encode(tt('room_stat_total_delta', 'Net Puan')) ?>,
      colRound: <?= json_encode(tt('room_col_round', 'Tur')) ?>,
      colTarget: <?= json_encode(tt('room_col_target', 'Hedef Renk')) ?>,
      colTargetFlag: <?= json_encode(tt('room_col_target_flag', 'Hedef Bayrak')) ?>,
      colPicked: <?= json_encode(tt('room_col_picked', 'Verilen Cevap')) ?>,
      colPickedFlag: <?= json_encode(tt('room_col_picked_flag', 'Seçilen Bayrak')) ?>,
      colTime: <?= json_encode(tt('room_col_time', 'Süre')) ?>,
      colResult: <?= json_encode(tt('room_col_result', 'Sonuç & Puan')) ?>,
      colCorrect: <?= json_encode(tt('room_col_correct', 'Doğru')) ?>,
      statTimeoutBadge: <?= json_encode(tt('room_stat_timeout_badge', 'Süre Doldu')) ?>,
    };

    function escapeHtml(s) {
      return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function isFlagTarget(val) {
      if (state.gameMode === 'flags') return true;
      if (typeof val === 'string' && val.toLowerCase().endsWith('.png')) return true;
      return false;
    }

    function formatFlagLabel(path) {
      if (!path) return '';
      const base = String(path).split('/').pop() || '';
      return base.replace(/\.png$/i, '').replace(/[-_]/g, ' ');
    }

    // In-Browser Debug Logger with visual drawer integration
    const RoomLogger = {
      logs: [],
      maxLogs: 250,
      log(type, tag, msg, data = null) {
        const now = new Date();
        const time = now.toTimeString().split(' ')[0] + '.' + String(now.getMilliseconds()).padStart(3, '0');
        const entry = { time, type, tag, msg, data };
        this.logs.push(entry);
        if (this.logs.length > this.maxLogs) this.logs.shift();
        window.RoomLogHistory = this.logs;

        const colorMap = {
          INFO: 'color: #38bdf8; font-weight: 600',
          WARN: 'color: #facc15; font-weight: 600',
          ERROR: 'color: #f87171; font-weight: 700',
          DEBUG: 'color: #a78bfa'
        };
        const style = colorMap[type] || 'color: #94a3b8';
        if (data !== null) {
          console.log(`%c[${time}] [${type}] [${tag}] ${msg}`, style, data);
        } else {
          console.log(`%c[${time}] [${type}] [${tag}] ${msg}`, style);
        }

        this.renderToDrawer();
      },
      info(tag, msg, data = null) { this.log('INFO', tag, msg, data); },
      warn(tag, msg, data = null) { this.log('WARN', tag, msg, data); },
      error(tag, msg, data = null) { this.log('ERROR', tag, msg, data); },
      debug(tag, msg, data = null) { this.log('DEBUG', tag, msg, data); },

      renderToDrawer() {
        const drawerBody = document.getElementById('debugLogDrawerBody');
        const drawer = document.getElementById('debugLogDrawer');
        if (!drawerBody || !drawer || drawer.classList.contains('d-none')) return;

        drawerBody.innerHTML = this.logs.map(l => `
          <div class="debug-log-line debug-log-${l.type.toLowerCase()}">
            <span class="text-secondary">[${l.time}]</span>
            <span class="badge ${l.type === 'ERROR' ? 'bg-danger' : (l.type === 'WARN' ? 'bg-warning text-dark' : 'bg-secondary')} py-0 px-1">${l.type}</span>
            <span class="text-info fw-semibold">[${escapeHtml(l.tag)}]</span>
            <span>${escapeHtml(l.msg)}</span>
            ${l.data !== null ? `<span class="text-muted small">${escapeHtml(typeof l.data === 'object' ? JSON.stringify(l.data) : String(l.data))}</span>` : ''}
          </div>
        `).join('');
        drawerBody.scrollTop = drawerBody.scrollHeight;
      }
    };

    function wireDebugLogs() {
      if (!CAN_VIEW_LOGS) return;
      const toggleBtn = document.getElementById('debugLogToggleBtn');
      const drawer = document.getElementById('debugLogDrawer');
      const closeBtn = document.getElementById('debugCloseLogsBtn');
      const clearBtn = document.getElementById('debugClearLogsBtn');
      const copyBtn = document.getElementById('debugCopyLogsBtn');

      toggleBtn?.addEventListener('click', () => {
        if (!drawer) return;
        const isHidden = drawer.classList.contains('d-none');
        if (isHidden) {
          drawer.classList.remove('d-none');
          RoomLogger.renderToDrawer();
        } else {
          drawer.classList.add('d-none');
        }
      });

      closeBtn?.addEventListener('click', () => {
        drawer?.classList.add('d-none');
      });

      clearBtn?.addEventListener('click', () => {
        RoomLogger.logs = [];
        window.RoomLogHistory = [];
        const drawerBody = document.getElementById('debugLogDrawerBody');
        if (drawerBody) drawerBody.innerHTML = '';
        RoomLogger.info('DebugConsole', 'Log history cleared by user');
      });

      copyBtn?.addEventListener('click', async () => {
        const text = RoomLogger.logs.map(l => `[${l.time}] [${l.type}] [${l.tag}] ${l.msg} ${l.data ? JSON.stringify(l.data) : ''}`).join('\n');
        try {
          await navigator.clipboard.writeText(text);
          copyBtn.textContent = '✅';
          setTimeout(() => { copyBtn.textContent = '📋 ' + (STR.copyLogs || 'Kopyala'); }, 1500);
        } catch(e) {}
      });
    }

    const state = {
      round: 0,
      roundsTotal: <?= (int)($room['rounds_total'] ?? 25) ?>,
      gameMode: ROOM_GAME_MODE,
      score: 0,
      phase: 'lobby', // 'lobby', 'countdown', 'show', 'question', 'intermission', 'finished'
      targetColor: null,
      gridColors: [],
      showMs: 3000,
      answerMs: 5000,
      countdownMs: 3000,
      eliminated: false,
      answered: false,
      isHost: false,
      questionStartTs: 0,
      activeTimer: null,
      countdownInterval: null,
      intermissionTimer: null,
      players: [],
      presenceMemberIds: new Set(),
      myRoundStats: [],
    };

    function isPlayerOnline(p) {
      if (!p) return false;
      const uid = String(p.user_id || '');
      const email = String(p.email || '');
      if (email === ME_EMAIL || (ME_ID && uid === ME_ID)) return true;
      if (state.presenceMemberIds && state.presenceMemberIds.has(uid)) return true;
      return (p.is_online !== 0 && p.is_online !== false && p.is_online !== '0');
    }

    // UI Elements
    const hudRound = document.getElementById('hudRound');
    const hudScore = document.getElementById('hudScore');
    const hudTimer = document.getElementById('hudTimer');
    const hudStatus = document.getElementById('hudStatus');
    const stageContent = document.getElementById('stageContent');
    const playerList = document.getElementById('playerList');
    const hostControls = document.getElementById('hostControls');
    const startMatchBtn = document.getElementById('startMatchBtn');
    const feedbackToast = document.getElementById('feedbackToast');
    const toastIcon = document.getElementById('toastIcon');
    const toastText = document.getElementById('toastText');

    // Web Audio Sound Engine
    let audioCtx = null;
    function playTone(freq, dur = 0.12, type = 'sine', gain = 0.08) {
      try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return;
        audioCtx = audioCtx || new Ctx();
        if (audioCtx.state === 'suspended') audioCtx.resume();
        const osc = audioCtx.createOscillator();
        const g = audioCtx.createGain();
        osc.type = type;
        osc.frequency.value = freq;
        g.gain.setValueAtTime(gain, audioCtx.currentTime);
        g.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + dur);
        osc.connect(g);
        g.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + dur);
      } catch(e) {}
    }

    function showToast(text, isCorrect) {
      toastIcon.textContent = isCorrect ? '✅' : '❌';
      toastText.textContent = text;
      feedbackToast.className = 'feedback-toast show ' + (isCorrect ? 'correct' : 'wrong');
      setTimeout(() => { feedbackToast.classList.remove('show'); }, 2200);
    }

    function renderPlayers(players) {
      if (Array.isArray(players) && players.length > 0) {
        state.players = players;
      }
      if (!playerList) return;
      playerList.innerHTML = '';
      (state.players || []).forEach(p => {
        const isMe = p.email === ME_EMAIL || String(p.user_id) === String(ME_ID);
        const isElim = p.status === 'eliminated';
        const isOnline = isPlayerOnline(p);

        const tag = document.createElement('div');
        tag.className = 'player-tag' + (isMe ? ' self' : '') + (isElim ? ' eliminated' : '') + (!isOnline ? ' offline' : '');

        const dot = document.createElement('span');
        dot.className = 'live-dot';
        if (isElim || !isOnline) {
          dot.style.background = '#64748b';
          dot.style.boxShadow = 'none';
        }
        tag.appendChild(dot);

        const nameSpan = document.createElement('span');
        let statusSuffix = '';
        if (isElim) statusSuffix = ' 💀';
        else if (!isOnline) statusSuffix = ` (${STR.offline})`;
        nameSpan.textContent = (p.email ? p.email.split('@')[0] : (p.nickname || STR.player)) + (isMe ? ' ' + STR.you : '') + statusSuffix;
        tag.appendChild(nameSpan);

        if (Number(p.score) !== 0) {
          const scoreBadge = document.createElement('span');
          const isNeg = Number(p.score) < 0;
          scoreBadge.className = 'badge ' + (isNeg ? 'bg-danger-subtle text-danger' : 'bg-dark-subtle text-dark-emphasis') + ' ms-1';
          scoreBadge.textContent = p.score + ' ' + STR.pts;
          tag.appendChild(scoreBadge);
        }

        playerList.appendChild(tag);
      });

      if (state.phase === 'lobby' && state.isHost) {
        updateStartButtonState();
      }
    }

    function updateStartButtonState() {
      const btn = document.getElementById('startMatchBtn');
      const notice = document.getElementById('minPlayersNotice');
      if (!btn) return;
      const onlinePlayers = (state.players || []).filter(isPlayerOnline);
      const count = onlinePlayers.length;
      if (count < 2) {
        btn.disabled = true;
        btn.classList.add('opacity-75');
        if (notice) {
          notice.className = 'small text-warning mt-2 fw-semibold text-center';
          notice.textContent = `👥 ${STR.waitingMinPlayers} (${count}/2)`;
        }
      } else {
        btn.disabled = false;
        btn.classList.remove('opacity-75');
        if (notice) {
          notice.className = 'small text-success mt-2 fw-semibold text-center';
          notice.textContent = `✅ ${STR.ready} (${count} ${STR.players})`;
        }
      }
    }

    // Step 1: Countdown Phase
    function runCountdown() {
      state.phase = 'countdown';
      hudStatus.textContent = STR.getReady;
      hudStatus.className = 'hud-val text-info';
      playTone(520, 0.08, 'square');

      if (state.countdownInterval) {
        clearInterval(state.countdownInterval);
        clearTimeout(state.countdownInterval);
        state.countdownInterval = null;
      }

      const isFlag = isFlagTarget(state.targetColor);
      const remTitle = isFlag ? STR.rememberFlag : STR.rememberColor;

      // Sonraki tura geçişte (round > 1 veya countdownMs <= 1000): 3-2-1 diye sayma, 1 saniye sonra sonraki tura geç
      if (state.round > 1 || state.countdownMs <= 1000) {
        stageContent.innerHTML = `
          <h2 class="stage-title">${remTitle}</h2>
          <div class="fs-1 my-3">${isFlag ? '🚩' : '🎯'}</div>
          <div class="stage-subtitle">${STR.watchScreen}</div>
        `;

        state.countdownInterval = setTimeout(() => {
          state.countdownInterval = null;
          runTargetShow();
        }, 1000);
        return;
      }

      // İlk tur / Maç başlangıcı: 3-2-1 geri sayımı
      let remaining = Math.max(1, Math.round(state.countdownMs / 1000));
      stageContent.innerHTML = `
        <h2 class="stage-title">${remTitle}</h2>
        <div class="big-countdown" id="countdownNum">${remaining}</div>
        <div class="stage-subtitle">${STR.watchScreen}</div>
      `;

      state.countdownInterval = setInterval(() => {
        remaining -= 1;
        const el = document.getElementById('countdownNum');
        if (remaining > 0) {
          playTone(remaining === 1 ? 760 : 520, 0.08, 'square');
          if (el) el.textContent = String(remaining);
        } else {
          clearInterval(state.countdownInterval);
          state.countdownInterval = null;
          runTargetShow();
        }
      }, 1000);
    }

    // Step 2: Target Color / Flag Show
    function runTargetShow() {
      state.phase = 'show';
      hudStatus.textContent = STR.memorize;
      hudStatus.className = 'hud-val text-warning';

      const isFlag = isFlagTarget(state.targetColor);
      const remTitle = isFlag ? STR.rememberFlag : STR.rememberColor;
      const showSub = isFlag ? STR.showingTargetFlag : STR.showingTarget;

      let boxHtml = '';
      if (isFlag) {
        boxHtml = `
          <div class="target-box target-box-flag">
            <img src="${escapeHtml(state.targetColor)}" class="target-flag-img" alt="Target Flag">
          </div>
        `;
      } else {
        boxHtml = `<div class="target-box" style="background: ${state.targetColor}"></div>`;
      }

      stageContent.innerHTML = `
        <h2 class="stage-title">${remTitle}</h2>
        ${boxHtml}
        <div class="stage-subtitle">${showSub}</div>
      `;

      setTimeout(() => {
        runQuestion();
      }, state.showMs);
    }

    // Step 3: Question & Interactive Grid Phase
    function runQuestion() {
      state.phase = 'question';
      state.answered = false;
      if (state.intermissionTimer) clearTimeout(state.intermissionTimer);
      state.questionStartTs = performance.now();

      const isFlag = isFlagTarget(state.targetColor);
      const pickTitle = isFlag ? STR.pickFlag : STR.pickColor;
      const pickUpper = isFlag ? STR.pickFlagUpper : STR.pickColorUpper;

      hudStatus.textContent = state.eliminated ? STR.spectating : pickUpper;
      hudStatus.className = 'hud-val ' + (state.eliminated ? 'text-secondary' : 'text-success');

      stageContent.innerHTML = `
        <h2 class="stage-title">${pickTitle}</h2>
        <div class="stage-subtitle">${state.eliminated ? STR.eliminatedSubtitle : STR.chooseFast}</div>
        <div class="choice-grid" id="choiceGrid"></div>
      `;

      const gridEl = document.getElementById('choiceGrid');
      const cells = [];
      const count = (state.gridColors && state.gridColors.length) || 9;
      const cols = Math.round(Math.sqrt(count)) || 3;
      gridEl.style.gridTemplateColumns = `repeat(${cols}, 1fr)`;
      if (cols >= 4) {
        gridEl.style.gap = cols >= 5 ? '6px' : '8px';
      }

      state.gridColors.forEach(item => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'choice-cell';
        if (isFlag) {
          btn.classList.add('choice-cell-flag');
          btn.innerHTML = `<img src="${escapeHtml(item)}" class="choice-flag-img" alt="Flag">`;
        } else {
          btn.style.background = item;
        }
        if (cols >= 5) {
          btn.style.borderRadius = '10px';
        } else if (cols >= 4) {
          btn.style.borderRadius = '14px';
        }
        btn.disabled = state.eliminated;
        btn.onclick = () => onChoicePick(item, btn, cells);
        gridEl.appendChild(btn);
        cells.push(btn);
      });

      // Timer countdown for answering
      let remaining = state.answerMs;
      hudTimer.textContent = (remaining / 1000).toFixed(1) + 's';

      if (state.activeTimer) clearInterval(state.activeTimer);
      state.activeTimer = setInterval(() => {
        remaining -= 100;
        if (remaining >= 0) {
          hudTimer.textContent = (remaining / 1000).toFixed(1) + 's';
        }
        if (remaining <= 0) {
          clearInterval(state.activeTimer);
          if (!state.answered && !state.eliminated) {
            onTimeOut(cells);
          }
        }
      }, 100);
    }

    async function onChoicePick(color, btn, cells) {
      if (state.answered || state.eliminated || state.phase !== 'question') return;
      state.answered = true;
      cells.forEach(c => c.disabled = true);
      if (state.activeTimer) clearInterval(state.activeTimer);

      const isCorrect = color === state.targetColor;
      const responseMs = Math.round(performance.now() - state.questionStartTs);

      if (isCorrect) {
        btn.classList.add('correct');
        playTone(660, 0.1, 'triangle');
        setTimeout(() => playTone(880, 0.12, 'triangle'), 100);
        showToast(STR.correct, true);
      } else {
        btn.classList.add('wrong');
        playTone(220, 0.2, 'sawtooth');
        if (state.gameMode === 'elimination') {
          showToast(STR.wrong, false);
          state.eliminated = true;
        } else {
          showToast(STR.wrongPenalty || STR.wrong, false);
          state.eliminated = false;
        }
      }

      // Show immediate waiting notice so player knows selection registered
      const gridEl = document.getElementById('choiceGrid');
      if (gridEl && !document.getElementById('waitingOthersNotice')) {
        const notice = document.createElement('div');
        notice.id = 'waitingOthersNotice';
        notice.className = 'waiting-others-box mt-3 text-center fw-semibold';
        notice.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>${STR.waitingOthers}`;
        gridEl.parentNode.appendChild(notice);
      }

      const myStatEntry = {
        round: state.round,
        target: state.targetColor,
        picked: color,
        is_timeout: false,
        correct: isCorrect,
        response_ms: responseMs,
        score_delta: 0
      };
      state.myRoundStats.push(myStatEntry);

      RoomLogger.info('Answer', `Picked color=${color} (${isCorrect ? 'CORRECT' : 'WRONG'}) in ${responseMs}ms for round ${state.round}`);

      try {
        const res = await fetch('api/rooms_answer.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            guid: GUID,
            round: state.round,
            picked: color,
            response_ms: responseMs,
            timeout: false
          })
        });
        const data = await res.json();
        RoomLogger.info('Answer', 'Server response received', data);

        if (data.ok && typeof data.score_delta !== 'undefined') {
          myStatEntry.score_delta = data.score_delta;
          state.score += data.score_delta;
          hudScore.textContent = String(state.score);
          if (data.score_delta < 0) {
            showToast(`${data.score_delta} ${STR.score || 'pts'}`, false);
          } else if (data.score_delta > 0) {
            showToast(`+${data.score_delta} ${STR.score || 'pts'}`, true);
          }
        }

        if (data.ok && data.finished && state.phase !== 'finished') {
          RoomLogger.info('GameState', 'Game finished after answer');
          renderFinalVictory(data.players || []);
        } else if (data.ok && (data.round_ended || data.all_answered)) {
          RoomLogger.info('GameState', 'All players answered! Immediately transitioning to leaderboard');
          renderLeaderboard(data.players || []);
        } else if (data.ok && data.answered_count && data.total_participants) {
          const notice = document.getElementById('waitingOthersNotice');
          if (notice) {
            notice.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>${STR.waitingOthers} (${data.answered_count}/${data.total_participants})`;
          }
        }
      } catch (e) {
        RoomLogger.error('Answer', 'Failed to submit answer', e);
      }
    }

    async function onTimeOut(cells) {
      if (state.answered || state.eliminated) return;
      state.answered = true;
      cells.forEach(c => c.disabled = true);
      playTone(200, 0.25, 'sawtooth');

      state.myRoundStats.push({
        round: state.round,
        target: state.targetColor,
        picked: null,
        is_timeout: true,
        correct: false,
        response_ms: state.answerMs,
        score_delta: 0
      });

      if (state.gameMode === 'elimination') {
        state.eliminated = true;
        showToast(STR.timeUp, false);
      } else {
        state.eliminated = false;
        showToast(STR.timeUpZeroPoints || STR.timeUp, false);
      }

      RoomLogger.warn('Answer', `Player timed out on round ${state.round}`);

      try {
        const res = await fetch('api/rooms_answer.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            guid: GUID,
            round: state.round,
            picked: '',
            response_ms: state.answerMs,
            timeout: true
          })
        });
        const data = await res.json();
        RoomLogger.info('Answer', 'Timeout response received', data);

        if (data && data.ok && data.finished && state.phase !== 'finished') {
          renderFinalVictory(data.players || []);
        } else if (data && data.ok && (data.round_ended || data.all_answered)) {
          renderLeaderboard(data.players || []);
        }
      } catch (e) {
        RoomLogger.error('Answer', 'Failed to submit timeout', e);
      }
    }

    function sortPlayers(list) {
      if (!Array.isArray(list)) return [];
      return [...list].sort((a, b) => {
        const actA = a.status === 'active' ? 1 : 0;
        const actB = b.status === 'active' ? 1 : 0;
        if (actB !== actA) return actB - actA;
        const scoreA = Number(a.score) || 0;
        const scoreB = Number(b.score) || 0;
        if (scoreB !== scoreA) return scoreB - scoreA;
        const corrA = Number(a.correct) || 0;
        const corrB = Number(b.correct) || 0;
        if (corrB !== corrA) return corrB - corrA;
        return 0;
      });
    }

    function renderLeaderboard(players, targetRound = null) {
      if (state.phase === 'finished') return;
      const r = targetRound || state.round;
      if (state.phase === 'intermission' && state.leaderboardRound === r) {
        if (Array.isArray(players) && players.length > 0) {
          state.players = players;
          renderPlayers(players);
        }
        return;
      }
      state.leaderboardRound = r;
      state.phase = 'intermission';
      if (state.countdownInterval) { clearInterval(state.countdownInterval); clearTimeout(state.countdownInterval); state.countdownInterval = null; }
      if (state.activeTimer) clearInterval(state.activeTimer);
      if (state.intermissionTimer) clearTimeout(state.intermissionTimer);
      hudTimer.textContent = '-';

      if (Array.isArray(players) && players.length > 0) {
        state.players = players;
        renderPlayers(players);
      }
      const sorted = sortPlayers(players || state.players || []);

      RoomLogger.info('GameState', `Rendering leaderboard for round ${state.round}`, { players_count: sorted.length });

      stageContent.innerHTML = `
        <div class="fs-1">🏆</div>
        <h2 class="stage-title">${STR.roundComplete.replace('{round}', state.round)}</h2>
        <p class="stage-subtitle">${STR.nextRoundIn}</p>
        <div class="progress my-2 mx-auto" style="height: 6px; max-width: 260px; background: rgba(255,255,255,0.12); border-radius: 999px; overflow: hidden;">
          <div id="intermissionProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-warning" style="width: 100%; transition: width 2.5s linear;"></div>
        </div>
        <div class="table-responsive w-100 mt-2">
          <table class="table table-sm align-middle text-start">
            <thead>
              <tr class="text-secondary small">
                <th>#</th>
                <th>${STR.player}</th>
                <th>${STR.score}</th>
                <th>${STR.status}</th>
              </tr>
            </thead>
            <tbody>
              ${sorted.map((p, idx) => `
                <tr class="${(p.email === ME_EMAIL || String(p.user_id) === String(ME_ID)) ? 'table-active fw-bold' : ''}">
                  <td>${idx === 0 ? '👑 1' : idx + 1}</td>
                  <td>${p.email ? p.email.split('@')[0] : (p.nickname || STR.player)}</td>
                  <td>${Number(p.score) || 0}</td>
                  <td><span class="badge ${p.status === 'eliminated' ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success'} rounded-pill">${p.status === 'eliminated' ? STR.eliminated : STR.active}</span></td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      `;

      // Animate progress bar smoothly
      setTimeout(() => {
        const bar = document.getElementById('intermissionProgressBar');
        if (bar) bar.style.width = '0%';
      }, 50);

      // Intermission safety timer: automatically advance after 2.8s if Pusher event was delayed
      state.intermissionTimer = setTimeout(async () => {
        if (state.phase === 'intermission') {
          RoomLogger.info('Intermission', 'Safety timer expired, polling tick to advance round');
          try {
            const res = await fetch('api/rooms_tick.php?guid=' + encodeURIComponent(GUID));
            const data = await res.json();
            if (data && data.round && data.round > state.round && data.question) {
              applyRoundData(data);
            } else if (data && (data.finished || data.status === 'finished')) {
              renderFinalVictory(data.players || []);
            }
          } catch(e) {}
        }
      }, 2800);
    }

    function applyRoundData(data) {
      if (!data || !data.round) return;

      if (state.phase === 'finished' && data.round !== 1 && !data.is_restart) {
        RoomLogger.warn('GameState', 'applyRoundData skipped because game is finished and not restart', data);
        return;
      }

      if (data.round < state.round && !data.is_restart && data.round !== 1) {
        RoomLogger.warn('GameState', `applyRoundData skipped: stale round ${data.round} < ${state.round}`);
        return;
      }

      if (state.round === data.round && (state.phase === 'countdown' || state.phase === 'show' || state.phase === 'question')) {
        return;
      }

      RoomLogger.info('GameState', `applyRoundData: Starting round ${data.round}`, data);

      if (state.intermissionTimer) clearTimeout(state.intermissionTimer);
      if (state.countdownInterval) {
        clearInterval(state.countdownInterval);
        clearTimeout(state.countdownInterval);
        state.countdownInterval = null;
      }
      if (state.activeTimer) clearInterval(state.activeTimer);

      state.phase = 'countdown';
      state.round = data.round;
      if (data.game_mode) state.gameMode = data.game_mode;
      if (data.rounds_total) state.roundsTotal = Number(data.rounds_total);
      hudRound.textContent = `${data.round} / ${state.roundsTotal || 25}`;
      state.targetColor = data.question?.target;
      state.gridColors = data.question?.grid || [];
      state.showMs = data.show_ms || 3000;
      state.answerMs = data.answer_ms || 5000;
      state.countdownMs = data.countdown_ms || 3000;
      state.answered = false;

      if (data.round === 1 || data.is_restart) {
        state.score = 0;
        state.eliminated = false;
        state.myRoundStats = [];
        hudScore.textContent = '0';
      }

      if (Array.isArray(data.players) && data.players.length > 0) {
        renderPlayers(data.players);
        const meRow = data.players.find(p => p.email === ME_EMAIL || String(p.user_id) === String(ME_ID));
        if (meRow) {
          state.eliminated = (state.gameMode === 'elimination' && meRow.status === 'eliminated');
          state.score = Number(meRow.score) || 0;
          hudScore.textContent = String(state.score);
        }
      }

      runCountdown();
    }

    async function renderFinalVictory(players) {
      state.phase = 'finished';
      if (state.countdownInterval) { clearInterval(state.countdownInterval); clearTimeout(state.countdownInterval); state.countdownInterval = null; }
      if (state.activeTimer) clearInterval(state.activeTimer);
      if (state.intermissionTimer) clearTimeout(state.intermissionTimer);
      hudTimer.textContent = '-';
      hudStatus.textContent = STR.finished;
      hudStatus.className = 'hud-val text-success';

      RoomLogger.info('GameState', 'Rendering Final Victory screen');

      let list = Array.isArray(players) && players.length > 0 ? players : state.players;

      if (!list || list.length === 0) {
        try {
          const res = await fetch('api/rooms_join.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({guid: GUID})
          });
          const data = await res.json();
          if (data && Array.isArray(data.players) && data.players.length > 0) {
            list = data.players;
          }
        } catch(e) {}
      }

      if (list && list.length > 0) {
        state.players = list;
        renderPlayers(list);
      }

      const sorted = sortPlayers(list || []);
      const p1 = sorted.length > 0 ? sorted[0] : null;
      const p2 = sorted.length > 1 ? sorted[1] : null;
      const p3 = sorted.length > 2 ? sorted[2] : null;

      const meRow = (sorted || []).find(p => p.email === ME_EMAIL || String(p.user_id) === String(ME_ID));
      if (meRow) {
        state.score = Number(meRow.score) || 0;
        hudScore.textContent = String(state.score);
      }

      function renderPodiumCol(player, rankNum, cssClass) {
        if (!player) {
          return `
            <div class="podium-col ${cssClass}">
              <div class="podium-player-info">
                <div class="podium-avatar podium-empty">-</div>
                <div class="podium-name podium-empty">-</div>
                <div class="podium-score podium-empty">-</div>
              </div>
              <div class="podium-pedestal pedestal-${rankNum}">
                <div class="podium-num">${rankNum}</div>
              </div>
            </div>
          `;
        }

        const isMe = player.email === ME_EMAIL || String(player.user_id) === String(ME_ID);
        const name = player.email ? player.email.split('@')[0] : (player.nickname || STR.player);
        const score = Number(player.score) || 0;
        const medalIcon = rankNum === 1 ? '🥇' : (rankNum === 2 ? '🥈' : '🥉');

        return `
          <div class="podium-col ${cssClass}">
            <div class="podium-player-info">
              ${rankNum === 1 ? '<div class="podium-crown">👑</div>' : ''}
              <div class="podium-avatar">
                <span>${medalIcon}</span>
              </div>
              <div class="podium-name" title="${escapeHtml(name)}">
                <span>${escapeHtml(name)}</span>
                ${isMe ? `<span class="badge bg-warning text-dark fs-7 ms-1 py-0 px-1">${STR.you}</span>` : ''}
              </div>
              <div class="podium-score">
                <span>${score.toLocaleString()}</span>
                <span class="score-pts">${STR.pts}</span>
              </div>
            </div>
            <div class="podium-pedestal pedestal-${rankNum}">
              <div class="podium-num">${rankNum}</div>
              ${rankNum === 1 ? `<div class="podium-bonus-tag">🎁 +5.000 ${STR.winBonus}</div>` : ''}
            </div>
          </div>
        `;
      }

      stageContent.innerHTML = `
        <div class="victory-container">
          <div class="victory-header">
            <div class="victory-trophy">👑</div>
            <h1 class="stage-title victory-title">${STR.winner}</h1>
            <p class="stage-subtitle victory-subtitle">${STR.concludedDesc}</p>
          </div>

          <div class="podium-wrapper">
            <div class="podium-stage">
              ${renderPodiumCol(p2, 2, 'podium-col-2')}
              ${renderPodiumCol(p1, 1, 'podium-col-1')}
              ${renderPodiumCol(p3, 3, 'podium-col-3')}
            </div>
          </div>

          <div class="victory-standings">
            <div class="standings-header">
              <span class="standings-title">📊 ${STR.roomLeaderboard}</span>
              <span class="standings-count">${sorted.length} ${STR.players}</span>
            </div>
            <div class="standings-table-wrap">
              <table class="standings-table">
                <thead>
                  <tr>
                    <th class="col-rank">#</th>
                    <th class="col-player">${STR.player}</th>
                    <th class="col-correct text-center">${STR.colCorrect || 'Doğru'}</th>
                    <th class="col-score text-end">${STR.score}</th>
                    <th class="col-status text-center">${STR.status}</th>
                  </tr>
                </thead>
                <tbody>
                  ${sorted.map((p, idx) => {
                    const isMe = p.email === ME_EMAIL || String(p.user_id) === String(ME_ID);
                    const isElim = p.status === 'eliminated';
                    const isWin = (idx === 0);
                    const pName = p.email ? p.email.split('@')[0] : (p.nickname || STR.player);
                    const pScore = Number(p.score) || 0;
                    const pCorrect = typeof p.correct_count !== 'undefined' ? Number(p.correct_count) : '-';
                    let rankBadge = '';
                    if (idx === 0) rankBadge = '<span class="rank-badge rank-1">🥇</span>';
                    else if (idx === 1) rankBadge = '<span class="rank-badge rank-2">🥈</span>';
                    else if (idx === 2) rankBadge = '<span class="rank-badge rank-3">🥉</span>';
                    else rankBadge = `<span class="rank-badge rank-other">${idx + 1}</span>`;

                    return `
                      <tr class="${isMe ? 'row-me' : ''} ${idx === 0 ? 'row-winner' : ''}">
                        <td class="col-rank">${rankBadge}</td>
                        <td class="col-player">
                          <div class="player-cell">
                            <span class="player-name">${escapeHtml(pName)}</span>
                            ${isMe ? `<span class="badge-you">${STR.you}</span>` : ''}
                          </div>
                        </td>
                        <td class="col-correct text-center">
                          <span class="badge bg-dark-subtle text-light border px-2">${pCorrect}</span>
                        </td>
                        <td class="col-score text-end">
                          <span class="score-badge">${pScore.toLocaleString()} <span class="score-pts">${STR.pts}</span></span>
                          ${idx === 0 ? `<span class="badge bg-warning-subtle text-warning border border-warning-subtle ms-1" style="font-size:10px" title="+5.000 ${STR.winBonus}">+5.000 🎁</span>` : ''}
                        </td>
                        <td class="col-status text-center">
                          ${isWin
                            ? `<span class="status-pill active">👑 ${STR.winner}</span>`
                            : (state.gameMode === 'elimination' && isElim 
                              ? `<span class="status-pill elim">💀 ${STR.eliminated}</span>`
                              : `<span class="status-pill active">✅ ${STR.finished}</span>`)}
                        </td>
                      </tr>
                    `;
                  }).join('')}
                </tbody>
              </table>
            </div>
          </div>

          <div class="d-flex flex-wrap gap-2 justify-content-center mt-3" id="victoryActions">
            ${state.isHost ? `
              <button id="restartMatchBtn" class="btn btn-action" type="button">
                🚀 ${STR.restartMatch}
              </button>
              <button id="backToLobbyBtn" class="btn btn-outline-light" type="button">
                ⏳ ${STR.backToLobby}
              </button>
            ` : ''}
            <a class="btn btn-outline-secondary" href="rooms.php">← ${STR.backToRooms}</a>
          </div>

          <div id="personalStatsSection" class="personal-stats-section">
            <div class="stats-header-bar">
              <h3>🎯 ${STR.personalStatsTitle}</h3>
              <p>${state.gameMode === 'flags' ? STR.personalStatsDescFlag : STR.personalStatsDesc}</p>
            </div>
            <div id="statsSummaryGrid" class="stats-summary-grid">
              <div class="stat-metric-card">
                <div id="statAvgSpeed" class="stat-metric-val"><span class="spinner-border spinner-border-sm"></span></div>
                <div class="stat-metric-lbl">⚡ ${STR.statAvgSpeed}</div>
              </div>
              <div class="stat-metric-card">
                <div id="statFastest" class="stat-metric-val"><span class="spinner-border spinner-border-sm"></span></div>
                <div class="stat-metric-lbl">🚀 ${STR.statFastest}</div>
              </div>
              <div class="stat-metric-card">
                <div id="statAccuracy" class="stat-metric-val"><span class="spinner-border spinner-border-sm"></span></div>
                <div class="stat-metric-lbl">🎯 ${STR.statAccuracy}</div>
              </div>
              <div class="stat-metric-card">
                <div id="statTotalDelta" class="stat-metric-val"><span class="spinner-border spinner-border-sm"></span></div>
                <div class="stat-metric-lbl">📈 ${STR.statTotalDelta}</div>
              </div>
            </div>
            <div class="round-stats-table-wrap">
              <table class="round-stats-table">
                <thead>
                  <tr>
                    <th style="width: 55px">${STR.colRound}</th>
                    <th>${state.gameMode === 'flags' ? STR.colTargetFlag : STR.colTarget}</th>
                    <th>${state.gameMode === 'flags' ? STR.colPickedFlag : STR.colPicked}</th>
                    <th style="width: 100px" class="text-center">${STR.colTime}</th>
                    <th style="width: 140px" class="text-end">${STR.colResult}</th>
                  </tr>
                </thead>
                <tbody id="personalStatsBody">
                  <tr>
                    <td colspan="5" class="text-center py-4 text-muted">
                      <span class="spinner-border spinner-border-sm me-2"></span>${STR.waiting || 'Yükleniyor...'}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      `;

      loadPlayerStatsTable();

      if (state.isHost) {
        const restartMatchBtn = document.getElementById('restartMatchBtn');
        const backToLobbyBtn = document.getElementById('backToLobbyBtn');

        restartMatchBtn?.addEventListener('click', async () => {
          const onlineCount = (state.players || []).filter(isPlayerOnline).length;
          if (onlineCount < 2) {
            showToast(STR.minPlayersRequired, false);
            return;
          }
          restartMatchBtn.disabled = true;
          restartMatchBtn.textContent = '⏳ ' + STR.restarting;
          if (backToLobbyBtn) backToLobbyBtn.disabled = true;

          RoomLogger.info('RestartMatch', 'Host clicked Restart Match', { guid: GUID });

          try {
            const res = await fetch('api/rooms_restart.php', {
              method: 'POST',
              headers: {'Content-Type': 'application/json'},
              body: JSON.stringify({guid: GUID, start_immediately: true})
            });
            const data = await res.json();
            RoomLogger.info('RestartMatch', 'Response received from rooms_restart.php', data);

            if (!data.ok) {
              restartMatchBtn.disabled = false;
              restartMatchBtn.textContent = '🚀 ' + STR.restartMatch;
              if (backToLobbyBtn) backToLobbyBtn.disabled = false;
              if (data.error === 'min_players_required') {
                showToast(STR.minPlayersRequired, false);
              } else {
                showToast(data.error || STR.errorGeneric, false);
              }
            } else {
              // Direct state transition if round 1 data returned in HTTP response
              if (data.round === 1 && data.question) {
                RoomLogger.info('RestartMatch', 'Instantly applying round 1 from HTTP response');
                applyRoundData(data);
              }
            }
          } catch(e) {
            RoomLogger.error('RestartMatch', 'Fetch error on rooms_restart.php', e);
            restartMatchBtn.disabled = false;
            restartMatchBtn.textContent = '🚀 ' + STR.restartMatch;
            if (backToLobbyBtn) backToLobbyBtn.disabled = false;
          }
        });

        backToLobbyBtn?.addEventListener('click', async () => {
          backToLobbyBtn.disabled = true;
          backToLobbyBtn.textContent = '⏳ ' + STR.restarting;
          if (restartMatchBtn) restartMatchBtn.disabled = true;

          RoomLogger.info('BackToLobby', 'Host clicked Back to Lobby', { guid: GUID });

          try {
            const res = await fetch('api/rooms_restart.php', {
              method: 'POST',
              headers: {'Content-Type': 'application/json'},
              body: JSON.stringify({guid: GUID, start_immediately: false})
            });
            const data = await res.json();
            RoomLogger.info('BackToLobby', 'Response received', data);

            if (data.ok) {
              resetToLobby(data.players || []);
            } else {
              backToLobbyBtn.disabled = false;
              backToLobbyBtn.textContent = '⏳ ' + STR.backToLobby;
              if (restartMatchBtn) restartMatchBtn.disabled = false;
              showToast(data.error || STR.errorGeneric, false);
            }
          } catch(e) {
            RoomLogger.error('BackToLobby', 'Fetch error', e);
            backToLobbyBtn.disabled = false;
            backToLobbyBtn.textContent = '⏳ ' + STR.backToLobby;
            if (restartMatchBtn) restartMatchBtn.disabled = false;
          }
        });
      }
    }

    async function loadPlayerStatsTable() {
      const bodyEl = document.getElementById('personalStatsBody');
      const avgEl = document.getElementById('statAvgSpeed');
      const fastEl = document.getElementById('statFastest');
      const accEl = document.getElementById('statAccuracy');
      const deltaEl = document.getElementById('statTotalDelta');

      function renderStatsUI(summary, roundsList) {
        if (avgEl) avgEl.textContent = (summary.avg_response_ms || 0) + ' ms';
        if (fastEl) fastEl.textContent = (summary.fastest_response_ms || 0) + ' ms';
        if (accEl) accEl.textContent = `${summary.correct_count || 0}/${summary.total_rounds || 0} (${summary.accuracy_percent || 0}%)`;
        if (deltaEl) {
          const d = summary.total_score_delta || 0;
          deltaEl.textContent = (d > 0 ? '+' : '') + d.toLocaleString() + ' ' + STR.pts;
          deltaEl.className = 'stat-metric-val ' + (d >= 0 ? 'text-success' : 'text-danger');
        }

        if (!bodyEl) return;
        if (!roundsList || roundsList.length === 0) {
          bodyEl.innerHTML = `<tr><td colspan="5" class="text-center py-3 text-muted">-</td></tr>`;
          return;
        }

        function renderSwatchOrFlag(val) {
          if (!val) return '-';
          if (isFlagTarget(val)) {
            const name = formatFlagLabel(val);
            return `<div class="flag-swatch-wrap" title="${escapeHtml(name)}">
                      <img src="${escapeHtml(val)}" class="flag-swatch-img" alt="Flag">
                      <span class="flag-name-text">${escapeHtml(name)}</span>
                    </div>`;
          }
          return `<div class="color-swatch-cell">
                    <span class="color-swatch" style="background: ${escapeHtml(val)}"></span>
                    <span class="swatch-hex">${escapeHtml(String(val).toUpperCase())}</span>
                  </div>`;
        }

        bodyEl.innerHTML = roundsList.map(st => {
          const targetBox = st.target ? renderSwatchOrFlag(st.target) : '-';

          let pickedBox = '';
          if (st.is_timeout || !st.picked) {
            pickedBox = `<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">⏱️ ${STR.statTimeoutBadge}</span>`;
          } else {
            pickedBox = renderSwatchOrFlag(st.picked);
          }

          const timeVal = st.is_timeout ? '-' : `${st.response_ms || 0} ms`;
          let resultBadge = '';
          if (st.correct) {
            resultBadge = `<span class="badge bg-success-subtle text-success border border-success-subtle">✅ +${(st.score_delta || 0).toLocaleString()}</span>`;
          } else if (st.is_timeout) {
            resultBadge = `<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">⏱️ 0 ${STR.pts}</span>`;
          } else if (state.gameMode === 'elimination') {
            resultBadge = `<span class="badge bg-danger-subtle text-danger border border-danger-subtle">💀 ${STR.eliminated}</span>`;
          } else {
            resultBadge = `<span class="badge bg-danger-subtle text-danger border border-danger-subtle">❌ ${(st.score_delta || 0).toLocaleString()}</span>`;
          }

          return `
            <tr>
              <td class="fw-bold text-secondary">#${st.round}</td>
              <td>${targetBox}</td>
              <td>${pickedBox}</td>
              <td class="text-center">
                <span class="badge bg-dark-subtle text-light border px-2">${timeVal}</span>
              </td>
              <td class="text-end">${resultBadge}</td>
            </tr>
          `;
        }).join('');
      }

      // Check if we have local stats immediately available as instant preview
      if (state.myRoundStats && state.myRoundStats.length > 0) {
        const localList = state.myRoundStats;
        let totalMs = 0, answeredCount = 0, fastest = null, correct = 0, delta = 0;
        localList.forEach(s => {
          if (s.correct) correct++;
          if (!s.is_timeout && s.response_ms > 0) {
            totalMs += s.response_ms;
            answeredCount++;
            if (fastest === null || s.response_ms < fastest) fastest = s.response_ms;
          }
          delta += (s.score_delta || 0);
        });
        const summary = {
          total_rounds: localList.length,
          correct_count: correct,
          accuracy_percent: localList.length > 0 ? Math.round((correct / localList.length) * 100) : 0,
          avg_response_ms: answeredCount > 0 ? Math.round(totalMs / answeredCount) : 0,
          fastest_response_ms: fastest || 0,
          total_score_delta: delta
        };
        renderStatsUI(summary, localList);
      }

      // Fetch authoritative events from backend
      try {
        const res = await fetch(`api/rooms_player_stats.php?guid=${encodeURIComponent(GUID)}`);
        const data = await res.json();
        if (data && data.ok && Array.isArray(data.stats) && data.stats.length > 0) {
          renderStatsUI(data.summary || {}, data.stats);
        } else if (!state.myRoundStats || state.myRoundStats.length === 0) {
          if (bodyEl) bodyEl.innerHTML = `<tr><td colspan="5" class="text-center py-3 text-muted">-</td></tr>`;
        }
      } catch (err) {
        RoomLogger.error('PlayerStats', 'Failed to fetch personal stats from server', err);
      }
    }

    function resetToLobby(players) {
      if (state.countdownInterval) { clearInterval(state.countdownInterval); clearTimeout(state.countdownInterval); state.countdownInterval = null; }
      if (state.activeTimer) clearInterval(state.activeTimer);
      if (state.intermissionTimer) clearTimeout(state.intermissionTimer);
      state.round = 0;
      state.score = 0;
      state.phase = 'lobby';
      state.eliminated = false;
      state.answered = false;
      state.targetColor = null;
      state.gridColors = [];
      state.myRoundStats = [];

      hudRound.textContent = '-';
      hudScore.textContent = '0';
      hudTimer.textContent = '-';
      hudStatus.textContent = STR.waiting;
      hudStatus.className = 'hud-val text-info';

      RoomLogger.info('GameState', 'Resetting room to lobby', { players_count: (players || []).length });

      const isPriv = <?= json_encode(!empty($room['is_private'])) ?>;
      const isPointsMode = (state.gameMode === 'points');
      stageContent.innerHTML = `
        <div class="stage-center">
          <div class="fs-1">⏳</div>
          <h1 class="stage-title">
            <span><?= htmlspecialchars($room['name'] ?: tt('room_default_name', 'Match Room')) ?></span>
            ${isPointsMode 
              ? `<span class="badge bg-warning-subtle text-warning fs-6 text-nowrap align-middle border border-warning-subtle ms-1">⚡ ${STR.modePoints}</span>`
              : `<span class="badge bg-danger-subtle text-danger fs-6 text-nowrap align-middle border border-danger-subtle ms-1">💀 ${STR.modeElimination}</span>`}
            ${isPriv ? `<span class="badge bg-secondary-subtle text-secondary fs-6 text-nowrap align-middle border border-secondary-subtle ms-1">🔒 ${STR.privateBadge}</span>` : ''}
          </h1>
          <p class="stage-subtitle">${isPriv ? STR.waitingDescPrivate : STR.waitingDesc}</p>
          <div class="d-flex flex-wrap gap-2 justify-content-center mt-2">
            <button id="copyInviteLinkBtn" class="btn btn-outline-light" type="button">
              🔗 ${STR.copyInvite}
            </button>
            <a id="shareWhatsappLobbyBtn" class="btn btn-success d-inline-flex align-items-center gap-1" href="#" target="_blank" rel="noopener noreferrer">
              <span>💬</span> ${STR.shareWhatsapp}
            </a>
            <div id="hostControls" class="${state.isHost ? '' : 'd-none'}">
              <button id="startMatchBtn" class="btn btn-action btn-lg">
                🚀 ${STR.startMatch}
              </button>
              <div id="minPlayersNotice" class="small text-warning mt-2 fw-semibold text-center"></div>
            </div>
          </div>
        </div>
      `;

      wireCopyInviteBtn();
      const startBtn = document.getElementById('startMatchBtn');
      startBtn?.addEventListener('click', handleStartMatch);

      if (Array.isArray(players) && players.length > 0) {
        renderPlayers(players);
      } else {
        state.players = (state.players || []).map(p => ({
          ...p,
          status: 'active',
          score: 0,
          correct: 0
        }));
        renderPlayers(state.players);
      }

      if (state.isHost) {
        updateStartButtonState();
      }

      showToast(STR.roomRestarted, true);
    }

    async function handleStartMatch() {
      const onlinePlayers = (state.players || []).filter(isPlayerOnline);
      if (onlinePlayers.length < 2) {
        showToast(STR.minPlayersRequired, false);
        return;
      }
      const btn = document.getElementById('startMatchBtn');
      if (btn) {
        btn.disabled = true;
        btn.textContent = '⏳ ' + STR.starting;
      }
      RoomLogger.info('StartMatch', 'Starting match for guid=' + GUID);
      try {
        const res = await fetch('api/rooms_next_round.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({guid: GUID})
        });
        const data = await res.json();
        RoomLogger.info('StartMatch', 'Response received', data);
        if (!data.ok) {
          if (btn) {
            btn.disabled = false;
            btn.textContent = '🚀 ' + STR.startMatch;
          }
          if (data.error === 'min_players_required') {
            showToast(STR.minPlayersRequired, false);
          } else {
            showToast(data.error || STR.errorGeneric, false);
          }
        } else if (data.round === 1 && data.question) {
          applyRoundData(data);
        }
      } catch (e) {
        RoomLogger.error('StartMatch', 'Fetch error', e);
        if (btn) {
          btn.disabled = false;
          btn.textContent = '🚀 ' + STR.startMatch;
        }
      }
    }

    // Join room & bind realtime
    async function initRoom() {
      RoomLogger.info('Init', `Joining room guid=${GUID} as me=${ME_EMAIL} (${ME_ID})`);
      try {
        const res = await fetch('api/rooms_join.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({guid: GUID})
        });
        const data = await res.json();
        RoomLogger.info('Init', 'Join response received', data);

        if (!data.ok) {
          hudStatus.textContent = STR.errorJoining;
          return;
        }

        const room = data.room;
        if (room.game_mode) state.gameMode = room.game_mode;
        if (room.rounds_total) state.roundsTotal = Number(room.rounds_total);
        state.isHost = (room.owner_id && ME_ID) ? (String(room.owner_id) === String(ME_ID)) : (String(room.owner_email || '').toLowerCase() === String(ME_EMAIL || '').toLowerCase());
        renderPlayers(data.players || []);

        if (room.status === 'finished') {
          renderFinalVictory(data.players || []);
        } else if (room.status === 'waiting') {
          if (state.isHost) {
            hostControls?.classList.remove('d-none');
            updateStartButtonState();
          }
          hudStatus.textContent = STR.waiting;
          hudStatus.className = 'hud-val text-info';
        } else {
          hudStatus.textContent = STR.active;
          hudStatus.className = 'hud-val text-info';
        }
      } catch (e) {
        RoomLogger.error('Init', 'Connection error joining room', e);
        hudStatus.textContent = STR.connError;
      }
    }

    // Pusher Setup & Dual-Channel Sync Variables
    let isPusherConnected = false;
    let tickTimerId = null;

    function scheduleNextTick(delayMs = null) {
      if (tickTimerId) clearTimeout(tickTimerId);
      const interval = delayMs !== null ? delayMs : (isPusherConnected ? 2400 : 1000);
      tickTimerId = setTimeout(performTickSync, interval);
    }

    if (PUSHER_KEY) {
      RoomLogger.info('Pusher', `Initializing Pusher key=${PUSHER_KEY} cluster=${PUSHER_CLUSTER}`);
      const pusher = new Pusher(PUSHER_KEY, {
        cluster: PUSHER_CLUSTER,
        authEndpoint: 'api/pusher_auth.php',
        forceTLS: true,
        enableStats: false,
        activityTimeout: 30000,
        pongTimeout: 6000
      });

      pusher.connection.bind('state_change', (states) => {
        RoomLogger.info('Pusher', `Connection state changed: ${states.previous} -> ${states.current}`);
        if (states.current === 'connected') {
          isPusherConnected = true;
          hudStatus.textContent = state.phase === 'lobby' ? STR.waiting : (state.phase === 'finished' ? STR.finished : STR.active);
          hudStatus.className = 'hud-val ' + (state.phase === 'finished' ? 'text-success' : 'text-info');
          // If reconnected after a drop, immediately trigger a resync to catch any missed events
          if (states.previous && states.previous !== 'initialized') {
            RoomLogger.info('Pusher', 'Reconnected to Pusher; scheduling immediate resync');
            scheduleNextTick(0);
          }
        } else if (states.current === 'unavailable' || states.current === 'failed' || states.current === 'disconnected') {
          isPusherConnected = false;
          RoomLogger.warn('Pusher', 'Connection lost or unavailable; fast polling fallback enabled');
          scheduleNextTick(1000);
        }
      });

      const channel = pusher.subscribe('presence-room-' + GUID);

      channel.bind('pusher:subscription_succeeded', (members) => {
        RoomLogger.info('Pusher', `Subscription succeeded to presence-room-${GUID}`, { count: members.count });
        state.presenceMemberIds = new Set();
        members.each((member) => {
          if (member.id) state.presenceMemberIds.add(String(member.id));
        });
        renderPlayers(state.players);
      });

      channel.bind('pusher:member_added', (member) => {
        RoomLogger.info('Pusher', 'Player connected to presence channel', member);
        if (member && member.id) {
          state.presenceMemberIds.add(String(member.id));
        }
        if (state.phase === 'lobby') {
          fetch('api/rooms_state.php?guid=' + encodeURIComponent(GUID))
            .then(res => res.json())
            .then(data => {
              if (data && data.ok && Array.isArray(data.players)) {
                renderPlayers(data.players);
              }
            }).catch(() => {});
        } else {
          renderPlayers(state.players);
        }
      });

      channel.bind('pusher:member_removed', (member) => {
        RoomLogger.info('Pusher', 'Player disconnected from presence channel', member);
        if (member && member.id) {
          const removedId = String(member.id);
          state.presenceMemberIds.delete(removedId);
          if (state.phase === 'lobby') {
            state.players = (state.players || []).filter(p => String(p.user_id) !== removedId);
            renderPlayers(state.players);
            updateStartButtonState();
          } else {
            state.players = (state.players || []).map(p => {
              if (String(p.user_id) === removedId) {
                return { ...p, is_online: 0 };
              }
              return p;
            });
            renderPlayers(state.players);
          }
        }
      });

      channel.bind('pusher:subscription_error', (status) => {
        RoomLogger.error('Pusher', 'Subscription error on presence channel', status);
      });

      channel.bind('room:update', (data) => {
        RoomLogger.info('Pusher', 'Event room:update received', data);
        renderPlayers(data.players || []);
        const meRow = (data.players || []).find(p => p.email === ME_EMAIL || String(p.user_id) === String(ME_ID));
        if (meRow && meRow.status === 'eliminated' && state.gameMode === 'elimination') {
          state.eliminated = true;
        }
        if (state.answered && data.answered_count && data.total_participants) {
          const notice = document.getElementById('waitingOthersNotice');
          if (notice) {
            notice.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>${STR.waitingOthers} (${data.answered_count}/${data.total_participants})`;
          }
        }
      });

      channel.bind('room:round', (data) => {
        RoomLogger.info('Pusher', 'Event room:round received', data);
        applyRoundData(data);
      });

      channel.bind('room:reset', (data) => {
        RoomLogger.info('Pusher', 'Event room:reset received', data);
        if (data.game_mode) state.gameMode = data.game_mode;
        resetToLobby(data.players || []);
      });

      channel.bind('room:leaderboard', (data) => {
        RoomLogger.info('Pusher', 'Event room:leaderboard received', data);
        if (state.phase === 'finished') return;
        if (data.game_mode) state.gameMode = data.game_mode;
        renderLeaderboard(data.players || [], data.round);
      });

      channel.bind('room:finished', (data) => {
        RoomLogger.info('Pusher', 'Event room:finished received', data);
        if (data.game_mode) state.gameMode = data.game_mode;
        renderFinalVictory(data.players || []);
      });
    }

    // Host Start Button Trigger
    startMatchBtn?.addEventListener('click', handleStartMatch);

    // Leave Beacon: Inform server when player closes tab or navigates away
    function sendLeaveBeacon() {
      try {
        const payload = JSON.stringify({guid: GUID});
        if (navigator.sendBeacon) {
          navigator.sendBeacon('api/rooms_leave.php', new Blob([payload], {type: 'application/json'}));
        } else {
          fetch('api/rooms_leave.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: payload,
            keepalive: true
          });
        }
      } catch(e) {}
    }
    window.addEventListener('beforeunload', sendLeaveBeacon);
    window.addEventListener('pagehide', sendLeaveBeacon);
    document.querySelectorAll('a[href="rooms.php"]').forEach(el => {
      el.addEventListener('click', () => { sendLeaveBeacon(); });
    });

    // Adaptive Keep-Alive and State Synchronization Tick (Dual-channel)
    async function performTickSync() {
      try {
        const res = await fetch('api/rooms_tick.php?guid=' + encodeURIComponent(GUID));
        const data = await res.json();
        if (!data || !data.ok) return;

        if ((data.finished || data.status === 'finished') && state.phase !== 'finished') {
          RoomLogger.info('TickSync', 'Match concluded on server', data);
          renderFinalVictory(data.players || []);
        } else if (data.status === 'waiting' && state.phase === 'finished') {
          RoomLogger.info('TickSync', 'Match reset to waiting lobby on server', data);
          resetToLobby(data.players || []);
        } else if (data.status === 'waiting' && state.phase !== 'lobby' && state.phase !== 'finished') {
          RoomLogger.info('TickSync', 'Room reset to waiting lobby', data);
          resetToLobby(data.players || []);
        } else if (data.status === 'waiting' && state.phase === 'lobby' && Array.isArray(data.players)) {
          state.players = data.players;
          renderPlayers(data.players);
        } else if ((state.phase === 'finished' || state.phase === 'lobby') && data.status === 'active' && data.round >= 1 && data.question) {
          RoomLogger.info('TickSync', 'Match restarted/active, applying round', data);
          applyRoundData(data);
        } else if (data.round && data.round > state.round && data.question && state.phase !== 'finished') {
          RoomLogger.info('TickSync', `New round ${data.round} detected, applying round`, data);
          applyRoundData(data);
        } else if (data.status === 'intermission' && state.phase === 'question') {
          RoomLogger.info('TickSync', 'Round intermission detected, rendering leaderboard', data);
          renderLeaderboard(data.players || [], data.round);
        }
      } catch(e) {
        RoomLogger.debug('TickSync', 'Tick fetch error (harmless)', e);
      } finally {
        scheduleNextTick();
      }
    }

    // Kick off initial sync ticker
    scheduleNextTick(1200);

    function wireCopyInviteBtn() {
      const copyBtn = document.getElementById('copyInviteLinkBtn');
      copyBtn?.addEventListener('click', async () => {
        const url = window.location.href;
        try {
          await navigator.clipboard.writeText(url);
          copyBtn.textContent = '✅ ' + STR.shareCopied;
          showToast(STR.inviteCopied, true);
          setTimeout(() => {
            copyBtn.textContent = '🔗 ' + STR.copyInvite;
          }, 2000);
        } catch(e) {
          showToast(STR.inviteCopied, true);
        }
      });

      const waBtn = document.getElementById('shareWhatsappLobbyBtn');
      if (waBtn) {
        const url = window.location.href;
        waBtn.href = 'https://api.whatsapp.com/send?text=' + encodeURIComponent((STR.shareWhatsappText || "Prismatch'te benimle oda oyununa katıl! 🎮 Bağlantı:") + ' ' + url);
      }
    }

    if (CAN_VIEW_LOGS) {
      wireDebugLogs();
    }
    wireCopyInviteBtn();
    initRoom();
  </script>
</body>
</html>
