#v0.3.1 -> v0.3.2
use schedule_maker;

-- Issue #105: Bina ve Derslik UNIQUE kısıtlamalarının güncellenmesi
ALTER TABLE buildings DROP INDEX name;
ALTER TABLE buildings ADD UNIQUE (unit_id, name);

ALTER TABLE classrooms DROP INDEX name;
ALTER TABLE classrooms ADD UNIQUE (building_id, name);

-- Issue #113: Birim yöneticisi / müdürü (manager_id) alanı
ALTER TABLE units ADD COLUMN manager_id INT NULL DEFAULT NULL AFTER type;
ALTER TABLE units ADD CONSTRAINT fk_units_manager FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL;

-- Issue #106: Mail kuyruğu tablosu
CREATE TABLE IF NOT EXISTS mail_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    to_email VARCHAR(150) NOT NULL,
    to_name VARCHAR(150) NULL,
    subject VARCHAR(255) NOT NULL,
    body LONGTEXT NOT NULL,
    alt_body TEXT NULL,
    attachments JSON NULL,
    status ENUM('pending', 'processing', 'sent', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    INDEX idx_mail_queue_status (status),
    INDEX idx_mail_queue_created (created_at)
) ENGINE = INNODB;

-- Issue #106: Mail kuyruğu ayarları
INSERT IGNORE INTO settings (`key`, `value`, `type`, `group`) VALUES 
('mail_batch_size', '10', 'integer', 'mail'),
('mail_max_attempts', '3', 'integer', 'mail');



