<?php
/** MWST-Plausibilitätsprüfung (Hinweise zu Steuerart, Kundenland, Pflichtangaben). @var array $review */
if (empty($review)) {
    return;
}
?>
<div class="space-y-2">
  <?php foreach ($review as $rv):
    $cls = ['error' => 'bg-rose-50 text-rose-900 ring-rose-200', 'warn' => 'bg-amber-50 text-amber-900 ring-amber-200', 'info' => 'bg-sky-50 text-sky-900 ring-sky-200'][$rv['level']] ?? '';
    $ic = ['error' => 'bx-error-circle', 'warn' => 'bx-error', 'info' => 'bx-info-circle'][$rv['level']] ?? 'bx-info-circle'; ?>
    <div class="flex gap-3 rounded-2xl p-4 text-sm ring-1 <?= $cls ?>"><i class="bx <?= $ic ?> text-xl"></i><p><strong>MWST-Prüfung:</strong> <?= e($rv['text']) ?></p></div>
  <?php endforeach; ?>
</div>
