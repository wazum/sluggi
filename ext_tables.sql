CREATE TABLE pages (
    tx_sluggi_sync tinyint(1) unsigned DEFAULT '0' NOT NULL,
    slug_locked tinyint(1) unsigned DEFAULT '0' NOT NULL
);

CREATE TABLE tx_sluggi_record_sync (
    tablename varchar(255) DEFAULT '' NOT NULL,
    record_uid int(11) unsigned DEFAULT '0' NOT NULL,
    is_synced tinyint(1) unsigned DEFAULT '1' NOT NULL,

    UNIQUE KEY tablename_record (tablename, record_uid)
);
