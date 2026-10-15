<?php
$offer = $inv; // Dokument-Partial erwartet $offer
$isInvoice = true;
$ds = $inv['display_status'];
$isCredit = $inv['kind'] === 'credit';
$title = ($isCredit ? 'Gutschrift ' : 'Rechnung ') . $inv['number'];
$titleHtml = e($inv['number']) . ' <span class="ml-2 align-middle">' . Status::badge('invoice', $ds) . '</span>';
$subtitle = e($inv['title']) . ' · <a class="link" href="' . e(url('admin/customers/' . $inv['customer_id'])) . '">' . e(customer_name($inv['customer'])) . '</a>';
$back = ['admin/invoices', 'Rechnungen'];
$review = $review ?? [];
$credits = $credits ?? [];
$publicUrl = InvoiceService::url($inv);
$actions = '<a href="' . e(url('admin/invoices/' . $inv['id'] . '/pdf')) . '" class="btn btn-secondary"><i class="bx bx-download"></i>PDF</a>' . ($inv['payments'] && $inv['status'] !== 'cancelled' ? '<a href="' . e(url('r/' . $inv['public_id'] . '/receipt')) . '" class="btn btn-secondary"><i class="bx bx-receipt"></i>Quittung</a>' : '') . ($inv['status'] !== 'draft' ? '<a href="' . e($publicUrl) . '" target="_blank" rel="noopener" class="btn btn-secondary"><i class="bx bx-link-external"></i>Kundenansicht</a>' : '');
include APP_DIR . '/views/partials/page-header.php';
$act = static fn(string $a) => e(url('admin/invoices/' . $inv['id'] . '/' . $a));
?>
<div class="grid gap-6 xl:grid-cols-3">
  <div class="reveal space-y-6 xl:col-span-2">
    <?php if ($inv['status'] === 'cancelled'): ?>
      <div class="flex gap-3 rounded-2xl bg-slate-100 p-4 text-sm text-slate-700 ring-1 ring-slate-200"><i class="bx bx-block text-xl"></i><p><strong>Diese Rechnung wurde storniert.</strong> Die Nummer bleibt reserviert. <?= $inv['payment_note'] ? e($inv['payment_note']) : '' ?></p></div>
    <?php endif; ?>
    <?php include APP_DIR . '/views/partials/tax-review.php'; ?>
    <?php if ($isCredit && $inv['credit_of']): ?>
      <div class="flex gap-3 rounded-2xl bg-slate-50 p-4 text-sm text-slate-700 ring-1 ring-slate-200"><i class="bx bx-undo text-xl"></i><p>Gutschrift zu Rechnung <a class="link" href="<?= e(url('admin/invoices/' . $inv['credit_of'])) ?>"><?= e((string) $inv['credit_of_number']) ?></a>. <?= $inv['status'] === 'draft' ? 'Sie wirkt erst, wenn Sie sie ausstellen.' : 'Ausgestellt und mit der Rechnung verrechnet.' ?></p></div>
    <?php endif; ?>
    <?php include APP_DIR . '/views/partials/offer-document.php'; ?>
  </div>

  <aside class="space-y-6">
    <section class="reveal reveal-delay-1 card p-5 sm:p-6">
      <h2 class="text-sm font-semibold text-slate-900">Aktionen</h2>
      <div class="mt-3 space-y-2">
      <?php if ($inv['status'] === 'draft'): ?>
        <form method="post" action="<?= $act('send') ?>" data-confirm="<?= $isCredit ? 'Gutschrift ausstellen und per E-Mail an ' : 'Rechnung per E-Mail an ' ?><?= e($inv['customer']['email'] ?? '') ?> senden? Danach ist sie nicht mehr bearbeitbar." data-confirm-label="Senden" data-confirm-variant="primary"><?= csrf_field() ?>
          <button class="btn btn-primary w-full"><i class="bx bx-send"></i><?= $isCredit ? 'Gutschrift ausstellen &amp; senden' : 'Rechnung senden' ?></button></form>
      <?php elseif ($inv['status'] === 'sent'): ?>
        <form method="post" action="<?= $act('paid') ?>" class="space-y-2 rounded-xl bg-emerald-50/60 p-3 ring-1 ring-emerald-200"><?= csrf_field() ?>
          <p class="text-xs font-semibold text-emerald-900">Zahlungseingang erfassen<?= $inv['paid_total'] > 0 ? ' · offen ' . money($inv['open_amount'], $inv['currency']) : '' ?></p>
          <div class="grid grid-cols-2 gap-2">
            <div><label class="label">Betrag</label><input class="input tabular" name="amount" inputmode="decimal" value="<?= e(number_format((float) ($inv['pay_amount'] ?: $inv['open_amount']), 2, '.', '')) ?>"></div>
            <div><label class="label">Zahlungsdatum</label><input class="input" type="date" name="paid_at" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>"></div>
            <div><label class="label">Zahlungsart</label><select class="input" name="method"><option value="bank">Überweisung</option><option value="cash">Bar</option><option value="other">Sonstige</option></select></div>
            <div><label class="label">Notiz</label><input class="input" name="note" maxlength="255" placeholder="z. B. Rate 1"></div></div>
          <button class="btn btn-success w-full"><i class="bx bx-check"></i>Zahlung erfassen</button>
          <p class="text-[11px] text-emerald-800/80">Ist der Betrag kleiner als der offene Betrag, gilt die Rechnung als teilbezahlt.</p></form>
        <form method="post" action="<?= $act('send') ?>" data-confirm="Rechnung erneut an <?= e($inv['customer']['email'] ?? '') ?> senden?" data-confirm-label="Senden" data-confirm-variant="primary"><?= csrf_field() ?>
          <button class="btn btn-secondary w-full"><i class="bx bx-envelope"></i>Erneut senden</button></form>
        <form method="post" action="<?= $act('remind') ?>" data-confirm="Zahlungserinnerung an <?= e($inv['customer']['email'] ?? '') ?> senden?" data-confirm-label="Erinnerung senden" data-confirm-variant="primary"><?= csrf_field() ?>
          <button class="btn btn-secondary w-full"><i class="bx bx-bell"></i>Zahlungserinnerung senden<?= $inv['reminder_count'] ? ' (' . (int) $inv['reminder_count'] . ' bisher)' : '' ?></button></form>
      <?php elseif ($inv['status'] === 'paid' && $isCredit): ?>
        <p class="flex items-center gap-2 text-sm text-emerald-700"><i class="bx bx-check-circle text-lg"></i>Ausgestellt am <?= fmt_date($inv['paid_at']) ?></p>
        <form method="post" action="<?= $act('send') ?>" data-confirm="Gutschrift erneut senden?" data-confirm-label="Senden" data-confirm-variant="primary"><?= csrf_field() ?><input type="hidden" name="resend" value="1"><button class="btn btn-secondary w-full"><i class="bx bx-envelope"></i>Erneut senden</button></form>
      <?php elseif ($inv['status'] === 'paid'): ?>
        <p class="flex items-center gap-2 text-sm text-emerald-700"><i class="bx bx-check-circle text-lg"></i>Vollständig bezahlt am <?= fmt_date($inv['paid_at']) ?></p>
      <?php endif; ?>
      <?php if (($inv['status'] === 'draft') || ($inv['status'] === 'sent' && !$inv['payments'] && !$inv['adjustments'])): ?>
        <form method="post" action="<?= $act('cancel') ?>" data-confirm="<?= $isCredit ? 'Gutschriftsentwurf' : 'Rechnung' ?> <?= e($inv['number']) ?> stornieren? Die Nummer bleibt reserviert.<?= $inv['status'] === 'sent' ? ' Hinweis: Eine bereits versendete Rechnung wird in der MWST-Abrechnung nicht mehr berücksichtigt – bei Rechnungen aus früheren Abrechnungsperioden korrigieren Sie besser per Gutschrift.' : '' ?>" data-confirm-label="Stornieren"><?= csrf_field() ?>
          <button class="btn btn-ghost btn-sm w-full text-rose-600 hover:bg-rose-50 hover:text-rose-700"><i class="bx bx-block"></i>Stornieren</button></form>
      <?php endif; ?>
      </div>
    </section>

    <?php if (!$isCredit && in_array($inv['status'], ['sent', 'paid'], true)): ?>
    <section class="reveal card p-5 sm:p-6">
      <h2 class="text-sm font-semibold text-slate-900"><i class="bx bx-undo align-middle text-slate-400"></i> Korrekturen</h2>
      <p class="mt-1 text-xs text-slate-500">Nachträgliche Entgeltsminderungen mindern die geschuldete MWST (Art. 41 MWSTG).</p>
      <?php if ($credits): ?><ul class="mt-3 space-y-1 text-sm"><?php foreach ($credits as $cr): ?><li><a class="link" href="<?= e(url('admin/invoices/' . $cr['id'])) ?>"><?= e($cr['number']) ?></a> · <?= money(abs((float) $cr['amount_due']), $inv['currency']) ?> <span class="text-xs text-slate-400"><?= $cr['status'] === 'draft' ? '(Entwurf)' : '(ausgestellt)' ?></span></li><?php endforeach; ?></ul><?php endif; ?>
      <details class="mt-3 rounded-xl bg-slate-50 p-3 ring-1 ring-slate-200"><summary class="cursor-pointer text-sm font-medium text-slate-800">Gutschrift erstellen</summary>
        <form method="post" action="<?= $act('credit') ?>" class="mt-3 space-y-2"><?= csrf_field() ?>
          <div><label class="label">Betrag inkl. MWST <span class="font-normal text-slate-400">(leer = ganze Rechnung)</span></label><input class="input tabular" name="amount" inputmode="decimal" placeholder="z. B. 120.00"></div>
          <div><label class="label">Grund</label><input class="input" name="reason" maxlength="120" placeholder="z. B. Preisnachlass, Retoure"></div>
          <button class="btn btn-secondary w-full"><i class="bx bx-plus"></i>Entwurf erstellen</button>
          <p class="text-[11px] text-slate-500">Die Gutschrift erhält eine eigene Nummer; die Steuersätze folgen anteilig der Rechnung.</p></form></details>
      <?php if ($inv['status'] === 'sent'): ?>
      <details class="mt-2 rounded-xl bg-slate-50 p-3 ring-1 ring-slate-200"><summary class="cursor-pointer text-sm font-medium text-slate-800">Skonto / Preisnachlass erfassen</summary>
        <form method="post" action="<?= $act('discount') ?>" class="mt-3 space-y-2"><?= csrf_field() ?>
          <div class="grid grid-cols-2 gap-2"><div><label class="label">Betrag inkl. MWST</label><input class="input tabular" name="amount" inputmode="decimal" required></div>
          <div><label class="label">Datum</label><input class="input" type="date" name="date" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>"></div></div>
          <div><label class="label">Notiz</label><input class="input" name="note" maxlength="255" placeholder="z. B. 2 % Skonto"></div>
          <button class="btn btn-secondary w-full"><i class="bx bx-plus"></i>Erfassen</button>
          <p class="text-[11px] text-slate-500">Ohne eigenen Beleg; die Rechnung wird um den Betrag reduziert.</p></form></details>
      <?php endif; ?>
      <?php if ($inv['paid_total'] > 0): ?>
      <details class="mt-2 rounded-xl bg-slate-50 p-3 ring-1 ring-slate-200"><summary class="cursor-pointer text-sm font-medium text-slate-800">Rückerstattung erfassen</summary>
        <form method="post" action="<?= $act('refund') ?>" class="mt-3 space-y-2"><?= csrf_field() ?>
          <div class="grid grid-cols-2 gap-2"><div><label class="label">Betrag</label><input class="input tabular" name="amount" inputmode="decimal" required></div>
          <div><label class="label">Datum</label><input class="input" type="date" name="date" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>"></div></div>
          <div><label class="label">Notiz</label><input class="input" name="note" maxlength="255"></div>
          <button class="btn btn-secondary w-full"><i class="bx bx-plus"></i>Erfassen</button></form></details>
      <?php endif; ?>
      <?php if ($inv['adjustments']): ?>
      <ul class="mt-3 space-y-1.5 text-sm">
        <?php foreach ($inv['adjustments'] as $ad): ?>
        <li class="flex items-center justify-between gap-2"><span><?= $ad['kind'] === 'credit' ? 'Gutschrift ' . e((string) $ad['credit_number']) : 'Skonto/Nachlass' ?> · <?= fmt_date($ad['adj_date']) ?></span>
          <span class="flex items-center gap-1"><span class="tabular font-medium">−<?= money($ad['amount'], $inv['currency']) ?></span>
          <?php if ($ad['kind'] === 'discount'): ?><form method="post" action="<?= e(url('admin/invoices/' . $inv['id'] . '/adjustments/' . $ad['id'] . '/delete')) ?>" data-confirm="Eintrag entfernen?" data-confirm-label="Entfernen"><?= csrf_field() ?><button class="btn btn-ghost btn-sm text-rose-600"><i class="bx bx-trash"></i></button></form><?php endif; ?></span></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($inv['status'] === 'draft'): ?>
    <section class="reveal card p-5 sm:p-6">
      <h2 class="text-sm font-semibold text-slate-900">Entwurf bearbeiten</h2>
      <p class="mt-1 text-xs text-slate-500">Beträge stammen aus der Offerte und sind fix.</p>
      <form method="post" action="<?= $act('update') ?>" class="mt-3 space-y-3"><?= csrf_field() ?>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="label">Rechnungsdatum</label><input class="input" type="date" name="issue_date" value="<?= e($inv['issue_date']) ?>" required></div>
          <div><label class="label">Fällig am</label><input class="input" type="date" name="due_date" value="<?= e($inv['due_date']) ?>" required></div>
        </div>
        <div><label class="label">Leistungszeitraum <span class="font-normal text-slate-400">(optional)</span></label><input class="input" name="service_period" maxlength="120" value="<?= e($inv['service_period'] ?? '') ?>" placeholder="z. B. 01.09.–30.09.2026"><p class="hint">Nach Art. 26 MWSTG gilt ohne Angabe das Rechnungsdatum als Leistungsdatum.</p></div>
        <div><label class="label">Einleitung</label><textarea class="input" name="intro" rows="3"><?= e($inv['intro']) ?></textarea></div>
        <div><label class="label">Zahlungsinformationen</label><textarea class="input font-mono text-[13px]" name="payment_info" rows="3"><?= e($inv['payment_info']) ?></textarea></div>
        <div><label class="label">Schlusstext</label><textarea class="input" name="footer" rows="2"><?= e($inv['footer']) ?></textarea></div>
        <button class="btn btn-secondary w-full"><i class="bx bx-save"></i>Speichern</button>
      </form>
    </section>
    <?php endif; ?>

    <section class="reveal card p-5 sm:p-6">
      <h2 class="text-sm font-semibold text-slate-900">Details</h2>
      <?php if ($inv['status'] !== 'draft'): ?>
      <div class="mt-3 flex items-center gap-2 rounded-xl bg-slate-50 p-1.5 pl-3 ring-1 ring-slate-200">
        <code class="min-w-0 flex-1 truncate text-xs text-slate-600"><?= e($publicUrl) ?></code>
        <button type="button" class="btn btn-secondary btn-icon size-8" data-copy="<?= e($publicUrl) ?>" data-tip="Link kopieren"><i class="bx bx-copy"></i></button>
      </div>
      <?php endif; ?>
      <dl class="mt-3 divide-y divide-slate-100">
        <div class="dl-row"><dt>Auftrag</dt><dd><a class="link" href="<?= e(url('admin/orders/' . $order['id'])) ?>"><?= e($order['number']) ?></a></dd></div>
        <div class="dl-row"><dt>Erstellt</dt><dd><?= fmt_datetime($inv['created_at']) ?></dd></div>
        <?php if ($creator): ?><div class="dl-row"><dt>Von</dt><dd><?= e($creator['name']) ?></dd></div><?php endif; ?>
        <div class="dl-row"><dt>Versendet</dt><dd><?= $inv['sent_at'] ? fmt_datetime($inv['sent_at']) : '–' ?></dd></div>
        <div class="dl-row"><dt>Geöffnet</dt><dd><?= $inv['viewed_at'] ? fmt_datetime($inv['viewed_at']) : '–' ?></dd></div>
        <?php if ($inv['last_reminder_at']): ?><div class="dl-row"><dt>Letzte Erinnerung</dt><dd><?= fmt_datetime($inv['last_reminder_at']) ?></dd></div><?php endif; ?>
      </dl>
    </section>

    <?php if (!$isCredit && in_array($inv['status'], ['draft', 'sent'], true) && !$inv['paid_total'] && !$inv['adjusted_total']): ?>
    <section class="reveal card p-5 sm:p-6">
      <h2 class="text-sm font-semibold text-slate-900"><i class="bx bx-calendar-check align-middle text-slate-400"></i> Zahlungsplan</h2>
      <?php if ($inv['installments']): ?>
        <ul class="mt-3 divide-y divide-slate-100 text-sm"><?php foreach ($inv['installments'] as $r): ?><li class="flex items-center justify-between gap-2 py-2"><span><?= e($r['label']) ?> · <?= fmt_date($r['due_date']) ?></span><span class="tabular font-medium"><?= money($r['amount'], $inv['currency']) ?></span></li><?php endforeach; ?></ul>
        <form method="post" action="<?= $act('plan') ?>" class="mt-3" data-confirm="Zahlungsplan entfernen? Es gilt wieder das Fälligkeitsdatum der Rechnung." data-confirm-label="Entfernen"><?= csrf_field() ?><input type="hidden" name="remove" value="1"><button class="btn btn-ghost btn-sm w-full"><i class="bx bx-trash"></i>Plan entfernen</button></form>
      <?php else: ?>
        <p class="mt-1 text-xs text-slate-500">Teilen Sie den Rechnungsbetrag in gleich hohe Raten auf. Die Raten erscheinen auf Rechnung und PDF; das Fälligkeitsdatum folgt der nächsten offenen Rate.</p>
        <form method="post" action="<?= $act('plan') ?>" class="mt-3 grid grid-cols-3 gap-2"><?= csrf_field() ?>
          <div><label class="label">Raten</label><input class="input" type="number" name="count" min="2" max="12" value="3"></div>
          <div><label class="label">Alle … Monate</label><input class="input" type="number" name="every" min="1" max="6" value="1"></div>
          <div><label class="label">Erste Rate</label><input class="input" type="date" name="first_due" value="<?= e($inv['due_date']) ?>" required></div>
          <button class="btn btn-secondary col-span-3"><i class="bx bx-plus"></i>Plan erstellen</button></form>
      <?php endif; ?>
    </section>
    <?php elseif ($inv['installments']): ?>
    <section class="reveal card p-5 sm:p-6">
      <h2 class="text-sm font-semibold text-slate-900"><i class="bx bx-calendar-check align-middle text-slate-400"></i> Zahlungsplan</h2>
      <ul class="mt-3 divide-y divide-slate-100 text-sm"><?php foreach ($inv['installments'] as $r): ?><li class="flex items-center justify-between gap-2 py-2"><span><?= e($r['label']) ?> · <?= fmt_date($r['due_date']) ?></span><span class="flex items-center gap-2"><span class="tabular font-medium"><?= money($r['amount'], $inv['currency']) ?></span><span class="badge <?= ['paid' => 'badge-emerald', 'partial' => 'badge-amber', 'overdue' => 'badge-rose'][$r['state']] ?? '' ?>"><?= ['paid' => 'bezahlt', 'partial' => 'teilweise', 'overdue' => 'überfällig', 'open' => 'offen'][$r['state']] ?></span></span></li><?php endforeach; ?></ul>
    </section>
    <?php endif; ?>

    <?php if ($inv['payments']): ?>
    <section class="reveal card p-5 sm:p-6">
      <h2 class="text-sm font-semibold text-slate-900"><i class="bx bx-wallet align-middle text-slate-400"></i> Zahlungseingänge</h2>
      <p class="mt-1 text-xs text-slate-500">Bezahlt <?= money($inv['paid_total'], $inv['currency']) ?> von <?= money($inv['amount_due'], $inv['currency']) ?><?= $inv['status'] === 'sent' ? ' · offen ' . money($inv['open_amount'], $inv['currency']) : '' ?></p>
      <ul class="mt-3 space-y-2 text-sm">
        <?php foreach ($inv['payments'] as $lp): ?>
        <li class="flex items-start justify-between gap-2 rounded-xl bg-slate-50 p-3 ring-1 ring-slate-200/70">
          <div><p class="font-medium text-slate-800 tabular"><?= money($lp['amount'], $inv['currency']) ?> <span class="font-normal text-slate-500">· <?= fmt_date($lp['paid_on']) ?></span></p>
            <p class="text-xs text-slate-500"><?= e(['bank' => 'Überweisung', 'cash' => 'Bar', 'online' => 'Online', 'other' => 'Sonstige', 'refund' => 'Rückerstattung'][$lp['method']] ?? $lp['method']) ?><?= $lp['note'] ? ' · ' . e($lp['note']) : '' ?><?= $lp['created_by_name'] ? ' · ' . e($lp['created_by_name']) : '' ?></p></div>
          <div class="flex shrink-0 gap-1">
            <?php if ((float) $lp['amount'] > 0): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('r/' . $inv['public_id'] . '/receipt/' . $lp['id'])) ?>" data-tip="Quittung"><i class="bx bx-receipt"></i></a><?php endif; ?>
            <?php if (!$lp['provider_payment_id'] && in_array($inv['status'], ['sent', 'paid'], true)): ?>
            <form method="post" action="<?= e(url('admin/invoices/' . $inv['id'] . '/payments/' . $lp['id'] . '/delete')) ?>" data-confirm="Diesen Zahlungseingang entfernen?" data-confirm-label="Entfernen"><?= csrf_field() ?><button class="btn btn-ghost btn-sm text-rose-600" data-tip="Entfernen"><i class="bx bx-trash"></i></button></form>
            <?php endif; ?></div>
        </li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>

    <?php $attType = 'invoice'; $attId = $inv['id']; include APP_DIR . '/views/partials/attachments.php'; ?>
    <?php if (!empty($payments)): ?>
    <section class="reveal card p-5 sm:p-6">
      <h2 class="mb-3 text-sm font-semibold text-slate-900"><i class="bx bx-credit-card align-middle text-slate-400"></i> Online-Zahlungen</h2>
      <ul class="space-y-3 text-sm">
        <?php foreach ($payments as $p): $lbl = ['open' => ['offen', 'badge'], 'paid' => ['bezahlt', 'badge badge-brand'], 'failed' => ['fehlgeschlagen', 'badge'], 'canceled' => ['abgebrochen', 'badge'], 'expired' => ['abgelaufen', 'badge']][$p['status']]; ?>
        <li class="rounded-xl bg-slate-50 p-3 ring-1 ring-slate-200/70">
          <div class="flex items-center justify-between gap-2"><span class="font-medium text-slate-800"><?= e(PaymentService::PROVIDERS[$p['provider']]) ?> · <?= money($p['amount'], $p['currency']) ?></span><span class="<?= $lbl[1] ?>"><?= $lbl[0] ?></span></div>
          <p class="mt-1 break-all font-mono text-[11px] text-slate-400"><?= e($p['provider_ref'] ?? '–') ?></p>
          <p class="mt-1 text-xs text-slate-500"><?= fmt_datetime($p['created_at']) ?><?= $p['method'] ? ' · ' . e($p['method']) : '' ?><?= $p['paid_at'] ? ' · bezahlt ' . fmt_datetime($p['paid_at']) : '' ?></p>
          <?php if ($p['note']): ?><p class="mt-1 text-xs font-medium text-amber-700"><i class="bx bx-error-circle align-middle"></i> <?= e($p['note']) ?></p><?php endif; ?>
          <?php if ($p['status'] === 'open' && $p['provider_ref']): ?>
          <form method="post" action="<?= e(url('admin/invoices/' . $inv['id'] . '/payments/' . $p['id'] . '/sync')) ?>" class="mt-2"><?= csrf_field() ?><button class="btn btn-ghost btn-sm"><i class="bx bx-refresh"></i>Status abfragen</button></form>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>

    <section class="reveal card p-5 sm:p-6">
      <h2 class="mb-4 text-sm font-semibold text-slate-900">Verlauf</h2>
      <?php $showInternal = true; $areaType = $inv['area_type']; include APP_DIR . '/views/partials/timeline.php'; ?>
    </section>
  </aside>
</div>
