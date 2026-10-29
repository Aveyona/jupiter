<?php
/** Administrácia J-Skladu: Prehľad skladu, Doklady, Pohyby, Nastavenia. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const JSK_CAP = 'manage_woocommerce';

add_action( 'admin_menu', function () {
	add_menu_page( 'J-Sklad', 'J-Sklad', JSK_CAP, 'j-sklad', 'jsk_render_overview', 'dashicons-archive', 58 );
	add_submenu_page( 'j-sklad', 'Prehľad skladu', 'Prehľad', JSK_CAP, 'j-sklad', 'jsk_render_overview' );
	add_submenu_page( 'j-sklad', 'Doklady', 'Doklady', JSK_CAP, 'j-sklad-docs', 'jsk_render_docs' );
	add_submenu_page( 'j-sklad', 'Pohyby zásob', 'Pohyby', JSK_CAP, 'j-sklad-moves', 'jsk_render_moves' );
	add_submenu_page( 'j-sklad', 'Nastavenia', 'Nastavenia', JSK_CAP, 'j-sklad-settings', 'jsk_render_settings' );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( false !== strpos( (string) $hook, 'j-sklad' ) || ( 'post.php' === $hook && 'product' === get_post_type() ) ) {
		wp_enqueue_style( 'j-sklad', JSK_URL . 'assets/admin.css', array(), JSK_VERSION );
	}
} );

function jsk_url( $page = 'j-sklad', $args = array() ) {
	return add_query_arg( $args, admin_url( 'admin.php?page=' . $page ) );
}

function jsk_head( $title ) {
	echo '<div class="wrap jsk"><h1 class="wp-heading-inline">' . esc_html( $title ) . ' <span class="jsk-ver">J-Sklad v' . esc_html( JSK_VERSION ) . '</span></h1>';
}

function jsk_status_label( $st ) {
	return array(
		'draft'      => 'Koncept',
		'posting'    => 'Zaúčtovaný len čiastočne',
		'posted'     => 'Zaúčtovaný',
		'cancelling' => 'Stornovaný len čiastočne',
		'cancelled'  => 'Stornovaný',
	)[ $st ] ?? $st;
}

/** Bezpečný reťazec z $_GET (pole namiesto reťazca → ''). */
function jsk_get( $k ) {
	return isset( $_GET[ $k ] ) && is_string( $_GET[ $k ] ) ? wp_unslash( $_GET[ $k ] ) : ''; // phpcs:ignore
}

/** Miniatúra produktu (pri variante bez obrázka obrázok hlavného produktu). */
function jsk_thumb( $p, $size = 48 ) {
	$img = $p->get_image_id();
	if ( ! $img && $p->get_parent_id() ) {
		$par = wc_get_product( $p->get_parent_id() );
		$img = $par ? $par->get_image_id() : 0;
	}
	$url = $img ? wp_get_attachment_image_url( $img, 'thumbnail' ) : '';
	return $url ? '<img src="' . esc_url( $url ) . '" alt="" width="' . (int) $size . '" height="' . (int) $size . '" loading="lazy" class="jsk-thumb">' : '<span class="jsk-thumb jsk-noimg" style="width:' . (int) $size . 'px;height:' . (int) $size . 'px"></span>';
}

function jsk_notice() {
	$m = isset( $_GET['jsk_msg'] ) ? sanitize_key( $_GET['jsk_msg'] ) : ''; // phpcs:ignore
	$t = array(
		'saved'     => array( 'success', 'Koncept je uložený.' ),
		'posted'    => array( 'success', 'Doklad je zaúčtovaný – zásoby sú zmenené.' ),
		'cancelled' => array( 'success', 'Doklad je stornovaný – zásoby sú vrátené.' ),
		'deleted'   => array( 'success', 'Koncept je zmazaný.' ),
		'skipped'   => array( 'warning', 'Koncept je uložený, ale niektoré riadky sa vynechali (nulové alebo neplatné množstvo, neexistujúci produkt):' ),
		'settings'  => array( 'success', 'Nastavenia sú uložené.' ),
		'err'       => array( 'error', 'Akcia sa nepodarila.' ),
	);
	if ( isset( $t[ $m ] ) ) {
		$e = get_transient( 'jsk_err_' . get_current_user_id() );
		delete_transient( 'jsk_err_' . get_current_user_id() );
		echo '<div class="notice notice-' . esc_attr( $t[ $m ][0] ) . ' is-dismissible"><p>' . esc_html( $t[ $m ][1] . ( ( 'err' === $m || 'skipped' === $m ) && $e ? ' ' . $e : '' ) ) . '</p></div>';
	}
}

/* ---------------------------------------------------------------- Prehľad */

function jsk_render_overview() {
	global $wpdb;
	if ( ! current_user_can( JSK_CAP ) ) {
		return;
	}
	$q    = sanitize_text_field( jsk_get( 's' ) );
	$f    = sanitize_key( jsk_get( 'f' ) );
	$cat  = isset( $_GET['cat'] ) ? (int) $_GET['cat'] : 0; // phpcs:ignore
	$pg   = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 ); // phpcs:ignore
	$low  = (float) jsk_opt( 'low_stock' );
	// Všetky = všetky produkty (aj služby a produkty bez sledovania zásob); ostatné filtre len sledované zásoby.
	$meta = '' === $f ? array() : array(
		array( 'key' => '_manage_stock', 'value' => 'yes' ),
	);
	if ( 'low' === $f ) {
		$meta[] = array( 'key' => '_stock', 'value' => $low, 'compare' => '<=', 'type' => 'DECIMAL(14,4)' );
		$meta[] = array( 'key' => '_stock', 'value' => 0, 'compare' => '>', 'type' => 'DECIMAL(14,4)' );
	} elseif ( 'out' === $f ) {
		$meta[] = array( 'key' => '_stock', 'value' => 0, 'compare' => '<=', 'type' => 'DECIMAL(14,4)' );
	} elseif ( 'nocost' === $f ) {
		$meta[] = array( 'key' => '_jsk_cost', 'compare' => 'NOT EXISTS' );
	} elseif ( 'tracked' !== $f ) {
		$f = '';
	}
	$args = array(
		'post_type'      => array( 'product', 'product_variation' ),
		'post_status'    => array( 'publish', 'private', 'draft', 'pending' ),
		'posts_per_page' => 50,
		'paged'          => $pg,
		'meta_query'     => $meta, // phpcs:ignore
		'orderby'        => 'title',
		'order'          => 'ASC',
		'fields'         => 'ids',
		// Variabilný produkt sa zobrazí cez svoje varianty (zásoba je pri nich).
		'tax_query'      => array( array( 'taxonomy' => 'product_type', 'field' => 'slug', 'terms' => array( 'variable', 'grouped', 'external' ), 'operator' => 'NOT IN' ) ), // phpcs:ignore
	);
	if ( '' !== $q ) {
		$ids = class_exists( 'WC_Data_Store' ) ? WC_Data_Store::load( 'product' )->search_products( $q, '', true, true, 500 ) : array();
		$args['post__in'] = $ids ? array_map( 'intval', $ids ) : array( 0 );
	}
	if ( $cat ) {
		// Produkty kategórie aj ich varianty (varianty kategóriu nemajú, dedia ju z hlavného produktu).
		$parents = get_posts( array( 'post_type' => 'product', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'tax_query' => array( array( 'taxonomy' => 'product_cat', 'terms' => $cat, 'include_children' => true ) ) ) ); // phpcs:ignore
		$vars    = $parents ? get_posts( array( 'post_type' => 'product_variation', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'post_parent__in' => $parents ) ) : array();
		$in      = array_map( 'intval', array_merge( $parents, $vars ) );
		$args['post__in'] = isset( $args['post__in'] ) ? array_values( array_intersect( $args['post__in'], $in ) ) : $in;
		if ( ! $args['post__in'] ) {
			$args['post__in'] = array( 0 );
		}
	}
	$wq = new WP_Query( $args );
	// Súhrn celého skladu (len produkty so sledovaním zásob).
	$sum = $wpdb->get_row( "SELECT COUNT(*) AS n, SUM( GREATEST( s.meta_value + 0, 0 ) ) AS qty, SUM( GREATEST( s.meta_value + 0, 0 ) * ( c.meta_value + 0 ) ) AS val, SUM( c.meta_value IS NULL ) AS nocost, SUM( GREATEST( s.meta_value + 0, 0 ) * IFNULL( ( SELECT MIN( pr.meta_value + 0 ) FROM {$wpdb->postmeta} pr WHERE pr.post_id = m.post_id AND pr.meta_key = '_price' AND pr.meta_value <> '' ), 0 ) ) AS sell
		FROM {$wpdb->postmeta} m
		JOIN {$wpdb->posts} p ON p.ID = m.post_id AND p.post_type IN ('product','product_variation') AND p.post_status IN ('publish','private','draft','pending')
		LEFT JOIN {$wpdb->postmeta} s ON s.post_id = m.post_id AND s.meta_key = '_stock'
		LEFT JOIN {$wpdb->postmeta} c ON c.post_id = m.post_id AND c.meta_key = '_jsk_cost'
		WHERE m.meta_key = '_manage_stock' AND m.meta_value = 'yes'", ARRAY_A ); // phpcs:ignore
	jsk_head( 'Prehľad skladu' );
	echo '<a class="page-title-action" href="' . esc_url( jsk_url( 'j-sklad-docs', array( 'new' => 'receipt' ) ) ) . '">Nová príjemka</a>';
	echo '<a class="page-title-action" href="' . esc_url( jsk_url( 'j-sklad-docs', array( 'new' => 'issue' ) ) ) . '">Nová výdajka</a><hr class="wp-header-end">';
	jsk_notice();
	?>
	<div class="jsk-cards">
		<div><span>Produkty so sledovaním zásob</span><strong><?php echo esc_html( number_format_i18n( (int) $sum['n'] ) ); ?></strong></div>
		<div><span>Kusov na sklade</span><strong><?php echo esc_html( jsk_qty( $sum['qty'] ) ); ?></strong></div>
		<div><span>Hodnota skladu v predajných cenách</span><strong><?php echo esc_html( jsk_money( $sum['sell'] ) ); ?></strong><small class="jsk-muted">aktuálne ceny v e-shope (pri zľave zľavnená)</small></div>
		<div><span>Hodnota skladu (nákupné ceny)</span><strong><?php echo esc_html( jsk_money( $sum['val'] ) ); ?></strong><?php if ( (int) $sum['nocost'] ) : ?><small><?php echo esc_html( number_format_i18n( (int) $sum['nocost'] ) ); ?> produktov bez nákupnej ceny</small><?php endif; ?></div>
	</div>
	<ul class="subsubsub jsk-filters">
		<?php
		$fl = array( '' => 'Všetky', 'tracked' => 'So sledovaním zásob', 'low' => 'Dochádza (≤ ' . jsk_qty( $low ) . ')', 'out' => 'Vypredané', 'nocost' => 'Bez nákupnej ceny' );
		$i  = 0;
		foreach ( $fl as $k => $l ) {
			echo ( $i++ ? ' | ' : '' ) . '<li><a class="' . ( $f === $k ? 'current' : '' ) . '" href="' . esc_url( jsk_url( 'j-sklad', array_filter( array( 'f' => $k, 's' => $q, 'cat' => $cat ) ) ) ) . '">' . esc_html( $l ) . '</a></li>';
		}
		?>
	</ul>
	<form method="get" class="jsk-filter">
		<input type="hidden" name="page" value="j-sklad">
		<?php if ( $f ) : ?><input type="hidden" name="f" value="<?php echo esc_attr( $f ); ?>"><?php endif; ?>
		<input type="search" name="s" value="<?php echo esc_attr( $q ); ?>" placeholder="Názov alebo SKU">
		<?php wp_dropdown_categories( array( 'taxonomy' => 'product_cat', 'name' => 'cat', 'selected' => $cat, 'show_option_all' => 'Všetky kategórie', 'hierarchical' => true, 'hide_empty' => false ) ); ?>
		<button class="button">Filtrovať</button>
		<span class="jsk-total"><?php echo esc_html( number_format_i18n( (int) $wq->found_posts ) ); ?> produktov</span>
	</form>
	<table class="widefat striped jsk-table">
		<thead><tr><th class="jsk-img-col"></th><th>Produkt</th><th>SKU</th><th class="n">Na sklade</th><th class="n">Predajná cena</th><th class="n">Nákupná cena (priemer)</th><th class="n">Hodnota (nákup)</th><th>Posledný pohyb</th><th></th></tr></thead>
		<tbody>
		<?php if ( ! $wq->posts ) : ?><tr><td colspan="9">Žiadne produkty. Filtre okrem Všetky ukazujú len produkty so zapnutým „Spravovať zásoby“.</td></tr><?php endif; ?>
		<?php foreach ( $wq->posts as $pid ) : ?>
			<?php
			$p = wc_get_product( $pid );
			if ( ! $p ) {
				continue;
			}
			// Variant so „zásobu spravuje hlavný produkt“: zásoba aj nákupná cena sú pri hlavnom produkte.
			$trk    = $p->managing_stock();
			$sid    = $trk ? jsk_stock_id( $p ) : $pid;
			$shared = $trk && $sid !== (int) $pid;
			$st     = (float) $p->get_stock_quantity();
			$cost   = jsk_cost( $sid );
			$last = jsk_last_move( $pid );
			$edit = get_edit_post_link( $p->is_type( 'variation' ) ? $p->get_parent_id() : $pid );
			?>
			<tr class="jsk-prow <?php echo ! $trk ? 'jsk-untracked' : ( $st <= 0 ? 'jsk-out' : ( $st <= $low ? 'jsk-low' : '' ) ); ?>">
				<td class="jsk-img-col"><a href="<?php echo esc_url( $edit ); ?>" tabindex="-1"><?php echo jsk_thumb( $p, 56 ); // phpcs:ignore ?></a></td>
				<td class="jsk-pname"><a href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $p->get_name() ); ?></a></td>
				<td><?php echo esc_html( $p->get_sku() ); ?></td>
				<td class="n jsk-stock"><?php echo $trk ? esc_html( jsk_qty( $st ) ) . ( $shared ? '<br><small class="jsk-muted" title="Zásobu vedie hlavný produkt – spoločná pre všetky jeho varianty.">spoločná</small>' : '' ) : '<small class="jsk-muted" title="Zásoby sa pri tomto produkte nesledujú (Zásoby → Spravovať zásoby).">' . ( $p->is_virtual() ? 'služba' : 'nesleduje sa' ) . '</small>'; ?></td>
				<td class="n jsk-price"><input type="number" class="jsk-sell" data-id="<?php echo (int) $pid; ?>" value="<?php echo esc_attr( (string) $p->get_regular_price() ); ?>" min="0" step="0.01" placeholder="cena" aria-label="Predajná cena"><?php if ( $p->is_on_sale() && '' !== (string) $p->get_sale_price() ) : ?><br><small class="jsk-sale">v akcii <?php echo esc_html( jsk_money( $p->get_sale_price() ) ); ?></small><?php endif; ?></td>
				<td class="n"><?php if ( $trk ) : ?><input type="number" class="jsk-cost" data-id="<?php echo (int) $pid; ?>"<?php echo $shared ? ' data-shared="1"' : ''; ?> data-qty="<?php echo esc_attr( max( 0, $st ) ); ?>" value="<?php echo null === $cost ? '' : esc_attr( round( $cost, 4 ) ); ?>" min="0" step="0.01" placeholder="zadať" aria-label="Nákupná cena"><?php else : ?><span class="jsk-muted">—</span><?php endif; ?></td>
				<td class="n jsk-val"><?php echo null === $cost || ! $trk ? '—' : ( $shared ? '<span title="Hodnota spoločnej zásoby sa ráta pri hlavnom produkte.">—</span>' : '' ) . ( $shared ? '' : esc_html( jsk_money( max( 0, $st ) * $cost ) ) ); ?></td>
				<td><?php echo $last ? esc_html( jsk_move_sources()[ $last['source'] ] ?? $last['source'] ) . '<br><small>' . esc_html( date_i18n( 'j. n. Y H:i', strtotime( $last['created_at'] ) ) ) . '</small>' : '—'; ?></td>
				<td><a href="<?php echo esc_url( jsk_url( 'j-sklad-moves', array( 'product' => $pid ) ) ); ?>">História</a></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<p class="description">Predajnú aj nákupnú cenu zmeníš priamo v tabuľke – uloží sa po opustení poľa alebo klávesom Enter. Predajná cena sa hneď prejaví v e-shope (akciová cena ostáva, ak je nastavená). Zmena nákupnej ceny sa zapíše do Pohybov; pri príjemke s cenou sa prepočíta automaticky. Nákupná cena sa zadáva len pri produktoch so sledovaním zásob.</p>
	<script>
	(function(){
		var nonce='<?php echo esc_js( wp_create_nonce( 'jsk_cost' ) ); ?>';
		function fmt(v){return v.toLocaleString('sk-SK',{minimumFractionDigits:2,maximumFractionDigits:2})+' €';}
		function save(inp){
			if(inp.value===inp.getAttribute('data-saved')){return;}
			var sell=inp.classList.contains('jsk-sell');
			var f=new FormData();f.append('action',sell?'jsk_set_price':'jsk_set_cost');f.append('_ajax_nonce',nonce);f.append('id',inp.dataset.id);f.append(sell?'price':'cost',inp.value);
			inp.classList.remove('jsk-ok','jsk-bad');inp.disabled=true;
			fetch(ajaxurl,{method:'POST',body:f,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
				inp.disabled=false;
				if(j&&j.success){inp.setAttribute('data-saved',inp.value);inp.classList.add('jsk-ok');if(!sell&&!inp.dataset.shared){var c=inp.closest('tr').querySelector('.jsk-val');c.textContent=inp.value===''?'—':fmt(parseFloat(inp.dataset.qty)*parseFloat(inp.value));}}
				else{inp.classList.add('jsk-bad');alert((j&&j.data)||'Nepodarilo sa uložiť.');}
			}).catch(function(){inp.disabled=false;inp.classList.add('jsk-bad');});
		}
		document.querySelectorAll('.jsk-cost, .jsk-sell').forEach(function(inp){
			var cls=inp.classList.contains('jsk-sell')?'.jsk-sell':'.jsk-cost';
			inp.setAttribute('data-saved',inp.value);
			inp.addEventListener('change',function(){save(inp);});
			inp.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();save(inp);var all=[].slice.call(document.querySelectorAll(cls)),i=all.indexOf(inp);if(all[i+1]){all[i+1].focus();}}});
		});
	})();
	</script>
	<?php
	if ( $wq->max_num_pages > 1 ) {
		echo '<p class="jsk-pages">' . paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $pg, 'total' => (int) $wq->max_num_pages ) ) . '</p>'; // phpcs:ignore
	}
	echo '</div>';
}

/* ---------------------------------------------------------------- Doklady */

function jsk_render_docs() {
	if ( ! current_user_can( JSK_CAP ) ) {
		return;
	}
	if ( ! empty( $_GET['new'] ) || ! empty( $_GET['edit'] ) ) { // phpcs:ignore
		jsk_render_doc_form();
		return;
	}
	if ( ! empty( $_GET['view'] ) ) { // phpcs:ignore
		jsk_render_doc_view( (int) $_GET['view'] ); // phpcs:ignore
		return;
	}
	global $wpdb;
	$type  = isset( jsk_doc_types()[ jsk_get( 'type' ) ] ) ? jsk_get( 'type' ) : '';
	$pg    = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 ); // phpcs:ignore
	$where = $type ? $wpdb->prepare( 'WHERE type = %s', $type ) : '';
	$total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . jsk_t( 'docs' ) . " $where" ); // phpcs:ignore
	$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT d.*, (SELECT COUNT(*) FROM ' . jsk_t( 'doc_items' ) . ' i WHERE i.doc_id = d.id) AS n FROM ' . jsk_t( 'docs' ) . " d $where ORDER BY (status = 'draft') DESC, doc_date DESC, id DESC LIMIT 50 OFFSET %d", ( $pg - 1 ) * 50 ), ARRAY_A ); // phpcs:ignore
	jsk_head( 'Doklady' );
	echo '<a class="page-title-action" href="' . esc_url( jsk_url( 'j-sklad-docs', array( 'new' => 'receipt' ) ) ) . '">Nová príjemka</a>';
	echo '<a class="page-title-action" href="' . esc_url( jsk_url( 'j-sklad-docs', array( 'new' => 'issue' ) ) ) . '">Nová výdajka</a><hr class="wp-header-end">';
	jsk_notice();
	echo '<ul class="subsubsub jsk-filters"><li><a class="' . ( '' === $type ? 'current' : '' ) . '" href="' . esc_url( jsk_url( 'j-sklad-docs' ) ) . '">Všetky</a></li>';
	foreach ( jsk_doc_types() as $k => $t ) {
		echo ' | <li><a class="' . ( $type === $k ? 'current' : '' ) . '" href="' . esc_url( jsk_url( 'j-sklad-docs', array( 'type' => $k ) ) ) . '">' . esc_html( $t[0] ) . '</a></li>';
	}
	echo '</ul>';
	?>
	<table class="widefat striped jsk-table">
		<thead><tr><th>Číslo</th><th>Druh</th><th>Dátum</th><th>Dodávateľ / dôvod</th><th class="n">Položky</th><th class="n">Suma (nákup)</th><th>Stav</th></tr></thead>
		<tbody>
		<?php if ( ! $rows ) : ?><tr><td colspan="7">Zatiaľ žiadne doklady.</td></tr><?php endif; ?>
		<?php foreach ( $rows as $r ) : ?>
			<?php $link = 'draft' === $r['status'] ? jsk_url( 'j-sklad-docs', array( 'edit' => $r['id'] ) ) : jsk_url( 'j-sklad-docs', array( 'view' => $r['id'] ) ); ?>
			<tr>
				<td><a href="<?php echo esc_url( $link ); ?>"><strong><?php echo esc_html( $r['number'] ? $r['number'] : 'Koncept #' . $r['id'] ); ?></strong></a></td>
				<td><?php echo esc_html( jsk_doc_types()[ $r['type'] ][0] ?? $r['type'] ); ?></td>
				<td><?php echo esc_html( date_i18n( 'j. n. Y', strtotime( $r['doc_date'] ) ) ); ?></td>
				<td><?php echo esc_html( trim( $r['partner'] . ( $r['partner_doc'] ? ' (' . $r['partner_doc'] . ')' : '' ) . ( $r['reason'] ? ' ' . $r['reason'] : '' ) ) ); ?></td>
				<td class="n"><?php echo (int) $r['n']; ?></td>
				<td class="n"><?php echo (float) $r['total'] ? esc_html( jsk_money( $r['total'] ) ) : '—'; ?></td>
				<td><span class="jsk-st jsk-st-<?php echo esc_attr( $r['status'] ); ?>"><?php echo esc_html( jsk_status_label( $r['status'] ) ); ?></span></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php
	if ( $total > 50 ) {
		echo '<p class="jsk-pages">' . paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $pg, 'total' => (int) ceil( $total / 50 ) ) ) . '</p>'; // phpcs:ignore
	}
	echo '</div>';
}

function jsk_render_doc_form() {
	$id   = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0; // phpcs:ignore
	$d    = $id ? jsk_doc( $id ) : null;
	$type = $d ? $d['type'] : ( in_array( jsk_get( 'new' ), array( 'receipt', 'issue' ), true ) ? jsk_get( 'new' ) : 'receipt' );
	if ( $d && 'draft' !== $d['status'] ) {
		jsk_render_doc_view( $id );
		return;
	}
	$rec = 'receipt' === $type;
	jsk_head( ( $id ? 'Upraviť: ' : 'Nová ' ) . mb_strtolower( jsk_doc_types()[ $type ][0] ) );
	echo '<hr class="wp-header-end">';
	jsk_notice();
	$items = $d ? $d['items'] : array();
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="jsk-doc-form" class="jsk-panel">
		<?php wp_nonce_field( 'jsk_doc' ); ?>
		<input type="hidden" name="action" value="jsk_doc_save">
		<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
		<input type="hidden" name="form_token" value="<?php echo esc_attr( wp_generate_uuid4() ); ?>">
		<input type="hidden" name="type" value="<?php echo esc_attr( $type ); ?>">
		<div class="jsk-head-fields">
			<label>Dátum<br><input type="date" name="doc_date" value="<?php echo esc_attr( $d ? $d['doc_date'] : current_time( 'Y-m-d' ) ); ?>" required></label>
			<?php if ( $rec ) : ?>
				<label>Dodávateľ<br><input type="text" name="partner" value="<?php echo esc_attr( $d['partner'] ?? '' ); ?>" class="regular-text"></label>
				<label>Číslo dokladu dodávateľa<br><input type="text" name="partner_doc" value="<?php echo esc_attr( $d['partner_doc'] ?? '' ); ?>" placeholder="napr. faktúra / dodací list"></label>
			<?php else : ?>
				<label>Dôvod výdaja<br><select name="reason"><?php foreach ( jsk_reasons() as $r ) : ?><option <?php selected( $d['reason'] ?? '', $r ); ?>><?php echo esc_html( $r ); ?></option><?php endforeach; ?></select></label>
			<?php endif; ?>
			<label class="jsk-wide">Poznámka<br><input type="text" name="note" value="<?php echo esc_attr( $d['note'] ?? '' ); ?>" class="large-text"></label>
		</div>
		<div class="jsk-search">
			<input type="search" id="jsk-find" placeholder="Pridať produkt: názov alebo SKU (funguje aj čítačka čiarových kódov – Enter)" autocomplete="off">
			<ul id="jsk-results" hidden></ul>
		</div>
		<table class="widefat jsk-table jsk-items">
			<thead><tr><th>Produkt</th><th>SKU</th><th class="n">Na sklade</th><th class="n">Množstvo</th><?php if ( $rec ) : ?><th class="n">Nákupná cena / ks</th><?php endif; ?><th></th></tr></thead>
			<tbody id="jsk-rows">
				<?php foreach ( $items as $it ) : ?>
					<?php $p = wc_get_product( $it['product_id'] ); ?>
					<tr>
						<td><input type="hidden" name="items[product_id][]" value="<?php echo (int) $it['product_id']; ?>"><?php echo esc_html( $it['name'] ); ?></td>
						<td><?php echo esc_html( $it['sku'] ); ?></td>
						<td class="n"><?php echo $p ? esc_html( jsk_qty( $p->get_stock_quantity() ) ) : '—'; ?></td>
						<td class="n"><input type="number" name="items[qty][]" value="<?php echo esc_attr( (float) $it['qty'] ); ?>" min="0" step="1" class="small-text"></td>
						<?php if ( $rec ) : ?><td class="n"><input type="number" name="items[price][]" value="<?php echo null === $it['price'] ? '' : esc_attr( (float) $it['price'] ); ?>" min="0" step="0.0001" class="small-text"></td><?php endif; ?>
						<td><button type="button" class="button-link jsk-del" aria-label="Odstrániť">✕</button></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description"><?php echo $rec ? 'Nákupná cena je nepovinná; ak ju zadáte, prepočíta sa priemerná nákupná cena produktu.' : 'Výdaj nesmie znížiť zásobu pod nulu (okrem produktov s povoleným predajom do mínusu).'; ?></p>
		<p class="jsk-actions">
			<button class="button" name="do" value="save">Uložiť koncept</button>
			<button class="button button-primary" name="do" value="post" onclick="return confirm('Zaúčtovať doklad? Zásoby produktov sa zmenia a doklad dostane číslo.');">Uložiť a zaúčtovať</button>
			<?php if ( $id ) : ?><a class="button-link jsk-danger" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=jsk_doc_delete&id=' . $id ), 'jsk_doc_delete_' . $id ) ); ?>" onclick="return confirm('Zmazať koncept?');">Zmazať koncept</a><?php endif; ?>
			<a class="button-link" href="<?php echo esc_url( jsk_url( 'j-sklad-docs' ) ); ?>">Späť na doklady</a>
		</p>
	</form>
	<script>
	(function(){
		var rec=<?php echo $rec ? 'true' : 'false'; ?>,find=document.getElementById('jsk-find'),res=document.getElementById('jsk-results'),rows=document.getElementById('jsk-rows'),t=null;
		function esc(s){return String(s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
		function add(p){
			var ex=rows.querySelector('input[name="items[product_id][]"][value="'+p.id+'"]');
			if(ex){var q=ex.closest('tr').querySelector('input[name="items[qty][]"]');q.value=(parseFloat(q.value)||0)+1;q.focus();return;}
			var tr=document.createElement('tr');
			tr.innerHTML='<td><input type="hidden" name="items[product_id][]" value="'+p.id+'">'+esc(p.name)+(p.manage?'':' <em class="jsk-warn">(nesleduje zásoby)</em>')+'</td><td>'+esc(p.sku)+'</td><td class="n">'+esc(p.stock)+'</td><td class="n"><input type="number" name="items[qty][]" value="1" min="0" step="1" class="small-text"></td>'+(rec?'<td class="n"><input type="number" name="items[price][]" value="'+(p.cost===null?'':p.cost)+'" min="0" step="0.0001" class="small-text"></td>':'')+'<td><button type="button" class="button-link jsk-del" aria-label="Odstrániť">✕</button></td>';
			rows.appendChild(tr);tr.querySelector('input[name="items[qty][]"]').focus();
		}
		function search(term,exact){
			var f=new FormData();f.append('action','jsk_search');f.append('term',term);f.append('_ajax_nonce','<?php echo esc_js( wp_create_nonce( 'jsk_search' ) ); ?>');
			fetch(ajaxurl,{method:'POST',body:f,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
				var list=(j&&j.success)?j.data:[];res.innerHTML='';
				if(exact){var hit=list.filter(function(p){return p.sku&&p.sku.toLowerCase()===term.toLowerCase();});if(hit.length===1||list.length===1){add(hit[0]||list[0]);find.value='';res.hidden=true;return;}}
				list.forEach(function(p){var li=document.createElement('li');li.innerHTML=(p.img?'<img src="'+esc(p.img)+'" alt="" width="32" height="32"> ':'')+'<strong>'+esc(p.name)+'</strong> <small>'+esc(p.sku)+' · na sklade '+esc(p.stock)+'</small>';li.onclick=function(){add(p);find.value='';res.hidden=true;find.focus();};res.appendChild(li);});
				if(!list.length){res.innerHTML='<li class="jsk-none">Nič sa nenašlo.</li>';}
				res.hidden=false;
			});
		}
		find.addEventListener('input',function(){clearTimeout(t);var v=find.value.trim();if(v.length<2){res.hidden=true;return;}t=setTimeout(function(){search(v,false);},250);});
		find.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();var v=find.value.trim();if(v){clearTimeout(t);search(v,true);}}});
		rows.addEventListener('click',function(e){if(e.target.classList.contains('jsk-del')){e.target.closest('tr').remove();}});
	})();
	</script>
	</div>
	<?php
}

function jsk_render_doc_view( $id ) {
	$d = jsk_doc( $id );
	jsk_head( $d ? jsk_doc_types()[ $d['type'] ][0] . ' ' . $d['number'] : 'Doklad' );
	echo '<hr class="wp-header-end">';
	jsk_notice();
	if ( ! $d ) {
		echo '<p>Doklad neexistuje.</p></div>';
		return;
	}
	$sum = 0;
	?>
	<div class="jsk-panel">
		<p class="jsk-meta">
			<span>Dátum: <strong><?php echo esc_html( date_i18n( 'j. n. Y', strtotime( $d['doc_date'] ) ) ); ?></strong></span>
			<?php if ( $d['partner'] ) : ?><span>Dodávateľ: <strong><?php echo esc_html( $d['partner'] ); ?></strong><?php echo $d['partner_doc'] ? ' (' . esc_html( $d['partner_doc'] ) . ')' : ''; ?></span><?php endif; ?>
			<?php if ( $d['reason'] ) : ?><span>Dôvod: <strong><?php echo esc_html( $d['reason'] ); ?></strong></span><?php endif; ?>
			<span>Stav: <span class="jsk-st jsk-st-<?php echo esc_attr( $d['status'] ); ?>"><?php echo esc_html( jsk_status_label( $d['status'] ) ); ?></span></span>
			<?php $u = get_userdata( (int) $d['user_id'] ); ?><span>Vystavil: <?php echo esc_html( $u ? $u->display_name : '—' ); ?></span>
		</p>
		<?php if ( $d['note'] ) : ?><p><?php echo esc_html( $d['note'] ); ?></p><?php endif; ?>
		<table class="widefat striped jsk-table">
			<thead><tr><th>Produkt</th><th>SKU</th><th class="n">Množstvo</th><th class="n">Nákupná cena / ks</th><th class="n">Spolu</th></tr></thead>
			<tbody>
			<?php foreach ( $d['items'] as $it ) : ?>
				<?php $line = null === $it['price'] ? null : (float) $it['qty'] * (float) $it['price']; $sum += (float) $line; ?>
				<tr><td><?php echo esc_html( $it['name'] ); ?></td><td><?php echo esc_html( $it['sku'] ); ?></td><td class="n"><?php echo esc_html( jsk_qty( $it['qty'] ) ); ?></td><td class="n"><?php echo esc_html( jsk_money( $it['price'] ) ); ?></td><td class="n"><?php echo esc_html( jsk_money( $line ) ); ?></td></tr>
			<?php endforeach; ?>
			</tbody>
			<tfoot><tr><th colspan="4">Spolu</th><th class="n"><?php echo esc_html( jsk_money( $sum ) ); ?></th></tr></tfoot>
		</table>
		<p class="jsk-actions">
			<a class="button" href="<?php echo esc_url( jsk_url( 'j-sklad-docs' ) ); ?>">← Späť na doklady</a>
			<a class="button" href="<?php echo esc_url( jsk_url( 'j-sklad-moves', array( 'doc' => $d['id'] ) ) ); ?>">Pohyby dokladu</a>
			<?php if ( 'posting' === $d['status'] ) : ?>
				<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=jsk_doc_post&id=' . $d['id'] ), 'jsk_doc_post_' . $d['id'] ) ); ?>">Dokončiť zaúčtovanie</a>
			<?php endif; ?>
			<?php if ( in_array( $d['status'], array( 'posted', 'cancelling' ), true ) && 'opening' !== $d['type'] ) : ?>
				<a class="button jsk-danger" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=jsk_doc_cancel&id=' . $d['id'] ), 'jsk_doc_cancel_' . $d['id'] ) ); ?>" onclick="return confirm('Stornovať doklad <?php echo esc_js( $d['number'] ); ?>? Zásoby sa vrátia, doklad ostane v evidencii.');">Stornovať</a>
			<?php endif; ?>
		</p>
	</div>
	</div>
	<?php
}

function jsk_back_err( $url, $err ) {
	set_transient( 'jsk_err_' . get_current_user_id(), $err, 120 );
	wp_safe_redirect( add_query_arg( 'jsk_msg', 'err', $url ) );
	exit;
}

add_action( 'admin_post_jsk_doc_save', function () {
	if ( ! current_user_can( JSK_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jsk_doc' );
	$in    = wp_unslash( $_POST );
	$items = array();
	$pids  = (array) ( $in['items']['product_id'] ?? array() );
	foreach ( $pids as $i => $pid ) {
		$items[] = array( 'product_id' => (int) $pid, 'qty' => $in['items']['qty'][ $i ] ?? 0, 'price' => $in['items']['price'][ $i ] ?? '' );
	}
	// Nový doklad odoslaný dvakrát (dvojklik, opakované odoslanie) – druhý raz sa nevytvorí ďalší koncept.
	$tok = (int) ( $in['id'] ?? 0 ) ? '' : preg_replace( '/[^a-f0-9-]/', '', strtolower( (string) ( $in['form_token'] ?? '' ) ) );
	if ( $tok ) {
		jsk_lock( 'jsk_tok', 10 );
		$prev = (int) get_transient( 'jsk_tok_' . md5( $tok ) );
		if ( $prev ) {
			jsk_unlock( 'jsk_tok' );
			wp_safe_redirect( jsk_url( 'j-sklad-docs', array( 'view' => $prev ) ) );
			exit;
		}
	}
	$id = jsk_save_draft( (int) ( $in['id'] ?? 0 ), sanitize_key( is_string( $in['type'] ?? null ) ? $in['type'] : 'receipt' ), $in, $items );
	if ( $tok ) {
		if ( ! is_wp_error( $id ) ) {
			set_transient( 'jsk_tok_' . md5( $tok ), (int) $id, DAY_IN_SECONDS );
		}
		jsk_unlock( 'jsk_tok' );
	}
	if ( is_wp_error( $id ) ) {
		jsk_back_err( jsk_url( 'j-sklad-docs' ), $id->get_error_message() );
	}
	if ( ! empty( $GLOBALS['jsk_skipped'] ) ) {
		// Pri vynechaných riadkoch sa nezaúčtuje – nech to používateľ vidí a skontroluje.
		set_transient( 'jsk_err_' . get_current_user_id(), implode( ', ', $GLOBALS['jsk_skipped'] ), 120 );
		wp_safe_redirect( jsk_url( 'j-sklad-docs', array( 'edit' => $id, 'jsk_msg' => 'skipped' ) ) );
		exit;
	}
	if ( 'post' === ( $in['do'] ?? '' ) ) {
		$r = jsk_post_doc( $id );
		if ( is_wp_error( $r ) ) {
			jsk_back_err( jsk_url( 'j-sklad-docs', array( 'edit' => $id ) ), $r->get_error_message() );
		}
		wp_safe_redirect( jsk_url( 'j-sklad-docs', array( 'view' => $id, 'jsk_msg' => 'posted' ) ) );
		exit;
	}
	wp_safe_redirect( jsk_url( 'j-sklad-docs', array( 'edit' => $id, 'jsk_msg' => 'saved' ) ) );
	exit;
} );

add_action( 'admin_post_jsk_doc_post', function () {
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore
	if ( ! current_user_can( JSK_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jsk_doc_post_' . $id );
	$r = jsk_post_doc( $id );
	if ( is_wp_error( $r ) ) {
		jsk_back_err( jsk_url( 'j-sklad-docs', array( 'view' => $id ) ), $r->get_error_message() );
	}
	wp_safe_redirect( jsk_url( 'j-sklad-docs', array( 'view' => $id, 'jsk_msg' => 'posted' ) ) );
	exit;
} );

add_action( 'admin_post_jsk_doc_cancel', function () {
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore
	if ( ! current_user_can( JSK_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jsk_doc_cancel_' . $id );
	$r = jsk_cancel_doc( $id );
	if ( is_wp_error( $r ) ) {
		jsk_back_err( jsk_url( 'j-sklad-docs', array( 'view' => $id ) ), $r->get_error_message() );
	}
	wp_safe_redirect( jsk_url( 'j-sklad-docs', array( 'view' => $id, 'jsk_msg' => 'cancelled' ) ) );
	exit;
} );

add_action( 'admin_post_jsk_doc_delete', function () {
	global $wpdb;
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore
	if ( ! current_user_can( JSK_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jsk_doc_delete_' . $id );
	$d = jsk_doc( $id );
	if ( $d && 'draft' === $d['status'] ) {
		$wpdb->delete( jsk_t( 'doc_items' ), array( 'doc_id' => $id ) );
		$wpdb->delete( jsk_t( 'docs' ), array( 'id' => $id ) );
	}
	wp_safe_redirect( jsk_url( 'j-sklad-docs', array( 'jsk_msg' => 'deleted' ) ) );
	exit;
} );

// Vyhľadanie produktov pre doklad (názov, SKU; aj varianty).
add_action( 'wp_ajax_jsk_search', function () {
	if ( ! current_user_can( JSK_CAP ) ) {
		wp_send_json_error( 'Nemáte oprávnenie.', 403 );
	}
	check_ajax_referer( 'jsk_search' );
	$term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
	$ids  = '' === $term ? array() : WC_Data_Store::load( 'product' )->search_products( $term, '', true, true, 30 );
	$out  = array();
	foreach ( array_slice( array_filter( array_map( 'intval', (array) $ids ) ), 0, 30 ) as $id ) {
		$p = wc_get_product( $id );
		if ( ! $p || $p->is_type( 'grouped' ) || $p->is_type( 'external' ) || ( $p->is_type( 'variable' ) && ! $p->managing_stock() ) ) {
			continue; // variabilný produkt bez spoločnej zásoby – vyberá sa konkrétny variant
		}
		$img = $p->get_image_id() ? $p->get_image_id() : ( $p->get_parent_id() ? (int) get_post_thumbnail_id( $p->get_parent_id() ) : 0 );
		$out[] = array(
			'id'     => $id,
			'name'   => wp_strip_all_tags( $p->get_name() ),
			'sku'    => (string) $p->get_sku(),
			'stock'  => $p->managing_stock() ? jsk_qty( $p->get_stock_quantity() ) : '—',
			'manage' => $p->managing_stock(),
			'cost'   => jsk_cost( jsk_stock_id( $p ) ),
			'img'    => $img ? (string) wp_get_attachment_image_url( $img, 'thumbnail' ) : '',
		);
	}
	wp_send_json_success( $out );
} );

// Ručné zadanie nákupnej ceny v Prehľade skladu (zapíše sa do knihy ako zmena ceny, zásoba sa nemení).
/* Predajná (bežná) cena priamo z Prehľadu skladu. */
add_action( 'wp_ajax_jsk_set_price', function () {
	if ( ! current_user_can( JSK_CAP ) ) {
		wp_send_json_error( 'Nemáte oprávnenie.', 403 );
	}
	check_ajax_referer( 'jsk_cost' );
	$p   = wc_get_product( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 );
	$raw = isset( $_POST['price'] ) ? trim( str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['price'] ) ) ) ) : '';
	if ( ! $p || $p->is_type( array( 'variable', 'grouped' ) ) ) {
		wp_send_json_error( 'Produkt neexistuje alebo cenu nemá (variabilný produkt má ceny pri variantoch).' );
	}
	if ( '' !== $raw && ( ! is_numeric( $raw ) || (float) $raw < 0 ) ) {
		wp_send_json_error( 'Zadajte kladné číslo.' );
	}
	$price = '' === $raw ? '' : wc_format_decimal( $raw, wc_get_price_decimals() );
	$p->set_regular_price( $price );
	if ( '' !== $price && '' !== (string) $p->get_sale_price() && (float) $p->get_sale_price() >= (float) $price ) {
		$p->set_sale_price( '' ); // akcia vyššia ako nová cena nedáva zmysel
	}
	$p->save();
	if ( function_exists( 'wc_delete_product_transients' ) ) {
		wc_delete_product_transients( $p->get_id() );
	}
	wp_send_json_success( array( 'price' => $price ) );
} );

add_action( 'wp_ajax_jsk_set_cost', function () {
	if ( ! current_user_can( JSK_CAP ) ) {
		wp_send_json_error( 'Nemáte oprávnenie.', 403 );
	}
	check_ajax_referer( 'jsk_cost' );
	$id  = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	$raw = isset( $_POST['cost'] ) ? trim( str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['cost'] ) ) ) ) : '';
	$p   = wc_get_product( $id );
	if ( ! $p || ! $p->managing_stock() ) {
		wp_send_json_error( 'Produkt neexistuje alebo nemá sledovanie zásob.' );
	}
	$id = jsk_stock_id( $p );
	if ( '' !== $raw && ( ! is_numeric( $raw ) || (float) $raw < 0 ) ) {
		wp_send_json_error( 'Zadajte kladné číslo.' );
	}
	$old = jsk_cost( $id );
	if ( '' === $raw ) {
		delete_post_meta( $id, '_jsk_cost' );
	} else {
		update_post_meta( $id, '_jsk_cost', round( (float) $raw, 4 ) );
	}
	$new = '' === $raw ? null : round( (float) $raw, 4 );
	if ( $old !== $new ) {
		jsk_ensure_opening( $id, jsk_db_stock( $id ) );
		$last = jsk_last_move( $id );
		jsk_log_move( $id, 0, $last && null !== $last['stock_after'] ? $last['stock_after'] : jsk_db_stock( $id ), 'cost', array( 'cost' => $new, 'note' => 'Nákupná cena: ' . jsk_money( $old ) . ' → ' . jsk_money( $new ) ) );
	}
	wp_send_json_success( array( 'cost' => $new ) );
} );

/* ---------------------------------------------------------------- Pohyby */

function jsk_render_moves() {
	global $wpdb;
	if ( ! current_user_can( JSK_CAP ) ) {
		return;
	}
	$pid  = isset( $_GET['product'] ) ? (int) $_GET['product'] : 0; // phpcs:ignore
	$doc  = isset( $_GET['doc'] ) ? (int) $_GET['doc'] : 0; // phpcs:ignore
	$src  = isset( jsk_move_sources()[ jsk_get( 'src' ) ] ) ? jsk_get( 'src' ) : '';
	$from = preg_match( '/^\d{4}-\d{2}-\d{2}$/', jsk_get( 'from' ) ) ? jsk_get( 'from' ) : '';
	$to   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', jsk_get( 'to' ) ) ? jsk_get( 'to' ) : '';
	$pg   = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 ); // phpcs:ignore
	$w    = array( '1=1' );
	if ( $pid ) {
		$kids = array_map( 'intval', get_children( array( 'post_parent' => $pid, 'post_type' => 'product_variation', 'fields' => 'ids' ) ) );
		$w[]  = 'm.product_id IN (' . implode( ',', array_merge( array( $pid ), $kids ) ) . ')';
	}
	if ( $doc ) {
		$w[] = $wpdb->prepare( 'm.doc_id = %d', $doc );
	}
	if ( $src ) {
		$w[] = $wpdb->prepare( 'm.source = %s', $src );
	}
	if ( $from ) {
		$w[] = $wpdb->prepare( 'm.created_at >= %s', $from . ' 00:00:00' );
	}
	if ( $to ) {
		$w[] = $wpdb->prepare( 'm.created_at <= %s', $to . ' 23:59:59' );
	}
	$w     = implode( ' AND ', $w );
	$total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . jsk_t( 'moves' ) . " m WHERE $w" ); // phpcs:ignore
	$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT m.*, d.number AS doc_number, d.type AS doc_type FROM ' . jsk_t( 'moves' ) . ' m LEFT JOIN ' . jsk_t( 'docs' ) . " d ON d.id = m.doc_id WHERE $w ORDER BY m.id DESC LIMIT 100 OFFSET %d", ( $pg - 1 ) * 100 ), ARRAY_A ); // phpcs:ignore
	$prod  = $pid ? wc_get_product( $pid ) : null;
	jsk_head( 'Pohyby zásob' . ( $prod ? ' – ' . $prod->get_name() : '' ) );
	echo '<hr class="wp-header-end">';
	?>
	<form method="get" class="jsk-filter">
		<input type="hidden" name="page" value="j-sklad-moves">
		<?php if ( $pid ) : ?><input type="hidden" name="product" value="<?php echo (int) $pid; ?>"><?php endif; ?>
		<?php if ( $doc ) : ?><input type="hidden" name="doc" value="<?php echo (int) $doc; ?>"><?php endif; ?>
		<select name="src"><option value="">Všetky pohyby</option><?php foreach ( jsk_move_sources() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $src, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
		<label>od <input type="date" name="from" value="<?php echo esc_attr( $from ); ?>"></label>
		<label>do <input type="date" name="to" value="<?php echo esc_attr( $to ); ?>"></label>
		<button class="button">Filtrovať</button>
		<?php if ( $pid || $doc || $src || $from || $to ) : ?><a class="button-link" href="<?php echo esc_url( jsk_url( 'j-sklad-moves' ) ); ?>">Zrušiť filter</a><?php endif; ?>
		<span class="jsk-total"><?php echo esc_html( number_format_i18n( $total ) ); ?> pohybov</span>
	</form>
	<table class="widefat striped jsk-table">
		<thead><tr><th>Dátum</th><th>Produkt</th><th>Pohyb</th><th class="n">Zmena</th><th class="n">Stav po</th><th>Doklad / objednávka</th><th>Poznámka</th><th>Kto</th></tr></thead>
		<tbody>
		<?php if ( ! $rows ) : ?><tr><td colspan="8">Žiadne pohyby.</td></tr><?php endif; ?>
		<?php foreach ( $rows as $r ) : ?>
			<?php
			$p   = wc_get_product( $r['product_id'] );
			$ref = '';
			if ( $r['doc_id'] ) {
				$ref = '<a href="' . esc_url( jsk_url( 'j-sklad-docs', array( 'view' => $r['doc_id'] ) ) ) . '">' . esc_html( $r['doc_number'] ? $r['doc_number'] : '#' . $r['doc_id'] ) . '</a>';
			} elseif ( $r['order_id'] && ( $o = wc_get_order( $r['order_id'] ) ) ) {
				$ref = '<a href="' . esc_url( $o->get_edit_order_url() ) . '">#' . esc_html( $o->get_order_number() ) . '</a>';
			}
			$u = $r['user_id'] ? get_userdata( (int) $r['user_id'] ) : null;
			?>
			<tr>
				<td><?php echo esc_html( date_i18n( 'j. n. Y H:i', strtotime( $r['created_at'] ) ) ); ?></td>
				<td><?php echo $p ? '<a href="' . esc_url( jsk_url( 'j-sklad-moves', array( 'product' => $r['product_id'] ) ) ) . '">' . esc_html( $p->get_name() ) . '</a>' : '#' . (int) $r['product_id']; ?></td>
				<td><?php echo esc_html( jsk_move_sources()[ $r['source'] ] ?? $r['source'] ); ?></td>
				<td class="n <?php echo (float) $r['qty'] < 0 ? 'jsk-neg' : 'jsk-pos'; ?>"><?php echo esc_html( ( (float) $r['qty'] > 0 ? '+' : '' ) . jsk_qty( $r['qty'] ) ); ?></td>
				<td class="n"><?php echo null === $r['stock_after'] ? '—' : esc_html( jsk_qty( $r['stock_after'] ) ); ?></td>
				<td><?php echo $ref ? $ref : '—'; // phpcs:ignore ?></td>
				<td><?php echo esc_html( $r['note'] ); ?></td>
				<td><?php echo esc_html( $u ? $u->display_name : ( 'order' === $r['source'] || 'restore' === $r['source'] ? 'zákazník / systém' : 'systém' ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php
	if ( $total > 100 ) {
		echo '<p class="jsk-pages">' . paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $pg, 'total' => (int) ceil( $total / 100 ) ) ) . '</p>'; // phpcs:ignore
	}
	echo '</div>';
}

/* ---------------------------------------------------------------- Nastavenia */

function jsk_render_settings() {
	if ( ! current_user_can( JSK_CAP ) ) {
		return;
	}
	$s = jsk_settings();
	jsk_head( 'Nastavenia' );
	echo '<hr class="wp-header-end">';
	jsk_notice();
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="jsk-panel">
		<?php wp_nonce_field( 'jsk_settings' ); ?>
		<input type="hidden" name="action" value="jsk_settings">
		<table class="form-table" role="presentation">
			<tr><th>Formát čísla príjemky</th><td><input type="text" name="format_receipt" value="<?php echo esc_attr( $s['format_receipt'] ); ?>"><p class="description">{RRRR} = rok, {RR} = rok dvojmiestne, {nnn} / {nnnn} = poradie v roku → PR2026001.</p></td></tr>
			<tr><th>Formát čísla výdajky</th><td><input type="text" name="format_issue" value="<?php echo esc_attr( $s['format_issue'] ); ?>"></td></tr>
			<tr><th>Formát otváracej príjemky</th><td><input type="text" name="format_opening" value="<?php echo esc_attr( $s['format_opening'] ); ?>"></td></tr>
			<tr><th>Hranica „dochádza“</th><td><input type="number" name="low_stock" min="0" step="any" value="<?php echo esc_attr( $s['low_stock'] ); ?>" class="small-text"> ks</td></tr>
			<tr><th>Obrázky produktov</th><td><label><input type="checkbox" name="media" value="1" <?php checked( ! empty( $s['media'] ) ); ?>> Obrázky produktov spravuje J-Sklad</label><p class="description">Hlavné obrázky a galérie produktov sa spravujú v J-Sklad → Obrázky a Knižnica multimédií ich nezobrazuje (ostanú v nej len obrázky webu). Súbory sa nepresúvajú – po vypnutí je Knižnica opäť kompletná.</p></td></tr>
			<tr><th>Dôvody výdaja</th><td><input type="text" name="reasons" value="<?php echo esc_attr( $s['reasons'] ); ?>" class="large-text"><p class="description">Oddelené čiarkou.</p></td></tr>
		</table>
		<?php submit_button( 'Uložiť nastavenia' ); ?>
	</form>
	</div>
	<?php
}

add_action( 'admin_post_jsk_settings', function () {
	if ( ! current_user_can( JSK_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jsk_settings' );
	$in  = wp_unslash( $_POST );
	$out = jsk_settings();
	foreach ( array( 'format_receipt', 'format_issue', 'format_opening' ) as $k ) {
		$v         = sanitize_text_field( $in[ $k ] ?? '' );
		$out[ $k ] = false !== strpos( $v, '{n' ) ? $v : jsk_defaults()[ $k ];
	}
	$out['low_stock'] = max( 0, (float) ( $in['low_stock'] ?? 0 ) );
	$out['reasons']   = sanitize_text_field( $in['reasons'] ?? '' );
	$out['media']     = empty( $in['media'] ) ? 0 : 1;
	update_option( 'jsk_settings', $out, false );
	wp_safe_redirect( jsk_url( 'j-sklad-settings', array( 'jsk_msg' => 'settings' ) ) );
	exit;
} );
