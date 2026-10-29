<?php
/**
 * Zoznam objednávok J-Objednávky – nadstavba nad WooCommerce (objednávky sa čítajú cez wc_get_orders, funguje s HPOS).
 * Stavy s počtami, vyhľadávanie (aj podľa čísla objednávky), filter platby a dátumu, stĺpce platba, doprava,
 * doklady (J-Fakturácia – zálohová faktúra aj faktúra so stavom, Vystaviť / Pregenerovať pri riadku), filter
 * „Na fakturáciu“ (vybavené objednávky bez faktúry) a hromadné akcie (stav, vystaviť faktúry, vybaviť a vystaviť).
 * Úprava objednávky prebieha v štandardnom detaile WooCommerce.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const JORD_PER_PAGE = 50;

function jord_list_url( $args = array() ) {
	return add_query_arg( $args, admin_url( 'admin.php?page=j-orders' ) );
}

/** Spôsoby platby, ktoré obchod pozná: kód => názov. */
function jord_payment_methods() {
	$out = array();
	if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
		foreach ( WC()->payment_gateways()->payment_gateways() as $id => $gw ) {
			$title       = method_exists( $gw, 'get_method_title' ) && $gw->get_method_title() ? $gw->get_method_title() : $gw->get_title();
			$out[ $id ] = wp_strip_all_tags( $gw->get_title() ? $gw->get_title() : $title );
		}
	}
	return $out;
}

/** ID objednávok zodpovedajúce hľadanému textu (číslo objednávky, meno, e-mail, telefón, adresa…). */
function jord_search_ids( $term ) {
	$ids = array();
	$id  = jord_find_order( $term );
	if ( $id ) {
		$ids[] = $id;
	}
	try {
		if ( function_exists( 'jord_hpos' ) && jord_hpos() ) {
			// HPOS: vyhľadávanie WooCommerce v tabuľkách objednávok (meno, e-mail, telefón, adresa, položky).
			$found = wc_get_orders( array( 's' => $term, 'type' => 'shop_order', 'return' => 'ids', 'limit' => 500, 'status' => array_keys( wc_get_order_statuses() ) ) );
		} else {
			// WC_Data_Store odovzdáva volania cez __call – method_exists by vrátilo false, preto priamo s ochranou.
			$found = WC_Data_Store::load( 'order' )->search_orders( $term );
		}
		$ids = array_merge( $ids, array_map( 'absint', (array) $found ) );
	} catch ( Throwable $e ) {
		unset( $e );
	}
	return array_values( array_unique( array_filter( $ids ) ) );
}

/** Je k dispozícii J-Fakturácia s pregenerovaním (0.9.5+)? */
function jord_jinv() {
	return function_exists( 'jinv_docs_for_order' ) && function_exists( 'jinv_regenerate_doc' ) && function_exists( 'jinv_fulfil_status' );
}

/** Beží Woo Faktura (režim prechodu) – faktúry vystavuje ona. */
function jord_wf() {
	return function_exists( 'jinv_woo_faktura_active' ) && jinv_woo_faktura_active();
}

/** ID objednávok, ktoré už majú platnú (nestornovanú) faktúru v J-Fakturácii. */
function jord_invoiced_ids() {
	global $wpdb;
	if ( ! function_exists( 'jinv_table' ) ) {
		return array();
	}
	return array_map( 'absint', (array) $wpdb->get_col( 'SELECT DISTINCT order_id FROM ' . jinv_table() . " WHERE kind = 'issued' AND status <> 'cancelled' AND order_id > 0" ) ); // phpcs:ignore
}

/** Argumenty dopytu „Na fakturáciu“: vybavené objednávky bez faktúry (aj bez faktúry z Woo Faktury) za posledných 90 dní. */
function jord_tobill_args( array $args ) {
	$args['status'] = array( 'wc-' . jinv_fulfil_status() );
	if ( empty( $args['date_created'] ) ) {
		$args['date_created'] = '>' . ( time() - (int) apply_filters( 'jord_tobill_days', 90 ) * DAY_IN_SECONDS );
	}
	$done           = jord_invoiced_ids();
	if ( $done ) {
		$args['exclude'] = $done;
	}
	$args['meta_query'] = array( array( 'key' => 'wc_wi_invoice_id', 'compare' => 'NOT EXISTS' ) ); // phpcs:ignore
	return $args;
}

function jord_tobill_count() {
	if ( ! jord_jinv() ) {
		return 0;
	}
	$r = wc_get_orders( jord_tobill_args( array( 'type' => 'shop_order', 'limit' => 1, 'paginate' => true, 'return' => 'ids' ) ) );
	return (int) $r->total;
}

/** Bunka Doklady: zálohová faktúra a faktúra so stavom + akcie. */
function jord_docs_cell( WC_Order $o, $back ) {
	if ( ! function_exists( 'jinv_docs_for_order' ) ) {
		return '—';
	}
	$out = array();
	$has = false;
	foreach ( jinv_docs_for_order( $o->get_id() ) as $d ) {
		if ( 'cancelled' === $d['status'] ) {
			continue;
		}
		$short = array( 'proforma' => 'ZF', 'issued' => 'F', 'creditnote' => 'D' )[ $d['kind'] ] ?? '';
		$has   = $has || 'issued' === $d['kind'];
		$title = jinv_kind_label( $d['kind'] ) . ' ' . $d['number'] . ' · ' . jinv_date( $d['issue_date'] );
		$out[] = '<span class="jord-doc"><a href="' . esc_url( admin_url( 'admin.php?page=j-invoices&view=' . (int) $d['id'] ) ) . '" title="' . esc_attr( $title ) . '"><b>' . esc_html( $short ) . '</b> ' . esc_html( $d['number'] ) . '</a> '
			. ( function_exists( 'jinv_status_badge' ) ? jinv_status_badge( $d ) : '' )
			. ( function_exists( 'jinv_pdf_url' ) && ( 'woo-faktura' !== $d['source'] || $d['file'] ) ? ' <a class="jord-pdf" target="_blank" href="' . esc_url( jinv_pdf_url( (int) $d['id'] ) ) . '" title="PDF">PDF</a>' : '' )
			. '</span>';
	}
	$wf_num = (string) $o->get_meta( 'wc_wi_invoice_id' );
	if ( ! $has && '' !== $wf_num ) {
		$out[] = '<span class="jord-doc jord-muted" title="Faktúra z Woo Faktury (zatiaľ neprevzatá do J-Fakturácie)"><b>F</b> ' . esc_html( $wf_num ) . ' <small>Woo Faktura</small></span>';
		$has   = true;
	}
	$acts = array();
	if ( jord_jinv() && ! jord_wf() && current_user_can( 'manage_woocommerce' ) && ! $o->has_status( array( 'cancelled', 'failed', 'refunded', 'checkout-draft', 'pending' ) ) ) {
		if ( ! $has ) {
			$acts[] = '<a class="button button-small" href="' . esc_url( jinv_action_url( 'create', $o->get_id(), $back ) ) . '">Vystaviť faktúru</a>';
		} else {
			$inv = jinv_by_order( $o->get_id(), 'issued' );
			if ( $inv ) {
				$acts[] = '<a class="button button-small" href="' . esc_url( jinv_action_url( 'regen', (int) $inv['id'], $back ) ) . '" onclick="return confirm(\'' . esc_js( jinv_regen_confirm( $inv ) ) . '\');">Pregenerovať</a>';
			}
		}
	}
	if ( ! $out && ! $acts ) {
		return '—';
	}
	return implode( '', $out ) . ( $acts ? '<span class="jord-acts">' . implode( ' ', $acts ) . '</span>' : '' );
}

function jord_render_list() {
	if ( ! current_user_can( 'edit_shop_orders' ) ) {
		return;
	}
	$statuses = wc_get_order_statuses();
	$st       = isset( $_GET['st'] ) ? sanitize_key( wp_unslash( $_GET['st'] ) ) : ''; // phpcs:ignore
	$st       = isset( $statuses[ $st ] ) || ( 'tobill' === $st && jord_jinv() ) ? $st : '';
	$q        = isset( $_GET['s'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) : ''; // phpcs:ignore
	$pay      = isset( $_GET['pay'] ) ? sanitize_key( wp_unslash( $_GET['pay'] ) ) : ''; // phpcs:ignore
	$from     = isset( $_GET['from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ) ? $_GET['from'] : ''; // phpcs:ignore
	$to       = isset( $_GET['to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ) ? $_GET['to'] : ''; // phpcs:ignore
	$pg       = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 ); // phpcs:ignore
	$methods  = jord_payment_methods();

	$args = array(
		'type'     => 'shop_order',
		'limit'    => JORD_PER_PAGE,
		'paged'    => $pg,
		'paginate' => true,
		'orderby'  => 'date',
		'order'    => 'DESC',
		'status'   => $st && 'tobill' !== $st ? array( $st ) : array_keys( $statuses ),
	);
	if ( 'tobill' === $st ) {
		$args = jord_tobill_args( $args );
	}
	if ( $pay ) {
		$args['payment_method'] = $pay;
	}
	if ( $from || $to ) {
		$args['date_created'] = ( $from ? $from : '2000-01-01' ) . '...' . ( $to ? $to : '2999-12-31' );
	}
	$empty = false;
	if ( '' !== $q ) {
		$ids = jord_search_ids( $q );
		if ( ! $ids ) {
			$empty = true;
		} elseif ( jord_hpos() ) {
			$args['id'] = $ids;
		} else {
			$args['post__in'] = $ids;
		}
	}
	if ( '' !== $q && ! $empty && ! empty( $args['exclude'] ) ) {
		$ids = array_values( array_diff( $ids, $args['exclude'] ) ); // hľadanie v „Na fakturáciu“
		unset( $args['exclude'] );
		$empty = ! $ids;
		if ( jord_hpos() ) {
			$args['id'] = $ids;
		} else {
			$args['post__in'] = $ids;
		}
	}
	$res    = $empty ? (object) array( 'orders' => array(), 'total' => 0, 'max_num_pages' => 0 ) : wc_get_orders( $args );
	$orders = $res->orders;
	$keep   = array_filter( array( 'st' => $st, 's' => $q, 'pay' => $pay, 'from' => $from, 'to' => $to ) );
	$msg    = isset( $_GET['jord_msg'] ) ? (int) $_GET['jord_msg'] : -1; // phpcs:ignore
	$imsg   = isset( $_GET['jord_inv'] ) ? (int) $_GET['jord_inv'] : -1; // phpcs:ignore
	$ierr   = isset( $_GET['jord_err'] ) ? (int) $_GET['jord_err'] : 0; // phpcs:ignore
	$back   = jord_list_url( array_merge( $keep, array( 'paged' => $pg ) ) );
	$tobill = jord_tobill_count();
	$jmsg   = isset( $_GET['jinv_msg'] ) ? sanitize_key( $_GET['jinv_msg'] ) : ''; // phpcs:ignore
	?>
	<div class="wrap jord">
		<h1 class="wp-heading-inline">Objednávky <span class="jord-ver">J-Objednávky v<?php echo esc_html( JORD_VERSION ); ?></span></h1>
		<a class="page-title-action" href="<?php echo esc_url( admin_url( jord_hpos() ? 'admin.php?page=wc-orders&action=new' : 'post-new.php?post_type=shop_order' ) ); ?>">Pridať objednávku</a>
		<a class="page-title-action" href="<?php echo esc_url( admin_url( jord_hpos() ? 'admin.php?page=wc-orders' : 'edit.php?post_type=shop_order' ) ); ?>">Zoznam WooCommerce</a>
		<hr class="wp-header-end">
		<?php if ( $msg >= 0 || $imsg >= 0 ) : ?><div class="notice notice-<?php echo $ierr ? 'warning' : 'success'; ?> is-dismissible"><p>
			<?php if ( $msg >= 0 ) : ?>Stav zmenený pri <?php echo (int) $msg; ?> objednávkach. <?php endif; ?>
			<?php if ( $imsg >= 0 ) : ?>Vystavené faktúry: <?php echo (int) $imsg; ?> (bez e-mailu zákazníkom). <?php endif; ?>
			<?php if ( $ierr ) : ?>Pri <?php echo (int) $ierr; ?> objednávkach sa faktúra nevystavila – dôvod je v poznámke objednávky. <?php endif; ?>
		</p></div><?php endif; ?>
		<?php if ( $jmsg ) : ?>
			<?php $jerr = get_transient( 'jinv_err_' . get_current_user_id() ); ?>
			<div class="notice notice-<?php echo 'err' === $jmsg ? 'error' : 'success'; ?> is-dismissible"><p><?php echo esc_html( 'err' === $jmsg ? 'J-Fakturácia: ' . ( $jerr ? $jerr : 'akcia sa nepodarila.' ) : ( 'regenerated' === $jmsg ? 'Faktúra je pregenerovaná podľa aktuálnej objednávky.' : 'Faktúra je vystavená.' ) ); ?></p></div>
		<?php endif; ?>
		<?php if ( jord_wf() ) : ?><div class="notice notice-info"><p><strong>Režim prechodu:</strong> faktúry zatiaľ vystavuje Woo Faktura, preto tu nie sú tlačidlá Vystaviť a Pregenerovať. V stĺpci Doklady vidno aj faktúry Woo Faktury.</p></div><?php endif; ?>

		<ul class="subsubsub jord-status">
			<li><a href="<?php echo esc_url( jord_list_url( array_diff_key( $keep, array( 'st' => 1 ) ) ) ); ?>" class="<?php echo '' === $st ? 'current' : ''; ?>">Všetky</a></li>
			<?php foreach ( $statuses as $key => $label ) : ?>
				<?php $count = (int) wc_orders_count( preg_replace( '/^wc-/', '', $key ) ); ?>
				<?php if ( ! $count && $st !== $key ) { continue; } ?>
				<li> | <a href="<?php echo esc_url( jord_list_url( array_merge( $keep, array( 'st' => $key ) ) ) ); ?>" class="<?php echo $st === $key ? 'current' : ''; ?>"><?php echo esc_html( $label ); ?> <span class="count">(<?php echo esc_html( number_format_i18n( $count ) ); ?>)</span></a></li>
			<?php endforeach; ?>
			<?php if ( jord_jinv() ) : ?>
				<li> | <a href="<?php echo esc_url( jord_list_url( array_merge( $keep, array( 'st' => 'tobill' ) ) ) ); ?>" class="jord-tobill <?php echo 'tobill' === $st ? 'current' : ''; ?><?php echo $tobill ? ' has-items' : ''; ?>" title="Vybavené objednávky (stav „<?php echo esc_attr( wc_get_order_status_name( jinv_fulfil_status() ) ); ?>“) bez faktúry, posledných 90 dní">Na fakturáciu <span class="count">(<?php echo esc_html( number_format_i18n( $tobill ) ); ?>)</span></a></li>
			<?php endif; ?>
		</ul>

		<form method="get" class="jord-filter">
			<input type="hidden" name="page" value="j-orders">
			<?php if ( $st ) : ?><input type="hidden" name="st" value="<?php echo esc_attr( $st ); ?>"><?php endif; ?>
			<input type="search" name="s" value="<?php echo esc_attr( $q ); ?>" placeholder="Číslo objednávky, meno, e-mail, telefón">
			<select name="pay"><option value="">Všetky platby</option><?php foreach ( $methods as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $pay, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
			<label>od <input type="date" name="from" value="<?php echo esc_attr( $from ); ?>"></label>
			<label>do <input type="date" name="to" value="<?php echo esc_attr( $to ); ?>"></label>
			<button class="button">Filtrovať</button>
			<?php if ( $keep ) : ?><a class="button-link" href="<?php echo esc_url( jord_list_url() ); ?>">Zrušiť filter</a><?php endif; ?>
			<span class="jord-total"><?php echo esc_html( number_format_i18n( (int) $res->total ) ); ?> objednávok</span>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="jord-bulk">
			<input type="hidden" name="action" value="jord_bulk_status">
			<input type="hidden" name="back" value="<?php echo esc_attr( jord_list_url( array_merge( $keep, array( 'paged' => $pg ) ) ) ); ?>">
			<?php wp_nonce_field( 'jord_bulk_status' ); ?>
			<div class="jord-bulk">
				<select name="to_status"><option value="">Hromadná akcia…</option>
					<optgroup label="Zmeniť stav na"><?php foreach ( $statuses as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option><?php endforeach; ?></optgroup>
					<?php if ( jord_jinv() && ! jord_wf() && current_user_can( 'manage_woocommerce' ) ) : ?>
						<optgroup label="Faktúry (J-Fakturácia)">
							<option value="jinv:fulfil">Vybaviť (<?php echo esc_html( wc_get_order_status_name( jinv_fulfil_status() ) ); ?>) a vystaviť faktúry</option>
							<option value="jinv:invoice">Len vystaviť faktúry</option>
						</optgroup>
					<?php endif; ?>
				</select>
				<button class="button" onclick="var s=this.form.to_status;if(!s.value||!this.form.querySelector('input[name=&quot;ids[]&quot;]:checked')){return false;}var t=s.options[s.selectedIndex].text;return confirm(s.value.indexOf('jinv:')===0?'„'+t+'“ pre vybrané objednávky? Faktúry sa vystavia bez e-mailu zákazníkom; zmena stavu môže poslať e-mail podľa nastavení WooCommerce.':'Zmeniť stav vybraných objednávok na „'+t+'“? Zákazníkom môžu odísť e-maily podľa nastavení WooCommerce.');">Použiť</button>
			</div>
			<table class="widefat striped jord-table">
				<thead><tr>
					<td class="check-column"><input type="checkbox" onclick="document.querySelectorAll('#jord-bulk input[name=&quot;ids[]&quot;]').forEach(c=>c.checked=this.checked)"></td>
					<th>Objednávka</th><th>Dátum</th><th>Stav</th><th>Zákazník</th><th>Platba</th><th>Doprava</th><th class="n">Suma</th><th>Doklady</th>
				</tr></thead>
				<tbody>
				<?php if ( ! $orders ) : ?>
					<tr><td colspan="9"><?php echo esc_html( 'tobill' === $st ? 'Všetky vybavené objednávky majú faktúru.' : 'Žiadne objednávky' . ( $keep ? ' pre zvolený filter' : '' ) . '.' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $orders as $o ) : ?>
					<?php
					$name = trim( $o->get_formatted_billing_full_name() );
					$co   = $o->get_billing_company();
					$note = $o->get_customer_note();
					$date = $o->get_date_created();
					?>
					<tr>
						<th class="check-column"><input type="checkbox" name="ids[]" value="<?php echo (int) $o->get_id(); ?>"></th>
						<td class="jord-num"><a href="<?php echo esc_url( $o->get_edit_order_url() ); ?>"><strong>#<?php echo esc_html( $o->get_order_number() ); ?></strong></a><?php if ( $note ) : ?> <span class="dashicons dashicons-format-chat jord-note" title="<?php echo esc_attr( $note ); ?>"></span><?php endif; ?></td>
						<td><?php echo $date ? esc_html( wc_format_datetime( $date, 'j. n. Y' ) ) . '<br><small>' . esc_html( wc_format_datetime( $date, 'H:i' ) ) . '</small>' : '—'; ?></td>
						<td><mark class="order-status status-<?php echo esc_attr( $o->get_status() ); ?>"><span><?php echo esc_html( wc_get_order_status_name( $o->get_status() ) ); ?></span></mark></td>
						<td><?php echo esc_html( $name ? $name : '—' ); ?><?php if ( $co ) : ?><br><small><?php echo esc_html( $co ); ?></small><?php endif; ?><br><small><?php echo esc_html( $o->get_billing_email() ); ?></small></td>
						<td><?php echo esc_html( $o->get_payment_method_title() ? $o->get_payment_method_title() : '—' ); ?><?php if ( $o->is_paid() && $o->get_date_paid() ) : ?><br><small class="jord-paid">zaplatené <?php echo esc_html( wc_format_datetime( $o->get_date_paid(), 'j. n.' ) ); ?></small><?php endif; ?></td>
						<td><?php echo esc_html( $o->get_shipping_method() ? $o->get_shipping_method() : '—' ); ?></td>
						<td class="n"><?php echo wp_kses_post( wc_price( $o->get_total(), array( 'currency' => $o->get_currency() ) ) ); ?></td>
						<td class="jord-docs"><?php echo jord_docs_cell( $o, $back ); // phpcs:ignore -- escapované v jord_docs_cell ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</form>
		<?php if ( $res->max_num_pages > 1 ) : ?>
			<p class="jord-pages"><?php echo paginate_links( array( 'base' => add_query_arg( 'paged', '%#%', jord_list_url( $keep ) ), 'format' => '', 'current' => $pg, 'total' => (int) $res->max_num_pages ) ); // phpcs:ignore ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/** Hromadná zmena stavu (cez update_status – poznámka v objednávke, e-maily podľa nastavení WooCommerce). */
add_action( 'admin_post_jord_bulk_status', function () {
	if ( ! current_user_can( 'edit_shop_orders' ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jord_bulk_status' );
	$to   = isset( $_POST['to_status'] ) ? sanitize_key( wp_unslash( $_POST['to_status'] ) ) : '';
	$ids  = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();
	$back = isset( $_POST['back'] ) ? esc_url_raw( wp_unslash( $_POST['back'] ) ) : admin_url( 'admin.php?page=j-orders' );
	$n    = 0;
	$raw  = isset( $_POST['to_status'] ) ? sanitize_text_field( wp_unslash( $_POST['to_status'] ) ) : '';
	if ( in_array( $raw, array( 'jinv:fulfil', 'jinv:invoice' ), true ) ) {
		if ( ! jord_jinv() || jord_wf() || ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Faktúry teraz vystaviť nemožno (J-Fakturácia nie je zapnutá alebo beží Woo Faktura).' );
		}
		$inv = 0;
		$err = 0;
		$fs  = jinv_fulfil_status();
		foreach ( $ids as $id ) {
			$o = wc_get_order( $id );
			if ( ! $o || 'shop_order' !== $o->get_type() || ! current_user_can( 'edit_shop_order', $id ) || $o->has_status( array( 'cancelled', 'failed', 'refunded', 'checkout-draft', 'pending' ) ) ) {
				continue;
			}
			$had = (bool) jinv_by_order( $o->get_id(), 'issued' );
			if ( 'jinv:fulfil' === $raw && ! $o->has_status( $fs ) ) {
				$o->update_status( $fs, 'J-Objednávky: hromadne vybavené.', true ); // automatický režim môže faktúru vystaviť už tu
				$n++;
			}
			if ( $had ) {
				continue;
			}
			$r = jinv_by_order( $o->get_id(), 'issued' ) ? true : jinv_create_doc( $o, 'issued', array( 'send' => false ) );
			if ( is_wp_error( $r ) ) {
				$err++;
				$o->add_order_note( 'J-Fakturácia: faktúra sa nevystavila – ' . $r->get_error_message() );
			} else {
				$inv++;
			}
		}
		$args = array( 'jord_inv' => $inv, 'jord_err' => $err );
		if ( 'jinv:fulfil' === $raw ) {
			$args['jord_msg'] = $n;
		}
		wp_safe_redirect( add_query_arg( $args, remove_query_arg( array( 'jord_msg', 'jord_inv', 'jord_err', 'jinv_msg' ), $back ) ) );
		exit;
	}
	if ( isset( wc_get_order_statuses()[ $to ] ) ) {
		foreach ( $ids as $id ) {
			$o = wc_get_order( $id );
			if ( $o && 'shop_order' === $o->get_type() && current_user_can( 'edit_shop_order', $id ) && $o->get_status() !== preg_replace( '/^wc-/', '', $to ) ) {
				$o->update_status( preg_replace( '/^wc-/', '', $to ), 'J-Objednávky: hromadná zmena stavu.', true );
				$n++;
			}
		}
	}
	wp_safe_redirect( add_query_arg( 'jord_msg', $n, remove_query_arg( array( 'jord_msg', 'jord_inv', 'jord_err', 'jinv_msg' ), $back ) ) );
	exit;
} );
