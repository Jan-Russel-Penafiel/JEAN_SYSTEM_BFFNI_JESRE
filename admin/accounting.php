<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['ACCOUNTING', 'ADMIN']);

$pageTitle = 'Accounting Department';
$activePage = 'accounting';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

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

        header('Location: ' . app_url('admin/accounting.php'));
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

        header('Location: ' . app_url('admin/accounting.php'));
        exit;
    }

    if ($action === 'delete_expense') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM accounting_expenses WHERE id = :id');
            $stmt->execute(['id' => $id]);
            flash_set('success', 'Expense deleted.');
        }

        header('Location: ' . app_url('admin/accounting.php'));
        exit;
    }
}

$totalSales = (float)$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM sales_orders WHERE payment_status = 'PAID'")->fetchColumn();
$totalExpenses = (float)$pdo->query('SELECT COALESCE(SUM(amount),0) FROM accounting_expenses')->fetchColumn();
$netIncome = $totalSales - $totalExpenses;

$salesAndExpenses = $pdo->query("SELECT DATE(created_at) AS sales_date, SUM(total_amount) AS total_sales FROM sales_orders WHERE payment_status = 'PAID' GROUP BY DATE(created_at) ORDER BY sales_date DESC LIMIT 7")->fetchAll();
$expenses = $pdo->query('SELECT * FROM accounting_expenses ORDER BY expense_date DESC, id DESC')->fetchAll();
$paidSales = $pdo->query("SELECT order_no, total_amount, created_at FROM sales_orders WHERE payment_status = 'PAID' ORDER BY id DESC LIMIT 20")->fetchAll();

include __DIR__ . '/../partials/header.php';
?>
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-brand-700">Accounting Department</h2>
            <p class="text-sm text-slate-500">Sales analytics, sales and expenses, and income statement.</p>
        </div>
        <button data-modal-open="create-expense-modal" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Create Expense</button>
    </div>

    <section class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-brand-100 bg-white p-4">
            <p class="text-xs text-slate-500">Total Paid Sales</p>
            <p class="mt-1 text-2xl font-bold text-emerald-600"><?= e(format_currency($totalSales)); ?></p>
        </div>
        <div class="rounded-xl border border-brand-100 bg-white p-4">
            <p class="text-xs text-slate-500">Total Expenses</p>
            <p class="mt-1 text-2xl font-bold text-rose-600"><?= e(format_currency($totalExpenses)); ?></p>
        </div>
        <div class="rounded-xl border border-brand-100 bg-white p-4">
            <p class="text-xs text-slate-500">Income Statement (Net)</p>
            <p class="mt-1 text-2xl font-bold <?= $netIncome >= 0 ? 'text-emerald-600' : 'text-rose-600'; ?>"><?= e(format_currency($netIncome)); ?></p>
        </div>
    </section>

    <section class="rounded-xl border border-brand-100 bg-white p-4 overflow-x-auto">
        <h3 class="text-base font-semibold text-brand-700 mb-3">Sales Analytics (Last 7 Recorded Dates)</h3>
        <table class="min-w-full text-sm">
            <thead>
            <tr class="border-b border-slate-100 text-left text-slate-500">
                <th class="py-2 pr-3">Date</th>
                <th class="py-2 pr-3">Total Sales</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$salesAndExpenses): ?>
                <tr><td class="py-3 text-slate-500" colspan="2">No paid sales yet.</td></tr>
            <?php else: ?>
                <?php foreach ($salesAndExpenses as $row): ?>
                    <tr class="border-b border-slate-50">
                        <td class="py-2 pr-3"><?= e($row['sales_date']); ?></td>
                        <td class="py-2 pr-3"><?= e(format_currency($row['total_sales'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="rounded-xl border border-brand-100 bg-white p-4 overflow-x-auto">
        <h3 class="text-base font-semibold text-brand-700 mb-3">Sales and Expenses</h3>
        <div class="grid gap-6 lg:grid-cols-2">
            <div>
                <p class="text-sm font-semibold text-slate-700 mb-2">Paid Sales</p>
                <table class="min-w-full text-sm">
                    <thead>
                    <tr class="border-b border-slate-100 text-left text-slate-500">
                        <th class="py-2 pr-3">Order #</th>
                        <th class="py-2 pr-3">Amount</th>
                        <th class="py-2 pr-3">Date</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$paidSales): ?>
                        <tr><td class="py-3 text-slate-500" colspan="3">No paid sales yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($paidSales as $sale): ?>
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
                <div class="flex items-center justify-between gap-2 mb-2">
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
                    <?php if (!$expenses): ?>
                        <tr><td class="py-3 text-slate-500" colspan="4">No expenses recorded.</td></tr>
                    <?php else: ?>
                        <?php foreach ($expenses as $expense): ?>
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

<?php foreach ($expenses as $expense): ?>
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
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white">Delete</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<?php include __DIR__ . '/../partials/footer.php'; ?>
