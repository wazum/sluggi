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
CREATE TABLE sys_redirect (
  source_path varchar(255) DEFAULT '' NOT NULL
);
