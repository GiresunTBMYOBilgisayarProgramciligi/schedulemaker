# mysql -u kullanici_adi vt_adi < update-database.sql
#v0.3.2 -> v0.3.3

-- KVKK ve Gizlilik Politikası Kullanıcı Onay Tablosu
CREATE TABLE IF NOT EXISTS user_consents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    consent_type VARCHAR(50) NOT NULL COMMENT 'kvkk_clarification, privacy_policy vb.',
    version VARCHAR(20) NOT NULL DEFAULT 'v1.0' COMMENT 'Metin sürümü',
    ip_address VARCHAR(45) NOT NULL COMMENT 'Kullanıcının IP adresi',
    user_agent VARCHAR(255) NULL COMMENT 'Tarayıcı/cihaz bilgisi',
    accepted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_consents_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_consent_lookup (user_id, consent_type, version)
) ENGINE = INNODB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;