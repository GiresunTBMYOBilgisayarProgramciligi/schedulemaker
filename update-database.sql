#v0.2.9 -> v0.3.0
CREATE TABLE IF NOT EXISTS schedule_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    academic_year VARCHAR(12) NOT NULL,
    semester ENUM('Güz', 'Bahar', 'Yaz') NOT NULL,
    schedule_type ENUM('lesson', 'midterm-exam', 'final-exam', 'makeup-exam') NOT NULL,
    note TEXT NOT NULL,
    status ENUM('pending', 'read', 'completed', 'rejected', 'info_sent') DEFAULT 'pending',
    editor_feedback TEXT NULL,
    read_at TIMESTAMP NULL,
    read_by INT NULL,
    status_updated_at TIMESTAMP NULL,
    status_updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_schedule_notes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_schedule_notes_read_by FOREIGN KEY (read_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_schedule_notes_status_by FOREIGN KEY (status_updated_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_user_schedule_note (user_id, academic_year, semester, schedule_type),
    INDEX idx_schedule_note_context (academic_year, semester, schedule_type),
    INDEX idx_status (status)
) ENGINE = INNODB;

ALTER TABLE schedules
    ADD COLUMN is_published BOOLEAN DEFAULT false AFTER academic_year,
    ADD COLUMN published_at TIMESTAMP NULL AFTER is_published,
    ADD COLUMN updated_at TIMESTAMP NULL AFTER published_at;

CREATE TABLE IF NOT EXISTS schedule_changes_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT NOT NULL,
    lecturer_id INT NULL,
    action_type VARCHAR(50) NOT NULL,
    detail TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_schedule_changes_queue_schedule FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE,
    CONSTRAINT fk_schedule_changes_queue_lecturer FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_schedule_changes_queue_schedule (schedule_id),
    INDEX idx_schedule_changes_queue_lecturer (lecturer_id)
) ENGINE = INNODB;

ALTER TABLE schedule_notes MODIFY COLUMN status ENUM('pending', 'read', 'completed', 'rejected', 'info_sent') DEFAULT 'pending';

