<?php
/** @var array $checks @var array $summary @var string $token */
$title = 'Systemstatus';
$subtitle = 'Automatische Prüfung von Umgebung, Sicherheit, E-Mail, Datensicherung und Rechnungsdaten.';
$actions = '<a href="' . e(url('admin/health')) . '" class="btn btn-secondary"><i class="bx bx-refresh"></i>Erneut prüfen</a>';
include APP_DIR . '/views/partials/page-header.php';
[$sl, $sc, $si] = HealthService::LEVEL[$summary['level']];
$groups = [];
foreach ($checks as $c) {
    $groups[$c['group']][] = $c;
}
$ping = url('health') . '?token=' . $token;
?>
<div class="reveal mb-6 flex items-center gap-4 rounded-2xl p-5 ring-1 <?= ['ok' => 'bg-emerald-50 ring-emerald-200', 'warn' => 'bg-amber-50 ring-amber-200', 'fail' => 'bg-rose-50 ring-rose-200'][$summary['level']] ?>">
  <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-white text-3xl shadow-sm <?= ['ok' => 'text-emerald-600', 'warn' => 'text-amber-600', 'fail' => 'text-rose-600'][$summary['level']] ?>"><i class="bx <?= $si ?>"></i></span>
  <div><p class="font-semibold text-slate-900"><?= ['ok' => 'Alles in Ordnung', 'warn' => 'Es gibt Hinweise', 'fail' => 'Es gibt Probleme, die Ihre Aufmerksamkeit brauchen'][$summary['level']] ?></p>
    <p class="text-sm text-slate-600"><?= $summary['ok'] ?> in Ordnung · <?= $summary['warn'] ?> Hinweis(e) · <?= $summary['fail'] ?> Problem(e)</p></div>
</div>

<div class="grid gap-6 xl:grid-cols-3">
  <div class="space-y-6 xl:col-span-2">
    <?php foreach ($groups as $g => $list): ?>
    <section class="reveal card">
      <div class="card-header"><h2 class="card-title"><?= e($g) ?></h2></div>
      <ul class="divide-y divide-slate-100">
        <?php foreach ($list as $c): [$l, $col, $ic] = HealthService::LEVEL[$c['level']]; ?>
        <li class="flex items-start gap-3 px-5 py-3.5 sm:px-6">
          <i class="bx <?= $ic ?> mt-0.5 text-xl <?= ['ok' => 'text-emerald-500', 'warn' => 'text-amber-500', 'fail' => 'text-rose-500'][$c['level']] ?>"></i>
          <div class="min-w-0 flex-1"><p class="text-sm font-medium text-slate-900"><?= e($c['label']) ?></p>
            <p class="text-sm text-slate-600"><?= e($c['detail']) ?></p>
            <?php if ($c['hint'] !== ''): ?><p class="mt-1 text-xs text-slate-500"><i class="bx bx-bulb align-middle text-amber-500"></i> <?= e($c['hint']) ?></p><?php endif; ?></div>
          <span class="badge badge-<?= $col === 'emerald' ? 'emerald' : ($col === 'amber' ? 'amber' : 'rose') ?>"><?= e($l) ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endforeach; ?>
  </div>

  <aside class="space-y-6">
    <section class="reveal card p-5 sm:p-6">
      <h2 class="text-sm font-semibold text-slate-900"><i class="bx bx-pulse align-middle text-slate-400"></i> Monitoring</h2>
      <p class="mt-1 text-xs text-slate-500">Mit einem Überwachungsdienst (z. B. UptimeRobot) werden Sie benachrichtigt, sobald ein Problem auftritt. Der Endpunkt liefert nur den Status je Prüfung – keine Details – und antwortet bei Problemen mit HTTP 503.</p>
      <?php if ($token === ''): ?>
        <form method="post" action="<?= e(url('admin/health/token')) ?>" class="mt-3"><?= csrf_field() ?><button class="btn btn-secondary w-full"><i class="bx bx-key"></i>Monitoring-Adresse erzeugen</button></form>
      <?php else: ?>
        <div class="mt-3 flex items-center gap-2 rounded-xl bg-slate-50 p-1.5 pl-3 ring-1 ring-slate-200"><code class="min-w-0 flex-1 truncate text-xs text-slate-600"><?= e($ping) ?></code><button type="button" class="btn btn-secondary btn-icon size-8" data-copy="<?= e($ping) ?>" data-tip="Adresse kopieren"><i class="bx bx-copy"></i></button></div>
        <div class="mt-3 flex gap-2">
          <form method="post" action="<?= e(url('admin/health/token')) ?>" class="flex-1" data-confirm="Neue Adresse erzeugen? Die bisherige wird ungültig." data-confirm-label="Erzeugen"><?= csrf_field() ?><button class="btn btn-secondary btn-sm w-full"><i class="bx bx-refresh"></i>Erneuern</button></form>
          <form method="post" action="<?= e(url('admin/health/token')) ?>" class="flex-1" data-confirm="Monitoring-Adresse widerrufen?" data-confirm-label="Widerrufen"><?= csrf_field() ?><input type="hidden" name="revoke" value="1"><button class="btn btn-ghost btn-sm w-full text-rose-600"><i class="bx bx-block"></i>Widerrufen</button></form>
        </div>
      <?php endif; ?>
    </section>
  </aside>
</div>
