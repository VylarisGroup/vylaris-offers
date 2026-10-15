<?php
/** Fortschrittsanzeige eines Auftrags. @var array $order */
$steps = ['new' => 'Eingegangen', 'in_progress' => 'In Bearbeitung', 'fulfilled' => $order['area_type'] === 'digital' ? 'Bereitgestellt' : 'Versendet', 'completed' => 'Abgeschlossen'];
$keys = array_keys($steps);
$idx = array_search($order['status'], $keys, true);
$cancelled = $order['status'] === 'cancelled';
?>
<?php if ($cancelled): ?>
  <div class="flex items-center gap-3 rounded-xl bg-rose-50 p-4 text-sm text-rose-800 ring-1 ring-rose-200"><i class="bx bx-block text-xl"></i>Dieser Auftrag wurde storniert.</div>
<?php else: ?>
<ol class="grid grid-cols-4 gap-2">
  <?php foreach ($steps as $k => $label):
    $i = array_search($k, $keys, true);
    $done = $i < $idx || $order['status'] === 'completed';
    $current = $i === $idx && $order['status'] !== 'completed'; ?>
    <li class="flex flex-col gap-2">
      <div class="h-1.5 overflow-hidden rounded-full bg-slate-200">
        <div class="h-full rounded-full bg-brand-600 transition-all duration-700" style="width: <?= $done ? '100' : ($current ? '50' : '0') ?>%"></div>
      </div>
      <div class="flex items-center gap-1.5 text-xs font-medium <?= $done || $current ? 'text-slate-900' : 'text-slate-400' ?>">
        <?php if ($done): ?><i class="bx bxs-check-circle text-base text-brand-600"></i><?php elseif ($current): ?><span class="pulse-dot ml-1 mr-0.5 size-2 rounded-full bg-brand-600 text-brand-600"></span><?php endif; ?>
        <span class="truncate"><?= e($label) ?></span>
      </div>
    </li>
  <?php endforeach; ?>
</ol>
<?php endif; ?>
