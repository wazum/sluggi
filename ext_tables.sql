#
# Table structure for table 'pages'
#
CREATE TABLE pages (
  tx_sluggi_locked SMALLINT(5) UNSIGNED DEFAULT '0' NOT NULL
);

#
# Table structure for table 'sys_redirect'
#
CREATE TABLE sys_redirect (
  source_path TEXT NOT NULL
);
