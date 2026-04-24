CREATE DATABASE IF NOT EXISTS jz_sisters_opc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE jz_sisters_opc;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('CASHIER','INVENTORY','PURCHASING','ACCOUNTING') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(50) NOT NULL UNIQUE,
    product_name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    price DECIMAL(12,2) NOT NULL DEFAULT 0,
    stock_qty INT NOT NULL DEFAULT 0,
    reorder_level INT NOT NULL DEFAULT 10,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sales_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_no VARCHAR(40) NOT NULL UNIQUE,
    cashier_id INT NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    payment_status ENUM('UNPAID','PAID') NOT NULL DEFAULT 'UNPAID',
    flow_status ENUM('ORDER_CONFIRMED','ORDER_COMPLETE') NOT NULL DEFAULT 'ORDER_CONFIRMED',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cashier_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS sales_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sales_order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sales_order_id INT NOT NULL,
    amount_paid DECIMAL(12,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    receipt_no VARCHAR(40) NOT NULL,
    paid_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS inventory_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    department ENUM('INVENTORY','PURCHASING','CASHIER') NOT NULL,
    change_type ENUM('SALE','PURCHASE','RETURN','ADJUSTMENT') NOT NULL,
    availability_status ENUM('YES','NO') DEFAULT NULL,
    item_check_status ENUM('YES','NO') DEFAULT NULL,
    qty_before INT NOT NULL,
    qty_change INT NOT NULL,
    qty_after INT NOT NULL,
    remarks VARCHAR(255) NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(40) NOT NULL UNIQUE,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
    supplier_name VARCHAR(150) NOT NULL,
    status ENUM('PENDING','SENT_TO_RECEIVING','RETURNED','STORED') NOT NULL DEFAULT 'PENDING',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS department_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    target_department ENUM('PURCHASING','INVENTORY','ACCOUNTING','CASHIER') NOT NULL,
    message VARCHAR(255) NOT NULL,
    status ENUM('PENDING','READ') NOT NULL DEFAULT 'PENDING',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS accounting_expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expense_date DATE NOT NULL,
    category VARCHAR(100) NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (name, username, password, role)
SELECT 'Main Cashier', 'cashier', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'CASHIER'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'cashier');

INSERT INTO users (name, username, password, role)
SELECT 'Inventory Officer', 'inventory', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'INVENTORY'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'inventory');

INSERT INTO users (name, username, password, role)
SELECT 'Purchasing Officer', 'purchasing', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'PURCHASING'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'purchasing');

INSERT INTO users (name, username, password, role)
SELECT 'Accounting Officer', 'accounting', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ACCOUNTING'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'accounting');

INSERT INTO products (sku, product_name, description, price, stock_qty, reorder_level)
SELECT 'JZ-001', 'Laundry Detergent 1kg', 'Household cleaning item', 85.00, 120, 20
WHERE NOT EXISTS (SELECT 1 FROM products WHERE sku = 'JZ-001');

INSERT INTO products (sku, product_name, description, price, stock_qty, reorder_level)
SELECT 'JZ-002', 'Dishwashing Liquid 500ml', 'Kitchen cleaning liquid', 45.00, 90, 15
WHERE NOT EXISTS (SELECT 1 FROM products WHERE sku = 'JZ-002');

INSERT INTO products (sku, product_name, description, price, stock_qty, reorder_level)
SELECT 'JZ-003', 'All-Purpose Soap Bar', 'General merchandise', 28.00, 70, 20
WHERE NOT EXISTS (SELECT 1 FROM products WHERE sku = 'JZ-003');
