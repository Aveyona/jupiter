<?php
/**
 * Adresár partnerov – dodávatelia (a neskôr aj odberatelia mimo e-shopu).
 * Prijatá faktúra sa viaže na dodávateľa (partner_id); údaje sa predvyplnia (IČO, DIČ, IBAN, splatnosť, kategória).
 * Detail dodávateľa ukazuje jeho faktúry, neuhradené sumy a príjemky J-Skladu.
 * Údaje firmy sa dajú načítať podľa IČO z Registra právnických osôb (api.statistics.sk).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_submenu_page( 'j-invoices', 'Dodávatelia', 'Dodávatelia', 'manage_woocommerce', 'j-invoices-partners', function () {
		jinv_render_admin( 'partners' );
	}, 2 );
}, 11 );

function jinv_partner_types() {
	return array( 'supplier' => 'Dodávateľ', 'customer' => 'Odberateľ', 'both' => 'Dodávateľ aj odberateľ' );
}

function jinv_partner_get( $id ) {
	global $wpdb;
	return $id ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . jinv_table( 'partners' ) . ' WHERE id = %d', $id ), ARRAY_A ) : null; // phpcs:ignore
}

/** IBAN bez medzier, veľkými písmenami; '' ak je neplatný. */
function jinv_iban_clean( $iban ) {
	$i = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $iban ) );
	if ( ! preg_match( '/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $i ) ) {
		return '';
	}
	$n = '';
	foreach ( str_split( substr( $i, 4 ) . substr( $i, 0, 4 ) ) as $ch ) {
		$n .= ctype_alpha( $ch ) ? (string) ( ord( $ch ) - 55 ) : $ch;
	}
	$mod = 0;
	foreach ( str_split( $n, 7 ) as $part ) {
		$mod = (int) ( $mod . $part ) % 97;
	}
	return 1 === $mod ? $i : '';
}

function jinv_iban_format( $iban ) {
	return trim( chunk_split( (string) $iban, 4, ' ' ) );
}

/** Uloženie partnera z poľa údajov. Vráti ID alebo WP_Error. */
function jinv_partner_save( array $in, $id = 0 ) {
	global $wpdb;
	$name = trim( sanitize_text_field( $in['name'] ?? '' ) );
	if ( '' === $name ) {
		return new WP_Error( 'jinv_partner', 'Chýba názov partnera.' );
	}
	$iban_raw = trim( (string) ( $in['iban'] ?? '' ) );
	$iban     = jinv_iban_clean( $iban_raw );
	if ( '' !== $iban_raw && '' === $iban ) {
		return new WP_Error( 'jinv_partner', 'IBAN „' . sanitize_text_field( $iban_raw ) . '“ nie je platný – skontrolujte ho.' );
	}
	$due = isset( $in['due_days'] ) && '' !== trim( (string) $in['due_days'] ) ? max( 0, min( 365, (int) $in['due_days'] ) ) : null;
	$row = array(
		'type'     => isset( jinv_partner_types()[ $in['type'] ?? '' ] ) ? $in['type'] : 'supplier',
		'name'     => mb_substr( $name, 0, 200 ),
		'ico'      => preg_replace( '/\s+/', '', sanitize_text_field( $in['ico'] ?? '' ) ),
		'dic'      => preg_replace( '/\s+/', '', sanitize_text_field( $in['dic'] ?? '' ) ),
		'icdph'    => strtoupper( preg_replace( '/\s+/', '', sanitize_text_field( $in['icdph'] ?? '' ) ) ),
		'street'   => sanitize_text_field( $in['street'] ?? '' ),
		'city'     => sanitize_text_field( $in['city'] ?? '' ),
		'zip'      => sanitize_text_field( $in['zip'] ?? '' ),
		'country'  => preg_match( '/^[A-Z]{2}$/', strtoupper( (string) ( $in['country'] ?? '' ) ) ) ? strtoupper( $in['country'] ) : 'SK',
		'email'    => sanitize_email( $in['email'] ?? '' ),
		'phone'    => sanitize_text_field( $in['phone'] ?? '' ),
		'web'      => esc_url_raw( $in['web'] ?? '' ),
		'iban'     => $iban,
		'due_days' => $due,
		'category' => sanitize_text_field( $in['category'] ?? '' ),
		'note'     => sanitize_textarea_field( $in['note'] ?? '' ),
		'active'   => isset( $in['active'] ) ? ( $in['active'] ? 1 : 0 ) : 1,
	);
	if ( $row['ico'] ) {
		$dup = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . jinv_table( 'partners' ) . ' WHERE ico = %s AND id <> %d LIMIT 1', $row['ico'], $id ) ); // phpcs:ignore
		if ( $dup ) {
			return new WP_Error( 'jinv_partner_dup', 'Partner s IČO ' . $row['ico'] . ' už v adresári je.', array( 'id' => $dup ) );
		}
	}
	if ( $id ) {
		$wpdb->update( jinv_table( 'partners' ), $row, array( 'id' => (int) $id ) );
		// Názov dodávateľa v jeho dokladoch (kvôli vyhľadávaniu v zozname faktúr).
		$wpdb->update( jinv_table(), array( 'customer_name' => $row['name'] ), array( 'partner_id' => (int) $id, 'kind' => 'received' ) );
		return (int) $id;
	}
	$wpdb->insert( jinv_table( 'partners' ), $row + array( 'created_at' => current_time( 'mysql' ) ) );
	return (int) $wpdb->insert_id;
}

/** Nájde partnera podľa IČO, inak podľa presného názvu. */
function jinv_partner_match( $name, $ico = '' ) {
	global $wpdb;
	$t   = jinv_table( 'partners' );
	$ico = preg_replace( '/\s+/', '', (string) $ico );
	if ( $ico ) {
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE ico = %s LIMIT 1", $ico ) ); // phpcs:ignore
		if ( $id ) {
			return $id;
		}
	}
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE name = %s LIMIT 1", trim( (string) $name ) ) ); // phpcs:ignore
}

/** Jednorazovo: dodávatelia z doterajších prijatých faktúr (podľa IČO / názvu) a prepojenie faktúr. */
function jinv_partners_from_received() {
	global $wpdb;
	$rows = $wpdb->get_results( 'SELECT id, data FROM ' . jinv_table() . " WHERE kind = 'received' AND partner_id = 0", ARRAY_A ); // phpcs:ignore
	$n    = 0;
	foreach ( (array) $rows as $r ) {
		$d   = json_decode( (string) $r['data'], true );
		$sup = isset( $d['supplier'] ) ? (array) $d['supplier'] : array();
		if ( empty( $sup['name'] ) ) {
			continue;
		}
		$pid = jinv_partner_match( $sup['name'], $sup['ico'] ?? '' );
		if ( ! $pid ) {
			$pid = jinv_partner_save( array( 'name' => $sup['name'], 'ico' => $sup['ico'] ?? '', 'dic' => $sup['dic'] ?? '', 'category' => $d['category'] ?? '' ) );
			if ( is_wp_error( $pid ) ) {
				continue;
			}
			$n++;
		}
		$wpdb->update( jinv_table(), array( 'partner_id' => $pid ), array( 'id' => (int) $r['id'] ) );
	}
	return $n;
}

/** Súhrn dokladov partnera: neuhradené, po splatnosti, spolu za rok. */
function jinv_partner_stats( $pid ) {
	global $wpdb;
	$t = jinv_table();
	$p = jinv_table( 'payments' );
	return $wpdb->get_row( $wpdb->prepare(
		"SELECT COUNT(*) AS docs,
			SUM(i.status = 'issued') AS open_n,
			COALESCE(SUM(CASE WHEN i.status = 'issued' THEN i.total - COALESCE((SELECT SUM(amount) FROM $p WHERE doc_id = i.id), 0) END), 0) AS open_sum,
			SUM(i.status = 'issued' AND i.due_date < %s) AS overdue_n,
			COALESCE(SUM(CASE WHEN i.status <> 'cancelled' AND i.year = %d THEN i.total END), 0) AS year_sum,
			MAX(i.issue_date) AS last_date
		FROM $t i WHERE i.partner_id = %d AND i.kind = 'received'",
		current_time( 'Y-m-d' ),
		(int) current_time( 'Y' ),
		$pid
	), ARRAY_A ); // phpcs:ignore
}

/* ---------------------------------------------------------------- Register právnických osôb */

/** Údaje firmy podľa IČO z RPO (Štatistický úrad SR). Vráti pole alebo WP_Error. */
function jinv_rpo_lookup( $ico ) {
	$ico = preg_replace( '/\D/', '', (string) $ico );
	if ( strlen( $ico ) < 6 || strlen( $ico ) > 8 ) {
		return new WP_Error( 'jinv_rpo', 'IČO musí mať 6 až 8 číslic.' );
	}
	$ico    = str_pad( $ico, 8, '0', STR_PAD_LEFT );
	$cached = get_transient( 'jinv_rpo_' . $ico );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$res = wp_remote_get( 'https://api.statistics.sk/rpo/v1/search?identifier=' . $ico, array( 'timeout' => 12, 'headers' => array( 'Accept' => 'application/json' ) ) );
	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		return new WP_Error( 'jinv_rpo', 'Register právnických osôb teraz neodpovedá – údaje vyplňte ručne.' );
	}
	$j = json_decode( wp_remote_retrieve_body( $res ), true );
	$e = $j['results'][0] ?? null;
	if ( ! $e ) {
		return new WP_Error( 'jinv_rpo', 'Firma s IČO ' . $ico . ' sa v registri nenašla.' );
	}
	$cur = function ( $list ) {
		$list  = array_values( array_filter( (array) $list, 'is_array' ) );
		$valid = array_values( array_filter( $list, fn( $x ) => empty( $x['validTo'] ) ) );
		$use   = $valid ? $valid : $list;
		return $use ? $use[ count( $use ) - 1 ] : null;
	};
	$name = $cur( $e['fullNames'] ?? array() );
	$addr = $cur( $e['addresses'] ?? array() );
	$out  = array(
		'name'   => (string) ( $name['value'] ?? '' ),
		'ico'    => $ico,
		'street' => trim( (string) ( $addr['street'] ?? ( $addr['municipality']['value'] ?? '' ) ) . ' ' . trim( ( isset( $addr['regNumber'] ) && $addr['regNumber'] ? $addr['regNumber'] : '' ) . ( ! empty( $addr['buildingNumber'] ) ? ( ! empty( $addr['regNumber'] ) ? '/' : '' ) . $addr['buildingNumber'] : '' ) ) ),
		'city'   => (string) ( $addr['municipality']['value'] ?? '' ),
		'zip'    => preg_replace( '/^(\d{3})(\d{2})$/', '$1 $2', preg_replace( '/\s+/', '', (string) ( $addr['postalCodes'][0] ?? '' ) ) ),
		'country' => 'SK',
	);
	if ( '' === $out['name'] ) {
		return new WP_Error( 'jinv_rpo', 'Register vrátil neúplné údaje – vyplňte ich ručne.' );
	}
	set_transient( 'jinv_rpo_' . $ico, $out, DAY_IN_SECONDS );
	return $out;
}

add_action( 'wp_ajax_jinv_rpo', function () {
	check_ajax_referer( 'jinv_partner', '_ajax_nonce' );
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_send_json_error( 'Nemáte oprávnenie.', 403 );
	}
	$r = jinv_rpo_lookup( wp_unslash( $_POST['ico'] ?? '' ) ); // phpcs:ignore
	is_wp_error( $r ) ? wp_send_json_error( $r->get_error_message() ) : wp_send_json_success( $r );
} );

/** Vyhľadanie partnerov (našepkávač v prijatej faktúre a v príjemke J-Skladu). */
function jinv_partner_search( $q, $limit = 12 ) {
	global $wpdb;
	$like = '%' . $wpdb->esc_like( trim( (string) $q ) ) . '%';
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, name, ico, dic, icdph, street, city, zip, iban, due_days, category FROM ' . jinv_table( 'partners' ) . " WHERE active = 1 AND type IN ('supplier','both') AND (name LIKE %s OR ico LIKE %s) ORDER BY name LIMIT %d", $like, $like, $limit ), ARRAY_A ); // phpcs:ignore
	return (array) $rows;
}

add_action( 'wp_ajax_jinv_partner_find', function () {
	check_ajax_referer( 'jinv_partner', '_ajax_nonce' );
	if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Nemáte oprávnenie.', 403 );
	}
	wp_send_json_success( jinv_partner_search( wp_unslash( $_GET['q'] ?? '' ) ) ); // phpcs:ignore
} );

/** Našepkávač dodávateľa pre formulár: vyplní polia podľa mapy (kľúč údaja => id poľa). */
function jinv_partner_picker_js( $input_id, $hidden_id, array $map = array() ) {
	$cfg = array(
		'ajax'   => admin_url( 'admin-ajax.php' ),
		'nonce'  => wp_create_nonce( 'jinv_partner' ),
		'input'  => $input_id,
		'hidden' => $hidden_id,
		'map'    => $map,
	);
	?>
	<script>
	(function (c) {
		var inp = document.getElementById(c.input), hid = document.getElementById(c.hidden); if (!inp) { return; }
		var box = document.createElement('ul'); box.className = 'jinv-ac'; box.hidden = true; inp.parentNode.style.position = 'relative'; inp.insertAdjacentElement('afterend', box);
		var t = null, items = [], sel = -1;
		function pick(p) {
			inp.value = p.name; if (hid) { hid.value = p.id; }
			Object.keys(c.map).forEach(function (k) { var el = document.getElementById(c.map[k]); if (el && p[k] !== null && p[k] !== undefined && p[k] !== '') { el.value = p[k]; el.dispatchEvent(new Event('change', { bubbles: true })); } });
			box.hidden = true; inp.dispatchEvent(new CustomEvent('jinv-partner', { detail: p, bubbles: true }));
		}
		function draw() {
			box.innerHTML = ''; if (!items.length) { box.hidden = true; return; }
			items.forEach(function (p, i) {
				var li = document.createElement('li'); li.className = i === sel ? 'is-sel' : '';
				li.innerHTML = '<strong></strong> <small></small>'; li.firstChild.textContent = p.name; li.lastChild.textContent = [p.ico ? 'IČO ' + p.ico : '', p.city].filter(Boolean).join(' · ');
				li.addEventListener('mousedown', function (e) { e.preventDefault(); pick(p); }); box.appendChild(li);
			});
			box.hidden = false;
		}
		inp.addEventListener('input', function () {
			if (hid) { hid.value = ''; }
			clearTimeout(t); var q = inp.value.trim(); if (q.length < 2) { items = []; draw(); return; }
			t = setTimeout(function () {
				fetch(c.ajax + '?action=jinv_partner_find&_ajax_nonce=' + c.nonce + '&q=' + encodeURIComponent(q), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { items = j && j.success ? j.data : []; sel = -1; draw(); });
			}, 200);
		});
		inp.addEventListener('keydown', function (e) {
			if (box.hidden) { return; }
			if (e.key === 'ArrowDown') { sel = Math.min(items.length - 1, sel + 1); draw(); e.preventDefault(); }
			else if (e.key === 'ArrowUp') { sel = Math.max(0, sel - 1); draw(); e.preventDefault(); }
			else if (e.key === 'Enter' && sel >= 0) { pick(items[sel]); e.preventDefault(); }
			else if (e.key === 'Escape') { box.hidden = true; }
		});
		inp.addEventListener('blur', function () { setTimeout(function () { box.hidden = true; }, 150); });
	})(<?php echo wp_json_encode( $cfg ); ?>);
	</script>
	<?php
}

/* ---------------------------------------------------------------- Stránky */

function jinv_partners_url( $args = array() ) {
	return add_query_arg( $args, admin_url( 'admin.php?page=j-invoices-partners' ) );
}

function jinv_render_partners() {
	$edit = isset( $_GET['edit'] ) ? sanitize_key( $_GET['edit'] ) : ''; // phpcs:ignore
	$view = isset( $_GET['partner'] ) ? (int) $_GET['partner'] : 0; // phpcs:ignore
	if ( '' !== $edit ) {
		jinv_render_partner_form( 'new' === $edit ? 0 : (int) $edit );
		return;
	}
	if ( $view ) {
		jinv_render_partner_detail( $view );
		return;
	}
	global $wpdb;
	$q    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore
	$arch = ! empty( $_GET['archived'] ); // phpcs:ignore
	$t    = jinv_table( 'partners' );
	$i    = jinv_table();
	$p    = jinv_table( 'payments' );
	$w    = $wpdb->prepare( 'p.active = %d', $arch ? 0 : 1 );
	if ( '' !== $q ) {
		$like = '%' . $wpdb->esc_like( $q ) . '%';
		$w   .= $wpdb->prepare( ' AND (p.name LIKE %s OR p.ico LIKE %s OR p.dic LIKE %s OR p.email LIKE %s OR p.city LIKE %s)', $like, $like, $like, $like, $like );
	}
	$rows = $wpdb->get_results( "SELECT p.*,
		(SELECT COUNT(*) FROM $i WHERE partner_id = p.id AND kind = 'received') AS docs,
		(SELECT COALESCE(SUM(x.total - COALESCE((SELECT SUM(amount) FROM $p WHERE doc_id = x.id), 0)), 0) FROM $i x WHERE x.partner_id = p.id AND x.kind = 'received' AND x.status = 'issued') AS open_sum,
		(SELECT COUNT(*) FROM $i WHERE partner_id = p.id AND kind = 'received' AND status = 'issued' AND due_date < CURDATE()) AS overdue,
		(SELECT MAX(issue_date) FROM $i WHERE partner_id = p.id AND kind = 'received') AS last_date
		FROM $t p WHERE $w ORDER BY p.name LIMIT 500", ARRAY_A ); // phpcs:ignore
	?>
	<p>
		<a class="button button-primary" href="<?php echo esc_url( jinv_partners_url( array( 'edit' => 'new' ) ) ); ?>">+ Nový dodávateľ</a>
		<a class="button" href="<?php echo esc_url( jinv_partners_url( $arch ? array() : array( 'archived' => 1 ) ) ); ?>"><?php echo $arch ? 'Aktívni dodávatelia' : 'Archivovaní'; ?></a>
	</p>
	<form method="get" class="jinv-filter">
		<input type="hidden" name="page" value="j-invoices-partners">
		<?php if ( $arch ) : ?><input type="hidden" name="archived" value="1"><?php endif; ?>
		<input type="search" name="s" value="<?php echo esc_attr( $q ); ?>" placeholder="Názov, IČO, DIČ, e-mail, mesto">
		<button class="button">Hľadať</button>
	</form>
	<table class="widefat striped jinv-table">
		<thead><tr><th>Dodávateľ</th><th>IČO / DIČ</th><th>Kontakt</th><th class="n">Faktúry</th><th class="n">Neuhradené</th><th>Posledná faktúra</th><th></th></tr></thead>
		<tbody>
		<?php if ( ! $rows ) : ?><tr><td colspan="7"><?php echo '' !== $q ? 'Nič sa nenašlo.' : 'Adresár je prázdny. Pridaj dodávateľa – alebo ho vytvorí prvá prijatá faktúra.'; ?></td></tr><?php endif; ?>
		<?php foreach ( (array) $rows as $r ) : ?>
			<tr>
				<td><a href="<?php echo esc_url( jinv_partners_url( array( 'partner' => $r['id'] ) ) ); ?>"><strong><?php echo esc_html( $r['name'] ); ?></strong></a><?php if ( $r['category'] ) : ?><br><small><?php echo esc_html( $r['category'] ); ?></small><?php endif; ?></td>
				<td><?php echo esc_html( $r['ico'] ); ?><?php echo $r['dic'] ? '<br><small>DIČ ' . esc_html( $r['dic'] ) . '</small>' : ''; ?></td>
				<td><?php echo esc_html( trim( $r['city'] ) ); ?><?php echo $r['email'] ? '<br><small>' . esc_html( $r['email'] ) . '</small>' : ''; ?></td>
				<td class="n"><?php echo (int) $r['docs']; ?></td>
				<td class="n"><?php echo (float) $r['open_sum'] > 0 ? '<strong>' . esc_html( jinv_money( $r['open_sum'] ) ) . '</strong>' . ( (int) $r['overdue'] ? '<br><span class="jinv-st jinv-st-overdue">' . (int) $r['overdue'] . ' po splatnosti</span>' : '' ) : '—'; ?></td>
				<td><?php echo $r['last_date'] ? esc_html( jinv_date( $r['last_date'] ) ) : '—'; ?></td>
				<td class="jinv-row-actions"><a href="<?php echo esc_url( jinv_partners_url( array( 'edit' => $r['id'] ) ) ); ?>">Upraviť</a> · <a href="<?php echo esc_url( jinv_admin_url( array( 'tab' => 'received', 'edit' => 'new', 'partner' => $r['id'] ) ) ); ?>">+ Faktúra</a></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

function jinv_render_partner_form( $id ) {
	$p = $id ? jinv_partner_get( $id ) : null;
	if ( $id && ! $p ) {
		echo '<p>Partner neexistuje.</p>';
		return;
	}
	$v = function ( $k, $def = '' ) use ( $p ) {
		return $p && isset( $p[ $k ] ) && null !== $p[ $k ] ? $p[ $k ] : $def;
	};
	$back = isset( $_GET['back'] ) ? esc_url_raw( wp_unslash( $_GET['back'] ) ) : ''; // phpcs:ignore
	?>
	<h2><?php echo $p ? 'Upraviť: ' . esc_html( $p['name'] ) : 'Nový dodávateľ'; ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="jinv-panel jinv-rform">
		<?php wp_nonce_field( 'jinv_partner_save' ); ?>
		<input type="hidden" name="action" value="jinv_partner_save"><input type="hidden" name="id" value="<?php echo (int) $id; ?>"><input type="hidden" name="back" value="<?php echo esc_attr( $back ); ?>">
		<table class="form-table" role="presentation">
			<tr><th><label for="pa-ico">IČO</label></th><td><input id="pa-ico" name="ico" value="<?php echo esc_attr( $v( 'ico' ) ); ?>" inputmode="numeric"> <button type="button" class="button" id="pa-rpo">Načítať z registra</button> <span id="pa-rpo-msg" class="description"></span></td></tr>
			<tr><th><label for="pa-name">Názov</label></th><td><input id="pa-name" name="name" class="regular-text" required value="<?php echo esc_attr( $v( 'name' ) ); ?>"></td></tr>
			<tr><th>DIČ / IČ DPH</th><td><input id="pa-dic" name="dic" placeholder="DIČ" value="<?php echo esc_attr( $v( 'dic' ) ); ?>" aria-label="DIČ"> <input name="icdph" placeholder="IČ DPH (SK…)" value="<?php echo esc_attr( $v( 'icdph' ) ); ?>" aria-label="IČ DPH"></td></tr>
			<tr><th><label for="pa-street">Adresa</label></th><td><input id="pa-street" name="street" class="regular-text" placeholder="Ulica a číslo" value="<?php echo esc_attr( $v( 'street' ) ); ?>"><br><input id="pa-zip" name="zip" placeholder="PSČ" value="<?php echo esc_attr( $v( 'zip' ) ); ?>" style="width:90px" aria-label="PSČ"> <input id="pa-city" name="city" placeholder="Mesto" value="<?php echo esc_attr( $v( 'city' ) ); ?>" aria-label="Mesto"> <input name="country" value="<?php echo esc_attr( $v( 'country', 'SK' ) ); ?>" style="width:50px" maxlength="2" aria-label="Krajina (kód)"></td></tr>
			<tr><th>Kontakt</th><td><input type="email" name="email" placeholder="e-mail" value="<?php echo esc_attr( $v( 'email' ) ); ?>" aria-label="E-mail"> <input name="phone" placeholder="telefón" value="<?php echo esc_attr( $v( 'phone' ) ); ?>" aria-label="Telefón"> <input type="url" name="web" placeholder="web" value="<?php echo esc_attr( $v( 'web' ) ); ?>" aria-label="Web"></td></tr>
			<tr><th><label for="pa-iban">IBAN</label></th><td><input id="pa-iban" name="iban" class="regular-text" value="<?php echo esc_attr( jinv_iban_format( $v( 'iban' ) ) ); ?>" placeholder="SK.. .... .... .... .... ...."><p class="description">Účet dodávateľa na úhradu jeho faktúr.</p></td></tr>
			<tr><th><label for="pa-due">Splatnosť faktúr</label></th><td><input id="pa-due" type="number" min="0" max="365" name="due_days" value="<?php echo esc_attr( (string) $v( 'due_days' ) ); ?>" style="width:80px"> dní <span class="description">– predvyplní dátum splatnosti novej prijatej faktúry</span></td></tr>
			<tr><th><label for="pa-cat">Predvolená kategória</label></th><td><input id="pa-cat" name="category" list="jinv-cats" value="<?php echo esc_attr( $v( 'category' ) ); ?>" placeholder="napr. Tovar"><?php jinv_categories_datalist(); ?></td></tr>
			<tr><th>Druh</th><td><select name="type"><?php foreach ( jinv_partner_types() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $v( 'type', 'supplier' ), $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></td></tr>
			<tr><th><label for="pa-note">Poznámka</label></th><td><textarea id="pa-note" name="note" rows="3" class="large-text"><?php echo esc_textarea( $v( 'note' ) ); ?></textarea></td></tr>
			<?php if ( $p ) : ?><tr><th>Stav</th><td><label><input type="checkbox" name="archived" value="1" <?php checked( ! (int) $p['active'] ); ?>> Archivovaný (nezobrazuje sa v ponuke, doklady ostávajú)</label></td></tr><?php endif; ?>
		</table>
		<p><button class="button button-primary">Uložiť</button> <a class="button" href="<?php echo esc_url( $p ? jinv_partners_url( array( 'partner' => $p['id'] ) ) : jinv_partners_url() ); ?>">Zrušiť</a></p>
	</form>
	<script>
	(function () {
		var b = document.getElementById('pa-rpo'), msg = document.getElementById('pa-rpo-msg');
		b.addEventListener('click', function () {
			var ico = document.getElementById('pa-ico').value.trim(); if (!ico) { msg.textContent = 'Najprv zadajte IČO.'; return; }
			msg.textContent = 'Hľadám v registri…'; b.disabled = true;
			var fd = new FormData(); fd.append('action', 'jinv_rpo'); fd.append('_ajax_nonce', <?php echo wp_json_encode( wp_create_nonce( 'jinv_partner' ) ); ?>); fd.append('ico', ico);
			fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
				b.disabled = false;
				if (!j.success) { msg.textContent = j.data || 'Nepodarilo sa.'; return; }
				var map = { name: 'pa-name', street: 'pa-street', zip: 'pa-zip', city: 'pa-city', ico: 'pa-ico' };
				Object.keys(map).forEach(function (k) { if (j.data[k]) { document.getElementById(map[k]).value = j.data[k]; } });
				msg.textContent = 'Načítané z Registra právnických osôb. DIČ a IBAN doplňte z faktúry.';
			}).catch(function () { b.disabled = false; msg.textContent = 'Register neodpovedá.'; });
		});
	})();
	</script>
	<?php
}

function jinv_render_partner_detail( $id ) {
	global $wpdb;
	$p = jinv_partner_get( $id );
	if ( ! $p ) {
		echo '<p>Partner neexistuje.</p>';
		return;
	}
	$s    = jinv_partner_stats( $id );
	$docs = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . jinv_table() . " WHERE partner_id = %d AND kind = 'received' ORDER BY issue_date DESC, id DESC LIMIT 100", $id ), ARRAY_A ); // phpcs:ignore
	$addr = trim( $p['street'] . ', ' . trim( $p['zip'] . ' ' . $p['city'] ), ', ' );
	?>
	<h2 class="jinv-partner-h"><?php echo esc_html( $p['name'] ); ?><?php echo (int) $p['active'] ? '' : ' <span class="jinv-st jinv-st-cancelled">archivovaný</span>'; ?></h2>
	<p class="jinv-partner-meta">
		<?php if ( $p['ico'] ) : ?><span>IČO <strong><?php echo esc_html( $p['ico'] ); ?></strong></span><?php endif; ?>
		<?php if ( $p['dic'] ) : ?><span>DIČ <?php echo esc_html( $p['dic'] ); ?></span><?php endif; ?>
		<?php if ( $p['icdph'] ) : ?><span>IČ DPH <?php echo esc_html( $p['icdph'] ); ?></span><?php endif; ?>
		<?php if ( $addr ) : ?><span><?php echo esc_html( $addr ); ?></span><?php endif; ?>
		<?php if ( $p['email'] ) : ?><span><a href="mailto:<?php echo esc_attr( $p['email'] ); ?>"><?php echo esc_html( $p['email'] ); ?></a></span><?php endif; ?>
		<?php if ( $p['phone'] ) : ?><span><?php echo esc_html( $p['phone'] ); ?></span><?php endif; ?>
		<?php if ( $p['iban'] ) : ?><span>IBAN <?php echo esc_html( jinv_iban_format( $p['iban'] ) ); ?></span><?php endif; ?>
		<?php if ( null !== $p['due_days'] ) : ?><span>Splatnosť <?php echo (int) $p['due_days']; ?> dní</span><?php endif; ?>
	</p>
	<?php if ( $p['note'] ) : ?><p class="description"><?php echo nl2br( esc_html( $p['note'] ) ); ?></p><?php endif; ?>
	<div class="jinv-cards">
		<div><span>Neuhradené</span><strong><?php echo (int) $s['open_n']; ?></strong><small><?php echo esc_html( jinv_money( $s['open_sum'] ) ); ?></small></div>
		<div class="<?php echo (int) $s['overdue_n'] ? 'is-warn' : ''; ?>"><span>Po splatnosti</span><strong><?php echo (int) $s['overdue_n']; ?></strong></div>
		<div><span>Nákupy <?php echo (int) current_time( 'Y' ); ?></span><strong><?php echo esc_html( jinv_money( $s['year_sum'] ) ); ?></strong></div>
		<div><span>Faktúr spolu</span><strong><?php echo (int) $s['docs']; ?></strong></div>
	</div>
	<p>
		<a class="button button-primary" href="<?php echo esc_url( jinv_admin_url( array( 'tab' => 'received', 'edit' => 'new', 'partner' => $id ) ) ); ?>">+ Prijatá faktúra</a>
		<?php if ( function_exists( 'jsk_url' ) ) : ?><a class="button" href="<?php echo esc_url( jsk_url( 'j-sklad-docs', array( 'new' => 'receipt', 'partner_id' => $id ) ) ); ?>">+ Príjemka na sklad</a><?php endif; ?>
		<a class="button" href="<?php echo esc_url( jinv_partners_url( array( 'edit' => $id ) ) ); ?>">Upraviť</a>
		<a class="button" href="<?php echo esc_url( jinv_partners_url() ); ?>">← Dodávatelia</a>
	</p>
	<h3>Prijaté faktúry</h3>
	<?php jinv_received_table( (array) $docs, false ); ?>
	<?php
	if ( function_exists( 'jsk_t' ) ) :
		$rec = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . jsk_t( 'docs' ) . " WHERE partner_id = %d AND type = 'receipt' ORDER BY id DESC LIMIT 50", $id ), ARRAY_A ); // phpcs:ignore
		if ( $rec ) :
			?>
			<h3>Príjemky (J-Sklad)</h3>
			<table class="widefat striped jinv-table">
				<thead><tr><th>Číslo</th><th>Dátum</th><th>Doklad dodávateľa</th><th class="n">Suma (nákup)</th><th>Stav</th></tr></thead>
				<tbody>
				<?php foreach ( $rec as $r ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( jsk_url( 'j-sklad-docs', 'draft' === $r['status'] ? array( 'edit' => $r['id'] ) : array( 'view' => $r['id'] ) ) ); ?>"><?php echo esc_html( $r['number'] ? $r['number'] : 'Koncept #' . $r['id'] ); ?></a></td>
						<td><?php echo esc_html( jinv_date( $r['doc_date'] ) ); ?></td>
						<td><?php echo esc_html( $r['partner_doc'] ); ?></td>
						<td class="n"><?php echo esc_html( jinv_money( $r['total'] ) ); ?></td>
						<td><?php echo esc_html( function_exists( 'jsk_status_label' ) ? jsk_status_label( $r['status'] ) : $r['status'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php
		endif;
	endif;
}

add_action( 'admin_post_jinv_partner_save', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jinv_partner_save' );
	$in           = wp_unslash( $_POST );
	$id           = (int) ( $in['id'] ?? 0 );
	$in['active'] = empty( $in['archived'] );
	$r            = jinv_partner_save( $in, $id );
	if ( is_wp_error( $r ) ) {
		$dup = $r->get_error_data();
		wp_die( esc_html( $r->get_error_message() ) . ( ! empty( $dup['id'] ) ? ' <a href="' . esc_url( jinv_partners_url( array( 'partner' => $dup['id'] ) ) ) . '">Otvoriť existujúceho</a>' : '' ) . ' <p><a href="javascript:history.back()">← Späť na formulár</a></p>', 'Dodávateľ sa neuložil', array( 'response' => 200 ) );
	}
	$back = (string) ( $in['back'] ?? '' );
	wp_safe_redirect( $back ? add_query_arg( 'partner', $r, wp_validate_redirect( $back, jinv_partners_url() ) ) : jinv_partners_url( array( 'partner' => $r, 'jinv_msg' => 'psaved' ) ) );
	exit;
} );

/** Zoznam kategórií nákladov (návrhy v poli Kategória). */
function jinv_categories_datalist() {
	$cats = apply_filters( 'jinv_expense_categories', array( 'Tovar', 'Materiál', 'Doprava a poštovné', 'Služby', 'Hosting a softvér', 'Reklama', 'Kancelária', 'Nájom a energie', 'Telefón a internet', 'Poplatky', 'Majetok' ) );
	echo '<datalist id="jinv-cats">';
	foreach ( $cats as $c ) {
		echo '<option>' . esc_html( $c ) . '</option>';
	}
	echo '</datalist>';
}
