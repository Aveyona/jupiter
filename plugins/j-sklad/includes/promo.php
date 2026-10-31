<?php
/**
 * Akcie a novinky: hromadné zľavy (akciová cena WooCommerce s dátumom od – do), názov akcie,
 * označenie „Novinka“ (ručne do dátumu alebo automaticky podľa dátumu pridania produktu),
 * štítky v obchode, krátke kódy [jsk_akcie] a [jsk_novinky] a filter obchodu ?jsk=akcie / ?jsk=novinky.
 *
 * Zľava sa ukladá ako bežná akciová cena WooCommerce (sale price + plánovanie), takže platí v košíku,
 * v objednávkach aj v iných doplnkoch. Pri variabilnom produkte sa nastaví všetkým variantom.
 * Meta: _jsk_promo (názov akcie), _jsk_new (yes = ručne novinka, no = nikdy novinka), _jsk_new_until (Y-m-d).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_submenu_page( 'j-sklad', 'Akcie a novinky', 'Akcie a novinky', JSK_CAP, 'j-sklad-promo', 'jsk_render_promo', 2 );
}, 11 );

function jsk_promo_defaults() {
	return array(
		'promo_badges'   => 1,
		'promo_pct'      => 1,
		'promo_text'     => 'Akcia',
		'new_text'       => 'Novinka',
		'new_days'       => 30,
		'promo_color'    => '#d63638',
		'new_color'      => '#1e8e3e',
	);
}

function jsk_promo_opt( $k ) {
	$s = wp_parse_args( (array) get_option( 'jsk_promo', array() ), jsk_promo_defaults() );
	return $s[ $k ] ?? null;
}

/* ---------------------------------------------------------------- Stav produktu */

/** Je produkt novinka? Ručné označenie (do dátumu), inak automaticky podľa dátumu pridania. */
function jsk_is_new( $p ) {
	$p = is_numeric( $p ) ? wc_get_product( $p ) : $p;
	if ( ! $p ) {
		return false;
	}
	$id   = $p->get_parent_id() ? $p->get_parent_id() : $p->get_id();
	$flag = (string) get_post_meta( $id, '_jsk_new', true );
	if ( 'no' === $flag ) {
		return false;
	}
	$today = current_time( 'Y-m-d' );
	if ( 'yes' === $flag ) {
		$until = (string) get_post_meta( $id, '_jsk_new_until', true );
		return '' === $until || $until >= $today;
	}
	$days = (int) jsk_promo_opt( 'new_days' );
	$c    = $p->get_date_created();
	return $days > 0 && $c && $c->date( 'Y-m-d' ) > gmdate( 'Y-m-d', strtotime( $today . ' -' . $days . ' days' ) );
}

/** Zľava v percentách (pri variabilnom produkte najvyššia z variantov), 0 = bez zľavy. */
function jsk_discount_pct( $p ) {
	$p = is_numeric( $p ) ? wc_get_product( $p ) : $p;
	if ( ! $p || ! $p->is_on_sale() ) {
		return 0;
	}
	$items = $p->is_type( 'variable' ) ? array_filter( array_map( 'wc_get_product', $p->get_children() ) ) : array( $p );
	$max   = 0;
	foreach ( $items as $v ) {
		$r = (float) $v->get_regular_price();
		$s = (float) $v->get_sale_price();
		if ( $r > 0 && $s > 0 && $s < $r && $v->is_on_sale() ) {
			$max = max( $max, (int) round( ( 1 - $s / $r ) * 100 ) );
		}
	}
	return $max;
}

/** ID produktov v akcii a noviniek (publikované). */
function jsk_promo_ids( $what ) {
	if ( 'akcie' === $what ) {
		$ids = array_map( 'intval', wc_get_product_ids_on_sale() );
		$par = array();
		foreach ( $ids as $id ) {
			$pid         = (int) wp_get_post_parent_id( $id );
			$par[ $pid ? $pid : $id ] = true;
		}
		return array_keys( $par );
	}
	$ids = wc_get_products( array( 'limit' => -1, 'status' => 'publish', 'return' => 'ids', 'orderby' => 'date', 'order' => 'DESC' ) );
	return array_values( array_filter( array_map( 'intval', $ids ), 'jsk_is_new' ) );
}

/* ---------------------------------------------------------------- Hromadné zmeny */

/** Zaokrúhlenie akciovej ceny: 0.01 | 0.1 | 1 | 9 (končí na ,90) | 99 (končí na ,99). */
function jsk_round_price( $v, $mode ) {
	switch ( (string) $mode ) {
		case '0.1':
			return round( $v, 1 );
		case '1':
			return round( $v );
		case '9':
		case '99':
			// Najbližšia cena končiaca na ,90 / ,99 (napr. 15,84 → 15,90; 16,20 → 15,90).
			$end = '9' === (string) $mode ? 0.9 : 0.99;
			$up  = floor( $v ) + $end;
			$dn  = $up - 1;
			return round( max( $end, abs( $up - $v ) <= abs( $v - $dn ) ? $up : $dn ), 2 );
	}
	return round( $v, 2 );
}

/** Varianty / produkt, ktorým sa nastavuje cena. */
function jsk_price_items( $p ) {
	return $p->is_type( 'variable' ) ? array_filter( array_map( 'wc_get_product', $p->get_children() ) ) : array( $p );
}

/**
 * Zľava pre produkty. $a: type (pct|amount|price), value, from, to (Y-m-d), label, round.
 * Vráti [ ok, skipped[] ].
 */
function jsk_promo_apply( array $ids, array $a ) {
	$ok   = 0;
	$skip = array();
	$val  = (float) str_replace( array( ' ', ',' ), array( '', '.' ), (string) $a['value'] );
	foreach ( $ids as $id ) {
		$p = wc_get_product( (int) $id );
		if ( ! $p || $p->is_type( array( 'grouped', 'external' ) ) ) {
			continue;
		}
		$done = false;
		foreach ( jsk_price_items( $p ) as $v ) {
			$reg = (float) $v->get_regular_price();
			if ( $reg <= 0 ) {
				$skip[] = $v->get_name() . ' (bez bežnej ceny)';
				continue;
			}
			$sale = 'pct' === $a['type'] ? $reg * ( 1 - $val / 100 ) : ( 'amount' === $a['type'] ? $reg - $val : $val );
			$sale = jsk_round_price( $sale, $a['round'] ?? '0.01' );
			if ( $sale <= 0 || $sale >= $reg ) {
				$skip[] = $v->get_name() . ' (akciová cena ' . wc_format_localized_price( $sale ) . ' nie je nižšia ako ' . wc_format_localized_price( $reg ) . ')';
				continue;
			}
			$v->set_sale_price( wc_format_decimal( $sale ) );
			$v->set_date_on_sale_from( $a['from'] ? $a['from'] . ' 00:00:00' : null );
			$v->set_date_on_sale_to( $a['to'] ? $a['to'] . ' 23:59:59' : null );
			$v->save();
			$done = true;
		}
		if ( $done ) {
			'' !== trim( (string) $a['label'] ) ? update_post_meta( $p->get_id(), '_jsk_promo', sanitize_text_field( $a['label'] ) ) : delete_post_meta( $p->get_id(), '_jsk_promo' );
			if ( $p->is_type( 'variable' ) ) {
				WC_Product_Variable::sync( $p->get_id() );
			}
			wc_delete_product_transients( $p->get_id() );
			++$ok;
		}
	}
	return array( $ok, $skip );
}

function jsk_promo_clear( array $ids ) {
	$n = 0;
	foreach ( $ids as $id ) {
		$p = wc_get_product( (int) $id );
		if ( ! $p ) {
			continue;
		}
		foreach ( jsk_price_items( $p ) as $v ) {
			$v->set_sale_price( '' );
			$v->set_date_on_sale_from( null );
			$v->set_date_on_sale_to( null );
			$v->save();
		}
		delete_post_meta( $p->get_id(), '_jsk_promo' );
		if ( $p->is_type( 'variable' ) ) {
			WC_Product_Variable::sync( $p->get_id() );
		}
		wc_delete_product_transients( $p->get_id() );
		++$n;
	}
	return $n;
}

function jsk_new_set( array $ids, $flag, $until = '' ) {
	foreach ( $ids as $id ) {
		$id = (int) $id;
		if ( '' === $flag ) {
			delete_post_meta( $id, '_jsk_new' );
			delete_post_meta( $id, '_jsk_new_until' );
			continue;
		}
		update_post_meta( $id, '_jsk_new', $flag );
		'yes' === $flag && $until ? update_post_meta( $id, '_jsk_new_until', $until ) : delete_post_meta( $id, '_jsk_new_until' );
		wc_delete_product_transients( $id );
	}
	return count( $ids );
}

add_action( 'admin_post_jsk_promo_bulk', function () {
	if ( ! current_user_can( JSK_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jsk_promo_bulk' );
	$in   = wp_unslash( $_POST );
	$ids  = array_filter( array_map( 'intval', (array) ( $in['ids'] ?? array() ) ) );
	$back = wp_get_referer() ? wp_get_referer() : jsk_url( 'j-sklad-promo' );
	$back = remove_query_arg( array( 'jsk_msg', 'jsk_info' ), $back );
	$date = fn( $k ) => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $in[ $k ] ?? '' ) ) ? $in[ $k ] : '';
	if ( ! $ids ) {
		set_transient( 'jsk_err_' . get_current_user_id(), 'Označ aspoň jeden produkt.', 60 );
		wp_safe_redirect( add_query_arg( 'jsk_msg', 'err', $back ) );
		exit;
	}
	$act = sanitize_key( $in['act'] ?? '' );
	$msg = '';
	switch ( $act ) {
		case 'sale':
			if ( (float) str_replace( ',', '.', (string) ( $in['value'] ?? '' ) ) <= 0 ) {
				set_transient( 'jsk_err_' . get_current_user_id(), 'Zadaj výšku zľavy alebo akciovú cenu.', 60 );
				wp_safe_redirect( add_query_arg( 'jsk_msg', 'err', $back ) );
				exit;
			}
			if ( $date( 'from' ) && $date( 'to' ) && $date( 'to' ) < $date( 'from' ) ) {
				set_transient( 'jsk_err_' . get_current_user_id(), 'Dátum konca akcie je skôr ako začiatok.', 60 );
				wp_safe_redirect( add_query_arg( 'jsk_msg', 'err', $back ) );
				exit;
			}
			list( $ok, $skip ) = jsk_promo_apply( $ids, array( 'type' => in_array( $in['type'] ?? '', array( 'pct', 'amount', 'price' ), true ) ? $in['type'] : 'pct', 'value' => $in['value'] ?? '', 'from' => $date( 'from' ), 'to' => $date( 'to' ), 'label' => $in['label'] ?? '', 'round' => $in['round'] ?? '0.01' ) );
			$msg = 'Zľava je nastavená pri ' . $ok . ' produktoch' . ( $date( 'from' ) || $date( 'to' ) ? ' (akcia ' . ( $date( 'from' ) ? 'od ' . date_i18n( 'j. n. Y', strtotime( $date( 'from' ) ) ) : '' ) . ( $date( 'to' ) ? ' do ' . date_i18n( 'j. n. Y', strtotime( $date( 'to' ) ) ) : '' ) . ')' : '' ) . '.' . ( $skip ? ' Vynechané: ' . implode( '; ', array_slice( $skip, 0, 8 ) ) . ( count( $skip ) > 8 ? '…' : '' ) : '' );
			break;
		case 'unsale':
			$msg = 'Zľava je zrušená pri ' . jsk_promo_clear( $ids ) . ' produktoch.';
			break;
		case 'new':
			jsk_new_set( $ids, 'yes', $date( 'new_until' ) );
			$msg = count( $ids ) . ' produktov je označených ako novinka' . ( $date( 'new_until' ) ? ' do ' . date_i18n( 'j. n. Y', strtotime( $date( 'new_until' ) ) ) : '' ) . '.';
			break;
		case 'unnew':
			jsk_new_set( $ids, 'no' );
			$msg = count( $ids ) . ' produktov už nie je novinka.';
			break;
		case 'autonew':
			jsk_new_set( $ids, '' );
			$msg = count( $ids ) . ' produktov je novinka automaticky podľa dátumu pridania.';
			break;
		default:
			set_transient( 'jsk_err_' . get_current_user_id(), 'Vyber akciu.', 60 );
			wp_safe_redirect( add_query_arg( 'jsk_msg', 'err', $back ) );
			exit;
	}
	set_transient( 'jsk_info_' . get_current_user_id(), $msg, 120 );
	wp_safe_redirect( add_query_arg( 'jsk_info', 1, $back ) );
	exit;
} );

add_action( 'admin_post_jsk_promo_settings', function () {
	if ( ! current_user_can( JSK_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jsk_promo_settings' );
	$in  = wp_unslash( $_POST );
	$col = fn( $k, $d ) => preg_match( '/^#[0-9a-fA-F]{6}$/', (string) ( $in[ $k ] ?? '' ) ) ? $in[ $k ] : $d;
	update_option( 'jsk_promo', array(
		'promo_badges' => ! empty( $in['promo_badges'] ) ? 1 : 0,
		'promo_pct'    => ! empty( $in['promo_pct'] ) ? 1 : 0,
		'promo_text'   => sanitize_text_field( $in['promo_text'] ?? 'Akcia' ),
		'new_text'     => sanitize_text_field( $in['new_text'] ?? 'Novinka' ),
		'new_days'     => max( 0, min( 365, (int) ( $in['new_days'] ?? 30 ) ) ),
		'promo_color'  => $col( 'promo_color', '#d63638' ),
		'new_color'    => $col( 'new_color', '#1e8e3e' ),
	), false );
	set_transient( 'jsk_info_' . get_current_user_id(), 'Nastavenia akcií a noviniek sú uložené.', 120 );
	wp_safe_redirect( jsk_url( 'j-sklad-promo', array( 'jsk_info' => 1, 'tab' => 'settings' ) ) );
	exit;
} );

/* ---------------------------------------------------------------- Stránka */

function jsk_render_promo() {
	if ( ! current_user_can( JSK_CAP ) ) {
		return;
	}
	$tab  = 'settings' === jsk_get( 'tab' ) ? 'settings' : 'list';
	$show = in_array( jsk_get( 'show' ), array( 'akcie', 'novinky', 'bez' ), true ) ? jsk_get( 'show' ) : '';
	$s    = trim( jsk_get( 's' ) );
	$cat  = (int) jsk_get( 'cat' );
	jsk_head( 'Akcie a novinky' );
	echo '<hr class="wp-header-end">';
	jsk_notice();
	if ( jsk_get( 'jsk_info' ) ) {
		$m = get_transient( 'jsk_info_' . get_current_user_id() );
		delete_transient( 'jsk_info_' . get_current_user_id() );
		if ( $m ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $m ) . '</p></div>';
		}
	}
	echo '<nav class="nav-tab-wrapper"><a class="nav-tab' . ( 'list' === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( jsk_url( 'j-sklad-promo' ) ) . '">Produkty</a><a class="nav-tab' . ( 'settings' === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( jsk_url( 'j-sklad-promo', array( 'tab' => 'settings' ) ) ) . '">Štítky a zobrazenie</a></nav>';
	if ( 'settings' === $tab ) {
		jsk_render_promo_settings();
		echo '</div>';
		return;
	}
	$args = array( 'limit' => 200, 'status' => array( 'publish', 'private', 'draft' ), 'orderby' => 'title', 'order' => 'ASC' );
	if ( '' !== $s ) {
		$args['s'] = $s;
	}
	if ( $cat ) {
		$t                = get_term( $cat, 'product_cat' );
		$args['category'] = $t && ! is_wp_error( $t ) ? array( $t->slug ) : array();
	}
	if ( 'akcie' === $show ) {
		$args['include'] = jsk_promo_ids( 'akcie' ) ? jsk_promo_ids( 'akcie' ) : array( 0 );
	}
	$prods = wc_get_products( $args );
	if ( 'novinky' === $show ) {
		$prods = array_values( array_filter( $prods, 'jsk_is_new' ) );
	} elseif ( 'bez' === $show ) {
		$prods = array_values( array_filter( $prods, fn( $p ) => ! $p->is_on_sale() && ! jsk_is_new( $p ) ) );
	}
	$na = count( jsk_promo_ids( 'akcie' ) );
	$nn = count( jsk_promo_ids( 'novinky' ) );
	?>
	<p class="description">Zľava sa uloží ako akciová cena WooCommerce (platí v košíku aj v objednávkach), voliteľne s dátumom začiatku a konca akcie – cena sa zmení a vráti sama. V obchode sa zobrazia štítky <strong><?php echo esc_html( jsk_promo_opt( 'promo_text' ) ); ?></strong> a <strong><?php echo esc_html( jsk_promo_opt( 'new_text' ) ); ?></strong>. Teraz je v akcii <strong><?php echo (int) $na; ?></strong> a noviniek <strong><?php echo (int) $nn; ?></strong> produktov.</p>
	<form method="get" class="jsk-filter">
		<input type="hidden" name="page" value="j-sklad-promo">
		<input type="search" name="s" value="<?php echo esc_attr( $s ); ?>" placeholder="Názov alebo číslo produktu">
		<?php wp_dropdown_categories( array( 'taxonomy' => 'product_cat', 'name' => 'cat', 'selected' => $cat, 'show_option_all' => 'Všetky kategórie', 'hide_empty' => false, 'hierarchical' => true, 'value_field' => 'term_id' ) ); ?>
		<select name="show"><option value="">Všetky produkty</option><option value="akcie" <?php selected( $show, 'akcie' ); ?>>V akcii</option><option value="novinky" <?php selected( $show, 'novinky' ); ?>>Novinky</option><option value="bez" <?php selected( $show, 'bez' ); ?>>Bez akcie a novinky</option></select>
		<button class="button">Filtrovať</button>
	</form>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="jsk-promo">
		<?php wp_nonce_field( 'jsk_promo_bulk' ); ?><input type="hidden" name="action" value="jsk_promo_bulk">
		<div class="jsk-panel jsk-promo-bulk">
			<label>Akcia s označenými<br><select name="act" id="jsk-act">
				<option value="sale">Nastaviť zľavu</option>
				<option value="unsale">Zrušiť zľavu</option>
				<option value="new">Označiť ako novinku</option>
				<option value="unnew">Zrušiť novinku</option>
				<option value="autonew">Novinka automaticky podľa dátumu pridania</option>
			</select></label>
			<span class="jsk-a-sale">
				<label>Zľava<br><input name="value" inputmode="decimal" style="width:90px;text-align:right" placeholder="20"> <select name="type"><option value="pct">%</option><option value="amount">€ z ceny</option><option value="price">€ nová cena</option></select></label>
				<label>Od<br><input type="date" name="from"></label>
				<label>Do<br><input type="date" name="to"></label>
				<label>Názov akcie<br><input name="label" placeholder="napr. Black Friday" style="width:170px"></label>
				<label>Zaokrúhliť<br><select name="round"><option value="0.01">na centy</option><option value="0.1">na 10 centov</option><option value="1">na celé €</option><option value="9">na ,90</option><option value="99">na ,99</option></select></label>
			</span>
			<span class="jsk-a-new" style="display:none"><label>Novinka do<br><input type="date" name="new_until" value="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' +' . max( 1, (int) jsk_promo_opt( 'new_days' ) ) . ' days' ) ) ); ?>"></label></span>
			<label><br><button class="button button-primary">Použiť na označené</button></label>
		</div>
		<table class="widefat striped jsk-table">
			<thead><tr><td class="check-column"><input type="checkbox" id="jsk-all"></td><th></th><th>Produkt</th><th>Kategória</th><th class="n">Bežná cena</th><th class="n">Akciová cena</th><th class="n">Zľava</th><th>Akcia</th><th>Novinka</th></tr></thead>
			<tbody>
			<?php if ( ! $prods ) : ?>
				<tr><td colspan="9">Žiadne produkty.</td></tr>
			<?php endif; ?>
			<?php
			foreach ( $prods as $p ) :
				$items = jsk_price_items( $p );
				$reg   = array_filter( array_map( fn( $v ) => (float) $v->get_regular_price(), $items ) );
				$sal   = array_filter( array_map( fn( $v ) => (float) $v->get_sale_price(), $items ) );
				$rng   = fn( $a ) => $a ? ( min( $a ) === max( $a ) ? wc_price( min( $a ) ) : wc_price( min( $a ) ) . ' – ' . wc_price( max( $a ) ) ) : '—';
				$v0    = reset( $items );
				$from  = $v0 && $v0->get_date_on_sale_from() ? $v0->get_date_on_sale_from()->date_i18n( 'j. n. Y' ) : '';
				$to    = $v0 && $v0->get_date_on_sale_to() ? $v0->get_date_on_sale_to()->date_i18n( 'j. n. Y' ) : '';
				$pct   = $sal ? jsk_discount_pct( $p ) : 0;
				$flag  = (string) get_post_meta( $p->get_id(), '_jsk_new', true );
				$until = (string) get_post_meta( $p->get_id(), '_jsk_new_until', true );
				$isnew = jsk_is_new( $p );
				$cats  = wp_list_pluck( get_the_terms( $p->get_id(), 'product_cat' ) ?: array(), 'name' );
				?>
				<tr>
					<th class="check-column"><input type="checkbox" name="ids[]" value="<?php echo (int) $p->get_id(); ?>"></th>
					<td><?php echo jsk_thumb( $p, 36 ); // phpcs:ignore ?></td>
					<td><a href="<?php echo esc_url( get_edit_post_link( $p->get_id() ) ); ?>"><?php echo esc_html( $p->get_name() ); ?></a><?php echo $p->get_sku() ? '<br><small>' . esc_html( $p->get_sku() ) . '</small>' : ''; ?><?php echo 'publish' !== $p->get_status() ? ' <small class="jsk-muted">(' . esc_html( get_post_status_object( $p->get_status() )->label ?? $p->get_status() ) . ')</small>' : ''; ?></td>
					<td><?php echo esc_html( implode( ', ', $cats ) ); ?></td>
					<td class="n"><?php echo wp_kses_post( $rng( $reg ) ); ?></td>
					<td class="n"><?php echo $sal ? wp_kses_post( $rng( $sal ) ) : '—'; ?></td>
					<td class="n"><?php echo $pct ? '<span class="jsk-badge jsk-badge-sale">−' . (int) $pct . ' %</span>' : ( $sal ? '<small>čaká</small>' : '' ); ?></td>
					<td><?php echo esc_html( (string) get_post_meta( $p->get_id(), '_jsk_promo', true ) ); ?><?php echo $from || $to ? '<br><small>' . esc_html( trim( ( $from ? 'od ' . $from : '' ) . ' ' . ( $to ? 'do ' . $to : '' ) ) ) . '</small>' : ''; ?></td>
					<td><?php echo $isnew ? '<span class="jsk-badge jsk-badge-new">' . esc_html( jsk_promo_opt( 'new_text' ) ) . '</span><br><small>' . ( 'yes' === $flag ? ( $until ? 'do ' . esc_html( date_i18n( 'j. n. Y', strtotime( $until ) ) ) : 'ručne' ) : 'automaticky' ) . '</small>' : ( 'no' === $flag ? '<small>vypnuté</small>' : '' ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</form>
	<script>
	(function () {
		var act = document.getElementById('jsk-act'), all = document.getElementById('jsk-all');
		function t() { document.querySelector('.jsk-a-sale').style.display = act.value === 'sale' ? '' : 'none'; document.querySelector('.jsk-a-new').style.display = act.value === 'new' ? '' : 'none'; }
		act.addEventListener('change', t); t();
		all.addEventListener('change', function () { document.querySelectorAll('#jsk-promo input[name="ids[]"]').forEach(function (c) { c.checked = all.checked; }); });
	})();
	</script>
	<h2>Zobrazenie v obchode</h2>
	<ul class="ul-disc">
		<li>Krátky kód <code>[jsk_akcie]</code> – produkty v akcii, <code>[jsk_novinky]</code> – novinky (voliteľne <code>limit="8" columns="4"</code>). Vložte ich na stránku, napr. „Akcie“ a „Novinky“.</li>
		<li>Filter obchodu: <a href="<?php echo esc_url( add_query_arg( 'jsk', 'akcie', get_permalink( wc_get_page_id( 'shop' ) ) ) ); ?>" target="_blank"><?php echo esc_html( add_query_arg( 'jsk', 'akcie', get_permalink( wc_get_page_id( 'shop' ) ) ) ); ?></a> a <a href="<?php echo esc_url( add_query_arg( 'jsk', 'novinky', get_permalink( wc_get_page_id( 'shop' ) ) ) ); ?>" target="_blank">?jsk=novinky</a> – odkazy do menu.</li>
	</ul>
	</div>
	<?php
}

function jsk_render_promo_settings() {
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="jsk-panel">
		<?php wp_nonce_field( 'jsk_promo_settings' ); ?><input type="hidden" name="action" value="jsk_promo_settings">
		<table class="form-table" role="presentation">
			<tr><th>Štítky v obchode</th><td><label><input type="checkbox" name="promo_badges" value="1" <?php checked( (int) jsk_promo_opt( 'promo_badges' ) ); ?>> zobrazovať štítky Akcia a Novinka pri produktoch (namiesto štítku WooCommerce „Výpredaj!“)</label><br><label><input type="checkbox" name="promo_pct" value="1" <?php checked( (int) jsk_promo_opt( 'promo_pct' ) ); ?>> pri akcii ukázať percento zľavy (napr. −20 %)</label></td></tr>
			<tr><th>Text štítku akcie</th><td><input name="promo_text" value="<?php echo esc_attr( jsk_promo_opt( 'promo_text' ) ); ?>"> <input type="color" name="promo_color" value="<?php echo esc_attr( jsk_promo_opt( 'promo_color' ) ); ?>"><p class="description">Ak má produkt názov akcie (napr. Black Friday), zobrazí sa ten.</p></td></tr>
			<tr><th>Text štítku novinky</th><td><input name="new_text" value="<?php echo esc_attr( jsk_promo_opt( 'new_text' ) ); ?>"> <input type="color" name="new_color" value="<?php echo esc_attr( jsk_promo_opt( 'new_color' ) ); ?>"></td></tr>
			<tr><th>Novinka automaticky</th><td><input type="number" name="new_days" min="0" max="365" value="<?php echo (int) jsk_promo_opt( 'new_days' ); ?>" style="width:80px"> dní od pridania produktu<p class="description">0 = len ručné označenie. Ručne sa dá novinka zapnúť (s dátumom konca) alebo vypnúť pri konkrétnom produkte.</p></td></tr>
		</table>
		<p><button class="button button-primary">Uložiť</button></p>
	</form>
	<?php
}

/* ---------------------------------------------------------------- Detail produktu a zoznam produktov */

add_action( 'woocommerce_product_options_pricing', function () {
	global $post;
	$flag = (string) get_post_meta( $post->ID, '_jsk_new', true );
	echo '<div class="options_group jsk-promo-fields">';
	woocommerce_wp_text_input( array( 'id' => '_jsk_promo', 'label' => 'Názov akcie', 'placeholder' => 'napr. Black Friday', 'desc_tip' => true, 'description' => 'Zobrazí sa na štítku produktu v akcii (J-Sklad).' ) );
	woocommerce_wp_select( array( 'id' => '_jsk_new', 'label' => 'Novinka', 'value' => $flag, 'options' => array( '' => 'automaticky (' . (int) jsk_promo_opt( 'new_days' ) . ' dní od pridania)', 'yes' => 'áno', 'no' => 'nie' ) ) );
	woocommerce_wp_text_input( array( 'id' => '_jsk_new_until', 'label' => 'Novinka do', 'type' => 'date', 'desc_tip' => true, 'description' => 'Prázdne = bez obmedzenia (pri voľbe áno).' ) );
	echo '</div>';
} );

add_action( 'woocommerce_admin_process_product_object', function ( $p ) {
	if ( ! isset( $_POST['_jsk_new'] ) ) { // phpcs:ignore
		return;
	}
	$label = sanitize_text_field( wp_unslash( $_POST['_jsk_promo'] ?? '' ) ); // phpcs:ignore
	$flag  = sanitize_key( $_POST['_jsk_new'] ?? '' ); // phpcs:ignore
	$until = sanitize_text_field( wp_unslash( $_POST['_jsk_new_until'] ?? '' ) ); // phpcs:ignore
	'' !== $label ? $p->update_meta_data( '_jsk_promo', $label ) : $p->delete_meta_data( '_jsk_promo' );
	in_array( $flag, array( 'yes', 'no' ), true ) ? $p->update_meta_data( '_jsk_new', $flag ) : $p->delete_meta_data( '_jsk_new' );
	'yes' === $flag && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $until ) ? $p->update_meta_data( '_jsk_new_until', $until ) : $p->delete_meta_data( '_jsk_new_until' );
} );

add_filter( 'manage_edit-product_columns', function ( $cols ) {
	$out = array();
	foreach ( $cols as $k => $v ) {
		$out[ $k ] = $v;
		if ( 'price' === $k ) {
			$out['jsk_promo'] = 'Akcia / novinka';
		}
	}
	return isset( $out['jsk_promo'] ) ? $out : $out + array( 'jsk_promo' => 'Akcia / novinka' );
} );

add_action( 'manage_product_posts_custom_column', function ( $col, $id ) {
	if ( 'jsk_promo' !== $col ) {
		return;
	}
	$p = wc_get_product( $id );
	if ( ! $p ) {
		return;
	}
	$pct = jsk_discount_pct( $p );
	echo $pct ? '<span class="jsk-badge jsk-badge-sale">−' . (int) $pct . ' %</span> ' : '';
	echo jsk_is_new( $p ) ? '<span class="jsk-badge jsk-badge-new">' . esc_html( jsk_promo_opt( 'new_text' ) ) . '</span>' : '';
}, 10, 2 );

/* ---------------------------------------------------------------- Obchod: štítky, krátke kódy, filter */

/** HTML štítkov produktu. */
function jsk_badges_html( $p ) {
	if ( ! $p ) {
		return '';
	}
	$out = '';
	if ( $p->is_on_sale() ) {
		$label = (string) get_post_meta( $p->get_parent_id() ? $p->get_parent_id() : $p->get_id(), '_jsk_promo', true );
		$pct   = (int) jsk_promo_opt( 'promo_pct' ) ? jsk_discount_pct( $p ) : 0;
		$txt   = $label ? $label : (string) jsk_promo_opt( 'promo_text' );
		$out  .= '<span class="jsk-b jsk-b-sale">' . esc_html( $txt ) . ( $pct ? ' −' . (int) $pct . ' %' : '' ) . '</span>';
	}
	if ( jsk_is_new( $p ) ) {
		$out .= '<span class="jsk-b jsk-b-new">' . esc_html( jsk_promo_opt( 'new_text' ) ) . '</span>';
	}
	return $out ? '<div class="jsk-badges">' . $out . '</div>' : '';
}

add_filter( 'woocommerce_sale_flash', function ( $html, $post, $product ) {
	return (int) jsk_promo_opt( 'promo_badges' ) ? '' : $html;
}, 20, 3 );

add_action( 'woocommerce_before_shop_loop_item_title', function () {
	global $product;
	if ( (int) jsk_promo_opt( 'promo_badges' ) ) {
		echo jsk_badges_html( $product ); // phpcs:ignore
	}
}, 9 );

// Detail produktu: štítky nad názvom (nezávisle od rozloženia galérie v téme).
add_action( 'woocommerce_single_product_summary', function () {
	global $product;
	if ( (int) jsk_promo_opt( 'promo_badges' ) ) {
		echo str_replace( 'class="jsk-badges"', 'class="jsk-badges jsk-badges-inline"', jsk_badges_html( $product ) ); // phpcs:ignore
	}
}, 4 );

// Blokové šablóny obchodu (Product Collection / Product Image): štítky do obrázka produktu.
add_filter( 'render_block_woocommerce/product-image', function ( $html, $block, $instance ) {
	if ( ! (int) jsk_promo_opt( 'promo_badges' ) || is_admin() ) {
		return $html;
	}
	$id = (int) ( $instance->context['postId'] ?? 0 );
	return $id ? jsk_badges_html( wc_get_product( $id ) ) . $html : $html;
}, 10, 3 );

add_filter( 'render_block_woocommerce/product-sale-badge', function ( $html ) {
	return (int) jsk_promo_opt( 'promo_badges' ) ? '' : $html;
} );

add_action( 'wp_head', function () {
	if ( ! (int) jsk_promo_opt( 'promo_badges' ) || ! function_exists( 'is_woocommerce' ) ) {
		return;
	}
	printf(
		'<style id="jsk-badges">.jsk-badges{position:absolute;top:8px;left:8px;z-index:5;display:flex;flex-direction:column;gap:4px;align-items:flex-start;pointer-events:none}.jsk-b{display:inline-block;padding:3px 8px;border-radius:4px;font-size:12px;font-weight:700;line-height:1.3;color:#fff;text-transform:uppercase;letter-spacing:.02em}.jsk-b-sale{background:%1$s}.jsk-b-new{background:%2$s}li.product,.wc-block-grid__product,.wc-block-product,.woocommerce div.product{position:relative}.jsk-badges-inline{position:static;flex-direction:row;margin:0 0 8px}</style>' . "\n",
		esc_attr( jsk_promo_opt( 'promo_color' ) ),
		esc_attr( jsk_promo_opt( 'new_color' ) )
	);
} );

/** [jsk_akcie limit="8" columns="4"] a [jsk_novinky …] */
function jsk_promo_shortcode( $what, $atts ) {
	$a   = shortcode_atts( array( 'limit' => 8, 'columns' => 4, 'orderby' => 'akcie' === $what ? 'popularity' : 'date', 'empty' => 'akcie' === $what ? 'Momentálne nemáme žiadne akcie.' : 'Momentálne nemáme žiadne novinky.' ), $atts );
	$ids = jsk_promo_ids( $what );
	if ( ! $ids ) {
		return '<p class="jsk-empty">' . esc_html( $a['empty'] ) . '</p>';
	}
	return do_shortcode( sprintf( '[products ids="%s" limit="%d" columns="%d" orderby="%s" order="DESC"]', implode( ',', $ids ), (int) $a['limit'], (int) $a['columns'], esc_attr( $a['orderby'] ) ) );
}
add_shortcode( 'jsk_akcie', fn( $atts ) => jsk_promo_shortcode( 'akcie', (array) $atts ) );
add_shortcode( 'jsk_novinky', fn( $atts ) => jsk_promo_shortcode( 'novinky', (array) $atts ) );

// Filter obchodu: ?jsk=akcie alebo ?jsk=novinky (hlavný dotaz obchodu WooCommerce aj kategórií).
function jsk_promo_filter_query( $q ) {
	$what = isset( $_GET['jsk'] ) && is_string( $_GET['jsk'] ) ? sanitize_key( $_GET['jsk'] ) : ''; // phpcs:ignore
	if ( ! in_array( $what, array( 'akcie', 'novinky' ), true ) ) {
		return;
	}
	$ids = jsk_promo_ids( $what );
	$q->set( 'post__in', $ids ? $ids : array( 0 ) );
}
add_action( 'woocommerce_product_query', 'jsk_promo_filter_query' );
add_action( 'pre_get_posts', function ( $q ) {
	if ( ! is_admin() && $q->is_main_query() && ( $q->is_post_type_archive( 'product' ) || $q->is_tax( get_object_taxonomies( 'product' ) ) || 'product' === $q->get( 'post_type' ) ) ) {
		jsk_promo_filter_query( $q );
	}
}, 999 );

add_filter( 'woocommerce_page_title', function ( $title ) {
	$what = isset( $_GET['jsk'] ) ? sanitize_key( $_GET['jsk'] ) : ''; // phpcs:ignore
	return 'akcie' === $what ? 'Akcie' : ( 'novinky' === $what ? 'Novinky' : $title );
} );
