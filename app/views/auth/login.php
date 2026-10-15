<?php $title = 'Anmelden'; $magic = ($mode ?? '') === 'magic';
$sso = SsoController::active(); $ssoOnly = SsoController::required() && empty($_GET['local']) && empty($_POST['local']); $local = !empty($_GET['local']) || !empty($_POST['local']); ?>
<div class="reveal card p-6 sm:p-8">
  <h1 class="text-xl font-bold text-slate-900">Willkommen zurück</h1>
  <p class="mt-1 text-sm text-slate-500">Melden Sie sich mit Passwort oder per Anmeldelink an.</p>

  <?php if (!empty($error)): ?>
    <div class="mt-5 flex gap-2.5 rounded-xl bg-rose-50 p-3.5 text-sm text-rose-700 ring-1 ring-rose-200" role="alert"><i class="bx bx-error-circle mt-0.5 text-lg"></i><?= e($error) ?></div>
  <?php endif; ?>

  <?php if ($sso): ?>
    <a href="<?= e(url('admin/sso/login')) ?>" class="btn btn-primary mt-6 w-full"><i class="bx bx-key"></i>Mit <?= e(Settings::get('sso_label', 'Vylaris-Login')) ?> anmelden</a>
    <?php if (!$ssoOnly): ?><p class="mt-5 text-center text-xs uppercase tracking-wider text-slate-400">oder lokal</p><?php endif; ?>
  <?php endif; ?>
  <?php if ($ssoOnly): ?>
    <p class="mt-4 text-center text-sm text-slate-500">Die Anmeldung erfolgt ausschliesslich über Single Sign-On.</p>
    <p class="mt-6 text-center text-xs text-slate-400"><a class="hover:text-slate-600" href="?local=1">Notfall-Zugang (lokal)</a></p>
  <?php else: ?>
  <div class="mt-6 grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1" role="tablist">
    <button type="button" class="tab justify-center<?= $magic ? '' : ' is-active' ?>" data-auth-tab="pw" role="tab"><i class="bx bx-lock-alt"></i>Passwort</button>
    <button type="button" class="tab justify-center<?= $magic ? ' is-active' : '' ?>" data-auth-tab="magic" role="tab"><i class="bx bx-envelope"></i>Anmeldelink</button>
  </div>

  <form method="post" action="<?= e(url('admin/login')) ?>" class="mt-6 space-y-4" data-auth-panel="pw" <?= $magic ? 'hidden' : '' ?>>
    <?= csrf_field() ?><?php if ($local): ?><input type="hidden" name="local" value="1"><?php endif; ?>
    <div>
      <label class="label" for="email">E-Mail-Adresse</label>
      <input class="input" type="email" id="email" name="email" value="<?= e($email ?? '') ?>" autocomplete="username" required autofocus>
    </div>
    <div>
      <label class="label" for="password">Passwort</label>
      <div class="relative">
        <input class="input pr-11" type="password" id="password" name="password" autocomplete="current-password" required>
        <button type="button" class="absolute inset-y-0 right-0 grid w-11 place-items-center text-slate-400 transition hover:text-slate-700" data-toggle-pw aria-label="Passwort anzeigen"><i class="bx bx-show text-xl"></i></button>
      </div>
    </div>
    <button class="btn btn-primary w-full"><i class="bx bx-log-in"></i>Anmelden</button>
  </form>

  <form method="post" action="<?= e(url('admin/magic')) ?>" class="mt-6 space-y-4" data-auth-panel="magic" <?= $magic ? '' : 'hidden' ?>>
    <?= csrf_field() ?><?php if ($local): ?><input type="hidden" name="local" value="1"><?php endif; ?>
    <div>
      <label class="label" for="email2">E-Mail-Adresse</label>
      <input class="input" type="email" id="email2" name="email" value="<?= e($email ?? '') ?>" autocomplete="email" required>
      <p class="hint">Wir senden Ihnen einen Link, der 30 Minuten gültig ist. Auch geeignet, wenn Sie Ihr Passwort vergessen haben.</p>
    </div>
    <button class="btn btn-primary w-full"><i class="bx bx-send"></i>Anmeldelink senden</button>
  </form>
  <?php endif; ?>
</div>
<script>
document.addEventListener('click', (e) => {
  const tab = e.target.closest('[data-auth-tab]');
  if (tab) {
    document.querySelectorAll('[data-auth-tab]').forEach(t => t.classList.toggle('is-active', t === tab));
    document.querySelectorAll('[data-auth-panel]').forEach(p => p.hidden = p.dataset.authPanel !== tab.dataset.authTab);
    const f = document.querySelector('[data-auth-panel="' + tab.dataset.authTab + '"] input[type=email]'); f && f.focus();
  }
  const pw = e.target.closest('[data-toggle-pw]');
  if (pw) {
    const input = pw.parentElement.querySelector('input');
    input.type = input.type === 'password' ? 'text' : 'password';
    pw.querySelector('i').className = 'bx ' + (input.type === 'password' ? 'bx-show' : 'bx-hide') + ' text-xl';
  }
});
</script>
