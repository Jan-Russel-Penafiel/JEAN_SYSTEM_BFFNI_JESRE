<?php
if (!isset($pageTitle)) {
    $pageTitle = 'JZ Sisters Trading OPC System';
}
if (!isset($activePage)) {
    $activePage = '';
}
$user = current_user();
$flash = flash_get();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle); ?></title>
    <link rel="stylesheet" href="<?= e(asset_url('css/tailwind.css')); ?>">
    <style>
        .page-bg {
            background: radial-gradient(circle at top right, #ffe4e6 0%, #ffffff 40%, #ffffff 100%);
        }
    </style>
</head>
<body class="page-bg min-h-screen text-slate-800">
<div class="flex min-h-screen">
    <?php if ($user): ?>
        <?php include __DIR__ . '/sidebar.php'; ?>
    <?php endif; ?>

    <main class="flex-1 p-4 pt-24 md:ml-72 md:pt-8 md:p-8 lg:ml-80">
        <?php if ($flash): ?>
            <div class="mb-6 rounded-xl border px-4 py-3 <?= $flash['type'] === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700'; ?>">
                <?= e($flash['message']); ?>
            </div>
        <?php endif; ?>
