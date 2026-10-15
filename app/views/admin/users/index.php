<?php
$title = 'Benutzer';
$subtitle = '<strong>Administratoren</strong> haben vollen Zugriff inkl. Benutzerverwaltung. <strong>Team</strong>-Mitglieder können alles ausser Benutzer verwalten.';
$actions = '<button type="button" class="btn btn-primary" data-modal-open="modal-user" data-fill data-modal-title="Neuer Benutzer"><i class="bx bx-user-plus"></i>Neuer Benutzer</button>';
include APP_DIR . '/views/partials/page-header.php';
?>
<div class="reveal card overflow-hidden">
  <div class="overflow-x-auto"><table class="table">
    <thead><tr><th>Benutzer</th><th>Rolle</th><th class="hidden md:table-cell">Letzte Anmeldung</th><th class="w-32"><span class="sr-only">Aktionen</span></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr class="<?= $u['active'] ? '' : 'opacity-60' ?>">
        <td><div class="flex items-center gap-3">
          <span class="grid size-9 shrink-0 place-items-center rounded-full <?= $u['role'] === 'admin' ? 'bg-gradient-to-br from-brand-500 to-brand-700 text-white' : 'bg-slate-100 text-slate-600' ?> text-xs font-bold"><?= e(initials($u['name'])) ?></span>
          <div class="min-w-0"><p class="font-medium text-slate-900"><?= e($u['name']) ?><?= (int) $u['id'] === (int) $me['id'] ? ' <span class="text-xs font-normal text-slate-400">(Sie)</span>' : '' ?></p><p class="truncate text-xs text-slate-500"><?= e($u['email']) ?></p></div>
        </div></td>
        <td><span class="badge <?= $u['role'] === 'admin' ? 'badge-brand' : 'badge-slate' ?>"><i class="bx <?= $u['role'] === 'admin' ? 'bx-crown' : 'bx-user' ?>"></i><?= $u['role'] === 'admin' ? 'Administrator' : 'Team' ?></span><?= $u['active'] ? '' : ' <span class="badge badge-rose">deaktiviert</span>' ?><?= !empty($u['totp_enabled']) ? ' <span class="badge badge-emerald" data-tip="Zwei-Faktor-Anmeldung aktiv"><i class="bx bx-shield-quarter"></i>2FA</span>' : '' ?></td>
        <td class="hidden md:table-cell text-slate-500"><?= $u['last_login_at'] ? time_ago($u['last_login_at']) : 'noch nie' ?></td>
        <td><div class="flex justify-end gap-1">
          <?php if ($u['active']): ?>
          <form method="post" action="<?= e(url('admin/users/' . $u['id'] . '/invite')) ?>" data-confirm="Anmeldelink (24 h gültig) an <?= e($u['email']) ?> senden?" data-confirm-label="Senden" data-confirm-variant="primary"><?= csrf_field() ?><button class="btn btn-ghost btn-icon" data-tip="Anmeldelink senden" aria-label="Anmeldelink senden"><i class="bx bx-send text-lg"></i></button></form>
          <?php endif; ?>
          <?php if (!empty($u['totp_enabled']) && (int) $u['id'] !== (int) $me['id']): ?>
          <form method="post" action="<?= e(url('admin/users/' . $u['id'] . '/reset-2fa')) ?>" data-confirm="Zwei-Faktor-Anmeldung von <?= e($u['name']) ?> zurücksetzen? Das Konto kann sich danach wieder ohne zweiten Faktor anmelden." data-confirm-label="Zurücksetzen"><?= csrf_field() ?><button class="btn btn-ghost btn-icon" data-tip="2FA zurücksetzen" aria-label="2FA zurücksetzen"><i class="bx bx-shield-x text-lg"></i></button></form>
          <?php endif; ?>
          <button type="button" class="btn btn-ghost btn-icon" data-modal-open="modal-user" data-modal-title="Benutzer bearbeiten" data-fill="<?= json_attr(['id' => $u['id'], 'name' => $u['name'], 'email' => $u['email'], 'role' => $u['role'], 'active' => $u['active']]) ?>" data-tip="Bearbeiten" aria-label="Bearbeiten"><i class="bx bx-edit text-lg"></i></button>
          <?php if ((int) $u['id'] !== (int) $me['id']): ?>
          <form method="post" action="<?= e(url('admin/users/' . $u['id'] . '/delete')) ?>" data-confirm="Benutzer „<?= e($u['name']) ?>“ löschen?" data-confirm-label="Löschen"><?= csrf_field() ?><button class="btn btn-ghost btn-icon text-slate-400 hover:text-rose-600" data-tip="Löschen" aria-label="Löschen"><i class="bx bx-trash text-lg"></i></button></form>
          <?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<div class="modal" id="modal-user" aria-hidden="true" role="dialog" aria-modal="true">
  <div class="modal-backdrop" data-modal-close></div>
  <form method="post" action="<?= e(url('admin/users')) ?>" class="modal-panel max-w-lg" data-defaults='{"role":"team","active":1,"invite":1}'><?= csrf_field() ?>
    <input type="hidden" name="id">
    <div class="flex items-center justify-between border-b border-slate-100 p-5 sm:px-6"><h3 class="text-base font-semibold text-slate-900" data-modal-heading>Benutzer</h3><button type="button" class="btn btn-ghost btn-icon" data-modal-close aria-label="Schliessen"><i class="bx bx-x text-2xl"></i></button></div>
    <div class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
      <div class="sm:col-span-2"><label class="label">Name <span class="text-rose-500">*</span></label><input class="input" name="name" required maxlength="120"></div>
      <div class="sm:col-span-2"><label class="label">E-Mail <span class="text-rose-500">*</span></label><input class="input" type="email" name="email" required></div>
      <div class="sm:col-span-2"><span class="label">Rolle</span>
        <div class="grid grid-cols-2 gap-2">
          <label class="flex cursor-pointer items-start gap-2 rounded-xl p-3 ring-1 ring-slate-200 transition hover:ring-slate-300 has-[:checked]:bg-brand-50 has-[:checked]:ring-2 has-[:checked]:ring-brand-500"><input type="radio" class="sr-only" name="role" value="team"><i class="bx bx-user text-xl text-slate-400"></i><span><span class="block text-sm font-medium">Team</span><span class="block text-xs text-slate-500">ohne Benutzerverwaltung</span></span></label>
          <label class="flex cursor-pointer items-start gap-2 rounded-xl p-3 ring-1 ring-slate-200 transition hover:ring-slate-300 has-[:checked]:bg-brand-50 has-[:checked]:ring-2 has-[:checked]:ring-brand-500"><input type="radio" class="sr-only" name="role" value="admin"><i class="bx bx-crown text-xl text-slate-400"></i><span><span class="block text-sm font-medium">Administrator</span><span class="block text-xs text-slate-500">voller Zugriff</span></span></label>
        </div></div>
      <div class="sm:col-span-2"><label class="label">Passwort <span class="font-normal text-slate-400">(optional)</span></label><input class="input" type="password" name="password" autocomplete="new-password" minlength="10"><p class="hint">Leer lassen = unverändert bzw. Anmeldung per Magic-Link. Mind. 10 Zeichen, Buchstaben und Ziffern.</p></div>
      <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700"><input type="checkbox" class="checkbox" name="active" value="1">Aktiv</label>
      <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700" data-new-only><input type="checkbox" class="checkbox" name="invite" value="1">Einladung per E-Mail</label>
    </div>
    <div class="flex flex-col-reverse gap-2 rounded-b-2xl bg-slate-50 px-6 py-4 sm:flex-row sm:justify-end"><button type="button" class="btn btn-secondary" data-modal-close>Abbrechen</button><button class="btn btn-primary"><i class="bx bx-save"></i>Speichern</button></div>
  </form>
</div>
<script>
document.querySelector('#modal-user form').addEventListener('modal:filled', (e) => {
  const isNew = !e.target.querySelector('[name=id]').value;
  e.target.querySelector('[data-new-only]').hidden = !isNew;
});
</script>
