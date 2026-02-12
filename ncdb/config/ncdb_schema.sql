-- National Crime Database Integration Schema
-- This schema manages secure connections, caching, logging, and synchronization with national crime databases

-- Table for storing NCDB connection configurations
CREATE TABLE IF NOT EXISTS `ncdb_connections` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `connection_name` VARCHAR(255) NOT NULL,
    `api_endpoint` VARCHAR(500) NOT NULL,
    `api_key_encrypted` LONGTEXT,
    `api_secret_encrypted` LONGTEXT,
    `connection_type` ENUM('REST', 'SOAP', 'DATABASE', 'FILE') DEFAULT 'REST',
    `is_active` BOOLEAN DEFAULT TRUE,
    `last_tested_at` TIMESTAMP NULL,
    `test_status` ENUM('ACTIVE', 'INACTIVE', 'ERROR') DEFAULT 'INACTIVE',
    `test_error_message` TEXT,
    `timeout_seconds` INT DEFAULT 30,
    `retry_attempts` INT DEFAULT 3,
    `retry_delay_seconds` INT DEFAULT 5,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_by` INT,
    `updated_by` INT,
    UNIQUE KEY `connection_name` (`connection_name`),
    FOREIGN KEY (`created_by`) REFERENCES `signup` (`user_id`) ON DELETE SET NULL,
    FOREIGN KEY (`updated_by`) REFERENCES `signup` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for caching NCDB queries to reduce API calls
CREATE TABLE IF NOT EXISTS `ncdb_cache` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `query_hash` VARCHAR(64) NOT NULL,
    `query_type` VARCHAR(50) NOT NULL,
    `query_parameters` JSON,
    `cached_result` LONGTEXT,
    `result_count` INT,
    `expires_at` TIMESTAMP,
    `hit_count` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `query_hash` (`query_hash`),
    INDEX `expires_at` (`expires_at`),
    INDEX `query_type` (`query_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for duplicate detection records
CREATE TABLE IF NOT EXISTS `ncdb_duplicate_detection` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `local_record_id` INT NOT NULL,
    `local_record_type` ENUM('BLOTTER', 'CASE', 'SUSPECT', 'WITNESS') NOT NULL,
    `ncdb_match_id` VARCHAR(255),
    `match_score` DECIMAL(5,2),
    `matching_fields` JSON,
    `confidence_level` ENUM('LOW', 'MEDIUM', 'HIGH', 'EXACT') DEFAULT 'MEDIUM',
    `is_duplicate` BOOLEAN DEFAULT FALSE,
    `duplicate_action_taken` VARCHAR(500),
    `reviewed_by` INT,
    `reviewed_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `local_record` (`local_record_type`, `local_record_id`),
    INDEX `is_duplicate` (`is_duplicate`),
    FOREIGN KEY (`reviewed_by`) REFERENCES `signup` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for comprehensive access and security logging
CREATE TABLE IF NOT EXISTS `ncdb_access_logs` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `user_ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `action_type` ENUM('QUERY', 'VERIFY', 'SYNC', 'CACHE_HIT', 'CACHE_MISS', 'DUPLICATE_CHECK', 'EXPORT', 'IMPORT', 'CONFIG_CHANGE', 'TEST_CONNECTION') NOT NULL,
    `query_type` VARCHAR(50),
    `query_parameters_encrypted` LONGTEXT,
    `result_count` INT,
    `execution_time_ms` INT,
    `status` ENUM('SUCCESS', 'PARTIAL', 'FAILED', 'DENIED') DEFAULT 'SUCCESS',
    `error_message` TEXT,
    `data_accessed` JSON,
    `ip_geolocation` VARCHAR(255),
    `is_suspicious` BOOLEAN DEFAULT FALSE,
    `threat_level` ENUM('NONE', 'LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'NONE',
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `user_id` (`user_id`),
    INDEX `action_type` (`action_type`),
    INDEX `status` (`status`),
    INDEX `created_at` (`created_at`),
    INDEX `is_suspicious` (`is_suspicious`),
    FOREIGN KEY (`user_id`) REFERENCES `signup` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for NCDB synchronization history
CREATE TABLE IF NOT EXISTS `ncdb_sync_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `connection_id` INT NOT NULL,
    `sync_type` ENUM('FULL', 'INCREMENTAL', 'MANUAL', 'SCHEDULED') DEFAULT 'MANUAL',
    `sync_start_time` TIMESTAMP,
    `sync_end_time` TIMESTAMP,
    `records_processed` INT,
    `records_synced` INT,
    `records_skipped` INT,
    `duplicates_found` INT,
    `duplicates_merged` INT,
    `status` ENUM('PENDING', 'IN_PROGRESS', 'COMPLETED', 'FAILED', 'PARTIAL') DEFAULT 'PENDING',
    `error_log` LONGTEXT,
    `warning_log` LONGTEXT,
    `initiated_by` INT,
    `sync_details` JSON,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `status` (`status`),
    INDEX `connection_id` (`connection_id`),
    INDEX `created_at` (`created_at`),
    FOREIGN KEY (`connection_id`) REFERENCES `ncdb_connections` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`initiated_by`) REFERENCES `signup` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for storing NCDB verification results
CREATE TABLE IF NOT EXISTS `ncdb_verification_results` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `local_record_id` INT NOT NULL,
    `local_record_type` ENUM('BLOTTER', 'CASE', 'SUSPECT', 'WITNESS') NOT NULL,
    `verification_type` ENUM('IDENTITY', 'CRIMINAL_HISTORY', 'STATUS_CHECK', 'WARRANT') NOT NULL,
    `ncdb_status` VARCHAR(100),
    `ncdb_data` JSON,
    `verification_result` ENUM('VERIFIED', 'UNVERIFIED', 'FLAGGED', 'ERROR') DEFAULT 'UNVERIFIED',
    `risk_flags` JSON,
    `verified_by` INT,
    `verified_at` TIMESTAMP NULL,
    `is_current` BOOLEAN DEFAULT TRUE,
    `expires_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `local_record` (`local_record_type`, `local_record_id`),
    INDEX `verification_result` (`verification_result`),
    FOREIGN KEY (`verified_by`) REFERENCES `signup` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for NCDB API rate limiting and throttling
CREATE TABLE IF NOT EXISTS `ncdb_rate_limits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `connection_id` INT,
    `request_count` INT DEFAULT 1,
    `request_limit` INT DEFAULT 100,
    `window_start` TIMESTAMP,
    `window_duration_minutes` INT DEFAULT 60,
    `is_rate_limited` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `user_window` (`user_id`, `connection_id`, `window_start`),
    FOREIGN KEY (`user_id`) REFERENCES `signup` (`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`connection_id`) REFERENCES `ncdb_connections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create indexes for performance optimization
CREATE INDEX idx_ncdb_access_time_range ON ncdb_access_logs(created_at, user_id);
CREATE INDEX idx_ncdb_cache_expire_cleanup ON ncdb_cache(expires_at, query_type);
CREATE INDEX idx_ncdb_sync_progress ON ncdb_sync_history(status, sync_start_time);
CREATE INDEX idx_ncdb_duplicate_confidence ON ncdb_duplicate_detection(is_duplicate, confidence_level);
