<?php $title = 'E-Mail gesendet'; ?>
<div class="reveal mx-auto max-w-md <?= Auth::customer() === null && str_starts_with($back, 'portal') ? 'pt-10' : '' ?>">
  <div class="card p-8 text-center">
    <span class="mx-auto grid size-16 place-items-center rounded-2xl bg-brand-50 text-4xl text-brand-600"><i class="bx bx-envelope-open"></i></span>
    <h1 class="mt-5 text-xl font-bold text-slate-900">Prüfen Sie Ihr Postfach</h1>
    <p class="mt-2 text-sm leading-relaxed text-slate-600">Falls zu <strong class="text-slate-900"><?= e($email) ?></strong> ein Konto existiert, haben wir soeben einen Anmeldelink gesendet. Er ist 30 Minuten gültig.</p>
    <p class="mt-4 text-xs text-slate-500">Keine E-Mail erhalten? Prüfen Sie den Spam-Ordner oder fordern Sie in einigen Minuten einen neuen Link an.</p>
    <a href="<?= e(url($back)) ?>" class="btn btn-secondary mt-6"><i class="bx bx-arrow-back"></i>Zurück</a>
  </div>
</div>
