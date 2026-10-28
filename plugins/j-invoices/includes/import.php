<?php
/**
 * Importy údajov – sprievodca prevzatím dokladov z doplnku Woo Faktura (Nastavenia → Importy údajov).
 *
 * Woo Faktura si ku každej objednávke ukladá: wc_wi_{typ}_id (číslo), wc_wi_invoice_{typ}_date, wc_wi_invoice_{typ}_due
 * a wc_wi_invoice_{typ} (názov PDF v priečinku woocommerce_wi_invoice_dir, predvolene uploads/invoices).
 * Typy: invoice (faktúra), proforma (zálohová faktúra), creditnote (dobropis).
 *
 * Import nič nemení vo Woo Faktura ani v objednávkach; pôvodné PDF ostávajú platným dokladom a J-Fakturácia ich len sprístupní.
 * Opakované spustenie je bezpečné – už prevzatý doklad sa nepridá druhýkrát.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const JINV_WF_BATCH = 100;

/** Typ Woo Faktury → druh dokladu v J-Fakturácii. */
function jinv_wf_types() {
	return array(
		'invoice'    => array( 'issued', 'Faktúry' ),
		'proforma'   => array( 'proforma', 'Zálohové faktúry' ),
		'creditnote' => array( 'creditnote', 'Dobropisy' ),
	);
}

/** Je zapnuté ukladanie objednávok v tabuľkách HPOS? */
function jinv_hpos() {
	return class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
}

/** Tabuľka a stĺpec s meta údajmi objednávok. */
function jinv_wf_meta_table() {
	global $wpdb;
	return jinv_hpos() ? array( $wpdb->prefix . 'wc_orders_meta', 'order_id' ) : array( $wpdb->postmeta, 'post_id' );
}

/** ID objednávok, ktoré majú aspoň jeden doklad Woo Faktury (zoradené). */
function jinv_wf_order_ids( $offset = 0, $limit = 0 ) {
	global $wpdb;
	list( $t, $col ) = jinv_wf_meta_table();
	$sql = "SELECT DISTINCT $col FROM $t WHERE meta_key IN ('wc_wi_invoice_id','wc_wi_proforma_id','wc_wi_creditnote_id') AND meta_value <> '' ORDER BY $col ASC"; // phpcs:ignore
	if ( $limit ) {
		$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $limit, $offset );
	}
	return array_map( 'intval', $wpdb->get_col( $sql ) ); // phpcs:ignore
}

/** Prehľad: koľko dokladov a PDF Woo Faktura má (bez zápisu). */
function jinv_wf_detect() {
	global $wpdb;
	list( $t ) = jinv_wf_meta_table();
	$counts = array();
	foreach ( jinv_wf_types() as $type => $def ) {
		$counts[ $type ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE meta_key = %s AND meta_value <> ''", 'wc_wi_' . $type . '_id' ) ); // phpcs:ignore
	}
	$dir   = jinv_wf_dir();
	$files = $dir && is_dir( $dir ) ? count( (array) glob( trailingslashit( $dir ) . '*.pdf' ) ) : 0;
	$done  = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . jinv_table() . " WHERE source = 'woo-faktura'" ); // phpcs:ignore
	return array(
		'active'   => jinv_woo_faktura_active(),
		'counts'   => $counts,
		'total'    => array_sum( $counts ),
		'dir'      => $dir,
		'files'    => $files,
		'orders'   => count( jinv_wf_order_ids() ),
		'imported' => $done,
		'settings' => array(
			'name'     => (string) get_option( 'woocommerce_wi_invoice_name' ),
			'ico'      => (string) get_option( 'woocommerce_wi_invoice_ico' ),
			'dic'      => (string) get_option( 'woocommerce_wi_invoice_vat_id' ),
			'due_days' => (int) get_option( 'woocommerce_wi_invoice_payment_due' ),
			'counters' => array(
				'invoice'    => (int) get_option( 'woocommerce_wi_invoice_count' ),
				'proforma'   => (int) get_option( 'woocommerce_wi_proforma_count' ),
				'creditnote' => (int) get_option( 'woocommerce_wi_creditnote_count' ),
			),
		),
	);
}

/** Priečinok s PDF Woo Faktury. */
function jinv_wf_dir() {
	$dir = (string) get_option( 'woocommerce_wi_invoice_dir' );
	if ( $dir && is_dir( $dir ) ) {
		return untrailingslashit( $dir );
	}
	$up = wp_upload_dir();
	return trailingslashit( $up['basedir'] ) . 'invoices';
}

/** Celá cesta k PDF prevzatého dokladu (alebo ''). */
function jinv_wf_file_path( $file ) {
	$file = basename( (string) $file );
	if ( '' === $file ) {
		return '';
	}
	$path = trailingslashit( jinv_wf_dir() ) . $file;
	return is_file( $path ) ? $path : '';
}

/** Číslo dokladu na poradové číslo pre jedinečný kľúč (len číslice; inak kontrolný súčet). */
function jinv_wf_seq( $number ) {
	$digits = preg_replace( '/\D/', '', (string) $number );
	if ( '' !== $digits && strlen( $digits ) <= 9 ) {
		return (int) $digits;
	}
	return (int) sprintf( '%u', crc32( (string) $number ) );
}

/** Údaje dodávateľa pre prevzatý doklad: nastavenia J-Fakturácie, chýbajúce doplnené z Woo Faktury. */
function jinv_wf_supplier() {
	$s   = jinv_settings();
	$sup = array_intersect_key( $s, array_flip( array( 'company', 'street', 'zip', 'city', 'country', 'ico', 'dic', 'icdph', 'register', 'vat_payer', 'vat_note', 'iban', 'swift', 'bank', 'email', 'phone', 'web', 'issued_by', 'footer' ) ) );
	if ( empty( $sup['ico'] ) ) {
		$sup['ico'] = (string) get_option( 'woocommerce_wi_invoice_ico' );
	}
	if ( empty( $sup['dic'] ) ) {
		$sup['dic'] = (string) get_option( 'woocommerce_wi_invoice_vat_id' );
	}
	return $sup;
}

/**
 * Spracuje jednu objednávku. $write = false → len kontrola (skúšobný beh).
 * Vráti zoznam výsledkov: [ typ, stav (new|exists|duplicate|error), číslo, správa, má PDF ].
 */
function jinv_wf_process_order( $order_id, $write ) {
	global $wpdb;
	$out = array();
	$o   = wc_get_order( $order_id );
	if ( ! $o ) {
		return array( array( '-', 'error', '', 'Objednávka #' . $order_id . ' neexistuje.', false ) );
	}
	foreach ( jinv_wf_types() as $type => $def ) {
		$kind   = $def[0];
		$number = trim( (string) $o->get_meta( 'wc_wi_' . $type . '_id' ) );
		if ( '' === $number ) {
			continue;
		}
		$file  = basename( (string) $o->get_meta( 'wc_wi_invoice_' . $type ) );
		$fpath = jinv_wf_file_path( $file );
		$date  = (string) $o->get_meta( 'wc_wi_invoice_' . $type . '_date' );
		$due   = (string) $o->get_meta( 'wc_wi_invoice_' . $type . '_due' );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$date = $o->get_date_created() ? $o->get_date_created()->date_i18n( 'Y-m-d' ) : current_time( 'Y-m-d' );
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $due ) ) {
			$due = $date;
		}
		$t = jinv_table();
		// Už prevzatý (rovnaká objednávka, druh a číslo)?
		$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE kind = %s AND order_id = %d AND number = %s LIMIT 1", $kind, $o->get_id(), $number ) ); // phpcs:ignore
		if ( $exists ) {
			$out[] = array( $type, 'exists', $number, '', (bool) $fpath );
			continue;
		}
		$year = (int) substr( $date, 0, 4 );
		$seq  = jinv_wf_seq( $number );
		// Rovnaké číslo už má iný doklad toho istého druhu v tom istom roku?
		$dup = (int) $wpdb->get_var( $wpdb->prepare( "SELECT order_id FROM $t WHERE kind = %s AND year = %d AND seq = %d LIMIT 1", $kind, $year, $seq ) ); // phpcs:ignore
		if ( $dup ) {
			$out[] = array( $type, 'duplicate', $number, sprintf( 'Číslo %s už má doklad k objednávke ID %d – tento sa preskočí.', $number, $dup ), (bool) $fpath );
			continue;
		}
		if ( ! $write ) {
			$out[] = array( $type, 'new', $number, $fpath ? '' : 'Chýba PDF (' . ( $file ? $file : 'bez názvu súboru' ) . ').', (bool) $fpath );
			continue;
		}
		$paid   = $o->is_paid() || $o->has_status( 'completed' );
		$status = 'issued';
		if ( 'issued' === $kind && $paid ) {
			$status = 'paid';
		} elseif ( 'proforma' === $kind ) {
			$status = $o->get_meta( 'wc_wi_invoice_id' ) || $paid ? 'paid' : ( $o->has_status( array( 'cancelled', 'failed' ) ) ? 'cancelled' : 'issued' );
		}
		$total = round( (float) $o->get_total(), 2 );
		if ( 'creditnote' === $kind ) {
			$ref   = (float) $o->get_total_refunded();
			$total = -1 * round( $ref > 0 ? $ref : $total, 2 );
		}
		$supply = $o->get_date_completed() ? $o->get_date_completed()->date_i18n( 'Y-m-d' ) : $date;
		$data   = array(
			'supplier' => jinv_wf_supplier(), // len orientačne – platné údaje dodávateľa sú v pôvodnom PDF
			'customer' => jinv_customer_from_order( $o ),
			'items'    => jinv_items_from_order( $o ),
			'payment'  => $o->get_payment_method_title(),
			'pay_code' => $o->get_payment_method(),
			'order_no' => $o->get_order_number(),
			'note'     => '',
			'imported' => array(
				'from'   => 'woo-faktura',
				'type'   => $type,
				'number' => $number,
				'file'   => $file,
				'at'     => current_time( 'mysql' ),
			),
		);
		$ok = $wpdb->insert( $t, array(
			'kind'           => $kind,
			'number'         => $number,
			'year'           => $year,
			'seq'            => $seq,
			'order_id'       => $o->get_id(),
			'status'         => $status,
			'issue_date'     => $date,
			'supply_date'    => $supply,
			'due_date'       => $due,
			'vs'             => preg_replace( '/\D/', '', $number ),
			'currency'       => $o->get_currency(),
			'total'          => $total,
			'customer_name'  => $data['customer']['company'] ? $data['customer']['company'] : $data['customer']['name'],
			'customer_email' => $data['customer']['email'],
			'data'           => wp_json_encode( $data, JSON_UNESCAPED_UNICODE ),
			'source'         => 'woo-faktura',
			'file'           => $fpath ? $file : '',
			'paid_at'        => 'paid' === $status ? current_time( 'mysql' ) : null,
			'created_at'     => current_time( 'mysql' ),
		) );
		if ( ! $ok ) {
			$out[] = array( $type, 'error', $number, 'Nepodarilo sa uložiť: ' . $wpdb->last_error, (bool) $fpath );
			continue;
		}
		if ( 'issued' === $kind && ! $o->get_meta( '_jinv_invoice' ) ) {
			$o->update_meta_data( '_jinv_invoice', $number );
			$o->save_meta_data();
		}
		$out[] = array( $type, 'new', $number, $fpath ? '' : 'Prevzaté bez PDF (súbor sa nenašiel).', (bool) $fpath );
	}
	return $out;
}

/** AJAX: jedna dávka skúšobného behu alebo importu. */
add_action( 'wp_ajax_jinv_wf_batch', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_send_json_error( 'Nemáte oprávnenie.', 403 );
	}
	check_ajax_referer( 'jinv_wf', 'nonce' );
	$write  = ! empty( $_POST['write'] ); // phpcs:ignore
	$offset = isset( $_POST['offset'] ) ? max( 0, (int) $_POST['offset'] ) : 0; // phpcs:ignore
	$key    = 'jinv_wf_' . ( $write ? 'run' : 'dry' ) . '_' . get_current_user_id();
	$stats  = 0 === $offset ? array() : (array) get_transient( $key );
	$stats  = wp_parse_args( $stats, array( 'orders' => 0, 'new' => array(), 'exists' => 0, 'duplicate' => 0, 'error' => 0, 'nopdf' => 0, 'problems' => array() ) );
	if ( $write && 0 === $offset ) {
		jinv_install(); // stĺpce source/file musia existovať
	}
	$ids = jinv_wf_order_ids( $offset, JINV_WF_BATCH );
	foreach ( $ids as $id ) {
		$stats['orders']++;
		foreach ( jinv_wf_process_order( $id, $write ) as $r ) {
			list( $type, $state, $num, $msg, $has_pdf ) = $r;
			if ( 'new' === $state ) {
				$stats['new'][ $type ] = ( isset( $stats['new'][ $type ] ) ? $stats['new'][ $type ] : 0 ) + 1;
				if ( ! $has_pdf ) {
					$stats['nopdf']++;
				}
			} elseif ( isset( $stats[ $state ] ) ) {
				$stats[ $state ]++;
			}
			if ( $msg && count( $stats['problems'] ) < 200 ) {
				$stats['problems'][] = array( $type, $num, $id, $msg );
			}
		}
	}
	set_transient( $key, $stats, DAY_IN_SECONDS );
	$total = count( jinv_wf_order_ids() );
	$next  = $offset + count( $ids );
	$done  = count( $ids ) < JINV_WF_BATCH || $next >= $total;
	if ( $done && $write ) {
		update_option( 'jinv_wf_last_import', array( 'at' => current_time( 'mysql' ), 'stats' => $stats ), false );
	}
	wp_send_json_success( array( 'next' => $next, 'total' => $total, 'done' => $done, 'stats' => $stats ) );
} );

/** Prevzatie nastavení z Woo Faktury (IČO, DIČ, splatnosť) – len prázdne polia J-Fakturácie. */
add_action( 'admin_post_jinv_wf_settings', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jinv_wf_settings' );
	$s   = jinv_settings();
	$map = array( 'ico' => 'woocommerce_wi_invoice_ico', 'dic' => 'woocommerce_wi_invoice_vat_id' );
	foreach ( $map as $k => $opt ) {
		$v = trim( (string) get_option( $opt ) );
		if ( '' === trim( (string) $s[ $k ] ) && '' !== $v ) {
			$s[ $k ] = sanitize_text_field( $v );
		}
	}
	$wf_logo = trim( (string) get_option( 'woocommerce_wi_invoice_logo' ) );
	if ( '' === trim( (string) ( $s['logo'] ?? '' ) ) && preg_match( '/\.(png|jpe?g)$/i', $wf_logo ) ) {
		$s['logo'] = esc_url_raw( $wf_logo ); // logo z Woo Faktury
	}
	if ( ! empty( $_POST['numbering'] ) ) { // phpcs:ignore
		$s['numbering'] = 'order'; // ako Woo Faktura: číslo dokladu = číslo objednávky
	}
	$due = (int) get_option( 'woocommerce_wi_invoice_payment_due' );
	if ( $due > 0 && ! empty( $_POST['due'] ) ) { // phpcs:ignore
		$s['due_days'] = min( 365, $due );
	}
	update_option( 'jinv_settings', $s, false );
	wp_safe_redirect( admin_url( 'admin.php?page=j-invoices-settings&section=import&step=2&settings_ok=1' ) );
	exit;
} );

/** Stiahnutie pôvodného PDF prevzatého dokladu (len administrátor obchodu). */
add_action( 'admin_post_jinv_wf_pdf', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore
	check_admin_referer( 'jinv_wf_pdf_' . $id );
	$inv  = jinv_get( $id );
	$path = $inv ? jinv_wf_file_path( $inv['file'] ) : '';
	if ( ! $path ) {
		wp_die( 'PDF sa nenašlo.' );
	}
	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: inline; filename="' . sanitize_file_name( $inv['number'] ) . '.pdf"' );
	header( 'Content-Length: ' . filesize( $path ) );
	readfile( $path ); // phpcs:ignore
	exit;
} );

function jinv_wf_pdf_url( $id ) {
	return wp_nonce_url( admin_url( 'admin-post.php?action=jinv_wf_pdf&id=' . (int) $id ), 'jinv_wf_pdf_' . (int) $id );
}

/** Obsah záložky Nastavenia → Importy údajov. */
function jinv_render_import() {
	$step = isset( $_GET['step'] ) ? max( 1, min( 4, (int) $_GET['step'] ) ) : 1; // phpcs:ignore
	$base = admin_url( 'admin.php?page=j-invoices-settings&section=import' );
	$d    = jinv_wf_detect();
	$steps = array( 1 => 'Zistenie', 2 => 'Nastavenia', 3 => 'Skúšobný beh', 4 => 'Import' );
	?>
	<div class="jinv-panel jinv-import">
		<h2>Import z doplnku Woo Faktura</h2>
		<p class="description">Prevezme faktúry, zálohové faktúry a dobropisy z Woo Faktury aj s pôvodnými PDF. Vo Woo Faktura ani v objednávkach sa nič nemení; import je bezpečné spustiť opakovane (napr. ešte raz tesne pred vypnutím Woo Faktury).</p>
		<ol class="jinv-steps">
			<?php foreach ( $steps as $n => $label ) : ?>
				<li class="<?php echo $n === $step ? 'is-active' : ( $n < $step ? 'is-done' : '' ); ?>"><span><?php echo (int) $n; ?></span><?php echo esc_html( $label ); ?></li>
			<?php endforeach; ?>
		</ol>

		<?php if ( 1 === $step ) : ?>
			<?php if ( ! $d['total'] ) : ?>
				<p><strong>Nenašli sa žiadne doklady Woo Faktury.</strong> Na tomto webe nie je čo prevziať.</p>
			<?php else : ?>
				<div class="jinv-cards">
					<?php foreach ( jinv_wf_types() as $type => $def ) : ?>
						<div><span><?php echo esc_html( $def[1] ); ?></span><strong><?php echo esc_html( number_format_i18n( $d['counts'][ $type ] ) ); ?></strong></div>
					<?php endforeach; ?>
					<div><span>Objednávky s dokladom</span><strong><?php echo esc_html( number_format_i18n( $d['orders'] ) ); ?></strong></div>
					<div><span>PDF v priečinku</span><strong><?php echo esc_html( number_format_i18n( $d['files'] ) ); ?></strong><small>vrátane starších verzií</small></div>
					<div class="<?php echo $d['imported'] ? '' : 'is-muted'; ?>"><span>Už prevzaté</span><strong><?php echo esc_html( number_format_i18n( $d['imported'] ) ); ?></strong></div>
				</div>
				<p>Priečinok s PDF: <code><?php echo esc_html( $d['dir'] ); ?></code></p>
				<p>Woo Faktura je <strong><?php echo $d['active'] ? 'zapnutá' : 'vypnutá'; ?></strong>. <?php echo $d['active'] ? 'Pokračuje vo vystavovaní – import potom zopakujte, nové doklady sa doplnia.' : ''; ?></p>
				<p><a class="button button-primary" href="<?php echo esc_url( $base . '&step=2' ); ?>">Pokračovať →</a></p>
			<?php endif; ?>

		<?php elseif ( 2 === $step ) : ?>
			<?php if ( isset( $_GET['settings_ok'] ) ) : // phpcs:ignore ?><div class="notice notice-success inline"><p>Údaje sú prevzaté do Nastavení → Dodávateľ.</p></div><?php endif; ?>
			<p>Údaje z Woo Faktury, ktoré sa dajú prevziať do J-Fakturácie (doplnia sa len prázdne polia):</p>
			<table class="widefat striped jinv-narrow">
				<tbody>
					<tr><th>Názov na faktúre</th><td><?php echo esc_html( $d['settings']['name'] ? $d['settings']['name'] : '—' ); ?> <small>(len na porovnanie – obchodné meno nastavte v záložke Dodávateľ)</small></td></tr>
					<tr><th>IČO</th><td><?php echo esc_html( $d['settings']['ico'] ? $d['settings']['ico'] : '—' ); ?></td></tr>
					<tr><th>DIČ</th><td><?php echo esc_html( $d['settings']['dic'] ? $d['settings']['dic'] : '—' ); ?></td></tr>
					<tr><th>Splatnosť</th><td><?php echo esc_html( $d['settings']['due_days'] ? $d['settings']['due_days'] . ' dní' : '—' ); ?></td></tr>
					<tr><th>Počítadlá Woo Faktury</th><td>faktúry <?php echo (int) $d['settings']['counters']['invoice']; ?>, zálohové <?php echo (int) $d['settings']['counters']['proforma']; ?>, dobropisy <?php echo (int) $d['settings']['counters']['creditnote']; ?> <small>(len informácia; čísla Woo Faktury boli = číslo objednávky)</small></td></tr>
				</tbody>
			</table>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="jinv-inline-form">
				<?php wp_nonce_field( 'jinv_wf_settings' ); ?>
				<input type="hidden" name="action" value="jinv_wf_settings">
				<label><input type="checkbox" name="due" value="1" checked> prevziať aj splatnosť</label>
				<label><input type="checkbox" name="numbering" value="1" checked> číslovať ako Woo Faktura (číslo dokladu = číslo objednávky)</label>
				<button class="button">Prevziať údaje</button>
			</form>
			<p>
				<a class="button" href="<?php echo esc_url( $base . '&step=1' ); ?>">← Späť</a>
				<a class="button button-primary" href="<?php echo esc_url( $base . '&step=3' ); ?>">Pokračovať na skúšobný beh →</a>
			</p>

		<?php else : ?>
			<?php $write = 4 === $step; ?>
			<?php if ( $write ) : ?>
				<p><strong>Ostrý import</strong> zapíše doklady do J-Fakturácie (Vydané faktúry). Už prevzaté sa preskočia. Pred prvým importom odporúčame zálohu databázy.</p>
			<?php else : ?>
				<p><strong>Skúšobný beh</strong> prejde všetky doklady a ukáže, čo by sa prevzalo – <strong>nič sa nezapíše</strong>.</p>
			<?php endif; ?>
			<p>
				<button type="button" class="button button-primary" id="jinv-wf-start" data-write="<?php echo $write ? '1' : '0'; ?>"><?php echo $write ? 'Spustiť import' : 'Spustiť skúšobný beh'; ?></button>
				<span id="jinv-wf-progress" class="jinv-progress" hidden><span class="jinv-bar"><i style="width:0"></i></span> <span class="jinv-ptext">0 / <?php echo (int) $d['orders']; ?> objednávok</span></span>
			</p>
			<div id="jinv-wf-result"></div>
			<p class="jinv-wf-nav">
				<a class="button" href="<?php echo esc_url( $base . '&step=' . ( $step - 1 ) ); ?>">← Späť</a>
				<?php if ( ! $write ) : ?><a class="button button-primary" id="jinv-wf-next" href="<?php echo esc_url( $base . '&step=4' ); ?>" hidden>Pokračovať na import →</a><?php endif; ?>
				<?php if ( $write ) : ?><a class="button button-primary" id="jinv-wf-list" href="<?php echo esc_url( jinv_admin_url() ); ?>" hidden>Otvoriť Vydané faktúry →</a><?php endif; ?>
			</p>
			<script>
			(function(){
				var btn=document.getElementById('jinv-wf-start'),prog=document.getElementById('jinv-wf-progress'),res=document.getElementById('jinv-wf-result');
				var names={invoice:'Faktúry',proforma:'Zálohové faktúry',creditnote:'Dobropisy'};
				var write=btn.getAttribute('data-write')==='1';
				function esc(s){return String(s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
				function show(st,done){
					var h='<div class="jinv-cards">';
					Object.keys(names).forEach(function(k){h+='<div><span>'+names[k]+(write?' – prevzaté':' – na prevzatie')+'</span><strong>'+(st.new[k]||0)+'</strong></div>';});
					h+='<div><span>Už prevzaté skôr</span><strong>'+st.exists+'</strong></div>';
					h+='<div class="'+(st.nopdf?'is-warn':'')+'"><span>Bez PDF</span><strong>'+st.nopdf+'</strong></div>';
					h+='<div class="'+((st.duplicate||st.error)?'is-warn':'')+'"><span>Duplicitné čísla / chyby</span><strong>'+st.duplicate+' / '+st.error+'</strong></div></div>';
					if(st.problems&&st.problems.length){
						h+='<details'+(done?' open':'')+'><summary>Upozornenia ('+st.problems.length+(st.problems.length>=200?'+':'')+')</summary><table class="widefat striped"><thead><tr><th>Doklad</th><th>Číslo</th><th>Objednávka (ID)</th><th>Poznámka</th></tr></thead><tbody>';
						st.problems.forEach(function(p){h+='<tr><td>'+esc(names[p[0]]||p[0])+'</td><td>'+esc(p[1])+'</td><td>'+esc(p[2])+'</td><td>'+esc(p[3])+'</td></tr>';});
						h+='</tbody></table></details>';
					}
					if(done){h+='<p class="jinv-done">'+(write?'Import je dokončený.':'Skúšobný beh je dokončený – nič sa nezapísalo.')+'</p>';}
					res.innerHTML=h;
				}
				function batch(offset){
					var f=new FormData();
					f.append('action','jinv_wf_batch');f.append('nonce','<?php echo esc_js( wp_create_nonce( 'jinv_wf' ) ); ?>');
					f.append('offset',offset);if(write){f.append('write','1');}
					fetch(ajaxurl,{method:'POST',body:f,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
						if(!j||!j.success){throw new Error(j&&j.data?j.data:'Chyba servera');}
						var d=j.data,pct=d.total?Math.min(100,Math.round(d.next/d.total*100)):100;
						prog.querySelector('i').style.width=pct+'%';
						prog.querySelector('.jinv-ptext').textContent=Math.min(d.next,d.total)+' / '+d.total+' objednávok';
						show(d.stats,d.done);
						if(d.done){btn.disabled=false;btn.textContent=write?'Spustiť import znova':'Spustiť znova';var n=document.getElementById(write?'jinv-wf-list':'jinv-wf-next');if(n){n.hidden=false;}}
						else{batch(d.next);}
					}).catch(function(e){btn.disabled=false;res.insertAdjacentHTML('beforeend','<div class="notice notice-error inline"><p>Prerušené: '+esc(e.message)+'. Spustite znova – už spracované sa preskočia.</p></div>');});
				}
				btn.addEventListener('click',function(){
					if(write&&!confirm('Spustiť import dokladov z Woo Faktury do J-Fakturácie?')){return;}
					btn.disabled=true;prog.hidden=false;res.innerHTML='';batch(0);
				});
			})();
			</script>
		<?php endif; ?>
	</div>
	<?php
}
