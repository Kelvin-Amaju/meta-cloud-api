-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jul 30, 2026 at 12:58 PM
-- Server version: 8.4.3
-- PHP Version: 8.4.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `netgrity_wa`
--

-- --------------------------------------------------------

--
-- Table structure for table `businesses`
--

CREATE TABLE `businesses` (
  `id` int UNSIGNED NOT NULL,
  `uuid` char(36) NOT NULL,
  `name` varchar(150) NOT NULL,
  `product_line` enum('hotel','school','hospital','erp','crm','other') NOT NULL DEFAULT 'other',
  `meta_business_id` varchar(50) DEFAULT NULL,
  `waba_id` varchar(50) DEFAULT NULL,
  `phone_number_id` varchar(50) NOT NULL,
  `display_phone_number` varchar(20) DEFAULT NULL,
  `access_token` text NOT NULL,
  `token_type` enum('temporary','system_user') NOT NULL DEFAULT 'system_user',
  `token_expires_at` datetime DEFAULT NULL,
  `status` enum('pending','active','suspended','revoked') NOT NULL DEFAULT 'pending',
  `onboarding_method` enum('manual','embedded_signup') NOT NULL DEFAULT 'manual',
  `onboarded_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `business_messages`
--

CREATE TABLE `business_messages` (
  `id` bigint UNSIGNED NOT NULL,
  `business_id` int UNSIGNED NOT NULL,
  `direction` enum('outbound','inbound') NOT NULL,
  `wa_message_id` varchar(100) DEFAULT NULL,
  `to_number` varchar(20) DEFAULT NULL,
  `from_number` varchar(20) DEFAULT NULL,
  `message_type` varchar(30) DEFAULT NULL,
  `body` text,
  `status` enum('queued','sent','delivered','read','failed') NOT NULL DEFAULT 'queued',
  `error_message` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `business_webhook_events`
--

CREATE TABLE `business_webhook_events` (
  `id` bigint UNSIGNED NOT NULL,
  `business_id` int UNSIGNED NOT NULL,
  `event_type` varchar(50) DEFAULT NULL,
  `payload` json NOT NULL,
  `processed` tinyint(1) NOT NULL DEFAULT '0',
  `received_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `businesses`
--
ALTER TABLE `businesses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uuid` (`uuid`),
  ADD UNIQUE KEY `phone_number_id` (`phone_number_id`),
  ADD KEY `idx_phone_number_id` (`phone_number_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_product_line` (`product_line`);

--
-- Indexes for table `business_messages`
--
ALTER TABLE `business_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_business_status` (`business_id`,`status`),
  ADD KEY `idx_wa_message_id` (`wa_message_id`);

--
-- Indexes for table `business_webhook_events`
--
ALTER TABLE `business_webhook_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_business_processed` (`business_id`,`processed`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `businesses`
--
ALTER TABLE `businesses`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `business_messages`
--
ALTER TABLE `business_messages`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `business_webhook_events`
--
ALTER TABLE `business_webhook_events`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `business_messages`
--
ALTER TABLE `business_messages`
  ADD CONSTRAINT `business_messages_ibfk_1` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `business_webhook_events`
--
ALTER TABLE `business_webhook_events`
  ADD CONSTRAINT `business_webhook_events_ibfk_1` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
