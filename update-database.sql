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

