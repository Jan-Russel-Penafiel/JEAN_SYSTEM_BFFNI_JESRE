UPDATE inventory_records
SET department = 'INVENTORY'
WHERE department IN ('RECEIVING', 'STORAGE');

UPDATE inventory_records
SET change_type = 'PURCHASE'
WHERE change_type = 'STORAGE_IN';

UPDATE inventory_records
SET change_type = 'ADJUSTMENT'
WHERE change_type = 'STORAGE_OUT';

ALTER TABLE inventory_records
    MODIFY department ENUM('INVENTORY', 'PURCHASING', 'CASHIER') NOT NULL,
    MODIFY change_type ENUM('SALE', 'PURCHASE', 'RETURN', 'ADJUSTMENT') NOT NULL;

UPDATE purchase_orders
SET status = 'STORED'
WHERE status = 'INSPECTED_OK';

UPDATE purchase_orders
SET status = 'RETURNED'
WHERE status = 'INSPECTED_NOT_OK';

ALTER TABLE purchase_orders
    MODIFY status ENUM('PENDING', 'SENT_TO_RECEIVING', 'RETURNED', 'STORED') NOT NULL DEFAULT 'PENDING';

DROP TABLE IF EXISTS receiving_reports;
DROP TABLE IF EXISTS storage_logs;
