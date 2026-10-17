<?php
$isNew = empty($tpl['id']);
$title = $isNew ? 'Neue Dokumentvorlage' : 'Vorlage: ' . $tpl['name'];
$subtitle = 'Änderungen erscheinen sofort in der Vorschau. Sie gelten für alle Dokumente, die diese Vorlage verwenden.';
$back = ['admin/doc-templates', 'Dokumentvorlagen'];
$scripts = ['js/doc-template.js'];
include APP_DIR . '/views/partials/page-header.php';
$brand = (string) Settings::get('brand_color', '#4f46e5');
$sel = static fn($a, $b) => (string) $a === (string) $b ? ' selected' : '';
$chk = static fn($v) => $v ? ' checked' : '';
$hasLogo = Brand::logoPath() !== null;
?>
<?php if (!empty($error)): ?>
  <div class="reveal mb-6 flex gap-3 rounded-2xl bg-rose-50 p-4 text-sm text-rose-800 ring-1 ring-rose-200" role="alert"><i class="bx bx-error-circle text-xl"></i><p><?= e($error) ?></p></div>
<?php endif; ?>
<form method="post" action="<?= e(url($isNew ? 'admin/doc-templates' : 'admin/doc-templates/' . $tpl['id'])) ?>" id="tpl-form" class="grid gap-6 xl:grid-cols-5" data-preview="<?= e(url('admin/doc-templates/preview')) ?>" data-brand="<?= e($brand) ?>">
  <?= csrf_field() ?>
  <div class="min-w-0 space-y-6 xl:col-span-2">
    <section class="reveal card">
      <div class="card-header"><h2 class="card-title"><i class="bx bx-label"></i>Allgemein</h2></div>
      <div class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
        <div class="sm:col-span-2"><label class="label" for="name">Name <span class="text-rose-500">*</span></label><input class="input" id="name" name="name" value="<?= e($tpl['name']) ?>" maxlength="120" required placeholder="z. B. Standard mit Logo rechts"></div>
        <div class="sm:col-span-2"><label class="label" for="doc_type">Verwendbar für</label>
          <select class="input" id="doc_type" name="doc_type"><?php foreach (DocTemplate::TYPES as $k => $l): ?><option value="<?= $k ?>"<?= $sel($tpl['doc_type'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      </div>
    </section>

    <section class="reveal card">
      <div class="card-header"><h2 class="card-title"><i class="bx bx-palette"></i>Farbe &amp; Schrift</h2></div>
      <div class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
        <div class="sm:col-span-2"><label class="label" for="accent">Akzentfarbe</label>
          <div class="flex flex-wrap items-center gap-2">
            <input type="color" id="accent-picker" class="h-[42px] w-14 cursor-pointer rounded-xl border-0 bg-white p-1 ring-1 ring-slate-200" value="<?= e($cfg['accent'] !== '' ? $cfg['accent'] : $brand) ?>" aria-label="Farbe wählen">
            <input class="input w-32 font-mono" id="accent" name="accent" value="<?= e($cfg['accent']) ?>" placeholder="<?= e($brand) ?>" pattern="#[0-9a-fA-F]{6}" maxlength="7">
            <button type="button" class="btn btn-ghost btn-sm" data-accent-reset>Markenfarbe verwenden</button>
          </div><p class="hint">Linie, Dokumenttyp, Summenfeld. Leer = Markenfarbe aus den Einstellungen.</p></div>
        <div><label class="label" for="font">Schrift</label><select class="input" id="font" name="font"><?php foreach (DocTemplate::FONTS as $k => $l): ?><option value="<?= $k ?>"<?= $sel($cfg['font'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div><label class="label" for="font_scale">Schriftgrösse <span class="font-normal text-slate-400" data-out="font_scale"><?= (int) $cfg['font_scale'] ?> %</span></label><input type="range" class="w-full accent-[var(--brand)]" id="font_scale" name="font_scale" min="90" max="110" step="2" value="<?= (int) $cfg['font_scale'] ?>"></div>
        <div class="sm:col-span-2"><label class="label" for="margin">Seitenrand links/rechts <span class="font-normal text-slate-400" data-out="margin"><?= (int) $cfg['margin'] ?> mm</span></label><input type="range" class="w-full accent-[var(--brand)]" id="margin" name="margin" min="15" max="25" step="1" value="<?= (int) $cfg['margin'] ?>"></div>
      </div>
    </section>

    <section class="reveal card">
      <div class="card-header"><h2 class="card-title"><i class="bx bx-image"></i>Kopfbereich</h2></div>
      <div class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
        <label class="flex cursor-pointer items-start gap-3 sm:col-span-2"><input type="checkbox" class="checkbox mt-0.5" name="logo" value="1"<?= $chk($cfg['logo']) ?>><span><span class="block text-sm font-medium text-slate-900">Logo anzeigen</span><span class="block text-xs text-slate-500"><?= $hasLogo ? 'Das unter Einstellungen → Erscheinungsbild hochgeladene Logo wird verwendet.' : 'Noch kein Logo hochgeladen – unter Einstellungen → Erscheinungsbild (PNG, JPG, GIF oder WebP).' ?></span></span></label>
        <div><label class="label" for="logo_pos">Logo-Position</label><select class="input" id="logo_pos" name="logo_pos"><option value="left"<?= $sel($cfg['logo_pos'], 'left') ?>>Links (über der Firma)</option><option value="right"<?= $sel($cfg['logo_pos'], 'right') ?>>Rechts (über den Dokumentdaten)</option></select></div>
        <div><label class="label" for="logo_height">Logo-Höhe <span class="font-normal text-slate-400" data-out="logo_height"><?= (int) $cfg['logo_height'] ?> mm</span></label><input type="range" class="w-full accent-[var(--brand)]" id="logo_height" name="logo_height" min="8" max="30" step="1" value="<?= (int) $cfg['logo_height'] ?>"></div>
        <div class="sm:col-span-2"><label class="label" for="company_block">Firmenangaben</label><select class="input" id="company_block" name="company_block"><option value="full"<?= $sel($cfg['company_block'], 'full') ?>>Name und Adresse</option><option value="name"<?= $sel($cfg['company_block'], 'name') ?>>Nur Name</option><option value="none"<?= $sel($cfg['company_block'], 'none') ?>>Ausblenden (z. B. Briefpapier mit Aufdruck)</option></select></div>
        <div class="sm:col-span-2"><label class="label" for="header_note">Zusatzzeile unter der Adresse <span class="font-normal text-slate-400">(optional)</span></label><input class="input" id="header_note" name="header_note" value="<?= e($cfg['header_note']) ?>" maxlength="160" placeholder="z. B. Mitglied Branchenverband · Handelsregister CHE-…"></div>
      </div>
    </section>

    <section class="reveal card">
      <div class="card-header"><h2 class="card-title"><i class="bx bx-table"></i>Tabelle &amp; Summen</h2></div>
      <div class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
        <div><label class="label" for="table_style">Tabellenstil</label><select class="input" id="table_style" name="table_style"><?php foreach (DocTemplate::TABLE_STYLES as $k => $l): ?><option value="<?= $k ?>"<?= $sel($cfg['table_style'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div><label class="label" for="total_style">Gesamtbetrag</label><select class="input" id="total_style" name="total_style"><option value="tint"<?= $sel($cfg['total_style'], 'tint') ?>>Helles Feld</option><option value="solid"<?= $sel($cfg['total_style'], 'solid') ?>>Farbiges Feld (weisse Schrift)</option></select></div>
      </div>
    </section>

    <section class="reveal card">
      <div class="card-header"><h2 class="card-title"><i class="bx bx-dock-bottom"></i>Fusszeile &amp; Standardtexte</h2></div>
      <div class="grid gap-4 p-5 sm:p-6">
        <div><label class="label" for="footer_text">Fusszeile</label><input class="input" id="footer_text" name="footer_text" value="<?= e($cfg['footer_text']) ?>" maxlength="160"><p class="hint">Platzhalter: <code>{firma}</code> <code>{nummer}</code> <code>{uid}</code> <code>{datum}</code></p></div>
        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700"><input type="checkbox" class="checkbox" name="page_numbers" value="1"<?= $chk($cfg['page_numbers']) ?>>Seitenzahlen anzeigen</label>
        <div><label class="label" for="intro_default">Einleitung für neue Dokumente <span class="font-normal text-slate-400">(optional)</span></label><textarea class="input" id="intro_default" name="intro_default" rows="3" data-no-preview><?= e($cfg['intro_default']) ?></textarea><p class="hint">Ersetzt den Standardtext aus den Einstellungen, wenn die Vorlage gewählt wird.</p></div>
        <div><label class="label" for="closing_default">Schlusstext für neue Dokumente <span class="font-normal text-slate-400">(optional)</span></label><textarea class="input" id="closing_default" name="closing_default" rows="3" data-no-preview><?= e($cfg['closing_default']) ?></textarea></div>
      </div>
    </section>

    <div class="flex flex-wrap gap-2">
      <button class="btn btn-primary"><i class="bx bx-save"></i>Speichern</button>
      <?php if (!$isNew): ?><button class="btn btn-secondary" name="stay" value="1">Speichern &amp; hier bleiben</button><?php endif; ?>
      <a href="<?= e(url('admin/doc-templates')) ?>" class="btn btn-ghost">Abbrechen</a>
    </div>
  </div>

  <aside class="min-w-0 xl:col-span-3">
    <div class="reveal card overflow-hidden xl:sticky xl:top-6">
      <div class="card-header">
        <h2 class="card-title"><i class="bx bx-show"></i>Vorschau</h2>
        <div class="inline-flex gap-1 rounded-xl bg-slate-100 p-1" role="tablist" aria-label="Dokumenttyp der Vorschau">
          <button type="button" class="tab is-active" data-kind="offer" role="tab"><i class="bx bx-file"></i>Offerte</button>
          <button type="button" class="tab" data-kind="invoice" role="tab"><i class="bx bx-receipt"></i>Rechnung</button>
        </div>
      </div>
      <div class="relative bg-slate-200/70">
        <iframe title="PDF-Vorschau" class="block h-[78vh] min-h-[480px] w-full" data-frame></iframe>
        <p class="pointer-events-none absolute inset-0 grid place-items-center text-sm text-slate-500" data-frame-hint>Vorschau wird geladen …</p>
      </div>
      <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">Beispieldaten – keine echten Kunden. Zeigt Ihr Browser kein PDF an, <a class="link" href="#" data-open-pdf target="_blank" rel="noopener">öffnen Sie die Vorschau in einem neuen Tab</a>.</p>
    </div>
  </aside>
</form>
