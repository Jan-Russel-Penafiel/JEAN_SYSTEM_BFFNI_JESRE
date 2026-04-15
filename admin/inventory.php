<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('ADMIN');

$pageTitle = 'Inventory Department';
$activePage = 'inventory';
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_adjustment') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $qtyChange = (int)($_POST['qty_change'] ?? 0);
        $remarks = trim($_POST['remarks'] ?? '');

        $productStmt = $pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
        $productStmt->execute(['id' => $productId]);
        $product = $productStmt->fetch();

        if (!$product) {
            flash_set('error', 'Invalid product selected.');
        } else {
            $qtyBefore = (int)$product['stock_qty'];
            $qtyAfter = $qtyBefore + $qtyChange;

            if ($qtyAfter < 0) {
                flash_set('error', 'Adjustment makes stock negative.');
            } else {
                $availabilityStatus = $qtyAfter > 0 ? 'YES' : 'NO';

                $pdo->beginTransaction();
                try {
                    $updateProduct = $pdo->prepare('UPDATE products SET stock_qty = :stock_qty WHERE id = :id');
                    $updateProduct->execute([
                        'stock_qty' => $qtyAfter,
                        'id' => $productId,
                    ]);

                    $insertRecord = $pdo->prepare('INSERT INTO inventory_records (product_id, department, change_type, availability_status, item_check_status, qty_before, qty_change, qty_after, remarks, created_by) VALUES (:product_id, :department, :change_type, :availability_status, :item_check_status, :qty_before, :qty_change, :qty_after, :remarks, :created_by)');
                    $insertRecord->execute([
                        'product_id' => $productId,
                        'department' => 'INVENTORY',
                        'change_type' => 'ADJUSTMENT',
                        'availability_status' => $availabilityStatus,
                        'item_check_status' => null,
                        'qty_before' => $qtyBefore,
                        'qty_change' => $qtyChange,
                        'qty_after' => $qtyAfter,
                        'remarks' => $remarks,
                        'created_by' => $user['id'],
                    ]);

                    if ($qtyAfter <= (int)$product['reorder_level']) {
                        $notify = $pdo->prepare('INSERT INTO department_notifications (target_department, message, status) VALUES (:target_department, :message, :status)');
                        $notify->execute([
                            'target_department' => 'PURCHASING',
                            'message' => 'Low stock detected for ' . $product['product_name'] . ' (SKU ' . $product['sku'] . ').',
                            'status' => 'PENDING',
                        ]);
                    }

                    $pdo->commit();
                    flash_set('success', 'Inventory record created and stock updated.');
                } catch (Exception $e) {
                    $pdo->rollBack();
                    flash_set('error', 'Failed to create inventory record.');
                }
            }
        }

        header('Location: ' . app_url('admin/inventory.php'));
        exit;
    }

    if ($action === 'update_record') {
        $id = (int)($_POST['id'] ?? 0);
        $availabilityStatus = ($_POST['availability_status'] ?? '') === 'NO' ? 'NO' : 'YES';
        $itemCheckStatus = ($_POST['item_check_status'] ?? '') === 'NO' ? 'NO' : 'YES';
        $remarks = trim($_POST['remarks'] ?? '');

        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE inventory_records SET availability_status = :availability_status, item_check_status = :item_check_status, remarks = :remarks WHERE id = :id');
            $stmt->execute([
                'id' => $id,
                'availability_status' => $availabilityStatus,
                'item_check_status' => $itemCheckStatus,
                'remarks' => $remarks,
            ]);
            flash_set('success', 'Inventory record updated.');
        }

        header('Location: ' . app_url('admin/inventory.php'));
        exit;
    }

    if ($action === 'delete_record') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM inventory_records WHERE id = :id');
            $stmt->execute(['id' => $id]);
            flash_set('success', 'Inventory record deleted.');
        }

        header('Location: ' . app_url('admin/inventory.php'));
        exit;
    }

    if ($action === 'notify_purchasing') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $productStmt = $pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
        $productStmt->execute(['id' => $productId]);
        $product = $productStmt->fetch();

        if ($product) {
            $message = 'Availability NO for ' . $product['product_name'] . '. Proceed to purchasing department.';
            $notify = $pdo->prepare('INSERT INTO department_notifications (target_department, message, status) VALUES (:target_department, :message, :status)');
            $notify->execute([
                'target_department' => 'PURCHASING',
                'message' => $message,
                'status' => 'PENDING',
            ]);
            flash_set('success', 'Purchasing department notified.');
        }

        header('Location: ' . app_url('admin/inventory.php'));
        exit;
    }

    if ($action === 'send_to_storage') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        $productStmt = $pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
        $productStmt->execute(['id' => $productId]);
        $product = $productStmt->fetch();

        if (!$product || $quantity <= 0 || $quantity > (int)$product['stock_qty']) {
            flash_set('error', 'Invalid storage quantity.');
        } else {
            $store = $pdo->prepare('INSERT INTO storage_logs (product_id, quantity, from_department, action, notes, created_by) VALUES (:product_id, :quantity, :from_department, :action, :notes, :created_by)');
            $store->execute([
                'product_id' => $productId,
                'quantity' => $quantity,
                'from_department' => 'INVENTORY',
                'action' => 'STORE',
                'notes' => $notes,
                'created_by' => $user['id'],
            ]);
            flash_set('success', 'Product sent to storage queue.');
        }

        header('Location: ' . app_url('admin/inventory.php'));
        exit;
    }

    if ($action === 'create_po_quick') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);
        $unitCost = (float)($_POST['unit_cost'] ?? 0);
        $supplierName = trim($_POST['supplier_name'] ?? '');

        if ($productId <= 0 || $quantity <= 0 || $supplierName === '') {
            flash_set('error', 'Complete purchase order fields are required.');
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
            flash_set('success', 'Purchase order created from inventory flow.');
        }

        header('Location: ' . app_url('admin/inventory.php'));
        exit;
    }
}

$products = $pdo->query('SELECT * FROM products ORDER BY product_name ASC')->fetchAll();
$records = $pdo->query('SELECT ir.*, p.product_name, p.sku, u.name AS created_by_name FROM inventory_records ir JOIN products p ON p.id = ir.product_id JOIN users u ON u.id = ir.created_by ORDER BY ir.id DESC LIMIT 100')->fetchAll();

include __DIR__ . '/../partials/header.php';
?>
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-brand-700">Inventory Department</h2>
            <p class="text-sm text-slate-500">Record data, check availability, and route items to storage or purchasing.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button data-modal-open="record-inventory-modal" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Record Data</button>
            <button data-modal-open="quick-po-modal" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">Create Purchase Order</button>
        </div>
    </div>

    <section class="rounded-xl border border-brand-100 bg-white p-4 overflow-x-auto">
        <h3 class="text-base font-semibold text-brand-700 mb-3">Stock Availability Check</h3>
        <table class="min-w-full text-sm">
            <thead>
            <tr class="border-b border-slate-100 text-left text-slate-500">
                <th class="py-2 pr-3">SKU</th>
                <th class="py-2 pr-3">Product</th>
                <th class="py-2 pr-3">Stock</th>
                <th class="py-2 pr-3">Reorder</th>
                <th class="py-2 pr-3">Availability</th>
                <th class="py-2">Flow Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($products as $product): ?>
                <?php
                $isAvailable = (int)$product['stock_qty'] > 0;
                $availability = $isAvailable ? 'YES' : 'NO';
                $isLow = (int)$product['stock_qty'] <= (int)$product['reorder_level'];
                ?>
                <tr class="border-b border-slate-50">
                    <td class="py-2 pr-3 font-medium text-slate-700"><?= e($product['sku']); ?></td>
                    <td class="py-2 pr-3"><?= e($product['product_name']); ?></td>
                    <td class="py-2 pr-3"><?= (int)$product['stock_qty']; ?></td>
                    <td class="py-2 pr-3"><?= (int)$product['reorder_level']; ?></td>
                    <td class="py-2 pr-3">
                        <span class="rounded-full px-2 py-1 text-xs font-semibold <?= $availability === 'YES' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'; ?>"><?= $availability; ?></span>
                    </td>
                    <td class="py-2">
                        <div class="flex flex-wrap gap-2">
                            <?php if ($isAvailable): ?>
                                <button data-modal-open="send-storage-<?= (int)$product['id']; ?>" class="rounded-md bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Send to Storage</button>
                            <?php endif; ?>
                            <?php if (!$isAvailable || $isLow): ?>
                                <button data-modal-open="notify-<?= (int)$product['id']; ?>" class="rounded-md bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Notify Purchasing</button>
                            <?php endif; ?>
                            <button data-modal-open="quick-po-<?= (int)$product['id']; ?>" class="rounded-md bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-700">Create PO</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="rounded-xl border border-brand-100 bg-white p-4 overflow-x-auto">
        <h3 class="text-base font-semibold text-brand-700 mb-3">Inventory Records</h3>
        <table class="min-w-full text-sm">
            <thead>
            <tr class="border-b border-slate-100 text-left text-slate-500">
                <th class="py-2 pr-3">Date</th>
                <th class="py-2 pr-3">Product</th>
                <th class="py-2 pr-3">Type</th>
                <th class="py-2 pr-3">Before</th>
                <th class="py-2 pr-3">Change</th>
                <th class="py-2 pr-3">After</th>
                <th class="py-2 pr-3">Availability</th>
                <th class="py-2">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$records): ?>
                <tr><td class="py-3 text-slate-500" colspan="8">No inventory records yet.</td></tr>
            <?php else: ?>
                <?php foreach ($records as $record): ?>
                    <tr class="border-b border-slate-50">
                        <td class="py-2 pr-3"><?= e($record['created_at']); ?></td>
                        <td class="py-2 pr-3"><?= e($record['sku']); ?> - <?= e($record['product_name']); ?></td>
                        <td class="py-2 pr-3"><?= e($record['change_type']); ?></td>
                        <td class="py-2 pr-3"><?= (int)$record['qty_before']; ?></td>
                        <td class="py-2 pr-3"><?= (int)$record['qty_change']; ?></td>
                        <td class="py-2 pr-3"><?= (int)$record['qty_after']; ?></td>
                        <td class="py-2 pr-3"><?= e($record['availability_status'] ?? '-'); ?></td>
                        <td class="py-2">
                            <div class="flex flex-wrap gap-2">
                                <button data-modal-open="view-record-<?= (int)$record['id']; ?>" class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">View</button>
                                <button data-modal-open="edit-record-<?= (int)$record['id']; ?>" class="rounded-md bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-700">Edit</button>
                                <button data-modal-open="delete-record-<?= (int)$record['id']; ?>" class="rounded-md bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700">Delete</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
</div>

<div id="record-inventory-modal" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-xl rounded-xl bg-white p-6">
        <h3 class="text-lg font-semibold text-brand-700">Record Inventory Data</h3>
        <form method="post" class="mt-4 space-y-3">
            <input type="hidden" name="action" value="create_adjustment">
            <div>
                <label class="text-sm text-slate-600">Product</label>
                <select name="product_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                    <option value="">Select Product</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= (int)$product['id']; ?>"><?= e($product['sku']); ?> - <?= e($product['product_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-sm text-slate-600">Quantity Change (use negative for deductions)</label>
                <input type="number" name="qty_change" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
            </div>
            <div>
                <label class="text-sm text-slate-600">Remarks</label>
                <textarea name="remarks" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"></textarea>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Save Record</button>
            </div>
        </form>
    </div>
</div>

<div id="quick-po-modal" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-xl rounded-xl bg-white p-6">
        <h3 class="text-lg font-semibold text-brand-700">Create Purchase Order</h3>
        <form method="post" class="mt-4 space-y-3">
            <input type="hidden" name="action" value="create_po_quick">
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
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Create PO</button>
            </div>
        </form>
    </div>
</div>

<?php foreach ($products as $product): ?>
    <div id="notify-<?= (int)$product['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-amber-700">Notify Purchasing</h3>
            <p class="mt-2 text-sm text-slate-600">Send low/empty stock alert for <span class="font-semibold"><?= e($product['product_name']); ?></span>?</p>
            <form method="post" class="mt-4 flex justify-end gap-2">
                <input type="hidden" name="action" value="notify_purchasing">
                <input type="hidden" name="product_id" value="<?= (int)$product['id']; ?>">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white">Notify</button>
            </form>
        </div>
    </div>

    <div id="send-storage-<?= (int)$product['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-emerald-700">Send to Storage</h3>
            <form method="post" class="mt-4 space-y-3">
                <input type="hidden" name="action" value="send_to_storage">
                <input type="hidden" name="product_id" value="<?= (int)$product['id']; ?>">
                <div>
                    <label class="text-sm text-slate-600">Quantity</label>
                    <input type="number" min="1" max="<?= (int)$product['stock_qty']; ?>" name="quantity" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Notes</label>
                    <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                    <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Send</button>
                </div>
            </form>
        </div>
    </div>

    <div id="quick-po-<?= (int)$product['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-xl rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-brand-700">Create PO for <?= e($product['product_name']); ?></h3>
            <form method="post" class="mt-4 space-y-3">
                <input type="hidden" name="action" value="create_po_quick">
                <input type="hidden" name="product_id" value="<?= (int)$product['id']; ?>">
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
<?php endforeach; ?>

<?php foreach ($records as $record): ?>
    <div id="view-record-<?= (int)$record['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-lg rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-brand-700">View Inventory Record</h3>
            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <dt class="text-slate-500">Product</dt><dd class="font-medium"><?= e($record['sku']); ?> - <?= e($record['product_name']); ?></dd>
                <dt class="text-slate-500">Department</dt><dd class="font-medium"><?= e($record['department']); ?></dd>
                <dt class="text-slate-500">Change Type</dt><dd class="font-medium"><?= e($record['change_type']); ?></dd>
                <dt class="text-slate-500">Qty Before</dt><dd class="font-medium"><?= (int)$record['qty_before']; ?></dd>
                <dt class="text-slate-500">Qty Change</dt><dd class="font-medium"><?= (int)$record['qty_change']; ?></dd>
                <dt class="text-slate-500">Qty After</dt><dd class="font-medium"><?= (int)$record['qty_after']; ?></dd>
                <dt class="text-slate-500">Availability</dt><dd class="font-medium"><?= e($record['availability_status'] ?? '-'); ?></dd>
                <dt class="text-slate-500">Items OK</dt><dd class="font-medium"><?= e($record['item_check_status'] ?? '-'); ?></dd>
                <dt class="text-slate-500">Remarks</dt><dd class="font-medium"><?= e($record['remarks']); ?></dd>
            </dl>
            <div class="mt-5 flex justify-end">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Close</button>
            </div>
        </div>
    </div>

    <div id="edit-record-<?= (int)$record['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-lg rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-brand-700">Edit Inventory Record</h3>
            <form method="post" class="mt-4 space-y-3">
                <input type="hidden" name="action" value="update_record">
                <input type="hidden" name="id" value="<?= (int)$record['id']; ?>">
                <div class="grid md:grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm text-slate-600">Availability</label>
                        <select name="availability_status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                            <option value="YES" <?= ($record['availability_status'] ?? '') === 'YES' ? 'selected' : ''; ?>>YES</option>
                            <option value="NO" <?= ($record['availability_status'] ?? '') === 'NO' ? 'selected' : ''; ?>>NO</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm text-slate-600">Items OK</label>
                        <select name="item_check_status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                            <option value="YES" <?= ($record['item_check_status'] ?? '') === 'YES' ? 'selected' : ''; ?>>YES</option>
                            <option value="NO" <?= ($record['item_check_status'] ?? '') === 'NO' ? 'selected' : ''; ?>>NO</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Remarks</label>
                    <textarea name="remarks" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"><?= e($record['remarks']); ?></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div id="delete-record-<?= (int)$record['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-rose-700">Delete Record</h3>
            <p class="mt-2 text-sm text-slate-600">Delete inventory record #<?= (int)$record['id']; ?>?</p>
            <form method="post" class="mt-5 flex justify-end gap-2">
                <input type="hidden" name="action" value="delete_record">
                <input type="hidden" name="id" value="<?= (int)$record['id']; ?>">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white">Delete</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<?php include __DIR__ . '/../partials/footer.php'; ?>
