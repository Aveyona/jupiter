<?php
/**
 * Úhrady dokladov – spoločný základ pre prijaté aj vydané faktúry, banku a pokladňu.
 * Doklad môže mať viac úhrad (čiastočné platby); stav „Uhradená“ sa nastaví, keď súčet úhrad dosiahne sumu dokladu.
 * Zdroj úhrady: manual (ručne), bank (výpis), cash (pokladňa), woo (platba objednávky); ref = identifikátor v zdroji.
 * Staršie doklady označené ako uhradené bez záznamu úhrady sa berú ako uhradené celou sumou.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function jinv_pay_methods() {
	return apply_filters( 'jinv_pay_methods', array(
		'bank'   => 'Prevodom',
		'cash'   => 'V hotovosti',
		'card'   => 'Kartou',
		'offset' => 'Zápočtom',
		'other'  => 'Iné',
	) );
}

function jinv_payments( $doc_id ) {
	global $wpdb;
	return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . jinv_table( 'payments' ) . ' WHERE doc_id = %d ORDER BY pay_date, id', $doc_id ), ARRAY_A ); // phpcs:ignore
}

/** Uhradená suma dokladu (pri staršom „uhradenom“ doklade bez záznamov = celá suma). */
function jinv_paid_sum( array $doc ) {
	global $wpdb;
	$sum = $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(amount) FROM ' . jinv_table( 'payments' ) . ' WHERE doc_id = %d', $doc['id'] ) ); // phpcs:ignore
	if ( null === $sum ) {
		return 'paid' === $doc['status'] ? (float) $doc['total'] : 0.0;
	}
	return round( (float) $sum, 2 );
}

/** Zostáva uhradiť. */
function jinv_remaining( array $doc ) {
	return max( 0, round( (float) $doc['total'] - jinv_paid_sum( $doc ), 2 ) );
}

/**
 * Zaznamená úhradu dokladu a prepočíta jeho stav. $args: date (Y-m-d), method, source, ref, note.
 * Vráti ID úhrady alebo WP_Error. Rovnaký zdroj + ref sa nezapíše dvakrát (napr. opakovaný import výpisu).
 */
function jinv_add_payment( $doc_id, $amount, $args = array() ) {
	global $wpdb;
	$doc = jinv_get( $doc_id );
	if ( ! $doc ) {
		return new WP_Error( 'jinv_pay', 'Doklad neexistuje.' );
	}
	if ( 'cancelled' === $doc['status'] ) {
		return new WP_Error( 'jinv_pay', 'Doklad je stornovaný – úhradu nemožno zapísať.' );
	}
	$amount = round( (float) $amount, 2 );
	if ( abs( $amount ) < 0.005 ) {
		return new WP_Error( 'jinv_pay', 'Suma úhrady je nulová.' );
	}
	$a = wp_parse_args( $args, array( 'date' => current_time( 'Y-m-d' ), 'method' => 'bank', 'source' => 'manual', 'ref' => '', 'note' => '' ) );
	if ( '' !== $a['ref'] && $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . jinv_table( 'payments' ) . ' WHERE source = %s AND ref = %s AND doc_id = %d', $a['source'], $a['ref'], $doc_id ) ) ) { // phpcs:ignore
		return new WP_Error( 'jinv_pay_dup', 'Táto úhrada už je zapísaná.' );
	}
	// Starší doklad „uhradený“ bez záznamov: najprv sa zapíše jeho pôvodná úhrada, aby súčet sedel.
	if ( 'paid' === $doc['status'] && ! jinv_payments( $doc_id ) ) {
		jinv_insert_payment( $doc_id, (float) $doc['total'], array( 'date' => $doc['paid_at'] ? substr( $doc['paid_at'], 0, 10 ) : $doc['issue_date'], 'method' => 'other', 'source' => 'manual', 'ref' => '', 'note' => 'Pôvodná úhrada' ) );
	}
	$id = jinv_insert_payment( $doc_id, $amount, $a );
	jinv_sync_paid( $doc_id );
	do_action( 'jinv_payment_added', $id, $doc_id, $amount, $a );
	return $id;
}

function jinv_insert_payment( $doc_id, $amount, array $a ) {
	global $wpdb;
	$wpdb->insert( jinv_table( 'payments' ), array(
		'doc_id'     => (int) $doc_id,
		'pay_date'   => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $a['date'] ) ? $a['date'] : current_time( 'Y-m-d' ),
		'amount'     => round( (float) $amount, 2 ),
		'method'     => isset( jinv_pay_methods()[ $a['method'] ] ) ? $a['method'] : 'other',
		'source'     => sanitize_key( $a['source'] ),
		'ref'        => mb_substr( (string) $a['ref'], 0, 100 ),
		'note'       => mb_substr( sanitize_text_field( (string) $a['note'] ), 0, 255 ),
		'user_id'    => get_current_user_id(),
		'created_at' => current_time( 'mysql' ),
	) );
	return (int) $wpdb->insert_id;
}

/** Suma zo vstupu: „1 234,50“, „1.234,50“, „1234.5“ → 1234.5. */
function jinv_parse_amount( $v ) {
	$v = preg_replace( '/[\s\x{00A0}€]/u', '', (string) $v );
	if ( false !== strpos( $v, ',' ) ) {
		$v = str_replace( array( '.', ',' ), array( '', '.' ), $v ); // bodka = oddeľovač tisícov
	}
	return (float) $v;
}

function jinv_delete_payment( $payment_id ) {
	global $wpdb;
	$doc_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT doc_id FROM ' . jinv_table( 'payments' ) . ' WHERE id = %d', $payment_id ) ); // phpcs:ignore
	if ( $doc_id ) {
		$wpdb->delete( jinv_table( 'payments' ), array( 'id' => (int) $payment_id ) );
		// Pohyb v banke, z ktorého úhrada vznikla, sa znova ponúkne na spárovanie.
		$wpdb->update( jinv_table( 'banktx' ), array( 'status' => 'new', 'doc_id' => 0, 'payment_id' => 0, 'matched_by' => '' ), array( 'payment_id' => (int) $payment_id ) );
		wp_cache_delete( 'jinv_bank_unmatched', 'jinv' );
		jinv_sync_paid( $doc_id );
		do_action( 'jinv_payment_deleted', $payment_id, $doc_id );
	}
	return $doc_id;
}

/** Celá úhrada zostávajúcej sumy (tlačidlo „Uhradená“). */
function jinv_pay_full( $doc_id, $args = array() ) {
	$doc = jinv_get( $doc_id );
	if ( ! $doc ) {
		return new WP_Error( 'jinv_pay', 'Doklad neexistuje.' );
	}
	$rest = jinv_remaining( $doc );
	if ( $rest < 0.005 ) {
		jinv_sync_paid( $doc_id );
		return 0;
	}
	return jinv_add_payment( $doc_id, $rest, $args );
}

/** Zrušenie všetkých ručných úhrad (tlačidlo „Neuhradená“); úhrady z banky a pokladne ostanú. */
function jinv_unpay( $doc_id ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . jinv_table( 'payments' ) . " WHERE doc_id = %d AND source IN ('manual','')", $doc_id ) ); // phpcs:ignore
	jinv_sync_paid( $doc_id, true );
}

/** Stav dokladu podľa úhrad: paid ↔ issued. Stornovaný doklad sa nemení. */
function jinv_sync_paid( $doc_id, $force = false ) {
	global $wpdb;
	$doc = jinv_get( $doc_id );
	if ( ! $doc || 'cancelled' === $doc['status'] ) {
		return;
	}
	$has = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . jinv_table( 'payments' ) . ' WHERE doc_id = %d', $doc_id ) ); // phpcs:ignore
	if ( ! $has && ! $force ) {
		return; // bez záznamov úhrad sa stav nemení (doklady uhradené pred zavedením úhrad)
	}
	$sum  = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(amount),0) FROM ' . jinv_table( 'payments' ) . ' WHERE doc_id = %d', $doc_id ) ); // phpcs:ignore
	$last = $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(pay_date) FROM ' . jinv_table( 'payments' ) . ' WHERE doc_id = %d', $doc_id ) ); // phpcs:ignore
	$full = (float) $doc['total'] > 0 ? $sum >= (float) $doc['total'] - 0.005 : $has > 0;
	if ( $full && 'paid' !== $doc['status'] ) {
		jinv_update( $doc_id, array( 'status' => 'paid', 'paid_at' => $last . ' 12:00:00' ) );
		do_action( 'jinv_doc_paid', $doc_id );
	} elseif ( ! $full && 'paid' === $doc['status'] ) {
		jinv_update( $doc_id, array( 'status' => 'issued', 'paid_at' => null ) );
	}
}

/** Tabuľka úhrad s formulárom na pridanie (detail prijatej / vydanej faktúry). */
function jinv_render_payments_box( array $doc, $back ) {
	$pays = jinv_payments( $doc['id'] );
	$paid = jinv_paid_sum( $doc );
	$rest = max( 0, round( (float) $doc['total'] - $paid, 2 ) );
	$m    = jinv_pay_methods();
	$src  = array( 'manual' => 'ručne', 'bank' => 'banka', 'cash' => 'pokladňa', 'woo' => 'objednávka' );
	?>
	<div class="jinv-panel jinv-pays">
		<h3>Úhrady <small>uhradené <?php echo esc_html( jinv_money( $paid, $doc['currency'] ) ); ?> z <?php echo esc_html( jinv_money( $doc['total'], $doc['currency'] ) ); ?><?php echo $rest > 0 && 'cancelled' !== $doc['status'] ? ' · zostáva <strong>' . esc_html( jinv_money( $rest, $doc['currency'] ) ) . '</strong>' : ''; ?></small></h3>
		<?php if ( $pays ) : ?>
			<table class="widefat striped jinv-table">
				<thead><tr><th>Dátum</th><th class="n">Suma</th><th>Spôsob</th><th>Zdroj</th><th>Poznámka</th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $pays as $p ) : ?>
					<tr>
						<td><?php echo esc_html( jinv_date( $p['pay_date'] ) ); ?></td>
						<td class="n"><?php echo esc_html( jinv_money( $p['amount'], $doc['currency'] ) ); ?></td>
						<td><?php echo esc_html( $m[ $p['method'] ] ?? $p['method'] ); ?></td>
						<td><?php echo esc_html( ( $src[ $p['source'] ] ?? $p['source'] ) . ( $p['ref'] ? ' · ' . $p['ref'] : '' ) ); ?></td>
						<td><?php echo esc_html( $p['note'] ); ?></td>
						<td class="jinv-row-actions"><a class="jinv-del" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=jinv_pay_delete&id=' . (int) $p['id'] . '&back=' . rawurlencode( $back ) ), 'jinv_pay_delete_' . (int) $p['id'] ) ); ?>" onclick="return confirm('Zmazať túto úhradu?');">Zmazať</a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php elseif ( 'paid' === $doc['status'] ) : ?>
			<p class="description">Doklad je označený ako uhradený (bez podrobností o úhrade).</p>
		<?php endif; ?>
		<?php if ( 'cancelled' !== $doc['status'] && $rest > 0 ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="jinv-inline-form">
				<?php wp_nonce_field( 'jinv_pay_add_' . (int) $doc['id'] ); ?>
				<input type="hidden" name="action" value="jinv_pay_add"><input type="hidden" name="id" value="<?php echo (int) $doc['id']; ?>"><input type="hidden" name="back" value="<?php echo esc_attr( $back ); ?>">
				<label>Dátum <input type="date" name="date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required></label>
				<label>Suma <input type="text" name="amount" inputmode="decimal" value="<?php echo esc_attr( number_format( $rest, 2, ',', '' ) ); ?>" style="width:110px" required></label>
				<label>Spôsob <select name="method"><?php foreach ( $m as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></label>
				<label>Poznámka <input type="text" name="note" placeholder="napr. čiastočná platba"></label>
				<button class="button">Pridať úhradu</button>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

/** Bezpečná návratová adresa v administrácii. */
function jinv_back_url( $back, $msg = '' ) {
	$url = wp_validate_redirect( (string) $back, admin_url( 'admin.php?page=j-invoices' ) );
	return $msg ? add_query_arg( 'jinv_msg', $msg, $url ) : $url;
}

add_action( 'admin_post_jinv_pay_add', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$id = (int) ( $_POST['id'] ?? 0 );
	check_admin_referer( 'jinv_pay_add_' . $id );
	$in = wp_unslash( $_POST );
	$r  = jinv_add_payment( $id, jinv_parse_amount( $in['amount'] ?? '0' ), array(
		'date'   => sanitize_text_field( $in['date'] ?? '' ),
		'method' => sanitize_key( $in['method'] ?? 'bank' ),
		'note'   => (string) ( $in['note'] ?? '' ),
	) );
	if ( is_wp_error( $r ) ) {
		set_transient( 'jinv_err_' . get_current_user_id(), $r->get_error_message(), 60 );
	}
	wp_safe_redirect( jinv_back_url( $in['back'] ?? '', is_wp_error( $r ) ? 'payerr' : 'payadded' ) );
	exit;
} );

add_action( 'admin_post_jinv_pay_delete', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$id = (int) ( $_GET['id'] ?? 0 ); // phpcs:ignore
	check_admin_referer( 'jinv_pay_delete_' . $id );
	jinv_delete_payment( $id );
	wp_safe_redirect( jinv_back_url( wp_unslash( $_GET['back'] ?? '' ), 'paydeleted' ) ); // phpcs:ignore
	exit;
} );
