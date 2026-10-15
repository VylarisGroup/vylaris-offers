<?php
$title = 'Kundenportal';
$openOffers = array_filter($offers, static fn($o) => $o['status'] === 'open');
$activeOrders = array_filter($orders, static fn($o) => in_array($o['status'], ['new', 'in_progress', 'fulfilled'], true));
$tab = in_array($_GET['tab'] ?? '', ['orders', 'invoices'], true) ? $_GET['tab'] : 'offers';
$unpaid = array_filter($invoices, static fn($i) => $i['status'] === 'sent');
?>
<div class="reveal pt-4">
  <p class="text-sm font-medium text-brand-700">Kundenportal</p>
  <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Guten Tag, <?= e(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: customer_name($c)) ?></h1>
  <p class="mt-1 text-sm text-slate-500"><?= e(customer_name($c)) ?> · Kunden-Nr. <?= e($c['customer_no']) ?></p>
</div>

<div class="mt-8 grid gap-4 sm:grid-cols-3">
  <?php foreach ([
      ['Offene Offerten', count($openOffers), 'bx-file', 'bg-sky-50 text-sky-600'],
      ['Aktive Aufträge', count($activeOrders), 'bx-loader-circle', 'bg-amber-50 text-amber-600'],
      ['Offene Rechnungen', count($unpaid), 'bx-receipt', 'bg-emerald-50 text-emerald-600'],
  ] as $i => [$label, $val, $icon, $cls]): ?>
    <div class="reveal reveal-delay-<?= $i + 1 ?> card card-hover flex items-center gap-4 p-5">
      <span class="kpi-icon <?= $cls ?>"><i class="bx <?= $icon ?>"></i></span>
      <div><p class="text-2xl font-bold text-slate-900 tabular"><?= $val ?></p><p class="text-sm text-slate-500"><?= $label ?></p></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="reveal mt-10">
  <div class="inline-flex gap-1 rounded-xl bg-slate-200/60 p-1" role="tablist">
    <a href="?tab=offers" class="tab<?= $tab === 'offers' ? ' is-active' : '' ?>"><i class="bx bx-file"></i>Offerten <span class="text-xs text-slate-400"><?= count($offers) ?></span></a>
    <a href="?tab=orders" class="tab<?= $tab === 'orders' ? ' is-active' : '' ?>"><i class="bx bx-package"></i>Aufträge <span class="text-xs text-slate-400"><?= count($orders) ?></span></a>
    <a href="?tab=invoices" class="tab<?= $tab === 'invoices' ? ' is-active' : '' ?>"><i class="bx bx-receipt"></i>Rechnungen <span class="text-xs text-slate-400"><?= count($invoices) ?></span></a>
  </div>
</div>

<div class="reveal mt-4 card overflow-hidden">
<?php if ($tab === 'offers'): ?>
  <?php if (!$offers): ?>
    <div class="empty-state"><i class="bx bx-file-blank"></i><p class="font-semibold text-slate-900">Noch keine Offerten</p><p class="mt-1 text-sm text-slate-500">Sobald wir Ihnen eine Offerte senden, erscheint sie hier.</p></div>
  <?php else: ?>
    <ul class="divide-y divide-slate-100">
      <?php foreach ($offers as $o): ?>
      <li>
        <a href="<?= e(url('o/' . $o['public_id'])) ?>" class="group flex flex-col gap-3 px-5 py-4 transition hover:bg-slate-50 sm:flex-row sm:items-center sm:px-6">
          <span class="hidden size-10 shrink-0 place-items-center rounded-xl bg-slate-100 text-xl text-slate-500 transition group-hover:bg-brand-50 group-hover:text-brand-600 sm:grid"><i class="bx <?= $o['area_type'] === 'digital' ? 'bx-cloud' : 'bx-package' ?>"></i></span>
          <div class="min-w-0 flex-1">
            <p class="truncate font-medium text-slate-900"><?= e($o['title']) ?></p>
            <p class="text-xs text-slate-500"><?= e($o['number']) ?> · <?= fmt_date($o['created_at']) ?><?= $o['valid_until'] && $o['status'] === 'open' ? ' · gültig bis ' . fmt_date($o['valid_until']) : '' ?></p>
          </div>
          <div class="flex items-center justify-between gap-4 sm:justify-end">
            <?= Status::badge('offer', $o['status']) ?>
            <span class="w-32 text-right font-semibold text-slate-900 tabular"><?= money($o['total'], $o['currency']) ?></span>
            <i class="bx bx-chevron-right hidden text-xl text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-slate-500 sm:block"></i>
          </div>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
<?php elseif ($tab === 'invoices'): ?>
  <?php if (!$invoices): ?>
    <div class="empty-state"><i class="bx bx-receipt"></i><p class="font-semibold text-slate-900">Noch keine Rechnungen</p><p class="mt-1 text-sm text-slate-500">Rechnungen erscheinen hier, sobald wir sie Ihnen senden.</p></div>
  <?php else: ?>
    <ul class="divide-y divide-slate-100">
      <?php foreach ($invoices as $i): ?>
      <li>
        <a href="<?= e(url('r/' . $i['public_id'])) ?>" class="group flex flex-col gap-3 px-5 py-4 transition hover:bg-slate-50 sm:flex-row sm:items-center sm:px-6">
          <span class="hidden size-10 shrink-0 place-items-center rounded-xl bg-slate-100 text-xl text-slate-500 transition group-hover:bg-brand-50 group-hover:text-brand-600 sm:grid"><i class="bx bx-receipt"></i></span>
          <div class="min-w-0 flex-1">
            <p class="truncate font-medium text-slate-900"><?= e($i['title']) ?></p>
            <p class="text-xs text-slate-500"><?= e($i['number']) ?> · <?= fmt_date($i['issue_date']) ?><?= $i['status'] === 'sent' ? ' · fällig ' . fmt_date($i['due_date']) : '' ?></p>
          </div>
          <div class="flex items-center justify-between gap-4 sm:justify-end">
            <?= Status::badge('invoice', Status::invoiceDisplay($i)) ?>
            <span class="w-32 text-right font-semibold text-slate-900 tabular"><?= money($i['amount_due'], $i['currency']) ?></span>
            <i class="bx bx-chevron-right hidden text-xl text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-slate-500 sm:block"></i>
          </div>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
<?php else: ?>
  <?php if (!$orders): ?>
    <div class="empty-state"><i class="bx bx-package"></i><p class="font-semibold text-slate-900">Noch keine Aufträge</p><p class="mt-1 text-sm text-slate-500">Nach der Annahme einer Offerte wird automatisch ein Auftrag eröffnet.</p></div>
  <?php else: ?>
    <ul class="divide-y divide-slate-100">
      <?php foreach ($orders as $o): ?>
      <li>
        <a href="<?= e(url('portal/orders/' . $o['id'])) ?>" class="group flex flex-col gap-3 px-5 py-4 transition hover:bg-slate-50 sm:flex-row sm:items-center sm:px-6">
          <span class="hidden size-10 shrink-0 place-items-center rounded-xl bg-slate-100 text-xl text-slate-500 transition group-hover:bg-brand-50 group-hover:text-brand-600 sm:grid"><i class="bx <?= $o['area_type'] === 'digital' ? 'bx-cloud-download' : 'bx-package' ?>"></i></span>
          <div class="min-w-0 flex-1">
            <p class="truncate font-medium text-slate-900"><?= e($o['title']) ?></p>
            <p class="text-xs text-slate-500"><?= e($o['number']) ?> · <?= fmt_date($o['created_at']) ?><?= $o['tracking_number'] ? ' · Sendung ' . e($o['tracking_number']) : '' ?></p>
          </div>
          <div class="flex items-center justify-between gap-4 sm:justify-end">
            <?= Status::badge('order', $o['status'], $o['area_type']) ?>
            <span class="w-32 text-right font-semibold text-slate-900 tabular"><?= money($o['total'], $o['currency']) ?></span>
            <i class="bx bx-chevron-right hidden text-xl text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-slate-500 sm:block"></i>
          </div>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
<?php endif; ?>
  <section class="mt-10 rounded-2xl bg-slate-50 p-5 text-sm text-slate-600 ring-1 ring-slate-200/70">
    <h2 class="font-semibold text-slate-900"><i class="bx bx-shield-alt-2 align-middle text-slate-400"></i> Ihre Daten</h2>
    <p class="mt-1">Sie können die zu Ihrer Person gespeicherten Daten jederzeit herunterladen (JSON). Für Berichtigung oder Löschung wenden Sie sich bitte an uns – siehe <a class="link" href="<?= e(url('datenschutz')) ?>">Datenschutzerklärung</a>.</p>
    <form method="post" action="<?= e(url('portal/export')) ?>" class="mt-3"><?= csrf_field() ?><button class="btn btn-secondary btn-sm"><i class="bx bx-download"></i>Meine Daten herunterladen</button></form>
  </section>
</div>
