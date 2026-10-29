<?php
/**
 * Import účtovníctva z iného programu (formát juc-import/1) – napr. z OBERON-u (Exalogic).
 *
 * Súbor pripraví nástroj tools/oberon2juc.py z databázy OBERON (.mdb). Import:
 *  1. nahratie súboru → náhľad po rokoch (počet dokladov, kontrola vyrovnania, konečné zostatky),
 *  2. výber rokov → zaúčtovanie dokladov (rad podľa typu dokladu, zdroj „import“), vytvorenie účtov a partnerov,
 *  3. voliteľne uzavretie importovaných rokov (iba na čítanie).
 * Opakovaný import nevytvorí duplicity (doklad sa spozná podľa ID z pôvodného programu).
 * Import otvoreného roka sa dá zmazať a zopakovať.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_submenu_page( 'j-uctovnictvo', 'Import – J-Účtovníctvo', 'Import (OBERON)', JUC_CAP, 'j-uctovnictvo-import', 'juc_page_import' );
}, 20 );

/** Priečinok pre nahraté importné súbory (chránený pred prístupom z webu). */
function juc_import_dir() {
	$u   = wp_upload_dir();
	$dir = trailingslashit( $u['basedir'] ) . 'j-uctovnictvo/import';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
		@file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore
		@file_put_contents( $dir . '/index.php', "<?php\n" ); // phpcs:ignore
	}
	return $dir;
}

function juc_import_file() {
	$f = (string) get_user_meta( get_current_user_id(), 'juc_import_file', true );
	return ( $f && is_file( juc_import_dir() . '/' . $f ) ) ? juc_import_dir() . '/' . $f : '';
}

/** Načítaný importný súbor alebo WP_Error. */
function juc_import_data() {
	$f = juc_import_file();
	if ( ! $f ) {
		return new WP_Error( 'juc_imp', 'Nie je nahratý žiadny importný súbor.' );
	}
	$d = json_decode( (string) file_get_contents( $f ), true ); // phpcs:ignore
	if ( ! is_array( $d ) || 'juc-import/1' !== ( $d['format'] ?? '' ) || ! isset( $d['entries'] ) ) {
		return new WP_Error( 'juc_imp', 'Súbor nie je v importnom formáte J-Účtovníctva (juc-import/1).' );
	}
	return $d;
}

/** Identifikátor importovaného dokladu (pre kontrolu duplicít). */
function juc_import_hash( array $d, array $e ) {
	return md5( strtolower( (string) ( $d['source'] ?? 'import' ) ) . '|' . $e['ext_id'] );
}

function juc_import_source_type( array $d ) {
	return substr( sanitize_key( (string) ( $d['source'] ?? 'import' ) ), 0, 20 );
}

/** Rok → [ docs, lines, total, unbalanced, uz, balances[acc3 => MD−D], posted ] */
function juc_import_summary( array $d ) {
	global $wpdb;
	$out = array();
	foreach ( $d['entries'] as $e ) {
		$y = (int) $e['year'];
		if ( ! isset( $out[ $y ] ) ) {
			$out[ $y ] = array( 'docs' => 0, 'lines' => 0, 'total' => 0.0, 'bad' => 0, 'uz' => 0, 'bal' => array(), 'from' => $e['date'], 'to' => $e['date'], 'series' => array() );
		}
		$o = &$out[ $y ];
		++$o['docs'];
		$o['from'] = min( $o['from'], $e['date'] );
		$o['to']   = max( $o['to'], $e['date'] );
		$o['uz']  += 'UZ' === $e['series'] ? 1 : 0;
		$o['series'][ $e['series'] ] = ( $o['series'][ $e['series'] ] ?? 0 ) + 1;
		foreach ( (array) $e['lines'] as $l ) {
			++$o['lines'];
			$a           = (float) $l['amount'];
			$o['total'] += $a;
			$m           = substr( (string) $l['md'], 0, 3 );
			$dd          = substr( (string) $l['d'], 0, 3 );
			$o['bal'][ $m ]  = ( $o['bal'][ $m ] ?? 0 ) + $a;
			$o['bal'][ $dd ] = ( $o['bal'][ $dd ] ?? 0 ) - $a;
			if ( '' === (string) $l['md'] || '' === (string) $l['d'] ) {
				++$o['bad'];
			}
		}
		unset( $o );
	}
	ksort( $out );
	$st = juc_import_source_type( $d );
	foreach ( $out as $y => &$o ) {
		$o['bal'] = array_filter( array_map( function ( $v ) { return round( $v, 2 ); }, $o['bal'] ) );
		ksort( $o['bal'], SORT_STRING );
		$yr          = juc_year_of( sprintf( '%04d-06-30', $y ) );
		$o['year']   = $yr;
		$o['posted'] = $yr ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . juc_t( 'entries' ) . " WHERE year_id = %d AND source = 'import' AND source_type = %s", $yr['id'], $st ) ) : 0; // phpcs:ignore
		$o['other']  = $yr ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . juc_t( 'entries' ) . " WHERE year_id = %d AND source <> 'import'", $yr['id'] ) ) : 0; // phpcs:ignore
	}
	return $out;
}

/* ---------------------------------------------------------------- Akcie */

add_action( 'admin_post_juc_import_upload', function () {
	if ( ! current_user_can( JUC_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'juc_import_upload' );
	$back = juc_url( 'j-uctovnictvo-import' );
	$f    = $_FILES['file'] ?? null; // phpcs:ignore
	if ( ! $f || UPLOAD_ERR_OK !== (int) $f['error'] || ! is_uploaded_file( $f['tmp_name'] ) ) {
		juc_back_err( $back, 'Súbor sa nepodarilo nahrať.' );
	}
	$raw = (string) file_get_contents( $f['tmp_name'] ); // phpcs:ignore
	$d   = json_decode( $raw, true );
	if ( ! is_array( $d ) || 'juc-import/1' !== ( $d['format'] ?? '' ) ) {
		juc_back_err( $back, 'Súbor nie je v importnom formáte J-Účtovníctva (juc-import/1). Databázu OBERON (.mdb) treba najprv previesť nástrojom oberon2juc.py.' );
	}
	$old = juc_import_file();
	if ( $old ) {
		@unlink( $old ); // phpcs:ignore
	}
	$name = 'import-' . wp_generate_password( 16, false ) . '.json';
	file_put_contents( juc_import_dir() . '/' . $name, $raw ); // phpcs:ignore
	update_user_meta( get_current_user_id(), 'juc_import_file', $name );
	wp_safe_redirect( $back );
	exit;
} );

add_action( 'admin_post_juc_import_run', function () {
	if ( ! current_user_can( JUC_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'juc_import_run' );
	$back = juc_url( 'j-uctovnictvo-import' );
	$d    = juc_import_data();
	if ( is_wp_error( $d ) ) {
		juc_back_err( $back, $d->get_error_message() );
	}
	$years = array_map( 'intval', (array) ( $_POST['years'] ?? array() ) ); // phpcs:ignore
	$close = array_map( 'intval', (array) ( $_POST['close'] ?? array() ) ); // phpcs:ignore
	if ( ! $years ) {
		juc_back_err( $back, 'Vyberte aspoň jeden rok.' );
	}
	$r = juc_import_run( $d, $years, $close, ! empty( $_POST['partners'] ) ); // phpcs:ignore
	set_transient( 'juc_import_result_' . get_current_user_id(), $r, 600 );
	wp_safe_redirect( add_query_arg( 'done', 1, $back ) );
	exit;
} );

add_action( 'admin_post_juc_import_delete', function () {
	if ( ! current_user_can( JUC_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'juc_import_delete' );
	$back = juc_url( 'j-uctovnictvo-import' );
	$y    = juc_year( (int) ( $_POST['year_id'] ?? 0 ) ); // phpcs:ignore
	if ( ! $y || 'closed' === $y['status'] ) {
		juc_back_err( $back, 'Import sa dá zmazať len v otvorenom roku.' );
	}
	$n = juc_import_delete_year( (int) $y['id'], sanitize_key( (string) ( $_POST['source_type'] ?? '' ) ) ); // phpcs:ignore
	set_transient( 'juc_import_result_' . get_current_user_id(), array( 'log' => array( 'Rok ' . substr( $y['start_date'], 0, 4 ) . ': zmazaných ' . $n . ' importovaných dokladov.' ) ), 600 );
	wp_safe_redirect( add_query_arg( 'done', 1, $back ) );
	exit;
} );

add_action( 'admin_post_juc_import_clear', function () {
	if ( ! current_user_can( JUC_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'juc_import_clear' );
	$f = juc_import_file();
	if ( $f ) {
		@unlink( $f ); // phpcs:ignore
	}
	delete_user_meta( get_current_user_id(), 'juc_import_file' );
	wp_safe_redirect( juc_url( 'j-uctovnictvo-import' ) );
	exit;
} );

/** Zmaže importované doklady roka (len otvorený rok). */
function juc_import_delete_year( $year_id, $source_type ) {
	global $wpdb;
	$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . juc_t( 'entries' ) . " WHERE year_id = %d AND source = 'import' AND source_type = %s", $year_id, $source_type ) ); // phpcs:ignore
	foreach ( array_chunk( array_map( 'intval', $ids ), 500 ) as $chunk ) {
		$in = implode( ',', $chunk );
		$wpdb->query( 'DELETE FROM ' . juc_t( 'lines' ) . " WHERE entry_id IN ($in)" ); // phpcs:ignore
		$wpdb->query( 'DELETE FROM ' . juc_t( 'entries' ) . " WHERE id IN ($in)" ); // phpcs:ignore
	}
	return count( $ids );
}

/** Uzavretie účtového obdobia (len na čítanie). */
function juc_close_year( $year_id ) {
	global $wpdb;
	return $wpdb->update( juc_t( 'years' ), array( 'status' => 'closed', 'closed_at' => current_time( 'mysql' ) ), array( 'id' => (int) $year_id ) );
}

/**
 * Samotný import vybraných rokov. Vráti [ 'posted' => n, 'skipped' => n, 'errors' => [], 'log' => [] ].
 */
function juc_import_run( array $d, array $years, array $close, $partners ) {
	global $wpdb;
	@set_time_limit( 600 ); // phpcs:ignore
	$st  = juc_import_source_type( $d );
	$res = array( 'posted' => 0, 'skipped' => 0, 'errors' => array(), 'log' => array() );

	// Účty z pôvodného programu (analytiky s pôvodným názvom, daňový / nedaňový).
	foreach ( (array) ( $d['accounts'] ?? array() ) as $a ) {
		$num = preg_replace( '/\D/', '', (string) ( $a['num'] ?? '' ) );
		if ( ! $num || juc_account( $num ) ) {
			continue;
		}
		$acc = juc_account_ensure( $num, sanitize_text_field( (string) ( $a['name'] ?? '' ) ) );
		if ( is_wp_error( $acc ) ) {
			$res['errors'][] = $acc->get_error_message();
			continue;
		}
		if ( isset( $a['tax'] ) && ! (int) $a['tax'] ) {
			$wpdb->update( juc_t( 'accounts' ), array( 'tax' => 0 ), array( 'num' => $num ) );
			wp_cache_delete( 'acc_' . $num, 'juc' );
		}
	}

	// Partneri do adresára J-Fakturácie (podľa IČO / názvu – existujúci sa nemenia).
	$pmap = array();
	if ( function_exists( 'jinv_partner_match' ) ) {
		$new = 0;
		foreach ( (array) ( $d['partners'] ?? array() ) as $p ) {
			$name = trim( (string) ( $p['name'] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}
			$id = jinv_partner_match( $name, (string) ( $p['ico'] ?? '' ) );
			if ( ! $id && $partners ) {
				$p['type'] = 'supplier';
				$p['note'] = 'Import z ' . ( $d['source'] ?? 'iného programu' );
				$saved     = jinv_partner_save( $p );
				if ( is_wp_error( $saved ) ) {
					unset( $p['iban'] );
					$saved = jinv_partner_save( $p );
				}
				$id   = is_wp_error( $saved ) ? 0 : (int) $saved;
				$new += $id ? 1 : 0;
			}
			if ( $id ) {
				$pmap[ mb_strtolower( $name ) ] = $id;
			}
		}
		if ( $partners ) {
			$res['log'][] = 'Partneri: nových ' . $new . ', spárovaných spolu ' . count( $pmap ) . '.';
		}
	}

	// Doklady – zámok účtovníctva (lock_date) sa pri importe histórie neuplatní, uzavretý rok áno.
	$GLOBALS['juc_importing'] = true;
	$per = array();
	foreach ( $d['entries'] as $e ) {
		$y = (int) $e['year'];
		if ( ! in_array( $y, $years, true ) ) {
			continue;
		}
		$hash = juc_import_hash( $d, $e );
		$ex   = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . juc_t( 'entries' ) . " WHERE source = 'import' AND source_type = %s AND source_hash = %s", $st, $hash ) ); // phpcs:ignore
		if ( $ex ) {
			++$res['skipped'];
			continue;
		}
		$pid   = $pmap[ mb_strtolower( (string) $e['partner'] ) ] ?? 0;
		$lines = array();
		foreach ( (array) $e['lines'] as $l ) {
			$lines[] = array( 'md' => $l['md'], 'd' => $l['d'], 'amount' => $l['amount'], 'text' => $l['text'] ? $l['text'] : $e['descr'], 'partner_id' => $pid, 'vs' => $e['vs'] );
		}
		$id = juc_post( array(
			'series'       => $e['series'],
			'number'       => $e['number'],
			'entry_date'   => $e['date'],
			'doc_date'     => $e['date'],
			'descr'        => $e['descr'] . ( $e['ext_doc'] ? ' (' . $e['ext_doc'] . ')' : '' ),
			'partner_id'   => $pid,
			'partner_name' => $e['partner'],
			'vs'           => $e['vs'],
			'source'       => 'import',
			'source_type'  => $st,
			'source_hash'  => $hash,
			'opening'      => ! empty( $e['opening'] ),
		), $lines );
		if ( is_wp_error( $id ) ) {
			$res['errors'][] = $y . ' / ' . $e['number'] . ': ' . $id->get_error_message();
			continue;
		}
		++$res['posted'];
		$per[ $y ] = ( $per[ $y ] ?? 0 ) + 1;
	}
	unset( $GLOBALS['juc_importing'] );
	foreach ( $years as $y ) {
		$res['log'][] = 'Rok ' . $y . ': zaúčtovaných ' . (int) ( $per[ $y ] ?? 0 ) . ' dokladov.';
		$yr = juc_year_of( sprintf( '%04d-06-30', $y ) );
		if ( $yr && in_array( $y, $close, true ) && ! $res['errors'] ) {
			juc_close_year( (int) $yr['id'] );
			$res['log'][] = 'Rok ' . $y . ' je uzavretý (len na čítanie).';
		}
	}
	if ( $res['errors'] && $close ) {
		$res['log'][] = 'Roky sa neuzavreli, lebo import mal chyby.';
	}
	return $res;
}

/* ---------------------------------------------------------------- Stránka */

function juc_page_import() {
	if ( ! current_user_can( JUC_CAP ) ) {
		return;
	}
	juc_head( 'Import účtovníctva (OBERON)' );
	$r = isset( $_GET['done'] ) ? get_transient( 'juc_import_result_' . get_current_user_id() ) : false; // phpcs:ignore
	if ( $r ) {
		delete_transient( 'juc_import_result_' . get_current_user_id() );
		$cls = empty( $r['errors'] ) ? 'success' : 'warning';
		echo '<div class="notice notice-' . esc_attr( $cls ) . '"><p><strong>Import:</strong> zaúčtovaných ' . (int) ( $r['posted'] ?? 0 ) . ' dokladov, preskočených (už importované) ' . (int) ( $r['skipped'] ?? 0 ) . '.</p>';
		foreach ( (array) ( $r['log'] ?? array() ) as $l ) {
			echo '<p>' . esc_html( $l ) . '</p>';
		}
		foreach ( array_slice( (array) ( $r['errors'] ?? array() ), 0, 30 ) as $l ) {
			echo '<p class="juc-warn">' . esc_html( $l ) . '</p>';
		}
		echo '</div>';
	}
	$d = juc_import_data();
	?>
	<div class="juc-panel">
		<h2>Ako preniesť údaje z OBERON-u</h2>
		<ol>
			<li>Databáza firmy v OBERON-e je súbor <code>.mdb</code> (napr. <code>Aveyona_s_r_o.mdb</code>). Pri zatvorenom OBERON-e ho skopírujte.</li>
			<li>Nástrojom <code>tools/oberon2juc.py</code> (v priečinku pluginu) ho preveďte na importný súbor: <code>python3 oberon2juc.py Firma.mdb Firma_OLD.mdb -o import.json</code>. Nástroj prečíta účtovný denník, adresár partnerov a názvy účtov.</li>
			<li>Súbor nahrajte sem, skontrolujte náhľad po rokoch a vyberte roky na import.</li>
		</ol>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="juc-inline">
			<?php wp_nonce_field( 'juc_import_upload' ); ?>
			<input type="hidden" name="action" value="juc_import_upload">
			<input type="file" name="file" accept=".json,application/json" required>
			<button class="button button-primary">Nahrať importný súbor</button>
		</form>
	</div>
	<?php
	if ( is_wp_error( $d ) ) {
		echo '<p class="juc-muted">' . esc_html( $d->get_error_message() ) . '</p></div>';
		return;
	}
	$sum = juc_import_summary( $d );
	$st  = juc_import_source_type( $d );
	echo '<h2>Náhľad – ' . esc_html( (string) ( $d['source'] ?? '' ) ) . ' <span class="juc-muted" style="font-weight:400;font-size:13px">súbor vytvorený ' . esc_html( (string) ( $d['created'] ?? '' ) ) . ', partnerov ' . count( (array) ( $d['partners'] ?? array() ) ) . ', nových účtov ' . count( (array) ( $d['accounts'] ?? array() ) ) . '</span></h2>';
	if ( ! empty( $d['errors'] ) ) {
		echo '<div class="notice notice-warning inline"><p>Pri prevode sa nepodarilo spracovať ' . count( $d['errors'] ) . ' dokladov:</p><ul>';
		foreach ( array_slice( $d['errors'], 0, 20 ) as $e ) {
			echo '<li>' . esc_html( $e ) . '</li>';
		}
		echo '</ul></div>';
	}
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'juc_import_run' ); ?>
		<input type="hidden" name="action" value="juc_import_run">
		<table class="widefat striped juc-table">
			<thead><tr><th>Importovať</th><th>Rok</th><th>Obdobie dokladov</th><th>Doklady podľa radu</th><th class="n">Dokladov</th><th class="n">Zápisov</th><th>Kontrola</th><th>V J-Účtovníctve</th><th>Po importe uzavrieť</th></tr></thead>
			<tbody>
			<?php foreach ( $sum as $y => $o ) :
				$closed  = $o['year'] && 'closed' === $o['year']['status'];
				$done    = $o['posted'] >= $o['docs'];
				$ser     = array();
				foreach ( $o['series'] as $s => $n ) {
					$ser[] = $s . ' ' . $n;
				}
				$res_bal = array_sum( array_filter( $o['bal'], function ( $k ) { return in_array( ( (string) $k )[0], array( '5', '6' ), true ); }, ARRAY_FILTER_USE_KEY ) );
				?>
				<tr>
					<td><input type="checkbox" name="years[]" value="<?php echo (int) $y; ?>" <?php checked( ! $done && ! $closed ); ?> <?php disabled( $closed ); ?>></td>
					<td><strong><?php echo (int) $y; ?></strong></td>
					<td><?php echo esc_html( juc_date( $o['from'] ) . ' – ' . juc_date( $o['to'] ) ); ?></td>
					<td class="juc-muted"><?php echo esc_html( implode( ', ', $ser ) ); ?></td>
					<td class="n"><?php echo (int) $o['docs']; ?></td>
					<td class="n"><?php echo (int) $o['lines']; ?></td>
					<td><?php
					echo $o['bad'] ? '<span class="juc-warn">' . (int) $o['bad'] . ' zápisov bez účtu</span><br>' : '<span class="juc-ok">vyrovnané</span><br>';
					echo $o['uz'] ? 's účtovnou závierkou' : ( abs( $res_bal ) > 0.004 ? 'bez závierky, výsledok ' . esc_html( juc_money( -$res_bal ) ) . ' €' : 'bez závierky' );
					?></td>
					<td><?php
					echo $o['posted'] ? esc_html( $o['posted'] . ' importovaných' ) : '–';
					echo $o['other'] ? '<br><span class="juc-warn">' . (int) $o['other'] . ' iných dokladov v roku</span>' : '';
					echo $closed ? '<br><strong>uzavretý</strong>' : '';
					?></td>
					<td><input type="checkbox" name="close[]" value="<?php echo (int) $y; ?>" <?php checked( $o['uz'] > 0 && ! $closed ); ?> <?php disabled( $closed ); ?>></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p><label><input type="checkbox" name="partners" value="1" checked> Doplniť chýbajúcich partnerov do adresára J-Fakturácie (existujúci sa nemenia)</label></p>
		<p class="description">Doklady sa zaúčtujú s pôvodným číslom a dátumom; rad podľa typu (PS počiatočný stav, BV banka, PD/VD pokladňa, DF/OF faktúry, MZ mzdy, ID interné, UZ závierka). Už importované doklady sa preskočia. Uzavretý rok je len na čítanie.</p>
		<p><button class="button button-primary button-large">Importovať vybrané roky</button></p>
	</form>

	<h2>Konečné zostatky v importnom súbore (syntetické účty, MD − Dal)</h2>
	<p class="description">Na porovnanie so súvahou a s obratovou predvahou z OBERON-u. Rok so závierkou má zostatky nulové (účty sú uzavreté na 702/710).</p>
	<table class="widefat striped juc-table">
		<thead><tr><th>Účet</th><?php foreach ( array_keys( $sum ) as $y ) { echo '<th class="n">' . (int) $y . '</th>'; } ?></tr></thead>
		<tbody>
		<?php
		$accs = array();
		foreach ( $sum as $o ) {
			$accs = array_merge( $accs, array_keys( $o['bal'] ) );
		}
		$accs = array_unique( array_map( 'strval', $accs ) );
		sort( $accs, SORT_STRING );
		foreach ( $accs as $a ) {
			echo '<tr><td>' . esc_html( $a . ' ' . juc_account_name( $a ) ) . '</td>';
			foreach ( $sum as $o ) {
				echo '<td class="n">' . esc_html( juc_money( $o['bal'][ $a ] ?? null ) ) . '</td>';
			}
			echo '</tr>';
		}
		?>
		</tbody>
	</table>

	<h2>Oprava importu</h2>
	<p class="description">Import v otvorenom roku sa dá zmazať (iba importované doklady) a nahrať znova. Uzavretý rok sa nemení.</p>
	<div class="juc-inline">
	<?php foreach ( $sum as $y => $o ) :
		if ( ! $o['posted'] || ! $o['year'] || 'closed' === $o['year']['status'] ) {
			continue;
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Zmazať <?php echo (int) $o['posted']; ?> importovaných dokladov roka <?php echo (int) $y; ?>?');">
			<?php wp_nonce_field( 'juc_import_delete' ); ?>
			<input type="hidden" name="action" value="juc_import_delete">
			<input type="hidden" name="year_id" value="<?php echo (int) $o['year']['id']; ?>">
			<input type="hidden" name="source_type" value="<?php echo esc_attr( $st ); ?>">
			<button class="button">Zmazať import <?php echo (int) $y; ?></button>
		</form>
	<?php endforeach; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'juc_import_clear' ); ?>
			<input type="hidden" name="action" value="juc_import_clear">
			<button class="button-link juc-danger">Odstrániť nahratý súbor</button>
		</form>
	</div>
	</div>
	<?php
}
