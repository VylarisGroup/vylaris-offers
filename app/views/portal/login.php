<?php $title = 'Kundenportal'; ?>
<div class="mx-auto grid max-w-5xl items-center gap-10 pt-6 sm:pt-12 lg:grid-cols-2">
  <div class="reveal">
    <span class="badge badge-brand"><i class="bx bx-lock-alt"></i>Sicherer Zugang ohne Passwort</span>
    <h1 class="mt-4 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">Ihre Offerten und Aufträge auf einen Blick.</h1>
    <p class="mt-4 max-w-md text-base leading-relaxed text-slate-600">Im Kundenportal sehen Sie alle Offerten von <?= e(Settings::get('company_name')) ?>, den Bearbeitungsstand Ihrer Aufträge und – sobald verfügbar – Versand- und Lieferinformationen.</p>
    <ul class="mt-6 space-y-3 text-sm text-slate-700">
      <?php foreach ([['bx-file', 'Offerten ansehen, annehmen oder ablehnen'], ['bx-package', 'Auftragsstatus in Echtzeit verfolgen'], ['bx-map-pin', 'Sendungsverfolgung und digitale Lieferungen']] as [$i, $t]): ?>
        <li class="flex items-center gap-3"><span class="grid size-8 place-items-center rounded-lg bg-white text-lg text-brand-600 shadow-sm ring-1 ring-slate-200"><i class="bx <?= $i ?>"></i></span><?= $t ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <div class="reveal reveal-delay-1 card p-6 sm:p-8">
    <h2 class="text-lg font-semibold text-slate-900">Anmelden</h2>
    <p class="mt-1 text-sm text-slate-500">Geben Sie die E-Mail-Adresse ein, die bei uns hinterlegt ist. Wir senden Ihnen einen Anmeldelink.</p>
    <?php if (!empty($error)): ?>
      <div class="mt-4 flex gap-2.5 rounded-xl bg-rose-50 p-3.5 text-sm text-rose-700 ring-1 ring-rose-200" role="alert"><i class="bx bx-error-circle mt-0.5 text-lg"></i><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= e(url('portal/login')) ?>" class="mt-6 space-y-4"><?= csrf_field() ?>
      <div>
        <label class="label" for="email">E-Mail-Adresse</label>
        <div class="relative">
          <i class="bx bx-envelope pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-lg text-slate-400"></i>
          <input class="input pl-10" type="email" id="email" name="email" value="<?= e($email ?? '') ?>" placeholder="name@firma.ch" autocomplete="email" required autofocus>
        </div>
      </div>
      <button class="btn btn-primary w-full"><i class="bx bx-send"></i>Anmeldelink anfordern</button>
    </form>
  </div>
</div>
