<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['ACCOUNTING']);

$pageTitle = 'Accounting Department';
$activePage = 'accounting';

$normalizeReportMonth = static function ($monthValue): string {
    $monthValue = trim((string)$monthValue);
    $monthDate = DateTime::createFromFormat('Y-m-d', $monthValue . '-01');

    if ($monthDate instanceof DateTime && $monthDate->format('Y-m') === $monthValue) {
        return $monthValue;
    }

    return date('Y-m');
};

$formatReportMonth = static function (string $monthValue): string {
    $monthDate = DateTime::createFromFormat('Y-m-d', $monthValue . '-01');

    if ($monthDate instanceof DateTime) {
        return $monthDate->format('F Y');
    }

    return $monthValue;
};

$accountingRedirect = static function ($monthValue = '') use ($normalizeReportMonth): string {
    return app_url('department/accounting.php') . '?report_month=' . urlencode($normalizeReportMonth($monthValue));
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $returnReportMonth = $_POST['return_report_month'] ?? '';

    if ($action === 'create_expense') {
        $expenseDate = $_POST['expense_date'] ?? '';
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);

        if ($expenseDate === '' || $category === '' || $description === '' || $amount <= 0) {
            flash_set('error', 'Complete expense details are required.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO accounting_expenses (expense_date, category, description, amount) VALUES (:expense_date, :category, :description, :amount)');
            $stmt->execute([
                'expense_date' => $expenseDate,
                'category' => $category,
                'description' => $description,
                'amount' => $amount,
            ]);
            flash_set('success', 'Expense added successfully.');
        }

        header('Location: ' . $accountingRedirect($returnReportMonth));
        exit;
    }

    if ($action === 'update_expense') {
        $id = (int)($_POST['id'] ?? 0);
        $expenseDate = $_POST['expense_date'] ?? '';
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);

        if ($id > 0 && $expenseDate !== '' && $category !== '' && $description !== '' && $amount > 0) {
            $stmt = $pdo->prepare('UPDATE accounting_expenses SET expense_date = :expense_date, category = :category, description = :description, amount = :amount WHERE id = :id');
            $stmt->execute([
                'id' => $id,
                'expense_date' => $expenseDate,
                'category' => $category,
                'description' => $description,
                'amount' => $amount,
            ]);
            flash_set('success', 'Expense updated successfully.');
        }

        header('Location: ' . $accountingRedirect($returnReportMonth));
        exit;
    }

    if ($action === 'delete_expense') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM accounting_expenses WHERE id = :id');
            $stmt->execute(['id' => $id]);
            flash_set('success', 'Expense deleted.');
        }

        header('Location: ' . $accountingRedirect($returnReportMonth));
        exit;
    }
}

$currentReportMonth = date('Y-m');
$selectedReportMonth = $normalizeReportMonth($_GET['report_month'] ?? $currentReportMonth);
$selectedReportLabel = $formatReportMonth($selectedReportMonth);
$selectedStartDate = $selectedReportMonth . '-01';
$selectedNextDate = date('Y-m-d', strtotime($selectedStartDate . ' +1 month'));
$selectedStartAt = $selectedStartDate . ' 00:00:00';
$selectedNextAt = $selectedNextDate . ' 00:00:00';

$totalSales = (float)$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM sales_orders WHERE payment_status = 'PAID'")->fetchColumn();
$totalExpenses = (float)$pdo->query('SELECT COALESCE(SUM(amount),0) FROM accounting_expenses')->fetchColumn();
$netIncome = $totalSales - $totalExpenses;

$monthlySalesRows = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS report_month, COUNT(*) AS paid_orders, COALESCE(SUM(total_amount),0) AS total_sales, MAX(created_at) AS last_sales_at FROM sales_orders WHERE payment_status = 'PAID' GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY report_month DESC")->fetchAll();
$monthlyExpenseRows = $pdo->query("SELECT DATE_FORMAT(expense_date, '%Y-%m') AS report_month, COUNT(*) AS expense_entries, COALESCE(SUM(amount),0) AS total_expenses, MAX(expense_date) AS last_expense_at FROM accounting_expenses GROUP BY DATE_FORMAT(expense_date, '%Y-%m') ORDER BY report_month DESC")->fetchAll();

$monthlyReports = [];
$ensureMonthlyReport = static function (string $monthValue) use (&$monthlyReports, $formatReportMonth): void {
    if (isset($monthlyReports[$monthValue])) {
        return;
    }

    $monthlyReports[$monthValue] = [
        'month' => $monthValue,
        'label' => $formatReportMonth($monthValue),
        'paid_orders' => 0,
        'expense_entries' => 0,
        'total_sales' => 0.0,
        'total_expenses' => 0.0,
        'net_income' => 0.0,
        'last_sales_at' => null,
        'last_expense_at' => null,
        'last_activity_at' => null,
    ];
};

foreach ($monthlySalesRows as $row) {
    $monthValue = (string)$row['report_month'];
    $ensureMonthlyReport($monthValue);
    $monthlyReports[$monthValue]['paid_orders'] = (int)$row['paid_orders'];
    $monthlyReports[$monthValue]['total_sales'] = (float)$row['total_sales'];
    $monthlyReports[$monthValue]['last_sales_at'] = $row['last_sales_at'];
}

foreach ($monthlyExpenseRows as $row) {
    $monthValue = (string)$row['report_month'];
    $ensureMonthlyReport($monthValue);
    $monthlyReports[$monthValue]['expense_entries'] = (int)$row['expense_entries'];
    $monthlyReports[$monthValue]['total_expenses'] = (float)$row['total_expenses'];
    $monthlyReports[$monthValue]['last_expense_at'] = $row['last_expense_at'];
}

$ensureMonthlyReport($currentReportMonth);
$ensureMonthlyReport($selectedReportMonth);

foreach ($monthlyReports as &$report) {
    $report['net_income'] = (float)$report['total_sales'] - (float)$report['total_expenses'];

    $salesActivity = $report['last_sales_at'] ?? '';
    $expenseActivity = $report['last_expense_at'] ?? '';

    if ($salesActivity !== '' && $expenseActivity !== '') {
        $report['last_activity_at'] = strtotime($salesActivity) >= strtotime($expenseActivity) ? $salesActivity : $expenseActivity;
    } else {
        $report['last_activity_at'] = $salesActivity !== '' ? $salesActivity : ($expenseActivity !== '' ? $expenseActivity : null);
    }
}
unset($report);

krsort($monthlyReports);

$availableReportMonths = array_keys($monthlyReports);
$selectedReport = $monthlyReports[$selectedReportMonth];

$dailySalesStmt = $pdo->prepare("SELECT DATE(created_at) AS sales_date, COUNT(*) AS paid_orders, COALESCE(SUM(total_amount),0) AS total_sales FROM sales_orders WHERE payment_status = 'PAID' AND created_at >= :start_at AND created_at < :next_at GROUP BY DATE(created_at) ORDER BY sales_date DESC LIMIT 12");
$dailySalesStmt->execute([
    'start_at' => $selectedStartAt,
    'next_at' => $selectedNextAt,
]);
$dailySales = $dailySalesStmt->fetchAll();

$selectedExpensesStmt = $pdo->prepare('SELECT * FROM accounting_expenses WHERE expense_date >= :start_date AND expense_date < :next_date ORDER BY expense_date DESC, id DESC');
$selectedExpensesStmt->execute([
    'start_date' => $selectedStartDate,
    'next_date' => $selectedNextDate,
]);
$selectedExpenses = $selectedExpensesStmt->fetchAll();

$selectedSalesStmt = $pdo->prepare("SELECT order_no, total_amount, created_at FROM sales_orders WHERE payment_status = 'PAID' AND created_at >= :start_at AND created_at < :next_at ORDER BY created_at DESC LIMIT 30");
$selectedSalesStmt->execute([
    'start_at' => $selectedStartAt,
    'next_at' => $selectedNextAt,
]);
$selectedSales = $selectedSalesStmt->fetchAll();

$chartReports = array_reverse(array_slice($monthlyReports, 0, 12, true), true);
$accountingChartData = [
    'labels' => [],
    'sales' => [],
    'expenses' => [],
    'net' => [],
];

foreach ($chartReports as $report) {
    $accountingChartData['labels'][] = $report['label'];
    $accountingChartData['sales'][] = (float)$report['total_sales'];
    $accountingChartData['expenses'][] = (float)$report['total_expenses'];
    $accountingChartData['net'][] = (float)$report['net_income'];
}

include __DIR__ . '/../partials/header.php';
?>
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-brand-700">Accounting Department</h2>
            <p class="text-sm text-slate-500">Sales analytics, sales reports, stored financial records, and monthly history from past months.</p>
        </div>
        <button data-modal-open="create-expense-modal" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Create Expense</button>
    </div>

    <section class="rounded-xl border border-brand-100 bg-white p-5">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h3 class="text-base font-semibold text-brand-700">Sales Report History</h3>
                <p class="mt-1 text-sm text-slate-500">View current and past month reports. All paid sales and expense records are saved automatically in the system.</p>
            </div>
            <form method="get" class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="report_month" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Report Month</label>
                    <select id="report_month" name="report_month" class="mt-1 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <?php foreach ($availableReportMonths as $monthValue): ?>
                            <option value="<?= e($monthValue); ?>" <?= $monthValue === $selectedReportMonth ? 'selected' : ''; ?>><?= e($formatReportMonth($monthValue)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">View Report</button>
            </form>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-brand-100 bg-brand-50/50 p-4">
                <p class="text-xs text-slate-500">Selected Report</p>
                <p class="mt-1 text-lg font-bold text-brand-700"><?= e($selectedReportLabel); ?></p>
            </div>
            <div class="rounded-xl border border-brand-100 bg-emerald-50 p-4">
                <p class="text-xs text-emerald-700">Paid Orders</p>
                <p class="mt-1 text-lg font-bold text-emerald-700"><?= $selectedReport['paid_orders']; ?></p>
            </div>
            <div class="rounded-xl border border-brand-100 bg-amber-50 p-4">
                <p class="text-xs text-amber-700">Expense Entries</p>
                <p class="mt-1 text-lg font-bold text-amber-700"><?= $selectedReport['expense_entries']; ?></p>
            </div>
            <div class="rounded-xl border border-brand-100 bg-slate-50 p-4">
                <p class="text-xs text-slate-500">Last Activity</p>
                <p class="mt-1 text-sm font-semibold text-slate-700"><?= e($selectedReport['last_activity_at'] ?? 'No records yet'); ?></p>
            </div>
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <div class="rounded-xl border border-brand-100 bg-white p-4">
            <p class="text-xs text-slate-500">Total Paid Sales</p>
            <p class="mt-1 text-2xl font-bold text-emerald-600"><?= e(format_currency($totalSales)); ?></p>
            <p class="mt-2 text-xs text-slate-500">All-time revenue from paid cashier transactions.</p>
        </div>
        <div class="rounded-xl border border-brand-100 bg-white p-4">
            <p class="text-xs text-slate-500">Total Expenses</p>
            <p class="mt-1 text-2xl font-bold text-amber-600"><?= e(format_currency($totalExpenses)); ?></p>
            <p class="mt-2 text-xs text-slate-500">All recorded expense entries saved by accounting.</p>
        </div>
        <div class="rounded-xl border border-brand-100 bg-white p-4">
            <p class="text-xs text-slate-500">Net Income</p>
            <p class="mt-1 text-2xl font-bold <?= $netIncome >= 0 ? 'text-emerald-600' : 'text-rose-600'; ?>"><?= e(format_currency($netIncome)); ?></p>
            <p class="mt-2 text-xs text-slate-500">All-time income statement based on sales minus expenses.</p>
        </div>
        <div class="rounded-xl border border-brand-100 bg-white p-4">
            <p class="text-xs text-slate-500"><?= e($selectedReportLabel); ?> Sales</p>
            <p class="mt-1 text-2xl font-bold text-emerald-600"><?= e(format_currency($selectedReport['total_sales'])); ?></p>
            <p class="mt-2 text-xs text-slate-500"><?= $selectedReport['paid_orders']; ?> paid order<?= $selectedReport['paid_orders'] === 1 ? '' : 's'; ?> included in this monthly sales report.</p>
        </div>
        <div class="rounded-xl border border-brand-100 bg-white p-4">
            <p class="text-xs text-slate-500"><?= e($selectedReportLabel); ?> Expenses</p>
            <p class="mt-1 text-2xl font-bold text-amber-600"><?= e(format_currency($selectedReport['total_expenses'])); ?></p>
            <p class="mt-2 text-xs text-slate-500"><?= $selectedReport['expense_entries']; ?> expense entr<?= $selectedReport['expense_entries'] === 1 ? 'y' : 'ies'; ?> recorded for this month.</p>
        </div>
        <div class="rounded-xl border border-brand-100 bg-white p-4">
            <p class="text-xs text-slate-500"><?= e($selectedReportLabel); ?> Net Income</p>
            <p class="mt-1 text-2xl font-bold <?= $selectedReport['net_income'] >= 0 ? 'text-emerald-600' : 'text-rose-600'; ?>"><?= e(format_currency($selectedReport['net_income'])); ?></p>
            <p class="mt-2 text-xs text-slate-500">Monthly income statement for the selected report period.</p>
        </div>
    </section>

    <section class="grid gap-4 xl:grid-cols-[1.35fr_0.95fr]">
        <div class="rounded-xl border border-brand-100 bg-white p-5 overflow-hidden">
            <h3 class="text-base font-semibold text-brand-700">Monthly Sales and Expense Trend</h3>
            <p class="mt-1 text-sm text-slate-500">Last 12 saved months of financial history for sales, expenses, and net income.</p>
            <div class="mt-4 h-72">
                <canvas id="accountingHistoryChart" class="h-full w-full"></canvas>
            </div>
        </div>

        <div class="rounded-xl border border-brand-100 bg-white p-5">
            <h3 class="text-base font-semibold text-brand-700">Selected Month Snapshot</h3>
            <p class="mt-1 text-sm text-slate-500"><?= e($selectedReportLabel); ?> financial summary.</p>

            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex items-start justify-between gap-4 rounded-lg border border-slate-100 bg-slate-50/80 px-4 py-3">
                    <dt class="text-slate-500">Paid Orders</dt>
                    <dd class="font-semibold text-slate-700"><?= $selectedReport['paid_orders']; ?></dd>
                </div>
                <div class="flex items-start justify-between gap-4 rounded-lg border border-slate-100 bg-slate-50/80 px-4 py-3">
                    <dt class="text-slate-500">Expense Entries</dt>
                    <dd class="font-semibold text-slate-700"><?= $selectedReport['expense_entries']; ?></dd>
                </div>
                <div class="flex items-start justify-between gap-4 rounded-lg border border-slate-100 bg-slate-50/80 px-4 py-3">
                    <dt class="text-slate-500">Last Sale</dt>
                    <dd class="text-right font-semibold text-slate-700"><?= e($selectedReport['last_sales_at'] ?? 'No paid sales'); ?></dd>
                </div>
                <div class="flex items-start justify-between gap-4 rounded-lg border border-slate-100 bg-slate-50/80 px-4 py-3">
                    <dt class="text-slate-500">Last Expense</dt>
                    <dd class="text-right font-semibold text-slate-700"><?= e($selectedReport['last_expense_at'] ?? 'No expense records'); ?></dd>
                </div>
                <div class="rounded-lg border border-brand-100 bg-brand-50/60 px-4 py-3 text-sm text-slate-600">
                    Sales data comes from cashier payments, and all reports stay available in history through the monthly filter above.
                </div>
            </dl>
        </div>
    </section>

    <section class="rounded-xl border border-brand-100 bg-white p-4 overflow-x-auto">
        <h3 class="text-base font-semibold text-brand-700 mb-3">Daily Sales Analytics for <?= e($selectedReportLabel); ?></h3>
        <table class="min-w-full text-sm">
            <thead>
            <tr class="border-b border-slate-100 text-left text-slate-500">
                <th class="py-2 pr-3">Date</th>
                <th class="py-2 pr-3">Paid Orders</th>
                <th class="py-2 pr-3">Total Sales</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$dailySales): ?>
                <tr><td class="py-3 text-slate-500" colspan="3">No paid sales recorded for this month yet.</td></tr>
            <?php else: ?>
                <?php foreach ($dailySales as $row): ?>
                    <tr class="border-b border-slate-50">
                        <td class="py-2 pr-3"><?= e($row['sales_date']); ?></td>
                        <td class="py-2 pr-3"><?= (int)$row['paid_orders']; ?></td>
                        <td class="py-2 pr-3"><?= e(format_currency($row['total_sales'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="rounded-xl border border-brand-100 bg-white p-4 overflow-x-auto">
        <div class="mb-3">
            <h3 class="text-base font-semibold text-brand-700">Past Monthly Report History</h3>
            <p class="mt-1 text-xs text-slate-500">Open any saved month to review its sales report, expense records, and income statement.</p>
        </div>
        <table class="min-w-full text-sm">
            <thead>
            <tr class="border-b border-slate-100 text-left text-slate-500">
                <th class="py-2 pr-3">Month</th>
                <th class="py-2 pr-3">Paid Orders</th>
                <th class="py-2 pr-3">Sales</th>
                <th class="py-2 pr-3">Expenses</th>
                <th class="py-2 pr-3">Net Income</th>
                <th class="py-2 pr-3">Last Activity</th>
                <th class="py-2">Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($monthlyReports as $report): ?>
                <tr class="border-b border-slate-50 <?= $report['month'] === $selectedReportMonth ? 'bg-brand-50/50' : ''; ?>">
                    <td class="py-2 pr-3 font-medium text-slate-700"><?= e($report['label']); ?></td>
                    <td class="py-2 pr-3"><?= $report['paid_orders']; ?></td>
                    <td class="py-2 pr-3"><?= e(format_currency($report['total_sales'])); ?></td>
                    <td class="py-2 pr-3"><?= e(format_currency($report['total_expenses'])); ?></td>
                    <td class="py-2 pr-3">
                        <span class="font-semibold <?= $report['net_income'] >= 0 ? 'text-emerald-600' : 'text-rose-600'; ?>"><?= e(format_currency($report['net_income'])); ?></span>
                    </td>
                    <td class="py-2 pr-3"><?= e($report['last_activity_at'] ?? 'No records'); ?></td>
                    <td class="py-2">
                        <a href="<?= e(app_url('department/accounting.php')); ?>?report_month=<?= e($report['month']); ?>" class="rounded-md bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-700">View Report</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="rounded-xl border border-brand-100 bg-white p-4 overflow-x-auto">
        <h3 class="text-base font-semibold text-brand-700 mb-3">Sales and Expenses for <?= e($selectedReportLabel); ?></h3>
        <div class="grid gap-6 lg:grid-cols-2">
            <div>
                <p class="mb-2 text-sm font-semibold text-slate-700">Paid Sales Records</p>
                <table class="min-w-full text-sm">
                    <thead>
                    <tr class="border-b border-slate-100 text-left text-slate-500">
                        <th class="py-2 pr-3">Order #</th>
                        <th class="py-2 pr-3">Amount</th>
                        <th class="py-2 pr-3">Date</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$selectedSales): ?>
                        <tr><td class="py-3 text-slate-500" colspan="3">No paid sales for this month.</td></tr>
                    <?php else: ?>
                        <?php foreach ($selectedSales as $sale): ?>
                            <tr class="border-b border-slate-50">
                                <td class="py-2 pr-3"><?= e($sale['order_no']); ?></td>
                                <td class="py-2 pr-3"><?= e(format_currency($sale['total_amount'])); ?></td>
                                <td class="py-2 pr-3"><?= e($sale['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div>
                <div class="mb-2 flex items-center justify-between gap-2">
                    <p class="text-sm font-semibold text-slate-700">Expense Records</p>
                    <button data-modal-open="create-expense-modal" class="rounded-md bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-700">Create</button>
                </div>
                <table class="min-w-full text-sm">
                    <thead>
                    <tr class="border-b border-slate-100 text-left text-slate-500">
                        <th class="py-2 pr-3">Date</th>
                        <th class="py-2 pr-3">Category</th>
                        <th class="py-2 pr-3">Amount</th>
                        <th class="py-2">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$selectedExpenses): ?>
                        <tr><td class="py-3 text-slate-500" colspan="4">No expenses recorded for this month.</td></tr>
                    <?php else: ?>
                        <?php foreach ($selectedExpenses as $expense): ?>
                            <tr class="border-b border-slate-50">
                                <td class="py-2 pr-3"><?= e($expense['expense_date']); ?></td>
                                <td class="py-2 pr-3"><?= e($expense['category']); ?></td>
                                <td class="py-2 pr-3"><?= e(format_currency($expense['amount'])); ?></td>
                                <td class="py-2">
                                    <div class="flex flex-wrap gap-2">
                                        <button data-modal-open="view-expense-<?= (int)$expense['id']; ?>" class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">View</button>
                                        <button data-modal-open="edit-expense-<?= (int)$expense['id']; ?>" class="rounded-md bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-700">Edit</button>
                                        <button data-modal-open="delete-expense-<?= (int)$expense['id']; ?>" class="rounded-md bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<div id="create-expense-modal" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-xl rounded-xl bg-white p-6">
        <h3 class="text-lg font-semibold text-brand-700">Create Expense</h3>
        <form method="post" class="mt-4 space-y-3">
            <input type="hidden" name="action" value="create_expense">
            <input type="hidden" name="return_report_month" value="<?= e($selectedReportMonth); ?>">
            <div class="grid md:grid-cols-2 gap-3">
                <div>
                    <label class="text-sm text-slate-600">Expense Date</label>
                    <input type="date" name="expense_date" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Category</label>
                    <input name="category" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                </div>
            </div>
            <div>
                <label class="text-sm text-slate-600">Description</label>
                <textarea name="description" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required></textarea>
            </div>
            <div>
                <label class="text-sm text-slate-600">Amount</label>
                <input type="number" step="0.01" min="0.01" name="amount" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Create</button>
            </div>
        </form>
    </div>
</div>

<?php foreach ($selectedExpenses as $expense): ?>
    <div id="view-expense-<?= (int)$expense['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-lg rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-brand-700">View Expense</h3>
            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <dt class="text-slate-500">Date</dt><dd class="font-medium"><?= e($expense['expense_date']); ?></dd>
                <dt class="text-slate-500">Category</dt><dd class="font-medium"><?= e($expense['category']); ?></dd>
                <dt class="text-slate-500">Amount</dt><dd class="font-medium"><?= e(format_currency($expense['amount'])); ?></dd>
                <dt class="text-slate-500">Description</dt><dd class="font-medium"><?= e($expense['description']); ?></dd>
            </dl>
            <div class="mt-5 flex justify-end">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Close</button>
            </div>
        </div>
    </div>

    <div id="edit-expense-<?= (int)$expense['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-xl rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-brand-700">Edit Expense</h3>
            <form method="post" class="mt-4 space-y-3">
                <input type="hidden" name="action" value="update_expense">
                <input type="hidden" name="id" value="<?= (int)$expense['id']; ?>">
                <input type="hidden" name="return_report_month" value="<?= e($selectedReportMonth); ?>">
                <div class="grid md:grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm text-slate-600">Expense Date</label>
                        <input type="date" name="expense_date" value="<?= e($expense['expense_date']); ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                    </div>
                    <div>
                        <label class="text-sm text-slate-600">Category</label>
                        <input name="category" value="<?= e($expense['category']); ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                    </div>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Description</label>
                    <textarea name="description" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required><?= e($expense['description']); ?></textarea>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Amount</label>
                    <input type="number" step="0.01" min="0.01" name="amount" value="<?= e($expense['amount']); ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div id="delete-expense-<?= (int)$expense['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-rose-700">Delete Expense</h3>
            <p class="mt-2 text-sm text-slate-600">Delete this expense record?</p>
            <form method="post" class="mt-5 flex justify-end gap-2">
                <input type="hidden" name="action" value="delete_expense">
                <input type="hidden" name="id" value="<?= (int)$expense['id']; ?>">
                <input type="hidden" name="return_report_month" value="<?= e($selectedReportMonth); ?>">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white">Delete</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<script src="<?= e(asset_url('vendor/chartjs/chart.umd.js')); ?>"></script>
<script>
    (() => {
        if (typeof Chart === 'undefined') {
            return;
        }

        const chartData = <?= json_encode($accountingChartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const numberFormat = new Intl.NumberFormat('en-PH');

        Chart.defaults.font.family = 'Poppins, ui-sans-serif, system-ui, sans-serif';
        Chart.defaults.color = '#475569';
        Chart.defaults.animation = false;

        new Chart(document.getElementById('accountingHistoryChart'), {
            data: {
                labels: chartData.labels,
                datasets: [{
                    type: 'bar',
                    label: 'Sales',
                    data: chartData.sales,
                    backgroundColor: '#10b981',
                    borderRadius: 8,
                }, {
                    type: 'bar',
                    label: 'Expenses',
                    data: chartData.expenses,
                    backgroundColor: '#f59e0b',
                    borderRadius: 8,
                }, {
                    type: 'line',
                    label: 'Net Income',
                    data: chartData.net,
                    borderColor: '#dc143c',
                    backgroundColor: 'rgba(220, 20, 60, 0.15)',
                    borderWidth: 3,
                    tension: 0.35,
                    fill: false,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                events: ['mousemove', 'mouseout', 'click'],
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => ` ${context.dataset.label}: PHP ${numberFormat.format(context.parsed.y)}`,
                        },
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => `PHP ${numberFormat.format(value)}`,
                        },
                    },
                },
            },
        });
    })();
</script>
<?php include __DIR__ . '/../partials/footer.php'; ?>
