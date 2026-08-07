USE `netgrity_wa`;

ALTER TABLE `business_messages`
  ADD COLUMN `delivered_at` datetime NULL AFTER `status`,
  ADD COLUMN `read_at` datetime NULL AFTER `delivered_at`,
  ADD COLUMN `media_url` text NULL AFTER `error_message`,
  ADD COLUMN `media_type` varchar(30) NULL AFTER `media_url`,
  ADD COLUMN `interactive_payload` json NULL AFTER `media_type`;

CREATE TABLE IF NOT EXISTS `broadcast_campaigns` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `business_id` int UNSIGNED NOT NULL,
  `campaign_name` varchar(150) NOT NULL,
  `payload_type` enum('template','text','media','interactive') NOT NULL DEFAULT 'template',
  `template_name` varchar(150) DEFAULT NULL,
  `message_body` text DEFAULT NULL,
  `media_url` text DEFAULT NULL,
  `media_type` varchar(30) DEFAULT NULL,
  `interactive_payload` json DEFAULT NULL,
  `recipient_file` varchar(255) DEFAULT NULL,
  `total_recipients` int UNSIGNED NOT NULL DEFAULT '0',
  `sent_count` int UNSIGNED NOT NULL DEFAULT '0',
  `status` enum('draft','queued','running','completed','failed') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_business_status` (`business_id`,`status`),
  CONSTRAINT `broadcast_campaigns_ibfk_1`
    FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
