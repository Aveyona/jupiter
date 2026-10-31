<?php
/** Administrácia J-Kupónov: Kupóny (evidencia) a Nový kupón. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_menu_page( 'J-Kupóny', 'J-Kupóny', JKP_CAP, 'j-kupony', 'jkp_page_list', 'dashicons-tickets-alt', 58 );
	add_submenu_page( 'j-kupony', 'Kupóny – J-Kupóny', 'Kupóny', JKP_CAP, 'j-kupony', 'jkp_page_list' );
	add_submenu_page( 'j-kupony', 'Nový kupón – J-Kupóny', 'Nový kupón', JKP_CAP, 'j-kupony-new', 'jkp_page_new' );
} );

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
		echo '</div>';
	}
}

function jkp_msg( $text, $type = 'success', $codes = array() ) {
	set_transient( 'jkp_msg_' . get_current_user_id(), array( 'text' => $text, 'type' => $type, 'codes' => $codes ), 120 );
}

/* ---------- Akcie ---------- */

add_action( 'admin_post_jkp_create', function () {
	if ( ! current_user_can( JKP_CAP ) ) {
		wp_die( 'Nedostatočné oprávnenie.' );
	}
	check_admin_referer( 'jkp_create' );
	$p     = wp_unslash( $_POST );
	$count = max( 1, min( 500, (int) ( $p['count'] ?? 1 ) ) );
	$args  = array(
		'type'          => sanitize_key( $p['type'] ?? 'percent' ),
		'amount'        => sanitize_text_field( $p['amount'] ?? '' ),
		'expires'       => sanitize_text_field( $p['expires'] ?? '' ),
		'limit'         => (int) ( $p['limit'] ?? 1 ),
		'per_user'      => (int) ( $p['per_user'] ?? 0 ),
		'min'           => sanitize_text_field( $p['min'] ?? '' ),
		'free_shipping' => ! empty( $p['free_shipping'] ),
		'email'         => sanitize_text_field( $p['email'] ?? '' ),
		'note'          => (string) ( $p['note'] ?? '' ),
		'batch'         => $count > 1 ? current_time( 'Y-m-d H:i' ) : '',
	);
	$codes = array();
	$err   = '';
	for ( $i = 0; $i < $count; $i++ ) {
		$code = ( 1 === $count && '' !== trim( (string) ( $p['code'] ?? '' ) ) ) ? (string) $p['code'] : jkp_generate_code( $codes );
		if ( '' === $code ) {
			$err = 'Došli voľné kódy – zvýšte dĺžku kódu.';
			break;
		}
		$r = jkp_create_coupon( array( 'code' => $code ) + $args );
		if ( is_wp_error( $r ) ) {
			$err = $r->get_error_message();
			break;
		}
		$codes[] = strtoupper( $code );
	}
	if ( $err && ! $codes ) {
		jkp_msg( $err, 'error' );
		set_transient( 'jkp_form_' . get_current_user_id(), $p, 300 );
		wp_safe_redirect( admin_url( 'admin.php?page=j-kupony-new' ) );
		exit;
	}
	$text = 1 === count( $codes ) ? 'Kupón <strong>' . esc_html( $codes[0] ) . '</strong> je vytvorený.' : 'Vytvorených kupónov: <strong>' . count( $codes ) . '</strong>.';
	if ( $err ) {
		$text .= ' ' . esc_html( $err );
	}
	jkp_msg( $text, $err ? 'warning' : 'success', $codes );
	wp_safe_redirect( admin_url( 'admin.php?page=j-kupony' ) );
	exit;
} );

add_action( 'admin_post_jkp_toggle', function () {
	$id = (int) ( $_GET['id'] ?? 0 ); // phpcs:ignore
	if ( ! current_user_can( JKP_CAP ) || 'shop_coupon' !== get_post_type( $id ) ) {
		wp_die( 'Nedostatočné oprávnenie.' );
	}
	check_admin_referer( 'jkp_toggle_' . $id );
	$on = 'publish' !== get_post_status( $id );
	wp_update_post( array( 'ID' => $id, 'post_status' => $on ? 'publish' : 'draft' ) );
	jkp_msg( 'Kupón ' . esc_html( strtoupper( get_the_title( $id ) ) ) . ( $on ? ' je zapnutý.' : ' je vypnutý – v pokladni sa už nedá použiť.' ) );
	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=j-kupony' ) );
	exit;
} );

// Generovanie kódu pre tlačidlo „Vygenerovať“ vo formulári.
add_action( 'wp_ajax_jkp_gen', function () {
	check_ajax_referer( 'jkp_gen' );
	if ( ! current_user_can( JKP_CAP ) ) {
		wp_send_json_error();
	}
	wp_send_json_success( jkp_generate_code() );
} );

/* ---------- Stránka: Kupóny ---------- */

function jkp_page_list() {
	if ( ! current_user_can( JKP_CAP ) ) {
		return;
	}
	jkp_head( 'Kupóny', ' <a href="' . esc_url( admin_url( 'admin.php?page=j-kupony-new' ) ) . '" class="page-title-action">Nový kupón</a>' );
	$f   = sanitize_key( $_GET['f'] ?? '' ); // phpcs:ignore
	$s   = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ); // phpcs:ignore
	$ids = get_posts( array( 'post_type' => 'shop_coupon', 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'numberposts' => 1000, 'fields' => 'ids', 'orderby' => 'date', 'order' => 'DESC', 's' => $s ) );
	$all = array();
	$cnt = array( '' => 0, 'active' => 0, 'off' => 0, 'expired' => 0, 'used' => 0 );
	foreach ( $ids as $id ) {
		$c  = new WC_Coupon( $id );
		$st = jkp_status( $c );
		$cnt['']++;
		$cnt[ $st ]++;
		if ( '' === $f || $f === $st ) {
			$all[] = array( $c, $st );
		}
	}
	$tabs = array( '' => 'Všetky', 'active' => 'Platné', 'used' => 'Vyčerpané', 'expired' => 'Po platnosti', 'off' => 'Vypnuté' );
	echo '<ul class="subsubsub">';
	$i = 0;
	foreach ( $tabs as $k => $label ) {
		$url = add_query_arg( array( 'page' => 'j-kupony', 'f' => $k, 's' => $s ), admin_url( 'admin.php' ) );
		echo '<li>' . ( $i++ ? ' | ' : '' ) . '<a href="' . esc_url( $url ) . '"' . ( $f === $k ? ' class="current"' : '' ) . '>' . esc_html( $label ) . ' <span class="count">(' . (int) $cnt[ $k ] . ')</span></a></li>';
	}
	echo '</ul>';
	?>
	<form method="get" style="float:right;margin:6px 0 10px"><input type="hidden" name="page" value="j-kupony"><input type="hidden" name="f" value="<?php echo esc_attr( $f ); ?>">
		<input type="search" name="s" value="<?php echo esc_attr( $s ); ?>" placeholder="Hľadať kód"> <button class="button">Hľadať</button>
	</form>
	<table class="widefat striped" style="clear:both">
		<thead><tr><th>Kód</th><th>Zľava</th><th>Stav</th><th>Platí do</th><th class="num">Použitý</th><th>Objednávky</th><th>Poznámka</th><th>Vytvorený</th><th></th></tr></thead>
		<tbody>
		<?php if ( ! $all ) : ?><tr><td colspan="9">Žiadne kupóny. <a href="<?php echo esc_url( admin_url( 'admin.php?page=j-kupony-new' ) ); ?>">Vytvoriť nový kupón</a></td></tr><?php endif; ?>
		<?php
		foreach ( $all as $row ) :
			list( $c, $st ) = $row;
			$exp    = $c->get_date_expires();
			$limit  = $c->get_usage_limit();
			$orders = $c->get_usage_count() ? jkp_coupon_orders( $c->get_code(), 3 ) : array();
			$cond   = array();
			if ( (float) $c->get_minimum_amount() > 0 ) {
				$cond[] = 'od ' . wp_strip_all_tags( wc_price( $c->get_minimum_amount() ) );
			}
			if ( $c->get_email_restrictions() ) {
				$cond[] = 'len pre ' . implode( ', ', $c->get_email_restrictions() );
			}
			if ( $c->get_usage_limit_per_user() ) {
				$cond[] = $c->get_usage_limit_per_user() . '× na zákazníka';
			}
			$toggle = wp_nonce_url( admin_url( 'admin-post.php?action=jkp_toggle&id=' . $c->get_id() ), 'jkp_toggle_' . $c->get_id() );
			?>
			<tr>
				<td><code style="font-size:15px;font-weight:700;letter-spacing:.06em"><?php echo esc_html( strtoupper( $c->get_code() ) ); ?></code></td>
				<td><?php echo esc_html( jkp_discount_text( $c ) ); ?><?php echo $cond ? '<br><small style="color:#646970">' . esc_html( implode( ' · ', $cond ) ) . '</small>' : ''; ?></td>
				<td><?php echo jkp_status_label( $st ); // phpcs:ignore ?></td>
				<td><?php echo $exp ? esc_html( $exp->date_i18n( 'j. n. Y' ) ) : '<span style="color:#646970">bez obmedzenia</span>'; ?></td>
				<td class="num"><?php echo (int) $c->get_usage_count(); ?> / <?php echo $limit ? (int) $limit : '∞'; ?></td>
				<td><?php
				foreach ( $orders as $o ) {
					echo '<a href="' . esc_url( $o->get_edit_order_url() ) . '">#' . esc_html( $o->get_order_number() ) . '</a> ';
				}
				echo ( ! $orders ? '<span style="color:#646970">–</span>' : '' );
				?></td>
				<td><?php echo esc_html( $c->get_description() ); ?></td>
				<td><?php echo esc_html( $c->get_date_created() ? $c->get_date_created()->date_i18n( 'j. n. Y' ) : '' ); ?></td>
				<td style="white-space:nowrap"><a class="button button-small" href="<?php echo esc_url( $toggle ); ?>"><?php echo 'off' === $st ? 'Zapnúť' : 'Vypnúť'; ?></a> <a class="button button-small" href="<?php echo esc_url( get_edit_post_link( $c->get_id() ) ); ?>">Upraviť</a></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<p class="description">Kupóny sú bežné kupóny WooCommerce – zákazník zadá kód v košíku alebo v pokladni (na veľkosti písmen nezáleží). Podrobné nastavenia (produkty, kategórie) nájdete pod tlačidlom Upraviť.</p>
	</div>
	<?php
}

/* ---------- Stránka: Nový kupón ---------- */

function jkp_page_new() {
	if ( ! current_user_can( JKP_CAP ) ) {
		return;
	}
	jkp_head( 'Nový kupón' );
	$v = get_transient( 'jkp_form_' . get_current_user_id() );
	delete_transient( 'jkp_form_' . get_current_user_id() );
	$v = is_array( $v ) ? $v : array();
	$g = function ( $k, $d = '' ) use ( $v ) {
		return isset( $v[ $k ] ) ? (string) $v[ $k ] : $d;
	};
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:760px">
		<input type="hidden" name="action" value="jkp_create">
		<?php wp_nonce_field( 'jkp_create' ); ?>
		<table class="form-table" role="presentation">
			<tr><th scope="row"><label for="jkp-count">Počet kupónov</label></th><td><input id="jkp-count" type="number" name="count" min="1" max="500" value="<?php echo esc_attr( $g( 'count', '1' ) ); ?>" class="small-text"> <span class="description">Viac kupónov naraz – každý dostane vlastný kód.</span></td></tr>
			<tr id="jkp-code-row"><th scope="row"><label for="jkp-code">Kód</label></th><td>
				<input id="jkp-code" type="text" name="code" value="<?php echo esc_attr( $g( 'code', jkp_generate_code() ) ); ?>" maxlength="30" style="width:160px;font:700 18px/1.4 monospace;letter-spacing:.08em;text-transform:uppercase">
				<button type="button" class="button" id="jkp-gen">Vygenerovať iný</button>
				<p class="description">Kód sa vygeneruje automaticky (<?php echo (int) jkp_code_length(); ?> znaky, bez zameniteľných znakov 0/O a 1/I). Môžete ho aj prepísať vlastným, napr. LETO10.</p>
			</td></tr>
			<tr><th scope="row">Zľava</th><td>
				<input type="text" name="amount" value="<?php echo esc_attr( $g( 'amount', '10' ) ); ?>" class="small-text" required inputmode="decimal">
				<select name="type">
					<option value="percent" <?php selected( $g( 'type', 'percent' ), 'percent' ); ?>>% z košíka</option>
					<option value="fixed_cart" <?php selected( $g( 'type' ), 'fixed_cart' ); ?>>€ z košíka</option>
				</select>
				<label style="margin-left:12px"><input type="checkbox" name="free_shipping" value="1" <?php checked( $g( 'free_shipping' ), '1' ); ?>> aj doprava zadarmo</label>
			</td></tr>
			<tr><th scope="row"><label for="jkp-exp">Platí do</label></th><td><input id="jkp-exp" type="date" name="expires" value="<?php echo esc_attr( $g( 'expires' ) ); ?>"> <span class="description">Prázdne = bez obmedzenia.</span></td></tr>
			<tr><th scope="row"><label for="jkp-limit">Počet použití</label></th><td><input id="jkp-limit" type="number" min="0" name="limit" value="<?php echo esc_attr( $g( 'limit', '1' ) ); ?>" class="small-text"> <span class="description">Koľkokrát sa dá kód použiť spolu (0 = neobmedzene).</span></td></tr>
			<tr><th scope="row"><label for="jkp-pu">Na jedného zákazníka</label></th><td><input id="jkp-pu" type="number" min="0" name="per_user" value="<?php echo esc_attr( $g( 'per_user', '0' ) ); ?>" class="small-text"> <span class="description">0 = bez obmedzenia.</span></td></tr>
			<tr><th scope="row"><label for="jkp-min">Minimálny nákup</label></th><td><input id="jkp-min" type="text" name="min" value="<?php echo esc_attr( $g( 'min' ) ); ?>" class="small-text" inputmode="decimal"> € <span class="description">Prázdne = bez minima.</span></td></tr>
			<tr><th scope="row"><label for="jkp-mail">Len pre e-mail</label></th><td><input id="jkp-mail" type="text" name="email" value="<?php echo esc_attr( $g( 'email' ) ); ?>" class="regular-text" placeholder="zakaznik@example.sk"> <span class="description">Nepovinné – kód platí len pre tieto e-maily.</span></td></tr>
			<tr><th scope="row"><label for="jkp-note">Poznámka</label></th><td><textarea id="jkp-note" name="note" rows="2" class="large-text" placeholder="napr. Pre účastníkov podujatia, Facebook súťaž…"><?php echo esc_textarea( $g( 'note' ) ); ?></textarea></td></tr>
		</table>
		<?php submit_button( 'Vytvoriť kupón' ); ?>
	</form>
	<script>
	(function () {
		var cnt = document.getElementById('jkp-count'), row = document.getElementById('jkp-code-row'), btn = document.getElementById('jkp-gen'), code = document.getElementById('jkp-code');
		function sync() { var many = parseInt(cnt.value, 10) > 1; row.style.opacity = many ? .45 : 1; code.disabled = many; }
		cnt.addEventListener('input', sync); sync();
		btn.addEventListener('click', function () {
			btn.disabled = true;
			fetch(ajaxurl + '?action=jkp_gen&_ajax_nonce=<?php echo esc_js( wp_create_nonce( 'jkp_gen' ) ); ?>', { credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (r) { if (r.success && r.data) { code.value = r.data; } })
				.finally(function () { btn.disabled = false; });
		});
	})();
	</script>
	</div>
	<?php
}
