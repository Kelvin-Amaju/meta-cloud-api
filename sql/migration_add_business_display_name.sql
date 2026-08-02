-- Migration: add the display_name column used by the updated business edit/create flow
ALTER TABLE `businesses`
  ADD COLUMN `display_name` varchar(120) NULL AFTER `phone_number_id`;

-- Optional safety index for display/search usage if you want to support name lookups
-- ALTER TABLE `businesses`
--   ADD INDEX `idx_display_name` (`display_name`);
