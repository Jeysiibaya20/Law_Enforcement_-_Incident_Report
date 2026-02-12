-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 20, 2026 at 03:23 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `law&inci`
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

-- --------------------------------------------------------

--
-- Table structure for table `attachments`
--

CREATE TABLE `attachments` (
  `id` int(11) NOT NULL,
  `entity_type` enum('incident','blotter','case') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `stored_filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `file_size` int(11) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attachments`
--

INSERT INTO `attachments` (`id`, `entity_type`, `entity_id`, `original_filename`, `stored_filename`, `file_path`, `file_type`, `file_size`, `mime_type`, `description`, `uploaded_by`, `uploaded_at`, `is_deleted`) VALUES
(1, 'blotter', 84, 'WEEK 1 LMS ACTIVITY BPM.docx', '696e8e811ceed_1768853121.docx', 'C:\\xampp\\htdocs\\Law_Enforcement_-_Incident_Report\\includes/../uploads/blotters/696e8e811ceed_1768853121.docx', 'docx', 333825, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', '', 130, '2026-01-19 20:05:21', 0);

-- --------------------------------------------------------

--
-- Table structure for table `barangay_officials`
--

CREATE TABLE `barangay_officials` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `barangay_name` varchar(150) NOT NULL,
  `position` varchar(100) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `barangay_officials`
--

INSERT INTO `barangay_officials` (`id`, `user_id`, `barangay_name`, `position`, `contact_number`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 138, 'Culiat', 'Barangay Treasurer', '123213', 1, '2026-01-06 19:37:34', '2026-01-06 19:37:34');

-- --------------------------------------------------------

--
-- Table structure for table `bcpc_officers`
--

CREATE TABLE `bcpc_officers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `barangay` varchar(100) NOT NULL,
  `rank` varchar(50) NOT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `current_case_load` int(11) DEFAULT 0,
  `max_case_load` int(11) DEFAULT 10,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bcpc_officers`
--

INSERT INTO `bcpc_officers` (`id`, `user_id`, `barangay`, `rank`, `specialization`, `contact_number`, `is_available`, `current_case_load`, `max_case_load`, `created_at`, `updated_at`) VALUES
(1, 136, 'Barangay 1', 'Senior Officer', 'Domestic Violence', '09123456789', 1, 1, 10, '2026-01-06 19:13:40', '2026-01-06 19:38:14'),
(2, 135, 'Barangay 2', 'Officer', 'Theft', '09123456789', 1, 1, 10, '2026-01-06 19:13:40', '2026-01-06 19:50:05'),
(3, 134, 'Barangay 3', 'Junior Officer', 'Assault', '09123456789', 1, 1, 10, '2026-01-06 19:13:40', '2026-01-06 19:50:05'),
(4, 133, 'Barangay 4', 'Senior Officer', 'Community Relations', '09123456789', 1, 0, 10, '2026-01-06 19:13:40', '2026-01-06 19:13:40'),
(5, 132, 'Barangay 5', 'Officer', 'Traffic', '09123456789', 1, 0, 10, '2026-01-06 19:13:40', '2026-01-06 19:13:40');

-- --------------------------------------------------------

--
-- Table structure for table `blotters`
--

CREATE TABLE `blotters` (
  `id` int(11) NOT NULL,
  `blotter_no` varchar(50) DEFAULT NULL,
  `complainant_name` varchar(150) DEFAULT NULL,
  `respondent_name` varchar(150) DEFAULT NULL,
  `incident_type` varchar(100) DEFAULT NULL,
  `incident_date` date NOT NULL,
  `incident_time` time NOT NULL,
  `location` varchar(150) DEFAULT NULL,
  `description` text NOT NULL,
  `status` enum('Pending','Under Investigation','Resolved','Archived') NOT NULL,
  `priority` enum('High','Medium','Low') NOT NULL,
  `officer_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `incident_id` int(11) DEFAULT NULL COMMENT 'Reference to incident',
  `created_from_incident` tinyint(1) DEFAULT 0,
  `nlp_threat_level` varchar(50) DEFAULT NULL,
  `nlp_severity_score` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `blotters`
--

INSERT INTO `blotters` (`id`, `blotter_no`, `complainant_name`, `respondent_name`, `incident_type`, `incident_date`, `incident_time`, `location`, `description`, `status`, `priority`, `officer_id`, `created_by`, `created_at`, `updated_at`, `incident_id`, `created_from_incident`, `nlp_threat_level`, `nlp_severity_score`) VALUES
(1, 'BLT1767622213563', 'jeys', 'ewan', 'theft', '2026-01-05', '22:10:00', 'Quezon City', 'hayts', 'Archived', 'Medium', NULL, NULL, '2026-01-05 14:10:13', '2026-01-05 17:04:10', NULL, 0, NULL, NULL),
(83, 'BLT1768852566610', 'awd', 'awd', 'awd', '2026-01-20', '03:57:00', 'awd', 'zxc', 'Archived', 'Low', 132, 130, '2026-01-19 19:56:06', '2026-01-19 20:05:26', NULL, 0, NULL, NULL),
(84, 'BLT1768853121642', 'awd', 'zxc', 'awd', '2026-01-06', '04:08:00', 'awdzxc', 'awdawd', 'Archived', 'Medium', NULL, 130, '2026-01-19 20:05:21', '2026-01-19 20:11:42', NULL, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `case_assignments`
--

CREATE TABLE `case_assignments` (
  `id` int(11) NOT NULL,
  `case_number` varchar(50) NOT NULL,
  `incident_type` varchar(100) NOT NULL,
  `complainant_name` varchar(150) NOT NULL,
  `respondent_name` varchar(150) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `incident_date` date NOT NULL,
  `incident_time` time DEFAULT NULL,
  `description` text NOT NULL,
  `priority` enum('High','Medium','Low') DEFAULT 'Medium',
  `status` enum('New','Ongoing','Resolved','Closed') DEFAULT 'New',
  `assigned_by` int(11) NOT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `barangay_chairperson_id` int(11) DEFAULT NULL,
  `assignment_date` datetime DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `nlp_threat_level` varchar(50) DEFAULT NULL,
  `nlp_severity_score` decimal(5,2) DEFAULT NULL,
  `incident_id` int(11) DEFAULT NULL COMMENT 'Reference to incident'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `case_assignments`
--

INSERT INTO `case_assignments` (`id`, `case_number`, `incident_type`, `complainant_name`, `respondent_name`, `location`, `incident_date`, `incident_time`, `description`, `priority`, `status`, `assigned_by`, `assigned_to`, `barangay_chairperson_id`, `assignment_date`, `created_at`, `updated_at`, `nlp_threat_level`, `nlp_severity_score`, `incident_id`) VALUES
(1, 'CASE-2026-01-05-001', 'awd', 'awd', 'awd', 'awd', '2026-01-01', '06:10:00', 'awdzxc', 'Medium', 'New', 130, 0, 130, '2026-01-06 04:10:14', '2026-01-05 20:10:14', '2026-01-05 20:10:14', NULL, NULL, NULL),
(2, 'CASE-2026-01-05-002', 'awd', 'awd', 'awd', 'awd', '2026-01-01', '06:10:00', 'awdzxc', 'Medium', 'New', 130, 0, 130, '2026-01-06 04:10:21', '2026-01-05 20:10:21', '2026-01-05 20:10:21', NULL, NULL, NULL),
(3, 'CASE-2026-01-05-003', 'qwda', 'zxc', 'wad', 'awd', '2025-12-31', '04:17:00', 'zxczxc', 'Medium', 'New', 130, 0, 130, '2026-01-06 04:17:37', '2026-01-05 20:17:37', '2026-01-05 20:17:37', NULL, NULL, NULL),
(5, 'INC-20260106-069D6', 'Other', 'Jeyceebaya Admin', '', '', '2026-01-06', '00:00:00', 'Incident', 'Low', 'New', 130, NULL, NULL, '2026-01-07 03:02:07', '2026-01-06 19:02:07', '2026-01-06 19:02:07', 'Low', 0.00, NULL),
(6, 'INC-20260107-23839', 'Violence', 'Dars', 'Ewan', 'Quezon city', '2026-01-08', '16:43:00', 'Nakipag sapakan sa kanto', 'Low', 'New', 128, NULL, NULL, '2026-01-08 00:44:05', '2026-01-07 16:44:05', '2026-01-07 16:44:05', 'Low', 20.00, NULL),
(7, 'INC-20260107-AE42C', 'Violence', 'Dars', 'Ewan', 'Quezon city', '2026-01-08', '16:43:00', 'Nakipag sapakan sa kanto', 'Low', 'New', 128, NULL, NULL, '2026-01-08 00:46:00', '2026-01-07 16:46:00', '2026-01-07 16:46:00', 'Low', 20.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `case_notifications`
--

CREATE TABLE `case_notifications` (
  `id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `case_id` int(11) NOT NULL,
  `case_number` varchar(50) NOT NULL,
  `notification_type` enum('New Assignment','Status Update','Reassignment','Follow-up Required') NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `case_timeline`
--

CREATE TABLE `case_timeline` (
  `id` int(11) NOT NULL,
  `case_id` int(11) NOT NULL,
  `case_number` varchar(50) NOT NULL,
  `event_type` enum('Case Created','Assigned','Status Changed','Follow-up','Reassigned','Resolved','Closed') NOT NULL,
  `event_description` text NOT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `event_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `case_timeline`
--

INSERT INTO `case_timeline` (`id`, `case_id`, `case_number`, `event_type`, `event_description`, `performed_by`, `event_date`) VALUES
(1, 1, 'CASE-2026-01-05-001', 'Case Created', 'Case created and assigned', 130, '2026-01-05 20:10:14'),
(2, 2, 'CASE-2026-01-05-002', 'Case Created', 'Case created and assigned', 130, '2026-01-05 20:10:21'),
(3, 3, 'CASE-2026-01-05-003', 'Case Created', 'Case created and assigned', 130, '2026-01-05 20:17:37');

-- --------------------------------------------------------

--
-- Table structure for table `case_updates`
--

CREATE TABLE `case_updates` (
  `id` int(11) NOT NULL,
  `case_id` int(11) NOT NULL,
  `case_number` varchar(50) NOT NULL,
  `update_type` enum('Status Change','Follow-up Action','Note','Reassignment') NOT NULL,
  `previous_status` varchar(20) DEFAULT NULL,
  `new_status` varchar(20) DEFAULT NULL,
  `action_description` text NOT NULL,
  `updated_by` int(11) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chatbot_conversations`
--

CREATE TABLE `chatbot_conversations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_message` text NOT NULL,
  `bot_reply` text NOT NULL,
  `source` varchar(50) DEFAULT 'knowledge_base',
  `language` varchar(10) DEFAULT 'en',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `critical_incidents_view`
-- (See below for the actual view)
--
CREATE TABLE `critical_incidents_view` (
`id` int(11)
,`case_no` varchar(30)
,`incident_type` enum('Abuse','Neglect','Violence','Theft','Assault','Domestic','Other')
,`location` varchar(255)
,`incident_date` date
,`nlp_threat_level` varchar(50)
,`nlp_severity_score` decimal(5,2)
,`assigned_to` varchar(253)
,`status` enum('Draft','Submitted','Under Review','Verified','Resolved','Closed','Archived')
);

-- --------------------------------------------------------

--
-- Table structure for table `employee_statuses`
--

CREATE TABLE `employee_statuses` (
  `employee_ids` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `incidents`
--

CREATE TABLE `incidents` (
  `id` int(11) NOT NULL,
  `case_no` varchar(30) DEFAULT NULL,
  `incident_type` enum('Abuse','Neglect','Violence','Theft','Assault','Domestic','Other') NOT NULL DEFAULT 'Other',
  `incident_subtype` varchar(100) DEFAULT NULL,
  `auto_classification` varchar(100) DEFAULT NULL COMMENT 'Auto-classified type based on narrative',
  `manual_classification` varchar(100) DEFAULT NULL COMMENT 'Admin-corrected classification',
  `urgency_level` enum('Low','Medium','High','Critical') DEFAULT 'Medium',
  `is_high_risk` tinyint(1) DEFAULT 0 COMMENT 'Automatically flagged if contains violence/abuse keywords',
  `reporter_name` varchar(150) NOT NULL,
  `reporter_email` varchar(150) DEFAULT NULL,
  `reporter_phone` varchar(20) DEFAULT NULL,
  `reporter_type` enum('Parent','Citizen','Officer','Organization') DEFAULT 'Citizen',
  `incident_date` date NOT NULL,
  `incident_time` time DEFAULT NULL,
  `incident_datetime` datetime GENERATED ALWAYS AS (concat(`incident_date`,' ',coalesce(`incident_time`,'00:00:00'))) STORED,
  `location` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `narrative` text NOT NULL,
  `evidence_description` text DEFAULT NULL,
  `victim_name` varchar(150) DEFAULT NULL,
  `victim_age` int(11) DEFAULT NULL,
  `victim_gender` enum('Male','Female','Other') DEFAULT NULL,
  `suspect_name` varchar(150) DEFAULT NULL,
  `status` enum('Draft','Submitted','Under Review','Verified','Resolved','Closed','Archived') DEFAULT 'Draft',
  `assigned_to` int(11) DEFAULT NULL COMMENT 'Officer ID',
  `admin_notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `nlp_sentiment` varchar(50) DEFAULT 'Neutral' COMMENT 'Sentiment analysis result',
  `nlp_threat_level` varchar(50) DEFAULT 'Low' COMMENT 'Threat level from NLP',
  `nlp_severity_score` decimal(5,2) DEFAULT 0.00 COMMENT 'Severity score 0-100',
  `nlp_emotions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Detected emotions' CHECK (json_valid(`nlp_emotions`)),
  `nlp_analysis_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Full NLP data' CHECK (json_valid(`nlp_analysis_data`)),
  `nlp_confidence_score` decimal(5,2) DEFAULT 0.00 COMMENT 'Confidence 0-100',
  `nlp_summary` longtext DEFAULT NULL COMMENT 'NLP summary',
  `review_requested` tinyint(1) DEFAULT 0,
  `review_requested_at` datetime DEFAULT NULL,
  `review_completed` tinyint(1) DEFAULT 0,
  `review_completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `incidents`
--

INSERT INTO `incidents` (`id`, `case_no`, `incident_type`, `incident_subtype`, `auto_classification`, `manual_classification`, `urgency_level`, `is_high_risk`, `reporter_name`, `reporter_email`, `reporter_phone`, `reporter_type`, `incident_date`, `incident_time`, `location`, `latitude`, `longitude`, `narrative`, `evidence_description`, `victim_name`, `victim_age`, `victim_gender`, `suspect_name`, `status`, `assigned_to`, `admin_notes`, `created_by`, `created_at`, `updated_by`, `updated_at`, `nlp_sentiment`, `nlp_threat_level`, `nlp_severity_score`, `nlp_emotions`, `nlp_analysis_data`, `nlp_confidence_score`, `nlp_summary`, `review_requested`, `review_requested_at`, `review_completed`, `review_completed_at`) VALUES
(1, 'INC-20260105-B5FC3', 'Other', 'CAR CRASH', 'Abuse', NULL, 'High', 0, 'Merben', 'bayajohnchristian@yahoo.com', '09513199637', 'Citizen', '2026-01-14', '02:39:00', 'Qc', NULL, NULL, 'Nabunggo', 'None', 'Merben', 23, 'Male', 'Kalbo', 'Under Review', 135, NULL, 131, '2026-01-06 02:40:45', NULL, '2026-01-07 03:50:05', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(2, 'INC-20260105-0AAA4', 'Abuse', 'physical', 'Abuse', 'Violence', 'High', 1, 'John Christian', 'jeyceebaya@gmail.com', '09513199637', 'Officer', '2026-01-12', '02:44:00', 'QC', NULL, NULL, 'awd', '', 'idk', 24, 'Male', '', 'Under Review', 134, 'abuse', 128, '2026-01-06 02:43:14', 130, '2026-01-07 03:50:05', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(4, 'INC-20260106-069D6', 'Other', '', 'Other', NULL, 'Medium', 0, 'Jeyceebaya Admin', '', '', 'Citizen', '2026-01-06', '00:00:00', '', NULL, NULL, 'Incident', '', '', NULL, NULL, '', 'Submitted', NULL, NULL, 130, '2026-01-07 03:02:07', NULL, '2026-01-07 03:02:07', 'Neutral', 'Low', 0.00, '[\"Neutral\"]', '{\"sentiment\":{\"sentiment\":\"Neutral\",\"score\":0},\"emotions\":[\"Neutral\"],\"severity_score\":0,\"key_phrases\":[],\"entities\":{\"people\":[\"Incident\"],\"locations\":[],\"dates\":[],\"items\":[]},\"threat_level\":\"Low\",\"actionable_items\":[],\"word_count\":1,\"text_quality\":{\"is_detailed\":false,\"has_timestamps\":false,\"has_locations\":false,\"has_specifics\":false,\"grammar_score\":80},\"confidence_score\":30.5}', 30.50, '📊 **Analysis Summary**\n\n🎯 **Sentiment**: Neutral (Score: 0)\n😢 **Emotions**: Neutral\n⚠️ **Threat Level**: Low\n💯 **Severity**: 0.0/100\n📝 **Confidence**: 30.5%\n\n👥 **People Mentioned**: Incident\n\n📊 **Text Quality**:\n• Detailed: Could be more detailed\n• Timestamps: No\n• Locations: No', 0, NULL, 0, NULL),
(5, 'INC-20260107-23839', 'Violence', 'Physical abuse', 'Other', NULL, 'Medium', 0, 'Dars', 'dars@gmail.com', '281904732', 'Citizen', '2026-01-08', '16:43:00', 'Quezon city', NULL, NULL, 'Nakipag sapakan sa kanto', '', 'Kenzie', 22, 'Male', 'Ewan', 'Submitted', NULL, NULL, 128, '2026-01-08 00:44:05', NULL, '2026-01-08 00:44:05', 'Neutral', 'Low', 20.00, '[\"Neutral\"]', '{\"sentiment\":{\"sentiment\":\"Neutral\",\"score\":0},\"emotions\":[\"Neutral\"],\"severity_score\":20,\"key_phrases\":[],\"entities\":{\"people\":[\"Nakipag\"],\"locations\":[],\"dates\":[],\"items\":[]},\"threat_level\":\"Low\",\"actionable_items\":[],\"word_count\":4,\"text_quality\":{\"is_detailed\":false,\"has_timestamps\":false,\"has_locations\":false,\"has_specifics\":false,\"grammar_score\":80},\"confidence_score\":32}', 32.00, '📊 **Analysis Summary**\n\n🎯 **Sentiment**: Neutral (Score: 0)\n😢 **Emotions**: Neutral\n⚠️ **Threat Level**: Low\n💯 **Severity**: 20.0/100\n📝 **Confidence**: 32.0%\n\n👥 **People Mentioned**: Nakipag\n\n📊 **Text Quality**:\n• Detailed: Could be more detailed\n• Timestamps: No\n• Locations: No', 0, NULL, 0, NULL),
(6, 'INC-20260107-AE42C', 'Violence', 'Physical abuse', 'Other', NULL, 'Medium', 0, 'Dars', 'dars@gmail.com', '281904732', 'Citizen', '2026-01-08', '16:43:00', 'Quezon city', NULL, NULL, 'Nakipag sapakan sa kanto', '', 'Kenzie', 22, 'Male', 'Ewan', 'Submitted', NULL, NULL, 128, '2026-01-08 00:46:00', NULL, '2026-01-08 00:46:00', 'Neutral', 'Low', 20.00, '[\"Neutral\"]', '{\"sentiment\":{\"sentiment\":\"Neutral\",\"score\":0},\"emotions\":[\"Neutral\"],\"severity_score\":20,\"key_phrases\":[],\"entities\":{\"people\":[\"Nakipag\"],\"locations\":[],\"dates\":[],\"items\":[]},\"threat_level\":\"Low\",\"actionable_items\":[],\"word_count\":4,\"text_quality\":{\"is_detailed\":false,\"has_timestamps\":false,\"has_locations\":false,\"has_specifics\":false,\"grammar_score\":80},\"confidence_score\":32}', 32.00, '📊 **Analysis Summary**\n\n🎯 **Sentiment**: Neutral (Score: 0)\n😢 **Emotions**: Neutral\n⚠️ **Threat Level**: Low\n💯 **Severity**: 20.0/100\n📝 **Confidence**: 32.0%\n\n👥 **People Mentioned**: Nakipag\n\n📊 **Text Quality**:\n• Detailed: Could be more detailed\n• Timestamps: No\n• Locations: No', 0, NULL, 0, NULL),
(7, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Near Barangay Hall', NULL, NULL, 'Group of individuals causing disturbance in public area.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-11-15 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(8, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Street Corner', NULL, NULL, 'Complainant reported missing personal belongings from their residence.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-26 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(9, NULL, 'Assault', NULL, NULL, NULL, 'High', 1, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Barangay Main Street', NULL, NULL, 'Witness observed altercation between two individuals at the location.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-06 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(10, NULL, 'Theft', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Park', NULL, NULL, 'Group of individuals causing disturbance in public area.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-10-27 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(12, NULL, 'Assault', NULL, NULL, NULL, 'High', 1, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Local Store', NULL, NULL, 'Found child asking for assistance with no identification.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-20 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(13, NULL, 'Assault', NULL, NULL, NULL, 'Medium', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Public Road', NULL, NULL, 'Complainant reported missing personal belongings from their residence.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-08 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(14, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Market Area', NULL, NULL, 'Unauthorized individual found on property premises.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-10 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(16, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Market Area', NULL, NULL, 'Traffic incident involving two vehicles with minor injuries.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-10-30 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(17, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Local Store', NULL, NULL, 'Merchant reported shoplifting incident during business hours.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-25 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(18, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Community Center', NULL, NULL, 'Minor was found wandering alone without parental supervision.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-10-23 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(19, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Market Area', NULL, NULL, 'Unauthorized individual found on property premises.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-11-11 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(22, NULL, '', NULL, NULL, NULL, 'Medium', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Community Center', NULL, NULL, 'Neighbor complaint regarding noise disturbance late at night.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-11-20 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(23, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Market Area', NULL, NULL, 'Neighbor complaint regarding noise disturbance late at night.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-31 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(24, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Near Barangay Hall', NULL, NULL, 'Unauthorized individual found on property premises.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-11-05 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(25, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Public Road', NULL, NULL, 'Witness observed altercation between two individuals at the location.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-11-13 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(26, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Park', NULL, NULL, 'Unauthorized individual found on property premises.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-10-29 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(27, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Street Corner', NULL, NULL, 'Unauthorized individual found on property premises.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-11-23 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(28, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Near Barangay Hall', NULL, NULL, 'Group of individuals causing disturbance in public area.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-15 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(29, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Near Barangay Hall', NULL, NULL, 'Merchant reported shoplifting incident during business hours.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-11-09 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(30, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Street Corner', NULL, NULL, 'Found child asking for assistance with no identification.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-11-20 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(31, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Market Area', NULL, NULL, 'Group of individuals causing disturbance in public area.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-14 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(32, NULL, 'Assault', NULL, NULL, NULL, 'High', 1, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Community Center', NULL, NULL, 'Neighbor complaint regarding noise disturbance late at night.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-11-13 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(34, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Public Road', NULL, NULL, 'Minor was found wandering alone without parental supervision.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-11-07 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(35, NULL, '', NULL, NULL, NULL, 'Medium', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Street Corner', NULL, NULL, 'Dispute between property owners regarding boundary.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-11-28 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(37, NULL, 'Theft', NULL, NULL, NULL, 'Medium', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Public Road', NULL, NULL, 'Neighbor complaint regarding noise disturbance late at night.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-10-29 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(38, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Near Barangay Hall', NULL, NULL, 'Merchant reported shoplifting incident during business hours.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-11-07 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(39, NULL, 'Assault', NULL, NULL, NULL, 'High', 1, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Local Store', NULL, NULL, 'Group of individuals causing disturbance in public area.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-28 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(40, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Local Store', NULL, NULL, 'Witness observed altercation between two individuals at the location.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-11-01 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(41, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'School Grounds', NULL, NULL, 'Unauthorized individual found on property premises.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-10-31 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(42, NULL, '', NULL, NULL, NULL, 'Medium', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Residential Area', NULL, NULL, 'Witness observed altercation between two individuals at the location.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-11-15 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(43, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Near Barangay Hall', NULL, NULL, 'Found child asking for assistance with no identification.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-11-28 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(44, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Local Store', NULL, NULL, 'Traffic incident involving two vehicles with minor injuries.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-23 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(46, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Community Center', NULL, NULL, 'Complainant reported missing personal belongings from their residence.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-11-29 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(48, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Barangay Main Street', NULL, NULL, 'Neighbor complaint regarding noise disturbance late at night.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-24 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(49, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Park', NULL, NULL, 'Merchant reported shoplifting incident during business hours.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-10-23 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(50, NULL, '', NULL, NULL, NULL, 'Medium', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Local Store', NULL, NULL, 'Unauthorized individual found on property premises.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-11-08 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(51, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Street Corner', NULL, NULL, 'Dispute between property owners regarding boundary.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-11-23 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(52, NULL, '', NULL, NULL, NULL, 'Medium', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'School Grounds', NULL, NULL, 'Traffic incident involving two vehicles with minor injuries.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2026-01-04 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(55, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Public Road', NULL, NULL, 'Traffic incident involving two vehicles with minor injuries.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-19 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(58, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Barangay Main Street', NULL, NULL, 'Found child asking for assistance with no identification.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-15 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(60, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Local Store', NULL, NULL, 'Dispute between property owners regarding boundary.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-08 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(61, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Local Store', NULL, NULL, 'Neighbor complaint regarding noise disturbance late at night.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-22 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(62, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Market Area', NULL, NULL, 'Unauthorized individual found on property premises.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-11-03 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(63, NULL, '', NULL, NULL, NULL, 'Medium', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Barangay Main Street', NULL, NULL, 'Group of individuals causing disturbance in public area.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-05 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(64, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'School Grounds', NULL, NULL, 'Witness observed altercation between two individuals at the location.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-27 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(66, NULL, 'Assault', NULL, NULL, NULL, 'Medium', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'School Grounds', NULL, NULL, 'Group of individuals causing disturbance in public area.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-10-27 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(67, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Local Store', NULL, NULL, 'Unauthorized individual found on property premises.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-20 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(68, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Park', NULL, NULL, 'Found child asking for assistance with no identification.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-10 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(70, NULL, 'Assault', NULL, NULL, NULL, 'High', 1, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Public Road', NULL, NULL, 'Minor was found wandering alone without parental supervision.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-24 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(71, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Street Corner', NULL, NULL, 'Neighbor complaint regarding noise disturbance late at night.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-10-26 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(72, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Street Corner', NULL, NULL, 'Unauthorized individual found on property premises.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-24 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(73, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Barangay Main Street', NULL, NULL, 'Unauthorized individual found on property premises.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-05 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(74, NULL, 'Assault', NULL, NULL, NULL, 'High', 1, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Public Road', NULL, NULL, 'Witness observed altercation between two individuals at the location.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-15 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(75, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Market Area', NULL, NULL, 'Merchant reported shoplifting incident during business hours.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-11-21 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(76, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Park', NULL, NULL, 'Merchant reported shoplifting incident during business hours.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-11-14 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(77, NULL, '', NULL, NULL, NULL, 'Medium', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'School Grounds', NULL, NULL, 'Complainant reported missing personal belongings from their residence.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-10-30 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(78, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Market Area', NULL, NULL, 'Unauthorized individual found on property premises.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-10-23 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(79, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Park', NULL, NULL, 'Traffic incident involving two vehicles with minor injuries.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-18 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(80, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Public Road', NULL, NULL, 'Witness observed altercation between two individuals at the location.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-23 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(81, NULL, 'Assault', NULL, NULL, NULL, 'High', 1, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Local Store', NULL, NULL, 'Minor was found wandering alone without parental supervision.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-11-02 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(82, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Near Barangay Hall', NULL, NULL, 'Found child asking for assistance with no identification.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-11-20 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(83, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Public Road', NULL, NULL, 'Traffic incident involving two vehicles with minor injuries.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-12 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(84, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Street Corner', NULL, NULL, 'Found child asking for assistance with no identification.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-11-01 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(85, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Local Store', NULL, NULL, 'Neighbor complaint regarding noise disturbance late at night.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-07 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(87, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Barangay Main Street', NULL, NULL, 'Dispute between property owners regarding boundary.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-10-30 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(88, NULL, 'Theft', NULL, NULL, NULL, 'Medium', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Park', NULL, NULL, 'Group of individuals causing disturbance in public area.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-10-28 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(89, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Market Area', NULL, NULL, 'Unauthorized individual found on property premises.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-10-21 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(90, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Residential Area', NULL, NULL, 'Found child asking for assistance with no identification.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-11-14 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(91, NULL, 'Theft', NULL, NULL, NULL, 'Medium', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Street Corner', NULL, NULL, 'Traffic incident involving two vehicles with minor injuries.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-04 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(93, NULL, '', NULL, NULL, NULL, 'Medium', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Public Road', NULL, NULL, 'Witness observed altercation between two individuals at the location.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-16 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(94, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Near Barangay Hall', NULL, NULL, 'Traffic incident involving two vehicles with minor injuries.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-11-23 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(96, NULL, 'Assault', NULL, NULL, NULL, 'Medium', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Market Area', NULL, NULL, 'Minor was found wandering alone without parental supervision.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-11-10 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(97, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Barangay Main Street', NULL, NULL, 'Minor was found wandering alone without parental supervision.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-08 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(98, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Barangay Main Street', NULL, NULL, 'Neighbor complaint regarding noise disturbance late at night.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-11-17 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(99, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Community Center', NULL, NULL, 'Unauthorized individual found on property premises.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-11-16 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(100, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Near Barangay Hall', NULL, NULL, 'Unauthorized individual found on property premises.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-15 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(101, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Local Store', NULL, NULL, 'Merchant reported shoplifting incident during business hours.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-14 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(103, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Street Corner', NULL, NULL, 'Unauthorized individual found on property premises.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-25 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(104, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Barangay Main Street', NULL, NULL, 'Neighbor complaint regarding noise disturbance late at night.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-11-21 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL),
(106, NULL, '', NULL, NULL, NULL, 'Low', 0, 'System Generator', NULL, NULL, 'Citizen', '0000-00-00', NULL, 'Street Corner', NULL, NULL, 'Dispute between property owners regarding boundary.', NULL, NULL, NULL, NULL, NULL, 'Draft', NULL, NULL, NULL, '2025-12-09 20:34:04', NULL, '2026-01-20 03:34:04', 'Neutral', 'Low', 0.00, NULL, NULL, 0.00, NULL, 0, NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `nlp_analysis_cache`
--

CREATE TABLE `nlp_analysis_cache` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `analysis_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`analysis_data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `notification_type` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` longtext NOT NULL,
  `threat_level` varchar(50) DEFAULT NULL,
  `urgency` varchar(100) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `incident_id`, `notification_type`, `title`, `message`, `threat_level`, `urgency`, `is_read`, `read_at`, `created_at`) VALUES
(2, 130, 4, 'New Case Filed', 'Case #INC-20260106-069D6 - New Case Filed', 'New incident report filed: Other\nLocation: \nReporter: Jeyceebaya Admin\nThreat Level: Low', NULL, NULL, 0, NULL, '2026-01-06 19:02:07'),
(3, 130, 5, 'New Case Filed', 'Case #INC-20260107-23839 - New Case Filed', 'New incident report filed: Violence\nLocation: Quezon city\nReporter: Dars\nThreat Level: Low', NULL, NULL, 0, NULL, '2026-01-07 16:44:05'),
(4, 138, 5, 'New Case Filed', 'Case #INC-20260107-23839 - New Case Filed', 'New incident report filed: Violence\nLocation: Quezon city\nReporter: Dars\nThreat Level: Low', NULL, NULL, 0, NULL, '2026-01-07 16:44:05'),
(5, 130, 6, 'New Case Filed', 'Case #INC-20260107-AE42C - New Case Filed', 'New incident report filed: Violence\nLocation: Quezon city\nReporter: Dars\nThreat Level: Low', NULL, NULL, 0, NULL, '2026-01-07 16:46:00'),
(6, 138, 6, 'New Case Filed', 'Case #INC-20260107-AE42C - New Case Filed', 'New incident report filed: Violence\nLocation: Quezon city\nReporter: Dars\nThreat Level: Low', NULL, NULL, 0, NULL, '2026-01-07 16:46:00');

-- --------------------------------------------------------

--
-- Table structure for table `reset_tokens`
--

CREATE TABLE `reset_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reset_tokens`
--

INSERT INTO `reset_tokens` (`id`, `user_id`, `token`, `expires`) VALUES
(1, 123, '413a866e478b65401f7e8bfbf20cf2d4', '2026-01-02 20:19:28'),
(2, 124, 'd3b17162cdf30708b69be9bd668657fd', '2026-01-02 20:33:36'),
(3, 124, 'ecb19fc6622596e322abc0729a3cb8d5', '2026-01-02 20:40:49'),
(4, 124, '46eda23f4193a88a0be2de2d9f97e6d4', '2026-01-02 20:40:51'),
(5, 124, 'e86a230cc8c58e01081702b63fe1adeb', '2026-01-02 20:40:56'),
(6, 124, '0749e9b46fae489c6ac2f73141119f09', '2026-01-02 20:41:05'),
(7, 124, 'be23ea7d05d3488084059fbea14be5a8', '2026-01-02 20:41:58'),
(8, 124, '884504dab15501296df4f2af288d6684', '2026-01-02 20:45:15'),
(9, 124, '406630116921871f3b6d9fd66fa2586a', '2026-01-02 20:46:05'),
(10, 124, 'e477a6392b8b906d76a1aac4ebde8239', '2026-01-02 20:48:12'),
(11, 124, 'b344c54b8c9aa04e00f2b7d210a8d319', '2026-01-02 20:55:09'),
(12, 124, '66ae81026a93f7043df04a838ae71f07', '2026-01-02 20:55:20'),
(13, 124, '02e5484ec227739151e8da944ac104c9', '2026-01-02 20:55:40'),
(14, 124, 'b801b60181d6354b588f9e26eb339b4f', '2026-01-02 20:55:51'),
(15, 124, '7c1d42cd25c0a23d0f7337d2222d5ca2', '2026-01-05 04:11:35'),
(16, 124, '2410d8fd009b8c194e23b71e1c984737', '2026-01-05 04:11:46'),
(17, 124, '72e61f24a67da8d39f34070e756aa636', '2026-01-05 04:14:21'),
(18, 124, '0f9a153c0c8de4ace0910a9139939939', '2026-01-05 04:15:38'),
(19, 124, '72a1d0c2f4b94079b4163c91ac3d81b6', '2026-01-05 04:19:27'),
(20, 124, 'cb4e60d0f28d30d18bc46cbe329ada55', '2026-01-05 04:20:02'),
(21, 124, '23dea03a39faa778071384d7f4d4ed37', '2026-01-05 16:11:44'),
(22, 124, 'd4854082d1ca700a248d31d860fe3397', '2026-01-05 16:11:48'),
(23, 124, '481088501db657f662efbcdddc99ae94', '2026-01-05 16:17:11'),
(24, 124, 'bbbc079595a092296cb7c805632a187e', '2026-01-05 16:18:43'),
(25, 124, '632bbef33da0c0fe4cb1c14292b68a42', '2026-01-05 16:19:56'),
(26, 124, '9d2180cdf2a3b6a211ce6f6b31dc903c', '2026-01-05 16:24:55'),
(27, 124, 'ebe0f3726403fb61e9acdb0b5f416b48', '2026-01-05 16:25:31'),
(28, 128, '70c42a8c8bbfde6228649f02099fb7cb', '2026-01-08 22:30:05'),
(29, 139, 'b15a099d9fae49636f93ae3aab867f47', '2026-01-12 09:05:42');

-- --------------------------------------------------------

--
-- Table structure for table `review_requests`
--

CREATE TABLE `review_requests` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `reason` text NOT NULL,
  `priority` enum('High','Normal','Low') DEFAULT 'Normal',
  `status` enum('Pending','Completed','Rejected') DEFAULT 'Pending',
  `responded_by` int(11) DEFAULT NULL,
  `response` varchar(50) DEFAULT NULL,
  `findings` longtext DEFAULT NULL,
  `recommendations` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responded_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `signup`
--

CREATE TABLE `signup` (
  `user_id` int(11) NOT NULL,
  `fullname` varchar(150) NOT NULL,
  `emailadd` varchar(150) NOT NULL,
  `role` varchar(50) DEFAULT 'User',
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `email_verified` tinyint(1) DEFAULT 0,
  `verification_token` varchar(255) DEFAULT NULL,
  `token_expires` datetime DEFAULT NULL,
  `terms_accepted` tinyint(1) DEFAULT 0,
  `terms_accepted_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `signup`
--

INSERT INTO `signup` (`user_id`, `fullname`, `emailadd`, `role`, `username`, `password`, `created_at`, `email_verified`, `verification_token`, `token_expires`, `terms_accepted`, `terms_accepted_date`) VALUES
(128, 'john christian', 'jeyceebaya@gmail.com', 'User', 'qwerty', '$2y$10$c.NSBhhqVhg2UPqYcYbK1e.PlRROPTNIwaXoKoGNHJU04lOvVWB8G', '2026-01-05 14:58:05', 1, NULL, NULL, 0, NULL),
(130, 'Jeyceebaya Admin', 'admin@alertara.local', 'Admin', 'Jeyceebaya', '$2y$10$zbhlPiLnzg3ka6Jshx299OG9hnWy9njd544E7TgHtJ2zHJgbC8uZO', '2026-01-05 16:59:26', 1, NULL, NULL, 1, NULL),
(131, 'Merben', 'bayajohnchristian@yahoo.com', 'User', 'Merben123', '$2y$10$29i5h5zBE.34BtehHOYeHOyt.MfT9WP8.rjplz.nDs233Qfnql2Uu', '2026-01-05 17:30:07', 1, NULL, NULL, 1, '2026-01-05 18:30:07'),
(132, 'Officer Anna', 'officer1@alertara.local', 'Officer', 'officer1', '$2y$10$8XvU.QzSjq2eWEmA/rp1b.wj//EXap5uhkg2R1ujt9pD55Lgxw/42', '2026-01-06 19:13:14', 1, NULL, NULL, 1, NULL),
(133, 'Officer Ben', 'officer2@alertara.local', 'Officer', 'officer2', '$2y$10$FrOip6XRwOLCPb1e4y1CE.COp/vTgB8tO4A7aQjupconAH/yz5Xi.', '2026-01-06 19:13:18', 1, NULL, NULL, 1, NULL),
(134, 'Officer Carla', 'officer3@alertara.local', 'Officer', 'officer3', '$2y$10$9snpmqLG2U93snFVnNs6LuXj.07nOg0RQrQEgeit/XOnYm/vRc/eW', '2026-01-06 19:13:40', 1, NULL, NULL, 1, NULL),
(135, 'Officer Dan', 'officer4@alertara.local', 'Officer', 'officer4', '$2y$10$1I6q3qIzEGtj3nQpD0glh.FczvrS8p6ZKGySXZHOrkaqpzZIfj/0.', '2026-01-06 19:13:40', 1, NULL, NULL, 1, NULL),
(136, 'Officer Ella', 'officer5@alertara.local', 'Officer', 'officer5', '$2y$10$s9fXPXUuPdy8Vdi4NQfYQ.IGwQkE1e5p33XV1x47MZwuKrNq2Y4fm', '2026-01-06 19:13:40', 1, NULL, NULL, 1, NULL),
(137, 'Test Officer', 'officer@alertara.local', 'Officer', 'officer', '$2y$10$LKAdWz7AN4aEWpQ.8RyIjO/ccP7NDIKTBCex1ezLTHytgwpJIwRei', '2026-01-06 19:26:44', 1, NULL, NULL, 1, NULL),
(138, 'Christian', 'jawdio@gmail.com', 'Barangay Official', 'christian00', '$2y$10$9GgyNCNOtk9V2GnPAwFhYe3aXL3T2/2sTCmoLDB8dEtSMAKtLk7/G', '2026-01-06 19:37:34', 1, NULL, NULL, 1, NULL),
(139, 'mary', 'emchivee@gmail.com', 'User', 'mary', '$2y$10$toAl4bWDvnuWPT2GqGzBe.Mz5WB8GLKCox6BaGbIKzpKq7R4Q/Ktm', '2026-01-12 07:04:16', 0, '73d607a07533ef6c98035e6f8a7c9ec35509bdc6ec2d05a189a2814da510fc2f', '2026-01-13 08:04:16', 1, '2026-01-12 08:04:16');

-- --------------------------------------------------------

--
-- Table structure for table `suspects`
--

CREATE TABLE `suspects` (
  `id` int(11) NOT NULL,
  `case_id` int(11) NOT NULL,
  `case_number` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `age` int(11) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT 'Male',
  `address` varchar(255) DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `zip_code` varchar(10) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `id_type` varchar(50) DEFAULT NULL,
  `id_number` varchar(100) DEFAULT NULL,
  `physical_description` text DEFAULT NULL,
  `known_aliases` varchar(255) DEFAULT NULL,
  `criminal_history` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `status` enum('Active','Arrested','Released','Deceased','Unknown') DEFAULT 'Active',
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suspects`
--

INSERT INTO `suspects` (`id`, `case_id`, `case_number`, `first_name`, `middle_name`, `last_name`, `age`, `date_of_birth`, `gender`, `address`, `barangay`, `city`, `province`, `zip_code`, `contact_number`, `email`, `id_type`, `id_number`, `physical_description`, `known_aliases`, `criminal_history`, `remarks`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 3, 'CASE-2026-01-05-003', 'Jeysii', 'm', 'b', 23, '2026-01-07', 'Male', '1QC', 'qc', 'qc', 'qc', '1128', '23213', 'awdda@gmail.com', 'none', 'none', 'awdawd', '', '', '', 'Active', 130, NULL, '2026-01-06 18:57:56', '2026-01-06 18:57:56');

-- --------------------------------------------------------

--
-- Table structure for table `suspect_updates`
--

CREATE TABLE `suspect_updates` (
  `id` int(11) NOT NULL,
  `suspect_id` int(11) NOT NULL,
  `update_type` varchar(50) DEFAULT NULL,
  `update_description` text DEFAULT NULL,
  `updated_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suspect_updates`
--

INSERT INTO `suspect_updates` (`id`, `suspect_id`, `update_type`, `update_description`, `updated_by`, `created_at`) VALUES
(1, 1, 'Record Created', 'Suspect record created', 130, '2026-01-06 18:57:56');

-- --------------------------------------------------------

--
-- Table structure for table `system_alerts`
--

CREATE TABLE `system_alerts` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `alert_type` varchar(100) NOT NULL,
  `severity` varchar(50) NOT NULL,
  `alert_message` text NOT NULL,
  `resolved` tinyint(1) DEFAULT 0,
  `resolved_by` int(11) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('Admin','Officer','Staff') NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `phone_number` varchar(20) DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `phone_verified` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `witnesses`
--

CREATE TABLE `witnesses` (
  `id` int(11) NOT NULL,
  `case_id` int(11) NOT NULL,
  `case_number` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `age` int(11) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT 'Male',
  `address` varchar(255) DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `zip_code` varchar(10) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `id_type` varchar(50) DEFAULT NULL,
  `id_number` varchar(100) DEFAULT NULL,
  `relationship_to_case` varchar(100) DEFAULT NULL,
  `witness_type` enum('Direct','Indirect','Hearsay','Character') DEFAULT 'Direct',
  `statement` text DEFAULT NULL,
  `reliability` enum('High','Medium','Low') DEFAULT 'Medium',
  `available_for_court` tinyint(1) DEFAULT 1,
  `protection_needed` tinyint(1) DEFAULT 0,
  `remarks` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `witness_updates`
--

CREATE TABLE `witness_updates` (
  `id` int(11) NOT NULL,
  `witness_id` int(11) NOT NULL,
  `update_type` varchar(50) DEFAULT NULL,
  `update_description` text DEFAULT NULL,
  `updated_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for view `critical_incidents_view`
--
DROP TABLE IF EXISTS `critical_incidents_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `critical_incidents_view`  AS SELECT `i`.`id` AS `id`, `i`.`case_no` AS `case_no`, `i`.`incident_type` AS `incident_type`, `i`.`location` AS `location`, `i`.`incident_date` AS `incident_date`, `i`.`nlp_threat_level` AS `nlp_threat_level`, `i`.`nlp_severity_score` AS `nlp_severity_score`, concat(`u`.`fullname`,' (',`u`.`username`,')') AS `assigned_to`, `i`.`status` AS `status` FROM (`incidents` `i` left join `signup` `u` on(`i`.`assigned_to` = `u`.`user_id`)) WHERE `i`.`nlp_threat_level` in ('Critical','High') ORDER BY `i`.`nlp_severity_score` DESC, `i`.`created_at` DESC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attachments`
--
ALTER TABLE `attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `barangay_officials`
--
ALTER TABLE `barangay_officials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `bcpc_officers`
--
ALTER TABLE `bcpc_officers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `idx_bcpc_available_load` (`is_available`,`current_case_load`);

--
-- Indexes for table `blotters`
--
ALTER TABLE `blotters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `blotter_no` (`blotter_no`),
  ADD KEY `idx_blotters_incident` (`incident_id`);

--
-- Indexes for table `case_assignments`
--
ALTER TABLE `case_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `case_number` (`case_number`),
  ADD KEY `idx_case_assignments_status` (`status`),
  ADD KEY `idx_case_assignments_assigned_to` (`assigned_to`),
  ADD KEY `idx_case_assignments_nlp_threat` (`nlp_threat_level`),
  ADD KEY `incident_id` (`incident_id`);

--
-- Indexes for table `case_notifications`
--
ALTER TABLE `case_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_recipient` (`recipient_id`);

--
-- Indexes for table `case_timeline`
--
ALTER TABLE `case_timeline`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_timeline_case_id` (`case_id`);

--
-- Indexes for table `case_updates`
--
ALTER TABLE `case_updates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_case_updates_case_id` (`case_id`);

--
-- Indexes for table `chatbot_conversations`
--
ALTER TABLE `chatbot_conversations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_time` (`user_id`,`created_at`),
  ADD KEY `idx_source` (`source`),
  ADD KEY `idx_user_created` (`user_id`,`created_at`);

--
-- Indexes for table `incidents`
--
ALTER TABLE `incidents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `case_no` (`case_no`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_urgency` (`urgency_level`),
  ADD KEY `idx_is_high_risk` (`is_high_risk`),
  ADD KEY `idx_incident_date` (`incident_date`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_case_no` (`case_no`),
  ADD KEY `idx_incidents_nlp_severity` (`nlp_severity_score`),
  ADD KEY `idx_incidents_review_status` (`review_requested`,`review_completed`);

--
-- Indexes for table `nlp_analysis_cache`
--
ALTER TABLE `nlp_analysis_cache`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `incident_id` (`incident_id`),
  ADD KEY `idx_analysis_date` (`created_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_unread` (`user_id`,`is_read`),
  ADD KEY `idx_incident_notifications` (`incident_id`),
  ADD KEY `idx_notification_type` (`notification_type`);

--
-- Indexes for table `reset_tokens`
--
ALTER TABLE `reset_tokens`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `review_requests`
--
ALTER TABLE `review_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `responded_by` (`responded_by`),
  ADD KEY `idx_incident_reviews` (`incident_id`),
  ADD KEY `idx_pending_reviews` (`status`,`priority`);

--
-- Indexes for table `signup`
--
ALTER TABLE `signup`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `suspects`
--
ALTER TABLE `suspects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_suspects_case_id` (`case_id`),
  ADD KEY `idx_suspects_status` (`status`);

--
-- Indexes for table `suspect_updates`
--
ALTER TABLE `suspect_updates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_alerts`
--
ALTER TABLE `system_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `incident_id` (`incident_id`),
  ADD KEY `resolved_by` (`resolved_by`),
  ADD KEY `idx_unresolved_alerts` (`resolved`),
  ADD KEY `idx_alert_severity` (`severity`),
  ADD KEY `idx_alert_date` (`created_at`);

--
-- Indexes for table `witnesses`
--
ALTER TABLE `witnesses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_witnesses_case_id` (`case_id`),
  ADD KEY `idx_witnesses_reliability` (`reliability`);

--
-- Indexes for table `witness_updates`
--
ALTER TABLE `witness_updates`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attachments`
--
ALTER TABLE `attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `barangay_officials`
--
ALTER TABLE `barangay_officials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bcpc_officers`
--
ALTER TABLE `bcpc_officers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `blotters`
--
ALTER TABLE `blotters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT for table `case_assignments`
--
ALTER TABLE `case_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `case_notifications`
--
ALTER TABLE `case_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `case_timeline`
--
ALTER TABLE `case_timeline`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `case_updates`
--
ALTER TABLE `case_updates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chatbot_conversations`
--
ALTER TABLE `chatbot_conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `incidents`
--
ALTER TABLE `incidents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=107;

--
-- AUTO_INCREMENT for table `nlp_analysis_cache`
--
ALTER TABLE `nlp_analysis_cache`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `reset_tokens`
--
ALTER TABLE `reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `review_requests`
--
ALTER TABLE `review_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `signup`
--
ALTER TABLE `signup`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=140;

--
-- AUTO_INCREMENT for table `suspects`
--
ALTER TABLE `suspects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `suspect_updates`
--
ALTER TABLE `suspect_updates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `system_alerts`
--
ALTER TABLE `system_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `witnesses`
--
ALTER TABLE `witnesses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `witness_updates`
--
ALTER TABLE `witness_updates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attachments`
--
ALTER TABLE `attachments`
  ADD CONSTRAINT `attachments_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `signup` (`user_id`);

--
-- Constraints for table `blotters`
--
ALTER TABLE `blotters`
  ADD CONSTRAINT `blotters_ibfk_1` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`),
  ADD CONSTRAINT `blotters_ibfk_2` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`),
  ADD CONSTRAINT `blotters_ibfk_3` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`),
  ADD CONSTRAINT `blotters_ibfk_4` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`);

--
-- Constraints for table `case_assignments`
--
ALTER TABLE `case_assignments`
  ADD CONSTRAINT `case_assignments_ibfk_1` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`),
  ADD CONSTRAINT `case_assignments_ibfk_2` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`),
  ADD CONSTRAINT `case_assignments_ibfk_3` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`),
  ADD CONSTRAINT `case_assignments_ibfk_4` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`);

--
-- Constraints for table `chatbot_conversations`
--
ALTER TABLE `chatbot_conversations`
  ADD CONSTRAINT `chatbot_conversations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `signup` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `incidents`
--
ALTER TABLE `incidents`
  ADD CONSTRAINT `incidents_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `signup` (`user_id`),
  ADD CONSTRAINT `incidents_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `signup` (`user_id`),
  ADD CONSTRAINT `incidents_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `signup` (`user_id`);

--
-- Constraints for table `nlp_analysis_cache`
--
ALTER TABLE `nlp_analysis_cache`
  ADD CONSTRAINT `nlp_analysis_cache_ibfk_1` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `signup` (`user_id`),
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`);

--
-- Constraints for table `review_requests`
--
ALTER TABLE `review_requests`
  ADD CONSTRAINT `review_requests_ibfk_1` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`),
  ADD CONSTRAINT `review_requests_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `signup` (`user_id`),
  ADD CONSTRAINT `review_requests_ibfk_3` FOREIGN KEY (`responded_by`) REFERENCES `signup` (`user_id`);

--
-- Constraints for table `system_alerts`
--
ALTER TABLE `system_alerts`
  ADD CONSTRAINT `system_alerts_ibfk_1` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`),
  ADD CONSTRAINT `system_alerts_ibfk_2` FOREIGN KEY (`resolved_by`) REFERENCES `signup` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
