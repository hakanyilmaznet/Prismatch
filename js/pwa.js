/**
 * Prismatch - Progressive Web App (PWA) Lifecycle & Install Manager
 * Handles Service Worker registration, PWA installation prompts,
 * online/offline state detection, and user sync notifications.
 */
(function () {
  'use strict';

  let deferredInstallPrompt = null;

  // 1. Service Worker Registration
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js', { scope: '/' })
        .then((reg) => {
          console.log('[PWA] Service Worker registered with scope:', reg.scope);

          // Check for worker updates
          reg.addEventListener('updatefound', () => {
            const newWorker = reg.installing;
            if (newWorker) {
              newWorker.addEventListener('statechange', () => {
                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                  showUpdateNotification();
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

  // 2. Install Prompt Handling (beforeinstallprompt)
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredInstallPrompt = e;

    // Show header install button
    const headerInstallBtn = document.getElementById('pwaInstallBtn');
    if (headerInstallBtn) {
      headerInstallBtn.classList.remove('d-none');
      headerInstallBtn.addEventListener('click', triggerInstallFlow);
    }

    // Show floating install banner on mobile if not previously dismissed
    try {
      const dismissed = sessionStorage.getItem('pm_pwa_install_dismissed');
      if (!dismissed && window.innerWidth <= 768) {
        showFloatingInstallBanner();
      }
    } catch (_) {}
  });

  window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = null;
    console.log('[PWA] Prismatch was installed successfully');
    hideFloatingInstallBanner();
    const headerInstallBtn = document.getElementById('pwaInstallBtn');
    if (headerInstallBtn) headerInstallBtn.classList.add('d-none');
    showToastNotification('🎉 Prismatch uygulaması başarıyla yüklendi!');
  });

  function triggerInstallFlow() {
    if (!deferredInstallPrompt) return;
    deferredInstallPrompt.prompt();
    deferredInstallPrompt.userChoice.then((choiceResult) => {
      if (choiceResult.outcome === 'accepted') {
        console.log('[PWA] User accepted the install prompt');
      } else {
        console.log('[PWA] User dismissed the install prompt');
      }
      deferredInstallPrompt = null;
      hideFloatingInstallBanner();
    });
  }

  // 3. Floating Install Banner
  function showFloatingInstallBanner() {
    if (document.getElementById('pmFloatingInstallBanner')) return;

    const banner = document.createElement('div');
    banner.id = 'pmFloatingInstallBanner';
    banner.className = 'pm-pwa-banner';
    banner.innerHTML = `
      <div class="pm-pwa-banner-inner">
        <img src="logo.svg" alt="Prismatch" width="40" height="40" class="pm-pwa-banner-icon" />
        <div class="pm-pwa-banner-text">
          <div class="pm-pwa-banner-title">Prismatch'i Yükleyin</div>
          <div class="pm-pwa-banner-sub">Hızlı erişim ve çevrimdışı oynama deneyimi</div>
        </div>
        <div class="pm-pwa-banner-actions">
          <button type="button" class="btn btn-sm btn-primary pm-pwa-btn-install" id="pmBannerInstallBtn">Yükle</button>
          <button type="button" class="btn btn-sm btn-link text-white-50 p-1" id="pmBannerDismissBtn" aria-label="Kapat">✕</button>
        </div>
      </div>
    `;

    document.body.appendChild(banner);

    document.getElementById('pmBannerInstallBtn')?.addEventListener('click', triggerInstallFlow);
    document.getElementById('pmBannerDismissBtn')?.addEventListener('click', () => {
      hideFloatingInstallBanner();
      try { sessionStorage.setItem('pm_pwa_install_dismissed', '1'); } catch (_) {}
    });
  }

  function hideFloatingInstallBanner() {
    const banner = document.getElementById('pmFloatingInstallBanner');
    if (banner) {
      banner.style.opacity = '0';
      banner.style.transform = 'translateY(20px)';
      setTimeout(() => banner.remove(), 250);
    }
  }

  // 4. Online / Offline Status Detection Banner
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
          <span>Çevrimdışı moddasınız. Oyunları kesintisiz oynayabilirsiniz; skorlarınız internet geldiğinde eşitlenecektir.</span>
        `;
        document.body.appendChild(netIndicator);
      }
    } else {
      if (netIndicator) {
        netIndicator.className = 'pm-network-online';
        netIndicator.innerHTML = `
          <span>✅</span>
          <span>Yeniden çevrimiçisiniz! Skorlarınız senkronize ediliyor...</span>
        `;
        setTimeout(() => {
          if (netIndicator) netIndicator.remove();
        }, 3200);
      }
    }
  }

  window.addEventListener('online', updateNetworkStatus);
  window.addEventListener('offline', updateNetworkStatus);

  // 5. Offline Score Sync Listener
  window.addEventListener('prismatch:scores-synced', (e) => {
    const count = e.detail?.count || 1;
    showToastNotification(`🔄 ${count} çevrimdışı skorunuz başarıyla eşitlendi!`);
  });

  // 6. Generic Toast Notification
  function showToastNotification(message) {
    const toast = document.createElement('div');
    toast.className = 'pm-pwa-toast';
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => {
      toast.classList.add('show');
    }, 50);
    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 300);
    }, 3500);
  }

  function showUpdateNotification() {
    showToastNotification('🚀 Prismatch güncellendi! Yeni özellikleri görmek için sayfayı yenileyin.');
  }

  // Inject PWA styles dynamically
  const pwaStyles = document.createElement('style');
  pwaStyles.textContent = `
    .pm-pwa-install-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: linear-gradient(135deg, rgba(255, 107, 91, 0.2), rgba(255, 153, 102, 0.2));
      border: 1px solid rgba(255, 107, 91, 0.4);
      color: #ff6b5b;
      font-size: 13px;
      font-weight: 700;
      padding: 6px 12px;
      border-radius: 999px;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .pm-pwa-install-btn:hover {
      background: linear-gradient(135deg, rgba(255, 107, 91, 0.35), rgba(255, 153, 102, 0.35));
      transform: translateY(-1px);
    }
    .pm-pwa-banner {
      position: fixed;
      bottom: max(16px, env(safe-area-inset-bottom));
      left: 50%;
      transform: translateX(-50%);
      width: min(440px, calc(100% - 24px));
      background: rgba(14, 18, 30, 0.94);
      border: 1px solid rgba(255, 107, 91, 0.4);
      border-radius: 20px;
      padding: 12px 16px;
      box-shadow: 0 16px 40px rgba(0, 0, 0, 0.5);
      z-index: 10000;
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      transition: all 0.25s ease;
    }
    .pm-pwa-banner-inner {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .pm-pwa-banner-text {
      flex: 1;
      min-width: 0;
    }
    .pm-pwa-banner-title {
      font-weight: 700;
      font-size: 14.5px;
      color: #fff;
    }
    .pm-pwa-banner-sub {
      font-size: 12px;
      color: rgba(255, 255, 255, 0.7);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .pm-pwa-btn-install {
      background: linear-gradient(135deg, #ff6b5b, #ff8c42) !important;
      border: none !important;
      font-weight: 700 !important;
      padding: 6px 14px !important;
      border-radius: 10px !important;
      color: #fff !important;
    }
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
      animation: slideDown 0.3s ease;
    }
    .pm-network-offline {
      background: rgba(220, 38, 38, 0.92);
      border: 1px solid rgba(255, 255, 255, 0.2);
      color: #fff;
    }
    .pm-network-online {
      background: rgba(16, 185, 129, 0.92);
      border: 1px solid rgba(255, 255, 255, 0.2);
      color: #fff;
    }
    .pm-pwa-toast {
      position: fixed;
      bottom: max(24px, env(safe-area-inset-bottom));
      left: 50%;
      transform: translateX(-50%) translateY(20px);
      background: rgba(18, 24, 38, 0.94);
      border: 1px solid rgba(255, 255, 255, 0.16);
      border-radius: 999px;
      padding: 10px 20px;
      font-size: 13.5px;
      font-weight: 600;
      color: #fff;
      box-shadow: 0 14px 36px rgba(0, 0, 0, 0.4);
      z-index: 100000;
      opacity: 0;
      transition: all 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      pointer-events: none;
    }
    .pm-pwa-toast.show {
      opacity: 1;
      transform: translateX(-50%) translateY(0);
    }
    @keyframes slideDown {
      from { transform: translateX(-50%) translateY(-20px); opacity: 0; }
      to { transform: translateX(-50%) translateY(0); opacity: 1; }
    }
  `;
  document.head.appendChild(pwaStyles);
})();
