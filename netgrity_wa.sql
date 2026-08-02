-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Aug 02, 2026 at 08:13 PM
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
-- Table structure for table `broadcast_campaigns`
--

CREATE TABLE `broadcast_campaigns` (
  `id` bigint UNSIGNED NOT NULL,
  `business_id` int UNSIGNED NOT NULL,
  `campaign_name` varchar(150) NOT NULL,
  `payload_type` enum('template','media','interactive') NOT NULL DEFAULT 'template',
  `template_name` varchar(150) DEFAULT NULL,
  `message_body` text,
  `media_url` text,
  `media_type` varchar(30) DEFAULT NULL,
  `interactive_payload` json DEFAULT NULL,
  `recipient_file` varchar(255) DEFAULT NULL,
  `total_recipients` int UNSIGNED NOT NULL DEFAULT '0',
  `sent_count` int UNSIGNED NOT NULL DEFAULT '0',
  `status` enum('draft','queued','running','completed','failed') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  `display_name` varchar(120) DEFAULT NULL,
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

--
-- Dumping data for table `businesses`
--

INSERT INTO `businesses` (`id`, `uuid`, `name`, `product_line`, `meta_business_id`, `waba_id`, `phone_number_id`, `display_name`, `display_phone_number`, `access_token`, `token_type`, `token_expires_at`, `status`, `onboarding_method`, `onboarded_at`, `created_at`, `updated_at`) VALUES
(3, 'c3d4e5f6-a7b8-9c0d-1e2f-3a4b5c6d7e8f', 'Netgrity Customer Support', 'erp', '1711462023424933', '2250546469118247', '1270265856164756', NULL, '+1-555-676-0161', 'EAAYUkM3z66UBSKVBlmBM4FBRF6ZCOTFDkJkvEed60WOrHktZB2TZAZBSJMOO39MtTd6C9zhxoZClk9ZBywBvouVpzZCrVIJF3RnSZALSzTnxE0qPpXRkyhaAfYVg47Up6P95HlhpBxGxdFrGs2XYxY6vcjnkJxOhwuL2bchQcM3JIUcWHlXe3BT8QmLqp9sYJmPbfwzKQZCdbiv9XTx8IZCP5Dhza7LZBZCR2tkXk0dY7Bl1OomWbiCvmdXXGBNe3Do9m8K1lWAJFI9NVqYp1hM5X9DUvuoZD', 'temporary', NULL, 'active', 'manual', '2026-07-30 14:17:37', '2026-07-30 14:17:37', '2026-07-30 15:14:37'),
(4, 'd4e5f6a7-b8c9-0d1e-2f3a-4b5c6d7e8f9a', 'Netgrity Enterprise', 'erp', '1595825405219184', '1028557119576992', '1294336087092508', 'Netgrity Enterprise', '+1-555-602-5615', 'EAAWrZAQ5m0XABSG2neQweyzhYxF1WfeguTwyhKkbfvHDz0ZCurRcdvVTgZCQxXR6TVeN2ZA0AEbZB14qmJaEspO9no4NZAqcnnvPBfqhkMfAGl4zDghlBWppYgneCUHcZCDYdQHiaJwpQTTveTV2OFyOPT9ipTuurtM4bThso1uZAaYQaWJ11cKItznlnGccjYmDZA8R5n8oCpCorWEiY40JVyHgcTjAQwSOXEijTXZAgHSDvwal4iZBsiDih56jkNf7zspRfyVoWrhbNKn5eGP5jgU2kvZBogkup6SQvQZDZD', 'temporary', NULL, 'active', 'manual', '2026-07-30 14:17:37', '2026-07-30 14:17:37', '2026-08-02 07:20:26');

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
  `delivered_at` datetime DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `error_message` text,
  `media_url` text,
  `media_type` varchar(30) DEFAULT NULL,
  `interactive_payload` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `business_messages`
--

INSERT INTO `business_messages` (`id`, `business_id`, `direction`, `wa_message_id`, `to_number`, `from_number`, `message_type`, `body`, `status`, `delivered_at`, `read_at`, `error_message`, `media_url`, `media_type`, `interactive_payload`, `created_at`, `updated_at`) VALUES
(2, 4, 'outbound', 'wamid.failed_1785418674', '2349044313696', NULL, 'text', 'Netgrity Systems\r\nChecking Multi SAAS\n\n*DO NOT REPLY TO THIS MESSAGE*', 'failed', NULL, NULL, 'Authentication Error', NULL, NULL, NULL, '2026-07-30 14:37:54', '2026-07-30 14:37:54'),
(3, 4, 'outbound', 'wamid.HBgNMjM0OTA0NDMxMzY5NhUCABEYEjJEQjI2N0Y4MEUwMTFDM0Y2MQA=', '2349044313696', NULL, 'text', 'Testing Multi Saas\n\n*DO NOT REPLY TO THIS MESSAGE*', 'sent', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-30 14:54:54', '2026-07-30 14:54:54'),
(4, 4, 'outbound', 'wamid.HBgNMjM0OTA0NDMxMzY5NhUCABEYEjM3RkMwN0Y4NDc3ODc1QUI2RAA=', '2349044313696', NULL, 'text', 'Testing SAAS', 'sent', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-30 14:58:19', '2026-07-30 14:58:19'),
(5, 3, 'outbound', 'wamid.failed_1785420464', '2349044313696', NULL, 'text', 'Netgrity Customer Support\n\n*DO NOT REPLY TO THIS MESSAGE*', 'failed', NULL, NULL, 'Authentication Error', NULL, NULL, NULL, '2026-07-30 15:07:44', '2026-07-30 15:07:44'),
(6, 3, 'outbound', 'wamid.HBgNMjM0OTA0NDMxMzY5NhUCABEYEjNFOUNERUE1OERERkU1OUM0QgA=', '2349044313696', NULL, 'text', 'Netgrity Customer Support\n\n*DO NOT REPLY TO THIS MESSAGE*', 'sent', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-30 15:12:57', '2026-07-30 15:12:57'),
(7, 3, 'outbound', 'wamid.HBgNMjM0OTA0NDMxMzY5NhUCABEYEjkxRkIwRTE2RjU5MTdDNzMzOQA=', '2349044313696', NULL, 'text', 'Netgrity Customer Support', 'sent', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-30 15:15:04', '2026-07-30 15:15:04'),
(8, 3, 'outbound', 'wamid.HBgNMjM0OTA0NDMxMzY5NhUCABEYEjNFRDA5RDhBQUEzMTYxMkM4MQA=', '2349044313696', NULL, 'text', 'Netgrity Customer Support', 'sent', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-30 15:15:56', '2026-07-30 15:15:56'),
(9, 4, 'outbound', 'wamid.HBgNMjM0OTA0NDMxMzY5NhUCABEYEkU1MDc5NzRBMDFDRjFFOUM1NQA=', '2349044313696', NULL, 'text', 'Netgrity Enterprise ERP', 'sent', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-30 15:24:38', '2026-07-30 15:24:38'),
(10, 3, 'outbound', 'wamid.HBgNMjM0OTA0NDMxMzY5NhUCABEYEjJFMDlCNkUxNTg0QjQ2MDVDRQA=', '2349044313696', NULL, 'text', 'Netgrity Customer Support', 'sent', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-30 15:25:07', '2026-07-30 15:25:07'),
(11, 4, 'outbound', 'wamid.HBgNMjM0OTA0NDMxMzY5NhUCABEYEkVBQzRCNTc5OEE3QzJDMzA4OQA=', '2349044313696', NULL, 'text', 'Testing App1', 'sent', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-30 15:28:09', '2026-07-30 15:28:09'),
(12, 3, 'outbound', 'wamid.HBgNMjM0OTA0NDMxMzY5NhUCABEYEjMzOTkxRTc3NTE0QTRCMjExMwA=', '2349044313696', NULL, 'text', 'Testing App2', 'sent', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-30 15:28:26', '2026-07-30 15:28:26'),
(13, 4, 'outbound', 'wamid.HBgNMjM0ODE3NDYxOTUwMhUCABEYEkFDQTI3RDRFOEZENjY3MEIxQQA=', '2348174619502', NULL, 'text', 'Netgrity Systems\r\n\r\nThis is message number 1\r\n\r\nPlease reply to this message', 'sent', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-30 15:43:23', '2026-07-30 15:43:23'),
(14, 3, 'outbound', 'wamid.HBgNMjM0ODE3NDYxOTUwMhUCABEYEjM5RjAzMzM2Mzk1M0YzOEVEMQA=', '2348174619502', NULL, 'text', 'Netgrity Customer Support\r\n\r\nThis is message number 2\r\n\r\nPlease reply to this message', 'sent', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-30 15:44:09', '2026-07-30 15:44:09'),
(15, 4, 'outbound', 'wamid.HBgNMjM0ODE3NDYxOTUwMhUCABEYEjAxM0ZGRjQ0Q0M4REVBNTVDNQA=', '2348174619502', NULL, 'text', 'Netgrity System\r\n\r\nThis is message number 1\n\n*DO NOT REPLY TO THIS MESSAGE*', 'sent', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-30 15:50:53', '2026-07-30 15:50:53'),
(16, 3, 'outbound', 'wamid.HBgNMjM0ODE3NDYxOTUwMhUCABEYEkQyMUM0RkZEMEE1MzNFQkEwNgA=', '2348174619502', NULL, 'text', 'Netgrity Customer Support\r\n\r\nThis is message number 2', 'sent', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-30 15:52:12', '2026-07-30 15:52:12'),
(17, 4, 'outbound', 'wamid.HBgNMjM0OTA0NDMxMzY5NhUCABEYEjFGQUJCNzhDREM0NTAxNzI0RgA=', '2349044313696', NULL, 'text', 'Netgrity Systems\r\n\r\nThis is message number 1', 'sent', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-30 15:53:35', '2026-07-30 15:53:35'),
(18, 3, 'outbound', 'wamid.HBgNMjM0OTA0NDMxMzY5NhUCABEYEjU2MTJFMDk1M0U4N0RBQjcwQgA=', '2349044313696', NULL, 'text', 'Netgrity Customer Support\r\n\r\nThis is message number 2', 'sent', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-30 15:54:03', '2026-07-30 15:54:03'),
(19, 4, 'outbound', 'wamid.failed_1785579241', '2349044313696', NULL, 'text', '[Template: appointment_reminder] Hi {{1}}, this is a reminder for your appointment on {{2}} at {{3}}.', 'failed', NULL, NULL, 'Authentication Error', NULL, NULL, NULL, '2026-08-01 11:14:01', '2026-08-01 11:14:01'),
(20, 4, 'outbound', 'wamid.HBgNMjM0OTA0NDMxMzY5NhUCABEYEkZCODA2RTk3MkFDMjFFODk5RAA=', '2349044313696', NULL, 'text', 'hello text', 'sent', NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-01 11:17:19', '2026-08-01 11:17:19'),
(21, 4, 'outbound', 'wamid.failed_1785579496', '2349044313696', NULL, 'text', '[Template: appointment_reminder] Hi {{1}}, this is a reminder for your appointment on {{2}} at {{3}}.', 'failed', NULL, NULL, '(#132001) Template name does not exist in the translation', NULL, NULL, NULL, '2026-08-01 11:18:16', '2026-08-01 11:18:16'),
(22, 4, 'outbound', 'wamid.failed_1785579555', '2349044313696', NULL, 'text', '[Template: order_shipped] Hi {{1}}, your order #{{2}} has shipped and is on its way!', 'failed', NULL, NULL, '(#132001) Template name does not exist in the translation', NULL, NULL, NULL, '2026-08-01 11:19:15', '2026-08-01 11:19:15');

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

-- --------------------------------------------------------

--
-- Table structure for table `message_templates`
--

CREATE TABLE `message_templates` (
  `id` int UNSIGNED NOT NULL,
  `business_id` int UNSIGNED NOT NULL,
  `meta_template_id` varchar(100) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `language` varchar(10) NOT NULL DEFAULT 'en_US',
  `category` enum('utility','marketing','authentication') NOT NULL DEFAULT 'utility',
  `status` enum('approved','pending','rejected','draft') NOT NULL DEFAULT 'draft',
  `body_text` text NOT NULL,
  `variable_count` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `broadcast_campaigns`
--
ALTER TABLE `broadcast_campaigns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_business_status` (`business_id`,`status`);

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
-- Indexes for table `message_templates`
--
ALTER TABLE `message_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_business_template` (`business_id`,`name`,`language`),
  ADD KEY `idx_business_status` (`business_id`,`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `broadcast_campaigns`
--
ALTER TABLE `broadcast_campaigns`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `businesses`
--
ALTER TABLE `businesses`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `business_messages`
--
ALTER TABLE `business_messages`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `business_webhook_events`
--
ALTER TABLE `business_webhook_events`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `message_templates`
--
ALTER TABLE `message_templates`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `broadcast_campaigns`
--
ALTER TABLE `broadcast_campaigns`
  ADD CONSTRAINT `broadcast_campaigns_ibfk_1` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE;

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

--
-- Constraints for table `message_templates`
--
ALTER TABLE `message_templates`
  ADD CONSTRAINT `message_templates_ibfk_1` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
