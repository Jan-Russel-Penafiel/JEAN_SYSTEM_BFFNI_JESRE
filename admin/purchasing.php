<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('ADMIN');

$pageTitle = 'Purchasing Department';
$activePage = 'purchasing';
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_po') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);
        $unitCost = (float)($_POST['unit_cost'] ?? 0);
        $supplierName = trim($_POST['supplier_name'] ?? '');

        if ($productId <= 0 || $quantity <= 0 || $supplierName === '') {
            flash_set('error', 'Please complete purchase order fields.');
        } else {
            $poNumber = 'PO-' . date('YmdHis') . '-' . random_int(100, 999);
            $stmt = $pdo->prepare('INSERT INTO purchase_orders (po_number, product_id, quantity, unit_cost, supplier_name, status, created_by) VALUES (:po_number, :product_id, :quantity, :unit_cost, :supplier_name, :status, :created_by)');
            $stmt->execute([
                'po_number' => $poNumber,
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'supplier_name' => $supplierName,
                'status' => 'PENDING',
                'created_by' => $user['id'],
            ]);
            flash_set('success', 'Purchase order created.');
        }
        header('Location: ' . app_url('admin/purchasing.php'));
        exit;
    }

    if ($action === 'update_po') {
        $id = (int)($_POST['id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);
        $unitCost = (float)($_POST['unit_cost'] ?? 0);
        $supplierName = trim($_POST['supplier_name'] ?? '');
        $status = $_POST['status'] ?? 'PENDING';

        $allowed = ['PENDING', 'SENT_TO_RECEIVING', 'INSPECTED_OK', 'INSPECTED_NOT_OK', 'RETURNED', 'STORED'];
        if (!in_array($status, $allowed, true)) {
            $status = 'PENDING';
        }

        if ($id > 0 && $quantity > 0 && $supplierName !== '') {
            $stmt = $pdo->prepare('UPDATE purchase_orders SET quantity = :quantity, unit_cost = :unit_cost, supplier_name = :supplier_name, status = :status WHERE id = :id');
            $stmt->execute([
                'id' => $id,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'supplier_name' => $supplierName,
                'status' => $status,
            ]);
            flash_set('success', 'Purchase order updated.');
        }

        header('Location: ' . app_url('admin/purchasing.php'));
        exit;
    }

    if ($action === 'delete_po') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM purchase_orders WHERE id = :id');
            $stmt->execute(['id' => $id]);
            flash_set('success', 'Purchase order deleted.');
        }
        header('Location: ' . app_url('admin/purchasing.php'));
        exit;
    }

    if ($action === 'send_to_receiving') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE purchase_orders SET status = 'SENT_TO_RECEIVING' WHERE id = :id");
            $stmt->execute(['id' => $id]);
            flash_set('success', 'Purchase order sent to receiving department.');
        }
        header('Location: ' . app_url('admin/purchasing.php'));
        exit;
    }

    if ($action === 'mark_returned') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("UPDATE purchase_orders SET status = 'RETURNED' WHERE id = :id");
                $stmt->execute(['id' => $id]);

                $notify = $pdo->prepare('INSERT INTO department_notifications (target_department, message, status) VALUES (:target_department, :message, :status)');
                $notify->execute([
                    'target_department' => 'PURCHASING',
                    'message' => 'Receiving returned items for PO ID #' . $id . '. Back to purchasing process.',
                    'status' => 'PENDING',
                ]);

                $pdo->commit();
                flash_set('success', 'Purchase order returned to purchasing process.');
            } catch (Exception $e) {
                $pdo->rollBack();
                flash_set('error', 'Failed to mark purchase order as returned.');
            }
        }

        header('Location: ' . app_url('admin/purchasing.php'));
        exit;
    }
}

$products = $pdo->query('SELECT id, sku, product_name FROM products ORDER BY product_name ASC')->fetchAll();
$purchaseOrders = $pdo->query('SELECT po.*, p.sku, p.product_name, u.name AS created_by_name FROM purchase_orders po JOIN products p ON p.id = po.product_id JOIN users u ON u.id = po.created_by ORDER BY po.id DESC')->fetchAll();

include __DIR__ . '/../partials/header.php';
?>
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-brand-700">Purchasing Department</h2>
            <p class="text-sm text-slate-500">Create purchase orders, send to receiving, and handle returns.</p>
        </div>
        <button data-modal-open="create-po-modal" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Create Purchase Order</button>
    </div>

    <section class="rounded-xl border border-brand-100 bg-white p-4 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
            <tr class="border-b border-slate-100 text-left text-slate-500">
                <th class="py-2 pr-3">PO Number</th>
                <th class="py-2 pr-3">Product</th>
                <th class="py-2 pr-3">Qty</th>
                <th class="py-2 pr-3">Unit Cost</th>
                <th class="py-2 pr-3">Supplier</th>
                <th class="py-2 pr-3">Status</th>
                <th class="py-2">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$purchaseOrders): ?>
                <tr><td class="py-3 text-slate-500" colspan="7">No purchase orders yet.</td></tr>
            <?php else: ?>
                <?php foreach ($purchaseOrders as $po): ?>
                    <tr class="border-b border-slate-50">
                        <td class="py-2 pr-3 font-medium text-slate-700"><?= e($po['po_number']); ?></td>
                        <td class="py-2 pr-3"><?= e($po['sku']); ?> - <?= e($po['product_name']); ?></td>
                        <td class="py-2 pr-3"><?= (int)$po['quantity']; ?></td>
                        <td class="py-2 pr-3"><?= e(format_currency($po['unit_cost'])); ?></td>
                        <td class="py-2 pr-3"><?= e($po['supplier_name']); ?></td>
                        <td class="py-2 pr-3">
                            <span class="rounded-full px-2 py-1 text-xs font-semibold <?= e(status_badge_class($po['status'])); ?>"><?= e($po['status']); ?></span>
                        </td>
                        <td class="py-2">
                            <div class="flex flex-wrap gap-2">
                                <button data-modal-open="view-po-<?= (int)$po['id']; ?>" class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">View</button>
                                <button data-modal-open="edit-po-<?= (int)$po['id']; ?>" class="rounded-md bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-700">Edit</button>
                                <button data-modal-open="send-po-<?= (int)$po['id']; ?>" class="rounded-md bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Send to Receiving</button>
                                <button data-modal-open="return-po-<?= (int)$po['id']; ?>" class="rounded-md bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Return</button>
                                <button data-modal-open="delete-po-<?= (int)$po['id']; ?>" class="rounded-md bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700">Delete</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
</div>

<div id="create-po-modal" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-xl rounded-xl bg-white p-6">
        <h3 class="text-lg font-semibold text-brand-700">Create Purchase Order</h3>
        <form method="post" class="mt-4 space-y-3">
            <input type="hidden" name="action" value="create_po">
            <div>
                <label class="text-sm text-slate-600">Product</label>
                <select name="product_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                    <option value="">Select Product</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= (int)$product['id']; ?>"><?= e($product['sku']); ?> - <?= e($product['product_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid md:grid-cols-2 gap-3">
                <div>
                    <label class="text-sm text-slate-600">Quantity</label>
                    <input type="number" min="1" name="quantity" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Unit Cost</label>
                    <input type="number" step="0.01" min="0" name="unit_cost" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                </div>
            </div>
            <div>
                <label class="text-sm text-slate-600">Supplier Name</label>
                <input name="supplier_name" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Create</button>
            </div>
        </form>
    </div>
</div>

<?php foreach ($purchaseOrders as $po): ?>
    <div id="view-po-<?= (int)$po['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-lg rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-brand-700">View Purchase Order</h3>
            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <dt class="text-slate-500">PO Number</dt><dd class="font-medium"><?= e($po['po_number']); ?></dd>
                <dt class="text-slate-500">Product</dt><dd class="font-medium"><?= e($po['sku']); ?> - <?= e($po['product_name']); ?></dd>
                <dt class="text-slate-500">Quantity</dt><dd class="font-medium"><?= (int)$po['quantity']; ?></dd>
                <dt class="text-slate-500">Unit Cost</dt><dd class="font-medium"><?= e(format_currency($po['unit_cost'])); ?></dd>
                <dt class="text-slate-500">Supplier</dt><dd class="font-medium"><?= e($po['supplier_name']); ?></dd>
                <dt class="text-slate-500">Status</dt><dd class="font-medium"><?= e($po['status']); ?></dd>
                <dt class="text-slate-500">Created By</dt><dd class="font-medium"><?= e($po['created_by_name']); ?></dd>
            </dl>
            <div class="mt-5 flex justify-end">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Close</button>
            </div>
        </div>
    </div>

    <div id="edit-po-<?= (int)$po['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-xl rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-brand-700">Edit Purchase Order</h3>
            <form method="post" class="mt-4 space-y-3">
                <input type="hidden" name="action" value="update_po">
                <input type="hidden" name="id" value="<?= (int)$po['id']; ?>">
                <div class="grid md:grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm text-slate-600">Quantity</label>
                        <input type="number" min="1" name="quantity" value="<?= (int)$po['quantity']; ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                    </div>
                    <div>
                        <label class="text-sm text-slate-600">Unit Cost</label>
                        <input type="number" step="0.01" min="0" name="unit_cost" value="<?= e($po['unit_cost']); ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                    </div>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Supplier Name</label>
                    <input name="supplier_name" value="<?= e($po['supplier_name']); ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Status</label>
                    <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                        <?php foreach (['PENDING','SENT_TO_RECEIVING','INSPECTED_OK','INSPECTED_NOT_OK','RETURNED','STORED'] as $status): ?>
                            <option value="<?= e($status); ?>" <?= $po['status'] === $status ? 'selected' : ''; ?>><?= e($status); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div id="send-po-<?= (int)$po['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-emerald-700">Send to Receiving</h3>
            <p class="mt-2 text-sm text-slate-600">Move PO <span class="font-semibold"><?= e($po['po_number']); ?></span> to receiving department?</p>
            <form method="post" class="mt-4 flex justify-end gap-2">
                <input type="hidden" name="action" value="send_to_receiving">
                <input type="hidden" name="id" value="<?= (int)$po['id']; ?>">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Send</button>
            </form>
        </div>
    </div>

    <div id="return-po-<?= (int)$po['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-amber-700">Return to Purchasing</h3>
            <p class="mt-2 text-sm text-slate-600">Mark this PO as returned and continue purchasing cycle?</p>
            <form method="post" class="mt-4 flex justify-end gap-2">
                <input type="hidden" name="action" value="mark_returned">
                <input type="hidden" name="id" value="<?= (int)$po['id']; ?>">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white">Return</button>
            </form>
        </div>
    </div>

    <div id="delete-po-<?= (int)$po['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-rose-700">Delete Purchase Order</h3>
            <p class="mt-2 text-sm text-slate-600">Delete PO <span class="font-semibold"><?= e($po['po_number']); ?></span>?</p>
            <form method="post" class="mt-4 flex justify-end gap-2">
                <input type="hidden" name="action" value="delete_po">
                <input type="hidden" name="id" value="<?= (int)$po['id']; ?>">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white">Delete</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<?php include __DIR__ . '/../partials/footer.php'; ?>
