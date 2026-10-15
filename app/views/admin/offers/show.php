<?php
$title = 'Offerte ' . $offer['number'];
$titleHtml = e($offer['number']) . ' <span class="ml-2 align-middle">' . Status::badge('offer', $offer['status']) . '</span>';
$subtitle = e($offer['title']) . ' · <a class="link" href="' . e(url('admin/customers/' . $offer['customer_id'])) . '">' . e(customer_name($offer['customer'])) . '</a>';
$back = ['admin/offers', 'Offerten'];
$publicUrl = OfferService::url($offer);
$actions = '<a href="' . e(url('admin/offers/' . $offer['id'] . '/pdf')) . '" class="btn btn-secondary"><i class="bx bx-download"></i>PDF</a><a href="' . e($publicUrl) . '" target="_blank" rel="noopener" class="btn btn-secondary"><i class="bx bx-link-external"></i>Kundenansicht</a>'
    . '<a href="' . e(url('admin/offers/new?from=' . $offer['id'] . '&copy=1')) . '" class="btn btn-secondary"><i class="bx bx-duplicate"></i>Duplizieren</a>'
    . '<a href="' . e(url('admin/offers/new?from=' . $offer['id'])) . '" class="btn btn-primary"><i class="bx bx-git-branch"></i>Neue Offerte auf Basis dieser</a>';
include APP_DIR . '/views/partials/page-header.php';
?>
<div class="grid gap-6 xl:grid-cols-3">
  <div class="reveal space-y-6 xl:col-span-2"><?php include APP_DIR . '/views/partials/tax-review.php'; ?><?php include APP_DIR . '/views/partials/offer-document.php'; ?></div>

  <aside class="space-y-6">
    <section class="reveal reveal-delay-1 card p-5 sm:p-6">
      <h2 class="text-sm font-semibold text-slate-900">Öffentlicher Link</h2>
      <div class="mt-3 flex items-center gap-2 rounded-xl bg-slate-50 p-1.5 pl-3 ring-1 ring-slate-200">
        <code class="min-w-0 flex-1 truncate text-xs text-slate-600"><?= e($publicUrl) ?></code>
        <button type="button" class="btn btn-secondary btn-icon size-8" data-copy="<?= e($publicUrl) ?>" data-tip="Link kopieren"><i class="bx bx-copy"></i></button>
      </div>
      <?php if ($offer['status'] === 'open'): ?>
        <form method="post" action="<?= e(url('admin/offers/' . $offer['id'] . '/send')) ?>" class="mt-3" data-confirm="Offerte per E-Mail an <?= e($offer['customer']['email'] ?? '') ?> senden?" data-confirm-label="Senden" data-confirm-variant="primary"><?= csrf_field() ?>
          <button class="btn btn-primary w-full"><i class="bx bx-send"></i><?= $offer['sent_at'] ? 'Erneut per E-Mail senden' : 'Per E-Mail senden' ?></button>
        </form>
      <?php endif; ?>
      <dl class="mt-4 divide-y divide-slate-100">
        <div class="dl-row"><dt>Erstellt</dt><dd><?= fmt_datetime($offer['created_at']) ?></dd></div>
        <?php if ($issuer !== ''): ?><div class="dl-row"><dt>Ausgestellt von</dt><dd><?= person_chip($issuer) ?><?= $offer['created_by'] ? '' : ' <span class="text-xs text-slate-400">(Konto gelöscht)</span>' ?></dd></div><?php else: ?><div class="dl-row"><dt>Ausgestellt von</dt><dd class="text-slate-400">System / Kundenanfrage</dd></div><?php endif; ?>
        <div class="dl-row"><dt>Versendet</dt><dd><?= $offer['sent_at'] ? fmt_datetime($offer['sent_at']) : '–' ?></dd></div>
        <div class="dl-row"><dt>Geöffnet</dt><dd><?= $offer['viewed_at'] ? fmt_datetime($offer['viewed_at']) : '–' ?></dd></div>
        <?php if ($offer['responded_at']): ?><div class="dl-row"><dt>Antwort</dt><dd><?= fmt_datetime($offer['responded_at']) ?></dd></div><?php endif; ?>
        <div class="dl-row"><dt>Bereich</dt><dd><i class="bx <?= e($area['icon'] ?? 'bx-category') ?> align-middle text-slate-400"></i> <?= e($area['name'] ?? ($offer['area_type'] === 'digital' ? 'Digital' : 'Physisch')) ?></dd></div>
      </dl>
    </section>

    <?php if ($order): ?>
      <a href="<?= e(url('admin/orders/' . $order['id'])) ?>" class="reveal card card-hover group flex items-center gap-4 bg-emerald-50/50 p-5 ring-emerald-200">
        <span class="kpi-icon bg-emerald-100 text-emerald-700"><i class="bx bx-package"></i></span>
        <div class="flex-1"><p class="text-sm font-semibold text-slate-900">Auftrag <?= e($order['number']) ?></p><p class="text-xs text-slate-500"><?= e(Status::label('order', $order['status'], $order['area_type'])) ?></p></div>
        <i class="bx bx-right-arrow-alt text-xl text-slate-400 transition group-hover:translate-x-0.5"></i>
      </a>
    <?php endif; ?>

    <?php if (!empty($offer['signature_file']) || SignatureService::mode($offer) !== 'none'): ?>
      <section class="reveal card p-5 sm:p-6">
        <h2 class="text-sm font-semibold text-slate-900">Digitale Unterschrift <span class="badge ml-1"><?= e(SignatureService::MODES[SignatureService::mode($offer)]) ?></span></h2>
        <?php if (SignatureService::mode($offer) === 'strict'): ?>
          <p class="mt-2 text-sm text-slate-600"><i class="bx bx-user-check align-middle text-slate-400"></i> <?= e($offer['signer_name']) ?> · <?= e($offer['signer_email']) ?></p>
          <?php if (!empty($offer['signer_verified_at'])): ?><p class="mt-1 text-xs text-emerald-700"><i class="bx bx-check-shield align-middle"></i> E-Mail bestätigt am <?= fmt_datetime($offer['signer_verified_at']) ?> (<?= e($offer['signer_verified_email']) ?>)</p><?php endif; ?>
        <?php endif; ?>
        <?php if (!empty($offer['signature_file'])): ?>
          <img src="<?= e(url('admin/offers/' . $offer['id'] . '/signature')) ?>" alt="Unterschrift" class="mt-3 w-full rounded-xl bg-white ring-1 ring-slate-200">
          <p class="mt-2 text-xs text-slate-500"><?= e($offer['response_name']) ?> · <?= fmt_datetime($offer['responded_at']) ?> · IP <?= e($offer['response_ip']) ?></p>
        <?php else: ?>
          <p class="mt-2 text-sm text-slate-500"><i class="bx bx-pen align-middle"></i> Unterschrift wird bei der Annahme verlangt<?= SignatureService::mode($offer) === 'strict' ? ' (nur von der genannten Person nach E-Mail-Bestätigung)' : '' ?>.</p>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ($offer['response_note'] || $offer['response_name']): ?>
      <section class="reveal card p-5 sm:p-6">
        <h2 class="text-sm font-semibold text-slate-900">Rückmeldung des Kunden</h2>
        <?php if ($offer['response_name']): ?><p class="mt-2 text-sm text-slate-600"><i class="bx bx-user align-middle text-slate-400"></i> <?= e($offer['response_name']) ?></p><?php endif; ?>
        <?php if ($offer['response_note']): ?><p class="mt-2 whitespace-pre-line rounded-xl bg-slate-50 p-3 text-sm italic text-slate-700"><?= e($offer['response_note']) ?></p><?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ($parent || $children): ?>
      <section class="reveal card p-5 sm:p-6">
        <h2 class="text-sm font-semibold text-slate-900">Versionen</h2>
        <ul class="mt-3 space-y-2 text-sm">
          <?php if ($parent): ?><li class="flex items-center justify-between gap-2"><a class="link" href="<?= e(url('admin/offers/' . $parent['id'])) ?>"><i class="bx bx-up-arrow-alt align-middle"></i> <?= e($parent['number']) ?></a><?= Status::badge('offer', $parent['status']) ?></li><?php endif; ?>
          <?php foreach ($children as $ch): ?><li class="flex items-center justify-between gap-2"><a class="link" href="<?= e(url('admin/offers/' . $ch['id'])) ?>"><i class="bx bx-down-arrow-alt align-middle"></i> <?= e($ch['number']) ?></a><?= Status::badge('offer', $ch['status']) ?></li><?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

    <?php $attType = 'offer'; $attId = $offer['id']; include APP_DIR . '/views/partials/attachments.php'; ?>
    <section class="reveal card p-5 sm:p-6">
      <h2 class="mb-4 text-sm font-semibold text-slate-900">Statushistorie</h2>
      <?php $showInternal = true; $areaType = $offer['area_type']; include APP_DIR . '/views/partials/timeline.php'; ?>
    </section>

    <?php if (true): ?>
      <form method="post" action="<?= e(url('admin/offers/' . $offer['id'] . '/delete')) ?>" data-confirm="Offerte <?= e($offer['number']) ?><?= $order ? ' samt Auftrag ' . e($order['number']) : '' ?> endgültig löschen? Der öffentliche Link funktioniert danach nicht mehr." data-confirm-label="Endgültig löschen"><?= csrf_field() ?>
        <button class="btn btn-ghost btn-sm w-full text-rose-600 hover:bg-rose-50 hover:text-rose-700"><i class="bx bx-trash"></i>Offerte<?= $order ? ' und Auftrag' : '' ?> löschen</button>
      </form>
    <?php endif; ?>
  </aside>
</div>
