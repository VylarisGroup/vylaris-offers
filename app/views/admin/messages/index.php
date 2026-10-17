<?php
$title = 'Nachrichten';
$subtitle = 'Nachrichten Ihrer Kunden aus dem Kundenportal';
$actions = '';
include APP_DIR . '/views/partials/page-header.php';
?>
<div class="reveal card">
  <?php if (!$rows): ?>
    <div class="empty-state"><i class="bx bx-message-square-dots"></i><p class="font-semibold text-slate-900">Noch keine Nachrichten</p><p class="mt-1 text-sm text-slate-500">Schreibt ein Kunde im Kundenportal, erscheint die Unterhaltung hier und Sie erhalten eine E-Mail.</p></div>
  <?php else: ?>
  <ul class="divide-y divide-slate-100">
    <?php foreach ($rows as $r): ?>
    <li><a href="<?= e(url('admin/messages/' . $r['id'])) ?>" class="group flex items-center gap-4 px-5 py-4 transition hover:bg-slate-50 sm:px-6">
      <span class="grid size-10 shrink-0 place-items-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700"><?= e(mb_strtoupper(mb_substr(customer_name($r), 0, 1))) ?></span>
      <div class="min-w-0 flex-1">
        <p class="truncate font-medium text-slate-900"><?= e(customer_name($r)) ?><?php if ((int) $r['unread'] > 0): ?> <span class="badge badge-brand ml-1"><?= (int) $r['unread'] ?> neu</span><?php endif; ?></p>
        <p class="truncate text-sm <?= (int) $r['unread'] > 0 ? 'font-medium text-slate-700' : 'text-slate-500' ?>"><?= $r['last_direction'] === 'out' ? '<span class="text-slate-400">Sie: </span>' : '' ?><?= e(mb_strimwidth(str_replace("\n", ' ', (string) $r['last_body']), 0, 110, '…')) ?></p>
      </div>
      <span class="shrink-0 text-xs text-slate-400"><?= fmt_datetime($r['last_at']) ?></span>
      <i class="bx bx-chevron-right hidden text-xl text-slate-300 group-hover:text-slate-500 sm:block"></i>
    </a></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>
