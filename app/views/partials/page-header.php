<?php
/** @var string $title  @var ?string $subtitle  @var ?string $actions  @var ?array $back [url,label] */
?>
<div class="reveal mb-6 flex flex-col gap-4 sm:mb-8 md:flex-row md:items-end md:justify-between">
  <div class="min-w-0">
    <?php if (!empty($back)): ?>
      <a href="<?= e(url($back[0])) ?>" class="group mb-2 inline-flex items-center gap-1 text-sm font-medium text-slate-500 transition hover:text-slate-800">
        <i class="bx bx-chevron-left text-lg transition-transform group-hover:-translate-x-0.5"></i><?= e($back[1]) ?>
      </a>
    <?php endif; ?>
    <h1 class="truncate text-2xl font-bold tracking-tight text-slate-900 sm:text-[28px]"><?= $titleHtml ?? e($title) ?></h1>
    <?php if (!empty($subtitle)): ?><p class="mt-1.5 text-sm text-slate-500"><?= $subtitle ?></p><?php endif; ?>
  </div>
  <?php if (!empty($actions)): ?><div class="flex flex-wrap items-center gap-2"><?= $actions ?></div><?php endif; ?>
</div>
