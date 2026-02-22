-- Migration: create_two_factor_codes.sql
-- Create table to store two-factor authentication codes (SMS/EMAIL)
-- Run this as a privileged DB user (e.g., via phpMyAdmin or mysql CLI)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `two_factor_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `code` varchar(10) NOT NULL,
  `type` enum('SMS','EMAIL') NOT NULL DEFAULT 'SMS',
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  -- Allow multiple codes per user/type; do not enforce uniqueness so late or retried OTPs can be stored
  KEY `idx_user_type` (`user_id`,`type`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Optional: cleanup procedure removed (create via admin tools or manually if needed)

SET FOREIGN_KEY_CHECKS = 1;

-- End of migration
