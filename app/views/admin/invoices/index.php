<?php
$title = 'Rechnungen';
$subtitle = 'Rechnungen entstehen aus Aufträgen. Offen insgesamt: <strong class="text-slate-700">' . e(money($sums['open_sum'])) . '</strong>';
include APP_DIR . '/views/partials/page-header.php';
$filters = ['' => 'Alle', 'draft' => 'Entwürfe', 'sent' => 'Offen', 'overdue' => 'Überfällig', 'paid' => 'Bezahlt', 'cancelled' => 'Storniert'];
?>
<div class="reveal mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
  <nav class="-mx-1 flex gap-1 overflow-x-auto px-1 pb-1" aria-label="Statusfilter">
    <?php foreach ($filters as $k => $label): ?>
      <a href="?<?= e(http_build_query(array_filter(['status' => $k, 'q' => $q]))) ?>" class="tab shrink-0<?= $status === $k ? ' is-active' : '' ?>"><?= e($label) ?> <span class="text-xs text-slate-400"><?= $counts[$k] ?? 0 ?></span></a>
    <?php endforeach; ?>
  </nav>
  <form method="get" class="relative lg:w-80" role="search">
    <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <i class="bx bx-search pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-lg text-slate-400"></i>
    <input class="input pl-10" type="search" name="q" value="<?= e($q) ?>" placeholder="Nummer, Titel, Kunde …">
  </form>
</div>
<div class="reveal card overflow-hidden">
  <?php if (!$invoices): ?>
    <div class="empty-state"><i class="bx bx-receipt"></i><p class="font-semibold text-slate-900">Keine Rechnungen</p><p class="mt-1 text-sm text-slate-500">Öffnen Sie einen Auftrag und wählen Sie „Rechnung erstellen“.</p></div>
  <?php else: ?>
  <div class="overflow-x-auto">
    <table class="table">
      <thead><tr><th>Rechnung</th><th class="hidden md:table-cell">Kunde</th><th class="hidden lg:table-cell">Datum</th><th class="hidden sm:table-cell">Fällig</th><th>Status</th><th class="text-right">Betrag</th></tr></thead>
      <tbody>
      <?php foreach ($invoices as $i): $ds = Status::invoiceDisplay($i); ?>
        <tr class="row-link" data-href="<?= e(url('admin/invoices/' . $i['id'])) ?>">
          <td>
            <a href="<?= e(url('admin/invoices/' . $i['id'])) ?>" class="block max-w-[260px] truncate font-medium text-slate-900 hover:text-brand-700"><?= e($i['title']) ?></a>
            <p class="text-xs text-slate-500"><?= e($i['number']) ?> · <?= e($i['order_number']) ?><span class="md:hidden"> · <?= e(customer_name($i)) ?></span></p>
          </td>
          <td class="hidden md:table-cell"><span class="block max-w-[200px] truncate"><?= e(customer_name($i)) ?></span></td>
          <td class="hidden lg:table-cell text-slate-500"><?= fmt_date($i['issue_date']) ?></td>
          <td class="hidden sm:table-cell <?= $ds === 'overdue' ? 'font-medium text-rose-600' : 'text-slate-500' ?>"><?= fmt_date($i['due_date']) ?></td>
          <td><?= Status::badge('invoice', $ds) ?></td>
          <td class="text-right font-semibold text-slate-900 tabular"><?php if ($i['status'] === 'sent' && (float) $i['paid_total'] > 0): ?><?= money((float) $i['amount_due'] - (float) $i['paid_total'], $i['currency']) ?><span class="block text-[11px] font-normal text-slate-400">offen von <?= money($i['amount_due'], $i['currency'], false) ?></span><?php else: ?><?= money($i['amount_due'], $i['currency']) ?><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
