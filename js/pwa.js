/**
 * Prismatch - Progressive Web App (PWA) Lifecycle & Install Manager
 * Comprehensive installation flow for Android & iOS (Add to Home Screen),
 * Service Worker registration, network state sync, and offline alerts.
 */
(function () {
  'use strict';

  let deferredInstallPrompt = null;
  let activeModal = null;

  // 1. Device and Environment Detection
  function getEnvironment() {
    const ua = navigator.userAgent || '';
    const platform = navigator.platform || '';

    // iOS detection (including iPadOS on modern Safari)
    const isIOS = /iPad|iPhone|iPod/.test(ua) || (platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    // Android detection
    const isAndroid = /Android/i.test(ua);

    // Mobile / tablet detection
    const isMobile = isIOS || isAndroid || /Mobi|Tablet|Touch/i.test(ua) || (window.innerWidth <= 820);

    // Safari browser detection on iOS
    const isIOSSafari = isIOS && /Safari/i.test(ua) && !/CriOS|FxiOS|OPiOS|mercury/i.test(ua);

    // Standalone (already installed PWA) detection
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches ||
                         window.navigator.standalone === true ||
                         document.referrer.includes('android-app://');

    return {
      isIOS,
      isAndroid,
      isMobile,
      isIOSSafari,
      isStandalone
    };
  }

  const env = getEnvironment();

  // 2. Localization Dictionary
  const langAttr = (document.documentElement.lang || 'tr').toLowerCase();
  const isTr = langAttr.startsWith('tr');

  const i18n = {
    // Android Banner
    androidBannerTitle: isTr ? "Prismatch'i Yükleyin" : "Install Prismatch",
    androidBannerSub: isTr ? "Hızlı erişim ve çevrimdışı oynama deneyimi" : "Fast access & offline gameplay experience",
    androidBannerBtn: isTr ? "Yükle" : "Install",

    // iOS Banner
    iosBannerTitle: isTr ? "Prismatch'i Ana Ekrana Ekleyin" : "Add Prismatch to Home Screen",
    iosBannerSub: isTr ? "Tam ekran ve kesintisiz uygulama deneyimi" : "Full-screen, native-like app experience",
    iosBannerBtn: isTr ? "Nasıl Eklenir?" : "How to Add?",

    // Desktop Banner
    desktopBannerTitle: isTr ? "Prismatch'i Yükleyin" : "Install Prismatch",
    desktopBannerSub: isTr ? "Masaüstünüzden tek tıkla hızlıca oynayın" : "One-click access directly from your desktop",
    desktopBannerBtn: isTr ? "Yükle" : "Install",

    // iOS Modal Guidance
    iosModalTitle: isTr ? "iOS'ta Ana Ekrana Ekleme" : "Add to Home Screen on iOS",
    iosModalSubtitle: isTr ? "Prismatch'i iPhone veya iPad'inize uygulama olarak ekleyin" : "Install Prismatch on your iPhone or iPad as an app",
    iosStep1Title: isTr ? "1. Paylaş Simgesine Dokunun" : "1. Tap the Share Button",
    iosStep1Desc: isTr ? "Safari alt araç çubuğundaki (veya iPad'de sağ üstteki) Paylaş simgesine dokunun." : "Tap the Share icon in the Safari bottom toolbar (or top right on iPad).",
    iosStep2Title: isTr ? "2. 'Ana Ekrana Ekle'yi Seçin" : "2. Tap 'Add to Home Screen'",
    iosStep2Desc: isTr ? "Açılan paylaşım menüsünü aşağı kaydırın ve 'Ana Ekrana Ekle' seçeneğine dokunun." : "Scroll down the share sheet and tap 'Add to Home Screen'.",
    iosStep3Title: isTr ? "3. 'Ekle' Butonuna Basın" : "3. Tap 'Add'",
    iosStep3Desc: isTr ? "Sağ üst köşedeki 'Ekle'ye dokunun. Prismatch artık ana ekranınızda hazır!" : "Tap 'Add' in the top right corner. Prismatch is ready on your home screen!",
    iosSafariPointer: isTr ? "Safari alt çubuğundaki Paylaş butonuna dokunarak başlayın" : "Tap the Share button on the bottom bar of Safari to begin",

    // Android Modal Guidance
    androidModalTitle: isTr ? "Android'de Uygulamayı Yükleme" : "Install Prismatch on Android",
    androidModalSubtitle: isTr ? "Prismatch'i cihazınıza yükleyerek tam ekran oynayın" : "Install Prismatch on your device for full-screen gameplay",
    androidStep1Title: isTr ? "1. Menüyü Açın" : "1. Open Browser Menu",
    androidStep1Desc: isTr ? "Tarayıcınızın sağ üst köşesindeki üç nokta (⋮) simgesine dokunun." : "Tap the three vertical dots (⋮) menu in the top-right corner of your browser.",
    androidStep2Title: isTr ? "2. 'Uygulamayı Yükle'yi Seçin" : "2. Select 'Install App'",
    androidStep2Desc: isTr ? "Menüden 'Uygulamayı Yükle' veya 'Ana Ekrana Ekle' seçeneğine dokunun." : "Tap 'Install App' or 'Add to Home Screen' from the menu.",
    androidStep3Title: isTr ? "3. Onaylayın" : "3. Confirm",
    androidStep3Desc: isTr ? "Açılan pencerede 'Yükle' butonuna basarak uygulamayı telefonunuza ekleyin." : "Tap 'Install' in the prompt to complete installation.",

    // General
    modalCloseBtn: isTr ? "Anladım" : "Got it",
    installedSuccess: isTr ? "🎉 Prismatch uygulaması başarıyla yüklendi!" : "🎉 Prismatch was installed successfully!",
    offlineMsg: isTr ? "⚡ Çevrimdışı moddasınız. Oyunları kesintisiz oynayabilirsiniz; skorlarınız internet geldiğinde eşitlenecektir." : "⚡ You are offline. Games remain playable; scores will sync when connection returns.",
    onlineMsg: isTr ? "✅ Yeniden çevrimiçisiniz! Skorlarınız senkronize ediliyor..." : "✅ You're back online! Syncing scores...",
    updatedMsg: isTr ? "🚀 Prismatch güncellendi! Yeni özellikleri görmek için sayfayı yenileyin." : "🚀 Prismatch updated! Refresh to see new features."
  };

  // 3. Service Worker Registration
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js', { scope: '/' })
        .then((reg) => {
          console.log('[PWA] Service Worker registered with scope:', reg.scope);
          reg.addEventListener('updatefound', () => {
            const newWorker = reg.installing;
            if (newWorker) {
              newWorker.addEventListener('statechange', () => {
                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                  showToastNotification(i18n.updatedMsg);
                }
              });
            }
          });
        })
        .catch((err) => {
          console.warn('[PWA] Service Worker registration failed:', err);
        });
    });
  }

  // 4. Header Install Button Setup
  function setupHeaderInstallButton() {
    const btn = document.getElementById('pwaInstallBtn');
    if (!btn) return;

    if (env.isStandalone) {
      btn.style.display = 'none';
      return;
    }

    // Reveal header button on Android, iOS, or when prompt is ready
    btn.style.display = 'inline-flex';
    btn.onclick = (e) => {
      e.preventDefault();
      handleInstallClick();
    };
  }

  // 5. Native Install Prompt Capture (beforeinstallprompt - Android / Chromium)
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredInstallPrompt = e;

    setupHeaderInstallButton();

    // Check dismissal and schedule banner if mobile
    if (!env.isStandalone && !isInstallDismissed()) {
      scheduleBannerDisplay();
    }
  });

  // Successful installation event
  window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = null;
    hideFloatingInstallBanner();
    closeInstallModal();

    const headerInstallBtn = document.getElementById('pwaInstallBtn');
    if (headerInstallBtn) headerInstallBtn.style.display = 'none';

    showToastNotification(i18n.installedSuccess);
  });

  // 6. Primary Install Click Handler
  function handleInstallClick() {
    if (env.isIOS) {
      showIosInstallGuide();
      return;
    }

    if (deferredInstallPrompt) {
      deferredInstallPrompt.prompt();
      deferredInstallPrompt.userChoice.then((choiceResult) => {
        if (choiceResult && choiceResult.outcome === 'accepted') {
          console.log('[PWA] User accepted the install prompt');
        } else {
          console.log('[PWA] User dismissed the native prompt');
        }
        deferredInstallPrompt = null;
        hideFloatingInstallBanner();
      }).catch(() => {
        showAndroidInstallGuide();
      });
      return;
    }

    if (env.isAndroid) {
      showAndroidInstallGuide();
      return;
    }

    // Desktop without deferred prompt
    showAndroidInstallGuide();
  }

  // 7. Dismissal Management (Dismiss for 3 days when user taps ✕)
  function isInstallDismissed() {
    try {
      const until = localStorage.getItem('pm_pwa_dismissed_until');
      if (until && Number(until) > Date.now()) {
        return true;
      }
    } catch (_) {}
    return false;
  }

  function setInstallDismissed() {
    try {
      const threeDaysLater = Date.now() + 3 * 24 * 60 * 60 * 1000;
      localStorage.setItem('pm_pwa_dismissed_until', threeDaysLater.toString());
    } catch (_) {}
  }

  // 8. Floating Install Banner
  function scheduleBannerDisplay() {
    if (env.isStandalone || isInstallDismissed()) return;
    if (document.getElementById('pmFloatingInstallBanner')) return;

    // Small initial delay so page loads smoothly first
    setTimeout(() => {
      if (!env.isStandalone && !isInstallDismissed()) {
        showFloatingInstallBanner();
      }
    }, 1800);
  }

  function showFloatingInstallBanner() {
    if (document.getElementById('pmFloatingInstallBanner') || env.isStandalone) return;

    const banner = document.createElement('div');
    banner.id = 'pmFloatingInstallBanner';
    banner.className = 'pm-pwa-banner';

    let title = i18n.androidBannerTitle;
    let subtitle = i18n.androidBannerSub;
    let btnText = i18n.androidBannerBtn;

    if (env.isIOS) {
      title = i18n.iosBannerTitle;
      subtitle = i18n.iosBannerSub;
      btnText = i18n.iosBannerBtn;
    } else if (!env.isAndroid && !env.isMobile) {
      title = i18n.desktopBannerTitle;
      subtitle = i18n.desktopBannerSub;
      btnText = i18n.desktopBannerBtn;
    }

    banner.innerHTML = `
      <div class="pm-pwa-banner-inner">
        <div class="pm-pwa-banner-logo-wrap">
          <img src="logo.svg" alt="Prismatch" width="42" height="42" class="pm-pwa-banner-icon" />
        </div>
        <div class="pm-pwa-banner-text">
          <div class="pm-pwa-banner-title">${escapeHtml(title)}</div>
          <div class="pm-pwa-banner-sub">${escapeHtml(subtitle)}</div>
        </div>
        <div class="pm-pwa-banner-actions">
          <button type="button" class="btn btn-sm pm-pwa-btn-install" id="pmBannerInstallBtn">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
              <polyline points="7 10 12 15 17 10"></polyline>
              <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            ${escapeHtml(btnText)}
          </button>
          <button type="button" class="pm-pwa-btn-dismiss" id="pmBannerDismissBtn" aria-label="Kapat">✕</button>
        </div>
      </div>
    `;

    document.body.appendChild(banner);

    // Fade and slide in
    requestAnimationFrame(() => {
      banner.classList.add('pm-pwa-banner-visible');
    });

    document.getElementById('pmBannerInstallBtn')?.addEventListener('click', handleInstallClick);
    document.getElementById('pmBannerDismissBtn')?.addEventListener('click', () => {
      setInstallDismissed();
      hideFloatingInstallBanner();
    });
  }

  function hideFloatingInstallBanner() {
    const banner = document.getElementById('pmFloatingInstallBanner');
    if (banner) {
      banner.classList.remove('pm-pwa-banner-visible');
      setTimeout(() => banner.remove(), 260);
    }
  }

  // 9. iOS Install Guide Modal
  function showIosInstallGuide() {
    closeInstallModal();

    const modal = document.createElement('div');
    modal.id = 'pmInstallGuideModal';
    modal.className = 'pm-pwa-modal-backdrop';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');

    const pointerHtml = env.isIOSSafari ? `
      <div class="pm-pwa-ios-pointer">
        <div class="pm-pwa-ios-pointer-arrow">↓</div>
        <span>${escapeHtml(i18n.iosSafariPointer)}</span>
      </div>
    ` : '';

    modal.innerHTML = `
      <div class="pm-pwa-modal-card">
        <div class="pm-pwa-modal-bar"></div>
        <button type="button" class="pm-pwa-modal-close" id="pmModalCloseBtn" aria-label="Kapat">✕</button>

        <div class="pm-pwa-modal-head">
          <div class="pm-pwa-modal-icon-badge">
            <img src="logo.svg" alt="Prismatch" width="46" height="46" />
          </div>
          <div>
            <h3 class="pm-pwa-modal-title">${escapeHtml(i18n.iosModalTitle)}</h3>
            <p class="pm-pwa-modal-subtitle">${escapeHtml(i18n.iosModalSubtitle)}</p>
          </div>
        </div>

        <div class="pm-pwa-steps">
          <div class="pm-pwa-step-item">
            <div class="pm-pwa-step-icon pm-pwa-step-icon-ios">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path>
                <polyline points="16 6 12 2 8 6"></polyline>
                <line x1="12" y1="2" x2="12" y2="15"></line>
              </svg>
            </div>
            <div class="pm-pwa-step-content">
              <div class="pm-pwa-step-title">${escapeHtml(i18n.iosStep1Title)}</div>
              <div class="pm-pwa-step-desc">${escapeHtml(i18n.iosStep1Desc)}</div>
            </div>
          </div>

          <div class="pm-pwa-step-item">
            <div class="pm-pwa-step-icon pm-pwa-step-icon-ios">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="18" height="18" rx="4"></rect>
                <line x1="12" y1="8" x2="12" y2="16"></line>
                <line x1="8" y1="12" x2="16" y2="12"></line>
              </svg>
            </div>
            <div class="pm-pwa-step-content">
              <div class="pm-pwa-step-title">${escapeHtml(i18n.iosStep2Title)}</div>
              <div class="pm-pwa-step-desc">${escapeHtml(i18n.iosStep2Desc)}</div>
            </div>
          </div>

          <div class="pm-pwa-step-item">
            <div class="pm-pwa-step-icon pm-pwa-step-icon-ios">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
            </div>
            <div class="pm-pwa-step-content">
              <div class="pm-pwa-step-title">${escapeHtml(i18n.iosStep3Title)}</div>
              <div class="pm-pwa-step-desc">${escapeHtml(i18n.iosStep3Desc)}</div>
            </div>
          </div>
        </div>

        ${pointerHtml}

        <div class="pm-pwa-modal-footer">
          <button type="button" class="btn pm-pwa-modal-btn-confirm" id="pmModalConfirmBtn">
            ${escapeHtml(i18n.modalCloseBtn)}
          </button>
        </div>
      </div>
    `;

    document.body.appendChild(modal);
    activeModal = modal;

    requestAnimationFrame(() => {
      modal.classList.add('pm-pwa-modal-visible');
    });

    bindModalEvents(modal);
  }

  // 10. Android Install Guide Modal (When native prompt isn't supported / ready)
  function showAndroidInstallGuide() {
    closeInstallModal();

    const modal = document.createElement('div');
    modal.id = 'pmInstallGuideModal';
    modal.className = 'pm-pwa-modal-backdrop';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');

    modal.innerHTML = `
      <div class="pm-pwa-modal-card">
        <div class="pm-pwa-modal-bar"></div>
        <button type="button" class="pm-pwa-modal-close" id="pmModalCloseBtn" aria-label="Kapat">✕</button>

        <div class="pm-pwa-modal-head">
          <div class="pm-pwa-modal-icon-badge">
            <img src="logo.svg" alt="Prismatch" width="46" height="46" />
          </div>
          <div>
            <h3 class="pm-pwa-modal-title">${escapeHtml(i18n.androidModalTitle)}</h3>
            <p class="pm-pwa-modal-subtitle">${escapeHtml(i18n.androidModalSubtitle)}</p>
          </div>
        </div>

        <div class="pm-pwa-steps">
          <div class="pm-pwa-step-item">
            <div class="pm-pwa-step-icon pm-pwa-step-icon-android">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                <circle cx="12" cy="5" r="2.2"></circle>
                <circle cx="12" cy="12" r="2.2"></circle>
                <circle cx="12" cy="19" r="2.2"></circle>
              </svg>
            </div>
            <div class="pm-pwa-step-content">
              <div class="pm-pwa-step-title">${escapeHtml(i18n.androidStep1Title)}</div>
              <div class="pm-pwa-step-desc">${escapeHtml(i18n.androidStep1Desc)}</div>
            </div>
          </div>

          <div class="pm-pwa-step-item">
            <div class="pm-pwa-step-icon pm-pwa-step-icon-android">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
              </svg>
            </div>
            <div class="pm-pwa-step-content">
              <div class="pm-pwa-step-title">${escapeHtml(i18n.androidStep2Title)}</div>
              <div class="pm-pwa-step-desc">${escapeHtml(i18n.androidStep2Desc)}</div>
            </div>
          </div>

          <div class="pm-pwa-step-item">
            <div class="pm-pwa-step-icon pm-pwa-step-icon-android">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
            </div>
            <div class="pm-pwa-step-content">
              <div class="pm-pwa-step-title">${escapeHtml(i18n.androidStep3Title)}</div>
              <div class="pm-pwa-step-desc">${escapeHtml(i18n.androidStep3Desc)}</div>
            </div>
          </div>
        </div>

        <div class="pm-pwa-modal-footer">
          <button type="button" class="btn pm-pwa-modal-btn-confirm" id="pmModalConfirmBtn">
            ${escapeHtml(i18n.modalCloseBtn)}
          </button>
        </div>
      </div>
    `;

    document.body.appendChild(modal);
    activeModal = modal;

    requestAnimationFrame(() => {
      modal.classList.add('pm-pwa-modal-visible');
    });

    bindModalEvents(modal);
  }

  function bindModalEvents(modal) {
    const close = () => closeInstallModal();

    modal.querySelector('#pmModalCloseBtn')?.addEventListener('click', close);
    modal.querySelector('#pmModalConfirmBtn')?.addEventListener('click', close);

    modal.addEventListener('click', (e) => {
      if (e.target === modal) close();
    });

    const onKey = (e) => {
      if (e.key === 'Escape') {
        close();
        window.removeEventListener('keydown', onKey);
      }
    };
    window.addEventListener('keydown', onKey);
  }

  function closeInstallModal() {
    if (activeModal) {
      activeModal.classList.remove('pm-pwa-modal-visible');
      const target = activeModal;
      activeModal = null;
      setTimeout(() => target.remove(), 260);
    }
  }

  // 11. Network Status Notification
  function updateNetworkStatus() {
    const isOnline = navigator.onLine;
    let netIndicator = document.getElementById('pmNetworkStatus');

    if (!isOnline) {
      if (!netIndicator) {
        netIndicator = document.createElement('div');
        netIndicator.id = 'pmNetworkStatus';
        netIndicator.className = 'pm-network-offline';
        netIndicator.innerHTML = `
          <span>⚡</span>
          <span>${escapeHtml(i18n.offlineMsg)}</span>
        `;
        document.body.appendChild(netIndicator);
      }
    } else {
      if (netIndicator) {
        netIndicator.className = 'pm-network-online';
        netIndicator.innerHTML = `
          <span>✅</span>
          <span>${escapeHtml(i18n.onlineMsg)}</span>
        `;
        setTimeout(() => {
          if (netIndicator) netIndicator.remove();
        }, 3200);
      }
    }
  }

  window.addEventListener('online', updateNetworkStatus);
  window.addEventListener('offline', updateNetworkStatus);

  // 12. Offline Score Sync Notification
  window.addEventListener('prismatch:scores-synced', (e) => {
    const count = e.detail?.count || 1;
    const msg = isTr
      ? `🔄 ${count} çevrimdışı skorunuz başarıyla eşitlendi!`
      : `🔄 ${count} offline score(s) successfully synced!`;
    showToastNotification(msg);
  });

  // 13. Toast Notification Utility
  function showToastNotification(message) {
    const toast = document.createElement('div');
    toast.className = 'pm-pwa-toast';
    toast.textContent = message;
    document.body.appendChild(toast);

    requestAnimationFrame(() => {
      toast.classList.add('show');
    });

    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 300);
    }, 3600);
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  // 14. Inject Comprehensive PWA Styles
  const pwaStyles = document.createElement('style');
  pwaStyles.id = 'pmPwaStyles';
  pwaStyles.textContent = `
    /* Floating Install Banner */
    .pm-pwa-banner {
      position: fixed;
      bottom: max(16px, env(safe-area-inset-bottom));
      left: 50%;
      transform: translateX(-50%) translateY(30px);
      width: min(460px, calc(100% - 24px));
      background: linear-gradient(135deg, rgba(14, 18, 30, 0.96), rgba(20, 26, 42, 0.94));
      border: 1px solid rgba(255, 107, 91, 0.45);
      border-radius: 20px;
      padding: 12px 14px;
      box-shadow: 0 16px 40px rgba(0, 0, 0, 0.55), 0 0 20px rgba(255, 107, 91, 0.15);
      z-index: 10000;
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      opacity: 0;
      transition: opacity 0.28s cubic-bezier(0.16, 1, 0.3, 1), transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
      box-sizing: border-box;
    }
    .pm-pwa-banner.pm-pwa-banner-visible {
      opacity: 1;
      transform: translateX(-50%) translateY(0);
    }
    .pm-pwa-banner-inner {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .pm-pwa-banner-logo-wrap {
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .pm-pwa-banner-icon {
      border-radius: 12px;
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.35);
    }
    .pm-pwa-banner-text {
      flex: 1;
      min-width: 0;
    }
    .pm-pwa-banner-title {
      font-weight: 700;
      font-size: 14px;
      color: #fff;
      line-height: 1.25;
      margin-bottom: 2px;
      letter-spacing: -0.2px;
    }
    .pm-pwa-banner-sub {
      font-size: 11.5px;
      color: rgba(255, 255, 255, 0.72);
      line-height: 1.25;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .pm-pwa-banner-actions {
      display: flex;
      align-items: center;
      gap: 6px;
      flex-shrink: 0;
    }
    .pm-pwa-btn-install {
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      background: linear-gradient(135deg, #ff6b5b, #ff8c42) !important;
      border: none !important;
      font-weight: 700 !important;
      font-size: 12.5px !important;
      padding: 7px 13px !important;
      border-radius: 999px !important;
      color: #fff !important;
      box-shadow: 0 4px 14px rgba(255, 107, 91, 0.35);
      cursor: pointer;
      transition: transform 0.15s ease, box-shadow 0.15s ease;
      white-space: nowrap;
    }
    .pm-pwa-btn-install:active {
      transform: scale(0.96);
    }
    .pm-pwa-btn-dismiss {
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.12);
      color: rgba(255, 255, 255, 0.65);
      font-size: 13px;
      width: 28px;
      height: 28px;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      padding: 0;
      transition: background 0.15s ease, color 0.15s ease;
    }
    .pm-pwa-btn-dismiss:hover {
      background: rgba(255, 255, 255, 0.16);
      color: #fff;
    }

    /* Install Guide Modal Backdrop & Card */
    .pm-pwa-modal-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(8, 12, 22, 0.78);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      z-index: 100000;
      display: flex;
      align-items: flex-end;
      justify-content: center;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.26s cubic-bezier(0.16, 1, 0.3, 1);
      padding: 0;
    }
    @media (min-width: 576px) {
      .pm-pwa-modal-backdrop {
        align-items: center;
        padding: 20px;
      }
    }
    .pm-pwa-modal-backdrop.pm-pwa-modal-visible {
      opacity: 1;
      pointer-events: auto;
    }
    .pm-pwa-modal-card {
      position: relative;
      width: 100%;
      max-width: 480px;
      background: linear-gradient(180deg, rgba(16, 22, 36, 0.98), rgba(11, 15, 26, 0.98));
      border: 1px solid rgba(255, 107, 91, 0.35);
      border-radius: 26px 26px 0 0;
      padding: 20px 22px 28px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.65), 0 0 35px rgba(255, 107, 91, 0.18);
      transform: translateY(40px);
      transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
      box-sizing: border-box;
      max-height: 90vh;
      overflow-y: auto;
    }
    @media (min-width: 576px) {
      .pm-pwa-modal-card {
        border-radius: 26px;
        transform: scale(0.96) translateY(10px);
        padding: 26px 26px 28px;
      }
      .pm-pwa-modal-backdrop.pm-pwa-modal-visible .pm-pwa-modal-card {
        transform: scale(1) translateY(0);
      }
    }
    .pm-pwa-modal-backdrop.pm-pwa-modal-visible .pm-pwa-modal-card {
      transform: translateY(0);
    }
    .pm-pwa-modal-bar {
      width: 42px;
      height: 4px;
      background: rgba(255, 255, 255, 0.22);
      border-radius: 999px;
      margin: 0 auto 16px;
    }
    @media (min-width: 576px) {
      .pm-pwa-modal-bar { display: none; }
    }
    .pm-pwa-modal-close {
      position: absolute;
      top: 18px;
      right: 18px;
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.12);
      color: rgba(255, 255, 255, 0.7);
      font-size: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.15s ease;
    }
    .pm-pwa-modal-close:hover {
      background: rgba(255, 255, 255, 0.18);
      color: #fff;
    }
    .pm-pwa-modal-head {
      display: flex;
      align-items: center;
      gap: 14px;
      margin-bottom: 20px;
      padding-right: 34px;
    }
    .pm-pwa-modal-icon-badge {
      width: 54px;
      height: 54px;
      border-radius: 16px;
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(255, 107, 91, 0.3);
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 8px 24px rgba(255, 107, 91, 0.2);
      flex-shrink: 0;
    }
    .pm-pwa-modal-title {
      font-size: 18px;
      font-weight: 800;
      color: #fff;
      margin: 0 0 3px 0;
      letter-spacing: -0.3px;
    }
    .pm-pwa-modal-subtitle {
      font-size: 12.5px;
      color: rgba(255, 255, 255, 0.72);
      margin: 0;
      line-height: 1.35;
    }

    /* Steps */
    .pm-pwa-steps {
      display: flex;
      flex-direction: column;
      gap: 10px;
      margin-bottom: 20px;
    }
    .pm-pwa-step-item {
      display: flex;
      align-items: flex-start;
      gap: 13px;
      background: rgba(255, 255, 255, 0.04);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 16px;
      padding: 12px 14px;
      transition: background 0.15s ease;
    }
    .pm-pwa-step-item:hover {
      background: rgba(255, 255, 255, 0.07);
    }
    .pm-pwa-step-icon {
      width: 38px;
      height: 38px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .pm-pwa-step-icon-ios {
      background: linear-gradient(135deg, rgba(0, 122, 255, 0.2), rgba(88, 86, 214, 0.2));
      border: 1px solid rgba(0, 122, 255, 0.4);
      color: #5ac8fa;
    }
    .pm-pwa-step-icon-android {
      background: linear-gradient(135deg, rgba(52, 168, 83, 0.2), rgba(66, 133, 244, 0.2));
      border: 1px solid rgba(52, 168, 83, 0.4);
      color: #34d399;
    }
    .pm-pwa-step-content {
      flex: 1;
      min-width: 0;
    }
    .pm-pwa-step-title {
      font-size: 13.5px;
      font-weight: 700;
      color: #fff;
      margin-bottom: 2px;
    }
    .pm-pwa-step-desc {
      font-size: 12px;
      color: rgba(255, 255, 255, 0.72);
      line-height: 1.35;
    }

    /* Safari Bottom Pointer Callout */
    .pm-pwa-ios-pointer {
      display: flex;
      align-items: center;
      gap: 10px;
      background: linear-gradient(135deg, rgba(255, 107, 91, 0.14), rgba(255, 140, 66, 0.14));
      border: 1px dashed rgba(255, 107, 91, 0.45);
      border-radius: 14px;
      padding: 10px 14px;
      font-size: 12px;
      font-weight: 600;
      color: #ff9d84;
      margin-bottom: 18px;
    }
    .pm-pwa-ios-pointer-arrow {
      font-size: 16px;
      animation: pmArrowBounce 1.2s infinite ease-in-out;
      display: inline-block;
    }
    @keyframes pmArrowBounce {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(4px); }
    }

    /* Modal Footer */
    .pm-pwa-modal-footer {
      display: flex;
      justify-content: flex-end;
    }
    .pm-pwa-modal-btn-confirm {
      width: 100%;
      background: linear-gradient(135deg, #ff6b5b, #ff8c42) !important;
      border: none !important;
      color: #fff !important;
      font-weight: 700 !important;
      font-size: 14px !important;
      padding: 11px 20px !important;
      border-radius: 14px !important;
      box-shadow: 0 6px 20px rgba(255, 107, 91, 0.35);
      cursor: pointer;
    }

    /* Network Offline & Online Banners */
    .pm-network-offline, .pm-network-online {
      position: fixed;
      top: 10px;
      left: 50%;
      transform: translateX(-50%);
      width: min(540px, calc(100% - 24px));
      padding: 10px 18px;
      border-radius: 999px;
      font-size: 13.5px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 10px;
      z-index: 100000;
      box-shadow: 0 12px 30px rgba(0, 0, 0, 0.4);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      animation: pmSlideDown 0.3s ease;
    }
    .pm-network-offline {
      background: rgba(220, 38, 38, 0.94);
      border: 1px solid rgba(255, 255, 255, 0.2);
      color: #fff;
    }
    .pm-network-online {
      background: rgba(16, 185, 129, 0.94);
      border: 1px solid rgba(255, 255, 255, 0.2);
      color: #fff;
    }

    /* Toast */
    .pm-pwa-toast {
      position: fixed;
      bottom: max(24px, env(safe-area-inset-bottom));
      left: 50%;
      transform: translateX(-50%) translateY(20px);
      background: rgba(18, 24, 38, 0.95);
      border: 1px solid rgba(255, 255, 255, 0.18);
      border-radius: 999px;
      padding: 10px 22px;
      font-size: 13.5px;
      font-weight: 600;
      color: #fff;
      box-shadow: 0 14px 36px rgba(0, 0, 0, 0.45);
      z-index: 100000;
      opacity: 0;
      transition: all 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      pointer-events: none;
    }
    .pm-pwa-toast.show {
      opacity: 1;
      transform: translateX(-50%) translateY(0);
    }

    @keyframes pmSlideDown {
      from { transform: translateX(-50%) translateY(-20px); opacity: 0; }
      to { transform: translateX(-50%) translateY(0); opacity: 1; }
    }
  `;
  document.head.appendChild(pwaStyles);

  // 15. Initial Execution on DOM Ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPwa);
  } else {
    initPwa();
  }

  function initPwa() {
    setupHeaderInstallButton();

    // Auto-prompt banner for Android / iOS mobile users
    if (env.isMobile && !env.isStandalone && !isInstallDismissed()) {
      scheduleBannerDisplay();
    }
  }

  // Export globally for programmatic opening (e.g. from any settings/help button)
  window.PrismatchPWA = {
    showIosInstallGuide,
    showAndroidInstallGuide,
    handleInstallClick,
    getEnvironment
  };
})();
