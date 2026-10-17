<?php
/** Gemeinsame Konversationsansicht. @var array $thread  @var string $viewer 'team'|'customer' */
?>
<div class="space-y-4 p-5 sm:p-6" id="thread">
  <?php if (!$thread): ?>
    <div class="empty-state py-10"><i class="bx bx-message-square-dots"></i><p class="text-sm text-slate-500"><?= $viewer === 'team' ? 'Noch keine Nachrichten mit diesem Kunden.' : 'Noch keine Nachrichten. Schreiben Sie uns – wir antworten so rasch wie möglich.' ?></p></div>
  <?php endif; ?>
  <?php foreach ($thread as $m):
      $mine = ($viewer === 'team') === ($m['direction'] === 'out');
      $ref = PortalMessage::refLabel($m); ?>
    <div class="flex <?= $mine ? 'justify-end' : 'justify-start' ?>">
      <div class="max-w-[85%] sm:max-w-[70%]">
        <div class="rounded-2xl px-4 py-3 text-sm leading-relaxed <?= $mine ? 'rounded-br-md bg-brand-600 text-white' : 'rounded-bl-md bg-slate-100 text-slate-800' ?>"><?= nl($m['body']) ?></div>
        <p class="mt-1 flex flex-wrap items-center gap-x-2 text-[11px] text-slate-400 <?= $mine ? 'justify-end' : '' ?>">
          <span><?= $mine ? ($viewer === 'team' ? e($m['author']) : 'Sie') : ($viewer === 'team' ? e($m['author']) : e($m['author'] ?: 'Team')) ?></span><span>·</span><span><?= fmt_datetime($m['created_at']) ?></span>
          <?php if ($ref): ?><span>·</span><a class="link" href="<?= e($viewer === 'team' ? $ref[1] : $ref[2]) ?>"><i class="bx bx-link align-middle"></i> <?= e($ref[0]) ?></a><?php endif; ?>
        </p>
      </div>
    </div>
  <?php endforeach; ?>
</div>
