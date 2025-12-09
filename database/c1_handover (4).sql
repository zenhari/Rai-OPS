-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Dec 08, 2025 at 06:30 PM
-- Server version: 10.11.13-MariaDB-0ubuntu0.24.04.1
-- PHP Version: 8.3.11

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `c1_handover`
--

-- --------------------------------------------------------

--
-- Table structure for table `carriedforwarditemlist`
--

CREATE TABLE `carriedforwarditemlist` (
  `id` int(11) NOT NULL,
  `carriedac_reg` varchar(50) DEFAULT NULL,
  `defect_description` text DEFAULT NULL,
  `open_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `mel_ref` varchar(50) DEFAULT NULL,
  `cat` varchar(50) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `handover_id` int(11) DEFAULT NULL,
  `nis` varchar(80) DEFAULT NULL,
  `catremarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crewdata`
--

CREATE TABLE `crewdata` (
  `id` int(11) NOT NULL,
  `certifying_staff` varchar(100) DEFAULT NULL,
  `non_certified_staff` varchar(100) DEFAULT NULL,
  `contractual_staff` varchar(100) DEFAULT NULL,
  `vacation` varchar(100) DEFAULT NULL,
  `mission` varchar(100) DEFAULT NULL,
  `training` varchar(100) DEFAULT NULL,
  `sick_leave` varchar(100) DEFAULT NULL,
  `handover_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `flightinformation`
--

CREATE TABLE `flightinformation` (
  `id` int(11) NOT NULL,
  `ac_reg` varchar(50) DEFAULT NULL,
  `flt_no` varchar(50) DEFAULT NULL,
  `type_of_check` varchar(100) DEFAULT NULL,
  `technical_delay_reason` varchar(100) DEFAULT NULL,
  `staff` varchar(100) DEFAULT NULL,
  `handover_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `handover`
--

CREATE TABLE `handover` (
  `id` int(11) NOT NULL,
  `date` date DEFAULT NULL,
  `station` varchar(50) DEFAULT NULL,
  `day_night` varchar(50) DEFAULT NULL,
  `shift` varchar(50) DEFAULT NULL,
  `shift_supervisor` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `sign1` varchar(200) NOT NULL,
  `sign2` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `partreplacementrecord`
--

CREATE TABLE `partreplacementrecord` (
  `id` int(11) NOT NULL,
  `partac_reg` varchar(50) DEFAULT NULL,
  `part_name` varchar(100) DEFAULT NULL,
  `pn_on` varchar(50) DEFAULT NULL,
  `sn_on` varchar(50) DEFAULT NULL,
  `pn_off` varchar(50) DEFAULT NULL,
  `sn_off` varchar(50) DEFAULT NULL,
  `atl_no` varchar(50) DEFAULT NULL,
  `handover_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(200) NOT NULL,
  `password` varchar(200) NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_active` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `mobile` varchar(100) NOT NULL,
  `access` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `carriedforwarditemlist`
--
ALTER TABLE `carriedforwarditemlist`
  ADD PRIMARY KEY (`id`),
  ADD KEY `handover_id` (`handover_id`);

--
-- Indexes for table `crewdata`
--
ALTER TABLE `crewdata`
  ADD PRIMARY KEY (`id`),
  ADD KEY `handover_id` (`handover_id`);

--
-- Indexes for table `flightinformation`
--
ALTER TABLE `flightinformation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `handover_id` (`handover_id`);

--
-- Indexes for table `handover`
--
ALTER TABLE `handover`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `partreplacementrecord`
--
ALTER TABLE `partreplacementrecord`
  ADD PRIMARY KEY (`id`),
  ADD KEY `handover_id` (`handover_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `carriedforwarditemlist`
--
ALTER TABLE `carriedforwarditemlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crewdata`
--
ALTER TABLE `crewdata`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `flightinformation`
--
ALTER TABLE `flightinformation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `handover`
--
ALTER TABLE `handover`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `partreplacementrecord`
--
ALTER TABLE `partreplacementrecord`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `carriedforwarditemlist`
--
ALTER TABLE `carriedforwarditemlist`
  ADD CONSTRAINT `carriedforwarditemlist_ibfk_1` FOREIGN KEY (`handover_id`) REFERENCES `handover` (`id`);

--
-- Constraints for table `crewdata`
--
ALTER TABLE `crewdata`
  ADD CONSTRAINT `crewdata_ibfk_1` FOREIGN KEY (`handover_id`) REFERENCES `handover` (`id`);

--
-- Constraints for table `flightinformation`
--
ALTER TABLE `flightinformation`
  ADD CONSTRAINT `flightinformation_ibfk_1` FOREIGN KEY (`handover_id`) REFERENCES `handover` (`id`);

--
-- Constraints for table `partreplacementrecord`
--
ALTER TABLE `partreplacementrecord`
  ADD CONSTRAINT `partreplacementrecord_ibfk_1` FOREIGN KEY (`handover_id`) REFERENCES `handover` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
