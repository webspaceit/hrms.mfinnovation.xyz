-- ============================================================
-- Tenant ownership upgrade (run once against an existing database)
--
-- Adds tenants.created_by so a landlord only sees the tenants they
-- entered. Admins always see every tenant.
--
-- Safe to run on a fresh database too: the ALTER below is a no-op
-- when the column already exists (the error can be ignored).
-- ============================================================

ALTER TABLE `wsit_tenants`
  ADD COLUMN `created_by` INT(11) DEFAULT NULL AFTER `status`;

-- Optional: assign every EXISTING tenant to one owner so nothing is hidden.
-- Pick the admin (or any user) and run the UPDATE. Rows left with a NULL
-- created_by stay visible to admins only until reassigned on the Tenants page.
-- UPDATE `wsit_tenants` SET `created_by` = (SELECT id FROM `wsit_users` LIMIT 1);