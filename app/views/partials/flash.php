<?php $msgs = flashes(); ?>
<div id="toasts" class="pointer-events-none fixed inset-x-0 top-0 z-[80] flex flex-col items-center gap-2 p-4 sm:items-end sm:p-6" aria-live="polite">
<?php foreach ($msgs as $f):
    [$icon, $cls] = match ($f['type']) {
        'success' => ['bx-check-circle', 'text-emerald-600 bg-emerald-50'],
        'error'   => ['bx-error-circle', 'text-rose-600 bg-rose-50'],
        'warning' => ['bx-error', 'text-amber-600 bg-amber-50'],
        default   => ['bx-info-circle', 'text-sky-600 bg-sky-50'],
    }; ?>
  <div class="toast pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl bg-white p-4 shadow-lift ring-1 ring-slate-200" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>" data-timeout="<?= $f['type'] === 'error' ? 9000 : 5000 ?>">
    <span class="grid size-8 shrink-0 place-items-center rounded-lg <?= $cls ?>"><i class="bx <?= $icon ?> text-xl"></i></span>
    <p class="flex-1 pt-1 text-sm leading-relaxed text-slate-700"><?= e($f['message']) ?></p>
    <button type="button" class="-mr-1 -mt-1 grid size-8 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" data-toast-close aria-label="Schliessen"><i class="bx bx-x text-xl"></i></button>
  </div>
<?php endforeach; ?>
</div>
