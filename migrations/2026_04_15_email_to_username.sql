-- Migration: users.email -> users.username
-- Run this once on existing databases created before April 15, 2026.

SET @has_email := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'email'
);

SET @has_username := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'username'
);

SET @rename_sql := IF(
    @has_email = 1 AND @has_username = 0,
    'ALTER TABLE users CHANGE COLUMN email username VARCHAR(50) NOT NULL',
    'SELECT 1'
);
PREPARE stmt_rename FROM @rename_sql;
EXECUTE stmt_rename;
DEALLOCATE PREPARE stmt_rename;

SET @has_username_after := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'username'
);

SET @has_uq_username := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND INDEX_NAME = 'uq_users_username'
);

SET @unique_sql := IF(
    @has_username_after = 1 AND @has_uq_username = 0,
    'ALTER TABLE users ADD UNIQUE KEY uq_users_username (username)',
    'SELECT 1'
);
PREPARE stmt_unique FROM @unique_sql;
EXECUTE stmt_unique;
DEALLOCATE PREPARE stmt_unique;

UPDATE users SET username = 'cashier' WHERE username = 'cashier@jzsisters.local';
