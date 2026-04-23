<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!is_logged_in()) {
    header('Location: ' . app_url('login.php'));
    exit;
}

$user = current_user();

$roleRoutes = [
    'ADMIN' => 'admin/dashboard.php',
    'CASHIER' => 'department/dashboard.php',
    'INVENTORY' => 'department/dashboard.php',
    'PURCHASING' => 'department/dashboard.php',
    'RECEIVING' => 'department/dashboard.php',
    'STORAGE' => 'department/dashboard.php',
    'ACCOUNTING' => 'department/dashboard.php',
];

$role = (string)($user['role'] ?? '');
if (isset($roleRoutes[$role])) {
    header('Location: ' . app_url($roleRoutes[$role]));
    exit;
}

header('Location: ' . app_url('unauthorized.php'));
exit;
