<?php $title = 'Link ungültig'; ?>
<div class="reveal mx-auto max-w-md pt-6">
  <div class="card p-8 text-center">
    <span class="mx-auto grid size-16 place-items-center rounded-2xl bg-amber-50 text-4xl text-amber-600"><i class="bx bx-time-five"></i></span>
    <h1 class="mt-5 text-xl font-bold text-slate-900">Link abgelaufen oder bereits verwendet</h1>
    <p class="mt-2 text-sm leading-relaxed text-slate-600">Anmeldelinks sind aus Sicherheitsgründen nur kurz gültig und können nur einmal verwendet werden. Bitte fordern Sie einen neuen Link an.</p>
    <a href="<?= e(url($back)) ?>" class="btn btn-primary mt-6"><i class="bx bx-refresh"></i>Neuen Link anfordern</a>
  </div>
</div>
