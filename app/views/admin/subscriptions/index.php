<?php
$title = 'Abos';
$subtitle = 'Wiederkehrende Rechnungen aus Aufträgen. Neue Abos richten Sie auf der Auftragsseite ein.';
include APP_DIR . '/views/partials/page-header.php';
$st = ['active' => ['Aktiv', 'emerald', 'bx-sync'], 'paused' => ['Pausiert', 'amber', 'bx-pause-circle'], 'ended' => ['Beendet', 'slate', 'bx-stop-circle']];
$act = static fn(array $s, string $a) => e(url('admin/subscriptions/' . $s['id'] . '/' . $a));
?>
<div class="reveal card overflow-hidden">
  <?php if (!$subs): ?>
    <div class="empty-state"><i class="bx bx-sync"></i><p class="font-semibold text-slate-900">Noch keine Abos</p><p class="mt-1 text-sm text-slate-500">Öffnen Sie einen Auftrag und wählen Sie „Abo einrichten“.</p></div>
  <?php else: ?>
  <div class="overflow-x-auto"><table class="table">
    <thead><tr><th>Abo</th><th class="hidden md:table-cell">Kunde</th><th>Intervall</th><th>Nächste Rechnung</th><th>Status</th><th class="text-right">Betrag</th><th class="w-40"><span class="sr-only">Aktionen</span></th></tr></thead>
    <tbody>
    <?php foreach ($subs as $s): [$sl, $sc, $si] = $st[$s['status']]; ?>
      <tr>
        <td><a class="font-medium text-slate-900 hover:text-brand-700" href="<?= e(url('admin/orders/' . $s['order_id'])) ?>"><?= e($s['title']) ?></a>
          <p class="text-xs text-slate-500"><?= e($s['order_number']) ?> · <?= (int) $s['runs'] ?> Rechnung(en)<?= $s['auto_send'] ? ' · automatischer Versand' : ' · als Entwurf' ?></p></td>
        <td class="hidden md:table-cell"><?= e(customer_name($s)) ?></td>
        <td><?= e(SubscriptionService::PERIODS[$s['period']][0]) ?></td>
        <td><?= $s['status'] === 'ended' ? '–' : fmt_date($s['next_run']) ?><?= $s['end_date'] ? '<p class="text-xs text-slate-400">bis ' . fmt_date($s['end_date']) . '</p>' : '' ?></td>
        <td><span class="badge badge-<?= $sc ?>"><i class="bx <?= $si ?>"></i><?= $sl ?></span></td>
        <td class="text-right font-semibold tabular"><?= money($s['total'], $s['currency']) ?></td>
        <td><div class="flex justify-end gap-1">
          <?php if ($s['status'] === 'active'): ?>
            <form method="post" action="<?= $act($s, 'run') ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-icon" data-tip="Fällige Rechnung jetzt erzeugen"><i class="bx bx-play-circle text-lg"></i></button></form>
            <form method="post" action="<?= $act($s, 'pause') ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-icon" data-tip="Pausieren"><i class="bx bx-pause text-lg"></i></button></form>
          <?php elseif ($s['status'] === 'paused'): ?>
            <form method="post" action="<?= $act($s, 'resume') ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-icon" data-tip="Fortsetzen"><i class="bx bx-play text-lg"></i></button></form>
          <?php endif; ?>
          <?php if ($s['status'] !== 'ended'): ?>
            <form method="post" action="<?= $act($s, 'autosend') ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-icon" data-tip="<?= $s['auto_send'] ? 'Auf „Entwurf“ umstellen' : 'Automatisch versenden' ?>"><i class="bx <?= $s['auto_send'] ? 'bx-envelope' : 'bx-edit-alt' ?> text-lg"></i></button></form>
            <form method="post" action="<?= $act($s, 'end') ?>" data-confirm="Abo „<?= e($s['title']) ?>“ endgültig beenden?" data-confirm-label="Beenden"><?= csrf_field() ?><button class="btn btn-ghost btn-icon text-slate-400 hover:text-rose-600" data-tip="Beenden"><i class="bx bx-stop-circle text-lg"></i></button></form>
          <?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
