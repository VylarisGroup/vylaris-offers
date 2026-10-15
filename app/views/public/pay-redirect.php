<?php /** @var string $url @var array $inv */ $title = 'Weiterleitung zur Zahlung'; ?>
<div class="mx-auto max-w-md text-center">
  <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-brand-50 text-3xl text-brand-600"><i class="bx bx-lock-alt"></i></span>
  <h1 class="mt-5 text-lg font-semibold text-slate-900">Weiterleitung zur sicheren Zahlung</h1>
  <p class="mt-2 text-sm text-slate-500">Rechnung <?= e($inv['number']) ?> · <?= money($inv['pay_amount'], $inv['currency']) ?></p>
  <a id="go" href="<?= e($url) ?>" rel="noopener noreferrer" class="btn btn-primary mt-6">Weiter zur Zahlung</a>
  <p class="mt-3 text-xs text-slate-400">Die Zahlung wird vom Zahlungsanbieter abgewickelt. Wir erhalten keine Kartendaten.</p>
</div>
<script>setTimeout(function () { window.location.replace(document.getElementById('go').href); }, 150);</script>
