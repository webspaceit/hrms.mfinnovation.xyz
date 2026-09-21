-- ============================================================
-- Rent Management System - Database Schema
-- English + Bengali Bilingual
-- ============================================================

CREATE DATABASE IF NOT EXISTS `oop_rms` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `oop_rms`;

-- ------------------------------------------------------------
-- Users (admins / owners)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Buildings
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `buildings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `total_flats` INT(11) NOT NULL DEFAULT 0,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Flats (units)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `flats` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `building_id` INT(11) NOT NULL,
  `unit_type` ENUM('flat','shop') NOT NULL DEFAULT 'flat',
  `flat_no` VARCHAR(50) NOT NULL,
  `floor` VARCHAR(50) DEFAULT NULL,
  `bedrooms` INT(11) NOT NULL DEFAULT 1,
  `bathrooms` INT(11) NOT NULL DEFAULT 1,
  `size_sqft` DECIMAL(10,2) DEFAULT NULL,
  `rent_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `advance_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `status` ENUM('available','occupied','maintenance') NOT NULL DEFAULT 'available',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `building_id` (`building_id`),
  CONSTRAINT `fk_flats_building` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tenants
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tenants` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `phone` VARCHAR(50) NOT NULL,
  `nid` VARCHAR(100) DEFAULT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `photo` VARCHAR(255) DEFAULT NULL,
  `deed_file` VARCHAR(255) DEFAULT NULL,
  `nid_file` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `portal_username` VARCHAR(100) DEFAULT NULL,
  `portal_password` VARCHAR(255) DEFAULT NULL,
  `portal_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `portal_last_login` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_portal_username` (`portal_username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Leases (rent agreements)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `leases` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` INT(11) NOT NULL,
  `flat_id` INT(11) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE DEFAULT NULL,
  `rent_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `advance_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `utility_fee` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `status` ENUM('active','expired','terminated') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `flat_id` (`flat_id`),
  CONSTRAINT `fk_leases_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_leases_flat` FOREIGN KEY (`flat_id`) REFERENCES `flats` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Payments (rent collections)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `lease_id` INT(11) NOT NULL,
  `tenant_id` INT(11) NOT NULL,
  `flat_id` INT(11) NOT NULL,
  `month` TINYINT(4) NOT NULL,
  `year` INT(11) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `utility_fee` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `parking_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `gas_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `water_fee` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `waste_fee` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `payment_method` ENUM('cash','bank','bKash','nogod','rocket','other') NOT NULL DEFAULT 'cash',
  `received_by` INT(11) DEFAULT NULL,
  `payment_date` DATE NOT NULL,
  `note` VARCHAR(255) DEFAULT NULL,
  `signature` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `lease_id` (`lease_id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `flat_id` (`flat_id`),
  CONSTRAINT `fk_payments_lease` FOREIGN KEY (`lease_id`) REFERENCES `leases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_flat` FOREIGN KEY (`flat_id`) REFERENCES `flats` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Expenses
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `expenses` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `category` VARCHAR(150) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `expense_date` DATE NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Settings (site config + language)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(50) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Translation cache (MyMemory bn<->en lookups for names)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `translation_cache` (
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

-- ------------------------------------------------------------
-- Invoices (monthly due / demand notes)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `invoices` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `lease_id` INT(11) NOT NULL,
  `tenant_id` INT(11) NOT NULL,
  `flat_id` INT(11) NOT NULL,
  `month` TINYINT(4) NOT NULL,
  `year` INT(11) NOT NULL,
  `rent_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `utility_fee` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `parking_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `gas_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `water_fee` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `waste_fee` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `arrears` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `arrears_months` INT(11) NOT NULL DEFAULT 0,
  `total_due` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `payment_status` ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `payment_status_override` ENUM('paid','partial','unpaid') NULL DEFAULT NULL,
  `paid_at` DATETIME DEFAULT NULL,
  `note` VARCHAR(255) DEFAULT NULL,
  `is_manual` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('draft','sent') NOT NULL DEFAULT 'draft',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lease_month` (`lease_id`,`month`,`year`),
  KEY `tenant_id` (`tenant_id`),
  KEY `flat_id` (`flat_id`),
  CONSTRAINT `fk_invoices_lease` FOREIGN KEY (`lease_id`) REFERENCES `leases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_invoices_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_invoices_flat` FOREIGN KEY (`flat_id`) REFERENCES `flats` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Invoice delivery log (email / whatsapp / sms)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `invoice_sends` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` INT(11) NOT NULL,
  `channel` ENUM('email','whatsapp','sms') NOT NULL,
  `recipient` VARCHAR(191) DEFAULT NULL,
  `status` ENUM('sent','failed') NOT NULL DEFAULT 'sent',
  `error` VARCHAR(255) DEFAULT NULL,
  `sent_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `invoice_id` (`invoice_id`),
  CONSTRAINT `fk_inv_sends_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Default Data
-- ------------------------------------------------------------
-- Default admin: username: admin / password: admin123
INSERT INTO `users` (`username`, `email`, `password`, `full_name`) VALUES
('admin', 'admin@rms.com', '$2y$12$dJt.B1F9r5LwGbXhleipQevSwPtJtCBkOnDliavhtwhh5Wimw0aN6', 'System Admin')
ON DUPLICATE KEY UPDATE `username` = `username`;

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_name', 'Rent Management System'),
('language', 'en'),
('currency', '৳')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

-- ------------------------------------------------------------
-- Sample Buildings
-- ------------------------------------------------------------
INSERT INTO `buildings` (`name`, `address`, `total_flats`, `description`) VALUES
('Green View Tower', '45/A, Road 5, Banani, Dhaka 1213', 12, '12-storey residential building'),
('Rahman Plaza', '78, Gulshan Avenue, Gulshan-1, Dhaka 1212', 8, '8-storey mixed-use building'),
('Friendship Center', '12, Mirpur Road, Mirpur-10, Dhaka 1216', 16, '16-storey commercial complex')
ON DUPLICATE KEY UPDATE `name` = `name`;

-- ------------------------------------------------------------
-- Sample Flats (Green View Tower - Building ID 1)
-- ------------------------------------------------------------
INSERT INTO `flats` (`building_id`, `unit_type`, `flat_no`, `floor`, `bedrooms`, `bathrooms`, `size_sqft`, `rent_amount`, `advance_amount`, `status`) VALUES
(1, 'flat', '1A', '1st', 2, 1, 950, 15000.00, 30000.00, 'occupied'),
(1, 'flat', '1B', '1st', 1, 1, 650, 10000.00, 20000.00, 'available'),
(1, 'flat', '2A', '2nd', 3, 2, 1250, 22000.00, 44000.00, 'occupied'),
(1, 'flat', '2B', '2nd', 2, 1, 900, 14000.00, 28000.00, 'available'),
(1, 'shop', 'GF-1', 'Ground', 1, 1, 350, 18000.00, 36000.00, 'occupied'),
(1, 'shop', 'GF-2', 'Ground', 1, 1, 400, 20000.00, 40000.00, 'occupied'),
(1, 'flat', '3A', '3rd', 3, 2, 1300, 24000.00, 48000.00, 'available'),
(1, 'flat', '3B', '3rd', 2, 1, 950, 15000.00, 30000.00, 'occupied'),
(1, 'flat', '4A', '4th', 2, 2, 1100, 18000.00, 36000.00, 'occupied'),
(1, 'flat', '4B', '4th', 1, 1, 700, 11000.00, 22000.00, 'available'),
(1, 'flat', '5A', '5th', 3, 2, 1350, 25000.00, 50000.00, 'occupied'),
(1, 'flat', '5B', '5th', 2, 1, 950, 15000.00, 30000.00, 'maintenance')
ON DUPLICATE KEY UPDATE `flat_no` = `flat_no`;

-- ------------------------------------------------------------
-- Sample Flats (Rahman Plaza - Building ID 2)
-- ------------------------------------------------------------
INSERT INTO `flats` (`building_id`, `unit_type`, `flat_no`, `floor`, `bedrooms`, `bathrooms`, `size_sqft`, `rent_amount`, `advance_amount`, `status`) VALUES
(2, 'flat', '1A', '1st', 2, 1, 900, 14000.00, 28000.00, 'occupied'),
(2, 'flat', '1B', '1st', 1, 1, 600, 9000.00, 18000.00, 'available'),
(2, 'flat', '2A', '2nd', 3, 2, 1200, 20000.00, 40000.00, 'occupied'),
(2, 'flat', '2B', '2nd', 2, 1, 850, 13000.00, 26000.00, 'occupied'),
(2, 'shop', 'GF-1', 'Ground', 1, 1, 500, 25000.00, 50000.00, 'occupied'),
(2, 'shop', 'GF-2', 'Ground', 1, 1, 450, 22000.00, 44000.00, 'available'),
(2, 'flat', '3A', '3rd', 3, 2, 1250, 21000.00, 42000.00, 'available'),
(2, 'flat', '3B', '3rd', 2, 1, 900, 14000.00, 28000.00, 'occupied')
ON DUPLICATE KEY UPDATE `flat_no` = `flat_no`;

-- ------------------------------------------------------------
-- Sample Flats (Friendship Center - Building ID 3)
-- ------------------------------------------------------------
INSERT INTO `flats` (`building_id`, `unit_type`, `flat_no`, `floor`, `bedrooms`, `bathrooms`, `size_sqft`, `rent_amount`, `advance_amount`, `status`) VALUES
(3, 'shop', 'GF-1', 'Ground', 1, 1, 600, 35000.00, 70000.00, 'occupied'),
(3, 'shop', 'GF-2', 'Ground', 1, 1, 550, 30000.00, 60000.00, 'occupied'),
(3, 'shop', '1F-1', '1st', 1, 1, 480, 28000.00, 56000.00, 'available'),
(3, 'shop', '1F-2', '1st', 1, 1, 500, 30000.00, 60000.00, 'occupied'),
(3, 'shop', '2F-1', '2nd', 1, 1, 520, 29000.00, 58000.00, 'occupied'),
(3, 'shop', '2F-2', '2nd', 1, 1, 500, 28000.00, 56000.00, 'available'),
(3, 'flat', '3A', '3rd', 2, 2, 1000, 16000.00, 32000.00, 'occupied'),
(3, 'flat', '3B', '3rd', 2, 1, 850, 13000.00, 26000.00, 'available'),
(3, 'flat', '4A', '4th', 3, 2, 1300, 22000.00, 44000.00, 'occupied'),
(3, 'flat', '4B', '4th', 2, 2, 1100, 18000.00, 36000.00, 'available'),
(3, 'flat', '5A', '5th', 2, 1, 900, 14000.00, 28000.00, 'occupied'),
(3, 'flat', '5B', '5th', 3, 2, 1400, 24000.00, 48000.00, 'available'),
(3, 'flat', '6A', '6th', 2, 2, 1150, 19000.00, 38000.00, 'occupied'),
(3, 'flat', '6B', '6th', 1, 1, 700, 11000.00, 22000.00, 'maintenance'),
(3, 'flat', '7A', '7th', 3, 2, 1350, 23000.00, 46000.00, 'available'),
(3, 'flat', '7B', '7th', 2, 1, 950, 15000.00, 30000.00, 'occupied')
ON DUPLICATE KEY UPDATE `flat_no` = `flat_no`;

-- ------------------------------------------------------------
-- Sample Tenants
-- ------------------------------------------------------------
INSERT INTO `tenants` (`name`, `email`, `phone`, `nid`, `address`) VALUES
('Kamal Hossain', 'kamal@gmail.com', '01712345678', '19901234567890123', 'Banani, Dhaka'),
('Fatima Rahman', 'fatima@gmail.com', '01812345679', '19851234567890124', 'Gulshan, Dhaka'),
('Abdul Karim', 'abdul@gmail.com', '01912345680', '19881234567890125', 'Mirpur, Dhaka'),
('Nusrat Jahan', 'nusrat@gmail.com', '01612345681', '19921234567890126', 'Dhanmondi, Dhaka'),
('Rahim Uddin', 'rahim@gmail.com', '01512345682', '19871234567890127', 'Uttara, Dhaka')
ON DUPLICATE KEY UPDATE `name` = `name`;

-- ------------------------------------------------------------
-- Sample Leases
-- ------------------------------------------------------------
INSERT INTO `leases` (`tenant_id`, `flat_id`, `start_date`, `end_date`, `rent_amount`, `advance_amount`, `utility_fee`, `status`) VALUES
(1, 1, '2026-01-01', NULL, 15000.00, 30000.00, 500.00, 'active'),
(2, 3, '2026-01-01', NULL, 22000.00, 44000.00, 800.00, 'active'),
(3, 5, '2026-02-01', NULL, 18000.00, 36000.00, 600.00, 'active'),
(4, 13, '2026-03-01', NULL, 20000.00, 40000.00, 700.00, 'active'),
(5, 14, '2026-02-01', NULL, 13000.00, 26000.00, 400.00, 'active')
ON DUPLICATE KEY UPDATE `tenant_id` = `tenant_id`;

-- ------------------------------------------------------------
-- Sample Payments
-- ------------------------------------------------------------
INSERT INTO `payments` (`lease_id`, `tenant_id`, `flat_id`, `month`, `year`, `amount`, `utility_fee`, `total_amount`, `payment_method`, `received_by`, `payment_date`, `note`) VALUES
(1, 1, 1, 1, 2026, 15000.00, 500.00, 15500.00, 'cash', 1, '2026-01-05', 'January rent'),
(1, 1, 1, 2, 2026, 15000.00, 500.00, 15500.00, 'bKash', 1, '2026-02-03', 'February rent'),
(2, 2, 3, 1, 2026, 22000.00, 800.00, 22800.00, 'cash', 1, '2026-01-06', 'January rent'),
(2, 2, 3, 2, 2026, 22000.00, 800.00, 22800.00, 'bank', 1, '2026-02-05', 'February rent'),
(3, 3, 5, 2, 2026, 18000.00, 600.00, 18600.00, 'cash', 1, '2026-02-07', 'February rent'),
(4, 4, 13, 3, 2026, 20000.00, 700.00, 20700.00, 'bKash', 1, '2026-03-04', 'March rent')
ON DUPLICATE KEY UPDATE `month` = `month`;
