<?php /** @var string $owner @var array $staff @var bool $withNone */ $withNone = $withNone ?? false; ?>
<select class="input w-auto" name="owner" data-autosubmit aria-label="Zuständigkeit filtern">
  <option value="">Alle Personen</option>
  <option value="me"<?= $owner === 'me' ? ' selected' : '' ?>>Meine</option>
  <?php if ($withNone): ?><option value="none"<?= $owner === 'none' ? ' selected' : '' ?>>Nicht zugewiesen</option><?php endif; ?>
  <?php foreach ($staff as $s): ?><option value="<?= (int) $s['id'] ?>"<?= $owner === (string) $s['id'] ? ' selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
</select>
