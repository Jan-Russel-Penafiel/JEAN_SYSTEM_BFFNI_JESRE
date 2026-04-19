<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['INVENTORY', 'ADMIN']);

$pageTitle = 'Product Management';
$activePage = 'products';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_product') {
        $sku = strtoupper(trim($_POST['sku'] ?? ''));
        $productName = trim($_POST['product_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $stockQty = (int)($_POST['stock_qty'] ?? 0);
        $reorderLevel = (int)($_POST['reorder_level'] ?? 10);

        if ($sku === '' || $productName === '' || $price <= 0) {
            flash_set('error', 'SKU, product name, and valid price are required.');
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO products (sku, product_name, description, price, stock_qty, reorder_level) VALUES (:sku, :product_name, :description, :price, :stock_qty, :reorder_level)');
                $stmt->execute([
                    'sku' => $sku,
                    'product_name' => $productName,
                    'description' => $description,
                    'price' => $price,
                    'stock_qty' => $stockQty,
                    'reorder_level' => $reorderLevel,
                ]);
                flash_set('success', 'Product created successfully.');
            } catch (PDOException $e) {
                flash_set('error', 'Failed to create product. SKU may already exist.');
            }
        }
        header('Location: ' . app_url('admin/products.php'));
        exit;
    }

    if ($action === 'update_product') {
        $id = (int)($_POST['id'] ?? 0);
        $sku = strtoupper(trim($_POST['sku'] ?? ''));
        $productName = trim($_POST['product_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $stockQty = (int)($_POST['stock_qty'] ?? 0);
        $reorderLevel = (int)($_POST['reorder_level'] ?? 10);

        if ($id <= 0 || $sku === '' || $productName === '' || $price <= 0) {
            flash_set('error', 'Invalid product update request.');
        } else {
            try {
                $stmt = $pdo->prepare('UPDATE products SET sku = :sku, product_name = :product_name, description = :description, price = :price, stock_qty = :stock_qty, reorder_level = :reorder_level WHERE id = :id');
                $stmt->execute([
                    'id' => $id,
                    'sku' => $sku,
                    'product_name' => $productName,
                    'description' => $description,
                    'price' => $price,
                    'stock_qty' => $stockQty,
                    'reorder_level' => $reorderLevel,
                ]);
                flash_set('success', 'Product updated successfully.');
            } catch (PDOException $e) {
                flash_set('error', 'Failed to update product. SKU may already exist.');
            }
        }
        header('Location: ' . app_url('admin/products.php'));
        exit;
    }

    if ($action === 'delete_product') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
                $stmt->execute(['id' => $id]);
                flash_set('success', 'Product deleted successfully.');
            } catch (PDOException $e) {
                flash_set('error', 'Cannot delete product that is used in transactions.');
            }
        }
        header('Location: ' . app_url('admin/products.php'));
        exit;
    }
}

$products = $pdo->query('SELECT * FROM products ORDER BY id DESC')->fetchAll();

include __DIR__ . '/../partials/header.php';
?>
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-brand-700">Products</h2>
            <p class="text-sm text-slate-500">Create, edit, view, and delete product records with modal forms.</p>
        </div>
        <button data-modal-open="create-product-modal" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Create Product</button>
    </div>

    <div class="rounded-xl border border-brand-100 bg-white p-4 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
            <tr class="border-b border-slate-100 text-left text-slate-500">
                <th class="py-3 pr-3">SKU</th>
                <th class="py-3 pr-3">Product Name</th>
                <th class="py-3 pr-3">Price</th>
                <th class="py-3 pr-3">Stock</th>
                <th class="py-3 pr-3">Reorder Level</th>
                <th class="py-3 pr-3">Status</th>
                <th class="py-3">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$products): ?>
                <tr><td class="py-4 text-slate-500" colspan="7">No products found.</td></tr>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <?php $stockStatus = ((int)$product['stock_qty'] <= (int)$product['reorder_level']) ? 'LOW' : 'OK'; ?>
                    <tr class="border-b border-slate-50">
                        <td class="py-3 pr-3 font-medium text-slate-700"><?= e($product['sku']); ?></td>
                        <td class="py-3 pr-3"><?= e($product['product_name']); ?></td>
                        <td class="py-3 pr-3"><?= e(format_currency($product['price'])); ?></td>
                        <td class="py-3 pr-3"><?= (int)$product['stock_qty']; ?></td>
                        <td class="py-3 pr-3"><?= (int)$product['reorder_level']; ?></td>
                        <td class="py-3 pr-3">
                            <span class="rounded-full px-2 py-1 text-xs font-semibold <?= e(status_badge_class($stockStatus)); ?>"><?= e($stockStatus); ?></span>
                        </td>
                        <td class="py-3">
                            <div class="flex flex-wrap gap-2">
                                <button data-modal-open="view-product-<?= (int)$product['id']; ?>" class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-200">View</button>
                                <button data-modal-open="edit-product-<?= (int)$product['id']; ?>" class="rounded-md bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-700 hover:bg-brand-200">Edit</button>
                                <button data-modal-open="delete-product-<?= (int)$product['id']; ?>" class="rounded-md bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-200">Delete</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="create-product-modal" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-2xl rounded-xl bg-white p-6">
        <h3 class="text-lg font-semibold text-brand-700">Create Product</h3>
        <form method="post" class="mt-4 space-y-3">
            <input type="hidden" name="action" value="create_product">
            <div class="grid md:grid-cols-2 gap-3">
                <div>
                    <label class="text-sm text-slate-600">SKU</label>
                    <input name="sku" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Product Name</label>
                    <input name="product_name" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                </div>
            </div>
            <div>
                <label class="text-sm text-slate-600">Description</label>
                <textarea name="description" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" rows="2"></textarea>
            </div>
            <div class="grid md:grid-cols-3 gap-3">
                <div>
                    <label class="text-sm text-slate-600">Price</label>
                    <input name="price" type="number" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Stock Qty</label>
                    <input name="stock_qty" type="number" min="0" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" value="0" required>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Reorder Level</label>
                    <input name="reorder_level" type="number" min="0" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" value="10" required>
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Create</button>
            </div>
        </form>
    </div>
</div>

<?php foreach ($products as $product): ?>
    <div id="view-product-<?= (int)$product['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-xl rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-brand-700">View Product</h3>
            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <dt class="text-slate-500">SKU</dt><dd class="font-medium"><?= e($product['sku']); ?></dd>
                <dt class="text-slate-500">Name</dt><dd class="font-medium"><?= e($product['product_name']); ?></dd>
                <dt class="text-slate-500">Price</dt><dd class="font-medium"><?= e(format_currency($product['price'])); ?></dd>
                <dt class="text-slate-500">Stock</dt><dd class="font-medium"><?= (int)$product['stock_qty']; ?></dd>
                <dt class="text-slate-500">Reorder</dt><dd class="font-medium"><?= (int)$product['reorder_level']; ?></dd>
                <dt class="text-slate-500">Description</dt><dd class="font-medium"><?= e($product['description']); ?></dd>
            </dl>
            <div class="mt-5 flex justify-end">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Close</button>
            </div>
        </div>
    </div>

    <div id="edit-product-<?= (int)$product['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-2xl rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-brand-700">Edit Product</h3>
            <form method="post" class="mt-4 space-y-3">
                <input type="hidden" name="action" value="update_product">
                <input type="hidden" name="id" value="<?= (int)$product['id']; ?>">
                <div class="grid md:grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm text-slate-600">SKU</label>
                        <input name="sku" value="<?= e($product['sku']); ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                    </div>
                    <div>
                        <label class="text-sm text-slate-600">Product Name</label>
                        <input name="product_name" value="<?= e($product['product_name']); ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                    </div>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Description</label>
                    <textarea name="description" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" rows="2"><?= e($product['description']); ?></textarea>
                </div>
                <div class="grid md:grid-cols-3 gap-3">
                    <div>
                        <label class="text-sm text-slate-600">Price</label>
                        <input name="price" type="number" step="0.01" min="0" value="<?= e($product['price']); ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                    </div>
                    <div>
                        <label class="text-sm text-slate-600">Stock Qty</label>
                        <input name="stock_qty" type="number" min="0" value="<?= (int)$product['stock_qty']; ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                    </div>
                    <div>
                        <label class="text-sm text-slate-600">Reorder Level</label>
                        <input name="reorder_level" type="number" min="0" value="<?= (int)$product['reorder_level']; ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div id="delete-product-<?= (int)$product['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-rose-700">Delete Product</h3>
            <p class="mt-2 text-sm text-slate-600">Are you sure you want to delete <span class="font-semibold"><?= e($product['product_name']); ?></span>?</p>
            <form method="post" class="mt-5 flex justify-end gap-2">
                <input type="hidden" name="action" value="delete_product">
                <input type="hidden" name="id" value="<?= (int)$product['id']; ?>">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white">Delete</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<?php include __DIR__ . '/../partials/footer.php'; ?>
