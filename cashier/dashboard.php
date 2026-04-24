<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('CASHIER');

$pageTitle = 'Cashier Dashboard';
$activePage = 'cashier_dashboard';
$user = current_user();

$availableProducts = (int)$pdo->query('SELECT COUNT(*) FROM products WHERE stock_qty > 0')->fetchColumn();
$myUnpaidOrdersStmt = $pdo->prepare("SELECT COUNT(*) FROM sales_orders WHERE cashier_id = :cashier_id AND payment_status = 'UNPAID'");
$myUnpaidOrdersStmt->execute(['cashier_id' => $user['id']]);
$myUnpaidOrders = (int)$myUnpaidOrdersStmt->fetchColumn();

$todaySalesStmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM sales_orders WHERE cashier_id = :cashier_id AND payment_status = 'PAID' AND DATE(created_at) = CURDATE()");
$todaySalesStmt->execute(['cashier_id' => $user['id']]);
$todaySales = (float)$todaySalesStmt->fetchColumn();

$recentOrdersStmt = $pdo->prepare('SELECT * FROM sales_orders WHERE cashier_id = :cashier_id ORDER BY id DESC LIMIT 6');
$recentOrdersStmt->execute(['cashier_id' => $user['id']]);
$recentOrders = $recentOrdersStmt->fetchAll();

include __DIR__ . '/../partials/header.php';
?>
<div class="space-y-6">
    <div>
        <h2 class="text-2xl font-bold text-brand-700">Cashier Dashboard</h2>
        <p class="text-sm text-slate-500">Browse products, confirm orders, mark sales orders complete, process payment, and issue receipts.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-brand-100 bg-white p-4">
            <p class="text-xs text-slate-500">Available Products</p>
            <p class="mt-1 text-2xl font-bold text-brand-700"><?= $availableProducts; ?></p>
        </div>
        <div class="rounded-xl border border-brand-100 bg-white p-4">
            <p class="text-xs text-slate-500">Unpaid Orders</p>
            <p class="mt-1 text-2xl font-bold text-amber-600"><?= $myUnpaidOrders; ?></p>
        </div>
        <div class="rounded-xl border border-brand-100 bg-white p-4">
            <p class="text-xs text-slate-500">Today Paid Sales</p>
            <p class="mt-1 text-2xl font-bold text-emerald-600"><?= e(format_currency($todaySales)); ?></p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-brand-100 bg-white p-5">
            <h3 class="text-lg font-semibold text-brand-700">Cashier Flow</h3>
            <ol class="mt-3 space-y-2 text-sm text-slate-600 list-decimal list-inside">
                <li>Browse Product</li>
                <li>Choose Product</li>
                <li>Order Confirmation</li>
                <li>Generate Sales Order</li>
                <li>Order Complete</li>
                <li>Payment</li>
                <li>Update Stock Records</li>
                <li>End</li>
            </ol>
        </section>

        <section class="rounded-xl border border-brand-100 bg-white p-5">
            <h3 class="text-lg font-semibold text-brand-700">Recent Orders</h3>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-100">
                        <th class="py-2 pr-3">Order #</th>
                        <th class="py-2 pr-3">Amount</th>
                        <th class="py-2 pr-3">Payment</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$recentOrders): ?>
                        <tr><td class="py-3 text-slate-500" colspan="3">No orders yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentOrders as $order): ?>
                            <tr class="border-b border-slate-50">
                                <td class="py-2 pr-3 font-medium text-slate-700"><?= e($order['order_no']); ?></td>
                                <td class="py-2 pr-3"><?= e(format_currency($order['total_amount'])); ?></td>
                                <td class="py-2 pr-3">
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold <?= e(status_badge_class($order['payment_status'])); ?>">
                                        <?= e($order['payment_status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
