<?php
$title = 'Berichte';
$subtitle = 'Auswertung nach Rechnungsdatum (offene und bezahlte Rechnungen). Beträge in ' . e((string) Settings::get('currency', 'CHF')) . '.';
include APP_DIR . '/views/partials/page-header.php';
$t = $sum['total'];
$cur = (string) Settings::get('currency', 'CHF');
$presets = [
    'Dieses Jahr' => [date('Y-01-01'), date('Y-m-d')],
    'Letztes Jahr' => [(date('Y') - 1) . '-01-01', (date('Y') - 1) . '-12-31'],
    'Dieses Quartal' => [date('Y-m-d', strtotime(date('Y') . '-' . (floor((date('n') - 1) / 3) * 3 + 1) . '-01')), date('Y-m-d')],
    'Letzte Abrechnungsperiode' => (static function (): array {
        $per = (string) Settings::get('mwst_period', 'quarter');
        $m = (int) date('n'); $y = (int) date('Y');
        $len = ['quarter' => 3, 'semester' => 6, 'year' => 12][$per] ?? 3;
        $start = intdiv($m - 1, $len) * $len + 1 - $len;   // Beginn der letzten abgeschlossenen Periode
        $ys = $y;
        if ($start < 1) { $start += 12; $ys--; }
        $from = sprintf('%04d-%02d-01', $ys, $start);
        return [$from, date('Y-m-t', strtotime($from . ' +' . ($len - 1) . ' months'))];
    })(),
    'Letzte 12 Monate' => [date('Y-m-d', strtotime('-12 months +1 day')), date('Y-m-d')],
];
$q = http_build_query(['from' => $from, 'to' => $to]);
$maxM = max(1.0, ...array_map(static fn($m) => $m['gross'], array_values($sum['months'] ?: [['gross' => 1.0]])));
?>
<form method="get" class="reveal card mb-6 flex flex-col gap-3 p-4 lg:flex-row lg:items-end">
  <div class="grid flex-1 grid-cols-2 gap-3 lg:max-w-md"><div><label class="label">Von</label><input class="input" type="date" name="from" value="<?= e($from) ?>"></div><div><label class="label">Bis</label><input class="input" type="date" name="to" value="<?= e($to) ?>"></div></div>
  <button class="btn btn-primary"><i class="bx bx-refresh"></i>Anzeigen</button>
  <div class="flex flex-wrap gap-1 lg:ml-auto"><?php foreach ($presets as $l => [$f, $tt]): ?><a class="btn btn-ghost btn-sm" href="?from=<?= e($f) ?>&to=<?= e($tt) ?>"><?= e($l) ?></a><?php endforeach; ?></div>
</form>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
  <?php foreach ([
    ['Fakturiert (brutto)', money($t['gross']), $t['count'] . ' Rechnungen', 'bx-receipt', 'bg-sky-50 text-sky-600'],
    ['davon netto', money($t['net']), 'Steuern: ' . money($t['tax']), 'bx-calculator', 'bg-violet-50 text-violet-600'],
    ['Bezahlt', money($t['paid']), $t['gross'] > 0 ? round($t['paid'] / $t['gross'] * 100) . ' % des Fakturierten' : '–', 'bx-check-circle', 'bg-emerald-50 text-emerald-600'],
    ['Noch offen (aus Zeitraum)', money($t['open']), 'Stand heute siehe unten', 'bx-time-five', 'bg-amber-50 text-amber-600'],
  ] as $i => [$l, $v, $s, $ic, $cls]): ?>
    <div class="reveal reveal-delay-<?= min(3, $i) ?> card p-5"><span class="kpi-icon <?= $cls ?>"><i class="bx <?= $ic ?>"></i></span>
      <p class="mt-4 text-sm font-medium text-slate-500"><?= e($l) ?></p><p class="mt-1 text-2xl font-bold tracking-tight text-slate-900 tabular"><?= e($v) ?></p><p class="mt-1 text-xs text-slate-400"><?= e($s) ?></p></div>
  <?php endforeach; ?>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-3">
  <section class="reveal card xl:col-span-2">
    <div class="card-header"><h2 class="card-title"><i class="bx bx-bar-chart-alt-2"></i>Umsatz pro Monat</h2></div>
    <?php if (!$sum['months']): ?><div class="empty-state py-10"><i class="bx bx-bar-chart"></i><p class="text-sm text-slate-500">Keine Rechnungen im Zeitraum.</p></div><?php else: ?>
    <div class="overflow-x-auto"><table class="table">
      <thead><tr><th>Monat</th><th class="text-right">Rechnungen</th><th class="text-right">Netto</th><th class="text-right">Steuer</th><th class="text-right">Brutto</th><th class="hidden w-40 sm:table-cell"></th></tr></thead>
      <tbody><?php foreach ($sum['months'] as $m => $r): ?>
        <tr><td class="font-medium"><?= e(date('m/Y', strtotime($m . '-01'))) ?></td><td class="text-right tabular"><?= $r['count'] ?></td><td class="text-right tabular"><?= money($r['net'], null, false) ?></td><td class="text-right tabular"><?= money($r['tax'], null, false) ?></td><td class="text-right font-semibold tabular"><?= money($r['gross'], null, false) ?></td>
          <td class="hidden sm:table-cell"><div class="h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-brand-600" style="width: <?= round($r['gross'] / $maxM * 100) ?>%"></div></div></td></tr>
      <?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
  </section>

  <section class="reveal card">
    <div class="card-header"><h2 class="card-title"><i class="bx bx-category"></i>Nach Produktbereich</h2></div>
    <ul class="divide-y divide-slate-100"><?php foreach ($sum['areas'] as $a => $r): ?>
      <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm sm:px-6"><span><?= e($a) ?><span class="block text-xs text-slate-400"><?= $r['count'] ?> Rechnungen</span></span><span class="font-semibold tabular"><?= money($r['gross']) ?></span></li>
    <?php endforeach; if (!$sum['areas']): ?><li class="px-6 py-6 text-sm text-slate-500">–</li><?php endif; ?></ul>
  </section>
</div>

<?php if (TaxService::liable()): $z = $ab['z']; ?>
<section class="reveal card mt-6">
  <div class="card-header"><h2 class="card-title"><i class="bx bx-file"></i>MWST-Abrechnung (Ziffern der ESTV)</h2><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/reports/export/abrechnung?' . $q)) ?>"><i class="bx bx-download"></i>CSV</a></div>
  <div class="overflow-x-auto"><table class="table"><thead><tr><th class="w-20">Ziff.</th><th>Bezeichnung</th><th class="text-right">Leistung</th><th class="text-right">Steuer</th></tr></thead><tbody>
    <?php foreach ([
      ['200', 'Total der vereinbarten bzw. vereinnahmten Entgelte (weltweit, ohne MWST)', 0], ['205', 'davon Leistungen mit Option (Art. 22 MWSTG)', 0],
      ['220', 'Von der Steuer befreite Leistungen, Ausfuhr (Art. 23)', 0], ['221', 'Leistungen im Ausland (Ort der Leistung im Ausland)', 0], ['225', 'Übertragung im Meldeverfahren (Art. 38)', 0],
      ['230', 'Von der Steuer ausgenommene Inlandleistungen (Art. 21)', 0], ['235', 'Entgeltsminderungen (Skonti, Rabatte, Gutschriften)', 0], ['280', 'Diverses (nicht unterstützt)', 0],
      ['289', 'Total Abzüge (Ziff. 220–280)', 1], ['299', 'Steuerbarer Gesamtumsatz (Ziff. 200 − 289)', 1],
    ] as [$k, $lbl, $strong]): if (in_array($k, ['205', '280'], true) && abs($z[$k]) < 0.005) { continue; } ?>
      <tr class="<?= $strong ? 'bg-slate-50 font-semibold' : '' ?>"><td class="font-mono text-xs text-slate-500"><?= $k ?></td><td><?= e($lbl) ?></td><td class="text-right tabular"><?= money($z[$k], null, false) ?></td><td></td></tr>
    <?php endforeach; ?>
    <?php foreach ($ab['tax_lines'] as $l): ?>
      <tr><td class="font-mono text-xs text-slate-500"><?= e((string) ($l['ziff'] ?? '–')) ?></td><td>Leistungen zum Satz <?= rate($l['rate']) ?><?= $ab['saldo'] ? ' <span class="text-xs text-slate-400">(Information – es gilt der Saldosteuersatz)</span>' : '' ?></td><td class="text-right tabular"><?= money($l['base'], null, false) ?></td><td class="text-right tabular"><?= $ab['saldo'] ? '–' : money($l['tax'], null, false) ?></td></tr>
    <?php endforeach; ?>
    <?php if ($ab['saldo']): ?>
      <tr><td class="font-mono text-xs text-slate-500">322/323</td><td>Saldosteuersatz <?= rate($ab['saldo']['rate']) ?> auf Brutto-Entgelte der steuerbaren Leistungen</td><td class="text-right tabular"><?= money($ab['saldo']['base'], null, false) ?></td><td class="text-right tabular"><?= money($ab['saldo']['tax'], null, false) ?></td></tr>
    <?php endif; ?>
    <tr class="bg-brand-50/60 font-semibold"><td class="font-mono text-xs text-slate-500">399</td><td>Total geschuldete Steuer</td><td></td><td class="text-right tabular"><?= money($ab['tax_total'], null, false) ?></td></tr>
  </tbody></table></div>
  <div class="space-y-1 border-t border-slate-100 px-5 py-3 text-xs text-slate-500 sm:px-6">
    <?php if (!$ab['reconciled']): ?><p class="font-medium text-amber-700"><i class="bx bx-error align-middle"></i> Abweichung: Ziff. 299 stimmt nicht mit der Summe der Leistungen je Satz überein (Rundung oder nicht zugeordnete Positionen). Bitte Belege prüfen.</p><?php endif; ?>
    <?php if (abs($ab['unclassified']) > 0.004): ?><p class="font-medium text-amber-700"><i class="bx bx-error align-middle"></i> Positionen ohne Steuerart: <?= money($ab['unclassified'], null, false) ?> sind im steuerbaren Umsatz enthalten, aber ohne Steuer. Prüfen Sie die Belege.</p><?php endif; ?>
    <?php if (abs($ab['passthrough']) > 0.004): ?><p>Durchlaufposten (nicht Teil des Entgelts, Art. 24 Abs. 6): <?= money($ab['passthrough'], null, false) ?></p><?php endif; ?>
    <p>Nicht enthalten: Vorsteuer (Ziff. 400 ff.), Bezugsteuer (Ziff. 382/383), Eigenverbrauch, Einlageentsteuerung, Margenbesteuerung, Steueranrechnungen. Diese tragen Sie in der ESTV-Abrechnung (SuisseTax) selbst ein. Hilfsmittel, keine Steuerberatung.</p>
  </div>
</section>
<?php elseif ($reg): $pct = min(100, $reg['pct']); ?>
<section class="reveal card mt-6 p-5 sm:p-6">
  <h2 class="card-title"><i class="bx bx-shield-quarter"></i>MWST-Eintragungsgrenze</h2>
  <p class="mt-2 text-sm text-slate-600">Ohne MWST-Pflicht darf keine MWST ausgewiesen werden. Die Pflicht beginnt, wenn der steuerbare Weltumsatz CHF 100'000 im Geschäftsjahr übersteigt (Art. 10 Abs. 2 MWSTG); dann ist die Eintragung bei der ESTV unverzüglich zu beantragen.</p>
  <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full <?= $reg['pct'] >= 100 ? 'bg-rose-500' : ($reg['pct'] >= 80 ? 'bg-amber-500' : 'bg-emerald-500') ?>" style="width: <?= round($pct) ?>%"></div></div>
  <p class="mt-2 text-sm text-slate-700 tabular"><?= money($reg['max']) ?> von <?= money($reg['limit']) ?> (<?= round($reg['pct']) ?> %) · Kalenderjahr <?= money($reg['turnover_year']) ?> · letzte 12 Monate <?= money($reg['turnover_12m']) ?></p>
</section>
<?php endif; ?>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
  <section class="reveal card">
    <div class="card-header"><h2 class="card-title"><i class="bx bx-receipt"></i>MWST / Steuer-Übersicht</h2><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/reports/export/vat?' . $q)) ?>"><i class="bx bx-download"></i>CSV</a></div>
    <table class="table"><thead><tr><th>Steuerart</th><th class="text-right">Satz</th><th class="text-right">Steuerbarer Umsatz</th><th class="text-right">Steuer</th></tr></thead><tbody>
      <?php foreach ($sum['vat'] as $v): ?><tr><td><?= e($v['label']) ?></td><td class="text-right tabular"><?= rate($v['rate']) ?></td><td class="text-right tabular"><?= money($v['base'], null, false) ?></td><td class="text-right font-semibold tabular"><?= money($v['tax'], null, false) ?></td></tr><?php endforeach; ?>
      <?php if (!$sum['vat']): ?><tr><td colspan="4" class="text-slate-500">–</td></tr><?php endif; ?>
    </tbody></table>
    <?php if (!empty($sum['saldo'])): ?>
    <div class="flex items-center justify-between gap-3 border-t border-slate-100 bg-amber-50/60 px-5 py-3 text-sm sm:px-6"><span>Saldosteuersatz <?= rate($sum['saldo']['rate']) ?> auf <?= money($sum['saldo']['base'], null, false) ?> (Brutto-Umsatz)</span><span class="font-semibold tabular"><?= money($sum['saldo']['tax']) ?></span></div>
    <?php endif; ?>
    <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-400 sm:px-6"><?= $sum['vat_method'] === 'received' ? 'Nach vereinnahmten Entgelten (Zahlungsdatum, Teilzahlungen anteilig)' : 'Nach vereinbarten Entgelten (Rechnungsdatum)' ?>; Schlussrechnungen abzüglich bereits verrechneter Anzahlungen. Kein Ersatz für die offizielle MWST-Abrechnung bei der ESTV.<?= TaxService::liable() ? '' : ' Sie sind als nicht MWST-pflichtig eingetragen.' ?></p>
  </section>

  <section class="reveal card">
    <div class="card-header"><h2 class="card-title"><i class="bx bx-trophy"></i>Top-Kunden</h2></div>
    <ul class="divide-y divide-slate-100"><?php foreach ($sum['customers'] as $cid => $r): ?>
      <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm sm:px-6"><a class="link" href="<?= e(url('admin/customers/' . $cid)) ?>"><?= e($r['name']) ?></a><span class="font-semibold tabular"><?= money($r['gross']) ?></span></li>
    <?php endforeach; if (!$sum['customers']): ?><li class="px-6 py-6 text-sm text-slate-500">–</li><?php endif; ?></ul>
  </section>
</div>

<section class="reveal card mt-6">
  <div class="card-header"><h2 class="card-title"><i class="bx bx-time-five"></i>Offene Posten (Stand heute)</h2></div>
  <div class="grid gap-3 p-5 sm:grid-cols-4 sm:p-6">
    <?php foreach ($recv['buckets'] as $k => [$l, $sumB, $cnt]): ?>
      <div class="rounded-xl p-4 ring-1 <?= $k === 'current' ? 'bg-slate-50 ring-slate-200' : ($k === 'd30' ? 'bg-amber-50 ring-amber-200' : 'bg-rose-50 ring-rose-200') ?>"><p class="text-xs font-medium text-slate-500"><?= e($l) ?></p><p class="mt-1 text-lg font-bold tabular text-slate-900"><?= money($sumB) ?></p><p class="text-xs text-slate-400"><?= $cnt ?> Rechnung(en)</p></div>
    <?php endforeach; ?>
  </div>
  <?php if ($recv['rows']): ?><div class="overflow-x-auto border-t border-slate-100"><table class="table"><thead><tr><th>Rechnung</th><th>Kunde</th><th>Fällig</th><th class="text-right">Überfällig</th><th class="text-right">Betrag</th></tr></thead><tbody>
    <?php foreach ($recv['rows'] as $r): ?><tr class="row-link" data-href="<?= e(url('admin/invoices/' . $r['id'])) ?>"><td><a class="link" href="<?= e(url('admin/invoices/' . $r['id'])) ?>"><?= e($r['number']) ?></a></td><td><?= e(customer_name($r)) ?></td><td><?= fmt_date($r['due_date']) ?></td><td class="text-right <?= $r['days_overdue'] ? 'font-medium text-rose-600' : 'text-slate-400' ?>"><?= $r['days_overdue'] ? $r['days_overdue'] . ' Tage' : '–' ?></td><td class="text-right font-semibold tabular"><?= money($r['open_amount'], $r['currency']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</section>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
  <section class="reveal card">
    <div class="card-header"><h2 class="card-title"><i class="bx bx-file"></i>Offerten im Zeitraum</h2></div>
    <ul class="divide-y divide-slate-100"><?php foreach ($offers['by_status'] as $s => [$cnt, $vol]): ?>
      <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm sm:px-6"><?= Status::badge('offer', $s) ?><span class="text-slate-500"><?= $cnt ?> · <span class="font-semibold text-slate-900 tabular"><?= money($vol) ?></span></span></li>
    <?php endforeach; ?></ul>
    <p class="border-t border-slate-100 px-5 py-3 text-sm text-slate-600 sm:px-6">Annahmequote: <strong><?= $offers['rate'] === null ? '–' : $offers['rate'] . ' %' ?></strong> <span class="text-xs text-slate-400">(angenommen / entschieden)</span></p>
  </section>

  <section class="reveal card">
    <div class="card-header"><h2 class="card-title"><i class="bx bx-download"></i>CSV-Export</h2></div>
    <div class="grid gap-2 p-5 sm:grid-cols-2 sm:p-6">
      <?php foreach (['invoices' => ['Rechnungen', 'bx-receipt'], 'orders' => ['Aufträge', 'bx-package'], 'offers' => ['Offerten', 'bx-file'], 'customers' => ['Kunden (alle)', 'bx-group']] as $k => [$l, $ic]): ?>
        <a class="btn btn-secondary justify-start" href="<?= e(url('admin/reports/export/' . $k . '?' . $q)) ?>"><i class="bx <?= $ic ?>"></i><?= $l ?></a>
      <?php endforeach; ?>
    </div>
    <p class="px-5 pb-5 text-xs text-slate-400 sm:px-6">Zeichensatz UTF-8, Trennzeichen „;“ – öffnet direkt in Excel und LibreOffice. Zeitraum gilt für Rechnungen, Aufträge und Offerten.</p>
  </section>
</div>
