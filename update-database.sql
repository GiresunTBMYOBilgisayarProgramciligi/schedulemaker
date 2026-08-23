#v0.3.0 -> v0.3.1
use schedule_maker;

ALTER TABLE schedule_notes
MODIFY COLUMN status ENUM(
    'pending',
    'read',
    'completed',
    'rejected',
    'info_sent'
) DEFAULT 'pending';


-- Issue 86: Hocaların Birim Bağı (Multiple Affiliations)
create table if not exists user_affiliations
(
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NOT NULL,
    unit_id       INT,
    department_id INT,
    program_id    INT,
    CONSTRAINT fk_ua_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ua_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE,
    CONSTRAINT fk_ua_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    CONSTRAINT fk_ua_program FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
    UNIQUE KEY uq_user_affiliation (user_id, unit_id, department_id, program_id)
) ENGINE = INNODB;

-- Mevcut ders atamalarından hocaların birim bağlarını user_affiliations tablosuna aktarma (Backfill)
INSERT IGNORE INTO user_affiliations (user_id, unit_id, department_id, program_id)
SELECT DISTINCT 
    la.lecturer_id, 
    d.unit_id,
    d.id as department_id,
    l.program_id
FROM lesson_assignments la
JOIN lessons l ON la.lesson_id = l.id
LEFT JOIN programs p ON l.program_id = p.id
JOIN departments d ON d.id = COALESCE(l.department_id, p.department_id)
JOIN users u ON u.id = la.lecturer_id
WHERE la.lecturer_id IS NOT NULL AND la.lecturer_id > 0
  AND NOT (
      u.unit_id <=> d.unit_id AND
      u.department_id <=> d.id AND
      u.program_id <=> l.program_id
  );
