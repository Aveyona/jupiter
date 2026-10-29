<?php
/**
 * Obrázky produktov v J-Skladu (voliteľné, Nastavenia → „Obrázky produktov spravuje J-Sklad“).
 *
 * Keď je zapnuté:
 * - J-Sklad → Obrázky: evidencia obrázkov ku každému produktu a variantu (hlavný obrázok, galéria) s úpravou na jednom mieste,
 * - Knižnica multimédií neukazuje obrázky, ktoré produkty práve používajú (hlavný obrázok, galéria) – ostávajú v nej obrázky webu;
 *   obrázok, ktorý je zároveň náhľadovým obrázkom článku či stránky, v Knižnici ostane,
 * - výber obrázka v úprave produktu a v J-Sklade ukazuje všetky obrázky ako doteraz.
 * Súbory sa nikam nepresúvajú; vypnutím voľby je Knižnica opäť kompletná.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function jsk_media_on() {
	return (bool) jsk_opt( 'media' );
}

/**
 * ID obrázkov, ktoré produkty práve používajú: hlavný obrázok a galéria produktov a variantov (len obrázky).
 * Neráta sa podľa „nahraté k produktu“ – obrázok odobratý z produktu sa tak hneď vráti do Knižnice.
 */
function jsk_product_image_ids() {
	global $wpdb;
	static $ids = null;
	if ( null !== $ids ) {
		return $ids;
	}
	$types = "'product','product_variation'";
	$thumb = $wpdb->get_col( "SELECT pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_thumbnail_id' AND p.post_type IN ($types)" ); // phpcs:ignore
	$gal   = $wpdb->get_col( "SELECT pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_product_image_gallery' AND pm.meta_value <> '' AND p.post_type IN ($types)" ); // phpcs:ignore
	$all   = $thumb;
	foreach ( $gal as $g ) {
		$all = array_merge( $all, explode( ',', $g ) );
	}
	$ids = array_values( array_unique( array_filter( array_map( 'intval', $all ) ) ) );
	if ( $ids ) {
		// Len prílohy typu obrázok (nie PDF, ZIP a pod.).
		$in  = implode( ',', $ids );
		$ids = array_map( 'intval', $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%' AND ID IN ($in)" ) ); // phpcs:ignore
	}
	// Obrázok, ktorý web používa aj inde (náhľad článku či stránky, logo, ikona webu, zástupný obrázok WooCommerce), v Knižnici ostane.
	$other = array_map( 'intval', $wpdb->get_col( "SELECT pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_thumbnail_id' AND p.post_type NOT IN ($types)" ) ); // phpcs:ignore
	$keep  = array_merge( $other, array_map( 'intval', array( get_option( 'site_icon' ), get_theme_mod( 'custom_logo' ), get_option( 'woocommerce_placeholder_image' ) ) ) );
	$ids   = array_values( array_diff( $ids, array_filter( $keep ) ) );
	return $ids;
}

/** Je požiadavka z Knižnice multimédií (upload.php)? Okno výberu obrázka v úprave produktu alebo článku nie. */
function jsk_media_is_library_request() {
	$ref = wp_get_referer();
	return $ref && false !== strpos( (string) wp_parse_url( $ref, PHP_URL_PATH ), '/wp-admin/upload.php' );
}

// Knižnica – mriežka (AJAX).
add_filter( 'ajax_query_attachments_args', function ( $q ) {
	if ( jsk_media_on() && jsk_media_is_library_request() && jsk_product_image_ids() ) {
		$q['post__not_in'] = array_merge( (array) ( $q['post__not_in'] ?? array() ), jsk_product_image_ids() );
	}
	return $q;
} );

// Knižnica – zoznam.
add_action( 'pre_get_posts', function ( $q ) {
	global $pagenow;
	if ( ! is_admin() || 'upload.php' !== $pagenow || ! $q->is_main_query() || ! jsk_media_on() || ! jsk_product_image_ids() ) {
		return;
	}
	$q->set( 'post__not_in', array_merge( (array) $q->get( 'post__not_in' ), jsk_product_image_ids() ) );
} );

// Upozornenie v Knižnici, kde sú obrázky produktov.
add_action( 'admin_notices', function () {
	global $pagenow;
	if ( 'upload.php' !== $pagenow || ! jsk_media_on() || ! current_user_can( JSK_CAP ) ) {
		return;
	}
	$n = count( jsk_product_image_ids() );
	if ( $n ) {
		echo '<div class="notice notice-info"><p><span class="dashicons dashicons-archive" style="color:#2271b1"></span> Obrázky produktov e-shopu (' . (int) $n . ') spravuje <a href="' . esc_url( jsk_url( 'j-sklad-media' ) ) . '"><strong>J-Sklad → Obrázky</strong></a>. Tu sú len obrázky webu.</p></div>';
	}
} );

add_action( 'admin_menu', function () {
	if ( jsk_media_on() ) {
		add_submenu_page( 'j-sklad', 'Obrázky produktov', 'Obrázky', JSK_CAP, 'j-sklad-media', 'jsk_render_media', 2 );
	}
}, 20 );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( false !== strpos( (string) $hook, 'j-sklad-media' ) ) {
		wp_enqueue_media();
	}
} );

/** Jedna dlaždica obrázka. */
function jsk_media_tile( $img, $role, $pid ) {
	$url = $img ? wp_get_attachment_image_url( $img, 'thumbnail' ) : '';
	if ( ! $url ) {
		return '';
	}
	return '<span class="jsk-mt" data-id="' . (int) $img . '"><a href="' . esc_url( (string) wp_get_attachment_url( $img ) ) . '" target="_blank" rel="noopener"><img src="' . esc_url( $url ) . '" alt="" loading="lazy"></a>'
		. '<button type="button" class="jsk-mt-x" data-act="' . esc_attr( 'main' === $role ? 'unset' : 'gdel' ) . '" data-pid="' . (int) $pid . '" title="Odobrať z produktu" aria-label="Odobrať z produktu">×</button></span>';
}

function jsk_media_row( $p, $is_var = false ) {
	$pid  = $p->get_id();
	$main = (int) $p->get_image_id();
	$gal  = $is_var ? array() : array_map( 'intval', $p->get_gallery_image_ids() );
	$name = $is_var ? jsk_var_label( $pid ) : $p->get_name();
	$sku  = $p->get_sku();
	$h    = '<tr class="' . ( $is_var ? 'jsk-mrow-var' : 'jsk-mrow' ) . '" data-pid="' . (int) $pid . '" data-gal="' . esc_attr( implode( ',', $gal ) ) . '">';
	$h   .= '<td class="jsk-mname">' . ( $is_var ? '<span class="jsk-muted">↳</span> ' : '' ) . '<a href="' . esc_url( (string) get_edit_post_link( $is_var ? $p->get_parent_id() : $pid ) ) . '">' . esc_html( $name ) . '</a>'
		. ( $sku ? '<br><small class="jsk-muted">Č. ' . esc_html( $sku ) . '</small>' : '' ) . ( 'publish' !== get_post_status( $is_var ? $p->get_parent_id() : $pid ) ? '<br><small class="jsk-muted">' . esc_html( get_post_status_object( get_post_status( $is_var ? $p->get_parent_id() : $pid ) )->label ?? '' ) . '</small>' : '' ) . '</td>';
	$h   .= '<td class="jsk-mmain">' . ( $main ? jsk_media_tile( $main, 'main', $pid ) : '' ) . '<button type="button" class="button jsk-mbtn" data-act="main" data-pid="' . (int) $pid . '">' . ( $main ? 'Zmeniť' : 'Pridať' ) . '</button></td>';
	$h   .= '<td class="jsk-mgal">';
	if ( ! $is_var ) {
		foreach ( $gal as $g ) {
			$h .= jsk_media_tile( $g, 'gal', $pid );
		}
		$h .= '<button type="button" class="button jsk-mbtn jsk-madd" data-act="gadd" data-pid="' . (int) $pid . '" title="Pridať do galérie">+ Galéria</button>';
	} else {
		$h .= '<span class="jsk-muted">—</span>';
	}
	return $h . '</td></tr>';
}

function jsk_render_media() {
	if ( ! current_user_can( JSK_CAP ) ) {
		return;
	}
	$q    = sanitize_text_field( jsk_get( 's' ) );
	$f    = sanitize_key( jsk_get( 'f' ) );
	$pg   = max( 1, (int) jsk_get( 'paged' ) );
	$args = array(
		'post_type'      => 'product',
		'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future' ),
		'posts_per_page' => 30,
		'paged'          => $pg,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'fields'         => 'ids',
	);
	if ( '' !== $q ) {
		$ids              = class_exists( 'WC_Data_Store' ) ? WC_Data_Store::load( 'product' )->search_products( $q, '', false, true, 500 ) : array();
		$args['post__in'] = $ids ? array_map( 'intval', $ids ) : array( 0 );
	}
	if ( 'noimg' === $f ) {
		$args['meta_query'] = array( 'relation' => 'OR', array( 'key' => '_thumbnail_id', 'compare' => 'NOT EXISTS' ), array( 'key' => '_thumbnail_id', 'value' => array( '', '0' ), 'compare' => 'IN' ) ); // phpcs:ignore
	}
	$wq    = new WP_Query( $args );
	$total = count( jsk_product_image_ids() );
	jsk_head( 'Obrázky produktov' );
	echo '<hr class="wp-header-end">';
	echo '<p class="description" style="max-width:820px">Hlavný obrázok a galéria každého produktu a variantu na jednom mieste. Knižnica multimédií obrázky používané produktmi nezobrazuje (' . (int) $total . ' obrázkov), aby v nej ostali obrázky webu; obrázok odobratý z produktu sa do Knižnice vráti. Kliknutím na obrázok ho otvoríš v plnej veľkosti, × ho odoberie z produktu (súbor ostane).</p>';
	echo '<form method="get" class="jsk-filter"><input type="hidden" name="page" value="j-sklad-media">'
		. '<input type="search" name="s" value="' . esc_attr( $q ) . '" placeholder="Názov alebo číslo produktu">'
		. '<select name="f"><option value="">Všetky produkty</option><option value="noimg"' . selected( $f, 'noimg', false ) . '>Bez hlavného obrázka</option></select>'
		. '<button class="button">Filtrovať</button><span class="jsk-total">' . (int) $wq->found_posts . ' produktov</span></form>';
	echo '<table class="widefat striped jsk-table jsk-media"><thead><tr><th>Produkt</th><th style="width:170px">Hlavný obrázok</th><th>Galéria</th></tr></thead><tbody>';
	if ( ! $wq->posts ) {
		echo '<tr><td colspan="3">Žiadne produkty.</td></tr>';
	}
	foreach ( $wq->posts as $id ) {
		$p = wc_get_product( $id );
		if ( ! $p ) {
			continue;
		}
		echo jsk_media_row( $p ); // phpcs:ignore
		if ( $p->is_type( 'variable' ) ) {
			foreach ( $p->get_children() as $vid ) {
				$v = wc_get_product( $vid );
				if ( $v ) {
					echo jsk_media_row( $v, true ); // phpcs:ignore
				}
			}
		}
	}
	echo '</tbody></table>';
	if ( $wq->max_num_pages > 1 ) {
		echo '<div class="tablenav"><div class="tablenav-pages">' . paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $pg, 'total' => $wq->max_num_pages ) ) . '</div></div>'; // phpcs:ignore
	}
	$nonce = wp_create_nonce( 'jsk_media' );
	?>
	<script>
	(function () {
		var ajax = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, nonce = <?php echo wp_json_encode( $nonce ); ?>;
		var send = function (pid, op, ids) {
			var fd = new FormData();
			fd.append('action', 'jsk_media'); fd.append('_wpnonce', nonce); fd.append('pid', pid); fd.append('op', op); fd.append('ids', ids.join(','));
			return fetch(ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
				if (!j || !j.success) { alert((j && j.data) || 'Uloženie sa nepodarilo.'); return; }
				var row = document.querySelector('tr[data-pid="' + pid + '"]'), tmp = document.createElement('tbody');
				tmp.innerHTML = j.data.row; row.replaceWith(tmp.firstElementChild);
			});
		};
		var pick = function (multi, title, cb) {
			var fr = wp.media({ title: title, button: { text: multi ? 'Pridať do galérie' : 'Nastaviť' }, library: { type: 'image' }, multiple: multi ? 'add' : false });
			fr.on('select', function () { cb(fr.state().get('selection').map(function (a) { return a.id; })); });
			fr.open();
		};
		document.addEventListener('click', function (e) {
			var b = e.target.closest('.jsk-media [data-act]');
			if (!b) return;
			var pid = b.dataset.pid, row = b.closest('tr'), gal = row.dataset.gal ? row.dataset.gal.split(',').map(Number) : [];
			if (b.dataset.act === 'main') pick(false, 'Hlavný obrázok produktu', function (ids) { send(pid, 'main', ids); });
			if (b.dataset.act === 'gadd') pick(true, 'Galéria produktu', function (ids) { send(pid, 'gallery', gal.concat(ids.filter(function (i) { return gal.indexOf(i) < 0; }))); });
			if (b.dataset.act === 'unset' && confirm('Odobrať hlavný obrázok z produktu? Súbor ostane.')) send(pid, 'main', []);
			if (b.dataset.act === 'gdel') { var id = +b.parentNode.dataset.id; send(pid, 'gallery', gal.filter(function (i) { return i !== id; })); }
		});
	})();
	</script>
	</div>
	<?php
}

add_action( 'wp_ajax_jsk_media', function () {
	if ( ! current_user_can( JSK_CAP ) || ! check_ajax_referer( 'jsk_media', '_wpnonce', false ) ) {
		wp_send_json_error( 'Nemáte oprávnenie.' );
	}
	$p = wc_get_product( (int) ( $_POST['pid'] ?? 0 ) );
	if ( ! $p ) {
		wp_send_json_error( 'Produkt neexistuje.' );
	}
	$ids = array_values( array_filter( array_map( 'intval', explode( ',', (string) wp_unslash( $_POST['ids'] ?? '' ) ) ), function ( $id ) {
		return $id > 0 && wp_attachment_is_image( $id );
	} ) );
	$op = sanitize_key( $_POST['op'] ?? '' );
	if ( 'main' === $op ) {
		$p->set_image_id( $ids ? $ids[0] : 0 );
	} elseif ( 'gallery' === $op && ! $p->is_type( 'variation' ) ) {
		$p->set_gallery_image_ids( $ids );
	} else {
		wp_send_json_error( 'Neplatná akcia.' );
	}
	$p->save();
	wp_send_json_success( array( 'row' => jsk_media_row( wc_get_product( $p->get_id() ), $p->is_type( 'variation' ) ) ) );
} );
