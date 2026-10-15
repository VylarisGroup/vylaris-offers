<?php $title = 'Auftrag ' . $order['number']; ?>
<div class="reveal pt-4">
  <a href="<?= e(url('portal?tab=orders')) ?>" class="group inline-flex items-center gap-1 text-sm font-medium text-slate-500 transition hover:text-slate-800"><i class="bx bx-chevron-left text-lg transition-transform group-hover:-translate-x-0.5"></i>Alle Aufträge</a>
  <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
      <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl"><?= e($order['title']) ?></h1>
      <p class="mt-1 text-sm text-slate-500">Auftrag <?= e($order['number']) ?> · erstellt am <?= fmt_date($order['created_at']) ?> · <?= ($contact = Responsibility::contactName('order', $order)) !== '' ? ' · Ansprechperson: ' . e($contact) : '' ?> · aus Offerte <a class="link" href="<?= e(url('o/' . $order['offer_public_id'])) ?>"><?= e($order['offer_number']) ?></a></p>
    </div>
    <?= Status::badge('order', $order['status'], $order['area_type']) ?>
  </div>
</div>

<div class="reveal reveal-delay-1 card mt-6 p-5 sm:p-6"><?php include APP_DIR . '/views/partials/order-progress.php'; ?></div>

<div class="mt-6 grid gap-6 lg:grid-cols-3">
  <div class="space-y-6 lg:col-span-2">
    <?php if ($order['customer_message']): ?>
      <div class="reveal flex gap-3 rounded-2xl bg-brand-50 p-5 text-sm leading-relaxed text-brand-900 ring-1 ring-brand-100"><i class="bx bx-message-rounded-detail text-xl text-brand-600"></i><p class="whitespace-pre-line"><?= e($order['customer_message']) ?></p></div>
    <?php endif; ?>

    <?php if ($order['area_type'] === 'physical'): ?>
    <section class="reveal card">
      <div class="card-header"><h2 class="card-title"><i class="bx bx-package"></i>Versand</h2><?= $order['shipping_status'] ? Status::badge('shipping', $order['shipping_status']) : '' ?></div>
      <div class="grid gap-6 p-5 sm:grid-cols-2 sm:p-6">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Lieferadresse</p>
          <p class="mt-1.5 text-sm leading-relaxed text-slate-800"><?= implode('<br>', array_map('e', address_lines(['company' => $order['ship_company'], 'name' => $order['ship_name'], 'street' => $order['ship_street'], 'address_extra' => $order['ship_extra'], 'zip' => $order['ship_zip'], 'city' => $order['ship_city'], 'country' => $order['ship_country']]))) ?></p>
        </div>
        <div>
          <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Versandart</p>
          <p class="mt-1.5 text-sm text-slate-800"><?= e($order['shipping_method'] ?: '–') ?><?= $order['carrier'] ? ' · ' . e($order['carrier']) : '' ?></p>
          <?php if ($order['tracking_number']): ?>
            <p class="mt-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Sendungsnummer</p>
            <p class="mt-1 flex items-center gap-2 font-mono text-sm text-slate-900"><?= e($order['tracking_number']) ?>
              <button type="button" class="btn btn-ghost btn-icon size-7" data-copy="<?= e($order['tracking_number']) ?>" data-tip="Kopieren"><i class="bx bx-copy"></i></button></p>
            <?php if ($order['tracking_url']): ?><a href="<?= e($order['tracking_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-sm mt-3"><i class="bx bx-map-pin"></i>Sendung verfolgen</a><?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </section>
    <?php else: ?>
    <section class="reveal card">
      <div class="card-header"><h2 class="card-title"><i class="bx bx-cloud-download"></i>Digitale Lieferung</h2></div>
      <div class="space-y-4 p-5 sm:p-6">
        <?php if (!$order['download_url'] && !$order['access_info']): ?>
          <p class="flex items-center gap-2 text-sm text-slate-500"><i class="bx bx-time-five text-lg"></i>Ihre Lieferung wird vorbereitet. Sie werden per E-Mail informiert, sobald sie bereitsteht.</p>
        <?php endif; ?>
        <?php if ($order['download_url']): ?>
          <a href="<?= e($order['download_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary"><i class="bx bx-download"></i>Download / Zugang öffnen</a>
        <?php endif; ?>
        <?php if ($order['access_info']): ?>
          <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Zugangsdaten &amp; Hinweise</p>
            <pre class="mt-2 whitespace-pre-wrap break-words rounded-xl bg-slate-50 p-4 font-mono text-sm text-slate-800 ring-1 ring-slate-200"><?= e($order['access_info']) ?></pre>
          </div>
        <?php endif; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php $filesBase = url('portal/orders/' . $order['id'] . '/files'); include APP_DIR . '/views/partials/attachments-public.php'; ?>
    <section class="reveal card">
      <div class="card-header"><h2 class="card-title"><i class="bx bx-history"></i>Verlauf</h2></div>
      <div class="p-5 sm:p-6"><?php $areaType = $order['area_type']; include APP_DIR . '/views/partials/timeline.php'; ?></div>
    </section>
  </div>

  <aside class="reveal reveal-delay-2 space-y-6">
    <div class="card p-5 sm:p-6">
      <h2 class="text-sm font-semibold text-slate-900">Zusammenfassung</h2>
      <dl class="mt-3 divide-y divide-slate-100">
        <div class="dl-row"><dt>Auftrag</dt><dd><?= e($order['number']) ?></dd></div>
        <div class="dl-row"><dt>Bereich</dt><dd><?= e($order['area_name'] ?: ($order['area_type'] === 'digital' ? 'Digital' : 'Physisch')) ?></dd></div>
        <div class="dl-row"><dt>Netto</dt><dd class="tabular"><?= money($order['net_total'], $order['currency']) ?></dd></div>
        <div class="dl-row"><dt>Steuern</dt><dd class="tabular"><?= money($order['tax_total'], $order['currency']) ?></dd></div>
        <div class="dl-row"><dt class="!text-slate-900 font-semibold">Total</dt><dd class="text-base tabular"><?= money($order['total'], $order['currency']) ?></dd></div>
      </dl>
      <a href="<?= e(url('o/' . $order['offer_public_id'])) ?>" class="btn btn-secondary btn-sm mt-4 w-full"><i class="bx bx-file"></i>Offerte ansehen</a>
    </div>
    <?php if (Settings::companyEmail()): ?>
    <div class="card p-5 text-sm sm:p-6">
      <p class="font-semibold text-slate-900">Fragen zum Auftrag?</p>
      <p class="mt-1 text-slate-500">Wir helfen gerne weiter.</p>
      <a href="mailto:<?= e(Settings::companyEmail()) ?>?subject=<?= rawurlencode('Auftrag ' . $order['number']) ?>" class="btn btn-ghost btn-sm mt-3 -ml-3"><i class="bx bx-envelope"></i>Nachricht senden</a>
    </div>
    <?php endif; ?>
  </aside>
</div>
