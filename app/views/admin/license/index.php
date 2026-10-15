<?php
$title = 'Lizenz';
$subtitle = 'Lizenzschlüssel, Status und freigeschaltete Funktionen. Geprüft wird gegen ' . e(parse_url(License::server(), PHP_URL_HOST) ?: License::server()) . '.';
include APP_DIR . '/views/partials/page-header.php';
$ok = $ev['mode'] === 'full';
$tone = $ev['status'] === 'active' ? 'emerald' : ($ok ? 'amber' : 'rose');
$labels = ['active' => 'Aktiv', 'offline' => 'Offline-Lizenz', 'grace' => 'Karenzfrist', 'expired' => 'Abgelaufen', 'blocked' => 'Gesperrt', 'unlicensed' => 'Nicht lizenziert',
    'unverified' => 'Nicht geprüft', 'not_found' => 'Unbekannter Schlüssel', 'wrong_product' => 'Falsches Produkt', 'not_activated' => 'Nicht aktiviert', 'device_limit' => 'Gerätelimit', 'unreachable' => 'Server nicht erreichbar'];
$fmtTs = static fn (?int $t) => $t ? date('d.m.Y H:i', $t) : '–';
?>
<div class="grid gap-6 xl:grid-cols-3">
  <div class="space-y-6 xl:col-span-2">
    <section class="reveal card p-5 sm:p-6">
      <div class="flex flex-wrap items-center gap-3">
        <span class="badge !bg-<?= $tone ?>-50 !text-<?= $tone ?>-700"><?= e($labels[$ev['status']] ?? $ev['status']) ?></span>
        <?php if ($ev['trial']): ?><span class="badge">Testlizenz</span><?php endif; ?>
        <span class="text-sm text-slate-600"><?= e($ev['message']) ?></span>
      </div>
      <?php if (!$ok): ?><p class="mt-3 rounded-xl bg-rose-50 px-3.5 py-2.5 text-sm text-rose-800 ring-1 ring-rose-200"><i class="bx bx-lock-alt align-middle"></i> <strong>Lesemodus:</strong> Offerten, Aufträge und Rechnungen bleiben les- und exportierbar (inkl. Datenschutz-Auskunft und Backup). Neue Einträge, Änderungen, Versand und die Annahme von Offerten sind gesperrt.</p><?php endif; ?>
      <dl class="mt-5 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
        <div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Produkt</dt><dd class="mt-0.5 text-slate-800"><?= e(($ev['product_name'] ?: License::product())) ?></dd></div>
        <div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Tarif</dt><dd class="mt-0.5 text-slate-800"><?= e($ev['plan'] ?: '–') ?></dd></div>
        <div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Gültig bis</dt><dd class="mt-0.5 text-slate-800"><?= $ev['expires'] ? e(date('d.m.Y', $ev['expires'])) : ($ev['checked'] ? 'unbegrenzt' : '–') ?></dd></div>
        <div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Letzte Prüfung</dt><dd class="mt-0.5 text-slate-800"><?= e($fmtTs($ev['checked'])) ?><?= !empty($st['net_error']) ? ' <span class="text-rose-600">(Server nicht erreichbar)</span>' : '' ?></dd></div>
        <div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Geräte</dt><dd class="mt-0.5 text-slate-800"><?= $ev['devices'] !== null ? e($ev['devices'] . ' von ' . $ev['max_devices']) : '–' ?></dd></div>
        <div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Geräte-ID dieser Installation</dt><dd class="mt-0.5 break-all font-mono text-[13px] text-slate-800"><?= e($hwid) ?></dd></div>
        <?php if (!empty($st['offline_at'])): ?><div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Offline-Lizenz geholt</dt><dd class="mt-0.5 text-slate-800"><?= e($fmtTs((int) $st['offline_at'])) ?></dd></div><?php endif; ?>
      </dl>
      <div class="mt-5 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
        <?php foreach (['check' => ['bx-refresh', 'Jetzt prüfen'], 'activate' => ['bx-power-off', 'Aktivieren'], 'offline' => ['bx-download', 'Offline-Lizenz holen']] as $a => [$ic, $lb]): ?>
          <form method="post" action="<?= e(url('admin/license/' . $a)) ?>"><?= csrf_field() ?><button class="btn btn-secondary"><i class="bx <?= $ic ?>"></i><?= e($lb) ?></button></form>
        <?php endforeach; ?>
        <form method="post" action="<?= e(url('admin/license/deactivate')) ?>" data-confirm="Dieses Gerät abmelden? Der Geräteplatz wird frei, danach läuft das System im Lesemodus, bis es erneut aktiviert wird." data-confirm-label="Abmelden"><?= csrf_field() ?><button class="btn btn-ghost text-rose-600"><i class="bx bx-log-out"></i>Gerät abmelden</button></form>
      </div>
    </section>

    <form method="post" action="<?= e(url('admin/license')) ?>" class="reveal card p-5 sm:p-6"><?= csrf_field() ?>
      <h2 class="text-sm font-semibold text-slate-900"><i class="bx bx-key align-middle text-slate-400"></i> Lizenzschlüssel</h2>
      <div class="mt-3 flex flex-col gap-3 sm:flex-row">
        <input class="input flex-1 font-mono" name="license_key" value="<?= e(License::key()) ?>" placeholder="XX-XXXX-XXXX-XXXX" autocomplete="off" spellcheck="false">
        <button class="btn btn-primary"><i class="bx bx-check-shield"></i>Speichern &amp; aktivieren</button>
      </div>
      <p class="hint">Der Schlüssel wird verschlüsselt gespeichert. An den Lizenzserver gehen Schlüssel, Geräte-ID (Domain + Installations-ID), Geräte-Name und Ihre IP-Adresse.</p>
    </form>

    <details class="reveal card p-5 sm:p-6" <?= License::pemToRaw(License::publicKey()) === null ? 'open' : '' ?>>
      <summary class="cursor-pointer text-sm font-semibold text-slate-900"><i class="bx bx-cog align-middle text-slate-400"></i> Erweitert</summary>
      <form method="post" action="<?= e(url('admin/license')) ?>" class="mt-4 grid gap-4 sm:grid-cols-2"><?= csrf_field() ?>
        <input type="hidden" name="advanced" value="1"><input type="hidden" name="license_key" value="<?= e(License::key()) ?>">
        <div><label class="label">Lizenzserver</label><input class="input font-mono text-[13px]" name="license_server" value="<?= e(License::server()) ?>"></div>
        <div><label class="label">Produkt-Kennung</label><input class="input font-mono text-[13px]" name="license_product" value="<?= e(License::product()) ?>"><p class="hint">Muss der Kennung des Produkts im Lizenz-Panel entsprechen.</p></div>
        <div class="sm:col-span-2"><label class="label">Öffentlicher Schlüssel (Ed25519, PEM)</label>
          <textarea class="input font-mono text-[12px]" rows="5" name="license_public_key" placeholder="-----BEGIN PUBLIC KEY-----&#10;…&#10;-----END PUBLIC KEY-----"><?= e(License::publicKey()) ?></textarea>
          <p class="hint">Standardmässig ist der Schlüssel des Lizenzservers fest eingebaut; nur bei einem Schlüsselwechsel ändern. Alle Serverantworten werden damit auf Echtheit geprüft.</p></div>
        <div><label class="label">Karenzfrist ohne Server (Tage)</label><input class="input" type="number" min="0" max="14" name="license_grace_days" value="<?= e(Settings::get('license_grace_days', '3')) ?>"><p class="hint">Gilt, wenn der Server nicht erreichbar ist und keine Offline-Lizenz mehr gültig ist.</p></div>
        <div class="flex items-end justify-end gap-2 sm:col-span-2">
          <button class="btn btn-primary"><i class="bx bx-save"></i>Speichern</button>
        </div>
      </form>
      <form method="post" action="<?= e(url('admin/license/fetch-key')) ?>" class="mt-2" data-confirm="Den Schlüssel einmalig vom Server übernehmen? Sicherer ist es, ihn aus dem Lizenz-Panel einzufügen – ein Angreifer im Netzwerk könnte sonst einen eigenen Schlüssel unterschieben." data-confirm-label="Übernehmen"><?= csrf_field() ?>
        <button class="btn btn-ghost btn-sm"><i class="bx bx-cloud-download"></i>Öffentlichen Schlüssel einmalig vom Server laden</button></form>
    </details>
  </div>

  <aside class="space-y-6">
    <section class="reveal card p-5 sm:p-6">
      <h2 class="text-sm font-semibold text-slate-900"><i class="bx bx-shield-quarter align-middle text-slate-400"></i> Freigeschaltete Funktionen</h2>
      <p class="mt-1 text-xs text-slate-500">Scopes aus dem Feld <code>features</code> der Lizenz. Offerten, Aufträge, Kunden und Einstellungen sind immer enthalten.</p>
      <ul class="mt-4 space-y-3">
        <?php foreach ($scopes as $id => $sc): ?>
          <li class="flex gap-3"><i class="bx <?= $sc['on'] ? 'bx-check-circle text-emerald-600' : 'bx-x-circle text-slate-300' ?> mt-0.5 text-xl"></i>
            <span class="min-w-0"><span class="block text-sm font-medium <?= $sc['on'] ? 'text-slate-900' : 'text-slate-400' ?>"><?= e($sc['label']) ?> <code class="ml-1 rounded bg-slate-100 px-1 text-[11px] font-normal text-slate-500"><?= e($id) ?></code></span>
              <span class="block text-xs text-slate-500"><?= e($sc['desc']) ?></span></span></li>
        <?php endforeach; ?>
      </ul>
    </section>
  </aside>
</div>
