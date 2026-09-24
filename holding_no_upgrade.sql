-- ============================================================
-- Holding number upgrade (run once against an existing database)
--
-- Adds buildings.holding_no so a building can carry its city
-- corporation / pourashava holding number, which is printed on
-- the shop rent receipt beside the building name.
--
-- Safe to run on a fresh database too: the ALTER below is a
-- no-op when the column already exists (the error can be ignored).
-- ============================================================

ALTER TABLE `wsit_buildings`
  ADD COLUMN `holding_no` VARCHAR(50) DEFAULT NULL COMMENT 'City corporation / pourashava holding number' AFTER `name`;