/**
 * Prismatch - Client-Side Offline Storage & Synchronization Store
 * Handles client-side score persistence, personal records, daily status caching,
 * and automatic synchronization with the backend when connection is restored.
 */
(function (global) {
  'use strict';

  const DB_NAME = 'prismatch_pwa_db';
  const DB_VERSION = 1;
  const QUEUE_STORE = 'score_sync_queue';
  const BESTS_STORE = 'personal_bests';

  let dbPromise = null;

  function openDB() {
    if (dbPromise) return dbPromise;
    if (!('indexedDB' in window)) {
      return Promise.resolve(null);
    }
    dbPromise = new Promise((resolve, reject) => {
      const req = indexedDB.open(DB_NAME, DB_VERSION);
      req.onupgradeneeded = (e) => {
        const db = e.target.result;
        if (!db.objectStoreNames.contains(QUEUE_STORE)) {
          db.createObjectStore(QUEUE_STORE, { keyPath: 'id', autoIncrement: true });
        }
        if (!db.objectStoreNames.contains(BESTS_STORE)) {
          db.createObjectStore(BESTS_STORE, { keyPath: 'mode' });
        }
      };
      req.onsuccess = () => resolve(req.result);
      req.onerror = () => {
        console.warn('[OfflineStore] Failed to open IndexedDB, falling back to localStorage');
        resolve(null);
      };
    });
    return dbPromise;
  }

  const OfflineStore = {
    /**
     * Save completed game score to local sync queue (IndexedDB / localStorage)
     */
    async queueScore(payload, isDaily = false) {
      const item = {
        payload,
        isDaily,
        queuedAt: Date.now(),
        attempts: 0,
      };

      // Also update local personal bests instantly in browser
      this.updateLocalBest(payload.game_mode || payload.gameMode || 'elimination', payload.score || 0, payload.reached_level || payload.reachedLevel || 1);

      // If daily, update client-side daily played flag
      if (isDaily) {
        const todayUtc = new Date().toISOString().slice(0, 10);
        this.setDailyPlayed(todayUtc, payload.game_mode || payload.gameMode || 'elimination');
      }

      const db = await openDB();
      if (db) {
        return new Promise((resolve) => {
          const tx = db.transaction(QUEUE_STORE, 'readwrite');
          const store = tx.objectStore(QUEUE_STORE);
          const req = store.add(item);
          req.onsuccess = () => {
            console.log('[OfflineStore] Score queued in IndexedDB', item);
            resolve(true);
          };
          req.onerror = () => {
            this.fallbackQueueScore(item);
            resolve(true);
          };
        });
      } else {
        this.fallbackQueueScore(item);
        return true;
      }
    },

    fallbackQueueScore(item) {
      try {
        const list = JSON.parse(localStorage.getItem('pm_offline_score_queue') || '[]');
        item.id = Date.now() + Math.random();
        list.push(item);
        localStorage.setItem('pm_offline_score_queue', JSON.stringify(list));
        console.log('[OfflineStore] Score queued in localStorage fallback');
      } catch (e) {
        console.error('[OfflineStore] localStorage queue failed', e);
      }
    },

    /**
     * Retrieve all pending scores waiting to be synced
     */
    async getPendingScores() {
      const db = await openDB();
      if (db) {
        return new Promise((resolve) => {
          const tx = db.transaction(QUEUE_STORE, 'readonly');
          const store = tx.objectStore(QUEUE_STORE);
          const req = store.getAll();
          req.onsuccess = () => resolve(req.result || []);
          req.onerror = () => resolve(this.fallbackGetPending());
        });
      }
      return this.fallbackGetPending();
    },

    fallbackGetPending() {
      try {
        return JSON.parse(localStorage.getItem('pm_offline_score_queue') || '[]');
      } catch (e) {
        return [];
      }
    },

    /**
     * Remove a successfully synced score
     */
    async removeScore(id) {
      const db = await openDB();
      if (db) {
        return new Promise((resolve) => {
          const tx = db.transaction(QUEUE_STORE, 'readwrite');
          const store = tx.objectStore(QUEUE_STORE);
          store.delete(id);
          tx.oncomplete = () => resolve();
          tx.onerror = () => resolve();
        });
      }
      try {
        let list = JSON.parse(localStorage.getItem('pm_offline_score_queue') || '[]');
        list = list.filter(item => item.id !== id);
        localStorage.setItem('pm_offline_score_queue', JSON.stringify(list));
      } catch (e) {}
    },

    /**
     * Flush all queued scores to the server when network is online
     */
    async syncPendingScores() {
      if (!navigator.onLine) {
        console.log('[OfflineStore] Skipping sync: Browser is currently offline');
        return;
      }

      const pending = await this.getPendingScores();
      if (!pending || pending.length === 0) return;

      console.log(`[OfflineStore] Syncing ${pending.length} pending score(s)...`);
      let syncedCount = 0;

      for (const item of pending) {
        const endpoint = item.isDaily ? 'api/daily_record.php' : 'api/record.php';
        try {
          const res = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(item.payload),
            credentials: 'same-origin',
          });

          if (res.ok) {
            await this.removeScore(item.id);
            syncedCount++;
          } else {
            const data = await res.json().catch(() => ({}));
            // If already recorded or invalid, remove from queue
            if (data && (data.error === 'already_played' || res.status === 400)) {
              await this.removeScore(item.id);
            }
          }
        } catch (e) {
          console.warn('[OfflineStore] Failed to sync score item, will retry on next reconnect', e);
          break; // Stop iteration if network dropped again
        }
      }

      if (syncedCount > 0) {
        console.log(`[OfflineStore] Successfully synced ${syncedCount} score(s)`);
        window.dispatchEvent(new CustomEvent('prismatch:scores-synced', {
          detail: { count: syncedCount },
        }));
      }
    },

    /**
     * Client-side high score & personal records management
     */
    updateLocalBest(mode, score, stage) {
      try {
        const key = `pm_best_${mode}`;
        const current = JSON.parse(localStorage.getItem(key) || '{"score":0,"stage":1}');
        const newScore = Math.max(current.score || 0, score || 0);
        const newStage = Math.max(current.stage || 1, stage || 1);
        localStorage.setItem(key, JSON.stringify({
          score: newScore,
          stage: newStage,
          updatedAt: Date.now()
        }));
      } catch (e) {}
    },

    saveLocalBest(mode, score, payload = {}) {
      const stage = payload.stage || payload.level || payload.reachedLevel || 1;
      return this.updateLocalBest(mode, score, stage);
    },

    getLocalBest(mode) {
      try {
        const key = `pm_best_${mode}`;
        const data = JSON.parse(localStorage.getItem(key) || '{"score":0,"stage":1}');
        return {
          score: data.score || 0,
          stage: data.stage || data.level || 1,
          level: data.level || data.stage || 1,
          updatedAt: data.updatedAt || 0
        };
      } catch (e) {
        return { score: 0, stage: 1, level: 1 };
      }
    },

    /**
     * Client-side Daily Challenge played status tracker
     */
    setDailyPlayed(dateStr, mode) {
      try {
        const key = `pm_daily_played_${dateStr}_${mode}`;
        localStorage.setItem(key, '1');
      } catch (e) {}
    },

    isDailyPlayed(dateStr, mode) {
      try {
        const key = `pm_daily_played_${dateStr}_${mode}`;
        return localStorage.getItem(key) === '1';
      } catch (e) {
        return false;
      }
    }
  };

  // Auto-sync when reconnecting to network
  window.addEventListener('online', () => {
    OfflineStore.syncPendingScores();
  });

  // Attempt sync on page load if online
  if (navigator.onLine) {
    setTimeout(() => {
      OfflineStore.syncPendingScores();
    }, 2000);
  }

  global.PrismatchOfflineStore = OfflineStore;
  try {
    window.dispatchEvent(new CustomEvent('pm:offline-ready', { detail: OfflineStore }));
  } catch(e) {}
})(typeof window !== 'undefined' ? window : this);
