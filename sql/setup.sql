-- ******** 21/01/2020
-- Add 'type' to dataset
CREATE TABLE `dataset_type` (
                                `id` int(11) NOT NULL AUTO_INCREMENT,
                                `name` varchar(50) NOT NULL,
                                `description` varchar(255) DEFAULT NULL,
                                PRIMARY KEY (`id`)
);

create table if not exists dataset
(
    id            int auto_increment
        primary key,
    title         varchar(255)                       not null,
    description   varchar(255)                       not null,
    type          int                                null,
    uuid          varchar(28)                        not null,
    user_id       int                                not null,
    date_created  datetime default CURRENT_TIMESTAMP null,
    date_modified datetime default CURRENT_TIMESTAMP null,
    constraint dataset_uuid_unique
       unique uuid (uuid),
    constraint dataset_dataset_type_id_fk
        foreign key (type) references dataset_type (id),
    constraint datasets___user_id
        foreign key (user_id) references user (id)
            on delete cascade
);

create index datasets__user_id
    on dataset (user_id);

-- JC 16/12/2019
-- Dataset permissions table
create table if not exists dataset_permission
(
    role_id    int           not null,
    dataset_id int           not null,
    v          int default 0 not null,
    r          int default 0 not null,
    w          int default 0 not null,
    d          int default 0 not null,
    g          int default 0 not null,
    primary key (role_id, dataset_id),
    constraint dataset_permission___dataset_id
        foreign key (dataset_id) references dataset (id)
            on delete cascade,
    constraint dataset_permission___role_id
        foreign key (role_id) references role (id)
            on delete cascade
);

-- also use the following to populate for existing datasets:
-- v & r permission for logged in users
INSERT INTO dataset_permission (role_id, dataset_id, v, r)
SELECT -1, id, 1, 1
FROM dataset;

-- v permission for anonymous users
INSERT INTO dataset_permission (role_id, dataset_id, v)
SELECT -2, id, 1
FROM dataset;

-- all permissions for dataset owners
INSERT INTO dataset_permission (role_id, dataset_id, v, r, w, d, g)
SELECT 0, id, 1, 1, 1, 1, 1
FROM dataset;



INSERT INTO dataset_type (id, name, description) VALUES (1, 'stream', 'Datasets that support live streaming of JSON data from sensors or other Internet conencted devices');
INSERT INTO dataset_type (id, name, description) VALUES (2, 'file', 'Datasets consisting of static files');


-- ******** 21/01/2020
-- Add dataset metadata
CREATE TABLE `metadata` (
                            `id` int(11) NOT NULL AUTO_INCREMENT,
                            `name` varchar(50) NOT NULL,
                            `description` varchar(255) DEFAULT NULL,
                            PRIMARY KEY (`id`)
);

INSERT INTO datahub_beta.metadata (id, name, description) VALUES (1, 'latitude', 'X latitiude coordinate (WGS84)');
INSERT INTO datahub_beta.metadata (id, name, description) VALUES (2, 'longitude', 'Y longitude coordiante (WGS84)');

create table if not exists dataset__metadata
(
    id         int auto_increment
        primary key,
    dataset_id int          not null,
    meta_id    int          not null,
    value      varchar(255) not null,
    constraint dataset__metadata__dataset_id
        foreign key (dataset_id) references dataset (id)
            on delete cascade,
    constraint dataset__metadata__metadata_id
        foreign key (meta_id) references metadata (id)
            on delete cascade
);

create index dataset_id
    on dataset__metadata (dataset_id);

create index meta_id
    on dataset__metadata (meta_id);

create index value
    on dataset__metadata (value);






