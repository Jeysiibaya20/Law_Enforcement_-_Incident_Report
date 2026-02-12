-- Case Management System Tables
-- Created for Law Enforcement Incident Report System

-- Table for case assignments
CREATE TABLE `case_assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `case_number` VARCHAR(50) UNIQUE NOT NULL,
  `incident_type` VARCHAR(100) NOT NULL,
  `complainant_name` VARCHAR(150) NOT NULL,
  `respondent_name` VARCHAR(150),
  `location` VARCHAR(255),
  `incident_date` DATE NOT NULL,
  `incident_time` TIME,
  `description` TEXT NOT NULL,
  `priority` ENUM('High', 'Medium', 'Low') DEFAULT 'Medium',
  `status` ENUM('New', 'Ongoing', 'Resolved', 'Closed') DEFAULT 'New',
  `assigned_by` INT NOT NULL,
  `assigned_to` INT,
  `barangay_chairperson_id` INT,
  `assignment_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`assigned_by`) REFERENCES `users`(`user_id`),
  FOREIGN KEY (`assigned_to`) REFERENCES `users`(`user_id`),
  FOREIGN KEY (`barangay_chairperson_id`) REFERENCES `users`(`user_id`)
);

-- Table for BCPC officers
CREATE TABLE `bcpc_officers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNIQUE NOT NULL,
  `barangay` VARCHAR(100) NOT NULL,
  `rank` VARCHAR(50) NOT NULL,
  `specialization` VARCHAR(100),
  `contact_number` VARCHAR(20),
  `is_available` BOOLEAN DEFAULT TRUE,
  `current_case_load` INT DEFAULT 0,
  `max_case_load` INT DEFAULT 10,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`)
);

-- Table for case status updates/follow-ups
CREATE TABLE `case_updates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `case_id` INT NOT NULL,
  `case_number` VARCHAR(50) NOT NULL,
  `update_type` ENUM('Status Change', 'Follow-up Action', 'Note', 'Reassignment') NOT NULL,
  `previous_status` VARCHAR(20),
  `new_status` VARCHAR(20),
  `action_description` TEXT NOT NULL,
  `updated_by` INT NOT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`case_id`) REFERENCES `case_assignments`(`id`),
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`user_id`)
);

-- Table for notifications
CREATE TABLE `case_notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `recipient_id` INT NOT NULL,
  `case_id` INT NOT NULL,
  `case_number` VARCHAR(50) NOT NULL,
  `notification_type` ENUM('New Assignment', 'Status Update', 'Reassignment', 'Follow-up Required') NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`recipient_id`) REFERENCES `users`(`user_id`),
  FOREIGN KEY (`case_id`) REFERENCES `case_assignments`(`id`)
);

-- Table for case timeline
CREATE TABLE `case_timeline` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `case_id` INT NOT NULL,
  `case_number` VARCHAR(50) NOT NULL,
  `event_type` ENUM('Case Created', 'Assigned', 'Status Changed', 'Follow-up', 'Reassigned', 'Resolved', 'Closed') NOT NULL,
  `event_description` TEXT NOT NULL,
  `performed_by` INT,
  `event_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`case_id`) REFERENCES `case_assignments`(`id`),
  FOREIGN KEY (`performed_by`) REFERENCES `users`(`user_id`)
);

-- Insert sample BCPC officers
INSERT INTO `bcpc_officers` (`user_id`, `barangay`, `rank`, `specialization`, `contact_number`) VALUES
(1, 'Barangay 1', 'Senior Officer', 'Domestic Violence', '09123456789'),
(2, 'Barangay 2', 'Officer', 'Theft', '09123456790'),
(3, 'Barangay 3', 'Junior Officer', 'Assault', '09123456791'),
(4, 'Barangay 4', 'Senior Officer', 'Community Relations', '09123456792'),
(5, 'Barangay 5', 'Officer', 'Traffic', '09123456793');

-- Create indexes for better performance
CREATE INDEX `idx_case_assignments_status` ON `case_assignments`(`status`);
CREATE INDEX `idx_case_assignments_assigned_to` ON `case_assignments`(`assigned_to`);
CREATE INDEX `idx_case_updates_case_id` ON `case_updates`(`case_id`);
CREATE INDEX `idx_notifications_recipient` ON `case_notifications`(`recipient_id`);
CREATE INDEX `idx_timeline_case_id` ON `case_timeline`(`case_id`);
