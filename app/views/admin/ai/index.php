<?php
$title = 'KI-Assistent';
$subtitle = 'OpenAI-kompatibles Gateway anbinden und Positionen aus Links übernehmen';
include APP_DIR . '/views/partials/page-header.php';
?>
<div class="grid gap-6 xl:grid-cols-3">
  <form method="post" action="<?= e(url('admin/ai')) ?>" class="reveal card xl:col-span-2" autocomplete="off"><?= csrf_field() ?>
    <div class="card-header"><h2 class="card-title"><i class="bx bx-bot"></i>Verbindung</h2>
      <span class="badge <?= $ready ? 'badge-emerald' : 'badge-slate' ?>"><?= $ready ? 'aktiv' : 'inaktiv' ?></span></div>
    <div class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
      <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-800 sm:col-span-2"><input type="checkbox" class="checkbox" name="ai_enabled" value="1" <?= $s['ai_enabled'] === '1' ? 'checked' : '' ?>>KI-Funktionen aktivieren</label>
      <div class="sm:col-span-2"><label class="label" for="ai_base_url">Gateway-Adresse (Basis-URL)</label>
        <input class="input" id="ai_base_url" name="ai_base_url" value="<?= e($s['ai_base_url']) ?>" placeholder="https://gateway.example.com/v1" maxlength="300" inputmode="url">
        <p class="hint">Anfragen gehen an <code>&lt;Adresse&gt;/chat/completions</code> (OpenAI-Format, z. B. LiteLLM, OpenRouter, vLLM, Ollama, Azure-Gateway).</p></div>
      <div><label class="label" for="ai_model">Modellname</label>
        <input class="input" id="ai_model" name="ai_model" value="<?= e($s['ai_model']) ?>" placeholder="gpt-4o-mini" maxlength="120"></div>
      <div><label class="label" for="ai_api_key">API-Schlüssel</label>
        <input class="input" id="ai_api_key" type="password" name="ai_api_key" value="<?= $s['key_set'] ? '••••••••' : '' ?>" placeholder="optional" autocomplete="new-password">
        <?php if ($s['key_set']): ?><label class="mt-1.5 flex items-center gap-2 text-xs text-slate-500"><input type="checkbox" class="checkbox" name="ai_key_clear" value="1">Gespeicherten Schlüssel entfernen</label><?php endif; ?>
        <p class="hint">Wird verschlüsselt gespeichert. Leer lassen, wenn das Gateway keinen Schlüssel braucht.</p></div>
      <div><label class="label" for="ai_timeout">Zeitlimit (Sekunden)</label>
        <input class="input tabular" id="ai_timeout" name="ai_timeout" value="<?= e($s['ai_timeout']) ?>" inputmode="numeric" maxlength="3"></div>
      <label class="flex cursor-pointer items-start gap-2 self-end text-sm text-slate-700"><input type="checkbox" class="checkbox mt-0.5" name="ai_json_mode" value="1" <?= $s['ai_json_mode'] === '1' ? 'checked' : '' ?>><span>JSON-Modus verwenden <span class="block text-xs text-slate-500">Wird automatisch ausgelassen, wenn das Gateway ihn ablehnt.</span></span></label>
      <div class="sm:col-span-2"><label class="label" for="ai_hint">Zusätzliche Vorgaben (optional)</label>
        <textarea class="input" id="ai_hint" name="ai_hint" rows="2" maxlength="600" placeholder="z. B. Beschreibungen kurz und im Sie-Stil halten; Preise netto angeben."><?= e($s['ai_hint']) ?></textarea></div>
    </div>
    <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 p-4 sm:px-6">
      <button class="btn btn-secondary" name="test" value="1"><i class="bx bx-plug"></i>Speichern &amp; testen</button>
      <button class="btn btn-primary"><i class="bx bx-save"></i>Speichern</button>
    </div>
  </form>

  <div class="space-y-6">
    <section class="reveal card">
      <div class="card-header"><h2 class="card-title"><i class="bx bx-link"></i>Position aus Link</h2></div>
      <div class="space-y-2 p-5 text-sm text-slate-600 sm:px-6">
        <p>Im Offerten-Editor fügen Sie über <strong>«Aus Link»</strong> (oder durch Einfügen eines Links in das Titelfeld) eine Position hinzu. Die KI liest die Seite und übernimmt <strong>Titel, Beschreibung, Einheit, Preis und Hauptbild</strong>.</p>
        <ul class="list-disc space-y-1 pl-5 text-[13px] text-slate-500">
          <li>Es werden nur öffentliche Webseiten geladen (keine internen Adressen).</li>
          <li>Der Seitentext wird an Ihr Gateway gesendet – wählen Sie einen Anbieter, der zu Ihren Datenschutzvorgaben passt.</li>
          <li>Preise bitte prüfen: Angaben der Seite sind oft inkl. MWST und werden nicht umgerechnet.</li>
          <li>Seiten, die ihren Inhalt erst per JavaScript laden, lassen sich nicht auslesen.</li>
        </ul>
      </div>
    </section>
    <section class="reveal card">
      <div class="card-header"><h2 class="card-title"><i class="bx bx-history"></i>Letzte Importe</h2></div>
      <?php if ($imports): ?>
      <ul class="divide-y divide-slate-100"><?php foreach ($imports as $i): ?>
        <li class="px-5 py-2.5 text-xs sm:px-6"><span class="text-slate-400 tabular"><?= e(fmt_datetime($i['created_at'])) ?></span><span class="block truncate text-slate-700"><?= e((string) $i['detail']) ?></span></li>
      <?php endforeach; ?></ul>
      <?php else: ?><p class="p-5 text-sm text-slate-500 sm:px-6">Noch keine Importe.</p><?php endif; ?>
    </section>
  </div>
</div>
<script>
// Der «Speichern & testen»-Knopf wird beim Absenden deaktiviert; daher den Wert als verstecktes Feld mitsenden.
document.querySelectorAll('button[name="test"]').forEach(function (b) {
  b.addEventListener('click', function () {
    var h = document.createElement('input'); h.type = 'hidden'; h.name = 'test'; h.value = '1'; b.form.appendChild(h);
  });
});
</script>
