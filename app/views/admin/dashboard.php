<?php
$title = 'Dashboard';
$hour = (int) date('G');
$greet = $hour < 11 ? 'Guten Morgen' : ($hour < 18 ? 'Guten Tag' : 'Guten Abend');
$max = max(1, ...array_map(static fn($m) => max($m['offers'], $m['orders']), array_values($months)));
$titleHtml = e($greet . ', ' . explode(' ', $user['name'])[0]);
$subtitle = 'Hier ist der aktuelle Stand Ihrer Offerten und Aufträge.';
$actions = '<a href="' . e(url('admin/customers/new')) . '" class="btn btn-secondary"><i class="bx bx-user-plus"></i>Kunde</a>'
    . '<a href="' . e(url('admin/offers/new')) . '" class="btn btn-primary"><i class="bx bx-plus"></i>Neue Offerte</a>';
include APP_DIR . '/views/partials/page-header.php';
$kpis = [
    ['Offene Offerten', (string) $stats['open_offers'], money($stats['open_value']) . ' Volumen', 'bx-file', 'bg-sky-50 text-sky-600', 'admin/offers?status=open'],
    ['Aktive Aufträge', (string) $stats['active_orders'], $stats['my_orders'] . ' bei Ihnen' . ($stats['unassigned'] ? ' · ' . $stats['unassigned'] . ' ohne Zuständigkeit' : ''), 'bx-package', 'bg-amber-50 text-amber-600', 'admin/orders?status=active&owner=me'],
    ['Auftragsvolumen ' . DashboardController::monthName((int) date('n')), money($stats['month_revenue']), 'inkl. Steuern', 'bx-trending-up', 'bg-emerald-50 text-emerald-600', 'admin/orders'],
    ['Offene Rechnungen', money($stats['inv_open']), $stats['inv_overdue'] ? $stats['inv_overdue'] . ' überfällig' : 'keine überfällig', 'bx-receipt', $stats['inv_overdue'] ? 'bg-rose-50 text-rose-600' : 'bg-teal-50 text-teal-600', 'admin/invoices?status=sent'],
    ['Annahmequote', $stats['acceptance'] === null ? '–' : $stats['acceptance'] . ' %', 'letzte 12 Monate', 'bx-target-lock', 'bg-violet-50 text-violet-600', 'admin/offers'],
];
?>
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
  <?php foreach ($kpis as $i => [$label, $value, $sub, $icon, $cls, $href]): ?>
    <a href="<?= e(url($href)) ?>" class="reveal reveal-delay-<?= min(3, $i) ?> card card-hover group p-5">
      <div class="flex items-start justify-between">
        <span class="kpi-icon <?= $cls ?> transition-transform duration-300 group-hover:scale-105"><i class="bx <?= $icon ?>"></i></span>
        <i class="bx bx-right-arrow-alt text-xl text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-slate-500"></i>
      </div>
      <p class="mt-4 text-sm font-medium text-slate-500"><?= e($label) ?></p>
      <p class="mt-1 text-2xl font-bold tracking-tight text-slate-900 tabular"><?= e($value) ?></p>
      <p class="mt-1 text-xs text-slate-400"><?= e($sub) ?></p>
    </a>
  <?php endforeach; ?>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-3">
  <!-- Diagramm -->
  <section class="reveal card xl:col-span-2">
    <div class="card-header">
      <h2 class="card-title"><i class="bx bx-bar-chart-alt-2"></i>Volumen der letzten 6 Monate</h2>
      <div class="flex items-center gap-4 text-xs text-slate-500">
        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-brand-200"></span>Offeriert</span>
        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-brand-600"></span>Beauftragt</span>
      </div>
    </div>
    <div class="flex h-64 items-end gap-3 px-5 pb-5 pt-8 sm:gap-6 sm:px-6">
      <?php $i = 0; foreach ($months as $m): $i++; ?>
        <div class="flex h-full flex-1 flex-col items-center gap-2">
          <div class="flex w-full flex-1 items-end justify-center gap-1">
            <div class="bar w-1/2 max-w-7 rounded-t-md bg-brand-200 transition hover:bg-brand-300" style="height: <?= round($m['offers'] / $max * 100, 1) ?>%; animation-delay: <?= $i * 60 ?>ms" data-tip="Offeriert: <?= e(money($m['offers'])) ?>"></div>
            <div class="bar w-1/2 max-w-7 rounded-t-md bg-brand-600 transition hover:bg-brand-700" style="height: <?= round($m['orders'] / $max * 100, 1) ?>%; animation-delay: <?= $i * 60 + 30 ?>ms" data-tip="Beauftragt: <?= e(money($m['orders'])) ?>"></div>
          </div>
          <span class="text-xs font-medium text-slate-500"><?= e($m['label']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Aktivität -->
  <section class="reveal reveal-delay-1 card">
    <div class="card-header"><h2 class="card-title"><i class="bx bx-pulse"></i>Letzte Aktivitäten</h2></div>
    <?php if (!$activity): ?>
      <div class="empty-state py-10"><i class="bx bx-coffee"></i><p class="text-sm text-slate-500">Noch keine Aktivitäten.</p></div>
    <?php else: ?>
    <ul class="divide-y divide-slate-100">
      <?php foreach ($activity as $h):
        [$label, $icon, $color] = History::describe($h, $h['area_type'] ?? null);
        $bg = $color === 'brand' ? 'bg-brand-50 text-brand-600' : "bg-$color-50 text-$color-600";
        $href = url('admin/' . ['offer' => 'offers/', 'order' => 'orders/', 'invoice' => 'invoices/'][$h['entity_type']] . $h['entity_id']); ?>
        <li><a href="<?= e($href) ?>" class="flex items-center gap-3 px-5 py-3 transition hover:bg-slate-50 sm:px-6">
          <span class="grid size-8 shrink-0 place-items-center rounded-full <?= $bg ?>"><i class="bx <?= e($icon) ?>"></i></span>
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm text-slate-800"><span class="font-medium"><?= e($h['ref_number'] ?? '#' . $h['entity_id']) ?></span> · <?= e($label) ?></p>
            <p class="truncate text-xs text-slate-400"><?= e($h['actor_name'] ?? '') ?> · <?= time_ago($h['created_at']) ?></p>
          </div>
        </a></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
  <section class="reveal card">
    <div class="card-header">
      <h2 class="card-title"><i class="bx bx-time-five"></i>Offene Offerten</h2>
      <a href="<?= e(url('admin/offers?status=open')) ?>" class="btn btn-ghost btn-sm">Alle<i class="bx bx-chevron-right"></i></a>
    </div>
    <?php if (!$openOffers): ?>
      <div class="empty-state py-10"><i class="bx bx-check-double"></i><p class="text-sm text-slate-500">Keine offenen Offerten.</p></div>
    <?php else: ?>
    <ul class="divide-y divide-slate-100">
      <?php foreach ($openOffers as $o):
        $soon = $o['valid_until'] && strtotime($o['valid_until']) - time() < 3 * 86400; ?>
        <li><a href="<?= e(url('admin/offers/' . $o['id'])) ?>" class="flex items-center gap-4 px-5 py-3.5 transition hover:bg-slate-50 sm:px-6">
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium text-slate-900"><?= e($o['title']) ?></p>
            <p class="truncate text-xs text-slate-500"><?= e($o['number']) ?> · <?= e(customer_name($o)) ?><?= $o['issuer_label'] ? ' · ' . e($o['issuer_label']) : '' ?></p>
          </div>
          <div class="text-right">
            <p class="text-sm font-semibold text-slate-900 tabular"><?= money($o['total'], $o['currency']) ?></p>
            <p class="text-xs <?= $soon ? 'font-medium text-amber-600' : 'text-slate-400' ?>"><?= $o['valid_until'] ? 'bis ' . fmt_date($o['valid_until']) : 'unbefristet' ?></p>
          </div>
        </a></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>

  <section class="reveal reveal-delay-1 card">
    <div class="card-header">
      <h2 class="card-title"><i class="bx bx-package"></i>Aufträge in Bearbeitung</h2>
      <a href="<?= e(url('admin/orders?status=active')) ?>" class="btn btn-ghost btn-sm">Alle<i class="bx bx-chevron-right"></i></a>
    </div>
    <?php if (!$activeOrders): ?>
      <div class="empty-state py-10"><i class="bx bx-party"></i><p class="text-sm text-slate-500">Alle Aufträge sind erledigt.</p></div>
    <?php else: ?>
    <ul class="divide-y divide-slate-100">
      <?php foreach ($activeOrders as $o): ?>
        <li><a href="<?= e(url('admin/orders/' . $o['id'])) ?>" class="flex items-center gap-4 px-5 py-3.5 transition hover:bg-slate-50 sm:px-6">
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium text-slate-900"><?= e($o['title']) ?></p>
            <p class="truncate text-xs text-slate-500"><?= e($o['number']) ?> · <?= e(customer_name($o)) ?></p>
          </div>
          <span class="hidden max-w-[130px] text-xs text-slate-500 sm:block"><?= person_chip($o['responsible_label'], 'offen') ?></span>
          <?= Status::badge('order', $o['status'], $o['area_type']) ?>
        </a></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>
</div>
