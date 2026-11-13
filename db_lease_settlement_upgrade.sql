-- Lease-end settlement tables for an existing RMS database.
-- Select the RMS database in phpMyAdmin and import this file by itself.
-- Uses CREATE TABLE IF NOT EXISTS; no stored procedures are required.

-- Keep the commercial floor type enabled on older databases too.
ALTER TABLE `wsit_flats`
  MODIFY COLUMN `unit_type` ENUM('flat','shop','commercial_floor') NOT NULL DEFAULT 'flat';

CREATE TABLE IF NOT EXISTS `wsit_lease_settlements` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `lease_id` INT(11) NOT NULL,
  `tenant_id` INT(11) NOT NULL,
  `flat_id` INT(11) NOT NULL,
  `end_date` DATE NOT NULL,
  `monthly_rent` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `prorated_rent` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `days_occupied` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `days_in_month` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `eligible_due` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `payment_credit` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `advance_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `advance_applied` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `refund_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `balance_due` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `settled_by` INT(11) DEFAULT NULL,
  `settled_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lease_settlement_lease` (`lease_id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `flat_id` (`flat_id`),
  CONSTRAINT `fk_lease_settlements_lease` FOREIGN KEY (`lease_id`) REFERENCES `wsit_leases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lease_settlements_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `wsit_tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lease_settlements_flat` FOREIGN KEY (`flat_id`) REFERENCES `wsit_flats` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wsit_lease_settlement_allocations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `settlement_id` INT(11) NOT NULL,
  `invoice_id` INT(11) NOT NULL,
  `lease_id` INT(11) NOT NULL,
  `month` TINYINT(4) NOT NULL,
  `year` INT(11) NOT NULL,
  `source` ENUM('payment_credit','advance') NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settlement_invoice_source` (`settlement_id`,`invoice_id`,`source`),
  KEY `invoice_id` (`invoice_id`),
  KEY `lease_month` (`lease_id`,`year`,`month`),
  CONSTRAINT `fk_settlement_allocations_settlement` FOREIGN KEY (`settlement_id`) REFERENCES `wsit_lease_settlements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_settlement_allocations_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `wsit_invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_settlement_allocations_lease` FOREIGN KEY (`lease_id`) REFERENCES `wsit_leases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
