#v0.2.7 -> v0.2.8
ALTER TABLE lessons DROP FOREIGN KEY lessons_ibfk_4;
ALTER TABLE lessons DROP FOREIGN KEY lessons_ibfk_5;
ALTER TABLE lessons DROP COLUMN exam_parent_lesson_id;
ALTER TABLE lessons DROP COLUMN parent_lesson_id;
