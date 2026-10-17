<?php
$title = 'Meine Angaben';
$v = static fn(string $k) => e((string) ($old[$k] ?? ''));
?>
<div class="reveal pt-4">
  <p class="text-sm font-medium text-brand-700">Kundenportal</p>
  <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Meine Angaben</h1>
  <p class="mt-1 text-sm text-slate-500">Halten Sie Ihre Kontakt- und Rechnungsadresse aktuell. Änderungen gelten für künftige Offerten und Rechnungen; bereits erstellte Dokumente bleiben unverändert.</p>
</div>
<?php if ($errors): ?><div class="reveal mt-6 flex gap-3 rounded-2xl bg-rose-50 p-4 text-sm text-rose-800 ring-1 ring-rose-200" role="alert"><i class="bx bx-error-circle text-xl"></i><div><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div></div><?php endif; ?>
<form method="post" action="<?= e(url('portal/profile')) ?>" class="reveal mt-6 card max-w-3xl"><?= csrf_field() ?>
  <div class="grid gap-4 p-5 sm:grid-cols-6 sm:p-6">
    <div class="sm:col-span-2"><label class="label" for="salutation">Anrede</label>
      <select class="input" id="salutation" name="salutation"><?php foreach (['' => '–', 'Herr' => 'Herr', 'Frau' => 'Frau', 'Divers' => 'Divers', 'Firma' => 'Firma'] as $k => $l): ?><option value="<?= $k ?>"<?= ($old['salutation'] ?? '') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
    <div class="sm:col-span-2"><label class="label" for="first_name">Vorname</label><input class="input" id="first_name" name="first_name" value="<?= $v('first_name') ?>" maxlength="100" autocomplete="given-name"></div>
    <div class="sm:col-span-2"><label class="label" for="last_name">Nachname</label><input class="input" id="last_name" name="last_name" value="<?= $v('last_name') ?>" maxlength="100" autocomplete="family-name"></div>
    <div class="sm:col-span-6"><label class="label" for="company">Firma <span class="font-normal text-slate-400">(optional)</span></label><input class="input" id="company" name="company" value="<?= $v('company') ?>" maxlength="190" autocomplete="organization"></div>
    <div class="sm:col-span-4"><label class="label" for="street">Strasse und Nr. <span class="text-rose-500">*</span></label><input class="input" id="street" name="street" value="<?= $v('street') ?>" maxlength="190" autocomplete="address-line1" required></div>
    <div class="sm:col-span-2"><label class="label" for="address_extra">Zusatz</label><input class="input" id="address_extra" name="address_extra" value="<?= $v('address_extra') ?>" maxlength="190" autocomplete="address-line2"></div>
    <div class="sm:col-span-2"><label class="label" for="zip">PLZ <span class="text-rose-500">*</span></label><input class="input" id="zip" name="zip" value="<?= $v('zip') ?>" maxlength="20" autocomplete="postal-code" required></div>
    <div class="sm:col-span-4"><label class="label" for="city">Ort <span class="text-rose-500">*</span></label><input class="input" id="city" name="city" value="<?= $v('city') ?>" maxlength="120" autocomplete="address-level2" required></div>
    <div class="sm:col-span-6"><label class="label" for="country">Land</label>
      <select class="input" id="country" name="country"><?php foreach (countries() as $code => $name): ?><option value="<?= $code ?>"<?= ($old['country'] ?? 'CH') === $code ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select></div>
    <div class="sm:col-span-3"><label class="label" for="phone">Telefon</label><input class="input" id="phone" name="phone" type="tel" value="<?= $v('phone') ?>" maxlength="50" autocomplete="tel"></div>
    <div class="sm:col-span-3"><label class="label" for="mobile">Mobile</label><input class="input" id="mobile" name="mobile" type="tel" value="<?= $v('mobile') ?>" maxlength="50" autocomplete="tel"></div>
    <div class="sm:col-span-6 rounded-xl bg-slate-50 p-4 text-sm text-slate-600 ring-1 ring-slate-200/70"><i class="bx bx-lock-alt align-middle text-slate-400"></i> Ihre E-Mail-Adresse (<strong><?= e($c['email']) ?></strong>) dient der Anmeldung und kann nur von uns geändert werden – schreiben Sie uns dazu eine <a class="link" href="<?= e(url('portal/messages')) ?>">Nachricht</a>. Gleiches gilt für Steuerangaben wie die MWST-Nr.</div>
  </div>
  <div class="flex justify-end rounded-b-2xl border-t border-slate-100 bg-slate-50/50 px-5 py-4 sm:px-6"><button class="btn btn-primary"><i class="bx bx-save"></i>Speichern</button></div>
</form>
