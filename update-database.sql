#v0.2.7 -> v0.2.8
ALTER TABLE lessons DROP FOREIGN KEY lessons_ibfk_6; # kısıtlama adı düzenlenmeli 
ALTER TABLE lessons DROP FOREIGN KEY lessons_ibfk_7; # kısıtlama adı düzenlenmeli
ALTER TABLE lessons DROP COLUMN exam_parent_lesson_id;
ALTER TABLE lessons DROP COLUMN parent_lesson_id;

# Üniversite genişlemesi: Birim (Fakülte/MYO/Enstitü) ve Bina yönetimi

# 1. Üst birim tablosu (Fakülte, Enstitü, MYO vb.)
CREATE TABLE IF NOT EXISTS units
(
    id     INT AUTO_INCREMENT,
    name   VARCHAR(150) NOT NULL,
    type   VARCHAR(30)  NOT NULL,
    active TINYINT(1) DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE (name)
) ENGINE = INNODB;

# 2. Bina tablosu (kampüsteki binalar)
CREATE TABLE IF NOT EXISTS buildings
(
    id      INT AUTO_INCREMENT,
    name    VARCHAR(150) NOT NULL,
    unit_id INT          NOT NULL,
    PRIMARY KEY (id),
    UNIQUE (name),
    CONSTRAINT fk_buildings_unit_id foreign key (unit_id) references units (id) on delete restrict on update cascade
) ENGINE = INNODB;

# Eğer tablo zaten varsa unit_id kolonu ekleme ve güncelleme işlemleri (v0.2.8 migration)
ALTER TABLE buildings ADD COLUMN unit_id INT NULL AFTER name;
UPDATE buildings SET unit_id = 1 WHERE unit_id IS NULL;
ALTER TABLE buildings MODIFY COLUMN unit_id INT NOT NULL;
ALTER TABLE buildings ADD CONSTRAINT fk_buildings_unit_id FOREIGN KEY (unit_id) REFERENCES units (id) ON DELETE RESTRICT ON UPDATE CASCADE;

# 3. Bölümlere birim bağlantısı
ALTER TABLE departments
    ADD COLUMN unit_id INT NULL AFTER active,
    ADD CONSTRAINT fk_departments_unit_id
        FOREIGN KEY (unit_id) REFERENCES units (id)
            ON DELETE SET NULL ON UPDATE CASCADE;

# 4. Dersliklere bina bağlantısı
ALTER TABLE classrooms
    ADD COLUMN building_id INT NULL AFTER exam_size,
    ADD CONSTRAINT fk_classrooms_building_id
        FOREIGN KEY (building_id) REFERENCES buildings (id)
            ON DELETE SET NULL ON UPDATE CASCADE;

# 5. Derslere bina bağlantısı (derslik filtrelemesi için)
ALTER TABLE lessons
    ADD COLUMN building_id INT NULL AFTER academic_year,
    ADD CONSTRAINT fk_lessons_building_id
        FOREIGN KEY (building_id) REFERENCES buildings (id)
            ON DELETE SET NULL ON UPDATE CASCADE;

# 6. Kullanıcılara birim bağlantısı
ALTER TABLE users
    ADD COLUMN unit_id INT NULL AFTER program_id,
    ADD CONSTRAINT fk_users_unit_id
        FOREIGN KEY (unit_id) REFERENCES units (id)
            ON DELETE SET NULL ON UPDATE CASCADE;

#Var olan derslere bina tanımlaması (geçici)
update lessons SET building_id=1 WHERE building_id IS NULL and department_id != 2;
update lessons SET building_id=2 WHERE building_id IS NULL and department_id = 2;