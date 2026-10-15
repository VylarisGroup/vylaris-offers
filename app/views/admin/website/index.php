<?php
/** Website-Builder. Erwartet $doc (Entwurf), $schema, $dirty. */
$title = 'Webseite';
$scripts = ['js/website-builder.js'];
$icons = [
    'bx-check-circle' => 'Häkchen', 'bx-star' => 'Stern', 'bx-heart' => 'Herz', 'bx-bolt-circle' => 'Blitz', 'bx-shield-alt-2' => 'Schild', 'bx-lock-alt' => 'Schloss',
    'bx-time-five' => 'Uhr', 'bx-rocket' => 'Rakete', 'bx-file' => 'Dokument', 'bx-receipt' => 'Rechnung', 'bx-package' => 'Paket', 'bx-car' => 'Lieferung',
    'bx-credit-card' => 'Zahlung', 'bx-mobile-alt' => 'Smartphone', 'bx-desktop' => 'Computer', 'bx-cloud' => 'Cloud', 'bx-cog' => 'Zahnrad', 'bx-support' => 'Support',
    'bx-envelope' => 'E-Mail', 'bx-phone' => 'Telefon', 'bx-map' => 'Karte', 'bx-world' => 'Welt', 'bx-trophy' => 'Pokal', 'bx-group' => 'Team', 'bx-user-check' => 'Kunde',
    'bx-line-chart' => 'Wachstum', 'bx-bulb' => 'Idee', 'bx-leaf' => 'Blatt', 'bx-gift' => 'Geschenk', 'bx-badge-check' => 'Qualität', 'bx-edit' => 'Bearbeiten',
    'bx-search-alt' => 'Suche', 'bx-cart' => 'Warenkorb', 'bx-store' => 'Laden', 'bx-wrench' => 'Werkzeug', 'bx-palette' => 'Design',
];
$subtitle = 'Gestalten Sie die Startseite aus Bausteinen. Änderungen erscheinen links als Entwurf und werden erst mit «Veröffentlichen» live.';
$actions = '<a class="btn btn-secondary" href="' . e(url('?view=1')) . '" target="_blank" rel="noopener"><i class="bx bx-link-external"></i>Live ansehen</a>';
include APP_DIR . '/views/partials/page-header.php';
?>
<div class="reveal mb-4 flex flex-wrap items-center gap-2" id="wb-toolbar">
  <span id="wb-status" class="badge <?= $dirty ? 'badge-amber' : 'badge-emerald' ?>"><i class="bx <?= $dirty ? 'bx-edit' : 'bx-check' ?>"></i><span><?= $dirty ? 'Ungeveröffentlichte Änderungen' : 'Veröffentlicht' ?></span></span>
  <span id="wb-save" class="text-xs text-slate-400" aria-live="polite"></span>
  <div class="ml-auto flex flex-wrap gap-2">
    <form method="post" action="<?= e(url('admin/website/discard')) ?>" data-confirm="Alle ungeveröffentlichten Änderungen verwerfen?" data-confirm-label="Verwerfen"><?= csrf_field() ?><button class="btn btn-secondary btn-sm"><i class="bx bx-undo"></i>Verwerfen</button></form>
    <form method="post" action="<?= e(url('admin/website/reset')) ?>" data-confirm="Den Entwurf auf die Standard-Vorlage zurücksetzen? Die veröffentlichte Seite bleibt unverändert." data-confirm-label="Zurücksetzen"><?= csrf_field() ?><button class="btn btn-secondary btn-sm"><i class="bx bx-reset"></i>Vorlage</button></form>
    <form method="post" action="<?= e(url('admin/website/publish')) ?>"><?= csrf_field() ?><button class="btn btn-primary btn-sm"><i class="bx bx-rocket"></i>Veröffentlichen</button></form>
  </div>
</div>

<div id="wb" class="reveal grid gap-6 xl:grid-cols-[minmax(380px,460px)_1fr]"
     data-doc="<?= json_attr($doc) ?>" data-schema="<?= json_attr($schema) ?>" data-types="<?= json_attr(SiteBuilder::TYPES) ?>" data-icons="<?= json_attr($icons) ?>"
     data-save-url="<?= e(url('admin/website/save')) ?>" data-upload-url="<?= e(url('admin/website/upload')) ?>" data-img-base="<?= e(url('site/img/')) ?>">
  <section class="space-y-4" aria-label="Editor"><div id="wb-editor" class="space-y-4"></div></section>
  <section class="min-w-0 xl:sticky xl:top-6 xl:self-start" aria-label="Vorschau">
    <div class="card overflow-hidden">
      <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-2.5">
        <p class="text-sm font-semibold text-slate-900"><i class="bx bx-show align-middle text-slate-400"></i> Vorschau <span class="font-normal text-slate-400">(Entwurf)</span></p>
        <div class="inline-flex gap-1 rounded-lg bg-slate-100 p-0.5" role="group" aria-label="Gerät">
          <button type="button" class="tab is-active" data-device="100%" aria-label="Desktop"><i class="bx bx-desktop"></i></button>
          <button type="button" class="tab" data-device="390px" aria-label="Smartphone"><i class="bx bx-mobile-alt"></i></button>
        </div>
      </div>
      <div class="bg-slate-100 p-3"><iframe id="wb-preview" src="<?= e(url('admin/website/preview')) ?>" title="Vorschau" class="mx-auto block h-[70vh] min-h-[480px] w-full rounded-lg bg-white shadow-sm ring-1 ring-slate-200 transition-[width] duration-300"></iframe></div>
    </div>
  </section>
</div>
