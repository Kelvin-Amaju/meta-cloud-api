CREATE TABLE messages (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    wa_message_id VARCHAR(120) UNIQUE,
    direction ENUM('inbound', 'outbound'),
    phone VARCHAR(25),
    type VARCHAR(30),
    body LONGTEXT,
    status VARCHAR(30),
    media_id VARCHAR(120),
    raw_payload JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE tenants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(150) NOT NULL,
    contact_name VARCHAR(150) DEFAULT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    -- Meta Information
    business_manager_id VARCHAR(100) DEFAULT NULL,
    waba_id VARCHAR(100) DEFAULT NULL,
    phone_number_id VARCHAR(100) DEFAULT NULL,
    display_phone_number VARCHAR(30) DEFAULT NULL,
    whatsapp_business_account_name VARCHAR(150) DEFAULT NULL,
    -- API Credentials
    access_token TEXT,
    token_expiry DATETIME DEFAULT NULL,
    verify_token VARCHAR(255) DEFAULT NULL,
    -- Webhook
    webhook_secret VARCHAR(255) DEFAULT NULL,
    -- Billing
    plan ENUM('trial', 'starter', 'business', 'enterprise') DEFAULT 'trial',
    expires_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

ALTER TABLE
    contacts
ADD
    tenant_id INT UNSIGNED NOT NULL;

ALTER TABLE
    messages
ADD
    tenant_id INT UNSIGNED NOT NULL;

ALTER TABLE
    templates
ADD
    tenant_id INT UNSIGNED NOT NULL;

ALTER TABLE
    campaigns
ADD
    tenant_id INT UNSIGNED NOT NULL;

ALTER TABLE
    logs
ADD
    tenant_id INT UNSIGNED NOT NULL;

FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE