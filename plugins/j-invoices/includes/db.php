<?php
/** Tabuľka faktúr {prefix}j_invoices. Údaje dodávateľa, odberateľa a položiek sa ukladajú ako snímka v čase vystavenia. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Tabuľka: faktúry (predvolené), partners, payments. */
function jinv_table( $name = '' ) {
	global $wpdb;
	return $wpdb->prefix . 'j_invoices' . ( $name ? '_' . $name : '' );
}

function jinv_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$t = jinv_table();
	$c = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE $t (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  kind varchar(20) NOT NULL DEFAULT 'issued',
  number varchar(40) NOT NULL DEFAULT '',
  year smallint(5) unsigned NOT NULL DEFAULT 0,
  seq int(10) unsigned NOT NULL DEFAULT 0,
  order_id bigint(20) unsigned NOT NULL DEFAULT 0,
  status varchar(20) NOT NULL DEFAULT 'issued',
  issue_date date NOT NULL,
  supply_date date NOT NULL,
  due_date date NOT NULL,
  vs varchar(20) NOT NULL DEFAULT '',
  currency char(3) NOT NULL DEFAULT 'EUR',
  total decimal(12,2) NOT NULL DEFAULT 0,
  customer_name varchar(200) NOT NULL DEFAULT '',
  customer_email varchar(200) NOT NULL DEFAULT '',
  partner_id bigint(20) unsigned NOT NULL DEFAULT 0,
  data longtext NOT NULL,
  source varchar(20) NOT NULL DEFAULT '',
  file varchar(255) NOT NULL DEFAULT '',
  sent_at datetime NULL DEFAULT NULL,
  paid_at datetime NULL DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY kind_year_seq (kind,year,seq),
  KEY order_id (order_id),
  KEY source (source),
  KEY issue_date (issue_date),
  KEY partner_id (partner_id)
) $c;" );
	// Adresár partnerov (dodávatelia, odberatelia).
	dbDelta( 'CREATE TABLE ' . jinv_table( 'partners' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  type varchar(20) NOT NULL DEFAULT 'supplier',
  name varchar(200) NOT NULL DEFAULT '',
  ico varchar(20) NOT NULL DEFAULT '',
  dic varchar(20) NOT NULL DEFAULT '',
  icdph varchar(20) NOT NULL DEFAULT '',
  street varchar(200) NOT NULL DEFAULT '',
  city varchar(100) NOT NULL DEFAULT '',
  zip varchar(20) NOT NULL DEFAULT '',
  country char(2) NOT NULL DEFAULT 'SK',
  email varchar(200) NOT NULL DEFAULT '',
  phone varchar(50) NOT NULL DEFAULT '',
  web varchar(200) NOT NULL DEFAULT '',
  iban varchar(40) NOT NULL DEFAULT '',
  due_days smallint(5) unsigned NULL DEFAULT NULL,
  category varchar(100) NOT NULL DEFAULT '',
  note text NULL,
  active tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY ico (ico),
  KEY name (name(50))
) $c;" );
	// Pohyby na bankových účtoch (Fio API, import výpisu).
	dbDelta( 'CREATE TABLE ' . jinv_table( 'banktx' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  account_id varchar(20) NOT NULL DEFAULT '',
  ext_id varchar(64) NOT NULL DEFAULT '',
  tx_date date NOT NULL,
  amount decimal(12,2) NOT NULL DEFAULT 0,
  currency char(3) NOT NULL DEFAULT 'EUR',
  vs varchar(20) NOT NULL DEFAULT '',
  ks varchar(10) NOT NULL DEFAULT '',
  ss varchar(20) NOT NULL DEFAULT '',
  counter_account varchar(50) NOT NULL DEFAULT '',
  counter_name varchar(200) NOT NULL DEFAULT '',
  message varchar(255) NOT NULL DEFAULT '',
  tx_type varchar(60) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT 'new',
  doc_id bigint(20) unsigned NOT NULL DEFAULT 0,
  order_id bigint(20) unsigned NOT NULL DEFAULT 0,
  payment_id bigint(20) unsigned NOT NULL DEFAULT 0,
  matched_by varchar(20) NOT NULL DEFAULT '',
  note varchar(255) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY account_ext (account_id,ext_id),
  KEY status (status),
  KEY vs (vs),
  KEY tx_date (tx_date)
) $c;" );
	// Úhrady dokladov (čiastočné aj celé; zdroj: ručne, banka, pokladňa).
	dbDelta( 'CREATE TABLE ' . jinv_table( 'payments' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  doc_id bigint(20) unsigned NOT NULL DEFAULT 0,
  pay_date date NOT NULL,
  amount decimal(12,2) NOT NULL DEFAULT 0,
  method varchar(20) NOT NULL DEFAULT 'bank',
  source varchar(20) NOT NULL DEFAULT 'manual',
  ref varchar(100) NOT NULL DEFAULT '',
  note varchar(255) NOT NULL DEFAULT '',
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY doc_id (doc_id),
  KEY source_ref (source,ref)
) $c;" );
	update_option( 'jinv_db_version', JINV_DB_VERSION, false );
}

function jinv_get( $id ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . jinv_table() . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore
	if ( $row ) {
		$row['data'] = json_decode( (string) $row['data'], true );
	}
	return $row;
}

/** Posledný platný (nestornovaný) doklad daného druhu k objednávke. */
function jinv_by_order( $order_id, $kind = 'issued' ) {
	global $wpdb;
	$id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . jinv_table() . " WHERE order_id = %d AND kind = %s AND status <> 'cancelled' ORDER BY id DESC LIMIT 1", $order_id, $kind ) ); // phpcs:ignore
	return $id ? jinv_get( $id ) : null;
}

/** Všetky doklady k objednávke (aj stornované), najstaršie prvé. */
function jinv_docs_for_order( $order_id ) {
	global $wpdb;
	return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, kind, number, status, issue_date, due_date, total, currency, source, file, data FROM ' . jinv_table() . ' WHERE order_id = %d ORDER BY id ASC', $order_id ), ARRAY_A ); // phpcs:ignore
}

function jinv_update( $id, array $fields ) {
	global $wpdb;
	return $wpdb->update( jinv_table(), $fields, array( 'id' => (int) $id ) );
}

/** Zoznam faktúr s jednoduchým filtrom. */
function jinv_list( $args = array() ) {
	global $wpdb;
	$a     = wp_parse_args( $args, array( 'kind' => 'issued', 'year' => 0, 'status' => '', 's' => '', 'limit' => 50, 'offset' => 0 ) );
	$where = array( $wpdb->prepare( 'kind = %s', $a['kind'] ) );
	if ( $a['year'] ) {
		$where[] = $wpdb->prepare( 'year = %d', $a['year'] );
	}
	if ( 'overdue' === $a['status'] ) {
		// Po splatnosti: neuhradené, splatnosť uplynula, bez dobierky (tá sa platí pri prevzatí).
		$where[] = $wpdb->prepare( "status = 'issued' AND kind <> 'creditnote' AND due_date < %s AND data NOT LIKE %s AND data NOT LIKE %s", current_time( 'Y-m-d' ), '%"pay_code":"cod"%', '%"pay_code":"jetpack_custom_gateway"%' );
	} elseif ( $a['status'] ) {
		$where[] = $wpdb->prepare( 'status = %s', $a['status'] );
	}
	if ( '' !== $a['s'] ) {
		$like    = '%' . $wpdb->esc_like( $a['s'] ) . '%';
		$where[] = $wpdb->prepare( '(number LIKE %s OR customer_name LIKE %s OR customer_email LIKE %s)', $like, $like, $like );
	}
	$w     = implode( ' AND ', $where );
	$t     = jinv_table();
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE $w" ); // phpcs:ignore
	$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE $w ORDER BY issue_date DESC, id DESC LIMIT %d OFFSET %d", $a['limit'], $a['offset'] ), ARRAY_A ); // phpcs:ignore
	return array( 'total' => $total, 'rows' => $rows );
}
