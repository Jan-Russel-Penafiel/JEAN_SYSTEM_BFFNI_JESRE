<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('ADMIN');

$pageTitle = 'Admin Dashboard';
$activePage = 'admin_dashboard';

$totalProducts = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$lowProducts = (int)$pdo->query('SELECT COUNT(*) FROM products WHERE stock_qty <= reorder_level')->fetchColumn();
$pendingPO = (int)$pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('PENDING','SENT_TO_RECEIVING','RETURNED','INSPECTED_NOT_OK')")->fetchColumn();
$totalSales = (float)$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM sales_orders WHERE payment_status = 'PAID'")->fetchColumn();
$totalExpenses = (float)$pdo->query('SELECT COALESCE(SUM(amount),0) FROM accounting_expenses')->fetchColumn();
$netIncome = $totalSales - $totalExpenses;

$totalUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalSalesOrders = (int)$pdo->query('SELECT COUNT(*) FROM sales_orders')->fetchColumn();
$totalSalesItems = (int)$pdo->query('SELECT COUNT(*) FROM sales_order_items')->fetchColumn();
$totalPayments = (int)$pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn();
$totalNotifications = (int)$pdo->query('SELECT COUNT(*) FROM department_notifications')->fetchColumn();

$inventoryTotal = (int)$pdo->query("SELECT COUNT(*) FROM inventory_records WHERE department = 'INVENTORY'")->fetchColumn();
$inventoryYes = (int)$pdo->query("SELECT COUNT(*) FROM inventory_records WHERE department = 'INVENTORY' AND availability_status = 'YES'")->fetchColumn();
$inventoryNo = (int)$pdo->query("SELECT COUNT(*) FROM inventory_records WHERE department = 'INVENTORY' AND availability_status = 'NO'")->fetchColumn();

$purchasingTotal = (int)$pdo->query('SELECT COUNT(*) FROM purchase_orders')->fetchColumn();
$poPendingCount = (int)$pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status = 'PENDING'")->fetchColumn();
$poSentCount = (int)$pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status = 'SENT_TO_RECEIVING'")->fetchColumn();
$poInspectedOkCount = (int)$pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status = 'INSPECTED_OK'")->fetchColumn();
$poInspectedNotOkCount = (int)$pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status = 'INSPECTED_NOT_OK'")->fetchColumn();
$poReturnedCount = (int)$pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status = 'RETURNED'")->fetchColumn();
$poStoredCount = (int)$pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status = 'STORED'")->fetchColumn();
$purchasingRejectedCount = $poInspectedNotOkCount + $poReturnedCount;

$receivingTotal = (int)$pdo->query('SELECT COUNT(*) FROM receiving_reports')->fetchColumn();
$receivingOk = (int)$pdo->query("SELECT COUNT(*) FROM receiving_reports WHERE items_ok = 'YES'")->fetchColumn();
$receivingNotOk = (int)$pdo->query("SELECT COUNT(*) FROM receiving_reports WHERE items_ok = 'NO'")->fetchColumn();

$storageTotal = (int)$pdo->query('SELECT COUNT(*) FROM storage_logs')->fetchColumn();
$storageStored = (int)$pdo->query("SELECT COUNT(*) FROM storage_logs WHERE action = 'STORE'")->fetchColumn();
$storageReturned = (int)$pdo->query("SELECT COUNT(*) FROM storage_logs WHERE action = 'RETURN_TO_PURCHASING'")->fetchColumn();

$accountingSalesCount = (int)$pdo->query("SELECT COUNT(*) FROM sales_orders WHERE payment_status = 'PAID'")->fetchColumn();
$salesUnpaidCount = (int)$pdo->query("SELECT COUNT(*) FROM sales_orders WHERE payment_status = 'UNPAID'")->fetchColumn();
$accountingExpenseCount = (int)$pdo->query('SELECT COUNT(*) FROM accounting_expenses')->fetchColumn();
$notificationPendingCount = (int)$pdo->query("SELECT COUNT(*) FROM department_notifications WHERE status = 'PENDING'")->fetchColumn();
$notificationReadCount = (int)$pdo->query("SELECT COUNT(*) FROM department_notifications WHERE status = 'READ'")->fetchColumn();

$healthyProducts = (int)$pdo->query('SELECT COUNT(*) FROM products WHERE stock_qty > reorder_level')->fetchColumn();
$criticalProducts = (int)$pdo->query('SELECT COUNT(*) FROM products WHERE stock_qty > 0 AND stock_qty <= reorder_level')->fetchColumn();
$outOfStockProducts = (int)$pdo->query('SELECT COUNT(*) FROM products WHERE stock_qty <= 0')->fetchColumn();

$totalProcessEvents = $inventoryTotal + $purchasingTotal + $receivingTotal + $storageTotal;
$approvedFlowEvents = $inventoryYes + $receivingOk + $storageStored;
$rejectedFlowEvents = $inventoryNo + $receivingNotOk + $storageReturned + $purchasingRejectedCount;
$pendingFlowEvents = max($totalProcessEvents - ($approvedFlowEvents + $rejectedFlowEvents), 0);

$dashboardChartData = [
    'recordsByModule' => [
        'labels' => [
            'Users',
            'Products',
            'Sales Orders',
            'Sales Items',
            'Payments',
            'Inventory Records',
            'Purchase Orders',
            'Receiving Reports',
            'Storage Logs',
            'Notifications',
            'Expenses',
        ],
        'values' => [
            $totalUsers,
            $totalProducts,
            $totalSalesOrders,
            $totalSalesItems,
            $totalPayments,
            $inventoryTotal,
            $purchasingTotal,
            $receivingTotal,
            $storageTotal,
            $totalNotifications,
            $accountingExpenseCount,
        ],
    ],
    'workflowDecisionMix' => [
        'labels' => ['Approved / YES', 'Rejected / NO', 'Pending / Open'],
        'values' => [$approvedFlowEvents, $rejectedFlowEvents, $pendingFlowEvents],
    ],
    'stockHealth' => [
        'labels' => ['Healthy Stock', 'Critical Stock', 'Out of Stock'],
        'values' => [$healthyProducts, $criticalProducts, $outOfStockProducts],
    ],
    'orderStatus' => [
        'labels' => [
            'Sales Paid',
            'Sales Unpaid',
            'PO Pending',
            'PO Sent',
            'PO Inspected OK',
            'PO Inspected Not OK',
            'PO Returned',
            'PO Stored',
            'Notif Pending',
            'Notif Read',
        ],
        'values' => [
            $accountingSalesCount,
            $salesUnpaidCount,
            $poPendingCount,
            $poSentCount,
            $poInspectedOkCount,
            $poInspectedNotOkCount,
            $poReturnedCount,
            $poStoredCount,
            $notificationPendingCount,
            $notificationReadCount,
        ],
    ],
    'financial' => [
        'labels' => ['Total Sales', 'Total Expenses', 'Net Income'],
        'values' => [$totalSales, $totalExpenses, $netIncome],
    ],
];

include __DIR__ . '/../partials/header.php';
?>
<div class="space-y-6">
    <div>
        <h2 class="text-2xl font-bold text-brand-700">Admin Panel</h2>
        <p class="text-sm text-slate-500">Inventory, Purchasing, Receiving, Storage, and Accounting controls.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-xl border border-brand-100 bg-white p-4">
            <p class="text-xs text-slate-500">Products</p>
            <p class="mt-1 text-2xl font-bold text-brand-700"><?= $totalProducts; ?></p>
        </div>
        <div class="rounded-xl border border-brand-100 bg-white p-4">
            <p class="text-xs text-slate-500">Low Stock</p>
            <p class="mt-1 text-2xl font-bold text-amber-600"><?= $lowProducts; ?></p>
        </div>
        <div class="rounded-xl border border-brand-100 bg-white p-4">
            <p class="text-xs text-slate-500">Pending Purchase Orders</p>
            <p class="mt-1 text-2xl font-bold text-brand-700"><?= $pendingPO; ?></p>
        </div>
        <div class="rounded-xl border border-brand-100 bg-white p-4">
            <p class="text-xs text-slate-500">Total Sales</p>
            <p class="mt-1 text-2xl font-bold text-emerald-600"><?= e(format_currency($totalSales)); ?></p>
        </div>
        <div class="rounded-xl border border-brand-100 bg-white p-4">
            <div class="flex items-start justify-between gap-2">
                <p class="text-xs text-slate-500">Net Income</p>
            </div>
            <p class="mt-1 text-2xl font-bold <?= $netIncome >= 0 ? 'text-emerald-600' : 'text-rose-600'; ?>"><?= e(format_currency($netIncome)); ?></p>
        </div>
    </div>

    <section class="rounded-xl border border-brand-100 bg-white p-5 sm:p-6">
            <h3 class="text-lg font-semibold text-brand-700">System-wide Statistics Charts</h3>
            <p class="mt-1 text-sm text-slate-500">Live data across every module: users, inventory, purchasing, receiving, storage, cashier sales, notifications, and accounting.</p>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg border border-brand-100 bg-brand-50/40 p-3">
                    <p class="text-xs text-slate-500">Total Records Tracked</p>
                    <p class="mt-1 text-xl font-bold text-brand-700"><?= $totalUsers + $totalProducts + $totalSalesOrders + $totalSalesItems + $totalPayments + $inventoryTotal + $purchasingTotal + $receivingTotal + $storageTotal + $totalNotifications + $accountingExpenseCount; ?></p>
                </div>
                <div class="rounded-lg border border-brand-100 bg-emerald-50 p-3">
                    <p class="text-xs text-emerald-700">Approved / YES Events</p>
                    <p class="mt-1 text-xl font-bold text-emerald-700"><?= $approvedFlowEvents; ?></p>
                </div>
                <div class="rounded-lg border border-brand-100 bg-rose-50 p-3">
                    <p class="text-xs text-rose-700">Rejected / NO Events</p>
                    <p class="mt-1 text-xl font-bold text-rose-700"><?= $rejectedFlowEvents; ?></p>
                </div>
                <div class="rounded-lg border border-brand-100 bg-amber-50 p-3">
                    <p class="text-xs text-amber-700">Pending / Open Events</p>
                    <p class="mt-1 text-xl font-bold text-amber-700"><?= $pendingFlowEvents; ?></p>
                </div>
            </div>

            <div class="mt-5 grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-brand-100 bg-white p-4 overflow-hidden">
                    <p class="text-sm font-semibold text-brand-700">Records by Module</p>
                    <div class="mt-3 h-56">
                        <canvas id="recordsByModuleChart" class="h-full w-full"></canvas>
                    </div>
                </div>
                <div class="rounded-xl border border-brand-100 bg-white p-4 overflow-hidden">
                    <p class="text-sm font-semibold text-brand-700">YES / NO / Pending Mix</p>
                    <div class="mt-3 h-56">
                        <canvas id="workflowDecisionMixChart" class="h-full w-full"></canvas>
                    </div>
                </div>
                <div class="rounded-xl border border-brand-100 bg-white p-4 overflow-hidden">
                    <p class="text-sm font-semibold text-brand-700">Product Stock Health</p>
                    <div class="mt-3 h-56">
                        <canvas id="stockHealthChart" class="h-full w-full"></canvas>
                    </div>
                </div>
                <div class="rounded-xl border border-brand-100 bg-white p-4 overflow-hidden">
                    <p class="text-sm font-semibold text-brand-700">Sales / Purchasing / Notification Status</p>
                    <div class="mt-3 h-56">
                        <canvas id="orderStatusChart" class="h-full w-full"></canvas>
                    </div>
                </div>
                <div class="rounded-xl border border-brand-100 bg-white p-4 md:col-span-2 overflow-hidden">
                    <p class="text-sm font-semibold text-brand-700">Financial Snapshot (PHP)</p>
                    <div class="mt-3 h-44">
                        <canvas id="financialChart" class="h-full w-full"></canvas>
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

        const chartData = <?= json_encode($dashboardChartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
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

        new Chart(document.getElementById('recordsByModuleChart'), {
            type: 'bar',
            data: {
                labels: chartData.recordsByModule.labels,
                datasets: [{
                    data: chartData.recordsByModule.values,
                    backgroundColor: ['#dc143c', '#f43f5e', '#fb7185', '#fecdd3', '#60a5fa', '#22c55e', '#2563eb', '#4f46e5', '#7c3aed', '#f59e0b', '#14b8a6'],
                    borderRadius: 6,
                }],
            },
            options: {
                ...commonBarOptions,
                scales: {
                    x: {
                        ticks: {
                            maxRotation: 45,
                            minRotation: 25,
                        },
                    },
                    y: commonBarOptions.scales.y,
                },
            },
        });

        new Chart(document.getElementById('workflowDecisionMixChart'), {
            type: 'doughnut',
            data: {
                labels: chartData.workflowDecisionMix.labels,
                datasets: [{
                    data: chartData.workflowDecisionMix.values,
                    backgroundColor: ['#10b981', '#f43f5e', '#f59e0b'],
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                events: ['mousemove', 'mouseout', 'click'],
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const value = context.parsed;
                                const total = chartData.workflowDecisionMix.values.reduce((sum, item) => sum + item, 0);
                                const pct = total > 0 ? Math.round((value / total) * 100) : 0;
                                return ` ${context.label}: ${numberFormat.format(value)} (${pct}%)`;
                            },
                        },
                    },
                },
            },
        });

        new Chart(document.getElementById('stockHealthChart'), {
            type: 'pie',
            data: {
                labels: chartData.stockHealth.labels,
                datasets: [{
                    data: chartData.stockHealth.values,
                    backgroundColor: ['#22c55e', '#f59e0b', '#ef4444'],
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                events: ['mousemove', 'mouseout', 'click'],
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                },
            },
        });

        new Chart(document.getElementById('orderStatusChart'), {
            type: 'bar',
            data: {
                labels: chartData.orderStatus.labels,
                datasets: [{
                    data: chartData.orderStatus.values,
                    backgroundColor: '#3b82f6',
                    borderRadius: 6,
                }],
            },
            options: {
                ...commonBarOptions,
                indexAxis: 'y',
            },
        });

        const financialValues = chartData.financial.values;
        new Chart(document.getElementById('financialChart'), {
            type: 'bar',
            data: {
                labels: chartData.financial.labels,
                datasets: [{
                    data: financialValues,
                    backgroundColor: ['#16a34a', '#f97316', financialValues[2] >= 0 ? '#10b981' : '#ef4444'],
                    borderRadius: 8,
                }],
            },
            options: {
                ...commonBarOptions,
                plugins: {
                    ...commonBarOptions.plugins,
                    tooltip: {
                        callbacks: {
                            label: (context) => ` PHP ${numberFormat.format(context.parsed.y)}`,
                        },
                    },
                },
            },
        });
    })();
</script>
<?php include __DIR__ . '/../partials/footer.php'; ?>
