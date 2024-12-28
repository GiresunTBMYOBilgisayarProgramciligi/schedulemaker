create table if not exists users
(
    id            int AUTO_INCREMENT,
    password      text,
    mail          varchar(90) NOT NULL ,
    name          varchar(50),
    last_name     varchar(50),
    role          varchar(20) default "user",
    title         varchar(15),
    department_id int,
    $schedule     text,
    register_date timestamp default current_timestamp,
    last_login    timestamp,
    primary key (id),
    unique (mail)
    ) ENGINE = INNODB;

create table if not exists departments
(
    id             int AUTO_INCREMENT,
    name           text,
    chairperson_id int,
    schedule       text,
    primary key (id),
    foreign key (chairperson_id) references users (id) on delete set null on update cascade
    ) ENGINE = INNODB;

create table if not exists classrooms
(
    id         int AUTO_INCREMENT,
    name       varchar(20),
    class_size int default 0,
    exam_size  int default 0,
    schedule   text,
    primary key (id),
    unique (name)
    ) ENGINE = INNODB;

create table if not exists lessons
(
    id            int AUTO_INCREMENT,
    code          varchar(50),
    name          text,
    size          int,
    lecturer_id   int,
    department_id int,
    primary key (id),
    unique (code),
    foreign key (lecturer_id) references users (id) on delete set null,
    foreign key (department_id) references departments (id) on delete set null

    ) ENGINE = INNODB;
/* password is 123456 */
insert into users(password, mail, name, last_name) values
    ("$2y$10$OOqHpMPJhvAR2uyoLFCPAuKTgFJDfEB1CtlrpSnxB9SQIYc/bWqYC","admin@admin.com","Admin","Admin");
