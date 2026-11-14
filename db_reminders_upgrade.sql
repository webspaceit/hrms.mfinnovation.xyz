-- ============================================================
-- Due-date reminders + browser push (upgrade for existing installs)
--
--   mysql -u root -p oop_rms < db_reminders_upgrade.sql
--
-- Safe to run more than once: every statement is IF NOT EXISTS /
-- INSERT IGNORE. Fresh installs get the same tables from database.sql.
-- Table names already carry the wsit_ prefix.
-- ============================================================

-- ------------------------------------------------------------
-- Browser push endpoints (one tenant can have several devices)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wsit_push_subscriptions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` INT(11) NOT NULL,
  `endpoint` VARCHAR(500) NOT NULL,
  `p256dh` VARCHAR(191) NOT NULL,
  `auth` VARCHAR(64) NOT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `last_success_at` DATETIME DEFAULT NULL,
  `last_error` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_push_endpoint` (`endpoint`(190)),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_push_sub_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `wsit_tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Reminder log - the UNIQUE key is what stops double sends:
-- one reminder per invoice + stage + channel, ever.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wsit_invoice_reminders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` INT(11) NOT NULL,
  `stage` VARCHAR(16) NOT NULL COMMENT 'before | due | overdue_1 | overdue_2 ...',
  `channel` VARCHAR(16) NOT NULL COMMENT 'push | whatsapp | email | sms',
  `recipient` VARCHAR(191) DEFAULT NULL,
  `status` ENUM('sent','failed') NOT NULL DEFAULT 'sent',
  `error` VARCHAR(255) DEFAULT NULL,
  `sent_on` DATE NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reminder_stage` (`invoice_id`,`stage`,`channel`),
  KEY `sent_on` (`sent_on`),
  CONSTRAINT `fk_reminder_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `wsit_invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Defaults (off until an admin turns them on in Settings)
-- ------------------------------------------------------------
INSERT IGNORE INTO `wsit_settings` (`setting_key`, `setting_value`) VALUES
  ('reminder_enabled',    '0'),
  ('reminder_days_before','3'),
  ('reminder_repeat_days','7'),
  ('reminder_max',        '3'),
  ('reminder_channels',   'push,whatsapp,email');
