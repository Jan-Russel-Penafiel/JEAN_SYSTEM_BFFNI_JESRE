<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!is_logged_in()) {
    header('Location: ' . app_url('login.php'));
    exit;
}

$user = current_user();

$roleRoutes = [
    'ADMIN' => 'admin/dashboard.php',
    'CASHIER' => 'cashier/dashboard.php',
    'INVENTORY' => 'admin/inventory.php',
    'PURCHASING' => 'admin/purchasing.php',
    'RECEIVING' => 'admin/receiving.php',
    'STORAGE' => 'admin/storage.php',
    'ACCOUNTING' => 'admin/accounting.php',
];

$role = (string)($user['role'] ?? '');
if (isset($roleRoutes[$role])) {
    header('Location: ' . app_url($roleRoutes[$role]));
    exit;
}

header('Location: ' . app_url('unauthorized.php'));
exit;
