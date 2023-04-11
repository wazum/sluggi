CREATE TABLE pages (
    tx_sluggi_sync SMALLINT(1) UNSIGNED DEFAULT '1' NOT NULL,
    # To be compatible with "ig_slug" extension
    slug_locked smallint(5) unsigned DEFAULT '0' NOT NULL
);
