-- Create report_distributions table for automated report tracking
CREATE TABLE IF NOT EXISTS `report_distributions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_type` varchar(100) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `recipients` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`recipients`)),
  `report_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`report_data`)),
  `distributed_at` datetime NOT NULL,
  `status` enum('sent','failed','pending') DEFAULT 'sent',
  PRIMARY KEY (`id`),
  KEY `report_type` (`report_type`),
  KEY `distributed_at` (`distributed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create automated_reports_schedule table for scheduling configuration
CREATE TABLE IF NOT EXISTS `automated_reports_schedule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_type` varchar(100) NOT NULL,
  `enabled` tinyint(1) DEFAULT 1,
  `frequency` enum('daily','weekly','monthly','quarterly') NOT NULL,
  `schedule_time` time DEFAULT NULL,
  `schedule_day` varchar(20) DEFAULT NULL,
  `schedule_date` int(11) DEFAULT NULL,
  `recipients` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`recipients`)),
  `last_run` datetime DEFAULT NULL,
  `next_run` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `report_type` (`report_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default schedule configurations
INSERT IGNORE INTO `automated_reports_schedule` (`report_type`, `enabled`, `frequency`, `schedule_time`, `schedule_day`, `schedule_date`, `recipients`) VALUES
('daily_incident_summary', 1, 'daily', '08:00:00', NULL, NULL, '["admin@example.com"]'),
('weekly_analytics_report', 1, 'weekly', '09:00:00', 'monday', NULL, '["admin@example.com", "supervisor@example.com"]'),
('monthly_case_analysis', 1, 'monthly', '10:00:00', NULL, 1, '["admin@example.com", "management@example.com"]'),
('quarterly_decision_report', 1, 'quarterly', '11:00:00', NULL, NULL, '["admin@example.com", "board@example.com"]');