-- Migration: Add missing blotter columns and notification blotter_id
-- Run this in phpMyAdmin or with the mysql CLI

-- Use DATABASE() so this is portable when running directly in phpMyAdmin
SET @db = DATABASE();

-- Add columns to `blotters` only when they do not already exist
SET @s = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE table_schema = @db AND table_name = 'blotters' AND column_name = 'respondent_contact') = 0,
    'ALTER TABLE `blotters` ADD COLUMN `respondent_contact` VARCHAR(50) DEFAULT NULL',
    'SELECT "respondent_contact exists"'
  )
);
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE table_schema = @db AND table_name = 'blotters' AND column_name = 'respondent_email') = 0,
    'ALTER TABLE `blotters` ADD COLUMN `respondent_email` VARCHAR(150) DEFAULT NULL',
    'SELECT "respondent_email exists"'
  )
);
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE table_schema = @db AND table_name = 'blotters' AND column_name = 'respondent_address') = 0,
    'ALTER TABLE `blotters` ADD COLUMN `respondent_address` VARCHAR(255) DEFAULT NULL',
    'SELECT "respondent_address exists"'
  )
);
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE table_schema = @db AND table_name = 'blotters' AND column_name = 'hearing_date') = 0,
    'ALTER TABLE `blotters` ADD COLUMN `hearing_date` DATE NULL',
    'SELECT "hearing_date exists"'
  )
);
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE table_schema = @db AND table_name = 'blotters' AND column_name = 'hearing_time') = 0,
    'ALTER TABLE `blotters` ADD COLUMN `hearing_time` TIME NULL',
    'SELECT "hearing_time exists"'
  )
);
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE table_schema = @db AND table_name = 'blotters' AND column_name = 'hearing_location') = 0,
    'ALTER TABLE `blotters` ADD COLUMN `hearing_location` VARCHAR(255) DEFAULT NULL',
    'SELECT "hearing_location exists"'
  )
);
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add blotter_id to notifications table if missing
SET @s = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE table_schema = @db AND table_name = 'notifications' AND column_name = 'blotter_id') = 0,
    'ALTER TABLE `notifications` ADD COLUMN `blotter_id` INT NULL',
    'SELECT "notifications.blotter_id exists"'
  )
);
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Done
SELECT 'Migration complete' AS message;


