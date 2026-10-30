CREATE TABLE tx_sluggitest_article (
    title varchar(255) DEFAULT '' NOT NULL,
    subtitle varchar(255) DEFAULT '' NOT NULL,
    slug varchar(2048) DEFAULT '' NOT NULL,
    deleted smallint(5) unsigned DEFAULT 0 NOT NULL
);

CREATE TABLE tx_sluggitest_translated (
    title varchar(255) DEFAULT '' NOT NULL,
    slug varchar(2048) DEFAULT '' NOT NULL,
    sys_language_uid int(11) DEFAULT 0 NOT NULL,
    l10n_parent int(11) unsigned DEFAULT 0 NOT NULL,
    deleted smallint(5) unsigned DEFAULT 0 NOT NULL
);
