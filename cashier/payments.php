<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('CASHIER');

$pageTitle = 'Payments';
$activePage = 'payments';
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_payment') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $amountPaid = (float)($_POST['amount_paid'] ?? 0);
        $paymentMethod = trim($_POST['payment_method'] ?? 'Cash');

        $orderStmt = $pdo->prepare('SELECT so.*, soi.quantity, soi.product_id, p.stock_qty, p.reorder_level, p.product_name, p.sku FROM sales_orders so JOIN sales_order_items soi ON soi.sales_order_id = so.id JOIN products p ON p.id = soi.product_id WHERE so.id = :order_id AND so.cashier_id = :cashier_id LIMIT 1');
        $orderStmt->execute([
            'order_id' => $orderId,
            'cashier_id' => $user['id'],
        ]);
        $order = $orderStmt->fetch();

        if (!$order) {
            flash_set('error', 'Order not found for payment.');
        } elseif ($order['payment_status'] === 'PAID') {
            flash_set('error', 'Order is already paid.');
        } elseif (($order['flow_status'] ?? '') !== 'ORDER_COMPLETE') {
            flash_set('error', 'Only order-complete sales orders can be paid.');
        } elseif ($amountPaid < (float)$order['total_amount']) {
            flash_set('error', 'Amount paid is less than total amount due.');
        } elseif ((int)$order['quantity'] > (int)$order['stock_qty']) {
            flash_set('error', 'Not enough stock available at payment time.');
        } else {
            $qtyBefore = (int)$order['stock_qty'];
            $qtyChange = -((int)$order['quantity']);
            $qtyAfter = $qtyBefore + $qtyChange;
            $receiptNo = 'OR-' . date('YmdHis') . '-' . random_int(100, 999);

            $pdo->beginTransaction();
            try {
                $paymentStmt = $pdo->prepare('INSERT INTO payments (sales_order_id, amount_paid, payment_method, receipt_no, paid_at) VALUES (:sales_order_id, :amount_paid, :payment_method, :receipt_no, :paid_at)');
                $paymentStmt->execute([
                    'sales_order_id' => $orderId,
                    'amount_paid' => $amountPaid,
                    'payment_method' => $paymentMethod,
                    'receipt_no' => $receiptNo,
                    'paid_at' => date('Y-m-d H:i:s'),
                ]);
                $createdPaymentId = (int)$pdo->lastInsertId();

                $updateOrder = $pdo->prepare("UPDATE sales_orders SET payment_status = 'PAID', flow_status = 'ORDER_COMPLETE' WHERE id = :id");
                $updateOrder->execute(['id' => $orderId]);

                $updateProduct = $pdo->prepare('UPDATE products SET stock_qty = :stock_qty WHERE id = :id');
                $updateProduct->execute([
                    'stock_qty' => $qtyAfter,
                    'id' => $order['product_id'],
                ]);

                $invStmt = $pdo->prepare('INSERT INTO inventory_records (product_id, department, change_type, availability_status, item_check_status, qty_before, qty_change, qty_after, remarks, created_by) VALUES (:product_id, :department, :change_type, :availability_status, :item_check_status, :qty_before, :qty_change, :qty_after, :remarks, :created_by)');
                $invStmt->execute([
                    'product_id' => $order['product_id'],
                    'department' => 'CASHIER',
                    'change_type' => 'SALE',
                    'availability_status' => $qtyAfter > 0 ? 'YES' : 'NO',
                    'item_check_status' => null,
                    'qty_before' => $qtyBefore,
                    'qty_change' => $qtyChange,
                    'qty_after' => $qtyAfter,
                    'remarks' => 'Payment posted for order ' . $order['order_no'],
                    'created_by' => $user['id'],
                ]);

                if ($qtyAfter <= (int)$order['reorder_level']) {
                    $notify = $pdo->prepare('INSERT INTO department_notifications (target_department, message, status) VALUES (:target_department, :message, :status)');
                    $notify->execute([
                        'target_department' => 'PURCHASING',
                        'message' => 'Low stock after cashier sale for ' . $order['product_name'] . ' (SKU ' . $order['sku'] . ').',
                        'status' => 'PENDING',
                    ]);
                }

                $pdo->commit();
                header('Location: ' . app_url('receipt.php') . '?payment_id=' . $createdPaymentId . '&print=1');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                flash_set('error', 'Failed to process payment.');
            }
        }

        header('Location: ' . app_url('cashier/payments.php'));
        exit;
    }

    if ($action === 'update_payment') {
        $paymentId = (int)($_POST['payment_id'] ?? 0);
        $amountPaid = (float)($_POST['amount_paid'] ?? 0);
        $paymentMethod = trim($_POST['payment_method'] ?? 'Cash');

        $stmt = $pdo->prepare('SELECT p.*, so.total_amount, so.cashier_id FROM payments p JOIN sales_orders so ON so.id = p.sales_order_id WHERE p.id = :payment_id LIMIT 1');
        $stmt->execute(['payment_id' => $paymentId]);
        $payment = $stmt->fetch();

        if (!$payment || (int)$payment['cashier_id'] !== (int)$user['id']) {
            flash_set('error', 'Payment not found.');
        } elseif ($amountPaid < (float)$payment['total_amount']) {
            flash_set('error', 'Amount paid cannot be less than order total.');
        } else {
            $update = $pdo->prepare('UPDATE payments SET amount_paid = :amount_paid, payment_method = :payment_method WHERE id = :id');
            $update->execute([
                'amount_paid' => $amountPaid,
                'payment_method' => $paymentMethod,
                'id' => $paymentId,
            ]);
            flash_set('success', 'Payment updated.');
        }

        header('Location: ' . app_url('cashier/payments.php'));
        exit;
    }

    if ($action === 'delete_payment') {
        $paymentId = (int)($_POST['payment_id'] ?? 0);

        $stmt = $pdo->prepare('SELECT p.*, so.id AS order_id, so.order_no, so.cashier_id, soi.quantity, soi.product_id, prod.stock_qty, prod.product_name, prod.sku FROM payments p JOIN sales_orders so ON so.id = p.sales_order_id JOIN sales_order_items soi ON soi.sales_order_id = so.id JOIN products prod ON prod.id = soi.product_id WHERE p.id = :payment_id LIMIT 1');
        $stmt->execute(['payment_id' => $paymentId]);
        $payment = $stmt->fetch();

        if (!$payment || (int)$payment['cashier_id'] !== (int)$user['id']) {
            flash_set('error', 'Payment not found.');
        } else {
            $qtyBefore = (int)$payment['stock_qty'];
            $qtyChange = (int)$payment['quantity'];
            $qtyAfter = $qtyBefore + $qtyChange;

            $pdo->beginTransaction();
            try {
                $delete = $pdo->prepare('DELETE FROM payments WHERE id = :id');
                $delete->execute(['id' => $paymentId]);

                $updateOrder = $pdo->prepare("UPDATE sales_orders SET payment_status = 'UNPAID' WHERE id = :id");
                $updateOrder->execute(['id' => $payment['order_id']]);

                $updateProduct = $pdo->prepare('UPDATE products SET stock_qty = :stock_qty WHERE id = :id');
                $updateProduct->execute([
                    'stock_qty' => $qtyAfter,
                    'id' => $payment['product_id'],
                ]);

                $invStmt = $pdo->prepare('INSERT INTO inventory_records (product_id, department, change_type, availability_status, item_check_status, qty_before, qty_change, qty_after, remarks, created_by) VALUES (:product_id, :department, :change_type, :availability_status, :item_check_status, :qty_before, :qty_change, :qty_after, :remarks, :created_by)');
                $invStmt->execute([
                    'product_id' => $payment['product_id'],
                    'department' => 'CASHIER',
                    'change_type' => 'RETURN',
                    'availability_status' => $qtyAfter > 0 ? 'YES' : 'NO',
                    'item_check_status' => null,
                    'qty_before' => $qtyBefore,
                    'qty_change' => $qtyChange,
                    'qty_after' => $qtyAfter,
                    'remarks' => 'Payment rollback for order ' . $payment['order_no'],
                    'created_by' => $user['id'],
                ]);

                $pdo->commit();
                flash_set('success', 'Payment deleted and stock reverted.');
            } catch (Exception $e) {
                $pdo->rollBack();
                flash_set('error', 'Failed to delete payment.');
            }
        }

        header('Location: ' . app_url('cashier/payments.php'));
        exit;
    }
}

$pendingCompletionStmt = $pdo->prepare("SELECT COUNT(*) FROM sales_orders WHERE cashier_id = :cashier_id AND payment_status = 'UNPAID' AND flow_status = 'ORDER_CONFIRMED'");
$pendingCompletionStmt->execute(['cashier_id' => $user['id']]);
$pendingCompletionCount = (int)$pendingCompletionStmt->fetchColumn();

$unpaidOrdersStmt = $pdo->prepare("SELECT so.*, soi.quantity, soi.product_id, p.sku, p.product_name FROM sales_orders so JOIN sales_order_items soi ON soi.sales_order_id = so.id JOIN products p ON p.id = soi.product_id WHERE so.cashier_id = :cashier_id AND so.payment_status = :payment_status AND so.flow_status = 'ORDER_COMPLETE' ORDER BY so.id DESC");
$unpaidOrdersStmt->execute([
    'cashier_id' => $user['id'],
    'payment_status' => 'UNPAID',
]);
$unpaidOrders = $unpaidOrdersStmt->fetchAll();

$paymentsStmt = $pdo->prepare('SELECT p.*, so.order_no, so.total_amount, so.cashier_id FROM payments p JOIN sales_orders so ON so.id = p.sales_order_id WHERE so.cashier_id = :cashier_id ORDER BY p.id DESC');
$paymentsStmt->execute(['cashier_id' => $user['id']]);
$payments = $paymentsStmt->fetchAll();

include __DIR__ . '/../partials/header.php';
?>
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-brand-700">Payments</h2>
            <p class="text-sm text-slate-500">Process payment for completed sales orders, issue a receipt, and update stock records.</p>
        </div>
        <button data-modal-open="create-payment-modal" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Process Payment</button>
    </div>

    <?php if ($pendingCompletionCount > 0): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
            <?= $pendingCompletionCount; ?> confirmed sales order<?= $pendingCompletionCount === 1 ? '' : 's are'; ?> waiting for order completion. Finish them in <a href="<?= e(app_url('cashier/orders.php')); ?>" class="font-semibold underline">Sales Orders</a> before payment.
        </div>
    <?php endif; ?>

    <section class="rounded-xl border border-brand-100 bg-white p-4 overflow-x-auto">
        <h3 class="text-base font-semibold text-brand-700 mb-3">Completed Unpaid Orders</h3>
        <table class="min-w-full text-sm">
            <thead>
            <tr class="border-b border-slate-100 text-left text-slate-500">
                <th class="py-2 pr-3">Order #</th>
                <th class="py-2 pr-3">Product</th>
                <th class="py-2 pr-3">Qty</th>
                <th class="py-2 pr-3">Amount Due</th>
                <th class="py-2">Action</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$unpaidOrders): ?>
                <tr><td class="py-3 text-slate-500" colspan="5">No unpaid orders found.</td></tr>
            <?php else: ?>
                <?php foreach ($unpaidOrders as $order): ?>
                    <tr class="border-b border-slate-50">
                        <td class="py-2 pr-3 font-medium text-slate-700"><?= e($order['order_no']); ?></td>
                        <td class="py-2 pr-3"><?= e($order['sku']); ?> - <?= e($order['product_name']); ?></td>
                        <td class="py-2 pr-3"><?= (int)$order['quantity']; ?></td>
                        <td class="py-2 pr-3"><?= e(format_currency($order['total_amount'])); ?></td>
                        <td class="py-2">
                            <button data-modal-open="pay-order-<?= (int)$order['id']; ?>" class="rounded-md bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Pay Now</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="rounded-xl border border-brand-100 bg-white p-4 overflow-x-auto">
        <h3 class="text-base font-semibold text-brand-700 mb-3">Payment Records</h3>
        <table class="min-w-full text-sm">
            <thead>
            <tr class="border-b border-slate-100 text-left text-slate-500">
                <th class="py-2 pr-3">Receipt #</th>
                <th class="py-2 pr-3">Order #</th>
                <th class="py-2 pr-3">Amount Paid</th>
                <th class="py-2 pr-3">Method</th>
                <th class="py-2 pr-3">Paid At</th>
                <th class="py-2">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$payments): ?>
                <tr><td class="py-3 text-slate-500" colspan="6">No payment records yet.</td></tr>
            <?php else: ?>
                <?php foreach ($payments as $payment): ?>
                    <tr class="border-b border-slate-50">
                        <td class="py-2 pr-3 font-medium text-slate-700"><?= e($payment['receipt_no']); ?></td>
                        <td class="py-2 pr-3"><?= e($payment['order_no']); ?></td>
                        <td class="py-2 pr-3"><?= e(format_currency($payment['amount_paid'])); ?></td>
                        <td class="py-2 pr-3"><?= e($payment['payment_method']); ?></td>
                        <td class="py-2 pr-3"><?= e($payment['paid_at']); ?></td>
                        <td class="py-2">
                            <div class="flex flex-wrap gap-2">
                                <a href="<?= e(app_url('receipt.php')); ?>?payment_id=<?= (int)$payment['id']; ?>" target="_blank" class="rounded-md bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Receipt</a>
                                <button data-modal-open="view-payment-<?= (int)$payment['id']; ?>" class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">View</button>
                                <button data-modal-open="edit-payment-<?= (int)$payment['id']; ?>" class="rounded-md bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-700">Edit</button>
                                <button data-modal-open="delete-payment-<?= (int)$payment['id']; ?>" class="rounded-md bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700">Delete</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
</div>

<div id="create-payment-modal" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-xl rounded-xl bg-white p-6">
        <h3 class="text-lg font-semibold text-brand-700">Process Payment</h3>
        <form method="post" class="mt-4 space-y-3">
            <input type="hidden" name="action" value="create_payment">
            <div>
                <label class="text-sm text-slate-600">Unpaid Order</label>
                <select name="order_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                    <option value="">Select Order</option>
                    <?php foreach ($unpaidOrders as $order): ?>
                        <option value="<?= (int)$order['id']; ?>"><?= e($order['order_no']); ?> - <?= e(format_currency($order['total_amount'])); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid md:grid-cols-2 gap-3">
                <div>
                    <label class="text-sm text-slate-600">Amount Paid</label>
                    <input type="number" step="0.01" min="0.01" name="amount_paid" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Payment Method</label>
                    <select name="payment_method" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                        <option value="Cash">Cash</option>
                        <option value="GCash">GCash</option>
                        <option value="Maya">Maya</option>
                        <option value="Card">Card</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Confirm Payment</button>
            </div>
        </form>
    </div>
</div>

<?php foreach ($unpaidOrders as $order): ?>
    <div id="pay-order-<?= (int)$order['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-xl rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-emerald-700">Payment for <?= e($order['order_no']); ?></h3>
            <form method="post" class="mt-4 space-y-3">
                <input type="hidden" name="action" value="create_payment">
                <input type="hidden" name="order_id" value="<?= (int)$order['id']; ?>">
                <div>
                    <label class="text-sm text-slate-600">Amount Paid</label>
                    <input type="number" step="0.01" min="<?= e($order['total_amount']); ?>" name="amount_paid" value="<?= e($order['total_amount']); ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Payment Method</label>
                    <select name="payment_method" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                        <option value="Cash">Cash</option>
                        <option value="GCash">GCash</option>
                        <option value="Maya">Maya</option>
                        <option value="Card">Card</option>
                    </select>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                    <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Pay and Update Stock</button>
                </div>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<?php foreach ($payments as $payment): ?>
    <div id="view-payment-<?= (int)$payment['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-lg rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-brand-700">View Payment</h3>
            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <dt class="text-slate-500">Receipt #</dt><dd class="font-medium"><?= e($payment['receipt_no']); ?></dd>
                <dt class="text-slate-500">Order #</dt><dd class="font-medium"><?= e($payment['order_no']); ?></dd>
                <dt class="text-slate-500">Order Total</dt><dd class="font-medium"><?= e(format_currency($payment['total_amount'])); ?></dd>
                <dt class="text-slate-500">Amount Paid</dt><dd class="font-medium"><?= e(format_currency($payment['amount_paid'])); ?></dd>
                <dt class="text-slate-500">Payment Method</dt><dd class="font-medium"><?= e($payment['payment_method']); ?></dd>
                <dt class="text-slate-500">Paid At</dt><dd class="font-medium"><?= e($payment['paid_at']); ?></dd>
            </dl>
            <div class="mt-5 flex justify-end gap-2">
                <a href="<?= e(app_url('receipt.php')); ?>?payment_id=<?= (int)$payment['id']; ?>" target="_blank" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Print Receipt</a>
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Close</button>
            </div>
        </div>
    </div>

    <div id="edit-payment-<?= (int)$payment['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-lg rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-brand-700">Edit Payment</h3>
            <form method="post" class="mt-4 space-y-3">
                <input type="hidden" name="action" value="update_payment">
                <input type="hidden" name="payment_id" value="<?= (int)$payment['id']; ?>">
                <div>
                    <label class="text-sm text-slate-600">Amount Paid</label>
                    <input type="number" step="0.01" min="<?= e($payment['total_amount']); ?>" name="amount_paid" value="<?= e($payment['amount_paid']); ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Payment Method</label>
                    <select name="payment_method" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                        <?php foreach (['Cash', 'GCash', 'Maya', 'Card'] as $method): ?>
                            <option value="<?= e($method); ?>" <?= $payment['payment_method'] === $method ? 'selected' : ''; ?>><?= e($method); ?></option>
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

    <div id="delete-payment-<?= (int)$payment['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-rose-700">Delete Payment</h3>
            <p class="mt-2 text-sm text-slate-600">Delete this payment and rollback stock for order <?= e($payment['order_no']); ?>?</p>
            <form method="post" class="mt-5 flex justify-end gap-2">
                <input type="hidden" name="action" value="delete_payment">
                <input type="hidden" name="payment_id" value="<?= (int)$payment['id']; ?>">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white">Delete</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<?php include __DIR__ . '/../partials/footer.php'; ?>
