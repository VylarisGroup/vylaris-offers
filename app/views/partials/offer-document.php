<?php
/**
 * Darstellung einer Offerte als Dokument (öffentliche Seite, Admin, Druck).
 * @var array $offer
 */
$isInvoice = !empty($isInvoice);
$isCreditDoc = $isInvoice && ($offer['kind'] ?? '') === 'credit';
$docLabel = $isInvoice ? ($isCreditDoc ? 'Gutschrift' : (($offer['kind'] ?? 'final') === 'advance' ? 'Anzahlungsrechnung' : 'Rechnung')) : 'Offerte';
$taxNotes = TaxService::notes($offer['taxes'] ?? []);
$c = $offer['customer'];
$cur = $offer['currency'];
$logo = Brand::logoUrl();
$hasDiscounts = (bool) array_filter($offer['items'], static fn($i) => (float) $i['line_discount'] > 0);
$liable = TaxService::liable();
$showTaxCol = $liable && count(array_unique(array_column($offer['items'], 'tax_label'))) > 1;
$companyLines = array_filter([
    Settings::get('company_street'),
    trim(Settings::get('company_zip') . ' ' . Settings::get('company_city')),
]);
?>
<article class="card overflow-hidden print:rounded-none">
  <!-- Kopf -->
  <div class="relative border-b border-slate-100 px-5 py-6 sm:px-10 sm:py-8">
    <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-brand-500 via-brand-600 to-brand-700"></div>
    <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <?php if ($logo): ?>
          <img src="<?= e($logo) ?>" alt="<?= e(Settings::get('company_name')) ?>" class="mb-3 h-10 w-auto max-w-[200px] object-contain">
        <?php endif; ?>
        <p class="font-semibold text-slate-900"><?= e(Settings::get('company_name')) ?></p>
        <p class="mt-0.5 text-sm leading-relaxed text-slate-500">
          <?= implode('<br>', array_map('e', $companyLines)) ?>
          <?php if (Settings::companyEmail()): ?><br><?= e(Settings::companyEmail()) ?><?php endif; ?>
          <?php if (Settings::get('company_phone')): ?><br><?= e(Settings::get('company_phone')) ?><?php endif; ?>
          <?php if (($uidLine = PdfService::uidLine()) !== ''): ?><br><?= e($uidLine) ?><?php endif; ?>
        </p>
      </div>
      <div class="sm:text-right">
        <p class="text-xs font-semibold uppercase tracking-[.18em] text-brand-600"><?= $docLabel ?></p>
        <p class="mt-1 text-2xl font-bold tracking-tight text-slate-900 tabular"><?= e($offer['number']) ?></p>
        <dl class="mt-3 grid grid-cols-[auto_auto] gap-x-4 gap-y-1 text-sm sm:justify-end">
          <dt class="text-slate-500">Datum</dt><dd class="font-medium text-slate-800 tabular"><?= fmt_date($isInvoice ? $offer['issue_date'] : $offer['created_at']) ?></dd>
          <?php if ($isInvoice): ?>
            <?php if ($isCreditDoc): ?><dt class="text-slate-500">Zu Rechnung</dt><dd class="font-medium text-slate-800"><?= e((string) ($offer['credit_of_number'] ?? '')) ?></dd>
            <?php else: ?><dt class="text-slate-500">Fällig am</dt><dd class="font-medium text-slate-800 tabular"><?= fmt_date($offer['due_date']) ?></dd><?php endif; ?>
            <?php if (!empty($offer['service_period'])): ?><dt class="text-slate-500">Leistungszeitraum</dt><dd class="font-medium text-slate-800"><?= e($offer['service_period']) ?></dd><?php endif; ?>
          <?php else: ?>
            <dt class="text-slate-500">Gültig bis</dt><dd class="font-medium text-slate-800 tabular"><?= $offer['valid_until'] ? fmt_date($offer['valid_until']) : 'unbefristet' ?></dd>
          <?php endif; ?>
          <?php if (!empty($c['customer_no'])): ?><dt class="text-slate-500">Kunden-Nr.</dt><dd class="font-medium text-slate-800"><?= e($c['customer_no']) ?></dd><?php endif; ?>
          <?php if (!$isInvoice && ($contact = Responsibility::contactName('offer', $offer)) !== ''): ?><dt class="text-slate-500">Ansprechperson</dt><dd class="font-medium text-slate-800"><?= e($contact) ?></dd><?php endif; ?>
        </dl>
      </div>
    </div>
  </div>

  <!-- Adressen -->
  <div class="grid gap-6 px-5 py-6 sm:grid-cols-2 sm:px-10">
    <div>
      <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400"><?= $isInvoice ? ($isCreditDoc ? 'Gutschrift an' : 'Rechnung an') : 'Offerte für' ?></p>
      <p class="text-sm leading-relaxed text-slate-800"><?= implode('<br>', array_map('e', address_lines($c))) ?></p>
      <?php if (!empty($c['vat_id'])): ?><p class="mt-1 text-xs text-slate-500">UID: <?= e($c['vat_id']) ?></p><?php endif; ?>
    </div>
    <div>
      <?php if ($offer['area_type'] === 'physical' || $offer['delivery']): ?>
        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Lieferung</p>
        <p class="text-sm leading-relaxed text-slate-800">
          <?php if ($offer['delivery']): ?>
            <?= implode('<br>', array_map('e', address_lines($offer['delivery']))) ?>
          <?php else: ?>
            <span class="text-slate-500">an Rechnungsadresse</span>
          <?php endif; ?>
        </p>
        <?php if ($offer['shipping_method']): ?><p class="mt-2 inline-flex items-center gap-1.5 text-sm text-slate-600"><i class="bx bx-car text-lg text-slate-400"></i><?= e($offer['shipping_method']) ?></p><?php endif; ?>
      <?php else: ?>
        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Bereitstellung</p>
        <p class="inline-flex items-center gap-1.5 text-sm text-slate-800"><i class="bx bx-cloud-download text-lg text-slate-400"></i>Digital – keine Versandkosten</p>
      <?php endif; ?>
      <?php if ($offer['delivery_info']): ?><p class="mt-1.5 text-sm text-slate-600"><?= e($offer['delivery_info']) ?></p><?php endif; ?>
    </div>
  </div>

  <!-- Titel & Einleitung -->
  <div class="px-5 pb-6 sm:px-10">
    <h2 class="text-xl font-bold tracking-tight text-slate-900"><?= e($offer['title']) ?></h2>
    <?php if ($offer['intro']): ?><p class="mt-2 max-w-3xl text-[15px] leading-relaxed text-slate-600"><?= nl($offer['intro']) ?></p><?php endif; ?>
  </div>

  <!-- Positionen: Tabelle (ab sm) -->
  <div class="hidden overflow-x-auto sm:block">
    <table class="table">
      <thead>
        <tr>
          <th class="w-12">Pos.</th>
          <th>Bezeichnung</th>
          <th class="text-right">Menge</th>
          <th class="text-right">Preis</th>
          <?php if ($hasDiscounts): ?><th class="text-right">Rabatt</th><?php endif; ?>
          <?php if ($showTaxCol): ?><th class="text-right">Steuer</th><?php endif; ?>
          <th class="text-right">Total <?= e($cur) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($offer['items'] as $it): ?>
        <tr>
          <td class="align-top text-slate-400 tabular"><?= (int) $it['position'] ?></td>
          <td class="align-top">
            <div class="flex items-start gap-3.5">
              <?php if ($imgUrl = ItemImage::url($it['image'] ?? null)): ?>
                <a href="<?= e($imgUrl) ?>" target="_blank" rel="noopener" class="block size-20 shrink-0 overflow-hidden rounded-xl bg-slate-50 shadow-sm ring-1 ring-slate-200 transition hover:shadow-md" aria-label="Bild vergrössern"><img src="<?= e($imgUrl) ?>" alt="<?= e($it['title']) ?>" loading="lazy" class="size-full object-cover"></a>
              <?php endif; ?>
              <div class="min-w-0">
                <p class="font-medium text-slate-900"><?= e($it['title']) ?></p>
                <?php if ($it['description']): ?><p class="mt-0.5 max-w-xl text-[13px] leading-relaxed text-slate-500"><?= nl($it['description']) ?></p><?php endif; ?>
              </div>
            </div>
          </td>
          <td class="whitespace-nowrap text-right align-top tabular"><?= qty($it['quantity']) ?> <?= e($it['unit']) ?></td>
          <td class="whitespace-nowrap text-right align-top tabular"><?= money($it['unit_price'], $cur, false) ?></td>
          <?php if ($hasDiscounts): ?>
            <td class="whitespace-nowrap text-right align-top tabular text-emerald-700"><?php if ((float) $it['line_discount'] > 0): ?>−<?= money($it['line_discount'], $cur, false) ?><?php if ($it['discount_type'] === 'percent'): ?><span class="block text-xs text-slate-400"><?= rate($it['discount_value']) ?></span><?php endif; ?><?php endif; ?></td>
          <?php endif; ?>
          <?php if ($showTaxCol): ?><td class="whitespace-nowrap text-right align-top text-xs text-slate-500"><?= e($it['tax_label']) ?></td><?php endif; ?>
          <td class="whitespace-nowrap text-right align-top font-semibold text-slate-900 tabular"><?= money($it['line_total'], $cur, false) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Positionen: Karten (mobil) -->
  <ul class="divide-y divide-slate-100 border-y border-slate-100 sm:hidden">
    <?php foreach ($offer['items'] as $it): ?>
      <li class="px-5 py-4">
        <?php if ($imgUrl = ItemImage::url($it['image'] ?? null)): ?>
          <a href="<?= e($imgUrl) ?>" target="_blank" rel="noopener" class="mb-3 block overflow-hidden rounded-xl bg-slate-50 ring-1 ring-slate-200" aria-label="Bild vergrössern"><img src="<?= e($imgUrl) ?>" alt="<?= e($it['title']) ?>" loading="lazy" class="max-h-56 w-full object-cover"></a>
        <?php endif; ?>
        <div class="flex justify-between gap-3">
          <p class="font-medium text-slate-900"><span class="mr-1 text-slate-400"><?= (int) $it['position'] ?>.</span><?= e($it['title']) ?></p>
          <p class="whitespace-nowrap font-semibold text-slate-900 tabular"><?= money($it['line_total'], $cur, false) ?></p>
        </div>
        <?php if ($it['description']): ?><p class="mt-1 text-[13px] leading-relaxed text-slate-500"><?= nl($it['description']) ?></p><?php endif; ?>
        <p class="mt-1.5 text-xs text-slate-500 tabular">
          <?= qty($it['quantity']) ?> <?= e($it['unit']) ?> × <?= money($it['unit_price'], $cur, false) ?>
          <?php if ((float) $it['line_discount'] > 0): ?> · <span class="text-emerald-700">Rabatt −<?= money($it['line_discount'], $cur, false) ?></span><?php endif; ?>
          <?php if ($showTaxCol): ?> · <?= e($it['tax_label']) ?><?php endif; ?>
        </p>
      </li>
    <?php endforeach; ?>
  </ul>

  <!-- Summen -->
  <div class="flex justify-end px-5 py-6 sm:px-10">
    <dl class="w-full max-w-sm text-sm">
      <div class="dl-row"><dt>Zwischensumme</dt><dd class="tabular"><?= money($offer['subtotal'], $cur) ?></dd></div>
      <?php if ((float) $offer['discount_amount'] > 0): ?>
        <div class="dl-row"><dt>Rabatt<?= $offer['discount_type'] === 'percent' ? ' ' . rate($offer['discount_value']) : '' ?></dt><dd class="text-emerald-700 tabular">−<?= money($offer['discount_amount'], $cur) ?></dd></div>
      <?php endif; ?>
      <?php if ($offer['area_type'] === 'physical'): ?>
        <div class="dl-row"><dt>Versand<?= $offer['shipping_method'] ? ' (' . e($offer['shipping_method']) . ')' : '' ?></dt><dd class="tabular"><?= (float) $offer['shipping_cost'] > 0 ? money($offer['shipping_cost'], $cur) : 'kostenlos' ?></dd></div>
      <?php endif; ?>
      <?php if ($liable): ?>
      <div class="dl-row border-t border-slate-100 pt-3"><dt class="!text-slate-700 font-medium">Total exkl. MWST</dt><dd class="tabular"><?= money($offer['net_total'], $cur) ?></dd></div>
      <?php foreach ($offer['taxes'] as $t): ?>
        <div class="dl-row"><dt><?= e($t['label']) ?><span class="block text-xs text-slate-400">auf <?= money($t['base'], $cur) ?></span></dt><dd class="tabular"><?= money($t['tax'], $cur) ?></dd></div>
      <?php endforeach; endif; ?>
      <?php if ((float) $offer['rounding'] != 0.0): ?>
        <div class="dl-row"><dt>Rundungsdifferenz</dt><dd class="tabular"><?= money($offer['rounding'], $cur) ?></dd></div>
      <?php endif; ?>
      <div class="mt-3 flex items-baseline justify-between gap-4 rounded-xl bg-brand-50 px-4 py-3.5 ring-1 ring-brand-100">
        <dt class="text-sm font-semibold text-brand-900">Gesamtbetrag</dt>
        <dd class="text-xl font-bold text-brand-900 tabular"><?= money($offer['total'], $cur) ?></dd>
      </div>
      <?php if ($isInvoice && !empty($offer['deduction_list'])): ?>
        <?php foreach ($offer['deduction_list'] as $ded): ?>
          <div class="dl-row mt-2"><dt>abzgl. Anzahlung <?= e($ded['number']) ?><span class="block text-xs text-slate-400"><?= fmt_date($ded['date']) ?></span></dt><dd class="tabular">−<?= money($ded['total'], $cur) ?></dd></div>
        <?php endforeach; ?>
        <div class="mt-3 flex items-baseline justify-between gap-4 rounded-xl bg-brand-600 px-4 py-3.5 text-white">
          <dt class="text-sm font-semibold">Zu zahlen</dt><dd class="text-xl font-bold tabular"><?= money($offer['amount_due'], $cur) ?></dd>
        </div>
      <?php endif; ?>
      <?php if ($isInvoice && !$isCreditDoc && (!empty($offer['payments']) || !empty($offer['adjustments'])) && $offer['status'] !== 'cancelled'): ?>
        <?php foreach ($offer['payments'] as $lp): $isRef = (float) $lp['amount'] < 0; ?>
          <div class="dl-row mt-2"><dt><?= $isRef ? 'Rückerstattung' : 'Zahlung' ?> vom <?= fmt_date($lp['paid_on']) ?></dt><dd class="<?= $isRef ? 'text-rose-700' : 'text-emerald-700' ?> tabular"><?= $isRef ? '+' : '−' ?><?= money(abs((float) $lp['amount']), $cur) ?></dd></div>
        <?php endforeach; ?>
        <?php foreach ($offer['adjustments'] ?? [] as $ad): ?>
          <div class="dl-row mt-2"><dt><?= $ad['kind'] === 'credit' ? 'Gutschrift ' . e((string) $ad['credit_number']) : 'Skonto/Preisnachlass' ?> vom <?= fmt_date($ad['adj_date']) ?></dt><dd class="text-emerald-700 tabular">−<?= money($ad['amount'], $cur) ?></dd></div>
        <?php endforeach; ?>
        <?php if ($offer['status'] !== 'paid'): ?>
        <div class="mt-3 flex items-baseline justify-between gap-4 rounded-xl bg-amber-50 px-4 py-3 text-amber-900 ring-1 ring-amber-200">
          <dt class="text-sm font-semibold">Noch offen</dt><dd class="text-lg font-bold tabular"><?= money($offer['open_amount'], $cur) ?></dd>
        </div>
        <?php endif; ?>
      <?php endif; ?>
    </dl>
  </div>

  <?php if ($isInvoice && !$isCreditDoc && $offer['status'] !== 'cancelled'): ?>
  <div class="mx-5 mb-6 rounded-2xl p-4 text-sm ring-1 sm:mx-10 <?= $offer['status'] === 'paid' ? 'bg-emerald-50 text-emerald-900 ring-emerald-200' : 'bg-slate-50 text-slate-700 ring-slate-200' ?>">
    <?php if ($offer['status'] === 'paid'): ?>
      <p class="flex items-center gap-2 font-semibold"><i class="bx bx-check-circle text-xl"></i>Bezahlt am <?= fmt_date($offer['paid_at']) ?> – vielen Dank!</p>
    <?php elseif (!empty($offer['installments'])): ?>
      <p class="font-semibold text-slate-900">Zahlbar in <?= count($offer['installments']) ?> Raten</p>
      <ul class="mt-2 divide-y divide-slate-200/70">
        <?php foreach ($offer['installments'] as $r): ?>
          <li class="flex items-center justify-between gap-3 py-1.5"><span><?= e($r['label']) ?> · fällig <?= fmt_date($r['due_date']) ?></span>
            <span class="flex items-center gap-2"><span class="tabular font-medium"><?= money($r['amount'], $cur) ?></span>
            <?php if ($r['state'] === 'paid'): ?><span class="badge badge-emerald">bezahlt</span><?php elseif ($r['state'] === 'partial'): ?><span class="badge badge-amber">teilweise</span><?php elseif ($r['state'] === 'overdue'): ?><span class="badge badge-rose">überfällig</span><?php endif; ?></span></li>
        <?php endforeach; ?>
      </ul>
      <?php if ($offer['payment_info']): ?><p class="mt-2 font-mono text-[13px] leading-relaxed"><?= nl($offer['payment_info']) ?></p><?php endif; ?>
    <?php else: ?>
      <p class="font-semibold text-slate-900">Zahlbar bis <?= fmt_date($offer['due_date']) ?></p>
      <?php if ($offer['payment_info']): ?><p class="mt-1.5 font-mono text-[13px] leading-relaxed"><?= nl($offer['payment_info']) ?></p><?php endif; ?>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if (!$liable || $taxNotes || $offer['footer'] || PdfService::uidLine() !== ''): ?>
  <div class="space-y-3 border-t border-slate-100 px-5 py-6 text-sm leading-relaxed text-slate-600 sm:px-10">
    <?php if (!$liable): ?>
      <p class="text-xs text-slate-500">Nicht MWST-pflichtig – es wird keine Mehrwertsteuer ausgewiesen.</p>
    <?php endif; ?>
    <?php foreach ($taxNotes as $tn): ?><p class="text-xs text-slate-500"><?= e($tn) ?></p><?php endforeach; ?>
    <?php if ($offer['footer']): ?><p><?= nl($offer['footer']) ?></p><?php endif; ?>
    <?php if (PdfService::uidLine() !== ''): ?><p class="text-xs text-slate-400"><?= e(Settings::get('company_name')) ?> · <?= e(PdfService::uidLine()) ?></p><?php endif; ?>
  </div>
  <?php endif; ?>
</article>
