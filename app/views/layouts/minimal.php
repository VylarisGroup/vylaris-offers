<!doctype html>
<html lang="de" class="h-full bg-slate-50 antialiased">
<head><?php include APP_DIR . '/views/partials/head.php'; ?><meta name="robots" content="noindex"></head>
<body class="min-h-full font-sans text-slate-800">
<main class="flex min-h-screen items-center justify-center px-4 py-12"><?= $content ?></main>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
