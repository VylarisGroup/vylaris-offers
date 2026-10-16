<?php
/** Gemeinsamer <head>: Tailwind, Boxicons, Schrift (alles lokal, keine externen Anfragen – DSGVO), Designsystem. */
$brand = preg_match('/^#[0-9a-f]{6}$/i', (string) Settings::get('brand_color')) ? Settings::get('brand_color') : '#4f46e5';
$siteName = isset($siteName) && $siteName !== '' ? (string) $siteName : (string) Settings::get('company_name');
$pageTitle = isset($title) && $title ? $title . ' · ' . $siteName : $siteName;
$favicon = Brand::faviconUrl();
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="base-url" content="<?= e(rtrim(url(''), '/')) ?>">
<title><?= e($pageTitle) ?></title>
<?php if ($favicon !== ''): ?><link rel="icon" href="<?= e($favicon) ?>"><?php else: ?><link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect width="32" height="32" rx="9" fill="' . $brand . '"/><path d="M10 11h12M10 16h12M10 21h7" stroke="white" stroke-width="2.6" stroke-linecap="round"/></svg>') ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= e(url('assets/vendor/inter/inter.css')) ?>">
<link rel="stylesheet" href="<?= e(url('assets/vendor/boxicons/css/boxicons.min.css')) ?>">
<link rel="preload" href="<?= e(url('assets/vendor/inter/inter-latin-wght-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<script>document.documentElement.classList.add('js');</script>
<script src="<?= e(url('assets/vendor/tailwind/tailwindcss-browser-4.3.3.js')) ?>"></script>
<style>:root{--brand: <?= e($brand) ?>;}</style>
<style type="text/tailwindcss">
@theme {
  --font-sans: "Inter", ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
  --color-brand-50:  color-mix(in oklab, var(--brand) 6%, white);
  --color-brand-100: color-mix(in oklab, var(--brand) 13%, white);
  --color-brand-200: color-mix(in oklab, var(--brand) 26%, white);
  --color-brand-300: color-mix(in oklab, var(--brand) 45%, white);
  --color-brand-400: color-mix(in oklab, var(--brand) 70%, white);
  --color-brand-500: color-mix(in oklab, var(--brand) 88%, white);
  --color-brand-600: var(--brand);
  --color-brand-700: color-mix(in oklab, var(--brand) 84%, black);
  --color-brand-800: color-mix(in oklab, var(--brand) 68%, black);
  --color-brand-900: color-mix(in oklab, var(--brand) 50%, black);
  --shadow-soft: 0 1px 2px rgb(15 23 42 / 0.04), 0 4px 16px -4px rgb(15 23 42 / 0.06);
  --shadow-lift: 0 2px 4px rgb(15 23 42 / 0.04), 0 14px 32px -10px rgb(15 23 42 / 0.16);
}
@layer components {
  .btn { @apply inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold whitespace-nowrap transition duration-200 select-none cursor-pointer focus-visible:outline-none focus-visible:ring-4 disabled:opacity-60 disabled:cursor-not-allowed active:scale-[.98]; }
  .btn i { @apply text-lg leading-none; }
  .btn-primary { @apply bg-brand-600 text-white shadow-sm shadow-brand-600/25 hover:bg-brand-700 hover:shadow-md hover:shadow-brand-600/25 hover:-translate-y-px focus-visible:ring-brand-500/30; }
  .btn-secondary { @apply bg-white text-slate-700 ring-1 ring-inset ring-slate-200 shadow-sm hover:bg-slate-50 hover:ring-slate-300 hover:text-slate-900 focus-visible:ring-slate-300/50; }
  .btn-ghost { @apply text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus-visible:ring-slate-200; }
  .btn-danger { @apply bg-rose-600 text-white shadow-sm shadow-rose-600/20 hover:bg-rose-700 hover:-translate-y-px focus-visible:ring-rose-500/30; }
  .btn-success { @apply bg-emerald-600 text-white shadow-sm shadow-emerald-600/25 hover:bg-emerald-700 hover:-translate-y-px focus-visible:ring-emerald-500/30; }
  .btn-sm { @apply px-3 py-1.5 text-xs rounded-lg; }
  .btn-sm i { @apply text-base; }
  .btn-lg { @apply px-6 py-3.5 text-base rounded-2xl; }
  .btn-icon { @apply size-9 p-0 rounded-lg; }
  .card { @apply bg-white rounded-2xl ring-1 ring-slate-200/80 shadow-soft; }
  .card-hover { @apply transition duration-300 hover:shadow-lift hover:-translate-y-0.5 hover:ring-slate-300/80; }
  .card-header { @apply flex items-center justify-between gap-3 px-5 sm:px-6 py-4 border-b border-slate-100; }
  .card-title { @apply text-[15px] font-semibold text-slate-900 flex items-center gap-2; }
  .card-title i { @apply text-xl text-slate-400; }
  .input { @apply block w-full rounded-xl border-0 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 transition duration-150 hover:ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500 focus:outline-none disabled:bg-slate-50 disabled:text-slate-500; }
  select.input { @apply pr-9 appearance-none bg-no-repeat; background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%2394a3b8' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.6' d='M6 8l4 4 4-4'/%3e%3c/svg%3e"); background-position: right .6rem center; background-size: 1.3em; }
  textarea.input { @apply min-h-[90px] leading-relaxed; }
  .input-error { @apply ring-rose-400 focus:ring-rose-500 bg-rose-50/40; }
  .label { @apply block text-sm font-medium text-slate-700 mb-1.5; }
  .hint { @apply mt-1.5 text-xs text-slate-500; }
  .error-text { @apply mt-1.5 text-xs font-medium text-rose-600 flex items-center gap-1; }
  .checkbox { @apply size-4 rounded border-slate-300 text-brand-600 accent-brand-600 cursor-pointer; }
  .badge { @apply inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset whitespace-nowrap transition-colors; }
  .badge i { @apply text-[13px]; }
  .badge-sky { @apply bg-sky-50 text-sky-700 ring-sky-600/20; }
  .badge-emerald { @apply bg-emerald-50 text-emerald-700 ring-emerald-600/20; }
  .badge-rose { @apply bg-rose-50 text-rose-700 ring-rose-600/20; }
  .badge-slate { @apply bg-slate-100 text-slate-600 ring-slate-500/20; }
  .badge-violet { @apply bg-violet-50 text-violet-700 ring-violet-600/20; }
  .badge-amber { @apply bg-amber-50 text-amber-800 ring-amber-600/25; }
  .badge-indigo { @apply bg-indigo-50 text-indigo-700 ring-indigo-600/20; }
  .badge-brand { @apply bg-brand-50 text-brand-700 ring-brand-600/20; }
  .table { @apply w-full text-sm; }
  .table th { @apply px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 bg-slate-50/70 first:pl-5 sm:first:pl-6 last:pr-5 sm:last:pr-6; }
  .table td { @apply px-4 py-3.5 text-slate-700 align-middle border-t border-slate-100 first:pl-5 sm:first:pl-6 last:pr-5 sm:last:pr-6; }
  .table tbody tr { @apply transition-colors duration-150 hover:bg-slate-50/80; }
  .table tr.row-link { @apply cursor-pointer; }
  .nav-link { @apply flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-slate-600 transition duration-150 hover:bg-slate-100 hover:text-slate-900; }
  .nav-link i { @apply text-xl text-slate-400 transition-colors duration-150; }
  .nav-link:hover i { @apply text-slate-600; }
  .nav-link.is-active { @apply bg-brand-50 text-brand-700; }
  .nav-group-btn { @apply flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm font-semibold text-slate-700 transition duration-150 hover:bg-slate-100 hover:text-slate-900 cursor-pointer; }
  .nav-group-btn > i:first-child { @apply text-xl text-slate-400; }
  .nav-chevron { @apply text-lg text-slate-400 transition-transform duration-200; }
  .nav-group[data-open] .nav-chevron { @apply rotate-180; }
  .nav-group[data-open] .nav-group-badge { @apply hidden; }
  .nav-group[data-has-active="1"] > .nav-group-btn { @apply text-brand-700; }
  .nav-group-body { display: grid; grid-template-rows: 0fr; transition: grid-template-rows .22s cubic-bezier(.2,.8,.2,1); }
  .nav-group[data-open] > .nav-group-body { grid-template-rows: 1fr; }
  .nav-group:not([data-open]) .nav-group-body a { visibility: hidden; transition: visibility 0s linear .22s; }
  [data-nav].no-anim *, [data-nav].no-anim *::after { transition: none !important; }
  .nav-sub { @apply ml-5 border-l border-slate-200 pl-2; }
  .nav-link-sub { @apply py-1.5; }
  .nav-link-sub i { @apply text-lg; }
  @media (prefers-reduced-motion: reduce) { .nav-group-body { transition: none; } .nav-chevron { transition: none; } }
  .nav-link.is-active i { @apply text-brand-600; }
  .tab { @apply inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium text-slate-600 transition hover:text-slate-900 hover:bg-white/70; }
  .tab.is-active { @apply bg-white text-slate-900 shadow-sm ring-1 ring-slate-200/80; }
  .kpi-icon { @apply grid size-11 place-items-center rounded-xl text-2xl; }
  .dropdown-item { @apply flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 hover:text-slate-900 cursor-pointer; }
  .dropdown-item i { @apply text-lg text-slate-400; }
  .section-label { @apply px-3 pt-5 pb-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-400; }
  .empty-state { @apply flex flex-col items-center justify-center text-center px-6 py-14; }
  .empty-state > i { @apply grid size-14 place-items-center rounded-2xl bg-slate-100 text-3xl text-slate-400 mb-4; }
  .link { @apply font-medium text-brand-700 hover:text-brand-800 underline decoration-brand-300 underline-offset-2 hover:decoration-brand-600 transition-colors; }
  .dl-row { @apply flex justify-between gap-4 py-2 text-sm; }
  .dl-row dt { @apply text-slate-500; }
  .dl-row dd { @apply text-slate-900 font-medium text-right; }
}
</style>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
