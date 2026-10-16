/**
 * Assistent-Modus für Formulare – schrittweise Eingabe, ohne Abhängigkeiten.
 *
 * Ein Formular wird mit `data-wizard` zum Assistenten-fähigen Formular. Das bisherige Formular bleibt
 * Standard; oben erscheint ein Umschalter «Formular | Assistent». Der Assistent blendet nur ein
 * Teil des bestehenden Formulars ein – gesendet wird immer dasselbe Formular an dieselbe Adresse.
 *
 *   <form data-wizard>                     Schritte = Elemente mit data-step="schlüssel"
 *     <section data-step="kontakt" data-step-title="Kontakt" data-step-icon="bx-id-card">…</section>
 *   <form data-wizard="auto">              Schritte = jedes sichtbare Kind des Feldcontainers (eine Frage pro Schritt)
 *     <div data-wizard-fields class="grid">…</div>
 *
 * Weitere Attribute:
 *   data-wizard-order="a,b,c"     Reihenfolge der Schritte (sonst Dokumentreihenfolge)
 *   data-wizard-only              Element nur im Assistenten sichtbar (z. B. Zusammenfassung)
 *   data-wizard-summary           Hier wird die Zusammenfassung aus allen [data-sum="Bezeichnung"] erzeugt
 *   data-wizard-final             Nur im letzten Schritt sichtbar (z. B. Speichern-Leiste eines Dialogs)
 *   data-wizard-key="…"           Schlüssel für die Merkung der Wahl (sonst Formular-ID/Adresse)
 *   Ereignis `wizard:validate`    (cancelable, detail.step) – Seiten können Schritte zusätzlich prüfen.
 */
(() => {
  'use strict';

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const $ = (sel, ctx = document) => ctx.querySelector(sel);
  const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
  const store = {
    get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set(k, v) { try { localStorage.setItem(k, v); } catch (e) { /* ohne Speicher weiter */ } },
  };
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const clean = (s) => String(s || '').replace(/\*/g, '').replace(/\(optional\)/i, '').replace(/\s+/g, ' ').trim();

  function setup(form) {
    if (form.__wizard) return;
    const auto = form.dataset.wizard === 'auto';
    const key = 'wz:' + (form.dataset.wizardKey || form.id || (form.getAttribute('action') || location.pathname));
    const inModal = !!form.closest('.modal');
    let on = false;
    let steps = [];
    let cur = 0;
    let ui = null;

    /* ---------- Schritte ermitteln ---------- */
    const collect = () => {
      if (auto) {
        const box = $('[data-wizard-fields]', form) || $('.grid', form);
        const out = [];
        Array.from(box ? box.children : []).filter((el) => !el.hidden && !el.matches('input[type=hidden]')).forEach((el) => {
          const prev = out[out.length - 1];
          // Aufeinanderfolgende Kontrollkästchen («Aktiv», «Einladung» …) bilden zusammen einen Schritt
          if (el.tagName === 'LABEL' && prev && prev.els.every((x) => x.tagName === 'LABEL')) { prev.els.push(el); prev.title = 'Optionen'; return; }
          const lab = $('.label, legend, label', el);
          out.push({ key: 'a' + out.length, title: clean(lab ? lab.textContent : '') || 'Schritt ' + (out.length + 1), icon: '', els: [el] });
        });
        return out;
      }
      const map = new Map();
      $$('[data-step]', form).forEach((el) => {
        const k = el.dataset.step;
        if (!map.has(k)) map.set(k, { key: k, title: el.dataset.stepTitle || k, icon: el.dataset.stepIcon || '', els: [] });
        const s = map.get(k);
        if (el.dataset.stepTitle && s.title === k) s.title = el.dataset.stepTitle;
        if (el.dataset.stepIcon && !s.icon) s.icon = el.dataset.stepIcon;
        s.els.push(el);
      });
      let list = Array.from(map.values());
      const order = (form.dataset.wizardOrder || '').split(',').map((x) => x.trim()).filter(Boolean);
      if (order.length) list.sort((a, b) => (order.indexOf(a.key) + 1 || 99) - (order.indexOf(b.key) + 1 || 99));
      return list;
    };
    // Schritte, deren Inhalt komplett ausgeblendet ist (z. B. «Versand» bei digitalen Produkten), werden übersprungen.
    const skipped = (s) => s.els.every((el) => el.hidden);
    const live = () => steps.map((s, i) => i).filter((i) => !skipped(steps[i]));

    /* ---------- Oberfläche ---------- */
    const bar = document.createElement('div');
    bar.className = 'wz-switch';
    bar.innerHTML = `<div class="wz-switch-inner" role="group" aria-label="Eingabeart">
      <button type="button" data-wz-mode="form"><i class="bx bx-list-ul"></i>Formular</button>
      <button type="button" data-wz-mode="wizard"><i class="bx bx-magic-wand"></i>Assistent</button></div>
      <p class="wz-switch-hint">Schritt für Schritt statt alles auf einmal.</p>`;
    const anchor = auto ? ($('[data-wizard-fields]', form) || $('.grid', form)) : null;
    if (anchor) anchor.before(bar); else form.prepend(bar);

    const buildUi = () => {
      ui = document.createElement('div');
      ui.className = 'wz-ui';
      ui.innerHTML = `<div class="wz-head"><ol class="wz-steps" data-wz-steps></ol>
        <div class="wz-progress" aria-hidden="true"><span data-wz-bar></span></div>
        <p class="wz-count" data-wz-count aria-live="polite"></p></div>`;
      const nav = document.createElement('div');
      nav.className = 'wz-nav';
      nav.innerHTML = `${inModal ? '<button type="button" class="btn btn-ghost" data-modal-close>Abbrechen</button>' : ''}
        <span class="flex-1"></span>
        <button type="button" class="btn btn-secondary" data-wz-prev><i class="bx bx-left-arrow-alt"></i>Zurück</button>
        <button type="button" class="btn btn-primary" data-wz-next>Weiter<i class="bx bx-right-arrow-alt"></i></button>`;
      ui.nav = nav;
      bar.after(ui);
      // Navigation ans Ende des Schritt-Bereichs
      const last = steps.length ? steps[steps.length - 1].els.slice(-1)[0] : null;
      const host = auto ? (anchor && anchor.parentNode) : form;
      if (auto && anchor) anchor.after(nav);
      else if (last && last.parentNode === host) host.append(nav);
      else form.append(nav);
      ui.addEventListener('click', (ev) => {
        const b = ev.target.closest('[data-wz-goto]');
        if (b) goto(parseInt(b.dataset.wzGoto, 10), true);
      });
      nav.addEventListener('click', (ev) => {
        if (ev.target.closest('[data-wz-next]')) next();
        else if (ev.target.closest('[data-wz-prev]')) prev();
      });
    };

    const renderStepper = () => {
      const idx = live();
      const list = $('[data-wz-steps]', ui);
      list.innerHTML = idx.map((i, n) => {
        const s = steps[i];
        const state = i === cur ? 'is-current' : (i < cur ? 'is-done' : '');
        return `<li class="wz-step ${state}"><button type="button" data-wz-goto="${i}" ${i === cur ? 'aria-current="step"' : ''}${i > cur ? ' tabindex="-1"' : ''}>
          <span class="wz-dot">${i < cur ? '<i class="bx bx-check"></i>' : (n + 1)}</span><span class="wz-label">${esc(s.title)}</span></button></li>`;
      }).join('');
      const pos = idx.indexOf(cur);
      $('[data-wz-bar]', ui).style.width = (idx.length > 1 ? (pos / (idx.length - 1)) * 100 : 100) + '%';
      $('[data-wz-count]', ui).textContent = `Schritt ${pos + 1} von ${idx.length}: ${steps[cur].title}`;
    };

    const summarize = () => {
      const box = $('[data-wizard-summary]', form);
      if (!box) return;
      const rows = [];
      const seen = new Set();
      $$('[data-sum]', form).forEach((f) => {
        const name = f.name || f.id;
        if (f.type === 'radio') { if (seen.has(name)) return; seen.add(name); f = $$(`[name="${CSS.escape(f.name)}"]`, form).find((r) => r.checked); if (!f) return; }
        let v;
        if (f.type === 'checkbox') v = f.checked ? 'Ja' : 'Nein';
        else if (f.tagName === 'SELECT') v = f.selectedIndex >= 0 && f.value !== '' ? f.options[f.selectedIndex].text : '';
        else if (f.type === 'radio') { const l = f.closest('label'); const t = l && $('.font-semibold', l); v = t ? clean(t.textContent) : (l ? clean(l.textContent) : f.value); }
        else if (f.type === 'date' && /^\d{4}-\d{2}-\d{2}$/.test(f.value)) v = f.value.split('-').reverse().join('.');
        else v = f.value;
        v = String(v).trim();
        if (v !== '') rows.push([f.dataset.sum || (f.closest('[data-sum]') || {}).dataset?.sum, v]);
      });
      const target = $('[data-wz-sum-body]', box) || box;
      target.innerHTML = rows.length
        ? '<dl>' + rows.map(([l, v]) => `<div class="dl-row"><dt>${esc(l)}</dt><dd class="whitespace-pre-line break-words">${esc(v)}</dd></div>`).join('') + '</dl>'
        : '<p class="text-sm text-slate-500">Noch keine Angaben.</p>';
    };

    /* ---------- Anzeige ---------- */
    const apply = () => {
      steps.forEach((s, i) => s.els.forEach((el) => el.classList.toggle('wz-off', i !== cur)));
      const isLast = cur === live().slice(-1)[0];
      $$('[data-wizard-final]', form).forEach((el) => el.classList.toggle('wz-off', !isLast));
      const idx = live();
      const nav = ui.nav;
      $('[data-wz-prev]', nav).hidden = idx.indexOf(cur) <= 0;
      $('[data-wz-next]', nav).hidden = isLast;
      renderStepper();
      if (isLast) summarize();
      $$('[data-autogrow]', form).forEach((ta) => ta.dispatchEvent(new Event('input', { bubbles: true })));
    };

    const validate = (i) => {
      const s = steps[i];
      for (const el of s.els) {
        for (const f of $$('input, select, textarea', el)) {
          if (f.disabled || f.type === 'hidden' || f.closest('[hidden]')) continue;
          if (!f.checkValidity()) {
            f.reportValidity();
            f.focus({ preventScroll: false });
            return false;
          }
        }
      }
      const ev = new CustomEvent('wizard:validate', { cancelable: true, bubbles: true, detail: { step: s.key, form } });
      return form.dispatchEvent(ev);
    };

    const focusFirst = () => {
      const s = steps[cur];
      const f = s && s.els.map((el) => $('input:not([type=hidden]):not([type=radio]):not([type=checkbox]), select, textarea', el)).find(Boolean);
      if (f && !f.closest('[hidden]')) f.focus({ preventScroll: true });
    };

    const goto = (i, jump) => {
      if (i === cur && ui && jump !== 'init') return;
      if (jump === true && i > cur) {
        // Vorwärts springen nur Schritt für Schritt (mit Prüfung)
        for (let k = cur; k < i; k++) { if (!skipped(steps[k]) && !validate(k)) { cur = k; apply(); return; } }
      }
      cur = i;
      apply();
      if (jump !== 'init') {
        const top = (ui || form).getBoundingClientRect().top;
        if (top < 0 || top > window.innerHeight * 0.5) (ui || form).scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
        focusFirst();
      }
    };
    const stepFrom = (from, dir) => { const idx = live(); const p = idx.indexOf(from); return idx[p + dir]; };
    const next = () => { if (!validate(cur)) return; const n = stepFrom(cur, 1); if (n !== undefined) goto(n); };
    const prev = () => { const n = stepFrom(cur, -1); if (n !== undefined) goto(n); };

    const enable = (persist) => {
      if (on) return;
      steps = collect();
      if (steps.length < 2) return;
      on = true;
      form.classList.add('wz-on');
      $$('[data-wizard-only]', form).forEach((el) => { el.hidden = false; });
      if (!ui) buildUi();
      ui.hidden = false; ui.nav.hidden = false;
      $$('[data-wz-mode]', bar).forEach((b) => b.setAttribute('aria-pressed', b.dataset.wzMode === 'wizard' ? 'true' : 'false'));
      // Beim Fehler zum betroffenen Schritt springen
      let start = live()[0];
      const bad = steps.findIndex((s) => s.els.some((el) => el.querySelector('.input-error, .error-text')));
      if (bad >= 0 && !skipped(steps[bad])) start = bad;
      cur = start;
      goto(start, 'init');
      let flag = $('input[name="_wizard"]', form);
      if (!flag) { flag = document.createElement('input'); flag.type = 'hidden'; flag.name = '_wizard'; form.append(flag); }
      flag.value = '1';
      if (persist) store.set(key, '1');
    };
    const disable = (persist) => {
      if (!on) { $$('[data-wz-mode]', bar).forEach((b) => b.setAttribute('aria-pressed', b.dataset.wzMode === 'form' ? 'true' : 'false')); return; }
      on = false;
      form.classList.remove('wz-on');
      $$('.wz-off', form).forEach((el) => el.classList.remove('wz-off'));
      $$('[data-wizard-only]', form).forEach((el) => { el.hidden = true; });
      if (ui) { ui.hidden = true; ui.nav.hidden = true; }
      $$('[data-wz-mode]', bar).forEach((b) => b.setAttribute('aria-pressed', b.dataset.wzMode === 'form' ? 'true' : 'false'));
      const flag = $('input[name="_wizard"]', form);
      if (flag) flag.remove();
      if (persist) store.set(key, '0');
    };

    bar.addEventListener('click', (ev) => {
      const b = ev.target.closest('[data-wz-mode]');
      if (!b) return;
      if (b.dataset.wzMode === 'wizard') enable(true); else disable(true);
    });

    // Enter springt zum nächsten Schritt statt das Formular abzusenden
    form.addEventListener('keydown', (ev) => {
      if (!on || ev.key !== 'Enter' || ev.target.tagName === 'TEXTAREA' || ev.target.closest('button, a')) return;
      if (cur !== live().slice(-1)[0]) { ev.preventDefault(); next(); }
    });
    // Sicherheitsnetz: abgeschickt wird nur im letzten Schritt
    form.addEventListener('submit', (ev) => {
      if (on && cur !== live().slice(-1)[0]) { ev.preventDefault(); ev.stopImmediatePropagation(); next(); }
    }, true);

    // Dialoge: beim Öffnen/Befüllen zurück auf Schritt 1 (Schritte neu bestimmen – Felder können sich geändert haben)
    form.addEventListener('modal:filled', () => {
      if (!on) return;
      steps = collect();
      cur = live()[0];
      apply();
    });

    form.__wizard = { enable, disable, isOn: () => on };

    const param = new URLSearchParams(location.search).get('wizard');
    const pref = store.get(key);
    if ((!inModal && param === '1') || form.dataset.wizardStart === '1') enable(false);
    else if (pref === '1' && param !== '0') enable(false);
    else disable(false);
  }

  const init = (root = document) => $$('form[data-wizard]', root).forEach(setup);
  window.appWizard = { init };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => init());
  else init();
})();
