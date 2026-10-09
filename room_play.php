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

$userDisplay = (string)($_SESSION['user_name'] ?? '');
if ($userDisplay === '' && $userId) {
    $userDisplay = user_display_name((string)$userId);
}
if ($userDisplay === '') {
    $userDisplay = user_display_name_from_row(['email' => $userEmail]);
}

$seoTitle = ($room['name'] ?: tt('room_play_title', 'Multiplayer Match')) . ' - ' . tt('app_name', 'Prismatch');
$seoDescription = tt('room_play_desc', 'Compete live in real time against other players.');
$roomTeams = (new \Prismatch\Services\RoomGameService())->getRoomTeams($room);
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
    [data-bs-theme="light"] .podium-name {
      color: #0f172a !important;
      text-shadow: none !important;
    }
    [data-bs-theme="light"] .victory-title {
      color: #0f172a !important;
    }
    [data-bs-theme="light"] .stats-header-bar h3,
    [data-bs-theme="light"] .stat-metric-val {
      color: #0f172a !important;
    }
    [data-bs-theme="light"] .personal-stats-section {
      background: rgba(0, 0, 0, 0.03) !important;
    }
    [data-bs-theme="light"] .stats-summary-grid {
      background: rgba(0, 0, 0, 0.02) !important;
    }
    [data-bs-theme="light"] .victory-standings {
      background: rgba(0, 0, 0, 0.02) !important;
    }
    [data-bs-theme="light"] .round-stats-table th {
      background: #e2e8f0 !important;
      color: #334155 !important;
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
      padding: 0 16px calc(var(--pm-footer-offset, 0px) + 88px);
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
    .hud-leave-wrap {
      min-width: 130px;
    }
    .hud-leave-btn {
      background: rgba(239, 68, 68, 0.1);
      border: 1px solid rgba(239, 68, 68, 0.32);
      border-radius: 16px;
      padding: 10px 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      color: #fca5a5;
      font-size: 13px;
      font-weight: 700;
      letter-spacing: 0.3px;
      font-family: inherit;
      cursor: pointer;
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      box-shadow: 0 4px 16px rgba(239, 68, 68, 0.08);
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
      user-select: none;
      text-decoration: none;
      outline: none;
      width: 100%;
      height: 100%;
      min-height: 44px;
      box-sizing: border-box;
    }
    .hud-leave-btn:hover {
      background: rgba(239, 68, 68, 0.22);
      border-color: rgba(239, 68, 68, 0.6);
      color: #ffffff;
      transform: translateY(-1.5px);
      box-shadow: 0 6px 20px rgba(239, 68, 68, 0.22);
    }
    .hud-leave-btn:active {
      transform: translateY(0) scale(0.97);
    }
    .hud-leave-btn .leave-icon {
      font-size: 16px;
      line-height: 1;
      display: inline-block;
      transition: transform 0.2s ease;
    }
    .hud-leave-btn:hover .leave-icon {
      transform: translateX(-2px);
    }
    [data-bs-theme="light"] .hud-leave-btn {
      background: rgba(239, 68, 68, 0.08);
      border-color: rgba(239, 68, 68, 0.25);
      color: #dc2626;
      box-shadow: 0 2px 10px rgba(220, 38, 38, 0.05);
    }
    [data-bs-theme="light"] .hud-leave-btn:hover {
      background: rgba(239, 68, 68, 0.16);
      border-color: rgba(239, 68, 68, 0.45);
      color: #b91c1c;
      box-shadow: 0 4px 16px rgba(220, 38, 38, 0.14);
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
    .choice-grid.grid-loading {
      opacity: 0;
      visibility: hidden;
    }
    .choice-grid.grid-ready {
      opacity: 1;
      visibility: visible;
      transition: opacity 0.08s ease-in;
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
      color: var(--arena-text);
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
      color: var(--arena-text);
      text-shadow: 0 1px 3px rgba(0,0,0,0.35);
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
      color: var(--arena-text);
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
      color: var(--arena-text);
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
      .podium-name { font-size: 12px; }
      .reaction-bar {
        bottom: 8px;
        width: calc(100% - 16px);
        padding: 6px 10px;
        gap: 6px;
        border-radius: 16px;
      }
      .reaction-btn {
        width: 38px;
        height: 38px;
        min-width: 38px;
        min-height: 38px;
        font-size: 20px;
        border-radius: 10px;
      }
      .shout-pill {
        padding: 4px 10px;
        font-size: 11px;
      }
      .hud-leave-wrap {
        min-width: 100px;
      }
      .hud-leave-btn {
        padding: 8px 12px;
        font-size: 12px;
        min-height: 38px;
      }
      .arena-container {
        padding-bottom: calc(var(--pm-footer-offset, 0px) + 78px);
      }
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

    /* Live Reaction & Party Sound Bar (Fixed Bottom Dock) */
    .reaction-bar {
      position: fixed;
      bottom: 14px;
      left: 50%;
      transform: translateX(-50%);
      width: min(880px, calc(100% - 24px));
      z-index: 1040;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      background: rgba(15, 23, 42, 0.9);
      border: 1px solid rgba(255, 255, 255, 0.16);
      border-radius: 20px;
      padding: 8px 14px;
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      box-shadow: 0 12px 36px rgba(0, 0, 0, 0.45), 0 0 0 1px rgba(255, 255, 255, 0.06);
      overflow-x: auto;
      scrollbar-width: none;
      -webkit-overflow-scrolling: touch;
      transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .reaction-bar::-webkit-scrollbar {
      display: none;
    }
    [data-bs-theme="light"] .reaction-bar {
      background: rgba(255, 255, 255, 0.94);
      border-color: rgba(0, 0, 0, 0.12);
      box-shadow: 0 12px 36px rgba(0, 0, 0, 0.12), 0 0 0 1px rgba(0, 0, 0, 0.04);
    }
    .reaction-group {
      display: flex;
      align-items: center;
      gap: 6px;
      flex-shrink: 0;
    }
    .reaction-btn {
      width: 44px;
      height: 44px;
      min-width: 44px;
      min-height: 44px;
      border-radius: 12px;
      border: 1px solid rgba(255, 255, 255, 0.08);
      background: rgba(255, 255, 255, 0.06);
      font-size: 22px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      padding: 0;
      transition: transform 0.15s cubic-bezier(0.175, 0.885, 0.32, 1.275), background 0.15s ease, border-color 0.15s ease;
      touch-action: manipulation;
      user-select: none;
    }
    [data-bs-theme="light"] .reaction-btn {
      background: rgba(0, 0, 0, 0.04);
      border-color: rgba(0, 0, 0, 0.08);
    }
    .reaction-btn:hover {
      transform: scale(1.15) translateY(-2px);
      background: rgba(255, 255, 255, 0.15);
      border-color: rgba(255, 255, 255, 0.3);
    }
    .reaction-btn:active {
      transform: scale(0.92);
    }
    .reaction-btn.bounce {
      animation: reactionBounce 0.35s ease;
    }
    @keyframes reactionBounce {
      0% { transform: scale(0.9); }
      50% { transform: scale(1.25); }
      100% { transform: scale(1); }
    }
    .reaction-divider {
      width: 1px;
      height: 28px;
      background: var(--arena-border);
      flex-shrink: 0;
      margin: 0 4px;
    }
    .shout-pill {
      min-height: 44px;
      height: 44px;
      padding: 0 12px;
      border-radius: 22px;
      border: 1px solid rgba(255, 255, 255, 0.12);
      background: rgba(255, 255, 255, 0.06);
      color: var(--arena-text);
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      white-space: nowrap;
      transition: transform 0.15s ease, background 0.15s ease, border-color 0.15s ease;
      touch-action: manipulation;
      user-select: none;
      flex-shrink: 0;
    }
    [data-bs-theme="light"] .shout-pill {
      background: rgba(0, 0, 0, 0.04);
      border-color: rgba(0, 0, 0, 0.08);
    }
    .shout-pill:hover {
      transform: translateY(-2px);
      background: rgba(255, 255, 255, 0.14);
      border-color: rgba(255, 255, 255, 0.3);
    }
    .shout-pill:active {
      transform: scale(0.95);
    }
    .shout-pill.bounce {
      animation: reactionBounce 0.35s ease;
    }
    .reaction-sound-btn {
      width: 44px;
      height: 44px;
      min-width: 44px;
      min-height: 44px;
      border-radius: 12px;
      border: 1px solid var(--arena-border);
      background: transparent;
      color: var(--arena-muted);
      font-size: 18px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      flex-shrink: 0;
      transition: all 0.15s ease;
      padding: 0;
      touch-action: manipulation;
    }
    .reaction-sound-btn:hover {
      color: var(--arena-text);
      background: rgba(255, 255, 255, 0.08);
    }
    .reaction-sound-btn.is-muted {
      color: #ef4444;
      opacity: 0.8;
    }

    /* Floating Reactions Overlay (pointer-events-none) */
    .reaction-overlay {
      position: fixed;
      inset: 0;
      pointer-events: none;
      z-index: 1060;
      overflow: hidden;
    }
    .floating-reaction {
      position: absolute;
      bottom: 80px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 4px;
      pointer-events: none;
      animation: floatReactionUp 2.2s cubic-bezier(0.22, 1, 0.36, 1) forwards;
      will-change: transform, opacity;
    }
    .floating-reaction .floating-emoji {
      font-size: 44px;
      line-height: 1;
      filter: drop-shadow(0 6px 14px rgba(0,0,0,0.4));
      animation: emojiWobble 0.6s ease-in-out infinite alternate;
    }
    .floating-reaction .floating-sender {
      background: rgba(15, 23, 42, 0.88);
      color: #f8fafc;
      font-size: 11px;
      font-weight: 700;
      padding: 3px 8px;
      border-radius: 999px;
      border: 1px solid rgba(255, 255, 255, 0.22);
      backdrop-filter: blur(8px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.3);
      white-space: nowrap;
      max-width: 140px;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .floating-reaction.is-self .floating-sender {
      background: linear-gradient(135deg, #ff6b5b, #ffb020);
      color: #fff;
      border-color: rgba(255, 255, 255, 0.4);
    }
    .floating-reaction .floating-text {
      background: linear-gradient(135deg, rgba(32, 183, 125, 0.95), rgba(59, 130, 246, 0.95));
      color: #ffffff;
      font-size: 13px;
      font-weight: 800;
      padding: 4px 10px;
      border-radius: 999px;
      border: 1px solid rgba(255, 255, 255, 0.35);
      backdrop-filter: blur(8px);
      box-shadow: 0 6px 16px rgba(0,0,0,0.35);
      white-space: nowrap;
    }
    @keyframes floatReactionUp {
      0% {
        opacity: 0;
        transform: translate(-50%, 40px) scale(0.5);
      }
      15% {
        opacity: 1;
        transform: translate(calc(-50% + var(--wobble-dx, 0px) * 0.4), 0) scale(1.2);
      }
      35% {
        transform: translate(calc(-50% - var(--wobble-dx, 0px) * 0.7), -120px) scale(1);
      }
      70% {
        opacity: 0.95;
        transform: translate(calc(-50% + var(--wobble-dx, 0px)), -280px) scale(0.95);
      }
      100% {
        opacity: 0;
        transform: translate(calc(-50% - var(--wobble-dx, 0px) * 0.5), -440px) scale(0.75);
      }
    }
    @keyframes emojiWobble {
      0% { transform: rotate(-8deg); }
      100% { transform: rotate(8deg); }
    }

    /* Streak Multiplier & Flame Glow */
    .streak-chip {
      background: linear-gradient(135deg, rgba(255, 107, 91, 0.22), rgba(255, 176, 32, 0.22)) !important;
      border-color: rgba(255, 107, 91, 0.5) !important;
      animation: streakPulse 1.2s infinite alternate ease-in-out;
    }
    @keyframes streakPulse {
      0% { transform: scale(1); filter: drop-shadow(0 0 2px rgba(255, 107, 91, 0.4)); }
      100% { transform: scale(1.04); filter: drop-shadow(0 0 8px rgba(255, 176, 32, 0.7)); }
    }
    .stage-on-fire {
      border-color: rgba(255, 107, 91, 0.8) !important;
      animation: fireAura 1.4s infinite alternate ease-in-out !important;
    }
    @keyframes fireAura {
      0% { box-shadow: 0 0 25px rgba(255, 107, 91, 0.35), inset 0 0 20px rgba(255, 176, 32, 0.15); }
      100% { box-shadow: 0 0 50px rgba(255, 107, 91, 0.65), inset 0 0 35px rgba(255, 176, 32, 0.35); }
    }
    .screen-shake {
      animation: stageShake 0.4s ease-in-out;
    }
    @keyframes stageShake {
      0%, 100% { transform: translateX(0); }
      20% { transform: translateX(-8px) rotate(-0.8deg); }
      40% { transform: translateX(8px) rotate(0.8deg); }
      60% { transform: translateX(-5px); }
      80% { transform: translateX(5px); }
    }

    /* End-Game Awards / Accolades */
    .victory-awards-section {
      width: 100%;
      margin: 20px 0 10px;
    }
    .awards-title {
      font-family: "Baloo 2", sans-serif;
      font-size: 20px;
      font-weight: 800;
      color: #ffb020;
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }
    .awards-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
      gap: 12px;
      width: 100%;
    }
    .award-card {
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid var(--arena-border);
      border-radius: 16px;
      padding: 14px;
      display: flex;
      align-items: flex-start;
      gap: 12px;
      backdrop-filter: blur(12px);
      box-shadow: 0 4px 16px rgba(0,0,0,0.1);
      transition: transform 0.2s ease, border-color 0.2s ease;
      text-align: left;
    }
    .award-card:hover {
      transform: translateY(-2px);
      border-color: rgba(255, 176, 32, 0.5);
    }
    .award-card.is-me {
      background: linear-gradient(135deg, rgba(255, 107, 91, 0.16), rgba(255, 176, 32, 0.14));
      border-color: rgba(255, 176, 32, 0.55);
    }
    .award-icon {
      font-size: 32px;
      line-height: 1;
      flex-shrink: 0;
      filter: drop-shadow(0 4px 8px rgba(0,0,0,0.3));
    }
    .award-content {
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 3px;
      min-width: 0;
    }
    .award-name {
      font-size: 12px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #ffb020;
    }
    .award-winner {
      font-size: 14px;
      font-weight: 700;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .award-stat {
      font-size: 12px;
      font-weight: 600;
      color: #38bdf8;
    }
    .award-desc {
      font-size: 11px;
      color: var(--arena-muted);
      line-height: 1.3;
      margin-top: 2px;
    }

    /* Step 3: Power-ups & Sabotage Bar */
    .powerup-bar {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      margin: 8px auto 10px;
      max-width: 480px;
      width: 100%;
      transition: opacity 0.25s ease, transform 0.25s ease;
    }
    .powerup-btn {
      position: relative;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid var(--arena-border);
      color: #fff;
      border-radius: 999px;
      padding: 6px 14px;
      min-height: 44px;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      user-select: none;
      -webkit-tap-highlight-color: transparent;
    }
    [data-bs-theme="light"] .powerup-btn {
      background: #ffffff;
      color: #0f172a;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
    }
    .powerup-btn:hover:not(:disabled) {
      transform: translateY(-2px) scale(1.03);
      background: rgba(255, 255, 255, 0.16);
      border-color: rgba(255, 255, 255, 0.4);
      box-shadow: 0 6px 18px rgba(0, 0, 0, 0.25);
    }
    .powerup-btn:active:not(:disabled) {
      transform: translateY(0) scale(0.97);
    }
    .powerup-btn:disabled, .powerup-btn.is-used {
      opacity: 0.45;
      cursor: not-allowed;
      pointer-events: none;
      box-shadow: none;
    }
    .powerup-btn.is-locked {
      opacity: 0.65;
      filter: grayscale(0.3);
    }
    .powerup-btn.is-ready {
      animation: powerupPulse 2s infinite ease-in-out;
      border-color: #38bdf8 !important;
      box-shadow: 0 0 12px rgba(56, 189, 248, 0.45);
    }
    .powerup-btn.btn-ink.is-ready {
      border-color: #a855f7 !important;
      box-shadow: 0 0 12px rgba(168, 85, 247, 0.45);
    }
    @keyframes powerupPulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.04); }
    }
    .powerup-btn.btn-5050 {
      border-color: rgba(56, 189, 248, 0.45);
    }
    .powerup-btn.btn-ink {
      border-color: rgba(168, 85, 247, 0.45);
    }
    .powerup-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #10b981, #06b6d4);
      color: #fff;
      font-size: 11px;
      font-weight: 800;
      min-width: 20px;
      height: 20px;
      padding: 0 6px;
      border-radius: 999px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }
    .powerup-btn.is-locked .powerup-badge {
      background: rgba(148, 163, 184, 0.25);
      color: #cbd5e1;
      font-size: 10px;
      font-weight: 700;
      min-width: 36px;
      border: 1px solid rgba(255, 255, 255, 0.12);
    }
    [data-bs-theme="light"] .powerup-btn.is-locked .powerup-badge {
      color: #475569;
      background: rgba(100, 116, 139, 0.15);
      border: 1px solid rgba(0, 0, 0, 0.1);
    }
    .powerup-btn.is-used .powerup-badge {
      background: #64748b;
    }

    /* 50/50 Dimmed Tiles */
    .choice-cell.fifty-fifty-dimmed {
      opacity: 0.12 !important;
      pointer-events: none !important;
      filter: grayscale(90%) blur(1.5px) !important;
      transform: scale(0.92) !important;
      transition: all 0.35s ease !important;
    }

    /* Sabotage Ink Splat Overlay */
    .ink-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      pointer-events: auto;
      z-index: 9999;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      transition: opacity 0.4s ease;
    }
    .ink-overlay.is-clearing {
      opacity: 0;
      pointer-events: none;
    }
    .ink-alert-banner {
      background: linear-gradient(135deg, #7e22ce, #c026d3);
      color: #fff;
      padding: 10px 22px;
      border-radius: 999px;
      font-weight: 800;
      font-size: 15px;
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.4), 0 0 16px rgba(192, 38, 211, 0.5);
      margin-bottom: 20px;
      animation: alertBounce 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      text-align: center;
      max-width: 90%;
      user-select: none;
      z-index: 10001;
    }
    @keyframes alertBounce {
      0% { transform: scale(0.5); opacity: 0; }
      100% { transform: scale(1); opacity: 1; }
    }
    .ink-splat-blot {
      position: absolute;
      width: 36px;
      height: 36px;
      cursor: pointer;
      filter: drop-shadow(0 3px 8px rgba(0,0,0,0.6));
      animation: splatPop 0.22s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
      user-select: none;
      transition: transform 0.15s ease, opacity 0.15s ease;
    }
    .ink-splat-blot:hover {
      transform: scale(1.35);
      filter: drop-shadow(0 4px 12px rgba(255, 255, 255, 0.4));
    }
    .ink-splat-blot.blot-popped {
      animation: blotDisappear 0.2s ease forwards;
    }
    @keyframes splatPop {
      0% { transform: scale(0.2) rotate(15deg); opacity: 0; }
      100% { transform: scale(1) rotate(0deg); opacity: 0.94; }
    }
    @keyframes blotDisappear {
      0% { transform: scale(1); opacity: 0.94; }
      100% { transform: scale(1.3); opacity: 0; }
    }

    /* Sabotage Target Selection Modal */
    .sabotage-modal-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.65);
      backdrop-filter: blur(6px);
      z-index: 9990;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 16px;
    }
    .sabotage-modal-card {
      background: var(--arena-bg, #0b1120);
      border: 1px solid rgba(168, 85, 247, 0.45);
      border-radius: 20px;
      padding: 20px;
      width: 100%;
      max-width: 380px;
      box-shadow: 0 16px 40px rgba(0, 0, 0, 0.6), 0 0 25px rgba(168, 85, 247, 0.2);
      text-align: center;
      animation: alertBounce 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .sabotage-targets-list {
      display: flex;
      flex-direction: column;
      gap: 8px;
      margin: 16px 0;
      max-height: 220px;
      overflow-y: auto;
    }
    .sabotage-target-btn {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      padding: 10px 14px;
      border-radius: 12px;
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid var(--arena-border);
      color: #fff;
      font-weight: 700;
      cursor: pointer;
      min-height: 44px;
      transition: all 0.2s ease;
    }
    .sabotage-target-btn:hover {
      background: rgba(168, 85, 247, 0.2);
      border-color: rgba(168, 85, 247, 0.6);
      transform: translateY(-1px);
    }

    /* Step 4: Team vs Team Battle Styles */
    .hud-team-battle {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid var(--arena-border);
      border-radius: 14px;
      padding: 6px 14px;
      margin: 8px auto 10px;
      max-width: 540px;
      width: 100%;
      user-select: none;
    }
    .team-battle-label {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 13px;
      font-weight: 800;
      white-space: nowrap;
    }
    .team-red-label { color: #f43f5e; }
    .team-blue-label { color: #38bdf8; }
    .team-green-label { color: #10b981; }
    .team-yellow-label { color: #f59e0b; }
    .team-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      display: inline-block;
    }
    .dot-red { background: #f43f5e; box-shadow: 0 0 8px rgba(244, 63, 94, 0.6); }
    .dot-blue { background: #38bdf8; box-shadow: 0 0 8px rgba(56, 189, 248, 0.6); }
    .dot-green { background: #10b981; box-shadow: 0 0 8px rgba(16, 185, 129, 0.6); }
    .dot-yellow { background: #f59e0b; box-shadow: 0 0 8px rgba(245, 158, 11, 0.6); }
    .team-battle-bar-wrap {
      flex: 1;
      height: 10px;
      background: rgba(0, 0, 0, 0.4);
      border-radius: 999px;
      overflow: hidden;
      display: flex;
      box-shadow: inset 0 2px 4px rgba(0,0,0,0.3);
    }
    .team-progress-red {
      height: 100%;
      background: linear-gradient(90deg, #f43f5e, #fb7185);
      transition: width 0.4s ease;
    }
    .team-progress-blue {
      height: 100%;
      background: linear-gradient(90deg, #38bdf8, #0284c7);
      transition: width 0.4s ease;
    }
    .team-progress-green {
      height: 100%;
      background: linear-gradient(90deg, #10b981, #34d399);
      transition: width 0.4s ease;
    }
    .team-progress-yellow {
      height: 100%;
      background: linear-gradient(90deg, #f59e0b, #fbbf24);
      transition: width 0.4s ease;
    }

    /* Lobby Team Chooser Box */
    .lobby-teams-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 14px;
      width: 100%;
      max-width: 900px;
      margin: 16px auto 12px;
      text-align: left;
    }
    .lobby-team-card {
      background: rgba(255, 255, 255, 0.04);
      border: 2px solid var(--arena-border);
      border-radius: 16px;
      padding: 14px;
      display: flex;
      flex-direction: column;
      gap: 10px;
      transition: all 0.2s ease;
    }
    .lobby-team-card.team-card-red {
      border-color: rgba(244, 63, 94, 0.4);
    }
    .lobby-team-card.team-card-blue {
      border-color: rgba(56, 189, 248, 0.4);
    }
    .lobby-team-card.team-card-green {
      border-color: rgba(16, 185, 129, 0.4);
    }
    .lobby-team-card.team-card-yellow {
      border-color: rgba(245, 158, 11, 0.4);
    }
    .lobby-team-card.is-my-team {
      background: rgba(255, 255, 255, 0.08);
      box-shadow: 0 0 18px rgba(255, 255, 255, 0.1);
    }
    .lobby-team-card.team-card-red.is-my-team {
      border-color: #f43f5e;
      box-shadow: 0 0 20px rgba(244, 63, 94, 0.3);
    }
    .lobby-team-card.team-card-blue.is-my-team {
      border-color: #38bdf8;
      box-shadow: 0 0 20px rgba(56, 189, 248, 0.3);
    }
    .lobby-team-card.team-card-green.is-my-team {
      border-color: #10b981;
      box-shadow: 0 0 20px rgba(16, 185, 129, 0.3);
    }
    .lobby-team-card.team-card-yellow.is-my-team {
      border-color: #f59e0b;
      box-shadow: 0 0 20px rgba(245, 158, 11, 0.3);
    }
    .team-card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
    }
    .team-card-title {
      font-weight: 800;
      font-size: 14px;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .team-roster-list {
      display: flex;
      flex-direction: column;
      gap: 5px;
      min-height: 44px;
      font-size: 13px;
    }
    .team-member-item {
      display: flex;
      align-items: center;
      gap: 6px;
      color: rgba(255, 255, 255, 0.85);
    }

    /* Final Team Victory Banner */
    .team-victory-banner {
      background: linear-gradient(135deg, rgba(244, 63, 94, 0.2), rgba(56, 189, 248, 0.2));
      border: 2px solid rgba(255, 255, 255, 0.2);
      border-radius: 20px;
      padding: 16px 20px;
      text-align: center;
      margin-bottom: 20px;
      width: 100%;
      box-shadow: 0 12px 32px rgba(0, 0, 0, 0.3);
      animation: alertBounce 0.4s ease;
    }
    .team-victory-banner.winner-red {
      background: linear-gradient(135deg, rgba(244, 63, 94, 0.35), rgba(244, 63, 94, 0.15));
      border-color: #f43f5e;
      box-shadow: 0 0 30px rgba(244, 63, 94, 0.35);
    }
    .team-victory-banner.winner-blue {
      background: linear-gradient(135deg, rgba(56, 189, 248, 0.35), rgba(56, 189, 248, 0.15));
      border-color: #38bdf8;
      box-shadow: 0 0 30px rgba(56, 189, 248, 0.35);
    }
    .team-victory-banner.winner-green {
      background: linear-gradient(135deg, rgba(16, 185, 129, 0.35), rgba(16, 185, 129, 0.15));
      border-color: #10b981;
      box-shadow: 0 0 30px rgba(16, 185, 129, 0.35);
    }
    .team-victory-banner.winner-yellow {
      background: linear-gradient(135deg, rgba(245, 158, 11, 0.35), rgba(245, 158, 11, 0.15));
      border-color: #f59e0b;
      box-shadow: 0 0 30px rgba(245, 158, 11, 0.35);
    }
    .team-victory-title {
      font-family: "Baloo 2", sans-serif;
      font-size: 24px;
      font-weight: 800;
      margin-bottom: 4px;
    }
    .team-victory-scores {
      font-size: 16px;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 16px;
      margin-top: 8px;
    }

    /* Lobby Rules Card */
    .lobby-rules-card {
      background: rgba(255, 255, 255, 0.04);
      border: 1px solid var(--arena-border);
      border-radius: 18px;
      padding: 16px 20px;
      max-width: 680px;
      width: 100%;
      margin: 18px auto 0;
      text-align: left;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
      backdrop-filter: blur(8px);
    }
    .lobby-rules-header {
      display: flex;
      align-items: center;
      gap: 8px;
      font-weight: 800;
      font-size: 14px;
      margin-bottom: 12px;
      color: var(--arena-text);
      border-bottom: 1px solid var(--arena-border);
      padding-bottom: 8px;
      letter-spacing: 0.3px;
    }
    .lobby-rules-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 12px;
    }
    .lobby-rule-item {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      font-size: 12px;
      line-height: 1.45;
      color: var(--arena-muted);
    }
    .lobby-rule-icon {
      font-size: 18px;
      line-height: 1;
      flex-shrink: 0;
      margin-top: 1px;
    }
    .lobby-rule-text strong {
      color: var(--arena-text);
      display: block;
      margin-bottom: 2px;
      font-size: 13px;
    }
    .lobby-rule-item.mode-highlight {
      grid-column: 1 / -1;
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 12px;
      padding: 10px 12px;
    }
    [data-bs-theme="light"] .lobby-rules-card {
      background: rgba(255, 255, 255, 0.85);
      border-color: rgba(0, 0, 0, 0.08);
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
    }
    [data-bs-theme="light"] .lobby-rule-item.mode-highlight {
      background: rgba(0, 0, 0, 0.03);
      border-color: rgba(0, 0, 0, 0.08);
    }

    /* Lobby Avatars Chooser (50 Avatars) */
    .lobby-avatars-card {
      background: rgba(255, 255, 255, 0.04);
      border: 1px solid var(--arena-border);
      border-radius: 20px;
      padding: 16px 20px;
      max-width: 820px;
      width: 100%;
      margin: 18px auto 0;
      text-align: left;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
      backdrop-filter: blur(10px);
    }
    .lobby-avatars-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 14px;
      border-bottom: 1px solid var(--arena-border);
      padding-bottom: 10px;
      flex-wrap: wrap;
    }
    .my-active-avatar-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 14px;
      border-radius: 999px;
      color: #fff;
      font-weight: 800;
      font-size: 13px;
      border: 1px solid rgba(255, 255, 255, 0.25);
      animation: alertBounce 0.3s ease;
    }
    .my-active-avatar-badge .avatar-icon-large {
      font-size: 18px;
    }
    .lobby-avatars-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(64px, 1fr));
      gap: 6px;
      overflow: visible;
    }
    .avatar-tile-btn {
      position: relative;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 8px 4px 6px;
      border-radius: 14px;
      background: rgba(255, 255, 255, 0.04);
      border: 1px solid var(--arena-border);
      cursor: pointer;
      transition: all 0.18s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      color: #fff;
      outline: none;
      user-select: none;
    }
    .avatar-tile-btn:hover:not(:disabled) {
      transform: translateY(-2px) scale(1.05);
      background: var(--avatar-bg, rgba(255, 255, 255, 0.12));
      border-color: var(--avatar-color, #38bdf8);
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.3), 0 0 10px var(--avatar-color, #38bdf8);
    }
    .avatar-tile-btn.is-mine {
      background: var(--avatar-bg, rgba(56, 189, 248, 0.2)) !important;
      border: 2px solid #38bdf8 !important;
      box-shadow: 0 0 18px rgba(56, 189, 248, 0.6) !important;
      transform: scale(1.04);
    }
    .avatar-tile-btn.is-taken {
      opacity: 0.35;
      filter: grayscale(65%);
      cursor: not-allowed;
      pointer-events: none;
      background: rgba(0, 0, 0, 0.2);
    }
    .avatar-tile-icon {
      font-size: 24px;
      line-height: 1.1;
      display: block;
      margin-bottom: 3px;
    }
    .avatar-tile-name {
      font-size: 9.5px;
      font-weight: 700;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 64px;
      opacity: 0.85;
      text-align: center;
    }
    .avatar-status-badge {
      position: absolute;
      top: -4px;
      right: -4px;
      font-size: 8px;
      font-weight: 800;
      padding: 1px 4px;
      border-radius: 999px;
      text-transform: uppercase;
    }
    .avatar-status-badge.badge-mine {
      background: #38bdf8;
      color: #0b1120;
      box-shadow: 0 0 6px rgba(56, 189, 248, 0.8);
    }
    .avatar-status-badge.badge-taken {
      background: #64748b;
      color: #fff;
      max-width: 50px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .player-pill-avatar {
      font-size: 13px;
      display: inline-flex;
      align-items: center;
      line-height: 1;
    }
    .reaction-avatar-btn {
      position: relative;
      background: linear-gradient(135deg, rgba(255, 255, 255, 0.12), rgba(56, 189, 248, 0.22)) !important;
      border: 1.5px solid var(--avatar-glow, #38bdf8) !important;
      box-shadow: 0 0 10px rgba(56, 189, 248, 0.35);
    }
    .reaction-avatar-btn:hover {
      box-shadow: 0 0 16px rgba(56, 189, 248, 0.6) !important;
      transform: scale(1.15) !important;
    }
    [data-bs-theme="light"] .lobby-avatars-card {
      background: rgba(255, 255, 255, 0.88);
      border-color: rgba(0, 0, 0, 0.08);
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
    }
    [data-bs-theme="light"] .avatar-tile-btn {
      background: rgba(0, 0, 0, 0.03);
      color: #1e293b;
    }

    /* === JUICE: LIVING AVATARS === */
    @keyframes avatarBounceGlow {
      0% { transform: scale(1); }
      30% { transform: scale(1.45) translateY(-8px) rotate(-8deg); filter: drop-shadow(0 0 16px #ffe600); }
      60% { transform: scale(1.25) translateY(2px) rotate(8deg); }
      100% { transform: scale(1) translateY(0) rotate(0deg); }
    }
    .avatar-bounce-glow {
      animation: avatarBounceGlow 0.85s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards !important;
      display: inline-block;
    }

    @keyframes avatarDizzy {
      0% { transform: rotate(0deg); }
      20% { transform: rotate(-18deg) scale(0.9); }
      40% { transform: rotate(18deg) scale(0.85); filter: grayscale(80%); }
      60% { transform: rotate(-12deg); }
      80% { transform: rotate(12deg); }
      100% { transform: rotate(0deg) scale(1); }
    }
    .avatar-dizzy {
      animation: avatarDizzy 0.8s ease forwards !important;
      display: inline-block;
    }

    /* === SCREEN SHAKE & COMBO FLAME BORDERS === */
    @keyframes screenShakeLight {
      0%, 100% { transform: translate(0, 0); }
      20% { transform: translate(-3px, 2px); }
      40% { transform: translate(3px, -2px); }
      60% { transform: translate(-2px, -1px); }
      80% { transform: translate(2px, 1px); }
    }
    body.screen-shake-light {
      animation: screenShakeLight 0.32s ease-in-out;
    }

    @keyframes screenShakeHeavy {
      0%, 100% { transform: translate(0, 0) rotate(0deg); }
      15% { transform: translate(-7px, 5px) rotate(-0.5deg); }
      30% { transform: translate(7px, -5px) rotate(0.5deg); }
      50% { transform: translate(-5px, -4px) rotate(-0.3deg); }
      70% { transform: translate(5px, 4px) rotate(0.3deg); }
      85% { transform: translate(-2px, 2px); }
    }
    body.screen-shake-heavy {
      animation: screenShakeHeavy 0.45s ease-in-out;
    }

    body.combo-border-3::after,
    body.combo-border-5::after,
    body.combo-border-10::after {
      content: '';
      position: fixed;
      inset: 0;
      pointer-events: none;
      z-index: 9980;
      transition: opacity 0.4s ease;
    }
    body.combo-border-3::after {
      box-shadow: inset 0 0 24px rgba(56, 189, 248, 0.45);
    }
    body.combo-border-5::after {
      box-shadow: inset 0 0 45px rgba(249, 115, 22, 0.7), inset 0 0 90px rgba(239, 68, 68, 0.35);
      animation: flamePulse 1.2s infinite alternate;
    }
    body.combo-border-10::after {
      box-shadow: inset 0 0 60px rgba(168, 85, 247, 0.8), inset 0 0 120px rgba(236, 72, 153, 0.5);
      animation: rainbowLightning 1s infinite alternate;
    }
    @keyframes flamePulse {
      0% { opacity: 0.7; }
      100% { opacity: 1; }
    }
    @keyframes rainbowLightning {
      0% { filter: hue-rotate(0deg); }
      100% { filter: hue-rotate(360deg); }
    }

    /* === DYNAMIC TOP ANNOUNCER BANNER === */
    .announcer-banner {
      position: fixed;
      top: 68px;
      left: 50%;
      transform: translateX(-50%) translateY(-120px);
      z-index: 10050;
      pointer-events: none;
      transition: transform 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.35s ease;
      opacity: 0;
      max-width: 90vw;
      width: max-content;
    }
    .announcer-banner.is-showing {
      transform: translateX(-50%) translateY(0);
      opacity: 1;
    }
    .announcer-inner {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 22px;
      border-radius: 999px;
      color: #fff;
      font-weight: 800;
      box-shadow: 0 12px 36px rgba(0, 0, 0, 0.5), 0 0 20px rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(255, 255, 255, 0.25);
    }
    .announcer-primary { background: linear-gradient(135deg, #0284c7, #2563eb); }
    .announcer-warning { background: linear-gradient(135deg, #ea580c, #dc2626); }
    .announcer-success { background: linear-gradient(135deg, #059669, #0284c7); }
    .announcer-purple { background: linear-gradient(135deg, #7c3aed, #db2777); }
    .announcer-icon { font-size: 24px; animation: bounce 0.6s infinite alternate; }
    .announcer-title { font-size: 15px; letter-spacing: 0.5px; }
    .announcer-sub { font-size: 12px; opacity: 0.88; font-weight: 600; }

    /* === SABOTAGES: MIRROR, FREEZE, BLACKOUT, SHIELD === */
    /* 1. Mirror Mode */
    .arena-stage.mirror-active,
    .choice-grid.mirror-active {
      transform: scaleX(-1) rotate(180deg) !important;
      transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .mirror-alert-tag {
      position: absolute;
      top: 12px;
      left: 50%;
      transform: translateX(-50%);
      background: rgba(168, 85, 247, 0.9);
      color: #fff;
      padding: 4px 14px;
      border-radius: 999px;
      font-weight: 800;
      font-size: 13px;
      z-index: 20;
      animation: alertBounce 0.3s ease;
    }

    /* 2. Freeze Overlay */
    .freeze-overlay {
      position: absolute;
      inset: -4px;
      background: rgba(186, 230, 253, 0.35);
      backdrop-filter: blur(4px);
      border: 2px solid #38bdf8;
      border-radius: 18px;
      z-index: 15;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      color: #0369a1;
      font-weight: 800;
      cursor: pointer;
      user-select: none;
      animation: alertBounce 0.25s ease;
      box-shadow: inset 0 0 20px rgba(56, 189, 248, 0.5);
    }
    .freeze-cracks {
      font-size: 32px;
      filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
    }
    .freeze-hint {
      background: #0284c7;
      color: #fff;
      padding: 4px 14px;
      border-radius: 999px;
      font-size: 13px;
      margin-top: 6px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.2);
    }

    /* 3. Blackout Flashlight Overlay */
    .blackout-overlay {
      position: fixed;
      inset: 0;
      pointer-events: none;
      z-index: 9995;
      background: radial-gradient(circle 120px at var(--mouse-x, 50%) var(--mouse-y, 50%), transparent 0%, rgba(0, 0, 0, 0.96) 100%);
      transition: opacity 0.3s ease;
    }

    /* 4. Prism Shield */
    .shield-badge-active {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      background: linear-gradient(135deg, #06b6d4, #3b82f6);
      color: #fff;
      padding: 3px 10px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 800;
      box-shadow: 0 0 14px rgba(6, 182, 212, 0.6);
      animation: shieldPulse 1.4s infinite alternate;
    }
    @keyframes shieldPulse {
      0% { box-shadow: 0 0 10px rgba(6, 182, 212, 0.5); }
      100% { box-shadow: 0 0 24px rgba(6, 182, 212, 0.95); }
    }

    /* === NEW GAME MODES UI === */
    /* Hot Potato Bomb Banner */
    .hot-potato-banner {
      background: linear-gradient(135deg, #b91c1c, #ea580c);
      color: #fff;
      padding: 8px 18px;
      border-radius: 999px;
      font-weight: 800;
      font-size: 14px;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 4px 16px rgba(220, 38, 38, 0.4);
      margin-bottom: 12px;
      animation: alertBounce 0.3s ease;
    }
    .hot-potato-banner.is-mine {
      animation: bombPulse 0.5s infinite alternate;
      border: 2px solid #fff;
    }
    @keyframes bombPulse {
      0% { transform: scale(1); filter: drop-shadow(0 0 4px #ef4444); }
      100% { transform: scale(1.06); filter: drop-shadow(0 0 18px #f97316); }
    }

    /* Alchemy Blend Helper */
    .alchemy-blend-preview {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.15);
      border-radius: 999px;
      padding: 4px 14px;
      font-size: 13px;
      font-weight: 700;
      margin-top: 8px;
    }
    .alchemy-slot {
      width: 22px;
      height: 22px;
      border-radius: 50%;
      border: 2px dashed rgba(255, 255, 255, 0.4);
      display: inline-block;
      vertical-align: middle;
      transition: all 0.2s ease;
    }
    .alchemy-slot.is-filled {
      border: 2px solid #fff;
      box-shadow: 0 0 8px rgba(255, 255, 255, 0.5);
    }

    /* Spectator Bet & Cheer Bar */
    .spectator-bar {
      background: rgba(15, 23, 42, 0.85);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.12);
      border-radius: 16px;
      padding: 10px 16px;
      margin: 10px auto;
      max-width: 680px;
      width: 100%;
      text-align: center;
    }
    .spectator-bar-title {
      font-size: 13px;
      font-weight: 800;
      color: #94a3b8;
      margin-bottom: 8px;
    }
    .spectator-bets-grid {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      justify-content: center;
    }
    .spectator-bet-btn {
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.12);
      color: #fff;
      border-radius: 999px;
      padding: 5px 12px;
      font-size: 12px;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .spectator-bet-btn:hover {
      background: rgba(56, 189, 248, 0.25);
      border-color: #38bdf8;
    }
    .spectator-bet-btn.is-voted {
      background: #0284c7;
      border-color: #38bdf8;
      box-shadow: 0 0 12px rgba(56, 189, 248, 0.5);
    }
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
      <div class="hud-chip streak-chip d-none" id="hudStreakChip">
        <span class="hud-label"><?= htmlspecialchars(tt('hud_streak', 'Kombo')) ?></span>
        <span id="hudStreakVal" class="hud-val text-warning">🔥 0x</span>
      </div>
      <div class="hud-chip">
        <span class="hud-label"><?= htmlspecialchars(tt('hud_time', 'Time')) ?></span>
        <span id="hudTimer" class="hud-val">-</span>
      </div>
      <div class="hud-chip">
        <span class="hud-label"><?= htmlspecialchars(tt('room_status', 'Status')) ?></span>
        <span id="hudStatus" class="hud-val text-warning"><?= htmlspecialchars(tt('status_connecting', 'Connecting...')) ?></span>
      </div>
      <div class="hud-leave-wrap d-flex align-items-center">
        <button id="leaveRoomBtn" class="hud-leave-btn w-100" type="button" title="<?= htmlspecialchars(tt('room_leave_btn_title', 'Odadan ayrıl')) ?>">
          <span class="leave-icon" aria-hidden="true">🚪</span>
          <span class="leave-text"><?= htmlspecialchars(tt('room_leave_btn', 'Odadan Ayrıl')) ?></span>
        </button>
      </div>
    </div>

    <!-- Step 3: Power-ups & Sabotage Bar (Active match only) -->
    <div id="powerupBar" class="powerup-bar d-none" aria-label="<?= htmlspecialchars(tt('powerup_bar_aria', 'Joker ve Sabotaj Kartları')) ?>">
      <button type="button" id="powerup5050Btn" class="powerup-btn btn-5050 is-locked" disabled title="<?= htmlspecialchars(tt('powerup_5050_title_locked', '50/50 Joker: 5 tur üst üste doğru cevap vererek aç (0/5)')) ?>">
        <span>🎯</span>
        <span><?= htmlspecialchars(tt('powerup_5050_label', '50/50')) ?></span>
        <span id="badge5050" class="powerup-badge">🔒 0/5</span>
      </button>
      <button type="button" id="powerupInkBtn" class="powerup-btn btn-ink is-locked" disabled title="<?= htmlspecialchars(tt('powerup_ink_title_locked', 'Mürekkep Sabotajı: 5 tur üst üste doğru cevap vererek aç (0/5)')) ?>">
        <span>🦑</span>
        <span><?= htmlspecialchars(tt('powerup_ink_label', 'Mürekkep')) ?></span>
        <span id="badgeInk" class="powerup-badge">🔒 0/5</span>
      </button>
      <button type="button" id="powerupMirrorBtn" class="powerup-btn btn-mirror is-locked" disabled title="<?= htmlspecialchars(tt('powerup_mirror_title_locked', 'Ayna Sabotajı: 5 tur üst üste doğru cevap vererek aç (0/5)')) ?>">
        <span>🪞</span>
        <span><?= htmlspecialchars(tt('powerup_mirror_label', 'Ayna')) ?></span>
        <span id="badgeMirror" class="powerup-badge">🔒 0/5</span>
      </button>
      <button type="button" id="powerupFreezeBtn" class="powerup-btn btn-freeze is-locked" disabled title="<?= htmlspecialchars(tt('powerup_freeze_title_locked', 'Buz Sabotajı: 5 tur üst üste doğru cevap vererek aç (0/5)')) ?>">
        <span>🧊</span>
        <span><?= htmlspecialchars(tt('powerup_freeze_label', 'Buz')) ?></span>
        <span id="badgeFreeze" class="powerup-badge">🔒 0/5</span>
      </button>
      <button type="button" id="powerupBlackoutBtn" class="powerup-btn btn-blackout is-locked" disabled title="<?= htmlspecialchars(tt('powerup_blackout_title_locked', 'Fener Sabotajı: 5 tur üst üste doğru cevap vererek aç (0/5)')) ?>">
        <span>🔦</span>
        <span><?= htmlspecialchars(tt('powerup_blackout_label', 'Fener')) ?></span>
        <span id="badgeBlackout" class="powerup-badge">🔒 0/5</span>
      </button>
      <button type="button" id="powerupShieldBtn" class="powerup-btn btn-shield is-locked" disabled title="<?= htmlspecialchars(tt('powerup_shield_title_locked', 'Prizma Kalkanı: 5 tur üst üste doğru cevap vererek aç (0/5)')) ?>">
        <span>🛡️</span>
        <span><?= htmlspecialchars(tt('powerup_shield_label', 'Kalkan')) ?></span>
        <span id="badgeShield" class="powerup-badge">🔒 0/5</span>
      </button>
    </div>

    <!-- Dynamic Top Announcer Banner -->
    <div id="announcerBanner" class="announcer-banner d-none" aria-live="assertive"></div>

    <!-- Hot Potato Bomb Banner (Hot potato mode only) -->
    <div id="hotPotatoBanner" class="hot-potato-banner d-none">
      <span class="fs-5">💣</span>
      <span id="hotPotatoText">Bomba bekleniyor...</span>
    </div>

    <!-- Spectator Betting & Cheer Bar (Eliminated/Spectator players only) -->
    <div id="spectatorBar" class="spectator-bar d-none">
      <div class="spectator-bar-title">🍿 <?= htmlspecialchars(tt('spectator_bets_title', 'İzleyici Arenası: Bir Sonraki Turun Galibini Tahmin Et (+250 Puan)')) ?></div>
      <div id="spectatorBetsGrid" class="spectator-bets-grid"></div>
    </div>

    <!-- Step 4: Live Team vs Team Battle Bar (Teams mode only) -->
    <div id="hudTeamBattle" class="hud-team-battle d-none">
      <div class="team-battle-label team-red-label">
        <span class="team-dot dot-red"></span>
        <span class="team-name"><?= htmlspecialchars(tt('room_team_red_name', 'Kırmızı Takım')) ?></span>
        <span id="hudScoreRed" class="team-score">0</span>
      </div>
      <div class="team-battle-bar-wrap">
        <div id="teamProgressBarRed" class="team-progress-red" style="width: 50%;"></div>
        <div id="teamProgressBarBlue" class="team-progress-blue" style="width: 50%;"></div>
      </div>
      <div class="team-battle-label team-blue-label">
        <span id="hudScoreBlue" class="team-score">0</span>
        <span class="team-name"><?= htmlspecialchars(tt('room_team_blue_name', 'Mavi Takım')) ?></span>
        <span class="team-dot dot-blue"></span>
      </div>
    </div>

    <!-- Main Live Game Stage -->
    <main class="arena-stage" id="arenaStage">
      <!-- Sabotage Overlays -->
      <div id="freezeOverlay" class="freeze-overlay d-none">
        <div class="freeze-cracks">🧊💥</div>
        <div class="freeze-title fw-bold fs-5 text-white">DONMA SABOTAJI!</div>
        <div class="freeze-hint">Buzu kırmak için 2 kez tıkla! 🔨</div>
      </div>
      <div id="blackoutOverlay" class="blackout-overlay d-none"></div>

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
          <?php elseif (($room['game_mode'] ?? 'elimination') === 'teams'): ?>
            <span class="badge bg-danger-subtle text-danger fs-6 text-nowrap align-middle border border-danger-subtle ms-1">
              ⚔️ <?= htmlspecialchars(tt('rooms_mode_teams_short', 'Takım Savaşı')) ?>
            </span>
          <?php elseif (($room['game_mode'] ?? 'elimination') === 'hot_potato'): ?>
            <span class="badge bg-danger-subtle text-danger fs-6 text-nowrap align-middle border border-danger-subtle ms-1">
              💣 <?= htmlspecialchars(tt('rooms_mode_hotpotato_short', 'Sıcak Patates')) ?>
            </span>
          <?php elseif (($room['game_mode'] ?? 'elimination') === 'flash_memory'): ?>
            <span class="badge bg-info-subtle text-info fs-6 text-nowrap align-middle border border-info-subtle ms-1">
              ⚡ <?= htmlspecialchars(tt('rooms_mode_flash_short', 'Flaş Hafıza')) ?>
            </span>
          <?php elseif (($room['game_mode'] ?? 'elimination') === 'alchemy'): ?>
            <span class="badge rounded-pill fs-6 text-nowrap align-middle ms-1" style="background: rgba(168,85,247,0.18); color: #c084fc; border: 1px solid rgba(168,85,247,0.4);">
              🧪 <?= htmlspecialchars(tt('rooms_mode_alchemy_short', 'Renk Simyası')) ?>
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
        <div id="lobbyTeamsContainer"></div>
        <div id="lobbyAvatarContainer" class="w-100"></div>
        <div id="lobbyRulesContainer"></div>
      </div>
    </main>

    <!-- Live Team Reaction & Sound Effects Bar -->
    <div id="reactionBar" class="reaction-bar" aria-label="<?= htmlspecialchars(tt('reaction_bar_aria', 'Canlı Tepkiler')) ?>">
      <div class="reaction-group">
        <!-- User's Custom Chosen Avatar Reaction -->
        <button type="button" class="reaction-btn reaction-avatar-btn" id="myAvatarReactionBtn" title="<?= htmlspecialchars(tt('reaction_avatar_title', 'Benim Avatarım!')) ?>">
          <span class="reaction-icon" id="myAvatarReactionIcon">💎</span>
        </button>
        <button type="button" class="reaction-btn" data-emoji="🔥" data-sound="fire" title="<?= htmlspecialchars(tt('reaction_fire_title', 'Alev / Fire!')) ?>">
          <span class="reaction-icon">🔥</span>
        </button>
        <button type="button" class="reaction-btn" data-emoji="😂" data-sound="laugh" title="<?= htmlspecialchars(tt('reaction_laugh_title', 'Gülme / Haha!')) ?>">
          <span class="reaction-icon">😂</span>
        </button>
        <button type="button" class="reaction-btn" data-emoji="😱" data-sound="shock" title="<?= htmlspecialchars(tt('reaction_shock_title', 'Şok / Olamaz!')) ?>">
          <span class="reaction-icon">😱</span>
        </button>
        <button type="button" class="reaction-btn" data-emoji="💩" data-sound="poop" title="<?= htmlspecialchars(tt('reaction_poop_title', 'Patates / Oops!')) ?>">
          <span class="reaction-icon">💩</span>
        </button>
        <button type="button" class="reaction-btn" data-emoji="🚀" data-sound="rocket" title="<?= htmlspecialchars(tt('reaction_rocket_title', 'Roket / Haydi!')) ?>">
          <span class="reaction-icon">🚀</span>
        </button>
        <button type="button" class="reaction-btn" data-emoji="🎉" data-sound="party" title="<?= htmlspecialchars(tt('reaction_party_title', 'Parti / GG!')) ?>">
          <span class="reaction-icon">🎉</span>
        </button>
      </div>

      <div class="reaction-divider"></div>

      <!-- Quick Banter Pills for Live Matches -->
      <div class="reaction-group">
        <button type="button" class="shout-pill" data-emoji="🐞" data-sound="banter" data-text="<?= htmlspecialchars(tt('reaction_shout_bug_text', 'Olamaz! 🐞')) ?>">
          <span><?= htmlspecialchars(tt('reaction_shout_bug_text', 'Olamaz! 🐞')) ?></span>
        </button>
        <button type="button" class="shout-pill" data-emoji="👑" data-sound="banter" data-text="<?= htmlspecialchars(tt('reaction_shout_po_text', 'Harikasın! 👑')) ?>">
          <span><?= htmlspecialchars(tt('reaction_shout_po_text', 'Harikasın! 👑')) ?></span>
        </button>
        <button type="button" class="shout-pill" data-emoji="⚡" data-sound="banter" data-text="<?= htmlspecialchars(tt('reaction_shout_hadi_text', 'Hadi! ⚡')) ?>">
          <span><?= htmlspecialchars(tt('reaction_shout_hadi_text', 'Hadi! ⚡')) ?></span>
        </button>
        <button type="button" class="shout-pill" data-emoji="🏆" data-sound="party" data-text="<?= htmlspecialchars(tt('reaction_shout_gg_text', 'GG! 🏆')) ?>">
          <span><?= htmlspecialchars(tt('reaction_shout_gg_text', 'GG! 🏆')) ?></span>
        </button>
      </div>

      <div class="reaction-divider"></div>

      <!-- Sound Mute/Unmute for Reactions -->
      <button type="button" id="reactionSoundToggle" class="reaction-sound-btn" title="<?= htmlspecialchars(tt('room_reaction_sound_toggle', 'Tepki Seslerini Aç/Kapat')) ?>">
        <span id="reactionSoundIcon">🔊</span>
      </button>
    </div>

    <!-- Player Roster & Presence -->
    <div class="players-bar">
      <div class="small fw-bold text-uppercase text-secondary me-2"><?= htmlspecialchars(tt('room_players', 'Players')) ?>:</div>
      <div id="playerList" class="d-flex flex-wrap gap-2 align-items-center flex-1">
        <!-- Live pills dynamically populated -->
      </div>
    </div>
  </div>

  <!-- Floating Reaction Overlay (pointer-events-none) -->
  <div id="reactionOverlay" class="reaction-overlay" aria-hidden="true"></div>

  <!-- Feedback Toast -->
  <div id="feedbackToast" class="feedback-toast">
    <span id="toastIcon"></span>
    <span id="toastText"></span>
  </div>

  <!-- Sabotage Target Selection Modal -->
  <div id="sabotageModal" class="sabotage-modal-backdrop d-none" aria-modal="true" role="dialog">
    <div class="sabotage-modal-card">
      <div class="fs-1">🦑</div>
      <h3 class="fs-5 fw-bold text-white mt-1"><?= htmlspecialchars(tt('sabotage_modal_title', 'Mürekkep Kime Gitsin?')) ?></h3>
      <p class="small text-secondary mb-2"><?= htmlspecialchars(tt('sabotage_modal_desc', 'Seçtiğin rakibin ekranı 3 saniye mürekkeple kaplanacak!')) ?></p>
      <div id="sabotageTargetsList" class="sabotage-targets-list"></div>
      <button type="button" id="closeSabotageModalBtn" class="btn btn-outline-secondary btn-sm rounded-pill w-100 mt-1">
        <?= htmlspecialchars(tt('close', 'Vazgeç')) ?>
      </button>
    </div>
  </div>

  <?php if ($canViewLogs): ?>
  <!-- In-Game Debug / Log Panel Toggle & Drawer (yilmazmukerrem@gmail.com only) -->
  <div id="debugLogToggleBtn" class="debug-log-toggle" title="<?= htmlspecialchars(tt('room_debug_title', 'Debug / Hata Ayıklama')) ?>">
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
    const ME_NAME = <?= json_encode($userDisplay) ?>;
    const GUID = <?= json_encode($guid) ?>;
    const PUSHER_KEY = <?= json_encode(defined('PUSHER_KEY') ? PUSHER_KEY : '') ?>;
    const PUSHER_CLUSTER = <?= json_encode(defined('PUSHER_CLUSTER') ? PUSHER_CLUSTER : 'eu') ?>;
    const ROOM_GAME_MODE = <?= json_encode($room['game_mode'] ?? 'elimination') ?>;
    const ROOM_TEAMS = <?= json_encode($roomTeams, JSON_UNESCAPED_UNICODE) ?>;
    const ALL_FLAGS = <?= json_encode((($room['game_mode'] ?? '') === 'flags') ? \Prismatch\Services\RoomGameService::getFlagPalette() : []) ?>;
    const ALL_AVATARS = <?= json_encode(\Prismatch\Services\RoomGameService::getConceptAvatars(), JSON_UNESCAPED_UNICODE) ?>;

    const STR = {
      lobbyAvatarTitle: <?= json_encode(tt('lobby_avatar_title', 'Karakter Avatarını Seç (50 Avatar)')) ?>,
      lobbyAvatarSubtitle: <?= json_encode(tt('lobby_avatar_subtitle', 'Her avatar tek bir oyuncuya özeldir. Seçtiğin avatar oyun içinde ve emojilerde görünür!')) ?>,
      avatarTaken: <?= json_encode(tt('avatar_taken_error', 'Bu avatar başka bir oyuncu tarafından seçildi!')) ?>,
      reactionAvatarTitle: <?= json_encode(tt('reaction_avatar_title', 'Avatarımı Gönder!')) ?>,
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
      awardsTitle: <?= json_encode(tt('room_awards_title', "Maçın En'leri & Takım Rozetleri")) ?>,
      streakCombo: <?= json_encode(tt('room_streak_combo', "Seri Kombo!")) ?>,
      streakBroken: <?= json_encode(tt('room_streak_broken', "Kombo Kırıldı!")) ?>,
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
      confirmLeaveLobby: <?= json_encode(tt('room_confirm_leave_lobby', 'Odadan ayrılmak istiyor musunuz?')) ?>,
      confirmLeaveMatch: <?= json_encode(tt('room_confirm_leave_match', 'Odadan ayrılmak istediğinize emin misiniz? Oyundan elenecek ve izleyici durumuna geçeceksiniz.')) ?>,
      confirmExitToRooms: <?= json_encode(tt('room_confirm_exit_to_rooms', 'İzleyici olarak odada kalıp maçı izlemek istiyor musunuz? (İptal: Oda listesine dön)')) ?>,
      spectatorNotice: <?= json_encode(tt('room_spectator_notice', 'Elendiniz. İzleyici modundasınız.')) ?>,
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
      modeTeams: <?= json_encode(tt('rooms_mode_teams_short', 'Takım Savaşı')) ?>,
      teamRed: <?= json_encode(tt('room_team_red', '🔴 Kırmızı Takım')) ?>,
      teamBlue: <?= json_encode(tt('room_team_blue', '🔵 Mavi Takım')) ?>,
      joinTeamRed: <?= json_encode(tt('room_join_team_red', '🔴 Kırmızı Takıma Katıl')) ?>,
      joinTeamBlue: <?= json_encode(tt('room_join_team_blue', '🔵 Mavi Takıma Katıl')) ?>,
      yourTeam: <?= json_encode(tt('room_your_team', 'Senin Takımın')) ?>,
      teamRedWon: <?= json_encode(tt('room_team_red_won', '🏆 🔴 KIRMIZI TAKIM KAZANDI!')) ?>,
      teamBlueWon: <?= json_encode(tt('room_team_blue_won', '🏆 🔵 MAVİ TAKIM KAZANDI!')) ?>,
      teamTie: <?= json_encode(tt('room_team_tie', '🤝 DOSTLUK KAZANDI! (BERABERE)')) ?>,
      teamVictoryDesc: <?= json_encode(tt('room_team_victory_desc', 'Takımlar kıyasıya yarıştı! İşte nihai takım skorları:')) ?>,
      powerup5050Ready: <?= json_encode(tt('powerup_5050_ready', '50/50 Joker: Yanlış şıkların yarısını eler (Hazır!)')) ?>,
      powerup5050Locked: <?= json_encode(tt('powerup_5050_locked', '50/50 Joker: 5 tur üst üste doğru cevap vererek aç ({streak}/5)')) ?>,
      powerupInkReady: <?= json_encode(tt('powerup_ink_ready', 'Mürekkep Sabotajı: Rakibin ekranını karala! (Hazır!)')) ?>,
      powerupInkLocked: <?= json_encode(tt('powerup_ink_locked', 'Mürekkep Sabotajı: 5 tur üst üste doğru cevap vererek aç ({streak}/5)')) ?>,
      powerup5050StreakReq: <?= json_encode(tt('powerup_5050_streak_req', '50/50 jokeri için 5 tur üst üste doğru cevap gerekli! ({streak}/5)')) ?>,
      powerup5050OnlyQuestion: <?= json_encode(tt('powerup_5050_only_question', '50/50 yalnızca seçenekler ekrandayken kullanılabilir!')) ?>,
      powerup5050Active: <?= json_encode(tt('powerup_5050_active', '🎯 50/50 Joker Aktif! Yanlış şıklar elendi.')) ?>,
      sabotageNoRivals: <?= json_encode(tt('sabotage_no_rivals', 'Sabote edilecek aktif rakip yok!')) ?>,
      sabotageLeader: <?= json_encode(tt('sabotage_leader', '(Lider)')) ?>,
      powerupInkStreakReq: <?= json_encode(tt('powerup_ink_streak_req', 'Mürekkep sabotajı için 5 tur üst üste doğru cevap gerekli! ({streak}/5)')) ?>,
      powerupInkFired: <?= json_encode(tt('powerup_ink_fired', '🦑 {target} hedeflendi! Mürekkep fırlatıldı!')) ?>,
      powerupInkAlert: <?= json_encode(tt('powerup_ink_alert', '🦑 <strong>{attacker}</strong> sana mürekkep fırlattı!<br><span style="font-size: 13px; font-weight: normal; opacity: 0.9;">Pikselleri açmak için lekelere tıkla!</span>')) ?>,
      noMembersYet: <?= json_encode(tt('no_members_yet', 'Henüz kimse yok')) ?>,
      joinedTeamRed: <?= json_encode(tt('joined_team_red', '🔴 Kırmızı takıma katıldın!')) ?>,
      joinedTeamBlue: <?= json_encode(tt('joined_team_blue', '🔵 Mavi takıma katıldın!')) ?>,
      powerupBothUnlocked: <?= json_encode(tt('powerup_both_unlocked', '🎯 50/50 ve 🦑 Mürekkep Jokerleri')) ?>,
      powerupInkUnlocked: <?= json_encode(tt('powerup_ink_unlocked', '🦑 Mürekkep Sabotajı')) ?>,
      powerup5050Unlocked: <?= json_encode(tt('powerup_5050_unlocked', '🎯 50/50 Jokeri')) ?>,
      powerupEarnedToast: <?= json_encode(tt('powerup_earned_toast', '🎉 5 tur üst üste doğru! {item} Kazandın!')) ?>,
      powerupInkBrdcst: <?= json_encode(tt('powerup_ink_broadcast', '🦑 {attacker}, {victim} oyuncusuna mürekkep fırlattı!')) ?>,
      powerup5050Brdcst: <?= json_encode(tt('powerup_5050_broadcast', '🎯 {user} 50/50 jokerini kullandı!')) ?>,
      lobbyRulesTitle: <?= json_encode(tt('lobby_rules_title', 'Oyun Kuralları & İpuçları')) ?>,
      ruleGoalTitle: <?= json_encode(tt('lobby_rule_goal_title', 'Hedefi Hafızana Al')) ?>,
      ruleGoalDesc: <?= json_encode(tt('lobby_rule_goal_desc', 'Tur başında ekranda beliren hedef rengi veya bayrağı dikkatlice incele ve kaybolmadan hafızana al.')) ?>,
      ruleSpeedTitle: <?= json_encode(tt('lobby_rule_speed_title', 'Hız ve İsabet')) ?>,
      ruleSpeedDesc: <?= json_encode(tt('lobby_rule_speed_desc', 'Izgaradaki doğru seçeneği ne kadar hızlı bulup seçersen, o kadar yüksek puan kazanırsın.')) ?>,
      rulePowerupsTitle: <?= json_encode(tt('lobby_rule_powerups_title', 'Jokerler & Sabotaj')) ?>,
      rulePowerupsDesc: <?= json_encode(tt('lobby_rule_powerups_desc', '5 tur üst üste doğru cevap vererek 🎯 %50 Jokerini ve rakiplerin ekranını karalayan 🦑 Mürekkep Sabotajını aç.')) ?>,
      ruleModeTitle: <?= json_encode(tt('lobby_rule_mode_title', 'Mod Kuralı')) ?>,
      ruleElimDesc: <?= json_encode(tt('lobby_rule_elim_desc', 'Hayatta kalma mücadelesi! Yanlış seçim yapan veya süresi dolan elenir ve izleyici olur. Son hayatta kalan kazanır.')) ?>,
      rulePointsDesc: <?= json_encode(tt('lobby_rule_points_desc', 'Elenme yok! Doğru cevap puan kazandırır, yanlış seçim puan düşürür. 25 turun sonunda en yüksek puanlı kazanır.')) ?>,
      ruleFlagsDesc: <?= json_encode(tt('lobby_rule_flags_desc', '250+ ülke bayrağı! Elenme yok, doğru bayrağı en hızlı bulan ve en çok puanı toplayan şampiyon olur.')) ?>,
      ruleTeamsDesc: <?= json_encode(tt('lobby_rule_teams_desc', 'Takımını seç! Tüm takım üyelerinin bireysel puanları takım havuzuna eklenir. En yüksek skoru toplayan takım kazanır.')) ?>,
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

    // Flag Image Preloader & Cache: Hedef bayrağın diğerlerinden önce yüklenip kendini ele vermesini önler
    const flagImageCache = new Map();

    function preloadFlagImage(src) {
      if (!src) return Promise.resolve(src);
      if (flagImageCache.has(src)) {
        return flagImageCache.get(src);
      }
      const p = new Promise((resolve) => {
        const img = new Image();
        img.onload = () => {
          if (typeof img.decode === 'function') {
            img.decode().then(() => resolve(src)).catch(() => resolve(src));
          } else {
            resolve(src);
          }
        };
        img.onerror = () => resolve(src);
        img.src = src;
      });
      flagImageCache.set(src, p);
      return p;
    }

    function preloadFlagImages(urls) {
      if (!Array.isArray(urls) || urls.length === 0) return Promise.resolve([]);
      return Promise.all(urls.map(u => preloadFlagImage(u)));
    }

    let backgroundPreloadStarted = false;
    function backgroundPreloadAllFlags() {
      if (backgroundPreloadStarted || !Array.isArray(ALL_FLAGS) || ALL_FLAGS.length === 0) return;
      backgroundPreloadStarted = true;
      let idx = 0;
      function step() {
        if (idx >= ALL_FLAGS.length) return;
        const batch = ALL_FLAGS.slice(idx, idx + 6);
        idx += 6;
        preloadFlagImages(batch).then(() => {
          if ('requestIdleCallback' in window) {
            requestIdleCallback(step, { timeout: 1000 });
          } else {
            setTimeout(step, 80);
          }
        });
      }
      if ('requestIdleCallback' in window) {
        requestIdleCallback(step, { timeout: 1000 });
      } else {
        setTimeout(step, 200);
      }
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
      gridPreloadPromise: null,
      showMs: 3000,
      answerMs: 5000,
      countdownMs: 3000,
      eliminated: false,
      userExplicitlyLeft: false,
      joinedAsSpectator: false,
      answered: false,
      isHost: false,
      questionStartTs: 0,
      activeTimer: null,
      countdownInterval: null,
      intermissionTimer: null,
      players: [],
      presenceMemberIds: new Set(),
      myRoundStats: [],
      streak: 0,
      maxStreak: 0,
      powerups: {
        fifty_fifty_available: false,
        fifty_fifty_streak: 0,
        ink_available: false,
        ink_streak: 0,
        mirror_available: false,
        mirror_streak: 0,
        freeze_available: false,
        freeze_streak: 0,
        blackout_available: false,
        blackout_streak: 0,
        shield_available: false,
        shield_streak: 0,
        has_shield: false,
      },
      bombHolderId: null,
      alchemyPick1: null,
      alchemyPick2: null,
      myPredictionUserId: null,
      teams: (Array.isArray(ROOM_TEAMS) && ROOM_TEAMS.length > 0) ? ROOM_TEAMS : [
        { id: 'red', name: 'Red', color: '#ef4444' },
        { id: 'blue', name: 'Blue', color: '#3b82f6' }
      ],
      myTeam: (Array.isArray(ROOM_TEAMS) && ROOM_TEAMS[0]) ? ROOM_TEAMS[0].id : 'red',
      myAvatar: null,
      avatars: (Array.isArray(ALL_AVATARS) && ALL_AVATARS.length > 0) ? ALL_AVATARS : [],
      teamSummary: null,
      targetShowTimer: null,
      serverTimeOffsetMs: 0,
      roundStartedAtMs: 0,
      targetShowStartTs: 0,
      pendingLeaderboard: null,
    };

    function getPlayerDisplayName(p) {
      if (!p) return STR.player || 'Player';
      const raw = p.name || p.user_name || p.nickname;
      if (raw && String(raw).trim() !== '') return String(raw).trim();
      if (p.email) {
        const parts = String(p.email).split('@');
        if (parts[0] && parts[0].trim() !== '') return parts[0].trim();
      }
      return STR.player || 'Player';
    }

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
    const hudStreakChip = document.getElementById('hudStreakChip');
    const hudStreakVal = document.getElementById('hudStreakVal');
    const stageContent = document.getElementById('stageContent');
    const playerList = document.getElementById('playerList');
    const hostControls = document.getElementById('hostControls');
    const startMatchBtn = document.getElementById('startMatchBtn');
    const feedbackToast = document.getElementById('feedbackToast');

    function updateStreakUI(streak) {
      if (!hudStreakChip || !hudStreakVal) return;
      if (streak >= 2) {
        hudStreakChip.classList.remove('d-none');
        hudStreakVal.innerHTML = `🔥 ${streak}x`;
      } else {
        hudStreakChip.classList.add('d-none');
      }
    }
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

    // Live Reaction Sound Engine (Synthesized, low-latency, zero-asset)
    let reactionSoundsEnabled = localStorage.getItem('pm-reaction-sound') !== '0';

    function updateReactionSoundUI() {
      const icon = document.getElementById('reactionSoundIcon');
      const btn = document.getElementById('reactionSoundToggle');
      if (icon && btn) {
        icon.textContent = reactionSoundsEnabled ? '🔊' : '🔇';
        btn.classList.toggle('is-muted', !reactionSoundsEnabled);
      }
    }

    function playReactionSound(soundType) {
      if (!reactionSoundsEnabled) return;
      try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return;
        audioCtx = audioCtx || new Ctx();
        if (audioCtx.state === 'suspended') audioCtx.resume();
        const now = audioCtx.currentTime;

        switch (soundType) {
          case 'fire': {
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(140, now);
            osc.frequency.exponentialRampToValueAtTime(880, now + 0.28);
            gain.gain.setValueAtTime(0.09, now);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.32);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start(now);
            osc.stop(now + 0.32);
            break;
          }
          case 'laugh': {
            [0, 0.08, 0.16, 0.24].forEach((delay, idx) => {
              const osc = audioCtx.createOscillator();
              const gain = audioCtx.createGain();
              osc.type = 'triangle';
              osc.frequency.value = (idx % 2 === 0) ? 587 : 784;
              gain.gain.setValueAtTime(0.08, now + delay);
              gain.gain.exponentialRampToValueAtTime(0.0001, now + delay + 0.06);
              osc.connect(gain);
              gain.connect(audioCtx.destination);
              osc.start(now + delay);
              osc.stop(now + delay + 0.07);
            });
            break;
          }
          case 'shock': {
            [550, 584].forEach(f => {
              const osc = audioCtx.createOscillator();
              const gain = audioCtx.createGain();
              osc.type = 'sawtooth';
              osc.frequency.setValueAtTime(f, now);
              osc.frequency.exponentialRampToValueAtTime(f * 0.45, now + 0.35);
              gain.gain.setValueAtTime(0.07, now);
              gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.35);
              osc.connect(gain);
              gain.connect(audioCtx.destination);
              osc.start(now);
              osc.stop(now + 0.35);
            });
            break;
          }
          case 'poop': {
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(320, now);
            osc.frequency.exponentialRampToValueAtTime(65, now + 0.38);
            gain.gain.setValueAtTime(0.12, now);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.40);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start(now);
            osc.stop(now + 0.40);
            break;
          }
          case 'rocket': {
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(180, now);
            osc.frequency.exponentialRampToValueAtTime(1400, now + 0.35);
            gain.gain.setValueAtTime(0.10, now);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.38);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start(now);
            osc.stop(now + 0.38);
            break;
          }
          case 'party': {
            [523.25, 659.25, 783.99, 1046.50].forEach((freq, idx) => {
              const osc = audioCtx.createOscillator();
              const gain = audioCtx.createGain();
              osc.type = 'sine';
              osc.frequency.value = freq;
              const start = now + (idx * 0.06);
              gain.gain.setValueAtTime(0.08, start);
              gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.22);
              osc.connect(gain);
              gain.connect(audioCtx.destination);
              osc.start(start);
              osc.stop(start + 0.25);
            });
            break;
          }
          case 'shield': {
            [523.25, 659.25, 1046.50, 1318.51].forEach((freq, idx) => {
              const osc = audioCtx.createOscillator();
              const gain = audioCtx.createGain();
              osc.type = 'triangle';
              osc.frequency.setValueAtTime(freq, now + idx * 0.06);
              gain.gain.setValueAtTime(0.09, now + idx * 0.06);
              gain.gain.exponentialRampToValueAtTime(0.0001, now + idx * 0.06 + 0.35);
              osc.connect(gain);
              gain.connect(audioCtx.destination);
              osc.start(now + idx * 0.06);
              osc.stop(now + idx * 0.06 + 0.36);
            });
            break;
          }
          case 'reflect': {
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'square';
            osc.frequency.setValueAtTime(1200, now);
            osc.frequency.exponentialRampToValueAtTime(2400, now + 0.15);
            gain.gain.setValueAtTime(0.12, now);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.28);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start(now);
            osc.stop(now + 0.3);
            break;
          }
          case 'freeze': {
            [1400, 1850, 2200].forEach((freq, idx) => {
              const osc = audioCtx.createOscillator();
              const gain = audioCtx.createGain();
              osc.type = 'sawtooth';
              osc.frequency.setValueAtTime(freq, now + idx * 0.04);
              gain.gain.setValueAtTime(0.08, now + idx * 0.04);
              gain.gain.exponentialRampToValueAtTime(0.0001, now + idx * 0.04 + 0.18);
              osc.connect(gain);
              gain.connect(audioCtx.destination);
              osc.start(now + idx * 0.04);
              osc.stop(now + idx * 0.04 + 0.2);
            });
            break;
          }
          case 'mirror': {
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(300, now);
            osc.frequency.linearRampToValueAtTime(900, now + 0.18);
            osc.frequency.linearRampToValueAtTime(200, now + 0.35);
            gain.gain.setValueAtTime(0.1, now);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.38);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start(now);
            osc.stop(now + 0.4);
            break;
          }
          case 'blackout': {
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(240, now);
            osc.frequency.exponentialRampToValueAtTime(45, now + 0.4);
            gain.gain.setValueAtTime(0.15, now);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.45);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start(now);
            osc.stop(now + 0.45);
            break;
          }
          case 'bomb_tick': {
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(800, now);
            gain.gain.setValueAtTime(0.09, now);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.04);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start(now);
            osc.stop(now + 0.05);
            break;
          }
          case 'bomb_boom': {
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(160, now);
            osc.frequency.exponentialRampToValueAtTime(30, now + 0.6);
            gain.gain.setValueAtTime(0.25, now);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.65);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start(now);
            osc.stop(now + 0.7);
            break;
          }
          case 'combo_3': {
            [660, 880, 1100].forEach((freq, idx) => {
              const osc = audioCtx.createOscillator();
              const gain = audioCtx.createGain();
              osc.type = 'triangle';
              osc.frequency.setValueAtTime(freq, now + idx * 0.07);
              gain.gain.setValueAtTime(0.1, now + idx * 0.07);
              gain.gain.exponentialRampToValueAtTime(0.0001, now + idx * 0.07 + 0.22);
              osc.connect(gain);
              gain.connect(audioCtx.destination);
              osc.start(now + idx * 0.07);
              osc.stop(now + idx * 0.07 + 0.23);
            });
            break;
          }
          case 'combo_5': {
            [523, 659, 784, 1046, 1318].forEach((freq, idx) => {
              const osc = audioCtx.createOscillator();
              const gain = audioCtx.createGain();
              osc.type = 'sine';
              osc.frequency.setValueAtTime(freq, now + idx * 0.06);
              gain.gain.setValueAtTime(0.12, now + idx * 0.06);
              gain.gain.exponentialRampToValueAtTime(0.0001, now + idx * 0.06 + 0.28);
              osc.connect(gain);
              gain.connect(audioCtx.destination);
              osc.start(now + idx * 0.06);
              osc.stop(now + idx * 0.06 + 0.3);
            });
            break;
          }
          case 'announcer_leader_down': {
            [740, 659, 587, 440].forEach((freq, idx) => {
              const osc = audioCtx.createOscillator();
              const gain = audioCtx.createGain();
              osc.type = 'sawtooth';
              osc.frequency.setValueAtTime(freq, now + idx * 0.1);
              gain.gain.setValueAtTime(0.1, now + idx * 0.1);
              gain.gain.exponentialRampToValueAtTime(0.0001, now + idx * 0.1 + 0.25);
              osc.connect(gain);
              gain.connect(audioCtx.destination);
              osc.start(now + idx * 0.1);
              osc.stop(now + idx * 0.1 + 0.26);
            });
            break;
          }
          case 'ink_splat': {
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(360, now);
            osc.frequency.exponentialRampToValueAtTime(70, now + 0.3);
            gain.gain.setValueAtTime(0.12, now);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.35);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start(now);
            osc.stop(now + 0.35);
            break;
          }
          case 'ink_pop': {
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(540, now);
            osc.frequency.exponentialRampToValueAtTime(920, now + 0.08);
            gain.gain.setValueAtTime(0.14, now);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.1);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start(now);
            osc.stop(now + 0.1);
            break;
          }
          case 'fifty_fifty': {
            [587, 740, 880].forEach((freq, idx) => {
              const osc = audioCtx.createOscillator();
              const gain = audioCtx.createGain();
              osc.type = 'sine';
              osc.frequency.setValueAtTime(freq, now + idx * 0.07);
              gain.gain.setValueAtTime(0.08, now + idx * 0.07);
              gain.gain.exponentialRampToValueAtTime(0.0001, now + idx * 0.07 + 0.28);
              osc.connect(gain);
              gain.connect(audioCtx.destination);
              osc.start(now + idx * 0.07);
              osc.stop(now + idx * 0.07 + 0.28);
            });
            break;
          }
          case 'banter':
          default: {
            [660, 880].forEach((freq, idx) => {
              const osc = audioCtx.createOscillator();
              const gain = audioCtx.createGain();
              osc.type = 'triangle';
              osc.frequency.value = freq;
              const start = now + (idx * 0.08);
              gain.gain.setValueAtTime(0.07, start);
              gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.12);
              osc.connect(gain);
              gain.connect(audioCtx.destination);
              osc.start(start);
              osc.stop(start + 0.14);
            });
            break;
          }
        }
      } catch(e) {}
    }

    function spawnFloatingReaction(emoji, userName, sound = 'pop', text = null, isSelf = false) {
      playReactionSound(sound);

      const overlay = document.getElementById('reactionOverlay');
      if (!overlay) return;

      const bubble = document.createElement('div');
      bubble.className = 'floating-reaction' + (isSelf ? ' is-self' : '');

      const randomX = Math.floor(Math.random() * 76) + 12;
      bubble.style.left = `${randomX}%`;

      const wobble = (Math.random() * 40 - 20).toFixed(0);
      bubble.style.setProperty('--wobble-dx', `${wobble}px`);

      let html = `<span class="floating-emoji">${emoji}</span>`;
      if (text) {
        html += `<span class="floating-text">${escapeHtml(text)}</span>`;
      }
      if (userName) {
        const displayName = isSelf ? (STR.you ? `${escapeHtml(userName)} ${STR.you}` : escapeHtml(userName)) : escapeHtml(userName);
        html += `<span class="floating-sender">${displayName}</span>`;
      }
      bubble.innerHTML = html;
      overlay.appendChild(bubble);

      setTimeout(() => {
        if (bubble.parentNode) {
          bubble.parentNode.removeChild(bubble);
        }
      }, 2300);
    }

    let lastReactionTime = 0;
    async function sendReaction(emoji, sound = 'pop', text = null) {
      const now = Date.now();
      if (now - lastReactionTime < 220) return;
      lastReactionTime = now;

      spawnFloatingReaction(emoji, ME_NAME, sound, text, true);

      try {
        await fetch('api/rooms_reaction.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            guid: GUID,
            emoji: emoji,
            sound: sound,
            text: text
          })
        });
      } catch (err) {
        RoomLogger.warn('Reaction', 'Failed to broadcast reaction', err);
      }
    }

    function showToast(text, isCorrect = null) {
      if (isCorrect === true) {
        toastIcon.textContent = '✅';
        feedbackToast.className = 'feedback-toast show correct';
      } else if (isCorrect === false) {
        toastIcon.textContent = '❌';
        feedbackToast.className = 'feedback-toast show wrong';
      } else {
        toastIcon.textContent = '⚡';
        feedbackToast.className = 'feedback-toast show';
      }
      toastText.textContent = text;
      setTimeout(() => { feedbackToast.classList.remove('show'); }, 2200);
    }

    function updatePowerupUI() {
      const bar = document.getElementById('powerupBar');
      if (!bar) return;

      const isGameActive = state.phase === 'question' || state.phase === 'show' || state.phase === 'countdown';
      if (!isGameActive || state.eliminated) {
        bar.classList.add('d-none');
        return;
      }

      bar.classList.remove('d-none');

      const items = [
        { id: '5050', key: 'fifty_fifty', readyTitle: STR.powerup5050Ready || '50/50 Joker: Yanlış şıkların yarısını eler (Hazır!)', lockedTitle: STR.powerup5050Locked || '50/50 Joker: 5 tur üst üste doğru cevap vererek aç ({streak}/5)' },
        { id: 'Ink', key: 'ink', readyTitle: STR.powerupInkReady || 'Mürekkep Sabotajı: Rakibin ekranını karala! (Hazır!)', lockedTitle: STR.powerupInkLocked || 'Mürekkep Sabotajı: 5 tur üst üste doğru cevap vererek aç ({streak}/5)' },
        { id: 'Mirror', key: 'mirror', readyTitle: STR.powerupMirrorReady || 'Ayna Sabotajı: Liderin ekranını ters çevir! (Hazır!)', lockedTitle: STR.powerupMirrorLocked || 'Ayna Sabotajı: 5 tur üst üste doğru cevap vererek aç ({streak}/5)' },
        { id: 'Freeze', key: 'freeze', readyTitle: STR.powerupFreezeReady || 'Buz Sabotajı: Liderin şıklarını dondur! (Hazır!)', lockedTitle: STR.powerupFreezeLocked || 'Buz Sabotajı: 5 tur üst üste doğru cevap vererek aç ({streak}/5)' },
        { id: 'Blackout', key: 'blackout', readyTitle: STR.powerupBlackoutReady || 'Fener Sabotajı: Liderin ekranını karart! (Hazır!)', lockedTitle: STR.powerupBlackoutLocked || 'Fener Sabotajı: 5 tur üst üste doğru cevap vererek aç ({streak}/5)' },
        { id: 'Shield', key: 'shield', readyTitle: STR.powerupShieldReady || 'Prizma Kalkanı: Gelecek ilk sabotajı geri yansıtır! (Hazır!)', lockedTitle: STR.powerupShieldLocked || 'Prizma Kalkanı: 5 tur üst üste doğru cevap vererek aç ({streak}/5)' },
      ];

      items.forEach(it => {
        const btn = document.getElementById(`powerup${it.id}Btn`);
        const badge = document.getElementById(`badge${it.id}`);
        if (!btn) return;

        const isAvail = Boolean(state.powerups[`${it.key}_available`]);
        const streak = Math.min(5, Math.max(0, state.powerups[`${it.key}_streak`] || 0));

        if (isAvail) {
          btn.disabled = false;
          btn.classList.remove('is-used', 'is-locked');
          btn.classList.add('is-ready');
          if (badge) badge.textContent = '1';
          btn.title = it.readyTitle;
        } else {
          btn.disabled = true;
          btn.classList.remove('is-ready', 'is-used');
          btn.classList.add('is-locked');
          if (badge) badge.textContent = `🔒 ${streak}/5`;
          btn.title = it.lockedTitle.replace('{streak}', String(streak));
        }
      });
    }

    function useFiftyFifty() {
      if (!state.powerups.fifty_fifty_available) {
        const streak = state.powerups.fifty_fifty_streak || 0;
        showToast((STR.powerup5050StreakReq || '50/50 jokeri için 5 tur üst üste doğru cevap gerekli! ({streak}/5)').replace('{streak}', streak));
        return;
      }
      if (state.phase !== 'question' || state.eliminated || state.answered) {
        showToast(STR.powerup5050OnlyQuestion || '50/50 yalnızca seçenekler ekrandayken kullanılabilir!');
        return;
      }
      const gridEl = document.getElementById('choiceGrid');
      if (!gridEl) return;
      const buttons = Array.from(gridEl.querySelectorAll('.choice-cell'));
      const wrongButtons = buttons.filter(btn => btn.dataset.item !== state.targetColor);
      if (wrongButtons.length <= 1) return;

      const shuffled = wrongButtons.sort(() => 0.5 - Math.random());
      const toDim = shuffled.slice(0, Math.floor(wrongButtons.length / 2));
      toDim.forEach(btn => {
        btn.classList.add('fifty-fifty-dimmed');
      });

      state.powerups.fifty_fifty_available = false;
      state.powerups.fifty_fifty_streak = 0;
      updatePowerupUI();
      playReactionSound('fifty_fifty');
      showToast(STR.powerup5050Active || '🎯 50/50 Joker Aktif! Yanlış şıklar elendi.');

      fetch('api/rooms_powerup.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ guid: GUID, type: 'fifty_fifty' })
      }).catch(e => RoomLogger.warn('Powerup', '50/50 report error', e));
    }

    function openSabotageModal() {
      // Oyuncu seçimi iptal edildi: Doğrudan lider rakip hedeflenir
      fireInkSabotage();
    }

    function fireInkSabotage() {
      if (!state.powerups.ink_available) {
        const streak = state.powerups.ink_streak || 0;
        showToast((STR.powerupInkStreakReq || 'Mürekkep sabotajı için 5 tur üst üste doğru cevap gerekli! ({streak}/5)').replace('{streak}', streak));
        return;
      }
      if (state.eliminated) return;

      const rivals = (state.players || []).filter(p => 
        p.email !== ME_EMAIL && 
        String(p.user_id) !== String(ME_ID) && 
        p.status !== 'eliminated'
      );

      if (rivals.length === 0) {
        showToast(STR.sabotageNoRivals || 'Sabote edilecek aktif rakip yok!');
        return;
      }

      // SADECE lider rakip oyuncuyu hedefle
      const sortedRivals = [...rivals].sort((a, b) => (Number(b.score) || 0) - (Number(a.score) || 0));
      const leader = sortedRivals[0];
      const leaderName = leader.user_name || (leader.email ? leader.email.split('@')[0] : (STR.player || 'Lider'));

      // Rastgele canlı mürekkep rengi
      const inkColors = ['#ff007f', '#00e5ff', '#39ff14', '#ffe600', '#a855f7', '#ff3d00', '#00ff88', '#ec4899', '#3b82f6', '#ff5722', '#8a2be2', '#00f5d4'];
      const chosenColor = inkColors[Math.floor(Math.random() * inkColors.length)];

      state.powerups.ink_available = false;
      state.powerups.ink_streak = 0;
      updatePowerupUI();

      playReactionSound('ink_splat');
      showToast((STR.powerupInkFired || '🦑 Lider {target} hedeflendi! Mürekkep fırlatıldı!').replace('{target}', leaderName.split('@')[0]));

      fetch('api/rooms_powerup.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
          guid: GUID,
          type: 'ink_splat',
          target_user_id: leader.user_id,
          color: chosenColor
        })
      }).catch(e => RoomLogger.warn('Powerup', 'Ink sabotage report error', e));
    }

    function triggerInkSplatEffect(attackerName, splatColor) {
      const color = (splatColor && typeof splatColor === 'string' && splatColor.startsWith('#')) ? splatColor : '#ff007f';
      playReactionSound('ink_splat');
      if (navigator.vibrate) navigator.vibrate([100, 50, 100]);

      const existing = document.getElementById('inkOverlay');
      if (existing) existing.remove();

      const overlay = document.createElement('div');
      overlay.id = 'inkOverlay';
      overlay.className = 'ink-overlay';

      const banner = document.createElement('div');
      banner.className = 'ink-alert-banner';
      banner.style.background = `linear-gradient(135deg, ${color}, #0f172a)`;
      banner.style.boxShadow = `0 8px 30px rgba(0, 0, 0, 0.5), 0 0 24px ${color}80`;
      banner.innerHTML = (STR.powerupInkAlert || '🦑 <strong>{attacker}</strong> sana mürekkep fırlattı!<br><span style="font-size: 13px; font-weight: normal; opacity: 0.9;">Temizlemek için damlalara tıkla!</span>').replace('{attacker}', escapeHtml(attackerName));
      overlay.appendChild(banner);

      const blotSvgPaths = [
        'M48.7,11.2 C66.8,4.1 82.5,18.4 88.3,34.8 C94.2,51.6 89.1,72.4 75.3,83.7 C61.2,95.3 39.8,94.9 24.1,84.1 C9.2,73.8 2.1,54.7 6.4,37.2 C10.6,20.1 30.1,18.5 48.7,11.2 Z',
        'M35.2,8.4 C52.1,-1.2 75.3,4.7 85.6,20.5 C95.9,36.4 91.2,60.1 79.4,74.6 C67.9,88.7 46.8,96.3 30.1,88.2 C13.7,80.3 3.5,60.8 7.4,43.2 C11.2,26.1 18.2,17.9 35.2,8.4 Z',
        'M53.1,14.5 C68.4,9.2 84.6,22.1 89.2,37.8 C93.7,53.2 84.1,70.5 71.3,79.8 C58.2,89.4 38.6,87.6 25.4,77.3 C12.8,67.4 8.4,49.2 14.2,34.5 C20.1,19.3 37.4,19.8 53.1,14.5 Z',
        'M50,15 C65,10 80,25 85,45 C90,65 75,85 55,85 C35,85 15,70 15,50 C15,30 35,20 50,15 Z',
        'M45,5 C60,20 85,30 85,55 C85,80 60,95 40,85 C20,75 10,50 20,30 C30,10 40,2 45,5 Z'
      ];

      // Küçük damlalar, tüm ekrana yayılmış (40 damla)
      const dropCount = 40;
      let remainingBlots = dropCount;

      const cols = 8;
      const rows = 5;
      for (let i = 0; i < dropCount; i++) {
        const cCol = i % cols;
        const cRow = Math.floor(i / cols);
        // Izgara hücresi içerisinde rastgele yerleşim (tüm ekrana homojen yayılım)
        const leftPercent = Math.min(94, Math.max(4, ((cCol + 0.15 + Math.random() * 0.7) / cols) * 100));
        const topPercent = Math.min(92, Math.max(6, ((cRow + 0.15 + Math.random() * 0.7) / rows) * 100));
        const sizePx = Math.floor(22 + Math.random() * 24); // 22px - 46px küçük damla boyutu
        const rot = Math.floor(Math.random() * 360);
        const pathData = blotSvgPaths[i % blotSvgPaths.length];

        const blot = document.createElement('div');
        blot.className = 'ink-splat-blot';
        blot.style.width = `${sizePx}px`;
        blot.style.height = `${sizePx}px`;
        blot.style.left = `${leftPercent.toFixed(1)}%`;
        blot.style.top = `${topPercent.toFixed(1)}%`;
        blot.style.transform = `translate(-50%, -50%) rotate(${rot}deg)`;
        blot.style.animationDelay = `${(Math.random() * 0.15).toFixed(2)}s`;

        blot.innerHTML = `
          <svg viewBox="0 0 100 100" width="100%" height="100%">
            <path d="${pathData}" fill="${color}"></path>
            <circle cx="20" cy="18" r="4" fill="${color}"></circle>
            <circle cx="85" cy="80" r="5" fill="${color}"></circle>
          </svg>
        `;

        blot.addEventListener('pointerdown', (e) => {
          e.stopPropagation();
          playReactionSound('ink_pop');
          blot.classList.add('blot-popped');
          setTimeout(() => blot.remove(), 180);
          remainingBlots--;
          if (remainingBlots <= 0) {
            clearInkOverlay();
          }
        });

        overlay.appendChild(blot);
      }

      document.body.appendChild(overlay);

      function clearInkOverlay() {
        overlay.classList.add('is-clearing');
        setTimeout(() => {
          if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
        }, 350);
      }

      setTimeout(() => {
        clearInkOverlay();
      }, 3400);
    }

    function getLeaderRival() {
      const rivals = (state.players || []).filter(p => 
        p.email !== ME_EMAIL && 
        String(p.user_id) !== String(ME_ID) && 
        p.status !== 'eliminated'
      );
      if (rivals.length === 0) return null;
      const sortedRivals = [...rivals].sort((a, b) => (Number(b.score) || 0) - (Number(a.score) || 0));
      return sortedRivals[0];
    }

    function fireMirrorSabotage() {
      if (!state.powerups.mirror_available) {
        const streak = state.powerups.mirror_streak || 0;
        showToast((STR.powerupMirrorStreakReq || 'Ayna sabotajı için 5 tur üst üste doğru cevap gerekli! ({streak}/5)').replace('{streak}', String(streak)));
        return;
      }
      if (state.eliminated) return;
      const leader = getLeaderRival();
      if (!leader) {
        showToast(STR.sabotageNoRivals || 'Sabote edilecek aktif rakip yok!');
        return;
      }
      const leaderName = leader.user_name || (leader.email ? leader.email.split('@')[0] : (STR.player || 'Lider'));
      state.powerups.mirror_available = false;
      state.powerups.mirror_streak = 0;
      updatePowerupUI();
      playReactionSound('mirror');
      showToast((STR.powerupMirrorFired || '🪞 Lider {target} hedeflendi! Ayna fırlatıldı!').replace('{target}', leaderName.split('@')[0]));

      fetch('api/rooms_powerup.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
          guid: GUID,
          type: 'mirror',
          target_user_id: leader.user_id
        })
      }).catch(e => RoomLogger.warn('Powerup', 'Mirror sabotage report error', e));
    }

    function fireFreezeSabotage() {
      if (!state.powerups.freeze_available) {
        const streak = state.powerups.freeze_streak || 0;
        showToast((STR.powerupFreezeStreakReq || 'Buz sabotajı için 5 tur üst üste doğru cevap gerekli! ({streak}/5)').replace('{streak}', String(streak)));
        return;
      }
      if (state.eliminated) return;
      const leader = getLeaderRival();
      if (!leader) {
        showToast(STR.sabotageNoRivals || 'Sabote edilecek aktif rakip yok!');
        return;
      }
      const leaderName = leader.user_name || (leader.email ? leader.email.split('@')[0] : (STR.player || 'Lider'));
      state.powerups.freeze_available = false;
      state.powerups.freeze_streak = 0;
      updatePowerupUI();
      playReactionSound('freeze');
      showToast((STR.powerupFreezeFired || '🧊 Lider {target} hedeflendi! Buz fırlatıldı!').replace('{target}', leaderName.split('@')[0]));

      fetch('api/rooms_powerup.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
          guid: GUID,
          type: 'freeze',
          target_user_id: leader.user_id
        })
      }).catch(e => RoomLogger.warn('Powerup', 'Freeze sabotage report error', e));
    }

    function fireBlackoutSabotage() {
      if (!state.powerups.blackout_available) {
        const streak = state.powerups.blackout_streak || 0;
        showToast((STR.powerupBlackoutStreakReq || 'Fener sabotajı için 5 tur üst üste doğru cevap gerekli! ({streak}/5)').replace('{streak}', String(streak)));
        return;
      }
      if (state.eliminated) return;
      const leader = getLeaderRival();
      if (!leader) {
        showToast(STR.sabotageNoRivals || 'Sabote edilecek aktif rakip yok!');
        return;
      }
      const leaderName = leader.user_name || (leader.email ? leader.email.split('@')[0] : (STR.player || 'Lider'));
      state.powerups.blackout_available = false;
      state.powerups.blackout_streak = 0;
      updatePowerupUI();
      playReactionSound('blackout');
      showToast((STR.powerupBlackoutFired || '🔦 Lider {target} hedeflendi! Fener karartması fırlatıldı!').replace('{target}', leaderName.split('@')[0]));

      fetch('api/rooms_powerup.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
          guid: GUID,
          type: 'blackout',
          target_user_id: leader.user_id
        })
      }).catch(e => RoomLogger.warn('Powerup', 'Blackout sabotage report error', e));
    }

    function activateShield() {
      if (!state.powerups.shield_available) {
        const streak = state.powerups.shield_streak || 0;
        showToast((STR.powerupShieldStreakReq || 'Prizma kalkanı için 5 tur üst üste doğru cevap gerekli! ({streak}/5)').replace('{streak}', String(streak)));
        return;
      }
      if (state.eliminated) return;
      state.powerups.shield_available = false;
      state.powerups.shield_streak = 0;
      state.powerups.has_shield = true;
      updatePowerupUI();
      playReactionSound('shield');
      showToast(STR.powerupShieldActivated || '🛡️ Prizma Kalkanı Aktif! Gelecek ilk sabotaj yansıtılacak!', true);
      showAnnouncer('🛡️ PRİZMA KALKANI AKTİF!', 'Gelecek ilk sabotaj düşmana geri yansıtılacak!', 'primary', 2600);

      fetch('api/rooms_powerup.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
          guid: GUID,
          type: 'shield'
        })
      }).catch(e => RoomLogger.warn('Powerup', 'Shield activation error', e));
    }

    let announcerTimeout = null;
    function showAnnouncer(title, sub = '', variant = 'primary', duration = 3000) {
      const el = document.getElementById('announcerBanner');
      if (!el) return;
      el.className = 'announcer-banner is-showing';
      el.innerHTML = `
        <div class="announcer-inner announcer-${variant}">
          <span class="announcer-icon">📢</span>
          <div class="d-flex flex-column text-start">
            <span class="announcer-title">${escapeHtml(title)}</span>
            ${sub ? `<span class="announcer-sub">${escapeHtml(sub)}</span>` : ''}
          </div>
        </div>
      `;
      clearTimeout(announcerTimeout);
      announcerTimeout = setTimeout(() => {
        el.classList.remove('is-showing');
      }, duration);
    }

    function triggerMirrorEffect(attackerName) {
      playReactionSound('mirror');
      if (navigator.vibrate) navigator.vibrate([120, 60, 120]);
      const stage = document.getElementById('arenaStage');
      const grid = document.getElementById('choiceGrid');
      if (stage) stage.classList.add('mirror-active');
      if (grid) grid.classList.add('mirror-active');

      const existing = document.getElementById('mirrorAlertTag');
      if (existing) existing.remove();

      const banner = document.createElement('div');
      banner.className = 'mirror-alert-tag';
      banner.id = 'mirrorAlertTag';
      banner.textContent = `🪞 ${attackerName} ekranını ters çevirdi!`;
      document.body.appendChild(banner);

      setTimeout(() => {
        if (stage) stage.classList.remove('mirror-active');
        if (grid) grid.classList.remove('mirror-active');
        const el = document.getElementById('mirrorAlertTag');
        if (el) el.remove();
      }, 3500);
    }

    function triggerFreezeEffect(attackerName) {
      playReactionSound('freeze');
      if (navigator.vibrate) navigator.vibrate([150, 70, 150]);
      const existing = document.getElementById('freezeOverlay');
      if (existing) existing.remove();

      const grid = document.getElementById('choiceGrid') || document.getElementById('arenaStage');
      if (!grid) return;

      const overlay = document.createElement('div');
      overlay.id = 'freezeOverlay';
      overlay.className = 'freeze-overlay';
      overlay.innerHTML = `
        <div class="freeze-cracks">🧊❄️</div>
        <div class="fw-bold mt-1">${escapeHtml(attackerName)} seni dondurdu!</div>
        <div class="freeze-hint" id="freezeHint">Kırmak için 2 kez dokun! (2)</div>
      `;

      let clicksLeft = 2;
      const shatter = () => {
        clicksLeft--;
        playReactionSound('freeze');
        const hint = document.getElementById('freezeHint');
        if (hint) hint.textContent = `Kırmak için dokun! (${clicksLeft})`;
        if (clicksLeft <= 0) {
          overlay.style.transition = 'opacity 0.2s, transform 0.2s';
          overlay.style.opacity = '0';
          overlay.style.transform = 'scale(1.1)';
          playReactionSound('ink_pop');
          setTimeout(() => overlay.remove(), 200);
        }
      };

      overlay.addEventListener('pointerdown', (e) => {
        e.stopPropagation();
        shatter();
      });

      grid.style.position = 'relative';
      grid.appendChild(overlay);

      setTimeout(() => {
        if (overlay.parentNode) overlay.remove();
      }, 3500);
    }

    function triggerBlackoutEffect(attackerName) {
      playReactionSound('blackout');
      if (navigator.vibrate) navigator.vibrate(200);
      const existing = document.getElementById('blackoutOverlay');
      if (existing) existing.remove();

      const overlay = document.createElement('div');
      overlay.id = 'blackoutOverlay';
      overlay.className = 'blackout-overlay';
      document.body.appendChild(overlay);

      const moveHandler = (e) => {
        const x = e.clientX ?? (e.touches && e.touches[0] ? e.touches[0].clientX : window.innerWidth / 2);
        const y = e.clientY ?? (e.touches && e.touches[0] ? e.touches[0].clientY : window.innerHeight / 2);
        overlay.style.setProperty('--mouse-x', `${x}px`);
        overlay.style.setProperty('--mouse-y', `${y}px`);
      };

      window.addEventListener('pointermove', moveHandler);
      window.addEventListener('touchmove', moveHandler);

      showToast(`🔦 ${attackerName} ekranını kararttı! Feneri hareket ettir!`);

      setTimeout(() => {
        window.removeEventListener('pointermove', moveHandler);
        window.removeEventListener('touchmove', moveHandler);
        overlay.style.opacity = '0';
        setTimeout(() => overlay.remove(), 300);
      }, 3500);
    }

    function triggerAvatarAnimation(userId, animType) {
      const cssClass = animType === 'bounce' ? 'avatar-bounce-glow' : 'avatar-dizzy';
      const el = document.getElementById(`playerAvatar-${userId}`) ||
                 document.querySelector(`[data-user-id="${userId}"] .player-pill-avatar`) ||
                 document.querySelector(`[data-user-id="${userId}"]`);
      if (el) {
        el.classList.remove('avatar-bounce-glow', 'avatar-dizzy');
        void el.offsetWidth;
        el.classList.add(cssClass);
        setTimeout(() => {
          el.classList.remove(cssClass);
        }, 900);
      }
    }

    function renderSpectatorBar() {
      const bar = document.getElementById('spectatorBar');
      const grid = document.getElementById('spectatorBetsGrid');
      if (!bar || !grid) return;

      if (!state.eliminated && state.phase !== 'leaderboard') {
        bar.classList.add('d-none');
        return;
      }

      const aliveRivals = (state.players || []).filter(p => p.status !== 'eliminated');
      if (aliveRivals.length === 0) {
        bar.classList.add('d-none');
        return;
      }

      bar.classList.remove('d-none');
      grid.innerHTML = aliveRivals.map(p => {
        const pName = getPlayerDisplayName(p);
        const av = (state.avatars || ALL_AVATARS || []).find(a => String(a.id) === String(p.avatar));
        const icon = av ? av.icon : '👤';
        const isPredicted = state.myPredictionUserId && String(state.myPredictionUserId) === String(p.user_id);
        return `
          <div class="spectator-card d-flex align-items-center justify-content-between p-2 rounded-3 my-1" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);">
            <div class="d-flex align-items-center gap-2">
              <span class="fs-5">${icon}</span>
              <span class="fw-bold">${escapeHtml(pName)}</span>
              <span class="badge bg-secondary-subtle text-light">${p.score || 0} p</span>
            </div>
            <div class="d-flex align-items-center gap-1.5">
              <button type="button" class="btn btn-sm ${isPredicted ? 'btn-success' : 'btn-outline-warning'} spectator-bet-btn" data-user-id="${p.user_id}">
                ${isPredicted ? '✓ Tahminin' : '🍿 Kazanır (+250)'}
              </button>
              <button type="button" class="btn btn-sm btn-outline-info spectator-cheer-btn" data-user-id="${p.user_id}" title="Alkışla!">
                🎈
              </button>
            </div>
          </div>
        `;
      }).join('');

      grid.querySelectorAll('.spectator-bet-btn').forEach(btn => {
        btn.onclick = () => {
          const uid = btn.dataset.userId;
          submitSpectatorBet(uid);
        };
      });
      grid.querySelectorAll('.spectator-cheer-btn').forEach(btn => {
        btn.onclick = () => {
          const uid = btn.dataset.userId;
          sendSpectatorCheer(uid, '🎈');
        };
      });
    }

    async function submitSpectatorBet(targetUserId) {
      state.myPredictionUserId = targetUserId;
      playReactionSound('pop');
      showToast('🍿 Galip tahminin kaydedildi! Tur sonunda doğruysa +250 puan!', true);
      renderSpectatorBar();
      try {
        await fetch('api/rooms_predict.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ guid: GUID, predicted_user_id: targetUserId })
        });
      } catch (err) {
        RoomLogger.warn('Spectator', 'Failed to submit bet', err);
      }
    }

    async function sendSpectatorCheer(targetUserId, emoji) {
      playReactionSound('pop');
      showToast('🎈 Tezahürat gönderildi!');
      try {
        await fetch('api/rooms_cheer.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ guid: GUID, target_user_id: targetUserId, emoji: emoji })
        });
      } catch (err) {
        RoomLogger.warn('Spectator', 'Failed to send cheer', err);
      }
    }

    function updateTeamBattleUI() {
      const hudBar = document.getElementById('hudTeamBattle');
      if (!hudBar) return;

      if (state.gameMode !== 'teams') {
        hudBar.classList.add('d-none');
        return;
      }

      const isGameActive = state.phase === 'question' || state.phase === 'show' || state.phase === 'countdown' || state.phase === 'intermission' || state.phase === 'leaderboard';
      if (!isGameActive) {
        hudBar.classList.add('d-none');
        return;
      }

      hudBar.classList.remove('d-none');

      const teams = state.teams || [
        { id: 'red', name: 'Red', color: '#ef4444' },
        { id: 'blue', name: 'Blue', color: '#3b82f6' }
      ];

      const teamScores = {};
      teams.forEach(t => { teamScores[t.id] = 0; });
      const defaultTeamId = teams[0]?.id || 'red';

      (state.players || []).forEach(p => {
        const tId = teams.some(t => t.id === p.team) ? p.team : defaultTeamId;
        const s = Number(p.score) || 0;
        teamScores[tId] = (teamScores[tId] || 0) + s;
      });

      const totalScore = Object.values(teamScores).reduce((a, b) => a + b, 0);

      let contentEl = document.getElementById('hudTeamBattleContent');
      let barEl = document.getElementById('hudTeamBattleBar');
      if (!contentEl || !barEl) {
        hudBar.innerHTML = `
          <div id="hudTeamBattleContent" class="d-flex align-items-center justify-content-between flex-wrap gap-2 w-100"></div>
          <div id="hudTeamBattleBar" class="team-battle-bar-wrap w-100 mt-1 d-flex"></div>
        `;
        contentEl = document.getElementById('hudTeamBattleContent');
        barEl = document.getElementById('hudTeamBattleBar');
      }

      contentEl.innerHTML = teams.map(t => {
        const score = teamScores[t.id] || 0;
        return `
          <div class="team-battle-label team-${t.id}-label d-inline-flex align-items-center gap-1.5" style="color: ${t.color};">
            <span class="team-dot dot-${t.id}" style="background: ${t.color}; box-shadow: 0 0 8px ${t.color}80;"></span>
            <span class="team-name fw-bold">${escapeHtml(t.name)}</span>
            <span id="hudScore_${t.id}" class="team-score fw-extrabold ms-1">${score.toLocaleString()}</span>
          </div>
        `;
      }).join('');

      barEl.innerHTML = teams.map(t => {
        const score = teamScores[t.id] || 0;
        let pct = 100 / teams.length;
        if (totalScore > 0) {
          pct = Math.max(8, Math.min(85, Math.round((score / totalScore) * 100)));
        }
        return `
          <div class="team-progress-${t.id}" style="width: ${pct}%; height: 100%; background: ${t.color}; transition: width 0.4s ease;"></div>
        `;
      }).join('');
    }

    function renderLobbyTeams() {
      const container = document.getElementById('lobbyTeamsContainer');
      if (!container) return;
      if (state.gameMode !== 'teams' || state.phase !== 'lobby') {
        container.innerHTML = '';
        return;
      }

      const teams = state.teams || [
        { id: 'red', name: 'Red', color: '#ef4444' },
        { id: 'blue', name: 'Blue', color: '#3b82f6' }
      ];

      const players = state.players || [];
      const teamRosters = {};
      teams.forEach(t => { teamRosters[t.id] = []; });
      const defaultTeamId = teams[0]?.id || 'red';

      players.forEach(p => {
        const tId = teams.some(t => t.id === p.team) ? p.team : defaultTeamId;
        teamRosters[tId].push(p);
      });

      const myTeam = state.myTeam || defaultTeamId;

      const teamBtnClassMap = {
        red: 'danger',
        blue: 'primary',
        green: 'success',
        yellow: 'warning'
      };

      container.innerHTML = `
        <div class="lobby-teams-grid">
          ${teams.map(t => {
            const members = teamRosters[t.id] || [];
            const isMine = myTeam === t.id;
            const btnTheme = teamBtnClassMap[t.id] || 'primary';
            return `
              <div class="lobby-team-card team-card-${t.id} ${isMine ? 'is-my-team' : ''}" style="${isMine ? 'border-color:' + t.color + ';' : ''}">
                <div class="team-card-header">
                  <div class="team-card-title team-${t.id}-label" style="color: ${t.color};">
                    <span class="team-dot dot-${t.id}" style="background: ${t.color}; box-shadow: 0 0 8px ${t.color}80;"></span>
                    <span>${escapeHtml(t.name)}</span>
                  </div>
                  <span class="badge rounded-pill" style="background: ${t.color}22; color: ${t.color}; border: 1px solid ${t.color}44;">
                    ${members.length} ${escapeHtml(STR.players || 'Oyuncu')}
                  </span>
                </div>
                <div class="team-roster-list">
                  ${members.length === 0 ? `<div class="text-secondary small fst-italic">${escapeHtml(STR.noMembersYet || 'Henüz kimse yok')}</div>` : members.map(m => {
                    const isMe = m.email === ME_EMAIL || String(m.user_id) === String(ME_ID);
                    const mName = m.email ? m.email.split('@')[0] : (m.nickname || STR.player);
                    const mAvObj = (state.avatars || ALL_AVATARS || []).find(a => String(a.id) === String(m.avatar));
                    return `
                      <div class="team-member-item">
                        <span class="team-dot dot-${t.id}" style="background: ${t.color}; width: 8px; height: 8px;"></span>
                        ${mAvObj ? `<span class="me-1" title="${escapeHtml(mAvObj.name)}">${mAvObj.icon}</span>` : ''}
                        <span class="text-truncate" style="max-width: 140px;">${escapeHtml(mName)}</span>
                        ${isMe ? `<span class="badge bg-${btnTheme} text-light fs-8 py-0 px-1 ms-1">${STR.you}</span>` : ''}
                      </div>
                    `;
                  }).join('')}
                </div>
                <button type="button" class="btn btn-sm ${isMine ? 'btn-' + btnTheme + ' disabled' : 'btn-outline-' + btnTheme} w-100 rounded-pill mt-2 fw-bold" onclick="joinTeam('${t.id}')" ${isMine ? 'disabled' : ''}>
                  ${isMine ? '✓ ' + (STR.yourTeam || 'Senin Takımın') : escapeHtml(t.name) + ' ' + (STR.roomsJoin || 'Takımına Katıl')}
                </button>
              </div>
            `;
          }).join('')}
        </div>
      `;
    }

    function getLobbyRulesHtml(gameMode) {
      const mode = gameMode || state.gameMode || 'elimination';
      let modeIcon = '💀';
      let modeName = STR.modeElimination || 'Eleme Modu';
      let modeDesc = STR.ruleElimDesc;

      if (mode === 'points') {
        modeIcon = '⚡';
        modeName = STR.modePoints || 'Puan Yarışı';
        modeDesc = STR.rulePointsDesc;
      } else if (mode === 'flags') {
        modeIcon = '🚩';
        modeName = STR.modeFlags || 'Bayrak Modu';
        modeDesc = STR.ruleFlagsDesc;
      } else if (mode === 'teams') {
        modeIcon = '⚔️';
        modeName = STR.modeTeams || 'Takım Savaşı';
        modeDesc = STR.ruleTeamsDesc;
      }

      return `
        <div class="lobby-rules-card" id="lobbyRulesCard">
          <div class="lobby-rules-header">
            <span>📜</span>
            <span>${escapeHtml(STR.lobbyRulesTitle || 'Oyun Kuralları & İpuçları')}</span>
          </div>
          <div class="lobby-rules-grid">
            <div class="lobby-rule-item">
              <span class="lobby-rule-icon">👁️</span>
              <div class="lobby-rule-text">
                <strong>${escapeHtml(STR.ruleGoalTitle || 'Hedefi Hafızana Al')}</strong>
                <span>${escapeHtml(STR.ruleGoalDesc)}</span>
              </div>
            </div>
            <div class="lobby-rule-item">
              <span class="lobby-rule-icon">⚡</span>
              <div class="lobby-rule-text">
                <strong>${escapeHtml(STR.ruleSpeedTitle || 'Hız ve İsabet')}</strong>
                <span>${escapeHtml(STR.ruleSpeedDesc)}</span>
              </div>
            </div>
            <div class="lobby-rule-item">
              <span class="lobby-rule-icon">🎯</span>
              <div class="lobby-rule-text">
                <strong>${escapeHtml(STR.rulePowerupsTitle || 'Jokerler & Sabotaj')}</strong>
                <span>${escapeHtml(STR.rulePowerupsDesc)}</span>
              </div>
            </div>
            <div class="lobby-rule-item mode-highlight">
              <span class="lobby-rule-icon">${modeIcon}</span>
              <div class="lobby-rule-text">
                <strong>${escapeHtml(STR.ruleModeTitle || 'Mod Kuralı')}: ${escapeHtml(modeName)}</strong>
                <span>${escapeHtml(modeDesc)}</span>
              </div>
            </div>
          </div>
        </div>
      `;
    }

    function renderLobbyRules() {
      const container = document.getElementById('lobbyRulesContainer');
      if (!container) return;
      if (state.phase !== 'lobby') {
        container.innerHTML = '';
        return;
      }
      container.innerHTML = getLobbyRulesHtml(state.gameMode);
    }

    async function joinTeam(team) {
      const teams = state.teams || [];
      if (!teams.some(t => t.id === team)) return;
      state.myTeam = team;
      const targetTeam = teams.find(t => t.id === team);
      const teamName = targetTeam ? targetTeam.name : team;

      try {
        const res = await fetch('api/rooms_team.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ guid: GUID, team: team })
        });
        const data = await res.json();
        if (data && data.ok) {
          if (Array.isArray(data.players)) {
            state.players = data.players;
            renderPlayers(data.players);
          }
          playTone(600, 0.08, 'sine');
          showToast(`✓ ${teamName} takımına katıldın!`);
        } else {
          showToast(data.error || STR.errorGeneric);
        }
      } catch (err) {
        RoomLogger.error('Teams', 'Failed to change team', err);
      }
    }
    window.joinTeam = joinTeam;

    function updateMyAvatarUI() {
      const myBtn = document.getElementById('myAvatarReactionBtn');
      const myIcon = document.getElementById('myAvatarReactionIcon');
      if (!myBtn || !myIcon) return;

      const avatars = state.avatars || ALL_AVATARS || [];
      const currentAv = avatars.find(a => String(a.id) === String(state.myAvatar)) || avatars[0];
      if (currentAv) {
        myIcon.textContent = currentAv.icon;
        myBtn.style.setProperty('--avatar-glow', currentAv.color || '#38bdf8');
        myBtn.title = `${currentAv.name} (${STR.reactionAvatarTitle || 'Avatarımı Gönder!'})`;
      }
    }

    function renderLobbyAvatars() {
      const container = document.getElementById('lobbyAvatarContainer');
      if (!container) return;
      if (state.phase !== 'lobby') {
        container.innerHTML = '';
        return;
      }

      const avatars = state.avatars || ALL_AVATARS || [];
      const players = state.players || [];
      const myUid = String(ME_ID);
      const myEmail = ME_EMAIL;

      // Find current user's avatar
      const meRow = players.find(p => p.email === myEmail || String(p.user_id) === myUid);
      const myAvatarId = meRow && meRow.avatar ? String(meRow.avatar) : state.myAvatar;

      // Map which player owns each avatar ID
      const avatarOwners = {};
      players.forEach(p => {
        if (p.avatar && p.status !== 'eliminated') {
          avatarOwners[String(p.avatar)] = p;
        }
      });

      let myAvatarObj = avatars.find(a => String(a.id) === String(myAvatarId)) || avatars[0];

      let html = `
        <div class="lobby-avatars-card" id="lobbyAvatarsCard">
          <div class="lobby-avatars-header">
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <span class="fs-4">🎭</span>
              <div>
                <div class="fw-bold fs-6 text-white">${escapeHtml(STR.lobbyAvatarTitle || 'Karakter Avatarını Seç (50 Avatar)')}</div>
                <div class="small text-secondary">${escapeHtml(STR.lobbyAvatarSubtitle || 'Her avatar tek bir oyuncuya özeldir. Seçtiğin avatar oyun içinde ve emojilerde görünür!')}</div>
              </div>
            </div>
            <div class="my-active-avatar-badge" style="background: ${myAvatarObj.bg || '#38bdf8'}; box-shadow: 0 0 16px ${myAvatarObj.color}66;">
              <span class="avatar-icon-large">${myAvatarObj.icon}</span>
              <span class="avatar-name-large">${escapeHtml(myAvatarObj.name)}</span>
            </div>
          </div>
          <div class="lobby-avatars-grid">
      `;

      avatars.forEach(av => {
        const owner = avatarOwners[String(av.id)];
        const isSelectedByMe = owner ? (owner.email === myEmail || String(owner.user_id) === myUid) : (String(av.id) === String(myAvatarId));
        const isTakenByOther = owner && !isSelectedByMe;

        let extraClass = '';
        let badgeHtml = '';
        let disabledAttr = '';

        if (isSelectedByMe) {
          extraClass = ' is-mine';
          badgeHtml = `<span class="avatar-status-badge badge-mine">✓ Sen</span>`;
        } else if (isTakenByOther) {
          extraClass = ' is-taken';
          disabledAttr = 'disabled';
          const ownerName = getPlayerDisplayName(owner);
          badgeHtml = `<span class="avatar-status-badge badge-taken" title="${escapeHtml(ownerName)}">${escapeHtml(ownerName)}</span>`;
        }

        html += `
          <button type="button" 
                  class="avatar-tile-btn${extraClass}" 
                  data-avatar-id="${av.id}"
                  ${disabledAttr}
                  style="--avatar-color: ${av.color}; --avatar-bg: ${av.bg};"
                  title="${escapeHtml(av.name)}${isTakenByOther ? ' (' + escapeHtml(getPlayerDisplayName(owner)) + ' tarafından seçildi)' : ''}">
            <span class="avatar-tile-icon">${av.icon}</span>
            <span class="avatar-tile-name">${escapeHtml(av.name)}</span>
            ${badgeHtml}
          </button>
        `;
      });

      html += `
          </div>
        </div>
      `;

      container.innerHTML = html;

      // Click handler
      container.querySelectorAll('.avatar-tile-btn:not(.is-taken)').forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          const avId = btn.getAttribute('data-avatar-id');
          if (avId) {
            chooseAvatar(avId);
          }
        });
      });
    }

    async function chooseAvatar(avatarId) {
      playReactionSound('pop');
      state.myAvatar = String(avatarId);
      updateMyAvatarUI();

      try {
        const res = await fetch('api/rooms_avatar.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({ guid: GUID, avatar: String(avatarId) })
        });
        const data = await res.json();
        if (data && data.ok) {
          if (Array.isArray(data.players)) {
            state.players = data.players;
            renderPlayers(data.players);
          }
          renderLobbyAvatars();
          updateMyAvatarUI();
          showToast('✨ Avatarın seçildi!', true);
        } else {
          showToast(data.msg || (STR.avatarTaken || 'Bu avatar başka bir oyuncu tarafından alındı!'), false);
          if (typeof initRoom === 'function') initRoom();
        }
      } catch (err) {
        RoomLogger.error('Avatar', 'Failed to update avatar', err);
      }
    }
    window.chooseAvatar = chooseAvatar;

    function renderPlayers(players) {
      if (Array.isArray(players) && players.length > 0) {
        state.players = players;
      }
      if (!playerList) return;
      playerList.innerHTML = '';
      (state.players || []).forEach(p => {
        const isMe = p.email === ME_EMAIL || String(p.user_id) === String(ME_ID);
        if (isMe && p.team) {
          state.myTeam = p.team;
        }
        if (isMe && p.avatar) {
          state.myAvatar = String(p.avatar);
          updateMyAvatarUI();
        }
        const isElim = p.status === 'eliminated';
        const isOnline = isPlayerOnline(p);

        const tag = document.createElement('div');
        tag.className = 'player-tag' + (isMe ? ' self' : '') + (isElim ? ' eliminated' : '') + (!isOnline ? ' offline' : '');
        tag.dataset.userId = String(p.user_id || '');

        const dot = document.createElement('span');
        dot.className = 'live-dot';
        if (isElim || !isOnline) {
          dot.style.background = '#64748b';
          dot.style.boxShadow = 'none';
        } else if (state.gameMode === 'teams') {
          const pTeamObj = (state.teams || []).find(t => t.id === p.team) || (state.teams || [])[0];
          const tColor = pTeamObj ? pTeamObj.color : '#f43f5e';
          dot.style.background = tColor;
          dot.style.boxShadow = `0 0 6px ${tColor}99`;
        }
        tag.appendChild(dot);

        // Player Avatar Icon Badge
        const pAvObj = (state.avatars || ALL_AVATARS || []).find(a => String(a.id) === String(p.avatar));
        if (pAvObj) {
          const avSpan = document.createElement('span');
          avSpan.id = `playerAvatar-${p.user_id}`;
          avSpan.className = 'player-pill-avatar me-1';
          avSpan.textContent = pAvObj.icon;
          avSpan.title = pAvObj.name;
          tag.appendChild(avSpan);
        }

        const nameSpan = document.createElement('span');
        let statusSuffix = '';
        if (isElim) statusSuffix = ' 💀 (' + (STR.spectating || 'İzleyici') + ')';
        else if (!isOnline) statusSuffix = ` (${STR.offline})`;
        nameSpan.textContent = getPlayerDisplayName(p) + (isMe ? ' ' + STR.you : '') + statusSuffix;
        tag.appendChild(nameSpan);

        if (state.gameMode === 'teams') {
          const pTeamObj = (state.teams || []).find(t => t.id === p.team) || (state.teams || [])[0];
          const teamPill = document.createElement('span');
          const tColor = pTeamObj ? pTeamObj.color : '#f43f5e';
          teamPill.className = 'badge ms-1';
          teamPill.style.fontSize = '10px';
          teamPill.style.backgroundColor = tColor + '22';
          teamPill.style.color = tColor;
          teamPill.style.border = '1px solid ' + tColor + '44';
          teamPill.textContent = pTeamObj ? pTeamObj.name : 'Team';
          tag.appendChild(teamPill);
        }

        if (Number(p.score) !== 0) {
          const scoreBadge = document.createElement('span');
          const isNeg = Number(p.score) < 0;
          scoreBadge.className = 'badge ' + (isNeg ? 'bg-danger-subtle text-danger' : 'bg-dark-subtle text-dark-emphasis') + ' ms-1';
          scoreBadge.textContent = p.score + ' ' + STR.pts;
          tag.appendChild(scoreBadge);
        }

        playerList.appendChild(tag);
      });

      if (state.phase === 'lobby') {
        renderLobbyTeams();
        renderLobbyAvatars();
        if (state.isHost) {
          updateStartButtonState();
        }
      }

      updateTeamBattleUI();
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
      if (state.targetShowTimer) {
        clearTimeout(state.targetShowTimer);
        state.targetShowTimer = null;
      }

      const isFlag = isFlagTarget(state.targetColor);
      const remTitle = isFlag ? STR.rememberFlag : STR.rememberColor;

      // Sunucu saatine göre senkronize countdown hesaplama
      const nowEst = Date.now() + (state.serverTimeOffsetMs || 0);
      const elapsedSinceStart = state.roundStartedAtMs ? Math.max(0, nowEst - state.roundStartedAtMs) : 0;

      // Sonraki tura geçişte (round > 1 veya countdownMs <= 1000):
      if (state.round > 1 || state.countdownMs <= 1000) {
        stageContent.innerHTML = `
          <h2 class="stage-title">${remTitle}</h2>
          <div class="fs-1 my-3">${isFlag ? '🚩' : '🎯'}</div>
          <div class="stage-subtitle">${STR.watchScreen}</div>
        `;

        // Tüm oyuncuların aynı anda hedef renge geçmesi için senkronize geçiş
        const delay = Math.max(250, 1000 - elapsedSinceStart);
        state.countdownInterval = setTimeout(() => {
          state.countdownInterval = null;
          runTargetShow();
        }, delay);
        return;
      }

      // İlk tur / Maç başlangıcı: 3-2-1 geri sayımı
      const totalCountSeconds = Math.max(1, Math.round(state.countdownMs / 1000));
      const elapsedSeconds = Math.floor(elapsedSinceStart / 1000);
      let remaining = Math.max(1, totalCountSeconds - elapsedSeconds);

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
      state.targetShowStartTs = performance.now();
      hudStatus.textContent = STR.memorize;
      hudStatus.className = 'hud-val text-warning';

      if (state.targetShowTimer) {
        clearTimeout(state.targetShowTimer);
        state.targetShowTimer = null;
      }

      const isFlag = isFlagTarget(state.targetColor);
      const remTitle = isFlag ? STR.rememberFlag : STR.rememberColor;
      const showSub = isFlag ? STR.showingTargetFlag : STR.showingTarget;

      let boxHtml = '';
      if (isFlag) {
        boxHtml = `
          <div class="target-box target-box-flag">
            <img src="${escapeHtml(state.targetColor)}" class="target-flag-img" alt="Target Flag" loading="eager" decoding="sync">
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

      // Emoji panelini hedef renk gösterim ekranında sayfanın alt tarafına sabitle
      const rBarShow = document.getElementById('reactionBar');
      if (rBarShow) rBarShow.classList.remove('d-none');

      // HEDEF RENK GÖRÜNTÜLEME SÜRESİ GARANTİSİ:
      // Minimum süre: Bayrak modunda en az 1600ms, renk modunda en az 1200ms.
      // Asla hedefin anlık flaş yapıp veya görünmeden geçilmesine izin verilmez.
      const minSafeShowMs = isFlag ? 1600 : 1200;
      const effectiveShowMs = Math.max(minSafeShowMs, Number(state.showMs) || minSafeShowMs);

      state.targetShowTimer = setTimeout(() => {
        state.targetShowTimer = null;
        runQuestion();
      }, effectiveShowMs);
    }

    // Step 3: Question & Interactive Grid Phase
    async function runQuestion() {
      if (state.targetShowTimer) {
        clearTimeout(state.targetShowTimer);
        state.targetShowTimer = null;
      }

      // Eğer hedef rengi izlerken tüm canlı oyuncular bitirdiyse ve bu oyuncu izleyici/elenmişse:
      if (state.pendingLeaderboard && state.eliminated) {
        const pb = state.pendingLeaderboard;
        state.pendingLeaderboard = null;
        renderLeaderboard(pb.players, pb.targetRound);
        return;
      }

      state.phase = 'question';
      state.answered = false;
      if (state.intermissionTimer) clearTimeout(state.intermissionTimer);

      const isFlag = isFlagTarget(state.targetColor);
      const pickTitle = isFlag ? STR.pickFlag : STR.pickColor;
      const pickUpper = isFlag ? STR.pickFlagUpper : STR.pickColorUpper;

      hudStatus.textContent = state.eliminated ? STR.spectating : pickUpper;
      hudStatus.className = 'hud-val ' + (state.eliminated ? 'text-secondary' : 'text-success');

      if (state.gameMode === 'alchemy') {
        const recipeName = state.targetName || 'Karışım';
        stageContent.innerHTML = `
          <h2 class="stage-title">🧪 ${escapeHtml(recipeName)} Rengini Oluştur!</h2>
          <div class="stage-subtitle">Hedef: <strong style="color: ${state.targetColor}">${escapeHtml(recipeName)}</strong> — Karıştırmak için 2 renk seç!</div>
          <div class="alchemy-blend-preview my-2">
            <span>Bileşen 1: <span id="alchemySlot1" class="alchemy-slot"></span></span>
            <span class="mx-1">+</span>
            <span>Bileşen 2: <span id="alchemySlot2" class="alchemy-slot"></span></span>
          </div>
          <div class="choice-grid" id="choiceGrid"></div>
        `;
        state.alchemyPick1 = null;
        state.alchemyPick2 = null;
      } else if (state.gameMode === 'flash_memory') {
        stageContent.innerHTML = `
          <h2 class="stage-title">⚡ Flaş Rengi Hatırla!</h2>
          <div class="stage-subtitle">${state.eliminated ? STR.eliminatedSubtitle : '❓ Gördüğün rengi hemen işaretle!'}</div>
          <div class="choice-grid" id="choiceGrid"></div>
        `;
      } else {
        stageContent.innerHTML = `
          <h2 class="stage-title">${pickTitle}</h2>
          <div class="stage-subtitle">${state.eliminated ? STR.eliminatedSubtitle : STR.chooseFast}</div>
          <div class="choice-grid ${isFlag ? 'grid-loading' : ''}" id="choiceGrid"></div>
        `;
      }

      // Emoji panelini renk seçim ekranında da sayfanın alt tarafına sabitle
      const rBarQuest = document.getElementById('reactionBar');
      if (rBarQuest) rBarQuest.classList.remove('d-none');

      // Bayrak modunda: Hedef bayrak önceden belleğe alındığı için seçeneklerdeki diğer bayraklardan
      // önce yüklenip kendini ele vermemesi adına tüm bayrak dosyalarının indiğinden emin ol
      if (isFlag && state.gridPreloadPromise) {
        await Promise.race([
          state.gridPreloadPromise,
          new Promise(r => setTimeout(r, 350))
        ]);
      }

      state.questionStartTs = performance.now();

      const gridEl = document.getElementById('choiceGrid');
      if (!gridEl) return;
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
          btn.innerHTML = `<img src="${escapeHtml(item)}" class="choice-flag-img" alt="Flag" loading="eager" decoding="sync">`;
        } else {
          btn.style.background = item;
        }
        if (cols >= 5) {
          btn.style.borderRadius = '10px';
        } else if (cols >= 4) {
          btn.style.borderRadius = '14px';
        }
        btn.dataset.item = item;
        btn.disabled = state.eliminated;
        btn.onclick = () => onChoicePick(item, btn, cells);
        gridEl.appendChild(btn);
        cells.push(btn);
      });

      updatePowerupUI();

      if (isFlag) {
        // Tüm bayraklar hazır, ızgarayı aynı anda pürüzsüzce görünür yap
        requestAnimationFrame(() => {
          gridEl.classList.remove('grid-loading');
          gridEl.classList.add('grid-ready');
        });
      }

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

      if (state.gameMode === 'alchemy') {
        if (!state.alchemyPick1) {
          state.alchemyPick1 = color;
          btn.classList.add('border-4', 'border-warning');
          btn.disabled = true;
          const slot1 = document.getElementById('alchemySlot1');
          if (slot1) {
            slot1.style.background = color;
            slot1.classList.add('is-filled');
          }
          playTone(523, 0.1, 'triangle');
          return;
        } else {
          state.alchemyPick2 = color;
          btn.classList.add('border-4', 'border-warning');
          const slot2 = document.getElementById('alchemySlot2');
          if (slot2) {
            slot2.style.background = color;
            slot2.classList.add('is-filled');
          }
          color = state.alchemyPick1 + '+' + state.alchemyPick2;
        }
      }

      state.answered = true;
      cells.forEach(c => c.disabled = true);
      if (state.activeTimer) clearInterval(state.activeTimer);

      let isCorrect = false;
      if (state.gameMode === 'alchemy') {
        const comps = state.components || [];
        const pickedParts = color.split('+');
        if (pickedParts.length === 2 && comps.length === 2) {
          isCorrect = (
            (pickedParts[0] === comps[0] && pickedParts[1] === comps[1]) ||
            (pickedParts[0] === comps[1] && pickedParts[1] === comps[0])
          );
        } else {
          isCorrect = color === state.targetColor;
        }
      } else {
        isCorrect = color === state.targetColor;
      }
      const responseMs = Math.round(performance.now() - state.questionStartTs);

      if (isCorrect) {
        state.streak = (state.streak || 0) + 1;
        if (state.streak > (state.maxStreak || 0)) state.maxStreak = state.streak;
        btn.classList.add('correct');
        triggerAvatarAnimation(ME_ID, 'bounce');

        const pitchMult = Math.min(5, state.streak);
        playTone(660 + pitchMult * 40, 0.1, 'triangle');
        setTimeout(() => playTone(880 + pitchMult * 50, 0.12, 'triangle'), 100);

        updateStreakUI(state.streak);

        if (state.streak >= 10) {
          document.body.classList.remove('combo-border-3', 'combo-border-5');
          document.body.classList.add('combo-border-10', 'screen-shake-heavy');
          setTimeout(() => document.body.classList.remove('screen-shake-heavy'), 450);
          playReactionSound('combo_5');
          showAnnouncer('⚡ 10X EFSANEVİ KOMBO!', 'Durdurulamaz bir seridesin!', 'purple', 3500);
        } else if (state.streak >= 5) {
          document.body.classList.remove('combo-border-3');
          document.body.classList.add('combo-border-5', 'screen-shake-light');
          setTimeout(() => document.body.classList.remove('screen-shake-light'), 350);
          playReactionSound('combo_5');
          showAnnouncer('🔥 5X ALEV SERİSİ!', 'Ortalığı yakıyorsun!', 'warning', 2500);
        } else if (state.streak >= 3) {
          document.body.classList.add('combo-border-3');
          playReactionSound('combo_3');
          document.getElementById('arenaStage')?.classList.add('stage-on-fire');
        }

        // Power-ups: 5 consecutive correct answers unlock/renew cards
        const pKeys = [
          { key: 'fifty_fifty', name: '🎯 50/50' },
          { key: 'ink', name: '🦑 Mürekkep' },
          { key: 'mirror', name: '🪞 Ayna' },
          { key: 'freeze', name: '🧊 Buz' },
          { key: 'blackout', name: '🔦 Fener' },
          { key: 'shield', name: '🛡️ Kalkan' },
        ];
        const newlyUnlocked = [];
        pKeys.forEach(p => {
          if (!state.powerups[p.key + '_available']) {
            state.powerups[p.key + '_streak'] = (state.powerups[p.key + '_streak'] || 0) + 1;
            if (state.powerups[p.key + '_streak'] >= 5) {
              state.powerups[p.key + '_available'] = true;
              state.powerups[p.key + '_streak'] = 5;
              newlyUnlocked.push(p.name);
            }
          }
        });
        updatePowerupUI();
        if (newlyUnlocked.length > 0) {
          setTimeout(() => {
            playTone(880, 0.25, 'triangle');
            showToast('🎉 5 tur doğru! ' + newlyUnlocked.join(', ') + ' Kazandın!', true);
          }, 350);
        }

        // Hot potato bomb pass on correct
        if (state.gameMode === 'hot_potato' && String(state.bombHolderId) === String(ME_ID)) {
          const aliveRivals = (state.players || []).filter(p => String(p.user_id) !== String(ME_ID) && p.status !== 'eliminated');
          if (aliveRivals.length > 0) {
            const nextHolder = aliveRivals[Math.floor(Math.random() * aliveRivals.length)];
            state.bombHolderId = nextHolder.user_id;
            const hpText = document.getElementById('hotPotatoText');
            const hpBanner = document.getElementById('hotPotatoBanner');
            if (hpBanner) hpBanner.classList.remove('is-mine');
            if (hpText) hpText.textContent = `💣 Bomba: ${getPlayerDisplayName(nextHolder)} oyuncusuna paslandı!`;
            showToast(`💣 Bombayı ${getPlayerDisplayName(nextHolder)} oyuncusuna pasladın!`, true);
            playReactionSound('bomb_tick');
          }
        }

        let toastMsg = STR.correct;
        if (state.streak >= 2) {
          toastMsg += ` 🔥 ${state.streak}x ${STR.streakCombo || 'Kombo!'}`;
        }
        showToast(toastMsg, true);
      } else {
        btn.classList.add('wrong');
        playTone(220, 0.2, 'sawtooth');
        triggerAvatarAnimation(ME_ID, 'dizzy');

        // Streak broken: reset locked powerups & combo borders
        document.body.classList.remove('combo-border-3', 'combo-border-5', 'combo-border-10');
        document.body.classList.add('screen-shake-heavy');
        setTimeout(() => document.body.classList.remove('screen-shake-heavy'), 450);

        ['fifty_fifty', 'ink', 'mirror', 'freeze', 'blackout', 'shield'].forEach(k => {
          if (!state.powerups[k + '_available']) {
            state.powerups[k + '_streak'] = 0;
          }
        });
        updatePowerupUI();

        if (state.streak >= 2) {
          showToast(`${STR.streakBroken || 'Kombo Kırıldı!'} (${state.streak}x) 💔`, false);
        }

        state.streak = 0;
        updateStreakUI(0);
        document.getElementById('arenaStage')?.classList.remove('stage-on-fire');

        if (state.gameMode === 'hot_potato' && String(state.bombHolderId) === String(ME_ID)) {
          playReactionSound('bomb_boom');
          showAnnouncer('💥 BOMBA SENDE PATLADI!', '-500 Ceza Puanı!', 'warning', 3500);
          showToast('💥 BOMBA SENDE PATLADI!', false);
        } else if (state.gameMode === 'elimination') {
          showToast(STR.wrong, false);
          state.eliminated = false;
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
            let ptsMsg = `+${data.score_delta} ${STR.score || 'pts'}`;
            if (data.streak_bonus > 0) {
              ptsMsg += ` (+${data.streak_bonus} 🔥)`;
            }
            showToast(ptsMsg, true);
          }
        }

        if (data.ok && data.finished && state.phase !== 'finished') {
          RoomLogger.info('GameState', 'Game finished after answer');
          renderFinalVictory(data.players || [], data.awards || null, data.team_summary || null);
        } else if (data.ok && (data.round_ended || data.all_answered)) {
          RoomLogger.info('GameState', 'All players answered! Immediately transitioning to leaderboard');
          renderLeaderboard(data.players || []);
        } else if (state.pendingLeaderboard) {
          const pb = state.pendingLeaderboard;
          state.pendingLeaderboard = null;
          renderLeaderboard(pb.players, pb.targetRound);
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

      triggerAvatarAnimation(ME_ID, 'dizzy');
      document.body.classList.remove('combo-border-3', 'combo-border-5', 'combo-border-10');
      document.body.classList.add('screen-shake-heavy');
      setTimeout(() => document.body.classList.remove('screen-shake-heavy'), 450);

      if (state.streak >= 2) {
        showToast(`${STR.streakBroken || 'Kombo Kırıldı!'} (${state.streak}x) 💔`, false);
      }
      state.streak = 0;
      updateStreakUI(0);
      document.getElementById('arenaStage')?.classList.remove('stage-on-fire');

      // Streak broken: reset locked powerups progress
      ['fifty_fifty', 'ink', 'mirror', 'freeze', 'blackout', 'shield'].forEach(k => {
        if (!state.powerups[k + '_available']) {
          state.powerups[k + '_streak'] = 0;
        }
      });
      updatePowerupUI();

      if (state.gameMode === 'hot_potato' && String(state.bombHolderId) === String(ME_ID)) {
        playReactionSound('bomb_boom');
        showAnnouncer('💥 BOMBA SENDE PATLADI!', 'Süre doldu, patlama yaşandı!', 'warning', 3500);
        showToast('💥 BOMBA SENDE PATLADI!', false);
      }

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
        state.eliminated = false;
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
          renderFinalVictory(data.players || [], data.awards || null, data.team_summary || null);
        } else if (data && data.ok && (data.round_ended || data.all_answered)) {
          renderLeaderboard(data.players || []);
        } else if (state.pendingLeaderboard) {
          const pb = state.pendingLeaderboard;
          state.pendingLeaderboard = null;
          renderLeaderboard(pb.players, pb.targetRound);
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

      // HEDEF RENK GÖRÜNTÜLEME KORUMA KALKANI:
      // Eğer kullanıcı şu anda hedef rengi görüyorsa (phase === 'show') veya geri sayım aşamasındaysa (phase === 'countdown'),
      // hedef rengin gösterim süresi bitene kadar leaderboard ile araya GİRİLMEZ!
      // Erken gelen sonuç verisi kuyruğa alınır ve hedef gösterimi bittikten sonra devreye girer.
      if (state.phase === 'countdown' || state.phase === 'show') {
        RoomLogger.info('GameState', 'Holding leaderboard display until target color exposure finishes', { phase: state.phase, round: r });
        state.pendingLeaderboard = { players, targetRound: r };
        return;
      }

      if (state.phase === 'intermission' && state.leaderboardRound === r) {
        if (Array.isArray(players) && players.length > 0) {
          state.players = players;
          renderPlayers(players);
        }
        return;
      }
      state.pendingLeaderboard = null;
      state.leaderboardRound = r;
      state.phase = 'intermission';
      if (state.countdownInterval) { clearInterval(state.countdownInterval); clearTimeout(state.countdownInterval); state.countdownInterval = null; }
      if (state.targetShowTimer) { clearTimeout(state.targetShowTimer); state.targetShowTimer = null; }
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
                  <td>${escapeHtml(getPlayerDisplayName(p))}</td>
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

      if (data.server_now_ms) {
        state.serverTimeOffsetMs = Number(data.server_now_ms) - Date.now();
      }
      state.roundStartedAtMs = Number(data.started_at_ms || data.server_now_ms || Date.now());
      state.pendingLeaderboard = null;

      if (state.intermissionTimer) clearTimeout(state.intermissionTimer);
      if (state.countdownInterval) {
        clearInterval(state.countdownInterval);
        clearTimeout(state.countdownInterval);
        state.countdownInterval = null;
      }
      if (state.targetShowTimer) {
        clearTimeout(state.targetShowTimer);
        state.targetShowTimer = null;
      }
      if (state.activeTimer) clearInterval(state.activeTimer);

      state.phase = 'countdown';
      state.round = data.round;
      if (data.game_mode) state.gameMode = data.game_mode;
      if (data.rounds_total) state.roundsTotal = Number(data.rounds_total);
      hudRound.textContent = `${data.round} / ${state.roundsTotal || 25}`;
      state.targetColor = data.question?.target;
      state.gridColors = data.question?.grid || [];

      // Minimum güvenli hedef görüntüleme süresi garantisi (flash_memory: 400ms, bayrak: 1600ms, renk: 1200ms)
      if (state.gameMode === 'flash_memory') {
        state.showMs = Number(data.show_ms) || 400;
      } else {
        const minShow = (state.gameMode === 'flags' || isFlagTarget(state.targetColor)) ? 1600 : 1200;
        state.showMs = Math.max(minShow, Number(data.show_ms) || minShow);
      }
      if (data.question?.target_name) {
        state.targetName = data.question.target_name;
      }
      if (data.question?.components) {
        state.components = data.question.components;
      }
      state.answerMs = Number(data.answer_ms) || 5000;
      state.countdownMs = Number(data.countdown_ms) || (state.round > 1 ? 1000 : 3000);
      state.answered = false;

      // Tur başlamadan önce bu turda gösterilecek tüm bayrak dosyalarını (hedef ve tüm seçenekler) hemen indir
      if (state.gameMode === 'flags' || isFlagTarget(state.targetColor)) {
        const roundFlags = Array.from(new Set([
          ...(Array.isArray(state.gridColors) ? state.gridColors : []),
          state.targetColor
        ])).filter(src => isFlagTarget(src));
        state.gridPreloadPromise = preloadFlagImages(roundFlags);
        RoomLogger.info('Flags', `Preloading ${roundFlags.length} flags before round ${state.round}`);
      } else {
        state.gridPreloadPromise = Promise.resolve();
      }

      // Hot potato bomb setup
      if (state.gameMode === 'hot_potato') {
        const hpBanner = document.getElementById('hotPotatoBanner');
        if (hpBanner) {
          hpBanner.classList.remove('d-none');
          if (!state.bombHolderId) {
            const active = (state.players || []).filter(p => p.status !== 'eliminated');
            if (active.length > 0) state.bombHolderId = active[Math.floor(Math.random() * active.length)].user_id;
          }
          const isMine = String(state.bombHolderId) === String(ME_ID);
          hpBanner.classList.toggle('is-mine', isMine);
          const holderObj = (state.players || []).find(p => String(p.user_id) === String(state.bombHolderId));
          const holderName = holderObj ? getPlayerDisplayName(holderObj) : 'Lider';
          const hpText = document.getElementById('hotPotatoText');
          if (hpText) hpText.textContent = isMine ? '💣 BOMBA SENDE! Çabuk doğru rengi seç ve pasla!' : `💣 Bomba: ${holderName} oyuncusunda!`;
        }
      } else {
        document.getElementById('hotPotatoBanner')?.classList.add('d-none');
      }

      if (data.round === 1 || data.is_restart) {
        state.score = 0;
        state.myRoundStats = [];
        ['fifty_fifty', 'ink', 'mirror', 'freeze', 'blackout', 'shield'].forEach(k => {
          state.powerups[k + '_available'] = false;
          state.powerups[k + '_streak'] = 0;
        });
        state.powerups.has_shield = false;
        state.bombHolderId = null;
        document.body.classList.remove('combo-border-3', 'combo-border-5', 'combo-border-10');
        hudScore.textContent = '0';
      }
      updatePowerupUI();
      renderSpectatorBar();

      if (Array.isArray(data.players) && data.players.length > 0) {
        renderPlayers(data.players);
        const meRow = data.players.find(p => p.email === ME_EMAIL || String(p.user_id) === String(ME_ID));
        if (meRow) {
          state.eliminated = Boolean(meRow.status === 'eliminated');
          state.score = Number(meRow.score) || 0;
          hudScore.textContent = String(state.score);
        }
      }

      runCountdown();
    }

    async function renderFinalVictory(players, awards = null, teamSummary = null) {
      state.phase = 'finished';
      if (teamSummary) {
        state.teamSummary = teamSummary;
      }
      const hudTeamBattle = document.getElementById('hudTeamBattle');
      if (hudTeamBattle) hudTeamBattle.classList.add('d-none');
      updatePowerupUI();
      if (state.countdownInterval) { clearInterval(state.countdownInterval); clearTimeout(state.countdownInterval); state.countdownInterval = null; }
      if (state.activeTimer) clearInterval(state.activeTimer);
      if (state.intermissionTimer) clearTimeout(state.intermissionTimer);
      hudTimer.textContent = '-';
      hudStatus.textContent = STR.finished;
      hudStatus.className = 'hud-val text-success';

      RoomLogger.info('GameState', 'Rendering Final Victory screen', { awards_count: (awards || []).length, has_team_summary: Boolean(teamSummary || state.teamSummary) });

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

      function renderAwardsHtml(awardList) {
        if (!Array.isArray(awardList) || awardList.length === 0) return '';
        return `
          <div class="victory-awards-section">
            <div class="awards-title">🏅 ${STR.awardsTitle || "Maçın En'leri & Takım Rozetleri"}</div>
            <div class="awards-grid">
              ${awardList.map(a => {
                const isMe = a.email === ME_EMAIL || String(a.user_id) === String(ME_ID);
                const winnerName = getPlayerDisplayName(a);
                const aAvObj = (state.avatars || ALL_AVATARS || []).find(av => String(av.id) === String(a.avatar));
                return `
                  <div class="award-card ${isMe ? 'is-me' : ''}">
                    <div class="award-icon">${a.icon}</div>
                    <div class="award-content">
                      <div class="award-name">${escapeHtml(a.title)}</div>
                      <div class="award-winner">
                        ${aAvObj ? `<span class="me-1" title="${escapeHtml(aAvObj.name)}">${aAvObj.icon}</span>` : ''}
                        <span>${escapeHtml(winnerName)}</span>
                        ${isMe ? `<span class="badge bg-warning text-dark fs-7 ms-1 py-0 px-1">${STR.you}</span>` : ''}
                      </div>
                      <div class="award-stat">${escapeHtml(a.stat)}</div>
                      <div class="award-desc">${escapeHtml(a.desc)}</div>
                    </div>
                  </div>
                `;
              }).join('')}
            </div>
          </div>
        `;
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
        const name = getPlayerDisplayName(player);
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

      let teamVictoryHtml = '';
      if (state.gameMode === 'teams') {
        const teams = state.teams || [
          { id: 'red', name: 'Red', color: '#ef4444' },
          { id: 'blue', name: 'Blue', color: '#3b82f6' }
        ];
        const teamScores = {};
        teams.forEach(t => { teamScores[t.id] = 0; });
        const defaultTeamId = teams[0]?.id || 'red';

        (list || []).forEach(p => {
          const s = Number(p.score) || 0;
          const tId = teams.some(t => t.id === p.team) ? p.team : defaultTeamId;
          teamScores[tId] = (teamScores[tId] || 0) + s;
        });

        const summary = teamSummary || state.teamSummary;
        if (summary && summary.scores) {
          teams.forEach(t => {
            if (typeof summary.scores[t.id] === 'number') {
              teamScores[t.id] = summary.scores[t.id];
            }
          });
        }

        let winningTeam = summary?.winning_team;
        if (!winningTeam) {
          let maxS = -1;
          let isTie = false;
          teams.forEach(t => {
            const sc = teamScores[t.id] || 0;
            if (sc > maxS) {
              maxS = sc;
              winningTeam = t.id;
              isTie = false;
            } else if (sc === maxS && maxS >= 0) {
              isTie = true;
            }
          });
          if (isTie) winningTeam = 'tie';
        }

        let bannerClass = 'winner-' + winningTeam;
        let bannerTitle = '';
        if (winningTeam === 'tie') {
          bannerClass = '';
          bannerTitle = STR.teamTie || '🤝 DOSTLUK KAZANDI! (BERABERE)';
        } else {
          const winningObj = teams.find(t => t.id === winningTeam);
          const winName = winningObj ? winningObj.name : winningTeam.toUpperCase();
          bannerTitle = `🏆 ${escapeHtml(winName).toUpperCase()} KAZANDI!`;
        }

        teamVictoryHtml = `
          <div class="team-victory-banner ${bannerClass}">
            <div class="team-victory-title">${bannerTitle}</div>
            <div class="small opacity-75">${STR.teamVictoryDesc || 'Takımlar kıyasıya yarıştı! İşte nihai takım skorları:'}</div>
            <div class="team-victory-scores flex-wrap justify-content-center gap-3 mt-2">
              ${teams.map((t, idx) => {
                const sc = (teamScores[t.id] || 0).toLocaleString();
                return `
                  <span class="team-${t.id}-label d-inline-flex align-items-center gap-1" style="color: ${t.color};">
                    <span class="team-dot dot-${t.id}" style="background: ${t.color}; box-shadow: 0 0 8px ${t.color}80;"></span>
                    <strong>${escapeHtml(t.name)}:</strong> ${sc} ${STR.pts}
                  </span>
                  ${idx < teams.length - 1 ? '<span class="text-secondary opacity-50">⚡</span>' : ''}
                `;
              }).join('')}
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

          ${teamVictoryHtml}

          <div class="podium-wrapper">
            <div class="podium-stage">
              ${renderPodiumCol(p2, 2, 'podium-col-2')}
              ${renderPodiumCol(p1, 1, 'podium-col-1')}
              ${renderPodiumCol(p3, 3, 'podium-col-3')}
            </div>
          </div>

          <div id="victoryAwardsContainer">
            ${renderAwardsHtml(awards)}
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
                    const pName = getPlayerDisplayName(p);
                    const pScore = Number(p.score) || 0;
                    const pCorrect = typeof p.correct_count !== 'undefined' ? Number(p.correct_count) : '-';
                    const pAvObj = (state.avatars || ALL_AVATARS || []).find(a => String(a.id) === String(p.avatar));
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
                            ${pAvObj ? `<span class="player-cell-avatar me-1" title="${escapeHtml(pAvObj.name)}">${pAvObj.icon}</span>` : ''}
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
      state.userExplicitlyLeft = false;
      state.joinedAsSpectator = false;
      state.answered = false;
      state.targetColor = null;
      state.gridColors = [];
      state.gridPreloadPromise = null;
      state.myRoundStats = [];
      state.powerups.fifty_fifty_available = false;
      state.powerups.fifty_fifty_streak = 0;
      state.powerups.ink_available = false;
      state.powerups.ink_streak = 0;
      updatePowerupUI();

      hudRound.textContent = '-';
      hudScore.textContent = '0';
      hudTimer.textContent = '-';
      hudStatus.textContent = STR.waiting;
      hudStatus.className = 'hud-val text-info';

      RoomLogger.info('GameState', 'Resetting room to lobby', { players_count: (players || []).length });

      const isPriv = <?= json_encode(!empty($room['is_private'])) ?>;
      let modeBadge = '';
      if (state.gameMode === 'teams') {
        modeBadge = `<span class="badge bg-danger-subtle text-danger fs-6 text-nowrap align-middle border border-danger-subtle ms-1">⚔️ ${STR.modeTeams || 'Takım Savaşı'}</span>`;
      } else if (state.gameMode === 'flags') {
        modeBadge = `<span class="badge bg-info-subtle text-info fs-6 text-nowrap align-middle border border-info-subtle ms-1">🚩 ${STR.modeFlags || 'Bayrak Modu'}</span>`;
      } else if (state.gameMode === 'points') {
        modeBadge = `<span class="badge bg-warning-subtle text-warning fs-6 text-nowrap align-middle border border-warning-subtle ms-1">⚡ ${STR.modePoints}</span>`;
      } else {
        modeBadge = `<span class="badge bg-danger-subtle text-danger fs-6 text-nowrap align-middle border border-danger-subtle ms-1">💀 ${STR.modeElimination}</span>`;
      }

      stageContent.innerHTML = `
        <div class="stage-center">
          <div class="fs-1">⏳</div>
          <h1 class="stage-title">
            <span><?= htmlspecialchars($room['name'] ?: tt('room_default_name', 'Match Room')) ?></span>
            ${modeBadge}
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
          <div id="lobbyTeamsContainer"></div>
          <div id="lobbyRulesContainer">${getLobbyRulesHtml(state.gameMode)}</div>
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
        if (state.gameMode === 'flags') backgroundPreloadAllFlags();
        if (room.rounds_total) state.roundsTotal = Number(room.rounds_total);
        state.isHost = (room.owner_id && ME_ID) ? (String(room.owner_id) === String(ME_ID)) : (String(room.owner_email || '').toLowerCase() === String(ME_EMAIL || '').toLowerCase());
        if (Array.isArray(data.avatars) && data.avatars.length > 0) {
          state.avatars = data.avatars;
        }
        renderPlayers(data.players || []);
        updateMyAvatarUI();

        const meRowInit = (data.players || []).find(p => p.email === ME_EMAIL || String(p.user_id) === String(ME_ID));
        if (meRowInit && meRowInit.status === 'eliminated' && room.status === 'active') {
          state.joinedAsSpectator = true;
          state.eliminated = true;
        } else {
          state.eliminated = false;
        }

        if (room.status === 'finished') {
          renderFinalVictory(data.players || []);
        } else if (room.status === 'waiting') {
          if (state.isHost) {
            hostControls?.classList.remove('d-none');
            updateStartButtonState();
          }
          hudStatus.textContent = STR.waiting;
          hudStatus.className = 'hud-val text-info';
          renderLobbyRules();
          renderLobbyAvatars();
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
              if (data && data.ok) {
                if (data.room && Array.isArray(data.room.teams) && data.room.teams.length > 0) {
                  state.teams = data.room.teams;
                }
                if (Array.isArray(data.players)) {
                  renderPlayers(data.players);
                }
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
        if (data.teams && Array.isArray(data.teams) && data.teams.length > 0) {
          state.teams = data.teams;
        }
        renderPlayers(data.players || []);
        const meRow = (data.players || []).find(p => p.email === ME_EMAIL || String(p.user_id) === String(ME_ID));
        if (meRow && meRow.status === 'eliminated' && (state.userExplicitlyLeft || state.joinedAsSpectator)) {
          state.eliminated = true;
          if (hudStatus) {
            hudStatus.textContent = STR.spectating;
            hudStatus.className = 'hud-val text-secondary';
          }
          document.querySelectorAll('.arena-btn').forEach(b => b.disabled = true);
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
        if (data.team_summary && Array.isArray(data.team_summary.teams) && data.team_summary.teams.length > 0) {
          state.teams = data.team_summary.teams;
        }
        renderFinalVictory(data.players || [], data.awards || null, data.team_summary || null);
      });

      channel.bind('room:reaction', (data) => {
        RoomLogger.info('Pusher', 'Event room:reaction received', data);
        if (data && (String(data.user_id) !== String(ME_ID) && data.email !== ME_EMAIL)) {
          spawnFloatingReaction(data.emoji || '🔥', data.user_name || '', data.sound || 'pop', data.text || null, false);
        }
      });

      channel.bind('room:powerup', (data) => {
        RoomLogger.info('Pusher', 'Event room:powerup received', data);
        if (!data || !data.type) return;

        const isMeVictim = (String(data.target_user_id) === String(ME_ID) || data.target_email === ME_EMAIL);
        const isMeAttacker = (String(data.from_user_id) === String(ME_ID) || data.from_email === ME_EMAIL);

        if (data.reflected) {
          playReactionSound('reflect');
          showAnnouncer('🛡️ KALKAN YANSITTI!', (data.absorbed_by_name || 'Rakip') + ' saldırıyı ' + (data.from_name || 'saldırgana') + ' geri tepti!', 'warning', 3500);
        }

        if (data.type === 'ink_splat') {
          if (isMeVictim) {
            triggerInkSplatEffect(data.from_name || (STR.player || 'Player'), data.color);
          } else {
            const victimName = data.target_name ? data.target_name.split('@')[0] : (STR.player || 'Lider');
            const attackerName = data.from_name ? data.from_name.split('@')[0] : (STR.player || 'Player');
            showToast((STR.powerupInkBrdcst || '🦑 {attacker}, lider {victim} oyuncusuna mürekkep fırlattı!').replace('{attacker}', attackerName).replace('{victim}', victimName));
            playReactionSound('ink_splat');
          }
        } else if (data.type === 'mirror') {
          if (isMeVictim) {
            triggerMirrorEffect(data.from_name || (STR.player || 'Player'));
          } else {
            const victimName = data.target_name ? data.target_name.split('@')[0] : (STR.player || 'Lider');
            const attackerName = data.from_name ? data.from_name.split('@')[0] : (STR.player || 'Player');
            showToast(`🪞 ${attackerName}, ${victimName} ekranını ters çevirdi!`);
            playReactionSound('mirror');
          }
        } else if (data.type === 'freeze') {
          if (isMeVictim) {
            triggerFreezeEffect(data.from_name || (STR.player || 'Player'));
          } else {
            const victimName = data.target_name ? data.target_name.split('@')[0] : (STR.player || 'Lider');
            const attackerName = data.from_name ? data.from_name.split('@')[0] : (STR.player || 'Player');
            showToast(`🧊 ${attackerName}, ${victimName} şıklarını dondurdu!`);
            playReactionSound('freeze');
          }
        } else if (data.type === 'blackout') {
          if (isMeVictim) {
            triggerBlackoutEffect(data.from_name || (STR.player || 'Player'));
          } else {
            const victimName = data.target_name ? data.target_name.split('@')[0] : (STR.player || 'Lider');
            const attackerName = data.from_name ? data.from_name.split('@')[0] : (STR.player || 'Player');
            showToast(`🔦 ${attackerName}, ${victimName} ekranını kararttı!`);
            playReactionSound('blackout');
          }
        } else if (data.type === 'shield') {
          if (!isMeAttacker) {
            const userName = data.from_name ? data.from_name.split('@')[0] : (STR.player || 'Player');
            showToast(`🛡️ ${userName} Prizma Kalkanı kuşandı!`);
          }
        } else if (data.type === 'fifty_fifty') {
          if (!isMeAttacker) {
            const userName = data.from_name ? data.from_name.split('@')[0] : (STR.player || 'Player');
            showToast((STR.powerup5050Brdcst || '🎯 {user} 50/50 jokerini kullandı!').replace('{user}', userName));
          }
        }
      });

      channel.bind('room:cheer', (data) => {
        if (!data) return;
        if (String(data.target_user_id) === String(ME_ID)) {
          spawnFloatingReaction(data.emoji || '🎈', data.from_name || 'İzleyici', 'pop', 'Tezahürat!', false);
          playReactionSound('pop');
          showToast(`🎈 ${data.from_name} sana tezahürat gönderdi!`, true);
        }
      });

      channel.bind('room:prediction', (data) => {
        if (!data) return;
        if (String(data.predicted_user_id) === String(ME_ID)) {
          showToast(`🍿 ${data.spectator_name} senin kazanacağını tahmin etti!`, true);
        }
      });
    }

    // Power-up & Sabotage Bar Button Listeners
    document.getElementById('powerup5050Btn')?.addEventListener('click', (e) => {
      e.preventDefault();
      useFiftyFifty();
    });

    document.getElementById('powerupInkBtn')?.addEventListener('click', (e) => {
      e.preventDefault();
      fireInkSabotage();
    });

    document.getElementById('powerupMirrorBtn')?.addEventListener('click', (e) => {
      e.preventDefault();
      fireMirrorSabotage();
    });

    document.getElementById('powerupFreezeBtn')?.addEventListener('click', (e) => {
      e.preventDefault();
      fireFreezeSabotage();
    });

    document.getElementById('powerupBlackoutBtn')?.addEventListener('click', (e) => {
      e.preventDefault();
      fireBlackoutSabotage();
    });

    document.getElementById('powerupShieldBtn')?.addEventListener('click', (e) => {
      e.preventDefault();
      activateShield();
    });

    document.getElementById('closeSabotageModalBtn')?.addEventListener('click', (e) => {
      e.preventDefault();
      const modal = document.getElementById('sabotageModal');
      if (modal) modal.classList.add('d-none');
    });

    // Reaction UI Controls & Click Handlers
    updateReactionSoundUI();

    // User's custom chosen Avatar reaction sender
    document.getElementById('myAvatarReactionBtn')?.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      const avatars = state.avatars || ALL_AVATARS || [];
      const currentAv = avatars.find(a => String(a.id) === String(state.myAvatar)) || avatars[0];
      if (currentAv) {
        const btn = document.getElementById('myAvatarReactionBtn');
        btn?.classList.add('bounce');
        setTimeout(() => btn?.classList.remove('bounce'), 350);
        sendReaction(currentAv.icon, 'party', currentAv.name);
      }
    });

    document.querySelectorAll('.reaction-btn:not(#myAvatarReactionBtn)').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const emoji = btn.getAttribute('data-emoji') || '🔥';
        const sound = btn.getAttribute('data-sound') || 'fire';
        btn.classList.add('bounce');
        setTimeout(() => btn.classList.remove('bounce'), 350);
        sendReaction(emoji, sound, null);
      });
    });

    document.querySelectorAll('.shout-pill').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const emoji = btn.getAttribute('data-emoji') || '⚡';
        const sound = btn.getAttribute('data-sound') || 'banter';
        const text = btn.getAttribute('data-text') || '';
        btn.classList.add('bounce');
        setTimeout(() => btn.classList.remove('bounce'), 350);
        sendReaction(emoji, sound, text);
      });
    });

    document.getElementById('reactionSoundToggle')?.addEventListener('click', (e) => {
      e.preventDefault();
      reactionSoundsEnabled = !reactionSoundsEnabled;
      try {
        localStorage.setItem('pm-reaction-sound', reactionSoundsEnabled ? '1' : '0');
      } catch (err) {}
      updateReactionSoundUI();
      if (reactionSoundsEnabled) {
        playReactionSound('banter');
      }
    });

    // Host Start Button Trigger
    startMatchBtn?.addEventListener('click', handleStartMatch);

    // Leave Beacon is only sent when user explicitly leaves via exit button
    // (Active players should NOT be eliminated on accidental refresh, tab-switch or pagehide)
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

    // Adaptive Keep-Alive and State Synchronization Tick (Dual-channel)
    async function performTickSync() {
      try {
        const res = await fetch('api/rooms_tick.php?guid=' + encodeURIComponent(GUID));
        const data = await res.json();
        if (!data || !data.ok) return;

        if (Array.isArray(data.players)) {
          const meRow = data.players.find(p => p.email === ME_EMAIL || String(p.user_id) === String(ME_ID));
          if (meRow && meRow.status === 'eliminated' && (state.userExplicitlyLeft || state.joinedAsSpectator) && !state.eliminated) {
            state.eliminated = true;
            if (hudStatus) {
              hudStatus.textContent = STR.spectating;
              hudStatus.className = 'hud-val text-secondary';
            }
            document.querySelectorAll('.arena-btn').forEach(b => b.disabled = true);
          }
        }

        if ((data.finished || data.status === 'finished') && state.phase !== 'finished') {
          RoomLogger.info('TickSync', 'Match concluded on server', data);
          renderFinalVictory(data.players || [], data.awards || null, data.team_summary || null);
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

    function wireLeaveRoomBtn() {
      const leaveBtn = document.getElementById('leaveRoomBtn');
      leaveBtn?.addEventListener('click', async () => {
        if (state.phase === 'lobby' || state.eliminated || state.userExplicitlyLeft) {
          if (confirm(STR.confirmLeaveLobby || 'Odadan ayrılmak istiyor musunuz?')) {
            try {
              await fetch('api/rooms_leave.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({guid: GUID})
              });
            } catch(e) {}
            window.location.href = 'rooms.php';
          }
          return;
        }

        if (confirm(STR.confirmLeaveMatch || 'Odadan ayrılmak istediğinize emin misiniz? Oyundan elenecek ve izleyici durumuna geçeceksiniz.')) {
          state.userExplicitlyLeft = true;
          try {
            await fetch('api/rooms_leave.php', {
              method: 'POST',
              headers: {'Content-Type': 'application/json'},
              body: JSON.stringify({guid: GUID})
            });
          } catch(e) {}
          state.eliminated = true;
          if (hudStatus) {
            hudStatus.textContent = STR.spectating;
            hudStatus.className = 'hud-val text-secondary';
          }
          document.querySelectorAll('.arena-btn').forEach(b => b.disabled = true);
          showToast(STR.spectatorNotice || 'Elendiniz. İzleyici modundasınız.', false);
          renderPlayers(state.players);

          const stayAsSpectator = confirm(STR.confirmExitToRooms || 'İzleyici olarak odada kalıp maçı izlemek istiyor musunuz? (İptal: Oda listesine dön)');
          if (!stayAsSpectator) {
            window.location.href = 'rooms.php';
          }
        }
      });
    }

    if (CAN_VIEW_LOGS) {
      wireDebugLogs();
    }
    wireCopyInviteBtn();
    wireLeaveRoomBtn();
    if (ROOM_GAME_MODE === 'flags') {
      backgroundPreloadAllFlags();
    }
    renderLobbyRules();
    initRoom();
  </script>
</body>
</html>
