-- Migration: alter_two_factor_codes.sql
-- Drop UNIQUE constraint on (user_id,type) and replace with non-unique index
-- Safe to run on MySQL. If the unique index doesn't exist, the DROP will error; run with care.

SET FOREIGN_KEY_CHECKS = 0;

-- Remove the unique index if it exists (safe on different MySQL/MariaDB versions)
SET @i = (
	SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
	WHERE TABLE_SCHEMA = DATABASE()
		AND TABLE_NAME = 'two_factor_codes'
		AND INDEX_NAME = 'user_type_unique'
);
SET @drop_sql = IF(@i > 0,
	'ALTER TABLE `two_factor_codes` DROP INDEX `user_type_unique`',
	'SELECT 0 as noop'
);
PREPARE stmt FROM @drop_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add a non-unique index for performance if it does not already exist
SET @j = (
	SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
	WHERE TABLE_SCHEMA = DATABASE()
		AND TABLE_NAME = 'two_factor_codes'
		AND INDEX_NAME = 'idx_user_type'
);
SET @add_sql = IF(@j = 0,
	'ALTER TABLE `two_factor_codes` ADD INDEX `idx_user_type` (`user_id`,`type`)',
	'SELECT 0 as noop'
);
PREPARE stmt2 FROM @add_sql;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

SET FOREIGN_KEY_CHECKS = 1;

-- End of migration
