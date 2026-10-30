<?php
$copyOf ??= null;
$title = $copyOf ? 'Offerte duplizieren' : ($from ? 'Neue Offerte auf Basis von ' . $from['number'] : 'Neue Offerte');
$subtitle = $copyOf ? 'Kopie von ' . e($copyOf['number']) . ' – Kunde und Positionen können frei angepasst werden. Die Originalofferte bleibt unverändert.' : ($from
    ? 'Die bisherige Offerte ' . e($from['number']) . ($from['status'] === 'open' ? ' wird beim Speichern als <strong>ersetzt</strong> markiert.' : ' bleibt unverändert.')
    : 'Positionen erfassen, Rabatte und Steuern werden automatisch berechnet.');
$back = $copyOf ? ['admin/offers/' . $copyOf['id'], 'Zurück zu ' . $copyOf['number']] : ($from ? ['admin/offers/' . $from['id'], 'Zurück zu ' . $from['number']] : ['admin/offers', 'Offerten']);
$scripts = ['js/offer-editor.js'];
include APP_DIR . '/views/partials/page-header.php';

$f = static fn(string $k, $d = '') => $form[$k] ?? $d;
$err = static fn(string $k) => isset($errors[$k]) ? '<p class="error-text"><i class="bx bx-error-circle"></i>' . e($errors[$k]) . '</p>' : '';
$delivery = is_array($form['delivery'] ?? null) ? $form['delivery'] : [];
$editorConfig = [
    'customers'  => $customerData,
    'areas'      => array_column(array_map(static fn($a) => ['id' => $a['id'], 'type' => $a['type'], 'name' => $a['name']], $areas), null, 'id'),
    'taxes'      => array_map(static fn($t) => ['id' => $t['id'], 'cls' => $t['tax_class'] ?? 'taxable', 'def' => !empty($t['is_default']), 'rate' => $t['rate'], 'display' => $t['display'], 'name' => $t['name']], $taxes),
    'liable'     => TaxService::liable(),
    'shipping'   => array_map(static fn($s) => ['name' => $s['name'], 'cost' => (float) $s['cost']], $shipping),
    'templates'  => array_map(static fn($t) => ['id' => $t['id'], 'title' => $t['title'], 'description' => $t['description'], 'unit' => $t['unit'], 'unit_price' => (float) $t['unit_price'], 'tax' => $t['tax_template_id'], 'area' => $t['product_area_id'], 'area_name' => $t['area_name'], 'image' => $t['image']], $templates),
    'items'      => array_values(is_array($form['items'] ?? null) ? $form['items'] : []),
    'currency'   => Settings::get('currency', 'CHF'),
    'style'      => Settings::get('number_style', 'ch'),
    'round005'   => Settings::get('currency') === 'CHF' && Settings::get('round_005') === '1',
    'quickUrl'   => url('admin/templates/quick'),
    'imgUpload'  => url('admin/item-images'),
    'imgBase'    => url('item-img/'),
];
?>
<?php if ($errors): ?>
  <div class="reveal mb-6 flex gap-3 rounded-2xl bg-rose-50 p-4 text-sm text-rose-800 ring-1 ring-rose-200" role="alert">
    <i class="bx bx-error-circle text-xl"></i>
    <div><p class="font-semibold">Die Offerte konnte nicht erstellt werden.</p>
      <ul class="mt-1 list-inside list-disc space-y-0.5"><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div>
  </div>
<?php endif; ?>

<form method="post" action="<?= e(url('admin/offers')) ?>" id="offer-form" class="grid gap-6 xl:grid-cols-3" data-wizard<?= ($_POST['_wizard'] ?? '') === '1' ? ' data-wizard-start="1"' : '' ?> data-wizard-order="customer,items,terms,delivery,texts,summary" data-editor="<?= json_attr($editorConfig) ?>" novalidate>
  <?= csrf_field() ?>
  <?php if ($from): ?><input type="hidden" name="parent_id" value="<?= (int) $from['id'] ?>"><?php endif; ?>
  <?php if (!empty($form['inquiry_id']) && ($inqRow = DB::one('SELECT * FROM inquiries WHERE id = ?', [(int) $form['inquiry_id']]))): ?>
    <input type="hidden" name="inquiry_id" value="<?= (int) $inqRow['id'] ?>">
    <div class="reveal mb-6 rounded-2xl bg-sky-50 p-4 text-sm text-sky-900 ring-1 ring-sky-200"><p class="font-semibold"><i class="bx bx-envelope-open align-middle"></i> Anfrage vom <?= fmt_datetime($inqRow['created_at']) ?></p><?php if ($inqRow['subject']): ?><p class="mt-1 font-medium"><?= e($inqRow['subject']) ?></p><?php endif; ?><p class="mt-1 whitespace-pre-line"><?= e($inqRow['message']) ?></p></div>
  <?php endif; ?>

  <div class="min-w-0 space-y-6 xl:col-span-2">
    <!-- Kunde & Bereich -->
    <section class="reveal card" data-step="customer" data-step-title="Kunde &amp; Bereich" data-step-icon="bx-user">
      <div class="card-header"><h2 class="card-title"><i class="bx bx-user"></i>Kunde &amp; Produktbereich</h2>
        <a href="<?= e(url('admin/customers/new')) ?>" class="btn btn-ghost btn-sm"><i class="bx bx-user-plus"></i>Neuer Kunde</a></div>
      <div class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
        <div class="sm:col-span-2">
          <label class="label" for="customer_id">Kunde <span class="text-rose-500">*</span></label>
          <select class="input<?= isset($errors['customer_id']) ? ' input-error' : '' ?>" id="customer_id" name="customer_id" data-sum="Kunde" required data-customer>
            <option value="">Kunde wählen …</option>
            <?php foreach ($customers as $c): ?>
              <option value="<?= (int) $c['id'] ?>"<?= (int) $f('customer_id') === (int) $c['id'] ? ' selected' : '' ?>><?= e(customer_name($c)) ?><?= $c['company'] ? ' – ' . e(trim($c['first_name'] . ' ' . $c['last_name'])) : '' ?> (<?= e($c['customer_no']) ?>)</option>
            <?php endforeach; ?>
          </select>
          <?= $err('customer_id') ?>
          <p class="hint" data-customer-info></p>
        </div>
        <div class="sm:col-span-2">
          <span class="label">Produktbereich <span class="text-rose-500">*</span></span>
          <div class="grid gap-3 sm:grid-cols-2">
            <?php foreach ($areas as $a): ?>
              <label class="group relative flex cursor-pointer items-start gap-3 rounded-xl p-4 ring-1 ring-slate-200 transition hover:ring-slate-300 has-[:checked]:bg-brand-50/60 has-[:checked]:ring-2 has-[:checked]:ring-brand-500">
                <input type="radio" class="sr-only" name="product_area_id" data-sum="Produktbereich" value="<?= (int) $a['id'] ?>" data-area <?= (int) $f('product_area_id') === (int) $a['id'] ? 'checked' : '' ?>>
                <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-slate-100 text-xl text-slate-500 transition group-has-[:checked]:bg-brand-600 group-has-[:checked]:text-white"><i class="bx <?= e($a['icon']) ?>"></i></span>
                <span class="min-w-0"><span class="block text-sm font-semibold text-slate-900"><?= e($a['name']) ?></span><span class="block text-xs text-slate-500"><?= $a['type'] === 'digital' ? 'Digital – ohne Versand' : 'Physisch – mit Versand' ?></span></span>
                <i class="bx bxs-check-circle absolute right-3 top-3 text-xl text-brand-600 opacity-0 transition group-has-[:checked]:opacity-100"></i>
              </label>
            <?php endforeach; ?>
          </div>
          <?= $err('product_area_id') ?>
          <?php if (!$areas): ?><p class="hint">Keine aktiven Produktbereiche. <a class="link" href="<?= e(url('admin/areas')) ?>">Produktbereich anlegen</a></p><?php endif; ?>
        </div>
        <div class="sm:col-span-2">
          <label class="label" for="title">Titel der Offerte <span class="text-rose-500">*</span></label>
          <input class="input<?= isset($errors['title']) ? ' input-error' : '' ?>" id="title" name="title" data-sum="Titel" value="<?= e($f('title')) ?>" maxlength="190" placeholder="z. B. Website-Relaunch 2027" required>
          <?= $err('title') ?>
        </div>
      </div>
    </section>

    <!-- Positionen -->
    <section class="reveal card" data-step="items" data-step-title="Positionen" data-step-icon="bx-list-ul">
      <div class="card-header">
        <h2 class="card-title"><i class="bx bx-list-ul"></i>Positionen <span class="badge badge-slate" data-item-count>0</span></h2>
        <button type="button" class="btn btn-secondary btn-sm" data-modal-open="modal-templates"><i class="bx bx-bookmarks"></i>Aus Vorlage</button>
      </div>
      <?= isset($errors['items']) ? '<div class="px-6 pt-4">' . $err('items') . '</div>' : '' ?>
      <div data-items class="divide-y divide-slate-100"></div>
      <div class="flex flex-wrap gap-2 border-t border-slate-100 p-4 sm:px-6">
        <button type="button" class="btn btn-secondary" data-add-item><i class="bx bx-plus"></i>Position hinzufügen</button>
        <button type="button" class="btn btn-ghost" data-modal-open="modal-templates"><i class="bx bx-bookmark-plus"></i>Vorlage einfügen</button>
      </div>
    </section>

    <!-- Texte -->
    <section class="reveal card" data-step="texts" data-step-title="Texte" data-step-icon="bx-text">
      <div class="card-header"><h2 class="card-title"><i class="bx bx-text"></i>Texte</h2></div>
      <div class="grid gap-4 p-5 sm:p-6">
        <?php if (!empty($docTemplates) && count($docTemplates) > 1): ?>
        <div>
          <label class="label" for="template_id">Dokumentvorlage <span class="font-normal text-slate-400">(Layout des PDF)</span></label>
          <select class="input" id="template_id" name="template_id" data-doc-template>
            <option value="" data-intro="" data-closing="">Standardvorlage</option>
            <?php foreach ($docTemplates as $dt): $dc = DocTemplate::config($dt); ?>
              <option value="<?= (int) $dt['id'] ?>" data-intro="<?= e($dc['intro_default']) ?>" data-closing="<?= e($dc['closing_default']) ?>"<?= (int) $f('template_id') === (int) $dt['id'] ? ' selected' : '' ?>><?= e($dt['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="hint">Bestimmt Farbe, Schrift, Logo und Tabellenstil. Verwalten unter <a class="link" href="<?= e(url('admin/doc-templates')) ?>">Dokumentvorlagen</a>.</p>
        </div>
        <?php endif; ?>
        <div><label class="label" for="intro">Einleitung</label><textarea class="input" id="intro" name="intro" rows="3"><?= e($f('intro')) ?></textarea></div>
        <div><label class="label" for="footer">Schlusstext / Konditionen</label><textarea class="input" id="footer" name="footer" rows="3"><?= e($f('footer')) ?></textarea></div>
      </div>
    </section>
  </div>

  <!-- Seitenleiste -->
  <div class="space-y-6">
    <section class="reveal reveal-delay-1 card" data-step="terms" data-step-title="Konditionen" data-step-icon="bx-calendar">
      <div class="card-header"><h2 class="card-title"><i class="bx bx-calendar"></i>Gültigkeit</h2></div>
      <div class="space-y-3 p-5 sm:p-6">
        <div>
          <label class="label" for="valid_until">Gültig bis</label>
          <input class="input<?= isset($errors['valid_until']) ? ' input-error' : '' ?>" type="date" id="valid_until" name="valid_until" data-sum="Gültig bis" value="<?= e($f('valid_until')) ?>" min="<?= date('Y-m-d') ?>" data-valid-until<?= $f('no_expiry') ? ' disabled' : '' ?>>
          <?= $err('valid_until') ?>
          <div class="mt-2 flex flex-wrap gap-1.5" data-valid-quick>
            <?php foreach ([14, 30, 60, 90] as $dd): ?><button type="button" class="btn btn-ghost btn-sm" data-valid-days="<?= $dd ?>">+<?= $dd ?> Tage</button><?php endforeach; ?>
          </div>
        </div>
        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700">
          <input type="checkbox" class="checkbox" name="no_expiry" value="1" data-valid-none <?= $f('no_expiry') ? 'checked' : '' ?>>Ohne Ablaufdatum
        </label>
        <fieldset class="border-t border-slate-100 pt-3" data-sigmode>
          <legend class="label">Unterschrift bei Annahme</legend>
          <?php $sm = $f('signature_mode') ?: 'none';
          $modes = ['none' => ['Keine Unterschrift', 'Der Kunde nimmt die Offerte ohne Unterschrift an.'],
                    'open' => ['Offene Unterschrift', 'Unterschrift erforderlich. Jede Person mit dem Offertenlink kann unterschreiben.'],
                    'strict' => ['Strikte Unterschrift', 'Nur eine bestimmte Person kann unterschreiben – sie bestätigt ihre E-Mail-Adresse per Code.']];
          foreach ($modes as $k => [$lbl, $hint]): ?>
            <label class="mb-1.5 flex cursor-pointer items-start gap-2 text-sm text-slate-700">
              <input type="radio" class="mt-1" name="signature_mode" value="<?= $k ?>" <?= $sm === $k ? 'checked' : '' ?>>
              <span><span class="font-medium"><?= $lbl ?></span><span class="block text-xs text-slate-400"><?= $hint ?></span></span>
            </label>
          <?php endforeach; ?>
          <div class="mt-2 space-y-3 rounded-xl bg-slate-50 p-3 ring-1 ring-slate-200/70" data-sigstrict <?= $sm === 'strict' ? '' : 'hidden' ?>>
            <div>
              <label class="label" for="signer_name">Unterschreibende Person (Vor- und Nachname)</label>
              <input class="input<?= isset($errors['signer_name']) ? ' input-error' : '' ?>" id="signer_name" name="signer_name" maxlength="190" value="<?= e($f('signer_name')) ?>" placeholder="Leer = Kontaktperson des Kunden">
              <?= $err('signer_name') ?>
            </div>
            <div>
              <label class="label" for="signer_email">E-Mail-Adresse der Person</label>
              <input class="input<?= isset($errors['signer_email']) ? ' input-error' : '' ?>" type="email" id="signer_email" name="signer_email" maxlength="190" value="<?= e($f('signer_email')) ?>" placeholder="Leer = E-Mail des Kunden">
              <?= $err('signer_email') ?>
              <p class="hint">Der Bestätigungscode geht nur an diese Adresse. Es muss eine natürliche Person sein, keine Firma oder Sammeladresse.</p>
            </div>
          </div>
          <script>
            (function () {
              var fs = document.currentScript.parentNode, box = fs.querySelector('[data-sigstrict]');
              fs.addEventListener('change', function () { var r = fs.querySelector('input[name=signature_mode]:checked'); box.hidden = !(r && r.value === 'strict'); });
            })();
          </script>
        </fieldset>
              </div>
    </section>

    <section class="reveal reveal-delay-1 card" data-step="terms">
      <div class="card-header"><h2 class="card-title"><i class="bx bx-receipt"></i>Steuern &amp; Rabatt</h2></div>
      <div class="space-y-4 p-5 sm:p-6">
        <div data-tax-wrap>
          <div class="mb-1.5 flex items-center justify-between gap-2">
            <label class="label !mb-0" for="tax_template_id">Steuervorlage</label>
          </div>
          <select class="input<?= isset($errors['tax_template_id']) ? ' input-error' : '' ?>" id="tax_template_id" name="tax_template_id" data-sum="Steuervorlage" data-default-tax>
            <?php $curTax = (int) $f('tax_template_id'); $hasCur = $curTax && TaxService::find($curTax); foreach ($taxes as $t): ?>
              <option value="<?= (int) $t['id'] ?>"<?= ($hasCur ? $curTax === (int) $t['id'] : !empty($t['is_default'])) ? ' selected' : '' ?>><?= e($t['display']) ?> – <?= e($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="hint" data-tax-hint>Wird anhand der Kundenadresse vorgeschlagen und gilt für alle Positionen mit „Standard“ sowie den Versand.</p>
          <?= $err('tax_template_id') ?>
        </div>
        <div>
          <label class="label">Rabatt auf Gesamtbetrag</label>
          <div class="flex gap-2">
            <select class="input w-28 shrink-0" name="discount_type" data-calc>
              <option value="percent"<?= $f('discount_type') === 'percent' ? ' selected' : '' ?>>%</option>
              <option value="amount"<?= $f('discount_type') === 'amount' ? ' selected' : '' ?>><?= e(Settings::get('currency')) ?></option>
            </select>
            <input class="input tabular" name="discount_value" inputmode="decimal" value="<?= e($f('discount_value')) ?>" placeholder="0" data-calc>
          </div>
          <?= $err('discount_value') ?>
        </div>
      </div>
    </section>

    <section class="reveal reveal-delay-2 card" data-physical data-step="delivery" data-step-title="Lieferung" data-step-icon="bx-car" hidden>
      <div class="card-header"><h2 class="card-title"><i class="bx bx-car"></i>Versand</h2></div>
      <div class="space-y-4 p-5 sm:p-6">
        <div>
          <label class="label" for="shipping_method">Versandart</label>
          <input class="input" id="shipping_method" name="shipping_method" data-sum="Versandart" list="shipping-list" value="<?= e($f('shipping_method')) ?>" placeholder="Versandart wählen oder eingeben" data-shipping-method>
          <datalist id="shipping-list"><?php foreach ($shipping as $s): ?><option value="<?= e($s['name']) ?>"><?= e(money($s['cost'])) ?></option><?php endforeach; ?></datalist>
        </div>
        <div>
          <label class="label" for="shipping_cost">Versandkosten (netto)</label>
          <div class="relative"><span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm text-slate-400"><?= e(Settings::get('currency')) ?></span>
            <input class="input pl-14 tabular" id="shipping_cost" name="shipping_cost" inputmode="decimal" value="<?= e($f('shipping_cost', '0.00')) ?>" data-calc data-shipping-cost></div>
        </div>
      </div>
    </section>

    <section class="reveal reveal-delay-2 card" data-step="delivery">
      <div class="card-header"><h2 class="card-title"><i class="bx bx-map"></i>Lieferadresse</h2></div>
      <div class="space-y-4 p-5 sm:p-6">
        <p class="text-sm text-slate-500">Standard: Adresse des Kunden. Der Kunde kann sie bei der Annahme ändern.</p>
        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700">
          <input type="checkbox" class="checkbox" name="alt_delivery" value="1" data-collapse-toggle="#alt-delivery" <?= $f('alt_delivery') ? 'checked' : '' ?>>Abweichende Lieferadresse
        </label>
        <div class="fold" id="alt-delivery"><div class="grid gap-2.5">
          <input class="input" name="delivery[company]" placeholder="Firma" value="<?= e($delivery['company'] ?? '') ?>">
          <input class="input" name="delivery[name]" placeholder="Name *" value="<?= e($delivery['name'] ?? '') ?>">
          <input class="input" name="delivery[street]" placeholder="Strasse und Nr. *" value="<?= e($delivery['street'] ?? '') ?>">
          <input class="input" name="delivery[address_extra]" placeholder="Adresszusatz" value="<?= e($delivery['address_extra'] ?? '') ?>">
          <div class="grid grid-cols-3 gap-2.5"><input class="input" name="delivery[zip]" placeholder="PLZ *" value="<?= e($delivery['zip'] ?? '') ?>"><input class="input col-span-2" name="delivery[city]" placeholder="Ort *" value="<?= e($delivery['city'] ?? '') ?>"></div>
          <select class="input" name="delivery[country]"><?php foreach (countries() as $code => $name): ?><option value="<?= $code ?>"<?= ($delivery['country'] ?? 'CH') === $code ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select>
          <?= $err('delivery') ?>
        </div></div>
      </div>
    </section>

    <section class="reveal reveal-delay-2 card" data-step="delivery">
      <div class="card-header"><h2 class="card-title"><i class="bx bx-info-circle"></i><span data-delivery-label>Lieferhinweis</span></h2></div>
      <div class="p-5 sm:p-6">
        <input class="input" name="delivery_info" data-sum="Lieferhinweis" value="<?= e($f('delivery_info')) ?>" maxlength="255" placeholder="z. B. Lieferung innert 10 Arbeitstagen" data-delivery-info>
        <p class="hint">Erscheint auf der Offerte, z. B. Lieferzeit oder Form der Bereitstellung.</p>
      </div>
    </section>

    <!-- Summen -->
    <section class="card" data-step="summary" data-step-title="Prüfen &amp; senden" data-step-icon="bx-check-circle" data-wizard-only hidden data-wizard-summary>
      <div class="card-header"><h2 class="card-title"><i class="bx bx-list-check"></i>Ihre Angaben</h2></div>
      <div class="px-5 py-3 sm:px-6" data-wz-sum-body></div>
    </section>

    <section class="reveal reveal-delay-3 card xl:sticky xl:top-6" data-step="summary">
      <div class="card-header"><h2 class="card-title"><i class="bx bx-calculator"></i>Zusammenfassung</h2></div>
      <dl class="px-5 py-4 sm:px-6" data-totals>
        <div class="dl-row"><dt>Zwischensumme</dt><dd class="tabular" data-t="subtotal">–</dd></div>
        <div class="dl-row" data-row="discount" hidden><dt>Rabatt</dt><dd class="tabular text-emerald-700" data-t="discount">–</dd></div>
        <div class="dl-row" data-row="shipping" hidden><dt>Versand</dt><dd class="tabular" data-t="shipping">–</dd></div>
        <div class="dl-row border-t border-slate-100"><dt>Total exkl. Steuern</dt><dd class="tabular" data-t="net">–</dd></div>
        <div data-t="taxes"></div>
        <div class="dl-row" data-row="rounding" hidden><dt>Rundung</dt><dd class="tabular" data-t="rounding">–</dd></div>
        <div class="mt-3 flex items-baseline justify-between rounded-xl bg-brand-50 px-4 py-3 ring-1 ring-brand-100">
          <dt class="text-sm font-semibold text-brand-900">Gesamtbetrag</dt><dd class="text-xl font-bold text-brand-900 tabular" data-t="total">–</dd>
        </div>
      </dl>
      <div class="space-y-3 border-t border-slate-100 p-5 sm:p-6">
        <label class="flex cursor-pointer items-start gap-2.5 text-sm text-slate-700">
          <input type="checkbox" class="checkbox mt-0.5" name="send_now" value="1" checked>
          <span>Offerte direkt per E-Mail an den Kunden senden</span>
        </label>
        <button class="btn btn-primary w-full"><i class="bx bx-check"></i>Offerte erstellen</button>
        <p class="flex gap-1.5 text-xs leading-relaxed text-slate-500"><i class="bx bx-lock-alt text-sm"></i>Offerten können nach dem Erstellen nicht mehr geändert werden – nur gelöscht oder durch eine neue ersetzt.</p>
      </div>
    </section>
  </div>
</form>

<!-- Vorlagen-Modal -->
<div class="modal" id="modal-templates" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="tpl-title">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-panel max-w-2xl">
    <div class="flex items-center justify-between gap-4 border-b border-slate-100 p-5 sm:px-6">
      <h3 id="tpl-title" class="text-base font-semibold text-slate-900">Positionsvorlage einfügen</h3>
      <button type="button" class="btn btn-ghost btn-icon" data-modal-close aria-label="Schliessen"><i class="bx bx-x text-2xl"></i></button>
    </div>
    <div class="border-b border-slate-100 p-4 sm:px-6">
      <div class="relative"><i class="bx bx-search pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-lg text-slate-400"></i>
        <input class="input pl-10" type="search" placeholder="Vorlagen durchsuchen …" data-tpl-search autofocus></div>
    </div>
    <ul class="max-h-[50vh] divide-y divide-slate-100 overflow-y-auto" data-tpl-list></ul>
    <div class="flex justify-between gap-2 rounded-b-2xl bg-slate-50 px-6 py-3 text-xs text-slate-500">
      <span>Tipp: Speichern Sie Positionen über <i class="bx bx-bookmark-plus align-middle"></i> als Vorlage.</span>
      <a class="link" href="<?= e(url('admin/templates')) ?>">Vorlagen verwalten</a>
    </div>
  </div>
</div>
<script>
document.getElementById('offer-form').addEventListener('wizard:validate', function (e) {
  if (e.detail.step === 'items') {
    var rows = this.querySelectorAll('[data-items] [data-row]');
    var ok = Array.prototype.some.call(rows, function (r) { var t = r.querySelector('input[name$="[title]"]'); return t && t.value.trim() !== ''; });
    if (!ok) { e.preventDefault(); window.toast && window.toast('Bitte mindestens eine Position mit Titel erfassen.', 'error'); }
  }
  if (e.detail.step === 'customer' && this.querySelector('[name=product_area_id]') && !this.querySelector('[name=product_area_id]:checked')) {
    e.preventDefault(); window.toast && window.toast('Bitte einen Produktbereich wählen.', 'error');
  }
});
</script>
<script>
(function () {
  var sel = document.querySelector('[data-doc-template]'); if (!sel) return;
  var intro = document.getElementById('intro'), foot = document.getElementById('footer'), prev = sel.options[sel.selectedIndex];
  sel.addEventListener('change', function () {
    var cur = sel.options[sel.selectedIndex];
    // Texte nur ersetzen, wenn sie leer oder noch der Standardtext der bisherigen Vorlage sind
    [[intro, 'intro'], [foot, 'closing']].forEach(function (p) {
      var el = p[0], key = p[1]; if (!el) return;
      var old = prev.dataset[key] || '', now = cur.dataset[key] || '';
      if ((el.value.trim() === '' || el.value.trim() === old.trim()) && now !== '') el.value = now;
    });
    prev = cur;
  });
})();
</script>
