-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Dec 14, 2025 at 06:59 AM
-- Server version: 10.11.13-MariaDB-ubu2204
-- PHP Version: 8.3.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `raiops_data`
--

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL COMMENT 'Course/Class name',
  `duration` varchar(100) DEFAULT NULL COMMENT 'Course duration (e.g., "40 hours", "2 weeks")',
  `instructor_id` int(11) DEFAULT NULL COMMENT 'Instructor user ID',
  `location` varchar(255) DEFAULT NULL COMMENT 'Class location',
  `material_file` varchar(255) DEFAULT NULL COMMENT 'Path to uploaded material file (PDF/DOCX)',
  `description` text DEFAULT NULL,
  `status` enum('active','inactive','completed') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`id`, `name`, `duration`, `instructor_id`, `location`, `material_file`, `description`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(6, 'PBN/NAVIGATION', '06', 13, 'Class C', NULL, '', 'active', 36, '2025-12-09 06:22:00', '2025-12-09 06:22:00'),
(7, 'CRM/DRM', '06', 162, 'Class C', NULL, '', 'active', 45, '2025-12-13 06:41:40', '2025-12-13 06:41:40'),
(8, 'CRM/DRM', '06', 162, 'Class C', NULL, '', 'active', 45, '2025-12-13 06:44:52', '2025-12-13 06:44:52'),
(9, 'test', '02', 5, 'Class C', NULL, '', 'active', 5, '2025-12-13 10:01:43', '2025-12-13 10:01:43');

-- --------------------------------------------------------

--
-- Table structure for table `class_assignments`
--

CREATE TABLE `class_assignments` (
  `id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'Assigned to specific user',
  `role_id` int(11) DEFAULT NULL COMMENT 'Assigned to all users with this role',
  `assigned_by` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `class_assignments`
--

INSERT INTO `class_assignments` (`id`, `class_id`, `user_id`, `role_id`, `assigned_by`, `assigned_at`) VALUES
(10, 4, 206, NULL, 36, '2025-12-07 13:06:03'),
(11, 4, 5, NULL, 36, '2025-12-07 13:06:03'),
(12, 4, 173, NULL, 36, '2025-12-07 13:06:03'),
(13, 4, 131, NULL, 36, '2025-12-07 13:06:03'),
(14, 4, NULL, 14, 36, '2025-12-07 13:06:03'),
(15, 4, NULL, 11, 36, '2025-12-07 13:06:03'),
(24, 3, 10, NULL, 1, '2025-12-07 16:04:32'),
(25, 3, NULL, 16, 1, '2025-12-07 16:04:32'),
(26, 5, NULL, 16, 42, '2025-12-08 06:47:29'),
(27, 6, 206, NULL, 36, '2025-12-09 06:22:00'),
(28, 6, 5, NULL, 36, '2025-12-09 06:22:00'),
(29, 6, 131, NULL, 36, '2025-12-09 06:22:00'),
(30, 7, 84, NULL, 45, '2025-12-13 06:41:40'),
(31, 7, 120, NULL, 45, '2025-12-13 06:41:40'),
(32, 7, 119, NULL, 45, '2025-12-13 06:41:40'),
(43, 8, 119, NULL, 45, '2025-12-13 06:52:40'),
(44, 8, 164, NULL, 45, '2025-12-13 06:52:40'),
(51, 9, NULL, 16, 1, '2025-12-14 06:49:23'),
(52, 9, NULL, 14, 1, '2025-12-14 06:49:23'),
(53, 9, NULL, 22, 1, '2025-12-14 06:49:23');

-- --------------------------------------------------------

--
-- Table structure for table `class_schedules`
--

CREATE TABLE `class_schedules` (
  `id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `day_of_week` enum('saturday','sunday','monday','tuesday','wednesday','thursday','friday') NOT NULL,
  `start_time` time NOT NULL COMMENT 'Class start time',
  `end_time` time NOT NULL COMMENT 'Class end time',
  `start_date` date DEFAULT NULL COMMENT 'First occurrence date',
  `end_date` date DEFAULT NULL COMMENT 'Last occurrence date',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `class_schedules`
--

INSERT INTO `class_schedules` (`id`, `class_id`, `day_of_week`, `start_time`, `end_time`, `start_date`, `end_date`, `created_at`, `updated_at`) VALUES
(9, 4, 'monday', '13:00:00', '19:00:00', '2025-12-08', '2025-12-08', '2025-12-07 13:06:03', '2025-12-07 13:06:03'),
(22, 3, 'monday', '09:00:00', '13:00:00', '2025-12-07', '2025-12-07', '2025-12-07 16:04:32', '2025-12-07 16:04:32'),
(23, 3, 'wednesday', '09:00:00', '13:00:00', '2025-12-10', '2025-12-10', '2025-12-07 16:04:32', '2025-12-07 16:04:32'),
(24, 5, 'monday', '13:00:00', '19:00:00', '2025-12-08', '2025-12-08', '2025-12-08 06:47:29', '2025-12-08 06:47:29'),
(25, 6, 'monday', '13:00:00', '19:00:00', '2025-12-08', '2025-12-08', '2025-12-09 06:22:00', '2025-12-09 06:22:00'),
(26, 7, 'monday', '10:00:00', '16:00:00', '2025-12-15', '2025-12-15', '2025-12-13 06:41:40', '2025-12-13 06:41:40'),
(32, 8, 'sunday', '00:00:00', '18:00:00', '2025-12-14', '2025-12-14', '2025-12-13 06:52:40', '2025-12-13 06:52:40'),
(38, 9, 'sunday', '08:00:00', '14:00:00', '2025-12-13', '2025-12-13', '2025-12-14 06:49:23', '2025-12-14 06:49:23'),
(39, 9, 'monday', '08:00:00', '14:00:00', '2025-12-14', '2025-12-14', '2025-12-14 06:49:23', '2025-12-14 06:49:23'),
(40, 9, 'wednesday', '09:00:00', '11:00:00', '2025-12-15', '2025-12-14', '2025-12-14 06:49:23', '2025-12-14 06:49:23');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_instructor_id` (`instructor_id`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `class_assignments`
--
ALTER TABLE `class_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_class` (`class_id`,`user_id`),
  ADD KEY `idx_class_id` (`class_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_role_id` (`role_id`),
  ADD KEY `idx_assigned_by` (`assigned_by`);

--
-- Indexes for table `class_schedules`
--
ALTER TABLE `class_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_class_id` (`class_id`),
  ADD KEY `idx_day_of_week` (`day_of_week`),
  ADD KEY `idx_dates` (`start_date`,`end_date`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `class_assignments`
--
ALTER TABLE `class_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `class_schedules`
--
ALTER TABLE `class_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `classes_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `class_assignments`
--
ALTER TABLE `class_assignments`
  ADD CONSTRAINT `class_assignments_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_assignments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_assignments_ibfk_3` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_assignments_ibfk_4` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `class_schedules`
--
ALTER TABLE `class_schedules`
  ADD CONSTRAINT `class_schedules_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
