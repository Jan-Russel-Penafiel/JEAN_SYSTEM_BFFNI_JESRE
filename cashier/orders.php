<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('CASHIER');

$pageTitle = 'Sales Orders';
$activePage = 'sales_orders';
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_order') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);

        $productStmt = $pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
        $productStmt->execute(['id' => $productId]);
        $product = $productStmt->fetch();

        if (!$product || $quantity <= 0) {
            flash_set('error', 'Please choose a valid product and quantity.');
        } elseif ($quantity > (int)$product['stock_qty']) {
            flash_set('error', 'Requested quantity exceeds available stock.');
        } else {
            $orderNo = 'SO-' . date('YmdHis') . '-' . random_int(100, 999);
            $totalAmount = $quantity * (float)$product['price'];

            $pdo->beginTransaction();
            try {
                $orderStmt = $pdo->prepare('INSERT INTO sales_orders (order_no, cashier_id, total_amount, payment_status, flow_status) VALUES (:order_no, :cashier_id, :total_amount, :payment_status, :flow_status)');
                $orderStmt->execute([
                    'order_no' => $orderNo,
                    'cashier_id' => $user['id'],
                    'total_amount' => $totalAmount,
                    'payment_status' => 'UNPAID',
                    'flow_status' => 'ORDER_COMPLETE',
                ]);

                $salesOrderId = (int)$pdo->lastInsertId();
                $itemStmt = $pdo->prepare('INSERT INTO sales_order_items (sales_order_id, product_id, quantity, unit_price, subtotal) VALUES (:sales_order_id, :product_id, :quantity, :unit_price, :subtotal)');
                $itemStmt->execute([
                    'sales_order_id' => $salesOrderId,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => $product['price'],
                    'subtotal' => $totalAmount,
                ]);

                $pdo->commit();
                flash_set('success', 'Sales order created successfully.');
            } catch (Exception $e) {
                $pdo->rollBack();
                flash_set('error', 'Failed to create sales order.');
            }
        }

        header('Location: ' . app_url('cashier/orders.php'));
        exit;
    }

    if ($action === 'update_order') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $itemId = (int)($_POST['item_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);

        $orderStmt = $pdo->prepare('SELECT so.*, soi.product_id, soi.unit_price, p.stock_qty FROM sales_orders so JOIN sales_order_items soi ON soi.sales_order_id = so.id JOIN products p ON p.id = soi.product_id WHERE so.id = :order_id AND soi.id = :item_id AND so.cashier_id = :cashier_id LIMIT 1');
        $orderStmt->execute([
            'order_id' => $orderId,
            'item_id' => $itemId,
            'cashier_id' => $user['id'],
        ]);
        $order = $orderStmt->fetch();

        if (!$order || $quantity <= 0) {
            flash_set('error', 'Invalid order update request.');
        } elseif ($order['payment_status'] === 'PAID') {
            flash_set('error', 'Paid orders can no longer be edited.');
        } elseif ($quantity > (int)$order['stock_qty']) {
            flash_set('error', 'Requested quantity exceeds available stock.');
        } else {
            $subtotal = $quantity * (float)$order['unit_price'];
            $pdo->beginTransaction();
            try {
                $updateItem = $pdo->prepare('UPDATE sales_order_items SET quantity = :quantity, subtotal = :subtotal WHERE id = :id');
                $updateItem->execute([
                    'quantity' => $quantity,
                    'subtotal' => $subtotal,
                    'id' => $itemId,
                ]);

                $updateOrder = $pdo->prepare('UPDATE sales_orders SET total_amount = :total_amount WHERE id = :id');
                $updateOrder->execute([
                    'total_amount' => $subtotal,
                    'id' => $orderId,
                ]);

                $pdo->commit();
                flash_set('success', 'Order updated successfully.');
            } catch (Exception $e) {
                $pdo->rollBack();
                flash_set('error', 'Failed to update order.');
            }
        }

        header('Location: ' . app_url('cashier/orders.php'));
        exit;
    }

    if ($action === 'delete_order') {
        $orderId = (int)($_POST['order_id'] ?? 0);

        $check = $pdo->prepare('SELECT * FROM sales_orders WHERE id = :id AND cashier_id = :cashier_id LIMIT 1');
        $check->execute([
            'id' => $orderId,
            'cashier_id' => $user['id'],
        ]);
        $order = $check->fetch();

        if (!$order) {
            flash_set('error', 'Order not found.');
        } elseif ($order['payment_status'] === 'PAID') {
            flash_set('error', 'Cannot delete paid order.');
        } else {
            $stmt = $pdo->prepare('DELETE FROM sales_orders WHERE id = :id');
            $stmt->execute(['id' => $orderId]);
            flash_set('success', 'Order deleted successfully.');
        }

        header('Location: ' . app_url('cashier/orders.php'));
        exit;
    }
}

$products = $pdo->query('SELECT id, sku, product_name, price, stock_qty FROM products ORDER BY product_name ASC')->fetchAll();

$ordersStmt = $pdo->prepare('SELECT so.*, soi.id AS item_id, soi.quantity, soi.unit_price, soi.subtotal, p.sku, p.product_name, p.stock_qty FROM sales_orders so JOIN sales_order_items soi ON soi.sales_order_id = so.id JOIN products p ON p.id = soi.product_id WHERE so.cashier_id = :cashier_id ORDER BY so.id DESC');
$ordersStmt->execute(['cashier_id' => $user['id']]);
$orders = $ordersStmt->fetchAll();

include __DIR__ . '/../partials/header.php';
?>
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-brand-700">Sales Orders</h2>
            <p class="text-sm text-slate-500">Order confirmation, generate sales order, and manage unpaid orders.</p>
        </div>
        <button data-modal-open="create-order-modal" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Create Order</button>
    </div>

    <section class="rounded-xl border border-brand-100 bg-white p-4 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
            <tr class="border-b border-slate-100 text-left text-slate-500">
                <th class="py-2 pr-3">Order #</th>
                <th class="py-2 pr-3">Product</th>
                <th class="py-2 pr-3">Qty</th>
                <th class="py-2 pr-3">Amount</th>
                <th class="py-2 pr-3">Payment</th>
                <th class="py-2 pr-3">Flow</th>
                <th class="py-2">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$orders): ?>
                <tr><td class="py-3 text-slate-500" colspan="7">No sales orders yet.</td></tr>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <tr class="border-b border-slate-50">
                        <td class="py-2 pr-3 font-medium text-slate-700"><?= e($order['order_no']); ?></td>
                        <td class="py-2 pr-3"><?= e($order['sku']); ?> - <?= e($order['product_name']); ?></td>
                        <td class="py-2 pr-3"><?= (int)$order['quantity']; ?></td>
                        <td class="py-2 pr-3"><?= e(format_currency($order['total_amount'])); ?></td>
                        <td class="py-2 pr-3"><span class="rounded-full px-2 py-1 text-xs font-semibold <?= e(status_badge_class($order['payment_status'])); ?>"><?= e($order['payment_status']); ?></span></td>
                        <td class="py-2 pr-3"><?= e($order['flow_status']); ?></td>
                        <td class="py-2">
                            <div class="flex flex-wrap gap-2">
                                <button data-modal-open="view-order-<?= (int)$order['id']; ?>" class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">View</button>
                                <button data-modal-open="edit-order-<?= (int)$order['id']; ?>" class="rounded-md bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-700">Edit</button>
                                <button data-modal-open="delete-order-<?= (int)$order['id']; ?>" class="rounded-md bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700">Delete</button>
                                <?php if ($order['payment_status'] === 'UNPAID'): ?>
                                    <a href="<?= e(app_url('cashier/payments.php')); ?>" class="rounded-md bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Payment</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
</div>

<div id="create-order-modal" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-xl rounded-xl bg-white p-6">
        <h3 class="text-lg font-semibold text-brand-700">Create Sales Order</h3>
        <form method="post" class="mt-4 space-y-3">
            <input type="hidden" name="action" value="create_order">
            <div>
                <label class="text-sm text-slate-600">Product</label>
                <select name="product_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                    <option value="">Select Product</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= (int)$product['id']; ?>"><?= e($product['sku']); ?> - <?= e($product['product_name']); ?> (Stock: <?= (int)$product['stock_qty']; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-sm text-slate-600">Quantity</label>
                <input type="number" min="1" name="quantity" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Create</button>
            </div>
        </form>
    </div>
</div>

<?php foreach ($orders as $order): ?>
    <div id="view-order-<?= (int)$order['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-lg rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-brand-700">View Order</h3>
            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <dt class="text-slate-500">Order #</dt><dd class="font-medium"><?= e($order['order_no']); ?></dd>
                <dt class="text-slate-500">Product</dt><dd class="font-medium"><?= e($order['sku']); ?> - <?= e($order['product_name']); ?></dd>
                <dt class="text-slate-500">Quantity</dt><dd class="font-medium"><?= (int)$order['quantity']; ?></dd>
                <dt class="text-slate-500">Unit Price</dt><dd class="font-medium"><?= e(format_currency($order['unit_price'])); ?></dd>
                <dt class="text-slate-500">Total Amount</dt><dd class="font-medium"><?= e(format_currency($order['total_amount'])); ?></dd>
                <dt class="text-slate-500">Payment Status</dt><dd class="font-medium"><?= e($order['payment_status']); ?></dd>
                <dt class="text-slate-500">Flow Status</dt><dd class="font-medium"><?= e($order['flow_status']); ?></dd>
            </dl>
            <div class="mt-5 flex justify-end">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Close</button>
            </div>
        </div>
    </div>

    <div id="edit-order-<?= (int)$order['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-lg rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-brand-700">Edit Order Quantity</h3>
            <form method="post" class="mt-4 space-y-3">
                <input type="hidden" name="action" value="update_order">
                <input type="hidden" name="order_id" value="<?= (int)$order['id']; ?>">
                <input type="hidden" name="item_id" value="<?= (int)$order['item_id']; ?>">
                <div>
                    <label class="text-sm text-slate-600">Quantity</label>
                    <input type="number" min="1" max="<?= (int)$order['stock_qty']; ?>" name="quantity" value="<?= (int)$order['quantity']; ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required <?= $order['payment_status'] === 'PAID' ? 'disabled' : ''; ?>>
                </div>
                <?php if ($order['payment_status'] === 'PAID'): ?>
                    <p class="text-xs text-rose-600">Paid orders cannot be edited.</p>
                <?php endif; ?>
                <div class="flex justify-end gap-2">
                    <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white" <?= $order['payment_status'] === 'PAID' ? 'disabled' : ''; ?>>Save</button>
                </div>
            </form>
        </div>
    </div>

    <div id="delete-order-<?= (int)$order['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-rose-700">Delete Order</h3>
            <p class="mt-2 text-sm text-slate-600">Delete order <span class="font-semibold"><?= e($order['order_no']); ?></span>?</p>
            <?php if ($order['payment_status'] === 'PAID'): ?>
                <p class="mt-2 text-xs text-rose-600">Paid orders cannot be deleted.</p>
            <?php endif; ?>
            <form method="post" class="mt-5 flex justify-end gap-2">
                <input type="hidden" name="action" value="delete_order">
                <input type="hidden" name="order_id" value="<?= (int)$order['id']; ?>">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white" <?= $order['payment_status'] === 'PAID' ? 'disabled' : ''; ?>>Delete</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<?php include __DIR__ . '/../partials/footer.php'; ?>
