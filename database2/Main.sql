SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";



--
-- 
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `CleanupExpired2FACodes` ()   BEGIN
    DELETE FROM two_factor_codes 
    WHERE expires_at < NOW() OR (used = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR));
END$$

DELIMITER ;


CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `fullname` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('Admin','userss') NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `phone_number` varchar(20) DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `phone_verified` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `signup`(
`user_id` int(11) NOT NULL,
  `fullname` varchar(150) NOT NULL,
  `emailadd` varchar(150) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE incidents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  case_no VARCHAR(30) UNIQUE,
  type ENUM('Theft','Assault','Domestic','Other'),
  status ENUM('Draft','Submitted','Under Review','Resolved','Closed'),
  location VARCHAR(255),
  incident_datetime DATETIME,
  narrative TEXT,
  created_by INT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE userss (
id INT AUTO_INCREMENT PRIMARY KEY,
full_name VARCHAR(150) NOT NULL,
role ENUM('Admin','Officer','Staff') DEFAULT 'Staff'
);

CREATE TABLE blotters (
id INT AUTO_INCREMENT PRIMARY KEY,
blotter_no VARCHAR(50) UNIQUE NOT NULL,
complainant_name VARCHAR(150) NOT NULL,
respondent_name VARCHAR(150) NOT NULL,
incident_type VARCHAR(100) NOT NULL,
incident_date DATE NOT NULL,
incident_time TIME,
location VARCHAR(255),
description TEXT NOT NULL,
status ENUM('Pending','Under Investigation','Resolved','Archived') DEFAULT 'Pending',
priority ENUM('High','Medium','Low') DEFAULT 'Medium',
officer_id INT,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
FOREIGN KEY (officer_id) REFERENCES users(id)
);

CREATE TABLE blotter_updates (
id INT AUTO_INCREMENT PRIMARY KEY,
blotter_id INT NOT NULL,
remarks TEXT NOT NULL,
status ENUM('Pending','Under Investigation','Resolved') NOT NULL,
updated_by INT,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (blotter_id) REFERENCES blotters(id),
FOREIGN KEY (updated_by) REFERENCES users(id)
);



