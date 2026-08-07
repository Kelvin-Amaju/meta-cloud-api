-- sql/migration_002_broadcast_text_payload.sql
-- Allow free-form "text" broadcast campaigns (was rejected by the enum).

USE `netgrity_wa`;

ALTER TABLE `broadcast_campaigns`
  MODIFY `payload_type` enum('template','text','media','interactive') NOT NULL DEFAULT 'template';
