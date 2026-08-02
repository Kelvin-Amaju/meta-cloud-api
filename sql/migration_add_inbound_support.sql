USE `netgrity_wa`;

-- Adds 'received' so inbound messages persisted from webhooks can be
-- distinguished from outbound delivery statuses.
ALTER TABLE `business_messages`
  MODIFY `status` enum('queued','sent','delivered','read','failed','received') NOT NULL DEFAULT 'queued';
