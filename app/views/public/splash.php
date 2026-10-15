<?php
/**
 * Startseite (Splash), gerendert aus den Blöcken des Website-Builders.
 * Erwartet: $doc (SiteBuilder-Dokument), $preview (bool), optional $error/$email für die Portal-Anmeldung.
 */
$siteName = (string) ($doc['seo']['title'] ?: Settings::get('company_name'));
$title = '';
$logo = Brand::logoUrl();
$company = (string) Settings::get('company_name');
$href = static fn(string $l): string => e(SiteBuilder::href($l));
$btn = static function (string $label, string $link, string $class = 'btn-primary btn-lg') use ($href): string {
    return ($label !== '' && $link !== '') ? '<a href="' . $href($link) . '" class="btn ' . $class . '">' . e($label) . '</a>' : '';
};
$paras = static function (string $text, string $class = '') {
    foreach (preg_split('/\n{2,}/', trim($text)) ?: [] as $p) {
        if (trim($p) !== '') {
            echo '<p class="' . e($class) . '">' . nl2br(e(trim($p)), false) . '</p>';
        }
    }
};
$companyAddr = trim(implode(', ', array_filter([(string) Settings::get('company_street'), trim(Settings::get('company_zip') . ' ' . Settings::get('company_city'))])));
$inqBlock = null;
foreach ($doc['blocks'] as $bb) {
    if ($bb['type'] === 'inquiry' && $bb['visible'] && InquiryService::enabled()) {
        $inqBlock = $bb;
        break;
    }
}
$ctaUrl = $doc['nav']['cta_url'] !== '' ? $doc['nav']['cta_url'] : ($inqBlock ? '#s-' . $inqBlock['id'] : (Settings::companyEmail() ? 'mailto:' . Settings::companyEmail() : ''));
?><!doctype html>
<html lang="de" class="scroll-smooth bg-white antialiased">
<head>
<?php include APP_DIR . '/views/partials/head.php'; ?>
<meta name="description" content="<?= e($doc['seo']['description']) ?>">
<meta property="og:title" content="<?= e($siteName) ?>">
<meta property="og:description" content="<?= e($doc['seo']['description']) ?>">
<?php if (!$doc['seo']['index'] || $preview): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
</head>
<body class="min-h-screen font-sans text-slate-800">
<header class="sticky top-0 z-40 border-b border-slate-200/70 bg-white/85 backdrop-blur-md">
  <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
    <a href="<?= e(url('')) ?>" class="flex items-center gap-3">
      <?php if ($logo): ?><img src="<?= e($logo) ?>" alt="<?= e($company) ?>" class="h-9 w-auto max-w-[180px] object-contain">
      <?php else: ?><span class="grid size-9 place-items-center rounded-xl bg-brand-600 text-white shadow-sm shadow-brand-600/30"><i class="bx bx-briefcase-alt-2 text-xl"></i></span><span class="text-base font-bold tracking-tight text-slate-900"><?= e($company) ?></span><?php endif; ?>
    </a>
    <nav class="flex items-center gap-2">
      <?php if ($doc['nav']['show_portal']): ?><a href="<?= e(url('portal')) ?>" class="btn btn-ghost btn-sm"><i class="bx bx-user-circle"></i>Kundenportal</a><?php endif; ?>
      <?= $btn($doc['nav']['cta_label'], $ctaUrl, 'btn-primary btn-sm') ?>
    </nav>
  </div>
</header>

<main>
<?php foreach ($doc['blocks'] as $b): if (!$b['visible'] || ($b['type'] === 'cta' && $b['title'] === '')) continue; $id = 's-' . $b['id']; ?>
<?php switch ($b['type']):
  case 'hero':
    $bg = ['soft' => 'bg-gradient-to-b from-brand-100/70 via-brand-50/40 to-white', 'brand' => 'bg-gradient-to-br from-brand-600 to-brand-800 text-white', 'plain' => 'bg-white'][$b['background']] ?? 'bg-white';
    $dark = $b['background'] === 'brand';
    $center = $b['align'] === 'center' && !$b['show_login'];
    $hasSide = $b['show_login'] || ($b['image'] !== '' && !$center);
?>
<section id="<?= e($id) ?>" class="<?= $bg ?>">
  <div class="mx-auto grid max-w-6xl items-center gap-12 px-4 py-16 sm:px-6 sm:py-24 <?= $hasSide ? 'lg:grid-cols-2' : '' ?>">
    <div class="reveal <?= $center ? 'mx-auto max-w-3xl text-center' : '' ?>">
      <?php if ($b['eyebrow'] !== ''): ?><span class="badge <?= $dark ? 'bg-white/15 text-white ring-white/30' : 'badge-brand' ?>"><i class="bx bx-lock-alt"></i><?= e($b['eyebrow']) ?></span><?php endif; ?>
      <h1 class="mt-5 text-4xl font-bold tracking-tight sm:text-5xl <?= $dark ? 'text-white' : 'text-slate-900' ?>"><?= e($b['title']) ?></h1>
      <?php if ($b['subtitle'] !== ''): ?><p class="mt-5 max-w-xl text-lg leading-relaxed <?= $dark ? 'text-white/85' : 'text-slate-600' ?> <?= $center ? 'mx-auto' : '' ?>"><?= nl2br(e($b['subtitle']), false) ?></p><?php endif; ?>
      <?php if ($b['btn1_label'] !== '' || $b['btn2_label'] !== ''): ?>
        <div class="mt-8 flex flex-wrap gap-3 <?= $center ? 'justify-center' : '' ?>">
          <?= $btn($b['btn1_label'], $b['btn1_url'], $dark ? 'bg-white text-brand-700 hover:bg-brand-50 btn-lg' : 'btn-primary btn-lg') ?>
          <?= $btn($b['btn2_label'], $b['btn2_url'], $dark ? 'bg-white/10 text-white ring-1 ring-inset ring-white/40 hover:bg-white/20 btn-lg' : 'btn-secondary btn-lg') ?>
        </div>
      <?php endif; ?>
      <?php if ($b['bullets']): ?>
        <ul class="mt-8 space-y-3 text-sm <?= $center ? 'mx-auto inline-block text-left' : '' ?> <?= $dark ? 'text-white/90' : 'text-slate-700' ?>">
          <?php foreach ($b['bullets'] as $li): ?><li class="flex items-center gap-3"><span class="grid size-8 shrink-0 place-items-center rounded-lg text-lg <?= $dark ? 'bg-white/15 text-white' : 'bg-white text-brand-600 shadow-sm ring-1 ring-slate-200' ?>"><i class="bx <?= e($li['icon']) ?>"></i></span><?= e($li['text']) ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    <?php if ($b['show_login']): ?>
      <div class="reveal reveal-delay-1 card p-6 text-slate-800 sm:p-8" id="login">
        <h2 class="text-lg font-semibold text-slate-900">Im Kundenportal anmelden</h2>
        <p class="mt-1 text-sm text-slate-500">Geben Sie die bei uns hinterlegte E-Mail-Adresse ein. Wir senden Ihnen einen Anmeldelink.</p>
        <form method="post" action="<?= e(url('portal/login')) ?>" class="mt-6 space-y-4"><?= csrf_field() ?>
          <div>
            <label class="label" for="email-<?= e($b['id']) ?>">E-Mail-Adresse</label>
            <div class="relative"><i class="bx bx-envelope pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-lg text-slate-400"></i>
            <input class="input pl-10" type="email" id="email-<?= e($b['id']) ?>" name="email" placeholder="name@firma.ch" autocomplete="email" required></div>
          </div>
          <button class="btn btn-primary w-full"><i class="bx bx-send"></i>Anmeldelink anfordern</button>
        </form>
      </div>
    <?php elseif ($b['image'] !== '' && !$center): ?>
      <div class="reveal reveal-delay-1"><img src="<?= e(SiteBuilder::imageUrl($b['image'])) ?>" alt="" class="w-full rounded-3xl object-cover shadow-lift ring-1 ring-slate-200/60" loading="lazy"></div>
    <?php endif; ?>
  </div>
</section>
<?php break; case 'features': $cols = ['2' => 'sm:grid-cols-2', '3' => 'sm:grid-cols-2 lg:grid-cols-3', '4' => 'sm:grid-cols-2 lg:grid-cols-4'][$b['columns']] ?? 'sm:grid-cols-3'; ?>
<section id="<?= e($id) ?>" class="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-20">
  <div class="reveal mx-auto max-w-2xl text-center">
    <?php if ($b['title'] !== ''): ?><h2 class="text-3xl font-bold tracking-tight text-slate-900"><?= e($b['title']) ?></h2><?php endif; ?>
    <?php if ($b['subtitle'] !== ''): ?><p class="mt-3 text-base text-slate-600"><?= e($b['subtitle']) ?></p><?php endif; ?>
  </div>
  <div class="mt-12 grid gap-5 <?= $cols ?>">
    <?php foreach ($b['items'] as $i => $it): ?>
      <div class="reveal card card-hover p-6" style="transition-delay:<?= min($i, 5) * 60 ?>ms">
        <span class="kpi-icon bg-brand-50 text-brand-600"><i class="bx <?= e($it['icon']) ?>"></i></span>
        <h3 class="mt-4 text-base font-semibold text-slate-900"><?= e($it['title']) ?></h3>
        <p class="mt-1.5 text-sm leading-relaxed text-slate-600"><?= nl2br(e($it['text']), false) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php break; case 'text': $c = $b['align'] === 'center'; ?>
<section id="<?= e($id) ?>" class="mx-auto max-w-3xl px-4 py-14 sm:px-6 <?= $c ? 'text-center' : '' ?>">
  <div class="reveal">
    <?php if ($b['title'] !== ''): ?><h2 class="text-3xl font-bold tracking-tight text-slate-900"><?= e($b['title']) ?></h2><?php endif; ?>
    <div class="mt-4 space-y-4 text-base leading-relaxed text-slate-600"><?php $paras($b['body']); ?></div>
  </div>
</section>
<?php break; case 'image_text': $left = $b['image_side'] === 'left'; ?>
<section id="<?= e($id) ?>" class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
  <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-16">
    <div class="reveal <?= $left ? 'lg:order-2' : '' ?>">
      <?php if ($b['title'] !== ''): ?><h2 class="text-3xl font-bold tracking-tight text-slate-900"><?= e($b['title']) ?></h2><?php endif; ?>
      <div class="mt-4 space-y-4 text-base leading-relaxed text-slate-600"><?php $paras($b['body']); ?></div>
      <?php if ($b['btn_label'] !== '' && $b['btn_url'] !== ''): ?><div class="mt-6"><?= $btn($b['btn_label'], $b['btn_url']) ?></div><?php endif; ?>
    </div>
    <?php if ($b['image'] !== ''): ?><div class="reveal reveal-delay-1 <?= $left ? 'lg:order-1' : '' ?>"><img src="<?= e(SiteBuilder::imageUrl($b['image'])) ?>" alt="<?= e($b['title']) ?>" class="w-full rounded-3xl object-cover shadow-lift ring-1 ring-slate-200/60" loading="lazy"></div><?php endif; ?>
  </div>
</section>
<?php break; case 'steps': ?>
<section id="<?= e($id) ?>" class="bg-slate-50/80">
  <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-20">
    <div class="reveal mx-auto max-w-2xl text-center">
      <?php if ($b['title'] !== ''): ?><h2 class="text-3xl font-bold tracking-tight text-slate-900"><?= e($b['title']) ?></h2><?php endif; ?>
      <?php if ($b['subtitle'] !== ''): ?><p class="mt-3 text-base text-slate-600"><?= e($b['subtitle']) ?></p><?php endif; ?>
    </div>
    <ol class="mt-12 grid gap-6 sm:grid-cols-<?= max(1, min(4, count($b['items']))) ?>">
      <?php foreach ($b['items'] as $i => $it): ?>
        <li class="reveal relative rounded-2xl bg-white p-6 shadow-soft ring-1 ring-slate-200/80" style="transition-delay:<?= min($i, 5) * 60 ?>ms">
          <span class="grid size-9 place-items-center rounded-full bg-brand-600 text-sm font-bold text-white"><?= $i + 1 ?></span>
          <h3 class="mt-4 text-base font-semibold text-slate-900"><?= e($it['title']) ?></h3>
          <p class="mt-1.5 text-sm leading-relaxed text-slate-600"><?= nl2br(e($it['text']), false) ?></p>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>
<?php break; case 'faq': ?>
<section id="<?= e($id) ?>" class="mx-auto max-w-3xl px-4 py-16 sm:px-6">
  <?php if ($b['title'] !== ''): ?><h2 class="reveal text-center text-3xl font-bold tracking-tight text-slate-900"><?= e($b['title']) ?></h2><?php endif; ?>
  <div class="mt-8 divide-y divide-slate-200 rounded-2xl bg-white shadow-soft ring-1 ring-slate-200/80">
    <?php foreach ($b['items'] as $it): ?>
      <details class="group px-5 py-4">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-[15px] font-semibold text-slate-900"><?= e($it['q']) ?><i class="bx bx-chevron-down text-xl text-slate-400 transition group-open:rotate-180"></i></summary>
        <div class="mt-3 space-y-3 text-sm leading-relaxed text-slate-600"><?php $paras($it['a']); ?></div>
      </details>
    <?php endforeach; ?>
  </div>
</section>
<?php break; case 'cta': $brandStyle = $b['style'] === 'brand'; ?>
<section id="<?= e($id) ?>" class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
  <div class="reveal rounded-3xl px-6 py-14 text-center sm:px-12 <?= $brandStyle ? 'bg-gradient-to-br from-brand-600 to-brand-800 text-white shadow-lift' : 'bg-brand-50 ring-1 ring-brand-100' ?>">
    <h2 class="mx-auto max-w-2xl text-3xl font-bold tracking-tight <?= $brandStyle ? 'text-white' : 'text-slate-900' ?>"><?= e($b['title']) ?></h2>
    <?php if ($b['text'] !== ''): ?><p class="mx-auto mt-3 max-w-xl text-base <?= $brandStyle ? 'text-white/85' : 'text-slate-600' ?>"><?= e($b['text']) ?></p><?php endif; ?>
    <?php if ($b['btn_label'] !== '' && $b['btn_url'] !== ''): ?><div class="mt-8"><?= $btn($b['btn_label'], $b['btn_url'], $brandStyle ? 'bg-white text-brand-700 hover:bg-brand-50 btn-lg' : 'btn-primary btn-lg') ?></div><?php endif; ?>
  </div>
</section>
<?php break; case 'inquiry': if (!InquiryService::enabled()) break; InquiryService::arm(); ?>
<section id="<?= e($id) ?>" class="bg-slate-50/70 py-16">
  <div class="mx-auto max-w-2xl px-4 sm:px-6">
    <div class="reveal text-center">
      <?php if ($b['title'] !== ''): ?><h2 class="text-3xl font-bold tracking-tight text-slate-900"><?= e($b['title']) ?></h2><?php endif; ?>
      <?php if ($b['text'] !== ''): ?><p class="mx-auto mt-3 max-w-xl text-base text-slate-600"><?= e($b['text']) ?></p><?php endif; ?>
    </div>
    <form method="post" action="<?= e(url('anfrage')) ?>" class="reveal card mt-8 space-y-4 p-6 sm:p-8" autocomplete="on"<?= !empty($preview) ? ' onsubmit="return false"' : '' ?>>
      <?= csrf_field() ?>
      <div class="hidden" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off" value=""></label></div>
      <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="label" for="iq-name">Name <span class="text-rose-500">*</span></label><input class="input" id="iq-name" name="name" required maxlength="120" autocomplete="name"></div>
        <div><label class="label" for="iq-email">E-Mail <span class="text-rose-500">*</span></label><input class="input" id="iq-email" type="email" name="email" required maxlength="190" autocomplete="email"></div>
        <?php if ($b['ask_company']): ?><div><label class="label" for="iq-company">Firma</label><input class="input" id="iq-company" name="company" maxlength="120" autocomplete="organization"></div><?php endif; ?>
        <?php if ($b['ask_phone']): ?><div><label class="label" for="iq-phone">Telefon</label><input class="input" id="iq-phone" name="phone" maxlength="50" autocomplete="tel"></div><?php endif; ?>
      </div>
      <?php if ($b['ask_subject']): ?><div><label class="label" for="iq-subject">Betreff</label><input class="input" id="iq-subject" name="subject" maxlength="190"></div><?php endif; ?>
      <div><label class="label" for="iq-message">Ihr Anliegen <span class="text-rose-500">*</span></label><textarea class="input" id="iq-message" name="message" rows="5" required minlength="10" maxlength="4000"></textarea></div>
      <label class="flex cursor-pointer items-start gap-3 text-sm text-slate-600"><input type="checkbox" class="checkbox mt-0.5" name="consent" value="1" required><span>Ich habe die <a class="link" href="<?= e(url('datenschutz')) ?>" target="_blank" rel="noopener">Datenschutzerklärung</a> gelesen und bin damit einverstanden, dass meine Angaben zur Bearbeitung meiner Anfrage gespeichert werden.</span></label>
      <button class="btn btn-primary btn-lg w-full"<?= !empty($preview) ? ' disabled' : '' ?>><i class="bx bx-send"></i><?= e($b['submit_label'] !== '' ? $b['submit_label'] : 'Anfrage senden') ?></button>
      <?php if (!empty($preview)): ?><p class="text-center text-xs text-slate-400">Vorschau – das Formular ist hier deaktiviert.</p><?php endif; ?>
    </form>
  </div>
</section>
<?php break; case 'contact': ?>
<section id="<?= e($id) ?>" class="mx-auto max-w-4xl px-4 py-16 sm:px-6">
  <div class="reveal text-center">
    <?php if ($b['title'] !== ''): ?><h2 class="text-3xl font-bold tracking-tight text-slate-900"><?= e($b['title']) ?></h2><?php endif; ?>
    <?php if ($b['text'] !== ''): ?><p class="mx-auto mt-3 max-w-xl text-base text-slate-600"><?= e($b['text']) ?></p><?php endif; ?>
  </div>
  <div class="mt-10 grid gap-4 sm:grid-cols-3">
    <?php if ($b['show_email'] && Settings::companyEmail()): ?><a href="mailto:<?= e(Settings::companyEmail()) ?>" class="reveal card card-hover p-5 text-center"><span class="kpi-icon mx-auto bg-brand-50 text-brand-600"><i class="bx bx-envelope"></i></span><p class="mt-3 text-sm font-semibold text-slate-900">E-Mail</p><p class="mt-0.5 break-all text-sm text-slate-600"><?= e(Settings::companyEmail()) ?></p></a><?php endif; ?>
    <?php if ($b['show_phone'] && Settings::get('company_phone')): ?><a href="tel:<?= e(preg_replace('/[^+\d]/', '', (string) Settings::get('company_phone'))) ?>" class="reveal card card-hover p-5 text-center"><span class="kpi-icon mx-auto bg-brand-50 text-brand-600"><i class="bx bx-phone"></i></span><p class="mt-3 text-sm font-semibold text-slate-900">Telefon</p><p class="mt-0.5 text-sm text-slate-600"><?= e(Settings::get('company_phone')) ?></p></a><?php endif; ?>
    <?php if ($b['show_address'] && $companyAddr !== ''): ?><div class="reveal card p-5 text-center"><span class="kpi-icon mx-auto bg-brand-50 text-brand-600"><i class="bx bx-map"></i></span><p class="mt-3 text-sm font-semibold text-slate-900">Adresse</p><p class="mt-0.5 text-sm text-slate-600"><?= e($company) ?><br><?= e($companyAddr) ?></p></div><?php endif; ?>
  </div>
</section>
<?php break; endswitch; ?>
<?php endforeach; ?>
<?php if (!$doc['blocks']): ?><div class="mx-auto max-w-xl px-4 py-32 text-center text-slate-500"><i class="bx bx-layout text-5xl text-slate-300"></i><p class="mt-3">Diese Seite hat noch keine Inhalte.</p></div><?php endif; ?>
</main>

<footer class="border-t border-slate-200/80 bg-slate-50/70">
  <div class="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-8 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6">
    <div>
      <p class="font-medium text-slate-700"><?= e($company) ?><?= $companyAddr !== '' ? ' · ' . e($companyAddr) : '' ?></p>
      <?php if ($doc['footer']['text'] !== ''): ?><p class="mt-1"><?= e($doc['footer']['text']) ?></p><?php endif; ?>
    </div>
    <p class="flex flex-wrap gap-x-4 gap-y-1">
      <a class="transition hover:text-slate-800" href="<?= e(url('datenschutz')) ?>"><i class="bx bx-shield-alt-2 align-middle"></i> Datenschutz</a>
      <a class="transition hover:text-slate-800" href="<?= e(url('portal')) ?>"><i class="bx bx-user-circle align-middle"></i> Kundenportal</a>
      <?php if ($doc['footer']['show_admin_link']): ?><a class="transition hover:text-slate-800" href="<?= e(url('admin')) ?>"><i class="bx bx-lock-alt align-middle"></i> Administration</a><?php endif; ?>
    </p>
  </div>
</footer>
<?php include APP_DIR . '/views/partials/flash.php'; ?>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
