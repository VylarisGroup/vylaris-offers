<?php
$title = 'Audit-Log';
$qs = static fn (array $o = []) => http_build_query(array_filter(array_merge(['action' => $action, 'q' => $q], $o), static fn ($v) => $v !== '' && $v !== null));
?>
<?php $subtitle = 'Sicherheitsrelevante Ereignisse – ohne Passwörter und Geschäftsinhalte. Einträge werden nach der Aufbewahrungsfrist automatisch gelöscht.'; include APP_DIR . '/views/partials/page-header.php'; ?>


<form method="get" class="reveal card mb-4 flex flex-col gap-3 p-4 sm:flex-row sm:items-end">
  <div class="sm:w-64"><label class="label" for="a-action">Ereignis</label>
    <select class="input" id="a-action" name="action"><option value="">Alle</option>
      <?php foreach (Audit::LABELS as $k => $l): ?><option value="<?= e($k) ?>"<?= $action === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
    </select></div>
  <div class="flex-1"><label class="label" for="a-q">Suche</label><input class="input" id="a-q" name="q" value="<?= e($q) ?>" placeholder="Benutzer, Detail …"></div>
  <button class="btn btn-secondary"><i class="bx bx-search"></i>Filtern</button>
</form>

<div class="reveal card overflow-x-auto">
  <table class="w-full text-left text-sm">
    <thead class="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400"><tr><th class="px-4 py-3">Zeit</th><th class="px-4 py-3">Ereignis</th><th class="px-4 py-3">Benutzer</th><th class="px-4 py-3">Detail</th><th class="px-4 py-3">IP</th></tr></thead>
    <tbody class="divide-y divide-slate-100">
    <?php foreach ($rows as $r): $bad = in_array($r['action'], ['login_failed', '2fa_failed'], true); ?>
      <tr>
        <td class="whitespace-nowrap px-4 py-2.5 text-slate-500"><?= fmt_datetime($r['created_at']) ?></td>
        <td class="px-4 py-2.5"><span class="badge<?= $bad ? ' !bg-rose-50 !text-rose-700' : '' ?>"><?= e(Audit::LABELS[$r['action']] ?? $r['action']) ?></span></td>
        <td class="px-4 py-2.5 text-slate-700"><?= e($r['user_label'] ?? '–') ?></td>
        <td class="px-4 py-2.5 text-slate-600"><?= e(trim(($r['entity'] ? $r['entity'] . ($r['entity_id'] ? ' #' . $r['entity_id'] : '') . ' · ' : '') . ($r['detail'] ?? ''))) ?></td>
        <td class="whitespace-nowrap px-4 py-2.5 text-slate-400"><?= e($r['ip'] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">Keine Einträge.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php if ($pages > 1): ?>
<div class="mt-4 flex items-center justify-between text-sm text-slate-500">
  <span><?= $total ?> Einträge · Seite <?= $page ?> von <?= $pages ?></span>
  <span class="flex gap-2">
    <?php if ($page > 1): ?><a class="btn btn-secondary btn-sm" href="?<?= e($qs(['page' => $page - 1])) ?>">Zurück</a><?php endif; ?>
    <?php if ($page < $pages): ?><a class="btn btn-secondary btn-sm" href="?<?= e($qs(['page' => $page + 1])) ?>">Weiter</a><?php endif; ?>
  </span>
</div>
<?php endif; ?>
