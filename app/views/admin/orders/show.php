<?php
$title = 'Auftrag ' . $order['number'];
$titleHtml = e($order['number']) . ' <span class="ml-2 align-middle">' . Status::badge('order', $order['status'], $order['area_type']) . '</span>';
$subtitle = e($order['title']) . ' · <a class="link" href="' . e(url('admin/customers/' . $order['customer_id'])) . '">' . e(customer_name($order)) . '</a>';
$back = ['admin/orders', 'Aufträge'];
$actions = '<a href="' . e(url('admin/offers/' . $order['offer_id'])) . '" class="btn btn-secondary"><i class="bx bx-file"></i>Offerte ' . e($order['offer_number']) . '</a>';
include APP_DIR . '/views/partials/page-header.php';
$physical = $order['area_type'] === 'physical';
?>
<div class="reveal card mb-6 p-5 sm:p-6"><?php include APP_DIR . '/views/partials/order-progress.php'; ?></div>

<div class="grid gap-6 xl:grid-cols-3">
  <div class="space-y-6 xl:col-span-2">
    <!-- Status ändern -->
    <section class="reveal card">
      <div class="card-header"><h2 class="card-title"><i class="bx bx-transfer"></i>Status &amp; Notiz</h2></div>
      <form method="post" action="<?= e(url('admin/orders/' . $order['id'] . '/status')) ?>" class="space-y-4 p-5 sm:p-6"><?= csrf_field() ?>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
          <?php foreach (Status::ORDER as $k => [$label, $color, $icon]): ?>
            <label class="group flex cursor-pointer flex-col items-center gap-1.5 rounded-xl p-3 text-center ring-1 ring-slate-200 transition hover:ring-slate-300 has-[:checked]:bg-brand-50/60 has-[:checked]:ring-2 has-[:checked]:ring-brand-500">
              <input type="radio" class="sr-only" name="status" value="<?= $k ?>" <?= $order['status'] === $k ? 'checked' : '' ?>>
              <i class="bx <?= $k === 'fulfilled' && !$physical ? 'bx-cloud-download' : $icon ?> text-2xl text-slate-400 transition group-has-[:checked]:text-brand-600"></i>
              <span class="text-xs font-medium text-slate-700"><?= e(Status::label('order', $k, $order['area_type'])) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <div>
          <label class="label" for="note">Notiz <span class="font-normal text-slate-400">(optional)</span></label>
          <textarea class="input" id="note" name="note" rows="2" placeholder="z. B. Material bestellt, Termin vereinbart …"></textarea>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm text-slate-700">
            <label class="flex cursor-pointer items-center gap-2"><input type="checkbox" class="checkbox" name="customer_visible" value="1" checked data-collapse-toggle="#notify-wrap">Für Kunden sichtbar</label>
            <span class="fold is-open" id="notify-wrap"><label class="flex cursor-pointer items-center gap-2"><input type="checkbox" class="checkbox" name="notify" value="1" checked>Kunde per E-Mail informieren</label></span>
          </div>
          <button class="btn btn-primary"><i class="bx bx-check"></i>Speichern</button>
        </div>
      </form>
    </section>

    <!-- Versand / digitale Lieferung -->
    <section class="reveal card">
      <div class="card-header">
        <h2 class="card-title"><i class="bx <?= $physical ? 'bx-package' : 'bx-cloud-upload' ?>"></i><?= $physical ? 'Versand' : 'Digitale Lieferung' ?></h2>
        <?= $physical ? Status::badge('shipping', $order['shipping_status'] ?: 'pending') : '' ?>
      </div>
      <form method="post" action="<?= e(url('admin/orders/' . $order['id'] . '/fulfillment')) ?>" class="space-y-5 p-5 sm:p-6"><?= csrf_field() ?>
        <?php if ($physical): ?>
          <div>
            <span class="label">Versandstatus</span>
            <div class="flex flex-wrap gap-2">
              <?php foreach (Status::SHIPPING as $k => [$label, , $icon]): ?>
                <label class="flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 ring-1 ring-slate-200 transition hover:ring-slate-300 has-[:checked]:bg-brand-600 has-[:checked]:text-white has-[:checked]:ring-brand-600">
                  <input type="radio" class="sr-only" name="shipping_status" value="<?= $k ?>" <?= ($order['shipping_status'] ?: 'pending') === $k ? 'checked' : '' ?>><i class="bx <?= $icon ?> text-base"></i><?= e($label) ?>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label" for="shipping_method">Versandart</label>
              <input class="input" id="shipping_method" name="shipping_method" list="ship-methods" value="<?= e($order['shipping_method']) ?>">
              <datalist id="ship-methods"><?php foreach ($shippingMethods as $m): ?><option value="<?= e($m['name']) ?>"><?php endforeach; ?></datalist></div>
            <div><label class="label" for="shipping_cost">Versandkosten (netto, Info)</label><input class="input tabular" id="shipping_cost" name="shipping_cost" inputmode="decimal" value="<?= e(number_format((float) $order['shipping_cost'], 2, '.', '')) ?>"></div>
            <div><label class="label" for="carrier">Transporteur</label><input class="input" id="carrier" name="carrier" value="<?= e($order['carrier']) ?>" list="carriers" placeholder="z. B. Die Post, DHL">
              <datalist id="carriers"><?php foreach (['Die Post', 'DHL', 'DPD', 'GLS', 'UPS', 'FedEx', 'Planzer', 'Österreichische Post', 'Hermes'] as $cr): ?><option value="<?= $cr ?>"><?php endforeach; ?></datalist></div>
            <div><label class="label" for="tracking_number">Sendungsnummer</label><input class="input font-mono" id="tracking_number" name="tracking_number" value="<?= e($order['tracking_number']) ?>"></div>
            <div class="sm:col-span-2"><label class="label" for="tracking_url">Tracking-Link</label><input class="input" id="tracking_url" name="tracking_url" type="url" value="<?= e($order['tracking_url']) ?>" placeholder="https://"></div>
          </div>
          <fieldset class="rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70">
            <legend class="px-1 text-xs font-semibold uppercase tracking-wider text-slate-500">Lieferadresse</legend>
            <div class="grid gap-3 sm:grid-cols-6">
              <input class="input sm:col-span-3" name="ship_company" value="<?= e($order['ship_company']) ?>" placeholder="Firma">
              <input class="input sm:col-span-3" name="ship_name" value="<?= e($order['ship_name']) ?>" placeholder="Name">
              <input class="input sm:col-span-3" name="ship_street" value="<?= e($order['ship_street']) ?>" placeholder="Strasse und Nr.">
              <input class="input sm:col-span-3" name="ship_extra" value="<?= e($order['ship_extra']) ?>" placeholder="Adresszusatz">
              <input class="input sm:col-span-2" name="ship_zip" value="<?= e($order['ship_zip']) ?>" placeholder="PLZ">
              <input class="input sm:col-span-2" name="ship_city" value="<?= e($order['ship_city']) ?>" placeholder="Ort">
              <select class="input sm:col-span-2" name="ship_country"><?php foreach (countries() as $code => $name): ?><option value="<?= $code ?>"<?= ($order['ship_country'] ?: 'CH') === $code ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select>
            </div>
          </fieldset>
        <?php else: ?>
          <?php if ($order['ship_name'] || $order['ship_street']): ?>
            <div class="rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70"><p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Abweichende Adresse</p>
              <p class="mt-1.5 text-sm leading-relaxed text-slate-700"><?= implode('<br>', array_map('e', address_lines(['company' => $order['ship_company'], 'name' => $order['ship_name'], 'street' => $order['ship_street'], 'address_extra' => $order['ship_extra'], 'zip' => $order['ship_zip'], 'city' => $order['ship_city'], 'country' => $order['ship_country']]))) ?></p></div>
          <?php endif; ?>
          <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label" for="delivery_email">Liefer-E-Mail</label><input class="input" type="email" id="delivery_email" name="delivery_email" value="<?= e($order['delivery_email']) ?>"></div>
            <div><label class="label" for="download_url">Download- / Zugangslink</label><input class="input" type="url" id="download_url" name="download_url" value="<?= e($order['download_url']) ?>" placeholder="https://"></div>
            <div class="sm:col-span-2"><label class="label" for="access_info">Zugangsdaten, Lizenzschlüssel, Hinweise</label>
              <textarea class="input font-mono text-[13px]" id="access_info" name="access_info" rows="4" placeholder="Wird dem Kunden im Portal angezeigt"><?= e($order['access_info']) ?></textarea>
              <p class="hint"><i class="bx bx-lock-alt align-middle"></i> Nur für den angemeldeten Kunden im Portal sichtbar – nicht in E-Mails.</p></div>
          </div>
          <?php if (in_array($order['status'], ['new', 'in_progress'], true)): ?>
            <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700"><input type="checkbox" class="checkbox" name="mark_delivered" value="1">Als <strong>bereitgestellt</strong> markieren</label>
          <?php endif; ?>
        <?php endif; ?>
        <div class="grid gap-4 sm:grid-cols-2">
          <div><label class="label" for="customer_message">Nachricht an Kunden <span class="font-normal text-slate-400">(im Portal)</span></label><textarea class="input" id="customer_message" name="customer_message" rows="3"><?= e($order['customer_message']) ?></textarea></div>
          <div><label class="label" for="internal_note">Interne Notiz</label><textarea class="input" id="internal_note" name="internal_note" rows="3"><?= e($order['internal_note']) ?></textarea></div>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700"><input type="checkbox" class="checkbox" name="notify" value="1" checked>Kunde bei Statusänderung informieren</label>
          <button class="btn btn-primary"><i class="bx bx-save"></i>Speichern</button>
        </div>
      </form>
    </section>

    <section class="reveal card">
      <div class="card-header"><h2 class="card-title"><i class="bx bx-list-ul"></i>Positionen</h2><a href="<?= e(url('o/' . $order['offer_public_id'])) ?>" target="_blank" rel="noopener" class="btn btn-ghost btn-sm"><i class="bx bx-link-external"></i>Dokument</a></div>
      <div class="overflow-x-auto"><table class="table">
        <thead><tr><th>Pos.</th><th>Bezeichnung</th><th class="text-right">Menge</th><th class="text-right">Total</th></tr></thead>
        <tbody>
          <?php foreach ($offer['items'] as $it): ?>
            <tr><td class="text-slate-400"><?= (int) $it['position'] ?></td><td><p class="font-medium text-slate-900"><?= e($it['title']) ?></p><?php if ($it['description']): ?><p class="text-xs text-slate-500"><?= nl($it['description']) ?></p><?php endif; ?></td>
              <td class="whitespace-nowrap text-right tabular"><?= qty($it['quantity']) ?> <?= e($it['unit']) ?></td><td class="whitespace-nowrap text-right font-medium tabular"><?= money($it['line_total'], $order['currency']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    </section>
  </div>

  <aside class="space-y-6">
    <section class="reveal card p-5 sm:p-6">
      <h2 class="text-sm font-semibold text-slate-900"><i class="bx bx-user-check align-middle text-slate-400"></i> Zuständigkeit</h2>
      <dl class="mt-3 divide-y divide-slate-100">
        <div class="dl-row"><dt>Verantwortlich</dt><dd><?= person_chip($order['responsible_label']) ?><?= $order['responsible_label'] && !$order['responsible_id'] ? ' <span class="text-xs text-slate-400">(Konto gelöscht)</span>' : '' ?></dd></div>
        <div class="dl-row"><dt>Offerte von</dt><dd><?= person_chip($order['issuer_label'], '–') ?></dd></div>
      </dl>
      <?php $me = Auth::user(); ?>
      <form method="post" action="<?= e(url('admin/orders/' . $order['id'] . '/responsible')) ?>" class="mt-4 flex flex-col gap-2"><?= csrf_field() ?>
        <select class="input" name="user_id" aria-label="Verantwortliche Person">
          <option value="0"<?= !$order['responsible_id'] ? ' selected' : '' ?>>Nicht zugewiesen</option>
          <?php foreach ($staff as $s): ?><option value="<?= (int) $s['id'] ?>"<?= (int) $order['responsible_id'] === (int) $s['id'] ? ' selected' : '' ?>><?= e($s['name']) ?><?= (int) $s['id'] === (int) $me['id'] ? ' (ich)' : '' ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-secondary w-full"><i class="bx bx-save"></i>Zuweisen</button>
      </form>
      <?php if ((int) $order['responsible_id'] !== (int) $me['id']): ?>
      <form method="post" action="<?= e(url('admin/orders/' . $order['id'] . '/responsible')) ?>" class="mt-2"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $me['id'] ?>"><button class="btn btn-ghost btn-sm w-full"><i class="bx bx-hand"></i>Auftrag übernehmen</button></form>
      <?php endif; ?>
      <p class="mt-3 text-xs text-slate-500">Die zugewiesene Person wird per E-Mail informiert und erhält Meldungen zu Annahme und Zahlung. Änderungen erscheinen im Verlauf.</p>
    </section>
    <section class="reveal reveal-delay-1 card p-5 sm:p-6">
      <h2 class="text-sm font-semibold text-slate-900">Übersicht</h2>
      <dl class="mt-3 divide-y divide-slate-100">
        <div class="dl-row"><dt>Eingang</dt><dd><?= fmt_datetime($order['created_at']) ?></dd></div>
        <div class="dl-row"><dt>Bereich</dt><dd><i class="bx <?= e($order['area_icon'] ?: 'bx-category') ?> align-middle text-slate-400"></i> <?= e($order['area_name'] ?: '–') ?></dd></div>
        <?php if ($order['shipped_at']): ?><div class="dl-row"><dt>Versendet</dt><dd><?= fmt_datetime($order['shipped_at']) ?></dd></div><?php endif; ?>
        <?php if ($order['delivered_at']): ?><div class="dl-row"><dt><?= $physical ? 'Zugestellt' : 'Bereitgestellt' ?></dt><dd><?= fmt_datetime($order['delivered_at']) ?></dd></div><?php endif; ?>
        <?php if ($order['completed_at']): ?><div class="dl-row"><dt>Abgeschlossen</dt><dd><?= fmt_datetime($order['completed_at']) ?></dd></div><?php endif; ?>
        <div class="dl-row"><dt>Netto</dt><dd class="tabular"><?= money($order['net_total'], $order['currency']) ?></dd></div>
        <div class="dl-row"><dt>Steuern</dt><dd class="tabular"><?= money($order['tax_total'], $order['currency']) ?></dd></div>
        <div class="dl-row"><dt class="!text-slate-900 font-semibold">Total</dt><dd class="text-base tabular"><?= money($order['total'], $order['currency']) ?></dd></div>
      </dl>
    </section>
    <?php $ov = InvoiceService::orderOverview($order); ?>
    <section class="reveal reveal-delay-2 card p-5 sm:p-6">
      <h2 class="text-sm font-semibold text-slate-900">Rechnungen</h2>
      <?php foreach ($ov['list'] as $iv): ?>
        <a href="<?= e(url('admin/invoices/' . $iv['id'])) ?>" class="mt-3 flex items-center justify-between gap-3 rounded-xl bg-slate-50 p-3 ring-1 ring-slate-200 transition hover:bg-white">
          <span><span class="block text-sm font-medium text-slate-900"><?= e($iv['number']) ?><?= $iv['kind'] === 'advance' ? ' · Anzahlung' : '' ?></span><span class="block text-xs text-slate-500"><?= money($iv['total'], $iv['currency']) ?> · fällig <?= fmt_date($iv['due_date']) ?></span></span>
          <?= Status::badge('invoice', Status::invoiceDisplay($iv)) ?></a>
      <?php endforeach; ?>
      <?php if ($order['status'] === 'cancelled'): ?><p class="mt-2 text-sm text-slate-500">Der Auftrag ist storniert.</p>
      <?php elseif (!$ov['final'] && License::has('invoices')): ?>
        <form method="post" action="<?= e(url('admin/orders/' . $order['id'] . '/invoice')) ?>" class="mt-4 space-y-3 border-t border-slate-100 pt-4" x-data><?= csrf_field() ?>
          <div class="grid grid-cols-2 gap-3">
            <div class="col-span-2"><label class="label">Art</label>
              <select class="input" name="kind" data-invoice-kind>
                <option value="final"><?= $ov['advances'] ? 'Schlussrechnung (verrechnet Anzahlungen)' : 'Gesamtrechnung' ?></option>
                <?php if ($ov['remaining'] > 0): ?><option value="advance">Anzahlungsrechnung</option><?php endif; ?>
              </select></div>
            <div data-advance-field class="hidden"><label class="label">Berechnung</label><select class="input" name="mode"><option value="percent">in %</option><option value="amount">Betrag inkl. Steuer</option></select></div>
            <div data-advance-field class="hidden"><label class="label">Wert</label><input class="input" name="value" inputmode="decimal" placeholder="z. B. 30"><p class="hint">Offen: <?= money($ov['remaining'], $order['currency']) ?></p></div>
            <div><label class="label">Rechnungsdatum</label><input class="input" type="date" name="issue_date" value="<?= date('Y-m-d') ?>"></div>
            <div><label class="label">Frist (Tage)</label><input class="input" type="number" min="0" max="365" name="payment_days" value="<?= e(Settings::get('invoice_payment_days', 30)) ?>"></div>
          </div>
          <button class="btn btn-primary w-full"><i class="bx bx-receipt"></i>Entwurf erstellen</button>
        </form>
        <script>document.querySelectorAll('[data-invoice-kind]').forEach(function (s) { var f = function () { s.closest('form').querySelectorAll('[data-advance-field]').forEach(function (e) { e.classList.toggle('hidden', s.value !== 'advance'); }); }; s.addEventListener('change', f); f(); });</script>
      <?php endif; ?>
    </section>
    <?php $subRow = DB::one("SELECT * FROM subscriptions WHERE order_id = ? AND status <> 'ended' ORDER BY id DESC LIMIT 1", [$order['id']]); ?>
    <?php if ($order['status'] !== 'cancelled' && License::has('subscriptions')): ?>
    <section class="reveal reveal-delay-2 card p-5 sm:p-6">
      <h2 class="text-sm font-semibold text-slate-900">Abo / wiederkehrend</h2>
      <?php if ($subRow): ?>
        <a href="<?= e(url('admin/subscriptions')) ?>" class="mt-3 flex items-center justify-between gap-3 rounded-xl bg-slate-50 p-3 text-sm ring-1 ring-slate-200 transition hover:bg-white">
          <span><span class="block font-medium text-slate-900"><?= e(SubscriptionService::PERIODS[$subRow['period']][0]) ?></span><span class="block text-xs text-slate-500">nächste Rechnung <?= fmt_date($subRow['next_run']) ?></span></span>
          <span class="badge <?= $subRow['status'] === 'active' ? 'badge-emerald' : 'badge-amber' ?>"><?= $subRow['status'] === 'active' ? 'aktiv' : 'pausiert' ?></span></a>
      <?php else: ?>
        <details class="mt-2"><summary class="cursor-pointer text-sm font-medium text-brand-700 hover:text-brand-800">Abo einrichten …</summary>
          <form method="post" action="<?= e(url('admin/orders/' . $order['id'] . '/subscription')) ?>" class="mt-3 space-y-3"><?= csrf_field() ?>
            <p class="text-xs text-slate-500">Erzeugt in festen Abständen eine Rechnung über <?= money($order['total'], $order['currency']) ?> (Positionen der Offerte).</p>
            <div><label class="label">Bezeichnung</label><input class="input" name="title" value="<?= e($order['title']) ?>" maxlength="190"></div>
            <div class="grid grid-cols-2 gap-3">
              <div><label class="label">Intervall</label><select class="input" name="period"><?php foreach (SubscriptionService::PERIODS as $k => [$l]): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select></div>
              <div><label class="label">Frist (Tage)</label><input class="input" type="number" name="payment_days" min="0" max="365" value="<?= e(Settings::get('invoice_payment_days', 30)) ?>"></div>
              <div><label class="label">Erste Rechnung</label><input class="input" type="date" name="start" value="<?= date('Y-m-d', strtotime('+1 month')) ?>" min="<?= date('Y-m-d') ?>" required></div>
              <div><label class="label">Ende <span class="font-normal text-slate-400">(optional)</span></label><input class="input" type="date" name="end"></div>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" class="checkbox" name="auto_send" value="1">Rechnungen automatisch per E-Mail senden</label>
            <button class="btn btn-secondary w-full"><i class="bx bx-sync"></i>Abo starten</button>
          </form></details>
      <?php endif; ?>
    </section>
    <?php endif; ?>
    <section class="reveal reveal-delay-2 card p-5 sm:p-6">
      <h2 class="text-sm font-semibold text-slate-900">Kunde</h2>
      <p class="mt-2 text-sm font-medium text-slate-900"><?= e(customer_name($order)) ?></p>
      <p class="text-sm text-slate-500"><?= e($order['customer_no']) ?></p>
      <a class="link mt-2 inline-block text-sm" href="mailto:<?= e($order['customer_email']) ?>?subject=<?= rawurlencode('Auftrag ' . $order['number']) ?>"><?= e($order['customer_email']) ?></a>
    </section>
    <?php $attType = 'order'; $attId = $order['id']; include APP_DIR . '/views/partials/attachments.php'; ?>
    <section class="reveal reveal-delay-3 card p-5 sm:p-6">
      <h2 class="mb-4 text-sm font-semibold text-slate-900">Statushistorie</h2>
      <?php $showInternal = true; $areaType = $order['area_type']; include APP_DIR . '/views/partials/timeline.php'; ?>
    </section>
    <form method="post" action="<?= e(url('admin/orders/' . $order['id'] . '/delete')) ?>" data-confirm="Auftrag <?= e($order['number']) ?> endgültig löschen? Die Offerte bleibt erhalten und kann danach auch gelöscht werden. Nicht möglich, sobald Rechnungen existieren." data-confirm-label="Endgültig löschen"><?= csrf_field() ?>
      <button class="btn btn-ghost btn-sm w-full text-rose-600 hover:bg-rose-50 hover:text-rose-700"><i class="bx bx-trash"></i>Auftrag löschen</button>
    </form>
  </aside>
</div>
