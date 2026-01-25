#v0.1.2 -> v0.1.3

-- 1. Foreign Key Kısıtlamalarını CASCADE Olarak Güncelle
-- Not: Önce eski kısıtlamaları kaldırıyoruz, sonra CASCADE ile yeniden ekliyoruz.

ALTER TABLE programs DROP FOREIGN KEY programs_ibfk_1;

ALTER TABLE programs
ADD CONSTRAINT programs_ibfk_1 FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE CASCADE;

ALTER TABLE lessons DROP FOREIGN KEY lessons_ibfk_2;
-- department_id
ALTER TABLE lessons DROP FOREIGN KEY lessons_ibfk_3;
-- program_id
ALTER TABLE lessons
ADD CONSTRAINT lessons_ibfk_2 FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE CASCADE;

ALTER TABLE lessons
ADD CONSTRAINT lessons_ibfk_3 FOREIGN KEY (program_id) REFERENCES programs (id) ON DELETE CASCADE;

-- 2. Polimorfik ve Hiyerarşik Silmeler İçin BEFORE DELETE Trigger Zinciri
-- Not: Her tablo için tek komutluk triggerlar kullanılarak DELIMITER ihtiyacı ortadan kaldırılmıştır.

DROP TRIGGER IF EXISTS trg_dept_del_prog;

CREATE TRIGGER trg_dept_del_prog BEFORE DELETE ON departments FOR EACH ROW 
DELETE FROM programs WHERE department_id = OLD.id;

DROP TRIGGER IF EXISTS trg_dept_del_less;

CREATE TRIGGER trg_dept_del_less BEFORE DELETE ON departments FOR EACH ROW 
DELETE FROM lessons WHERE department_id = OLD.id AND program_id IS NULL;

DROP TRIGGER IF EXISTS trg_prog_del_less;

CREATE TRIGGER trg_prog_del_less BEFORE DELETE ON programs FOR EACH ROW 
DELETE FROM lessons WHERE program_id = OLD.id;

DROP TRIGGER IF EXISTS trg_prog_del_sched;

CREATE TRIGGER trg_prog_del_sched BEFORE DELETE ON programs FOR EACH ROW 
DELETE FROM schedules WHERE owner_type = 'program' AND owner_id = OLD.id;

DROP TRIGGER IF EXISTS trg_lesson_del_sched;

CREATE TRIGGER trg_lesson_del_sched BEFORE DELETE ON lessons FOR EACH ROW 
DELETE FROM schedules WHERE owner_type = 'lesson' AND owner_id = OLD.id;

DROP TRIGGER IF EXISTS trg_user_del_sched;

CREATE TRIGGER trg_user_del_sched BEFORE DELETE ON users FOR EACH ROW 
DELETE FROM schedules WHERE owner_type = 'user' AND owner_id = OLD.id;

DROP TRIGGER IF EXISTS trg_class_del_sched;

CREATE TRIGGER trg_class_del_sched BEFORE DELETE ON classrooms FOR EACH ROW 
DELETE FROM schedules WHERE owner_type = 'classroom' AND owner_id = OLD.id;