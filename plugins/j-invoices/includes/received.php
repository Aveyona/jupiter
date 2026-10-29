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
	$in  = wp_unslash( $_POST );
	$id  = isset( $in['id'] ) ? (int) $in['id'] : 0;
	$old = $id ? jinv_get( $id ) : null;
	if ( $id && ( ! $old || 'received' !== $old['kind'] ) ) {
		wp_die( 'Doklad nie je prijatá faktúra – úprava sa neuložila.' );
	}
	$date  = function ( $k, $def ) use ( $in ) {
		$v = isset( $in[ $k ] ) ? sanitize_text_field( $in[ $k ] ) : '';
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : $def;
	};
	$issue = $date( 'issue_date', current_time( 'Y-m-d' ) );
	$data  = $old && is_array( $old['data'] ) ? $old['data'] : array();
	$data['supplier'] = array(
		'name' => sanitize_text_field( $in['sup_name'] ?? '' ),
		'ico'  => preg_replace( '/\s+/', '', sanitize_text_field( $in['sup_ico'] ?? '' ) ),
		'dic'  => preg_replace( '/\s+/', '', sanitize_text_field( $in['sup_dic'] ?? '' ) ),
	);
	$data['iban']     = jinv_iban_clean( $in['iban'] ?? '' );
	$data['category'] = sanitize_text_field( $in['category'] ?? '' );
	$data['note']     = sanitize_textarea_field( $in['note'] ?? '' );

	// Dodávateľ z adresára; nový sa do adresára uloží (ak to nie je vypnuté).
	$pid = (int) ( $in['partner_id'] ?? 0 );
	if ( $pid && ! jinv_partner_get( $pid ) ) {
		$pid = 0;
	}
	if ( ! $pid && '' !== $data['supplier']['name'] ) {
		$pid = jinv_partner_match( $data['supplier']['name'], $data['supplier']['ico'] );
		if ( ! $pid && ! empty( $in['save_partner'] ) ) {
			$r   = jinv_partner_save( array( 'name' => $data['supplier']['name'], 'ico' => $data['supplier']['ico'], 'dic' => $data['supplier']['dic'], 'iban' => $data['iban'], 'category' => $data['category'] ) );
			$pid = is_wp_error( $r ) ? 0 : (int) $r;
		}
	}
	if ( $pid ) {
		// Chýbajúce údaje dodávateľa sa doplnia do adresára (IČO, DIČ, IBAN), existujúce sa neprepisujú.
		$p   = jinv_partner_get( $pid );
		$upd = array();
		foreach ( array( 'ico' => $data['supplier']['ico'], 'dic' => $data['supplier']['dic'], 'iban' => $data['iban'] ) as $k => $val ) {
			if ( '' !== $val && '' === (string) $p[ $k ] ) {
				$upd[ $k ] = $val;
			}
		}
		if ( $upd ) {
			$wpdb->update( jinv_table( 'partners' ), $upd, array( 'id' => $pid ) );
		}
	}

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
	$stock = (int) ( $in['stock_doc'] ?? 0 );
	if ( $stock ) {
		$data['stock_doc'] = $stock;
	}
	$row = array(
		'number'        => sanitize_text_field( $in['number'] ?? '' ),
		'issue_date'    => $issue,
		'supply_date'   => $date( 'supply_date', $issue ),
		'due_date'      => $date( 'due_date', $issue ),
		'vs'            => preg_replace( '/\D/', '', (string) ( $in['vs'] ?? '' ) ),
		'currency'      => preg_match( '/^[A-Z]{3}$/', (string) ( $in['currency'] ?? '' ) ) ? $in['currency'] : 'EUR',
		'total'         => round( (float) str_replace( array( ' ', ',' ), array( '', '.' ), (string) ( $in['total'] ?? '0' ) ), 2 ),
		'customer_name' => $data['supplier']['name'], // pri prijatých = dodávateľ (kvôli hľadaniu)
		'partner_id'    => $pid,
		'data'          => wp_json_encode( $data, JSON_UNESCAPED_UNICODE ),
	);
	$paid = ! empty( $in['paid'] );
	if ( $old ) {
		jinv_update( $id, $row );
		jinv_sync_paid( $id ); // zmena sumy môže zmeniť stav úhrady
	} else {
		$year = (int) substr( $issue, 0, 4 );
		for ( $try = 0; $try < 5; $try++ ) {
			$seq = 1 + (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(seq) FROM ' . jinv_table() . " WHERE kind = 'received' AND year = %d", $year ) ); // phpcs:ignore
			$ok  = $wpdb->insert( jinv_table(), $row + array(
				'kind'       => 'received',
				'year'       => $year,
				'seq'        => $seq,
				'status'     => 'issued',
				'created_at' => current_time( 'mysql' ),
			) );
			if ( $ok ) {
				$id = (int) $wpdb->insert_id;
				break;
			}
		}
		if ( $id && $paid ) {
			jinv_pay_full( $id, array( 'date' => $date( 'paid_date', current_time( 'Y-m-d' ) ), 'method' => sanitize_key( $in['paid_method'] ?? 'bank' ) ) );
		}
	}
	// Prepojenie s príjemkou J-Skladu (obojsmerne).
	if ( $id && $stock && function_exists( 'jsk_t' ) ) {
		$wpdb->update( jsk_t( 'docs' ), array( 'invoice_id' => $id ) + ( $pid ? array( 'partner_id' => $pid ) : array() ), array( 'id' => $stock ) );
	}
	if ( $id && ! empty( $in['make_receipt'] ) && function_exists( 'jsk_save_draft' ) ) {
		$doc = jinv_received_make_receipt( $id );
		if ( ! is_wp_error( $doc ) ) {
			wp_safe_redirect( jsk_url( 'j-sklad-docs', array( 'edit' => $doc, 'jsk_msg' => 'from_invoice' ) ) );
			exit;
		}
	}
	wp_safe_redirect( jinv_admin_url( array( 'tab' => 'received', 'edit' => $id, 'jinv_msg' => 'rsaved' ) ) );
	exit;
} );

/**
 * Prijatá faktúra z iného doplnku (napr. J-Emails – faktúra z e-mailu).
 * $a: number, issue_date, supply_date, due_date, vs, total, currency, supplier[name, ico, dic], partner_id, category,
 *     note, iban, file_data (obsah PDF), file_name, ref (jedinečný odkaz na zdroj, napr. gmail:ID – zabráni duplicite).
 * Po uložení skúsi spárovať nespárované platby v banke (VS a suma). Vráti ID dokladu alebo WP_Error.
 */
function jinv_received_create( array $a ) {
	global $wpdb;
	$ref = (string) ( $a['ref'] ?? '' );
	if ( '' !== $ref ) {
		$ex = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . jinv_table() . " WHERE kind = 'received' AND data LIKE %s", '%' . $wpdb->esc_like( '"ref":' . wp_json_encode( $ref ) ) . '%' ) ); // phpcs:ignore
		if ( $ex ) {
			return new WP_Error( 'jinv_dup', 'Faktúra je už prevzatá.', $ex );
		}
	}
	$date  = function ( $v, $def ) {
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $v ) ? $v : $def;
	};
	$issue = $date( $a['issue_date'] ?? '', current_time( 'Y-m-d' ) );
	$sup   = (array) ( $a['supplier'] ?? array() );
	$pid   = (int) ( $a['partner_id'] ?? 0 );
	if ( $pid && ( $p = jinv_partner_get( $pid ) ) ) {
		$sup = array( 'name' => $p['name'], 'ico' => $p['ico'], 'dic' => $p['dic'] );
	} elseif ( ! empty( $sup['name'] ) ) {
		$pid = jinv_partner_match( $sup['name'], $sup['ico'] ?? '' );
	}
	if ( empty( $sup['name'] ) ) {
		return new WP_Error( 'jinv_received', 'Chýba dodávateľ.' );
	}
	$num = trim( sanitize_text_field( (string) ( $a['number'] ?? '' ) ) );
	if ( '' !== $num && $pid ) {
		$ex = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . jinv_table() . " WHERE kind = 'received' AND partner_id = %d AND number = %s", $pid, $num ) ); // phpcs:ignore
		if ( $ex ) {
			return new WP_Error( 'jinv_dup', 'Faktúra ' . $num . ' od tohto dodávateľa už existuje.', $ex );
		}
	}
	$data = array(
		'supplier' => array( 'name' => sanitize_text_field( $sup['name'] ), 'ico' => preg_replace( '/\s+/', '', (string) ( $sup['ico'] ?? '' ) ), 'dic' => preg_replace( '/\s+/', '', (string) ( $sup['dic'] ?? '' ) ) ),
		'iban'     => jinv_iban_clean( $a['iban'] ?? '' ),
		'category' => sanitize_text_field( (string) ( $a['category'] ?? '' ) ),
		'note'     => sanitize_textarea_field( (string) ( $a['note'] ?? '' ) ),
	);
	if ( '' !== $ref ) {
		$data['ref'] = $ref;
	}
	if ( ! empty( $a['file_data'] ) ) {
		$sub  = substr( $issue, 0, 4 );
		$dir  = jinv_received_dir() . '/' . $sub;
		wp_mkdir_p( $dir );
		$fn   = sanitize_file_name( (string) ( $a['file_name'] ?? 'faktura.pdf' ) );
		$name = wp_generate_password( 8, false ) . '-' . $fn;
		if ( false !== file_put_contents( $dir . '/' . $name, $a['file_data'] ) ) { // phpcs:ignore
			$data['file']      = $sub . '/' . $name;
			$data['file_name'] = $fn;
		}
	}
	$year = (int) substr( $issue, 0, 4 );
	$id   = 0;
	for ( $try = 0; $try < 5 && ! $id; $try++ ) {
		$seq = 1 + (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(seq) FROM ' . jinv_table() . " WHERE kind = 'received' AND year = %d", $year ) ); // phpcs:ignore
		if ( $wpdb->insert( jinv_table(), array(
			'kind'          => 'received',
			'number'        => '' !== $num ? $num : 'PF' . $year . '-' . $seq,
			'year'          => $year,
			'seq'           => $seq,
			'status'        => 'issued',
			'issue_date'    => $issue,
			'supply_date'   => $date( $a['supply_date'] ?? '', $issue ),
			'due_date'      => $date( $a['due_date'] ?? '', $issue ),
			'vs'            => preg_replace( '/\D/', '', (string) ( $a['vs'] ?? '' ) ),
			'currency'      => preg_match( '/^[A-Z]{3}$/', (string) ( $a['currency'] ?? '' ) ) ? $a['currency'] : 'EUR',
			'total'         => round( (float) ( $a['total'] ?? 0 ), 2 ),
			'customer_name' => $data['supplier']['name'],
			'partner_id'    => $pid,
			'data'          => wp_json_encode( $data, JSON_UNESCAPED_UNICODE ),
			'source'        => sanitize_key( (string) ( $a['source'] ?? 'import' ) ),
			'created_at'    => current_time( 'mysql' ),
		) ) ) {
			$id = (int) $wpdb->insert_id;
		}
	}
	if ( ! $id ) {
		return new WP_Error( 'jinv_db', 'Faktúru sa nepodarilo uložiť.' );
	}
	// Úhrada: nespárované výdaje v banke s rovnakým VS (alebo číslom faktúry).
	if ( function_exists( 'jinv_bank_auto_match' ) ) {
		$vs  = preg_replace( '/\D/', '', (string) ( $a['vs'] ?? '' ) );
		// Najprv presná suma (pri dodávateľovi s rovnakým VS na všetkých faktúrach rozhodne suma a najbližší dátum).
		$exact = $vs ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . jinv_table( 'banktx' ) . " WHERE status = 'new' AND vs = %s AND amount = %f ORDER BY ABS(DATEDIFF(tx_date, %s)) LIMIT 1", $vs, -1 * round( (float) ( $a['total'] ?? 0 ), 2 ), $issue ) ) : 0; // phpcs:ignore
		if ( $exact ) {
			jinv_bank_pair( $exact, $id, 'auto' );
		} else {
			$txs = $vs ? $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . jinv_table( 'banktx' ) . " WHERE status = 'new' AND amount < 0 AND vs = %s ORDER BY tx_date", $vs ) ) : array(); // phpcs:ignore
			foreach ( $txs as $tx ) {
				jinv_bank_auto_match( (int) $tx );
			}
		}
	}
	do_action( 'jinv_received_created', $id, $a );
	return $id;
}

/** Koncept príjemky J-Skladu k prijatej faktúre (dodávateľ, číslo faktúry, dátum dodania). Vráti ID dokladu skladu. */
function jinv_received_make_receipt( $id ) {
	global $wpdb;
	$r = jinv_get( $id );
	if ( ! $r || 'received' !== $r['kind'] || ! function_exists( 'jsk_save_draft' ) ) {
		return new WP_Error( 'jinv', 'Príjemku nemožno vytvoriť.' );
	}
	if ( ! empty( $r['data']['stock_doc'] ) && jsk_doc( (int) $r['data']['stock_doc'] ) ) {
		return (int) $r['data']['stock_doc'];
	}
	$doc = jsk_save_draft( 0, 'receipt', array(
		'doc_date'    => $r['supply_date'],
		'partner'     => $r['customer_name'],
		'partner_doc' => $r['number'],
		'note'        => 'K prijatej faktúre ' . $r['number'],
	), array() );
	if ( is_wp_error( $doc ) ) {
		return $doc;
	}
	$wpdb->update( jsk_t( 'docs' ), array( 'invoice_id' => $id, 'partner_id' => (int) $r['partner_id'] ), array( 'id' => $doc ) );
	$data              = (array) $r['data'];
	$data['stock_doc'] = (int) $doc;
	jinv_update( $id, array( 'data' => wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) ) );
	return (int) $doc;
}

// Úhrada / zmazanie prijatej faktúry / príjemka.
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
			jinv_pay_full( $id );
		} elseif ( 'unpaid' === $do ) {
			jinv_unpay( $id );
		} elseif ( 'receipt' === $do ) {
			$doc = jinv_received_make_receipt( $id );
			if ( ! is_wp_error( $doc ) ) {
				wp_safe_redirect( jsk_url( 'j-sklad-docs', array( 'edit' => $doc, 'jsk_msg' => 'from_invoice' ) ) );
				exit;
			}
		} elseif ( 'delete' === $do ) {
			global $wpdb;
			if ( ! empty( $r['data']['file'] ) ) {
				@unlink( jinv_received_dir() . '/' . $r['data']['file'] ); // phpcs:ignore
			}
			$wpdb->delete( jinv_table( 'payments' ), array( 'doc_id' => $id ) );
			$wpdb->delete( jinv_table(), array( 'id' => $id ) );
			if ( function_exists( 'jsk_t' ) ) {
				$wpdb->update( jsk_t( 'docs' ), array( 'invoice_id' => 0 ), array( 'invoice_id' => $id ) );
			}
		}
	}
	$back = isset( $_GET['back'] ) ? wp_unslash( $_GET['back'] ) : ''; // phpcs:ignore
	wp_safe_redirect( $back ? jinv_back_url( $back, 'r' . $do ) : jinv_admin_url( array( 'tab' => 'received', 'jinv_msg' => 'r' . $do ) ) );
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

function jinv_received_url( $do, $id, $back = '' ) {
	$a = 'file' === $do ? 'jinv_received_file' : 'jinv_received_action';
	return wp_nonce_url( admin_url( 'admin-post.php?action=' . $a . '&do=' . $do . '&id=' . (int) $id . ( $back ? '&back=' . rawurlencode( $back ) : '' ) ), 'jinv_r_' . $do . '_' . (int) $id );
}

/** Tabuľka prijatých faktúr (zoznam aj detail dodávateľa). */
function jinv_received_table( array $rows, $supplier_col = true ) {
	$back = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : ''; // phpcs:ignore
	?>
	<table class="widefat striped jinv-table">
		<thead><tr><?php if ( $supplier_col ) : ?><th>Dodávateľ</th><?php endif; ?><th>Číslo faktúry</th><th>Vystavená</th><th>Splatnosť</th><th class="n">Suma</th><th class="n">Zostáva</th><th>Stav</th><th>Príloha</th><th></th></tr></thead>
		<tbody>
		<?php if ( ! $rows ) : ?><tr><td colspan="<?php echo $supplier_col ? 9 : 8; ?>">Žiadne prijaté faktúry.</td></tr><?php endif; ?>
		<?php
		foreach ( $rows as $r ) :
			$r['data'] = is_array( $r['data'] ) ? $r['data'] : json_decode( (string) $r['data'], true );
			$st        = jinv_received_status_label( $r );
			$rest      = 'cancelled' === $r['status'] ? 0 : jinv_remaining( $r );
			$part      = $rest > 0 && $rest < (float) $r['total'] - 0.005;
			?>
			<tr>
				<?php if ( $supplier_col ) : ?>
					<td><?php if ( (int) $r['partner_id'] ) : ?><a href="<?php echo esc_url( jinv_partners_url( array( 'partner' => $r['partner_id'] ) ) ); ?>"><strong><?php echo esc_html( $r['customer_name'] ); ?></strong></a><?php else : ?><strong><?php echo esc_html( $r['customer_name'] ); ?></strong><?php endif; ?><?php if ( ! empty( $r['data']['category'] ) ) : ?><br><small><?php echo esc_html( $r['data']['category'] ); ?></small><?php endif; ?></td>
				<?php endif; ?>
				<td><a href="<?php echo esc_url( jinv_admin_url( array( 'tab' => 'received', 'edit' => $r['id'] ) ) ); ?>"><?php echo esc_html( $r['number'] ); ?></a><?php if ( $r['vs'] ) : ?><br><small>VS <?php echo esc_html( $r['vs'] ); ?></small><?php endif; ?></td>
				<td><?php echo esc_html( jinv_date( $r['issue_date'] ) ); ?></td>
				<td><?php echo esc_html( jinv_date( $r['due_date'] ) ); ?></td>
				<td class="n"><?php echo esc_html( jinv_money( $r['total'], $r['currency'] ) ); ?></td>
				<td class="n"><?php echo $rest > 0 ? esc_html( jinv_money( $rest, $r['currency'] ) ) : '—'; ?></td>
				<td><span class="jinv-st jinv-st-<?php echo esc_attr( $st[0] ); ?>"><?php echo esc_html( $part ? 'Čiastočne uhradená' : $st[1] ); ?></span></td>
				<td><?php if ( ! empty( $r['data']['file'] ) ) : ?><a href="<?php echo esc_url( jinv_received_url( 'file', $r['id'] ) ); ?>" target="_blank" rel="noopener">Otvoriť</a><?php else : ?>—<?php endif; ?></td>
				<td class="jinv-row-actions">
					<a href="<?php echo esc_url( jinv_admin_url( array( 'tab' => 'received', 'edit' => $r['id'] ) ) ); ?>">Otvoriť</a>
					<?php if ( 'cancelled' !== $r['status'] ) : ?> ·
						<?php if ( 'paid' === $r['status'] ) : ?><a href="<?php echo esc_url( jinv_received_url( 'unpaid', $r['id'], $back ) ); ?>">Neuhradená</a><?php else : ?><a href="<?php echo esc_url( jinv_received_url( 'paid', $r['id'], $back ) ); ?>" title="Zapíše úhradu zostávajúcej sumy s dnešným dátumom">Uhradená</a><?php endif; ?>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php
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
	$f    = isset( $_GET['f'] ) ? sanitize_key( $_GET['f'] ) : ''; // phpcs:ignore
	$t    = jinv_table();
	$where = array( "kind = 'received'" );
	if ( $year ) {
		$where[] = $wpdb->prepare( 'year = %d', $year );
	}
	if ( '' !== $q ) {
		$like    = '%' . $wpdb->esc_like( $q ) . '%';
		$where[] = $wpdb->prepare( '(number LIKE %s OR customer_name LIKE %s OR vs LIKE %s)', $like, $like, $like );
	}
	$today = current_time( 'Y-m-d' );
	if ( 'open' === $f ) {
		$where[] = "status = 'issued'";
	} elseif ( 'overdue' === $f ) {
		$where[] = $wpdb->prepare( "status = 'issued' AND due_date < %s", $today );
	} elseif ( 'paid' === $f ) {
		$where[] = "status = 'paid'";
	}
	$rows = $wpdb->get_results( "SELECT * FROM $t WHERE " . implode( ' AND ', $where ) . ' ORDER BY issue_date DESC, id DESC LIMIT 300', ARRAY_A ); // phpcs:ignore
	$p    = jinv_table( 'payments' );
	$open = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) n, COALESCE(SUM(i.total - COALESCE((SELECT SUM(amount) FROM $p WHERE doc_id = i.id), 0)),0) s, SUM(i.due_date < %s) o, SUM(i.due_date BETWEEN %s AND %s) w FROM $t i WHERE i.kind = 'received' AND i.status = 'issued'", $today, $today, gmdate( 'Y-m-d', strtotime( $today . ' +7 days' ) ) ), ARRAY_A ); // phpcs:ignore
	$tabs = array( '' => 'Všetky', 'open' => 'Neuhradené', 'overdue' => 'Po splatnosti', 'paid' => 'Uhradené' );
	?>
	<div class="jinv-cards">
		<div><span>Neuhradené</span><strong><?php echo (int) $open['n']; ?></strong><small><?php echo esc_html( jinv_money( $open['s'] ) ); ?></small></div>
		<div class="<?php echo (int) $open['o'] ? 'is-warn' : ''; ?>"><span>Po splatnosti</span><strong><?php echo (int) $open['o']; ?></strong></div>
		<div><span>Splatné do 7 dní</span><strong><?php echo (int) $open['w']; ?></strong></div>
	</div>
	<p>
		<a class="button button-primary" href="<?php echo esc_url( jinv_admin_url( array( 'tab' => 'received', 'edit' => 'new' ) ) ); ?>">+ Pridať prijatú faktúru</a>
		<a class="button" href="<?php echo esc_url( jinv_partners_url() ); ?>">Dodávatelia</a>
	</p>
	<ul class="subsubsub jinv-subsub">
		<?php
		$i = 0;
		foreach ( $tabs as $k => $l ) :
			?>
			<li><?php echo $i++ ? ' | ' : ''; ?><a class="<?php echo $f === $k ? 'current' : ''; ?>" href="<?php echo esc_url( jinv_admin_url( array_filter( array( 'tab' => 'received', 'f' => $k, 'year' => $year ?: null, 's' => '' !== $q ? $q : null ) ) ) ); ?>"><?php echo esc_html( $l ); ?></a></li>
		<?php endforeach; ?>
	</ul>
	<form method="get" class="jinv-filter">
		<input type="hidden" name="page" value="j-invoices-received">
		<?php if ( $f ) : ?><input type="hidden" name="f" value="<?php echo esc_attr( $f ); ?>"><?php endif; ?>
		<input type="search" name="s" value="<?php echo esc_attr( $q ); ?>" placeholder="Dodávateľ, číslo faktúry, VS">
		<select name="year"><option value="0">Všetky roky</option><?php for ( $y = (int) current_time( 'Y' ); $y >= (int) current_time( 'Y' ) - 5; $y-- ) : ?><option value="<?php echo (int) $y; ?>" <?php selected( $year, $y ); ?>><?php echo (int) $y; ?></option><?php endfor; ?></select>
		<button class="button">Filtrovať</button>
	</form>
	<?php
	if ( ! $rows && ! $f && '' === $q && ! $year ) {
		echo '<p class="description">Zatiaľ žiadne prijaté faktúry. Pridaj prvú tlačidlom vyššie – stačí dodávateľ, číslo, dátumy, suma a PDF alebo fotka faktúry.</p>';
	}
	jinv_received_table( (array) $rows );
}

function jinv_render_received_form( $id ) {
	$r = $id ? jinv_get( $id ) : null;
	if ( $id && ( ! $r || 'received' !== $r['kind'] ) ) {
		echo '<p>Faktúra neexistuje.</p>';
		return;
	}
	$d     = $r ? (array) $r['data'] : array();
	$sup   = isset( $d['supplier'] ) ? (array) $d['supplier'] + array( 'name' => '', 'ico' => '', 'dic' => '' ) : array( 'name' => '', 'ico' => '', 'dic' => '' );
	$pid   = $r ? (int) $r['partner_id'] : (int) ( $_GET['partner'] ?? 0 ); // phpcs:ignore
	$today = current_time( 'Y-m-d' );
	$pre   = array();
	$stock = $r ? (int) ( $d['stock_doc'] ?? 0 ) : (int) ( $_GET['from_stock'] ?? 0 ); // phpcs:ignore
	$sdoc  = $stock && function_exists( 'jsk_doc' ) ? jsk_doc( $stock ) : null;
	if ( ! $r ) {
		// Nová faktúra: predvyplnenie z dodávateľa alebo z príjemky J-Skladu.
		if ( $sdoc ) {
			$pid = $pid ? $pid : (int) ( $sdoc['partner_id'] ?? 0 );
			$pre = array( 'number' => $sdoc['partner_doc'], 'supply_date' => $sdoc['doc_date'], 'total' => (float) $sdoc['total'] > 0 ? number_format( (float) $sdoc['total'], 2, ',', '' ) : '' );
			if ( ! $pid && $sdoc['partner'] ) {
				$sup['name'] = $sdoc['partner'];
			}
		}
		$pp = $pid ? jinv_partner_get( $pid ) : null;
		if ( $pp ) {
			$sup = array( 'name' => $pp['name'], 'ico' => $pp['ico'], 'dic' => $pp['dic'] );
			$d  += array( 'iban' => $pp['iban'], 'category' => $pp['category'] );
			if ( null !== $pp['due_days'] ) {
				$pre['due_date'] = gmdate( 'Y-m-d', strtotime( $today . ' +' . (int) $pp['due_days'] . ' days' ) );
			}
		}
	}
	$v = function ( $k, $def = '' ) use ( $r, $pre ) {
		if ( $r && isset( $r[ $k ] ) ) {
			return $r[ $k ];
		}
		return isset( $pre[ $k ] ) && '' !== $pre[ $k ] ? $pre[ $k ] : $def;
	};
	$back = jinv_admin_url( array( 'tab' => 'received', 'edit' => $id ) );
	$st   = $r ? jinv_received_status_label( $r ) : null;
	if ( $st && 'issued' === $r['status'] && jinv_paid_sum( $r ) > 0.005 ) {
		$st[1] = 'Čiastočne uhradená';
	}
	?>
	<h2><?php echo $r ? 'Prijatá faktúra ' . esc_html( $r['number'] ) : 'Nová prijatá faktúra'; ?> <?php if ( $st ) : ?><span class="jinv-st jinv-st-<?php echo esc_attr( $st[0] ); ?>"><?php echo esc_html( $st[1] ); ?></span><?php endif; ?></h2>
	<?php if ( $r ) : ?>
		<p>
			<a class="button" href="<?php echo esc_url( jinv_admin_url( array( 'tab' => 'received' ) ) ); ?>">← Prijaté faktúry</a>
			<?php if ( (int) $r['partner_id'] ) : ?><a class="button" href="<?php echo esc_url( jinv_partners_url( array( 'partner' => $r['partner_id'] ) ) ); ?>">Dodávateľ</a><?php endif; ?>
			<?php if ( ! empty( $d['file'] ) ) : ?><a class="button" href="<?php echo esc_url( jinv_received_url( 'file', $id ) ); ?>" target="_blank" rel="noopener">Otvoriť faktúru (príloha)</a><?php endif; ?>
			<?php if ( $sdoc ) : ?>
				<a class="button" href="<?php echo esc_url( jsk_url( 'j-sklad-docs', 'draft' === $sdoc['status'] ? array( 'edit' => $sdoc['id'] ) : array( 'view' => $sdoc['id'] ) ) ); ?>">Príjemka <?php echo esc_html( $sdoc['number'] ? $sdoc['number'] : '(koncept)' ); ?></a>
			<?php elseif ( function_exists( 'jsk_save_draft' ) ) : ?>
				<a class="button" href="<?php echo esc_url( jinv_received_url( 'receipt', $id ) ); ?>" title="Koncept príjemky s dodávateľom a číslom faktúry – doplníš tovar a nákupné ceny">Vytvoriť príjemku na sklad</a>
			<?php endif; ?>
		</p>
		<?php jinv_render_payments_box( $r, $back ); ?>
	<?php endif; ?>
	<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="jinv-panel jinv-rform">
		<?php wp_nonce_field( 'jinv_received_save' ); ?>
		<input type="hidden" name="action" value="jinv_received_save"><input type="hidden" name="id" value="<?php echo (int) $id; ?>">
		<input type="hidden" name="partner_id" id="r-pid" value="<?php echo (int) $pid; ?>">
		<?php if ( $stock && ! $r ) : ?><input type="hidden" name="stock_doc" value="<?php echo (int) $stock; ?>"><?php endif; ?>
		<table class="form-table" role="presentation">
			<tr><th><label for="r-sup">Dodávateľ</label></th><td><input id="r-sup" name="sup_name" class="regular-text" required autocomplete="off" value="<?php echo esc_attr( $sup['name'] ); ?>" placeholder="Začni písať názov alebo IČO dodávateľa">
				<p class="description" id="r-sup-note"><?php echo $pid ? 'Dodávateľ z adresára.' : ''; ?></p>
				<?php if ( ! $r ) : ?><label id="r-savep"<?php echo $pid ? ' hidden' : ''; ?>><input type="checkbox" name="save_partner" value="1" checked> uložiť nového dodávateľa do adresára</label><?php endif; ?>
			</td></tr>
			<tr><th><label for="r-ico">IČO / DIČ dodávateľa</label></th><td><input id="r-ico" name="sup_ico" placeholder="IČO" value="<?php echo esc_attr( $sup['ico'] ); ?>"> <input id="r-dic" name="sup_dic" placeholder="DIČ" value="<?php echo esc_attr( $sup['dic'] ); ?>" aria-label="DIČ dodávateľa"></td></tr>
			<tr><th><label for="r-num">Číslo faktúry</label></th><td><input id="r-num" name="number" required value="<?php echo esc_attr( $v( 'number' ) ); ?>"> <input name="vs" id="r-vs" placeholder="Variabilný symbol" value="<?php echo esc_attr( $v( 'vs' ) ); ?>" aria-label="Variabilný symbol"></td></tr>
			<tr><th>Dátumy</th><td class="jinv-dates">
				<label>Vystavenia <input type="date" name="issue_date" id="r-issue" required value="<?php echo esc_attr( $v( 'issue_date', $today ) ); ?>"></label>
				<label>Dodania <input type="date" name="supply_date" value="<?php echo esc_attr( $v( 'supply_date', $today ) ); ?>"></label>
				<label>Splatnosti <input type="date" name="due_date" id="r-due" required value="<?php echo esc_attr( $v( 'due_date', gmdate( 'Y-m-d', strtotime( $today . ' +14 days' ) ) ) ); ?>"></label>
			</td></tr>
			<tr><th><label for="r-tot">Suma spolu</label></th><td><input id="r-tot" name="total" inputmode="decimal" required value="<?php echo esc_attr( $r ? number_format( (float) $r['total'], 2, ',', '' ) : ( $pre['total'] ?? '' ) ); ?>" class="small-text" style="width:120px"> <select name="currency" aria-label="Mena"><?php foreach ( array( 'EUR', 'CZK', 'USD' ) as $c ) : ?><option <?php selected( $v( 'currency', 'EUR' ), $c ); ?>><?php echo esc_html( $c ); ?></option><?php endforeach; ?></select></td></tr>
			<tr><th><label for="r-iban">Účet na úhradu (IBAN)</label></th><td><input id="r-iban" name="iban" class="regular-text" value="<?php echo esc_attr( jinv_iban_format( $d['iban'] ?? '' ) ); ?>" placeholder="SK.. .... .... .... .... ...."></td></tr>
			<tr><th><label for="r-cat">Kategória</label></th><td><input id="r-cat" name="category" list="jinv-cats" value="<?php echo esc_attr( $d['category'] ?? '' ); ?>" placeholder="napr. Tovar, Doprava, Služby, Hosting"><?php jinv_categories_datalist(); ?></td></tr>
			<tr><th><label for="r-file">Faktúra (PDF alebo fotka)</label></th><td><input id="r-file" type="file" name="file" accept=".pdf,image/jpeg,image/png,image/webp"><?php if ( ! empty( $d['file'] ) ) : ?><p class="description">Aktuálna príloha: <a href="<?php echo esc_url( jinv_received_url( 'file', $id ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $d['file_name'] ); ?></a> – nový súbor ju nahradí.</p><?php endif; ?><p class="description">Najviac 15 MB. Súbory sú uložené v chránenom priečinku, otvorí ich len správca obchodu.</p></td></tr>
			<tr><th><label for="r-note">Poznámka</label></th><td><textarea id="r-note" name="note" rows="3" class="large-text"><?php echo esc_textarea( $d['note'] ?? '' ); ?></textarea></td></tr>
			<?php if ( ! $r ) : ?>
				<tr><th>Úhrada</th><td><label><input type="checkbox" name="paid" value="1" id="r-paid"> Faktúra je už uhradená</label> <span id="r-paidd" hidden><input type="date" name="paid_date" value="<?php echo esc_attr( $today ); ?>" aria-label="Dátum úhrady"> <select name="paid_method" aria-label="Spôsob úhrady"><?php foreach ( jinv_pay_methods() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></span></td></tr>
				<?php if ( function_exists( 'jsk_save_draft' ) && ! $stock ) : ?><tr><th>Sklad</th><td><label><input type="checkbox" name="make_receipt" value="1"> Po uložení vytvoriť príjemku na sklad (tovar a nákupné ceny doplníš v J-Sklade)</label></td></tr><?php endif; ?>
			<?php endif; ?>
		</table>
		<p><button class="button button-primary">Uložiť</button> <a class="button" href="<?php echo esc_url( jinv_admin_url( array( 'tab' => 'received' ) ) ); ?>">Zrušiť</a>
		<?php if ( $r ) : ?><a class="button jinv-danger" style="float:right" href="<?php echo esc_url( jinv_received_url( 'delete', $id ) ); ?>" onclick="return confirm('Zmazať prijatú faktúru aj s prílohou a úhradami?');">Zmazať</a><?php endif; ?></p>
	</form>
	<?php jinv_partner_picker_js( 'r-sup', 'r-pid', array( 'ico' => 'r-ico', 'dic' => 'r-dic', 'iban' => 'r-iban', 'category' => 'r-cat' ) ); ?>
	<script>
	(function () {
		var sup = document.getElementById('r-sup'), note = document.getElementById('r-sup-note'), sp = document.getElementById('r-savep');
		sup.addEventListener('jinv-partner', function (e) {
			note.textContent = 'Dodávateľ z adresára.'; if (sp) { sp.hidden = true; }
			var due = e.detail.due_days, iss = document.getElementById('r-issue').value;
			if (due !== null && due !== '' && iss && <?php echo $r ? 'false' : 'true'; ?>) { var d = new Date(iss + 'T12:00:00'); d.setDate(d.getDate() + parseInt(due, 10)); document.getElementById('r-due').value = d.toISOString().slice(0, 10); }
		});
		sup.addEventListener('input', function () { note.textContent = ''; if (sp) { sp.hidden = false; } });
		var num = document.getElementById('r-num'), vs = document.getElementById('r-vs');
		num.addEventListener('change', function () { if (!vs.value) { vs.value = num.value.replace(/\D/g, '').slice(0, 10); } });
		var pd = document.getElementById('r-paid'); if (pd) { pd.addEventListener('change', function () { document.getElementById('r-paidd').hidden = !pd.checked; }); }
	})();
	</script>
	<?php
}
