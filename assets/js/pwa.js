/**
 * PWA: Service Worker registrieren und «App installieren» anbieten.
 * Elemente mit [data-pwa-install] sind standardmässig versteckt und erscheinen nur, wenn der Browser die Installation anbietet
 * (bzw. auf iOS ein kurzer Hinweis, da Safari keinen Installationsdialog auslösen lässt).
 */
(() => {
  'use strict';
  const meta = document.querySelector('meta[name="pwa-sw"]');
  if (!meta || !('serviceWorker' in navigator)) return;
  window.addEventListener('load', () => {
    navigator.serviceWorker.register(meta.content, { scope: meta.dataset.scope }).catch(() => {});
  });

  const buttons = Array.from(document.querySelectorAll('[data-pwa-install]'));
  const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  if (standalone || !buttons.length) return;
  let deferred = null;
  const show = (on) => buttons.forEach((b) => { b.hidden = !on; });
  window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); deferred = e; show(true); });
  window.addEventListener('appinstalled', () => { deferred = null; show(false); });
  const ios = /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.MSStream;
  if (ios) show(true);
  buttons.forEach((b) => b.addEventListener('click', async () => {
    if (deferred) {
      deferred.prompt();
      await deferred.userChoice.catch(() => {});
      deferred = null; show(false);
    } else if (ios && window.toast) {
      window.toast('Zum Installieren: «Teilen» antippen und «Zum Home-Bildschirm» wählen.', 'info');
    } else if (ios) {
      alert('Zum Installieren: «Teilen» antippen und «Zum Home-Bildschirm» wählen.');
    }
  }));
})();
