<?php
$isNew = empty($customer['id']);
$title = $isNew ? 'Neuer Kunde' : 'Kunde bearbeiten';
$subtitle = $isNew ? 'Erfassen Sie Stammdaten und Adresse.' : e(customer_name($customer)) . ' · ' . e($customer['customer_no']);
$back = $isNew ? ['admin/customers', 'Kunden'] : ['admin/customers/' . $customer['id'], 'Zurück zum Kunden'];
include APP_DIR . '/views/partials/page-header.php';
$v = static fn(string $k) => e($customer[$k] ?? '');
$err = static fn(string $k) => isset($errors[$k]) ? '<p class="error-text"><i class="bx bx-error-circle"></i>' . e($errors[$k]) . '</p>' : '';
$cls = static fn(string $k) => isset($errors[$k]) ? ' input-error' : '';
?>
<?php if ($errors): ?>
  <div class="reveal mb-6 flex gap-3 rounded-2xl bg-rose-50 p-4 text-sm text-rose-800 ring-1 ring-rose-200" role="alert"><i class="bx bx-error-circle text-xl"></i><div><p class="font-semibold">Bitte prüfen Sie die Eingaben.</p><p><?= count($errors) ?> Feld(er) benötigen Ihre Aufmerksamkeit.</p></div></div>
<?php endif; ?>
<form method="post" action="<?= e(url($isNew ? 'admin/customers' : 'admin/customers/' . $customer['id'])) ?>" class="grid gap-6 lg:grid-cols-3" novalidate>
  <?= csrf_field() ?>
  <div class="space-y-6 lg:col-span-2">
    <section class="reveal card">
      <div class="card-header"><h2 class="card-title"><i class="bx bx-id-card"></i>Kontakt</h2></div>
      <div class="grid gap-4 p-5 sm:grid-cols-6 sm:p-6">
        <div class="sm:col-span-2"><label class="label" for="salutation">Anrede</label>
          <select class="input" id="salutation" name="salutation">
            <?php foreach (['' => '–', 'Herr' => 'Herr', 'Frau' => 'Frau', 'Divers' => 'Divers', 'Firma' => 'Firma'] as $k => $l): ?><option value="<?= $k ?>"<?= ($customer['salutation'] ?? '') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
          </select></div>
        <div class="sm:col-span-2"><label class="label" for="first_name">Vorname</label><input class="input<?= $cls('last_name') ?>" id="first_name" name="first_name" value="<?= $v('first_name') ?>" autocomplete="off"></div>
        <div class="sm:col-span-2"><label class="label" for="last_name">Nachname</label><input class="input<?= $cls('last_name') ?>" id="last_name" name="last_name" value="<?= $v('last_name') ?>" autocomplete="off"></div>
        <div class="sm:col-span-6 -mt-2"><?= $err('last_name') ?></div>
        <div class="sm:col-span-6"><label class="label" for="company">Firma <span class="font-normal text-slate-400">(optional)</span></label><input class="input" id="company" name="company" value="<?= $v('company') ?>"></div>
        <div class="sm:col-span-3"><label class="label" for="email">E-Mail <span class="text-rose-500">*</span></label><input class="input<?= $cls('email') ?>" type="email" id="email" name="email" value="<?= $v('email') ?>" required><?= $err('email') ?><p class="hint">Wird auch für die Anmeldung im Kundenportal verwendet.</p></div>
        <div class="sm:col-span-3"><label class="label" for="phone">Telefon</label><input class="input" type="tel" id="phone" name="phone" value="<?= $v('phone') ?>"></div>
        <div class="sm:col-span-3"><label class="label" for="mobile">Mobile</label><input class="input" type="tel" id="mobile" name="mobile" value="<?= $v('mobile') ?>"></div>
        <div class="sm:col-span-3"><label class="label" for="website">Website</label><input class="input" id="website" name="website" value="<?= $v('website') ?>" placeholder="https://"></div>
      </div>
    </section>

    <section class="reveal card">
      <div class="card-header"><h2 class="card-title"><i class="bx bx-map"></i>Postanschrift</h2></div>
      <div class="grid gap-4 p-5 sm:grid-cols-6 sm:p-6">
        <div class="sm:col-span-6"><label class="label" for="street">Strasse und Nr. <span class="text-rose-500">*</span></label><input class="input<?= $cls('street') ?>" id="street" name="street" value="<?= $v('street') ?>"><?= $err('street') ?></div>
        <div class="sm:col-span-6"><label class="label" for="address_extra">Adresszusatz</label><input class="input" id="address_extra" name="address_extra" value="<?= $v('address_extra') ?>" placeholder="z. B. Postfach, c/o, Stockwerk"></div>
        <div class="sm:col-span-2"><label class="label" for="zip">PLZ <span class="text-rose-500">*</span></label><input class="input<?= $cls('zip') ?>" id="zip" name="zip" value="<?= $v('zip') ?>"><?= $err('zip') ?></div>
        <div class="sm:col-span-4"><label class="label" for="city">Ort <span class="text-rose-500">*</span></label><input class="input<?= $cls('city') ?>" id="city" name="city" value="<?= $v('city') ?>"><?= $err('city') ?></div>
        <div class="sm:col-span-6"><label class="label" for="country">Land <span class="text-rose-500">*</span></label>
          <select class="input<?= $cls('country') ?>" id="country" name="country">
            <?php foreach (countries() as $code => $name): ?><option value="<?= $code ?>"<?= ($customer['country'] ?? 'CH') === $code ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
          </select>
                  </div>
      </div>
    </section>

    <section class="reveal card">
      <div class="card-header"><h2 class="card-title"><i class="bx bx-note"></i>Interne Notizen</h2></div>
      <div class="p-5 sm:p-6"><textarea class="input" name="notes" rows="4" placeholder="Nur intern sichtbar"><?= $v('notes') ?></textarea></div>
    </section>
  </div>

  <div class="space-y-6">
    <section class="reveal reveal-delay-1 card">
      <div class="card-header"><h2 class="card-title"><i class="bx bx-receipt"></i>Geschäftskunde</h2></div>
      <div class="space-y-4 p-5 sm:p-6">
        <div><label class="label" for="vat_id">UID / MWST-Nr. <span class="font-normal text-slate-400">(optional)</span></label><input class="input<?= $cls('vat_id') ?>" id="vat_id" name="vat_id" value="<?= $v('vat_id') ?>" placeholder="CHE-123.456.789 MWST"><?= $err('vat_id') ?><p class="hint">Erscheint auf Rechnungen an Geschäftskunden.</p></div>
      </div>
    </section>

    <section class="reveal reveal-delay-2 card">
      <div class="card-header"><h2 class="card-title"><i class="bx bx-user-circle"></i>Kundenportal</h2></div>
      <div class="p-5 sm:p-6">
        <label class="flex cursor-pointer items-start gap-3">
          <input type="checkbox" class="checkbox mt-0.5" name="portal_enabled" value="1" <?= !empty($customer['portal_enabled']) ? 'checked' : '' ?>>
          <span><span class="block text-sm font-medium text-slate-900">Zugang aktiv</span><span class="block text-xs text-slate-500">Anmeldung per Magic-Link an die hinterlegte E-Mail-Adresse.</span></span>
        </label>
      </div>
    </section>

    <div class="reveal reveal-delay-3 flex flex-col gap-2 lg:sticky lg:top-6">
      <button class="btn btn-primary w-full"><i class="bx bx-save"></i><?= $isNew ? 'Kunde anlegen' : 'Änderungen speichern' ?></button>
      <?php if ($isNew): ?><button class="btn btn-secondary w-full" name="then" value="offer"><i class="bx bx-file"></i>Anlegen &amp; Offerte erstellen</button><?php endif; ?>
    </div>
  </div>
</form>
