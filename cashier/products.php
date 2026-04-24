<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('CASHIER');

$pageTitle = 'Browse Products';
$activePage = 'browse_products';
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
                    'flow_status' => 'ORDER_CONFIRMED',
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
                flash_set('success', 'Order confirmed and sales order generated. Complete the order in Sales Orders before payment.');
            } catch (Exception $e) {
                $pdo->rollBack();
                flash_set('error', 'Failed to create sales order.');
            }
        }

        header('Location: ' . app_url('cashier/orders.php'));
        exit;
    }
}

$products = $pdo->query('SELECT * FROM products ORDER BY product_name ASC')->fetchAll();

include __DIR__ . '/../partials/header.php';
?>
<div class="space-y-6">
    <div>
        <h2 class="text-2xl font-bold text-brand-700">Browse Product</h2>
        <p class="text-sm text-slate-500">Choose product, confirm the order, generate the sales order, then send it to order completion.</p>
    </div>

    <section class="rounded-xl border border-brand-100 bg-white p-4 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
            <tr class="border-b border-slate-100 text-left text-slate-500">
                <th class="py-2 pr-3">SKU</th>
                <th class="py-2 pr-3">Product</th>
                <th class="py-2 pr-3">Price</th>
                <th class="py-2 pr-3">Stock</th>
                <th class="py-2">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$products): ?>
                <tr><td class="py-3 text-slate-500" colspan="5">No products available.</td></tr>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <tr class="border-b border-slate-50">
                        <td class="py-2 pr-3 font-medium text-slate-700"><?= e($product['sku']); ?></td>
                        <td class="py-2 pr-3"><?= e($product['product_name']); ?></td>
                        <td class="py-2 pr-3"><?= e(format_currency($product['price'])); ?></td>
                        <td class="py-2 pr-3">
                            <span class="rounded-full px-2 py-1 text-xs font-semibold <?= (int)$product['stock_qty'] > 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'; ?>">
                                <?= (int)$product['stock_qty']; ?>
                            </span>
                        </td>
                        <td class="py-2">
                            <div class="flex flex-wrap gap-2">
                                <button data-modal-open="view-product-<?= (int)$product['id']; ?>" class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">View</button>
                                <button data-modal-open="choose-product-<?= (int)$product['id']; ?>" class="rounded-md bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-700">Choose Product</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
</div>

<?php foreach ($products as $product): ?>
    <div id="view-product-<?= (int)$product['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-lg rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-brand-700">Product Details</h3>
            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <dt class="text-slate-500">SKU</dt><dd class="font-medium"><?= e($product['sku']); ?></dd>
                <dt class="text-slate-500">Product</dt><dd class="font-medium"><?= e($product['product_name']); ?></dd>
                <dt class="text-slate-500">Price</dt><dd class="font-medium"><?= e(format_currency($product['price'])); ?></dd>
                <dt class="text-slate-500">Available Stock</dt><dd class="font-medium"><?= (int)$product['stock_qty']; ?></dd>
                <dt class="text-slate-500">Description</dt><dd class="font-medium"><?= e($product['description']); ?></dd>
            </dl>
            <div class="mt-5 flex justify-end">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Close</button>
            </div>
        </div>
    </div>

    <div id="choose-product-<?= (int)$product['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-lg rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-brand-700">Order Confirmation</h3>
            <p class="mt-1 text-sm text-slate-500">Generate a sales order for <?= e($product['product_name']); ?>. Payment is available after the order is marked complete.</p>
            <form method="post" class="mt-4 space-y-3">
                <input type="hidden" name="action" value="create_order">
                <input type="hidden" name="product_id" value="<?= (int)$product['id']; ?>">
                <div>
                    <label class="text-sm text-slate-600">Quantity</label>
                    <input type="number" name="quantity" min="1" max="<?= (int)$product['stock_qty']; ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                </div>
                <p class="text-xs text-slate-500">Unit Price: <?= e(format_currency($product['price'])); ?></p>
                <div class="flex justify-end gap-2">
                    <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Confirm and Create Sales Order</button>
                </div>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<?php include __DIR__ . '/../partials/footer.php'; ?>
