<?php
$title = customer_name($customer);
$subtitle = e($customer['customer_no']) . ' · Kunde seit ' . fmt_date($customer['created_at']);
$back = ['admin/customers', 'Kunden'];
$actions = '<a href="' . e(url('admin/customers/' . $customer['id'] . '/edit')) . '" class="btn btn-secondary"><i class="bx bx-edit"></i>Bearbeiten</a>'
    . '<a href="' . e(url('admin/offers/new?customer=' . $customer['id'])) . '" class="btn btn-primary"><i class="bx bx-plus"></i>Neue Offerte</a>';
include APP_DIR . '/views/partials/page-header.php';
$revenue = array_sum(array_map(static fn($o) => $o['status'] !== 'cancelled' ? (float) $o['total'] : 0, $orders));
?>
<div class="grid gap-6 lg:grid-cols-3">
  <div class="space-y-6 lg:col-span-2">
    <div class="grid gap-4 sm:grid-cols-3">
      <?php foreach ([['Offerten', count($offers), 'bx-file', 'bg-sky-50 text-sky-600'], ['Aufträge', count($orders), 'bx-package', 'bg-amber-50 text-amber-600'], ['Umsatz', money($revenue), 'bx-wallet', 'bg-emerald-50 text-emerald-600']] as $i => [$l, $val, $ic, $cl]): ?>
        <div class="reveal reveal-delay-<?= $i ?> card flex items-center gap-4 p-5"><span class="kpi-icon <?= $cl ?>"><i class="bx <?= $ic ?>"></i></span><div><p class="text-lg font-bold text-slate-900 tabular"><?= e((string) $val) ?></p><p class="text-xs text-slate-500"><?= $l ?></p></div></div>
      <?php endforeach; ?>
    </div>

    <section class="reveal card overflow-hidden">
      <div class="card-header"><h2 class="card-title"><i class="bx bx-file"></i>Offerten</h2></div>
      <?php if (!$offers): ?>
        <div class="empty-state py-10"><i class="bx bx-file-blank"></i><p class="text-sm text-slate-500">Noch keine Offerten für diesen Kunden.</p></div>
      <?php else: ?>
      <div class="overflow-x-auto"><table class="table">
        <thead><tr><th>Nummer</th><th>Titel</th><th class="hidden sm:table-cell">Datum</th><th>Status</th><th class="text-right">Betrag</th></tr></thead>
        <tbody><?php foreach ($offers as $o): ?>
          <tr class="row-link" data-href="<?= e(url('admin/offers/' . $o['id'])) ?>">
            <td class="font-medium text-slate-900"><?= e($o['number']) ?></td>
            <td class="max-w-[220px] truncate"><?= e($o['title']) ?></td>
            <td class="hidden sm:table-cell"><?= fmt_date($o['created_at']) ?></td>
            <td><?= Status::badge('offer', $o['status']) ?></td>
            <td class="text-right font-medium tabular"><?= money($o['total'], $o['currency']) ?></td>
          </tr><?php endforeach; ?></tbody>
      </table></div>
      <?php endif; ?>
    </section>

    <section class="reveal card overflow-hidden">
      <div class="card-header"><h2 class="card-title"><i class="bx bx-package"></i>Aufträge</h2></div>
      <?php if (!$orders): ?>
        <div class="empty-state py-10"><i class="bx bx-package"></i><p class="text-sm text-slate-500">Noch keine Aufträge.</p></div>
      <?php else: ?>
      <div class="overflow-x-auto"><table class="table">
        <thead><tr><th>Nummer</th><th>Titel</th><th class="hidden sm:table-cell">Datum</th><th>Status</th><th class="text-right">Betrag</th></tr></thead>
        <tbody><?php foreach ($orders as $o): ?>
          <tr class="row-link" data-href="<?= e(url('admin/orders/' . $o['id'])) ?>">
            <td class="font-medium text-slate-900"><?= e($o['number']) ?></td>
            <td class="max-w-[220px] truncate"><?= e($o['title']) ?></td>
            <td class="hidden sm:table-cell"><?= fmt_date($o['created_at']) ?></td>
            <td><?= Status::badge('order', $o['status'], $o['area_type']) ?></td>
            <td class="text-right font-medium tabular"><?= money($o['total'], $o['currency']) ?></td>
          </tr><?php endforeach; ?></tbody>
      </table></div>
      <?php endif; ?>
    </section>
  </div>

  <aside class="space-y-6">
    <section class="reveal reveal-delay-1 card p-5 sm:p-6">
      <div class="flex items-center gap-3">
        <span class="grid size-12 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-brand-700 text-sm font-bold text-white"><?= e(initials(customer_name($customer))) ?></span>
        <div class="min-w-0"><p class="truncate font-semibold text-slate-900"><?= e(customer_name($customer)) ?></p><p class="truncate text-sm text-slate-500"><?= e(customer_person($customer)) ?></p></div>
      </div>
      <dl class="mt-5 space-y-3 text-sm">
        <div class="flex gap-3"><dt><i class="bx bx-envelope text-lg text-slate-400"></i></dt><dd class="min-w-0 truncate"><a class="link" href="mailto:<?= e($customer['email']) ?>"><?= e($customer['email']) ?></a></dd></div>
        <?php foreach (['phone' => 'bx-phone', 'mobile' => 'bx-mobile'] as $f => $ic): if ($customer[$f]): ?>
          <div class="flex gap-3"><dt><i class="bx <?= $ic ?> text-lg text-slate-400"></i></dt><dd><a class="hover:text-brand-700" href="tel:<?= e(preg_replace('/[^+\d]/', '', $customer[$f])) ?>"><?= e($customer[$f]) ?></a></dd></div>
        <?php endif; endforeach; ?>
        <?php if ($customer['website']): ?><div class="flex gap-3"><dt><i class="bx bx-globe text-lg text-slate-400"></i></dt><dd class="min-w-0 truncate"><a class="link" href="<?= e($customer['website']) ?>" target="_blank" rel="noopener noreferrer"><?= e(preg_replace('#^https?://#', '', $customer['website'])) ?></a></dd></div><?php endif; ?>
        <div class="flex gap-3"><dt><i class="bx bx-map text-lg text-slate-400"></i></dt><dd class="leading-relaxed"><?= implode('<br>', array_map('e', array_filter([$customer['street'], $customer['address_extra'], trim($customer['zip'] . ' ' . $customer['city']), country_name($customer['country'])]))) ?></dd></div>
      </dl>
    </section>

    <section class="reveal reveal-delay-2 card p-5 sm:p-6">
      <h3 class="text-sm font-semibold text-slate-900">Steuern</h3>
      <p class="mt-2 text-sm text-slate-600">Standard bei Offerten:</p>
      <p class="mt-1"><span class="badge badge-brand"><i class="bx bx-receipt"></i><?= e($tax['display'] ?? '–') ?></span></p>
      <?php if ($customer['vat_id']): ?><p class="mt-3 text-xs text-slate-500">UID: <?= e($customer['vat_id']) ?></p><?php endif; ?>
    </section>

    <section class="reveal reveal-delay-3 card p-5 sm:p-6">
      <h3 class="text-sm font-semibold text-slate-900">Kundenportal</h3>
      <p class="mt-2 text-sm text-slate-600"><?= $customer['portal_enabled'] ? 'Zugang aktiv' : 'Zugang deaktiviert' ?><?= $customer['last_portal_login_at'] ? ' · zuletzt ' . time_ago($customer['last_portal_login_at']) : '' ?></p>
      <?php if ($customer['portal_enabled']): ?>
        <form method="post" action="<?= e(url('admin/customers/' . $customer['id'] . '/portal-link')) ?>" class="mt-3" data-confirm="Einen Anmeldelink (24 Stunden gültig) an <?= e($customer['email']) ?> senden?" data-confirm-label="Senden" data-confirm-variant="primary"><?= csrf_field() ?>
          <button class="btn btn-secondary btn-sm"><i class="bx bx-send"></i>Anmeldelink senden</button>
        </form>
      <?php endif; ?>
    </section>

    <section class="reveal card p-5 sm:p-6">
      <h3 class="text-sm font-semibold text-slate-900"><i class="bx bx-shield-alt-2 align-middle text-slate-400"></i> Datenschutz</h3>
      <?php if ($customer['anonymized_at']): ?>
        <p class="mt-2 text-sm text-slate-600">Anonymisiert am <?= fmt_datetime($customer['anonymized_at']) ?>.</p>
      <?php else: ?>
        <?php if ($customer['restricted_at']): ?><p class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 ring-1 ring-amber-200">Verarbeitung eingeschränkt seit <?= fmt_datetime($customer['restricted_at']) ?> – keine neuen Offerten oder E-Mails.</p><?php endif; ?>
        <form method="post" action="<?= e(url('admin/customers/' . $customer['id'] . '/privacy-export')) ?>" class="mt-3"><?= csrf_field() ?>
          <button class="btn btn-secondary btn-sm w-full"><i class="bx bx-download"></i>Datenauskunft (JSON)</button>
        </form>
        <?php if (Auth::isAdmin()): ?>
          <?php if (!$privacy['can_anonymize']): ?>
            <p class="mt-3 text-xs text-slate-500">Vollständige Anonymisierung ist noch nicht möglich:</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-5 text-xs text-slate-500"><?php foreach ($privacy['reasons'] as $r): ?><li><?= e($r) ?></li><?php endforeach; ?></ul>
          <?php endif; ?>
          <?php if (!$customer['restricted_at']): ?>
          <form method="post" action="<?= e(url('admin/customers/' . $customer['id'] . '/restrict')) ?>" class="mt-3" data-confirm="Verarbeitung dieses Kunden einschränken? Telefon, Website, Notizen und Zugangsdaten werden gelöscht, das Portal gesperrt; es sind keine neuen Offerten und E-Mails mehr möglich." data-confirm-label="Einschränken" data-confirm-variant="danger"><?= csrf_field() ?>
            <button class="btn btn-ghost btn-sm w-full"><i class="bx bx-lock-alt"></i>Verarbeitung einschränken</button>
          </form>
          <?php endif; ?>
          <?php if ($privacy['can_anonymize']): ?>
          <form method="post" action="<?= e(url('admin/customers/' . $customer['id'] . '/anonymize')) ?>" class="mt-2" data-confirm="Kunden „<?= e(customer_name($customer)) ?>“ UNUMKEHRBAR anonymisieren? Name, Adresse, E-Mail, Unterschriften und Anhänge werden gelöscht." data-confirm-label="Anonymisieren" data-confirm-variant="danger"><?= csrf_field() ?>
            <button class="btn btn-ghost btn-sm w-full text-rose-600 hover:bg-rose-50 hover:text-rose-700"><i class="bx bx-eraser"></i>Anonymisieren (Löschung)</button>
          </form>
          <?php endif; ?>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <?php if ($customer['notes']): ?>
      <section class="reveal card p-5 sm:p-6"><h3 class="text-sm font-semibold text-slate-900">Notizen</h3><p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-600"><?= e($customer['notes']) ?></p></section>
    <?php endif; ?>

    <?php if (!$offers): ?>
      <form method="post" action="<?= e(url('admin/customers/' . $customer['id'] . '/delete')) ?>" data-confirm="Kunde „<?= e(customer_name($customer)) ?>“ endgültig löschen?" data-confirm-label="Löschen"><?= csrf_field() ?>
        <button class="btn btn-ghost btn-sm w-full text-rose-600 hover:bg-rose-50 hover:text-rose-700"><i class="bx bx-trash"></i>Kunde löschen</button>
      </form>
    <?php endif; ?>
  </aside>
</div>
