-- Migration: add_signup_sex.sql
-- Adds `sex` column to `signup` table if missing

-- Prefer using ALTER TABLE ... ADD COLUMN IF NOT EXISTS for compatibility
ALTER TABLE `signup` ADD COLUMN IF NOT EXISTS `sex` VARCHAR(20) DEFAULT NULL;

-- Done
SELECT 'Migration complete: add_signup_sex' AS message;
