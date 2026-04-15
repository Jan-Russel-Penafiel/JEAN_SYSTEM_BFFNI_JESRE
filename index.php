<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!is_logged_in()) {
    header('Location: ' . app_url('login.php'));
    exit;
}

$user = current_user();
if (($user['role'] ?? '') === 'ADMIN') {
    header('Location: ' . app_url('admin/dashboard.php'));
    exit;
}

header('Location: ' . app_url('cashier/dashboard.php'));
exit;
