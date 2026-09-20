# mysql -u kullanici_adi vt_adi < update-database.sql
#v0.3.2 -> v0.3.3

-- ============================================================================
-- 1. KVKK ve Gizlilik Politikası Kullanıcı Onay Tablosu
-- ============================================================================
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

-- ============================================================================
-- 2. Issue #124: Çoklu Schedule ve Birleştirilmiş Ders Hataları İyileştirmesi
-- ============================================================================

-- 2.1 Hoca programlarında hatalı 'group' statüsünde kalmış öğeleri 'single' yap
UPDATE schedule_items si
JOIN schedules s ON si.schedule_id = s.id
SET si.status = 'single'
WHERE s.owner_type = 'user' AND si.status = 'group';

-- 2.2 Birleştirme kaynaklı bozuk serileştirilmiş verileri (fazladan süslü parantez '}}}') düzelt
UPDATE schedule_items
SET data = LEFT(data, LENGTH(data) - 1)
WHERE data LIKE '%}}}';

-- 2.3 Program harici (hoca, derslik, ders) ve NULL kalmış schedule'ların semester_no değerini 0 yap
UPDATE schedules 
SET semester_no = 0 
WHERE semester_no IS NULL OR owner_type != 'program';

-- 2.4 Eski index'i kaldır (varsa 'owner_type')
SET @index_exists = (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'schedules' AND index_name = 'owner_type');
SET @drop_stmt = IF(@index_exists > 0, 'ALTER TABLE schedules DROP INDEX owner_type', 'SELECT 1');
PREPARE stmt FROM @drop_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2.5 Varsa daha önce eklenmiş olabilecek geçici sanal kolonu kaldır (Index silindikten sonra güvenle kaldırılır)
ALTER TABLE schedules DROP COLUMN IF EXISTS semester_no_key;

-- 2.6 semester_no kolonunu NOT NULL DEFAULT 0 yap (Böylece NULL bypass engellenir)
ALTER TABLE schedules MODIFY COLUMN semester_no INT NOT NULL DEFAULT 0;

-- 2.7 Yeni kesin tekillik sağlayan UNIQUE index'i ekle (semester_no = 0 ile deterministik tekillik)
SET @uk_exists = (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'schedules' AND index_name = 'uk_schedules_owner_period');
SET @add_stmt = IF(@uk_exists = 0, 'ALTER TABLE schedules ADD UNIQUE KEY uk_schedules_owner_period (owner_type, owner_id, semester_no, semester, academic_year, type)', 'SELECT 1');
PREPARE stmt FROM @add_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;