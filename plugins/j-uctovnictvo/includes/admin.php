<?php
/** Administrácia J-Účtovníctva: menu, prehľad, účtovné doklady (denník), nový doklad, nastavenia. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	$q      = function_exists( 'juc_queue_count' ) ? juc_queue_count() : 0;
	$bubble = $q ? ' <span class="awaiting-mod count-' . $q . '"><span class="pending-count">' . $q . '</span></span>' : '';
	add_menu_page( 'J-Účtovníctvo', 'J-Účtovníctvo' . $bubble, JUC_CAP, 'j-uctovnictvo', 'juc_page_overview', 'dashicons-book-alt', 58 );
	$pages = array(
		'j-uctovnictvo'          => array( 'Prehľad', 'juc_page_overview' ),
		'j-uctovnictvo-entries'  => array( 'Účtovné doklady', 'juc_page_entries' ),
		'j-uctovnictvo-new'      => array( 'Nový doklad', 'juc_page_new' ),
		'j-uctovnictvo-queue'    => array( 'Na zaúčtovanie' . $bubble, 'juc_page_queue' ),
		'j-uctovnictvo-ledger'   => array( 'Hlavná kniha', 'juc_page_ledger' ),
		'j-uctovnictvo-trial'    => array( 'Obratová predvaha', 'juc_page_trial' ),
		'j-uctovnictvo-saldo'    => array( 'Saldokonto', 'juc_page_saldo' ),
		'j-uctovnictvo-accounts' => array( 'Účtový rozvrh', 'juc_page_accounts' ),
		'j-uctovnictvo-settings' => array( 'Nastavenia', 'juc_page_settings' ),
	);
	foreach ( $pages as $slug => $p ) {
		add_submenu_page( 'j-uctovnictvo', wp_strip_all_tags( $p[0] ) . ' – J-Účtovníctvo', $p[0], JUC_CAP, $slug, $p[1] );
	}
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( false !== strpos( (string) $hook, 'j-uctovnictvo' ) ) {
		wp_enqueue_style( 'j-uctovnictvo', JUC_URL . 'assets/admin.css', array(), JUC_VERSION . '.' . (int) @filemtime( JUC_DIR . 'assets/admin.css' ) ); // phpcs:ignore
	}
} );

function juc_url( $page = 'j-uctovnictvo', $args = array() ) {
	return add_query_arg( $args, admin_url( 'admin.php?page=' . $page ) );
}

function juc_back_err( $url, $err ) {
	set_transient( 'juc_err_' . get_current_user_id(), $err, 120 );
	wp_safe_redirect( add_query_arg( 'juc_msg', 'err', $url ) );
	exit;
}

function juc_head( $title, $actions = '' ) {
	$y = juc_current_year();
	echo '<div class="wrap juc"><h1 class="wp-heading-inline">' . esc_html( $title ) . ' <span class="juc-ver">J-Účtovníctvo v' . esc_html( JUC_VERSION ) . '</span></h1>' . $actions; // phpcs:ignore
	echo '<hr class="wp-header-end">';
	juc_notice();
	if ( $y && 'closed' === $y['status'] ) {
		echo '<div class="notice notice-info"><p>Obdobie ' . esc_html( substr( $y['start_date'], 0, 4 ) ) . ' je uzavreté – len na čítanie.</p></div>';
	}
}

function juc_notice() {
	$m = isset( $_GET['juc_msg'] ) ? sanitize_key( $_GET['juc_msg'] ) : ''; // phpcs:ignore
	$t = array(
		'posted'   => array( 'success', 'Doklad je zaúčtovaný.' ),
		'storno'   => array( 'success', 'Doklad je stornovaný – vznikol stornovací doklad.' ),
		'saved'    => array( 'success', 'Nastavenia sú uložené.' ),
		'accadded' => array( 'success', 'Účet je pridaný.' ),
		'accsaved' => array( 'success', 'Účet je uložený.' ),
		'auto'     => array( 'info', '' ),
		'err'      => array( 'error', 'Akcia sa nepodarila.' ),
	);
	if ( ! isset( $t[ $m ] ) ) {
		return;
	}
	$e = get_transient( 'juc_err_' . get_current_user_id() );
	delete_transient( 'juc_err_' . get_current_user_id() );
	$txt = trim( $t[ $m ][1] . ( $e ? ' ' . $e : '' ) );
	if ( '' !== $txt ) {
		echo '<div class="notice notice-' . esc_attr( $t[ $m ][0] ) . ' is-dismissible"><p>' . esc_html( $txt ) . '</p></div>';
	}
}

/** Výber účtového obdobia (pre zostavy). */
function juc_year_select( $page, $extra = array() ) {
	$cur = juc_current_year();
	echo '<form method="get" class="juc-year"><input type="hidden" name="page" value="' . esc_attr( $page ) . '">';
	foreach ( $extra as $k => $v ) {
		echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
	}
	echo '<label>Obdobie <select name="year" onchange="this.form.submit()">';
	foreach ( juc_years() as $y ) {
		echo '<option value="' . (int) $y['id'] . '" ' . selected( (int) $cur['id'], (int) $y['id'], false ) . '>' . esc_html( substr( $y['start_date'], 0, 4 ) . ( 'closed' === $y['status'] ? ' (uzavreté)' : '' ) ) . '</option>';
	}
	echo '</select></label></form>';
}

/* ---------------------------------------------------------------- Prehľad */

function juc_page_overview() {
	if ( ! current_user_can( JUC_CAP ) ) {
		return;
	}
	$y  = juc_current_year();
	$t  = juc_turnovers( (int) $y['id'], '', '', 3 );
	$ns = 0.0;
	$vs = 0.0;
	foreach ( $t as $acc => $r ) {
		if ( '5' === $acc[0] ) {
			$ns += juc_balance( $r );
		} elseif ( '6' === $acc[0] ) {
			$vs -= juc_balance( $r );
		}
	}
	$bal = function ( $syn ) use ( $t ) {
		return isset( $t[ $syn ] ) ? juc_balance( $t[ $syn ] ) : 0.0;
	};
	juc_head( 'Prehľad účtovníctva', ' <a class="page-title-action" href="' . esc_url( juc_url( 'j-uctovnictvo-new' ) ) . '">Nový doklad</a>' );
	if ( ! get_option( 'juc_setup_done' ) && function_exists( 'juc_page_wizard' ) ) {
		echo '<div class="juc-setup"><h2>Založenie účtovníctva</h2><p>Účtovníctvo ešte nie je založené. Sprievodca prejde údaje firmy, účtové obdobie, účtový rozvrh, počiatočné stavy a napojenie faktúr, banky a skladu.</p><p><a class="button button-primary button-hero" href="' . esc_url( juc_url( 'j-uctovnictvo-wizard' ) ) . '">Spustiť sprievodcu založením</a></p></div>';
	}
	juc_year_select( 'j-uctovnictvo' );
	$q = function_exists( 'juc_queue_count' ) ? juc_queue_count() : 0;
	?>
	<div class="juc-cards">
		<div><span>Výnosy</span><strong><?php echo esc_html( juc_money( $vs ) ); ?> €</strong></div>
		<div><span>Náklady</span><strong><?php echo esc_html( juc_money( $ns ) ); ?> €</strong></div>
		<div class="<?php echo $vs - $ns < 0 ? 'is-neg' : 'is-pos'; ?>"><span>Výsledok hospodárenia (pred zdanením)</span><strong><?php echo esc_html( juc_money( $vs - $ns ) ); ?> €</strong></div>
		<div><span>Banka (221)</span><strong><?php echo esc_html( juc_money( $bal( '221' ) ) ); ?> €</strong></div>
		<div><span>Pokladňa (211)</span><strong><?php echo esc_html( juc_money( $bal( '211' ) ) ); ?> €</strong></div>
		<div><span>Pohľadávky – odberatelia (311)</span><strong><?php echo esc_html( juc_money( $bal( '311' ) ) ); ?> €</strong></div>
		<div><span>Záväzky – dodávatelia (321)</span><strong><?php echo esc_html( juc_money( -1 * $bal( '321' ) ) ); ?> €</strong></div>
		<div class="<?php echo $q ? 'is-warn' : ''; ?>"><span>Na zaúčtovanie</span><strong><?php echo (int) $q; ?></strong><?php if ( $q ) : ?><a href="<?php echo esc_url( juc_url( 'j-uctovnictvo-queue' ) ); ?>">Otvoriť →</a><?php endif; ?></div>
	</div>
	<?php if ( function_exists( 'juc_render_autopost_box' ) ) { juc_render_autopost_box(); } ?>
	<h2>Posledné doklady</h2>
	<?php juc_entries_table( array( 'year_id' => (int) $y['id'], 'limit' => 15 ) ); ?>
	</div>
	<?php
}

/* ---------------------------------------------------------------- Doklady (denník) */

/** Tabuľka dokladov so zápismi. $a: year_id, series, from, to, s, limit, acc */
function juc_entries_table( array $a ) {
	global $wpdb;
	$a = wp_parse_args( $a, array( 'year_id' => 0, 'series' => '', 'from' => '', 'to' => '', 's' => '', 'limit' => 300, 'offset' => 0 ) );
	$w = $wpdb->prepare( 'e.year_id = %d', $a['year_id'] );
	if ( $a['series'] ) {
		$w .= $wpdb->prepare( ' AND e.series = %s', $a['series'] );
	}
	if ( $a['from'] ) {
		$w .= $wpdb->prepare( ' AND e.entry_date >= %s', $a['from'] );
	}
	if ( $a['to'] ) {
		$w .= $wpdb->prepare( ' AND e.entry_date <= %s', $a['to'] );
	}
	if ( '' !== $a['s'] ) {
		$like = '%' . $wpdb->esc_like( $a['s'] ) . '%';
		$w   .= $wpdb->prepare( ' AND (e.number LIKE %s OR e.descr LIKE %s OR e.partner_name LIKE %s OR e.vs LIKE %s)', $like, $like, $like, $like );
	}
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT e.* FROM ' . juc_t( 'entries' ) . " e WHERE $w ORDER BY e.entry_date DESC, e.id DESC LIMIT %d OFFSET %d", $a['limit'], $a['offset'] ), ARRAY_A ); // phpcs:ignore
	$ids  = wp_list_pluck( (array) $rows, 'id' );
	$ln   = array();
	if ( $ids ) {
		foreach ( (array) $wpdb->get_results( 'SELECT * FROM ' . juc_t( 'lines' ) . ' WHERE entry_id IN (' . implode( ',', array_map( 'intval', $ids ) ) . ') ORDER BY id', ARRAY_A ) as $l ) { // phpcs:ignore
			$ln[ $l['entry_id'] ][] = $l;
		}
	}
	$ser = juc_series();
	?>
	<table class="widefat juc-table juc-journal">
		<thead><tr><th>Dátum</th><th>Doklad</th><th>Popis / partner</th><th>MD</th><th>Dal</th><th class="n">Suma €</th><th>Text zápisu</th></tr></thead>
		<tbody>
		<?php if ( ! $rows ) : ?><tr><td colspan="7">Žiadne doklady.</td></tr><?php endif; ?>
		<?php
		foreach ( (array) $rows as $e ) :
			$ls  = $ln[ $e['id'] ] ?? array();
			$cnt = max( 1, count( $ls ) );
			foreach ( $ls ? $ls : array( null ) as $i => $l ) :
				?>
				<tr class="<?php echo 0 === $i ? 'juc-first' : ''; ?><?php echo 'posted' !== $e['status'] ? ' juc-st-' . esc_attr( $e['status'] ) : ''; ?>">
					<?php if ( 0 === $i ) : ?>
						<td rowspan="<?php echo (int) $cnt; ?>"><?php echo esc_html( juc_date( $e['entry_date'] ) ); ?></td>
						<td rowspan="<?php echo (int) $cnt; ?>"><a href="<?php echo esc_url( juc_url( 'j-uctovnictvo-entries', array( 'entry' => $e['id'] ) ) ); ?>"><strong><?php echo esc_html( $e['number'] ); ?></strong></a><br><small title="<?php echo esc_attr( $ser[ $e['series'] ] ?? '' ); ?>"><?php echo esc_html( $e['series'] ); ?><?php echo 'cancelled' === $e['status'] ? ' · stornovaný' : ( 'storno' === $e['status'] ? ' · storno' : '' ); ?><?php echo $e['source'] ? ' · auto' : ''; ?></small></td>
						<td rowspan="<?php echo (int) $cnt; ?>"><?php echo esc_html( $e['descr'] ); ?><?php echo $e['partner_name'] ? '<br><small>' . esc_html( $e['partner_name'] ) . ( $e['vs'] ? ' · VS ' . esc_html( $e['vs'] ) : '' ) . '</small>' : ''; ?></td>
					<?php endif; ?>
					<?php if ( $l ) : ?>
						<td title="<?php echo esc_attr( juc_account_name( $l['md'] ) ); ?>"><?php echo esc_html( $l['md'] ); ?></td>
						<td title="<?php echo esc_attr( juc_account_name( $l['d'] ) ); ?>"><?php echo esc_html( $l['d'] ); ?></td>
						<td class="n"><?php echo esc_html( juc_money( $l['amount'] ) ); ?></td>
						<td><?php echo esc_html( $l['text'] ); ?></td>
					<?php else : ?>
						<td colspan="4">—</td>
					<?php endif; ?>
				</tr>
			<?php endforeach; ?>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

function juc_page_entries() {
	if ( ! current_user_can( JUC_CAP ) ) {
		return;
	}
	$id = (int) ( $_GET['entry'] ?? 0 ); // phpcs:ignore
	if ( $id ) {
		juc_render_entry( $id );
		return;
	}
	$y    = juc_current_year();
	$ser  = sanitize_key( $_GET['series'] ?? '' ); // phpcs:ignore
	$ser  = strtoupper( $ser );
	$from = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $_GET['from'] ?? '' ) ) ? $_GET['from'] : ''; // phpcs:ignore
	$to   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $_GET['to'] ?? '' ) ) ? $_GET['to'] : ''; // phpcs:ignore
	$q    = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ); // phpcs:ignore
	juc_head( 'Účtovné doklady – denník', ' <a class="page-title-action" href="' . esc_url( juc_url( 'j-uctovnictvo-new' ) ) . '">Nový doklad</a> <a class="page-title-action" href="' . esc_url( juc_url( 'j-uctovnictvo-entries', array( 'year' => $y['id'], 'series' => $ser, 'from' => $from, 'to' => $to, 's' => $q, 'print' => 1 ) ) ) . '" target="_blank">Tlačiť denník</a>' );
	if ( ! empty( $_GET['print'] ) ) { // phpcs:ignore
		echo '<style>#adminmenumain,#wpadminbar,#wpfooter,.notice,.juc-filter,.page-title-action{display:none!important}#wpcontent{margin:0!important}</style><script>addEventListener("load",function(){print()})</script>';
		echo '<p><strong>' . esc_html( juc_opt( 'company' ) ) . '</strong> · IČO ' . esc_html( juc_opt( 'ico' ) ) . ' · Účtovný denník ' . esc_html( substr( $y['start_date'], 0, 4 ) ) . '</p>';
	}
	?>
	<form method="get" class="juc-filter">
		<input type="hidden" name="page" value="j-uctovnictvo-entries"><input type="hidden" name="year" value="<?php echo (int) $y['id']; ?>">
		<select name="series"><option value="">Všetky série</option><?php foreach ( juc_series() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $ser, $k ); ?>><?php echo esc_html( $k . ' – ' . $l ); ?></option><?php endforeach; ?></select>
		<label>od <input type="date" name="from" value="<?php echo esc_attr( $from ); ?>"></label>
		<label>do <input type="date" name="to" value="<?php echo esc_attr( $to ); ?>"></label>
		<input type="search" name="s" value="<?php echo esc_attr( $q ); ?>" placeholder="Číslo, popis, partner, VS">
		<button class="button">Filtrovať</button>
	</form>
	<?php
	juc_year_select( 'j-uctovnictvo-entries' );
	juc_entries_table( array( 'year_id' => (int) $y['id'], 'series' => $ser, 'from' => $from, 'to' => $to, 's' => $q, 'limit' => empty( $_GET['print'] ) ? 300 : 100000 ) ); // phpcs:ignore
	echo '</div>';
}

function juc_render_entry( $id ) {
	$e = juc_entry( $id );
	juc_head( $e ? 'Účtovný doklad ' . $e['number'] : 'Doklad' );
	if ( ! $e ) {
		echo '<p>Doklad neexistuje.</p></div>';
		return;
	}
	$u   = get_userdata( (int) $e['user_id'] );
	$src = juc_source_link( $e );
	?>
	<div class="juc-panel">
		<p class="juc-meta">
			<span>Séria: <strong><?php echo esc_html( $e['series'] . ' – ' . ( juc_series()[ $e['series'] ] ?? '' ) ); ?></strong></span>
			<span>Dátum účtovného prípadu: <strong><?php echo esc_html( juc_date( $e['entry_date'] ) ); ?></strong></span>
			<span>Zaúčtované: <?php echo esc_html( date_i18n( 'j. n. Y H:i', strtotime( $e['created_at'] ) ) ); ?> (<?php echo esc_html( $u ? $u->display_name : 'automaticky' ); ?>)</span>
			<span>Stav: <strong><?php echo esc_html( array( 'posted' => 'zaúčtovaný', 'cancelled' => 'stornovaný', 'storno' => 'stornovací doklad' )[ $e['status'] ] ?? $e['status'] ); ?></strong></span>
			<?php if ( $e['partner_name'] ) : ?><span>Partner: <?php echo esc_html( $e['partner_name'] ); ?></span><?php endif; ?>
			<?php if ( $e['vs'] ) : ?><span>VS / párovací symbol: <?php echo esc_html( $e['vs'] ); ?></span><?php endif; ?>
			<?php if ( $src ) : ?><span>Zdrojový doklad: <?php echo $src; // phpcs:ignore ?></span><?php endif; ?>
		</p>
		<p><strong><?php echo esc_html( $e['descr'] ); ?></strong></p>
		<table class="widefat striped juc-table">
			<thead><tr><th>MD</th><th>Dal</th><th class="n">Suma €</th><th>Text</th><th>Partner / VS</th></tr></thead>
			<tbody>
			<?php foreach ( $e['lines'] as $l ) : ?>
				<tr>
					<td><a href="<?php echo esc_url( juc_url( 'j-uctovnictvo-ledger', array( 'acc' => $l['md'] ) ) ); ?>"><?php echo esc_html( $l['md'] ); ?></a> <small><?php echo esc_html( juc_account_name( $l['md'] ) ); ?></small></td>
					<td><a href="<?php echo esc_url( juc_url( 'j-uctovnictvo-ledger', array( 'acc' => $l['d'] ) ) ); ?>"><?php echo esc_html( $l['d'] ); ?></a> <small><?php echo esc_html( juc_account_name( $l['d'] ) ); ?></small></td>
					<td class="n"><?php echo esc_html( juc_money( $l['amount'] ) ); ?></td>
					<td><?php echo esc_html( $l['text'] ); ?></td>
					<td><?php echo esc_html( trim( ( (int) $l['partner_id'] && function_exists( 'jinv_partner_get' ) && ( $pp = jinv_partner_get( (int) $l['partner_id'] ) ) ? $pp['name'] : '' ) . ( $l['vs'] ? ' · ' . $l['vs'] : '' ), ' ·' ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
			<tfoot><tr><th colspan="2">Spolu</th><th class="n"><?php echo esc_html( juc_money( $e['total'] ) ); ?></th><th colspan="2"></th></tr></tfoot>
		</table>
		<p>
			<a class="button" href="<?php echo esc_url( juc_url( 'j-uctovnictvo-entries' ) ); ?>">← Doklady</a>
			<?php if ( 'posted' === $e['status'] ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="juc-inline" onsubmit="return confirm('Stornovať doklad <?php echo esc_js( $e['number'] ); ?>? Vznikne stornovací doklad so zápornými sumami.');">
					<?php wp_nonce_field( 'juc_storno_' . $e['id'] ); ?><input type="hidden" name="action" value="juc_storno"><input type="hidden" name="id" value="<?php echo (int) $e['id']; ?>">
					<input type="text" name="reason" placeholder="Dôvod storna" class="regular-text">
					<button class="button juc-danger">Stornovať</button>
				</form>
				<?php if ( ! $e['source'] ) : ?><a class="button" href="<?php echo esc_url( juc_url( 'j-uctovnictvo-new', array( 'copy' => $e['id'] ) ) ); ?>">Kopírovať do nového dokladu</a><?php endif; ?>
			<?php endif; ?>
			<?php if ( (int) $e['storno_of'] ) : ?><a class="button" href="<?php echo esc_url( juc_url( 'j-uctovnictvo-entries', array( 'entry' => $e['storno_of'] ) ) ); ?>">Pôvodný doklad</a><?php endif; ?>
		</p>
	</div>
	</div>
	<?php
}

add_action( 'admin_post_juc_storno', function () {
	if ( ! current_user_can( JUC_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$id = (int) ( $_POST['id'] ?? 0 );
	check_admin_referer( 'juc_storno_' . $id );
	$r = juc_storno( $id, sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) ) ); // phpcs:ignore
	if ( is_wp_error( $r ) ) {
		juc_back_err( juc_url( 'j-uctovnictvo-entries', array( 'entry' => $id ) ), $r->get_error_message() );
	}
	wp_safe_redirect( juc_url( 'j-uctovnictvo-entries', array( 'entry' => $r, 'juc_msg' => 'storno' ) ) );
	exit;
} );

/* ---------------------------------------------------------------- Nový doklad */

function juc_page_new() {
	if ( ! current_user_can( JUC_CAP ) ) {
		return;
	}
	$copy = (int) ( $_GET['copy'] ?? 0 ); // phpcs:ignore
	$src  = $copy ? juc_entry( $copy ) : null;
	$ser  = strtoupper( sanitize_key( $_GET['series'] ?? ( $src['series'] ?? 'ID' ) ) ); // phpcs:ignore
	$tpl  = juc_templates();
	juc_head( 'Nový účtovný doklad' );
	$lines = $src ? $src['lines'] : array( array( 'md' => '', 'd' => '', 'amount' => '', 'text' => '' ) );
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="juc-panel" id="juc-new">
		<?php wp_nonce_field( 'juc_new' ); ?><input type="hidden" name="action" value="juc_new">
		<div class="juc-head-fields">
			<label>Séria<br><select name="series"><?php foreach ( juc_series() as $k => $l ) : if ( in_array( $k, array( 'OF', 'OD', 'DF', 'BV', 'SK' ), true ) ) { continue; } ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $ser, $k ); ?>><?php echo esc_html( $k . ' – ' . $l ); ?></option><?php endforeach; ?></select></label>
			<label>Číslo dokladu<br><input name="number" placeholder="automaticky" style="width:150px"></label>
			<label>Dátum účtovného prípadu<br><input type="date" name="entry_date" required value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>"></label>
			<label class="juc-wide">Popis<br><input name="descr" required class="large-text" value="<?php echo esc_attr( $src['descr'] ?? '' ); ?>" placeholder="napr. Úhrada bankového poplatku za október"></label>
			<label>Partner<br><input name="partner_name" class="regular-text" value="<?php echo esc_attr( $src['partner_name'] ?? '' ); ?>"></label>
			<label>VS / párovací symbol<br><input name="vs" value="<?php echo esc_attr( $src['vs'] ?? '' ); ?>" style="width:150px"></label>
		</div>
		<table class="widefat juc-table juc-lines">
			<thead><tr><th>Predkontácia</th><th>MD</th><th>Dal</th><th class="n">Suma €</th><th>Text zápisu</th><th></th></tr></thead>
			<tbody id="juc-lines">
			<?php foreach ( $lines as $l ) : ?>
				<tr>
					<td><select class="juc-tpl"><option value="">—</option><?php foreach ( $tpl as $k => $t ) : ?><option value="<?php echo esc_attr( $t[1] . '|' . $t[2] . '|' . $t[0] ); ?>"><?php echo esc_html( $t[0] ); ?></option><?php endforeach; ?></select></td>
					<td><input name="md[]" list="juc-accs" value="<?php echo esc_attr( $l['md'] ); ?>" class="juc-acc" inputmode="numeric"><small class="juc-accn"></small></td>
					<td><input name="d[]" list="juc-accs" value="<?php echo esc_attr( $l['d'] ); ?>" class="juc-acc" inputmode="numeric"><small class="juc-accn"></small></td>
					<td class="n"><input name="amount[]" value="<?php echo '' === $l['amount'] ? '' : esc_attr( number_format( (float) $l['amount'], 2, ',', '' ) ); ?>" inputmode="decimal" class="juc-amt"></td>
					<td><input name="text[]" value="<?php echo esc_attr( $l['text'] ); ?>" class="regular-text"></td>
					<td><button type="button" class="button-link juc-del" aria-label="Odstrániť riadok">✕</button></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
			<tfoot><tr><td colspan="3"><button type="button" class="button" id="juc-add">+ Riadok</button></td><td class="n"><strong id="juc-sum">0,00</strong></td><td colspan="2"></td></tr></tfoot>
		</table>
		<?php juc_accounts_datalist(); ?>
		<p><button class="button button-primary">Zaúčtovať</button> <span class="description">Zaúčtovaný doklad sa už nedá meniť – oprava je storno a nový doklad.</span></p>
	</form>
	<script>
	(function () {
		var names = {}; document.querySelectorAll('#juc-accs option').forEach(function (o) { names[o.value] = o.textContent.replace(/^\d+\s/, ''); });
		var tb = document.getElementById('juc-lines');
		function num(v) { return parseFloat(String(v).replace(/\s/g, '').replace(',', '.')) || 0; }
		function sum() { var s = 0; tb.querySelectorAll('.juc-amt').forEach(function (i) { s += num(i.value); }); document.getElementById('juc-sum').textContent = s.toFixed(2).replace('.', ','); }
		function bind(tr) {
			tr.querySelectorAll('.juc-acc').forEach(function (i) { var sh = i.parentNode.querySelector('.juc-accn'); var f = function () { sh.textContent = names[i.value] || (i.value ? '⚠ nie je v rozvrhu' : ''); }; i.addEventListener('input', f); f(); });
			tr.querySelector('.juc-amt').addEventListener('input', sum);
			tr.querySelector('.juc-del').addEventListener('click', function () { if (tb.rows.length > 1) { tr.remove(); sum(); } });
			tr.querySelector('.juc-tpl').addEventListener('change', function () {
				var v = this.value.split('|'); if (!this.value) { return; }
				var md = tr.querySelector('[name="md[]"]'), d = tr.querySelector('[name="d[]"]'), t = tr.querySelector('[name="text[]"]');
				if (v[0]) { md.value = v[0]; } if (v[1]) { d.value = v[1]; } if (!t.value) { t.value = v[2]; }
				md.dispatchEvent(new Event('input')); d.dispatchEvent(new Event('input')); (v[0] ? (v[1] ? tr.querySelector('.juc-amt') : d) : md).focus();
			});
		}
		Array.prototype.forEach.call(tb.rows, bind); sum();
		document.getElementById('juc-add').addEventListener('click', function () {
			var tr = tb.rows[tb.rows.length - 1].cloneNode(true);
			tr.querySelectorAll('input').forEach(function (i) { i.value = ''; }); tr.querySelector('select').value = '';
			tr.querySelectorAll('.juc-accn').forEach(function (s) { s.textContent = ''; });
			tb.appendChild(tr); bind(tr); tr.querySelector('.juc-tpl').focus();
		});
	})();
	</script>
	</div>
	<?php
}

add_action( 'admin_post_juc_new', function () {
	if ( ! current_user_can( JUC_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'juc_new' );
	$in    = wp_unslash( $_POST );
	$lines = array();
	foreach ( (array) ( $in['md'] ?? array() ) as $i => $md ) {
		$amt = (float) str_replace( array( ' ', ',' ), array( '', '.' ), (string) ( $in['amount'][ $i ] ?? '0' ) );
		if ( '' === trim( (string) $md ) && '' === trim( (string) ( $in['d'][ $i ] ?? '' ) ) && ! $amt ) {
			continue;
		}
		$lines[] = array( 'md' => $md, 'd' => $in['d'][ $i ] ?? '', 'amount' => $amt, 'text' => $in['text'][ $i ] ?? '', 'vs' => $in['vs'] ?? '' );
	}
	$ser = strtoupper( sanitize_key( $in['series'] ?? 'ID' ) );
	$r   = juc_post( array(
		'series'       => $ser,
		'number'       => sanitize_text_field( $in['number'] ?? '' ),
		'entry_date'   => sanitize_text_field( $in['entry_date'] ?? '' ),
		'descr'        => $in['descr'] ?? '',
		'partner_name' => $in['partner_name'] ?? '',
		'vs'           => preg_replace( '/\s+/', '', (string) ( $in['vs'] ?? '' ) ),
		'opening'      => 'PS' === $ser ? 1 : 0,
	), $lines );
	if ( is_wp_error( $r ) ) {
		juc_back_err( juc_url( 'j-uctovnictvo-new' ), $r->get_error_message() );
	}
	wp_safe_redirect( juc_url( 'j-uctovnictvo-entries', array( 'entry' => $r, 'juc_msg' => 'posted' ) ) );
	exit;
} );

/* ---------------------------------------------------------------- Zvyšné stránky */

function juc_page_accounts() {
	if ( ! current_user_can( JUC_CAP ) ) {
		return;
	}
	juc_head( 'Účtový rozvrh' );
	juc_render_accounts();
	echo '</div>';
}

/** Odkaz na zdrojový doklad (faktúra, pohyb v banke, doklad skladu). */
function juc_source_link( array $e ) {
	if ( 'jinv' === $e['source'] && function_exists( 'jinv_get' ) && ( $d = jinv_get( (int) $e['source_id'] ) ) ) {
		$url = 'received' === $d['kind'] ? jinv_admin_url( array( 'tab' => 'received', 'edit' => $d['id'] ) ) : jinv_admin_url( array( 'view' => $d['id'] ) );
		return '<a href="' . esc_url( $url ) . '">' . esc_html( $d['number'] ) . '</a>';
	}
	if ( 'jsk' === $e['source'] && 'doc' === $e['source_type'] && function_exists( 'jsk_url' ) ) {
		return '<a href="' . esc_url( jsk_url( 'j-sklad-docs', array( 'view' => $e['source_id'] ) ) ) . '">doklad skladu #' . (int) $e['source_id'] . '</a>';
	}
	if ( 'bank' === $e['source'] && function_exists( 'jinv_admin_url' ) ) {
		return '<a href="' . esc_url( jinv_admin_url( array( 'tab' => 'bank', 'view' => 'all' ) ) ) . '">pohyb v banke #' . (int) $e['source_id'] . '</a>';
	}
	return '';
}
