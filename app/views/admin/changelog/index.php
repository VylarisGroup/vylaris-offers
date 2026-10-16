<?php
$title = 'Änderungsprotokoll';
$subtitle = 'Alle Neuerungen von VylarisOffers. Installiert ist Version <strong>' . e(APP_VERSION) . '</strong>.';
include APP_DIR . '/views/partials/page-header.php';
?>
<div class="reveal card max-w-3xl p-5 sm:p-6">
  <?php if (!$entries): ?>
    <div class="empty-state"><i class="bx bx-gift"></i><p class="text-sm text-slate-500">Noch kein Änderungsprotokoll vorhanden.</p></div>
  <?php else: include APP_DIR . '/views/partials/changelog-entries.php'; endif; ?>
</div>
