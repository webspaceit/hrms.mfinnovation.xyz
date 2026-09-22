-- ============================================================
-- User roles upgrade (admin / landlord)
-- Run ONCE on an already installed database (phpMyAdmin ->
-- select the database -> Import). Fresh installs do not need it:
-- database.sql already includes the `role` column.
--
-- Safe to re-run: a "Duplicate column name" message simply means
-- the column already exists.
-- ============================================================

ALTER TABLE `wsit_users`
  ADD COLUMN `role` ENUM('admin','landlord') NOT NULL DEFAULT 'admin' AFTER `full_name`;

-- Keep every existing account as admin (they had full access
-- before roles existed). Adjust individual accounts later from
-- the Users page.
UPDATE `wsit_users` SET `role` = 'admin';