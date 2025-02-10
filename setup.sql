CREATE DATABASE if not exists schedule_maker CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
/* .env dosyasında belirttiğiniz kullanıcı adı ve şifreyi ilgili alanlara yazdıktan sonra çalıştırın*/
CREATE USER if not exists 'kullanici_adi'@'localhost' IDENTIFIED BY 'parola';
GRANT ALL PRIVILEGES ON schedule_maker.* TO 'kullanici_adi'@'localhost';
/*Eğer kullanıcı uzak bağlantı yapacaksa: */
#CREATE USER if not exists 'kullanici_adi'@'%' IDENTIFIED BY 'parola';
#GRANT ALL PRIVILEGES ON schedule_maker.* TO 'kullanici_adi'@'%';
FLUSH PRIVILEGES;
use schedule_maker;
create table if not exists schedule
(
    id          int AUTO_INCREMENT,
    type        varchar(20), /* exam, lesson*/
    owner_type  varchar(20),
    owner_id    int,
    time        varchar(20),
    semester_no int,
    day0        text,
    day1        text,
    day2        text,
    day3        text,
    day4        text,
    day5        text,
    semester    varchar(20),
    primary key (id),
    unique (owner_type, owner_id, time, semester_no,semester)
) ENGINE = INNODB;

create table if not exists users
(
    id            int AUTO_INCREMENT,
    password      text,
    mail          varchar(90) NOT NULL,
    name          varchar(50),
    last_name     varchar(50),
    role          varchar(20) default 'user',
    title         varchar(50),
    department_id int,
    program_id    int,
    register_date timestamp   default current_timestamp,
    last_login    timestamp,
    approved      BOOLEAN     DEFAULT false, -- Onay alanı
    primary key (id),
    unique (mail)
) ENGINE = INNODB;

create table if not exists departments
(
    id             int AUTO_INCREMENT,
    name           varchar(100),
    chairperson_id int,
    primary key (id),
    unique (name),
    foreign key (chairperson_id) references users (id) on delete set null on update cascade
) ENGINE = INNODB;

create table if not exists classrooms
(
    id         int AUTO_INCREMENT,
    name       varchar(20),
    plan       text, # oturma planı
    class_size int default 0,
    exam_size  int default 0,
    primary key (id),
    unique (name)
) ENGINE = INNODB;

create table if not exists programs
(
    id            int AUTO_INCREMENT,
    name          varchar(100) not null,
    department_id int,
    primary key (id),
    unique (name),
    foreign key (department_id) references departments (id) on delete set null
) ENGINE = INNODB;

create table if not exists lessons
(
    id            int AUTO_INCREMENT,
    code          varchar(50) NOT NULL,
    name          text        NOT NULL,
    size          int,
    hours         int         NOT NULL DEFAULT 2,
    type          varchar(50)          default 'Zorunlu',
    semester_no   int,
    lecturer_id   int,
    department_id int,
    program_id    int,
    primary key (id),
    unique (code, program_id),
    foreign key (lecturer_id) references users (id) on delete set null,
    foreign key (department_id) references departments (id) on delete set null,
    foreign key (program_id) references programs (id) on delete set null

) ENGINE = INNODB;

create table if not exists settings
(
    id           int AUTO_INCREMENT,
    `key`        varchar(255)                                           NOT NULL,                                                       -- Ayarın benzersiz anahtarı
    `value`      text                                                   NOT NULL,                                                       -- Ayarın değeri
    `type`       enum ('string', 'integer', 'boolean', 'json', 'array') NOT NULL DEFAULT 'string',                                      -- Veri türü
    `group`      VARCHAR(100)                                                    DEFAULT 'general',                                     -- Ayarın hangi gruba ait olduğu
    `created_at` TIMESTAMP                                                       DEFAULT CURRENT_TIMESTAMP,                             -- Oluşturulma tarihi
    `updated_at` TIMESTAMP                                                       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, -- Güncellenme tarihi
    primary key (id),
    unique (`key`, `group`)
);

-- users tablosuna department_id için dış anahtar ekleme
ALTER TABLE users
    ADD FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE users
    ADD FOREIGN KEY (program_id) REFERENCES programs (id) ON DELETE SET NULL ON UPDATE CASCADE;

/* password is 123456 */
insert into users(password, mail, name, last_name, title, role, approved)
values ('$2y$10$OOqHpMPJhvAR2uyoLFCPAuKTgFJDfEB1CtlrpSnxB9SQIYc/bWqYC', 'admin@admin.com', 'Admin', 'Admin', 'Admin',
        'admin', true);