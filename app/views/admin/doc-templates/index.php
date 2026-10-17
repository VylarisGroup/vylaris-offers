<?php
$title = 'Dokumentvorlagen';
$subtitle = 'Gestalten Sie das PDF-Layout von Offerten und Rechnungen: Farbe, Schrift, Logo, Tabellenstil und Fusszeile. Inhalte und Beträge bleiben unverändert.';
$actions = '<a href="' . e(url('admin/doc-templates/new')) . '" class="btn btn-primary"><i class="bx bx-plus"></i>Neue Vorlage</a>';
include APP_DIR . '/views/partials/page-header.php';
?>
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
  <?php foreach ($templates as $t): $c = DocTemplate::config($t); $acc = $c['accent'] !== '' ? $c['accent'] : (string) Settings::get('brand_color', '#4f46e5'); ?>
  <article class="reveal card flex flex-col overflow-hidden">
    <a href="<?= e(url('admin/doc-templates/' . $t['id'])) ?>" class="block bg-slate-100 p-5" aria-label="Vorlage <?= e($t['name']) ?> bearbeiten">
      <!-- Mini-Vorschau -->
      <div class="mx-auto aspect-[210/150] w-full max-w-[260px] overflow-hidden rounded-md bg-white p-3 shadow-sm ring-1 ring-slate-200" style="font-family: <?= $c['font'] === 'times' ? 'Georgia, serif' : ($c['font'] === 'courier' ? 'Courier New, monospace' : 'Inter, sans-serif') ?>">
        <div class="flex items-start justify-between gap-2 <?= $c['logo'] && $c['logo_pos'] === 'right' ? 'flex-row' : '' ?>">
          <div class="space-y-1">
            <?php if ($c['logo'] && $c['logo_pos'] === 'left'): ?><div class="h-3 w-8 rounded-sm" style="background:<?= e($acc) ?>"></div><?php endif; ?>
            <div class="h-1.5 w-14 rounded bg-slate-700"></div>
            <?php if ($c['company_block'] === 'full'): ?><div class="h-1 w-10 rounded bg-slate-300"></div><div class="h-1 w-12 rounded bg-slate-300"></div><?php endif; ?>
          </div>
          <div class="space-y-1 text-right">
            <?php if ($c['logo'] && $c['logo_pos'] === 'right'): ?><div class="ml-auto h-3 w-8 rounded-sm" style="background:<?= e($acc) ?>"></div><?php endif; ?>
            <div class="ml-auto h-1 w-6 rounded" style="background:<?= e($acc) ?>"></div><div class="ml-auto h-2 w-12 rounded bg-slate-800"></div>
          </div>
        </div>
        <div class="mt-2 h-px w-full" style="background:<?= e($acc) ?>"></div>
        <div class="mt-2 space-y-1">
          <div class="h-1.5 w-20 rounded bg-slate-700"></div>
          <?php $ts = $c['table_style']; ?>
          <div class="mt-1.5 h-2 w-full rounded-sm <?= $ts === 'filled' ? 'bg-slate-200' : '' ?>" style="<?= $ts === 'lines' ? 'border-bottom:1.5px solid ' . e($acc) : '' ?>"></div>
          <div class="flex justify-between"><div class="h-1 w-16 rounded bg-slate-300"></div><div class="h-1 w-5 rounded bg-slate-400"></div></div>
          <div class="flex justify-between"><div class="h-1 w-12 rounded bg-slate-300"></div><div class="h-1 w-5 rounded bg-slate-400"></div></div>
        </div>
        <div class="mt-2 ml-auto flex h-3 w-16 items-center justify-end rounded-sm px-1" style="background:<?= $c['total_style'] === 'solid' ? e($acc) : 'color-mix(in oklab, ' . e($acc) . ' 12%, white)' ?>"><div class="h-1 w-6 rounded <?= $c['total_style'] === 'solid' ? 'bg-white/90' : 'bg-slate-700' ?>"></div></div>
      </div>
    </a>
    <div class="flex flex-1 flex-col gap-3 p-5">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0"><h2 class="truncate font-semibold text-slate-900"><?= e($t['name']) ?></h2>
          <p class="text-xs text-slate-500"><?= e(DocTemplate::TYPES[$t['doc_type']] ?? '') ?> · <?= e(DocTemplate::FONTS[$c['font']] ?? '') ?><?= $usage[(int) $t['id']] ? ' · ' . $usage[(int) $t['id']] . ' Dokument(e)' : '' ?></p></div>
        <span class="size-5 shrink-0 rounded-full ring-2 ring-white shadow" style="background:<?= e($acc) ?>" data-tip="Akzentfarbe <?= e($acc) ?>"></span>
      </div>
      <div class="flex flex-wrap gap-1.5">
        <?php if ($t['is_default_offer']): ?><span class="badge badge-brand"><i class="bx bx-check"></i>Standard Offerten</span><?php endif; ?>
        <?php if ($t['is_default_invoice']): ?><span class="badge badge-brand"><i class="bx bx-check"></i>Standard Rechnungen</span><?php endif; ?>
      </div>
      <div class="mt-auto flex flex-wrap items-center gap-1.5 border-t border-slate-100 pt-3">
        <a href="<?= e(url('admin/doc-templates/' . $t['id'])) ?>" class="btn btn-secondary btn-sm"><i class="bx bx-edit"></i>Bearbeiten</a>
        <a href="<?= e(url('admin/doc-templates/new?copy=' . $t['id'])) ?>" class="btn btn-ghost btn-sm" data-tip="Als Kopie anlegen"><i class="bx bx-copy"></i></a>
        <div class="relative ml-auto" data-dropdown>
          <button type="button" class="btn btn-ghost btn-icon" data-dropdown-toggle aria-haspopup="true" aria-expanded="false" aria-label="Weitere Aktionen"><i class="bx bx-dots-vertical-rounded text-xl"></i></button>
          <div class="dropdown-menu right-0 bottom-full mb-1" role="menu">
            <?php foreach (['offer' => 'Offerten', 'invoice' => 'Rechnungen'] as $k => $lbl):
              $col = $k === 'offer' ? 'is_default_offer' : 'is_default_invoice';
              if ($t[$col] || !in_array($t['doc_type'], ['all', $k], true)) continue; ?>
              <form method="post" action="<?= e(url('admin/doc-templates/' . $t['id'] . '/default/' . $k)) ?>"><?= csrf_field() ?><button class="dropdown-item" role="menuitem"><i class="bx bx-star"></i>Standard für <?= $lbl ?></button></form>
            <?php endforeach; ?>
            <form method="post" action="<?= e(url('admin/doc-templates/' . $t['id'] . '/delete')) ?>" data-confirm="Vorlage „<?= e($t['name']) ?>“ löschen? Dokumente damit verwenden danach die Standardvorlage." data-confirm-label="Löschen"><?= csrf_field() ?><button class="dropdown-item text-rose-600" role="menuitem"><i class="bx bx-trash !text-rose-500"></i>Löschen</button></form>
          </div>
        </div>
      </div>
    </div>
  </article>
  <?php endforeach; ?>
</div>
<p class="mt-6 flex gap-2 text-sm text-slate-500"><i class="bx bx-info-circle mt-0.5 text-lg text-slate-400"></i><span>Eine Vorlage wählen Sie beim Erstellen einer Offerte aus (unter «Texte»); Rechnungen übernehmen sie von der Offerte und lassen sich im Entwurf umstellen. Der Zahlteil der QR-Rechnung bleibt wegen der Schweizer Vorgaben immer unverändert.</span></p>
