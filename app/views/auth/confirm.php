<?php $title = 'Anmeldung bestätigen'; ?>
<div class="reveal mx-auto max-w-md pt-6">
  <div class="card p-8 text-center">
    <span class="mx-auto grid size-16 place-items-center rounded-2xl bg-emerald-50 text-4xl text-emerald-600"><i class="bx bx-shield-quarter"></i></span>
    <h1 class="mt-5 text-xl font-bold text-slate-900">Anmeldung bestätigen</h1>
    <p class="mt-2 text-sm leading-relaxed text-slate-600">Klicken Sie auf den Button, um sich beim <?= e($area) ?> anzumelden. Der Link kann nur einmal verwendet werden.</p>
    <form method="post" action="<?= e(url($action)) ?>" class="mt-6"><?= csrf_field() ?>
      <button class="btn btn-primary btn-lg w-full"><i class="bx bx-log-in"></i>Jetzt anmelden</button>
    </form>
  </div>
</div>
