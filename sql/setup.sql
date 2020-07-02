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
    title         varchar(255) charset utf8          not null,
    description   text                               not null,
    type          int                                null,
    uuid          varchar(64) charset utf8           not null,
    user_id       int                                not null,
    date_created  datetime default CURRENT_TIMESTAMP null,
    date_modified datetime default CURRENT_TIMESTAMP null,
    constraint uuid
        unique (uuid),
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
)
    collate = utf8mb4_unicode_ci;

create index dataset_permission_role_id_dataset_id_index
    on dataset_permission (role_id, dataset_id);

create index dataset_permission_role_id_index
    on dataset_permission (role_id);

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


create table if not exists metadata
(
    id          int auto_increment
        primary key,
    name        varchar(50) charset utf8 not null,
    description text charset utf8        null
)
    collate = utf8mb4_unicode_ci;


INSERT INTO metadata (id, name, description) VALUES (1, 'latitude', 'X latitiude coordinate (WGS84)');
INSERT INTO metadata (id, name, description) VALUES (2, 'longitude', 'Y longitude coordiante (WGS84)');
INSERT INTO metadata (id, name, description) VALUES (3, 'attribution', 'Dataset attribution');
INSERT INTO metadata (id, name, description) VALUES (4, 'tags', 'Dataset tags');


-- ******** 21/01/2020
-- Add dataset metadata
create table if not exists dataset__metadata
(
    id         int auto_increment
        primary key,
    dataset_id int                       not null,
    meta_id    int                       not null,
    value      varchar(255) charset utf8 not null,
    constraint dataset__metadata__dataset_id
        foreign key (dataset_id) references dataset (id)
            on delete cascade,
    constraint dataset__metadata__metadata_id
        foreign key (meta_id) references metadata (id)
            on delete cascade
)
    collate = utf8mb4_unicode_ci;

create index dataset_id
    on dataset__metadata (dataset_id);

create index meta_id
    on dataset__metadata (meta_id);

create index value
    on dataset__metadata (value);



-- JC 23/03/2020
-- Dataset Owners
create table if not exists owner
(
    id   int auto_increment
        primary key,
    name varchar(250) not null,
    constraint owner_name_uindex
        unique (name)
);

create table if not exists dataset__owner
(
    id         int auto_increment
        primary key,
    dataset_id int not null,
    owner_id   int not null,
    constraint dataset__owner_dataset_id_fk
        foreign key (dataset_id) references dataset (id)
            on delete cascade,
    constraint dataset__owner_owner_id_fk
        foreign key (owner_id) references owner (id)
            on delete cascade
);

-- JC 23/03/2020
-- Dataset licences
create table if not exists licence
(
    id          int auto_increment
        primary key,
    name        varchar(250)  null,
    description text null,
    uri         varchar(250)  null,
    constraint licence_name_uindex
        unique (name)
);

create table if not exists dataset__licence
(
    id         int auto_increment
        primary key,
    dataset_id int not null,
    licence_id int not null,
    constraint dataset__licence_dataset_id_fk
        foreign key (dataset_id) references dataset (id)
            on delete cascade,
    constraint dataset__licence_licence_id_fk
        foreign key (licence_id) references licence (id)
            on delete cascade
);

-- Some sample licences
INSERT INTO licence (id, name, description, uri) VALUES (1, 'Apache License', 'This is the description of the Apache licence', 'https://www.apache.org/licenses/LICENSE-2.0');
INSERT INTO licence (id, name, description, uri) VALUES (2, 'Creative Commons', 'Description of Creative Commons licence', 'https://creativecommons.org/licenses/by/4.0/');
INSERT INTO licence (id, name, description, uri) VALUES (3, 'Open Government Licence', null, 'http://www.nationalarchives.gov.uk/doc/open-government-licence/version/3/');
INSERT INTO licence (id, name, description, uri) VALUES (4, 'Koubachi Platform Terms of Service', null, 'https://datahub.mksmart.org/policy/koubachi-platform-terms-of-service/');
INSERT INTO licence (id, name, description, uri) VALUES (5, 'Flickr APIs Terms of Use', null, 'https://www.flickr.com/help/terms/api');






