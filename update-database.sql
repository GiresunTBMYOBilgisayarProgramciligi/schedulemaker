#v0.3.1 -> v0.3.2
use schedule_maker;

-- Issue #105: Bina ve Derslik UNIQUE kısıtlamalarının güncellenmesi
ALTER TABLE buildings DROP INDEX name;
ALTER TABLE buildings ADD UNIQUE (unit_id, name);

ALTER TABLE classrooms DROP INDEX name;
ALTER TABLE classrooms ADD UNIQUE (building_id, name);
