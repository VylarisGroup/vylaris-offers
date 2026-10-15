<?php
$title = 'E-Mail-Protokoll';
$subtitle = 'Die letzten 200 versendeten oder fehlgeschlagenen E-Mails.';
$actions = '<a href="' . e(url('admin/settings?tab=mail')) . '" class="btn btn-secondary"><i class="bx bx-cog"></i>E-Mail-Einstellungen</a>';
include APP_DIR . '/views/partials/page-header.php';
$contexts = ['offer_sent' => 'Offerte', 'offer_accepted_customer' => 'Auftragsbestätigung', 'offer_accepted_team' => 'Team: Annahme', 'offer_declined_team' => 'Team: Ablehnung', 'order_update' => 'Auftragsupdate', 'portal_link' => 'Portal-Link', 'staff_link' => 'Admin-Link', 'test' => 'Test'];
?>
<div class="reveal card overflow-hidden">
  <?php if (!$logs): ?>
    <div class="empty-state"><i class="bx bx-envelope"></i><p class="font-semibold text-slate-900">Noch keine E-Mails versendet</p></div>
  <?php else: ?>
  <div class="overflow-x-auto"><table class="table">
    <thead><tr><th>Zeitpunkt</th><th>Empfänger</th><th class="hidden md:table-cell">Betreff</th><th class="hidden sm:table-cell">Art</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $l): ?>
      <tr>
        <td class="whitespace-nowrap text-slate-500"><?= fmt_datetime($l['created_at']) ?></td>
        <td class="max-w-[200px] truncate"><?= e($l['recipient']) ?></td>
        <td class="hidden max-w-xs truncate md:table-cell"><?= e($l['subject']) ?></td>
        <td class="hidden sm:table-cell text-xs text-slate-500"><?= e($contexts[$l['context']] ?? $l['context']) ?></td>
        <td><?php if ($l['status'] === 'sent'): ?><span class="badge badge-emerald"><i class="bx bx-check"></i>gesendet</span><?php else: ?><span class="badge badge-rose" data-tip="<?= e(mb_substr((string) $l['error'], 0, 90)) ?>"><i class="bx bx-error"></i>fehlgeschlagen</span>
          <p class="mt-1 max-w-xs text-xs text-rose-600"><?= e(mb_substr((string) $l['error'], 0, 200)) ?></p><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
