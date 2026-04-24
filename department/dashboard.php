<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['CASHIER', 'INVENTORY', 'PURCHASING', 'ACCOUNTING']);

$pageTitle = 'Department Home';
$activePage = 'department_home';

$user = current_user();
$role = (string)($user['role'] ?? '');
$pdo = $GLOBALS['pdo'] ?? null;

if (!$pdo instanceof PDO) {
    throw new RuntimeException('Database connection unavailable.');
}

$queryInt = fn (string $sql): int => (int)$pdo->query($sql)->fetchColumn();
$queryFloat = fn (string $sql): float => (float)$pdo->query($sql)->fetchColumn();

$departmentConfigs = [
    'CASHIER' => [
        'name' => 'Cashier Department',
        'summary' => 'Track sales orders, payments, and order completion from a cashier-focused view.',
        'eyebrow' => 'Sales Desk Snapshot',
        'heroGradient' => 'from-brand-600 to-brand-700',
        'focus' => ['Open Orders', 'Paid Transactions', 'Daily Collections'],
        'links' => [
            ['label' => 'Open Cashier Dashboard', 'path' => app_url('cashier/dashboard.php')],
            ['label' => 'Browse Products', 'path' => app_url('cashier/products.php')],
            ['label' => 'Manage Orders', 'path' => app_url('cashier/orders.php')],
            ['label' => 'Process Payments', 'path' => app_url('cashier/payments.php')],
        ],
        'stats' => [
            ['label' => 'Sales Orders', 'value' => $queryInt('SELECT COUNT(*) FROM sales_orders'), 'valueClass' => 'text-brand-700', 'hint' => 'All cashier orders recorded today and earlier.', 'cardClass' => 'bg-white'],
            ['label' => 'Paid Orders', 'value' => $queryInt("SELECT COUNT(*) FROM sales_orders WHERE payment_status = 'PAID'"), 'valueClass' => 'text-emerald-600', 'hint' => 'Orders already settled at the register.', 'cardClass' => 'bg-white'],
            ['label' => 'Unpaid Orders', 'value' => $queryInt("SELECT COUNT(*) FROM sales_orders WHERE payment_status = 'UNPAID'"), 'valueClass' => 'text-rose-600', 'hint' => 'Transactions still waiting for payment.', 'cardClass' => 'bg-white'],
            ['label' => 'Sales Collected', 'value' => format_currency($queryFloat("SELECT COALESCE(SUM(total_amount),0) FROM sales_orders WHERE payment_status = 'PAID'")), 'valueClass' => 'text-emerald-600', 'hint' => 'Total paid sales amount.', 'cardClass' => 'bg-white'],
        ],
        'chartTitle' => 'Cashier Performance',
        'chartDescription' => 'Order completion and sales throughput for the cashier desk.',
        'charts' => [
            [
                'id' => 'cashierPaymentsChart',
                'title' => 'Payment Completion',
                'description' => 'Paid vs unpaid sales orders.',
                'type' => 'doughnut',
                'labels' => ['Paid', 'Unpaid'],
                'values' => [$queryInt("SELECT COUNT(*) FROM sales_orders WHERE payment_status = 'PAID'"), $queryInt("SELECT COUNT(*) FROM sales_orders WHERE payment_status = 'UNPAID'")],
                'colors' => ['#10b981', '#f43f5e'],
                'legendPosition' => 'bottom',
            ],
            [
                'id' => 'cashierThroughputChart',
                'title' => 'Sales Throughput',
                'description' => 'Counts of orders, items, payments, and completed sales.',
                'type' => 'bar',
                'labels' => ['Orders', 'Items', 'Payments', 'Paid Sales'],
                'values' => [$queryInt('SELECT COUNT(*) FROM sales_orders'), $queryInt('SELECT COUNT(*) FROM sales_order_items'), $queryInt('SELECT COUNT(*) FROM payments'), $queryInt("SELECT COUNT(*) FROM sales_orders WHERE payment_status = 'PAID'")],
                'colors' => ['#2563eb'],
                'legendPosition' => 'none',
            ],
        ],
        'activityTitle' => 'Recent Sales Orders',
        'activityDescription' => 'The latest cashier transactions currently in the register queue.',
        'activityType' => 'sales_orders',
        'activityRows' => $pdo->query('SELECT order_no, total_amount, payment_status, flow_status, created_at FROM sales_orders ORDER BY created_at DESC LIMIT 5')->fetchAll(),
        'notesTitle' => 'Cashier Focus',
        'notes' => [
            'Keep payment status updated before closing the register.',
            'Use the order list to follow up on unpaid transactions.',
            'Payments should reconcile with the sales order total.',
        ],
    ],
    'INVENTORY' => [
        'name' => 'Inventory Department',
        'summary' => 'Monitor product availability, stock health, and movement activity in one place.',
        'eyebrow' => 'Stock Control Snapshot',
        'heroGradient' => 'from-emerald-600 to-emerald-700',
        'focus' => ['Stock Health', 'Movement Logs', 'Reorder Watch'],
        'links' => [
            ['label' => 'Open Inventory Workspace', 'path' => app_url('department/inventory.php')],
            ['label' => 'Manage Products', 'path' => app_url('department/products.php')],
        ],
        'stats' => [
            ['label' => 'Products', 'value' => $queryInt('SELECT COUNT(*) FROM products'), 'valueClass' => 'text-brand-700', 'hint' => 'Master items tracked in stock.', 'cardClass' => 'bg-white'],
            ['label' => 'Healthy Stock', 'value' => $queryInt('SELECT COUNT(*) FROM products WHERE stock_qty > reorder_level'), 'valueClass' => 'text-emerald-600', 'hint' => 'Products safely above reorder level.', 'cardClass' => 'bg-white'],
            ['label' => 'Low Stock', 'value' => $queryInt('SELECT COUNT(*) FROM products WHERE stock_qty > 0 AND stock_qty <= reorder_level'), 'valueClass' => 'text-amber-600', 'hint' => 'Products needing replenishment soon.', 'cardClass' => 'bg-white'],
            ['label' => 'Out of Stock', 'value' => $queryInt('SELECT COUNT(*) FROM products WHERE stock_qty <= 0'), 'valueClass' => 'text-rose-600', 'hint' => 'Products currently unavailable.', 'cardClass' => 'bg-white'],
        ],
        'chartTitle' => 'Inventory Overview',
        'chartDescription' => 'A stock-health view paired with inventory movement distribution.',
        'charts' => [
            [
                'id' => 'inventoryHealthChart',
                'title' => 'Stock Health',
                'description' => 'Healthy, low, and out-of-stock product counts.',
                'type' => 'pie',
                'labels' => ['Healthy', 'Low', 'Out'],
                'values' => [$queryInt('SELECT COUNT(*) FROM products WHERE stock_qty > reorder_level'), $queryInt('SELECT COUNT(*) FROM products WHERE stock_qty > 0 AND stock_qty <= reorder_level'), $queryInt('SELECT COUNT(*) FROM products WHERE stock_qty <= 0')],
                'colors' => ['#22c55e', '#f59e0b', '#ef4444'],
                'legendPosition' => 'bottom',
            ],
            [
                'id' => 'inventoryMovementChart',
                'title' => 'Movement Mix',
                'description' => 'Inventory records by movement type.',
                'type' => 'bar',
                'labels' => ['Sale', 'Purchase', 'Return', 'Adjustment'],
                'values' => [
                    $queryInt("SELECT COUNT(*) FROM inventory_records WHERE department = 'INVENTORY' AND change_type = 'SALE'"),
                    $queryInt("SELECT COUNT(*) FROM inventory_records WHERE department = 'INVENTORY' AND change_type = 'PURCHASE'"),
                    $queryInt("SELECT COUNT(*) FROM inventory_records WHERE department = 'INVENTORY' AND change_type = 'RETURN'"),
                    $queryInt("SELECT COUNT(*) FROM inventory_records WHERE department = 'INVENTORY' AND change_type = 'ADJUSTMENT'"),
                ],
                'colors' => ['#2563eb'],
                'legendPosition' => 'none',
            ],
        ],
        'activityTitle' => 'Recent Inventory Records',
        'activityDescription' => 'Latest product movement logs captured under inventory control.',
        'activityType' => 'inventory_records',
        'activityRows' => $pdo->query("SELECT ir.change_type, ir.availability_status, ir.qty_before, ir.qty_change, ir.qty_after, ir.remarks, ir.created_at, p.product_name, p.sku FROM inventory_records ir INNER JOIN products p ON p.id = ir.product_id WHERE ir.department = 'INVENTORY' ORDER BY ir.created_at DESC LIMIT 5")->fetchAll(),
        'notesTitle' => 'Inventory Focus',
        'notes' => [
            'Review low-stock products before they become unavailable.',
            'Use movement logs to trace every stock adjustment.',
            'Keep product availability aligned with actual counts.',
        ],
    ],
    'PURCHASING' => [
        'name' => 'Purchasing Department',
        'summary' => 'Handle restocking from low-stock alert through supplier follow-up and inventory handoff.',
        'eyebrow' => 'Procurement Snapshot',
        'heroGradient' => 'from-amber-600 to-amber-700',
        'focus' => ['Low Stock Alerts', 'Supplier Tracking', 'Inventory Handoff'],
        'links' => [
            ['label' => 'Open Purchasing Workspace', 'path' => app_url('department/purchasing.php')],
        ],
        'stats' => [
            ['label' => 'Purchase Orders', 'value' => $queryInt('SELECT COUNT(*) FROM purchase_orders'), 'valueClass' => 'text-brand-700', 'hint' => 'All purchase orders logged.', 'cardClass' => 'bg-white'],
            ['label' => 'Waiting Delivery', 'value' => $queryInt("SELECT COUNT(*) FROM purchase_orders WHERE status = 'PENDING'"), 'valueClass' => 'text-amber-600', 'hint' => 'Orders waiting for supplier delivery.', 'cardClass' => 'bg-white'],
            ['label' => 'Forwarded to Inventory', 'value' => $queryInt("SELECT COUNT(*) FROM purchase_orders WHERE status = 'SENT_TO_RECEIVING'"), 'valueClass' => 'text-sky-600', 'hint' => 'Delivered orders already handed to inventory.', 'cardClass' => 'bg-white'],
            ['label' => 'Completed / Follow Up', 'value' => $queryInt("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('RETURNED','STORED')"), 'valueClass' => 'text-emerald-600', 'hint' => 'Orders completed or sent for supplier follow-up.', 'cardClass' => 'bg-white'],
        ],
        'chartTitle' => 'Purchasing Pipeline',
        'chartDescription' => 'The restocking flow from waiting delivery to inventory handoff.',
        'charts' => [
            [
                'id' => 'purchasingStatusChart',
                'title' => 'Purchase Order Status',
                'description' => 'Distribution of the procurement workflow.',
                'type' => 'bar',
                'indexAxis' => 'y',
                'labels' => ['Waiting Delivery', 'Forwarded to Inventory', 'Follow Up', 'Completed'],
                'values' => [
                    $queryInt("SELECT COUNT(*) FROM purchase_orders WHERE status = 'PENDING'"),
                    $queryInt("SELECT COUNT(*) FROM purchase_orders WHERE status = 'SENT_TO_RECEIVING'"),
                    $queryInt("SELECT COUNT(*) FROM purchase_orders WHERE status = 'RETURNED'"),
                    $queryInt("SELECT COUNT(*) FROM purchase_orders WHERE status = 'STORED'"),
                ],
                'colors' => ['#f59e0b'],
                'legendPosition' => 'none',
            ],
            [
                'id' => 'purchasingFlowChart',
                'title' => 'Order Flow Groups',
                'description' => 'Orders waiting for delivery, handed to inventory, or already closed.',
                'type' => 'doughnut',
                'labels' => ['Waiting Delivery', 'Forwarded', 'Closed'],
                'values' => [
                    $queryInt("SELECT COUNT(*) FROM purchase_orders WHERE status = 'PENDING'"),
                    $queryInt("SELECT COUNT(*) FROM purchase_orders WHERE status = 'SENT_TO_RECEIVING'"),
                    $queryInt("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('RETURNED','STORED')"),
                ],
                'colors' => ['#f97316', '#3b82f6', '#10b981'],
                'legendPosition' => 'bottom',
            ],
        ],
        'activityTitle' => 'Recent Purchase Orders',
        'activityDescription' => 'Latest supplier orders and their processing state.',
        'activityType' => 'purchase_orders',
        'activityRows' => $pdo->query('SELECT po_number, supplier_name, status, quantity, unit_cost, created_at FROM purchase_orders ORDER BY created_at DESC LIMIT 5')->fetchAll(),
        'notesTitle' => 'Purchasing Focus',
        'notes' => [
            'Review low-stock alerts before shelves run empty.',
            'Track supplier names carefully for every purchase order.',
            'Forward delivered items directly to inventory for stock update.',
            'Use follow-up status when delivery has not arrived yet.',
        ],
    ],
    'ACCOUNTING' => [
        'name' => 'Accounting Department',
        'summary' => 'Review sales, expenses, monthly sales report history, and net income from one finance workspace.',
        'eyebrow' => 'Financial Snapshot',
        'heroGradient' => 'from-rose-600 to-rose-700',
        'focus' => ['Revenue', 'Monthly Reports', 'Net Income'],
        'links' => [
            ['label' => 'Open Accounting Workspace', 'path' => app_url('department/accounting.php')],
        ],
        'stats' => [
            ['label' => 'Paid Sales', 'value' => format_currency($queryFloat("SELECT COALESCE(SUM(total_amount),0) FROM sales_orders WHERE payment_status = 'PAID'")), 'valueClass' => 'text-emerald-600', 'hint' => 'Revenue from paid orders.', 'cardClass' => 'bg-white'],
            ['label' => 'Expenses', 'value' => format_currency($queryFloat('SELECT COALESCE(SUM(amount),0) FROM accounting_expenses')), 'valueClass' => 'text-amber-600', 'hint' => 'Recorded operating expenses.', 'cardClass' => 'bg-white'],
            ['label' => 'Net Income', 'value' => format_currency($queryFloat("SELECT COALESCE(SUM(total_amount),0) FROM sales_orders WHERE payment_status = 'PAID'") - $queryFloat('SELECT COALESCE(SUM(amount),0) FROM accounting_expenses')), 'valueClass' => 'text-brand-700', 'hint' => 'Sales minus expenses.', 'cardClass' => 'bg-white'],
            ['label' => 'Pending Notifications', 'value' => $queryInt("SELECT COUNT(*) FROM department_notifications WHERE status = 'PENDING'"), 'valueClass' => 'text-rose-600', 'hint' => 'Items waiting for review.', 'cardClass' => 'bg-white'],
        ],
        'chartTitle' => 'Financial Overview',
        'chartDescription' => 'Revenue, expense, and department notification tracking.',
        'charts' => [
            [
                'id' => 'accountingFinancialChart',
                'title' => 'Financial Snapshot',
                'description' => 'Sales collected, expenses, and net income.',
                'type' => 'bar',
                'labels' => ['Sales', 'Expenses', 'Net Income'],
                'values' => [
                    $queryFloat("SELECT COALESCE(SUM(total_amount),0) FROM sales_orders WHERE payment_status = 'PAID'"),
                    $queryFloat('SELECT COALESCE(SUM(amount),0) FROM accounting_expenses'),
                    $queryFloat("SELECT COALESCE(SUM(total_amount),0) FROM sales_orders WHERE payment_status = 'PAID'") - $queryFloat('SELECT COALESCE(SUM(amount),0) FROM accounting_expenses'),
                ],
                'colors' => ['#16a34a'],
                'legendPosition' => 'none',
                'currency' => true,
            ],
            [
                'id' => 'accountingNotificationChart',
                'title' => 'Notification Status',
                'description' => 'Pending and read department notifications.',
                'type' => 'doughnut',
                'labels' => ['Pending', 'Read'],
                'values' => [$queryInt("SELECT COUNT(*) FROM department_notifications WHERE status = 'PENDING'"), $queryInt("SELECT COUNT(*) FROM department_notifications WHERE status = 'READ'")],
                'colors' => ['#f43f5e', '#10b981'],
                'legendPosition' => 'bottom',
            ],
        ],
        'activityTitle' => 'Recent Expenses',
        'activityDescription' => 'Latest costs recorded by accounting. Open the workspace for monthly report history.',
        'activityType' => 'accounting_expenses',
        'activityRows' => $pdo->query('SELECT expense_date, category, description, amount, created_at FROM accounting_expenses ORDER BY expense_date DESC, created_at DESC LIMIT 5')->fetchAll(),
        'notesTitle' => 'Accounting Focus',
        'notes' => [
            'Compare collected sales against recorded expenses.',
            'Review past monthly reports to track business performance over time.',
            'Clear pending notifications to keep departments aligned.',
            'Net income should stay visible on every review cycle.',
        ],
    ],
];

if (!isset($departmentConfigs[$role])) {
    header('Location: ' . app_url('unauthorized.php'));
    exit;
}

$config = $departmentConfigs[$role];

include __DIR__ . '/../partials/header.php';
?>
<div class="space-y-6">
    <section class="overflow-hidden rounded-2xl border border-brand-100 bg-white shadow-sm">
        <div class="bg-brand-600 p-6 text-white sm:p-8">
            <p class="text-xs uppercase tracking-[0.2em] text-white/75"><?= e($config['eyebrow']); ?></p>
            <h2 class="mt-2 text-3xl font-bold sm:text-4xl"><?= e($config['name']); ?></h2>
            <p class="mt-3 max-w-3xl text-sm text-white/85 sm:text-base"><?= e($config['summary']); ?></p>

            <div class="mt-5 flex flex-wrap gap-2">
                <?php foreach ($config['focus'] as $focus): ?>
                    <span class="rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-medium text-white/90"><?= e($focus); ?></span>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="grid gap-3 border-t border-brand-100 bg-brand-50/40 p-4 sm:grid-cols-3">
            <div class="rounded-xl border border-brand-100 bg-white p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Department Role</p>
                <p class="mt-1 text-lg font-semibold text-brand-700"><?= e($role); ?></p>
            </div>
            <div class="rounded-xl border border-brand-100 bg-white p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Portal</p>
                <p class="mt-1 text-lg font-semibold text-brand-700">Department Home</p>
            </div>
            <div class="rounded-xl border border-brand-100 bg-white p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Workspace</p>
                <p class="mt-1 text-lg font-semibold text-brand-700">Role-based dashboard</p>
            </div>
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <?php foreach ($config['stats'] as $stat): ?>
            <div class="rounded-xl border border-brand-100 <?= e($stat['cardClass']); ?> p-4 shadow-sm">
                <p class="text-xs text-slate-500"><?= e($stat['label']); ?></p>
                <p class="mt-1 text-2xl font-bold <?= e($stat['valueClass']); ?>"><?= $stat['value']; ?></p>
                <p class="mt-1 text-xs text-slate-500"><?= e($stat['hint']); ?></p>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="rounded-xl border border-brand-100 bg-white p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-brand-700"><?= e($config['chartTitle']); ?></h3>
                <p class="mt-1 text-sm text-slate-500"><?= e($config['chartDescription']); ?></p>
            </div>
            <span class="rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700">Live department data</span>
        </div>

        <div class="mt-5 grid gap-4 md:grid-cols-2">
            <?php foreach ($config['charts'] as $chart): ?>
                <div class="overflow-hidden rounded-xl border border-brand-100 bg-white p-4">
                    <p class="text-sm font-semibold text-brand-700"><?= e($chart['title']); ?></p>
                    <p class="mt-1 text-xs text-slate-500"><?= e($chart['description']); ?></p>
                    <div class="mt-3 h-56">
                        <canvas id="<?= e($chart['id']); ?>" class="h-full w-full"></canvas>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="grid gap-4 xl:grid-cols-[1.4fr_0.9fr]">
        <div class="rounded-xl border border-brand-100 bg-white p-5 sm:p-6">
            <h3 class="text-lg font-semibold text-brand-700"><?= e($config['activityTitle']); ?></h3>
            <p class="mt-1 text-sm text-slate-500"><?= e($config['activityDescription']); ?></p>

            <div class="mt-4 space-y-3">
                <?php if (!$config['activityRows']): ?>
                    <p class="text-sm text-slate-500">No records yet.</p>
                <?php else: ?>
                    <?php foreach ($config['activityRows'] as $row): ?>
                        <div class="rounded-lg border border-slate-100 bg-slate-50/70 p-4">
                            <?php if ($config['activityType'] === 'sales_orders'): ?>
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-brand-700"><?= e($row['order_no']); ?></p>
                                        <p class="mt-1 text-sm text-slate-600">Amount: <?= e(format_currency((float)$row['total_amount'])); ?></p>
                                    </div>
                                    <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold <?= $row['payment_status'] === 'PAID' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'; ?>"><?= e($row['payment_status']); ?></span>
                                </div>
                                <p class="mt-2 text-xs text-slate-400">Flow: <?= e($row['flow_status']); ?> &middot; <?= e($row['created_at']); ?></p>
                            <?php elseif ($config['activityType'] === 'inventory_records'): ?>
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-brand-700"><?= e($row['product_name']); ?></p>
                                        <p class="mt-1 text-sm text-slate-600"><?= e($row['sku']); ?> &middot; Qty <?= e((string)$row['qty_before']); ?> &rarr; <?= e((string)$row['qty_after']); ?></p>
                                    </div>
                                    <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold <?= $row['availability_status'] === 'YES' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'; ?>"><?= e((string)$row['change_type']); ?></span>
                                </div>
                                <p class="mt-2 text-xs text-slate-400"><?= e((string)$row['remarks']); ?> &middot; <?= e($row['created_at']); ?></p>
                            <?php elseif ($config['activityType'] === 'purchase_orders'): ?>
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-brand-700"><?= e($row['po_number']); ?></p>
                                        <p class="mt-1 text-sm text-slate-600"><?= e($row['supplier_name']); ?> &middot; Qty <?= e((string)$row['quantity']); ?></p>
                                    </div>
                                    <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold bg-sky-100 text-sky-700"><?= e(display_status_label($row['status'])); ?></span>
                                </div>
                                <p class="mt-2 text-xs text-slate-400">Unit Cost: <?= e(format_currency((float)$row['unit_cost'])); ?> &middot; <?= e($row['created_at']); ?></p>
                            <?php elseif ($config['activityType'] === 'accounting_expenses'): ?>
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-brand-700"><?= e($row['category']); ?></p>
                                        <p class="mt-1 text-sm text-slate-600"><?= e($row['description']); ?></p>
                                    </div>
                                    <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-semibold text-amber-700"><?= e(format_currency((float)$row['amount'])); ?></span>
                                </div>
                                <p class="mt-2 text-xs text-slate-400">Date: <?= e($row['expense_date']); ?> &middot; <?= e($row['created_at']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-xl border border-brand-100 bg-white p-5 sm:p-6">
                <h3 class="text-lg font-semibold text-brand-700"><?= e($config['notesTitle']); ?></h3>
                <div class="mt-4 space-y-3">
                    <?php foreach ($config['notes'] as $note): ?>
                        <div class="rounded-lg border border-brand-100 bg-brand-50/50 px-4 py-3 text-sm text-slate-600"><?= e($note); ?></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="rounded-xl border border-brand-100 bg-white p-5 sm:p-6">
                <h3 class="text-lg font-semibold text-brand-700">Quick Actions</h3>
                <p class="mt-1 text-sm text-slate-500">Open the pages most relevant to this department.</p>

                <div class="mt-4 grid gap-3">
                    <?php foreach ($config['links'] as $link): ?>
                        <a href="<?= e($link['path']); ?>" class="group rounded-xl border border-brand-100 bg-brand-50 px-4 py-3 transition hover:-translate-y-0.5 hover:border-brand-200 hover:bg-brand-100/70">
                            <p class="text-sm font-semibold text-brand-700"><?= e($link['label']); ?></p>
                            <p class="mt-1 text-xs text-slate-500">Go to department page</p>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>
</div>

<script src="<?= e(asset_url('vendor/chartjs/chart.umd.js')); ?>"></script>
<script>
    (() => {
        if (typeof Chart === 'undefined') {
            return;
        }

        const charts = <?= json_encode($config['charts'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const numberFormat = new Intl.NumberFormat('en-PH');

        Chart.defaults.font.family = 'Poppins, ui-sans-serif, system-ui, sans-serif';
        Chart.defaults.color = '#475569';
        Chart.defaults.animation = false;

        const commonBarOptions = {
            responsive: true,
            maintainAspectRatio: false,
            events: ['mousemove', 'mouseout', 'click'],
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (context) => {
                            const parsed = context.parsed;
                            const rawValue = typeof parsed === 'number' ? parsed : (parsed.y ?? parsed.x ?? 0);
                            return ` ${numberFormat.format(rawValue)}`;
                        },
                    },
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                    },
                },
            },
        };

        charts.forEach((chart) => {
            const element = document.getElementById(chart.id);

            if (!element) {
                return;
            }

            const baseDataset = {
                data: chart.values,
                backgroundColor: chart.colors,
                borderWidth: 0,
                borderRadius: chart.type === 'bar' ? 8 : 0,
            };

            const options = {
                responsive: true,
                maintainAspectRatio: false,
                events: ['mousemove', 'mouseout', 'click'],
                plugins: {
                    legend: {
                        display: chart.legendPosition !== 'none',
                        position: chart.legendPosition || 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const value = chart.type === 'bar' ? context.parsed.y : context.parsed;
                                return chart.currency ? ` PHP ${numberFormat.format(value)}` : ` ${numberFormat.format(value)}`;
                            },
                        },
                    },
                },
            };

            if (chart.type === 'bar') {
                options.indexAxis = chart.indexAxis || 'x';
                options.scales = {
                    ...(options.indexAxis === 'y' ? { x: { beginAtZero: true, ticks: { precision: 0 } } } : commonBarOptions.scales),
                    ...(options.indexAxis === 'y' ? { y: { ticks: { precision: 0 } } } : {}),
                };

                if (chart.indexAxis !== 'y') {
                    options.scales.y = commonBarOptions.scales.y;
                }
            }

            new Chart(element, {
                type: chart.type,
                data: {
                    labels: chart.labels,
                    datasets: [baseDataset],
                },
                options,
            });
        });
    })();
</script>
<?php include __DIR__ . '/../partials/footer.php'; ?>
