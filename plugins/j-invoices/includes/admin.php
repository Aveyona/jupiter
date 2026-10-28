<?php
/** Administrácia J-Invoices: zoznam vydaných faktúr, náhľad faktúry, nastavenia. Menu je pod Marketingom (J-Help ho zaradí). */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_menu_page( 'J-Invoices', 'J-Invoices', 'manage_woocommerce', 'j-invoices', 'jinv_render_admin', 'dashicons-media-spreadsheet', 58 );
	add_submenu_page( 'j-invoices', 'Vydané faktúry', 'Vydané faktúry', 'manage_woocommerce', 'j-invoices', 'jinv_render_admin' );
	add_submenu_page( 'j-invoices', 'Nastavenia faktúr', 'Nastavenia', 'manage_woocommerce', 'j-invoices&tab=settings', '__return_null' );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( false !== strpos( (string) $hook, 'j-invoices' ) ) {
		wp_enqueue_style( 'j-invoices-admin', JINV_URL . 'assets/admin.css', array(), JINV_VERSION );
		wp_add_inline_style( 'j-invoices-admin', jinv_invoice_css() );
	}
} );

function jinv_admin_url( $args = array() ) {
	return add_query_arg( $args, admin_url( 'admin.php?page=j-invoices' ) );
}

// Akcie s faktúrou: uhradená / storno / vystaviť k objednávke.
add_action( 'admin_post_jinv_action', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$do = isset( $_REQUEST['do'] ) ? sanitize_key( $_REQUEST['do'] ) : ''; // phpcs:ignore
	$id = isset( $_REQUEST['id'] ) ? (int) $_REQUEST['id'] : 0; // phpcs:ignore
	check_admin_referer( 'jinv_' . $do . '_' . $id );
	$msg = '';
	if ( 'create' === $do ) {
		$r   = jinv_create_from_order( $id );
		$msg = is_wp_error( $r ) ? 'err' : 'created';
		$id  = is_wp_error( $r ) ? 0 : (int) $r;
	} elseif ( 'paid' === $do ) {
		jinv_update( $id, array( 'status' => 'paid', 'paid_at' => current_time( 'mysql' ) ) );
		$msg = 'paid';
	} elseif ( 'unpaid' === $do ) {
		jinv_update( $id, array( 'status' => 'issued', 'paid_at' => null ) );
		$msg = 'unpaid';
	} elseif ( 'cancel' === $do ) {
		$inv = jinv_get( $id );
		jinv_update( $id, array( 'status' => 'cancelled' ) );
		if ( $inv && $inv['order_id'] && ( $o = wc_get_order( $inv['order_id'] ) ) ) {
			$o->delete_meta_data( '_jinv_invoice' );
			$o->save();
			$o->add_order_note( sprintf( 'J-Invoices: faktúra č. %s bola stornovaná.', $inv['number'] ) );
		}
		$msg = 'cancelled';
	}
	$back = isset( $_REQUEST['back'] ) ? esc_url_raw( wp_unslash( $_REQUEST['back'] ) ) : ''; // phpcs:ignore
	wp_safe_redirect( $back ? add_query_arg( 'jinv_msg', $msg, $back ) : jinv_admin_url( array( 'view' => $id, 'jinv_msg' => $msg ) ) );
	exit;
} );

function jinv_action_url( $do, $id, $back = '' ) {
	$url = admin_url( 'admin-post.php?action=jinv_action&do=' . $do . '&id=' . (int) $id . ( $back ? '&back=' . rawurlencode( $back ) : '' ) );
	return wp_nonce_url( $url, 'jinv_' . $do . '_' . (int) $id );
}

function jinv_status_badge( array $inv ) {
	$st = jinv_display_status( $inv );
	$l  = jinv_statuses();
	return '<span class="jinv-st jinv-st-' . esc_attr( $st ) . '">' . esc_html( isset( $l[ $st ] ) ? $l[ $st ] : $st ) . '</span>';
}

function jinv_render_admin() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$tab  = isset( $_GET['tab'] ) && 'settings' === $_GET['tab'] ? 'settings' : 'issued'; // phpcs:ignore
	$view = isset( $_GET['view'] ) ? (int) $_GET['view'] : 0; // phpcs:ignore
	$msgs = array(
		'created'   => 'Faktúra je vystavená.',
		'paid'      => 'Faktúra je označená ako uhradená.',
		'unpaid'    => 'Faktúra je znova neuhradená.',
		'cancelled' => 'Faktúra je stornovaná.',
		'err'       => 'Faktúru sa nepodarilo vystaviť.',
	);
	$msg  = isset( $_GET['jinv_msg'] ) ? sanitize_key( $_GET['jinv_msg'] ) : ''; // phpcs:ignore
	?>
	<div class="wrap jinv">
		<h1>J-Invoices <span class="jinv-ver">v<?php echo esc_html( JINV_VERSION ); ?></span></h1>
		<?php if ( isset( $msgs[ $msg ] ) ) : ?><div class="notice notice-<?php echo 'err' === $msg ? 'error' : 'success'; ?> is-dismissible"><p><?php echo esc_html( $msgs[ $msg ] ); ?></p></div><?php endif; ?>
		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore ?><div class="notice notice-success is-dismissible"><p>Nastavenia sú uložené.</p></div><?php endif; ?>
		<nav class="jinv-tabs">
			<a href="<?php echo esc_url( jinv_admin_url() ); ?>" class="<?php echo 'issued' === $tab ? 'is-active' : ''; ?>">Vydané faktúry</a>
			<a href="<?php echo esc_url( jinv_admin_url( array( 'tab' => 'settings' ) ) ); ?>" class="<?php echo 'settings' === $tab ? 'is-active' : ''; ?>">Nastavenia</a>
		</nav>
		<?php
		if ( 'settings' === $tab ) {
			jinv_render_settings();
		} elseif ( $view ) {
			jinv_render_view( $view );
		} else {
			jinv_render_list();
		}
		?>
	</div>
	<?php
}

function jinv_render_list() {
	$year = isset( $_GET['year'] ) ? (int) $_GET['year'] : 0; // phpcs:ignore
	$st   = isset( $_GET['st'] ) ? sanitize_key( $_GET['st'] ) : ''; // phpcs:ignore
	$q    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore
	$pg   = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 ); // phpcs:ignore
	$res  = jinv_list( array( 'year' => $year, 'status' => $st, 's' => $q, 'limit' => 50, 'offset' => ( $pg - 1 ) * 50 ) );
	$s    = jinv_settings();
	$miss = array();
	foreach ( array( 'company' => 'obchodné meno', 'ico' => 'IČO', 'iban' => 'IBAN' ) as $k => $l ) {
		if ( empty( $s[ $k ] ) ) {
			$miss[] = $l;
		}
	}
	?>
	<?php if ( $miss ) : ?><div class="notice notice-warning"><p>V nastaveniach chýba: <strong><?php echo esc_html( implode( ', ', $miss ) ); ?></strong>. <a href="<?php echo esc_url( jinv_admin_url( array( 'tab' => 'settings' ) ) ); ?>">Doplniť údaje dodávateľa →</a></p></div><?php endif; ?>
	<form method="get" class="jinv-filter">
		<input type="hidden" name="page" value="j-invoices">
		<input type="search" name="s" value="<?php echo esc_attr( $q ); ?>" placeholder="Číslo, odberateľ, e-mail">
		<select name="year"><option value="0">Všetky roky</option><?php for ( $y = (int) current_time( 'Y' ); $y >= (int) current_time( 'Y' ) - 5; $y-- ) : ?><option value="<?php echo (int) $y; ?>" <?php selected( $year, $y ); ?>><?php echo (int) $y; ?></option><?php endfor; ?></select>
		<select name="st"><option value="">Všetky stavy</option><?php foreach ( array( 'issued' => 'Vystavená', 'paid' => 'Uhradená', 'cancelled' => 'Stornovaná' ) as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $st, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
		<button class="button">Filtrovať</button>
	</form>
	<table class="widefat striped jinv-table">
		<thead><tr><th>Číslo</th><th>Odberateľ</th><th>Objednávka</th><th>Vystavená</th><th>Splatnosť</th><th class="n">Suma</th><th>Stav</th></tr></thead>
		<tbody>
		<?php if ( ! $res['rows'] ) : ?>
			<tr><td colspan="7">Zatiaľ žiadne faktúry. Vzniknú automaticky z objednávok (prevod na účet po zaplatení, dobierka a osobný odber po vybavení) alebo ich vystavíš v detaile objednávky.</td></tr>
		<?php endif; ?>
		<?php foreach ( $res['rows'] as $r ) : ?>
			<tr>
				<td><a href="<?php echo esc_url( jinv_admin_url( array( 'view' => $r['id'] ) ) ); ?>"><strong><?php echo esc_html( $r['number'] ); ?></strong></a></td>
				<td><?php echo esc_html( $r['customer_name'] ); ?><br><small><?php echo esc_html( $r['customer_email'] ); ?></small></td>
				<td><?php if ( $r['order_id'] && ( $o = wc_get_order( $r['order_id'] ) ) ) : ?><a href="<?php echo esc_url( $o->get_edit_order_url() ); ?>">#<?php echo esc_html( $o->get_order_number() ); ?></a><?php else : ?>—<?php endif; ?></td>
				<td><?php echo esc_html( jinv_date( $r['issue_date'] ) ); ?></td>
				<td><?php echo esc_html( jinv_date( $r['due_date'] ) ); ?></td>
				<td class="n"><?php echo esc_html( jinv_money( $r['total'], $r['currency'] ) ); ?></td>
				<td><?php echo jinv_status_badge( $r ); // phpcs:ignore ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php if ( $res['total'] > 50 ) : ?>
		<p class="jinv-pages"><?php echo paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $pg, 'total' => (int) ceil( $res['total'] / 50 ) ) ); // phpcs:ignore ?></p>
	<?php endif; ?>
	<?php
}

function jinv_render_view( $id ) {
	$inv = jinv_get( $id );
	if ( ! $inv ) {
		echo '<p>Faktúra neexistuje.</p>';
		return;
	}
	$back = jinv_admin_url( array( 'view' => $id ) );
	?>
	<p class="jinv-actions">
		<a class="button" href="<?php echo esc_url( jinv_admin_url() ); ?>">← Späť na zoznam</a>
		<button type="button" class="button button-primary" onclick="window.print()">Tlačiť / uložiť ako PDF</button>
		<?php if ( 'issued' === $inv['status'] ) : ?><a class="button" href="<?php echo esc_url( jinv_action_url( 'paid', $id, $back ) ); ?>">Označiť ako uhradenú</a><?php endif; ?>
		<?php if ( 'paid' === $inv['status'] ) : ?><a class="button" href="<?php echo esc_url( jinv_action_url( 'unpaid', $id, $back ) ); ?>">Zrušiť úhradu</a><?php endif; ?>
		<?php if ( 'cancelled' !== $inv['status'] ) : ?><a class="button jinv-danger" href="<?php echo esc_url( jinv_action_url( 'cancel', $id, $back ) ); ?>" onclick="return confirm('Stornovať faktúru <?php echo esc_js( $inv['number'] ); ?>? Číslo ostane obsadené.');">Stornovať</a><?php endif; ?>
		<?php echo jinv_status_badge( $inv ); // phpcs:ignore ?>
		<span class="jinv-note">Odoslanie zákazníkovi e-mailom (s PDF a QR kódom) pribudne v ďalšej verzii – vždy až po tvojom potvrdení.</span>
	</p>
	<?php
	echo jinv_render_invoice_html( $inv ); // phpcs:ignore
}

function jinv_render_settings() {
	$s = jinv_settings();
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="jinv-settings">
		<?php wp_nonce_field( 'jinv_settings' ); ?>
		<input type="hidden" name="action" value="jinv_save_settings">
		<?php foreach ( jinv_setting_fields() as $title => $fields ) : ?>
			<div class="jinv-panel">
				<h2><?php echo esc_html( $title ); ?></h2>
				<table class="form-table" role="presentation">
					<?php foreach ( $fields as $k => $f ) : ?>
						<tr>
							<th><label for="jinv-<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $f[0] ); ?></label></th>
							<td>
								<?php if ( 'checkbox' === $f[1] ) : ?>
									<label><input id="jinv-<?php echo esc_attr( $k ); ?>" type="checkbox" name="<?php echo esc_attr( $k ); ?>" value="1" <?php checked( ! empty( $s[ $k ] ) ); ?>> áno</label>
								<?php else : ?>
									<input id="jinv-<?php echo esc_attr( $k ); ?>" type="<?php echo esc_attr( $f[1] ); ?>" name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( (string) $s[ $k ] ); ?>" class="<?php echo 'number' === $f[1] ? 'small-text' : 'regular-text'; ?>">
								<?php endif; ?>
								<?php if ( $f[2] ) : ?><p class="description"><?php echo esc_html( $f[2] ); ?></p><?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
			</div>
		<?php endforeach; ?>
		<p class="description">Ukážka čísla ďalšej faktúry: <strong><?php echo esc_html( jinv_format_number( (int) current_time( 'Y' ), 1, $s['format'] ) ); ?></strong>. Zmena údajov platí pre nové faktúry, vystavené ostávajú nezmenené.</p>
		<?php submit_button( 'Uložiť nastavenia' ); ?>
	</form>
	<?php
}
