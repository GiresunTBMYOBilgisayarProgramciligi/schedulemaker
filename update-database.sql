#v0.2.4 -> v0.2.5

-- Sınav birleştirme: exam_parent_lesson_id kolonu ekleme
ALTER TABLE lessons ADD COLUMN exam_parent_lesson_id INT NULL AFTER parent_lesson_id;

-- Eksik dış anahtar: parent_lesson_id
ALTER TABLE lessons ADD FOREIGN KEY (parent_lesson_id) REFERENCES lessons (id) ON DELETE SET NULL;

-- Yeni dış anahtar: exam_parent_lesson_id
ALTER TABLE lessons ADD FOREIGN KEY (exam_parent_lesson_id) REFERENCES lessons (id) ON DELETE SET NULL;

-- Mevcut parent_lesson_id değerlerini exam_parent_lesson_id alanına kopyala
UPDATE lessons SET exam_parent_lesson_id = parent_lesson_id WHERE parent_lesson_id IS NOT NULL;