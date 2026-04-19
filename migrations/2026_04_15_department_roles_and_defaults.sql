ALTER TABLE users
    MODIFY role ENUM('ADMIN','CASHIER','INVENTORY','PURCHASING','RECEIVING','STORAGE','ACCOUNTING') NOT NULL;

INSERT INTO users (name, username, password, role)
SELECT 'Inventory Officer', 'inventory', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'INVENTORY'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'inventory');

INSERT INTO users (name, username, password, role)
SELECT 'Purchasing Officer', 'purchasing', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'PURCHASING'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'purchasing');

INSERT INTO users (name, username, password, role)
SELECT 'Receiving Officer', 'receiving', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'RECEIVING'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'receiving');

INSERT INTO users (name, username, password, role)
SELECT 'Storage Officer', 'storage', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'STORAGE'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'storage');

INSERT INTO users (name, username, password, role)
SELECT 'Accounting Officer', 'accounting', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ACCOUNTING'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'accounting');
