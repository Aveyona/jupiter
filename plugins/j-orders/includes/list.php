<?php
/**
 * Zoznam objednávok J-Orders – nadstavba nad WooCommerce (objednávky sa čítajú cez wc_get_orders, funguje s HPOS).
 * Stavy s počtami, vyhľadávanie (aj podľa čísla objednávky), filter platby a dátumu, stĺpce platba, doprava,
 * faktúra (J-Fakturácia) a hromadná zmena stavu. Úprava objednávky prebieha v štandardnom detaile WooCommerce.
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
	$store = WC_Data_Store::load( 'order' );
	if ( method_exists( $store, 'search_orders' ) ) {
		$ids = array_merge( $ids, array_map( 'absint', (array) $store->search_orders( $term ) ) );
	}
	return array_values( array_unique( array_filter( $ids ) ) );
}

function jord_render_list() {
	if ( ! current_user_can( 'edit_shop_orders' ) ) {
		return;
	}
	$statuses = wc_get_order_statuses();
	$st       = isset( $_GET['st'] ) ? sanitize_key( wp_unslash( $_GET['st'] ) ) : ''; // phpcs:ignore
	$st       = isset( $statuses[ $st ] ) ? $st : '';
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
		'status'   => $st ? array( $st ) : array_keys( $statuses ),
	);
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
	$res    = $empty ? (object) array( 'orders' => array(), 'total' => 0, 'max_num_pages' => 0 ) : wc_get_orders( $args );
	$orders = $res->orders;
	$keep   = array_filter( array( 'st' => $st, 's' => $q, 'pay' => $pay, 'from' => $from, 'to' => $to ) );
	$msg    = isset( $_GET['jord_msg'] ) ? (int) $_GET['jord_msg'] : -1; // phpcs:ignore
	?>
	<div class="wrap jord">
		<h1 class="wp-heading-inline">Objednávky <span class="jord-ver">J-Orders v<?php echo esc_html( JORD_VERSION ); ?></span></h1>
		<a class="page-title-action" href="<?php echo esc_url( admin_url( jord_hpos() ? 'admin.php?page=wc-orders&action=new' : 'post-new.php?post_type=shop_order' ) ); ?>">Pridať objednávku</a>
		<a class="page-title-action" href="<?php echo esc_url( admin_url( jord_hpos() ? 'admin.php?page=wc-orders' : 'edit.php?post_type=shop_order' ) ); ?>">Zoznam WooCommerce</a>
		<hr class="wp-header-end">
		<?php if ( $msg >= 0 ) : ?><div class="notice notice-success is-dismissible"><p>Stav zmenený pri <?php echo (int) $msg; ?> objednávkach.</p></div><?php endif; ?>

		<ul class="subsubsub jord-status">
			<li><a href="<?php echo esc_url( jord_list_url( array_diff_key( $keep, array( 'st' => 1 ) ) ) ); ?>" class="<?php echo '' === $st ? 'current' : ''; ?>">Všetky</a></li>
			<?php foreach ( $statuses as $key => $label ) : ?>
				<?php $count = (int) wc_orders_count( preg_replace( '/^wc-/', '', $key ) ); ?>
				<?php if ( ! $count && $st !== $key ) { continue; } ?>
				<li> | <a href="<?php echo esc_url( jord_list_url( array_merge( $keep, array( 'st' => $key ) ) ) ); ?>" class="<?php echo $st === $key ? 'current' : ''; ?>"><?php echo esc_html( $label ); ?> <span class="count">(<?php echo esc_html( number_format_i18n( $count ) ); ?>)</span></a></li>
			<?php endforeach; ?>
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
				<select name="to_status"><option value="">Zmeniť stav vybraných na…</option><?php foreach ( $statuses as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
				<button class="button" onclick="var s=this.form.to_status;if(!s.value||!this.form.querySelector('input[name=&quot;ids[]&quot;]:checked')){return false;}return confirm('Zmeniť stav vybraných objednávok na „'+s.options[s.selectedIndex].text+'“? Zákazníkom môžu odísť e-maily podľa nastavení WooCommerce.');">Použiť</button>
			</div>
			<table class="widefat striped jord-table">
				<thead><tr>
					<td class="check-column"><input type="checkbox" onclick="document.querySelectorAll('#jord-bulk input[name=&quot;ids[]&quot;]').forEach(c=>c.checked=this.checked)"></td>
					<th>Objednávka</th><th>Dátum</th><th>Stav</th><th>Zákazník</th><th>Platba</th><th>Doprava</th><th class="n">Suma</th><th>Faktúra</th>
				</tr></thead>
				<tbody>
				<?php if ( ! $orders ) : ?>
					<tr><td colspan="9">Žiadne objednávky<?php echo $keep ? ' pre zvolený filter' : ''; ?>.</td></tr>
				<?php endif; ?>
				<?php foreach ( $orders as $o ) : ?>
					<?php
					$name = trim( $o->get_formatted_billing_full_name() );
					$co   = $o->get_billing_company();
					$note = $o->get_customer_note();
					$inv  = function_exists( 'jinv_by_order' ) ? jinv_by_order( $o->get_id() ) : null;
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
						<td><?php if ( $inv ) : ?><a href="<?php echo esc_url( admin_url( 'admin.php?page=j-invoices&view=' . (int) $inv['id'] ) ); ?>"><?php echo esc_html( $inv['number'] ); ?></a><?php else : ?>—<?php endif; ?></td>
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
	if ( isset( wc_get_order_statuses()[ $to ] ) ) {
		foreach ( $ids as $id ) {
			$o = wc_get_order( $id );
			if ( $o && $o->get_status() !== preg_replace( '/^wc-/', '', $to ) ) {
				$o->update_status( preg_replace( '/^wc-/', '', $to ), 'J-Orders: hromadná zmena stavu.', true );
				$n++;
			}
		}
	}
	wp_safe_redirect( add_query_arg( 'jord_msg', $n, remove_query_arg( 'jord_msg', $back ) ) );
	exit;
} );
