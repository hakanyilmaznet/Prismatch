/**
 * Prismatch - Progressive Web App (PWA) Install Manager & Mobile Guidance
 * Prompts users on Android and iOS (Safari "Ana Ekrana Ekle") to install the app.
 */
(function () {
  'use strict';

  let deferredInstallPrompt = null;
  let activeModal = null;

  // Clear legacy dismissal keys if any
  try {
    sessionStorage.removeItem('pm_pwa_install_dismissed');
  } catch (_) {}

  // 1. Environment & Device Detection
  function getEnvironment() {
    const ua = navigator.userAgent || navigator.vendor || window.opera || '';
    const platform = navigator.platform || '';
    const search = window.location.search || '';

    // URL overrides for testing (?pwa=ios or ?pwa=android or ?pwa=1)
    const forceIos = search.includes('pwa=ios') || search.includes('ios=1');
    const forceAndroid = search.includes('pwa=android') || search.includes('android=1');
    const forceShow = search.includes('pwa=') || search.includes('install=1');

    // iOS detection (iPhone, iPad, iPod, iPadOS on MacIntel)
    const isIOS = forceIos ||
                  /iPad|iPhone|iPod/.test(ua) ||
                  (platform === 'MacIntel' && navigator.maxTouchPoints > 1) ||
                  (/Macintosh/.test(ua) && 'ontouchend' in document);

    // Android detection
    const isAndroid = forceAndroid || (!isIOS && /Android/i.test(ua));

    // General mobile detection (including responsive devtools or tablet)
    const isMobile = isIOS || isAndroid || /Mobi|Tablet|Touch|Mobile/i.test(ua) || (window.innerWidth <= 820) || forceShow;

    // Safari on iOS detection
    const isIOSSafari = isIOS && /Safari/i.test(ua) && !/CriOS|FxiOS|OPiOS|mercury/i.test(ua);

    // Standalone check (already installed PWA)
    const isStandalone = !forceShow && (
      window.matchMedia('(display-mode: standalone)').matches ||
      window.navigator.standalone === true ||
      document.referrer.includes('android-app://')
    );

    return {
      isIOS,
      isAndroid,
      isMobile,
      isIOSSafari,
      isStandalone,
      forceShow
    };
  }

  const env = getEnvironment();

  // 2. Translations (Turkish & English)
  const langAttr = (document.documentElement.lang || 'tr').toLowerCase();
  const isTr = langAttr.startsWith('tr');

  const i18n = {
    // iOS Prompt
    iosTitle: isTr ? "Prismatch'i Ana Ekrana Ekleyin" : "Add Prismatch to Home Screen",
    iosSubtitle: isTr
      ? "Safari üzerinden birkaç saniyede ana ekranınıza ekleyerek tam ekran ve kesintisiz uygulama hızında oynayın."
      : "Add to your home screen in seconds via Safari for a full-screen, native app experience.",
    iosStep1Title: isTr ? "1. Paylaş Butonuna Dokunun" : "1. Tap the Share Button",
    iosStep1Desc: isTr
      ? "Safari'nin alt araç çubuğunda (veya iPad'de üstte) bulunan Paylaş simgesine dokunun."
      : "Tap the Share icon on the Safari bottom bar (or top right on iPad).",
    iosStep2Title: isTr ? "2. 'Ana Ekrana Ekle'yi Seçin" : "2. Tap 'Add to Home Screen'",
    iosStep2Desc: isTr
      ? "Açılan paylaşım sayfasını aşağı kaydırıp 'Ana Ekrana Ekle' seçeneğine dokunun."
      : "Scroll down the share sheet and tap 'Add to Home Screen'.",
    iosStep3Title: isTr ? "3. Sağ Üstten 'Ekle'ye Basın" : "3. Tap 'Add' in Top Right",
    iosStep3Desc: isTr
      ? "Sağ üst köşedeki 'Ekle' yazısına dokunun. Prismatch artık ana ekranınızda hazır!"
      : "Tap 'Add' in the top right corner. Prismatch is now installed on your home screen!",
    iosSafariPointer: isTr
      ? "Aşağıdaki Safari araç çubuğunda yer alan Paylaş simgesine dokunun ↓"
      : "Tap the Share button in the Safari toolbar below ↓",

    // Android Prompt
    androidTitle: isTr ? "Prismatch'i Uygulama Olarak Yükleyin" : "Install Prismatch App",
    androidSubtitle: isTr
      ? "Hızlı erişim, tam ekran oyun alanı ve çevrimdışı oynama deneyimi için uygulamayı telefonunuza yükleyin."
      : "Install the app for instant access, full-screen gameplay, and offline mode.",
    androidBtnInstall: isTr ? "Uygulamayı Yükle" : "Install App",
    androidStep1Title: isTr ? "1. Tarayıcı Menüsünü Açın" : "1. Open Browser Menu",
    androidStep1Desc: isTr
      ? "Tarayıcınızın sağ üst köşesindeki üç nokta (⋮) simgesine dokunun."
      : "Tap the three dots (⋮) menu in the top-right corner of your browser.",
    androidStep2Title: isTr ? "2. 'Uygulamayı Yükle'yi Seçin" : "2. Select 'Install App'",
    androidStep2Desc: isTr
      ? "Menüden 'Uygulamayı Yükle' veya 'Ana Ekrana Ekle' seçeneğine dokunun."
      : "Choose 'Install App' or 'Add to Home Screen' from the menu.",
    androidStep3Title: isTr ? "3. Kurulumu Onaylayın" : "3. Confirm Installation",
    androidStep3Desc: isTr
      ? "Çıkan onay kutusunda 'Yükle'ye basarak kurulumu tamamlayın."
      : "Tap 'Install' to add Prismatch directly to your device apps.",

    // General
    btnLater: isTr ? "Daha Sonra" : "Maybe Later",
    btnGotIt: isTr ? "Tamam, Anladım" : "Got It",
    successInstalled: isTr ? "🎉 Prismatch başarıyla cihazınıza yüklendi!" : "🎉 Prismatch was installed successfully!",
    offlineNotice: isTr
      ? "⚡ Çevrimdışı moddasınız. Skorlarınız internet bağlantısı sağlandığında otomatik senkronize edilecektir."
      : "⚡ You are offline. Scores will automatically sync when connection is restored.",
    onlineNotice: isTr
      ? "✅ İnternet bağlantısı sağlandı. Skorlarınız eşitleniyor..."
      : "✅ Back online. Syncing scores...",
    updatedNotice: isTr
      ? "🚀 Prismatch güncellendi! Yeni sürüm için sayfayı yenileyin."
      : "🚀 Prismatch updated! Refresh to enjoy new features."
  };

  // 3. Service Worker
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js', { scope: '/' })
        .then((reg) => {
          reg.addEventListener('updatefound', () => {
            const worker = reg.installing;
            if (worker) {
              worker.addEventListener('statechange', () => {
                if (worker.state === 'installed' && navigator.serviceWorker.controller) {
                  showToast(i18n.updatedNotice);
                }
              });
            }
          });
        })
        .catch(() => {});
    });
  }

  // 4. Native Install Prompt Capture (beforeinstallprompt - Android / Chrome)
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredInstallPrompt = e;
    setupHeaderButton();

    // Auto-prompt on Android if not already standalone or dismissed
    if (!env.isStandalone && (env.isAndroid || env.isMobile)) {
      if (!isDismissed() || env.forceShow) {
        showPrompt();
      }
    }
  });

  window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = null;
    closeModal();
    hideFloatingBanner();

    const btn = document.getElementById('pwaInstallBtn');
    if (btn) btn.style.display = 'none';

    showToast(i18n.successInstalled);
  });

  // 5. Setup Header Button
  function setupHeaderButton() {
    const btn = document.getElementById('pwaInstallBtn');
    if (!btn) return;

    if (env.isStandalone) {
      btn.style.display = 'none';
      return;
    }

    btn.style.display = 'inline-flex';
    btn.onclick = (e) => {
      e.preventDefault();
      triggerDirectInstallGuide();
    };
  }

  // 6. Dismissal Management (24 hours suppression when dismissed)
  function isDismissed() {
    if (env.forceShow) return false;
    try {
      const until = localStorage.getItem('pm_pwa_dismissed_v2');
      if (until && Number(until) > Date.now()) {
        return true;
      }
    } catch (_) {}
    return false;
  }

  function setDismissed() {
    try {
      // Suppress auto-popup for 24 hours
      const nextTime = Date.now() + 24 * 60 * 60 * 1000;
      localStorage.setItem('pm_pwa_dismissed_v2', nextTime.toString());
    } catch (_) {}
  }

  // 7. Show Appropriate Guide / Prompt
  function showPrompt() {
    if (env.isStandalone) return;
    if (activeModal) return;

    if (env.isIOS) {
      showIosModal();
    } else {
      showAndroidModal();
    }
  }

  function triggerDirectInstallGuide() {
    if (env.isIOS) {
      showIosModal();
      return;
    }

    if (deferredInstallPrompt) {
      deferredInstallPrompt.prompt();
      deferredInstallPrompt.userChoice.then((choice) => {
        if (choice && choice.outcome === 'accepted') {
          closeModal();
          hideFloatingBanner();
        }
        deferredInstallPrompt = null;
      }).catch(() => {
        showAndroidModal();
      });
      return;
    }

    showAndroidModal();
  }

  // 8. iOS Modal: Step-by-step "Add to Home Screen"
  function showIosModal() {
    closeModal();

    const modal = document.createElement('div');
    modal.id = 'pmPwaModal';
    modal.className = 'pm-pwa-overlay';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');

    const pointerHtml = env.isIOSSafari ? `
      <div class="pm-pwa-ios-callout">
        <span class="pm-pwa-pointer-bounce">↓</span>
        <span>${escapeHtml(i18n.iosSafariPointer)}</span>
      </div>
    ` : '';

    modal.innerHTML = `
      <div class="pm-pwa-sheet">
        <div class="pm-pwa-sheet-handle"></div>
        <button type="button" class="pm-pwa-sheet-close" id="pmModalClose" aria-label="Kapat">✕</button>

        <div class="pm-pwa-sheet-header">
          <img src="logo.svg" alt="Prismatch" width="52" height="52" class="pm-pwa-sheet-logo" />
          <div class="pm-pwa-sheet-title-group">
            <div class="pm-pwa-sheet-badge">iOS & Safari</div>
            <h3 class="pm-pwa-sheet-title">${escapeHtml(i18n.iosTitle)}</h3>
            <p class="pm-pwa-sheet-sub">${escapeHtml(i18n.iosSubtitle)}</p>
          </div>
        </div>

        <div class="pm-pwa-steps-list">
          <div class="pm-pwa-step">
            <div class="pm-pwa-step-num">1</div>
            <div class="pm-pwa-step-icon pm-pwa-icon-ios">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path>
                <polyline points="16 6 12 2 8 6"></polyline>
                <line x1="12" y1="2" x2="12" y2="15"></line>
              </svg>
            </div>
            <div class="pm-pwa-step-body">
              <div class="pm-pwa-step-h">${escapeHtml(i18n.iosStep1Title)}</div>
              <div class="pm-pwa-step-p">${escapeHtml(i18n.iosStep1Desc)}</div>
            </div>
          </div>

          <div class="pm-pwa-step">
            <div class="pm-pwa-step-num">2</div>
            <div class="pm-pwa-step-icon pm-pwa-icon-ios">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="18" height="18" rx="4"></rect>
                <line x1="12" y1="8" x2="12" y2="16"></line>
                <line x1="8" y1="12" x2="16" y2="12"></line>
              </svg>
            </div>
            <div class="pm-pwa-step-body">
              <div class="pm-pwa-step-h">${escapeHtml(i18n.iosStep2Title)}</div>
              <div class="pm-pwa-step-p">${escapeHtml(i18n.iosStep2Desc)}</div>
            </div>
          </div>

          <div class="pm-pwa-step">
            <div class="pm-pwa-step-num">3</div>
            <div class="pm-pwa-step-icon pm-pwa-icon-ios">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
            </div>
            <div class="pm-pwa-step-body">
              <div class="pm-pwa-step-h">${escapeHtml(i18n.iosStep3Title)}</div>
              <div class="pm-pwa-step-p">${escapeHtml(i18n.iosStep3Desc)}</div>
            </div>
          </div>
        </div>

        ${pointerHtml}

        <div class="pm-pwa-sheet-footer">
          <button type="button" class="btn btn-secondary pm-pwa-btn-secondary" id="pmModalLater">${escapeHtml(i18n.btnLater)}</button>
          <button type="button" class="btn btn-primary pm-pwa-btn-primary" id="pmModalGotIt">${escapeHtml(i18n.btnGotIt)}</button>
        </div>
      </div>
    `;

    document.body.appendChild(modal);
    activeModal = modal;

    // Trigger open animation
    setTimeout(() => {
      modal.classList.add('pm-pwa-open');
    }, 20);

    bindModalEvents(modal);
  }

  // 9. Android Modal: One-click Install or Manual Guide
  function showAndroidModal() {
    closeModal();

    const modal = document.createElement('div');
    modal.id = 'pmPwaModal';
    modal.className = 'pm-pwa-overlay';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');

    const hasNativePrompt = Boolean(deferredInstallPrompt);

    const stepsHtml = !hasNativePrompt ? `
      <div class="pm-pwa-steps-list">
        <div class="pm-pwa-step">
          <div class="pm-pwa-step-num">1</div>
          <div class="pm-pwa-step-icon pm-pwa-icon-android">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
              <circle cx="12" cy="5" r="2.2"></circle>
              <circle cx="12" cy="12" r="2.2"></circle>
              <circle cx="12" cy="19" r="2.2"></circle>
            </svg>
          </div>
          <div class="pm-pwa-step-body">
            <div class="pm-pwa-step-h">${escapeHtml(i18n.androidStep1Title)}</div>
            <div class="pm-pwa-step-p">${escapeHtml(i18n.androidStep1Desc)}</div>
          </div>
        </div>

        <div class="pm-pwa-step">
          <div class="pm-pwa-step-num">2</div>
          <div class="pm-pwa-step-icon pm-pwa-icon-android">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
              <polyline points="7 10 12 15 17 10"></polyline>
              <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
          </div>
          <div class="pm-pwa-step-body">
            <div class="pm-pwa-step-h">${escapeHtml(i18n.androidStep2Title)}</div>
            <div class="pm-pwa-step-p">${escapeHtml(i18n.androidStep2Desc)}</div>
          </div>
        </div>

        <div class="pm-pwa-step">
          <div class="pm-pwa-step-num">3</div>
          <div class="pm-pwa-step-icon pm-pwa-icon-android">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
          </div>
          <div class="pm-pwa-step-body">
            <div class="pm-pwa-step-h">${escapeHtml(i18n.androidStep3Title)}</div>
            <div class="pm-pwa-step-p">${escapeHtml(i18n.androidStep3Desc)}</div>
          </div>
        </div>
      </div>
    ` : '';

    modal.innerHTML = `
      <div class="pm-pwa-sheet">
        <div class="pm-pwa-sheet-handle"></div>
        <button type="button" class="pm-pwa-sheet-close" id="pmModalClose" aria-label="Kapat">✕</button>

        <div class="pm-pwa-sheet-header">
          <img src="logo.svg" alt="Prismatch" width="52" height="52" class="pm-pwa-sheet-logo" />
          <div class="pm-pwa-sheet-title-group">
            <div class="pm-pwa-sheet-badge">Android</div>
            <h3 class="pm-pwa-sheet-title">${escapeHtml(i18n.androidTitle)}</h3>
            <p class="pm-pwa-sheet-sub">${escapeHtml(i18n.androidSubtitle)}</p>
          </div>
        </div>

        ${stepsHtml}

        <div class="pm-pwa-sheet-footer">
          <button type="button" class="btn btn-secondary pm-pwa-btn-secondary" id="pmModalLater">${escapeHtml(i18n.btnLater)}</button>
          <button type="button" class="btn btn-primary pm-pwa-btn-primary" id="pmAndroidInstallAction">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
              <polyline points="7 10 12 15 17 10"></polyline>
              <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            ${escapeHtml(hasNativePrompt ? i18n.androidBtnInstall : i18n.btnGotIt)}
          </button>
        </div>
      </div>
    `;

    document.body.appendChild(modal);
    activeModal = modal;

    setTimeout(() => {
      modal.classList.add('pm-pwa-open');
    }, 20);

    modal.querySelector('#pmAndroidInstallAction')?.addEventListener('click', () => {
      if (deferredInstallPrompt) {
        deferredInstallPrompt.prompt();
        deferredInstallPrompt.userChoice.then(() => {
          deferredInstallPrompt = null;
          closeModal();
        });
      } else {
        closeModal();
      }
    });

    bindModalEvents(modal);
  }

  function bindModalEvents(modal) {
    const handleDismiss = () => {
      setDismissed();
      closeModal();
    };

    modal.querySelector('#pmModalClose')?.addEventListener('click', handleDismiss);
    modal.querySelector('#pmModalLater')?.addEventListener('click', handleDismiss);
    modal.querySelector('#pmModalGotIt')?.addEventListener('click', handleDismiss);

    modal.addEventListener('click', (e) => {
      if (e.target === modal) handleDismiss();
    });

    const onKey = (e) => {
      if (e.key === 'Escape') {
        handleDismiss();
        window.removeEventListener('keydown', onKey);
      }
    };
    window.addEventListener('keydown', onKey);
  }

  function closeModal() {
    if (activeModal) {
      activeModal.classList.remove('pm-pwa-open');
      const cur = activeModal;
      activeModal = null;
      setTimeout(() => cur.remove(), 280);
    }
  }

  function hideFloatingBanner() {
    const banner = document.getElementById('pmFloatingInstallBanner');
    if (banner) banner.remove();
  }

  // 10. Toast Utility
  function showToast(msg) {
    const toast = document.createElement('div');
    toast.className = 'pm-pwa-toast';
    toast.textContent = msg;
    document.body.appendChild(toast);

    requestAnimationFrame(() => toast.classList.add('pm-show'));
    setTimeout(() => {
      toast.classList.remove('pm-show');
      setTimeout(() => toast.remove(), 300);
    }, 3800);
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

  // 11. Offline / Online Status Listeners
  window.addEventListener('online', () => {
    showToast(i18n.onlineNotice);
  });
  window.addEventListener('offline', () => {
    showToast(i18n.offlineNotice);
  });

  // Offline score sync listener
  window.addEventListener('prismatch:scores-synced', (e) => {
    const count = e.detail?.count || 1;
    showToast(isTr ? `🔄 ${count} skor senkronize edildi!` : `🔄 ${count} score(s) synced!`);
  });

  // 12. Modern CSS Injection
  const styles = document.createElement('style');
  styles.id = 'pmPwaModernStyles';
  styles.textContent = `
    /* Overlay Backdrop */
    .pm-pwa-overlay {
      position: fixed;
      inset: 0;
      background: rgba(6, 10, 18, 0.76);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      z-index: 999999;
      display: flex;
      align-items: flex-end;
      justify-content: center;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.28s cubic-bezier(0.16, 1, 0.3, 1);
      padding: 0;
    }
    @media (min-width: 576px) {
      .pm-pwa-overlay {
        align-items: center;
        padding: 20px;
      }
    }
    .pm-pwa-overlay.pm-pwa-open {
      opacity: 1;
      pointer-events: auto;
    }

    /* Bottom Sheet Card */
    .pm-pwa-sheet {
      position: relative;
      width: 100%;
      max-width: 500px;
      background: linear-gradient(180deg, #131a2b, #0c101d);
      border: 1px solid rgba(255, 107, 91, 0.4);
      border-radius: 28px 28px 0 0;
      padding: 20px 22px 28px;
      box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.7), 0 0 35px rgba(255, 107, 91, 0.2);
      transform: translateY(100%);
      transition: transform 0.32s cubic-bezier(0.16, 1, 0.3, 1);
      box-sizing: border-box;
      max-height: 88vh;
      overflow-y: auto;
      color: #fff;
    }
    @media (min-width: 576px) {
      .pm-pwa-sheet {
        border-radius: 26px;
        transform: scale(0.94) translateY(20px);
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.7), 0 0 40px rgba(255, 107, 91, 0.22);
      }
      .pm-pwa-overlay.pm-pwa-open .pm-pwa-sheet {
        transform: scale(1) translateY(0);
      }
    }
    .pm-pwa-overlay.pm-pwa-open .pm-pwa-sheet {
      transform: translateY(0);
    }

    .pm-pwa-sheet-handle {
      width: 44px;
      height: 4px;
      background: rgba(255, 255, 255, 0.25);
      border-radius: 999px;
      margin: 0 auto 16px;
    }
    @media (min-width: 576px) {
      .pm-pwa-sheet-handle { display: none; }
    }

    .pm-pwa-sheet-close {
      position: absolute;
      top: 18px;
      right: 18px;
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.14);
      color: rgba(255, 255, 255, 0.75);
      font-size: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.15s ease;
    }
    .pm-pwa-sheet-close:hover {
      background: rgba(255, 255, 255, 0.18);
      color: #fff;
    }

    .pm-pwa-sheet-header {
      display: flex;
      align-items: center;
      gap: 15px;
      margin-bottom: 20px;
      padding-right: 36px;
    }
    .pm-pwa-sheet-logo {
      border-radius: 14px;
      box-shadow: 0 8px 24px rgba(255, 107, 91, 0.25);
      border: 1px solid rgba(255, 255, 255, 0.12);
      flex-shrink: 0;
    }
    .pm-pwa-sheet-badge {
      display: inline-block;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.6px;
      background: rgba(255, 107, 91, 0.2);
      border: 1px solid rgba(255, 107, 91, 0.45);
      color: #ff8c42;
      padding: 2px 8px;
      border-radius: 999px;
      margin-bottom: 5px;
    }
    .pm-pwa-sheet-title {
      font-size: 18px;
      font-weight: 800;
      color: #fff;
      margin: 0 0 4px 0;
      line-height: 1.25;
      letter-spacing: -0.3px;
    }
    .pm-pwa-sheet-sub {
      font-size: 12.5px;
      color: rgba(255, 255, 255, 0.72);
      margin: 0;
      line-height: 1.4;
    }

    /* Steps List */
    .pm-pwa-steps-list {
      display: flex;
      flex-direction: column;
      gap: 10px;
      margin-bottom: 22px;
    }
    .pm-pwa-step {
      display: flex;
      align-items: center;
      gap: 12px;
      background: rgba(255, 255, 255, 0.04);
      border: 1px solid rgba(255, 255, 255, 0.09);
      border-radius: 16px;
      padding: 12px 14px;
    }
    .pm-pwa-step-num {
      width: 22px;
      height: 22px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.12);
      color: #fff;
      font-size: 12px;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
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
    .pm-pwa-icon-ios {
      background: linear-gradient(135deg, rgba(10, 132, 255, 0.25), rgba(94, 92, 230, 0.25));
      border: 1px solid rgba(10, 132, 255, 0.45);
      color: #64d2ff;
    }
    .pm-pwa-icon-android {
      background: linear-gradient(135deg, rgba(52, 168, 83, 0.25), rgba(66, 133, 244, 0.25));
      border: 1px solid rgba(52, 168, 83, 0.45);
      color: #34d399;
    }
    .pm-pwa-step-body {
      flex: 1;
      min-width: 0;
    }
    .pm-pwa-step-h {
      font-size: 13.5px;
      font-weight: 700;
      color: #fff;
      margin-bottom: 2px;
    }
    .pm-pwa-step-p {
      font-size: 12px;
      color: rgba(255, 255, 255, 0.72);
      line-height: 1.35;
    }

    /* iOS Safari Bottom Callout Pointer */
    .pm-pwa-ios-callout {
      display: flex;
      align-items: center;
      gap: 10px;
      background: linear-gradient(135deg, rgba(255, 107, 91, 0.15), rgba(255, 140, 66, 0.15));
      border: 1px dashed rgba(255, 107, 91, 0.5);
      border-radius: 14px;
      padding: 10px 14px;
      font-size: 12.5px;
      font-weight: 600;
      color: #ff9d84;
      margin-bottom: 20px;
    }
    .pm-pwa-pointer-bounce {
      font-size: 17px;
      animation: pmArrowBounce 1.2s infinite ease-in-out;
      display: inline-block;
    }
    @keyframes pmArrowBounce {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(4px); }
    }

    /* Footer Actions */
    .pm-pwa-sheet-footer {
      display: flex;
      gap: 10px;
    }
    .pm-pwa-btn-secondary {
      flex: 1;
      background: rgba(255, 255, 255, 0.08) !important;
      border: 1px solid rgba(255, 255, 255, 0.14) !important;
      color: rgba(255, 255, 255, 0.8) !important;
      font-weight: 600 !important;
      font-size: 13.5px !important;
      padding: 11px !important;
      border-radius: 14px !important;
      cursor: pointer;
    }
    .pm-pwa-btn-primary {
      flex: 2;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      background: linear-gradient(135deg, #ff6b5b, #ff8c42) !important;
      border: none !important;
      color: #fff !important;
      font-weight: 700 !important;
      font-size: 14px !important;
      padding: 11px 18px !important;
      border-radius: 14px !important;
      box-shadow: 0 6px 20px rgba(255, 107, 91, 0.4);
      cursor: pointer;
    }

    /* Toast */
    .pm-pwa-toast {
      position: fixed;
      bottom: max(24px, env(safe-area-inset-bottom));
      left: 50%;
      transform: translateX(-50%) translateY(20px);
      background: rgba(18, 24, 38, 0.96);
      border: 1px solid rgba(255, 255, 255, 0.18);
      border-radius: 999px;
      padding: 10px 22px;
      font-size: 13.5px;
      font-weight: 600;
      color: #fff;
      box-shadow: 0 14px 36px rgba(0, 0, 0, 0.5);
      z-index: 9999999;
      opacity: 0;
      transition: all 0.25s ease;
      pointer-events: none;
    }
    .pm-pwa-toast.pm-show {
      opacity: 1;
      transform: translateX(-50%) translateY(0);
    }
  `;
  document.head.appendChild(styles);

  // 13. Auto-Trigger Initialization
  function init() {
    setupHeaderButton();

    // Auto-prompt on mobile (Android or iOS) if not installed as standalone PWA
    if (!env.isStandalone && env.isMobile) {
      if (!isDismissed() || env.forceShow) {
        // Trigger after a brief, smooth 450ms delay
        setTimeout(() => {
          showPrompt();
        }, 450);
      }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Global helper API for testing and manual triggers
  window.PrismatchPWA = {
    showPrompt,
    showIosModal,
    showAndroidModal,
    triggerDirectInstallGuide,
    getEnvironment
  };
})();
