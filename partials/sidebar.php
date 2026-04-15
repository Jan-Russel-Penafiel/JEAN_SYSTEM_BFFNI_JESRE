<?php
$role = $user['role'] ?? '';
$displayName = $user['name'] ?? 'User';

if ($role === 'ADMIN') {
    $displayName = 'System Admin';
}

$menu = [];

if ($role === 'ADMIN') {
    $menu = [
        ['key' => 'admin_dashboard', 'label' => 'Dashboard', 'path' => app_url('admin/dashboard.php')],
        ['key' => 'inventory', 'label' => 'Inventory', 'path' => app_url('admin/inventory.php')],
        ['key' => 'purchasing', 'label' => 'Purchasing', 'path' => app_url('admin/purchasing.php')],
        ['key' => 'receiving', 'label' => 'Receiving', 'path' => app_url('admin/receiving.php')],
        ['key' => 'storage', 'label' => 'Storage', 'path' => app_url('admin/storage.php')],
        ['key' => 'accounting', 'label' => 'Accounting', 'path' => app_url('admin/accounting.php')],
        ['key' => 'products', 'label' => 'Products', 'path' => app_url('admin/products.php')],
        ['key' => 'users', 'label' => 'Users', 'path' => app_url('admin/users.php')],
    ];
}

if ($role === 'CASHIER') {
    $menu = [
        ['key' => 'cashier_dashboard', 'label' => 'Cashier Dashboard', 'path' => app_url('cashier/dashboard.php')],
        ['key' => 'browse_products', 'label' => 'Browse Products', 'path' => app_url('cashier/products.php')],
        ['key' => 'sales_orders', 'label' => 'Sales Orders', 'path' => app_url('cashier/orders.php')],
        ['key' => 'payments', 'label' => 'Payments', 'path' => app_url('cashier/payments.php')],
    ];
}
?>
<nav class="fixed left-0 right-0 top-0 z-20 border-b border-brand-100 bg-white/95 backdrop-blur md:hidden">
    <div class="px-4 py-3">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-brand-700">JZ Sisters Trading OPC</p>
                <p class="text-xs text-slate-500"><?= e($displayName); ?> (<?= e($role); ?>)</p>
            </div>
            <a href="<?= e(app_url('logout.php')); ?>" class="rounded-md bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white">Logout</a>
        </div>
        <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
            <?php foreach ($menu as $item): ?>
                <a href="<?= e($item['path']); ?>"
                   class="whitespace-nowrap rounded-md px-3 py-1.5 text-xs font-medium <?= $activePage === $item['key'] ? 'bg-brand-600 text-white' : 'bg-brand-50 text-brand-700'; ?>">
                    <?= e($item['label']); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</nav>

<aside class="hidden md:fixed md:inset-y-0 md:left-0 md:z-30 md:flex md:w-72 lg:w-80 flex-col border-r border-brand-100 bg-white/95 backdrop-blur">
    <div class="border-b border-brand-100 p-6">
        <h1 class="text-xl font-bold text-brand-700">JZ Sisters Trading OPC</h1>
        <p class="mt-1 text-sm text-slate-500">Management System</p>
    </div>
    <div class="flex flex-1 flex-col p-4">
        <div class="space-y-2">
            <?php foreach ($menu as $item): ?>
                <a href="<?= e($item['path']); ?>"
                   class="block rounded-lg px-4 py-2.5 text-sm font-medium transition <?= $activePage === $item['key'] ? 'bg-brand-600 text-white shadow-md shadow-brand-200' : 'text-slate-700 hover:bg-brand-50 hover:text-brand-700'; ?>">
                    <?= e($item['label']); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="mt-auto border-t border-brand-100 pt-4">
            <p class="text-sm font-semibold text-brand-700"><?= e($displayName); ?> (<?= e($role); ?>)</p>
            <a href="<?= e(app_url('logout.php')); ?>" class="mt-2 block rounded-lg bg-brand-600 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-brand-700">Logout</a>
        </div>
    </div>
</aside>
