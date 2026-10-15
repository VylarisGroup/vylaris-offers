/* Passkeys (WebAuthn): Registrierung im Profil und Anmeldung als zweiter Faktor. */
(() => {
  'use strict';
  const b64uToBuf = (s) => {
    s = s.replace(/-/g, '+').replace(/_/g, '/');
    const bin = atob(s + '='.repeat((4 - (s.length % 4)) % 4));
    return Uint8Array.from(bin, (c) => c.charCodeAt(0)).buffer;
  };
  const bufToB64u = (buf) => {
    let bin = '';
    new Uint8Array(buf).forEach((b) => (bin += String.fromCharCode(b)));
    return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  };
  const supported = !!(window.PublicKeyCredential && navigator.credentials);

  async function post(url, csrf, body) {
    const res = await fetch(url, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf, 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(body || {}),
    });
    let data = null;
    try { data = await res.json(); } catch (e) { /* keine JSON-Antwort */ }
    if (!data) throw new Error('Unerwartete Antwort des Servers (' + res.status + ').');
    if (!res.ok || !data.ok) {
      const err = new Error(data.error || 'Anfrage fehlgeschlagen.');
      err.redirect = data.redirect;
      throw err;
    }
    return data;
  }

  function friendly(e) {
    if (e && (e.name === 'NotAllowedError' || e.name === 'AbortError')) return 'Der Vorgang wurde abgebrochen oder ist abgelaufen.';
    if (e && e.name === 'InvalidStateError') return 'Dieser Passkey ist auf diesem Gerät bereits registriert.';
    return (e && e.message) || 'Es ist ein Fehler aufgetreten.';
  }

  function setBusy(btn, busy) {
    btn.disabled = busy;
    btn.classList.toggle('opacity-60', busy);
  }

  // Registrierung
  document.querySelectorAll('[data-passkey-register]').forEach((box) => {
    const btn = box.querySelector('[data-passkey-start]');
    const errEl = box.parentElement.querySelector('[data-passkey-error]');
    if (!supported) {
      btn.disabled = true;
      box.parentElement.querySelector('[data-passkey-unsupported]')?.classList.remove('hidden');
      return;
    }
    btn.addEventListener('click', async () => {
      errEl.classList.add('hidden');
      setBusy(btn, true);
      try {
        const { options } = await post(box.dataset.optionsUrl, box.dataset.csrf);
        options.challenge = b64uToBuf(options.challenge);
        options.user.id = b64uToBuf(options.user.id);
        (options.excludeCredentials || []).forEach((c) => (c.id = b64uToBuf(c.id)));
        const cred = await navigator.credentials.create({ publicKey: options });
        const r = cred.response;
        const out = await post(box.dataset.registerUrl, box.dataset.csrf, {
          id: cred.id,
          name: box.querySelector('[data-passkey-name]').value,
          clientDataJSON: bufToB64u(r.clientDataJSON),
          attestationObject: bufToB64u(r.attestationObject),
        });
        window.location.href = out.redirect;
      } catch (e) {
        errEl.textContent = friendly(e);
        errEl.classList.remove('hidden');
        setBusy(btn, false);
      }
    });
  });

  // Anmeldung
  document.querySelectorAll('[data-passkey-login]').forEach((box) => {
    const btn = box.querySelector('[data-passkey-start]');
    const errEl = box.querySelector('[data-passkey-error]');
    if (!supported) {
      box.classList.add('hidden');
      return;
    }
    const run = async () => {
      errEl.classList.add('hidden');
      setBusy(btn, true);
      try {
        const { options } = await post(box.dataset.optionsUrl, box.dataset.csrf);
        options.challenge = b64uToBuf(options.challenge);
        (options.allowCredentials || []).forEach((c) => (c.id = b64uToBuf(c.id)));
        const cred = await navigator.credentials.get({ publicKey: options });
        const r = cred.response;
        const out = await post(box.dataset.verifyUrl, box.dataset.csrf, {
          id: cred.id,
          clientDataJSON: bufToB64u(r.clientDataJSON),
          authenticatorData: bufToB64u(r.authenticatorData),
          signature: bufToB64u(r.signature),
        });
        window.location.href = out.redirect;
      } catch (e) {
        if (e && e.redirect) { window.location.href = e.redirect; return; }
        errEl.textContent = friendly(e);
        errEl.classList.remove('hidden');
        setBusy(btn, false);
      }
    };
    btn.addEventListener('click', run);
  });
})();
