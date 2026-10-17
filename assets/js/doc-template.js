/**
 * Dokumentvorlagen: Live-Vorschau des PDF (Beispieldaten) und kleine Bedienhilfen.
 * Die Formularwerte gehen als Adresszeile an die Vorschau (GET, nur Lesezugriff, nur Administratoren).
 */
(() => {
  'use strict';
  const form = document.getElementById('tpl-form');
  if (!form) return;
  const frame = form.querySelector('[data-frame]');
  const hint = form.querySelector('[data-frame-hint]');
  const openLink = form.querySelector('[data-open-pdf]');
  let kind = 'offer';
  let timer = null;

  const query = () => {
    const q = new URLSearchParams();
    new FormData(form).forEach((v, k) => {
      if (k === '_csrf' || k === 'name' || k === 'doc_type' || k === 'stay' || k === 'intro_default' || k === 'closing_default') return;
      q.append(k, String(v));
    });
    // Nicht angehakte Kontrollkästchen fehlen in FormData → als 0 melden
    ['logo', 'page_numbers'].forEach((n) => { if (!q.has(n)) q.set(n, '0'); });
    q.set('kind', kind);
    q.set('t', String(Date.now()));
    return q.toString();
  };
  const refresh = () => {
    const url = form.dataset.preview + '?' + query();
    hint.hidden = false; hint.textContent = 'Vorschau wird geladen …';
    frame.src = url + '#toolbar=0&navpanes=0&view=FitH';
    openLink.href = url;
  };
  frame.addEventListener('load', () => { hint.hidden = true; });
  const schedule = () => { clearTimeout(timer); timer = setTimeout(refresh, 450); };

  form.addEventListener('input', (e) => {
    const out = e.target.dataset && e.target.id ? form.querySelector('[data-out="' + e.target.id + '"]') : null;
    if (out) out.textContent = e.target.value + (e.target.id === 'font_scale' ? ' %' : ' mm');
    if (e.target.id === 'accent-picker') { form.querySelector('#accent').value = e.target.value; }
    if (e.target.id === 'accent') {
      const v = e.target.value;
      if (/^#[0-9a-f]{6}$/i.test(v)) form.querySelector('#accent-picker').value = v;
    }
    if (e.target.hasAttribute && e.target.hasAttribute('data-no-preview')) return;
    if (e.target.name === 'name' || e.target.name === 'doc_type') return;
    schedule();
  });
  form.addEventListener('change', (e) => {
    if (e.target.name === 'name' || e.target.name === 'doc_type' || (e.target.hasAttribute && e.target.hasAttribute('data-no-preview'))) return;
    schedule();
  });
  form.querySelector('[data-accent-reset]').addEventListener('click', () => {
    form.querySelector('#accent').value = '';
    form.querySelector('#accent-picker').value = form.dataset.brand;
    schedule();
  });
  form.querySelectorAll('[data-kind]').forEach((b) => b.addEventListener('click', () => {
    kind = b.dataset.kind;
    form.querySelectorAll('[data-kind]').forEach((x) => { x.classList.toggle('is-active', x === b); x.setAttribute('aria-selected', x === b ? 'true' : 'false'); });
    refresh();
  }));
  // «Speichern & hier bleiben»: Wert als verstecktes Feld senden (die Schaltfläche wird beim Absenden deaktiviert)
  const stay = form.querySelector('button[name="stay"]');
  if (stay) stay.addEventListener('click', () => {
    if (!form.querySelector('input[type="hidden"][name="stay"]')) {
      const h = document.createElement('input'); h.type = 'hidden'; h.name = 'stay'; h.value = '1'; form.appendChild(h);
    }
  });
  refresh();
})();
