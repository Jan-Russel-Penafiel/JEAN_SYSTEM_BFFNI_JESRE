<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('ADMIN');

$pageTitle = 'Storage Department';
$activePage = 'storage';
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_log') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);
        $fromDepartment = $_POST['from_department'] ?? 'INVENTORY';
        $storageAction = $_POST['storage_action'] ?? 'STORE';
        $notes = trim($_POST['notes'] ?? '');

        $validDept = ['INVENTORY', 'RECEIVING', 'PURCHASING'];
        $validAction = ['STORE', 'RETURN_TO_PURCHASING'];

        if (!in_array($fromDepartment, $validDept, true)) {
            $fromDepartment = 'INVENTORY';
        }
        if (!in_array($storageAction, $validAction, true)) {
            $storageAction = 'STORE';
        }

        if ($productId <= 0 || $quantity <= 0) {
            flash_set('error', 'Please provide valid product and quantity.');
        } else {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('INSERT INTO storage_logs (product_id, quantity, from_department, action, notes, created_by) VALUES (:product_id, :quantity, :from_department, :action, :notes, :created_by)');
                $stmt->execute([
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'from_department' => $fromDepartment,
                    'action' => $storageAction,
                    'notes' => $notes,
                    'created_by' => $user['id'],
                ]);

                if ($storageAction === 'RETURN_TO_PURCHASING') {
                    $notify = $pdo->prepare('INSERT INTO department_notifications (target_department, message, status) VALUES (:target_department, :message, :status)');
                    $notify->execute([
                        'target_department' => 'PURCHASING',
                        'message' => 'Storage returned stock to purchasing. Check returned item process.',
                        'status' => 'PENDING',
                    ]);
                }

                $pdo->commit();
                flash_set('success', 'Storage log created successfully.');
            } catch (Exception $e) {
                $pdo->rollBack();
                flash_set('error', 'Failed to create storage log.');
            }
        }

        header('Location: ' . app_url('admin/storage.php'));
        exit;
    }

    if ($action === 'update_log') {
        $id = (int)($_POST['id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);
        $storageAction = $_POST['storage_action'] ?? 'STORE';
        $notes = trim($_POST['notes'] ?? '');

        if ($id > 0 && $quantity > 0) {
            $storageAction = $storageAction === 'RETURN_TO_PURCHASING' ? 'RETURN_TO_PURCHASING' : 'STORE';
            $stmt = $pdo->prepare('UPDATE storage_logs SET quantity = :quantity, action = :action, notes = :notes WHERE id = :id');
            $stmt->execute([
                'id' => $id,
                'quantity' => $quantity,
                'action' => $storageAction,
                'notes' => $notes,
            ]);
            flash_set('success', 'Storage log updated.');
        }

        header('Location: ' . app_url('admin/storage.php'));
        exit;
    }

    if ($action === 'delete_log') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM storage_logs WHERE id = :id');
            $stmt->execute(['id' => $id]);
            flash_set('success', 'Storage log deleted.');
        }

        header('Location: ' . app_url('admin/storage.php'));
        exit;
    }
}

$products = $pdo->query('SELECT id, sku, product_name FROM products ORDER BY product_name ASC')->fetchAll();
$logs = $pdo->query('SELECT sl.*, p.sku, p.product_name, u.name AS created_by_name FROM storage_logs sl JOIN products p ON p.id = sl.product_id JOIN users u ON u.id = sl.created_by ORDER BY sl.id DESC')->fetchAll();

include __DIR__ . '/../partials/header.php';
?>
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-brand-700">Storage Department</h2>
            <p class="text-sm text-slate-500">Store approved items or return rejected stocks to purchasing.</p>
        </div>
        <button data-modal-open="create-log-modal" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Create Storage Log</button>
    </div>

    <section class="rounded-xl border border-brand-100 bg-white p-4 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
            <tr class="border-b border-slate-100 text-left text-slate-500">
                <th class="py-2 pr-3">Date</th>
                <th class="py-2 pr-3">Product</th>
                <th class="py-2 pr-3">Qty</th>
                <th class="py-2 pr-3">From</th>
                <th class="py-2 pr-3">Action</th>
                <th class="py-2">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$logs): ?>
                <tr><td class="py-3 text-slate-500" colspan="6">No storage logs yet.</td></tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <tr class="border-b border-slate-50">
                        <td class="py-2 pr-3"><?= e($log['created_at']); ?></td>
                        <td class="py-2 pr-3"><?= e($log['sku']); ?> - <?= e($log['product_name']); ?></td>
                        <td class="py-2 pr-3"><?= (int)$log['quantity']; ?></td>
                        <td class="py-2 pr-3"><?= e($log['from_department']); ?></td>
                        <td class="py-2 pr-3"><span class="rounded-full px-2 py-1 text-xs font-semibold <?= $log['action'] === 'STORE' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'; ?>"><?= e($log['action']); ?></span></td>
                        <td class="py-2">
                            <div class="flex flex-wrap gap-2">
                                <button data-modal-open="view-log-<?= (int)$log['id']; ?>" class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">View</button>
                                <button data-modal-open="edit-log-<?= (int)$log['id']; ?>" class="rounded-md bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-700">Edit</button>
                                <button data-modal-open="delete-log-<?= (int)$log['id']; ?>" class="rounded-md bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700">Delete</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
</div>

<div id="create-log-modal" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-xl rounded-xl bg-white p-6">
        <h3 class="text-lg font-semibold text-brand-700">Create Storage Log</h3>
        <form method="post" class="mt-4 space-y-3">
            <input type="hidden" name="action" value="create_log">
            <div>
                <label class="text-sm text-slate-600">Product</label>
                <select name="product_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                    <option value="">Select Product</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= (int)$product['id']; ?>"><?= e($product['sku']); ?> - <?= e($product['product_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid md:grid-cols-3 gap-3">
                <div>
                    <label class="text-sm text-slate-600">Quantity</label>
                    <input type="number" min="1" name="quantity" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                </div>
                <div>
                    <label class="text-sm text-slate-600">From</label>
                    <select name="from_department" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                        <option value="INVENTORY">INVENTORY</option>
                        <option value="RECEIVING">RECEIVING</option>
                        <option value="PURCHASING">PURCHASING</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Action</label>
                    <select name="storage_action" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                        <option value="STORE">STORE</option>
                        <option value="RETURN_TO_PURCHASING">RETURN_TO_PURCHASING</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="text-sm text-slate-600">Notes</label>
                <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"></textarea>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Create</button>
            </div>
        </form>
    </div>
</div>

<?php foreach ($logs as $log): ?>
    <div id="view-log-<?= (int)$log['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-lg rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-brand-700">View Storage Log</h3>
            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <dt class="text-slate-500">Product</dt><dd class="font-medium"><?= e($log['sku']); ?> - <?= e($log['product_name']); ?></dd>
                <dt class="text-slate-500">Quantity</dt><dd class="font-medium"><?= (int)$log['quantity']; ?></dd>
                <dt class="text-slate-500">From</dt><dd class="font-medium"><?= e($log['from_department']); ?></dd>
                <dt class="text-slate-500">Action</dt><dd class="font-medium"><?= e($log['action']); ?></dd>
                <dt class="text-slate-500">Notes</dt><dd class="font-medium"><?= e($log['notes']); ?></dd>
                <dt class="text-slate-500">Created By</dt><dd class="font-medium"><?= e($log['created_by_name']); ?></dd>
            </dl>
            <div class="mt-5 flex justify-end">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Close</button>
            </div>
        </div>
    </div>

    <div id="edit-log-<?= (int)$log['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-lg rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-brand-700">Edit Storage Log</h3>
            <form method="post" class="mt-4 space-y-3">
                <input type="hidden" name="action" value="update_log">
                <input type="hidden" name="id" value="<?= (int)$log['id']; ?>">
                <div>
                    <label class="text-sm text-slate-600">Quantity</label>
                    <input type="number" min="1" name="quantity" value="<?= (int)$log['quantity']; ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Action</label>
                    <select name="storage_action" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                        <option value="STORE" <?= $log['action'] === 'STORE' ? 'selected' : ''; ?>>STORE</option>
                        <option value="RETURN_TO_PURCHASING" <?= $log['action'] === 'RETURN_TO_PURCHASING' ? 'selected' : ''; ?>>RETURN_TO_PURCHASING</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Notes</label>
                    <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"><?= e($log['notes']); ?></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div id="delete-log-<?= (int)$log['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-rose-700">Delete Storage Log</h3>
            <p class="mt-2 text-sm text-slate-600">Delete storage log #<?= (int)$log['id']; ?>?</p>
            <form method="post" class="mt-5 flex justify-end gap-2">
                <input type="hidden" name="action" value="delete_log">
                <input type="hidden" name="id" value="<?= (int)$log['id']; ?>">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white">Delete</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<?php include __DIR__ . '/../partials/footer.php'; ?>
