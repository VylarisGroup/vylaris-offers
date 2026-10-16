<?php
/** Zeigt beim ersten Aufruf nach einem Update, was neu ist. Einmal pro Benutzer und Version (gilt als gesehen, sobald der Dialog erscheint). */
$pendingLog = Changelog::pendingFor($me ?? null);
if ($pendingLog): ?>
<div class="modal" id="changelog-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="changelog-title" data-autoopen>
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-panel max-w-xl">
    <div class="flex items-start gap-4 p-6 pb-3">
      <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-brand-50 text-2xl text-brand-600"><i class="bx bx-gift"></i></span>
      <div>
        <h2 id="changelog-title" class="text-lg font-semibold text-slate-900">Neuigkeiten in Version <?= e(APP_VERSION) ?></h2>
        <p class="mt-0.5 text-sm text-slate-500">Das hat sich seit Ihrem letzten Besuch geändert.</p>
      </div>
    </div>
    <div class="max-h-[55vh] overflow-y-auto px-6 pb-4"><?php $entries = $pendingLog; include APP_DIR . '/views/partials/changelog-entries.php'; ?></div>
    <form method="post" action="<?= e(url('admin/changelog/seen')) ?>" class="flex flex-col-reverse gap-2 rounded-b-2xl bg-slate-50 px-6 py-4 sm:flex-row sm:items-center sm:justify-between"><?= csrf_field() ?>
      <input type="hidden" name="back" value="<?= e((string) parse_url($_SERVER['REQUEST_URI'] ?? '/admin', PHP_URL_PATH)) ?>">
      <a href="<?= e(url('admin/changelog')) ?>" class="link text-sm">Alle Änderungen ansehen</a>
      <button class="btn btn-primary"><i class="bx bx-check"></i>Verstanden</button>
    </form>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var m = document.getElementById('changelog-modal');
  if (!m) return;
  var markSeen = function () {
    // Einmal anzeigen genügt: Sobald der Dialog erscheint, gilt die Version als gesehen.
    var body = new FormData();
    body.append('_csrf', document.querySelector('meta[name="csrf-token"]').content);
    fetch(<?= json_encode(url('admin/changelog/seen')) ?>, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).catch(function () {});
  };
  window.addEventListener('load', function () {
    setTimeout(function () { if (window.appModal) { window.appModal.open(m); markSeen(); } }, 350);
  });
});
</script>
<?php endif; ?>
