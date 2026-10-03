# mysql -u kullanici_adi vt_adi < update-database.sql
#v0.3.3 -> v0.3.4

-- ============================================================================
-- Performans ve Sorgu Optimizasyonu İndeksleri (v0.3.4)
-- ============================================================================

-- 1. lesson_assignments: Hoca ve dönem bazlı sorgular için kompozit index
SET @la_idx_exists = (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'lesson_assignments' AND index_name = 'idx_la_lecturer_period');
SET @add_la_idx = IF(@la_idx_exists = 0, 'ALTER TABLE lesson_assignments ADD INDEX idx_la_lecturer_period (lecturer_id, academic_year, semester)', 'SELECT 1');
PREPARE stmt FROM @add_la_idx;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. lessons: Program ve yarıyıl bazlı filtrelemeler için kompozit index
SET @less_idx_exists = (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'lessons' AND index_name = 'idx_lessons_program_semester');
SET @add_less_idx = IF(@less_idx_exists = 0, 'ALTER TABLE lessons ADD INDEX idx_lessons_program_semester (program_id, semester_no)', 'SELECT 1');
PREPARE stmt FROM @add_less_idx;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- Log ve Hata Yönetimi Ayarları (v0.3.4)
-- ============================================================================
INSERT INTO settings (`group`, `key`, `value`, `type`) VALUES
('log', 'log_rotation_period', 'daily', 'string'),
('log', 'log_retention_days', '14', 'integer'),
('log', 'log_max_files', '14', 'integer'),
('log', 'log_level', 'DEBUG', 'string')
ON DUPLICATE KEY UPDATE `type` = VALUES(`type`);