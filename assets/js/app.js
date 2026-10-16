/**
 * Globale Interaktionen – ohne Abhängigkeiten.
 * Scroll-Einblendungen, Dropdowns, Modals, Bestätigungen, Ladezustände,
 * Toasts, mobile Navigation, Tabellen-Links, Kopieren in die Zwischenablage.
 */
(() => {
  'use strict';

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const $ = (sel, ctx = document) => ctx.querySelector(sel);
  const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

  /* ------------------------------------------------ Scroll-Einblendung */
  const reveal = (root = document) => {
    const els = $$('.reveal:not(.is-visible)', root);
    if (reduceMotion || !('IntersectionObserver' in window)) {
      els.forEach((el) => el.classList.add('is-visible'));
      return;
    }
    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          io.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -40px 0px', threshold: 0.05 });
    els.forEach((el) => io.observe(el));
  };
  window.appReveal = reveal;

  /* ------------------------------------------------ Toasts */
  const dismissToast = (t) => {
    if (!t || t.classList.contains('is-leaving')) return;
    t.classList.add('is-leaving');
    setTimeout(() => t.remove(), reduceMotion ? 0 : 260);
  };
  const initToasts = (root = document) => {
    $$('.toast', root).forEach((t) => {
      const ms = parseInt(t.dataset.timeout || '5000', 10);
      let timer = setTimeout(() => dismissToast(t), ms);
      t.addEventListener('mouseenter', () => clearTimeout(timer));
      t.addEventListener('mouseleave', () => { timer = setTimeout(() => dismissToast(t), 2500); });
    });
  };
  window.toast = (message, type = 'success') => {
    const wrap = $('#toasts');
    if (!wrap) return alert(message);
    const icons = { success: ['bx-check-circle', 'text-emerald-600 bg-emerald-50'], error: ['bx-error-circle', 'text-rose-600 bg-rose-50'], info: ['bx-info-circle', 'text-sky-600 bg-sky-50'] };
    const [icon, cls] = icons[type] || icons.info;
    const el = document.createElement('div');
    el.className = 'toast pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl bg-white p-4 shadow-lift ring-1 ring-slate-200';
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    el.dataset.timeout = type === 'error' ? '8000' : '4000';
    el.innerHTML = `<span class="grid size-8 shrink-0 place-items-center rounded-lg ${cls}"><i class="bx ${icon} text-xl"></i></span>
      <p class="flex-1 pt-1 text-sm leading-relaxed text-slate-700"></p>
      <button type="button" class="-mr-1 -mt-1 grid size-8 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" data-toast-close aria-label="Schliessen"><i class="bx bx-x text-xl"></i></button>`;
    $('p', el).textContent = message;
    wrap.appendChild(el);
    initToasts(wrap);
  };

  /* ------------------------------------------------ Modals */
  let lastFocus = null;
  const openModal = (modal) => {
    if (!modal) return;
    lastFocus = document.activeElement;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
    const focusable = $('[autofocus], input:not([type=hidden]):not([disabled]), select, textarea, button:not([data-modal-close])', $('.modal-panel', modal));
    setTimeout(() => focusable && focusable.focus({ preventScroll: true }), reduceMotion ? 0 : 60);
  };
  const closeModal = (modal) => {
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    if (!$('.modal.is-open')) document.body.classList.remove('modal-open');
    if (lastFocus && lastFocus.focus) lastFocus.focus({ preventScroll: true });
  };
  window.appModal = { open: openModal, close: closeModal };

  /** Formular im Modal mit Daten befüllen (Bearbeiten) oder leeren (Neu). */
  const fillForm = (form, data) => {
    form.reset();
    $$('[data-error]', form).forEach((e) => e.remove());
    const defaults = form.dataset.defaults ? JSON.parse(form.dataset.defaults) : {};
    const values = Object.assign({}, defaults, data || {});
    Object.entries(values).forEach(([key, val]) => {
      $$(`[name="${CSS.escape(key)}"]`, form).forEach((field) => {
        if (field.type === 'checkbox') field.checked = !!Number(val) || val === true;
        else if (field.type === 'radio') field.checked = String(field.value) === String(val);
        else field.value = val ?? '';
      });
    });
    if (!data || !data.id) { const idField = $('[name="id"]', form); if (idField) idField.value = ''; }
    form.dispatchEvent(new Event('modal:filled'));
  };

  /* ------------------------------------------------ Ausklappbare Seitenleiste */
  const navStore = {
    get(k) { try { return localStorage.getItem('nav:' + k); } catch (e) { return null; } },
    set(k, v) { try { localStorage.setItem('nav:' + k, v); } catch (e) { /* ohne Speicher weiter */ } },
  };
  function setNavGroup(g, open) {
    g.toggleAttribute('data-open', open);
    const b = $('.nav-group-btn', g);
    if (b) b.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  function toggleNavGroup(g) {
    const open = !g.hasAttribute('data-open');
    setNavGroup(g, open);
    navStore.set(g.dataset.navGroup, open ? '1' : '0');
  }
  const initNav = () => {
    const nav = $('[data-nav]');
    if (!nav) return;
    nav.classList.add('no-anim');
    $$('.nav-group', nav).forEach((g) => {
      // Gruppe mit der aktuellen Seite bleibt immer offen; sonst gilt die gemerkte Wahl bzw. der Standard.
      if (g.dataset.hasActive === '1') return setNavGroup(g, true);
      const saved = navStore.get(g.dataset.navGroup);
      setNavGroup(g, saved === null ? g.dataset.default === '1' : saved === '1');
    });
    requestAnimationFrame(() => requestAnimationFrame(() => nav.classList.remove('no-anim')));
    // Aktiver Eintrag sichtbar halten (lange Navigation)
    const act = $('.nav-link.is-active', nav);
    if (act && act.scrollIntoView) act.scrollIntoView({ block: 'nearest' });
  };
  initNav();

  /* ------------------------------------------------ Bestätigungsdialog */
  const confirmModal = () => $('#confirm-modal');
  const askConfirm = (text, okLabel, variant) => new Promise((resolve) => {
    const m = confirmModal();
    if (!m) return resolve(window.confirm(text));
    $('[data-confirm-text]', m).textContent = text;
    const ok = $('[data-confirm-ok]', m);
    ok.textContent = okLabel || 'Bestätigen';
    ok.className = 'btn ' + (variant === 'primary' ? 'btn-primary' : 'btn-danger');
    const icon = $('[data-confirm-icon]', m);
    icon.className = 'grid size-11 shrink-0 place-items-center rounded-xl text-2xl ' + (variant === 'primary' ? 'bg-brand-50 text-brand-600' : 'bg-rose-50 text-rose-600');
    icon.innerHTML = `<i class="bx ${variant === 'primary' ? 'bx-help-circle' : 'bx-error'}"></i>`;
    const cleanup = (result) => {
      ok.removeEventListener('click', onOk);
      m.removeEventListener('modal:closed', onCancel);
      closeModal(m);
      resolve(result);
    };
    const onOk = () => cleanup(true);
    const onCancel = () => cleanup(false);
    ok.addEventListener('click', onOk);
    m.addEventListener('modal:closed', onCancel, { once: true });
    openModal(m);
    setTimeout(() => ok.focus(), 80);
  });

  /* ------------------------------------------------ Ladezustand */
  const setLoading = (btn, on = true) => {
    if (!btn) return;
    btn.classList.toggle('is-loading', on);
    btn.disabled = on;
    btn.setAttribute('aria-busy', on ? 'true' : 'false');
  };
  window.setLoading = setLoading;

  /* ------------------------------------------------ Event-Delegation */
  document.addEventListener('click', (ev) => {
    const t = ev.target;

    const toastClose = t.closest('[data-toast-close]');
    if (toastClose) return dismissToast(toastClose.closest('.toast'));

    const opener = t.closest('[data-modal-open]');
    if (opener) {
      ev.preventDefault();
      const modal = document.getElementById(opener.dataset.modalOpen);
      if (!modal) return;
      const form = $('form', modal);
      if (form && opener.hasAttribute('data-fill')) fillForm(form, opener.dataset.fill ? JSON.parse(opener.dataset.fill) : null);
      const heading = $('[data-modal-heading]', modal);
      if (heading && opener.dataset.modalTitle) heading.textContent = opener.dataset.modalTitle;
      return openModal(modal);
    }

    const closer = t.closest('[data-modal-close]');
    if (closer) {
      const modal = closer.closest('.modal');
      closeModal(modal);
      modal && modal.dispatchEvent(new Event('modal:closed'));
      return;
    }

    const ddToggle = t.closest('[data-dropdown-toggle]');
    $$('[data-dropdown].is-open').forEach((dd) => {
      if (!ddToggle || dd !== ddToggle.closest('[data-dropdown]')) {
        if (!dd.contains(t) || t.closest('.dropdown-item')) {
          dd.classList.remove('is-open');
          const b = $('[data-dropdown-toggle]', dd); b && b.setAttribute('aria-expanded', 'false');
        }
      }
    });
    if (ddToggle) {
      ev.preventDefault();
      const dd = ddToggle.closest('[data-dropdown]');
      const open = dd.classList.toggle('is-open');
      ddToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      return;
    }

    const grpBtn = t.closest('.nav-group-btn');
    if (grpBtn) { ev.preventDefault(); toggleNavGroup(grpBtn.closest('.nav-group')); return; }

    if (t.closest('[data-sidebar-open]')) return document.body.classList.add('sidebar-open');
    if (t.closest('[data-sidebar-close]')) return document.body.classList.remove('sidebar-open');

    const copy = t.closest('[data-copy]');
    if (copy) {
      ev.preventDefault();
      const text = copy.dataset.copy;
      const done = () => {
        const icon = $('i', copy);
        const prev = icon ? icon.className : null;
        if (icon) icon.className = 'bx bx-check text-emerald-600';
        window.toast('Link in die Zwischenablage kopiert.', 'success');
        setTimeout(() => { if (icon && prev) icon.className = prev; }, 1600);
      };
      if (navigator.clipboard) navigator.clipboard.writeText(text).then(done, () => window.prompt('Link kopieren:', text));
      else window.prompt('Link kopieren:', text);
      return;
    }

    const printBtn = t.closest('[data-print]');
    if (printBtn) { ev.preventDefault(); window.print(); return; }

    const row = t.closest('tr[data-href]');
    if (row && !t.closest('a, button, input, select, textarea, form, label')) {
      if (ev.metaKey || ev.ctrlKey) window.open(row.dataset.href, '_blank');
      else window.location.href = row.dataset.href;
    }
  });

  document.addEventListener('keydown', (ev) => {
    if (ev.key === 'Escape') {
      const open = $$('.modal.is-open').pop();
      if (open) { closeModal(open); open.dispatchEvent(new Event('modal:closed')); return; }
      $$('[data-dropdown].is-open').forEach((dd) => dd.classList.remove('is-open'));
      document.body.classList.remove('sidebar-open');
    }
    if (ev.key === 'Enter' && ev.target.matches('tr[data-href]')) window.location.href = ev.target.dataset.href;
  });

  /* Formulare: Bestätigung + Ladezustand + Doppelklick-Schutz */
  document.addEventListener('submit', async (ev) => {
    const form = ev.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (ev.defaultPrevented) return;   // z. B. clientseitige Prüfung (Unterschrift) hat das Absenden gestoppt
    if (form.dataset.confirm && !form.dataset.confirmed) {
      ev.preventDefault();
      const ok = await askConfirm(form.dataset.confirm, form.dataset.confirmLabel, form.dataset.confirmVariant);
      if (ok) {
        form.dataset.confirmed = '1';
        const btn = ev.submitter || $('[type=submit], button:not([type])', form);
        setLoading(btn, true);
        HTMLFormElement.prototype.submit.call(form);
      }
      return;
    }
    if (form.dataset.submitting) { ev.preventDefault(); return; }
    form.dataset.submitting = '1';
    const btn = ev.submitter || $('[type=submit], button:not([type])', form);
    if (btn && !form.hasAttribute('data-no-loading')) setLoading(btn, true);
    // Bei Zurück-Navigation (bfcache) wieder freigeben.
    setTimeout(() => { delete form.dataset.submitting; }, 8000);
  });

  window.addEventListener('pageshow', (ev) => {
    if (ev.persisted) $$('.btn.is-loading').forEach((b) => setLoading(b, false));
  });

  /* Ein-/Ausklappen gesteuert durch Checkbox: data-collapse-toggle="#id" */
  const syncCollapse = (cb) => {
    const target = $(cb.dataset.collapseToggle);
    if (!target) return;
    const open = cb.type === 'checkbox' ? cb.checked !== cb.hasAttribute('data-collapse-invert') : true;
    target.classList.toggle('is-open', open);
    $$('input, select, textarea', target).forEach((f) => { if (!f.hasAttribute('data-keep-enabled')) f.disabled = !open; });
  };
  document.addEventListener('change', (ev) => {
    if (ev.target.matches('[data-collapse-toggle]')) syncCollapse(ev.target);
    if (ev.target.matches('[data-autosubmit]')) ev.target.form && ev.target.form.requestSubmit();
  });

  /* ------------------------------------------------ Start */
  document.addEventListener('DOMContentLoaded', () => {
    reveal();
    initToasts();
    $$('[data-collapse-toggle]').forEach(syncCollapse);
    $$('tr[data-href]').forEach((r) => r.setAttribute('tabindex', '0'));

    const auto = $('[data-auto-open]');
    if (auto) setTimeout(() => { window.location.href = auto.getAttribute('href'); }, 700);

    const modalToOpen = $('[data-open-on-load]');
    if (modalToOpen) openModal(modalToOpen);
  });
})();
