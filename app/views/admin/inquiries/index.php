<?php
$title = 'Anfragen';
$subtitle = 'Anfragen über das Formular auf Ihrer Startseite';
$actions = '';
include APP_DIR . '/views/partials/page-header.php';
$tabs = ['' => 'Alle', 'new' => 'Neu', 'handled' => 'Erledigt', 'spam' => 'Spam'];
?>
<div class="reveal card">
  <div class="flex flex-wrap gap-1 border-b border-slate-100 p-3">
    <?php foreach ($tabs as $k => $l): ?>
      <a href="<?= e(url('admin/inquiries' . ($k !== '' ? '?status=' . $k : ''))) ?>" class="tab<?= $status === $k ? ' is-active' : '' ?>"><?= e($l) ?> <span class="text-xs text-slate-400"><?= (int) ($counts[$k] ?? 0) ?></span></a>
    <?php endforeach; ?>
  </div>
  <?php if (!$rows): ?>
    <div class="empty-state"><i class="bx bx-envelope-open"></i><p class="font-semibold text-slate-900">Keine Anfragen</p><p class="mt-1 text-sm text-slate-500">Neue Anfragen erscheinen hier, sobald jemand das Formular auf Ihrer Startseite absendet.</p></div>
  <?php else: ?>
  <ul class="divide-y divide-slate-100">
    <?php foreach ($rows as $q): [$sl, $sc] = InquiryService::STATUS[$q['status']]; ?>
    <li class="px-5 py-4 sm:px-6">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
          <p class="font-medium text-slate-900"><?= e($q['name']) ?><?= $q['company'] ? ' <span class="font-normal text-slate-500">· ' . e($q['company']) . '</span>' : '' ?> <span class="badge badge-<?= $sc === 'sky' ? 'brand' : ($sc === 'emerald' ? 'emerald' : '') ?> ml-1"><?= e($sl) ?></span></p>
          <p class="text-xs text-slate-500"><a class="link" href="mailto:<?= e($q['email']) ?>"><?= e($q['email']) ?></a><?= $q['phone'] ? ' · ' . e($q['phone']) : '' ?> · <?= fmt_datetime($q['created_at']) ?></p>
        </div>
        <div class="flex flex-wrap gap-2">
          <?php if ($q['status'] !== 'spam' && $q['customer_id']): ?>
            <?php if ($q['offer_id']): ?><a class="btn btn-secondary btn-sm" href="<?= e(url('admin/offers/' . $q['offer_id'])) ?>"><i class="bx bx-file"></i><?= e($q['offer_number']) ?></a>
            <?php else: ?><a class="btn btn-primary btn-sm" href="<?= e(url('admin/offers/new?customer=' . $q['customer_id'] . '&inquiry=' . $q['id'])) ?>"><i class="bx bx-plus"></i>Offerte erstellen</a><?php endif; ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/customers/' . $q['customer_id'])) ?>"><i class="bx bx-user"></i>Kunde</a>
          <?php endif; ?>
          <?php if ($q['status'] === 'new'): ?>
            <form method="post" action="<?= e(url('admin/inquiries/' . $q['id'] . '/status/handled')) ?>"><?= csrf_field() ?><button class="btn btn-secondary btn-sm"><i class="bx bx-check"></i>Erledigt</button></form>
            <form method="post" action="<?= e(url('admin/inquiries/' . $q['id'] . '/status/spam')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm"><i class="bx bx-block"></i>Spam</button></form>
          <?php else: ?>
            <form method="post" action="<?= e(url('admin/inquiries/' . $q['id'] . '/status/new')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm"><i class="bx bx-undo"></i>Wieder öffnen</button></form>
          <?php endif; ?>
          <form method="post" action="<?= e(url('admin/inquiries/' . $q['id'] . '/delete')) ?>" data-confirm="Anfrage endgültig löschen?" data-confirm-label="Löschen"><?= csrf_field() ?><button class="btn btn-ghost btn-sm text-rose-600" data-tip="Löschen"><i class="bx bx-trash"></i></button></form>
        </div>
      </div>
      <?php if ($q['subject']): ?><p class="mt-2 text-sm font-semibold text-slate-800"><?= e($q['subject']) ?></p><?php endif; ?>
      <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-slate-600"><?= e($q['message']) ?></p>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>
