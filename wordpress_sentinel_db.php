<?php
global $wordpress_sentinel_db_version;
$wordpress_sentinel_db_version = "1.04"; 

function wordpress_sentinel_install() {
  global $wpdb;
  global $wordpress_sentinel_db_version;
  
  require_once(ABSPATH.'wp-admin/includes/upgrade.php');

  $section_table = "CREATE TABLE " . $wpdb->prefix . "wordpresssentinel_section (
   section_id int NOT NULL AUTO_INCREMENT,
   type int NOT NULL,
   name varchar(50) NOT NULL,
   slug varchar(50) NOT NULL,
   location varchar(200) NOT NULL,
   mode int NOT NULL,
   status int NOT NULL,
   files int NOT NULL,
   snapshot_made timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
   last_checked timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
   watch tinyint NOT NULL DEFAULT 1,
   UNIQUE KEY (section_id)
  );";
  dbDelta($section_table);
  
  $file_table = "CREATE TABLE " . $wpdb->prefix . "wordpresssentinel_file (
   file_id int NOT NULL AUTO_INCREMENT,
   section_id int NOT NULL,
   file_ref varchar(40) NOT NULL,
   location varchar(200) NOT NULL,
   size int NOT NULL,
   update_date timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
   checksum varchar(40) NOT NULL,
   status int NOT NULL,
   changed_size int NOT NULL,
   changed_date timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
   changed_checksum varchar(40) NOT NULL,
   watch tinyint NOT NULL DEFAULT 1,
   UNIQUE KEY (file_id)
  );";
  dbDelta($file_table);
      
  delete_option("wordpress_sentinel_db_version");
  add_option("wordpress_sentinel_db_version", $wordpress_sentinel_db_version);
}