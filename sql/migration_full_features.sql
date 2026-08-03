USE `netgrity_wa`;

-- =====================================================================
-- Full-feature migration: customers, conversations, broadcast recipients
-- + link business_messages to customers for inbox/threading.
-- =====================================================================

-- 1) customers ------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `business_id` INT UNSIGNED NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `name` VARCHAR(150) DEFAULT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `tags` VARCHAR(255) DEFAULT NULL,
  `notes` TEXT,
  `last_message_at` DATETIME DEFAULT NULL,
  `total_messages` INT UNSIGNED NOT NULL DEFAULT '0',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_business_phone` (`business_id`,`phone`),
  KEY `idx_business` (`business_id`),
  CONSTRAINT `fk_customers_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 2) conversations ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `conversations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `business_id` INT UNSIGNED NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `last_message_at` DATETIME NOT NULL,
  `last_message_preview` VARCHAR(255) DEFAULT NULL,
  `last_direction` ENUM('outbound','inbound') NOT NULL DEFAULT 'inbound',
  `unread_count` INT UNSIGNED NOT NULL DEFAULT '0',
  `status` ENUM('open','closed') NOT NULL DEFAULT 'open',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_business_customer` (`business_id`,`customer_id`),
  KEY `idx_business_last` (`business_id`,`last_message_at`),
  CONSTRAINT `fk_conversations_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_conversations_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 3) broadcast_recipients --------------------------------------------
CREATE TABLE IF NOT EXISTS `broadcast_recipients` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campaign_id` BIGINT UNSIGNED NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `status` ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `wamid` VARCHAR(100) DEFAULT NULL,
  `error_message` TEXT,
  `sent_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_campaign` (`campaign_id`),
  CONSTRAINT `fk_broadcast_recipients_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `broadcast_campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 4) business_messages.customer_id ------------------------------------
ALTER TABLE `business_messages`
  ADD COLUMN `customer_id` INT UNSIGNED DEFAULT NULL AFTER `business_id`,
  ADD KEY `idx_customer` (`customer_id`),
  ADD CONSTRAINT `fk_business_messages_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL;

-- 5) Create customer records from existing message phone numbers ------
INSERT INTO `customers` (business_id, phone, last_message_at, total_messages)
SELECT sub.business_id, sub.phone, sub.last_message_at, sub.total
FROM (
  SELECT m.business_id,
         COALESCE(NULLIF(m.from_number, ''), m.to_number) AS phone,
         MAX(m.created_at) AS last_message_at,
         COUNT(*) AS total
  FROM `business_messages` m
  WHERE COALESCE(NULLIF(m.from_number, ''), m.to_number) IS NOT NULL
  GROUP BY m.business_id, COALESCE(NULLIF(m.from_number, ''), m.to_number)
) sub
ON DUPLICATE KEY UPDATE
  last_message_at = VALUES(last_message_at),
  total_messages  = VALUES(total_messages);

-- 6) Backfill customer links on existing messages ---------------------
UPDATE `business_messages` m
JOIN `customers` c
  ON c.business_id = m.business_id
 AND c.phone = COALESCE(NULLIF(m.from_number, ''), m.to_number)
SET m.customer_id = c.id;

-- 7) Seed conversations from existing messages -------------------------
INSERT INTO `conversations` (business_id, customer_id, last_message_at, last_message_preview, last_direction, unread_count)
SELECT c.business_id, c.id, m.created_at, SUBSTRING(COALESCE(m.body, ''), 1, 255), m.direction, 0
FROM `business_messages` m
JOIN `customers` c ON c.business_id = m.business_id AND c.id = m.customer_id
JOIN (
  SELECT business_id, customer_id, MAX(id) AS max_id
  FROM `business_messages`
  GROUP BY business_id, customer_id
) latest ON latest.max_id = m.id
ON DUPLICATE KEY UPDATE last_message_at = VALUES(last_message_at);
