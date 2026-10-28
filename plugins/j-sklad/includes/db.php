<?php
/**
 * Tabuľky J-Skladu:
 *  - {prefix}jsk_docs       doklady (príjemka, výdajka, otváracia príjemka)
 *  - {prefix}jsk_doc_items  položky dokladov
 *  - {prefix}jsk_moves      kniha pohybov – každá zmena zásoby produktu (aj predaj a ručné zmeny)
 * Zásoba samotná je v produktoch WooCommerce (_stock); J-Sklad ju mení len funkciami WooCommerce.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function jsk_t( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'jsk_' . $name;
}

function jsk_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$c = $wpdb->get_charset_collate();
	dbDelta( 'CREATE TABLE ' . jsk_t( 'docs' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  type varchar(20) NOT NULL DEFAULT 'receipt',
  number varchar(40) NOT NULL DEFAULT '',
  year smallint(5) unsigned NOT NULL DEFAULT 0,
  seq int(10) unsigned NOT NULL DEFAULT 0,
  doc_date date NOT NULL,
  partner varchar(200) NOT NULL DEFAULT '',
  partner_doc varchar(100) NOT NULL DEFAULT '',
  reason varchar(100) NOT NULL DEFAULT '',
  note text NULL,
  status varchar(20) NOT NULL DEFAULT 'draft',
  total decimal(14,4) NOT NULL DEFAULT 0,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  posted_at datetime NULL DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY type_year (type,year,seq),
  KEY status (status),
  KEY doc_date (doc_date)
) $c;" );
	dbDelta( 'CREATE TABLE ' . jsk_t( 'doc_items' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  doc_id bigint(20) unsigned NOT NULL DEFAULT 0,
  product_id bigint(20) unsigned NOT NULL DEFAULT 0,
  sku varchar(100) NOT NULL DEFAULT '',
  name varchar(255) NOT NULL DEFAULT '',
  qty decimal(14,4) NOT NULL DEFAULT 0,
  price decimal(14,4) NULL DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY doc_id (doc_id),
  KEY product_id (product_id)
) $c;" );
	dbDelta( 'CREATE TABLE ' . jsk_t( 'moves' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  product_id bigint(20) unsigned NOT NULL DEFAULT 0,
  qty decimal(14,4) NOT NULL DEFAULT 0,
  stock_after decimal(14,4) NULL DEFAULT NULL,
  source varchar(20) NOT NULL DEFAULT 'other',
  doc_id bigint(20) unsigned NOT NULL DEFAULT 0,
  order_id bigint(20) unsigned NOT NULL DEFAULT 0,
  cost decimal(14,4) NULL DEFAULT NULL,
  note varchar(255) NOT NULL DEFAULT '',
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY product_id (product_id,id),
  KEY source (source),
  KEY doc_id (doc_id),
  KEY order_id (order_id),
  KEY created_at (created_at)
) $c;" );
	update_option( 'jsk_db_version', JSK_DB_VERSION, false );
}

function jsk_doc( $id ) {
	global $wpdb;
	$d = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . jsk_t( 'docs' ) . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore
	if ( $d ) {
		$d['items'] = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . jsk_t( 'doc_items' ) . ' WHERE doc_id = %d ORDER BY id', $id ), ARRAY_A ); // phpcs:ignore
	}
	return $d;
}

/** Posledný pohyb produktu (alebo null). */
function jsk_last_move( $product_id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . jsk_t( 'moves' ) . ' WHERE product_id = %d ORDER BY id DESC LIMIT 1', $product_id ), ARRAY_A ); // phpcs:ignore
}
