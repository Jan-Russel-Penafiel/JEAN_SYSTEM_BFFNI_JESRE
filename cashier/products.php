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
        } elseif ($quantity + get_cashier_open_order_product_quantity($pdo, (int)$user['id'], $productId) > (int)$product['stock_qty']) {
            flash_set('error', 'Requested quantity exceeds available stock.');
        } else {
            $pdo->beginTransaction();
            try {
                add_product_to_cashier_open_order($pdo, (int)$user['id'], $product, $quantity);
                $pdo->commit();
                flash_set('success', 'Product added to the current sales order. Complete the order before payment.');
            } catch (Exception $e) {
                $pdo->rollBack();
                flash_set('error', 'Failed to create sales order.');
            }
        }

        header('Location: ' . app_url('cashier/orders.php'));
        exit;
    }

    if ($action === 'bulk_create_order') {
        $submittedQuantities = $_POST['bulk_quantities'] ?? [];
        $bulkProducts = [];
        $errorMessage = '';

        if (!is_array($submittedQuantities)) {
            $submittedQuantities = [];
        }

        $productStmt = $pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');

        foreach ($submittedQuantities as $rawProductId => $rawQuantity) {
            $productId = (int)$rawProductId;
            $quantity = (int)$rawQuantity;

            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            $productStmt->execute(['id' => $productId]);
            $product = $productStmt->fetch();

            if (!$product) {
                $errorMessage = 'One selected product no longer exists.';
                break;
            }

            if ($quantity + get_cashier_open_order_product_quantity($pdo, (int)$user['id'], $productId) > (int)$product['stock_qty']) {
                $errorMessage = 'Requested quantity exceeds available stock for ' . $product['product_name'] . '.';
                break;
            }

            $product['quantity'] = $quantity;
            $bulkProducts[] = $product;
        }

        if ($errorMessage !== '') {
            flash_set('error', $errorMessage);
        } elseif (!$bulkProducts) {
            flash_set('error', 'Select at least one product quantity for bulk order.');
        } else {
            $pdo->beginTransaction();
            try {
                add_products_to_cashier_open_order($pdo, (int)$user['id'], $bulkProducts);
                $pdo->commit();
                flash_set('success', 'Bulk order added to one sales order. Complete the order before payment.');
            } catch (Exception $e) {
                $pdo->rollBack();
                flash_set('error', 'Failed to create bulk order.');
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
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-brand-700">Browse Product</h2>
            <p class="text-sm text-slate-500">Choose products, confirm the order, generate the sales order, then send it to order completion.</p>
        </div>
        <button data-modal-open="bulk-order-modal" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Bulk Order</button>
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

<div id="bulk-order-modal" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-3xl rounded-xl bg-white p-6">
        <h3 class="text-lg font-semibold text-brand-700">Bulk Order</h3>
        <form method="post" class="mt-4 space-y-4">
            <input type="hidden" name="action" value="bulk_create_order">
            <div class="max-h-[70vh] overflow-y-auto rounded-xl border border-brand-100">
                <table class="min-w-full text-sm">
                    <thead class="bg-brand-50 text-left text-slate-600">
                    <tr>
                        <th class="px-3 py-2">Product</th>
                        <th class="px-3 py-2 text-right">Price</th>
                        <th class="px-3 py-2 text-right">Stock</th>
                        <th class="px-3 py-2 text-right">Qty</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr class="border-t border-brand-100">
                            <td class="px-3 py-3">
                                <p class="font-semibold text-slate-700"><?= e($product['sku']); ?> - <?= e($product['product_name']); ?></p>
                            </td>
                            <td class="px-3 py-3 text-right"><?= e(format_currency($product['price'])); ?></td>
                            <td class="px-3 py-3 text-right"><?= (int)$product['stock_qty']; ?></td>
                            <td class="px-3 py-3 text-right">
                                <input type="number" min="0" max="<?= (int)$product['stock_qty']; ?>" name="bulk_quantities[<?= (int)$product['id']; ?>]" class="ml-auto w-24 rounded-lg border border-slate-200 px-3 py-2 text-right" value="0">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Create Bulk Order</button>
            </div>
        </form>
    </div>
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
