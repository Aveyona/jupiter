<?php
/** Menu J-Objednávky a stránka Číslovanie (náhrada My Order Numbers). */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_menu_page( 'J-Objednávky', 'J-Objednávky', 'edit_shop_orders', 'j-orders', 'jord_render_list', 'dashicons-clipboard', 58 );
	add_submenu_page( 'j-orders', 'Objednávky', 'Objednávky', 'edit_shop_orders', 'j-orders', 'jord_render_list' );
	add_submenu_page( 'j-orders', 'Číslovanie objednávok', 'Číslovanie', 'manage_woocommerce', 'j-orders-numbers', 'jord_render_numbers' );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( false !== strpos( (string) $hook, 'j-orders' ) ) {
		wp_enqueue_style( 'j-orders', JORD_URL . 'assets/admin.css', array(), JORD_VERSION );
	}
} );

function jord_render_numbers() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$s     = jord_numbers_settings( true );
	$other = jord_other_numbering();
	$next  = jord_effective_last( $s ) + 1;
	while ( jord_number_exists( jord_format( $next, $s ) ) ) {
		$next++;
	}
	$last   = wc_get_orders( array( 'limit' => 1, 'type' => 'shop_order', 'orderby' => 'date', 'order' => 'DESC', 'status' => array_keys( jord_list_statuses() ) ) );
	$last   = $last ? $last[0] : null;
	$hist   = array_reverse( (array) get_option( JORD_HISTORY, array() ) );
	$notice = isset( $_GET['jord_notice'] ) ? sanitize_key( $_GET['jord_notice'] ) : ''; // phpcs:ignore
	?>
	<div class="wrap jord">
		<h1>Číslovanie objednávok <span class="jord-ver">J-Objednávky v<?php echo esc_html( JORD_VERSION ); ?></span></h1>
		<?php if ( 'saved' === $notice ) : ?><div class="notice notice-success is-dismissible"><p>Nastavenie je uložené.</p></div><?php endif; ?>
		<?php if ( 'invalid' === $notice ) : ?><div class="notice notice-error is-dismissible"><p>Nastavenie nie je uložené – skontrolujte hodnoty.</p></div><?php endif; ?>
		<?php if ( $other ) : ?>
			<div class="notice notice-warning"><p><strong>Čísla objednávok zatiaľ prideľuje doplnok <?php echo esc_html( $other ); ?>.</strong> J-Objednávky ich len zobrazuje a vyhľadáva. Po vypnutí doplnku <?php echo esc_html( $other ); ?> prevezme číslovanie J-Objednávky a nadviaže na jeho posledné číslo.</p></div>
		<?php endif; ?>

		<div class="jord-panel">
			<h2>Stav</h2>
			<table class="widefat striped jord-narrow"><tbody>
				<tr><th>Číslovanie J-Objednávky</th><td><?php echo $s['enabled'] ? ( $other ? 'zapnuté – čaká na vypnutie doplnku ' . esc_html( $other ) : '<strong>zapnuté</strong>' ) : 'vypnuté (objednávky majú čísla WooCommerce = ID)'; ?></td></tr>
				<tr><th>Posledná objednávka</th><td><?php if ( $last ) : ?><strong><?php echo esc_html( $last->get_order_number() ); ?></strong> – <a href="<?php echo esc_url( $last->get_edit_order_url() ); ?>">ID <?php echo (int) $last->get_id(); ?></a>, <?php echo esc_html( wc_format_datetime( $last->get_date_created(), 'j. n. Y H:i' ) ); ?>, <?php echo esc_html( wc_get_order_status_name( $last->get_status() ) ); ?><?php else : ?>—<?php endif; ?></td></tr>
				<tr><th>Posledné použité poradové číslo</th><td><?php echo esc_html( jord_format( $s['last'], $s ) ); ?> (<?php echo (int) $s['last']; ?>)</td></tr>
				<tr><th>Ďalšia objednávka dostane číslo</th><td><strong class="jord-big"><?php echo esc_html( $s['enabled'] ? jord_format( $next, $s ) : '—' ); ?></strong></td></tr>
			</tbody></table>
		</div>

		<div class="jord-panel">
			<h2>Upraviť číslovanie</h2>
			<p>Používa sa hlavne pri zmene roka (napr. prefix <code>22027</code> a posledné číslo <code>0</code> → ďalšia objednávka <code>220270001</code>). Ak by zvolené číslo už mala iná objednávka, automaticky sa preskočí.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="jord_save_numbers">
				<?php wp_nonce_field( 'jord_save_numbers' ); ?>
				<table class="form-table" role="presentation">
					<tr><th scope="row">Vlastné čísla objednávok</th><td><label><input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?>> zapnuté</label><p class="description">Vypnuté: nové objednávky majú číslo WooCommerce (ID). Už pridelené čísla ostávajú. Po zapnutí dostávajú vlastné číslo len nové objednávky – staršie si ponechajú číslo WooCommerce.</p></td></tr>
					<tr><th scope="row"><label for="jord-prefix">Prefix</label></th><td><input name="prefix" id="jord-prefix" type="text" class="regular-text" maxlength="20" value="<?php echo esc_attr( $s['prefix'] ); ?>"><p class="description">Písmená, číslice, „-“, „_“, „/“.</p></td></tr>
					<tr><th scope="row"><label for="jord-digits">Počet číslic poradového čísla</label></th><td><input name="digits" id="jord-digits" type="number" min="1" max="10" class="small-text" value="<?php echo (int) $s['digits']; ?>"></td></tr>
					<tr><th scope="row"><label for="jord-last">Posledné použité poradové číslo</label></th><td><input name="last" id="jord-last" type="number" min="0" step="1" value="<?php echo (int) $s['last']; ?>"><p class="description">Ďalšia objednávka dostane toto číslo + 1.</p></td></tr>
					<tr><th scope="row"><label for="jord-suffix">Prípona</label></th><td><input name="suffix" id="jord-suffix" type="text" class="regular-text" maxlength="20" value="<?php echo esc_attr( $s['suffix'] ); ?>"><p class="description">Nepovinné, zvyčajne prázdne.</p></td></tr>
				</table>
				<?php submit_button( 'Uložiť' ); ?>
			</form>
		</div>

		<?php if ( $hist ) : ?>
			<div class="jord-panel">
				<h2>História zmien</h2>
				<table class="widefat striped jord-narrow">
					<thead><tr><th>Dátum</th><th>Kto</th><th>Zmena</th></tr></thead>
					<tbody>
					<?php foreach ( array_slice( $hist, 0, 20 ) as $h ) : ?>
						<tr><td><?php echo esc_html( wp_date( 'j. n. Y H:i', (int) $h['time'] ) ); ?></td><td><?php echo esc_html( $h['user'] ); ?></td><td><?php echo esc_html( $h['text'] ); ?></td></tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

add_action( 'admin_post_jord_save_numbers', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jord_save_numbers' );
	$prefix = isset( $_POST['prefix'] ) ? trim( wp_unslash( $_POST['prefix'] ) ) : ''; // phpcs:ignore
	$suffix = isset( $_POST['suffix'] ) ? trim( wp_unslash( $_POST['suffix'] ) ) : ''; // phpcs:ignore
	$digits = isset( $_POST['digits'] ) ? (int) $_POST['digits'] : 0;
	$last   = isset( $_POST['last'] ) ? (int) $_POST['last'] : -1;
	$back   = admin_url( 'admin.php?page=j-orders-numbers' );
	$ok     = preg_match( '/^[A-Za-z0-9_\-\/]{0,20}$/', $prefix ) && preg_match( '/^[A-Za-z0-9_\-\/]{0,20}$/', $suffix ) && $digits >= 1 && $digits <= 10 && $last >= 0;
	if ( ! $ok ) {
		wp_safe_redirect( add_query_arg( 'jord_notice', 'invalid', $back ) );
		exit;
	}
	global $wpdb;
	$locked = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', JORD_LOCK ) );
	$old    = jord_numbers_settings( true );
	$new    = array(
		'enabled' => empty( $_POST['enabled'] ) ? 0 : 1,
		'prefix'  => $prefix,
		'suffix'  => $suffix,
		'digits'  => $digits,
		'last'    => $last,
		'since'   => (int) $old['since'],
	);
	if ( $new['enabled'] && empty( $old['enabled'] ) ) {
		$new['since'] = time(); // staršie objednávky si ponechajú číslo WooCommerce
	}
	update_option( JORD_OPTION, $new, false );
	update_option( 'jord_mon_synced', 1, false ); // ručné nastavenie má prednosť pred počítadlom My Order Numbers
	if ( $locked ) {
		$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', JORD_LOCK ) );
	}
	if ( $old != $new ) { // phpcs:ignore – porovnanie hodnôt bez ohľadu na typ
		jord_numbers_log( 'Ručná zmena', $old, $new );
	}
	wp_safe_redirect( add_query_arg( 'jord_notice', 'saved', $back ) );
	exit;
} );
