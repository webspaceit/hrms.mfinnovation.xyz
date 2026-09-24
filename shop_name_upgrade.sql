-- ============================================================
-- Shop name upgrade (run once against an existing database)
--
-- Adds flats.shop_name so a shop unit can carry its business
-- name, which is printed on the shop rent receipt.
--
-- Safe to run on a fresh database too: the ALTER below is a
-- no-op when the column already exists (the error can be ignored).
-- ============================================================

ALTER TABLE `wsit_flats`
  ADD COLUMN `shop_name` VARCHAR(150) DEFAULT NULL AFTER `flat_no`;