<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Unauthorized Access';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized</title>
    <link rel="stylesheet" href="<?= e(asset_url('css/tailwind.css')); ?>">
</head>
<body class="min-h-screen bg-rose-50 flex items-center justify-center p-4">
<div class="max-w-lg w-full rounded-2xl bg-white p-8 shadow-lg border border-rose-100 text-center">
    <h1 class="text-2xl font-bold text-rose-700">Unauthorized</h1>
    <p class="mt-3 text-slate-600">You do not have permission to access this page.</p>
    <a href="<?= e(app_url('index.php')); ?>" class="mt-6 inline-flex rounded-lg bg-rose-600 px-5 py-2 text-white font-semibold hover:bg-rose-700">Back to Dashboard</a>
</div>
</body>
</html>
