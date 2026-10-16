<?php
/** @var array $entries  Liste aus Changelog::parse() */
$tones = ['emerald' => 'bg-emerald-50 text-emerald-600', 'sky' => 'bg-sky-50 text-sky-600', 'amber' => 'bg-amber-50 text-amber-600', 'rose' => 'bg-rose-50 text-rose-600', 'slate' => 'bg-slate-100 text-slate-500'];
foreach ($entries as $i => $en): ?>
  <section class="<?= $i > 0 ? 'mt-8 border-t border-slate-100 pt-6' : '' ?>">
    <div class="flex items-baseline gap-3">
      <h3 class="text-base font-semibold text-slate-900">Version <?= e($en['version']) ?></h3>
      <?php if ($en['date'] !== ''): ?><span class="text-xs text-slate-400"><?= e($en['date']) ?></span><?php endif; ?>
      <?php if ($en['version'] === APP_VERSION): ?><span class="badge badge-brand">Installiert</span><?php endif; ?>
    </div>
    <?php foreach ($en['sections'] as $title => $items): [$icon, $tone] = Changelog::style((string) $title); ?>
      <div class="mt-4">
        <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
          <span class="grid size-6 place-items-center rounded-lg <?= $tones[$tone] ?>"><i class="bx <?= e($icon) ?> text-sm"></i></span><?= e((string) $title) ?>
        </p>
        <ul class="mt-2 space-y-1.5 pl-8 text-sm leading-relaxed text-slate-600">
          <?php foreach ($items as $it): ?><li class="list-disc marker:text-slate-300"><?= Changelog::inline($it) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
  </section>
<?php endforeach; ?>
