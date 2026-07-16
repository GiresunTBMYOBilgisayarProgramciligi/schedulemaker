#v0.2.4 -> v0.2.5

-- Sınav birleştirme: exam_parent_lesson_id kolonu ekleme
ALTER TABLE lessons ADD COLUMN exam_parent_lesson_id INT NULL AFTER parent_lesson_id;

-- Eksik dış anahtar: parent_lesson_id
ALTER TABLE lessons ADD FOREIGN KEY (parent_lesson_id) REFERENCES lessons (id) ON DELETE SET NULL;

-- Yeni dış anahtar: exam_parent_lesson_id
ALTER TABLE lessons ADD FOREIGN KEY (exam_parent_lesson_id) REFERENCES lessons (id) ON DELETE SET NULL;

-- Mevcut parent_lesson_id değerlerini exam_parent_lesson_id alanına kopyala
UPDATE lessons SET exam_parent_lesson_id = parent_lesson_id WHERE parent_lesson_id IS NOT NULL;

#v0.2.6 -> v0.2.7

-- Yeni tablo: lesson_combinations
CREATE TABLE IF NOT EXISTS lesson_combinations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_lesson_id INT NOT NULL,
    child_lesson_id INT NOT NULL,
    type ENUM('lesson', 'exam') NOT NULL,
    semester ENUM('Güz', 'Bahar', 'Yaz') NOT NULL,
    academic_year VARCHAR(12) NOT NULL,
    FOREIGN KEY (parent_lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
    FOREIGN KEY (child_lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
    UNIQUE KEY (child_lesson_id, type, semester, academic_year)
) ENGINE = INNODB;

-- Mevcut ders programı birleştirmelerini (parent_lesson_id) yeni tabloya aktar
INSERT IGNORE INTO lesson_combinations (parent_lesson_id, child_lesson_id, type, semester, academic_year)
SELECT parent_lesson_id, id, 'lesson', 'Bahar', '2025 - 2026'
FROM lessons
WHERE parent_lesson_id IS NOT NULL;

-- Mevcut sınav programı birleştirmelerini (exam_parent_lesson_id) yeni tabloya aktar
INSERT IGNORE INTO lesson_combinations (parent_lesson_id, child_lesson_id, type, semester, academic_year)
SELECT exam_parent_lesson_id, id, 'exam', 'Bahar', '2025 - 2026'
FROM lessons
WHERE exam_parent_lesson_id IS NOT NULL;


CREATE TABLE `password_resets` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mail Ayarları
INSERT INTO settings (`group`, `key`, `value`, `type`) VALUES
('mail', 'smtp_host', 'localhost', 'string'),
('mail', 'smtp_port', '587', 'integer'),
('mail', 'smtp_user', '', 'string'),
('mail', 'smtp_pass', '', 'string'),
('mail', 'smtp_secure', 'tls', 'string'),
('mail', 'mail_from', 'noreply@localhost', 'string'),
('mail', 'mail_from_name', 'Schedule Maker', 'string');
