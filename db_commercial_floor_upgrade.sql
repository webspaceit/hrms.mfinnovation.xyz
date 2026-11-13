-- Commercial Floor schema change for an existing RMS database.
-- Run this file by itself in phpMyAdmin after selecting the RMS database.
-- It does not use stored procedures or change existing unit records.
ALTER TABLE `wsit_flats`
  MODIFY COLUMN `unit_type` ENUM('flat','shop','commercial_floor') NOT NULL DEFAULT 'flat';
