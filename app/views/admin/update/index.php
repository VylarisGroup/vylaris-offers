<?php
$title = 'Update';
$subtitle = 'Neue Versionen aus Ihrem GitHub-Repository (Releases) einspielen – mit Sicherung und Rückgängig-Funktion.';
include APP_DIR . '/views/partials/page-header.php';
$repo = UpdateService::repo();
$avail = UpdateService::available();
$checked = (int) Settings::get('update_checked_at', '0');
$err = (string) Settings::get('update_error', '');
$hasToken = (string) Settings::get('update_token', '') !== '';
$fmt = static fn (int $t) => $t ? date('d.m.Y H:i', $t) : '–';
?>
<div class="grid gap-6 xl:grid-cols-3">
  <div class="space-y-6 xl:col-span-2">
    <section class="reveal card p-5 sm:p-6">
      <div class="flex flex-wrap items-center gap-3">
        <span class="badge <?= $avail ? '!bg-amber-50 !text-amber-700' : '!bg-emerald-50 !text-emerald-700' ?>"><?= $avail ? 'Update verfügbar' : ($repo === '' ? 'Nicht eingerichtet' : 'Aktuell') ?></span>
        <span class="text-sm text-slate-600">Installiert: <strong class="font-mono"><?= e(APP_VERSION) ?></strong><?php if ($latest): ?> · Neueste: <strong class="font-mono"><?= e($latest['version']) ?></strong><?php endif; ?></span>
      </div>
      <?php if ($err !== ''): ?><p class="mt-3 rounded-xl bg-rose-50 px-3.5 py-2.5 text-sm text-rose-800 ring-1 ring-rose-200"><i class="bx bx-error-circle align-middle"></i> <?= e($err) ?></p><?php endif; ?>
      <?php if (!$zipOk): ?><p class="mt-3 rounded-xl bg-rose-50 px-3.5 py-2.5 text-sm text-rose-800 ring-1 ring-rose-200">Die PHP-Erweiterung «zip» fehlt – in Plesk unter PHP-Einstellungen aktivieren.</p><?php endif; ?>
      <?php if ($avail && $latest): ?>
        <div class="mt-4 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70">
          <div class="flex items-baseline justify-between gap-3"><h2 class="text-sm font-semibold text-slate-900"><?= e($latest['name'] ?: ('Version ' . $latest['version'])) ?></h2>
            <?php if ($latest['published']): ?><span class="text-xs text-slate-400"><?= e(date('d.m.Y', strtotime($latest['published']))) ?></span><?php endif; ?></div>
          <?php if ($latest['notes'] !== ''): ?><pre class="mt-2 max-h-56 overflow-auto whitespace-pre-wrap font-sans text-[13px] text-slate-600"><?= e($latest['notes']) ?></pre><?php endif; ?>
          <?php if (!UpdateService::sameMajor($latest['version'], APP_VERSION)): ?><p class="mt-2 text-xs text-amber-700"><i class="bx bx-info-circle align-middle"></i> Hauptversions-Sprung – Release-Hinweise lesen. Die Automatik installiert so ein Update nicht.</p><?php endif; ?>
        </div>
      <?php endif; ?>
      <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
        <?php if ($avail): ?>
        <form method="post" action="<?= e(url('admin/update/install')) ?>" data-confirm="Version <?= e($latest['version']) ?> jetzt installieren? Vorher wird automatisch eine Sicherung (Datenbank und Programmdateien) erstellt."><?= csrf_field() ?>
          <button class="btn btn-primary"><i class="bx bx-cloud-download"></i>Version <?= e($latest['version']) ?> installieren</button></form>
        <?php endif; ?>
        <?php if ($repo !== ''): ?><form method="post" action="<?= e(url('admin/update/check')) ?>"><?= csrf_field() ?><button class="btn btn-secondary"><i class="bx bx-refresh"></i>Jetzt prüfen</button></form><?php endif; ?>
        <span class="text-xs text-slate-400">Zuletzt geprüft: <?= e($fmt($checked)) ?></span>
      </div>
    </section>

    <form method="post" action="<?= e(url('admin/update')) ?>" class="reveal card p-5 sm:p-6"><?= csrf_field() ?>
      <h2 class="text-base font-semibold text-slate-900">GitHub-Repository</h2>
      <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2"><label class="label">Repository</label><input class="input font-mono text-[13px]" name="update_repo" value="<?= e($repo) ?>" placeholder="<?= e(UpdateService::DEFAULT_REPO) ?>" autocomplete="off" spellcheck="false">
          <p class="hint">Updates stammen aus dem neuesten <strong>Release</strong> (Tag z. B. <code>v1.1.0</code>). Vorabversionen und Entwürfe werden ignoriert. Hängt am Release eine <code>.zip</code>-Datei an, wird diese verwendet, sonst der Quellcode des Tags. Optional prüft eine Datei <code>&lt;name&gt;.zip.sha256</code> die Integrität.</p></div>
        <div class="sm:col-span-2"><label class="label">Zugriffs-Token <span class="font-normal text-slate-400">(nur bei privatem Repository)</span></label>
          <input class="input font-mono text-[13px]" type="password" name="update_token" value="" placeholder="<?= $hasToken ? '•••••••• (gespeichert – leer lassen, um es zu behalten)' : 'github_pat_…' ?>" autocomplete="new-password" spellcheck="false">
          <p class="hint">GitHub → Settings → Developer settings → <em>Fine-grained personal access tokens</em>: nur dieses Repository, Berechtigung <em>Contents: Read-only</em>. Das Token wird verschlüsselt gespeichert und nur an GitHub gesendet.</p>
          <?php if ($hasToken): ?><label class="mt-1 flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" class="size-4 rounded accent-indigo-600" name="update_token_remove" value="1"> Gespeichertes Token entfernen</label><?php endif; ?></div>
        <label class="flex cursor-pointer items-start gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70 sm:col-span-2"><input type="checkbox" class="mt-0.5 size-4 rounded accent-indigo-600" name="update_auto" value="1" <?= Settings::get('update_auto', '0') === '1' ? 'checked' : '' ?>>
          <span><span class="block text-sm font-medium text-slate-900">Updates automatisch installieren</span><span class="block text-xs text-slate-500">Der Cronjob (<code>cron.php</code>) installiert neue Versionen derselben Hauptversion selbstständig, mit Sicherung. Hauptversions-Sprünge (z. B. 1.x → 2.0) immer manuell. Ohne Cronjob erfolgt keine Automatik.</span></span></label>
      </div>
      <div class="mt-5 flex justify-end"><button class="btn btn-primary"><i class="bx bx-save"></i>Speichern &amp; prüfen</button></div>
    </form>
  </div>

  <aside class="space-y-6">
    <section class="reveal card p-5 sm:p-6">
      <h2 class="text-sm font-semibold text-slate-900"><i class="bx bx-history align-middle text-slate-400"></i> Letztes Update</h2>
      <?php if ($last): ?>
        <p class="mt-2 text-sm text-slate-600"><?= !empty($last['rollback']) ? 'Zurückgesetzt auf' : 'Aktualisiert auf' ?> <strong class="font-mono"><?= e((string) $last['to']) ?></strong> (von <span class="font-mono"><?= e((string) $last['from']) ?></span>)<br><span class="text-xs text-slate-400"><?= e($fmt((int) $last['at'])) ?> · <?= e((string) ($last['by'] ?? '')) ?></span></p>
      <?php else: ?><p class="mt-2 text-sm text-slate-500">Noch kein Update installiert.</p><?php endif; ?>
      <?php if ($backups): ?>
        <p class="mt-4 text-xs font-semibold uppercase tracking-wider text-slate-400">Sicherungen</p>
        <ul class="mt-1 space-y-1 text-sm text-slate-600"><?php foreach ($backups as $b): ?><li>Version <span class="font-mono"><?= e($b['version']) ?></span> · <?= e(date('d.m.Y H:i', $b['time'])) ?><?= $b['db'] ? ' · mit Datenbank' : '' ?></li><?php endforeach; ?></ul>
        <form method="post" action="<?= e(url('admin/update/rollback')) ?>" class="mt-4" data-confirm="Programmdateien auf Version <?= e($backups[0]['version']) ?> zurücksetzen? Die Datenbank bleibt unverändert."><?= csrf_field() ?>
          <button class="btn btn-secondary btn-sm"><i class="bx bx-undo"></i>Auf Version <?= e($backups[0]['version']) ?> zurücksetzen</button></form>
        <p class="mt-2 text-xs text-slate-400">Die Datenbank-Sicherungen liegen in <code>storage/update/</code> (nicht öffentlich erreichbar).</p>
      <?php endif; ?>
    </section>
    <section class="reveal card p-5 sm:p-6 text-sm text-slate-600">
      <h2 class="text-sm font-semibold text-slate-900"><i class="bx bx-shield-quarter align-middle text-slate-400"></i> Sicherheit</h2>
      <p class="mt-2 text-xs leading-relaxed">Wer Zugriff auf das Repository oder das Release hat, kann Programmcode einspielen. Schützen Sie das GitHub-Konto mit 2FA und verwenden Sie ein schreibgeschütztes Token. Unverändert bleiben <code>app/config.php</code>, <code>storage/</code> und die Daten.</p>
    </section>
  </aside>
</div>
