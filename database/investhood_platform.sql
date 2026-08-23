-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 21, 2026 at 10:56 PM
-- Server version: 10.4.24-MariaDB
-- PHP Version: 8.1.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `investhood_platform`
--

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `id` int(10) UNSIGNED NOT NULL,
  `application_reference` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `candidate_id` int(10) UNSIGNED NOT NULL,
  `opportunity_id` int(10) UNSIGNED NOT NULL,
  `status` enum('draft','submitted','eligibility_review','screened','assessment','interview','waitlisted','selected','rejected','withdrawn','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `submitted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`id`, `application_reference`, `candidate_id`, `opportunity_id`, `status`, `created_at`, `updated_at`, `submitted_at`) VALUES
(1, 'APP-2026-513C8C', 9, 3, 'draft', '2026-08-19 10:35:03', '2026-08-21 00:56:30', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `application_documents`
--

CREATE TABLE `application_documents` (
  `id` int(10) UNSIGNED NOT NULL,
  `application_id` int(10) UNSIGNED NOT NULL,
  `document_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `file_checksum` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `application_responses`
--

CREATE TABLE `application_responses` (
  `id` int(10) UNSIGNED NOT NULL,
  `application_id` int(10) UNSIGNED NOT NULL,
  `question_id` int(10) UNSIGNED NOT NULL,
  `response` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `record_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `record_id` int(10) UNSIGNED DEFAULT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `record_type`, `record_id`, `reason`, `ip_address`, `created_at`) VALUES
(1, 9, 'profile_updated', 'candidate_profile', NULL, 'Personal information updated', '::1', '2026-08-06 13:01:51'),
(2, 9, 'qualification_added', 'qualification', 1, 'Qualification added', '::1', '2026-08-06 13:05:54'),
(3, 9, 'qualification_added', 'qualification', 2, 'Qualification added', '::1', '2026-08-06 13:07:14'),
(4, 9, 'qualification_updated', 'qualification', 1, 'Qualification updated', '::1', '2026-08-06 13:14:24'),
(5, 9, 'qualification_added', 'qualification', 3, 'Qualification added', '::1', '2026-08-06 13:14:53'),
(6, 9, 'qualification_removed', 'qualification', 3, 'Qualification removed', '::1', '2026-08-06 13:15:20'),
(7, 9, 'experience_added', 'work_experience', 1, 'Work experience added', '::1', '2026-08-06 13:19:13'),
(8, 9, 'experience_updated', 'work_experience', 1, 'Work experience updated', '::1', '2026-08-06 13:19:38'),
(9, 9, 'experience_updated', 'work_experience', 1, 'Work experience updated', '::1', '2026-08-06 13:20:04'),
(10, 9, 'document_uploaded', 'document', 1, 'Document uploaded: Hlobisile_Mathebula_CV.pdf', '::1', '2026-08-06 13:23:00'),
(11, 9, 'document_uploaded', 'document', 2, 'Document uploaded: ID Copy.pdf', '::1', '2026-08-06 13:24:50'),
(12, 9, 'document_uploaded', 'document', 3, 'Document uploaded: Matric Statement _Hlobisile Mathebula_.pdf', '::1', '2026-08-06 13:26:05'),
(13, 9, 'profile_updated', 'candidate_profile', NULL, 'Personal information updated', '::1', '2026-08-06 13:28:04'),
(14, 9, 'profile_updated', 'candidate_profile', NULL, 'Professional information updated', '::1', '2026-08-06 13:46:49'),
(15, 9, 'profile_updated', 'candidate_profile', NULL, 'Professional information updated', '::1', '2026-08-06 13:47:28'),
(16, 9, 'skill_added', 'skill', 1, 'Skill added: PHP', '::1', '2026-08-06 13:50:30'),
(17, 9, 'skill_added', 'skill', 4, 'Skill added: Python', '::1', '2026-08-06 13:50:50'),
(18, 9, 'skill_added', 'skill', 38, 'Skill added: Communication', '::1', '2026-08-06 13:51:08'),
(19, 9, 'skill_added', 'skill', 40, 'Skill added: Problem Solving', '::1', '2026-08-06 13:51:17'),
(20, 9, 'skill_added', 'skill', 39, 'Skill added: Teamwork', '::1', '2026-08-06 13:51:27'),
(21, 9, 'skill_added', 'skill', 10, 'Skill added: React', '::1', '2026-08-06 13:52:09'),
(22, 9, 'skill_added', 'skill', 15, 'Skill added: SQL', '::1', '2026-08-06 13:52:18'),
(23, 9, 'profile_updated', 'candidate_profile', NULL, 'Professional information updated', '::1', '2026-08-06 13:53:34'),
(24, 9, 'document_deleted', 'document', 3, 'Document deleted: Matric Statement _Hlobisile Mathebula_.pdf', '::1', '2026-08-06 13:54:35'),
(25, 9, 'skill_removed', 'skill', 1, 'Skill removed: PHP', '::1', '2026-08-06 14:14:58'),
(26, 9, 'skill_removed', 'skill', 4, 'Skill removed: Python', '::1', '2026-08-06 14:15:02'),
(27, 9, 'skill_removed', 'skill', 10, 'Skill removed: React', '::1', '2026-08-06 14:15:19'),
(28, 9, 'skill_added', 'skill', 10, 'Skill added: React', '::1', '2026-08-06 14:15:40'),
(29, 9, 'skill_added', 'skill', 1, 'Skill added: PHP', '::1', '2026-08-06 14:15:57'),
(30, 9, 'skill_added', 'skill', 4, 'Skill added: Python', '::1', '2026-08-06 14:16:31'),
(31, 9, 'skill_removed', 'skill', 38, 'Skill removed: Communication', '::1', '2026-08-06 14:16:38'),
(32, 9, 'skill_removed', 'skill', 40, 'Skill removed: Problem Solving', '::1', '2026-08-06 14:16:42'),
(33, 9, 'skill_added', 'skill', 40, 'Skill added: Problem Solving', '::1', '2026-08-06 14:16:57'),
(34, 9, 'skill_added', 'skill', 38, 'Skill added: Communication', '::1', '2026-08-06 14:17:13'),
(35, 9, 'skill_added', 'skill', 47, 'Skill added: Attention to Detail', '::1', '2026-08-06 14:17:46'),
(36, 9, 'profile_updated', 'candidate_profile', NULL, 'Professional information updated', '::1', '2026-08-06 17:03:34'),
(37, 9, 'certification_added', 'certification', 1, 'Certification added', '::1', '2026-08-07 09:18:04'),
(38, 9, 'document_uploaded', 'document', 4, 'Document uploaded: IT Diploma - Delani Sibande.pdf', '::1', '2026-08-07 09:41:03'),
(39, 9, 'document_deleted', 'document', 4, 'Document deleted: IT Diploma - Delani Sibande.pdf', '::1', '2026-08-07 09:41:33'),
(40, 9, 'certification_updated', 'certification', 1, 'Certification updated', '::1', '2026-08-07 09:42:49'),
(41, 9, 'certification_updated', 'certification', 1, 'Certification updated', '::1', '2026-08-07 09:43:49'),
(42, 9, 'consent_granted', 'consent', NULL, 'Consent granted for: programme_administration', '::1', '2026-08-07 09:59:00'),
(43, 9, 'consent_granted', 'consent', NULL, 'Consent granted for: future_opportunities', '::1', '2026-08-07 09:59:03'),
(44, 9, 'consent_withdrawn', 'consent', NULL, 'Consent withdrawn for: future_opportunities', '::1', '2026-08-07 09:59:05'),
(45, 9, 'consent_withdrawn', 'consent', NULL, 'Consent withdrawn for: programme_administration', '::1', '2026-08-07 09:59:05'),
(46, 9, 'consent_granted', 'consent', NULL, 'Consent granted for: programme_administration', '::1', '2026-08-07 09:59:23'),
(47, 9, 'consent_granted', 'consent', NULL, 'Consent granted for: future_opportunities', '::1', '2026-08-07 09:59:29'),
(48, 9, 'consent_granted', 'consent', NULL, 'Consent granted for: client_submission', '::1', '2026-08-07 09:59:47'),
(49, 9, 'profile_picture_removed', 'candidate_profile', NULL, 'Profile picture removed', '::1', '2026-08-07 10:12:22'),
(50, 9, 'consent_withdrawn', 'consent', NULL, 'Consent withdrawn for: programme_administration', '::1', '2026-08-07 10:13:20'),
(51, 9, 'consent_withdrawn', 'consent', NULL, 'Consent withdrawn for: future_opportunities', '::1', '2026-08-07 10:13:26'),
(52, 9, 'consent_withdrawn', 'consent', NULL, 'Consent withdrawn for: client_submission', '::1', '2026-08-07 10:13:28'),
(53, 9, 'consent_granted', 'consent', NULL, 'Consent granted for: client_submission', '::1', '2026-08-07 10:13:32'),
(54, 9, 'consent_granted', 'consent', NULL, 'Consent granted for: future_opportunities', '::1', '2026-08-07 10:13:37'),
(55, 9, 'consent_granted', 'consent', NULL, 'Consent granted for: programme_administration', '::1', '2026-08-07 10:13:38'),
(56, 9, 'consent_granted', 'consent', NULL, 'Consent granted for: talent_pool', '::1', '2026-08-07 10:27:27'),
(57, 9, 'consent_withdrawn', 'consent', NULL, 'Consent withdrawn for: talent_pool', '::1', '2026-08-07 10:27:31'),
(58, 9, 'consent_withdrawn', 'consent', NULL, 'Consent withdrawn for: client_submission', '::1', '2026-08-07 10:27:39'),
(59, 9, 'consent_withdrawn', 'consent', NULL, 'Consent withdrawn for: future_opportunities', '::1', '2026-08-07 10:27:41'),
(60, 9, 'consent_withdrawn', 'consent', NULL, 'Consent withdrawn for: programme_administration', '::1', '2026-08-07 10:27:42'),
(61, 9, 'consent_granted', 'consent', NULL, 'Consent granted for: programme_administration', '::1', '2026-08-07 10:28:40'),
(62, 9, 'consent_granted', 'consent', NULL, 'Consent granted for: future_opportunities', '::1', '2026-08-07 10:28:45'),
(63, 9, 'consent_granted', 'consent', NULL, 'Consent granted for: client_submission', '::1', '2026-08-07 10:28:47'),
(64, 9, 'qualification_updated', 'qualification', 2, 'Qualification updated', '::1', '2026-08-07 10:29:29'),
(65, 9, 'profile_updated', 'candidate_profile', NULL, 'Personal information updated', '::1', '2026-08-07 10:31:23'),
(66, 9, 'qualification_updated', 'qualification', 1, 'Qualification updated', '::1', '2026-08-07 10:32:56'),
(67, 9, 'profile_picture_removed', 'candidate_profile', NULL, 'Profile picture removed', '::1', '2026-08-07 10:33:39'),
(68, 9, 'profile_picture_uploaded', 'candidate_profile', NULL, 'Profile picture uploaded', '::1', '2026-08-07 10:38:13'),
(69, 9, 'profile_picture_uploaded', 'candidate_profile', NULL, 'Profile picture uploaded', '::1', '2026-08-07 10:38:54'),
(70, 9, 'profile_picture_uploaded', 'candidate_profile', NULL, 'Profile picture uploaded', '::1', '2026-08-07 10:42:04'),
(71, 9, 'document_uploaded', 'document', 5, 'Document uploaded: Delani Sibande CV.pdf', '::1', '2026-08-07 10:44:05'),
(72, 9, 'document_replaced', 'document', 5, 'Document replaced: Hlobisile_Mathebula_CV.pdf', '::1', '2026-08-07 10:44:55'),
(73, 9, 'profile_updated', 'candidate_profile', NULL, 'Professional information updated', '::1', '2026-08-07 11:21:24'),
(74, 9, 'profile_picture_removed', 'candidate_profile', NULL, 'Profile picture removed', '::1', '2026-08-08 12:04:28'),
(75, 9, 'account_updated', 'user', 9, 'Account information updated via settings.', '::1', '2026-08-08 12:08:27'),
(76, 9, 'email_changed', 'user', 9, 'Email address changed; verification reset.', '::1', '2026-08-08 12:08:27'),
(77, 9, 'account_updated', 'user', 9, 'Account information updated via settings.', '::1', '2026-08-08 12:08:37'),
(78, 9, 'email_changed', 'user', 9, 'Email address changed; verification reset.', '::1', '2026-08-08 12:08:37'),
(79, 9, 'account_updated', 'user', 9, 'Account information updated via settings.', '::1', '2026-08-08 12:08:53'),
(80, 9, 'account_updated', 'user', 9, 'Account information updated via settings.', '::1', '2026-08-08 12:09:09'),
(81, 9, 'account_updated', 'user', 9, 'Account information updated via settings.', '::1', '2026-08-08 12:09:21'),
(82, 9, 'account_updated', 'user', 9, 'Account information updated via settings.', '::1', '2026-08-08 12:09:31'),
(83, 9, 'verification_email_sent', 'user', 9, 'Verification email resent from settings.', '::1', '2026-08-08 12:10:08'),
(84, 9, 'password_changed', 'user', 9, 'Password changed from settings.', '::1', '2026-08-08 12:14:11'),
(85, 9, 'notification_preferences_updated', 'user', 9, 'Notification preferences saved.', '::1', '2026-08-08 12:15:26'),
(86, 9, 'notification_preferences_updated', 'user', 9, 'Notification preferences saved.', '::1', '2026-08-08 12:15:52'),
(87, 9, 'consent_withdrawn', 'consent', NULL, 'Consent withdrawn for: future_opportunities', '::1', '2026-08-08 12:16:24'),
(88, 9, 'consent_granted', 'consent', NULL, 'Consent granted for: future_opportunities', '::1', '2026-08-08 12:16:25'),
(89, 9, 'sessions_revoked', 'user', 9, 'Logged out other sessions.', '::1', '2026-08-08 12:18:55'),
(90, 1, 'Programme Created', 'programme', 1, 'Programme created', '::1', '2026-08-08 15:16:08'),
(91, 1, 'Programme Updated', 'programme', 1, 'Programme details updated', '::1', '2026-08-08 15:26:07'),
(92, 1, 'Programme Created', 'programme', 2, 'Programme created', '::1', '2026-08-08 15:29:38'),
(93, 1, 'Programme Created', 'programme', 3, 'Programme created', '::1', '2026-08-08 15:35:07'),
(94, 1, 'Programme Updated', 'programme', 3, 'Programme details updated', '::1', '2026-08-08 15:37:34'),
(95, 1, 'Programme Updated', 'programme', 2, 'Programme details updated', '::1', '2026-08-08 15:38:42'),
(96, 1, 'Programme Updated', 'programme', 1, 'Programme details updated', '::1', '2026-08-08 15:39:41'),
(97, 1, 'Programme Updated', 'programme', 2, 'Programme details updated', '::1', '2026-08-08 16:40:23'),
(98, 1, 'Cohort Created', 'cohort', 1, 'Cohort created under programme #1', '::1', '2026-08-08 16:49:09'),
(99, 1, 'Cohort Updated', 'cohort', 1, 'Cohort details updated', '::1', '2026-08-08 16:49:49'),
(100, 1, 'Programme Updated', 'programme', 2, 'Programme details updated', '::1', '2026-08-08 16:57:58'),
(101, 1, 'Cohort Updated', 'cohort', 1, 'Cohort details updated', '::1', '2026-08-08 17:00:50'),
(102, 1, 'Programme Updated', 'programme', 3, 'Programme details updated', '::1', '2026-08-08 17:14:12'),
(103, 1, 'Programme Duplicated', 'programme', 4, 'Duplicated from programme #1', '::1', '2026-08-08 17:14:41'),
(104, 1, 'Programme Archived', 'programme', 1, 'Status changed from active to archived', '::1', '2026-08-08 17:16:35'),
(105, 1, 'Programme Updated', 'programme', 4, 'Programme details updated', '::1', '2026-08-08 17:18:00'),
(106, 1, 'Programme Archived', 'programme', 4, 'Status changed from active to archived', '::1', '2026-08-08 17:18:23'),
(107, 1, 'Programme Updated', 'programme', 1, 'Programme details updated', '::1', '2026-08-08 17:19:21'),
(108, 9, 'profile_picture_uploaded', 'candidate_profile', NULL, 'Profile picture uploaded', '::1', '2026-08-08 17:23:30'),
(109, 1, 'Cohort Created', 'cohort', 3, 'Cohort created under programme #1', '::1', '2026-08-08 17:34:21'),
(110, 1, 'Evidence Requirement Added', 'cohort', 1, 'Added required document: CV', '::1', '2026-08-08 17:46:20'),
(111, 1, 'Evidence Requirement Added', 'cohort', 1, 'Added required document: ID Document', '::1', '2026-08-08 17:46:51'),
(112, 1, 'Evidence Requirement Added', 'cohort', 1, 'Added required document: Academic Transcript', '::1', '2026-08-08 17:47:01'),
(113, 1, 'Evidence Requirement Added', 'cohort', 1, 'Added required document: Qualification Certificate', '::1', '2026-08-08 17:47:26'),
(114, 1, 'Evidence Requirement Added', 'cohort', 1, 'Added required document: Proof of Residence', '::1', '2026-08-08 17:47:37'),
(115, 1, 'Evidence Requirement Removed', 'cohort', 1, 'Removed required document', '::1', '2026-08-08 17:47:47'),
(116, 1, 'Workflow Configuration Updated', 'cohort', 1, 'Cohort workflow stages updated', '::1', '2026-08-08 17:50:14'),
(117, 1, 'Workflow Configuration Updated', 'cohort', 1, 'Cohort workflow stages updated', '::1', '2026-08-08 17:59:04'),
(118, 1, 'Evidence Requirement Removed', 'cohort', 1, 'Removed required document', '::1', '2026-08-08 18:23:09'),
(119, 1, 'Evidence Requirement Added', 'cohort', 1, 'Added required document: Qualification Certificate', '::1', '2026-08-08 18:23:38'),
(120, 1, 'Workflow Configuration Updated', 'cohort', 1, 'Cohort workflow stages updated', '::1', '2026-08-08 18:25:23'),
(121, 1, 'Eligibility Updated', 'programme', 1, 'Programme eligibility requirements updated', '::1', '2026-08-09 11:15:44'),
(122, 1, 'Eligibility Updated', 'cohort', 1, 'Eligibility requirements updated', '::1', '2026-08-09 11:25:05'),
(123, 1, 'Eligibility Updated', 'cohort', 1, 'Eligibility requirements updated', '::1', '2026-08-09 11:27:32'),
(124, 1, 'Eligibility Updated', 'cohort', 1, 'Eligibility requirements updated', '::1', '2026-08-09 11:28:50'),
(125, 1, 'Cohort Skills Updated', 'cohort', 1, 'Cohort skills configuration updated', '::1', '2026-08-09 11:39:07'),
(126, 1, 'Eligibility Updated', 'cohort', 1, 'Eligibility requirements updated', '::1', '2026-08-09 11:44:13'),
(127, 1, 'Cohort Skills Updated', 'cohort', 1, 'Cohort skills configuration updated', '::1', '2026-08-09 11:44:51'),
(128, 1, 'Cohort Skills Updated', 'cohort', 1, 'Cohort skills configuration updated', '::1', '2026-08-09 11:56:26'),
(129, 1, 'Eligibility Updated', 'cohort', 1, 'Eligibility requirements updated', '::1', '2026-08-09 12:02:07'),
(130, 1, 'Eligibility Updated', 'programme', 1, 'Programme eligibility requirements updated', '::1', '2026-08-09 12:09:14'),
(131, 1, 'Eligibility Updated', 'programme', 1, 'Programme eligibility requirements updated', '::1', '2026-08-09 12:29:02'),
(132, 1, 'Eligibility Updated', 'programme', 1, 'Programme eligibility requirements updated', '::1', '2026-08-09 12:39:06'),
(133, 1, 'Eligibility Updated', 'programme', 1, 'Programme eligibility requirements updated', '::1', '2026-08-09 12:40:50'),
(134, 1, 'Evidence Requirement Added', 'cohort', 1, 'Added required document: Other supporting document', '::1', '2026-08-09 12:41:40'),
(135, 1, 'Evidence Requirement Removed', 'cohort', 1, 'Removed required document', '::1', '2026-08-09 12:41:53'),
(136, 1, 'Evidence Requirement Removed', 'cohort', 1, 'Removed required document', '::1', '2026-08-09 12:50:14'),
(137, 1, 'Evidence Requirement Added', 'cohort', 1, 'Added required document: Proof of Residence', '::1', '2026-08-09 12:50:42'),
(138, 1, 'Evidence Requirement Removed', 'cohort', 1, 'Removed required document', '::1', '2026-08-09 12:53:43'),
(139, 1, 'Evidence Requirement Added', 'cohort', 1, 'Added required document: Qualification Certificate', '::1', '2026-08-09 12:54:16'),
(140, 1, 'Evidence Requirement Removed', 'cohort', 1, 'Removed required document', '::1', '2026-08-09 12:54:25'),
(141, 1, 'Programme Skills Updated', 'programme', 1, 'Programme skills configuration updated', '::1', '2026-08-09 13:10:36'),
(142, 1, 'Cohort Updated', 'cohort', 1, 'Cohort details updated', '::1', '2026-08-09 13:14:42'),
(143, 1, 'Eligibility Updated', 'programme', 1, 'Programme eligibility requirements updated', '::1', '2026-08-09 13:33:37'),
(144, 1, 'Programme Updated', 'programme', 4, 'Programme details updated', '::1', '2026-08-09 13:35:18'),
(145, 1, 'Opportunity Created', 'opportunity', 1, 'Opportunity created', '::1', '2026-08-09 15:39:51'),
(146, 1, 'Opportunity Updated', 'opportunity', 1, 'Opportunity details updated', '::1', '2026-08-09 15:40:59'),
(147, 1, 'Opportunity Updated', 'opportunity', 1, 'Opportunity details updated', '::1', '2026-08-09 15:43:19'),
(148, 1, 'Opportunity Updated', 'opportunity', 1, 'Opportunity details updated', '::1', '2026-08-09 15:45:04'),
(149, 1, 'Opportunity Created', 'opportunity', 2, 'Opportunity created', '::1', '2026-08-09 16:11:30'),
(150, 1, 'Opportunity Updated', 'opportunity', 2, 'Opportunity details updated', '::1', '2026-08-09 16:12:31'),
(151, 1, 'Opportunity Updated', 'opportunity', 2, 'Opportunity details updated', '::1', '2026-08-09 16:13:07'),
(152, 1, 'Programme Updated', 'programme', 2, 'Programme details updated', '::1', '2026-08-09 16:14:02'),
(153, 1, 'Cohort Created', 'cohort', 4, 'Cohort created under programme #2', '::1', '2026-08-09 16:18:54'),
(154, 1, 'Eligibility Updated', 'cohort', 4, 'Eligibility requirements updated', '::1', '2026-08-09 16:21:22'),
(155, 1, 'Eligibility Updated', 'programme', 2, 'Programme eligibility requirements updated', '::1', '2026-08-09 16:23:33'),
(156, 1, 'Cohort Updated', 'cohort', 4, 'Cohort details updated', '::1', '2026-08-09 16:37:04'),
(157, 1, 'Cohort Updated', 'cohort', 1, 'Cohort details updated', '::1', '2026-08-09 19:13:26'),
(158, 1, 'Cohort Created', 'cohort', 5, 'Cohort created under programme #1', '::1', '2026-08-09 19:17:14'),
(159, 1, 'Programme Updated', 'programme', 1, 'Programme details updated', '::1', '2026-08-09 19:18:47'),
(160, 1, 'Cohort Updated', 'cohort', 1, 'Cohort details updated', '::1', '2026-08-09 19:19:44'),
(161, 1, 'Cohort Updated', 'cohort', 5, 'Cohort details updated', '::1', '2026-08-09 19:20:42'),
(162, 1, 'Cohort Updated', 'cohort', 4, 'Cohort details updated', '::1', '2026-08-09 19:24:09'),
(163, 1, 'Cohort Updated', 'cohort', 4, 'Cohort details updated', '::1', '2026-08-09 19:26:01'),
(164, 1, 'Cohort Updated', 'cohort', 1, 'Cohort details updated', '::1', '2026-08-09 19:27:03'),
(165, 1, 'Eligibility Updated', 'cohort', 5, 'Eligibility requirements updated', '::1', '2026-08-09 19:29:32'),
(166, 1, 'Eligibility Updated', 'programme', 1, 'Programme eligibility requirements updated', '::1', '2026-08-09 19:29:52'),
(167, 1, 'Opportunity Updated', 'opportunity', 2, 'Opportunity details updated', '::1', '2026-08-09 19:39:36'),
(168, 1, 'Cohort Updated', 'cohort', 4, 'Cohort details updated', '::1', '2026-08-09 19:41:12'),
(169, 1, 'Opportunity Updated', 'opportunity', 1, 'Opportunity details updated', '::1', '2026-08-09 19:42:37'),
(170, 1, 'Opportunity Updated', 'opportunity', 2, 'Opportunity details updated', '::1', '2026-08-09 19:45:32'),
(171, 1, 'Cohort Created', 'cohort', 6, 'Cohort created under programme #3', '::1', '2026-08-09 20:00:07'),
(172, 1, 'Opportunity Created', 'opportunity', 3, 'Opportunity created', '::1', '2026-08-09 20:21:41'),
(173, 1, 'Opportunity Updated', 'opportunity', 3, 'Opportunity details updated', '::1', '2026-08-09 20:23:01'),
(174, 1, 'Opportunity Updated', 'opportunity', 2, 'Opportunity details updated', '::1', '2026-08-09 20:25:06'),
(175, 1, 'Opportunity Updated', 'opportunity', 3, 'Opportunity details updated', '::1', '2026-08-09 20:27:16'),
(176, 1, 'Programme Updated', 'programme', 1, 'Programme details updated', '::1', '2026-08-12 06:55:17'),
(177, 1, 'Eligibility Updated', 'programme', 1, 'Programme eligibility requirements updated', '::1', '2026-08-12 06:58:48'),
(178, 1, 'Programme Skills Updated', 'programme', 1, 'Programme skills configuration updated', '::1', '2026-08-12 06:59:55'),
(179, 1, 'Cohort Updated', 'cohort', 1, 'Cohort details updated', '::1', '2026-08-12 07:07:32'),
(180, 1, 'Cohort Updated', 'cohort', 1, 'Cohort details updated', '::1', '2026-08-12 07:15:57'),
(181, 1, 'Evidence Requirement Added', 'cohort', 5, 'Added required document: CV', '::1', '2026-08-12 07:20:54'),
(182, 1, 'Evidence Requirement Added', 'cohort', 5, 'Added required document: ID Document', '::1', '2026-08-12 07:21:21'),
(183, 1, 'Evidence Requirement Removed', 'cohort', 5, 'Removed required document', '::1', '2026-08-12 07:21:30'),
(184, 1, 'Evidence Requirement Removed', 'cohort', 5, 'Removed required document', '::1', '2026-08-12 07:21:38'),
(185, 1, 'Workflow Configuration Updated', 'cohort', 5, 'Cohort workflow stages updated', '::1', '2026-08-12 07:22:32'),
(186, 1, 'Opportunity Updated', 'opportunity', 1, 'Opportunity details updated', '::1', '2026-08-13 13:04:07'),
(187, 9, 'profile_picture_removed', 'candidate_profile', NULL, 'Profile picture removed', '::1', '2026-08-13 13:37:20'),
(188, 1, 'Eligibility Updated', 'programme', 1, 'Programme eligibility requirements updated', '::1', '2026-08-17 07:29:57'),
(189, 1, 'Programme Skills Updated', 'programme', 1, 'Programme skills configuration updated', '::1', '2026-08-17 07:30:41'),
(190, 1, 'Programme Updated', 'programme', 1, 'Programme details updated', '::1', '2026-08-17 07:36:32'),
(191, 1, 'Programme Skills Updated', 'programme', 1, 'Programme skills configuration updated', '::1', '2026-08-17 07:56:45'),
(192, 1, 'Eligibility Updated', 'programme', 1, 'Programme eligibility requirements updated', '::1', '2026-08-17 08:17:21'),
(193, 1, 'Programme Skills Updated', 'programme', 1, 'Programme skills configuration updated', '::1', '2026-08-17 08:18:21'),
(194, 1, 'Evidence Requirement Added', 'cohort', 5, 'Added required document: CV', '::1', '2026-08-17 08:19:14'),
(195, 1, 'Evidence Requirement Added', 'cohort', 5, 'Added required document: ID Document', '::1', '2026-08-17 08:19:31'),
(196, 1, 'Evidence Requirement Added', 'cohort', 5, 'Added required document: Qualification Certificate', '::1', '2026-08-17 08:20:46'),
(197, 1, 'Workflow Configuration Updated', 'cohort', 5, 'Cohort workflow stages updated', '::1', '2026-08-17 08:23:25'),
(198, 9, 'profile_picture_uploaded', 'candidate_profile', NULL, 'Profile picture uploaded', '::1', '2026-08-19 09:49:41'),
(199, 9, 'profile_updated', 'candidate_profile', NULL, 'Personal information updated', '::1', '2026-08-19 09:50:59'),
(200, 9, 'profile_updated', 'candidate_profile', NULL, 'Professional information updated', '::1', '2026-08-19 09:52:30'),
(201, 9, 'qualification_removed', 'qualification', 2, 'Qualification removed', '::1', '2026-08-19 09:54:00'),
(202, 9, 'profile_picture_removed', 'candidate_profile', NULL, 'Profile picture removed', '::1', '2026-08-19 23:04:24'),
(203, 9, 'profile_updated', 'candidate_profile', NULL, 'Professional information updated', '::1', '2026-08-19 23:05:44');

-- --------------------------------------------------------

--
-- Table structure for table `availability_statuses`
--

CREATE TABLE `availability_statuses` (
  `id` int(10) UNSIGNED NOT NULL,
  `slug` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `availability_statuses`
--

INSERT INTO `availability_statuses` (`id`, `slug`, `label`, `description`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 'available_now', 'Available Now', 'Ready to start immediately', 1, 1, '2026-08-04 01:06:12'),
(2, 'available_from_date', 'Available From Date', 'Available to start from a specific date', 2, 1, '2026-08-04 01:06:12'),
(3, 'employed_open', 'Employed / Open', 'Currently employed but open to opportunities', 3, 1, '2026-08-04 01:06:12'),
(4, 'unavailable', 'Unavailable', 'Not currently available for opportunities', 4, 1, '2026-08-04 01:06:12'),
(5, 'do_not_contact', 'Do Not Contact', 'Do not contact for opportunities', 5, 1, '2026-08-04 01:06:12'),
(6, 'unknown_stale', 'Unknown / Stale', 'Availability status is unknown or outdated', 6, 1, '2026-08-04 01:06:12');

-- --------------------------------------------------------

--
-- Table structure for table `candidate_profiles`
--

CREATE TABLE `candidate_profiles` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `professional_title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `professional_summary` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `career_interests` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employment_status` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `availability_status_id` int(10) UNSIGNED DEFAULT NULL,
  `availability_date` date DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile_picture` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `completion_percent` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `candidate_profiles`
--

INSERT INTO `candidate_profiles` (`id`, `user_id`, `professional_title`, `professional_summary`, `career_interests`, `employment_status`, `availability_status_id`, `availability_date`, `address`, `city`, `profile_picture`, `completion_percent`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 9, 'Software Developer', 'I am a motivated Software Developer holding a Degree in Computer Science, with two years of hands-on experience developing web-based applications, databases, and software solutions. I have a strong understanding of Object-Oriented Programming principles and experience working with HTML, CSS, JavaScript, PHP, and MySQL in Agile development environments. I am passionate about learning new technologies, including Java and cloud platforms, and enjoy building reliable, scalable, and user-friendly software solutions while contributing effectively within collaborative development teams.', 'Back-end development', 'unemployed', 1, NULL, '1056 Madiba DR', 'Bronkhorstspruit', NULL, 89, 1, '2026-08-05 00:22:14', '2026-08-19 23:05:44'),
(2, 10, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, '2026-08-06 14:31:12', '2026-08-06 14:31:12');

-- --------------------------------------------------------

--
-- Table structure for table `candidate_saved_opportunities`
--

CREATE TABLE `candidate_saved_opportunities` (
  `id` int(10) UNSIGNED NOT NULL,
  `candidate_id` int(10) UNSIGNED NOT NULL,
  `opportunity_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `candidate_skills`
--

CREATE TABLE `candidate_skills` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `skill_id` int(10) UNSIGNED NOT NULL,
  `proficiency` enum('beginner','intermediate','advanced','expert') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'intermediate',
  `verification_status` enum('unverified','pending','verified','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unverified',
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `candidate_skills`
--

INSERT INTO `candidate_skills` (`id`, `user_id`, `skill_id`, `proficiency`, `verification_status`, `verified_at`, `created_at`) VALUES
(5, 9, 39, 'intermediate', 'unverified', NULL, '2026-08-06 13:51:27'),
(7, 9, 15, 'intermediate', 'unverified', NULL, '2026-08-06 13:52:18'),
(8, 9, 10, 'beginner', 'unverified', NULL, '2026-08-06 14:15:40'),
(9, 9, 1, 'advanced', 'unverified', NULL, '2026-08-06 14:15:57'),
(10, 9, 4, 'expert', 'unverified', NULL, '2026-08-06 14:16:30'),
(11, 9, 40, 'advanced', 'unverified', NULL, '2026-08-06 14:16:57'),
(12, 9, 38, 'advanced', 'unverified', NULL, '2026-08-06 14:17:13'),
(13, 9, 47, 'intermediate', 'unverified', NULL, '2026-08-06 14:17:46');

-- --------------------------------------------------------

--
-- Table structure for table `certifications`
--

CREATE TABLE `certifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `issuing_organisation` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year_obtained` year(4) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `credential_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verification_status` enum('unverified','pending','verified','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unverified',
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `certifications`
--

INSERT INTO `certifications` (`id`, `user_id`, `name`, `issuing_organisation`, `year_obtained`, `expiry_date`, `credential_id`, `verification_status`, `verified_at`, `created_at`, `updated_at`) VALUES
(1, 9, 'A+', 'CompTIA', 2025, '2027-12-07', 'CompTIA-1234', 'unverified', NULL, '2026-08-07 09:18:04', '2026-08-07 09:43:49');

-- --------------------------------------------------------

--
-- Table structure for table `cohorts`
--

CREATE TABLE `cohorts` (
  `id` int(10) UNSIGNED NOT NULL,
  `programme_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `application_open_date` date DEFAULT NULL,
  `application_close_date` date DEFAULT NULL,
  `max_capacity` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `applications_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `location` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_mode` enum('on_site','remote','hybrid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'hybrid',
  `status` enum('draft','open','closed','active','completed','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cohorts`
--

INSERT INTO `cohorts` (`id`, `programme_id`, `name`, `description`, `start_date`, `end_date`, `application_open_date`, `application_close_date`, `max_capacity`, `applications_count`, `location`, `province`, `delivery_mode`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'Software Development Programme - Pretoria', 'This cohort is designed for aspiring software developers who are looking to strengthen their technical skills through practical, project-based learning. Participants will gain hands-on experience in developing and maintaining web applications while working with technologies such as HTML, CSS, JavaScript, PHP, and MySQL.\r\nThroughout the programme, participants will work on real-world development tasks, practise database management, use Git for version control, and apply software development principles including debugging, testing, problem-solving, and secure coding practices. The cohort also focuses on professional development, teamwork, communication, and preparing participants for successful careers in the technology industry.', '2026-09-07', '2028-08-31', '2026-08-03', '2026-08-14', 15, 0, 'Pretoria', 'gauteng', 'hybrid', 'active', 1, '2026-08-08 16:49:09', '2026-08-12 07:15:57'),
(2, 4, '2026 Software Development Cohort', 'This cohort is designed for aspiring software developers who are looking to strengthen their technical skills through practical, project-based learning. Participants will gain hands-on experience in developing and maintaining web applications while working with technologies such as HTML, CSS, JavaScript, PHP, and MySQL.\r\nThroughout the programme, participants will work on real-world development tasks, practise database management, use Git for version control, and apply software development principles including debugging, testing, problem-solving, and secure coding practices. The cohort also focuses on professional development, teamwork, communication, and preparing participants for successful careers in the technology industry.', '2026-09-07', '2028-09-29', '2026-08-03', '2026-08-14', 10, 0, 'Pretoria', 'gauteng', 'hybrid', 'draft', 1, '2026-08-08 17:14:40', '2026-08-08 17:14:40'),
(4, 2, '2026/2027 IT Support & Technical Services Intake', 'This cohort is designed for recent IT graduates and aspiring technology professionals seeking practical workplace experience and professional development. Participants will gain hands-on exposure to areas such as software development, IT support, databases, networking, cybersecurity, and general technical services. Through structured training, mentorship, practical projects, and workplace activities, participants will have the opportunity to apply their academic knowledge in real-world environments while developing technical, problem-solving, communication, and teamwork skills. The programme aims to prepare participants for successful careers in the technology industry by providing practical experience and continuous professional development.', '2026-09-07', '2027-08-31', '2026-08-03', '2026-08-14', 12, 0, 'Pretoria', 'gauteng', 'on_site', 'active', 1, '2026-08-09 16:18:54', '2026-08-09 19:41:12'),
(5, 1, 'Software Development Programme - Mbombela', 'This cohort is designed for aspiring software developers who are looking to strengthen their technical skills through practical, project-based learning. Participants will gain hands-on experience in developing and maintaining web applications while working with technologies such as HTML, CSS, JavaScript, PHP, and MySQL.\r\n\r\nThroughout the programme, participants will work on real-world development tasks, practise database management, use Git for version control, and apply software development principles including debugging, testing, problem-solving, and secure coding practices. The cohort also focuses on professional development, teamwork, communication, and preparing participants for successful careers in the technology industry.', '2026-09-07', '2028-08-31', '2026-08-03', '2026-08-14', 10, 0, 'Mbombela', 'mpumalanga', 'hybrid', 'active', 1, '2026-08-09 19:17:13', '2026-08-09 19:20:42'),
(6, 3, '2027 Software Development WIL Programme Intake', '2027 Software Development WIL Programme Intake is designed to provide students with practical, hands-on experience in software development within a professional working environment. The cohort focuses on applying academic knowledge to real-world projects while developing technical, problem-solving, teamwork, and professional skills.', '2027-01-11', '2027-06-30', '2026-08-10', '2026-10-30', 15, 0, 'Pretoria', 'gauteng', 'on_site', 'active', 1, '2026-08-09 20:00:06', '2026-08-09 20:00:06');

-- --------------------------------------------------------

--
-- Table structure for table `cohort_documents`
--

CREATE TABLE `cohort_documents` (
  `id` int(10) UNSIGNED NOT NULL,
  `cohort_id` int(10) UNSIGNED NOT NULL,
  `document_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `verification_required` tinyint(1) NOT NULL DEFAULT 0,
  `expiry_required` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cohort_documents`
--

INSERT INTO `cohort_documents` (`id`, `cohort_id`, `document_name`, `is_required`, `verification_required`, `expiry_required`, `created_at`) VALUES
(1, 1, 'CV', 1, 0, 0, '2026-08-08 17:46:20'),
(2, 1, 'ID Document', 1, 0, 0, '2026-08-08 17:46:51'),
(3, 1, 'Academic Transcript', 1, 0, 0, '2026-08-08 17:47:01'),
(17, 5, 'CV', 1, 0, 0, '2026-08-17 08:19:14'),
(18, 5, 'ID Document', 1, 0, 0, '2026-08-17 08:19:31'),
(19, 5, 'Qualification Certificate', 1, 0, 0, '2026-08-17 08:20:46');

-- --------------------------------------------------------

--
-- Table structure for table `cohort_eligibility`
--

CREATE TABLE `cohort_eligibility` (
  `id` int(10) UNSIGNED NOT NULL,
  `cohort_id` int(10) UNSIGNED NOT NULL,
  `qualification_level` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qualification_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field_of_study` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `institution_requirements` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `min_completion_year` year(4) DEFAULT NULL,
  `max_completion_year` year(4) DEFAULT NULL,
  `min_experience` int(10) UNSIGNED DEFAULT NULL,
  `max_experience` int(10) UNSIGNED DEFAULT NULL,
  `province` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_restrictions` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `availability` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `citizenship_residency` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `programme_specific` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cohort_eligibility`
--

INSERT INTO `cohort_eligibility` (`id`, `cohort_id`, `qualification_level`, `qualification_name`, `field_of_study`, `institution_requirements`, `min_completion_year`, `max_completion_year`, `min_experience`, `max_experience`, `province`, `city`, `location_restrictions`, `availability`, `citizenship_residency`, `programme_specific`, `created_at`, `updated_at`) VALUES
(1, 1, 'diploma', 'Diploma Information Technology', 'Information Technology', '', 2000, 2025, 0, 0, 'gauteng', 'Pretoria', 'Relocate at your own cost', 'Currently available', 'South African citizens only', 'Graduated in the last 3 years\r\nAge range: 18 - 35\r\nNever participated in a graduate programme', '2026-08-09 11:25:04', '2026-08-09 12:02:07'),
(2, 4, 'diploma', 'Diploma Information Technology', 'Information Technology', '', NULL, NULL, NULL, NULL, 'gauteng', 'Pretoria', '', 'Currently available', '', '', '2026-08-09 16:21:22', '2026-08-09 16:21:22'),
(3, 5, 'diploma', 'Diploma Information Technology', 'Information Technology', '', 2000, 2025, 0, 0, 'gauteng', 'Pretoria', 'Relocate at your own cost', 'Currently available', 'South African citizens only', '', '2026-08-09 19:17:14', '2026-08-09 19:29:32');

-- --------------------------------------------------------

--
-- Table structure for table `cohort_participants`
--

CREATE TABLE `cohort_participants` (
  `id` int(10) UNSIGNED NOT NULL,
  `cohort_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `status` enum('selected','onboarded','active','completed','withdrawn') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'selected',
  `selected_at` datetime DEFAULT NULL,
  `onboarded_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cohort_skills`
--

CREATE TABLE `cohort_skills` (
  `id` int(10) UNSIGNED NOT NULL,
  `cohort_id` int(10) UNSIGNED NOT NULL,
  `skill_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `skill_category` enum('required_technical','preferred_technical','required_soft') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'required_technical',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cohort_skills`
--

INSERT INTO `cohort_skills` (`id`, `cohort_id`, `skill_name`, `skill_category`, `created_at`) VALUES
(15, 2, 'CSS', 'required_technical', '2026-08-08 17:14:41'),
(16, 2, 'HTML', 'required_technical', '2026-08-08 17:14:41'),
(17, 2, 'JavaScript', 'required_technical', '2026-08-08 17:14:41'),
(18, 2, 'PHP', 'required_technical', '2026-08-08 17:14:41'),
(19, 2, 'React JS', 'required_technical', '2026-08-08 17:14:41'),
(20, 2, 'SQL', 'required_technical', '2026-08-08 17:14:41'),
(83, 1, 'HTML/CSS', 'required_technical', '2026-08-09 11:56:26'),
(84, 1, 'JavaScript', 'required_technical', '2026-08-09 11:56:26'),
(85, 1, 'PHP', 'required_technical', '2026-08-09 11:56:26'),
(86, 1, 'SQL', 'required_technical', '2026-08-09 11:56:26'),
(87, 1, 'React JS', 'preferred_technical', '2026-08-09 11:56:26'),
(88, 1, 'Python', 'preferred_technical', '2026-08-09 11:56:26'),
(89, 1, 'SQL', 'preferred_technical', '2026-08-09 11:56:26'),
(90, 1, 'Effective communication', 'required_soft', '2026-08-09 11:56:26'),
(91, 1, 'Problem solving', 'required_soft', '2026-08-09 11:56:26'),
(92, 1, 'Team player', 'required_soft', '2026-08-09 11:56:26'),
(93, 1, 'Eager to learn', 'required_soft', '2026-08-09 11:56:26');

-- --------------------------------------------------------

--
-- Table structure for table `cohort_workflow`
--

CREATE TABLE `cohort_workflow` (
  `id` int(10) UNSIGNED NOT NULL,
  `cohort_id` int(10) UNSIGNED NOT NULL,
  `stage` enum('application','eligibility_review','screening','assessment','interview','selection','onboarding','active_participant') COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cohort_workflow`
--

INSERT INTO `cohort_workflow` (`id`, `cohort_id`, `stage`, `is_active`, `sort_order`, `created_at`) VALUES
(1, 1, 'application', 1, 1, '2026-08-08 16:49:09'),
(2, 1, 'eligibility_review', 0, 2, '2026-08-08 16:49:09'),
(3, 1, 'screening', 0, 3, '2026-08-08 16:49:09'),
(4, 1, 'assessment', 0, 4, '2026-08-08 16:49:09'),
(5, 1, 'interview', 1, 5, '2026-08-08 16:49:09'),
(6, 1, 'selection', 1, 6, '2026-08-08 16:49:09'),
(7, 1, 'onboarding', 1, 7, '2026-08-08 16:49:09'),
(8, 1, 'active_participant', 1, 8, '2026-08-08 16:49:09'),
(9, 2, 'application', 1, 1, '2026-08-08 17:14:41'),
(10, 2, 'eligibility_review', 1, 2, '2026-08-08 17:14:41'),
(11, 2, 'screening', 1, 3, '2026-08-08 17:14:41'),
(12, 2, 'assessment', 1, 4, '2026-08-08 17:14:41'),
(13, 2, 'interview', 1, 5, '2026-08-08 17:14:41'),
(14, 2, 'selection', 1, 6, '2026-08-08 17:14:41'),
(15, 2, 'onboarding', 1, 7, '2026-08-08 17:14:41'),
(16, 2, 'active_participant', 1, 8, '2026-08-08 17:14:41'),
(81, 4, 'application', 1, 1, '2026-08-09 16:18:54'),
(82, 4, 'eligibility_review', 1, 2, '2026-08-09 16:18:54'),
(83, 4, 'screening', 1, 3, '2026-08-09 16:18:54'),
(84, 4, 'assessment', 1, 4, '2026-08-09 16:18:54'),
(85, 4, 'interview', 1, 5, '2026-08-09 16:18:54'),
(86, 4, 'selection', 1, 6, '2026-08-09 16:18:54'),
(87, 4, 'onboarding', 1, 7, '2026-08-09 16:18:54'),
(88, 4, 'active_participant', 1, 8, '2026-08-09 16:18:54'),
(89, 5, 'application', 1, 1, '2026-08-09 19:17:14'),
(90, 5, 'eligibility_review', 1, 2, '2026-08-09 19:17:14'),
(91, 5, 'screening', 0, 3, '2026-08-09 19:17:14'),
(92, 5, 'assessment', 0, 4, '2026-08-09 19:17:14'),
(93, 5, 'interview', 1, 5, '2026-08-09 19:17:14'),
(94, 5, 'selection', 1, 6, '2026-08-09 19:17:14'),
(95, 5, 'onboarding', 1, 7, '2026-08-09 19:17:14'),
(96, 5, 'active_participant', 1, 8, '2026-08-09 19:17:14'),
(97, 6, 'application', 1, 1, '2026-08-09 20:00:06'),
(98, 6, 'eligibility_review', 1, 2, '2026-08-09 20:00:06'),
(99, 6, 'screening', 1, 3, '2026-08-09 20:00:06'),
(100, 6, 'assessment', 1, 4, '2026-08-09 20:00:06'),
(101, 6, 'interview', 1, 5, '2026-08-09 20:00:06'),
(102, 6, 'selection', 1, 6, '2026-08-09 20:00:06'),
(103, 6, 'onboarding', 1, 7, '2026-08-09 20:00:06'),
(104, 6, 'active_participant', 1, 8, '2026-08-09 20:00:06');

-- --------------------------------------------------------

--
-- Table structure for table `consents`
--

CREATE TABLE `consents` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `purpose` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('granted','withdrawn') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'granted',
  `granted_at` datetime DEFAULT NULL,
  `withdrawn_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `consents`
--

INSERT INTO `consents` (`id`, `user_id`, `purpose`, `status`, `granted_at`, `withdrawn_at`, `created_at`, `updated_at`) VALUES
(1, 9, 'programme_administration', 'granted', '2026-08-07 12:28:40', NULL, '2026-08-07 09:58:58', '2026-08-07 10:28:40'),
(2, 9, 'future_opportunities', 'granted', '2026-08-08 14:16:25', NULL, '2026-08-07 09:59:02', '2026-08-08 12:16:25'),
(7, 9, 'client_submission', 'granted', '2026-08-07 12:28:47', NULL, '2026-08-07 09:59:46', '2026-08-07 10:28:47'),
(14, 9, 'talent_pool', 'withdrawn', '2026-08-07 12:27:27', '2026-08-07 12:27:31', '2026-08-07 10:27:27', '2026-08-07 10:27:31');

-- --------------------------------------------------------

--
-- Table structure for table `deletion_requests`
--

CREATE TABLE `deletion_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `status` enum('pending','processing','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `document_type` enum('cv','qualification','supporting') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'supporting',
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `file_checksum` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uploaded_by` int(10) UNSIGNED NOT NULL,
  `verification_status` enum('unverified','pending','verified','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unverified',
  `expiry_date` date DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`id`, `user_id`, `document_type`, `original_filename`, `stored_filename`, `mime_type`, `file_size`, `file_checksum`, `uploaded_by`, `verification_status`, `expiry_date`, `uploaded_at`, `created_at`, `updated_at`) VALUES
(2, 9, 'supporting', 'ID Copy.pdf', '3078fcfcac5842fdfa905ddd0387d9cf.pdf', 'application/pdf', 543115, 'a24e5cf2960adcb08232eaec7d3d4c2e2f49a97f30ce5f532c1f1266ce11be38', 9, 'unverified', NULL, '2026-08-06 13:24:50', '2026-08-06 13:24:50', '2026-08-06 13:24:50'),
(5, 9, 'cv', 'Hlobisile_Mathebula_CV.pdf', '16e385092df39094f62fe5e8fb0a4e5a.pdf', 'application/pdf', 146033, '96e6916c4ae17d9061e7abc175e5b744c6259040c7fb67893e027bf6452f2607', 9, 'unverified', NULL, '2026-08-07 10:44:05', '2026-08-07 10:44:05', '2026-08-07 10:44:55');

-- --------------------------------------------------------

--
-- Table structure for table `email_verifications`
--

CREATE TABLE `email_verifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token_hash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `email_verifications`
--

INSERT INTO `email_verifications` (`id`, `user_id`, `token_hash`, `expires_at`, `used_at`, `created_at`) VALUES
(1, 10, '0e0a14b6986fbb2d6cbc1247d5e55e86e9e3689a847086187e8e2f9afd8a5c53', '2026-08-05 02:12:29', NULL, '2026-08-04 00:12:29'),
(2, 9, 'c805ab866437e624ee24d5a10a9629f571cfd768ba4ac78644b1577b7c0c2bbb', '2026-08-09 14:10:08', NULL, '2026-08-08 12:10:08');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `successful` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_preferences`
--

CREATE TABLE `notification_preferences` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `email_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notification_preferences`
--

INSERT INTO `notification_preferences` (`id`, `user_id`, `category`, `enabled`, `email_enabled`, `updated_at`) VALUES
(1, 9, 'applications', 1, 1, '2026-08-08 12:15:52'),
(2, 9, 'programmes', 1, 1, '2026-08-08 12:15:52'),
(3, 9, 'interviews', 1, 1, '2026-08-08 12:15:52'),
(4, 9, 'opportunities', 1, 1, '2026-08-08 12:15:52'),
(5, 9, 'talent_pool', 0, 0, '2026-08-08 12:15:52'),
(6, 9, 'learning', 1, 1, '2026-08-08 12:15:52'),
(7, 9, 'system', 1, 1, '2026-08-08 12:15:52');

-- --------------------------------------------------------

--
-- Table structure for table `opportunities`
--

CREATE TABLE `opportunities` (
  `id` int(10) UNSIGNED NOT NULL,
  `programme_id` int(10) UNSIGNED NOT NULL,
  `cohort_id` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('graduate_programme','internship','learnership','wil','skills_development','mentorship','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `organisation` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `short_description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `full_description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `application_open_date` date DEFAULT NULL,
  `application_close_date` date DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `available_positions` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `applications_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `min_age` int(10) UNSIGNED DEFAULT NULL,
  `max_age` int(10) UNSIGNED DEFAULT NULL,
  `province` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `physical_location` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `work_arrangement` enum('on_site','remote','hybrid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'hybrid',
  `status` enum('draft','published','closing_soon','closed','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `opportunities`
--

INSERT INTO `opportunities` (`id`, `programme_id`, `cohort_id`, `title`, `type`, `organisation`, `short_description`, `full_description`, `application_open_date`, `application_close_date`, `start_date`, `end_date`, `available_positions`, `applications_count`, `min_age`, `max_age`, `province`, `city`, `physical_location`, `work_arrangement`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'Software Developer Intern', 'graduate_programme', 'Investhood IT (Pty) Ltd', 'Entry-level opportunity for an aspiring software developer to gain practical experience in web application development, databases, software testing, and version control while working alongside experienced developers.', 'We are looking for a motivated and enthusiastic Software Developer Intern to join our development team. The successful candidate will gain hands-on experience in designing, developing, testing, and maintaining software applications. This opportunity is ideal for a recent graduate or aspiring developer looking to strengthen their technical skills and gain exposure to professional software development practices.', '2026-08-03', '2026-08-15', '2026-09-01', '2028-09-29', 10, 0, 18, 35, 'gauteng', 'Pretoria', '12 Tech Park, Pretoria', 'hybrid', 'published', 1, '2026-08-09 15:39:50', '2026-08-13 13:04:07'),
(2, 2, NULL, 'IT Technical Support Intern', 'graduate_programme', 'Investhood IT (Pty) Ltd', 'Entry-level opportunity for an aspiring IT professional to gain hands-on experience in technical support, hardware and software troubleshooting, system administration, networking, and end-user support.', 'We are seeking a motivated and customer-focused IT Support & Technical Services Intern to join our IT team. The intern will assist with providing technical support to users, troubleshooting hardware and software issues, maintaining IT equipment, and supporting the day-to-day operation of IT systems.\r\n\r\nThis programme provides practical exposure to IT support operations while allowing the intern to develop technical, communication, problem-solving, and customer-service skills under the guidance of experienced IT professionals.', '2026-08-03', '2026-08-14', '2026-09-07', '2027-08-31', 10, 0, 18, 35, 'gauteng', 'Pretoria', '12 Tech Park, Pretoria', 'on_site', 'published', 1, '2026-08-09 16:11:29', '2026-08-09 20:25:06'),
(3, 3, 6, 'Software Developer Intern', 'wil', 'Investhood IT (Pty) Ltd', 'A Software Developer Intern opportunity for students seeking practical experience in web application development. The successful candidates will work on real-world software projects while gaining experience in frontend and backend development, databases, debugging, version control, and software development practices.', NULL, '2026-08-10', '2026-10-30', '2027-01-11', '2027-06-30', 15, 0, 18, 35, 'mpumalanga', 'Mbombela', '23 Tech Park, Mbombela', 'on_site', 'published', 1, '2026-08-09 20:21:41', '2026-08-09 20:27:16');

-- --------------------------------------------------------

--
-- Table structure for table `opportunity_applications`
--

CREATE TABLE `opportunity_applications` (
  `id` int(10) UNSIGNED NOT NULL,
  `candidate_id` int(10) UNSIGNED NOT NULL,
  `opportunity_id` int(10) UNSIGNED NOT NULL,
  `status` enum('draft','submitted') NOT NULL DEFAULT 'draft',
  `application_data` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `opportunity_documents`
--

CREATE TABLE `opportunity_documents` (
  `id` int(10) UNSIGNED NOT NULL,
  `opportunity_id` int(10) UNSIGNED NOT NULL,
  `document_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `opportunity_documents`
--

INSERT INTO `opportunity_documents` (`id`, `opportunity_id`, `document_name`, `is_required`, `created_at`) VALUES
(44, 2, 'CV', 1, '2026-08-09 20:25:06'),
(45, 2, 'Qualification Certificate', 1, '2026-08-09 20:25:06'),
(46, 2, 'ID', 1, '2026-08-09 20:25:06'),
(47, 3, 'CV', 1, '2026-08-09 20:27:16'),
(48, 3, 'ID', 0, '2026-08-09 20:27:16'),
(49, 3, 'Academic Record', 1, '2026-08-09 20:27:16'),
(50, 3, 'Recommendation Letter (WIL Letter)', 1, '2026-08-09 20:27:16'),
(51, 1, 'CV', 1, '2026-08-13 13:04:07'),
(52, 1, 'Qualification Certificate', 1, '2026-08-13 13:04:07'),
(53, 1, 'ID', 1, '2026-08-13 13:04:07'),
(54, 1, 'Academic Record', 1, '2026-08-13 13:04:07');

-- --------------------------------------------------------

--
-- Table structure for table `opportunity_eligibility`
--

CREATE TABLE `opportunity_eligibility` (
  `id` int(10) UNSIGNED NOT NULL,
  `opportunity_id` int(10) UNSIGNED NOT NULL,
  `qualification_requirements` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `required_skills` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `preferred_skills` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `min_experience` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `availability_requirements` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `other_requirements` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `opportunity_eligibility`
--

INSERT INTO `opportunity_eligibility` (`id`, `opportunity_id`, `qualification_requirements`, `required_skills`, `preferred_skills`, `min_experience`, `availability_requirements`, `other_requirements`, `created_at`, `updated_at`) VALUES
(1, 1, 'Diploma or Degree in Information Technology, Computer Science, Software Development, or a related field. Recent graduate or currently completing a relevant qualification. Basic understanding of software development principles. Academic or personal project experience in software development is advantageous.', 'HTML/CSS, JavaScript fundamentals, PHP or another backend programming language, SQL, relational databases, basic understanding of MySQL, Git and GitHub, problem-solving, analytical skills, basic debugging and troubleshooting, ability to work effectively in a team', 'Experience with PHP and MySQL, knowledge of REST APIs, familiarity with JavaScript frameworks or libraries, understanding of responsive web design, basic knowledge of software testing, familiarity with MVC architecture, experience using XAMPP or similar local development environments', '0', 'Currently available', 'Strong willingness to learn and develop professionally. Good communication and interpersonal skills. Ability to work independently and collaboratively. Good attention to detail. Ability to meet deadlines. Must be willing to participate in training and development activities.', '2026-08-09 15:39:50', '2026-08-13 13:04:07'),
(2, 2, 'Diploma or Degree in Information Technology, Computer Science, Information Systems, or a related field. Recent graduate or currently completing a relevant qualification. Basic understanding of computer hardware, software, and operating systems. Basic understanding of networking concepts is advantageous.', 'Windows operating systems, Hardware and software troubleshooting, Microsoft Office applications, Basic networking knowledge, Computer installation and configuration, Technical problem-solving, Good communication skills, Customer service and user support, Basic understanding of cybersecurity, Ability to document and track technical issues', 'Basic knowledge of Linux, Experience with IT helpdesk or ticketing systems, Basic network troubleshooting, Familiarity with Active Directory, Basic knowledge of printers and peripheral devices, Familiarity with remote support tools, Basic knowledge of system administration, Understanding of IT security and access management', '0 - 1 year', 'Currently available', 'Strong willingness to learn and develop technical skills. Good interpersonal and communication skills. Ability to work under pressure and meet deadlines. Strong attention to detail. Ability to work independently and as part of a team. Professional and customer-focused attitude. Willingness to assist users with varying levels of technical knowledge.', '2026-08-09 16:11:30', '2026-08-09 20:25:06'),
(3, 3, 'Currently studying towards a Diploma/Degree in Information Technology, Computer Science, or Software Development.', 'Basic knowledge of HTML, CSS, JavaScript, PHP, and SQL. Understanding of databases and software development concepts. Good problem-solving and communication skills. Ability to work effectively in a team.', NULL, NULL, NULL, NULL, '2026-08-09 20:21:41', '2026-08-09 20:27:16');

-- --------------------------------------------------------

--
-- Table structure for table `opportunity_questions`
--

CREATE TABLE `opportunity_questions` (
  `id` int(10) UNSIGNED NOT NULL,
  `opportunity_id` int(10) UNSIGNED NOT NULL,
  `section` enum('eligibility','application') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'application',
  `question_text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `question_type` enum('text','textarea','yes_no','radio','dropdown','checkbox','number','date') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `options` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'JSON array of options for radio/dropdown/checkbox',
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `is_knockout` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Eligibility questions that may knock out',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `opportunity_responsibilities`
--

CREATE TABLE `opportunity_responsibilities` (
  `id` int(10) UNSIGNED NOT NULL,
  `opportunity_id` int(10) UNSIGNED NOT NULL,
  `type` enum('key_responsibilities','duties','programme_activities','learning_outcomes') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'key_responsibilities',
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `opportunity_responsibilities`
--

INSERT INTO `opportunity_responsibilities` (`id`, `opportunity_id`, `type`, `content`, `sort_order`, `created_at`) VALUES
(473, 2, 'key_responsibilities', 'Provide first-line technical support to employees and users.', 0, '2026-08-09 20:25:06'),
(474, 2, 'key_responsibilities', 'Troubleshoot hardware, software, and connectivity issues.', 1, '2026-08-09 20:25:06'),
(475, 2, 'key_responsibilities', 'Install, configure, and maintain computers and IT equipment.', 2, '2026-08-09 20:25:06'),
(476, 2, 'key_responsibilities', 'Assist with setting up user accounts and system access.', 3, '2026-08-09 20:25:06'),
(477, 2, 'key_responsibilities', 'Respond to IT support requests and document resolutions.', 4, '2026-08-09 20:25:06'),
(478, 2, 'key_responsibilities', 'Assist with network and connectivity troubleshooting.', 5, '2026-08-09 20:25:06'),
(479, 2, 'key_responsibilities', 'Support the maintenance of printers, scanners, and other peripherals.', 6, '2026-08-09 20:25:06'),
(480, 2, 'key_responsibilities', 'Assist with software installations and updates.', 7, '2026-08-09 20:25:06'),
(481, 2, 'key_responsibilities', 'Escalate complex technical issues to senior IT staff.', 8, '2026-08-09 20:25:06'),
(482, 2, 'key_responsibilities', 'Follow IT security, backup, and maintenance procedures.', 9, '2026-08-09 20:25:06'),
(483, 2, 'duties', 'Diagnose and resolve basic technical problems.', 0, '2026-08-09 20:25:06'),
(484, 2, 'duties', 'Install and configure operating systems and applications.', 1, '2026-08-09 20:25:06'),
(485, 2, 'duties', 'Set up workstations, laptops, printers, and other devices.', 2, '2026-08-09 20:25:06'),
(486, 2, 'duties', 'Assist users with Microsoft Office and other business applications.', 3, '2026-08-09 20:25:06'),
(487, 2, 'duties', 'Maintain an inventory of IT hardware and equipment.', 4, '2026-08-09 20:25:06'),
(488, 2, 'duties', 'Record and update support tickets.', 5, '2026-08-09 20:25:06'),
(489, 2, 'duties', 'Perform routine system maintenance.', 6, '2026-08-09 20:25:06'),
(490, 2, 'duties', 'Assist with network troubleshooting and connectivity checks.', 7, '2026-08-09 20:25:06'),
(491, 2, 'duties', 'Support user onboarding and offboarding processes.', 8, '2026-08-09 20:25:06'),
(492, 2, 'duties', 'Maintain technical documentation and support guides.', 9, '2026-08-09 20:25:06'),
(493, 2, 'duties', 'Assist with IT audits and equipment checks.', 10, '2026-08-09 20:25:06'),
(494, 2, 'duties', 'Follow company IT policies and security procedures.', 11, '2026-08-09 20:25:06'),
(495, 2, 'programme_activities', 'Participate in IT support and technical-services training.', 0, '2026-08-09 20:25:06'),
(496, 2, 'programme_activities', 'Provide supervised first-line support to users.', 1, '2026-08-09 20:25:06'),
(497, 2, 'programme_activities', 'Perform hardware and software troubleshooting exercises.', 2, '2026-08-09 20:25:06'),
(498, 2, 'programme_activities', 'Assist with computer and workstation setup.', 3, '2026-08-09 20:25:06'),
(499, 2, 'programme_activities', 'Participate in network troubleshooting activities.', 4, '2026-08-09 20:25:06'),
(500, 2, 'programme_activities', 'Gain practical experience with IT ticketing systems.', 5, '2026-08-09 20:25:06'),
(501, 2, 'programme_activities', 'Participate in user onboarding and technical support activities.', 6, '2026-08-09 20:25:06'),
(502, 2, 'programme_activities', 'Assist with IT equipment inventory and asset management.', 7, '2026-08-09 20:25:06'),
(503, 2, 'programme_activities', 'Attend cybersecurity awareness and IT best-practice sessions.', 8, '2026-08-09 20:25:06'),
(504, 2, 'programme_activities', 'Participate in professional development and workplace-readiness activities.', 9, '2026-08-09 20:25:06'),
(505, 2, 'programme_activities', 'Receive mentorship from experienced IT support professionals.', 10, '2026-08-09 20:25:06'),
(506, 2, 'learning_outcomes', 'Diagnose and resolve common hardware and software issues.', 0, '2026-08-09 20:25:06'),
(507, 2, 'learning_outcomes', 'Install and configure computers and software applications.', 1, '2026-08-09 20:25:06'),
(508, 2, 'learning_outcomes', 'Provide professional first-line technical support.', 2, '2026-08-09 20:25:06'),
(509, 2, 'learning_outcomes', 'Troubleshoot basic network and connectivity problems.', 3, '2026-08-09 20:25:06'),
(510, 2, 'learning_outcomes', 'Use IT ticketing systems to record and manage support requests.', 4, '2026-08-09 20:25:06'),
(511, 2, 'learning_outcomes', 'Understand basic system administration processes.', 5, '2026-08-09 20:25:06'),
(512, 2, 'learning_outcomes', 'Apply basic cybersecurity and IT security practices.', 6, '2026-08-09 20:25:06'),
(513, 2, 'learning_outcomes', 'Manage and maintain IT equipment and assets.', 7, '2026-08-09 20:25:06'),
(514, 2, 'learning_outcomes', 'Communicate effectively with technical and non-technical users.', 8, '2026-08-09 20:25:06'),
(515, 2, 'learning_outcomes', 'Document technical issues and their resolutions.', 9, '2026-08-09 20:25:06'),
(516, 2, 'learning_outcomes', 'Understand IT support workflows and escalation procedures.', 10, '2026-08-09 20:25:06'),
(517, 2, 'learning_outcomes', 'Apply practical IT knowledge in a professional workplace environment.', 11, '2026-08-09 20:25:06'),
(518, 3, 'key_responsibilities', 'Assist with developing and maintaining web applications.', 0, '2026-08-09 20:27:16'),
(519, 3, 'key_responsibilities', 'Write and maintain HTML, CSS, JavaScript, PHP, and SQL code.', 1, '2026-08-09 20:27:16'),
(520, 3, 'key_responsibilities', 'Assist with database development and management.', 2, '2026-08-09 20:27:16'),
(521, 3, 'key_responsibilities', 'Test and debug applications.', 3, '2026-08-09 20:27:16'),
(522, 3, 'key_responsibilities', 'Participate in software development and team meetings.', 4, '2026-08-09 20:27:16'),
(523, 3, 'key_responsibilities', 'Use Git/GitHub for version control.', 5, '2026-08-09 20:27:16'),
(524, 3, 'key_responsibilities', 'Document development work and technical processes.', 6, '2026-08-09 20:27:16'),
(525, 3, 'programme_activities', 'Software Development Projects - Develop and maintain real-world web applications.', 0, '2026-08-09 20:27:16'),
(526, 3, 'programme_activities', 'Coding & Development - Apply programming concepts using technologies such as PHP, JavaScript, HTML, CSS, and MySQL.', 1, '2026-08-09 20:27:16'),
(527, 3, 'programme_activities', 'Database Management - Create, query, maintain, and work with relational databases.', 2, '2026-08-09 20:27:16'),
(528, 3, 'programme_activities', 'Testing & Debugging - Identify, troubleshoot, and resolve software issues.', 3, '2026-08-09 20:27:16'),
(529, 3, 'programme_activities', 'Version Control - Use Git and GitHub to manage source code and collaborate with other developers.', 4, '2026-08-09 20:27:16'),
(530, 3, 'programme_activities', 'Code Reviews - Participate in reviewing code and following development best practices.', 5, '2026-08-09 20:27:16'),
(531, 3, 'programme_activities', 'Team Collaboration - Work with developers and other team members in a professional environment.', 6, '2026-08-09 20:27:16'),
(532, 3, 'programme_activities', 'Technical Documentation - Document application features, development processes, and solutions.', 7, '2026-08-09 20:27:16'),
(533, 3, 'programme_activities', 'Professional Development - Develop communication, teamwork, time management, and problem-solving skills.', 8, '2026-08-09 20:27:16'),
(534, 3, 'learning_outcomes', 'Develop functional web applications using modern web development technologies.', 0, '2026-08-09 20:27:16'),
(535, 3, 'learning_outcomes', 'Apply software development principles to real-world projects.', 1, '2026-08-09 20:27:16'),
(536, 3, 'learning_outcomes', 'Work with relational databases and perform common CRUD operations.', 2, '2026-08-09 20:27:16'),
(537, 3, 'learning_outcomes', 'Identify and troubleshoot technical problems through testing and debugging.', 3, '2026-08-09 20:27:16'),
(538, 3, 'learning_outcomes', 'Use Git/GitHub effectively for version control and collaboration.', 4, '2026-08-09 20:27:16'),
(539, 3, 'learning_outcomes', 'Understand the software development lifecycle from requirements through development, testing, and deployment.', 5, '2026-08-09 20:27:16'),
(540, 3, 'learning_outcomes', 'Work effectively within a development team using professional workflows.', 6, '2026-08-09 20:27:16'),
(541, 3, 'learning_outcomes', 'Produce technical documentation for software projects.', 7, '2026-08-09 20:27:16'),
(542, 3, 'learning_outcomes', 'Demonstrate improved problem-solving and analytical skills.', 8, '2026-08-09 20:27:16'),
(543, 3, 'learning_outcomes', 'Build practical experience and a portfolio that can support future employment opportunities.', 9, '2026-08-09 20:27:16'),
(544, 1, 'key_responsibilities', 'Assist in developing and maintaining web applications.', 0, '2026-08-13 13:04:07'),
(545, 1, 'key_responsibilities', 'Write clean, readable, and maintainable code.', 1, '2026-08-13 13:04:07'),
(546, 1, 'key_responsibilities', 'Assist with database design, queries, and data management.', 2, '2026-08-13 13:04:07'),
(547, 1, 'key_responsibilities', 'Identify and troubleshoot software issues.', 3, '2026-08-13 13:04:07'),
(548, 1, 'key_responsibilities', 'Participate in testing and debugging activities.', 4, '2026-08-13 13:04:07'),
(549, 1, 'key_responsibilities', 'Assist with integrating APIs and third-party services.', 5, '2026-08-13 13:04:07'),
(550, 1, 'key_responsibilities', 'Maintain technical documentation.', 6, '2026-08-13 13:04:07'),
(551, 1, 'key_responsibilities', 'Use Git/GitHub for source-code management.', 7, '2026-08-13 13:04:07'),
(552, 1, 'key_responsibilities', 'Participate in code reviews and development discussions.', 8, '2026-08-13 13:04:07'),
(553, 1, 'key_responsibilities', 'Work closely with developers and other team members to deliver project requirements.', 9, '2026-08-13 13:04:07'),
(554, 1, 'duties', 'Develop and update frontend and backend functionality.', 0, '2026-08-13 13:04:07'),
(555, 1, 'duties', 'Create and modify database tables and SQL queries.', 1, '2026-08-13 13:04:07'),
(556, 1, 'duties', 'Test application features and resolve bugs.', 2, '2026-08-13 13:04:07'),
(557, 1, 'duties', 'Assist with improving application performance and usability.', 3, '2026-08-13 13:04:07'),
(558, 1, 'duties', 'Document development work and technical processes.', 4, '2026-08-13 13:04:07'),
(559, 1, 'duties', 'Attend development meetings and provide progress updates.', 5, '2026-08-13 13:04:07'),
(560, 1, 'duties', 'Follow software development standards and security best practices.', 6, '2026-08-13 13:04:07'),
(561, 1, 'duties', 'Support the development team with general programming tasks.', 7, '2026-08-13 13:04:07'),
(562, 1, 'programme_activities', 'Participate in structured software development training.', 0, '2026-08-13 13:04:07'),
(563, 1, 'programme_activities', 'Work on real-world software development projects.', 1, '2026-08-13 13:04:07'),
(564, 1, 'programme_activities', 'Participate in sprint planning and development meetings.', 2, '2026-08-13 13:04:07'),
(565, 1, 'programme_activities', 'Complete practical coding tasks and technical exercises.', 3, '2026-08-13 13:04:07'),
(566, 1, 'programme_activities', 'Participate in code reviews and peer-learning sessions.', 4, '2026-08-13 13:04:07'),
(567, 1, 'programme_activities', 'Gain exposure to version control and collaborative development.', 5, '2026-08-13 13:04:07'),
(568, 1, 'programme_activities', 'Participate in software testing and debugging activities.', 6, '2026-08-13 13:04:07'),
(569, 1, 'programme_activities', 'Attend professional development and workplace-readiness sessions.', 7, '2026-08-13 13:04:07'),
(570, 1, 'programme_activities', 'Receive mentorship and guidance from experienced developers.', 8, '2026-08-13 13:04:07'),
(571, 1, 'programme_activities', 'Present completed work and project progress to the development team.', 9, '2026-08-13 13:04:07'),
(572, 1, 'learning_outcomes', 'Develop basic web applications using frontend and backend technologies.', 0, '2026-08-13 13:04:07'),
(573, 1, 'learning_outcomes', 'Design and interact with relational databases using SQL.', 1, '2026-08-13 13:04:07'),
(574, 1, 'learning_outcomes', 'Apply software development principles to real-world projects.', 2, '2026-08-13 13:04:07'),
(575, 1, 'learning_outcomes', 'Use Git and GitHub for version control and collaboration.', 3, '2026-08-13 13:04:07'),
(576, 1, 'learning_outcomes', 'Identify, troubleshoot, and resolve common software defects.', 4, '2026-08-13 13:04:07'),
(577, 1, 'learning_outcomes', 'Apply basic software testing practices.', 5, '2026-08-13 13:04:07'),
(578, 1, 'learning_outcomes', 'Understand the software development lifecycle.', 6, '2026-08-13 13:04:07'),
(579, 1, 'learning_outcomes', 'Work effectively within an agile development team.', 7, '2026-08-13 13:04:07'),
(580, 1, 'learning_outcomes', 'Develop clean, maintainable, and well-documented code.', 8, '2026-08-13 13:04:07'),
(581, 1, 'learning_outcomes', 'Understand basic application security and development best practices.', 9, '2026-08-13 13:04:07'),
(582, 1, 'learning_outcomes', 'Build a portfolio of practical software development experience.', 10, '2026-08-13 13:04:07');

-- --------------------------------------------------------

--
-- Table structure for table `opportunity_skills`
--

CREATE TABLE `opportunity_skills` (
  `id` int(10) UNSIGNED NOT NULL,
  `opportunity_id` int(10) UNSIGNED NOT NULL,
  `skill_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `skill_category` enum('required_technical','preferred_technical','required_soft') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'required_technical',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `opportunity_skills`
--

INSERT INTO `opportunity_skills` (`id`, `opportunity_id`, `skill_name`, `skill_category`, `created_at`) VALUES
(136, 2, 'Basic networking knowledge', 'required_technical', '2026-08-09 20:25:06'),
(137, 2, 'Computer installation and configuration', 'required_technical', '2026-08-09 20:25:06'),
(138, 2, 'Hardware and software troubleshooting', 'required_technical', '2026-08-09 20:25:06'),
(139, 2, 'Microsoft Office applications', 'required_technical', '2026-08-09 20:25:06'),
(140, 2, 'Windows operating systems', 'required_technical', '2026-08-09 20:25:06'),
(141, 2, 'Basic knowledge of Linux', 'preferred_technical', '2026-08-09 20:25:06'),
(142, 2, 'Basic knowledge of system administration', 'preferred_technical', '2026-08-09 20:25:06'),
(143, 2, 'Basic network troubleshooting', 'preferred_technical', '2026-08-09 20:25:06'),
(144, 2, 'Familiarity with remote support tools', 'preferred_technical', '2026-08-09 20:25:06'),
(145, 2, 'Customer service and user support', 'required_soft', '2026-08-09 20:25:06'),
(146, 2, 'Good communication skills', 'required_soft', '2026-08-09 20:25:06'),
(147, 2, 'Problem solving', 'required_soft', '2026-08-09 20:25:06'),
(148, 1, 'Git/GitHub', 'required_technical', '2026-08-13 13:04:07'),
(149, 1, 'HTML/CSS', 'required_technical', '2026-08-13 13:04:07'),
(150, 1, 'JavaScript', 'required_technical', '2026-08-13 13:04:07'),
(151, 1, 'PHP', 'required_technical', '2026-08-13 13:04:07'),
(152, 1, 'SQL', 'required_technical', '2026-08-13 13:04:07'),
(153, 1, 'MySQL', 'preferred_technical', '2026-08-13 13:04:07'),
(154, 1, 'PHP', 'preferred_technical', '2026-08-13 13:04:07'),
(155, 1, 'React JS', 'preferred_technical', '2026-08-13 13:04:07'),
(156, 1, 'Rest APIs', 'preferred_technical', '2026-08-13 13:04:07'),
(157, 1, 'XAMPP', 'preferred_technical', '2026-08-13 13:04:07'),
(158, 1, 'Attention to detail', 'required_soft', '2026-08-13 13:04:07'),
(159, 1, 'Good communication', 'required_soft', '2026-08-13 13:04:07'),
(160, 1, 'Interpersonal', 'required_soft', '2026-08-13 13:04:07'),
(161, 1, 'Problem solving', 'required_soft', '2026-08-13 13:04:07'),
(162, 1, 'Team player', 'required_soft', '2026-08-13 13:04:07');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token_hash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `programmes`
--

CREATE TABLE `programmes` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('graduate_programme','internship','learnership','wil','skills_development','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `objectives` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('draft','active','paused','completed','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `programmes`
--

INSERT INTO `programmes` (`id`, `name`, `type`, `description`, `objectives`, `duration`, `start_date`, `end_date`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Software Development Graduate Programme', 'graduate_programme', 'A structured graduate development programme designed for recent IT and Computer Science graduates who want to gain practical experience in software development. Graduates will work alongside experienced developers on real-world projects while developing their skills in web development, databases, APIs, version control, testing, and software engineering practices.', 'Provide graduates with practical software development experience. Strengthen programming and problem-solving skills. Develop experience with modern web technologies and databases. Introduce graduates to Git and collaborative development workflows. Develop understanding of software testing, debugging, and maintenance. Encourage professional communication and teamwork. Prepare graduates for long-term careers in software development.', '24 months', '2026-09-07', '2028-08-31', 'active', 1, '2026-08-08 15:16:08', '2026-08-17 07:36:32'),
(2, 'IT Support & Technical Services Graduate Programme', 'graduate_programme', 'A graduate programme designed to provide practical experience in IT support, troubleshooting, system administration, hardware and software configuration, and user assistance. Graduates will work with IT professionals to resolve technical issues and support the organisation’s day-to-day technology operations.', 'Develop practical IT troubleshooting skills. Gain experience supporting hardware and software systems. Develop skills in diagnosing and resolving technical problems. Introduce graduates to system administration and network fundamentals. Improve customer service and communication skills. Develop knowledge of IT security and best practices. Prepare graduates for careers in IT support and technical services.', '12 months', '2026-09-07', '2027-09-30', 'active', 1, '2026-08-08 15:29:38', '2026-08-09 16:14:02'),
(3, 'Software Development Work Integrated Learning (WIL) Programme', 'wil', 'A structured Work Integrated Learning programme designed to provide students with practical workplace experience in software development. Participants will have the opportunity to apply the knowledge gained through their academic studies to real-world projects while working alongside experienced IT professionals. The programme focuses on web development, database management, software testing, version control, problem-solving, and professional workplace practices.', 'Provide students with practical experience in a professional IT environment. Apply academic knowledge to real-world software development projects. Develop practical skills in HTML, CSS, JavaScript, PHP, and MySQL. Gain experience in designing, developing, testing, and maintaining web applications. Develop database management and SQL skills. Introduce students to Git and collaborative software development workflows. Improve debugging, troubleshooting, and problem-solving abilities. Develop professional communication, teamwork, and time-management skills. Prepare students for entry-level careers in software development and IT.', '6 months', '2027-01-11', '2027-06-30', 'active', 1, '2026-08-08 15:35:07', '2026-08-08 17:14:12'),
(4, 'Software Development Graduate Programme (Copy) 2026', 'graduate_programme', 'A structured graduate development programme designed for recent IT and Computer Science graduates who want to gain practical experience in software development. Graduates will work alongside experienced developers on real-world projects while developing their skills in web development, databases, APIs, version control, testing, and software engineering practices.', 'Provide graduates with practical software development experience. Strengthen programming and problem-solving skills. Develop experience with modern web technologies and databases. Introduce graduates to Git and collaborative development workflows. Develop understanding of software testing, debugging, and maintenance. Encourage professional communication and teamwork. Prepare graduates for long-term careers in software development.', '24 months', '2026-09-01', '2028-09-29', 'archived', 1, '2026-08-08 17:14:40', '2026-08-09 13:35:18');

-- --------------------------------------------------------

--
-- Table structure for table `programme_eligibility`
--

CREATE TABLE `programme_eligibility` (
  `id` int(10) UNSIGNED NOT NULL,
  `programme_id` int(10) UNSIGNED NOT NULL,
  `qualification_level` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qualification_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field_of_study` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `institution_requirements` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `min_completion_year` year(4) DEFAULT NULL,
  `max_completion_year` year(4) DEFAULT NULL,
  `min_experience` int(10) UNSIGNED DEFAULT NULL,
  `max_experience` int(10) UNSIGNED DEFAULT NULL,
  `province` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_restrictions` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `availability` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `citizenship_residency` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `programme_specific` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `programme_eligibility`
--

INSERT INTO `programme_eligibility` (`id`, `programme_id`, `qualification_level`, `qualification_name`, `field_of_study`, `institution_requirements`, `min_completion_year`, `max_completion_year`, `min_experience`, `max_experience`, `province`, `city`, `location_restrictions`, `availability`, `citizenship_residency`, `programme_specific`, `created_at`, `updated_at`) VALUES
(1, 1, 'diploma', 'Diploma Information Technology', 'Information Technology', NULL, NULL, NULL, 0, 0, 'gauteng', 'Pretoria', 'Relocate at your own cost', 'Currently available', 'South African citizens only', NULL, '2026-08-09 11:15:44', '2026-08-17 08:17:21'),
(2, 2, 'diploma', 'Diploma Information Technology', 'Information Technology', '', NULL, NULL, NULL, NULL, 'gauteng', 'Pretoria', '', 'Currently available', '', '', '2026-08-09 16:23:33', '2026-08-09 16:23:33');

-- --------------------------------------------------------

--
-- Table structure for table `programme_skills`
--

CREATE TABLE `programme_skills` (
  `id` int(10) UNSIGNED NOT NULL,
  `programme_id` int(10) UNSIGNED NOT NULL,
  `skill_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `skill_category` enum('required_technical','preferred_technical','required_soft') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'required_technical',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `programme_skills`
--

INSERT INTO `programme_skills` (`id`, `programme_id`, `skill_name`, `skill_category`, `created_at`) VALUES
(45, 1, 'HTML/CSS', 'required_technical', '2026-08-17 08:18:21'),
(46, 1, 'JavaScript', 'required_technical', '2026-08-17 08:18:21'),
(47, 1, 'PHP', 'required_technical', '2026-08-17 08:18:21'),
(48, 1, 'SQL', 'required_technical', '2026-08-17 08:18:21'),
(49, 1, 'C++', 'required_technical', '2026-08-17 08:18:21'),
(50, 1, 'Python', 'preferred_technical', '2026-08-17 08:18:21'),
(51, 1, 'React JS', 'preferred_technical', '2026-08-17 08:18:21'),
(52, 1, 'SQL', 'preferred_technical', '2026-08-17 08:18:21'),
(53, 1, 'Eager to learn', 'required_soft', '2026-08-17 08:18:21'),
(54, 1, 'Effective communication', 'required_soft', '2026-08-17 08:18:21'),
(55, 1, 'Problem solving', 'required_soft', '2026-08-17 08:18:21'),
(56, 1, 'Team player', 'required_soft', '2026-08-17 08:18:21');

-- --------------------------------------------------------

--
-- Table structure for table `qualifications`
--

CREATE TABLE `qualifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `institution` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year_completed` year(4) DEFAULT NULL,
  `level` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verification_status` enum('unverified','pending','verified','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unverified',
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `qualifications`
--

INSERT INTO `qualifications` (`id`, `user_id`, `name`, `institution`, `year_completed`, `level`, `verification_status`, `verified_at`, `created_at`, `updated_at`) VALUES
(1, 9, 'Computer Science', 'University of Pretoria', 2025, 'degree', 'unverified', NULL, '2026-08-06 13:05:54', '2026-08-07 10:32:56');

-- --------------------------------------------------------

--
-- Table structure for table `remember_me_tokens`
--

CREATE TABLE `remember_me_tokens` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `selector` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `validator_hash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `created_at`) VALUES
(1, 'Administrator', 'admin', 'Full platform administration and configuration access.', '2026-08-02 22:58:24'),
(2, 'Programme Manager', 'programme_manager', 'Manages programme delivery, cohorts, and performance.', '2026-08-02 22:58:24'),
(3, 'Programme Officer', 'programme_officer', 'Coordinates programme operations and candidate support.', '2026-08-02 22:58:24'),
(4, 'Recruiter', 'recruiter', 'Manages opportunities, talent sourcing, and placements.', '2026-08-02 22:58:24'),
(5, 'Supervisor', 'supervisor', 'Supervises candidates during work-integrated learning and internships.', '2026-08-02 22:58:24'),
(6, 'Assessor', 'assessor', 'Conducts candidate assessments and skills verification.', '2026-08-02 22:58:24'),
(7, 'Finance Officer', 'finance_officer', 'Manages financial records, stipends, and invoicing.', '2026-08-02 22:58:24'),
(8, 'Information Officer', 'information_officer', 'Manages privacy, compliance, and access to information.', '2026-08-02 22:58:24'),
(9, 'Candidate', 'candidate', 'Platform users seeking programmes, internships, and opportunities.', '2026-08-02 22:58:24');

-- --------------------------------------------------------

--
-- Table structure for table `skills`
--

CREATE TABLE `skills` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` enum('technical','soft') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'technical',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `skills`
--

INSERT INTO `skills` (`id`, `name`, `category`, `description`, `is_active`, `created_at`) VALUES
(1, 'PHP', 'technical', 'Server-side scripting language', 1, '2026-08-04 01:06:12'),
(2, 'JavaScript', 'technical', 'Client-side scripting language', 1, '2026-08-04 01:06:12'),
(3, 'MySQL', 'technical', 'Relational database management system', 1, '2026-08-04 01:06:12'),
(4, 'Python', 'technical', 'General-purpose programming language', 1, '2026-08-04 01:06:12'),
(5, 'Java', 'technical', 'Object-oriented programming language', 1, '2026-08-04 01:06:12'),
(6, 'C#', 'technical', 'Object-oriented programming language', 1, '2026-08-04 01:06:12'),
(7, 'C++', 'technical', 'General-purpose programming language', 1, '2026-08-04 01:06:12'),
(8, 'TypeScript', 'technical', 'Typed superset of JavaScript', 1, '2026-08-04 01:06:12'),
(9, 'Node.js', 'technical', 'JavaScript runtime environment', 1, '2026-08-04 01:06:12'),
(10, 'React', 'technical', 'JavaScript library for building UIs', 1, '2026-08-04 01:06:12'),
(11, 'Angular', 'technical', 'TypeScript-based web application framework', 1, '2026-08-04 01:06:12'),
(12, 'Vue.js', 'technical', 'Progressive JavaScript framework', 1, '2026-08-04 01:06:12'),
(13, 'HTML', 'technical', 'Markup language for web pages', 1, '2026-08-04 01:06:12'),
(14, 'CSS', 'technical', 'Styling language for web pages', 1, '2026-08-04 01:06:12'),
(15, 'SQL', 'technical', 'Structured query language', 1, '2026-08-04 01:06:12'),
(16, 'Cloud Computing', 'technical', 'Delivery of computing services over the internet', 1, '2026-08-04 01:06:12'),
(17, 'AWS', 'technical', 'Amazon Web Services cloud platform', 1, '2026-08-04 01:06:12'),
(18, 'Azure', 'technical', 'Microsoft cloud platform', 1, '2026-08-04 01:06:12'),
(19, 'Docker', 'technical', 'Containerisation platform', 1, '2026-08-04 01:06:12'),
(20, 'Kubernetes', 'technical', 'Container orchestration platform', 1, '2026-08-04 01:06:12'),
(21, 'Git', 'technical', 'Version control system', 1, '2026-08-04 01:06:12'),
(22, 'Linux', 'technical', 'Open-source operating system', 1, '2026-08-04 01:06:12'),
(23, 'Networking', 'technical', 'Computer networking fundamentals', 1, '2026-08-04 01:06:12'),
(24, 'Cybersecurity', 'technical', 'Protection of computer systems from threats', 1, '2026-08-04 01:06:12'),
(25, 'Data Analysis', 'technical', 'Inspection and interpretation of data', 1, '2026-08-04 01:06:12'),
(26, 'Machine Learning', 'technical', 'Algorithms that learn from data', 1, '2026-08-04 01:06:12'),
(27, 'Mobile Development', 'technical', 'Building applications for mobile devices', 1, '2026-08-04 01:06:12'),
(28, 'Android', 'technical', 'Android app development', 1, '2026-08-04 01:06:12'),
(29, 'iOS', 'technical', 'iOS app development', 1, '2026-08-04 01:06:12'),
(30, 'REST APIs', 'technical', 'Design and consumption of RESTful APIs', 1, '2026-08-04 01:06:12'),
(31, 'Database Design', 'technical', 'Designing relational database schemas', 1, '2026-08-04 01:06:12'),
(32, 'DevOps', 'technical', 'Practices combining development and operations', 1, '2026-08-04 01:06:12'),
(33, 'Testing / QA', 'technical', 'Software testing and quality assurance', 1, '2026-08-04 01:06:12'),
(34, 'Project Management', 'technical', 'Planning and managing projects', 1, '2026-08-04 01:06:12'),
(35, 'Figma', 'technical', 'UI/UX design tool', 1, '2026-08-04 01:06:12'),
(36, 'Power BI', 'technical', 'Business intelligence tool', 1, '2026-08-04 01:06:12'),
(37, 'Excel', 'technical', 'Spreadsheet application', 1, '2026-08-04 01:06:12'),
(38, 'Communication', 'soft', 'Clear and effective verbal and written communication', 1, '2026-08-04 01:06:12'),
(39, 'Teamwork', 'soft', 'Working effectively within a team', 1, '2026-08-04 01:06:12'),
(40, 'Problem Solving', 'soft', 'Analysing and resolving problems', 1, '2026-08-04 01:06:12'),
(41, 'Leadership', 'soft', 'Guiding and motivating others', 1, '2026-08-04 01:06:12'),
(42, 'Time Management', 'soft', 'Managing time effectively', 1, '2026-08-04 01:06:12'),
(43, 'Adaptability', 'soft', 'Adjusting to new conditions', 1, '2026-08-04 01:06:12'),
(44, 'Critical Thinking', 'soft', 'Objectively analysing and evaluating issues', 1, '2026-08-04 01:06:12'),
(45, 'Creativity', 'soft', 'Generating innovative ideas', 1, '2026-08-04 01:06:12'),
(46, 'Collaboration', 'soft', 'Working jointly with others', 1, '2026-08-04 01:06:12'),
(47, 'Attention to Detail', 'soft', 'Thoroughness and accuracy', 1, '2026-08-04 01:06:12'),
(48, 'Emotional Intelligence', 'soft', 'Understanding and managing emotions', 1, '2026-08-04 01:06:12'),
(49, 'Conflict Resolution', 'soft', 'Resolving disagreements constructively', 1, '2026-08-04 01:06:12'),
(50, 'Public Speaking', 'soft', 'Speaking confidently to audiences', 1, '2026-08-04 01:06:12'),
(51, 'Customer Service', 'soft', 'Serving and supporting customers', 1, '2026-08-04 01:06:12'),
(52, 'Decision Making', 'soft', 'Making effective choices', 1, '2026-08-04 01:06:12'),
(53, 'Negotiation', 'soft', 'Reaching mutual agreements', 1, '2026-08-04 01:06:12');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employment_status` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qualification_level` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `professional_title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `profile_picture` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','active','suspended','disabled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `email_verified_at` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role_id`, `first_name`, `last_name`, `username`, `email`, `phone`, `date_of_birth`, `gender`, `province`, `employment_status`, `qualification_level`, `professional_title`, `password_hash`, `profile_picture`, `status`, `email_verified_at`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 1, 'Platform', 'Administrator', 'admin', 'admin@investhoodit.co.za', '+27 11 234 5678', '1995-06-15', 'prefer-not-to-say', 'gauteng', 'employed', 'masters', 'Platform Administrator', '$2y$10$UFHCguRSSHaoRZcitgV.b.McBGkqgPa0we0pGwUyBiq1W2V8aJolu', NULL, 'active', '2026-08-03 01:00:17', '2026-08-19 23:09:41', '2026-08-02 23:00:17', '2026-08-19 21:09:41'),
(2, 2, 'Programme', 'Manager', 'programme.manager', 'programme.manager@investhoodit.co.za', '+27 11 234 5679', '1995-06-15', 'prefer-not-to-say', 'gauteng', 'employed', 'masters', 'Programme Manager', '$2y$10$UFHCguRSSHaoRZcitgV.b.McBGkqgPa0we0pGwUyBiq1W2V8aJolu', NULL, 'active', '2026-08-03 01:00:17', '2026-08-04 02:09:22', '2026-08-02 23:00:17', '2026-08-04 00:09:22'),
(3, 3, 'Programme', 'Officer', 'programme.officer', 'programme.officer@investhoodit.co.za', '+27 11 234 5680', '1995-06-15', 'prefer-not-to-say', 'gauteng', 'employed', 'degree', 'Programme Officer', '$2y$10$UFHCguRSSHaoRZcitgV.b.McBGkqgPa0we0pGwUyBiq1W2V8aJolu', NULL, 'active', '2026-08-03 01:00:17', NULL, '2026-08-02 23:00:17', '2026-08-02 23:00:17'),
(4, 4, 'Recruitment', 'Specialist', 'recruiter', 'recruiter@investhoodit.co.za', '+27 11 234 5681', '1995-06-15', 'prefer-not-to-say', 'gauteng', 'employed', 'degree', 'Recruiter', '$2y$10$UFHCguRSSHaoRZcitgV.b.McBGkqgPa0we0pGwUyBiq1W2V8aJolu', NULL, 'active', '2026-08-03 01:00:17', NULL, '2026-08-02 23:00:17', '2026-08-02 23:00:17'),
(5, 5, 'Workplace', 'Supervisor', 'supervisor', 'supervisor@investhoodit.co.za', '+27 11 234 5682', '1995-06-15', 'prefer-not-to-say', 'gauteng', 'employed', 'degree', 'Workplace Supervisor', '$2y$10$UFHCguRSSHaoRZcitgV.b.McBGkqgPa0we0pGwUyBiq1W2V8aJolu', NULL, 'active', '2026-08-03 01:00:17', '2026-08-19 12:04:51', '2026-08-02 23:00:17', '2026-08-19 10:04:51'),
(6, 6, 'Skills', 'Assessor', 'assessor', 'assessor@investhoodit.co.za', '+27 11 234 5683', '1995-06-15', 'prefer-not-to-say', 'gauteng', 'employed', 'degree', 'Skills Assessor', '$2y$10$UFHCguRSSHaoRZcitgV.b.McBGkqgPa0we0pGwUyBiq1W2V8aJolu', NULL, 'active', '2026-08-03 01:00:17', NULL, '2026-08-02 23:00:17', '2026-08-02 23:00:17'),
(7, 7, 'Finance', 'Officer', 'finance.officer', 'finance.officer@investhoodit.co.za', '+27 11 234 5684', '1995-06-15', 'prefer-not-to-say', 'gauteng', 'employed', 'degree', 'Finance Officer', '$2y$10$UFHCguRSSHaoRZcitgV.b.McBGkqgPa0we0pGwUyBiq1W2V8aJolu', NULL, 'active', '2026-08-03 01:00:17', '2026-08-04 02:08:15', '2026-08-02 23:00:17', '2026-08-04 00:08:15'),
(8, 8, 'Information', 'Officer', 'info.officer', 'info.officer@investhoodit.co.za', '+27 11 234 5685', '1995-06-15', 'prefer-not-to-say', 'gauteng', 'employed', 'degree', 'Information Officer', '$2y$10$UFHCguRSSHaoRZcitgV.b.McBGkqgPa0we0pGwUyBiq1W2V8aJolu', NULL, 'active', '2026-08-03 01:00:17', NULL, '2026-08-02 23:00:17', '2026-08-02 23:00:17'),
(9, 9, 'Hlobisile', 'Mthembu', 'Sky', 'candidate@gmail.com', '+27 12 345 3433', '1995-06-15', 'female', 'gauteng', 'employed', 'degree', 'Software Developer', '$2y$10$3Y8qIUV3YCaRu6plnDqGJOMWrnQucYeYZEDU2rcXRaaYBwxPs5Qnq', NULL, 'active', NULL, '2026-08-21 02:51:25', '2026-08-02 23:00:17', '2026-08-21 00:51:25'),
(10, 9, 'Delani', 'Sibande', 'Deco', 'sibanded1030@gmail.com', '0794065577', '1997-12-12', 'male', 'gauteng', 'recent-graduate', 'degree', 'Software Developer', '$2y$10$mlDKcbt12tR.64VpNTWm9uG6Wx7xVMglKNAtAN3BvCZdhS88jbv8C', NULL, 'active', NULL, '2026-08-19 13:45:09', '2026-08-04 00:12:28', '2026-08-19 11:45:09');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `session_hash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `login_time` datetime NOT NULL,
  `last_activity` datetime NOT NULL,
  `logout_time` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_sessions`
--

INSERT INTO `user_sessions` (`id`, `user_id`, `session_hash`, `ip_address`, `user_agent`, `login_time`, `last_activity`, `logout_time`, `is_active`) VALUES
(1, 5, '7d117b788bb622e7a6e619e1301e06036430705a3e43b56711e81c988b636f74', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-03 11:23:55', '2026-08-03 11:23:55', '2026-08-03 11:25:02', 0),
(2, 1, 'b4c6609f876183449b3344319699fd249fe43b19ed2021bcf1ec60dca4f9f812', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 01:13:30', '2026-08-04 01:13:30', '2026-08-04 01:46:32', 0),
(3, 9, 'ba80ead1da7a892684cd512dfefd12908ce530fe24709c54ab56c4933638616d', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 01:47:27', '2026-08-04 01:47:27', '2026-08-04 02:06:26', 0),
(4, 5, '1864eab807063903a9567a134c000f110f4d534c9ee0809f3e1c82649d3232f8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 02:07:17', '2026-08-04 02:07:17', '2026-08-04 02:07:40', 0),
(5, 7, '97bcdeafe4942430e77bbf57a83b13c362d1044baedcb730ee1149d042515c00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 02:08:15', '2026-08-04 02:08:15', '2026-08-04 02:08:29', 0),
(6, 2, 'a0a8524b4f8146d5ac21cd9730a9d4b9e8eb3930b551e2c02d93f57a9eed8013', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 02:09:22', '2026-08-04 02:09:22', '2026-08-04 02:09:54', 0),
(7, 10, 'df260cba9c4eb64e4a50391461dc140e98a59ddb458b66985c2cce1499c912cc', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 02:15:40', '2026-08-04 02:15:40', '2026-08-04 02:20:12', 0),
(8, 1, '9b29814c4a01e5cdc0aad6d7f28cbb0b1c5c044081f3a89615d0d61b8b8c441b', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 02:20:40', '2026-08-04 02:20:40', '2026-08-04 02:22:30', 0),
(9, 10, 'a2ea61086f46b9bd55db8374568ef8d0e0072a991bf5be9213191d38e8c59d42', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 03:44:42', '2026-08-04 03:44:42', NULL, 1),
(10, 1, '1b57599d71ced157dd18de2740b6fab36d446a3df30bfba91c5c28053f6e2789', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 12:15:15', '2026-08-04 12:15:15', '2026-08-04 12:18:02', 0),
(11, 9, '7d992587c9d28e8418ed25bf0882d41dcbe6ce049162a90ca23885966fc834f7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 12:19:17', '2026-08-04 12:19:17', '2026-08-04 12:26:07', 0),
(12, 9, 'ef8f72b55349f2603ff18ff486c81e0e9c90ebf191463d4fe92b8f39ffcbe6b5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 12:27:49', '2026-08-04 12:27:49', '2026-08-08 14:14:11', 0),
(13, 1, '7f582baa0abc450f1fc71cca67c44720f151c1ce90d0f20b1cd8461c655f1233', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 14:21:42', '2026-08-04 14:21:42', NULL, 1),
(14, 9, 'ebb9f333d8f382613d13d86bf356a3da03d7ff270cb6497a8df65e2c083de58a', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 02:21:01', '2026-08-05 02:21:01', '2026-08-05 02:26:51', 0),
(15, 9, '7f816cf58f74c0f1c5a801aa4225afef955cda023e4fbe6a51c441a0951dbf85', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 02:30:51', '2026-08-05 02:30:51', '2026-08-05 02:31:23', 0),
(16, 9, '12de91cdb3fa4e1a114b61a7e77991a2dd9caa2892e79b8de1b0f8db7ac088b7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-06 02:36:55', '2026-08-06 02:36:55', '2026-08-08 14:14:11', 0),
(17, 9, 'b5fe81e9d84e50fc850173bdaf21f61e740f36d2954cad47614bfb606d57821d', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-06 11:48:51', '2026-08-06 11:48:51', '2026-08-08 14:14:11', 0),
(18, 9, '3db68565f684dff119a5efd9b5f455c767fd7448955d52cae4517fefefa48a63', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-06 12:54:19', '2026-08-06 12:54:19', '2026-08-08 14:14:11', 0),
(19, 9, '8928185a34412478d54039ee07e7014ea47e99f042fc870fb6cceb54fe25f00f', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-06 13:35:28', '2026-08-06 13:35:28', '2026-08-06 16:25:21', 0),
(20, 10, 'f0ec3f44d4303e2bc7c7422e39e3f67862d55acea502d7e4e7f6ba068d56a30b', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-06 16:31:12', '2026-08-06 16:31:12', '2026-08-06 16:35:43', 0),
(21, 9, '7ff6cb7d95027b3fd23f1b1217f109dc307e8088a9ef4e5500bb1face7418ee0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-06 16:36:16', '2026-08-06 16:36:16', '2026-08-06 16:43:45', 0),
(22, 9, '62f1759556801a8dc2cb232e454629a44b1edc1ae457a5156fb05c2f667fc47c', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-06 19:00:45', '2026-08-06 19:00:45', '2026-08-08 14:14:11', 0),
(23, 9, 'bd1cff2e3009c8e215b6aebcabc96f081ef1747b6f1e8b2c7afbd59d22ac92e2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-07 11:15:28', '2026-08-07 11:15:28', '2026-08-07 13:26:55', 0),
(24, 9, 'f4b037dae2c71fac009cef542cd458d7dadea4fa951c8f34addc3621c5ba7d47', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-07 13:34:01', '2026-08-07 13:34:01', '2026-08-07 13:34:07', 0),
(25, 9, '1d77067b1e797dccf0ad9b742dd6df56d61d4e33b2b2a3bb713a7ae94f6870fb', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-07 13:37:52', '2026-08-07 13:37:52', '2026-08-07 13:38:35', 0),
(26, 1, '977a82af704b3d4d5aeafcdc87163f73da7eb07676161e698d4c36604b9bf40a', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-07 13:40:11', '2026-08-07 13:40:11', '2026-08-07 13:40:19', 0),
(27, 1, '8b6efef2f760a93dec11e5d4fcaa275e8a22fa0234af50e6931ad1a0d90f89b6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-07 13:49:01', '2026-08-07 13:49:01', '2026-08-07 13:49:47', 0),
(28, 9, '8b87b56351d19adf2bbc4b5a6623d632fe3186216948eb177188d54aa5ab596a', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-07 14:45:51', '2026-08-07 14:45:51', '2026-08-08 14:14:11', 0),
(29, 9, '0a64b9a77073605e7a7d59689f2a26af75b3a0b60b643415d852b86fd3e64abc', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-08 13:25:49', '2026-08-08 13:25:49', '2026-08-08 14:20:55', 0),
(30, 9, '41511a7e1446b93513f0902d4a4255d94ad182832702d8fc9edd71709d976cc6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-08 14:26:45', '2026-08-08 14:26:45', NULL, 1),
(31, 9, '3ed0b44c72465aaac1682e5714cc84a2271cc264cf14f23cc187689eda3027bc', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-08 16:38:20', '2026-08-08 16:38:20', '2026-08-08 16:38:40', 0),
(32, 1, 'e5fe048ef45914e0b827def3116addeefe36094c9a47b4bde5582d20a3d87032', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-08 16:39:20', '2026-08-08 16:39:20', '2026-08-08 18:25:44', 0),
(33, 1, 'e011c4c45bae8450d496fda088d6269d013feb6bb57a300f60179140d8aadd21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-08 18:27:23', '2026-08-08 18:27:23', '2026-08-08 19:21:01', 0),
(34, 9, '36b5505ed9a385c4e0a5eb53e96ef6f376eaed7cff2c3d29d50e7eecfaa26f6c', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-08 19:21:34', '2026-08-08 19:21:34', '2026-08-08 19:24:34', 0),
(35, 1, 'de12b75983b6277893ab6d7467adb4d37e264e0978b380f7021e8e57a678f9e4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-08 19:25:19', '2026-08-08 19:25:19', '2026-08-08 19:27:37', 0),
(36, 9, 'b0be720f445d03b6701837d64beefb76a68ccc51755a24db97c5950a788d60d9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-08 19:28:07', '2026-08-08 19:28:07', '2026-08-08 19:29:32', 0),
(37, 1, 'd7432850d1baf6cbbb573cb84b0ed7ba661a83d4b9d6d40e72cba6dcf794df62', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-08 19:30:46', '2026-08-08 19:30:46', '2026-08-08 20:01:07', 0),
(38, 9, 'fffe69d1667fedbe87e6336273074b9afa2b1b2e1f298d6df5ed0b80848fb210', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-08 20:01:53', '2026-08-08 20:01:53', '2026-08-08 20:04:47', 0),
(39, 1, '68e29be4947790ae7b86f15dddb6041a74b36b65dc4cac634df3d10ece7ec178', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-08 20:06:03', '2026-08-08 20:06:03', '2026-08-08 20:28:48', 0),
(40, 1, '940112ab348f0de218723a14191635706227b6bdfccabdcabdd3544ac82083a6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-08 20:30:50', '2026-08-08 20:30:50', NULL, 1),
(41, 1, '543ec580342afa95cd2c4e01f9bb38c39fbb488cd17065a728c77c240919f324', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-09 12:53:38', '2026-08-09 12:53:38', '2026-08-09 15:36:34', 0),
(42, 9, '9ca01662f01b4cd3b2d8228abc081e35987062a86669a50043279dc046ffc778', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-09 15:37:09', '2026-08-09 15:37:09', '2026-08-09 15:48:06', 0),
(43, 1, 'c9dfa17ba70abe6afd2ca635e7361cfe891ab893e2a2b37313b83eb7d51b0fb6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-09 15:48:39', '2026-08-09 15:48:39', NULL, 1),
(44, 1, '145e25355fad4510d37cbc674b5a345849d254ebb7efdab4cb84be279ae22513', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-09 17:03:24', '2026-08-09 17:03:24', '2026-08-09 18:31:19', 0),
(45, 1, '3afb2206636f9e887e026a15e51765af07e9eb718adfc3fc54f77b8036f0e771', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-09 18:34:03', '2026-08-09 18:34:03', '2026-08-09 18:39:26', 0),
(46, 1, '242c8e6d22dd11de561276064aa3cc73a0901cbf6f0a4290bcaf3f3b9e9d713f', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-09 21:06:53', '2026-08-09 21:06:53', '2026-08-09 22:30:16', 0),
(47, 9, 'cfa05313529715ebc985a5adf66a5ab189863369fc90b369f860191846667f4c', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-09 22:31:31', '2026-08-09 22:31:31', '2026-08-09 22:33:32', 0),
(48, 1, 'fd63a7d3a16979838cb820a8fc19d8c4cdd3190955101db16c0bb45f893987f9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-12 08:41:12', '2026-08-12 08:41:12', NULL, 1),
(49, 1, '9d2d4a111bece05c769d3f4fde77f5651f9ffddcc4ff99fe12eec00abe19fa21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-12 09:58:47', '2026-08-12 09:58:47', '2026-08-12 09:59:04', 0),
(50, 1, '16b5ec7467aaaef4041b3f7a3f6507836a39949010d3c2cf99964a4a5c52cab9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-12 10:19:10', '2026-08-12 10:19:10', NULL, 1),
(51, 9, 'bc89f2dee4db9c5f00b32051ed781429171da17439c204d4fa9f92b3b72e18c0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-12 12:31:39', '2026-08-12 12:31:39', NULL, 1),
(52, 9, '2a793c740f4dfce90fb17c9bcd00372b651dee55c3b901649eceb5c7c165511f', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-12 12:59:48', '2026-08-12 12:59:48', NULL, 1),
(53, 9, '10382db3c7d44b567f1badee77d75adedca7f4cc8bea026c1905c5064aef5791', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-13 13:56:12', '2026-08-13 13:56:12', NULL, 1),
(54, 9, '2485b514fdfc0afa4c417d4313061de486dd05421bc9a313660a98f2ad1073a6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-13 14:45:09', '2026-08-13 14:45:09', '2026-08-13 15:00:25', 0),
(55, 1, '4aa9f43fa78f11c2a23de1bd1ac000e75ec7d9d6b2edb24d775f19a57ea96949', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-13 15:01:14', '2026-08-13 15:01:14', '2026-08-13 15:04:36', 0),
(56, 9, '91e77ca46ea6761ab283be3d8decf4270ee33e081a5af9ad615ebc18f42687ef', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-13 15:04:58', '2026-08-13 15:04:58', NULL, 1),
(57, 9, 'baf8733b783822e161b55aeae9851e3e28cc182796287ef4a3b06b303821b693', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-13 15:36:58', '2026-08-13 15:36:58', '2026-08-13 16:00:28', 0),
(58, 1, 'ecdd6daefb40db75dda5a6478fa3da8dc7984d43d9722c9257bed20b13718a85', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-17 09:19:15', '2026-08-17 09:19:15', '2026-08-17 09:58:27', 0),
(59, 1, 'b10b11bd0a9382df4244f33b96b2e61e6cc3dbab771fdf4ee99d3b921774a84a', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-17 10:09:48', '2026-08-17 10:09:48', '2026-08-17 10:47:31', 0),
(60, 9, '700a233a584e8051c2b8812ef81b6b23b0e2d077e0a6ac6bf6713b30e019bf50', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-17 14:59:32', '2026-08-17 14:59:32', NULL, 1),
(61, 9, '82850fa7df4abc3a8693b84c2d274d13cf9bf28bcb44afbcd23148923af47beb', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-17 16:03:48', '2026-08-17 16:03:48', NULL, 1),
(62, 9, 'bc959ad4b40ea2f88df2392bfa9314af904ca2a106b1761e6fa834c61c439e78', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-19 11:01:07', '2026-08-19 11:01:07', '2026-08-19 11:05:59', 0),
(63, 9, 'b6a01eeadc4f2557cbc59919d8d6fe995fbc5c7dfc88a660d672d4d7b1aa6c13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-19 11:42:37', '2026-08-19 11:42:37', '2026-08-19 12:03:16', 0),
(64, 5, '114233ed070e354777af19c9a2207790de8de7e6a858de995324a49470bc3070', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-19 12:04:51', '2026-08-19 12:04:51', '2026-08-19 12:05:10', 0),
(65, 1, '6de633dc0a44cee013538d19c9c7cbf37c78a1f236df2f4dd6a0daaa3f7b922b', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-19 12:05:47', '2026-08-19 12:05:47', '2026-08-19 12:16:48', 0),
(66, 9, '37141758687919c17b7deb19d7fc544ba594aa6cd41fe4b31067738ce9dfcbea', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-19 12:31:31', '2026-08-19 12:31:31', NULL, 1),
(67, 9, '3be307da852c570f0a162b58bb39907a815100509f0d1f236f3d2c5370c3375f', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-19 13:37:55', '2026-08-19 13:37:55', '2026-08-19 13:44:48', 0),
(68, 10, 'a43082e97b5b03e9a0f2bec6fd8e2b8cfa2435e8dd764b4481e2b6454c53e2f5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-19 13:45:09', '2026-08-19 13:45:09', '2026-08-19 13:45:52', 0),
(69, 9, '83d57dc0d6e65412a35962275da3c61d85e1db421c6ffe53d4264433bb0a1388', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-19 13:46:13', '2026-08-19 13:46:13', NULL, 1),
(70, 9, '07eb3e49aa9096f9a67b4132bb96a6ddeafbef646c0b81fbf6c338bdfc13722b', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-19 15:08:45', '2026-08-19 15:08:45', NULL, 1),
(71, 9, '9eb2edb39a770e85b75627fb25becda77fefdee6019893ee78e59c4939fc7e63', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-19 16:17:59', '2026-08-19 16:17:59', NULL, 1),
(72, 9, 'eca776b8f0d05eadfc6dbed19bf973d4be57e4d2efb9efa54f57837d9d0fdd83', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-19 16:39:09', '2026-08-19 16:39:09', '2026-08-19 16:40:11', 0),
(73, 9, 'ef39f6248c98791834a55107a0bb92e556bc2bf8930ebb75331250211034f0fe', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-19 22:24:44', '2026-08-19 22:24:44', '2026-08-19 22:24:52', 0),
(74, 1, 'd76b7ec9f7327d591879184edd6b17543c877b12034aaed33b81554c698db3d3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-19 22:25:46', '2026-08-19 22:25:46', NULL, 1),
(75, 1, '38321df5bc6590132e71419bfdc00f8318da10819ccf000d1bb4a88bd87d249e', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-19 23:09:41', '2026-08-19 23:09:41', NULL, 1),
(76, 9, 'd49655cfacec13ced600c2b6f1f252a749c69b57db3af3de24a1f7cdb0343de6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-20 00:59:44', '2026-08-20 00:59:44', NULL, 1),
(77, 9, '77bfec1b4fb81bb28c2f27fb0359d215d1f25190c641a33f3dd59d7698e9f519', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-20 10:54:36', '2026-08-20 10:54:36', NULL, 1),
(78, 9, 'd3cfd646360480ac6e23734e47e8185b31045c4cf0128afaec5733c254354a87', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-21 02:42:57', '2026-08-21 02:42:57', NULL, 1),
(79, 9, 'f19a74a06b96b6adb1f05936d98fc1b330a191de877ad3998eb3dd361aba376e', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-21 02:51:25', '2026-08-21 02:51:25', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--

CREATE TABLE `user_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `setting_key` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `work_experience`
--

CREATE TABLE `work_experience` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `job_title` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `company` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 0,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `work_experience`
--

INSERT INTO `work_experience` (`id`, `user_id`, `job_title`, `company`, `start_date`, `end_date`, `is_current`, `description`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 9, 'Junior Software Developer', 'IT Consulting Ltd (Pty)', '2025-06-12', '2026-06-30', 0, 'Developed and maintained web-based applications using HTML, CSS, JavaScript, PHP, and MySQL, delivering new features and enhancements based on business and user requirements. Wrote, tested, debugged, and maintained application code to ensure software quality, functionality, and performance across development and production environments. Diagnosed and resolved application bugs, implemented fixes, and provided ongoing application support to improve system reliability and reduce recurring issues. Designed, developed, and managed MySQL databases, creating SQL queries and ensuring efficient integration between back-end databases and front-end applications. Collaborated with developers, project managers, and stakeholders throughout the Software Development Life Cycle (SDLC) to deliver projects on time. Used Git for version control, code management, and team collaboration during software development. Assisted with project-related tasks and collaborate with team members to support successful project delivery.', 1, '2026-08-06 13:19:13', '2026-08-06 13:20:03');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_applications_candidate_opportunity` (`candidate_id`,`opportunity_id`),
  ADD KEY `idx_applications_candidate` (`candidate_id`,`status`),
  ADD KEY `idx_applications_opportunity` (`opportunity_id`),
  ADD KEY `idx_applications_status` (`status`),
  ADD KEY `idx_applications_created` (`created_at`);

--
-- Indexes for table `application_documents`
--
ALTER TABLE `application_documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_app_docs_application_type` (`application_id`,`document_type`),
  ADD KEY `idx_app_docs_application` (`application_id`),
  ADD KEY `idx_app_docs_type` (`document_type`);

--
-- Indexes for table `application_responses`
--
ALTER TABLE `application_responses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_app_responses_application_question` (`application_id`,`question_id`),
  ADD KEY `idx_app_responses_application` (`application_id`),
  ADD KEY `idx_app_responses_question` (`question_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_logs_user` (`user_id`),
  ADD KEY `idx_audit_logs_action` (`action`),
  ADD KEY `idx_audit_logs_record` (`record_type`,`record_id`),
  ADD KEY `idx_audit_logs_time` (`created_at`);

--
-- Indexes for table `availability_statuses`
--
ALTER TABLE `availability_statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_availability_statuses_slug` (`slug`),
  ADD KEY `idx_availability_statuses_active` (`is_active`);

--
-- Indexes for table `candidate_profiles`
--
ALTER TABLE `candidate_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_candidate_profiles_user` (`user_id`),
  ADD KEY `idx_candidate_profiles_availability` (`availability_status_id`),
  ADD KEY `idx_candidate_profiles_active` (`is_active`);

--
-- Indexes for table `candidate_saved_opportunities`
--
ALTER TABLE `candidate_saved_opportunities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_candidate_opportunity` (`candidate_id`,`opportunity_id`),
  ADD KEY `idx_candidate_saved_candidate` (`candidate_id`),
  ADD KEY `idx_candidate_saved_opportunity` (`opportunity_id`);

--
-- Indexes for table `candidate_skills`
--
ALTER TABLE `candidate_skills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_candidate_skills_user_skill` (`user_id`,`skill_id`),
  ADD KEY `idx_candidate_skills_skill` (`skill_id`),
  ADD KEY `idx_candidate_skills_verification` (`verification_status`);

--
-- Indexes for table `certifications`
--
ALTER TABLE `certifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_certifications_user` (`user_id`),
  ADD KEY `idx_certifications_verification` (`verification_status`);

--
-- Indexes for table `cohorts`
--
ALTER TABLE `cohorts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cohorts_programme_name` (`programme_id`,`name`),
  ADD KEY `idx_cohorts_status` (`status`),
  ADD KEY `idx_cohorts_start` (`start_date`),
  ADD KEY `idx_cohorts_close` (`application_close_date`),
  ADD KEY `idx_cohorts_province` (`province`),
  ADD KEY `idx_cohorts_delivery` (`delivery_mode`),
  ADD KEY `fk_cohorts_created_by` (`created_by`);

--
-- Indexes for table `cohort_documents`
--
ALTER TABLE `cohort_documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cohort_docs_cohort_name` (`cohort_id`,`document_name`),
  ADD KEY `idx_cohort_docs_cohort` (`cohort_id`);

--
-- Indexes for table `cohort_eligibility`
--
ALTER TABLE `cohort_eligibility`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cohort_elig_cohort` (`cohort_id`);

--
-- Indexes for table `cohort_participants`
--
ALTER TABLE `cohort_participants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cohort_participants_cohort_user` (`cohort_id`,`user_id`),
  ADD KEY `idx_cohort_participants_cohort` (`cohort_id`,`status`),
  ADD KEY `idx_cohort_participants_user` (`user_id`);

--
-- Indexes for table `cohort_skills`
--
ALTER TABLE `cohort_skills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cohort_skills_cohort_name_cat` (`cohort_id`,`skill_name`,`skill_category`),
  ADD KEY `idx_cohort_skills_cohort` (`cohort_id`),
  ADD KEY `idx_cohort_skills_category` (`skill_category`);

--
-- Indexes for table `cohort_workflow`
--
ALTER TABLE `cohort_workflow`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cohort_workflow_cohort_stage` (`cohort_id`,`stage`),
  ADD KEY `idx_cohort_workflow_cohort` (`cohort_id`);

--
-- Indexes for table `consents`
--
ALTER TABLE `consents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_consents_user_purpose` (`user_id`,`purpose`),
  ADD KEY `idx_consents_status` (`status`),
  ADD KEY `idx_consents_purpose` (`purpose`);

--
-- Indexes for table `deletion_requests`
--
ALTER TABLE `deletion_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_deletion_req_user` (`user_id`,`status`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_documents_user` (`user_id`),
  ADD KEY `idx_documents_type` (`document_type`),
  ADD KEY `idx_documents_verification` (`verification_status`),
  ADD KEY `fk_documents_uploader` (`uploaded_by`);

--
-- Indexes for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_email_verifications_token` (`token_hash`),
  ADD KEY `idx_email_verifications_user` (`user_id`),
  ADD KEY `idx_email_verifications_expiry` (`expires_at`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_login_attempts_ip` (`ip_address`,`successful`,`attempted_at`),
  ADD KEY `idx_login_attempts_user` (`user_id`),
  ADD KEY `idx_login_attempts_time` (`attempted_at`);

--
-- Indexes for table `notification_preferences`
--
ALTER TABLE `notification_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_notif_pref_user_cat` (`user_id`,`category`),
  ADD KEY `idx_notif_pref_user` (`user_id`);

--
-- Indexes for table `opportunities`
--
ALTER TABLE `opportunities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_opportunities_status` (`status`),
  ADD KEY `idx_opportunities_type` (`type`),
  ADD KEY `idx_opportunities_programme` (`programme_id`),
  ADD KEY `idx_opportunities_cohort` (`cohort_id`),
  ADD KEY `idx_opportunities_open` (`application_open_date`),
  ADD KEY `idx_opportunities_close` (`application_close_date`),
  ADD KEY `idx_opportunities_province` (`province`),
  ADD KEY `idx_opportunities_created_by` (`created_by`);

--
-- Indexes for table `opportunity_applications`
--
ALTER TABLE `opportunity_applications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_candidate_opportunity_app` (`candidate_id`,`opportunity_id`),
  ADD KEY `idx_app_candidate` (`candidate_id`),
  ADD KEY `idx_app_opportunity` (`opportunity_id`);

--
-- Indexes for table `opportunity_documents`
--
ALTER TABLE `opportunity_documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_opp_docs_opp_name` (`opportunity_id`,`document_name`),
  ADD KEY `idx_opp_docs_opportunity` (`opportunity_id`);

--
-- Indexes for table `opportunity_eligibility`
--
ALTER TABLE `opportunity_eligibility`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_opp_elig_opportunity` (`opportunity_id`);

--
-- Indexes for table `opportunity_questions`
--
ALTER TABLE `opportunity_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_opp_questions_opportunity` (`opportunity_id`),
  ADD KEY `idx_opp_questions_section` (`section`);

--
-- Indexes for table `opportunity_responsibilities`
--
ALTER TABLE `opportunity_responsibilities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_opp_resp_opportunity` (`opportunity_id`),
  ADD KEY `idx_opp_resp_type` (`type`);

--
-- Indexes for table `opportunity_skills`
--
ALTER TABLE `opportunity_skills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_opp_skills_opp_name_cat` (`opportunity_id`,`skill_name`,`skill_category`),
  ADD KEY `idx_opp_skills_opportunity` (`opportunity_id`),
  ADD KEY `idx_opp_skills_category` (`skill_category`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_password_resets_token` (`token_hash`),
  ADD KEY `idx_password_resets_user` (`user_id`),
  ADD KEY `idx_password_resets_expiry` (`expires_at`);

--
-- Indexes for table `programmes`
--
ALTER TABLE `programmes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_programmes_type` (`type`),
  ADD KEY `idx_programmes_status` (`status`),
  ADD KEY `idx_programmes_start` (`start_date`),
  ADD KEY `idx_programmes_end` (`end_date`),
  ADD KEY `idx_programmes_created_by` (`created_by`);

--
-- Indexes for table `programme_eligibility`
--
ALTER TABLE `programme_eligibility`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_prog_elig_programme` (`programme_id`);

--
-- Indexes for table `programme_skills`
--
ALTER TABLE `programme_skills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_programme_skills_prog_name_cat` (`programme_id`,`skill_name`,`skill_category`),
  ADD KEY `idx_programme_skills_programme` (`programme_id`),
  ADD KEY `idx_programme_skills_category` (`skill_category`);

--
-- Indexes for table `qualifications`
--
ALTER TABLE `qualifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_qualifications_user` (`user_id`),
  ADD KEY `idx_qualifications_verification` (`verification_status`);

--
-- Indexes for table `remember_me_tokens`
--
ALTER TABLE `remember_me_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_remember_me_selector` (`selector`),
  ADD KEY `idx_remember_me_user` (`user_id`),
  ADD KEY `idx_remember_me_expiry` (`expires_at`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_roles_slug` (`slug`),
  ADD KEY `idx_roles_name` (`name`);

--
-- Indexes for table `skills`
--
ALTER TABLE `skills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_skills_name` (`name`),
  ADD KEY `idx_skills_category` (`category`),
  ADD KEY `idx_skills_active` (`is_active`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_username` (`username`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `idx_users_role` (`role_id`),
  ADD KEY `idx_users_status` (`status`),
  ADD KEY `idx_users_email_status` (`email`,`status`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_sessions_hash` (`session_hash`),
  ADD KEY `idx_user_sessions_user` (`user_id`,`is_active`),
  ADD KEY `idx_user_sessions_activity` (`last_activity`);

--
-- Indexes for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_settings_user_key` (`user_id`,`setting_key`),
  ADD KEY `idx_user_settings_user` (`user_id`);

--
-- Indexes for table `work_experience`
--
ALTER TABLE `work_experience`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_work_experience_user` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `application_documents`
--
ALTER TABLE `application_documents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `application_responses`
--
ALTER TABLE `application_responses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=204;

--
-- AUTO_INCREMENT for table `availability_statuses`
--
ALTER TABLE `availability_statuses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `candidate_profiles`
--
ALTER TABLE `candidate_profiles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `candidate_saved_opportunities`
--
ALTER TABLE `candidate_saved_opportunities`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `candidate_skills`
--
ALTER TABLE `candidate_skills`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `certifications`
--
ALTER TABLE `certifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `cohorts`
--
ALTER TABLE `cohorts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `cohort_documents`
--
ALTER TABLE `cohort_documents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `cohort_eligibility`
--
ALTER TABLE `cohort_eligibility`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `cohort_participants`
--
ALTER TABLE `cohort_participants`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cohort_skills`
--
ALTER TABLE `cohort_skills`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;

--
-- AUTO_INCREMENT for table `cohort_workflow`
--
ALTER TABLE `cohort_workflow`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=137;

--
-- AUTO_INCREMENT for table `consents`
--
ALTER TABLE `consents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `deletion_requests`
--
ALTER TABLE `deletion_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `email_verifications`
--
ALTER TABLE `email_verifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `notification_preferences`
--
ALTER TABLE `notification_preferences`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `opportunities`
--
ALTER TABLE `opportunities`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `opportunity_applications`
--
ALTER TABLE `opportunity_applications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `opportunity_documents`
--
ALTER TABLE `opportunity_documents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `opportunity_eligibility`
--
ALTER TABLE `opportunity_eligibility`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `opportunity_questions`
--
ALTER TABLE `opportunity_questions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `opportunity_responsibilities`
--
ALTER TABLE `opportunity_responsibilities`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=583;

--
-- AUTO_INCREMENT for table `opportunity_skills`
--
ALTER TABLE `opportunity_skills`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=163;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `programmes`
--
ALTER TABLE `programmes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `programme_eligibility`
--
ALTER TABLE `programme_eligibility`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `programme_skills`
--
ALTER TABLE `programme_skills`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `qualifications`
--
ALTER TABLE `qualifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `remember_me_tokens`
--
ALTER TABLE `remember_me_tokens`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `skills`
--
ALTER TABLE `skills`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `user_settings`
--
ALTER TABLE `user_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `work_experience`
--
ALTER TABLE `work_experience`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `fk_applications_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_applications_opportunity` FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `application_documents`
--
ALTER TABLE `application_documents`
  ADD CONSTRAINT `fk_app_docs_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `application_responses`
--
ALTER TABLE `application_responses`
  ADD CONSTRAINT `fk_app_responses_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_app_responses_question` FOREIGN KEY (`question_id`) REFERENCES `opportunity_questions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `candidate_profiles`
--
ALTER TABLE `candidate_profiles`
  ADD CONSTRAINT `fk_candidate_profiles_availability` FOREIGN KEY (`availability_status_id`) REFERENCES `availability_statuses` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_candidate_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `candidate_saved_opportunities`
--
ALTER TABLE `candidate_saved_opportunities`
  ADD CONSTRAINT `fk_saved_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_saved_opportunity` FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `candidate_skills`
--
ALTER TABLE `candidate_skills`
  ADD CONSTRAINT `fk_candidate_skills_skill` FOREIGN KEY (`skill_id`) REFERENCES `skills` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_candidate_skills_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `certifications`
--
ALTER TABLE `certifications`
  ADD CONSTRAINT `fk_certifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `cohorts`
--
ALTER TABLE `cohorts`
  ADD CONSTRAINT `fk_cohorts_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cohorts_programme` FOREIGN KEY (`programme_id`) REFERENCES `programmes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `cohort_documents`
--
ALTER TABLE `cohort_documents`
  ADD CONSTRAINT `fk_cohort_docs_cohort` FOREIGN KEY (`cohort_id`) REFERENCES `cohorts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `cohort_eligibility`
--
ALTER TABLE `cohort_eligibility`
  ADD CONSTRAINT `fk_cohort_elig_cohort` FOREIGN KEY (`cohort_id`) REFERENCES `cohorts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `cohort_participants`
--
ALTER TABLE `cohort_participants`
  ADD CONSTRAINT `fk_cohort_participants_cohort` FOREIGN KEY (`cohort_id`) REFERENCES `cohorts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cohort_participants_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `cohort_skills`
--
ALTER TABLE `cohort_skills`
  ADD CONSTRAINT `fk_cohort_skills_cohort` FOREIGN KEY (`cohort_id`) REFERENCES `cohorts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `cohort_workflow`
--
ALTER TABLE `cohort_workflow`
  ADD CONSTRAINT `fk_cohort_workflow_cohort` FOREIGN KEY (`cohort_id`) REFERENCES `cohorts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `consents`
--
ALTER TABLE `consents`
  ADD CONSTRAINT `fk_consents_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `deletion_requests`
--
ALTER TABLE `deletion_requests`
  ADD CONSTRAINT `fk_deletion_req_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `fk_documents_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_documents_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD CONSTRAINT `fk_email_verifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD CONSTRAINT `fk_login_attempts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `notification_preferences`
--
ALTER TABLE `notification_preferences`
  ADD CONSTRAINT `fk_notif_pref_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `opportunities`
--
ALTER TABLE `opportunities`
  ADD CONSTRAINT `fk_opportunities_cohort` FOREIGN KEY (`cohort_id`) REFERENCES `cohorts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_opportunities_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_opportunities_programme` FOREIGN KEY (`programme_id`) REFERENCES `programmes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `opportunity_applications`
--
ALTER TABLE `opportunity_applications`
  ADD CONSTRAINT `fk_app_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_app_opportunity` FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `opportunity_documents`
--
ALTER TABLE `opportunity_documents`
  ADD CONSTRAINT `fk_opp_docs_opportunity` FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `opportunity_eligibility`
--
ALTER TABLE `opportunity_eligibility`
  ADD CONSTRAINT `fk_opp_elig_opportunity` FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `opportunity_questions`
--
ALTER TABLE `opportunity_questions`
  ADD CONSTRAINT `fk_opp_questions_opportunity` FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `opportunity_responsibilities`
--
ALTER TABLE `opportunity_responsibilities`
  ADD CONSTRAINT `fk_opp_resp_opportunity` FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `opportunity_skills`
--
ALTER TABLE `opportunity_skills`
  ADD CONSTRAINT `fk_opp_skills_opportunity` FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `programmes`
--
ALTER TABLE `programmes`
  ADD CONSTRAINT `fk_programmes_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `programme_eligibility`
--
ALTER TABLE `programme_eligibility`
  ADD CONSTRAINT `fk_prog_elig_programme` FOREIGN KEY (`programme_id`) REFERENCES `programmes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `programme_skills`
--
ALTER TABLE `programme_skills`
  ADD CONSTRAINT `fk_programme_skills_programme` FOREIGN KEY (`programme_id`) REFERENCES `programmes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `qualifications`
--
ALTER TABLE `qualifications`
  ADD CONSTRAINT `fk_qualifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `remember_me_tokens`
--
ALTER TABLE `remember_me_tokens`
  ADD CONSTRAINT `fk_remember_me_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `fk_user_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD CONSTRAINT `fk_user_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `work_experience`
--
ALTER TABLE `work_experience`
  ADD CONSTRAINT `fk_work_experience_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
