/**
 * Offerten-Editor: dynamische Positionen, Vorlagen, Steuervorschlag anhand der
 * Kundenadresse und Live-Berechnung (identisch zu app/lib/Calc.php).
 * Massgebend ist immer die serverseitige Berechnung beim Speichern.
 */
(() => {
  'use strict';
  const form = document.getElementById('offer-form');
  if (!form) return;

  const cfg = JSON.parse(form.dataset.editor);
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));
  const list = $('[data-items]', form);
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  let uid = 0;

  /* ------------------------------------------------ Zahlen */
  const parseNum = (v) => {
    let s = String(v ?? '').trim().replace(/[’'\s ]/g, '');
    if (s.includes(',') && s.includes('.')) {
      s = s.lastIndexOf(',') > s.lastIndexOf('.') ? s.replace(/\./g, '').replace(',', '.') : s.replace(/,/g, '');
    } else s = s.replace(',', '.');
    const n = parseFloat(s);
    return Number.isFinite(n) ? n : 0;
  };
  const r2 = (v) => { const sign = v < 0 ? -1 : 1; return sign * Math.round(Math.abs(v) * 100 + 1e-9) / 100; };
  const fmt = (v, withCur = true) => {
    const [dec, th] = cfg.style === 'ch' ? ['.', '’'] : cfg.style === 'en' ? ['.', ','] : [',', '.'];
    const neg = v < 0; const parts = Math.abs(v).toFixed(2).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, th);
    return (withCur ? cfg.currency + ' ' : '') + (neg ? '−' : '') + parts.join(dec);
  };
  const discount = (base, type, value) => {
    if (base <= 0 || value <= 0) return 0;
    if (type === 'percent') return r2(base * Math.min(value, 100) / 100);
    if (type === 'amount') return r2(Math.min(value, base));
    return 0;
  };

  /* ------------------------------------------------ Steuern */
  const taxById = (id) => cfg.taxes.find((t) => String(t.id) === String(id));
  const currentCustomer = () => cfg.customers[$('[data-customer]', form).value] || null;

  const taxOptions = (selected) => {
    let html = `<option value="default"${selected === 'default' || !selected ? ' selected' : ''}>Standard</option>`;
    html += cfg.taxes.map((t) => `<option value="${t.id}"${String(selected) === String(t.id) ? ' selected' : ''}>${esc(t.name)} · ${esc(t.display)}</option>`).join('');
    return html;
  };

  /* ------------------------------------------------ Positionsbilder */
  const imgUrl = (name) => cfg.imgBase + name;
  const imgInner = (name) => name
    ? `<img src="${esc(imgUrl(name))}" alt="" class="size-full object-cover" loading="lazy">`
    : '<i class="bx bx-image-add text-xl text-slate-400"></i>';
  const imgHtml = (id, name) => `
    <div class="relative shrink-0" data-img>
      <input type="hidden" name="items[${id}][image]" value="${esc(name || '')}">
      <input type="file" class="hidden" accept="image/png,image/jpeg,image/webp,image/gif" data-img-file>
      <button type="button" class="grid size-11 place-items-center overflow-hidden rounded-xl bg-slate-50 ring-1 ring-slate-200 transition hover:ring-brand-400" data-img-pick data-tip="${name ? 'Bild ändern' : 'Bild hinzufügen'}" aria-label="Positionsbild">${imgInner(name)}</button>
      <button type="button" class="absolute -right-1.5 -top-1.5 grid size-5 place-items-center rounded-full bg-white text-slate-500 shadow ring-1 ring-slate-200 hover:text-rose-600 ${name ? '' : 'hidden'}" data-img-clear aria-label="Bild entfernen"><i class="bx bx-x text-sm"></i></button>
    </div>`;
  const setImg = (wrap, name) => {
    $('input[type=hidden]', wrap).value = name || '';
    const b = $('[data-img-pick]', wrap);
    b.innerHTML = imgInner(name);
    b.dataset.tip = name ? 'Bild ändern' : 'Bild hinzufügen';
    $('[data-img-clear]', wrap).classList.toggle('hidden', !name);
  };
  const uploadImg = async (wrap, file) => {
    const b = $('[data-img-pick]', wrap);
    if (file.size > 8 * 1024 * 1024) { window.toast('Das Bild ist zu gross (max. 8 MB).', 'error'); return; }
    b.innerHTML = '<i class="bx bx-loader-alt bx-spin text-xl text-slate-400"></i>';
    const body = new FormData();
    body.append('_csrf', document.querySelector('meta[name="csrf-token"]').content);
    body.append('image', file);
    try {
      const res = await fetch(cfg.imgUpload, { method: 'POST', body, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
      const json = await res.json();
      if (!json.ok) throw new Error(json.error || 'Upload fehlgeschlagen.');
      setImg(wrap, json.name);
    } catch (err) {
      setImg(wrap, $('input[type=hidden]', wrap).value);
      window.toast(err.message || 'Upload fehlgeschlagen.', 'error');
    }
  };

  /* ------------------------------------------------ Positionen */
  const rowHtml = (id, d) => `
    <div class="item-row group/row relative px-4 py-5 sm:px-6" data-row="${id}">
      <div class="flex gap-3">
        <div class="flex flex-col items-center gap-1 pt-2">
          <span class="grid size-7 place-items-center rounded-lg bg-slate-100 text-xs font-semibold text-slate-500" data-pos>1</span>
          <button type="button" class="btn btn-ghost btn-icon size-7 opacity-60 hover:opacity-100" data-move="-1" data-tip="Nach oben" aria-label="Nach oben"><i class="bx bx-chevron-up"></i></button>
          <button type="button" class="btn btn-ghost btn-icon size-7 opacity-60 hover:opacity-100" data-move="1" data-tip="Nach unten" aria-label="Nach unten"><i class="bx bx-chevron-down"></i></button>
        </div>
        <div class="grid min-w-0 flex-1 gap-3 sm:grid-cols-12">
          <div class="flex items-start gap-2 sm:col-span-12">
            <input class="input min-w-0 flex-1 font-medium" name="items[${id}][title]" value="${esc(d.title)}" placeholder="Titel der Position *" maxlength="190" aria-label="Titel">
            ${imgHtml(id, d.image)}
          </div>
          <div class="sm:col-span-12">
            <textarea class="input !min-h-[44px] text-[13px]" rows="1" name="items[${id}][description]" placeholder="Beschreibung (optional)" aria-label="Beschreibung" data-autogrow>${esc(d.description)}</textarea>
          </div>
          <div class="grid grid-cols-2 gap-3 sm:col-span-4">
            <div><label class="mb-1 block text-xs font-medium text-slate-500">Menge</label><input class="input tabular" name="items[${id}][quantity]" value="${esc(d.quantity ?? '1')}" inputmode="decimal" data-calc></div>
            <div><label class="mb-1 block text-xs font-medium text-slate-500">Einheit</label><input class="input" name="items[${id}][unit]" value="${esc(d.unit ?? 'Stk.')}" list="unit-list" maxlength="30"></div>
          </div>
          <div class="sm:col-span-3"><label class="mb-1 block text-xs font-medium text-slate-500">Einzelpreis</label><input class="input tabular" name="items[${id}][unit_price]" value="${esc(d.unit_price ?? '')}" inputmode="decimal" placeholder="0.00" data-calc></div>
          <div class="sm:col-span-5"><label class="mb-1 block text-xs font-medium text-slate-500">Rabatt</label>
            <div class="flex gap-2">
              <select class="input w-24 shrink-0" name="items[${id}][discount_type]" data-calc>
                <option value="percent"${d.discount_type === 'percent' || !d.discount_type || d.discount_type === 'none' ? ' selected' : ''}>%</option>
                <option value="amount"${d.discount_type === 'amount' ? ' selected' : ''}>${esc(cfg.currency)}</option>
              </select>
              <input class="input tabular" name="items[${id}][discount_value]" value="${esc(d.discount_value ?? '')}" inputmode="decimal" placeholder="0" data-calc>
            </div>
          </div>
          <div class="sm:col-span-7"><label class="mb-1 block text-xs font-medium text-slate-500">Steuer</label>
            <select class="input" name="items[${id}][tax]" data-calc data-item-tax>${taxOptions(d.tax)}</select>
          </div>
          <div class="flex items-end justify-between gap-3 sm:col-span-5 sm:justify-end">
            <div class="text-right">
              <p class="text-xs text-slate-500" data-line-info>&nbsp;</p>
              <p class="text-lg font-semibold text-slate-900 tabular" data-line-total>${fmt(0)}</p>
            </div>
            <div data-dropdown>
              <button type="button" class="btn btn-ghost btn-icon" data-dropdown-toggle aria-label="Weitere Aktionen"><i class="bx bx-dots-vertical-rounded text-xl"></i></button>
              <div class="dropdown-menu right-0 bottom-full mb-2">
                <button type="button" class="dropdown-item" data-duplicate><i class="bx bx-duplicate"></i>Duplizieren</button>
                <button type="button" class="dropdown-item" data-save-template><i class="bx bx-bookmark-plus"></i>Als Vorlage speichern</button>
                <button type="button" class="dropdown-item text-rose-600" data-remove><i class="bx bx-trash !text-rose-500"></i>Entfernen</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>`;

  const addRow = (data = {}, after = null, animate = true) => {
    const id = uid++;
    const wrap = document.createElement('div');
    wrap.innerHTML = rowHtml(id, data).trim();
    const row = wrap.firstElementChild;
    if (!animate) row.classList.remove('item-row');
    if (after) after.after(row); else list.appendChild(row);
    autogrow($('[data-autogrow]', row));
    renumber();
    recalc();
    return row;
  };

  const renumber = () => {
    const rows = $$('[data-row]', list);
    rows.forEach((r, i) => { $('[data-pos]', r).textContent = i + 1; });
    $('[data-item-count]', form).textContent = rows.length;
  };

  const rowData = (row) => {
    const get = (f) => { const el = $(`[name$="[${f}]"]`, row); return el ? el.value : ''; };
    return { title: get('title'), description: get('description'), quantity: get('quantity'), unit: get('unit'), unit_price: get('unit_price'), image: get('image'), discount_type: get('discount_type'), discount_value: get('discount_value'), tax: get('tax') };
  };

  const autogrow = (ta) => {
    if (!ta) return;
    const fit = () => { ta.style.height = 'auto'; ta.style.height = Math.min(ta.scrollHeight + 2, 320) + 'px'; };
    ta.addEventListener('input', fit);
    requestAnimationFrame(fit);
  };

  /* ------------------------------------------------ Berechnung */
  const resolveRate = (sel) => {
    const t = sel && sel !== 'default' ? taxById(sel) : taxById($('[data-default-tax]', form).value);
    return t ? { rate: Number(t.rate), label: t.display } : { rate: 0, label: '' };
  };

  const recalc = () => {
    const groups = new Map();
    let subtotal = 0;
    $$('[data-row]', list).forEach((row) => {
      const d = rowData(row);
      const q = Math.max(0, parseNum(d.quantity));
      const p = parseNum(d.unit_price);
      const lineSub = r2(q * p);
      const disc = discount(lineSub, d.discount_type, parseNum(d.discount_value));
      const total = r2(lineSub - disc);
      subtotal += total;
      const { rate, label } = resolveRate(d.tax);
      const key = rate.toFixed(3) + '|' + label;
      if (!groups.has(key)) groups.set(key, { label, rate, base: 0 });
      groups.get(key).base += total;
      const info = disc > 0 ? `${fmt(lineSub, false)} − ${fmt(disc, false)}` : (q && p ? `${q} × ${fmt(p, false)}` : ' ');
      $('[data-line-info]', row).textContent = info;
      const lt = $('[data-line-total]', row);
      const txt = fmt(total);
      if (lt.textContent !== txt) { lt.textContent = txt; lt.classList.remove('number-tick'); void lt.offsetWidth; lt.classList.add('number-tick'); }
    });
    subtotal = r2(subtotal);

    const dType = $('[name="discount_type"]', form).value;
    const dVal = parseNum($('[name="discount_value"]', form).value);
    const disc = discount(subtotal, dType, dVal);
    if (disc > 0 && subtotal > 0) {
      let remaining = disc; const keys = [...groups.keys()];
      keys.forEach((k, i) => {
        const g = groups.get(k);
        const share = i === keys.length - 1 ? remaining : r2(disc * g.base / subtotal);
        g.base -= share; remaining = r2(remaining - share);
      });
    }

    const physical = currentAreaType() === 'physical';
    const shipping = physical ? r2(Math.max(0, parseNum($('[data-shipping-cost]', form).value))) : 0;
    if (shipping > 0) {
      const { rate, label } = resolveRate('default');
      const key = rate.toFixed(3) + '|' + label;
      if (!groups.has(key)) groups.set(key, { label, rate, base: 0 });
      groups.get(key).base += shipping;
    }

    let taxTotal = 0;
    const breakdown = [...groups.values()].map((g) => {
      const base = r2(g.base); const tax = r2(base * g.rate / 100); taxTotal += tax;
      return { label: g.label, rate: g.rate, base, tax };
    }).sort((a, b) => b.rate - a.rate);
    taxTotal = r2(taxTotal);
    const net = r2(subtotal - disc + shipping);
    const gross = r2(net + taxTotal);
    const total = cfg.round005 ? r2(Math.round(gross * 20) / 20) : gross;
    const rounding = r2(total - gross);

    const set = (k, v) => { const el = $(`[data-t="${k}"]`, form); if (el) el.textContent = v; };
    const show = (k, on) => { const el = $(`[data-row="${k}"]`, form); if (el) el.hidden = !on; };
    set('subtotal', fmt(subtotal));
    set('discount', '−' + fmt(disc).replace(cfg.currency + ' ', cfg.currency + ' ')); show('discount', disc > 0);
    set('shipping', shipping > 0 ? fmt(shipping) : 'kostenlos'); show('shipping', physical);
    set('net', fmt(net));
    set('rounding', fmt(rounding)); show('rounding', rounding !== 0);
    const totalEl = $('[data-t="total"]', form);
    const totalTxt = fmt(total);
    if (totalEl.textContent !== totalTxt) { totalEl.textContent = totalTxt; totalEl.classList.remove('number-tick'); void totalEl.offsetWidth; totalEl.classList.add('number-tick'); }
    $('[data-t="taxes"]', form).innerHTML = breakdown.filter((t) => t.base !== 0 || t.tax !== 0).map((t) =>
      `<div class="dl-row"><dt>${esc(t.label || 'Steuer')}<span class="block text-xs text-slate-400">auf ${fmt(t.base)}</span></dt><dd class="tabular">${fmt(t.tax)}</dd></div>`).join('');
  };

  /* ------------------------------------------------ Kunde, Bereich, Steuervorschlag */
  const currentAreaType = () => {
    const r = $('[data-area]:checked', form);
    return r && cfg.areas[r.value] ? cfg.areas[r.value].type : null;
  };

  const applyArea = () => {
    const type = currentAreaType();
    const phys = $('[data-physical]', form);
    phys.hidden = type !== 'physical';
    $$('input, select, textarea', phys).forEach((el) => { el.disabled = type !== 'physical'; });
    $('[data-delivery-label]', form).textContent = type === 'digital' ? 'Bereitstellung' : 'Lieferhinweis';
    $('[data-delivery-info]', form).placeholder = type === 'digital' ? 'z. B. Zugang per E-Mail innert 2 Arbeitstagen' : 'z. B. Lieferung innert 10 Arbeitstagen';
    recalc();
  };

  const applyCustomer = (fromUser) => {
    const c = currentCustomer();
    const info = $('[data-customer-info]', form);
    info.innerHTML = c ? `<i class="bx bx-map align-middle"></i> ${esc(c.address)} · <i class="bx bx-envelope align-middle"></i> ${esc(c.email)}` : '';
    // MWST: Kunden im Ausland (ausser Liechtenstein) → Hinweis und Vorschlag der Steuerart
    const hint = $('[data-tax-hint]', form);
    const sel = $('[data-default-tax]', form);
    const country = c && c.country ? String(c.country).toUpperCase().trim() : '';
    const foreign = cfg.liable && country && country !== 'CH' && country !== 'LI';
    if (hint) {
      hint.classList.toggle('text-amber-700', !!foreign);
      hint.textContent = foreign
        ? 'Kunde im Ausland (' + country + '): Dienstleistungen sind am Empfängerort zu versteuern → «Im Ausland erbracht»; Warenlieferungen ins Ausland → «Ausfuhr, steuerbefreit». Schweizer MWST nur bei Ausnahmen (Grundstück, Veranstaltung, Gastgewerbe, Leistung an anwesende Personen).'
        : 'Gilt für alle Positionen mit „Standard“ sowie den Versand. Pro Position lässt sich eine andere Steuerart wählen.';
    }
    if (fromUser === true && sel && cfg.liable) {
      const areaEl = $('[data-area]:checked', form);
      const type = areaEl && cfg.areas[areaEl.value] ? cfg.areas[areaEl.value].type : 'digital';
      const want = foreign ? (type === 'physical' ? 'export' : 'abroad') : 'taxable';
      const cur = cfg.taxes.find((t) => String(t.id) === String(sel.value));
      const curCls = cur ? cur.cls : 'taxable';
      if (foreign || curCls === 'export' || curCls === 'abroad') {
        const cand = cfg.taxes.find((t) => t.cls === want && (want !== 'taxable' || t.def));
        if (cand) sel.value = String(cand.id);
      }
    }
    recalc();
  };

  /* ------------------------------------------------ Vorlagen */
  const tplList = $('[data-tpl-list]');
  const renderTemplates = (q = '') => {
    const needle = q.trim().toLowerCase();
    const areaId = ($('[data-area]:checked', form) || {}).value;
    const items = cfg.templates
      .filter((t) => !needle || (t.title + ' ' + (t.description || '')).toLowerCase().includes(needle))
      .sort((a, b) => (String(b.area) === String(areaId)) - (String(a.area) === String(areaId)));
    if (!items.length) {
      tplList.innerHTML = `<li class="empty-state py-10"><i class="bx bx-bookmarks"></i><p class="text-sm text-slate-500">${cfg.templates.length ? 'Keine passenden Vorlagen.' : 'Noch keine Vorlagen vorhanden.'}</p></li>`;
      return;
    }
    tplList.innerHTML = items.map((t) => `
      <li><button type="button" class="flex w-full items-start gap-4 px-5 py-3.5 text-left transition hover:bg-slate-50 sm:px-6" data-insert-tpl="${t.id}">
        <span class="mt-0.5 grid size-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-lg text-brand-600"><i class="bx bx-bookmark"></i></span>
        <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium text-slate-900">${esc(t.title)}</span>
          <span class="block truncate text-xs text-slate-500">${esc(t.description || '')}</span>
          ${t.area_name ? `<span class="badge badge-slate mt-1">${esc(t.area_name)}</span>` : ''}</span>
        <span class="whitespace-nowrap text-sm font-semibold text-slate-900 tabular">${fmt(t.unit_price)}<span class="block text-right text-xs font-normal text-slate-400">/ ${esc(t.unit || '')}</span></span>
      </button></li>`).join('');
  };

  const saveTemplate = async (row) => {
    const d = rowData(row);
    if (!d.title.trim()) { window.toast('Bitte zuerst einen Titel eingeben.', 'error'); return; }
    const body = new FormData();
    body.append('_csrf', document.querySelector('meta[name="csrf-token"]').content);
    body.append('title', d.title); body.append('description', d.description); body.append('unit', d.unit);
    body.append('unit_price', d.unit_price); if (d.image) body.append('image', d.image);
    if (d.tax && d.tax !== 'default') body.append('tax_template_id', d.tax);
    const area = $('[data-area]:checked', form); if (area) body.append('product_area_id', area.value);
    try {
      const res = await fetch(cfg.quickUrl, { method: 'POST', body, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
      const json = await res.json();
      if (!json.ok) throw new Error(json.error || 'Speichern fehlgeschlagen.');
      const t = json.template;
      cfg.templates.push({ id: t.id, title: t.title, description: t.description, unit: t.unit, unit_price: Number(t.unit_price), tax: t.tax_template_id, area: t.product_area_id, area_name: area ? cfg.areas[area.value].name : null });
      window.toast(`Vorlage „${t.title}“ gespeichert.`, 'success');
    } catch (e) { window.toast(e.message, 'error'); }
  };

  /* ------------------------------------------------ Events */
  form.addEventListener('input', (e) => { if (e.target.matches('[data-calc]')) recalc(); });
  form.addEventListener('change', (e) => {
    if (e.target.matches('[data-customer]')) applyCustomer(true);
    else if (e.target.matches('[data-area]')) applyArea();
    else if (e.target.matches('[data-default-tax]')) { recalc(); }
    else if (e.target.matches('[data-shipping-method]')) {
      const m = cfg.shipping.find((s) => s.name === e.target.value);
      if (m) { $('[data-shipping-cost]', form).value = m.cost.toFixed(2); recalc(); }
    } else if (e.target.matches('[data-calc]')) recalc();
  });
  form.addEventListener('change', (e) => {
    if (!e.target.matches('[data-img-file]')) return;
    const f = e.target.files[0]; e.target.value = '';
    if (f) uploadImg(e.target.closest('[data-img]'), f);
  });
  $('[data-shipping-method]', form).addEventListener('input', (e) => {
    const m = cfg.shipping.find((s) => s.name === e.target.value);
    if (m) { $('[data-shipping-cost]', form).value = m.cost.toFixed(2); recalc(); }
  });

  form.addEventListener('click', (e) => {
    const pick = e.target.closest('[data-img-pick]');
    if (pick) { $('[data-img-file]', pick.closest('[data-img]')).click(); return; }
    const clr = e.target.closest('[data-img-clear]');
    if (clr) { setImg(clr.closest('[data-img]'), ''); return; }
    if (e.target.closest('[data-add-item]')) {
      const row = addRow({});
      $('input', row).focus();
      return;
    }
    const row = e.target.closest('[data-row]');
    if (!row) return;
    if (e.target.closest('[data-remove]')) {
      if ($$('[data-row]', list).length === 1) { window.toast('Eine Offerte braucht mindestens eine Position.', 'info'); return; }
      row.classList.add('is-removing');
      setTimeout(() => { row.remove(); renumber(); recalc(); }, window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 200);
    } else if (e.target.closest('[data-duplicate]')) {
      addRow(rowData(row), row);
    } else if (e.target.closest('[data-save-template]')) {
      saveTemplate(row);
    } else if (e.target.closest('[data-move]')) {
      const dir = Number(e.target.closest('[data-move]').dataset.move);
      const sib = dir < 0 ? row.previousElementSibling : row.nextElementSibling;
      if (!sib) return;
      dir < 0 ? sib.before(row) : sib.after(row);
      row.classList.remove('flash-row'); void row.offsetWidth; row.classList.add('flash-row');
      renumber();
    }
  });

  if (tplList) {
    $('[data-tpl-search]').addEventListener('input', (e) => renderTemplates(e.target.value));
    document.querySelectorAll('[data-modal-open="modal-templates"]').forEach((b) => b.addEventListener('click', () => { $('[data-tpl-search]').value = ''; renderTemplates(); }));
    tplList.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-insert-tpl]');
      if (!btn) return;
      const t = cfg.templates.find((x) => String(x.id) === btn.dataset.insertTpl);
      // Leere erste Zeile ersetzen statt eine weitere hinzuzufügen.
      const rows = $$('[data-row]', list);
      const empty = rows.find((r) => { const d = rowData(r); return !d.title && !d.unit_price; });
      const data = { title: t.title, description: t.description || '', quantity: '1', unit: t.unit, unit_price: Number(t.unit_price).toFixed(2), tax: t.tax || 'default', image: t.image || '' };
      const newRow = addRow(data, empty || null);
      if (empty) empty.remove();
      renumber(); recalc();
      window.appModal.close(document.getElementById('modal-templates'));
      newRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
      window.toast(`„${t.title}“ eingefügt.`, 'success');
    });
  }

  /* ------------------------------------------------ Leistungskatalog-Suche direkt im Titelfeld */
  const sug = document.createElement('ul');
  sug.className = 'absolute inset-x-0 top-full z-30 mt-1 hidden max-h-64 overflow-y-auto rounded-xl bg-white py-1 shadow-lg ring-1 ring-slate-200';
  sug.setAttribute('role', 'listbox');
  let sugInput = null; let sugMatches = []; let sugIdx = -1;
  const closeSug = () => { sug.classList.add('hidden'); sugInput = null; sugMatches = []; sugIdx = -1; };
  const paintSug = () => {
    sug.innerHTML = sugMatches.map((t, i) => `<li role="option" aria-selected="${i === sugIdx}" data-sug="${i}" class="flex cursor-pointer items-center gap-3 px-3.5 py-2 text-sm ${i === sugIdx ? 'bg-brand-50' : 'hover:bg-slate-50'}">
      <i class="bx bx-bookmark text-brand-600"></i><span class="min-w-0 flex-1"><span class="block truncate font-medium text-slate-900">${esc(t.title)}</span><span class="block truncate text-xs text-slate-500">${esc(t.description || '')}</span></span>
      <span class="whitespace-nowrap text-xs font-semibold text-slate-700 tabular">${fmt(t.unit_price)}</span></li>`).join('') + '<li class="border-t border-slate-100 px-3.5 py-1.5 text-[11px] text-slate-400">Katalog-Treffer – ↑↓ wählen, Enter übernimmt</li>';
  };
  const applyTpl = (row, t) => {
    const set = (f, v) => { const el = $(`[name$="[${f}]"]`, row); if (el) el.value = v; };
    set('title', t.title); set('description', t.description || ''); { const w = $('[data-img]', row); if (w) setImg(w, t.image || ''); } set('unit', t.unit || 'Stk.'); set('unit_price', Number(t.unit_price).toFixed(2));
    const taxSel = $('[name$="[tax]"]', row);
    if (taxSel && [...taxSel.options].some((o) => o.value === String(t.tax || 'default'))) taxSel.value = String(t.tax || 'default');
    autogrow($('[data-autogrow]', row)); recalc(); closeSug();
    const q = $('[name$="[quantity]"]', row); if (q) { q.focus(); q.select(); }
  };
  list.addEventListener('input', (e) => {
    if (!e.target.matches('[name$="[title]"]')) return;
    const q = e.target.value.trim().toLowerCase();
    if (q.length < 2) { closeSug(); return; }
    sugMatches = cfg.templates.filter((t) => (t.title + ' ' + (t.description || '')).toLowerCase().includes(q) && t.title.toLowerCase() !== q).slice(0, 6);
    if (!sugMatches.length) { closeSug(); return; }
    sugInput = e.target; sugIdx = -1;
    e.target.parentElement.classList.add('relative');
    e.target.parentElement.appendChild(sug);
    sug.classList.remove('hidden'); paintSug();
  });
  list.addEventListener('keydown', (e) => {
    if (!sugInput || e.target !== sugInput) return;
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); sugIdx = (sugIdx + (e.key === 'ArrowDown' ? 1 : -1) + sugMatches.length) % sugMatches.length; paintSug(); }
    else if (e.key === 'Enter' && sugIdx >= 0) { e.preventDefault(); applyTpl(sugInput.closest('[data-row]'), sugMatches[sugIdx]); }
    else if (e.key === 'Escape') { closeSug(); }
  });
  sug.addEventListener('mousedown', (e) => {
    const li = e.target.closest('[data-sug]');
    if (!li || !sugInput) return;
    e.preventDefault();
    applyTpl(sugInput.closest('[data-row]'), sugMatches[Number(li.dataset.sug)]);
  });
  document.addEventListener('click', (e) => { if (sugInput && e.target !== sugInput && !sug.contains(e.target)) closeSug(); });

  // Leere Zeilen vor dem Absenden entfernen (Server ignoriert sie ebenfalls).
  form.addEventListener('submit', () => {
    $$('[data-item-tax]', list).forEach((s) => { s.disabled = false; });
    $('[data-default-tax]', form).disabled = false;
  });

  /* ------------------------------------------------ Start */
  list.innerHTML = '';
  (cfg.items.length ? cfg.items : [{}]).forEach((it) => addRow(it, null, false));
  const unitList = document.createElement('datalist');
  unitList.id = 'unit-list';
  unitList.innerHTML = ['Stk.', 'Std.', 'Tag', 'Pauschal', 'Monat', 'Jahr', 'Lizenz', 'm', 'm²', 'kg', 'Paket'].map((u) => `<option value="${u}">`).join('');
  document.body.appendChild(unitList);
  const hadTax = !!$('[data-default-tax]', form).value && !!cfg.items.length;
  applyCustomer(!hadTax || !currentCustomer());
  // Ablaufdatum: Schnellwahl und «Ohne Ablaufdatum»
  const vu = $('[data-valid-until]', form), vn = $('[data-valid-none]', form);
  if (vu && vn) {
    const syncValid = () => { vu.disabled = vn.checked; $$('[data-valid-days]', form).forEach((b) => { b.disabled = vn.checked; }); };
    vn.addEventListener('change', syncValid);
    $$('[data-valid-days]', form).forEach((b) => b.addEventListener('click', () => {
      const d = new Date(); d.setDate(d.getDate() + parseInt(b.dataset.validDays, 10));
      vu.value = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
      vn.checked = false; syncValid();
    }));
    syncValid();
  }
  applyArea();
})();
