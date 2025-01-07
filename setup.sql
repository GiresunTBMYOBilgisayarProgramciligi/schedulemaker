create table if not exists schedule
(
    id   int AUTO_INCREMENT,
    data text,
    primary key (id)
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
    schedule_id    int,
    register_date timestamp   default current_timestamp,
    last_login    timestamp,
    approved      BOOLEAN     DEFAULT false, -- Onay alanı
    primary key (id),
    unique (mail),
    foreign key (schedule_id) references schedule (id) on delete set null on update cascade
) ENGINE = INNODB;

create table if not exists departments
(
    id             int AUTO_INCREMENT,
    name           varchar(100),
    chairperson_id int,
    schedule_id    int,
    primary key (id),
    unique (name),
    foreign key (chairperson_id) references users (id) on delete set null on update cascade,
    foreign key (schedule_id) references schedule (id) on delete set null on update cascade
) ENGINE = INNODB;

create table if not exists classrooms
(
    id         int AUTO_INCREMENT,
    name       varchar(20),
    class_size int default 0,
    exam_size  int default 0,
    schedule_id    int,
    primary key (id),
    unique (name),
    foreign key (schedule_id) references schedule (id) on delete set null on update cascade
) ENGINE = INNODB;

create table if not exists programs
(
    id            int AUTO_INCREMENT,
    name          varchar(100) not null,
    department_id int,
    schedule_id    int,
    primary key (id),
    unique (name),
    foreign key (department_id) references departments (id) on delete set null,
    foreign key (schedule_id) references schedule (id) on delete set null on update cascade
) ENGINE = INNODB;

create table if not exists lessons
(
    id            int AUTO_INCREMENT,
    code          varchar(50) NOT NULL,
    name          text NOT NULL,
    size          int,
    hours         int NOT NULL DEFAULT 2,
    type          varchar(50) default 'Zorunlu',
    season        varchar(50) default '1. Yarıyıl',
    lecturer_id   int,
    department_id int,
    program_id    int,
    primary key (id),
    unique (code,program_id),
    foreign key (lecturer_id) references users (id) on delete set null,
    foreign key (department_id) references departments (id) on delete set null,
    foreign key (program_id) references programs (id) on delete set null

) ENGINE = INNODB;

-- users tablosuna department_id için dış anahtar ekleme
ALTER TABLE users
    ADD FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE users
    ADD FOREIGN KEY (program_id) REFERENCES programs (id) ON DELETE SET NULL ON UPDATE CASCADE;

/* password is 123456 */
insert into users(password, mail, name, last_name, title, role, approved)
values ('$2y$10$OOqHpMPJhvAR2uyoLFCPAuKTgFJDfEB1CtlrpSnxB9SQIYc/bWqYC', 'admin@admin.com', 'Admin', 'Admin', 'Admin',
        'admin', true);

insert into departments (name, chairperson_id)
values ('Bilgisayar Teknolojileri', 1);
insert into programs (name, department_id)
values ('Bilgisayar Programcılığı', 1)