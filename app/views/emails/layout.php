<?php
$brand = preg_match('/^#[0-9a-f]{6}$/i', (string) Settings::get('brand_color')) ? Settings::get('brand_color') : '#4f46e5';
$company = (string) Settings::get('company_name');
$logo = Brand::logoUrl();
$addr = trim(implode(' · ', array_filter([Settings::get('company_street'), trim(Settings::get('company_zip') . ' ' . Settings::get('company_city'))])));
?><!doctype html>
<html lang="de">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($title) ?></title></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#0f172a">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 12px">
<tr><td align="center">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px">
    <tr><td style="padding:0 8px 18px 8px;font-weight:700;font-size:17px;color:#0f172a">
      <?php if ($logo): ?><img src="<?= e($logo) ?>" alt="<?= e($company) ?>" style="max-height:40px;max-width:200px"><?php else: ?><?= e($company) ?><?php endif; ?>
    </td></tr>
    <tr><td style="background:#ffffff;border-radius:16px;padding:32px 32px 28px 32px;border:1px solid #e2e8f0">
      <div style="height:4px;width:48px;background:<?= e($brand) ?>;border-radius:4px;margin-bottom:20px"></div>
      <h1 style="margin:0 0 18px 0;font-size:21px;line-height:1.3;color:#0f172a"><?= e($title) ?></h1>
      <div style="font-size:15px;line-height:1.65;color:#334155"><?= $bodyHtml ?></div>
      <?php if ($ctaUrl): ?>
      <table role="presentation" cellpadding="0" cellspacing="0" style="margin:26px 0 8px 0"><tr><td style="background:<?= e($brand) ?>;border-radius:10px">
        <a href="<?= e($ctaUrl) ?>" style="display:inline-block;padding:13px 24px;color:#ffffff;font-weight:600;font-size:15px;text-decoration:none"><?= e($ctaLabel) ?></a>
      </td></tr></table>
      <p style="font-size:12px;color:#94a3b8;margin:14px 0 0 0;word-break:break-all">Falls der Button nicht funktioniert: <?= e($ctaUrl) ?></p>
      <?php endif; ?>
      <p style="font-size:15px;line-height:1.6;color:#334155;margin:24px 0 0 0">Freundliche Grüsse<br><?= e($company) ?></p>
    </td></tr>
    <tr><td style="padding:18px 8px;font-size:12px;color:#94a3b8;text-align:center;line-height:1.6">
      <?= e($company) ?><?= $addr ? ' · ' . e($addr) : '' ?><?php if (Settings::get('company_phone')): ?> · <?= e(Settings::get('company_phone')) ?><?php endif; ?>
    </td></tr>
  </table>
</td></tr>
</table>
</body>
</html>
