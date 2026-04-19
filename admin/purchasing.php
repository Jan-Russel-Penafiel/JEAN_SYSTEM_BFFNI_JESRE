<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['PURCHASING', 'ADMIN']);

$pageTitle = 'Purchasing Department';
$activePage = 'purchasing';
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_notification_read') {
        $notificationId = (int)($_POST['notification_id'] ?? 0);

        if ($notificationId > 0) {
            $stmt = $pdo->prepare("UPDATE department_notifications SET status = 'READ' WHERE id = :id AND target_department = :target_department");
            $stmt->execute([
                'id' => $notificationId,
                'target_department' => 'PURCHASING',
            ]);
            flash_set('success', 'Notification marked as read.');
        }

        header('Location: ' . app_url('admin/purchasing.php'));
        exit;
    }

    if ($action === 'mark_all_notifications_read') {
        $stmt = $pdo->prepare("UPDATE department_notifications SET status = 'READ' WHERE target_department = :target_department AND status = :status");
        $stmt->execute([
            'target_department' => 'PURCHASING',
            'status' => 'PENDING',
        ]);

        flash_set('success', 'All purchasing notifications marked as read.');
        header('Location: ' . app_url('admin/purchasing.php'));
        exit;
    }

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

    if ($action === 'contact_supplier') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE purchase_orders SET status = 'PENDING' WHERE id = :id");
            $stmt->execute(['id' => $id]);
            flash_set('success', 'Supplier contacted. Purchase order is waiting for delivery.');
        }

        header('Location: ' . app_url('admin/purchasing.php'));
        exit;
    }

    if ($action === 'wait_for_delivery') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE purchase_orders SET status = 'PENDING' WHERE id = :id");
            $stmt->execute(['id' => $id]);
            flash_set('success', 'Purchase order set to waiting for delivery.');
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
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("UPDATE purchase_orders SET status = 'SENT_TO_RECEIVING' WHERE id = :id");
                $stmt->execute(['id' => $id]);

                $notifyInventory = $pdo->prepare('INSERT INTO department_notifications (target_department, message, status) VALUES (:target_department, :message, :status)');
                $notifyInventory->execute([
                    'target_department' => 'INVENTORY',
                    'message' => 'Delivered items are forwarded to inventory for stock update. PO ID #' . $id . '.',
                    'status' => 'PENDING',
                ]);

                $pdo->commit();
                flash_set('success', 'Items delivered: forwarded to inventory and purchase status updated.');
            } catch (Exception $e) {
                $pdo->rollBack();
                flash_set('error', 'Failed to forward delivered items to inventory.');
            }
        }
        header('Location: ' . app_url('admin/purchasing.php'));
        exit;
    }

    if ($action === 'follow_up_supplier') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("UPDATE purchase_orders SET status = 'PENDING' WHERE id = :id");
                $stmt->execute(['id' => $id]);

                $notify = $pdo->prepare('INSERT INTO department_notifications (target_department, message, status) VALUES (:target_department, :message, :status)');
                $notify->execute([
                    'target_department' => 'PURCHASING',
                    'message' => 'Follow up supplier for PO ID #' . $id . '. Back to waiting for delivery.',
                    'status' => 'PENDING',
                ]);

                $pdo->commit();
                flash_set('success', 'Supplier follow-up recorded. Purchase order returned to waiting for delivery.');
            } catch (Exception $e) {
                $pdo->rollBack();
                flash_set('error', 'Failed to record supplier follow-up.');
            }
        }

        header('Location: ' . app_url('admin/purchasing.php'));
        exit;
    }
}

$products = $pdo->query('SELECT id, sku, product_name FROM products ORDER BY product_name ASC')->fetchAll();
$lowStockItems = $pdo->query("SELECT id, sku, product_name, stock_qty, reorder_level FROM products WHERE stock_qty <= reorder_level ORDER BY stock_qty ASC, product_name ASC")->fetchAll();
$purchaseOrders = $pdo->query('SELECT po.*, p.sku, p.product_name, u.name AS created_by_name FROM purchase_orders po JOIN products p ON p.id = po.product_id JOIN users u ON u.id = po.created_by ORDER BY po.id DESC')->fetchAll();
$notificationsStmt = $pdo->prepare('SELECT * FROM department_notifications WHERE target_department = :target_department ORDER BY status ASC, created_at DESC LIMIT 100');
$notificationsStmt->execute(['target_department' => 'PURCHASING']);
$notifications = $notificationsStmt->fetchAll();
$pendingNotifications = array_values(array_filter($notifications, static function ($notification) {
    return ($notification['status'] ?? '') === 'PENDING';
}));

include __DIR__ . '/../partials/header.php';
?>
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-brand-700">Purchasing Department</h2>
            <p class="text-sm text-slate-500">Receive low stock alerts, review low stock items, create purchase orders, contact suppliers, and wait for delivery.</p>
        </div>
        <button data-modal-open="create-po-modal" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Create Purchase Order</button>
    </div>

    <section class="rounded-xl border border-brand-100 bg-white p-4 overflow-x-auto">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="text-base font-semibold text-brand-700">Purchasing Notification Inbox</h3>
                <p class="text-xs text-slate-500">Low-stock and return alerts sent to purchasing.</p>
            </div>
            <?php if ($pendingNotifications): ?>
                <form method="post">
                    <input type="hidden" name="action" value="mark_all_notifications_read">
                    <button type="submit" class="rounded-md bg-emerald-100 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-200">Mark All as Read (<?= count($pendingNotifications); ?>)</button>
                </form>
            <?php endif; ?>
        </div>

        <table class="min-w-full text-sm">
            <thead>
            <tr class="border-b border-slate-100 text-left text-slate-500">
                <th class="py-2 pr-3">Date</th>
                <th class="py-2 pr-3">Message</th>
                <th class="py-2 pr-3">Status</th>
                <th class="py-2">Action</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$notifications): ?>
                <tr><td class="py-3 text-slate-500" colspan="4">No purchasing notifications yet.</td></tr>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <tr class="border-b border-slate-50">
                        <td class="py-2 pr-3"><?= e($notification['created_at']); ?></td>
                        <td class="py-2 pr-3"><?= e($notification['message']); ?></td>
                        <td class="py-2 pr-3">
                            <span class="rounded-full px-2 py-1 text-xs font-semibold <?= $notification['status'] === 'PENDING' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'; ?>"><?= e($notification['status']); ?></span>
                        </td>
                        <td class="py-2">
                            <?php if ($notification['status'] === 'PENDING'): ?>
                                <form method="post" class="inline">
                                    <input type="hidden" name="action" value="mark_notification_read">
                                    <input type="hidden" name="notification_id" value="<?= (int)$notification['id']; ?>">
                                    <button type="submit" class="rounded-md bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-700 hover:bg-brand-200">Mark as Read</button>
                                </form>
                            <?php else: ?>
                                <span class="text-xs text-slate-400">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="rounded-xl border border-brand-100 bg-white p-4 overflow-x-auto">
        <h3 class="mb-3 text-base font-semibold text-brand-700">View Low Stock Items</h3>
        <table class="min-w-full text-sm">
            <thead>
            <tr class="border-b border-slate-100 text-left text-slate-500">
                <th class="py-2 pr-3">SKU</th>
                <th class="py-2 pr-3">Product</th>
                <th class="py-2 pr-3">Stock</th>
                <th class="py-2 pr-3">Reorder</th>
                <th class="py-2">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$lowStockItems): ?>
                <tr><td class="py-3 text-slate-500" colspan="5">No low stock items found.</td></tr>
            <?php else: ?>
                <?php foreach ($lowStockItems as $item): ?>
                    <tr class="border-b border-slate-50">
                        <td class="py-2 pr-3 font-medium text-slate-700"><?= e($item['sku']); ?></td>
                        <td class="py-2 pr-3"><?= e($item['product_name']); ?></td>
                        <td class="py-2 pr-3"><?= (int)$item['stock_qty']; ?></td>
                        <td class="py-2 pr-3"><?= (int)$item['reorder_level']; ?></td>
                        <td class="py-2">
                            <div class="flex flex-wrap gap-2">
                                <button data-modal-open="view-low-item-<?= (int)$item['id']; ?>" class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Review Details</button>
                                <button data-modal-open="create-po-item-<?= (int)$item['id']; ?>" class="rounded-md bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-700">Create Purchase Order</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </section>

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
                                <button data-modal-open="contact-po-<?= (int)$po['id']; ?>" class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Contact Supplier</button>
                                <button data-modal-open="wait-po-<?= (int)$po['id']; ?>" class="rounded-md bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Wait for Delivery</button>
                                <button data-modal-open="send-po-<?= (int)$po['id']; ?>" class="rounded-md bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Items Delivered (YES)</button>
                                <button data-modal-open="followup-po-<?= (int)$po['id']; ?>" class="rounded-md bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700">Follow Up Supplier (NO)</button>
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

<?php foreach ($lowStockItems as $item): ?>
    <?php $suggestedQty = max(1, (int)$item['reorder_level'] - (int)$item['stock_qty']); ?>
    <div id="view-low-item-<?= (int)$item['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-lg rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-brand-700">Low Stock Item Details</h3>
            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <dt class="text-slate-500">SKU</dt><dd class="font-medium"><?= e($item['sku']); ?></dd>
                <dt class="text-slate-500">Product</dt><dd class="font-medium"><?= e($item['product_name']); ?></dd>
                <dt class="text-slate-500">Current Stock</dt><dd class="font-medium"><?= (int)$item['stock_qty']; ?></dd>
                <dt class="text-slate-500">Reorder Level</dt><dd class="font-medium"><?= (int)$item['reorder_level']; ?></dd>
                <dt class="text-slate-500">Suggested Order Qty</dt><dd class="font-medium"><?= (int)$suggestedQty; ?></dd>
            </dl>
            <div class="mt-5 flex justify-end">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Close</button>
            </div>
        </div>
    </div>

    <div id="create-po-item-<?= (int)$item['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-xl rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-brand-700">Create Purchase Order</h3>
            <p class="mt-1 text-sm text-slate-500">For low stock item: <?= e($item['sku']); ?> - <?= e($item['product_name']); ?></p>
            <form method="post" class="mt-4 space-y-3">
                <input type="hidden" name="action" value="create_po">
                <input type="hidden" name="product_id" value="<?= (int)$item['id']; ?>">
                <div class="grid md:grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm text-slate-600">Quantity</label>
                        <input type="number" min="1" name="quantity" value="<?= (int)$suggestedQty; ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
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
<?php endforeach; ?>

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
            <h3 class="text-lg font-semibold text-emerald-700">Items Delivered (YES)</h3>
            <p class="mt-2 text-sm text-slate-600">Forward PO <span class="font-semibold"><?= e($po['po_number']); ?></span> to inventory/receiving and update purchase status?</p>
            <form method="post" class="mt-4 flex justify-end gap-2">
                <input type="hidden" name="action" value="send_to_receiving">
                <input type="hidden" name="id" value="<?= (int)$po['id']; ?>">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Forward to Inventory</button>
            </form>
        </div>
    </div>

    <div id="contact-po-<?= (int)$po['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-slate-700">Contact Supplier</h3>
            <p class="mt-2 text-sm text-slate-600">Record supplier contact for PO <span class="font-semibold"><?= e($po['po_number']); ?></span>?</p>
            <form method="post" class="mt-4 flex justify-end gap-2">
                <input type="hidden" name="action" value="contact_supplier">
                <input type="hidden" name="id" value="<?= (int)$po['id']; ?>">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-lg bg-slate-700 px-4 py-2 text-sm font-semibold text-white">Contacted</button>
            </form>
        </div>
    </div>

    <div id="wait-po-<?= (int)$po['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-amber-700">Wait for Delivery</h3>
            <p class="mt-2 text-sm text-slate-600">Set PO <span class="font-semibold"><?= e($po['po_number']); ?></span> to waiting for delivery?</p>
            <form method="post" class="mt-4 flex justify-end gap-2">
                <input type="hidden" name="action" value="wait_for_delivery">
                <input type="hidden" name="id" value="<?= (int)$po['id']; ?>">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white">Wait</button>
            </form>
        </div>
    </div>

    <div id="followup-po-<?= (int)$po['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-rose-700">Follow Up Supplier (NO)</h3>
            <p class="mt-2 text-sm text-slate-600">Items not delivered yet. Follow up supplier and return to waiting for delivery?</p>
            <form method="post" class="mt-4 flex justify-end gap-2">
                <input type="hidden" name="action" value="follow_up_supplier">
                <input type="hidden" name="id" value="<?= (int)$po['id']; ?>">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white">Follow Up</button>
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
