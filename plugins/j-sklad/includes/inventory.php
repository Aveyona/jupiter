<?php
/**
 * Inventúra – súpis skutočného stavu skladu.
 *
 * Postup: Nová inventúra (všetky produkty so sledovaním zásob alebo kategória) → zapamätá sa stav v evidencii →
 * zadávaš spočítané množstvá (aj čítačkou čiarových kódov, ukladá sa priebežne) → Zaúčtovať: zásoba každej
 * spočítanej položky sa nastaví na spočítané množstvo, rozdiel (manko / prebytok) sa zapíše do knihy pohybov.
 * Rozdiel sa počíta voči zásobe v okamihu zaúčtovania – predaje počas inventúry sa tak nezapočítajú dvakrát.
 * Nespočítané položky ostanú bez zmeny (alebo sa dajú potvrdiť podľa evidencie). Inventúra sa dá stornovať.
 *
 * Inventúra je doklad J-Skladu (druh „inventory“, číslo IN2026001); položka: expected = stav v evidencii pri
 * založení, counted = spočítané, qty = zaúčtovaný rozdiel, price = priemerná nákupná cena pri zaúčtovaní.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_submenu_page( 'j-sklad', 'Inventúra', 'Inventúra', JSK_CAP, 'j-sklad-inventory', 'jsk_render_inventory', 3 );
}, 15 );

/** Produkty, ktoré nesú zásobu (bez duplicít variantov so zásobou hlavného produktu), voliteľne z kategórie. */
function jsk_inv_stock_products( $cat = 0 ) {
	$args = array( 'limit' => -1, 'return' => 'ids', 'status' => array( 'publish', 'private', 'draft', 'pending' ) );
	if ( $cat ) {
		$term = get_term( (int) $cat, 'product_cat' );
		if ( ! $term || is_wp_error( $term ) ) {
			return array();
		}
		$args['category'] = array( $term->slug );
	}
	$parents = wc_get_products( $args );
	$vars    = $parents ? wc_get_products( array( 'limit' => -1, 'return' => 'ids', 'type' => 'variation', 'parent' => $parents ) ) : array();
	$out     = array();
	foreach ( array_merge( $parents, $vars ) as $id ) {
		$p = wc_get_product( $id );
		if ( ! $p || ! $p->managing_stock() ) {
			continue;
		}
		$sid = jsk_stock_id( $p );
		if ( $sid && ! isset( $out[ $sid ] ) ) {
			$out[ $sid ] = $sid === (int) $id ? $p : wc_get_product( $sid );
		}
	}
	$out = array_filter( $out );
	uasort( $out, fn( $a, $b ) => strnatcasecmp( $a->get_name(), $b->get_name() ) );
	return $out;
}

/** Založenie inventúry: koncept s položkami a stavom v evidencii. */
function jsk_inv_create( $cat, $note ) {
	global $wpdb;
	$products = jsk_inv_stock_products( $cat );
	if ( ! $products ) {
		return new WP_Error( 'jsk_inv', 'Nenašli sa žiadne produkty so sledovaním zásob' . ( $cat ? ' v tejto kategórii' : '' ) . '.' );
	}
	$scope = 'Všetky produkty';
	if ( $cat ) {
		$t     = get_term( (int) $cat, 'product_cat' );
		$scope = 'Kategória: ' . ( $t && ! is_wp_error( $t ) ? $t->name : '#' . (int) $cat );
	}
	$wpdb->insert( jsk_t( 'docs' ), array(
		'type'       => 'inventory',
		'doc_date'   => current_time( 'Y-m-d' ),
		'reason'     => mb_substr( $scope, 0, 100 ),
		'note'       => sanitize_textarea_field( $note ),
		'status'     => 'draft',
		'user_id'    => get_current_user_id(),
		'created_at' => current_time( 'mysql' ),
	) );
	$id = (int) $wpdb->insert_id;
	if ( ! $id ) {
		return new WP_Error( 'jsk_inv', 'Inventúru sa nepodarilo založiť: ' . $wpdb->last_error );
	}
	foreach ( $products as $pid => $p ) {
		$st = jsk_db_stock( $pid );
		$wpdb->insert( jsk_t( 'doc_items' ), array(
			'doc_id'     => $id,
			'product_id' => $pid,
			'sku'        => (string) $p->get_sku(),
			'name'       => mb_substr( wp_strip_all_tags( $p->get_name() ), 0, 255 ),
			'qty'        => 0,
			'price'      => jsk_cost( $pid ),
			'expected'   => null === $st ? (float) $p->get_stock_quantity() : $st,
			'counted'    => null,
		) );
	}
	return $id;
}

/** Zaúčtovanie inventúry. $zero_uncounted = nespočítané položky nastaviť na 0 (inak ostanú bez zmeny). */
function jsk_inv_post( $id, $zero_uncounted = false ) {
	global $wpdb;
	if ( ! jsk_lock( 'jsk_doc_' . (int) $id, 0 ) ) {
		return new WP_Error( 'jsk_busy', 'Inventúra sa práve zaúčtováva.' );
	}
	try {
		$d = jsk_doc( $id );
		if ( ! $d || 'inventory' !== $d['type'] || ! in_array( $d['status'], array( 'draft', 'posting' ), true ) ) {
			return new WP_Error( 'jsk_state', 'Inventúra nie je koncept (možno už bola zaúčtovaná).' );
		}
		if ( 'draft' === $d['status'] ) {
			if ( $zero_uncounted ) {
				// Zapíše sa do konceptu – aj prerušené zaúčtovanie sa potom dokončí s rovnakou voľbou.
				$wpdb->query( $wpdb->prepare( 'UPDATE ' . jsk_t( 'doc_items' ) . ' SET counted = 0 WHERE doc_id = %d AND counted IS NULL', $id ) ); // phpcs:ignore
				$d = jsk_doc( $id );
			}
			$year = (int) current_time( 'Y' );
			jsk_lock( 'jsk_number' );
			list( $num, $seq ) = jsk_next_number( 'inventory', $year );
			$wpdb->update( jsk_t( 'docs' ), array( 'number' => $num, 'year' => $year, 'seq' => $seq, 'doc_date' => current_time( 'Y-m-d' ), 'status' => 'posting', 'posted_at' => current_time( 'mysql' ) ), array( 'id' => $id ) );
			jsk_unlock( 'jsk_number' );
			$d['number'] = $num;
		}
		$done  = jsk_done_items( $id, 'inventory' );
		$total = 0.0;
		foreach ( $d['items'] as $it ) {
			$counted = null === $it['counted'] ? null : (float) $it['counted'];
			if ( null === $counted || in_array( (int) $it['id'], $done, true ) ) {
				$total += (float) $it['qty'] * (float) $it['price'];
				continue;
			}
			$pid = (int) $it['product_id'];
			$p   = wc_get_product( $pid );
			if ( ! $p || ! $p->managing_stock() ) {
				continue; // produkt medzitým zmazaný alebo bez sledovania zásob – preskočí sa
			}
			$now  = jsk_db_stock( $pid );
			$now  = null === $now ? (float) $p->get_stock_quantity() : $now;
			$diff = round( $counted - $now, 4 );
			$cost = jsk_cost( $pid );
			$wpdb->update( jsk_t( 'doc_items' ), array( 'qty' => $diff, 'price' => $cost, 'counted' => $counted ), array( 'id' => (int) $it['id'] ) );
			if ( abs( $diff ) > JSK_EPS ) {
				$r = jsk_apply_move( $pid, $diff, 'inventory', array(
					'doc_id'  => $id,
					'item_id' => (int) $it['id'],
					'note'    => 'Inventúra ' . $d['number'] . ( $diff < 0 ? ' – manko' : ' – prebytok' ),
				) );
				if ( is_wp_error( $r ) ) {
					return new WP_Error( 'jsk_partial', $r->get_error_message() . ' Inventúra je zaúčtovaná len čiastočne – dokončite ju tlačidlom „Dokončiť zaúčtovanie“.' );
				}
			}
			$total += $diff * (float) $cost;
		}
		$wpdb->update( jsk_t( 'docs' ), array( 'status' => 'posted', 'total' => round( $total, 4 ) ), array( 'id' => $id ) );
		do_action( 'jsk_doc_posted', $id, 'inventory' );
		return true;
	} finally {
		jsk_unlock( 'jsk_doc_' . (int) $id );
	}
}

/** Stav inventúry (ženský rod). */
function jsk_inv_status( $st ) {
	return array( 'draft' => 'Rozpracovaná', 'posting' => 'Zaúčtovaná len čiastočne', 'posted' => 'Zaúčtovaná', 'cancelling' => 'Stornovaná len čiastočne', 'cancelled' => 'Stornovaná' )[ $st ] ?? jsk_status_label( $st );
}

/** Súhrn inventúry: položky, spočítané, s rozdielom, manko/prebytok v ks a v nákupných cenách. */
function jsk_inv_summary( array $d ) {
	$s = array( 'items' => count( $d['items'] ), 'counted' => 0, 'diff' => 0, 'minus' => 0.0, 'plus' => 0.0, 'minus_v' => 0.0, 'plus_v' => 0.0 );
	foreach ( $d['items'] as $it ) {
		if ( null === $it['counted'] ) {
			continue;
		}
		$s['counted']++;
		$diff = 'draft' === $d['status'] ? (float) $it['counted'] - (float) $it['expected'] : (float) $it['qty'];
		if ( abs( $diff ) <= JSK_EPS ) {
			continue;
		}
		$s['diff']++;
		$val = $diff * (float) $it['price'];
		if ( $diff < 0 ) {
			$s['minus']   += $diff;
			$s['minus_v'] += $val;
		} else {
			$s['plus']   += $diff;
			$s['plus_v'] += $val;
		}
	}
	return $s;
}

/* ---------------------------------------------------------------- Stránky */

function jsk_render_inventory() {
	if ( ! current_user_can( JSK_CAP ) ) {
		return;
	}
	$id = (int) jsk_get( 'id' );
	if ( $id ) {
		$d = jsk_doc( $id );
		if ( $d && 'inventory' === $d['type'] ) {
			if ( 'draft' === $d['status'] && ! empty( $_GET['print'] ) ) { // phpcs:ignore
				jsk_render_inventory_sheet( $d );
			} elseif ( 'draft' === $d['status'] ) {
				jsk_render_inventory_edit( $d );
			} else {
				jsk_render_inventory_view( $d, ! empty( $_GET['print'] ) ); // phpcs:ignore
			}
			return;
		}
	}
	global $wpdb;
	$rows = $wpdb->get_results( 'SELECT d.*, (SELECT COUNT(*) FROM ' . jsk_t( 'doc_items' ) . ' i WHERE i.doc_id = d.id) AS n, (SELECT COUNT(*) FROM ' . jsk_t( 'doc_items' ) . ' i WHERE i.doc_id = d.id AND i.counted IS NOT NULL) AS c FROM ' . jsk_t( 'docs' ) . " d WHERE d.type = 'inventory' ORDER BY (d.status = 'draft') DESC, d.id DESC LIMIT 100", ARRAY_A ); // phpcs:ignore
	jsk_head( 'Inventúra' );
	echo '<hr class="wp-header-end">';
	jsk_notice();
	$cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true ) );
	?>
	<div class="jsk-panel jsk-inv-new">
		<h2>Nová inventúra</h2>
		<p class="description">Zapamätá sa aktuálny stav v evidencii a pripraví sa súpis. Spočítané množstvá zadávaš postupne (aj čítačkou čiarových kódov), ukladajú sa priebežne. Zásoby sa zmenia až pri zaúčtovaní.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'jsk_inv_new' ); ?>
			<input type="hidden" name="action" value="jsk_inv_new">
			<label>Rozsah
				<select name="cat"><option value="0">Všetky produkty so sledovaním zásob</option>
					<?php foreach ( is_array( $cats ) ? $cats : array() as $c ) : ?><option value="<?php echo (int) $c->term_id; ?>">Kategória: <?php echo esc_html( $c->name ); ?></option><?php endforeach; ?>
				</select>
			</label>
			<label>Poznámka <input type="text" name="note" class="regular-text" placeholder="napr. Ročná inventúra 2026"></label>
			<button class="button button-primary">Založiť inventúru</button>
		</form>
	</div>
	<table class="widefat striped jsk-table">
		<thead><tr><th>Číslo</th><th>Dátum</th><th>Rozsah</th><th class="n">Spočítané</th><th class="n">Rozdiel (nákup)</th><th>Stav</th></tr></thead>
		<tbody>
		<?php if ( ! $rows ) : ?><tr><td colspan="6">Zatiaľ žiadna inventúra.</td></tr><?php endif; ?>
		<?php foreach ( $rows as $r ) : ?>
			<tr>
				<td><a href="<?php echo esc_url( jsk_url( 'j-sklad-inventory', array( 'id' => $r['id'] ) ) ); ?>"><strong><?php echo esc_html( $r['number'] ? $r['number'] : 'Rozpracovaná #' . $r['id'] ); ?></strong></a></td>
				<td><?php echo esc_html( date_i18n( 'j. n. Y', strtotime( $r['doc_date'] ) ) ); ?></td>
				<td><?php echo esc_html( $r['reason'] . ( $r['note'] ? ' – ' . $r['note'] : '' ) ); ?></td>
				<td class="n"><?php echo (int) $r['c']; ?> / <?php echo (int) $r['n']; ?></td>
				<td class="n"><?php echo 'draft' === $r['status'] ? '—' : esc_html( jsk_money( $r['total'] ) ); ?></td>
				<td><span class="jsk-st jsk-st-<?php echo esc_attr( $r['status'] ); ?>"><?php echo esc_html( jsk_inv_status( $r['status'] ) ); ?></span></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	</div>
	<?php
}

/** Rozpracovaná inventúra: súpis so zadávaním spočítaných množstiev. */
function jsk_render_inventory_edit( array $d ) {
	jsk_head( 'Inventúra – súpis' );
	echo '<hr class="wp-header-end">';
	jsk_notice();
	$s     = jsk_inv_summary( $d );
	$nonce = wp_create_nonce( 'jsk_inv_' . (int) $d['id'] );
	$post  = admin_url( 'admin-post.php' );
	?>
	<div class="jsk-panel jsk-inv">
		<p class="jsk-meta">
			<span>Založená: <strong><?php echo esc_html( date_i18n( 'j. n. Y', strtotime( $d['doc_date'] ) ) ); ?></strong></span>
			<span>Rozsah: <strong><?php echo esc_html( $d['reason'] ); ?></strong></span>
			<?php if ( $d['note'] ) : ?><span><?php echo esc_html( $d['note'] ); ?></span><?php endif; ?>
			<span>Spočítané: <strong id="jsk-inv-cnt"><?php echo (int) $s['counted']; ?></strong> / <?php echo (int) $s['items']; ?></span>
			<span>S rozdielom: <strong id="jsk-inv-dif"><?php echo (int) $s['diff']; ?></strong></span>
			<span id="jsk-inv-save" class="jsk-muted"></span>
		</p>
		<div class="jsk-inv-bar">
			<input type="search" id="jsk-inv-scan" class="regular-text" placeholder="Naskenuj kód / napíš SKU alebo názov a Enter" autofocus>
			<label><input type="checkbox" id="jsk-inv-plus" checked> sken pripočíta 1 ks</label>
			<span class="jsk-inv-filter">
				<button type="button" class="button is-on" data-f="all">Všetko</button>
				<button type="button" class="button" data-f="todo">Nespočítané</button>
				<button type="button" class="button" data-f="diff">Rozdiely</button>
			</span>
			<a class="button" target="_blank" href="<?php echo esc_url( jsk_url( 'j-sklad-inventory', array( 'id' => $d['id'], 'print' => 1 ) ) ); ?>">Tlačiť súpis</a>
		</div>
		<table class="widefat striped jsk-table jsk-inv-table">
			<thead><tr><th class="jsk-img-col"></th><th>Produkt</th><th>SKU</th><th class="n">V evidencii</th><th class="n">Spočítané</th><th class="n">Rozdiel</th><th class="n">Hodnota rozdielu</th></tr></thead>
			<tbody>
			<?php foreach ( $d['items'] as $it ) : ?>
				<?php
				$p    = wc_get_product( (int) $it['product_id'] );
				$now  = jsk_db_stock( (int) $it['product_id'] );
				$moved = null !== $now && abs( $now - (float) $it['expected'] ) > JSK_EPS;
				?>
				<tr data-item="<?php echo (int) $it['id']; ?>" data-sku="<?php echo esc_attr( mb_strtolower( (string) $it['sku'] ) ); ?>" data-name="<?php echo esc_attr( mb_strtolower( $it['name'] ) ); ?>" data-exp="<?php echo esc_attr( (string) (float) $it['expected'] ); ?>" data-price="<?php echo esc_attr( null === $it['price'] ? '' : (string) (float) $it['price'] ); ?>">
					<td class="jsk-img-col"><?php echo $p ? jsk_thumb( $p, 40 ) : ''; // phpcs:ignore ?></td>
					<td><?php echo esc_html( $it['name'] ); ?><?php if ( $p && $p->is_type( 'variation' ) ) : ?><br><small class="jsk-muted"><?php echo esc_html( jsk_var_label( $p->get_id() ) ); ?></small><?php endif; ?></td>
					<td><?php echo esc_html( $it['sku'] ); ?></td>
					<td class="n"><?php echo esc_html( jsk_qty( $it['expected'] ) ); ?><?php if ( $moved ) : ?><br><small class="jsk-inv-moved" title="Zásoba sa od založenia inventúry zmenila (predaj, príjem…). Pri zaúčtovaní sa nastaví spočítané množstvo – rozdiel sa počíta voči aktuálnej zásobe.">teraz <?php echo esc_html( jsk_qty( $now ) ); ?></small><?php endif; ?></td>
					<td class="n"><input type="number" class="jsk-inv-in" min="0" step="1" inputmode="numeric" value="<?php echo null === $it['counted'] ? '' : esc_attr( jsk_qty( $it['counted'] ) ); ?>" aria-label="Spočítané – <?php echo esc_attr( $it['name'] ); ?>"></td>
					<td class="n jsk-inv-d">—</td>
					<td class="n jsk-inv-v">—</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<div class="jsk-inv-foot">
			<form method="post" action="<?php echo esc_url( $post ); ?>" class="jsk-inline" onsubmit="return confirm('Nespočítané položky dostanú spočítané množstvo = stav v evidencii (bez rozdielu). Pokračovať?');">
				<?php wp_nonce_field( 'jsk_inv_fill_' . (int) $d['id'] ); ?><input type="hidden" name="action" value="jsk_inv_fill"><input type="hidden" name="id" value="<?php echo (int) $d['id']; ?>">
				<button class="button">Nespočítané potvrdiť podľa evidencie</button>
			</form>
			<form method="post" action="<?php echo esc_url( $post ); ?>" class="jsk-inline" id="jsk-inv-post-form">
				<?php wp_nonce_field( 'jsk_inv_post_' . (int) $d['id'] ); ?><input type="hidden" name="action" value="jsk_inv_post"><input type="hidden" name="id" value="<?php echo (int) $d['id']; ?>">
				<label class="jsk-muted"><input type="checkbox" name="zero" value="1"> nespočítané nastaviť na 0 ks</label>
				<button class="button button-primary">Zaúčtovať inventúru</button>
			</form>
			<form method="post" action="<?php echo esc_url( $post ); ?>" class="jsk-inline" onsubmit="return confirm('Zmazať rozpracovanú inventúru? Zásoby sa nemenia.');">
				<?php wp_nonce_field( 'jsk_inv_delete_' . (int) $d['id'] ); ?><input type="hidden" name="action" value="jsk_inv_delete"><input type="hidden" name="id" value="<?php echo (int) $d['id']; ?>">
				<button class="button jsk-danger">Zmazať</button>
			</form>
		</div>
	</div>
	<script>
	(function () {
		var ajax = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, nonce = <?php echo wp_json_encode( $nonce ); ?>, doc = <?php echo (int) $d['id']; ?>;
		var cur = <?php echo wp_json_encode( 'EUR' === jsk_opt( 'currency' ) ? '€' : (string) jsk_opt( 'currency' ) ); ?>;
		var rows = Array.prototype.slice.call(document.querySelectorAll('.jsk-inv-table tbody tr'));
		var save = document.getElementById('jsk-inv-save'), filter = 'all', queue = {}, timer = null;
		function num(v) { return Math.round(v * 10000) / 10000; }
		function fmt(v) { return (v > 0 ? '+' : '') + String(num(v)).replace('.', ','); }
		function money(v) { return (v > 0 ? '+' : '') + v.toFixed(2).replace('.', ',') + ' ' + cur; }
		function calc(tr) {
			var inp = tr.querySelector('input'), d = tr.querySelector('.jsk-inv-d'), v = tr.querySelector('.jsk-inv-v');
			tr.classList.remove('is-minus', 'is-plus', 'is-ok', 'is-todo');
			if (inp.value === '') { d.textContent = '—'; v.textContent = '—'; tr.classList.add('is-todo'); return; }
			var diff = num(parseFloat(inp.value) - parseFloat(tr.dataset.exp));
			d.textContent = diff === 0 ? '0' : fmt(diff);
			v.textContent = tr.dataset.price === '' || diff === 0 ? (diff === 0 ? '—' : '?') : money(diff * parseFloat(tr.dataset.price));
			tr.classList.add(diff < 0 ? 'is-minus' : diff > 0 ? 'is-plus' : 'is-ok');
		}
		function totals() {
			var c = 0, f = 0;
			rows.forEach(function (tr) { if (!tr.classList.contains('is-todo')) { c++; } if (tr.classList.contains('is-minus') || tr.classList.contains('is-plus')) { f++; } });
			document.getElementById('jsk-inv-cnt').textContent = c; document.getElementById('jsk-inv-dif').textContent = f;
		}
		function show() {
			rows.forEach(function (tr) {
				var ok = filter === 'all' || (filter === 'todo' && tr.classList.contains('is-todo')) || (filter === 'diff' && (tr.classList.contains('is-minus') || tr.classList.contains('is-plus')));
				tr.style.display = ok ? '' : 'none';
			});
		}
		function body(data) { var fd = new FormData(); fd.append('action', 'jsk_inv_set'); fd.append('_ajax_nonce', nonce); fd.append('id', doc); fd.append('items', JSON.stringify(data)); return fd; }
		function flush() {
			clearTimeout(timer);
			var items = Object.keys(queue); if (!items.length) { return Promise.resolve(true); }
			var data = queue; queue = {};
			save.textContent = 'Ukladá sa…';
			return fetch(ajax, { method: 'POST', body: body(data), credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
				save.textContent = j && j.success ? 'Uložené ' + new Date().toLocaleTimeString('sk-SK') : 'Neuložené – ' + ((j && j.data) || 'chyba');
				if (!(j && j.success)) { queue = Object.assign(data, queue); }
				return !!(j && j.success);
			}).catch(function () { save.textContent = 'Neuložené – skontroluj pripojenie'; queue = Object.assign(data, queue); return false; });
		}
		function changed(tr) { queue[tr.dataset.item] = tr.querySelector('input').value; calc(tr); totals(); clearTimeout(timer); timer = setTimeout(flush, 700); }
		rows.forEach(function (tr) {
			calc(tr);
			var inp = tr.querySelector('input');
			inp.addEventListener('input', function () { changed(tr); });
			inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); document.getElementById('jsk-inv-scan').focus(); } });
		});
		totals(); show();
		document.querySelectorAll('.jsk-inv-filter button').forEach(function (b) {
			b.addEventListener('click', function () { document.querySelectorAll('.jsk-inv-filter button').forEach(function (x) { x.classList.remove('is-on'); }); b.classList.add('is-on'); filter = b.dataset.f; show(); });
		});
		var scan = document.getElementById('jsk-inv-scan');
		scan.addEventListener('keydown', function (e) {
			if (e.key !== 'Enter') { return; }
			e.preventDefault();
			var q = scan.value.trim().toLowerCase(); if (!q) { return; }
			var hit = rows.filter(function (tr) { return tr.dataset.sku === q; });
			if (!hit.length) { hit = rows.filter(function (tr) { return tr.dataset.sku.indexOf(q) >= 0 || tr.dataset.name.indexOf(q) >= 0; }); }
			rows.forEach(function (tr) { tr.classList.remove('is-hit'); });
			if (!hit.length) { save.textContent = 'Nenašlo sa: ' + scan.value; scan.select(); return; }
			var tr = hit[0], inp = tr.querySelector('input');
			tr.style.display = ''; tr.classList.add('is-hit'); tr.scrollIntoView({ block: 'center' });
			if (hit.length === 1 && document.getElementById('jsk-inv-plus').checked && rows.some(function (r) { return r.dataset.sku === q; })) {
				inp.value = String((parseFloat(inp.value) || 0) + 1); changed(tr); scan.value = ''; save.textContent = tr.querySelector('td:nth-child(2)').textContent.trim() + ': ' + inp.value + ' ks';
			} else { inp.focus(); inp.select(); scan.value = ''; }
		});
		window.addEventListener('pagehide', function () { if (Object.keys(queue).length && navigator.sendBeacon) { navigator.sendBeacon(ajax, body(queue)); queue = {}; } });
		var form = document.getElementById('jsk-inv-post-form');
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var todo = rows.filter(function (tr) { return tr.classList.contains('is-todo'); }).length;
			var zero = form.querySelector('input[name=zero]').checked;
			var msg = 'Zaúčtovať inventúru? Zásoba každej spočítanej položky sa nastaví na spočítané množstvo.' + (todo ? '\n\nNespočítané položky (' + todo + '): ' + (zero ? 'nastavia sa na 0 ks!' : 'ostanú bez zmeny.') : '');
			if (!confirm(msg)) { return; }
			flush().then(function (ok) { if (ok) { form.submit(); } else { alert('Spočítané množstvá sa nepodarilo uložiť – skúste to znova.'); } });
		});
	})();
	</script>
	</div>
	<?php
}

/** Tlač súpisu na počítanie (rozpracovaná inventúra): stĺpec na zapísanie spočítaného množstva. */
function jsk_render_inventory_sheet( array $d ) {
	echo '<style>#adminmenumain,#wpadminbar,#wpfooter,.notice{display:none!important}#wpcontent{margin:0!important;padding:0 16px!important}html.wp-toolbar{padding:0!important}.jsk-sheet td{height:26px}.jsk-sheet .jsk-blank{width:110px;border-bottom:1px solid #8C8F94}@media print{@page{margin:12mm}body{background:#fff}}</style>';
	jsk_head( 'Inventúrny súpis' );
	?>
	<p class="jsk-meta">
		<span>Založená: <strong><?php echo esc_html( date_i18n( 'j. n. Y', strtotime( $d['doc_date'] ) ) ); ?></strong></span>
		<span>Rozsah: <strong><?php echo esc_html( $d['reason'] ); ?></strong></span>
		<?php if ( $d['note'] ) : ?><span><?php echo esc_html( $d['note'] ); ?></span><?php endif; ?>
		<span>Položiek: <strong><?php echo count( $d['items'] ); ?></strong></span>
	</p>
	<table class="widefat striped jsk-table jsk-sheet">
		<thead><tr><th>#</th><th>Produkt</th><th>SKU</th><th class="n">V evidencii</th><th>Spočítané</th></tr></thead>
		<tbody>
		<?php foreach ( $d['items'] as $i => $it ) : ?>
			<tr><td><?php echo (int) $i + 1; ?></td><td><?php echo esc_html( $it['name'] ); ?></td><td><?php echo esc_html( $it['sku'] ); ?></td><td class="n"><?php echo esc_html( jsk_qty( $it['expected'] ) ); ?></td><td class="jsk-blank"><?php echo null === $it['counted'] ? '' : esc_html( jsk_qty( $it['counted'] ) ); ?></td></tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<p class="jsk-sign">Počítal: ............................................ &nbsp; Dátum: ............................ &nbsp; Podpis: ............................</p>
	<script>window.addEventListener('load', function () { window.print(); });</script>
	</div>
	<?php
}

/** Zaúčtovaná inventúra – protokol (aj na tlač). */
function jsk_render_inventory_view( array $d, $print = false ) {
	$s = jsk_inv_summary( $d );
	if ( $print ) {
		// Tlačová verzia bez menu administrácie.
		echo '<style>#adminmenumain,#wpadminbar,#wpfooter,.notice,.jsk-noprint{display:none!important}#wpcontent{margin:0!important;padding:0 16px!important}html.wp-toolbar{padding:0!important}@media print{@page{margin:12mm}body{background:#fff}}</style>';
	}
	jsk_head( 'Inventúra ' . $d['number'] );
	echo '<hr class="wp-header-end">';
	if ( ! $print ) {
		jsk_notice();
	}
	$u    = get_userdata( (int) $d['user_id'] );
	$only = $print ? false : ! empty( $_GET['diff'] ); // phpcs:ignore
	?>
	<div class="jsk-panel">
		<p class="jsk-meta">
			<span>Dátum: <strong><?php echo esc_html( date_i18n( 'j. n. Y', strtotime( $d['doc_date'] ) ) ); ?></strong></span>
			<span>Rozsah: <strong><?php echo esc_html( $d['reason'] ); ?></strong></span>
			<span>Stav: <span class="jsk-st jsk-st-<?php echo esc_attr( $d['status'] ); ?>"><?php echo esc_html( jsk_inv_status( $d['status'] ) ); ?></span></span>
			<span>Vykonal: <?php echo esc_html( $u ? $u->display_name : '—' ); ?></span>
			<?php if ( $d['note'] ) : ?><span><?php echo esc_html( $d['note'] ); ?></span><?php endif; ?>
		</p>
		<div class="jsk-cards">
			<div><span>Položky / spočítané</span><strong><?php echo (int) $s['items']; ?> / <?php echo (int) $s['counted']; ?></strong></div>
			<div><span>S rozdielom</span><strong><?php echo (int) $s['diff']; ?></strong></div>
			<div><span>Manko</span><strong class="jsk-minus"><?php echo esc_html( jsk_qty( $s['minus'] ) ); ?> ks · <?php echo esc_html( jsk_money( $s['minus_v'] ) ); ?></strong></div>
			<div><span>Prebytok</span><strong class="jsk-plus">+<?php echo esc_html( jsk_qty( $s['plus'] ) ); ?> ks · <?php echo esc_html( jsk_money( $s['plus_v'] ) ); ?></strong></div>
			<div><span>Rozdiel spolu (nákup)</span><strong><?php echo esc_html( jsk_money( $s['minus_v'] + $s['plus_v'] ) ); ?></strong></div>
		</div>
		<p class="jsk-noprint">
			<a class="button" href="<?php echo esc_url( jsk_url( 'j-sklad-inventory' ) ); ?>">← Inventúry</a>
			<a class="button" href="<?php echo esc_url( jsk_url( 'j-sklad-inventory', array( 'id' => $d['id'], 'diff' => $only ? 0 : 1 ) ) ); ?>"><?php echo $only ? 'Všetky položky' : 'Len rozdiely'; ?></a>
			<a class="button" target="_blank" href="<?php echo esc_url( jsk_url( 'j-sklad-inventory', array( 'id' => $d['id'], 'print' => 1 ) ) ); ?>">Tlačiť protokol</a>
			<a class="button" href="<?php echo esc_url( jsk_url( 'j-sklad-moves', array( 'doc' => $d['id'] ) ) ); ?>">Pohyby inventúry</a>
			<?php if ( 'posting' === $d['status'] ) : ?>
				<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=jsk_inv_post&id=' . $d['id'] ), 'jsk_inv_post_' . $d['id'] ) ); ?>">Dokončiť zaúčtovanie</a>
			<?php endif; ?>
			<?php if ( in_array( $d['status'], array( 'posted', 'cancelling' ), true ) ) : ?>
				<a class="button jsk-danger" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=jsk_doc_cancel&id=' . $d['id'] ), 'jsk_doc_cancel_' . $d['id'] ) ); ?>" onclick="return confirm('Stornovať inventúru <?php echo esc_js( $d['number'] ); ?>? Rozdiely sa vrátia späť, doklad ostane v evidencii.');">Stornovať</a>
			<?php endif; ?>
		</p>
		<table class="widefat striped jsk-table">
			<thead><tr><th>Produkt</th><th>SKU</th><th class="n">V evidencii</th><th class="n">Spočítané</th><th class="n">Rozdiel</th><th class="n">Nákupná cena</th><th class="n">Hodnota rozdielu</th></tr></thead>
			<tbody>
			<?php foreach ( $d['items'] as $it ) : ?>
				<?php
				$q = (float) $it['qty'];
				if ( $only && abs( $q ) <= JSK_EPS ) {
					continue;
				}
				?>
				<tr class="<?php echo $q < -JSK_EPS ? 'is-minus' : ( $q > JSK_EPS ? 'is-plus' : '' ); ?>">
					<td><?php echo esc_html( $it['name'] ); ?></td>
					<td><?php echo esc_html( $it['sku'] ); ?></td>
					<td class="n"><?php echo esc_html( jsk_qty( null === $it['counted'] ? $it['expected'] : (float) $it['counted'] - $q ) ); ?></td>
					<td class="n"><?php echo null === $it['counted'] ? '<span class="jsk-muted">nespočítané</span>' : esc_html( jsk_qty( $it['counted'] ) ); ?></td>
					<td class="n"><?php echo abs( $q ) > JSK_EPS ? esc_html( ( $q > 0 ? '+' : '' ) . jsk_qty( $q ) ) : '0'; ?></td>
					<td class="n"><?php echo esc_html( jsk_money( $it['price'] ) ); ?></td>
					<td class="n"><?php echo abs( $q ) > JSK_EPS && null !== $it['price'] ? esc_html( jsk_money( $q * (float) $it['price'] ) ) : '—'; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ( $print ) : ?>
			<p class="jsk-sign">Inventúru vykonal: ............................................ &nbsp; Dátum: ............................ &nbsp; Podpis: ............................</p>
			<script>window.addEventListener('load', function () { window.print(); });</script>
		<?php endif; ?>
	</div>
	</div>
	<?php
}

/* ---------------------------------------------------------------- Akcie */

add_action( 'admin_post_jsk_inv_new', function () {
	if ( ! current_user_can( JSK_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jsk_inv_new' );
	$r = jsk_inv_create( (int) ( $_POST['cat'] ?? 0 ), (string) wp_unslash( $_POST['note'] ?? '' ) ); // phpcs:ignore
	if ( is_wp_error( $r ) ) {
		jsk_back_err( jsk_url( 'j-sklad-inventory' ), $r->get_error_message() );
	}
	wp_safe_redirect( jsk_url( 'j-sklad-inventory', array( 'id' => $r, 'jsk_msg' => 'inv_new' ) ) );
	exit;
} );

/** Priebežné ukladanie spočítaných množstiev (AJAX): items = { item_id: množstvo alebo "" }. */
add_action( 'wp_ajax_jsk_inv_set', function () {
	global $wpdb;
	$id = (int) ( $_POST['id'] ?? 0 );
	if ( ! current_user_can( JSK_CAP ) || ! check_ajax_referer( 'jsk_inv_' . $id, '_ajax_nonce', false ) ) {
		wp_send_json_error( 'Nemáte oprávnenie – obnovte stránku.', 403 );
	}
	$d = $wpdb->get_row( $wpdb->prepare( 'SELECT type, status FROM ' . jsk_t( 'docs' ) . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore
	if ( ! $d || 'inventory' !== $d['type'] || 'draft' !== $d['status'] ) {
		wp_send_json_error( 'Inventúra už nie je rozpracovaná.' );
	}
	$items = json_decode( (string) wp_unslash( $_POST['items'] ?? '{}' ), true ); // phpcs:ignore
	$n     = 0;
	foreach ( (array) $items as $item => $v ) {
		$v   = trim( str_replace( ',', '.', (string) $v ) );
		$val = '' === $v ? null : max( 0, (float) $v );
		$n  += (int) $wpdb->query( $wpdb->prepare( 'UPDATE ' . jsk_t( 'doc_items' ) . ' SET counted = ' . ( null === $val ? 'NULL' : '%f' ) . ' WHERE id = %d AND doc_id = %d', ...( null === $val ? array( (int) $item, $id ) : array( $val, (int) $item, $id ) ) ) ); // phpcs:ignore
	}
	wp_send_json_success( array( 'saved' => $n ) );
} );

add_action( 'admin_post_jsk_inv_fill', function () {
	global $wpdb;
	$id = (int) ( $_POST['id'] ?? 0 );
	if ( ! current_user_can( JSK_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jsk_inv_fill_' . $id );
	$wpdb->query( $wpdb->prepare( 'UPDATE ' . jsk_t( 'doc_items' ) . ' i JOIN ' . jsk_t( 'docs' ) . " d ON d.id = i.doc_id SET i.counted = i.expected WHERE i.doc_id = %d AND i.counted IS NULL AND d.status = 'draft' AND d.type = 'inventory'", $id ) ); // phpcs:ignore
	wp_safe_redirect( jsk_url( 'j-sklad-inventory', array( 'id' => $id, 'jsk_msg' => 'saved' ) ) );
	exit;
} );

$jsk_inv_post = function () {
	$id = (int) ( $_REQUEST['id'] ?? 0 ); // phpcs:ignore
	if ( ! current_user_can( JSK_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jsk_inv_post_' . $id );
	$r = jsk_inv_post( $id, ! empty( $_POST['zero'] ) ); // phpcs:ignore
	if ( is_wp_error( $r ) ) {
		jsk_back_err( jsk_url( 'j-sklad-inventory', array( 'id' => $id ) ), $r->get_error_message() );
	}
	wp_safe_redirect( jsk_url( 'j-sklad-inventory', array( 'id' => $id, 'jsk_msg' => 'inv_posted' ) ) );
	exit;
};
add_action( 'admin_post_jsk_inv_post', $jsk_inv_post );

add_action( 'admin_post_jsk_inv_delete', function () {
	global $wpdb;
	$id = (int) ( $_POST['id'] ?? 0 );
	if ( ! current_user_can( JSK_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jsk_inv_delete_' . $id );
	$d = jsk_doc( $id );
	if ( $d && 'inventory' === $d['type'] && 'draft' === $d['status'] ) {
		$wpdb->delete( jsk_t( 'doc_items' ), array( 'doc_id' => $id ) );
		$wpdb->delete( jsk_t( 'docs' ), array( 'id' => $id ) );
	}
	wp_safe_redirect( jsk_url( 'j-sklad-inventory', array( 'jsk_msg' => 'deleted' ) ) );
	exit;
} );
