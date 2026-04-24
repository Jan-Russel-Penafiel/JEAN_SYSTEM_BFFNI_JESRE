<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    header('Location: ' . app_url('index.php'));
    exit;
}

$errors = [];
$name = '';
$username = '';
$role = 'CASHIER';
$roleOptions = app_roles();

$roleLabels = [
    'CASHIER' => 'Cashier',
    'INVENTORY' => 'Inventory',
    'PURCHASING' => 'Purchasing',
    'ACCOUNTING' => 'Accounting',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $selectedRole = strtoupper(trim((string)($_POST['role'] ?? 'CASHIER')));
    $role = in_array($selectedRole, $roleOptions, true) ? $selectedRole : 'CASHIER';

    if ($name === '' || $username === '' || $password === '') {
        $errors[] = 'Name, username, and password are required.';
    }
    if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
        $errors[] = 'Username must be 3-30 characters and use letters, numbers, or underscore.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Password and confirm password do not match.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        if ($stmt->fetch()) {
            $errors[] = 'Username is already taken.';
        }
    }

    if (!$errors) {
        $insert = $pdo->prepare('INSERT INTO users (name, username, password, role) VALUES (:name, :username, :password, :role)');
        $insert->execute([
            'name' => $name,
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
        ]);

        flash_set('success', 'Account created successfully. Please sign in.');
        header('Location: ' . app_url('login.php'));
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - JZ Sisters Trading OPC</title>
    <link rel="stylesheet" href="<?= e(asset_url('css/tailwind.css')); ?>">
</head>
<body class="min-h-screen bg-gradient-to-tr from-white to-brand-50 text-slate-800">
<div class="min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-lg rounded-2xl bg-white border border-brand-100 p-8 shadow-xl shadow-brand-100/40">
        <h1 class="text-2xl font-bold text-brand-700">Sign Up</h1>
        <p class="mt-1 text-sm text-slate-500">Create an account for your assigned department.</p>

        <?php if ($errors): ?>
            <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                <?= e(implode(' ', $errors)); ?>
            </div>
        <?php endif; ?>

        <form method="post" class="mt-6 space-y-4">
            <div>
                <label for="name" class="block text-sm font-medium text-slate-600">Full Name</label>
                <input id="name" name="name" value="<?= e($name); ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-100" required>
            </div>
            <div>
                <label for="username" class="block text-sm font-medium text-slate-600">Username</label>
                <input id="username" name="username" type="text" value="<?= e($username); ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-100" required>
            </div>
            <div>
                <label for="role" class="block text-sm font-medium text-slate-600">Department</label>
                <select id="role" name="role" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-100" required>
                    <?php foreach ($roleOptions as $option): ?>
                        <option value="<?= e($option); ?>" <?= $role === $option ? 'selected' : ''; ?>><?= e($roleLabels[$option] ?? $option); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label for="password" class="block text-sm font-medium text-slate-600">Password</label>
                    <input id="password" name="password" type="password" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-100" required>
                </div>
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-slate-600">Confirm Password</label>
                    <input id="confirm_password" name="confirm_password" type="password" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-100" required>
                </div>
            </div>
            <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2.5 font-semibold text-white hover:bg-brand-700">Create Account</button>
        </form>

        <p class="mt-5 text-sm text-slate-600">Already have an account?
            <a href="<?= e(app_url('login.php')); ?>" class="font-semibold text-brand-700 hover:underline">Sign In</a>
        </p>
    </div>
</div>
</body>
</html>
