<?php
/** Administrácia J-Fakturácia: zoznam vydaných faktúr, náhľad faktúry, nastavenia. Menu je pod Marketingom (J-Pomocník ho zaradí). */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_menu_page( 'J-Fakturácia', 'J-Fakturácia', 'manage_woocommerce', 'j-invoices', 'jinv_render_admin', 'dashicons-media-spreadsheet', 58 );
	add_submenu_page( 'j-invoices', 'Vydané faktúry', 'Vydané faktúry', 'manage_woocommerce', 'j-invoices', 'jinv_render_admin' );
	add_submenu_page( 'j-invoices', 'Prijaté faktúry', 'Prijaté faktúry', 'manage_woocommerce', 'j-invoices-received', function () {
		jinv_render_admin( 'received' );
	} );
	add_submenu_page( 'j-invoices', 'Nastavenia', 'Nastavenia', 'manage_woocommerce', 'j-invoices-settings', function () {
		jinv_render_admin( 'settings' );
	} );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( false !== strpos( (string) $hook, 'j-invoices' ) ) {
		wp_enqueue_style( 'j-invoices-admin', JINV_URL . 'assets/admin.css', array(), JINV_VERSION );
		wp_add_inline_style( 'j-invoices-admin', jinv_invoice_css() );
	}
} );

/** Adresa stránky J-Fakturácie; 'tab' => received|settings vyberie samostatnú stránku. */
function jinv_admin_url( $args = array() ) {
	$pages = array( 'received' => 'j-invoices-received', 'settings' => 'j-invoices-settings' );
	$page  = 'j-invoices';
	if ( isset( $args['tab'] ) ) {
		$page = isset( $pages[ $args['tab'] ] ) ? $pages[ $args['tab'] ] : 'j-invoices';
		unset( $args['tab'] );
	}
	return add_query_arg( $args, admin_url( 'admin.php?page=' . $page ) );
}

// Akcie s faktúrou: uhradená / storno / vystaviť k objednávke.
add_action( 'admin_post_jinv_action', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$do = isset( $_REQUEST['do'] ) ? sanitize_key( $_REQUEST['do'] ) : ''; // phpcs:ignore
	$id = isset( $_REQUEST['id'] ) ? (int) $_REQUEST['id'] : 0; // phpcs:ignore
	check_admin_referer( 'jinv_' . $do . '_' . $id );
	$msg = '';
	$create = array( 'create' => 'issued', 'create_proforma' => 'proforma', 'create_creditnote' => 'creditnote' );
	if ( isset( $create[ $do ] ) ) {
		$r   = jinv_create_doc( $id, $create[ $do ] );
		$msg = is_wp_error( $r ) ? 'err' : 'created';
		if ( is_wp_error( $r ) ) {
			set_transient( 'jinv_err_' . get_current_user_id(), $r->get_error_message(), 60 );
		}
		$id = is_wp_error( $r ) ? 0 : (int) $r;
	} elseif ( 'send' === $do ) {
		$r   = jinv_send_doc( $id );
		$msg = is_wp_error( $r ) ? 'senderr' : 'sent';
		if ( is_wp_error( $r ) ) {
			set_transient( 'jinv_err_' . get_current_user_id(), $r->get_error_message(), 60 );
		}
	} elseif ( 'paid' === $do ) {
		jinv_update( $id, array( 'status' => 'paid', 'paid_at' => current_time( 'mysql' ) ) );
		$msg = 'paid';
	} elseif ( 'unpaid' === $do ) {
		jinv_update( $id, array( 'status' => 'issued', 'paid_at' => null ) );
		$msg = 'unpaid';
	} elseif ( 'cancel' === $do ) {
		$inv = jinv_get( $id );
		jinv_update( $id, array( 'status' => 'cancelled' ) );
		if ( $inv && $inv['order_id'] && ( $o = wc_get_order( $inv['order_id'] ) ) ) {
			$meta = jinv_kinds()[ $inv['kind'] ][1] ?? '';
			if ( $meta && (string) $o->get_meta( $meta ) === (string) $inv['number'] ) {
				$o->delete_meta_data( $meta );
				$o->save();
			}
			$o->add_order_note( sprintf( 'J-Fakturácia: stornovaný doklad – %s č. %s.', mb_strtolower( jinv_kind_label( $inv['kind'] ) ), $inv['number'] ) );
		}
		$msg = 'cancelled';
	}
	$back = isset( $_REQUEST['back'] ) ? esc_url_raw( wp_unslash( $_REQUEST['back'] ) ) : ''; // phpcs:ignore
	wp_safe_redirect( $back ? add_query_arg( 'jinv_msg', $msg, $back ) : jinv_admin_url( array( 'view' => $id, 'jinv_msg' => $msg ) ) );
	exit;
} );

function jinv_action_url( $do, $id, $back = '' ) {
	$url = admin_url( 'admin-post.php?action=jinv_action&do=' . $do . '&id=' . (int) $id . ( $back ? '&back=' . rawurlencode( $back ) : '' ) );
	return wp_nonce_url( $url, 'jinv_' . $do . '_' . (int) $id );
}

function jinv_status_badge( array $inv ) {
	$st = jinv_display_status( $inv );
	$l  = jinv_statuses();
	return '<span class="jinv-st jinv-st-' . esc_attr( $st ) . '">' . esc_html( isset( $l[ $st ] ) ? $l[ $st ] : $st ) . '</span>';
}

/** Každá podpoložka J-Fakturácie je samostatná stránka: issued (Vydané faktúry), received (Prijaté faktúry), settings (Nastavenia). */
function jinv_render_admin( $tab = 'issued' ) {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$tab    = in_array( $tab, array( 'issued', 'received', 'settings' ), true ) ? $tab : 'issued';
	$titles = array( 'issued' => 'Vydané faktúry', 'received' => 'Prijaté faktúry', 'settings' => 'Nastavenia' );
	$view = isset( $_GET['view'] ) ? (int) $_GET['view'] : 0; // phpcs:ignore
	$msgs = array(
		'created'   => 'Doklad je vystavený.',
		'paid'      => 'Doklad je označený ako uhradený.',
		'unpaid'    => 'Doklad je znova neuhradený.',
		'cancelled' => 'Doklad je stornovaný.',
		'sent'      => 'Doklad je odoslaný zákazníkovi e-mailom.',
		'senderr'   => 'E-mail sa nepodarilo odoslať.' . ( ( $e2 = get_transient( 'jinv_err_' . get_current_user_id() ) ) ? ' ' . $e2 : '' ),
		'err'       => 'Doklad sa nepodarilo vystaviť.' . ( ( $e = get_transient( 'jinv_err_' . get_current_user_id() ) ) ? ' ' . $e : '' ),
		'rsaved'    => 'Prijatá faktúra je uložená.',
		'rpaid'     => 'Prijatá faktúra je označená ako uhradená.',
		'runpaid'   => 'Prijatá faktúra je znova neuhradená.',
		'rdelete'   => 'Prijatá faktúra je zmazaná.',
	);
	$msg  = isset( $_GET['jinv_msg'] ) ? sanitize_key( $_GET['jinv_msg'] ) : ''; // phpcs:ignore
	?>
	<div class="wrap jinv">
		<h1><?php echo esc_html( $titles[ $tab ] ); ?> <span class="jinv-ver">J-Fakturácia v<?php echo esc_html( JINV_VERSION ); ?></span></h1>
		<?php if ( isset( $msgs[ $msg ] ) ) : ?><div class="notice notice-<?php echo in_array( $msg, array( 'err', 'senderr' ), true ) ? 'error' : 'success'; ?> is-dismissible"><p><?php echo esc_html( $msgs[ $msg ] ); ?></p></div><?php endif; ?>
		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore ?><div class="notice notice-success is-dismissible"><p>Nastavenia sú uložené.</p></div><?php endif; ?>
		<?php
		if ( 'settings' === $tab ) {
			jinv_render_settings();
		} elseif ( 'received' === $tab ) {
			jinv_render_received();
		} elseif ( $view ) {
			jinv_render_view( $view );
		} else {
			jinv_render_list();
		}
		?>
	</div>
	<?php
}

function jinv_render_list() {
	$year = isset( $_GET['year'] ) ? (int) $_GET['year'] : 0; // phpcs:ignore
	$st   = isset( $_GET['st'] ) ? sanitize_key( $_GET['st'] ) : ''; // phpcs:ignore
	$q    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore
	$pg   = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 ); // phpcs:ignore
	$kinds = array( 'issued' => 'Faktúry', 'proforma' => 'Zálohové faktúry', 'creditnote' => 'Dobropisy' );
	$kind  = isset( $_GET['doc'] ) && isset( $kinds[ $_GET['doc'] ] ) ? sanitize_key( $_GET['doc'] ) : 'issued'; // phpcs:ignore
	$res  = jinv_list( array( 'kind' => $kind, 'year' => $year, 'status' => $st, 's' => $q, 'limit' => 50, 'offset' => ( $pg - 1 ) * 50 ) );
	$s    = jinv_settings();
	$miss = array();
	foreach ( array( 'company' => 'obchodné meno', 'ico' => 'IČO', 'iban' => 'IBAN' ) as $k => $l ) {
		if ( empty( $s[ $k ] ) ) {
			$miss[] = $l;
		}
	}
	?>
	<?php if ( jinv_woo_faktura_active() ) : ?><div class="notice notice-info"><p><strong>Režim prechodu:</strong> beží doplnok Woo Faktura, preto J-Fakturácia faktúry automaticky nevystavuje a do pokladne nepridáva firemné polia (vystavuje Woo Faktura). Doklady z Woo Faktury prevezmeš v <a href="<?php echo esc_url( admin_url( 'admin.php?page=j-invoices-settings&section=import' ) ); ?>">Nastavenia → Importy údajov</a>.</p></div><?php endif; ?>
	<?php if ( $miss ) : ?><div class="notice notice-warning"><p>V nastaveniach chýba: <strong><?php echo esc_html( implode( ', ', $miss ) ); ?></strong>. <a href="<?php echo esc_url( jinv_admin_url( array( 'tab' => 'settings' ) ) ); ?>">Doplniť údaje dodávateľa →</a></p></div><?php endif; ?>
	<form method="get" class="jinv-filter">
		<input type="hidden" name="page" value="j-invoices">
		<?php if ( true ) : ?><select name="doc"><?php foreach ( $kinds as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $kind, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select><?php endif; ?>
		<input type="search" name="s" value="<?php echo esc_attr( $q ); ?>" placeholder="Číslo, odberateľ, e-mail">
		<select name="year"><option value="0">Všetky roky</option><?php for ( $y = (int) current_time( 'Y' ); $y >= (int) current_time( 'Y' ) - 5; $y-- ) : ?><option value="<?php echo (int) $y; ?>" <?php selected( $year, $y ); ?>><?php echo (int) $y; ?></option><?php endfor; ?></select>
		<select name="st"><option value="">Všetky stavy</option><?php foreach ( array( 'issued' => 'Vystavená', 'paid' => 'Uhradená', 'cancelled' => 'Stornovaná' ) as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $st, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
		<button class="button">Filtrovať</button>
	</form>
	<table class="widefat striped jinv-table">
		<thead><tr><th>Číslo</th><th>Odberateľ</th><th>Objednávka</th><th>Vystavená</th><th>Splatnosť</th><th class="n">Suma</th><th>Stav</th></tr></thead>
		<tbody>
		<?php if ( ! $res['rows'] ) : ?>
			<tr><td colspan="7">Zatiaľ žiadne doklady tohto druhu. Vznikajú automaticky z objednávok (prevod na účet: zálohová faktúra pri objednávke a faktúra po zaplatení; ostatné platby: faktúra po vybavení) alebo ich vystavíš v detaile objednávky (box Doklady).</td></tr>
		<?php endif; ?>
		<?php foreach ( $res['rows'] as $r ) : ?>
			<tr>
				<td><a href="<?php echo esc_url( jinv_admin_url( array( 'view' => $r['id'] ) ) ); ?>"><strong><?php echo esc_html( $r['number'] ); ?></strong></a></td>
				<td><?php echo esc_html( $r['customer_name'] ); ?><br><small><?php echo esc_html( $r['customer_email'] ); ?></small></td>
				<td><?php if ( $r['order_id'] && ( $o = wc_get_order( $r['order_id'] ) ) ) : ?><a href="<?php echo esc_url( $o->get_edit_order_url() ); ?>">#<?php echo esc_html( $o->get_order_number() ); ?></a><?php else : ?>—<?php endif; ?></td>
				<td><?php echo esc_html( jinv_date( $r['issue_date'] ) ); ?></td>
				<td><?php echo esc_html( jinv_date( $r['due_date'] ) ); ?></td>
				<td class="n"><?php echo esc_html( jinv_money( $r['total'], $r['currency'] ) ); ?></td>
				<td><?php echo jinv_status_badge( $r ); // phpcs:ignore ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php if ( $res['total'] > 50 ) : ?>
		<p class="jinv-pages"><?php echo paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $pg, 'total' => (int) ceil( $res['total'] / 50 ) ) ); // phpcs:ignore ?></p>
	<?php endif; ?>
	<?php
}

/** Sú v evidencii aj zálohové faktúry alebo dobropisy (napr. po importe)? */
function jinv_has_other_kinds() {
	global $wpdb;
	return (bool) $wpdb->get_var( 'SELECT 1 FROM ' . jinv_table() . " WHERE kind IN ('proforma','creditnote') LIMIT 1" ); // phpcs:ignore
}

function jinv_render_view( $id ) {
	$inv = jinv_get( $id );
	if ( ! $inv ) {
		echo '<p>Faktúra neexistuje.</p>';
		return;
	}
	$back = jinv_admin_url( array( 'view' => $id ) );
	$wf   = 'woo-faktura' === $inv['source'];
	$wf_f = $wf && $inv['file'] && jinv_wf_file_path( $inv['file'] );
	$o    = $inv['order_id'] ? wc_get_order( $inv['order_id'] ) : null;
	?>
	<p class="jinv-actions">
		<a class="button" href="<?php echo esc_url( jinv_admin_url( array( 'doc' => $inv['kind'] ) ) ); ?>">← Späť na zoznam</a>
		<?php if ( ! $wf || $wf_f ) : ?>
			<a class="button button-primary" target="_blank" href="<?php echo esc_url( jinv_pdf_url( $id ) ); ?>"><?php echo $wf ? 'Pôvodné PDF (Woo Faktura)' : 'Otvoriť PDF'; ?></a>
			<a class="button" href="<?php echo esc_url( jinv_pdf_url( $id, true ) ); ?>">Stiahnuť PDF</a>
		<?php endif; ?>
		<?php if ( 'cancelled' !== $inv['status'] && $inv['customer_email'] && ( ! $wf || $wf_f ) ) : ?>
			<a class="button" href="<?php echo esc_url( jinv_action_url( 'send', $id, $back ) ); ?>" onclick="return confirm('Poslať doklad <?php echo esc_js( $inv['number'] ); ?> e-mailom na <?php echo esc_js( $inv['customer_email'] ); ?>?');"><?php echo $inv['sent_at'] ? 'Poslať znova e-mailom' : 'Poslať e-mailom'; ?></a>
		<?php endif; ?>
		<?php if ( 'issued' === $inv['status'] ) : ?><a class="button" href="<?php echo esc_url( jinv_action_url( 'paid', $id, $back ) ); ?>">Označiť ako uhradený</a><?php endif; ?>
		<?php if ( 'paid' === $inv['status'] ) : ?><a class="button" href="<?php echo esc_url( jinv_action_url( 'unpaid', $id, $back ) ); ?>">Zrušiť úhradu</a><?php endif; ?>
		<?php if ( 'cancelled' !== $inv['status'] ) : ?><a class="button jinv-danger" href="<?php echo esc_url( jinv_action_url( 'cancel', $id, $back ) ); ?>" onclick="return confirm('Stornovať doklad <?php echo esc_js( $inv['number'] ); ?>? Číslo ostane obsadené.');">Stornovať</a><?php endif; ?>
		<?php if ( $o ) : ?><a class="button-link" href="<?php echo esc_url( $o->get_edit_order_url() ); ?>">Objednávka #<?php echo esc_html( $o->get_order_number() ); ?></a><?php endif; ?>
		<?php echo jinv_status_badge( $inv ); // phpcs:ignore ?>
	</p>
	<p class="jinv-note">
		<?php if ( $wf ) : ?>
			Doklad je prevzatý z Woo Faktury – platný je pôvodný PDF doklad. <?php echo $wf_f ? '' : 'PDF sa nezachovalo – náhľad nižšie je zostavený z aktuálnych údajov objednávky.'; ?>
		<?php else : ?>
			<?php echo $inv['sent_at'] ? 'Odoslané zákazníkovi ' . esc_html( date_i18n( 'j. n. Y H:i', strtotime( $inv['sent_at'] ) ) ) . '.' : 'Zákazníkovi zatiaľ neodoslané.'; ?>
		<?php endif; ?>
	</p>
	<?php
	// Prevzatý doklad s PDF: zobrazí sa pôvodné PDF (platný doklad), nie náhľad zostavený z dnešných údajov.
	if ( $wf_f ) {
		echo '<iframe class="jinv-pdf" src="' . esc_url( jinv_pdf_url( $id ) ) . '" title="' . esc_attr( $inv['number'] ) . '"></iframe>';
		return;
	}
	echo jinv_render_invoice_html( $inv ); // phpcs:ignore
}

function jinv_render_settings() {
	$s        = jinv_settings();
	$sections = jinv_settings_sections();
	$section  = isset( $_GET['section'] ) ? sanitize_key( $_GET['section'] ) : 'dodavatel'; // phpcs:ignore
	if ( ! isset( $sections[ $section ] ) ) {
		$section = 'dodavatel';
	}
	?>
	<nav class="jinv-tabs" aria-label="Nastavenia">
		<?php foreach ( $sections as $key => $sec ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=j-invoices-settings&section=' . $key ) ); ?>" class="<?php echo $key === $section ? 'is-active' : ''; ?>"><?php echo esc_html( $sec[0] ); ?></a>
		<?php endforeach; ?>
	</nav>
	<?php
	if ( 'import' === $section ) {
		jinv_render_import();
		return;
	}
	$all = jinv_setting_fields();
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="jinv-settings">
		<?php wp_nonce_field( 'jinv_settings' ); ?>
		<input type="hidden" name="action" value="jinv_save_settings">
		<input type="hidden" name="section" value="<?php echo esc_attr( $section ); ?>">
		<?php foreach ( $sections[ $section ][1] as $title ) : ?>
			<?php if ( empty( $all[ $title ] ) ) { continue; } ?>
			<div class="jinv-panel">
				<h2><?php echo esc_html( $title ); ?></h2>
				<table class="form-table" role="presentation">
					<?php foreach ( $all[ $title ] as $k => $f ) : ?>
						<tr>
							<th><label for="jinv-<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $f[0] ); ?></label></th>
							<td>
								<?php if ( 'select' === $f[1] ) : ?>
									<select id="jinv-<?php echo esc_attr( $k ); ?>" name="<?php echo esc_attr( $k ); ?>"><?php foreach ( $f[3] as $ov => $ol ) : ?><option value="<?php echo esc_attr( $ov ); ?>" <?php selected( (string) $s[ $k ], (string) $ov ); ?>><?php echo esc_html( $ol ); ?></option><?php endforeach; ?></select>
								<?php elseif ( 'checkbox' === $f[1] ) : ?>
									<label><input id="jinv-<?php echo esc_attr( $k ); ?>" type="checkbox" name="<?php echo esc_attr( $k ); ?>" value="1" <?php checked( ! empty( $s[ $k ] ) ); ?>> áno</label>
								<?php else : ?>
									<input id="jinv-<?php echo esc_attr( $k ); ?>" type="<?php echo esc_attr( $f[1] ); ?>" name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( (string) $s[ $k ] ); ?>" class="<?php echo 'number' === $f[1] ? 'small-text' : 'regular-text'; ?>">
								<?php endif; ?>
								<?php if ( $f[2] ) : ?><p class="description"><?php echo esc_html( $f[2] ); ?></p><?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
			</div>
		<?php endforeach; ?>
		<?php if ( 'doklady' === $section ) : ?>
			<p class="description">Ukážka čísla ďalšej faktúry: <strong><?php echo esc_html( 'order' === $s['numbering'] ? 'ako objednávka (napr. ' . ( ( $lo = wc_get_orders( array( 'limit' => 1, 'type' => 'shop_order' ) ) ) ? $lo[0]->get_order_number() : '1234' ) . ')' : jinv_next_number( 'issued', null, (int) current_time( 'Y' ) )[0] ); ?></strong>. Zmena platí pre nové doklady, vystavené ostávajú nezmenené.</p>
		<?php endif; ?>
		<?php submit_button( 'Uložiť nastavenia' ); ?>
	</form>
	<?php
}
