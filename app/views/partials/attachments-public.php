<?php
/** Kundenansicht der freigegebenen Anhänge. @var array $files  @var string $filesBase (URL-Präfix, danach /{id}) */
if (!$files) {
    return;
}
?>
<section class="card no-print mt-6 p-5 sm:p-6">
  <h2 class="text-sm font-semibold text-slate-900"><i class="bx bx-paperclip align-middle text-slate-400"></i> Anhänge</h2>
  <ul class="mt-3 divide-y divide-slate-100">
    <?php foreach ($files as $a): ?>
      <li><a href="<?= e($filesBase . '/' . $a['id']) ?>" class="group flex items-center gap-3 py-2.5">
        <i class="bx bx-file text-xl text-slate-400 transition group-hover:text-brand-600"></i>
        <span class="min-w-0 flex-1 truncate text-sm font-medium text-slate-900 group-hover:text-brand-700"><?= e($a['original_name']) ?></span>
        <span class="text-xs text-slate-400"><?= e(AttachmentService::human((int) $a['size'])) ?></span>
        <i class="bx bx-download text-lg text-slate-400"></i></a></li>
    <?php endforeach; ?>
  </ul>
</section>
