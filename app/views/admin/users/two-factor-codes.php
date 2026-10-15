<?php
$title = 'Wiederherstellungscodes';
$subtitle = 'Die Zwei-Faktor-Anmeldung ist aktiv.';
include APP_DIR . '/views/partials/page-header.php';
?>
<div class="reveal card max-w-xl p-5 sm:p-6">
  <p class="flex gap-2 rounded-xl bg-amber-50 p-3 text-sm text-amber-900 ring-1 ring-amber-200"><i class="bx bx-error mt-0.5 text-lg"></i><span><strong>Jetzt speichern – diese Codes werden nur einmal angezeigt.</strong> Jeder Code funktioniert genau einmal, falls Sie keinen Zugriff auf Ihre App haben.</span></p>
  <ul class="mt-5 grid grid-cols-2 gap-2 font-mono text-sm">
    <?php foreach ($codes as $c): ?><li class="rounded-lg bg-slate-50 px-3 py-2 text-center tracking-wider text-slate-900 ring-1 ring-slate-200"><?= e($c) ?></li><?php endforeach; ?>
  </ul>
  <div class="mt-5 flex gap-2">
    <button type="button" class="btn btn-secondary" data-copy="<?= e(implode("\n", $codes)) ?>"><i class="bx bx-copy"></i>Kopieren</button>
    <button type="button" class="btn btn-secondary" data-print><i class="bx bx-printer"></i>Drucken</button>
    <a href="<?= e(url('admin/profile')) ?>" class="btn btn-primary ml-auto">Fertig</a>
  </div>
</div>
