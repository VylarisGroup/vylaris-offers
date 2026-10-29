<?php
/** @var array $tokens @var array $hooks @var array $deliveries @var array $acc @var ?array $once */
$title = 'Integrationen';
$subtitle = 'API für Ihre Buchhaltungssoftware, Webhooks und Buchungsjournal.';
include APP_DIR . '/views/partials/page-header.php';
$base = rtrim(url(''), '/');
$evLabels = Webhooks::EVENTS;
?>
<?php if ($once): ?>
<div class="reveal mb-6 rounded-2xl bg-emerald-50 p-5 ring-1 ring-emerald-200">
  <p class="text-sm font-semibold text-emerald-900"><i class="bx bx-key align-middle"></i> <?= $once['type'] === 'token' ? 'API-Token' : 'Webhook-Secret' ?> für „<?= e($once['name']) ?>“</p>
  <p class="mt-1 text-sm text-emerald-800">Wird <strong>nur jetzt einmal</strong> angezeigt – bitte sofort sicher speichern.</p>
  <code class="mt-3 block select-all break-all rounded-xl bg-white p-3 text-sm text-slate-900 ring-1 ring-emerald-200"><?= e($once['value']) ?></code>
</div>
<?php endif; ?>

<div class="grid gap-6 lg:grid-cols-2">
  <section class="reveal card p-5 sm:p-6">
    <h2 class="text-sm font-semibold text-slate-900"><i class="bx bx-code-curly align-middle text-slate-400"></i> API (nur lesend)</h2>
    <p class="mt-1 text-sm text-slate-500">Basis-URL: <code><?= e($base) ?>/api/v1</code> · Header <code>Authorization: Bearer &lt;Token&gt;</code>. Endpunkte: <code>/ping</code>, <code>/invoices</code>, <code>/offers</code>, <code>/orders</code>, <code>/customers</code> (je auch <code>/{id}</code>). Filter: <code>status</code>, <code>from</code>, <code>to</code> (YYYY-MM-DD), <code>customer_id</code>, <code>page</code>, <code>per_page</code> (max. 100). Limit: 120 Anfragen/Minute.</p>
    <pre class="mt-3 overflow-x-auto rounded-xl bg-slate-900 p-3 text-xs text-slate-100">curl -H "Authorization: Bearer omk_…" \
  "<?= e($base) ?>/api/v1/invoices?from=<?= date('Y-m-01') ?>"</pre>
    <p class="mt-2 text-xs text-slate-400">Kundendaten (Name, Adresse, E-Mail) liefert die API nur mit dem Bereich „Kunden“. Rechnungsentwürfe werden nie ausgegeben.</p>

    <form method="post" action="<?= e(url('admin/integrations/tokens')) ?>" class="mt-5 space-y-3 border-t border-slate-100 pt-5"><?= csrf_field() ?>
      <div class="grid gap-3 sm:grid-cols-3">
        <div class="sm:col-span-2"><label class="label" for="tk-name">Neuer Token (Name)</label><input class="input" id="tk-name" name="name" maxlength="120" placeholder="z. B. Buchhaltung" required></div>
        <div><label class="label" for="tk-days">Gültig (Tage)</label><input class="input" id="tk-days" type="number" name="days" min="1" max="3650" placeholder="unbegrenzt"></div>
      </div>
      <div class="flex flex-wrap gap-x-4 gap-y-1">
        <?php foreach (ApiTokens::SCOPES as $k => $l): ?><label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" class="checkbox" name="scopes[<?= e($k) ?>]" value="1" <?= $k === 'invoices' ? 'checked' : '' ?>><?= e($l) ?></label><?php endforeach; ?>
      </div>
      <button class="btn btn-secondary btn-sm"><i class="bx bx-plus"></i>Token erstellen</button>
    </form>

    <?php if ($tokens): ?>
    <ul class="mt-5 divide-y divide-slate-100 text-sm">
      <?php foreach ($tokens as $t): $dead = $t['revoked_at'] || ($t['expires_at'] && strtotime($t['expires_at']) < time()); ?>
        <li class="flex items-center justify-between gap-3 py-2.5">
          <div class="min-w-0"><p class="truncate font-medium text-slate-800"><?= e($t['name']) ?> <span class="font-mono text-xs text-slate-400"><?= e($t['token_prefix']) ?>…</span><?php if ($dead): ?> <span class="badge">inaktiv</span><?php endif; ?></p>
            <p class="text-xs text-slate-500"><?= e(str_replace(',', ', ', $t['scopes'])) ?> · <?= $t['last_used_at'] ? 'zuletzt ' . time_ago($t['last_used_at']) : 'noch nie verwendet' ?><?= $t['expires_at'] ? ' · läuft ab ' . fmt_date($t['expires_at']) : '' ?></p></div>
          <?php if (!$t['revoked_at']): ?>
          <form method="post" action="<?= e(url('admin/integrations/tokens/' . $t['id'] . '/revoke')) ?>" data-confirm="Token „<?= e($t['name']) ?>“ widerrufen? Anwendungen mit diesem Token verlieren sofort den Zugriff." data-confirm-label="Widerrufen" data-confirm-variant="danger"><?= csrf_field() ?><button class="btn btn-ghost btn-sm text-rose-600"><i class="bx bx-block"></i></button></form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>

  <section class="reveal card p-5 sm:p-6">
    <h2 class="text-sm font-semibold text-slate-900"><i class="bx bx-book-content align-middle text-slate-400"></i> Buchungsjournal (CSV)</h2>
    <p class="mt-1 text-sm text-slate-500">Generisches Journal: Rechnungen als „Debitoren an Ertrag“ je Steuersatz (Betrag brutto mit Steuersatz/-betrag) und Zahlungseingänge als „Bank an Debitoren“. Die Spalten lassen sich in den CSV-Import gängiger Buchhaltungssoftware zuordnen; ein softwarespezifisches Format (z. B. Bexio, Abacus) ist nicht enthalten.</p>
    <form method="post" action="<?= e(url('admin/integrations/accounts')) ?>" class="mt-4 grid gap-3 sm:grid-cols-4"><?= csrf_field() ?>
      <div><label class="label" for="a1">Konto Debitoren</label><input class="input" id="a1" name="acc_debtors" value="<?= e($acc['acc_debtors']) ?>" maxlength="20"></div>
      <div><label class="label" for="a2">Konto Ertrag</label><input class="input" id="a2" name="acc_revenue" value="<?= e($acc['acc_revenue']) ?>" maxlength="20"></div>
      <div><label class="label" for="a3">Konto Bank</label><input class="input" id="a3" name="acc_bank" value="<?= e($acc['acc_bank']) ?>" maxlength="20"></div>
      <div><label class="label" for="a4">Konto Online-Zahlungen</label><input class="input" id="a4" name="acc_online" value="<?= e($acc['acc_online']) ?>" maxlength="20"></div>
      <div><label class="label" for="a5">Konto Kasse (Barzahlungen)</label><input class="input" id="a5" name="acc_cash" value="<?= e($acc['acc_cash']) ?>" maxlength="20"></div>
      <div><label class="label" for="a6">Konto Erlösminderungen (Skonto)</label><input class="input" id="a6" name="acc_discount" value="<?= e($acc['acc_discount']) ?>" maxlength="20"></div>
      <div class="sm:col-span-4"><button class="btn btn-secondary btn-sm"><i class="bx bx-save"></i>Konten speichern</button></div>
    </form>
    <form method="get" action="<?= e(url('admin/integrations/journal')) ?>" class="mt-5 flex flex-col gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-end">
      <div><label class="label" for="j-from">Von</label><input class="input" id="j-from" type="date" name="from" value="<?= date('Y-01-01') ?>"></div>
      <div><label class="label" for="j-to">Bis</label><input class="input" id="j-to" type="date" name="to" value="<?= date('Y-m-d') ?>"></div>
      <button class="btn btn-primary"><i class="bx bx-download"></i>Journal herunterladen</button>
    </form>
  </section>
</div>

<section class="reveal card mt-6 p-5 sm:p-6">
  <h2 class="text-sm font-semibold text-slate-900"><i class="bx bx-broadcast align-middle text-slate-400"></i> Webhooks</h2>
  <p class="mt-1 text-sm text-slate-500">Bei Ereignissen sendet das System einen signierten HTTPS-POST (JSON). Es werden nur Kennungen, Nummern, Beträge und Kundenname/-nummer übermittelt. Signatur-Header: <code>X-Offerten-Signature: t=…,v1=HMAC-SHA256(secret, "t.body")</code>. Fehlgeschlagene Zustellungen werden bis zu 6-mal wiederholt. Es sind nur öffentliche https-Adressen erlaubt.</p>

  <?php foreach ($hooks as $h): $ev = array_filter(explode(',', $h['events'])); ?>
  <details class="mt-4 rounded-xl ring-1 ring-slate-200">
    <summary class="flex cursor-pointer items-center justify-between gap-3 px-4 py-3 text-sm"><span class="min-w-0"><span class="font-medium text-slate-800"><?= e($h['name']) ?></span> <span class="truncate text-xs text-slate-400"><?= e($h['url']) ?></span></span><?= $h['active'] ? '<span class="badge badge-brand">aktiv</span>' : '<span class="badge">pausiert</span>' ?></summary>
    <div class="space-y-3 border-t border-slate-100 p-4">
      <form method="post" action="<?= e(url('admin/integrations/webhooks/' . $h['id'])) ?>" class="space-y-3"><?= csrf_field() ?>
        <div class="grid gap-3 sm:grid-cols-3"><div><label class="label">Name</label><input class="input" name="name" value="<?= e($h['name']) ?>" maxlength="120" required></div><div class="sm:col-span-2"><label class="label">URL (https)</label><input class="input" type="url" name="url" value="<?= e($h['url']) ?>" maxlength="500" required></div></div>
        <div class="flex flex-wrap gap-x-4 gap-y-1"><?php foreach ($evLabels as $k => $l): ?><label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" class="checkbox" name="events[<?= e($k) ?>]" value="1" <?= in_array($k, $ev, true) ? 'checked' : '' ?>><?= e($l) ?></label><?php endforeach; ?></div>
        <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" class="checkbox" name="active" value="1" <?= $h['active'] ? 'checked' : '' ?>>Aktiv</label>
        <button class="btn btn-secondary btn-sm"><i class="bx bx-save"></i>Speichern</button>
      </form>
      <div class="flex flex-wrap gap-2 border-t border-slate-100 pt-3">
        <form method="post" action="<?= e(url('admin/integrations/webhooks/' . $h['id'] . '/test')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm"><i class="bx bx-paper-plane"></i>Testaufruf</button></form>
        <form method="post" action="<?= e(url('admin/integrations/webhooks/' . $h['id'] . '/rotate')) ?>" data-confirm="Neues Secret erzeugen? Das alte wird sofort ungültig." data-confirm-label="Erneuern" data-confirm-variant="danger"><?= csrf_field() ?><button class="btn btn-ghost btn-sm"><i class="bx bx-refresh"></i>Secret erneuern</button></form>
        <form method="post" action="<?= e(url('admin/integrations/webhooks/' . $h['id'] . '/delete')) ?>" data-confirm="Webhook „<?= e($h['name']) ?>“ löschen?" data-confirm-label="Löschen" data-confirm-variant="danger"><?= csrf_field() ?><button class="btn btn-ghost btn-sm text-rose-600"><i class="bx bx-trash"></i>Löschen</button></form>
      </div>
    </div>
  </details>
  <?php endforeach; ?>

  <form method="post" action="<?= e(url('admin/integrations/webhooks')) ?>" class="mt-5 space-y-3 border-t border-slate-100 pt-5"><?= csrf_field() ?>
    <div class="grid gap-3 sm:grid-cols-3"><div><label class="label" for="wh-name">Neuer Webhook (Name)</label><input class="input" id="wh-name" name="name" maxlength="120" required></div><div class="sm:col-span-2"><label class="label" for="wh-url">URL (https)</label><input class="input" id="wh-url" type="url" name="url" maxlength="500" placeholder="https://…" required></div></div>
    <div class="flex flex-wrap gap-x-4 gap-y-1"><?php foreach ($evLabels as $k => $l): ?><label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" class="checkbox" name="events[<?= e($k) ?>]" value="1" <?= $k === 'invoice.paid' ? 'checked' : '' ?>><?= e($l) ?></label><?php endforeach; ?></div>
    <input type="hidden" name="active" value="1">
    <button class="btn btn-secondary btn-sm"><i class="bx bx-plus"></i>Webhook erstellen</button>
  </form>

  <?php if ($deliveries): ?>
  <h3 class="mt-6 text-xs font-semibold uppercase tracking-wider text-slate-400">Letzte Zustellungen</h3>
  <ul class="mt-2 divide-y divide-slate-100 text-sm">
    <?php foreach ($deliveries as $d): ?><li class="flex flex-wrap items-center justify-between gap-2 py-2"><span><?= e($d['name']) ?> · <span class="text-slate-500"><?= e($evLabels[$d['event']] ?? $d['event']) ?></span></span><span class="text-xs text-slate-500"><?= fmt_datetime($d['created_at']) ?> · <?= e(['pending' => 'ausstehend', 'sent' => 'zugestellt', 'failed' => 'fehlgeschlagen'][$d['status']]) ?><?= $d['last_http'] ? ' (HTTP ' . (int) $d['last_http'] . ')' : '' ?><?= $d['status'] !== 'sent' && $d['last_error'] ? ' – ' . e($d['last_error']) : '' ?></span></li><?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>
