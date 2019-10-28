#
# Table structure for table 'pages'
#
CREATE TABLE pages (
  tx_sluggi_lock SMALLINT(5) UNSIGNED DEFAULT '0' NOT NULL,
  tx_sluggi_sync SMALLINT(5) UNSIGNED DEFAULT '1' NOT NULL
);

#
# Table structure for table 'sys_redirect'
#
# source_path varchar(255) is not long enough for some URL
#
CREATE TABLE sys_redirect (
  source_host varchar(255) DEFAULT '' NOT NULL,
  source_path varchar(2048) DEFAULT '' NOT NULL,
  KEY index_source (source_host(80),source_path(80))
);
