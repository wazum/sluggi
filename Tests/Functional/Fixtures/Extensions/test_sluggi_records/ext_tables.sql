CREATE TABLE tx_sluggitest_article (
    title varchar(255) DEFAULT '' NOT NULL,
    subtitle varchar(255) DEFAULT '' NOT NULL,
    slug varchar(2048) DEFAULT '' NOT NULL,
    deleted smallint(5) unsigned DEFAULT 0 NOT NULL
);
