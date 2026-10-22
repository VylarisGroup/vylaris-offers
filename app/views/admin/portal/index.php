<?php
$title = 'Kundenportal';
$subtitle = 'Zugang der Kunden, Systemprüfung und Fehlersuche bei der Anmeldung';
$actions = '<a href="' . e(url('portal')) . '" target="_blank" rel="noopener" class="btn btn-secondary"><i class="bx bx-link-external"></i>Portal öffnen</a><a href="' . e(url('admin/messages')) . '" class="btn btn-secondary"><i class="bx bx-message-square-dots"></i>Nachrichten' . ($stats['unread'] ? ' <span class="badge badge-brand">' . (int) $stats['unread'] . '</span>' : '') . '</a>';
include APP_DIR . '/views/partials/page-header.php';
$icon = ['ok' => ['bx-check-circle', 'text-emerald-600'], 'err' => ['bx-error-circle', 'text-rose-600'], 'warn' => ['bx-info-circle', 'text-amber-600']];
?>
<div class="grid gap-4 sm:grid-cols-4">
  <?php foreach ([['Zugang aktiv', $stats['enabled'], 'bx-user-check', 'bg-emerald-50 text-emerald-600'], ['Zugang aus', $stats['disabled'], 'bx-user-x', 'bg-slate-100 text-slate-500'], ['Schon angemeldet', $stats['logged_in'], 'bx-log-in-circle', 'bg-sky-50 text-sky-600'], ['Ungelesene Nachrichten', $stats['unread'], 'bx-message-square-dots', 'bg-amber-50 text-amber-600']] as $i => [$l, $v, $ic, $cls]): ?>
    <div class="reveal card flex items-center gap-4 p-5"><span class="kpi-icon <?= $cls ?>"><i class="bx <?= $ic ?>"></i></span><div><p class="text-2xl font-bold text-slate-900 tabular"><?= (int) $v ?></p><p class="text-sm text-slate-500"><?= $l ?></p></div></div>
  <?php endforeach; ?>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
  <section class="reveal card">
    <div class="card-header"><h2 class="card-title"><i class="bx bx-check-shield"></i>Systemprüfung</h2></div>
    <ul class="divide-y divide-slate-100">
      <?php foreach ($checks as [$label, $ok, $hint]): ?>
      <li class="flex gap-3 px-5 py-3 sm:px-6"><i class="bx <?= $ok ? 'bx-check-circle text-emerald-600' : 'bx-error-circle text-rose-600' ?> mt-0.5 text-xl"></i>
        <div class="min-w-0"><p class="text-sm font-medium text-slate-900"><?= e($label) ?></p><p class="text-sm text-slate-500"><?= e($hint) ?></p></div></li>
      <?php endforeach; ?>
    </ul>
  </section>

  <section class="reveal card">
    <div class="card-header"><h2 class="card-title"><i class="bx bx-search-alt"></i>Anmeldung eines Kunden prüfen</h2></div>
    <form method="get" class="flex gap-2 p-5 pb-3 sm:px-6"><input class="input" type="email" name="q" value="<?= e($q) ?>" placeholder="E-Mail-Adresse des Kunden" required><button class="btn btn-primary"><i class="bx bx-search"></i>Prüfen</button></form>
    <?php if ($diag): ?>
    <ul class="space-y-2 px-5 pb-5 sm:px-6">
      <?php foreach ($diag as [$t, $msg]): ?><li class="flex gap-2 text-sm text-slate-700"><i class="bx <?= $icon[$t][0] ?> mt-0.5 text-lg <?= $icon[$t][1] ?>"></i><span><?= e($msg) ?></span></li><?php endforeach; ?>
    </ul>
    <?php else: ?><p class="px-5 pb-5 text-sm text-slate-500 sm:px-6">Zeigt, warum ein Kunde keinen Anmeldelink erhält (Zugang, Sperre, Limit, letzter E-Mail-Versand).</p><?php endif; ?>
    <?php if ($mailLog): ?>
    <div class="border-t border-slate-100 px-5 py-4 sm:px-6"><p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Letzte Anmeldelink-E-Mails</p>
      <ul class="space-y-1 text-xs text-slate-600"><?php foreach ($mailLog as $m): ?><li class="flex flex-wrap gap-x-2"><span class="badge <?= $m['status'] === 'sent' ? 'badge-emerald' : 'badge-rose' ?>"><?= $m['status'] === 'sent' ? 'gesendet' : 'Fehler' ?></span><span><?= e($m['recipient']) ?></span><span class="text-slate-400"><?= fmt_datetime($m['created_at']) ?></span><?php if ($m['error']): ?><span class="w-full text-rose-600"><?= e(mb_substr((string) $m['error'], 0, 200)) ?></span><?php endif; ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
  </section>
</div>

<section class="reveal card mt-6">
  <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 p-3">
    <?php foreach (['' => 'Alle', 'on' => 'Zugang aktiv', 'never' => 'Noch nie angemeldet', 'off' => 'Zugang aus'] as $k => $l): ?><a href="<?= e(url('admin/portal' . ($k !== '' ? '?f=' . $k : ''))) ?>" class="tab<?= $filter === $k ? ' is-active' : '' ?>"><?= e($l) ?></a><?php endforeach; ?>
  </div>
  <?php if (!$customers): ?><div class="empty-state"><i class="bx bx-user"></i><p class="text-sm text-slate-500">Keine Kunden in dieser Ansicht.</p></div>
  <?php else: ?>
  <div class="overflow-x-auto"><table class="table">
    <thead><tr><th>Kunde</th><th class="hidden sm:table-cell">E-Mail</th><th>Zugang</th><th class="hidden md:table-cell">Letzte Anmeldung</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($customers as $c): ?>
      <tr>
        <td><a class="font-medium text-slate-900 hover:text-brand-700" href="<?= e(url('admin/customers/' . $c['id'])) ?>"><?= e(customer_name($c)) ?></a><?php if ((int) $c['unread']): ?> <a href="<?= e(url('admin/messages/' . $c['id'])) ?>" class="badge badge-brand"><?= (int) $c['unread'] ?> neu</a><?php endif; ?><p class="text-xs text-slate-500"><?= e($c['customer_no']) ?></p></td>
        <td class="hidden sm:table-cell text-sm text-slate-600"><?= e($c['email']) ?></td>
        <td><?php if ($c['restricted_at']): ?><span class="badge badge-slate">eingeschränkt</span><?php else: ?><span class="badge <?= $c['portal_enabled'] ? 'badge-emerald' : 'badge-slate' ?>"><?= $c['portal_enabled'] ? 'aktiv' : 'aus' ?></span><?php endif; ?></td>
        <td class="hidden md:table-cell text-sm text-slate-500"><?= $c['last_portal_login_at'] ? e(time_ago($c['last_portal_login_at'])) : '–' ?></td>
        <td class="text-right"><div class="inline-flex gap-1">
          <?php if ($c['portal_enabled'] && !$c['restricted_at']): ?><form method="post" action="<?= e(url('admin/portal/' . $c['id'] . '/link')) ?>" data-confirm="Anmeldelink (24 Stunden gültig) an <?= e($c['email']) ?> senden?" data-confirm-label="Senden" data-confirm-variant="primary"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" data-tip="Anmeldelink senden"><i class="bx bx-send"></i></button></form><?php endif; ?>
          <?php if (!$c['restricted_at']): ?><form method="post" action="<?= e(url('admin/portal/' . $c['id'] . '/toggle')) ?>"><?= csrf_field() ?><input type="hidden" name="f" value="<?= e($filter) ?>"><button class="btn btn-ghost btn-sm" data-tip="<?= $c['portal_enabled'] ? 'Zugang deaktivieren' : 'Zugang aktivieren' ?>"><i class="bx <?= $c['portal_enabled'] ? 'bx-user-x' : 'bx-user-check' ?>"></i></button></form><?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>
