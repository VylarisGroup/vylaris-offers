<?php
$offer = $inv;
$isInvoice = true;
$title = ($inv['kind'] === 'credit' ? 'Gutschrift ' : 'Rechnung ') . $inv['number'];
$ds = $inv['display_status'];
?>
<div class="mx-auto max-w-4xl">
  <div class="reveal no-print mb-6">
    <?php if ($inv['kind'] === 'credit'): ?>
      <div class="flex items-center gap-4 rounded-2xl bg-slate-50 p-5 ring-1 ring-slate-200"><i class="bx bx-undo text-3xl text-slate-500"></i>
        <div class="flex-1"><p class="font-semibold text-slate-900">Gutschrift <?= $inv['status'] === 'cancelled' ? '(storniert)' : 'zu Rechnung ' . e((string) $inv['credit_of_number']) ?></p><p class="text-sm text-slate-600">Der Betrag wird mit der Rechnung verrechnet bzw. zurückerstattet.</p></div>
        <a href="<?= e(url('r/' . $inv['public_id'] . '/pdf')) ?>" class="btn btn-secondary btn-sm"><i class="bx bx-download"></i>PDF</a></div>
    <?php elseif ($inv['status'] === 'paid'): ?>
      <div class="flex items-center gap-4 rounded-2xl bg-emerald-50 p-5 ring-1 ring-emerald-200"><span class="grid size-12 shrink-0 place-items-center rounded-xl bg-emerald-600 text-2xl text-white"><i class="bx bx-check"></i></span>
        <div class="flex-1"><p class="font-semibold text-emerald-900">Bezahlt – vielen Dank!</p><p class="text-sm text-emerald-800">Zahlungseingang am <?= fmt_date($inv['paid_at']) ?></p></div>
        <div class="flex flex-wrap gap-2"><a href="<?= e(url('r/' . $inv['public_id'] . '/receipt')) ?>" class="btn btn-secondary btn-sm"><i class="bx bx-receipt"></i>Zahlungsbestätigung</a>
        <a href="<?= e(url('r/' . $inv['public_id'] . '/pdf')) ?>" class="btn btn-secondary btn-sm"><i class="bx bx-download"></i>Rechnung (PDF)</a></div></div>
    <?php elseif ($inv['status'] === 'cancelled'): ?>
      <div class="flex items-center gap-4 rounded-2xl bg-slate-100 p-5 ring-1 ring-slate-200"><i class="bx bx-block text-3xl text-slate-500"></i><p class="font-semibold text-slate-800">Diese Rechnung wurde storniert und ist nicht mehr gültig.</p></div>
    <?php else: ?>
      <div class="flex flex-col gap-4 rounded-2xl bg-white/80 p-5 shadow-soft ring-1 ring-slate-200/80 backdrop-blur sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
          <span class="grid size-12 shrink-0 place-items-center rounded-xl <?= $ds === 'overdue' ? 'bg-rose-50 text-rose-600' : 'bg-brand-50 text-brand-600' ?> text-2xl"><i class="bx bx-receipt"></i></span>
          <div><p class="font-semibold text-slate-900"><?= $ds === 'overdue' ? 'Diese Rechnung ist überfällig' : ($ds === 'partial' ? 'Teilzahlung erhalten – vielen Dank' : 'Rechnung zur Zahlung') ?></p>
            <p class="text-sm text-slate-500"><?php if ($inv['paid_total'] > 0): ?>Noch offen <strong class="text-slate-800"><?= money($inv['open_amount'], $inv['currency']) ?></strong> von <?= money($inv['amount_due'], $inv['currency']) ?><?php else: ?>Betrag <strong class="text-slate-800"><?= money($inv['amount_due'], $inv['currency']) ?></strong><?php endif; ?> · nächste Fälligkeit <?= fmt_date($inv['due_date']) ?></p></div>
        </div>
        <span class="flex flex-wrap gap-2"><?php if ($inv['payments']): ?><a href="<?= e(url('r/' . $inv['public_id'] . '/receipt')) ?>" class="btn btn-secondary btn-sm"><i class="bx bx-receipt"></i>Zahlungsbestätigung</a><?php endif; ?><a href="<?= e(url('r/' . $inv['public_id'] . '/pdf')) ?>" class="btn btn-secondary btn-sm"><i class="bx bx-download"></i>PDF<?= SwissQr::available($inv) && $inv['status'] === 'sent' ? ' mit QR-Einzahlungsschein' : '' ?></a></span>
      </div>
      <?php if (PaymentService::payable($inv)): $provs = PaymentService::enabled(); ?>
      <div class="mt-3 flex flex-col gap-2 rounded-2xl bg-white/80 p-4 shadow-soft ring-1 ring-slate-200/80 sm:flex-row sm:items-center">
        <p class="flex-1 text-sm text-slate-600"><i class="bx bx-lock-alt align-middle text-slate-400"></i> Sicher online bezahlen – die Zahlung wird direkt beim Zahlungsanbieter abgewickelt.</p>
        <?php foreach ($provs as $pv): ?>
          <form method="post" action="<?= e(url('r/' . $inv['public_id'] . '/pay/' . $pv)) ?>"><?= csrf_field() ?>
            <button class="btn btn-primary w-full"><i class="bx bx-credit-card"></i><?= money($inv['pay_amount'], $inv['currency']) ?> online bezahlen<?= count($provs) > 1 ? ' (' . e(PaymentService::PROVIDERS[$pv]) . ')' : '' ?></button>
          </form>
          <?php if ($inv['pay_amount'] + 0.004 < $inv['open_amount']): ?>
          <form method="post" action="<?= e(url('r/' . $inv['public_id'] . '/pay/' . $pv)) ?>"><?= csrf_field() ?><input type="hidden" name="full" value="1">
            <button class="btn btn-secondary w-full">Gesamten Rest (<?= money($inv['open_amount'], $inv['currency']) ?>)<?= count($provs) > 1 ? ' · ' . e(PaymentService::PROVIDERS[$pv]) : '' ?></button>
          </form>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
  <div class="reveal"><?php include APP_DIR . '/views/partials/offer-document.php'; ?></div>
  <?php $filesBase = url('r/' . $inv['public_id'] . '/files'); include APP_DIR . '/views/partials/attachments-public.php'; ?>
</div>
