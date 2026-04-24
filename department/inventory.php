<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['INVENTORY']);

$pageTitle = 'Inventory Department';
$activePage = 'inventory';
$user = current_user();

if (($_GET['export'] ?? '') === 'stock_report_csv') {
    $reportRows = $pdo->query("SELECT sku, product_name, stock_qty, reorder_level, CASE WHEN stock_qty <= 0 THEN 'OUT_OF_STOCK' WHEN stock_qty <= reorder_level THEN 'LOW_STOCK' ELSE 'AVAILABLE' END AS stock_status FROM products ORDER BY product_name ASC")->fetchAll();

    $filename = 'inventory-stock-status-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Generated At', date('Y-m-d H:i:s')]);
    fputcsv($output, []);
    fputcsv($output, ['SKU', 'Product Name', 'Stock Qty', 'Reorder Level', 'Stock Status']);

    foreach ($reportRows as $row) {
        fputcsv($output, [
            $row['sku'],
            $row['product_name'],
            (int)$row['stock_qty'],
            (int)$row['reorder_level'],
            $row['stock_status'],
        ]);
    }

    fclose($output);
    exit;
}

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

        header('Location: ' . app_url('department/inventory.php'));
        exit;
    }

    if ($action === 'release_item') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);
        $remarks = trim($_POST['remarks'] ?? '');

        $productStmt = $pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
        $productStmt->execute(['id' => $productId]);
        $product = $productStmt->fetch();

        if (!$product || $quantity <= 0) {
            flash_set('error', 'Invalid release item request.');
        } else {
            $qtyBefore = (int)$product['stock_qty'];
            $qtyAfter = $qtyBefore - $quantity;

            if ($qtyAfter < 0) {
                flash_set('error', 'Release quantity exceeds available stock.');
            } else {
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
                        'availability_status' => $qtyAfter > 0 ? 'YES' : 'NO',
                        'item_check_status' => null,
                        'qty_before' => $qtyBefore,
                        'qty_change' => -$quantity,
                        'qty_after' => $qtyAfter,
                        'remarks' => $remarks !== '' ? $remarks : 'Released item and updated stock.',
                        'created_by' => $user['id'],
                    ]);

                    if ($qtyAfter <= (int)$product['reorder_level']) {
                        $notify = $pdo->prepare('INSERT INTO department_notifications (target_department, message, status) VALUES (:target_department, :message, :status)');
                        $notify->execute([
                            'target_department' => 'PURCHASING',
                            'message' => 'Low stock after release item for ' . $product['product_name'] . ' (SKU ' . $product['sku'] . ').',
                            'status' => 'PENDING',
                        ]);
                    }

                    $pdo->commit();
                    flash_set('success', 'Item released and stock updated.');
                } catch (Exception $e) {
                    $pdo->rollBack();
                    flash_set('error', 'Failed to release item.');
                }
            }
        }

        header('Location: ' . app_url('department/inventory.php'));
        exit;
    }

    if ($action === 'tag_out_of_stock') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $remarks = trim($_POST['remarks'] ?? '');

        $productStmt = $pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
        $productStmt->execute(['id' => $productId]);
        $product = $productStmt->fetch();

        if (!$product) {
            flash_set('error', 'Invalid out-of-stock request.');
        } else {
            $qtyBefore = (int)$product['stock_qty'];
            $qtyAfter = 0;
            $qtyChange = -$qtyBefore;

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
                    'availability_status' => 'NO',
                    'item_check_status' => null,
                    'qty_before' => $qtyBefore,
                    'qty_change' => $qtyChange,
                    'qty_after' => $qtyAfter,
                    'remarks' => $remarks !== '' ? $remarks : 'Tagged as out of stock.',
                    'created_by' => $user['id'],
                ]);

                $notify = $pdo->prepare('INSERT INTO department_notifications (target_department, message, status) VALUES (:target_department, :message, :status)');
                $notify->execute([
                    'target_department' => 'PURCHASING',
                    'message' => 'Product tagged out of stock: ' . $product['product_name'] . ' (SKU ' . $product['sku'] . ').',
                    'status' => 'PENDING',
                ]);

                $pdo->commit();
                flash_set('success', 'Product tagged as out of stock and purchasing notified.');
            } catch (Exception $e) {
                $pdo->rollBack();
                flash_set('error', 'Failed to tag product as out of stock.');
            }
        }

        header('Location: ' . app_url('department/inventory.php'));
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

        header('Location: ' . app_url('department/inventory.php'));
        exit;
    }

    if ($action === 'delete_record') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM inventory_records WHERE id = :id');
            $stmt->execute(['id' => $id]);
            flash_set('success', 'Inventory record deleted.');
        }

        header('Location: ' . app_url('department/inventory.php'));
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

        header('Location: ' . app_url('department/inventory.php'));
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

        header('Location: ' . app_url('department/inventory.php'));
        exit;
    }

    if ($action === 'receive_delivered_items') {
        $purchaseOrderId = (int)($_POST['purchase_order_id'] ?? 0);
        $receivedQty = (int)($_POST['received_qty'] ?? 0);

        $poStmt = $pdo->prepare('SELECT po.*, p.sku, p.product_name, p.stock_qty FROM purchase_orders po JOIN products p ON p.id = po.product_id WHERE po.id = :id LIMIT 1');
        $poStmt->execute(['id' => $purchaseOrderId]);
        $purchaseOrder = $poStmt->fetch();

        if (!$purchaseOrder || $receivedQty <= 0) {
            flash_set('error', 'Invalid delivered-items request.');
        } elseif (($purchaseOrder['status'] ?? '') !== 'SENT_TO_RECEIVING') {
            flash_set('error', 'Purchase order is no longer waiting for inventory update.');
        } else {
            $qtyBefore = (int)$purchaseOrder['stock_qty'];
            $qtyAfter = $qtyBefore + $receivedQty;

            $pdo->beginTransaction();
            try {
                $updateProduct = $pdo->prepare('UPDATE products SET stock_qty = :stock_qty WHERE id = :id');
                $updateProduct->execute([
                    'stock_qty' => $qtyAfter,
                    'id' => $purchaseOrder['product_id'],
                ]);

                $insertRecord = $pdo->prepare('INSERT INTO inventory_records (product_id, department, change_type, availability_status, item_check_status, qty_before, qty_change, qty_after, remarks, created_by) VALUES (:product_id, :department, :change_type, :availability_status, :item_check_status, :qty_before, :qty_change, :qty_after, :remarks, :created_by)');
                $insertRecord->execute([
                    'product_id' => $purchaseOrder['product_id'],
                    'department' => 'INVENTORY',
                    'change_type' => 'PURCHASE',
                    'availability_status' => 'YES',
                    'item_check_status' => 'YES',
                    'qty_before' => $qtyBefore,
                    'qty_change' => $receivedQty,
                    'qty_after' => $qtyAfter,
                    'remarks' => 'Received delivered items for PO ' . $purchaseOrder['po_number'],
                    'created_by' => $user['id'],
                ]);

                $updatePO = $pdo->prepare("UPDATE purchase_orders SET status = 'STORED' WHERE id = :id AND status = 'SENT_TO_RECEIVING'");
                $updatePO->execute(['id' => $purchaseOrderId]);

                $pdo->commit();
                flash_set('success', 'Delivered items received, stock updated, and purchase order marked completed.');
            } catch (Exception $e) {
                $pdo->rollBack();
                flash_set('error', 'Failed to process delivered items in inventory.');
            }
        }

        header('Location: ' . app_url('department/inventory.php'));
        exit;
    }
}

$products = $pdo->query('SELECT * FROM products ORDER BY product_name ASC')->fetchAll();
$incomingPurchaseOrders = $pdo->query("SELECT po.id, po.po_number, po.quantity, po.status, p.sku, p.product_name FROM purchase_orders po JOIN products p ON p.id = po.product_id WHERE po.status = 'SENT_TO_RECEIVING' ORDER BY po.id DESC")->fetchAll();
$records = $pdo->query('SELECT ir.*, p.product_name, p.sku, u.name AS created_by_name FROM inventory_records ir JOIN products p ON p.id = ir.product_id JOIN users u ON u.id = ir.created_by ORDER BY ir.id DESC LIMIT 100')->fetchAll();

include __DIR__ . '/../partials/header.php';
?>
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-brand-700">Inventory Department</h2>
            <p class="text-sm text-slate-500">Check stock availability, release item, tag out of stock, record shortages, and update stock after purchasing deliveries.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= e(app_url('department/inventory.php')); ?>?export=stock_report_csv" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Export Stock Report</a>
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
                                <button data-modal-open="release-item-<?= (int)$product['id']; ?>" class="rounded-md bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Release Item</button>
                            <?php else: ?>
                                <button data-modal-open="tag-out-<?= (int)$product['id']; ?>" class="rounded-md bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700">Tag as Out of Stock</button>
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
        <h3 class="text-base font-semibold text-brand-700 mb-3">Purchasing Deliveries to Update</h3>
        <table class="min-w-full text-sm">
            <thead>
            <tr class="border-b border-slate-100 text-left text-slate-500">
                <th class="py-2 pr-3">PO #</th>
                <th class="py-2 pr-3">Product</th>
                <th class="py-2 pr-3">Ordered Qty</th>
                <th class="py-2 pr-3">Status</th>
                <th class="py-2">Action</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$incomingPurchaseOrders): ?>
                <tr><td class="py-3 text-slate-500" colspan="5">No purchase orders are waiting for inventory stock update.</td></tr>
            <?php else: ?>
                <?php foreach ($incomingPurchaseOrders as $purchaseOrder): ?>
                    <tr class="border-b border-slate-50">
                        <td class="py-2 pr-3 font-medium text-slate-700"><?= e($purchaseOrder['po_number']); ?></td>
                        <td class="py-2 pr-3"><?= e($purchaseOrder['sku']); ?> - <?= e($purchaseOrder['product_name']); ?></td>
                        <td class="py-2 pr-3"><?= (int)$purchaseOrder['quantity']; ?></td>
                        <td class="py-2 pr-3"><span class="rounded-full px-2 py-1 text-xs font-semibold <?= e(status_badge_class($purchaseOrder['status'])); ?>"><?= e(display_status_label($purchaseOrder['status'])); ?></span></td>
                        <td class="py-2">
                            <form method="post" class="flex flex-wrap items-center gap-2">
                                <input type="hidden" name="action" value="receive_delivered_items">
                                <input type="hidden" name="purchase_order_id" value="<?= (int)$purchaseOrder['id']; ?>">
                                <input type="number" min="1" max="<?= (int)$purchaseOrder['quantity']; ?>" name="received_qty" value="<?= (int)$purchaseOrder['quantity']; ?>" class="w-24 rounded-md border border-slate-200 px-2 py-1 text-xs" required>
                                <button type="submit" class="rounded-md bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Add Stock & Complete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
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
    <div id="release-item-<?= (int)$product['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-emerald-700">Release Item</h3>
            <p class="mt-1 text-sm text-slate-500">Release available stock and update inventory records.</p>
            <form method="post" class="mt-4 space-y-3">
                <input type="hidden" name="action" value="release_item">
                <input type="hidden" name="product_id" value="<?= (int)$product['id']; ?>">
                <div>
                    <label class="text-sm text-slate-600">Quantity</label>
                    <input type="number" min="1" max="<?= (int)$product['stock_qty']; ?>" name="quantity" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Remarks</label>
                    <textarea name="remarks" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                    <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Release</button>
                </div>
            </form>
        </div>
    </div>

    <div id="tag-out-<?= (int)$product['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-rose-700">Tag as Out of Stock</h3>
            <p class="mt-2 text-sm text-slate-600">Mark <span class="font-semibold"><?= e($product['product_name']); ?></span> as out of stock and notify purchasing?</p>
            <form method="post" class="mt-4 space-y-3">
                <input type="hidden" name="action" value="tag_out_of_stock">
                <input type="hidden" name="product_id" value="<?= (int)$product['id']; ?>">
                <div>
                    <label class="text-sm text-slate-600">Remarks</label>
                    <textarea name="remarks" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                    <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white">Tag Out of Stock</button>
                </div>
            </form>
        </div>
    </div>

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
