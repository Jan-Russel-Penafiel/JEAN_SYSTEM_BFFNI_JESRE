<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!is_logged_in()) {
    header('Location: ' . app_url('login.php'));
    exit;
}

$user = current_user();

$roleRoutes = [
    'CASHIER' => 'department/dashboard.php',
    'INVENTORY' => 'department/dashboard.php',
    'PURCHASING' => 'department/dashboard.php',
    'ACCOUNTING' => 'department/dashboard.php',
];

$role = (string)($user['role'] ?? '');
if (isset($roleRoutes[$role])) {
    header('Location: ' . app_url($roleRoutes[$role]));
    exit;
}

header('Location: ' . app_url('unauthorized.php'));
exit;
