<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    header('Location: ' . app_url('index.php'));
    exit;
}

$errors = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $errors[] = 'Username and password are required.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $errors[] = 'Invalid login credentials.';
        } else {
            login_user($user);
            header('Location: ' . app_url('index.php'));
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - JZ Sisters Trading OPC</title>
    <link rel="stylesheet" href="<?= e(asset_url('css/tailwind.css')); ?>">
</head>
<body class="min-h-screen bg-gradient-to-br from-brand-50 via-white to-white text-slate-800">
<div class="min-h-screen grid md:grid-cols-2">
    <section class="hidden md:flex flex-col items-center justify-center bg-brand-600 text-white p-12">
        <div class="flex h-96 w-96 items-center justify-center rounded-full bg-white/15 p-4 ring-8 ring-white/70 shadow-2xl shadow-brand-900/40">
            <img src="<?= e(app_url('jz.jpg')); ?>"
                 alt="JZ Sisters Trading OPC"
                 class="h-full w-full rounded-full object-cover object-center">
        </div>
        <h1 class="mt-8 text-center text-4xl font-bold leading-tight">JZ Sisters Trading OPC</h1>
    </section>

    <section class="flex items-center justify-center p-6">
        <div class="w-full max-w-md rounded-2xl bg-white border border-brand-100 p-8 shadow-xl shadow-brand-100/40">
            <div class="mb-4 flex justify-center md:hidden">
                <div class="h-24 w-24 rounded-full border-4 border-brand-100 p-1 shadow-lg shadow-brand-100/50">
                    <img src="<?= e(app_url('jz.jpg')); ?>"
                         alt="JZ Sisters Trading OPC"
                         class="h-full w-full rounded-full object-cover object-center">
                </div>
            </div>
            <h2 class="text-2xl font-bold text-brand-700">Sign In</h2>
            <p class="mt-1 text-sm text-slate-500">Access your assigned department dashboard.</p>

            <?php if ($errors): ?>
                <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    <?= e(implode(' ', $errors)); ?>
                </div>
            <?php endif; ?>

            <form method="post" class="mt-6 space-y-4">
                <div>
                    <label for="username" class="block text-sm font-medium text-slate-600">Username</label>
                    <input id="username" name="username" type="text" value="<?= e($username); ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-100" required>
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-slate-600">Password</label>
                    <input id="password" name="password" type="password" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-100" required>
                </div>
                <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2.5 font-semibold text-white hover:bg-brand-700">Sign In</button>
            </form>

            <p class="mt-5 text-sm text-slate-600">No account yet?
                <a href="<?= e(app_url('register.php')); ?>" class="font-semibold text-brand-700 hover:underline">Sign Up</a>
            </p>
        </div>
    </section>
</div>
</body>
</html>
