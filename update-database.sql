Drop table schedule;
create table if not exists schedules
(
    id            int AUTO_INCREMENT,
    type          ENUM('lesson','midterm-exam','final-exam','makeup-exam') NOT NULL,
    owner_type    ENUM('user','lesson','program','classroom') NOT NULL,
    owner_id      int,
    semester_no   int,
    semester      ENUM('Güz','Bahar','Yaz') NOT NULL,
    academic_year varchar(12),
    primary key (id),
    unique (owner_type, owner_id, semester_no, semester, academic_year, type)
) ENGINE = INNODB;

create table if not exists schedule_items
(
    id            int AUTO_INCREMENT,
    schedule_id   int,
    day_index     int,
    week_index    int,
    start_time    TIME,
    end_time      TIME,
    status        ENUM('preferred','unavailable','group','single'),
    data          TEXT,
    description   TEXT,
    primary key (id),
    unique (schedule_id,day_index,week_index,start_time,end_time),
    foreign key (schedule_id) references schedules (id) on delete cascade
) ENGINE = INNODB;

ALTER TABLE `lessons` ADD COLUMN `group_no` INT NOT NULL DEFAULT 0 after `code`;
alter table `lessons` drop INDEX `code`;
ALTER TABLE `lessons` ADD UNIQUE (`code`,`program_id`,`group_no`);