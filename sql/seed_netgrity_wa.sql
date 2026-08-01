-- Seed Data Script for netgrity_wa database

USE `netgrity_wa`;

-- Insert sample active business accounts if table is empty
INSERT INTO `businesses` (
    `id`,
    `uuid`,
    `name`,
    `product_line`,
    `meta_business_id`,
    `waba_id`,
    `phone_number_id`,
    `display_phone_number`,
    `access_token`,
    `token_type`,
    `status`,
    `onboarding_method`,
    `onboarded_at`
)
SELECT 
    1,
    'a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d',
    'Grand Palace Hotel',
    'hotel',
    'bm_hotel_001',
    'waba_hotel_001',
    '105001234567890',
    '+234 904 431 3696',
    'EAAG...',
    'system_user',
    'active',
    'manual',
    NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT * FROM `businesses` WHERE `id` = 1);

INSERT INTO `businesses` (
    `id`,
    `uuid`,
    `name`,
    `product_line`,
    `meta_business_id`,
    `waba_id`,
    `phone_number_id`,
    `display_phone_number`,
    `access_token`,
    `token_type`,
    `status`,
    `onboarding_method`,
    `onboarded_at`
)
SELECT 
    2,
    'b2c3d4e5-f6a7-8b9c-0d1e-2f3a4b5c6d7e',
    'Apex International School',
    'school',
    'bm_school_002',
    'waba_school_002',
    '105009876543210',
    '+234 801 122 3344',
    'EAAG...',
    'system_user',
    'active',
    'manual',
    NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT * FROM `businesses` WHERE `id` = 2);

INSERT INTO `businesses` (
    `id`,
    `uuid`,
    `name`,
    `product_line`,
    `meta_business_id`,
    `waba_id`,
    `phone_number_id`,
    `display_phone_number`,
    `access_token`,
    `token_type`,
    `status`,
    `onboarding_method`,
    `onboarded_at`
)
SELECT 
    3,
    'c3d4e5f6-a7b8-9c0d-1e2f-3a4b5c6d7e8f',
    'St. Jude Specialist Hospital',
    'hospital',
    'bm_hosp_003',
    'waba_hosp_003',
    '105005556667778',
    '+234 703 998 8776',
    'EAAG...',
    'system_user',
    'active',
    'manual',
    NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT * FROM `businesses` WHERE `id` = 3);

INSERT INTO `businesses` (
    `id`,
    `uuid`,
    `name`,
    `product_line`,
    `meta_business_id`,
    `waba_id`,
    `phone_number_id`,
    `display_phone_number`,
    `access_token`,
    `token_type`,
    `status`,
    `onboarding_method`,
    `onboarded_at`
)
SELECT 
    4,
    'd4e5f6a7-b8c9-0d1e-2f3a-4b5c6d7e8f9a',
    'Netgrity Enterprise ERP',
    'erp',
    'bm_erp_004',
    'waba_erp_004',
    '105004443332221',
    '+234 812 345 6789',
    'EAAG...',
    'system_user',
    'active',
    'manual',
    NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT * FROM `businesses` WHERE `id` = 4);
