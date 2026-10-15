<?php
/** Reiter «Branding» (Whitelabel). Erwartet $s aus SettingsController::index(). */
$bv = static fn(string $k) => e($s[$k] ?? '');
$hasLogo = Brand::hasUpload('logo');
$hasFav = Brand::hasUpload('favicon');
?>
<form method="post" action="<?= e(url('admin/settings/branding')) ?>" enctype="multipart/form-data" class="reveal card max-w-4xl"><?= csrf_field() ?>
  <div class="grid gap-5 p-5 sm:grid-cols-6 sm:p-6">
    <div class="sm:col-span-6 rounded-xl bg-slate-50 p-4 text-sm text-slate-600 ring-1 ring-slate-200/70"><i class="bx bx-info-circle align-middle text-slate-400"></i> Das System tritt unter Ihrem eigenen Namen und Erscheinungsbild auf: Administration, Anmeldung, Kundenportal, Offerten, E-Mails und PDF-Dokumente. Einzig in der Seitenliste der Administration steht unten der Hinweis «<?= e(Brand::POWERED_BY) ?>».</div>

    <div class="sm:col-span-3"><label class="label">Systemname <span class="font-normal text-slate-400">(optional)</span></label>
      <input class="input" name="brand_name" maxlength="60" value="<?= $bv('brand_name') ?>" placeholder="<?= e($s['company_name'] ?? '') ?>">
      <p class="hint">Erscheint in der Administration, im Browser-Tab, im Login, in Authenticator-Apps und Passkeys. Leer = Firmenname.</p></div>
    <div class="sm:col-span-3"><label class="label">Untertitel <span class="font-normal text-slate-400">(optional)</span></label>
      <input class="input" name="brand_tagline" maxlength="60" value="<?= $bv('brand_tagline') ?>" placeholder="<?= e(Brand::DEFAULT_TAGLINE) ?>">
      <p class="hint">Kleiner Text unter dem Namen in der Seitenliste.</p></div>

    <div class="sm:col-span-3"><label class="label">Markenfarbe</label>
      <div class="flex gap-2"><input type="color" class="h-[42px] w-14 cursor-pointer rounded-xl border-0 bg-white p-1 ring-1 ring-slate-200" value="<?= $bv('brand_color') ?>" oninput="this.nextElementSibling.value=this.value"><input class="input font-mono" name="brand_color" value="<?= $bv('brand_color') ?>" pattern="#[0-9a-fA-F]{6}" oninput="if(/^#[0-9a-f]{6}$/i.test(this.value))this.previousElementSibling.value=this.value"></div>
      <p class="hint">Buttons, Akzente, E-Mails und PDF-Dokumente.</p></div>
    <div class="sm:col-span-3"></div>

    <div class="sm:col-span-3"><label class="label">Logo</label>
      <?php if ($hasLogo): ?><div class="mb-2 flex items-center gap-3 rounded-xl bg-slate-50 p-3 ring-1 ring-slate-200/70"><img src="<?= e(Brand::logoUrl()) ?>" alt="" class="h-10 w-auto max-w-[140px] object-contain"><label class="ml-auto flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" class="checkbox" name="logo_remove" value="1">Entfernen</label></div><?php endif; ?>
      <input class="input" type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/gif">
      <p class="hint">PNG, JPG, WebP oder GIF, max. 1 MB. Quadratisch oder querformatig, am besten mit transparentem Hintergrund. Wird eigenständig gehostet – keine Anfrage an Drittserver (DSGVO).</p></div>
    <div class="sm:col-span-3"><label class="label">Favicon</label>
      <?php if ($hasFav): ?><div class="mb-2 flex items-center gap-3 rounded-xl bg-slate-50 p-3 ring-1 ring-slate-200/70"><img src="<?= e(Brand::faviconUrl()) ?>" alt="" class="size-8 object-contain"><label class="ml-auto flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" class="checkbox" name="favicon_remove" value="1">Entfernen</label></div><?php endif; ?>
      <input class="input" type="file" name="favicon" accept="image/png,image/x-icon,image/vnd.microsoft.icon,image/jpeg,image/webp,image/gif">
      <p class="hint">PNG oder ICO, mindestens 32 × 32 Pixel. Ohne Upload wird ein Symbol in der Markenfarbe verwendet.</p></div>
  </div>
  <div class="flex justify-end rounded-b-2xl border-t border-slate-100 bg-slate-50/50 px-5 py-4 sm:px-6"><button class="btn btn-primary"><i class="bx bx-save"></i>Speichern</button></div>
</form>
