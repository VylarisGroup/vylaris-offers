<?php
$title = 'Offerte ' . $offer['number'];
$c = $offer['customer'];
$open = $offer['status'] === 'open';
$pid = $offer['public_id'];
?>
<div class="mx-auto max-w-4xl">

  <!-- Statusbereich -->
  <div class="reveal no-print mb-6">
    <?php if ($open): ?>
      <div class="flex flex-col gap-4 rounded-2xl bg-white/80 p-5 shadow-soft ring-1 ring-slate-200/80 backdrop-blur sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
          <span class="relative grid size-12 shrink-0 place-items-center rounded-xl bg-brand-50 text-2xl text-brand-600"><i class="bx bx-file"></i></span>
          <div>
            <p class="font-semibold text-slate-900">Ihre Offerte wartet auf Ihre Entscheidung</p>
            <p class="text-sm text-slate-500">
              <?php if ($offer['valid_until']):
                $days = (int) floor((strtotime($offer['valid_until'] . ' 23:59:59') - time()) / 86400); ?>
                Gültig bis <?= fmt_date($offer['valid_until']) ?><?= $days <= 7 ? ' · <span class="font-medium text-amber-700">noch ' . ($days <= 0 ? 'heute' : $days . ' Tag' . ($days === 1 ? '' : 'e')) . '</span>' : '' ?>
              <?php else: ?>Unbefristet gültig<?php endif; ?>
            </p>
          </div>
        </div>
        <div class="flex flex-wrap gap-2">
          <a href="<?= e(url('o/' . $pid . '/pdf')) ?>" class="btn btn-ghost btn-sm"><i class="bx bx-download"></i>PDF</a><button type="button" class="btn btn-ghost btn-sm" data-print><i class="bx bx-printer"></i>Drucken</button>
        </div>
      </div>
    <?php elseif ($offer['status'] === 'accepted'): ?>
      <div class="flex flex-col gap-4 rounded-2xl bg-emerald-50 p-5 ring-1 ring-emerald-200 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
          <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-emerald-600 text-2xl text-white shadow-sm shadow-emerald-600/30"><i class="bx bx-check"></i></span>
          <div>
            <p class="font-semibold text-emerald-900">Offerte angenommen – vielen Dank!</p>
            <p class="text-sm text-emerald-800">Angenommen am <?= fmt_datetime($offer['responded_at']) ?><?= $offer['response_name'] ? ' von ' . e($offer['response_name']) : '' ?><?= $order ? ' · Auftrag <strong>' . e($order['number']) . '</strong>' : '' ?></p>
          </div>
        </div>
        <a href="<?= e(url('portal')) ?>" class="btn btn-success"><i class="bx bx-user-circle"></i>Status im Kundenportal</a>
      </div>
    <?php elseif ($offer['status'] === 'declined'): ?>
      <div class="flex items-start gap-4 rounded-2xl bg-slate-100 p-5 ring-1 ring-slate-200">
        <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-white text-2xl text-slate-500 shadow-sm"><i class="bx bx-x"></i></span>
        <div class="flex-1">
          <p class="font-semibold text-slate-900">Diese Offerte wurde abgelehnt</p>
          <p class="text-sm text-slate-600">am <?= fmt_datetime($offer['responded_at']) ?><?= $offer['response_note'] ? ' · ' . e($offer['response_note']) : '' ?></p>
          <?php if ($mailto): ?>
            <div class="mt-4 rounded-xl bg-white p-4 ring-1 ring-slate-200">
              <p class="text-sm text-slate-700">Ihr E-Mail-Programm sollte sich automatisch öffnen. Falls nicht, klicken Sie hier:</p>
              <a href="<?= e($mailto) ?>" class="btn btn-primary btn-sm mt-3" data-auto-open><i class="bx bx-envelope"></i>Anfrage per E-Mail senden</a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php elseif ($offer['status'] === 'superseded'): ?>
      <div class="flex flex-col gap-4 rounded-2xl bg-violet-50 p-5 ring-1 ring-violet-200 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
          <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-white text-2xl text-violet-600 shadow-sm"><i class="bx bx-git-branch"></i></span>
          <div>
            <p class="font-semibold text-violet-900">Diese Offerte wurde durch eine neuere Version ersetzt</p>
            <p class="text-sm text-violet-800">Bitte verwenden Sie die aktuelle Offerte<?= $successor ? ' ' . e($successor['number']) : '' ?>.</p>
          </div>
        </div>
        <?php if ($successor): ?><a href="<?= e(url('o/' . $successor['public_id'])) ?>" class="btn btn-primary"><i class="bx bx-right-arrow-alt"></i>Zur aktuellen Offerte</a><?php endif; ?>
      </div>
    <?php else: ?>
      <div class="flex items-center gap-4 rounded-2xl bg-amber-50 p-5 ring-1 ring-amber-200">
        <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-white text-2xl text-amber-600 shadow-sm"><i class="bx bx-calendar-x"></i></span>
        <div>
          <p class="font-semibold text-amber-900">Diese Offerte ist abgelaufen</p>
          <p class="text-sm text-amber-800">Die Gültigkeit endete am <?= fmt_date($offer['valid_until']) ?>. Gerne erstellen wir Ihnen eine neue Offerte<?= Settings::companyEmail() ? ' – schreiben Sie uns an <a class="link" href="mailto:' . e(Settings::companyEmail()) . '?subject=' . rawurlencode('Neue Offerte – Bezug ' . $offer['number']) . '">' . e(Settings::companyEmail()) . '</a>' : '' ?>.</p>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="reveal reveal-delay-1">
    <?php include APP_DIR . '/views/partials/offer-document.php'; ?>
    <?php $filesBase = url('o/' . $pid . '/files'); include APP_DIR . '/views/partials/attachments-public.php'; ?>
  </div>

  <?php if ($open): ?>
  <!-- Aktionen -->
  <section id="annehmen" class="reveal no-print mt-8 scroll-mt-6">
    <div class="card p-5 sm:p-8">
      <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h3 class="text-lg font-semibold text-slate-900">Wie möchten Sie fortfahren?</h3>
          <p class="mt-1 max-w-md text-sm text-slate-500">Mit der Annahme wird automatisch ein Auftrag eröffnet. Sie erhalten eine Bestätigung per E-Mail.</p>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap lg:justify-end">
          <button type="button" class="btn btn-ghost order-3 sm:order-1" data-modal-open="modal-new"><i class="bx bx-revision"></i>Neue Offerte anfragen</button>
          <button type="button" class="btn btn-secondary order-2" data-modal-open="modal-decline"><i class="bx bx-x"></i>Ablehnen</button>
          <button type="button" class="btn btn-success btn-lg order-1 sm:order-3" data-modal-open="modal-accept"><i class="bx bx-check-circle"></i>Offerte annehmen</button>
        </div>
      </div>
    </div>
  </section>

  <!-- Modal: Annehmen -->
  <div class="modal" id="modal-accept" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="accept-title">
    <div class="modal-backdrop" data-modal-close></div>
    <form method="post" action="<?= e(url("o/$pid/accept")) ?>" class="modal-panel max-w-lg"><?= csrf_field() ?>
      <div class="flex items-start justify-between gap-4 border-b border-slate-100 p-6">
        <div class="flex items-center gap-3">
          <span class="grid size-11 place-items-center rounded-xl bg-emerald-50 text-2xl text-emerald-600"><i class="bx bx-check-shield"></i></span>
          <div>
            <h3 id="accept-title" class="text-base font-semibold text-slate-900">Offerte annehmen</h3>
            <p class="text-sm text-slate-500"><?= e($offer['number']) ?> · <?= money($offer['total'], $offer['currency']) ?></p>
          </div>
        </div>
        <button type="button" class="btn btn-ghost btn-icon" data-modal-close aria-label="Schliessen"><i class="bx bx-x text-2xl"></i></button>
      </div>
      <div class="space-y-4 p-6">
        <?php $sigMode = SignatureService::mode($offer); ?>
        <div>
          <label class="label" for="acc-name"><?= $sigMode === 'strict' ? 'Unterschreibende Person' : 'Ihr Name' ?> <span class="text-rose-500">*</span></label>
          <?php if ($sigMode === 'strict'): ?>
            <input class="input bg-slate-50" id="acc-name" value="<?= e($offer['signer_name']) ?>" readonly tabindex="-1">
            <p class="hint">Diese Offerte kann nur von dieser Person unterschrieben werden.</p>
          <?php else: ?>
            <input class="input" id="acc-name" name="name" value="<?= e(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''))) ?>" required maxlength="190" autocomplete="name">
          <?php endif; ?>
        </div>
        <?php {
          $d = $offer['delivery'] ?: ['company' => $c['company'] ?? '', 'name' => trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')), 'street' => $c['street'] ?? '', 'address_extra' => $c['address_extra'] ?? '', 'zip' => $c['zip'] ?? '', 'city' => $c['city'] ?? '', 'country' => $c['country'] ?? 'CH']; ?>
          <div class="rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Lieferadresse</p>
            <p class="mt-1.5 text-sm leading-relaxed text-slate-700"><?= implode('<br>', array_map('e', address_lines($d))) ?></p>
            <label class="mt-3 flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-700">
              <input type="checkbox" class="checkbox" name="alt_delivery" value="1" data-collapse-toggle="#acc-addr">Andere Lieferadresse verwenden
            </label>
          </div>
          <div class="fold" id="acc-addr">
            <div class="grid gap-3 sm:grid-cols-2">
              <input class="input sm:col-span-2" name="delivery[company]" placeholder="Firma (optional)" value="<?= e($d['company']) ?>">
              <input class="input sm:col-span-2" name="delivery[name]" placeholder="Name *" value="<?= e($d['name']) ?>">
              <input class="input sm:col-span-2" name="delivery[street]" placeholder="Strasse und Nr. *" value="<?= e($d['street']) ?>">
              <input class="input sm:col-span-2" name="delivery[address_extra]" placeholder="Adresszusatz" value="<?= e($d['address_extra']) ?>">
              <input class="input" name="delivery[zip]" placeholder="PLZ *" value="<?= e($d['zip']) ?>">
              <input class="input" name="delivery[city]" placeholder="Ort *" value="<?= e($d['city']) ?>">
              <select class="input sm:col-span-2" name="delivery[country]">
                <?php foreach (countries() as $code => $name): ?><option value="<?= $code ?>"<?= $code === $d['country'] ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
        <?php } ?>
        <div>
          <label class="label" for="acc-note">Bemerkung <span class="font-normal text-slate-400">(optional)</span></label>
          <textarea class="input" id="acc-note" name="note" rows="3" maxlength="2000" placeholder="z. B. gewünschter Liefertermin"></textarea>
        </div>
        <?php if ($sigMode !== 'none'): ?>
        <div data-sigpad>
          <div class="mb-1.5 flex items-center justify-between">
            <label class="label !mb-0">Unterschrift <span class="text-rose-500">*</span></label>
            <button type="button" class="btn btn-ghost btn-sm" data-sig-clear><i class="bx bx-eraser"></i>Löschen</button>
          </div>
          <canvas class="h-36 w-full touch-none rounded-xl bg-white ring-1 ring-slate-300" data-sig-canvas aria-label="Unterschriftsfeld – mit Maus oder Finger unterschreiben"></canvas>
          <input type="hidden" name="signature" data-sig-input data-required="1">
          <p class="hint">Mit Finger oder Maus im Feld unterschreiben.</p>
        </div>
        <?php endif; ?>
        <?php if ($sigMode === 'strict'): ?>
        <div class="rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70" data-codebox data-code-url="<?= e(url("o/$pid/code")) ?>">
          <p class="text-sm font-semibold text-slate-800"><i class="bx bx-envelope align-middle"></i> E-Mail-Bestätigung</p>
          <p class="mt-1 text-sm text-slate-600">Zur Bestätigung senden wir einen 6-stelligen Code an <strong><?= e(SignatureService::maskEmail((string) $offer['signer_email'])) ?></strong>.</p>
          <div class="mt-3 flex flex-col gap-2 sm:flex-row">
            <button type="button" class="btn btn-secondary btn-sm" data-code-send><i class="bx bx-send"></i>Code senden</button>
            <input class="input sm:max-w-[11rem]" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" placeholder="Code" required aria-label="Bestätigungscode">
          </div>
          <p class="hint" data-code-msg role="status"></p>
        </div>
        <?php endif; ?>
        <label class="flex cursor-pointer items-start gap-3 rounded-xl bg-emerald-50/60 p-4 text-sm leading-relaxed text-slate-700 ring-1 ring-emerald-200/70">
          <input type="checkbox" class="checkbox mt-0.5" name="confirm" value="1" required>
          <span>Ich nehme die Offerte <strong><?= e($offer['number']) ?></strong> über <strong><?= money($offer['total'], $offer['currency']) ?></strong> verbindlich an.</span>
        </label>
      </div>
      <div class="flex flex-col-reverse gap-2 rounded-b-2xl bg-slate-50 px-6 py-4 sm:flex-row sm:justify-end">
        <button type="button" class="btn btn-secondary" data-modal-close>Abbrechen</button>
        <button class="btn btn-success"><i class="bx bx-check"></i>Verbindlich annehmen</button>
      </div>
    </form>
  </div>

  <!-- Modal: Ablehnen -->
  <div class="modal" id="modal-decline" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="decline-title">
    <div class="modal-backdrop" data-modal-close></div>
    <form method="post" action="<?= e(url("o/$pid/decline")) ?>" class="modal-panel max-w-lg"><?= csrf_field() ?>
      <div class="flex items-start justify-between gap-4 border-b border-slate-100 p-6">
        <div class="flex items-center gap-3">
          <span class="grid size-11 place-items-center rounded-xl bg-rose-50 text-2xl text-rose-600"><i class="bx bx-x-circle"></i></span>
          <div><h3 id="decline-title" class="text-base font-semibold text-slate-900">Offerte ablehnen</h3><p class="text-sm text-slate-500">Ihr Feedback hilft uns weiter.</p></div>
        </div>
        <button type="button" class="btn btn-ghost btn-icon" data-modal-close aria-label="Schliessen"><i class="bx bx-x text-2xl"></i></button>
      </div>
      <div class="space-y-4 p-6">
        <input type="hidden" name="name" value="<?= e(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''))) ?>">
        <div>
          <label class="label" for="dec-reason">Grund <span class="font-normal text-slate-400">(optional)</span></label>
          <textarea class="input" id="dec-reason" name="reason" rows="3" maxlength="2000" placeholder="z. B. Budget, Zeitpunkt, anderer Anbieter …"></textarea>
        </div>
      </div>
      <div class="flex flex-col-reverse gap-2 rounded-b-2xl bg-slate-50 px-6 py-4 sm:flex-row sm:justify-end">
        <button type="button" class="btn btn-secondary" data-modal-close>Abbrechen</button>
        <button class="btn btn-danger"><i class="bx bx-x"></i>Offerte ablehnen</button>
      </div>
    </form>
  </div>

  <!-- Modal: Neue Offerte anfragen -->
  <div class="modal" id="modal-new" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="new-title">
    <div class="modal-backdrop" data-modal-close></div>
    <form method="post" action="<?= e(url("o/$pid/request-new")) ?>" class="modal-panel max-w-lg"><?= csrf_field() ?>
      <div class="flex items-start justify-between gap-4 border-b border-slate-100 p-6">
        <div class="flex items-center gap-3">
          <span class="grid size-11 place-items-center rounded-xl bg-brand-50 text-2xl text-brand-600"><i class="bx bx-revision"></i></span>
          <div><h3 id="new-title" class="text-base font-semibold text-slate-900">Neue Offerte anfragen</h3><p class="text-sm text-slate-500">Änderungswünsche per E-Mail senden</p></div>
        </div>
        <button type="button" class="btn btn-ghost btn-icon" data-modal-close aria-label="Schliessen"><i class="bx bx-x text-2xl"></i></button>
      </div>
      <div class="space-y-3 p-6 text-sm leading-relaxed text-slate-600">
        <input type="hidden" name="name" value="<?= e(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''))) ?>">
        <p class="flex gap-2.5 rounded-xl bg-amber-50 p-3.5 text-amber-900 ring-1 ring-amber-200"><i class="bx bx-info-circle mt-0.5 text-lg"></i><span>Die aktuelle Offerte <strong><?= e($offer['number']) ?></strong> wird dabei <strong>abgelehnt</strong>.</span></p>
        <p>Anschliessend öffnet sich Ihr E-Mail-Programm mit einer vorbereiteten Nachricht an <?= e(Settings::companyEmail() ?: Settings::get('company_name')) ?>, in der Sie Ihre Wünsche beschreiben können.</p>
      </div>
      <div class="flex flex-col-reverse gap-2 rounded-b-2xl bg-slate-50 px-6 py-4 sm:flex-row sm:justify-end">
        <button type="button" class="btn btn-secondary" data-modal-close>Abbrechen</button>
        <button class="btn btn-primary"><i class="bx bx-envelope"></i>Ablehnen &amp; E-Mail öffnen</button>
      </div>
    </form>
  </div>
  <?php else: ?>
  <div class="no-print mt-6 flex justify-center">
    <a href="<?= e(url('o/' . $pid . '/pdf')) ?>" class="btn btn-ghost btn-sm"><i class="bx bx-download"></i>PDF herunterladen</a><button type="button" class="btn btn-ghost btn-sm" data-print><i class="bx bx-printer"></i>Drucken</button>
  </div>
  <?php endif; ?>
</div>

<script>
(function () {
  document.querySelectorAll('[data-codebox]').forEach(function (box) {
    var btn = box.querySelector('[data-code-send]'), msg = box.querySelector('[data-code-msg]'), form = box.closest('form');
    btn.addEventListener('click', function () {
      btn.disabled = true; msg.textContent = 'Wird gesendet …';
      var fd = new FormData(); fd.append('_csrf', form.querySelector('[name=_csrf]').value);
      fetch(box.dataset.codeUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) { msg.textContent = j.message; msg.className = 'hint ' + (j.ok ? 'text-emerald-700' : 'text-rose-600'); setTimeout(function () { btn.disabled = false; }, j.ok ? 60000 : 3000); })
        .catch(function () { msg.textContent = 'Senden fehlgeschlagen. Bitte erneut versuchen.'; btn.disabled = false; });
    });
  });
  document.querySelectorAll('[data-sigpad]').forEach(function (pad) {
    var cv = pad.querySelector('[data-sig-canvas]'), input = pad.querySelector('[data-sig-input]'), form = pad.closest('form');
    var ctx = cv.getContext('2d'), drawing = false, dirty = false, last = null;
    function setup() {
      var r = cv.getBoundingClientRect(); if (!r.width) return;
      var ratio = Math.max(window.devicePixelRatio || 1, 1), keep = dirty ? cv.toDataURL() : null;
      cv.width = Math.round(r.width * ratio); cv.height = Math.round(r.height * ratio);
      ctx.setTransform(ratio, 0, 0, ratio, 0, 0); ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, r.width, r.height);
      ctx.lineWidth = 2.2; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#0f172a';
      if (keep) { var img = new Image(); img.onload = function () { ctx.drawImage(img, 0, 0, r.width, r.height); }; img.src = keep; }
    }
    function pos(e) { var r = cv.getBoundingClientRect(); return { x: e.clientX - r.left, y: e.clientY - r.top }; }
    cv.addEventListener('pointerdown', function (e) { e.preventDefault(); cv.setPointerCapture(e.pointerId); drawing = true; last = pos(e); ctx.beginPath(); ctx.arc(last.x, last.y, 1.1, 0, 6.3); ctx.fill(); dirty = true; });
    cv.addEventListener('pointermove', function (e) { if (!drawing) return; var p = pos(e); ctx.beginPath(); ctx.moveTo(last.x, last.y); ctx.lineTo(p.x, p.y); ctx.stroke(); last = p; });
    ['pointerup', 'pointercancel'].forEach(function (n) { cv.addEventListener(n, function () { drawing = false; }); });
    pad.querySelector('[data-sig-clear]').addEventListener('click', function () { dirty = false; input.value = ''; setup(); });
    form.addEventListener('submit', function (e) {
      if (dirty) { input.value = cv.toDataURL('image/png'); }
      else if (input.dataset.required) { e.preventDefault(); cv.classList.add('ring-2', 'ring-rose-500'); window.toast && window.toast('Bitte unterschreiben Sie im Feld.', 'error'); }
    });
    document.querySelectorAll('[data-modal-open]').forEach(function (b) { b.addEventListener('click', function () { setTimeout(setup, 60); }); });
    window.addEventListener('resize', function () { if (!dirty) setup(); });
    setup();
  });
})();
</script>
