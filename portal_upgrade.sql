-- ============================================================
-- Tenant Portal upgrade
-- Run this ONCE on an already installed database (phpMyAdmin ->
-- select the database -> Import). Fresh installs do not need it:
-- database.sql already contains these columns.
--
-- Safe to re-run: "Duplicate column name" / "Duplicate key name"
-- messages simply mean the column/index already exists.
-- ============================================================

ALTER TABLE `wsit_tenants`
  ADD COLUMN `portal_username` VARCHAR(100) DEFAULT NULL AFTER `status`,
  ADD COLUMN `portal_password` VARCHAR(255) DEFAULT NULL AFTER `portal_username`,
  ADD COLUMN `portal_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `portal_password`,
  ADD COLUMN `portal_last_login` DATETIME DEFAULT NULL AFTER `portal_enabled`;

ALTER TABLE `wsit_tenants`
  ADD UNIQUE KEY `uq_portal_username` (`portal_username`);
