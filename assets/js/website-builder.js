/**
 * Website-Builder (Administration). Das Formular wird vollständig aus dem Schema des Servers erzeugt;
 * der Server prüft und bereinigt alles erneut (SiteBuilder::sanitize). Es wird nur Text gespeichert, nie HTML.
 */
(() => {
  'use strict';
  const root = document.getElementById('wb');
  if (!root) return;

  const doc = JSON.parse(root.dataset.doc);
  const schema = JSON.parse(root.dataset.schema);
  const types = JSON.parse(root.dataset.types);
  const icons = JSON.parse(root.dataset.icons);
  const editor = document.getElementById('wb-editor');
  const preview = document.getElementById('wb-preview');
  const saveEl = document.getElementById('wb-save');
  const statusEl = document.getElementById('wb-status');
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const open = new Set(JSON.parse(sessionStorageGet('wb-open') || '[]'));
  const typeIcon = { hero: 'bx-layout', features: 'bx-grid-alt', text: 'bx-text', image_text: 'bx-image', steps: 'bx-list-ol', faq: 'bx-help-circle', cta: 'bx-pointer', contact: 'bx-envelope' };

  function sessionStorageGet(k) { try { return sessionStorage.getItem(k); } catch { return null; } }
  function sessionStorageSet(k, v) { try { sessionStorage.setItem(k, v); } catch { /* optional */ } }

  /* ---------------------------------------------------------- DOM-Helfer */
  const el = (tag, attrs = {}, ...kids) => {
    const n = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
      if (k === 'class') n.className = v;
      else if (k.startsWith('on')) n.addEventListener(k.slice(2), v);
      else if (v !== false && v != null) n.setAttribute(k, v === true ? '' : v);
    }
    kids.flat().forEach((c) => c != null && n.append(c.nodeType ? c : document.createTextNode(c)));
    return n;
  };
  const icon = (name) => el('i', { class: 'bx ' + name });
  const iconBtn = (name, label, onclick, extra = '') => el('button', { type: 'button', class: 'btn btn-ghost btn-icon ' + extra, 'aria-label': label, title: label, onclick }, icon(name));
  let uid = 0;
  const nextId = () => 'wbf-' + (++uid);

  /* ---------------------------------------------------------- Speichern */
  let timer = null; let inflight = false; let again = false; let scrollY = 0;
  const schedule = () => { saveEl.textContent = 'Änderungen …'; clearTimeout(timer); timer = setTimeout(save, 700); };
  async function save() {
    if (inflight) { again = true; return; }
    inflight = true;
    try {
      const res = await fetch(root.dataset.saveUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify(doc), credentials: 'same-origin' });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Fehler');
      saveEl.textContent = 'Entwurf gespeichert';
      setStatus(data.dirty);
      try { scrollY = preview.contentWindow.scrollY; } catch { scrollY = 0; }
      preview.contentWindow.location.reload();
    } catch (e) {
      saveEl.textContent = 'Speichern fehlgeschlagen: ' + e.message;
    } finally {
      inflight = false;
      if (again) { again = false; save(); }
    }
  }
  preview.addEventListener('load', () => { try { preview.contentWindow.scrollTo(0, scrollY); } catch { /* ignore */ } });
  function setStatus(dirty) {
    statusEl.className = 'badge ' + (dirty ? 'badge-amber' : 'badge-emerald');
    statusEl.replaceChildren(icon(dirty ? 'bx-edit' : 'bx-check'), el('span', {}, dirty ? 'Ungeveröffentlichte Änderungen' : 'Veröffentlicht'));
  }
  window.addEventListener('beforeunload', (e) => { if (timer && saveEl.textContent === 'Änderungen …') { e.preventDefault(); e.returnValue = ''; } });

  /* ---------------------------------------------------------- Felder */
  function field(def, obj, onChange) {
    const [key, kind, label, max, opts] = def;
    const id = nextId();
    const set = (v) => { obj[key] = v; onChange(); };
    const wrap = (...c) => el('div', {}, ...c);
    const lab = (t = label) => el('label', { class: 'label', for: id }, t);
    switch (kind) {
      case 'text':
        return wrap(lab(), el('input', { class: 'input', id, value: obj[key] ?? '', maxlength: max, oninput: (e) => set(e.target.value) }));
      case 'long':
        return wrap(lab(), el('textarea', { class: 'input', id, rows: max > 600 ? 5 : 3, maxlength: max, oninput: (e) => set(e.target.value) }, obj[key] ?? ''));
      case 'link':
        return wrap(lab(), el('input', { class: 'input font-mono text-[13px]', id, value: obj[key] ?? '', maxlength: 300, placeholder: 'https://… · mailto:… · portal · #anker', oninput: (e) => set(e.target.value) }));
      case 'bool':
        return el('label', { class: 'flex cursor-pointer items-center gap-2.5 text-sm text-slate-700' }, el('input', { type: 'checkbox', class: 'checkbox', checked: !!obj[key], onchange: (e) => { set(e.target.checked); } }), label);
      case 'enum': {
        const sel = el('select', { class: 'input', id, onchange: (e) => set(e.target.value) }, Object.entries(opts).map(([v, t]) => el('option', { value: v, selected: String(obj[key]) === v }, t)));
        return wrap(lab(), sel);
      }
      case 'icon': {
        const prev = el('span', { class: 'grid size-10 shrink-0 place-items-center rounded-xl bg-brand-50 text-xl text-brand-600' }, icon(obj[key]));
        const sel = el('select', { class: 'input', id, onchange: (e) => { prev.replaceChildren(icon(e.target.value)); set(e.target.value); } },
          Object.entries(icons).map(([v, t]) => el('option', { value: v, selected: obj[key] === v }, t)));
        return wrap(lab(), el('div', { class: 'flex items-center gap-2' }, prev, sel));
      }
      case 'image':
        return imageField(label, obj, key, onChange);
      case 'items':
        return itemsField(def, obj, onChange);
      default: return el('div');
    }
  }

  function imageField(label, obj, key, onChange) {
    const base = root.dataset.imgBase;
    const box = el('div');
    const draw = () => {
      const msg = el('p', { class: 'mt-1 text-xs text-rose-600', role: 'alert' });
      const input = el('input', { type: 'file', accept: 'image/png,image/jpeg,image/webp,image/gif', class: 'hidden', onchange: async (e) => {
        const f = e.target.files[0]; if (!f) return;
        const fd = new FormData(); fd.append('image', f);
        msg.textContent = 'Lädt hoch …';
        try {
          const r = await fetch(root.dataset.uploadUrl, { method: 'POST', headers: { 'X-CSRF-Token': csrf }, body: fd, credentials: 'same-origin' });
          const d = await r.json();
          if (!d.ok) throw new Error(d.error);
          obj[key] = d.name; onChange(); draw();
        } catch (err) { msg.textContent = err.message || 'Upload fehlgeschlagen.'; }
      } });
      box.replaceChildren(
        el('span', { class: 'label' }, label),
        el('div', { class: 'flex items-center gap-3' },
          obj[key] ? el('img', { src: base + obj[key].replace(/\.\w+$/, ''), alt: '', class: 'h-14 w-20 rounded-lg object-cover ring-1 ring-slate-200' }) : el('span', { class: 'grid h-14 w-20 place-items-center rounded-lg bg-slate-100 text-2xl text-slate-300' }, icon('bx-image-add')),
          el('button', { type: 'button', class: 'btn btn-secondary btn-sm', onclick: () => input.click() }, icon('bx-upload'), obj[key] ? 'Ersetzen' : 'Hochladen'),
          obj[key] ? el('button', { type: 'button', class: 'btn btn-ghost btn-sm', onclick: () => { obj[key] = ''; onChange(); draw(); } }, 'Entfernen') : null,
          input),
        msg, el('p', { class: 'hint' }, 'PNG, JPG, WebP oder GIF, max. 2 MB.'));
    };
    draw();
    return box;
  }

  function itemsField(def, obj, onChange) {
    const [key, , label, max, sub] = def;
    const box = el('div');
    const blank = () => Object.fromEntries(sub.map((s) => [s[0], s[1] === 'icon' ? Object.keys(icons)[0] : s[1] === 'bool' ? false : '']));
    const draw = () => {
      const rows = obj[key];
      box.replaceChildren(
        el('div', { class: 'mb-2 flex items-center justify-between' }, el('span', { class: 'label !mb-0' }, label + ' (' + rows.length + '/' + max + ')'),
          el('button', { type: 'button', class: 'btn btn-secondary btn-sm', disabled: rows.length >= max, onclick: () => { rows.push(blank()); onChange(); draw(); } }, icon('bx-plus'), 'Hinzufügen')),
        el('div', { class: 'space-y-2.5' }, rows.map((row, i) => el('div', { class: 'rounded-xl bg-slate-50 p-3 ring-1 ring-slate-200/70' },
          el('div', { class: 'space-y-2.5' }, sub.map((s) => field(s, row, onChange))),
          el('div', { class: 'mt-2 flex justify-end gap-0.5' },
            iconBtn('bx-chevron-up', 'Nach oben', () => { if (i > 0) { [rows[i - 1], rows[i]] = [rows[i], rows[i - 1]]; onChange(); draw(); } }, 'size-8'),
            iconBtn('bx-chevron-down', 'Nach unten', () => { if (i < rows.length - 1) { [rows[i + 1], rows[i]] = [rows[i], rows[i + 1]]; onChange(); draw(); } }, 'size-8'),
            iconBtn('bx-trash', 'Entfernen', () => { rows.splice(i, 1); onChange(); draw(); }, 'size-8 hover:!text-rose-600'))))));
    };
    draw();
    return box;
  }

  /* ---------------------------------------------------------- Seiteneinstellungen */
  function pageCard() {
    const ch = schedule;
    const card = el('div', { class: 'card' });
    const body = el('div', { class: 'space-y-4 p-4' });
    body.append(
      el('label', { class: 'flex cursor-pointer items-start gap-3 rounded-xl bg-slate-50 p-3 ring-1 ring-slate-200/70' }, el('input', { type: 'checkbox', class: 'checkbox mt-0.5', checked: doc.enabled, onchange: (e) => { doc.enabled = e.target.checked; ch(); } }),
        el('span', { class: 'text-sm text-slate-700' }, el('strong', { class: 'block text-slate-900' }, 'Startseite aktiv'), 'Aus: Besucher werden direkt zum Kundenportal weitergeleitet.')),
      field(['title', 'text', 'Seitentitel (Browser-Tab, Suchmaschinen)', 70], doc.seo, ch),
      field(['description', 'long', 'Beschreibung (Suchmaschinen, Link-Vorschau)', 200], doc.seo, ch),
      field(['index', 'bool', 'Von Suchmaschinen indexieren lassen', 0], doc.seo, ch),
      el('hr', { class: 'border-slate-100' }),
      field(['show_portal', 'bool', 'Button «Kundenportal» in der Kopfzeile', 0], doc.nav, ch),
      field(['cta_label', 'text', 'Kopfzeile: Button-Text', 40], doc.nav, ch),
      field(['cta_url', 'link', 'Kopfzeile: Button-Ziel (leer = Firmen-E-Mail)', 300], doc.nav, ch),
      el('hr', { class: 'border-slate-100' }),
      field(['text', 'text', 'Zusatztext in der Fusszeile', 200], doc.footer, ch),
      field(['show_admin_link', 'bool', 'Link «Administration» in der Fusszeile', 0], doc.footer, ch));
    const key = 'page';
    const head = el('button', { type: 'button', class: 'flex w-full items-center gap-3 px-4 py-3 text-left', 'aria-expanded': String(open.has(key)) }, icon('bx-cog'), el('span', { class: 'flex-1 text-sm font-semibold text-slate-900' }, 'Seiteneinstellungen'), icon('bx-chevron-down'));
    body.hidden = !open.has(key);
    head.addEventListener('click', () => { toggle(key); body.hidden = !open.has(key); head.setAttribute('aria-expanded', open.has(key)); });
    card.append(head, body);
    return card;
  }
  function toggle(k) { open.has(k) ? open.delete(k) : open.add(k); sessionStorageSet('wb-open', JSON.stringify([...open])); }

  /* ---------------------------------------------------------- Blöcke */
  const summary = (b) => b.title || b.eyebrow || (b.items && b.items[0] && (b.items[0].title || b.items[0].q)) || '';
  function blockCard(b, i) {
    const def = schema[b.type];
    const isOpen = open.has(b.id);
    const body = el('div', { class: 'space-y-4 border-t border-slate-100 p-4' }, def.fields.map((f) => field(f, b, () => { schedule(); sum.textContent = summary(b) || '—'; })));
    body.hidden = !isOpen;
    const sum = el('span', { class: 'block truncate text-xs text-slate-500' }, summary(b) || '—');
    const head = el('button', { type: 'button', class: 'flex min-w-0 flex-1 items-center gap-3 text-left', 'aria-expanded': String(isOpen), onclick: () => { toggle(b.id); body.hidden = !open.has(b.id); head.setAttribute('aria-expanded', open.has(b.id)); } },
      el('span', { class: 'grid size-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-lg text-brand-600' }, icon(typeIcon[b.type] || 'bx-square')),
      el('span', { class: 'min-w-0' }, el('span', { class: 'block text-sm font-semibold text-slate-900' }, types[b.type]), sum));
    const move = (d) => { const j = i + d; if (j < 0 || j >= doc.blocks.length) return; [doc.blocks[i], doc.blocks[j]] = [doc.blocks[j], doc.blocks[i]]; renderAll(); schedule(); };
    return el('div', { class: 'card' + (b.visible ? '' : ' opacity-60') },
      el('div', { class: 'flex items-center gap-1 px-3 py-2.5' }, head,
        iconBtn(b.visible ? 'bx-show' : 'bx-hide', b.visible ? 'Ausblenden' : 'Einblenden', () => { b.visible = !b.visible; renderAll(); schedule(); }, 'size-8'),
        iconBtn('bx-chevron-up', 'Nach oben', () => move(-1), 'size-8'), iconBtn('bx-chevron-down', 'Nach unten', () => move(1), 'size-8'),
        iconBtn('bx-copy', 'Duplizieren', () => { if (doc.blocks.length >= 30) return; const c = JSON.parse(JSON.stringify(b)); c.id = Array.from(crypto.getRandomValues(new Uint8Array(4)), (x) => x.toString(16).padStart(2, '0')).join(''); doc.blocks.splice(i + 1, 0, c); renderAll(); schedule(); }, 'size-8'),
        iconBtn('bx-trash', 'Löschen', () => { if (confirm('Diesen Baustein löschen?')) { doc.blocks.splice(i, 1); renderAll(); schedule(); } }, 'size-8 hover:!text-rose-600')),
      body);
  }

  function addMenu() {
    const wrap = el('div', { class: 'card p-4' }, el('p', { class: 'mb-3 text-sm font-semibold text-slate-900' }, icon('bx-plus-circle'), ' Baustein hinzufügen'));
    const grid = el('div', { class: 'grid grid-cols-2 gap-2' });
    Object.entries(types).forEach(([t, name]) => grid.append(el('button', { type: 'button', class: 'btn btn-secondary justify-start', disabled: doc.blocks.length >= 30, onclick: () => {
      const b = { id: Array.from(crypto.getRandomValues(new Uint8Array(4)), (x) => x.toString(16).padStart(2, '0')).join(''), type: t, visible: true };
      schema[t].fields.forEach((f) => { b[f[0]] = f[1] === 'items' ? [] : f[1] === 'bool' ? (f[0] !== 'show_login') : f[1] === 'enum' ? Object.keys(f[4])[0] : f[1] === 'icon' ? Object.keys(icons)[0] : ''; });
      if (t === 'features' || t === 'steps' || t === 'faq') b.title = t === 'faq' ? 'Häufige Fragen' : 'Neuer Abschnitt';
      if (b.columns !== undefined) b.columns = '3';
      doc.blocks.push(b); open.add(b.id); sessionStorageSet('wb-open', JSON.stringify([...open])); renderAll(); schedule();
      setTimeout(() => editor.lastElementChild.previousElementSibling?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 50);
    } }, icon(typeIcon[t]), name)));
    wrap.append(grid);
    return wrap;
  }

  function renderAll() {
    editor.replaceChildren(pageCard(), ...doc.blocks.map(blockCard), addMenu());
  }

  /* Gerätewechsel der Vorschau */
  document.querySelectorAll('[data-device]').forEach((b) => b.addEventListener('click', () => {
    document.querySelectorAll('[data-device]').forEach((x) => x.classList.toggle('is-active', x === b));
    preview.style.width = b.dataset.device;
  }));

  renderAll();
})();
