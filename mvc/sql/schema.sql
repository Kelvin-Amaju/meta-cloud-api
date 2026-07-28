CREATE TABLE messages (

    id BIGINT AUTO_INCREMENT PRIMARY KEY,

    wa_message_id VARCHAR(120) UNIQUE,

    direction ENUM('inbound','outbound'),

    phone VARCHAR(25),

    type VARCHAR(30),

    body LONGTEXT,

    status VARCHAR(30),

    media_id VARCHAR(120),

    raw_payload JSON,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);