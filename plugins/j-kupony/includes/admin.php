<?php
/** Administrácia J-Kupónov: Kupóny (evidencia, detail, série), Nový kupón, Nastavenia. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_menu_page( 'J-Kupóny', 'J-Kupóny', JKP_CAP, 'j-kupony', 'jkp_page_list', 'dashicons-tickets-alt', 58.5 );
	add_submenu_page( 'j-kupony', 'Kupóny – J-Kupóny', 'Kupóny', JKP_CAP, 'j-kupony', 'jkp_page_list' );
	add_submenu_page( 'j-kupony', 'Nový kupón – J-Kupóny', 'Nový kupón', JKP_CAP, 'j-kupony-new', 'jkp_page_new' );
	add_submenu_page( 'j-kupony', 'Nastavenia – J-Kupóny', 'Nastavenia', JKP_CAP, 'j-kupony-settings', 'jkp_page_settings' );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( false === strpos( (string) $hook, 'j-kupony' ) ) {
		return;
	}
	if ( false !== strpos( (string) $hook, 'j-kupony-new' ) ) {
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_style( 'woocommerce_admin_styles' );
	}
	wp_register_style( 'j-kupony', false, array(), JKP_VERSION );
	wp_enqueue_style( 'j-kupony' );
	wp_add_inline_style( 'j-kupony', '
		.jkp-st{display:inline-block;padding:2px 9px;border-radius:10px;font-size:12px;white-space:nowrap}
		.jkp-code{font:700 15px/1.3 monospace;letter-spacing:.06em}
		.jkp-stats{display:flex;flex-wrap:wrap;gap:12px;margin:14px 0}
		.jkp-stats>div{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:10px 14px;min-width:150px}
		.jkp-stats b{display:block;font-size:20px;line-height:1.3}
		.jkp-stats span{color:#646970;font-size:12px}
		.jkp-muted{color:#646970}
		.jkp-void td{opacity:.55}
		.jkp-batches td,.jkp-batches th{padding:6px 10px}
	' );
} );

function jkp_url( array $args = array() ) {
	return add_query_arg( $args, admin_url( 'admin.php?page=j-kupony' ) );
}

function jkp_head( $title, $action = '' ) {
	echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html( $title ) . ' <span style="font-size:12px;color:#646970;font-weight:400">J-Kupóny v' . esc_html( JKP_VERSION ) . '</span></h1>' . $action . '<hr class="wp-header-end">'; // phpcs:ignore
	if ( 'yes' !== get_option( 'woocommerce_enable_coupons' ) ) {
		echo '<div class="notice notice-warning"><p>Kupóny sú vo WooCommerce vypnuté – zákazník nemá v košíku pole na zadanie kódu. Zapnete ich vo <a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=general' ) ) . '">WooCommerce → Nastavenia → Všeobecné → Povoliť používanie kupónov</a>.</p></div>';
	}
	$m = get_transient( 'jkp_msg_' . get_current_user_id() );
	if ( $m ) {
		delete_transient( 'jkp_msg_' . get_current_user_id() );
		echo '<div class="notice notice-' . esc_attr( $m['type'] ) . ' is-dismissible"><p>' . wp_kses_post( $m['text'] ) . '</p>';
		if ( ! empty( $m['codes'] ) ) {
			echo '<p><textarea readonly rows="' . esc_attr( min( 10, count( $m['codes'] ) ) ) . '" style="width:260px;font:600 15px/1.5 monospace" onclick="this.select()">' . esc_textarea( implode( "\n", $m['codes'] ) ) . '</textarea><br><small>Kliknite do poľa a skopírujte kódy (Ctrl+C).</small></p>';
		}
		if ( ! empty( $m['batch'] ) ) {
			echo '<p>' . jkp_batch_links( $m['batch'] ) . '</p>'; // phpcs:ignore
		}
		echo '</div>';
	}
}

function jkp_msg( $text, $type = 'success', $codes = array(), $batch = '' ) {
	set_transient( 'jkp_msg_' . get_current_user_id(), array( 'text' => $text, 'type' => $type, 'codes' => $codes, 'batch' => $batch ), 300 );
}

/** Odkazy pre sériu: zobraziť, CSV, tlač. */
function jkp_batch_links( $batch ) {
	$csv   = wp_nonce_url( admin_url( 'admin-post.php?action=jkp_batch_csv&batch=' . rawurlencode( $batch ) ), 'jkp_batch_' . $batch );
	$print = wp_nonce_url( admin_url( 'admin-post.php?action=jkp_batch_print&batch=' . rawurlencode( $batch ) ), 'jkp_batch_' . $batch );
	return '<a class="button button-small" href="' . esc_url( jkp_url( array( 'batch' => $batch ) ) ) . '">Zobraziť sériu</a> '
		. '<a class="button button-small" href="' . esc_url( $csv ) . '">Stiahnuť CSV</a> '
		. '<a class="button button-small" href="' . esc_url( $print ) . '" target="_blank" rel="noopener">Tlačiť kódy</a>';
}

function jkp_check( $nonce ) {
	if ( ! current_user_can( JKP_CAP ) ) {
		wp_die( 'Nedostatočné oprávnenie.' );
	}
	check_admin_referer( $nonce );
}

/* ---------------------------------------------------------------- Akcie */

add_action( 'admin_post_jkp_create', function () {
	jkp_check( 'jkp_create' );
	$p     = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification
	$count = max( 1, min( 500, (int) ( $p['count'] ?? 1 ) ) );
	$args  = jkp_validate( array(
		'type'          => sanitize_key( $p['type'] ?? 'percent' ),
		'amount'        => sanitize_text_field( $p['amount'] ?? '' ),
		'expires'       => sanitize_text_field( $p['expires'] ?? '' ),
		'limit'         => (int) ( $p['limit'] ?? 1 ),
		'per_user'      => (int) ( $p['per_user'] ?? 0 ),
		'min'           => sanitize_text_field( $p['min'] ?? '' ),
		'free_shipping' => ! empty( $p['free_shipping'] ),
		'email'         => sanitize_text_field( $p['email'] ?? '' ),
		'note'          => (string) ( $p['note'] ?? '' ),
		'combine'       => ! empty( $p['combine'] ),
		'cats'          => (array) ( $p['cats'] ?? array() ),
		'products'      => (array) ( $p['products'] ?? array() ),
		'from'          => (int) ( $p['from'] ?? 0 ),
	) );
	$back = function ( $err ) use ( $p ) {
		jkp_msg( esc_html( $err ), 'error' );
		set_transient( 'jkp_form_' . get_current_user_id(), $p, 600 );
		wp_safe_redirect( admin_url( 'admin.php?page=j-kupony-new' ) );
		exit;
	};
	if ( is_wp_error( $args ) ) {
		$back( $args->get_error_message() );
	}
	$prefix = jkp_code_prefix( $p['prefix'] ?? null );
	if ( $count > 1 ) {
		$args['batch']      = 'b' . current_time( 'YmdHis' ) . wp_rand( 100, 999 );
		$args['batch_name'] = sanitize_text_field( $p['batch_name'] ?? '' );
		if ( '' === $args['batch_name'] ) {
			$args['batch_name'] = 'Séria ' . current_time( 'j. n. Y H:i' );
		}
		if ( function_exists( 'wc_set_time_limit' ) ) {
			wc_set_time_limit( 300 );
		}
	}
	$codes = array();
	$err   = '';
	for ( $i = 0; $i < $count; $i++ ) {
		$code = ( 1 === $count && '' !== trim( (string) ( $p['code'] ?? '' ) ) ) ? (string) $p['code'] : jkp_generate_code( $codes, $count > 1, $prefix );
		if ( '' === $code ) {
			$err = 'Došli voľné kódy – zvýšte dĺžku kódu v Nastaveniach.';
			break;
		}
		$r = jkp_create_coupon( array( 'code' => $code ) + $args );
		if ( is_wp_error( $r ) ) {
			$err = $r->get_error_message();
			break;
		}
		$codes[] = strtoupper( $code );
		$last    = $r;
	}
	if ( ! $codes ) {
		$back( $err );
	}
	$text = 1 === count( $codes ) ? 'Kupón <strong>' . esc_html( $codes[0] ) . '</strong> je vytvorený.' : 'Vytvorených kupónov: <strong>' . count( $codes ) . '</strong> (' . esc_html( $args['batch_name'] ) . ').';
	if ( $err ) {
		$text .= ' ' . esc_html( $err );
	}
	jkp_msg( $text, $err ? 'warning' : 'success', $codes, $args['batch'] ?? '' );
	wp_safe_redirect( 1 === count( $codes ) ? jkp_url( array( 'id' => $last ) ) : jkp_url( array( 'batch' => $args['batch'] ) ) );
	exit;
} );

add_action( 'admin_post_jkp_toggle', function () {
	$id = (int) ( $_GET['id'] ?? 0 ); // phpcs:ignore
	if ( 'shop_coupon' !== get_post_type( $id ) ) {
		wp_die( 'Kupón neexistuje.' );
	}
	jkp_check( 'jkp_toggle_' . $id );
	$on = 'publish' !== get_post_status( $id );
	wp_update_post( array( 'ID' => $id, 'post_status' => $on ? 'publish' : 'draft' ) );
	jkp_msg( 'Kupón ' . esc_html( strtoupper( get_the_title( $id ) ) ) . ( $on ? ' je zapnutý.' : ' je vypnutý – v pokladni sa už nedá použiť.' ) );
	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : jkp_url() );
	exit;
} );

/** ID kupónov série. */
function jkp_batch_ids( $batch ) {
	return array_keys( array_filter( jkp_index(), fn( $r ) => $r['batch'] === $batch ) );
}

add_action( 'admin_post_jkp_batch_off', function () {
	$batch = sanitize_key( $_POST['batch'] ?? '' ); // phpcs:ignore
	jkp_check( 'jkp_batch_' . $batch );
	$on = ! empty( $_POST['on'] ); // phpcs:ignore
	$n  = 0;
	foreach ( jkp_batch_ids( $batch ) as $id ) {
		$st = get_post_status( $id );
		if ( $on && 'draft' === $st ) {
			wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
			++$n;
		} elseif ( ! $on && 'publish' === $st ) {
			wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
			++$n;
		}
	}
	jkp_msg( ( $on ? 'Zapnutých' : 'Vypnutých' ) . ' kupónov v sérii: <strong>' . $n . '</strong>.' );
	wp_safe_redirect( jkp_url( array( 'batch' => $batch ) ) );
	exit;
} );

add_action( 'admin_post_jkp_batch_csv', function () {
	$batch = sanitize_key( $_GET['batch'] ?? '' ); // phpcs:ignore
	jkp_check( 'jkp_batch_' . $batch );
	$idx  = jkp_index();
	$rows = array_filter( $idx, fn( $r ) => $r['batch'] === $batch );
	$name = $rows ? reset( $rows )['batch_name'] : $batch;
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="kupony-' . sanitize_file_name( remove_accents( $name ) ) . '.csv"' );
	$labels = array( 'active' => 'Platný', 'off' => 'Vypnutý', 'expired' => 'Po platnosti', 'used' => 'Vyčerpaný' );
	$out    = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" );
	fputcsv( $out, array( 'Kód', 'Zľava', 'Stav', 'Platí do', 'Použitý', 'Séria' ), ';' );
	foreach ( $rows as $r ) {
		$c = new WC_Coupon( $r['id'] );
		fputcsv( $out, array( $r['code'], jkp_discount_text( $c ), $labels[ $r['status'] ], $r['exp'] ? wp_date( 'j. n. Y', $r['exp'] ) : '', $r['count'], preg_replace( '/^[=+\-@\t\r]/', "'$0", $r['batch_name'] ) ), ';' );
	}
	fclose( $out );
	exit;
} );

add_action( 'admin_post_jkp_batch_print', function () {
	$batch = sanitize_key( $_GET['batch'] ?? '' ); // phpcs:ignore
	jkp_check( 'jkp_batch_' . $batch );
	$rows = array_filter( jkp_index(), fn( $r ) => $r['batch'] === $batch && 'active' === $r['status'] );
	$shop = get_bloginfo( 'name' );
	echo '<!doctype html><html lang="sk"><head><meta charset="utf-8"><title>Kupóny</title><style>
		body{font-family:Arial,sans-serif;margin:16px}.g{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
		.c{border:1px dashed #888;border-radius:6px;padding:12px;text-align:center;break-inside:avoid}
		.c b{display:block;font:700 22px/1.4 monospace;letter-spacing:.1em;margin:4px 0}.c small{color:#555}
		@media print{.np{display:none}}</style></head><body>';
	echo '<p class="np"><button onclick="window.print()">Tlačiť</button> Tlačia sa len platné kupóny série (' . count( $rows ) . ').</p><div class="g">';
	foreach ( $rows as $r ) {
		$c = new WC_Coupon( $r['id'] );
		echo '<div class="c"><small>' . esc_html( $shop ) . ' – zľavový kód</small><b>' . esc_html( $r['code'] ) . '</b>' . esc_html( jkp_discount_text( $c ) )
			. ( $r['exp'] ? '<br><small>platí do ' . esc_html( wp_date( 'j. n. Y', $r['exp'] ) ) . '</small>' : '' ) . '</div>';
	}
	echo '</div></body></html>';
	exit;
} );

add_action( 'admin_post_jkp_settings', function () {
	jkp_check( 'jkp_settings' );
	$p = wp_unslash( $_POST ); // phpcs:ignore
	update_option( 'jkp_settings', array(
		'len'       => max( 3, min( 20, (int) ( $p['len'] ?? 4 ) ) ),
		'len_batch' => max( 4, min( 20, (int) ( $p['len_batch'] ?? 6 ) ) ),
		'prefix'    => jkp_code_prefix( $p['prefix'] ?? '' ),
		'free_ship' => empty( $p['free_ship'] ) ? 0 : 1,
		'warn_days' => max( 1, min( 60, (int) ( $p['warn_days'] ?? 7 ) ) ),
	), false );
	jkp_msg( 'Nastavenia sú uložené.' );
	wp_safe_redirect( admin_url( 'admin.php?page=j-kupony-settings' ) );
	exit;
} );

// Generovanie kódu pre tlačidlo „Vygenerovať“ vo formulári.
add_action( 'wp_ajax_jkp_gen', function () {
	check_ajax_referer( 'jkp_gen' );
	if ( ! current_user_can( JKP_CAP ) ) {
		wp_send_json_error();
	}
	wp_send_json_success( jkp_generate_code( array(), false, isset( $_GET['prefix'] ) ? sanitize_text_field( wp_unslash( $_GET['prefix'] ) ) : null ) ); // phpcs:ignore
} );

/* ---------------------------------------------------------------- Stránka: Kupóny */

function jkp_page_list() {
	if ( ! current_user_can( JKP_CAP ) ) {
		return;
	}
	if ( ! empty( $_GET['id'] ) ) { // phpcs:ignore
		jkp_page_detail( (int) $_GET['id'] ); // phpcs:ignore
		return;
	}
	jkp_head( 'Kupóny', ' <a href="' . esc_url( admin_url( 'admin.php?page=j-kupony-new' ) ) . '" class="page-title-action">Nový kupón</a>' );
	$f     = sanitize_key( $_GET['f'] ?? '' ); // phpcs:ignore
	$s     = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ); // phpcs:ignore
	$batch = sanitize_key( $_GET['batch'] ?? '' ); // phpcs:ignore
	$pg    = max( 1, (int) ( $_GET['paged'] ?? 1 ) ); // phpcs:ignore
	$per   = 50;
	$idx   = jkp_index();

	if ( ! $batch && ! $s && ! $f && 1 === $pg ) {
		jkp_render_stats();
	}

	$cnt = array( '' => 0, 'active' => 0, 'off' => 0, 'expired' => 0, 'used' => 0 );
	$all = array();
	foreach ( $idx as $r ) {
		if ( $batch && $r['batch'] !== $batch ) {
			continue;
		}
		if ( '' !== $s && false === stripos( $r['code'], $s ) && false === stripos( $r['note'], $s ) && false === stripos( $r['batch_name'], $s ) ) {
			continue;
		}
		$cnt['']++;
		$cnt[ $r['status'] ]++;
		if ( '' === $f || $f === $r['status'] ) {
			$all[] = $r;
		}
	}

	if ( $batch ) {
		$b = jkp_batches( $idx )[ $batch ] ?? null;
		if ( $b ) {
			echo '<h2 style="margin-bottom:4px">' . esc_html( $b['name'] ) . ' <a class="jkp-muted" style="font-size:13px;font-weight:400" href="' . esc_url( jkp_url() ) . '">← všetky kupóny</a></h2>';
			echo '<p>Kupónov: <strong>' . (int) $b['total'] . '</strong> · platných ' . (int) $b['active'] . ' · použitých ' . (int) $b['used'] . '</p><p>' . jkp_batch_links( $batch ) . '</p>'; // phpcs:ignore
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:8px 0">';
			wp_nonce_field( 'jkp_batch_' . $batch );
			echo '<input type="hidden" name="action" value="jkp_batch_off"><input type="hidden" name="batch" value="' . esc_attr( $batch ) . '">';
			echo '<button class="button" onclick="return confirm(\'Vypnúť všetky kupóny série? V pokladni sa už nebudú dať použiť.\')">Vypnúť celú sériu</button> ';
			echo '<button class="button" name="on" value="1">Zapnúť vypnuté kupóny série</button></form>';
		}
	}

	$tabs = array( '' => 'Všetky', 'active' => 'Platné', 'used' => 'Vyčerpané', 'expired' => 'Po platnosti', 'off' => 'Vypnuté' );
	echo '<ul class="subsubsub">';
	$i = 0;
	foreach ( $tabs as $k => $label ) {
		$url = jkp_url( array_filter( array( 'f' => $k, 's' => $s, 'batch' => $batch ) ) );
		echo '<li>' . ( $i++ ? ' | ' : '' ) . '<a href="' . esc_url( $url ) . '"' . ( $f === $k ? ' class="current"' : '' ) . '>' . esc_html( $label ) . ' <span class="count">(' . (int) $cnt[ $k ] . ')</span></a></li>';
	}
	echo '</ul>';
	$pages = max( 1, (int) ceil( count( $all ) / $per ) );
	$pg    = min( $pg, $pages );
	$rows  = array_slice( $all, ( $pg - 1 ) * $per, $per );
	?>
	<form method="get" style="float:right;margin:6px 0 10px"><input type="hidden" name="page" value="j-kupony"><input type="hidden" name="f" value="<?php echo esc_attr( $f ); ?>"><input type="hidden" name="batch" value="<?php echo esc_attr( $batch ); ?>">
		<input type="search" name="s" value="<?php echo esc_attr( $s ); ?>" placeholder="Kód, poznámka, séria"> <button class="button">Hľadať</button>
	</form>
	<table class="widefat striped" style="clear:both">
		<thead><tr><th>Kód</th><th>Zľava a podmienky</th><th>Stav</th><th>Platí do</th><th class="num">Použitý</th><th>Séria / poznámka</th><th>Vytvorený</th><th></th></tr></thead>
		<tbody>
		<?php if ( ! $rows ) : ?><tr><td colspan="8">Žiadne kupóny. <a href="<?php echo esc_url( admin_url( 'admin.php?page=j-kupony-new' ) ); ?>">Vytvoriť nový kupón</a></td></tr><?php endif; ?>
		<?php
		foreach ( $rows as $r ) :
			$c      = new WC_Coupon( $r['id'] );
			$cond   = jkp_conditions( $c );
			$toggle = wp_nonce_url( admin_url( 'admin-post.php?action=jkp_toggle&id=' . $r['id'] ), 'jkp_toggle_' . $r['id'] );
			?>
			<tr>
				<td><a class="jkp-code" href="<?php echo esc_url( jkp_url( array( 'id' => $r['id'] ) ) ); ?>"><?php echo esc_html( $r['code'] ); ?></a></td>
				<td><?php echo esc_html( jkp_discount_text( $c ) ); ?><?php echo $cond ? '<br><small class="jkp-muted">' . esc_html( implode( ' · ', $cond ) ) . '</small>' : ''; ?></td>
				<td><?php echo jkp_status_label( $r['status'] ); // phpcs:ignore ?></td>
				<td><?php echo $r['exp'] ? esc_html( wp_date( 'j. n. Y', $r['exp'] ) ) : '<span class="jkp-muted">bez obmedzenia</span>'; ?></td>
				<td class="num"><?php echo (int) $r['count']; ?> / <?php echo $r['limit'] ? (int) $r['limit'] : '∞'; ?></td>
				<td><?php echo $r['batch'] ? '<a href="' . esc_url( jkp_url( array( 'batch' => $r['batch'] ) ) ) . '">' . esc_html( $r['batch_name'] ) . '</a><br>' : ''; ?><small><?php echo esc_html( $r['note'] ); ?></small></td>
				<td><?php echo esc_html( mysql2date( 'j. n. Y', $r['date'] ) ); ?></td>
				<td style="white-space:nowrap"><a class="button button-small" href="<?php echo esc_url( $toggle ); ?>"><?php echo 'off' === $r['status'] ? 'Zapnúť' : 'Vypnúť'; ?></a> <a class="button button-small" href="<?php echo esc_url( jkp_url( array( 'id' => $r['id'] ) ) ); ?>">Detail</a></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php
	if ( $pages > 1 ) {
		echo '<div class="tablenav"><div class="tablenav-pages">' . paginate_links( array( // phpcs:ignore
			'base'    => add_query_arg( 'paged', '%#%' ),
			'format'  => '',
			'current' => $pg,
			'total'   => $pages,
		) ) . '</div></div>';
	}
	$batches = jkp_batches( $idx );
	if ( $batches && ! $batch ) {
		echo '<h2>Série hromadných kupónov</h2><table class="widefat striped jkp-batches" style="max-width:820px"><thead><tr><th>Séria</th><th>Vytvorená</th><th class="num">Kupónov</th><th class="num">Platných</th><th class="num">Použitých</th><th></th></tr></thead><tbody>';
		foreach ( array_slice( $batches, 0, 20, true ) as $k => $b ) {
			echo '<tr><td><a href="' . esc_url( jkp_url( array( 'batch' => $k ) ) ) . '">' . esc_html( $b['name'] ) . '</a></td><td>' . esc_html( mysql2date( 'j. n. Y', $b['date'] ) ) . '</td><td class="num">' . (int) $b['total'] . '</td><td class="num">' . (int) $b['active'] . '</td><td class="num">' . (int) $b['used'] . '</td><td>' . jkp_batch_links( $k ) . '</td></tr>'; // phpcs:ignore
		}
		echo '</tbody></table>';
	}
	?>
	<p class="description">Kupóny sú bežné kupóny WooCommerce – zákazník zadá kód v košíku alebo v pokladni (na veľkosti písmen nezáleží).</p>
	</div>
	<?php
}

/** Podmienky kupónu ako krátke texty. */
function jkp_conditions( WC_Coupon $c ) {
	$cond = array();
	if ( (float) $c->get_minimum_amount() > 0 ) {
		$cond[] = 'od ' . wp_strip_all_tags( wc_price( $c->get_minimum_amount() ) );
	}
	if ( $c->get_email_restrictions() ) {
		$cond[] = 'len pre ' . implode( ', ', $c->get_email_restrictions() );
	}
	if ( $c->get_usage_limit_per_user() ) {
		$cond[] = $c->get_usage_limit_per_user() . '× na zákazníka';
	}
	if ( $c->get_product_categories() ) {
		$names  = array_filter( array_map( fn( $t ) => ( $x = get_term( $t, 'product_cat' ) ) && ! is_wp_error( $x ) ? $x->name : '', $c->get_product_categories() ) );
		$cond[] = 'kategórie: ' . implode( ', ', $names );
	}
	if ( $c->get_product_ids() ) {
		$cond[] = count( $c->get_product_ids() ) . ' vybraných produktov';
	}
	if ( ! $c->get_individual_use() ) {
		$cond[] = 'dá sa kombinovať';
	}
	return $cond;
}

/** Štatistika: tento a minulý mesiac, najpoužívanejšie kódy. */
function jkp_render_stats() {
	$m0   = current_time( 'Y-m-01' );
	$m1   = gmdate( 'Y-m-01', strtotime( $m0 . ' -1 month' ) );
	$e1   = gmdate( 'Y-m-t', strtotime( $m1 ) );
	$cur  = jkp_stats( $m0 );
	$prev = jkp_stats( $m1, $e1 );
	$mn   = fn( $ymd ) => wp_date( 'F', strtotime( $ymd . ' 12:00' ) );
	echo '<div class="jkp-stats">';
	echo '<div><span>Objednávky s kupónom – ' . esc_html( $mn( $m0 ) ) . '</span><b>' . (int) $cur['orders'] . '</b><span>' . esc_html( $mn( $m1 ) ) . ': ' . (int) $prev['orders'] . '</span></div>';
	echo '<div><span>Poskytnutá zľava – ' . esc_html( $mn( $m0 ) ) . '</span><b>' . wp_kses_post( wc_price( $cur['discount'] ) ) . '</b><span>' . esc_html( $mn( $m1 ) ) . ': ' . wp_kses_post( wc_price( $prev['discount'] ) ) . '</span></div>';
	echo '<div><span>Tržby z objednávok s kupónom</span><b>' . wp_kses_post( wc_price( $cur['revenue'] ) ) . '</b><span>' . esc_html( $mn( $m1 ) ) . ': ' . wp_kses_post( wc_price( $prev['revenue'] ) ) . '</span></div>';
	$top = array_slice( $cur['codes'] ? $cur['codes'] : $prev['codes'], 0, 5, true );
	if ( $top ) {
		echo '<div><span>Najpoužívanejšie kódy (' . esc_html( $cur['codes'] ? $mn( $m0 ) : $mn( $m1 ) ) . ')</span>';
		foreach ( $top as $code => $x ) {
			echo '<div><code>' . esc_html( $code ) . '</code> ' . (int) $x['uses'] . '× · ' . wp_kses_post( wc_price( $x['discount'] ) ) . '</div>';
		}
		echo '</div>';
	}
	echo '</div>';
}

/* ---------------------------------------------------------------- Detail kupónu */

function jkp_page_detail( $id ) {
	if ( 'shop_coupon' !== get_post_type( $id ) ) {
		jkp_head( 'Kupón' );
		echo '<p>Kupón neexistuje. <a href="' . esc_url( jkp_url() ) . '">Späť na kupóny</a></p></div>';
		return;
	}
	$c   = new WC_Coupon( $id );
	$st  = jkp_status( $c );
	$exp = $c->get_date_expires();
	jkp_head( 'Kupón ' . strtoupper( $c->get_code() ), ' <a href="' . esc_url( jkp_url() ) . '" class="page-title-action">← Všetky kupóny</a>' );
	$uses  = jkp_coupon_uses( $c->get_code(), 500 );
	$ok    = array_filter( $uses, fn( $u ) => ! $u['void'] );
	$disc  = array_sum( wp_list_pluck( $ok, 'discount' ) );
	$rev   = array_sum( array_map( fn( $u ) => (float) $u['order']->get_total() - (float) $u['order']->get_total_refunded(), $ok ) );
	$batch = (string) $c->get_meta( '_jkp_batch' );
	$toggle = wp_nonce_url( admin_url( 'admin-post.php?action=jkp_toggle&id=' . $id ), 'jkp_toggle_' . $id );
	?>
	<div class="jkp-stats">
		<div><span>Stav</span><b><?php echo jkp_status_label( $st ); // phpcs:ignore ?></b><span><?php echo $exp ? 'platí do ' . esc_html( $exp->date_i18n( 'j. n. Y' ) ) : 'bez obmedzenia platnosti'; ?></span></div>
		<div><span>Zľava</span><b><?php echo esc_html( jkp_discount_text( $c ) ); ?></b><span><?php echo esc_html( implode( ' · ', jkp_conditions( $c ) ) ); ?></span></div>
		<div><span>Použitý</span><b><?php echo (int) $c->get_usage_count(); ?> / <?php echo $c->get_usage_limit() ? (int) $c->get_usage_limit() : '∞'; ?></b><span>podľa WooCommerce</span></div>
		<div><span>Poskytnutá zľava</span><b><?php echo wp_kses_post( wc_price( $disc ) ); ?></b><span>bez zrušených objednávok</span></div>
		<div><span>Tržby s kódom</span><b><?php echo wp_kses_post( wc_price( $rev ) ); ?></b><span>po vrátení peňazí</span></div>
	</div>
	<p>
		<a class="button" href="<?php echo esc_url( $toggle ); ?>"><?php echo 'off' === $st ? 'Zapnúť' : 'Vypnúť'; ?></a>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=j-kupony-new&from=' . $id ) ); ?>">Duplikovať (nový kód, rovnaké podmienky)</a>
		<a class="button" href="<?php echo esc_url( get_edit_post_link( $id ) ); ?>">Upraviť vo WooCommerce</a>
	</p>
	<?php
	if ( $batch ) {
		$name = (string) $c->get_meta( '_jkp_batch_name' );
		echo '<p>Séria: <a href="' . esc_url( jkp_url( array( 'batch' => $batch ) ) ) . '">' . esc_html( $name ? $name : $batch ) . '</a></p>';
	}
	if ( $c->get_description() ) {
		echo '<p>Poznámka: ' . esc_html( $c->get_description() ) . '</p>';
	}
	?>
	<h2>Objednávky s kódom</h2>
	<table class="widefat striped" style="max-width:900px">
		<thead><tr><th>Objednávka</th><th>Dátum</th><th>Zákazník</th><th>Stav</th><th class="num">Zľava</th><th class="num">Spolu</th></tr></thead>
		<tbody>
		<?php if ( ! $uses ) : ?><tr><td colspan="6">Kód zatiaľ nebol použitý.</td></tr><?php endif; ?>
		<?php foreach ( $uses as $u ) : $o = $u['order']; ?>
			<tr class="<?php echo $u['void'] ? 'jkp-void' : ''; ?>">
				<td><a href="<?php echo esc_url( $o->get_edit_order_url() ); ?>">#<?php echo esc_html( $o->get_order_number() ); ?></a></td>
				<td><?php echo $o->get_date_created() ? esc_html( $o->get_date_created()->date_i18n( 'j. n. Y H:i' ) ) : ''; ?></td>
				<td><?php echo esc_html( trim( $o->get_formatted_billing_full_name() ) ); ?> <small class="jkp-muted"><?php echo esc_html( $o->get_billing_email() ); ?></small></td>
				<td><?php echo esc_html( wc_get_order_status_name( $o->get_status() ) ); ?><?php echo $u['void'] ? ' <small class="jkp-muted">(nezapočítaná)</small>' : ''; ?></td>
				<td class="num"><?php echo wp_kses_post( wc_price( $u['discount'] ) ); ?></td>
				<td class="num"><?php echo wp_kses_post( wc_price( $o->get_total() ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	</div>
	<?php
}

/* ---------------------------------------------------------------- Stránka: Nový kupón */

function jkp_page_new() {
	if ( ! current_user_can( JKP_CAP ) ) {
		return;
	}
	$v    = get_transient( 'jkp_form_' . get_current_user_id() );
	delete_transient( 'jkp_form_' . get_current_user_id() );
	$v    = is_array( $v ) ? $v : array();
	$from = (int) ( $_GET['from'] ?? 0 ); // phpcs:ignore
	if ( ! $v && $from && 'shop_coupon' === get_post_type( $from ) ) {
		$v = jkp_coupon_args( new WC_Coupon( $from ) );
	}
	jkp_head( $from ? 'Nový kupón podľa ' . strtoupper( get_the_title( $from ) ) : 'Nový kupón' );
	$g = function ( $k, $d = '' ) use ( $v ) {
		return isset( $v[ $k ] ) && ! is_array( $v[ $k ] ) ? (string) $v[ $k ] : $d;
	};
	$set    = jkp_settings();
	$cats   = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
	$selcat = array_map( 'intval', (array) ( $v['cats'] ?? array() ) );
	$selpr  = array_map( 'intval', (array) ( $v['products'] ?? array() ) );
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:820px">
		<input type="hidden" name="action" value="jkp_create">
		<input type="hidden" name="from" value="<?php echo (int) $from; ?>">
		<?php wp_nonce_field( 'jkp_create' ); ?>
		<table class="form-table" role="presentation">
			<tr><th scope="row"><label for="jkp-count">Počet kupónov</label></th><td><input id="jkp-count" type="number" name="count" min="1" max="500" value="<?php echo esc_attr( $g( 'count', '1' ) ); ?>" class="small-text"> <span class="description">Viac kupónov naraz (séria) – každý dostane vlastný kód s <?php echo (int) jkp_code_length( true ); ?> znakmi.</span></td></tr>
			<tr id="jkp-batch-row"><th scope="row"><label for="jkp-bn">Názov série</label></th><td><input id="jkp-bn" type="text" name="batch_name" value="<?php echo esc_attr( $g( 'batch_name' ) ); ?>" class="regular-text" placeholder="napr. Veľtrh 2026, Letáky do balíkov"></td></tr>
			<tr><th scope="row"><label for="jkp-pre">Predpona kódu</label></th><td><input id="jkp-pre" type="text" name="prefix" value="<?php echo esc_attr( $g( 'prefix', $set['prefix'] ) ); ?>" maxlength="15" style="width:120px;text-transform:uppercase" placeholder="napr. LETO-"> <span class="description">Nepovinné – pridá sa pred generovaný kód.</span></td></tr>
			<tr id="jkp-code-row"><th scope="row"><label for="jkp-code">Kód</label></th><td>
				<input id="jkp-code" type="text" name="code" value="<?php echo esc_attr( $g( 'code', jkp_generate_code( array(), false, $g( 'prefix', $set['prefix'] ) ) ) ); ?>" maxlength="30" style="width:200px;font:700 18px/1.4 monospace;letter-spacing:.08em;text-transform:uppercase">
				<button type="button" class="button" id="jkp-gen">Vygenerovať iný</button>
				<p class="description">Kód sa vygeneruje automaticky (<?php echo (int) jkp_code_length(); ?> znaky, bez zameniteľných znakov 0/O a 1/I). Môžete ho aj prepísať vlastným, napr. LETO10.</p>
			</td></tr>
			<tr><th scope="row">Zľava</th><td>
				<input type="text" name="amount" value="<?php echo esc_attr( $g( 'amount', '10' ) ); ?>" class="small-text" inputmode="decimal">
				<select name="type">
					<option value="percent" <?php selected( $g( 'type', 'percent' ), 'percent' ); ?>>% z košíka</option>
					<option value="fixed_cart" <?php selected( $g( 'type' ), 'fixed_cart' ); ?>>€ z košíka</option>
				</select>
				<label style="margin-left:12px"><input type="checkbox" name="free_shipping" value="1" <?php checked( $g( 'free_shipping' ), '1' ); ?>> aj doprava zadarmo</label>
				<p class="description">Len doprava zadarmo: zľavu nastavte na 0 a zaškrtnite dopravu zadarmo.<?php echo empty( $set['free_ship'] ) ? ' <strong>Pozor:</strong> v Nastaveniach je vypnuté vynulovanie dopravy – zadarmo bude len spôsob „Doprava zadarmo“ WooCommerce.' : ''; ?></p>
			</td></tr>
			<tr><th scope="row"><label for="jkp-exp">Platí do</label></th><td><input id="jkp-exp" type="date" name="expires" min="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" value="<?php echo esc_attr( $g( 'expires' ) ); ?>"> <span class="description">Vrátane tohto dňa. Prázdne = bez obmedzenia.</span></td></tr>
			<tr><th scope="row"><label for="jkp-limit">Počet použití</label></th><td><input id="jkp-limit" type="number" min="0" name="limit" value="<?php echo esc_attr( $g( 'limit', '1' ) ); ?>" class="small-text"> <span class="description">Koľkokrát sa dá každý kód použiť spolu (0 = neobmedzene).</span></td></tr>
			<tr><th scope="row"><label for="jkp-pu">Na jedného zákazníka</label></th><td><input id="jkp-pu" type="number" min="0" name="per_user" value="<?php echo esc_attr( $g( 'per_user', '0' ) ); ?>" class="small-text"> <span class="description">0 = bez obmedzenia.</span></td></tr>
			<tr><th scope="row"><label for="jkp-min">Minimálny nákup</label></th><td><input id="jkp-min" type="text" name="min" value="<?php echo esc_attr( $g( 'min' ) ); ?>" class="small-text" inputmode="decimal"> € <span class="description">Prázdne = bez minima.</span></td></tr>
			<tr><th scope="row"><label for="jkp-mail">Len pre e-mail</label></th><td><input id="jkp-mail" type="text" name="email" value="<?php echo esc_attr( $g( 'email' ) ); ?>" class="regular-text" placeholder="zakaznik@example.sk"> <span class="description">Nepovinné – kód platí len pre tieto e-maily (viac oddeľte čiarkou).</span></td></tr>
			<tr><th scope="row"><label for="jkp-cats">Len na kategórie</label></th><td>
				<select id="jkp-cats" name="cats[]" multiple class="wc-enhanced-select" style="width:420px" data-placeholder="všetky kategórie">
					<?php foreach ( is_array( $cats ) ? $cats : array() as $t ) : ?>
						<option value="<?php echo (int) $t->term_id; ?>" <?php selected( in_array( (int) $t->term_id, $selcat, true ) ); ?>><?php echo esc_html( $t->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</td></tr>
			<tr><th scope="row"><label for="jkp-prods">Len na produkty</label></th><td>
				<select id="jkp-prods" name="products[]" multiple class="wc-product-search" style="width:420px" data-placeholder="hľadať produkt…" data-action="woocommerce_json_search_products_and_variations">
					<?php
					foreach ( $selpr as $pid ) {
						$pr = wc_get_product( $pid );
						if ( $pr ) {
							echo '<option value="' . (int) $pid . '" selected>' . esc_html( wp_strip_all_tags( $pr->get_formatted_name() ) ) . '</option>';
						}
					}
					?>
				</select>
				<p class="description">Pri obmedzení na kategórie alebo produkty sa zľava v % počíta len z týchto položiek.</p>
			</td></tr>
			<tr><th scope="row">Kombinovanie</th><td><label><input type="checkbox" name="combine" value="1" <?php checked( $g( 'combine' ), '1' ); ?>> dá sa použiť spolu s inými kupónmi</label></td></tr>
			<tr><th scope="row"><label for="jkp-note">Poznámka</label></th><td><textarea id="jkp-note" name="note" rows="2" class="large-text" placeholder="napr. Pre účastníkov podujatia, Facebook súťaž…"><?php echo esc_textarea( $g( 'note' ) ); ?></textarea></td></tr>
		</table>
		<?php submit_button( 'Vytvoriť kupón' ); ?>
	</form>
	<script>
	(function () {
		var cnt = document.getElementById('jkp-count'), row = document.getElementById('jkp-code-row'), brow = document.getElementById('jkp-batch-row'), btn = document.getElementById('jkp-gen'), code = document.getElementById('jkp-code'), pre = document.getElementById('jkp-pre');
		function sync() { var many = parseInt(cnt.value, 10) > 1; row.style.opacity = many ? .45 : 1; code.disabled = many; brow.style.display = many ? '' : 'none'; }
		cnt.addEventListener('input', sync); sync();
		btn.addEventListener('click', function () {
			btn.disabled = true;
			fetch(ajaxurl + '?action=jkp_gen&prefix=' + encodeURIComponent(pre.value) + '&_ajax_nonce=<?php echo esc_js( wp_create_nonce( 'jkp_gen' ) ); ?>', { credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (r) { if (r.success && r.data) { code.value = r.data; } })
				.finally(function () { btn.disabled = false; });
		});
	})();
	</script>
	</div>
	<?php
}

/* ---------------------------------------------------------------- Stránka: Nastavenia */

function jkp_page_settings() {
	if ( ! current_user_can( JKP_CAP ) ) {
		return;
	}
	jkp_head( 'Nastavenia kupónov' );
	$s = jkp_settings();
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:820px">
		<input type="hidden" name="action" value="jkp_settings">
		<?php wp_nonce_field( 'jkp_settings' ); ?>
		<table class="form-table" role="presentation">
			<tr><th scope="row"><label for="jkp-len">Dĺžka kódu</label></th><td><input id="jkp-len" type="number" name="len" min="3" max="20" value="<?php echo (int) $s['len']; ?>" class="small-text"> znakov <span class="description">pri jednom kupóne (napr. kód pre konkrétneho zákazníka).</span></td></tr>
			<tr><th scope="row"><label for="jkp-lenb">Dĺžka kódu v sérii</label></th><td><input id="jkp-lenb" type="number" name="len_batch" min="4" max="20" value="<?php echo (int) $s['len_batch']; ?>" class="small-text"> znakov
				<p class="description">WooCommerce neobmedzuje počet pokusov v pokladni. Pri stovkách platných kódov by sa krátky kód dal uhádnuť skúšaním – pre série odporúčame aspoň 6 znakov.</p></td></tr>
			<tr><th scope="row"><label for="jkp-pref">Predvolená predpona</label></th><td><input id="jkp-pref" type="text" name="prefix" value="<?php echo esc_attr( $s['prefix'] ); ?>" maxlength="15" style="width:120px;text-transform:uppercase"> <span class="description">Napr. HED- → HED-K7MX. Prázdne = bez predpony.</span></td></tr>
			<tr><th scope="row">Doprava zadarmo</th><td><label><input type="checkbox" name="free_ship" value="1" <?php checked( ! empty( $s['free_ship'] ) ); ?>> kupón s dopravou zadarmo vynuluje cenu každého spôsobu dopravy (Packeta, pevná cena…)</label>
				<p class="description">Bez tejto voľby WooCommerce uplatní dopravu zadarmo z kupónu len pri spôsobe „Doprava zadarmo“ s podmienkou „platný kupón na dopravu zadarmo“.</p></td></tr>
			<tr><th scope="row"><label for="jkp-wd">Upozornenie na koniec platnosti</label></th><td><input id="jkp-wd" type="number" name="warn_days" min="1" max="60" value="<?php echo (int) $s['warn_days']; ?>" class="small-text"> dní vopred <span class="description">(dlaždica na nástenke Jupiter).</span></td></tr>
		</table>
		<?php submit_button( 'Uložiť nastavenia' ); ?>
	</form>
	</div>
	<?php
}
