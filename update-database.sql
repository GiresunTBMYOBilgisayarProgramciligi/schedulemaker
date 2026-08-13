#v0.3.0 -> v0.3.1
ALTER TABLE schedule_notes MODIFY COLUMN status ENUM('pending', 'read', 'completed', 'rejected', 'info_sent') DEFAULT 'pending';

-- programs tablosundaki tekil 'name' kısıtlamasını kaldırıp (department_id, name) bileşik indexi ekleme
ALTER TABLE programs DROP INDEX name;
ALTER TABLE programs ADD UNIQUE KEY unique_program_department_name (department_id, name);

-- departments tablosundaki tekil 'name' kısıtlamasını kaldırıp (unit_id, name) bileşik indexi ekleme
ALTER TABLE departments DROP INDEX name;
ALTER TABLE departments ADD UNIQUE KEY unique_department_unit_name (unit_id, name);
