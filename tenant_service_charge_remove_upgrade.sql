-- ============================================================
-- tenant_service_charge_remove_upgrade.sql — ALTER-ONLY removal
-- ------------------------------------------------------------
-- Removes tenants.service_charge_file (the OLD per-tenant service
-- charge receipt upload from the Tenants page).
--
-- The service charge receipt is now attached per PAYMENT (see
-- payment_service_charge_upgrade.sql + db_alter_upgrade.sql), so
-- the tenants-level column and its upload UI have been removed.
--
-- Safe to run on any database:
--   * Drops the column ONLY when it exists (no error otherwise)
--   * Never touches payment data, other columns, or any other table
--   * Fully IDEMPOTENT — safe to run repeatedly
--
-- Note: dropping this column removes any old per-tenant scan path
-- that was stored in it (nothing references it after the UI/code
-- removal, and it was never used since payments carry the scan).
-- The uploaded scan FILES on disk are left untouched.
-- ============================================================

DROP PROCEDURE IF EXISTS `rms_drop_col_if_exists`;
DELIMITER $$
CREATE PROCEDURE `rms_drop_col_if_exists`(IN tname VARCHAR(64), IN cname VARCHAR(64))
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tname AND COLUMN_NAME = cname
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', tname, '` DROP COLUMN `', cname, '`');
        PREPARE s FROM @ddl;
        EXECUTE s;
        DEALLOCATE PREPARE s;
    END IF;
END$$
DELIMITER ;

CALL rms_drop_col_if_exists('wsit_tenants', 'service_charge_file');

DROP PROCEDURE IF EXISTS `rms_drop_col_if_exists`;