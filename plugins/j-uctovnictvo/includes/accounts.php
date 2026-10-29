<?php
/**
 * Účtový rozvrh: syntetické účty z rámcovej účtovej osnovy (includes/osnova.php) a analytické účty firmy.
 * Číslo účtu = 3-miestny syntetický účet + analytika (napr. 221001). Typ: A aktívny, P pasívny, AP aktívno-pasívny,
 * N nákladový, V výnosový, Z závierkový (701, 702, 710), O podsúvahový.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Rámcová účtová osnova: [ [číslo, názov, typ], … ]. */
function juc_osnova() {
	static $o = null;
	if ( null === $o ) {
		$o = (array) include JUC_DIR . 'includes/osnova.php';
	}
	return $o;
}

/** Nedaňové náklady/výnosy (predvolene) – daňový výdavok sa dá zmeniť pri účte. */
function juc_nontax_accounts() {
	return apply_filters( 'juc_nontax_accounts', array( '513', '543', '545', '547', '549', '552', '553', '557', '558', '591', '592', '595', '597', '599' ) );
}

/** Naplnenie prázdneho rozvrhu syntetickými účtami osnovy. */
function juc_seed_accounts( $force = false ) {
	global $wpdb;
	if ( ! $force && (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . juc_t( 'accounts' ) ) ) { // phpcs:ignore
		return 0;
	}
	$n = 0;
	foreach ( juc_osnova() as $a ) {
		if ( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . juc_t( 'accounts' ) . ' WHERE num = %s', $a[0] ) ) ) { // phpcs:ignore
			continue;
		}
		$wpdb->insert( juc_t( 'accounts' ), array(
			'num'  => $a[0],
			'syn'  => substr( $a[0], 0, 3 ),
			'name' => $a[1],
			'type' => $a[2],
			'tax'  => in_array( $a[0], juc_nontax_accounts(), true ) ? 0 : 1,
		) );
		$n++;
	}
	return $n;
}

function juc_account( $num ) {
	global $wpdb;
	$num = preg_replace( '/\D/', '', (string) $num );
	$c   = wp_cache_get( 'acc_' . $num, 'juc' );
	if ( false !== $c ) {
		return $c ? $c : null;
	}
	$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . juc_t( 'accounts' ) . ' WHERE num = %s', $num ), ARRAY_A ); // phpcs:ignore
	wp_cache_set( 'acc_' . $num, $r ? $r : 0, 'juc', 300 );
	return $r;
}

function juc_account_name( $num ) {
	$a = juc_account( $num );
	return $a ? $a['name'] : '';
}

/** Účet so všetkými účtami (pre výber vo formulároch). */
function juc_accounts( $active_only = true ) {
	global $wpdb;
	return (array) $wpdb->get_results( 'SELECT * FROM ' . juc_t( 'accounts' ) . ( $active_only ? ' WHERE active = 1' : '' ) . ' ORDER BY num', ARRAY_A ); // phpcs:ignore
}

/**
 * Účet musí existovať a byť aktívny. Analytický účet k existujúcemu syntetickému sa pri automatickom účtovaní
 * vytvorí sám (názov = názov syntetického účtu). Vráti riadok účtu alebo WP_Error.
 */
function juc_account_ensure( $num, $name = '' ) {
	global $wpdb;
	$num = preg_replace( '/\D/', '', (string) $num );
	$a   = juc_account( $num );
	if ( $a ) {
		return (int) $a['active'] ? $a : new WP_Error( 'juc_acc', 'Účet ' . $num . ' je vypnutý v účtovom rozvrhu.' );
	}
	$syn = juc_account( substr( $num, 0, 3 ) );
	if ( strlen( $num ) < 3 || ! $syn ) {
		return new WP_Error( 'juc_acc', 'Účet ' . $num . ' nie je v účtovom rozvrhu.' );
	}
	$wpdb->insert( juc_t( 'accounts' ), array(
		'num'  => $num,
		'syn'  => $syn['num'],
		'name' => $name ? $name : $syn['name'],
		'type' => $syn['type'],
		'tax'  => (int) $syn['tax'],
	) );
	wp_cache_delete( 'acc_' . $num, 'juc' );
	return juc_account( $num );
}

function juc_account_types() {
	return array( 'A' => 'aktívny', 'P' => 'pasívny', 'AP' => 'aktívno-pasívny', 'N' => 'nákladový', 'V' => 'výnosový', 'Z' => 'závierkový', 'O' => 'podsúvahový' );
}

function juc_account_classes() {
	return array(
		'0' => 'Dlhodobý majetok',
		'1' => 'Zásoby',
		'2' => 'Finančné účty',
		'3' => 'Zúčtovacie vzťahy',
		'4' => 'Kapitálové účty a dlhodobé záväzky',
		'5' => 'Náklady',
		'6' => 'Výnosy',
		'7' => 'Závierkové a podsúvahové účty',
	);
}

/** <datalist> všetkých aktívnych účtov pre polia MD / Dal. */
function juc_accounts_datalist( $id = 'juc-accs' ) {
	echo '<datalist id="' . esc_attr( $id ) . '">';
	foreach ( juc_accounts() as $a ) {
		echo '<option value="' . esc_attr( $a['num'] ) . '">' . esc_html( $a['num'] . ' ' . $a['name'] ) . '</option>';
	}
	echo '</datalist>';
}

/* ---------------------------------------------------------------- Stránka Účtový rozvrh */

function juc_render_accounts() {
	global $wpdb;
	$q    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore
	$cls  = isset( $_GET['cls'] ) ? preg_replace( '/\D/', '', wp_unslash( $_GET['cls'] ) ) : ''; // phpcs:ignore
	$used = isset( $_GET['used'] ) ? 1 : 0; // phpcs:ignore
	$w    = '1=1';
	if ( '' !== $q ) {
		$like = '%' . $wpdb->esc_like( $q ) . '%';
		$w   .= $wpdb->prepare( ' AND (a.num LIKE %s OR a.name LIKE %s)', $like, $like );
	}
	if ( '' !== $cls ) {
		$w .= $wpdb->prepare( ' AND a.num LIKE %s', $cls . '%' );
	}
	$l    = juc_t( 'lines' );
	$rows = $wpdb->get_results( 'SELECT a.*, (SELECT COUNT(*) FROM ' . $l . ' WHERE md = a.num OR d = a.num) AS n FROM ' . juc_t( 'accounts' ) . " a WHERE $w ORDER BY a.num", ARRAY_A ); // phpcs:ignore
	if ( $used ) {
		$rows = array_filter( (array) $rows, fn( $r ) => (int) $r['n'] > 0 || strlen( $r['num'] ) > 3 );
	}
	$types = juc_account_types();
	?>
	<div class="juc-panel">
		<h3>Pridať analytický účet</h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="juc-inline">
			<?php wp_nonce_field( 'juc_account_save' ); ?><input type="hidden" name="action" value="juc_account_save">
			<label>Číslo <input name="num" required pattern="\d{4,9}" placeholder="napr. 221001" style="width:110px"></label>
			<label>Názov <input name="name" required class="regular-text" placeholder="napr. Fio – e-shop"></label>
			<label><input type="checkbox" name="tax" value="1" checked> daňový</label>
			<button class="button button-primary">Pridať</button>
		</form>
		<p class="description">Analytický účet = syntetický účet (prvé 3 číslice) + rozlíšenie, napr. 518001 Hosting a softvér, 518002 Reklama. Typ a zaradenie do výkazov preberá zo syntetického účtu.</p>
	</div>
	<form method="get" class="juc-filter">
		<input type="hidden" name="page" value="j-uctovnictvo-accounts">
		<input type="search" name="s" value="<?php echo esc_attr( $q ); ?>" placeholder="Číslo alebo názov účtu">
		<select name="cls"><option value="">Všetky triedy</option><?php foreach ( juc_account_classes() as $k => $l2 ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $cls, (string) $k ); ?>><?php echo esc_html( $k . ' – ' . $l2 ); ?></option><?php endforeach; ?></select>
		<label><input type="checkbox" name="used" value="1" <?php checked( $used ); ?>> len používané a analytické</label>
		<button class="button">Filtrovať</button>
	</form>
	<table class="widefat striped juc-table">
		<thead><tr><th>Účet</th><th>Názov</th><th>Typ</th><th>Daňový</th><th class="n">Zápisy</th><th>Stav</th><th></th></tr></thead>
		<tbody>
		<?php
		$last = '';
		foreach ( (array) $rows as $r ) :
			$c = substr( $r['num'], 0, 1 );
			if ( $c !== $last && '' === $q ) :
				$last = $c;
				?>
				<tr class="juc-class"><td colspan="7"><strong><?php echo esc_html( 'Účtová trieda ' . $c . ' – ' . ( juc_account_classes()[ $c ] ?? '' ) ); ?></strong></td></tr>
			<?php endif; ?>
			<tr class="<?php echo strlen( $r['num'] ) > 3 ? 'juc-an' : ''; ?><?php echo (int) $r['active'] ? '' : ' juc-off'; ?>">
				<td><a href="<?php echo esc_url( juc_url( 'j-uctovnictvo-ledger', array( 'acc' => $r['num'] ) ) ); ?>"><strong><?php echo esc_html( $r['num'] ); ?></strong></a></td>
				<td>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="juc-acc-form">
						<?php wp_nonce_field( 'juc_account_save' ); ?><input type="hidden" name="action" value="juc_account_save"><input type="hidden" name="num" value="<?php echo esc_attr( $r['num'] ); ?>"><input type="hidden" name="edit" value="1">
						<input name="name" value="<?php echo esc_attr( $r['name'] ); ?>" class="juc-acc-name" aria-label="Názov účtu <?php echo esc_attr( $r['num'] ); ?>">
						<?php if ( in_array( $r['type'], array( 'N', 'V' ), true ) ) : ?><label class="juc-muted"><input type="checkbox" name="tax" value="1" <?php checked( (int) $r['tax'] ); ?>> daň.</label><?php else : ?><input type="hidden" name="tax" value="1"><?php endif; ?>
						<label class="juc-muted"><input type="checkbox" name="active" value="1" <?php checked( (int) $r['active'] ); ?>> aktívny</label>
						<button class="button button-small">Uložiť</button>
					</form>
				</td>
				<td><?php echo esc_html( $types[ $r['type'] ] ?? $r['type'] ); ?></td>
				<td><?php echo in_array( $r['type'], array( 'N', 'V' ), true ) ? ( (int) $r['tax'] ? 'áno' : '<span class="juc-warn">nie</span>' ) : '—'; // phpcs:ignore ?></td>
				<td class="n"><?php echo (int) $r['n'] ? (int) $r['n'] : '—'; ?></td>
				<td><?php echo (int) $r['active'] ? 'aktívny' : 'vypnutý'; ?></td>
				<td></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

add_action( 'admin_post_juc_account_save', function () {
	global $wpdb;
	if ( ! current_user_can( JUC_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'juc_account_save' );
	$in   = wp_unslash( $_POST );
	$num  = preg_replace( '/\D/', '', (string) ( $in['num'] ?? '' ) );
	$name = trim( sanitize_text_field( $in['name'] ?? '' ) );
	$back = juc_url( 'j-uctovnictvo-accounts', array( 's' => substr( $num, 0, 3 ) ) );
	if ( ! empty( $in['edit'] ) ) {
		$a = juc_account( $num );
		if ( $a && '' !== $name ) {
			$wpdb->update( juc_t( 'accounts' ), array( 'name' => $name, 'tax' => ! empty( $in['tax'] ) ? 1 : 0, 'active' => ! empty( $in['active'] ) ? 1 : 0 ), array( 'id' => $a['id'] ) );
			wp_cache_delete( 'acc_' . $num, 'juc' );
		}
		wp_safe_redirect( add_query_arg( 'juc_msg', 'accsaved', wp_get_referer() ? wp_get_referer() : $back ) );
		exit;
	}
	if ( strlen( $num ) < 4 || juc_account( $num ) ) {
		juc_back_err( $back, strlen( $num ) < 4 ? 'Analytický účet musí mať aspoň 4 číslice (syntetický účet + rozlíšenie).' : 'Účet ' . $num . ' už existuje.' );
	}
	$r = juc_account_ensure( $num, $name );
	if ( is_wp_error( $r ) ) {
		juc_back_err( $back, $r->get_error_message() );
	}
	$wpdb->update( juc_t( 'accounts' ), array( 'tax' => ! empty( $in['tax'] ) ? 1 : 0 ), array( 'num' => $num ) );
	wp_cache_delete( 'acc_' . $num, 'juc' );
	wp_safe_redirect( add_query_arg( 'juc_msg', 'accadded', $back ) );
	exit;
} );
