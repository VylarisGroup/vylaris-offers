<?php $title = $title ?? 'Fehler'; ?>
<div class="reveal w-full max-w-lg text-center">
  <span class="mx-auto grid size-16 place-items-center rounded-2xl <?= $status >= 500 ? 'bg-rose-50 text-rose-600' : 'bg-amber-50 text-amber-600' ?> text-4xl">
    <i class="bx <?= $status === 404 ? 'bx-search-alt' : ($status >= 500 ? 'bx-error-alt' : 'bx-lock-alt') ?>"></i>
  </span>
  <p class="mt-6 text-sm font-semibold text-slate-400">Fehler <?= (int) $status ?></p>
  <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900"><?= e($title) ?></h1>
  <p class="mt-3 text-[15px] leading-relaxed text-slate-600"><?= e($message) ?></p>
  <?php if (!empty($ref)): ?><p class="mt-3 text-sm text-slate-500">Fehlernummer: <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-slate-700"><?= e($ref) ?></code></p><?php endif; ?>
  <div class="mt-8 flex flex-wrap justify-center gap-3">
    <a href="javascript:history.back()" class="btn btn-secondary"><i class="bx bx-arrow-back"></i>Zurück</a>
    <?php if ($status === 419): ?><a href="" class="btn btn-primary"><i class="bx bx-refresh"></i>Neu laden</a><?php endif; ?>
  </div>
  <?php if (!empty($debug)): ?>
    <pre class="mt-8 max-h-96 overflow-auto rounded-xl bg-slate-900 p-4 text-left text-xs leading-relaxed text-slate-100"><?= e($debug::class . ': ' . $debug->getMessage() . "\n" . $debug->getFile() . ':' . $debug->getLine() . "\n\n" . $debug->getTraceAsString()) ?></pre>
  <?php endif; ?>
</div>
