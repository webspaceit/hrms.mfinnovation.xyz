-- ============================================================
-- Payment service-charge receipt upgrade (run once on an already
-- imported database: phpMyAdmin -> select the DB -> SQL tab -> paste)
--
-- Lets each FLAT rent payment carry its own scan of the PREVIOUS
-- MONTH's service charge receipt, uploaded from the Collect
-- Payment form. Shops are not affected.
--
-- Safe to re-run: a "Duplicate column name" message simply means
-- the column already exists.
-- ============================================================

ALTER TABLE `wsit_payments`
  ADD COLUMN `service_charge_file` VARCHAR(255) DEFAULT NULL
  COMMENT 'Previous month service charge receipt scan - flat rent payments only'
  AFTER `signature`;