-- Attachments System Tables
-- Created for Law Enforcement Incident Report System

-- Drop existing table if it exists with wrong foreign key
DROP TABLE IF EXISTS `attachments`;

-- Table for file attachments
CREATE TABLE `attachments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `entity_type` ENUM('incident', 'blotter', 'case') NOT NULL,
  `entity_id` INT NOT NULL,
  `original_filename` VARCHAR(255) NOT NULL,
  `stored_filename` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_type` VARCHAR(100) NOT NULL,
  `file_size` INT NOT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `uploaded_by` INT NOT NULL,
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `is_deleted` BOOLEAN DEFAULT FALSE,
  INDEX `idx_entity` (`entity_type`, `entity_id`),
  FOREIGN KEY (`uploaded_by`) REFERENCES `signup`(`user_id`)
);

-- Create upload directory structure
-- This will be handled by PHP code