<?php

require_once __DIR__ . '/../includes/sales_order_helpers.php';

function assert_true($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function assert_same_value($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, "Assertion failed: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$pdo->exec("
    CREATE TABLE sales_orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_no TEXT NOT NULL UNIQUE,
        cashier_id INTEGER NOT NULL,
        total_amount REAL NOT NULL,
        payment_status TEXT NOT NULL DEFAULT 'UNPAID',
        flow_status TEXT NOT NULL DEFAULT 'ORDER_CONFIRMED',
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
");

$pdo->exec("
    CREATE TABLE sales_order_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sales_order_id INTEGER NOT NULL,
        product_id INTEGER NOT NULL,
        quantity INTEGER NOT NULL,
        unit_price REAL NOT NULL,
        subtotal REAL NOT NULL
    );
");

$cashierId = 7;
$firstProduct = [
    'id' => 101,
    'price' => 85.00,
];
$secondProduct = [
    'id' => 202,
    'price' => 45.00,
];

$orderId = add_product_to_cashier_open_order($pdo, $cashierId, $firstProduct, 2, static function () {
    return 'SO-TEST-001';
});
$secondOrderId = add_product_to_cashier_open_order($pdo, $cashierId, $secondProduct, 1, static function () {
    return 'SO-TEST-002';
});

assert_same_value($orderId, $secondOrderId, 'second product should be added to the existing open sales order');
assert_same_value(1, (int)$pdo->query('SELECT COUNT(*) FROM sales_orders')->fetchColumn(), 'only one sales order should exist before payment');
assert_same_value(2, (int)$pdo->query('SELECT COUNT(*) FROM sales_order_items')->fetchColumn(), 'both products should be sales order lines');
assert_same_value(215.0, (float)$pdo->query('SELECT total_amount FROM sales_orders WHERE id = 1')->fetchColumn(), 'order total should include all product lines');

$combinedOrder = fetch_sales_order_with_items($pdo, $orderId);
assert_true($combinedOrder !== null, 'combined order should be fetchable');
assert_same_value(2, count($combinedOrder['items']), 'combined order should include all items for the receipt');

$bulkPdo = new PDO('sqlite::memory:');
$bulkPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$bulkPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$bulkPdo->exec("
    CREATE TABLE sales_orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_no TEXT NOT NULL UNIQUE,
        cashier_id INTEGER NOT NULL,
        total_amount REAL NOT NULL,
        payment_status TEXT NOT NULL DEFAULT 'UNPAID',
        flow_status TEXT NOT NULL DEFAULT 'ORDER_CONFIRMED',
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
");
$bulkPdo->exec("
    CREATE TABLE sales_order_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sales_order_id INTEGER NOT NULL,
        product_id INTEGER NOT NULL,
        quantity INTEGER NOT NULL,
        unit_price REAL NOT NULL,
        subtotal REAL NOT NULL
    );
");

$bulkOrderId = add_products_to_cashier_open_order(
    $bulkPdo,
    $cashierId,
    [
        ['id' => 301, 'price' => 12.50, 'quantity' => 4],
        ['id' => 302, 'price' => 40.00, 'quantity' => 0],
        ['id' => 303, 'price' => 9.75, 'quantity' => 3],
    ],
    static function () {
        return 'SO-BULK-001';
    }
);

assert_same_value(1, (int)$bulkPdo->query('SELECT COUNT(*) FROM sales_orders')->fetchColumn(), 'bulk order should create one sales order');
assert_same_value(2, (int)$bulkPdo->query('SELECT COUNT(*) FROM sales_order_items')->fetchColumn(), 'bulk order should ignore zero-quantity rows');
assert_same_value(79.25, (float)$bulkPdo->query('SELECT total_amount FROM sales_orders WHERE id = 1')->fetchColumn(), 'bulk order total should include selected products only');
assert_same_value(2, count(fetch_sales_order_with_items($bulkPdo, $bulkOrderId)['items']), 'bulk order receipt should have one order with multiple items');

echo "sales_order_helpers_test passed\n";
