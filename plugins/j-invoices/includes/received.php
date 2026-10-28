<?php
/**
 * Prijaté faktúry: evidencia dodávateľských faktúr s prílohou (PDF alebo fotka), splatnosťou a úhradou.
 * Ukladajú sa do rovnakej tabuľky ako vydané (kind = received); prílohy v chránenom priečinku uploads/j-fakturacia/prijate.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function jinv_received_dir() {
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'j-fakturacia/prijate';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	$root = dirname( $dir );
	if ( ! file_exists( $root . '/.htaccess' ) ) {
		@file_put_contents( $root . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore
		@file_put_contents( $root . '/index.php', "<?php // Silence.\n" ); // phpcs:ignore
	}
	return $dir;
}

function jinv_received_status_label( array $r ) {
	if ( 'paid' === $r['status'] ) {
		return array( 'paid', 'Uhradená' );
	}
	if ( 'cancelled' === $r['status'] ) {
		return array( 'cancelled', 'Stornovaná' );
	}
	if ( $r['due_date'] < current_time( 'Y-m-d' ) ) {
		return array( 'overdue', 'Po splatnosti' );
	}
	return array( 'issued', 'Neuhradená' );
}

/** Uloženie (nová aj úprava). */
add_action( 'admin_post_jinv_received_save', function () {
	global $wpdb;
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jinv_received_save' );
	$in    = wp_unslash( $_POST );
	$id    = isset( $in['id'] ) ? (int) $in['id'] : 0;
	$old   = $id ? jinv_get( $id ) : null;
	$date  = function ( $k, $def ) use ( $in ) {
		$v = isset( $in[ $k ] ) ? sanitize_text_field( $in[ $k ] ) : '';
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : $def;
	};
	$issue  = $date( 'issue_date', current_time( 'Y-m-d' ) );
	$data   = $old && is_array( $old['data'] ) ? $old['data'] : array();
	$data['supplier'] = array(
		'name' => sanitize_text_field( isset( $in['sup_name'] ) ? $in['sup_name'] : '' ),
		'ico'  => sanitize_text_field( isset( $in['sup_ico'] ) ? $in['sup_ico'] : '' ),
		'dic'  => sanitize_text_field( isset( $in['sup_dic'] ) ? $in['sup_dic'] : '' ),
	);
	$data['category'] = sanitize_text_field( isset( $in['category'] ) ? $in['category'] : '' );
	$data['note']     = sanitize_textarea_field( isset( $in['note'] ) ? $in['note'] : '' );

	// Príloha.
	if ( ! empty( $_FILES['file']['name'] ) && UPLOAD_ERR_OK === (int) $_FILES['file']['error'] ) {
		$ok_types = array( 'pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' );
		$ft       = wp_check_filetype_and_ext( $_FILES['file']['tmp_name'], $_FILES['file']['name'], $ok_types );
		if ( $ft['ext'] && (int) $_FILES['file']['size'] <= 15 * MB_IN_BYTES ) {
			$sub  = substr( $issue, 0, 4 );
			$dir  = jinv_received_dir() . '/' . $sub;
			wp_mkdir_p( $dir );
			$name = wp_generate_password( 8, false ) . '-' . sanitize_file_name( $_FILES['file']['name'] );
			if ( move_uploaded_file( $_FILES['file']['tmp_name'], $dir . '/' . $name ) ) {
				if ( ! empty( $data['file'] ) ) {
					@unlink( jinv_received_dir() . '/' . $data['file'] ); // phpcs:ignore
				}
				$data['file']      = $sub . '/' . $name;
				$data['file_name'] = sanitize_file_name( $_FILES['file']['name'] );
			}
		}
	}
	$row = array(
		'number'         => sanitize_text_field( isset( $in['number'] ) ? $in['number'] : '' ),
		'issue_date'     => $issue,
		'supply_date'    => $date( 'supply_date', $issue ),
		'due_date'       => $date( 'due_date', $issue ),
		'vs'             => preg_replace( '/\D/', '', isset( $in['vs'] ) ? (string) $in['vs'] : '' ),
		'currency'       => preg_match( '/^[A-Z]{3}$/', isset( $in['currency'] ) ? (string) $in['currency'] : '' ) ? $in['currency'] : 'EUR',
		'total'          => round( (float) str_replace( array( ' ', ',' ), array( '', '.' ), isset( $in['total'] ) ? (string) $in['total'] : '0' ), 2 ),
		'customer_name'  => $data['supplier']['name'], // pri prijatých = dodávateľ (kvôli hľadaniu)
		'data'           => wp_json_encode( $data, JSON_UNESCAPED_UNICODE ),
	);
	$paid = ! empty( $in['paid'] );
	if ( $old ) {
		$row['status']  = $paid ? 'paid' : ( 'paid' === $old['status'] ? 'issued' : $old['status'] );
		$row['paid_at'] = $paid ? ( $old['paid_at'] ? $old['paid_at'] : current_time( 'mysql' ) ) : null;
		jinv_update( $id, $row );
	} else {
		$year = (int) substr( $issue, 0, 4 );
		for ( $try = 0; $try < 5; $try++ ) {
			$seq = 1 + (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(seq) FROM ' . jinv_table() . " WHERE kind = 'received' AND year = %d", $year ) ); // phpcs:ignore
			$ok  = $wpdb->insert( jinv_table(), $row + array(
				'kind'       => 'received',
				'year'       => $year,
				'seq'        => $seq,
				'status'     => $paid ? 'paid' : 'issued',
				'paid_at'    => $paid ? current_time( 'mysql' ) : null,
				'created_at' => current_time( 'mysql' ),
			) );
			if ( $ok ) {
				$id = (int) $wpdb->insert_id;
				break;
			}
		}
	}
	wp_safe_redirect( jinv_admin_url( array( 'tab' => 'received', 'jinv_msg' => 'rsaved' ) ) );
	exit;
} );

// Úhrada / zmazanie prijatej faktúry.
add_action( 'admin_post_jinv_received_action', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$do = isset( $_GET['do'] ) ? sanitize_key( $_GET['do'] ) : ''; // phpcs:ignore
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore
	check_admin_referer( 'jinv_r_' . $do . '_' . $id );
	$r = jinv_get( $id );
	if ( $r && 'received' === $r['kind'] ) {
		if ( 'paid' === $do ) {
			jinv_update( $id, array( 'status' => 'paid', 'paid_at' => current_time( 'mysql' ) ) );
		} elseif ( 'unpaid' === $do ) {
			jinv_update( $id, array( 'status' => 'issued', 'paid_at' => null ) );
		} elseif ( 'delete' === $do ) {
			global $wpdb;
			if ( ! empty( $r['data']['file'] ) ) {
				@unlink( jinv_received_dir() . '/' . $r['data']['file'] ); // phpcs:ignore
			}
			$wpdb->delete( jinv_table(), array( 'id' => $id ) );
		}
	}
	wp_safe_redirect( jinv_admin_url( array( 'tab' => 'received', 'jinv_msg' => 'r' . $do ) ) );
	exit;
} );

// Stiahnutie prílohy (len pre správcu obchodu).
add_action( 'admin_post_jinv_received_file', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore
	check_admin_referer( 'jinv_r_file_' . $id );
	$r    = jinv_get( $id );
	$path = $r && ! empty( $r['data']['file'] ) ? jinv_received_dir() . '/' . $r['data']['file'] : '';
	if ( ! $path || ! is_file( $path ) || 0 !== strpos( realpath( $path ), realpath( jinv_received_dir() ) ) ) {
		wp_die( 'Príloha neexistuje.' );
	}
	$ft = wp_check_filetype( $path );
	nocache_headers();
	header( 'Content-Type: ' . ( $ft['type'] ? $ft['type'] : 'application/octet-stream' ) );
	header( 'Content-Disposition: inline; filename="' . rawurlencode( $r['data']['file_name'] ? $r['data']['file_name'] : basename( $path ) ) . '"' );
	header( 'Content-Length: ' . filesize( $path ) );
	readfile( $path ); // phpcs:ignore
	exit;
} );

function jinv_received_url( $do, $id ) {
	$a = 'file' === $do ? 'jinv_received_file' : 'jinv_received_action';
	return wp_nonce_url( admin_url( 'admin-post.php?action=' . $a . '&do=' . $do . '&id=' . (int) $id ), 'jinv_r_' . $do . '_' . (int) $id );
}

function jinv_render_received() {
	global $wpdb;
	$edit = isset( $_GET['edit'] ) ? sanitize_key( $_GET['edit'] ) : ''; // phpcs:ignore
	if ( '' !== $edit ) {
		jinv_render_received_form( 'new' === $edit ? 0 : (int) $edit );
		return;
	}
	$year = isset( $_GET['year'] ) ? (int) $_GET['year'] : 0; // phpcs:ignore
	$q    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore
	$res  = jinv_list( array( 'kind' => 'received', 'year' => $year, 's' => $q, 'limit' => 200 ) );
	$t    = jinv_table();
	$open = $wpdb->get_row( "SELECT COUNT(*) n, COALESCE(SUM(total),0) s, SUM(due_date < CURDATE()) o FROM $t WHERE kind = 'received' AND status = 'issued'", ARRAY_A ); // phpcs:ignore
	?>
	<div class="jinv-cards">
		<div><span>Neuhradené</span><strong><?php echo (int) $open['n']; ?></strong><small><?php echo esc_html( jinv_money( $open['s'] ) ); ?></small></div>
		<div class="<?php echo (int) $open['o'] ? 'is-warn' : ''; ?>"><span>Po splatnosti</span><strong><?php echo (int) $open['o']; ?></strong></div>
	</div>
	<p><a class="button button-primary" href="<?php echo esc_url( jinv_admin_url( array( 'tab' => 'received', 'edit' => 'new' ) ) ); ?>">+ Pridať prijatú faktúru</a></p>
	<form method="get" class="jinv-filter">
		<input type="hidden" name="page" value="j-invoices-received">
		<input type="search" name="s" value="<?php echo esc_attr( $q ); ?>" placeholder="Dodávateľ, číslo faktúry">
		<select name="year"><option value="0">Všetky roky</option><?php for ( $y = (int) current_time( 'Y' ); $y >= (int) current_time( 'Y' ) - 5; $y-- ) : ?><option value="<?php echo (int) $y; ?>" <?php selected( $year, $y ); ?>><?php echo (int) $y; ?></option><?php endfor; ?></select>
		<button class="button">Filtrovať</button>
	</form>
	<table class="widefat striped jinv-table">
		<thead><tr><th>Dodávateľ</th><th>Číslo faktúry</th><th>Vystavená</th><th>Splatnosť</th><th class="n">Suma</th><th>Stav</th><th>Príloha</th><th></th></tr></thead>
		<tbody>
		<?php if ( ! $res['rows'] ) : ?><tr><td colspan="8">Zatiaľ žiadne prijaté faktúry. Pridaj prvú tlačidlom vyššie – stačí dodávateľ, číslo, dátumy, suma a PDF alebo fotka faktúry.</td></tr><?php endif; ?>
		<?php foreach ( $res['rows'] as $r ) : $r['data'] = json_decode( (string) $r['data'], true ); $st = jinv_received_status_label( $r ); ?>
			<tr>
				<td><strong><?php echo esc_html( $r['customer_name'] ); ?></strong><?php if ( ! empty( $r['data']['category'] ) ) : ?><br><small><?php echo esc_html( $r['data']['category'] ); ?></small><?php endif; ?></td>
				<td><?php echo esc_html( $r['number'] ); ?><?php if ( $r['vs'] ) : ?><br><small>VS <?php echo esc_html( $r['vs'] ); ?></small><?php endif; ?></td>
				<td><?php echo esc_html( jinv_date( $r['issue_date'] ) ); ?></td>
				<td><?php echo esc_html( jinv_date( $r['due_date'] ) ); ?></td>
				<td class="n"><?php echo esc_html( jinv_money( $r['total'], $r['currency'] ) ); ?></td>
				<td><span class="jinv-st jinv-st-<?php echo esc_attr( $st[0] ); ?>"><?php echo esc_html( $st[1] ); ?></span></td>
				<td><?php if ( ! empty( $r['data']['file'] ) ) : ?><a href="<?php echo esc_url( jinv_received_url( 'file', $r['id'] ) ); ?>" target="_blank" rel="noopener">Otvoriť</a><?php else : ?>—<?php endif; ?></td>
				<td class="jinv-row-actions">
					<a href="<?php echo esc_url( jinv_admin_url( array( 'tab' => 'received', 'edit' => $r['id'] ) ) ); ?>">Upraviť</a> ·
					<?php if ( 'paid' === $r['status'] ) : ?><a href="<?php echo esc_url( jinv_received_url( 'unpaid', $r['id'] ) ); ?>">Neuhradená</a><?php else : ?><a href="<?php echo esc_url( jinv_received_url( 'paid', $r['id'] ) ); ?>">Uhradená</a><?php endif; ?> ·
					<a class="jinv-del" href="<?php echo esc_url( jinv_received_url( 'delete', $r['id'] ) ); ?>" onclick="return confirm('Zmazať prijatú faktúru aj s prílohou?');">Zmazať</a>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

function jinv_render_received_form( $id ) {
	$r = $id ? jinv_get( $id ) : null;
	if ( $id && ( ! $r || 'received' !== $r['kind'] ) ) {
		echo '<p>Faktúra neexistuje.</p>';
		return;
	}
	$d   = $r ? (array) $r['data'] : array();
	$sup = isset( $d['supplier'] ) ? (array) $d['supplier'] : array( 'name' => '', 'ico' => '', 'dic' => '' );
	$v   = function ( $k, $def = '' ) use ( $r ) {
		return $r && isset( $r[ $k ] ) ? $r[ $k ] : $def;
	};
	$today = current_time( 'Y-m-d' );
	?>
	<h2><?php echo $r ? 'Upraviť prijatú faktúru' : 'Nová prijatá faktúra'; ?></h2>
	<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="jinv-panel jinv-rform">
		<?php wp_nonce_field( 'jinv_received_save' ); ?>
		<input type="hidden" name="action" value="jinv_received_save"><input type="hidden" name="id" value="<?php echo (int) $id; ?>">
		<table class="form-table" role="presentation">
			<tr><th><label for="r-sup">Dodávateľ</label></th><td><input id="r-sup" name="sup_name" class="regular-text" required value="<?php echo esc_attr( $sup['name'] ); ?>"></td></tr>
			<tr><th><label for="r-ico">IČO / DIČ dodávateľa</label></th><td><input id="r-ico" name="sup_ico" placeholder="IČO" value="<?php echo esc_attr( $sup['ico'] ); ?>"> <input name="sup_dic" placeholder="DIČ" value="<?php echo esc_attr( $sup['dic'] ); ?>" aria-label="DIČ dodávateľa"></td></tr>
			<tr><th><label for="r-num">Číslo faktúry</label></th><td><input id="r-num" name="number" required value="<?php echo esc_attr( $v( 'number' ) ); ?>"> <input name="vs" placeholder="Variabilný symbol" value="<?php echo esc_attr( $v( 'vs' ) ); ?>" aria-label="Variabilný symbol"></td></tr>
			<tr><th>Dátumy</th><td class="jinv-dates">
				<label>Vystavenia <input type="date" name="issue_date" required value="<?php echo esc_attr( $v( 'issue_date', $today ) ); ?>"></label>
				<label>Dodania <input type="date" name="supply_date" value="<?php echo esc_attr( $v( 'supply_date', $today ) ); ?>"></label>
				<label>Splatnosti <input type="date" name="due_date" required value="<?php echo esc_attr( $v( 'due_date', gmdate( 'Y-m-d', strtotime( $today . ' +14 days' ) ) ) ); ?>"></label>
			</td></tr>
			<tr><th><label for="r-tot">Suma spolu</label></th><td><input id="r-tot" name="total" inputmode="decimal" required value="<?php echo esc_attr( $r ? number_format( (float) $r['total'], 2, ',', '' ) : '' ); ?>" class="small-text" style="width:120px"> <select name="currency" aria-label="Mena"><?php foreach ( array( 'EUR', 'CZK', 'USD' ) as $c ) : ?><option <?php selected( $v( 'currency', 'EUR' ), $c ); ?>><?php echo esc_html( $c ); ?></option><?php endforeach; ?></select></td></tr>
			<tr><th><label for="r-cat">Kategória</label></th><td><input id="r-cat" name="category" list="jinv-cats" value="<?php echo esc_attr( isset( $d['category'] ) ? $d['category'] : '' ); ?>" placeholder="napr. Tovar, Doprava, Služby, Hosting"><datalist id="jinv-cats"><option>Tovar</option><option>Doprava</option><option>Služby</option><option>Hosting a softvér</option><option>Reklama</option><option>Kancelária</option></datalist></td></tr>
			<tr><th><label for="r-file">Faktúra (PDF alebo fotka)</label></th><td><input id="r-file" type="file" name="file" accept=".pdf,image/jpeg,image/png,image/webp"><?php if ( ! empty( $d['file'] ) ) : ?><p class="description">Aktuálna príloha: <a href="<?php echo esc_url( jinv_received_url( 'file', $id ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $d['file_name'] ); ?></a> – nový súbor ju nahradí.</p><?php endif; ?><p class="description">Najviac 15 MB. Súbory sú uložené v chránenom priečinku, otvorí ich len správca obchodu.</p></td></tr>
			<tr><th><label for="r-note">Poznámka</label></th><td><textarea id="r-note" name="note" rows="3" class="large-text"><?php echo esc_textarea( isset( $d['note'] ) ? $d['note'] : '' ); ?></textarea></td></tr>
			<tr><th>Úhrada</th><td><label><input type="checkbox" name="paid" value="1" <?php checked( $r && 'paid' === $r['status'] ); ?>> Faktúra je uhradená</label></td></tr>
		</table>
		<p><button class="button button-primary">Uložiť</button> <a class="button" href="<?php echo esc_url( jinv_admin_url( array( 'tab' => 'received' ) ) ); ?>">Zrušiť</a></p>
	</form>
	<?php
}
