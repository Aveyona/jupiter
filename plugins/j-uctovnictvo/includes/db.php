<?php
/**
 * Tabuľky J-Účtovníctva ({prefix}juc_…):
 *  - accounts  účtový rozvrh (syntetické a analytické účty)
 *  - years     účtové obdobia
 *  - entries   účtovné doklady (hlavička zápisu; zdroj = doklad J-Fakturácie, banky, J-Skladu alebo ručný)
 *  - lines     účtovné zápisy: suma na stranu MD jedného účtu a Dal druhého účtu (predkontácia)
 * Zaúčtovaný doklad sa nemení – oprava = storno (zápis s opačnými sumami) a nový doklad.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function juc_t( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'juc_' . $name;
}

function juc_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$c = $wpdb->get_charset_collate();
	dbDelta( 'CREATE TABLE ' . juc_t( 'accounts' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  num varchar(12) NOT NULL DEFAULT '',
  syn char(3) NOT NULL DEFAULT '',
  name varchar(200) NOT NULL DEFAULT '',
  type varchar(3) NOT NULL DEFAULT 'A',
  tax tinyint(1) NOT NULL DEFAULT 1,
  active tinyint(1) NOT NULL DEFAULT 1,
  note varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  UNIQUE KEY num (num),
  KEY syn (syn)
) $c;" );
	dbDelta( 'CREATE TABLE ' . juc_t( 'years' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  start_date date NOT NULL,
  end_date date NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'open',
  closed_at datetime NULL DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY start_date (start_date)
) $c;" );
	dbDelta( 'CREATE TABLE ' . juc_t( 'entries' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  year_id bigint(20) unsigned NOT NULL DEFAULT 0,
  series varchar(10) NOT NULL DEFAULT 'ID',
  number varchar(40) NOT NULL DEFAULT '',
  seq int(10) unsigned NOT NULL DEFAULT 0,
  entry_date date NOT NULL,
  doc_date date NOT NULL,
  descr varchar(255) NOT NULL DEFAULT '',
  partner_id bigint(20) unsigned NOT NULL DEFAULT 0,
  partner_name varchar(200) NOT NULL DEFAULT '',
  vs varchar(20) NOT NULL DEFAULT '',
  total decimal(14,2) NOT NULL DEFAULT 0,
  source varchar(20) NOT NULL DEFAULT '',
  source_type varchar(20) NOT NULL DEFAULT '',
  source_id bigint(20) unsigned NOT NULL DEFAULT 0,
  source_hash char(32) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT 'posted',
  storno_of bigint(20) unsigned NOT NULL DEFAULT 0,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY year_series (year_id,series,seq),
  KEY entry_date (entry_date),
  KEY source (source,source_type,source_id),
  KEY status (status)
) $c;" );
	dbDelta( 'CREATE TABLE ' . juc_t( 'lines' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  entry_id bigint(20) unsigned NOT NULL DEFAULT 0,
  year_id bigint(20) unsigned NOT NULL DEFAULT 0,
  entry_date date NOT NULL,
  md varchar(12) NOT NULL DEFAULT '',
  d varchar(12) NOT NULL DEFAULT '',
  amount decimal(14,2) NOT NULL DEFAULT 0,
  text varchar(255) NOT NULL DEFAULT '',
  partner_id bigint(20) unsigned NOT NULL DEFAULT 0,
  vs varchar(20) NOT NULL DEFAULT '',
  opening tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY entry_id (entry_id),
  KEY md (md,year_id),
  KEY d (d,year_id),
  KEY year_date (year_id,entry_date),
  KEY pair (partner_id,vs)
) $c;" );
	update_option( 'juc_db_version', JUC_DB_VERSION, false );
}
