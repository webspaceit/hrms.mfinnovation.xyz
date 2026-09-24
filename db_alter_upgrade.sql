-- ============================================================
-- db_alter_upgrade.sql — ALTER-ONLY schema upgrade
-- ------------------------------------------------------------
-- For an ALREADY IMPORTED database that has DATA: brings the
-- schema up to date WITHOUT removing or overwriting any data.
--
-- What it does:
--   * Adds ONLY the columns/tables that are missing
--   * Never drops a column, never truncates, never deletes rows
--   * Is fully IDEMPOTENT — safe to run on a database that is
--     already current (it simply makes 0 changes)
--
-- How to run:
--   1. phpMyAdmin -> select your database
--   2. Import tab -> choose this file (or paste it in the SQL tab)
--   3. Run. A green "0 rows affected" per statement is normal.
--
-- Note: existing rows keep their values. New columns get safe
-- defaults ("0" for new bill amounts, "admin" for user role).
-- ============================================================

-- ------------------------------------------------------------
-- SANITY CHECK
-- If the next SELECT returns 0 rows, your database still uses the
-- OLD UNPREFIXED table names (users, flats, ...). Then first run
-- the RENAME TABLE block at the very bottom of this file, and
-- only afterwards re-run this file.
-- ------------------------------------------------------------
SELECT TABLE_NAME FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('wsit_users','wsit_buildings','wsit_flats','wsit_tenants',
                     'wsit_leases','wsit_payments','wsit_invoices');

-- ------------------------------------------------------------
-- Helper procedures (idempotent add-column / add-key)
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS `rms_add_col`;
DELIMITER $$
CREATE PROCEDURE `rms_add_col`(IN tname VARCHAR(64), IN cname VARCHAR(64), IN cdef TEXT)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tname AND COLUMN_NAME = cname
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', tname, '` ADD COLUMN `', cname, '` ', cdef);
        PREPARE s FROM @ddl;
        EXECUTE s;
        DEALLOCATE PREPARE s;
    END IF;
END$$
DELIMITER ;

DROP PROCEDURE IF EXISTS `rms_add_key`;
DELIMITER $$
CREATE PROCEDURE `rms_add_key`(IN tname VARCHAR(64), IN kname VARCHAR(64), IN kdef TEXT)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tname AND INDEX_NAME = kname
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', tname, '` ADD ', kdef);
        PREPARE s FROM @ddl;
        EXECUTE s;
        DEALLOCATE PREPARE s;
    END IF;
END$$
DELIMITER ;

-- ------------------------------------------------------------
-- Buildings
-- ------------------------------------------------------------
CALL rms_add_col('wsit_buildings', 'holding_no', 'VARCHAR(50) DEFAULT NULL COMMENT ''City corporation / pourashava holding number''');

-- ------------------------------------------------------------
-- Flats
-- ------------------------------------------------------------
CALL rms_add_col('wsit_flats', 'shop_name', 'VARCHAR(150) DEFAULT NULL COMMENT ''Business name for shop units, shown on the shop rent receipt''');
CALL rms_add_col('wsit_flats', 'unit_type', 'ENUM(''flat'',''shop'') NOT NULL DEFAULT ''flat''');

-- ------------------------------------------------------------
-- Tenants
-- ------------------------------------------------------------
CALL rms_add_col('wsit_tenants', 'photo', 'VARCHAR(255) DEFAULT NULL');
CALL rms_add_col('wsit_tenants', 'deed_file', 'VARCHAR(255) DEFAULT NULL');
CALL rms_add_col('wsit_tenants', 'nid_file', 'VARCHAR(255) DEFAULT NULL');
CALL rms_add_col('wsit_tenants', 'created_by', 'INT(11) DEFAULT NULL COMMENT ''User that entered the tenant (landlords only see their own)''');
CALL rms_add_col('wsit_tenants', 'portal_username', 'VARCHAR(100) DEFAULT NULL');
CALL rms_add_col('wsit_tenants', 'portal_password', 'VARCHAR(255) DEFAULT NULL');
CALL rms_add_col('wsit_tenants', 'portal_enabled', 'TINYINT(1) NOT NULL DEFAULT 1');
CALL rms_add_col('wsit_tenants', 'portal_last_login', 'DATETIME DEFAULT NULL');
CALL rms_add_key('wsit_tenants', 'uq_portal_username', 'UNIQUE KEY `uq_portal_username` (`portal_username`)');

-- ------------------------------------------------------------
-- Users (roles: admin / landlord)
-- ------------------------------------------------------------
CALL rms_add_col('wsit_users', 'role', 'ENUM(''admin'',''landlord'') NOT NULL DEFAULT ''admin'' AFTER `full_name`');

-- Keep every existing account as admin (they had full access before
-- roles existed). Adjust individual accounts later from the Users page.
UPDATE `wsit_users` SET `role` = 'admin';

-- ------------------------------------------------------------
-- Payments (parking / gas / water / waste bill amounts)
-- ------------------------------------------------------------
CALL rms_add_col('wsit_payments', 'parking_amount', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
CALL rms_add_col('wsit_payments', 'gas_amount', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
CALL rms_add_col('wsit_payments', 'water_fee', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
CALL rms_add_col('wsit_payments', 'waste_fee', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
CALL rms_add_col('wsit_payments', 'service_charge_file', 'VARCHAR(255) DEFAULT NULL COMMENT ''Previous month service charge receipt scan - flat rent payments only''');

-- ------------------------------------------------------------
-- Invoices (bill amounts, arrears, status tracking)
-- ------------------------------------------------------------
CALL rms_add_col('wsit_invoices', 'parking_amount', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
CALL rms_add_col('wsit_invoices', 'gas_amount', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
CALL rms_add_col('wsit_invoices', 'water_fee', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
CALL rms_add_col('wsit_invoices', 'waste_fee', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
CALL rms_add_col('wsit_invoices', 'arrears', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
CALL rms_add_col('wsit_invoices', 'arrears_months', 'INT(11) NOT NULL DEFAULT 0');
CALL rms_add_col('wsit_invoices', 'payment_status_override', 'ENUM(''paid'',''partial'',''unpaid'') NULL DEFAULT NULL');
CALL rms_add_col('wsit_invoices', 'paid_at', 'DATETIME DEFAULT NULL');
CALL rms_add_col('wsit_invoices', 'is_manual', 'TINYINT(1) NOT NULL DEFAULT 0');
CALL rms_add_col('wsit_invoices', 'status', 'ENUM(''draft'',''sent'') NOT NULL DEFAULT ''draft''');

-- ------------------------------------------------------------
-- Tables that only exist in newer versions (safe no-op if present)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wsit_translation_cache` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `src` VARCHAR(255) NOT NULL,
  `lang_from` VARCHAR(5) NOT NULL,
  `lang_to` VARCHAR(5) NOT NULL,
  `result` TEXT NULL,
  `hits` INT(11) NOT NULL DEFAULT 0,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_src` (`src`, `lang_from`, `lang_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wsit_invoice_sends` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` INT(11) NOT NULL,
  `channel` ENUM('email','whatsapp','sms') NOT NULL,
  `recipient` VARCHAR(191) DEFAULT NULL,
  `status` ENUM('sent','failed') NOT NULL DEFAULT 'sent',
  `error` VARCHAR(255) DEFAULT NULL,
  `sent_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `invoice_id` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Cleanup (helper procedures are no longer needed)
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS `rms_add_col`;
DROP PROCEDURE IF EXISTS `rms_add_key`;

-- ============================================================
-- ONLY for the OLD non-prefixed database (users, flats, ...):
-- rename the tables first, then run the top part again.
-- Uncomment and run this block once, then re-run this whole file.
-- ============================================================
-- RENAME TABLE users TO wsit_users, buildings TO wsit_buildings,
--   flats TO wsit_flats, tenants TO wsit_tenants, leases TO wsit_leases,
--   payments TO wsit_payments, expenses TO wsit_expenses,
--   settings TO wsit_settings, translation_cache TO wsit_translation_cache,
--   invoices TO wsit_invoices, invoice_sends TO wsit_invoice_sends;