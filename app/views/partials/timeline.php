<?php
/** @var array $history  @var ?string $areaType  @var bool $showInternal */
$showInternal = $showInternal ?? false;
?>
<?php if (!$history): ?>
  <p class="text-sm text-slate-500">Noch keine Einträge.</p>
<?php else: ?>
<ol class="relative space-y-5 before:absolute before:bottom-2 before:left-[15px] before:top-2 before:w-px before:bg-slate-200">
  <?php foreach ($history as $h):
    [$label, $icon, $color] = History::describe($h, $areaType ?? null);
    $bg = $color === 'brand' ? 'bg-brand-50 text-brand-600' : "bg-$color-50 text-$color-600"; ?>
    <li class="relative flex gap-3.5">
      <span class="relative z-10 grid size-8 shrink-0 place-items-center rounded-full <?= $bg ?> ring-4 ring-white"><i class="bx <?= e($icon) ?> text-base"></i></span>
      <div class="min-w-0 flex-1 pt-1">
        <div class="flex flex-wrap items-baseline justify-between gap-x-3">
          <p class="text-sm font-medium text-slate-900"><?= e($label) ?>
            <?php if ($showInternal && !$h['customer_visible']): ?><span class="badge badge-slate ml-1" data-tip="Für den Kunden nicht sichtbar"><i class="bx bx-hide"></i>intern</span><?php endif; ?>
          </p>
          <time class="text-xs text-slate-400" datetime="<?= e($h['created_at']) ?>" title="<?= e(fmt_datetime($h['created_at'])) ?>"><?= fmt_datetime($h['created_at']) ?></time>
        </div>
        <?php if ($h['note']): ?><p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-slate-600"><?= e($h['note']) ?></p><?php endif; ?>
        <?php if ($showInternal && $h['actor_name']): ?><p class="mt-0.5 text-xs text-slate-400"><?= $h['actor_type'] === 'customer' ? 'Kunde' : '' ?> <?= e($h['actor_name']) ?></p><?php endif; ?>
      </div>
    </li>
  <?php endforeach; ?>
</ol>
<?php endif; ?>
