<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/i18n.php';

$lang = function_exists('get_lang') ? get_lang() : 'en';
$dir  = function_exists('lang_dir') ? lang_dir($lang) : 'ltr';

$userEmail = $_SESSION['user_email'] ?? null;
$userId = $_SESSION['user_id'] ?? null;
$showLangPicker = true;

function tt(string $key, string $fallback = ''): string {
    $v = t($key);
    if ($v === $key) return $fallback !== '' ? $fallback : $key;
    return $v;
}

function room_status_badge(string $status): string {
    switch ($status) {
        case 'waiting':
            return '<span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1 rounded-pill">' . htmlspecialchars(tt('room_status_waiting', 'Waiting')) . '</span>';
        case 'active':
            return '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill"><span class="pulse-dot"></span> ' . htmlspecialchars(tt('room_status_active', 'Active')) . '</span>';
        case 'finished':
            return '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 rounded-pill">' . htmlspecialchars(tt('room_status_finished', 'Finished')) . '</span>';
        default:
            return '<span class="badge bg-secondary px-2 py-1 rounded-pill">' . htmlspecialchars($status) . '</span>';
    }
}

function format_room_dt(?string $utcIso, ?string $lang = null): string {
    if (!$utcIso) return '-';
    if (function_exists('format_dt_local')) {
        return (string)format_dt_local($utcIso, $lang);
    }
    try {
        $dt = new DateTimeImmutable($utcIso, new DateTimeZone('UTC'));
        return $dt->format('d/m/Y H:i');
    } catch (\Exception $e) {
        return '-';
    }
}

function room_winner_name(string $roomId): ?string {
    $email = room_winner_email($roomId);
    if (!$email) return null;
    return user_display_name_from_row(['email' => $email]);
}

$isLoggedIn = !empty($userEmail) && !empty($userId);
$rooms = $isLoggedIn ? list_user_rooms((string)$userId, 100) : [];
$publicRooms = list_public_rooms(30);
$seoTitle = tt('rooms_title', 'Multiplayer Rooms');
$seoDescription = tt('rooms_desc', 'Create or join multiplayer rooms to compete live in real time.');
$seoLangs = function_exists('supported_languages') ? array_keys(supported_languages()) : [];
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
  <title><?= htmlspecialchars($seoTitle) ?> - <?= htmlspecialchars(tt('app_name', 'Prismatch')) ?></title>
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
    'breadcrumbs' => [
      ['name' => tt('nav_home', 'Home'), 'url' => '/'],
      ['name' => tt('rooms_title', 'Multiplayer Rooms'), 'url' => '/rooms.php'],
    ],
  ]) ?>
  <?= seo_alternate_links($seoLangs, seo_current_url()) ?>
  <style>
    body {
      padding-top: calc(var(--pm-header-offset, 0px) + 20px);
      padding-bottom: calc(var(--pm-footer-offset, 0px) + 24px);
    }
    .winner-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-weight: 700;
      color: #ffb020;
    }
    .room-badge-mode {
      white-space: nowrap !important;
      word-break: keep-all !important;
      overflow-wrap: normal !important;
      display: inline-flex !important;
      align-items: center;
      gap: 3px;
      font-size: 0.72rem;
      padding: 0.25rem 0.6rem;
      line-height: 1.2;
    }
    .table-modern {
      min-width: 580px;
    }
    .share-link-input {
      font-size: 0.88rem;
    }
    @media (max-width: 576px) {
      .share-link-input {
        font-size: 0.8rem;
      }
    }
    @media (max-width: 767px) {
      .card-modern .row > div {
        width: 100% !important;
        flex: 0 0 100% !important;
        max-width: 100% !important;
      }
      .card-modern .row {
        gap: 12px;
      }
    }
  </style>
</head>
<body class="pm-has-fixed-header">
  <?php include __DIR__ . '/header.php'; ?>

  <main class="wrap">
    <!-- Hero / Intro -->
    <div class="hero-card">
      <div>
        <h1 class="hero-title"><?= htmlspecialchars(tt('rooms_title', 'Multiplayer Rooms')) ?></h1>
        <div class="text-secondary"><?= htmlspecialchars(tt('rooms_desc', 'Create or join rooms to compete with friends live in real time.')) ?></div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2">
          <?= htmlspecialchars(tt('rooms_badge_info', '25 Rounds · Dynamic Grid')) ?>
        </span>
      </div>
    </div>

    <?php if (!$isLoggedIn): ?>
    <div class="card-modern text-center py-4 px-3 mb-4">
      <div class="fs-1 mb-2">🎮</div>
      <h2 class="h5 fw-bold mb-2"><?= htmlspecialchars(tt('rooms_guest_banner_title', 'Çok Oyunculu Odalara Katılın!')) ?></h2>
      <p class="text-secondary small mb-3 mx-auto" style="max-width:540px;">
        <?= htmlspecialchars(tt('rooms_guest_banner_desc', 'Arkadaşlarınızla canlı yarışmak veya yeni bir oda kurmak için Google ile oturum açın.')) ?>
      </p>
      <div>
        <a href="login.php?next=<?= rawurlencode('rooms.php') ?>" class="btn btn-action px-4 py-2">
          <?= htmlspecialchars(tt('home_cta_login', 'Login with Google')) ?>
        </a>
      </div>
    </div>
    <?php else: ?>
    <!-- Create Room Card -->
    <div class="card-modern">
      <h2 class="h5 fw-bold mb-3 d-flex align-items-center gap-2">
        <span>🎮</span> <?= htmlspecialchars(tt('rooms_create_title', 'Create a New Room')) ?>
      </h2>
      <div class="row g-3 align-items-end">
        <div class="col-md-7 col-lg-8">
          <label class="form-label small fw-semibold text-secondary" for="roomNameInput">
            <?= htmlspecialchars(tt('rooms_name_label', 'Room Name')) ?>
          </label>
          <input id="roomNameInput" class="form-control form-control-lg rounded-3" type="text" maxlength="80" placeholder="<?= htmlspecialchars(tt('rooms_name_placeholder', 'e.g. Arena Champions #1')) ?>" />
        </div>
        <div class="col-md-5 col-lg-4 d-flex gap-2">
          <button id="createRoomBtn" class="btn btn-create btn-lg w-100" type="button">
            <?= htmlspecialchars(tt('rooms_create_btn', 'Create Room')) ?>
          </button>
        </div>
      </div>

      <!-- Game Mode Selection -->
      <div class="mt-3 p-3 rounded-3 border bg-body-tertiary">
        <label class="form-label small fw-bold text-secondary mb-2 d-flex align-items-center gap-1">
          <span>🎯</span> <?= htmlspecialchars(tt('rooms_mode_label', 'Game Mode')) ?>
        </label>
        <div class="row g-2">
          <div class="col-md-6 col-lg-3">
            <div class="card h-100 p-3 mode-select-card" id="modeCardPoints" style="cursor: pointer; border: 2px solid var(--bs-primary); border-radius: 12px; transition: all 0.2s ease;">
              <div class="form-check m-0">
                <input class="form-check-input" type="radio" name="roomGameMode" id="modePoints" value="points" checked style="cursor: pointer;">
                <label class="form-check-label fw-bold user-select-none" for="modePoints" style="cursor: pointer;">
                  ⚡ <?= htmlspecialchars(tt('rooms_mode_points_title', 'Puan Yarışı (Elenmesiz)')) ?>
                </label>
              </div>
              <div class="small text-secondary mt-1 ps-4">
                <?= htmlspecialchars(tt('rooms_mode_points_desc', 'Elenme yok! Doğru cevap puan kazandırır, yanlışta kazanılacak puan toplamdan düşülür. Pas geçen 0 puan alır.')) ?>
              </div>
            </div>
          </div>
          <div class="col-md-6 col-lg-3">
            <div class="card h-100 p-3 mode-select-card" id="modeCardElimination" style="cursor: pointer; border: 2px solid rgba(255,255,255,0.12); border-radius: 12px; transition: all 0.2s ease;">
              <div class="form-check m-0">
                <input class="form-check-input" type="radio" name="roomGameMode" id="modeElimination" value="elimination" style="cursor: pointer;">
                <label class="form-check-label fw-bold user-select-none" for="modeElimination" style="cursor: pointer;">
                  💀 <?= htmlspecialchars(tt('rooms_mode_elim_title', 'Eleme Modu (Hayatta Kalma)')) ?>
                </label>
              </div>
              <div class="small text-secondary mt-1 ps-4">
                <?= htmlspecialchars(tt('rooms_mode_elim_desc', 'Yanlış yapan veya süresi dolan elenir. Son hayatta kalan veya en yüksek puanlı kazanır.')) ?>
              </div>
            </div>
          </div>
          <div class="col-md-6 col-lg-3">
            <div class="card h-100 p-3 mode-select-card" id="modeCardFlags" style="cursor: pointer; border: 2px solid rgba(255,255,255,0.12); border-radius: 12px; transition: all 0.2s ease;">
              <div class="form-check m-0">
                <input class="form-check-input" type="radio" name="roomGameMode" id="modeFlags" value="flags" style="cursor: pointer;">
                <label class="form-check-label fw-bold user-select-none" for="modeFlags" style="cursor: pointer;">
                  🚩 <?= htmlspecialchars(tt('rooms_mode_flags_title', 'Bayrak Modu (Dünya Bayrakları)')) ?>
                </label>
              </div>
              <div class="small text-secondary mt-1 ps-4">
                <?= htmlspecialchars(tt('rooms_mode_flags_desc', 'Renkler yerine 250+ ülke bayrağı! Elenme yok, doğru bayrak puan kazandırır, yanlış seçim puan düşürür.')) ?>
              </div>
            </div>
          </div>
          <div class="col-md-6 col-lg-3">
            <div class="card h-100 p-3 mode-select-card" id="modeCardTeams" style="cursor: pointer; border: 2px solid rgba(255,255,255,0.12); border-radius: 12px; transition: all 0.2s ease;">
              <div class="form-check m-0">
                <input class="form-check-input" type="radio" name="roomGameMode" id="modeTeams" value="teams" style="cursor: pointer;">
                <label class="form-check-label fw-bold user-select-none" for="modeTeams" style="cursor: pointer;">
                  ⚔️ <?= htmlspecialchars(tt('rooms_mode_teams_title', 'Takım Savaşı (Devs vs QAs)')) ?>
                </label>
              </div>
              <div class="small text-secondary mt-1 ps-4">
                <?= htmlspecialchars(tt('rooms_mode_teams_desc', '🔴 Kırmızı vs 🔵 Mavi Takım! Bireysel puanlar takım havuzuna yazılır, en çok puanı toplayan takım kazanır.')) ?>
              </div>
            </div>
      </div>

      <!-- Teams Configuration (Revealed when Teams mode is chosen) -->
      <div id="teamsConfigWrap" class="mt-3 p-3 rounded-3 border bg-body-tertiary" style="display: none; border-left: 4px solid #f59e0b !important;">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
          <div>
            <div class="fw-bold d-flex align-items-center gap-1.5 text-warning-emphasis">
              <span>⚔️</span> <?= htmlspecialchars(tt('teams_config_title', 'Takım Ayarları')) ?>
            </div>
            <div class="small text-secondary">
              <?= htmlspecialchars(tt('teams_config_desc', 'Takım sayısını (2-4) ve takımlarınızın özel isimlerini belirleyin.')) ?>
            </div>
          </div>
          <div class="btn-group btn-group-sm" role="group" aria-label="Takım Sayısı">
            <input type="radio" class="btn-check" name="teamCountRadio" id="teamCount2" value="2" checked autocomplete="off">
            <label class="btn btn-outline-warning fw-semibold px-3" for="teamCount2">2 <?= htmlspecialchars(tt('teams_count_unit', 'Takım')) ?></label>

            <input type="radio" class="btn-check" name="teamCountRadio" id="teamCount3" value="3" autocomplete="off">
            <label class="btn btn-outline-warning fw-semibold px-3" for="teamCount3">3 <?= htmlspecialchars(tt('teams_count_unit', 'Takım')) ?></label>

            <input type="radio" class="btn-check" name="teamCountRadio" id="teamCount4" value="4" autocomplete="off">
            <label class="btn btn-outline-warning fw-semibold px-3" for="teamCount4">4 <?= htmlspecialchars(tt('teams_count_unit', 'Takım')) ?></label>
          </div>
        </div>

        <div class="row g-2">
          <!-- Team Red -->
          <div class="col-12 col-sm-6 col-lg-3" id="teamWrapRed">
            <label class="form-label small fw-bold text-danger d-flex align-items-center gap-1 mb-1" for="teamNameRed">
              <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#ef4444;"></span>
              <?= htmlspecialchars(tt('team_slot_red', '1. Takım (Kırmızı)')) ?>
            </label>
            <input type="text" class="form-control form-control-sm border-danger-subtle" id="teamNameRed" maxlength="40" placeholder="<?= htmlspecialchars(tt('team_default_red', 'Kırmızı Takım')) ?>" value="<?= htmlspecialchars(tt('team_default_red', 'Kırmızı Takım')) ?>">
          </div>
          <!-- Team Blue -->
          <div class="col-12 col-sm-6 col-lg-3" id="teamWrapBlue">
            <label class="form-label small fw-bold text-primary d-flex align-items-center gap-1 mb-1" for="teamNameBlue">
              <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#3b82f6;"></span>
              <?= htmlspecialchars(tt('team_slot_blue', '2. Takım (Mavi)')) ?>
            </label>
            <input type="text" class="form-control form-control-sm border-primary-subtle" id="teamNameBlue" maxlength="40" placeholder="<?= htmlspecialchars(tt('team_default_blue', 'Mavi Takım')) ?>" value="<?= htmlspecialchars(tt('team_default_blue', 'Mavi Takım')) ?>">
          </div>
          <!-- Team Green -->
          <div class="col-12 col-sm-6 col-lg-3" id="teamWrapGreen" style="display: none;">
            <label class="form-label small fw-bold text-success d-flex align-items-center gap-1 mb-1" for="teamNameGreen">
              <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#10b981;"></span>
              <?= htmlspecialchars(tt('team_slot_green', '3. Takım (Yeşil)')) ?>
            </label>
            <input type="text" class="form-control form-control-sm border-success-subtle" id="teamNameGreen" maxlength="40" placeholder="<?= htmlspecialchars(tt('team_default_green', 'Yeşil Takım')) ?>" value="<?= htmlspecialchars(tt('team_default_green', 'Yeşil Takım')) ?>">
          </div>
          <!-- Team Yellow -->
          <div class="col-12 col-sm-6 col-lg-3" id="teamWrapYellow" style="display: none;">
            <label class="form-label small fw-bold text-warning d-flex align-items-center gap-1 mb-1" for="teamNameYellow">
              <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#f59e0b;"></span>
              <?= htmlspecialchars(tt('team_slot_yellow', '4. Takım (Sarı)')) ?>
            </label>
            <input type="text" class="form-control form-control-sm border-warning-subtle" id="teamNameYellow" maxlength="40" placeholder="<?= htmlspecialchars(tt('team_default_yellow', 'Sarı Takım')) ?>" value="<?= htmlspecialchars(tt('team_default_yellow', 'Sarı Takım')) ?>">
          </div>
        </div>
      </div>

      <div class="mt-3 p-3 rounded-3 border bg-body-tertiary d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="form-check form-switch d-inline-flex align-items-center gap-2 m-0">
          <input class="form-check-input" type="checkbox" role="switch" id="roomIsPrivate" style="cursor: pointer; width: 2.4em; height: 1.25em;">
          <label class="form-check-label fw-bold user-select-none" for="roomIsPrivate" style="cursor: pointer;">
            🔒 <?= htmlspecialchars(tt('rooms_private_label', 'Private Room (Only players with the link can join)')) ?>
          </label>
        </div>
        <span class="text-secondary small">
          <?= htmlspecialchars(tt('rooms_private_hint', 'Public rooms appear in the lobby for everyone. Private rooms are hidden and accessible only via direct link.')) ?>
        </span>
      </div>
      <div id="createMsg" class="small mt-2"></div>

      <!-- Share Box (Revealed after creation) -->
      <div id="shareWrap" class="mt-4 pt-3 border-top" hidden>
        <div class="alert alert-success d-flex flex-column gap-3 rounded-3 p-3 p-sm-4 shadow-sm mb-0">
          <div class="fw-semibold fs-6" id="shareSuccessTitle">🎉 <?= htmlspecialchars(tt('rooms_created_success', 'Room created successfully! Share this link with players:')) ?></div>
          
          <div class="input-group">
            <input id="shareLink" class="form-control font-monospace px-3 py-2 bg-body-tertiary share-link-input" type="text" readonly />
            <button id="copyLinkBtn" class="btn btn-dark px-3 py-2 d-inline-flex align-items-center gap-1.5 fw-semibold text-nowrap" type="button">
              <span>📋</span> <span id="copyLinkBtnText"><?= htmlspecialchars(tt('rooms_copy', 'Copy Link')) ?></span>
            </button>
          </div>

          <div class="d-flex flex-column flex-sm-row gap-2 pt-1">
            <a id="shareWhatsappBtn" class="btn btn-success flex-fill py-2.5 d-inline-flex align-items-center justify-content-center gap-2 fw-bold shadow-sm" href="#" target="_blank" rel="noopener noreferrer">
              <span class="fs-5">💬</span> <?= htmlspecialchars(tt('rooms_share_whatsapp', 'WhatsApp ile Gönder')) ?>
            </a>
            <a id="openRoomBtn" class="btn btn-primary flex-fill py-2.5 d-inline-flex align-items-center justify-content-center gap-2 fw-bold shadow-sm" href="#">
              <?= htmlspecialchars(tt('rooms_open', 'Enter Room')) ?> →
            </a>
          </div>
          <div id="shareMsg" class="small text-success fw-semibold"></div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Active Public Rooms Card -->
    <?php if (!empty($publicRooms)): ?>
    <div class="card-modern">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
          <h2 class="h5 fw-bold mb-0 d-flex align-items-center gap-2">
            <span>🌐</span> <?= htmlspecialchars(tt('rooms_active_public', 'Active Public Rooms')) ?>
          </h2>
          <div class="small text-secondary mt-1"><?= htmlspecialchars(tt('rooms_active_desc', 'Join an open match and compete with others live!')) ?></div>
        </div>
        <span class="badge bg-success-subtle text-success rounded-pill px-2.5 py-1">
          <span class="pulse-dot"></span> <?= count($publicRooms) ?> <?= htmlspecialchars(tt('rooms_available', 'active')) ?>
        </span>
      </div>

      <div class="table-responsive">
        <table class="table table-modern align-middle">
          <thead>
            <tr>
              <th><?= htmlspecialchars(tt('room_name', 'Room Name')) ?></th>
              <th><?= htmlspecialchars(tt('room_status', 'Status')) ?></th>
              <th><?= htmlspecialchars(tt('room_players', 'Players')) ?></th>
              <th><?= htmlspecialchars(tt('room_rounds', 'Rounds')) ?></th>
              <th><?= htmlspecialchars(tt('room_host', 'Host')) ?></th>
              <th class="text-end"><?= htmlspecialchars(tt('room_action', 'Action')) ?></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($publicRooms as $pr):
            $pGuid = (string)($pr['guid'] ?? '');
            $pStatus = (string)($pr['status'] ?? 'waiting');
            $pPlayers = (int)($pr['player_count'] ?? 1);
            $pHost = explode('@', (string)($pr['owner_email'] ?? ''))[0] ?: tt('room_host', 'Host');
          ?>
              <td>
                <div class="d-flex flex-wrap align-items-center gap-1.5">
                  <span class="fw-semibold"><?= htmlspecialchars($pr['name'] ?: tt('room_prefix', 'Room #') . substr($pGuid, 0, 8)) ?></span>
                  <?php if (($pr['game_mode'] ?? 'elimination') === 'points'): ?>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill room-badge-mode">
                      ⚡ <?= htmlspecialchars(tt('rooms_mode_points_short', 'Puan')) ?>
                    </span>
                  <?php elseif (($pr['game_mode'] ?? 'elimination') === 'flags'): ?>
                    <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill room-badge-mode">
                      🚩 <?= htmlspecialchars(tt('rooms_mode_flags_short', 'Bayrak')) ?>
                    </span>
                  <?php elseif (($pr['game_mode'] ?? 'elimination') === 'teams'): ?>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill room-badge-mode">
                      ⚔️ <?= htmlspecialchars(tt('rooms_mode_teams_short', 'Takım')) ?>
                    </span>
                  <?php else: ?>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill room-badge-mode">
                      💀 <?= htmlspecialchars(tt('rooms_mode_elim_short', 'Eleme')) ?>
                    </span>
                  <?php endif; ?>
                </div>
              </td>
              <td><?= room_status_badge($pStatus) ?></td>
              <td><span class="badge bg-dark-subtle text-dark-emphasis rounded-pill"><?= $pPlayers ?> 👥</span></td>
              <td><span class="fw-semibold"><?= (int)$pr['current_round'] ?></span> / <?= (int)$pr['rounds_total'] ?></td>
              <td class="small text-secondary"><?= htmlspecialchars($pHost) ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-primary" href="room_play.php?guid=<?= urlencode($pGuid) ?>">
                  <?= htmlspecialchars(tt('rooms_join_btn', 'Join')) ?> →
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Room History Card -->
    <div class="card-modern">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="h5 fw-bold mb-0 d-flex align-items-center gap-2">
          <span>📜</span> <?= htmlspecialchars(tt('rooms_history', 'Your Match History')) ?>
        </h2>
        <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2.5 py-1">
          <?= count($rooms) ?> <?= htmlspecialchars(tt('rooms_total', 'rooms')) ?>
        </span>
      </div>

      <?php if (!$rooms): ?>
        <div class="text-center py-5 text-secondary">
          <div class="fs-1 mb-2">🏟️</div>
          <div class="fw-semibold"><?= htmlspecialchars(tt('no_results', 'No matches found.')) ?></div>
          <div class="small"><?= htmlspecialchars(tt('rooms_empty_hint', 'Create your first multiplayer room above to start competing!')) ?></div>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-modern align-middle">
            <thead>
              <tr>
                <th><?= htmlspecialchars(tt('room_name', 'Room Name')) ?></th>
                <th><?= htmlspecialchars(tt('rooms_type', 'Type')) ?></th>
                <th><?= htmlspecialchars(tt('room_status', 'Status')) ?></th>
                <th><?= htmlspecialchars(tt('room_rounds', 'Rounds')) ?></th>
                <th><?= htmlspecialchars(tt('room_winner', 'Winner')) ?></th>
                <th><?= htmlspecialchars(tt('room_created', 'Created')) ?></th>
                <th class="text-end"><?= htmlspecialchars(tt('room_action', 'Action')) ?></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($rooms as $r):
              $winner = room_winner_name((string)($r['id'] ?? ''));
              $guid = (string)($r['guid'] ?? '');
              $status = (string)($r['status'] ?? 'waiting');
              $isPriv = !empty($r['is_private']);
            ?>
              <tr>
                <td>
                  <div class="d-flex flex-wrap align-items-center gap-1.5">
                  <span class="fw-semibold"><?= htmlspecialchars($r['name'] ?: tt('room_prefix', 'Room #') . substr($guid, 0, 8)) ?></span>
                  <?php if (($r['game_mode'] ?? 'elimination') === 'points'): ?>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill room-badge-mode">
                      ⚡ <?= htmlspecialchars(tt('rooms_mode_points_short', 'Puan')) ?>
                    </span>
                  <?php elseif (($r['game_mode'] ?? 'elimination') === 'flags'): ?>
                    <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill room-badge-mode">
                      🚩 <?= htmlspecialchars(tt('rooms_mode_flags_short', 'Bayrak')) ?>
                    </span>
                  <?php elseif (($r['game_mode'] ?? 'elimination') === 'teams'): ?>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill room-badge-mode">
                      ⚔️ <?= htmlspecialchars(tt('rooms_mode_teams_short', 'Takım')) ?>
                    </span>
                  <?php else: ?>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill room-badge-mode">
                      💀 <?= htmlspecialchars(tt('rooms_mode_elim_short', 'Eleme')) ?>
                    </span>
                  <?php endif; ?>
                </div>
              </td>
                <td>
                  <?php if ($isPriv): ?>
                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 rounded-pill">
                      🔒 <?= htmlspecialchars(tt('rooms_private_badge', 'Private')) ?>
                    </span>
                  <?php else: ?>
                    <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1 rounded-pill">
                      🌐 <?= htmlspecialchars(tt('rooms_public_badge', 'Public')) ?>
                    </span>
                  <?php endif; ?>
                </td>
                <td><?= room_status_badge($status) ?></td>
                <td><span class="fw-semibold"><?= (int)$r['current_round'] ?></span> / <?= (int)$r['rounds_total'] ?></td>
                <td>
                  <?php if ($winner): ?>
                    <span class="winner-badge">👑 <?= htmlspecialchars($winner) ?></span>
                  <?php else: ?>
                    <span class="text-secondary">-</span>
                  <?php endif; ?>
                </td>
                <td class="small text-secondary"><?= htmlspecialchars(format_room_dt($r['created_at'], $lang)) ?></td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm">
                    <a class="btn btn-primary" href="room_play.php?guid=<?= urlencode($guid) ?>">
                      <?= htmlspecialchars(tt('rooms_open', 'Open')) ?>
                    </a>
                    <a class="btn btn-outline-secondary" href="room_history.php?guid=<?= urlencode($guid) ?>">
                      <?= htmlspecialchars(tt('rooms_history', 'Details')) ?>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <?php include __DIR__ . '/footer.php'; ?>

  <script>
    const createBtn = document.getElementById('createRoomBtn');
    const roomNameInput = document.getElementById('roomNameInput');
    const createMsg = document.getElementById('createMsg');
    const shareWrap = document.getElementById('shareWrap');
    const shareLink = document.getElementById('shareLink');
    const copyLinkBtn = document.getElementById('copyLinkBtn');
    const shareMsg = document.getElementById('shareMsg');
    const openRoomBtn = document.getElementById('openRoomBtn');

    const setMsg = (el, text = '', type = '') => {
      if (!el) return;
      el.textContent = text || '';
      el.className = 'small mt-2 ' + (type === 'error' ? 'text-danger fw-semibold' : 'text-success fw-semibold');
    };

    const STR = {
      shareCopied: <?= json_encode(tt('rooms_share_copied', 'Copied!')) ?>,
      shareFailed: <?= json_encode(tt('rooms_share_failed', 'Could not copy link.')) ?>,
      nameRequired: <?= json_encode(tt('rooms_name_required', 'Please enter a room name.')) ?>,
      errorGeneric: <?= json_encode(tt('error_generic', 'An error occurred. Please try again.')) ?>,
      creating: <?= json_encode(tt('status_creating', 'Creating...')) ?>,
      copyLink: <?= json_encode(tt('rooms_copy', 'Copy Link')) ?>,
      createdSuccess: <?= json_encode(tt('rooms_created_success', 'Room created successfully! Share this link with players:')) ?>,
      createdPrivateSuccess: <?= json_encode(tt('rooms_created_private_success', 'Private room created! Only players with this link can enter:')) ?>,
      shareWhatsappText: <?= json_encode(tt('rooms_share_whatsapp_text', "Prismatch'te benimle oda oyununa katıl! 🎮 Bağlantı:")) ?>,
    };

    // Mode card selection behavior
    const modeCardPoints = document.getElementById('modeCardPoints');
    const modeCardElimination = document.getElementById('modeCardElimination');
    const modeCardFlags = document.getElementById('modeCardFlags');
    const modeCardTeams = document.getElementById('modeCardTeams');
    const radioPoints = document.getElementById('modePoints');
    const radioElimination = document.getElementById('modeElimination');
    const radioFlags = document.getElementById('modeFlags');
    const radioTeams = document.getElementById('modeTeams');

    const teamsConfigWrap = document.getElementById('teamsConfigWrap');
    const teamWrapGreen = document.getElementById('teamWrapGreen');
    const teamWrapYellow = document.getElementById('teamWrapYellow');
    const teamCountRadios = document.querySelectorAll('input[name="teamCountRadio"]');

    function syncTeamCount() {
      const selected = parseInt(document.querySelector('input[name="teamCountRadio"]:checked')?.value || '2', 10);
      if (teamWrapGreen) teamWrapGreen.style.display = (selected >= 3) ? 'block' : 'none';
      if (teamWrapYellow) teamWrapYellow.style.display = (selected >= 4) ? 'block' : 'none';
    }

    teamCountRadios.forEach(radio => radio.addEventListener('change', syncTeamCount));

    function syncModeCards() {
      const cards = [
        { card: modeCardPoints, radio: radioPoints, color: 'var(--bs-primary)' },
        { card: modeCardElimination, radio: radioElimination, color: 'var(--bs-danger)' },
        { card: modeCardFlags, radio: radioFlags, color: '#0dcaf0' },
        { card: modeCardTeams, radio: radioTeams, color: '#f59e0b' }
      ];
      cards.forEach(item => {
        if (item.radio && item.radio.checked) {
          item.card?.style.setProperty('border-color', item.color, 'important');
        } else {
          item.card?.style.setProperty('border-color', 'rgba(255,255,255,0.12)', 'important');
        }
      });
      if (teamsConfigWrap) {
        teamsConfigWrap.style.display = (radioTeams && radioTeams.checked) ? 'block' : 'none';
        if (radioTeams && radioTeams.checked) {
          syncTeamCount();
        }
      }
    }

    modeCardPoints?.addEventListener('click', () => {
      if (radioPoints) radioPoints.checked = true;
      syncModeCards();
    });
    modeCardElimination?.addEventListener('click', () => {
      if (radioElimination) radioElimination.checked = true;
      syncModeCards();
    });
    modeCardFlags?.addEventListener('click', () => {
      if (radioFlags) radioFlags.checked = true;
      syncModeCards();
    });
    modeCardTeams?.addEventListener('click', () => {
      if (radioTeams) radioTeams.checked = true;
      syncModeCards();
    });
    radioPoints?.addEventListener('change', syncModeCards);
    radioElimination?.addEventListener('change', syncModeCards);
    radioFlags?.addEventListener('change', syncModeCards);
    radioTeams?.addEventListener('change', syncModeCards);

    createBtn?.addEventListener('click', async () => {
      setMsg(createMsg, '');
      const name = (roomNameInput.value || '').trim();
      if (!name) {
        setMsg(createMsg, STR.nameRequired, 'error');
        roomNameInput.focus();
        return;
      }

      const isPrivate = !!document.getElementById('roomIsPrivate')?.checked;
      const gameMode = document.querySelector('input[name="roomGameMode"]:checked')?.value || 'points';

      let teams = null;
      if (gameMode === 'teams') {
        const teamCount = parseInt(document.querySelector('input[name="teamCountRadio"]:checked')?.value || '2', 10);
        const redName = (document.getElementById('teamNameRed')?.value || '').trim() || <?= json_encode(tt('team_default_red', 'Kırmızı Takım')) ?>;
        const blueName = (document.getElementById('teamNameBlue')?.value || '').trim() || <?= json_encode(tt('team_default_blue', 'Mavi Takım')) ?>;
        const greenName = (document.getElementById('teamNameGreen')?.value || '').trim() || <?= json_encode(tt('team_default_green', 'Yeşil Takım')) ?>;
        const yellowName = (document.getElementById('teamNameYellow')?.value || '').trim() || <?= json_encode(tt('team_default_yellow', 'Sarı Takım')) ?>;

        teams = [
          { id: 'red', name: redName, color: '#ef4444' },
          { id: 'blue', name: blueName, color: '#3b82f6' }
        ];
        if (teamCount >= 3) {
          teams.push({ id: 'green', name: greenName, color: '#10b981' });
        }
        if (teamCount >= 4) {
          teams.push({ id: 'yellow', name: yellowName, color: '#f59e0b' });
        }
      }

      createBtn.disabled = true;
      createBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> ' + STR.creating;

      try {
        const payload = {rounds_total: 25, name, is_private: isPrivate ? 1 : 0, game_mode: gameMode};
        if (teams) {
          payload.teams = teams;
        }
        const res = await fetch('api/rooms_create.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.ok && data.guid) {
          const url = new URL('room_play.php?guid=' + encodeURIComponent(data.guid), window.location.href).toString();
          if (shareWrap && shareLink) {
            shareWrap.hidden = false;
            shareLink.value = url;
            if (openRoomBtn) openRoomBtn.href = url;
            const shareWhatsappBtn = document.getElementById('shareWhatsappBtn');
            if (shareWhatsappBtn) {
              shareWhatsappBtn.href = 'https://api.whatsapp.com/send?text=' + encodeURIComponent((STR.shareWhatsappText || "Prismatch'te benimle oda oyununa katıl! 🎮 Bağlantı:") + ' ' + url);
            }
            const successTitle = document.getElementById('shareSuccessTitle');
            if (successTitle) {
              successTitle.textContent = isPrivate ? ('🔒 ' + STR.createdPrivateSuccess) : ('🎉 ' + STR.createdSuccess);
            }
            shareWrap.scrollIntoView({behavior: 'smooth'});
          } else {
            window.location.href = url;
          }
        } else {
          setMsg(createMsg, data.error || STR.errorGeneric, 'error');
        }
      } catch (e) {
        setMsg(createMsg, STR.errorGeneric, 'error');
      } finally {
        createBtn.disabled = false;
        createBtn.textContent = <?= json_encode(tt('rooms_create_btn', 'Create Room')) ?>;
      }
    });

    copyLinkBtn?.addEventListener('click', async () => {
      if (!shareLink || !shareLink.value) return;
      try {
        await navigator.clipboard.writeText(shareLink.value);
        copyLinkBtn.innerHTML = '<span>✅</span> <span>' + STR.shareCopied + '</span>';
        setTimeout(() => { copyLinkBtn.innerHTML = '<span>📋</span> <span>' + STR.copyLink + '</span>'; }, 2000);
      } catch (e) {
        shareLink.select();
        document.execCommand('copy');
        copyLinkBtn.innerHTML = '<span>✅</span> <span>' + STR.shareCopied + '</span>';
        setTimeout(() => { copyLinkBtn.innerHTML = '<span>📋</span> <span>' + STR.copyLink + '</span>'; }, 2000);
      }
    });
  </script>
</body>
</html>
