#v0.2.8 -> v0.2.9

CREATE TABLE IF NOT EXISTS lesson_assignments (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id     INT NOT NULL,
    lecturer_id   INT NOT NULL,
    semester      ENUM('Güz', 'Bahar', 'Yaz') NOT NULL,
    academic_year VARCHAR(12) NOT NULL,
    CONSTRAINT fk_la_lesson_id   FOREIGN KEY (lesson_id)   REFERENCES lessons(id) ON DELETE CASCADE,
    CONSTRAINT fk_la_lecturer_id FOREIGN KEY (lecturer_id) REFERENCES users(id)   ON DELETE CASCADE,
    UNIQUE KEY uq_la (lesson_id, semester, academic_year)
) ENGINE = INNODB;

-- Backfill data from lessons to lesson_assignments if exists
INSERT INTO lesson_assignments (lesson_id, lecturer_id, semester, academic_year)
SELECT id, lecturer_id, semester, academic_year
FROM lessons
WHERE lecturer_id IS NOT NULL
  AND semester IS NOT NULL
  AND academic_year IS NOT NULL
ON DUPLICATE KEY UPDATE lecturer_id = VALUES(lecturer_id);

-- Drop old columns and foreign keys from lessons table
ALTER TABLE lessons DROP FOREIGN KEY lessons_ibfk_1;
ALTER TABLE lessons DROP COLUMN lecturer_id;
ALTER TABLE lessons DROP COLUMN semester;
ALTER TABLE lessons DROP COLUMN academic_year;
