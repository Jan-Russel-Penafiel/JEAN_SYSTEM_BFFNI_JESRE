<?php

function generate_sales_order_number()
{
    return 'SO-' . date('YmdHis') . '-' . random_int(100, 999);
}

function add_product_to_cashier_open_order(PDO $pdo, int $cashierId, array $product, int $quantity, ?callable $orderNoFactory = null)
{
    if ($cashierId <= 0 || $quantity <= 0 || empty($product['id'])) {
        throw new InvalidArgumentException('Invalid sales order request.');
    }

    $productId = (int)$product['id'];
    $unitPrice = (float)($product['price'] ?? 0);

    $openOrderStmt = $pdo->prepare("SELECT * FROM sales_orders WHERE cashier_id = :cashier_id AND payment_status = 'UNPAID' AND flow_status = 'ORDER_CONFIRMED' ORDER BY id DESC LIMIT 1");
    $openOrderStmt->execute(['cashier_id' => $cashierId]);
    $openOrder = $openOrderStmt->fetch();

    if ($openOrder) {
        $salesOrderId = (int)$openOrder['id'];
    } else {
        $orderNoFactory = $orderNoFactory ?: 'generate_sales_order_number';
        $orderNo = (string)$orderNoFactory();

        $orderStmt = $pdo->prepare('INSERT INTO sales_orders (order_no, cashier_id, total_amount, payment_status, flow_status) VALUES (:order_no, :cashier_id, :total_amount, :payment_status, :flow_status)');
        $orderStmt->execute([
            'order_no' => $orderNo,
            'cashier_id' => $cashierId,
            'total_amount' => 0,
            'payment_status' => 'UNPAID',
            'flow_status' => 'ORDER_CONFIRMED',
        ]);

        $salesOrderId = (int)$pdo->lastInsertId();
    }

    $existingItemStmt = $pdo->prepare('SELECT * FROM sales_order_items WHERE sales_order_id = :sales_order_id AND product_id = :product_id LIMIT 1');
    $existingItemStmt->execute([
        'sales_order_id' => $salesOrderId,
        'product_id' => $productId,
    ]);
    $existingItem = $existingItemStmt->fetch();

    if ($existingItem) {
        $newQuantity = (int)$existingItem['quantity'] + $quantity;
        $lineUnitPrice = (float)$existingItem['unit_price'];
        $updateItem = $pdo->prepare('UPDATE sales_order_items SET quantity = :quantity, subtotal = :subtotal WHERE id = :id');
        $updateItem->execute([
            'quantity' => $newQuantity,
            'subtotal' => $newQuantity * $lineUnitPrice,
            'id' => (int)$existingItem['id'],
        ]);
    } else {
        $itemStmt = $pdo->prepare('INSERT INTO sales_order_items (sales_order_id, product_id, quantity, unit_price, subtotal) VALUES (:sales_order_id, :product_id, :quantity, :unit_price, :subtotal)');
        $itemStmt->execute([
            'sales_order_id' => $salesOrderId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $quantity * $unitPrice,
        ]);
    }

    recalculate_sales_order_total($pdo, $salesOrderId);

    return $salesOrderId;
}

function add_products_to_cashier_open_order(PDO $pdo, int $cashierId, array $products, ?callable $orderNoFactory = null)
{
    $salesOrderId = null;
    $hasSelectedProduct = false;

    foreach ($products as $product) {
        $quantity = (int)($product['quantity'] ?? 0);

        if ($quantity <= 0) {
            continue;
        }

        $hasSelectedProduct = true;
        $salesOrderId = add_product_to_cashier_open_order($pdo, $cashierId, $product, $quantity, $orderNoFactory);
        $orderNoFactory = null;
    }

    if (!$hasSelectedProduct || $salesOrderId === null) {
        throw new InvalidArgumentException('Select at least one product quantity.');
    }

    return $salesOrderId;
}

function get_cashier_open_order_product_quantity(PDO $pdo, int $cashierId, int $productId)
{
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(soi.quantity), 0) FROM sales_orders so JOIN sales_order_items soi ON soi.sales_order_id = so.id WHERE so.cashier_id = :cashier_id AND so.payment_status = 'UNPAID' AND so.flow_status = 'ORDER_CONFIRMED' AND soi.product_id = :product_id");
    $stmt->execute([
        'cashier_id' => $cashierId,
        'product_id' => $productId,
    ]);

    return (int)$stmt->fetchColumn();
}

function recalculate_sales_order_total(PDO $pdo, int $salesOrderId)
{
    $totalStmt = $pdo->prepare('SELECT COALESCE(SUM(subtotal), 0) FROM sales_order_items WHERE sales_order_id = :sales_order_id');
    $totalStmt->execute(['sales_order_id' => $salesOrderId]);
    $totalAmount = (float)$totalStmt->fetchColumn();

    $updateOrder = $pdo->prepare('UPDATE sales_orders SET total_amount = :total_amount WHERE id = :id');
    $updateOrder->execute([
        'total_amount' => $totalAmount,
        'id' => $salesOrderId,
    ]);

    return $totalAmount;
}

function fetch_sales_order_with_items(PDO $pdo, int $salesOrderId)
{
    $orderStmt = $pdo->prepare('SELECT * FROM sales_orders WHERE id = :id LIMIT 1');
    $orderStmt->execute(['id' => $salesOrderId]);
    $order = $orderStmt->fetch();

    if (!$order) {
        return null;
    }

    $itemStmt = $pdo->prepare('SELECT * FROM sales_order_items WHERE sales_order_id = :sales_order_id ORDER BY id ASC');
    $itemStmt->execute(['sales_order_id' => $salesOrderId]);
    $order['items'] = $itemStmt->fetchAll();

    return $order;
}

function fetch_sales_order_item_details(PDO $pdo, int $salesOrderId)
{
    $itemStmt = $pdo->prepare('SELECT soi.*, p.sku, p.product_name, p.stock_qty, p.reorder_level FROM sales_order_items soi JOIN products p ON p.id = soi.product_id WHERE soi.sales_order_id = :sales_order_id ORDER BY soi.id ASC');
    $itemStmt->execute(['sales_order_id' => $salesOrderId]);

    return $itemStmt->fetchAll();
}

function group_sales_order_rows(array $rows)
{
    $orders = [];

    foreach ($rows as $row) {
        $orderId = (int)$row['id'];

        if (!isset($orders[$orderId])) {
            $orders[$orderId] = $row;
            $orders[$orderId]['items'] = [];
        }

        if (isset($row['item_id'])) {
            $orders[$orderId]['items'][] = [
                'id' => (int)$row['item_id'],
                'product_id' => (int)$row['product_id'],
                'quantity' => (int)$row['quantity'],
                'unit_price' => (float)$row['unit_price'],
                'subtotal' => (float)$row['subtotal'],
                'sku' => $row['sku'],
                'product_name' => $row['product_name'],
                'stock_qty' => isset($row['stock_qty']) ? (int)$row['stock_qty'] : null,
            ];
        }
    }

    return array_values($orders);
}

function summarize_sales_order_items(array $items)
{
    $summary = [];

    foreach ($items as $item) {
        $summary[] = $item['sku'] . ' - ' . $item['product_name'] . ' x ' . (int)$item['quantity'];
    }

    return implode(', ', $summary);
}
