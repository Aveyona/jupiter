<?php
/** Administrácia J-Dopravy: Zásielky (objednávky s dopravou Packeta) a Nastavenia. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_menu_page( 'J-Doprava', 'J-Doprava', JDP_CAP, 'j-doprava', 'jdp_page_packets', 'dashicons-car', 58 );
	add_submenu_page( 'j-doprava', 'Zásielky – J-Doprava', 'Zásielky', JDP_CAP, 'j-doprava', 'jdp_page_packets' );
	add_submenu_page( 'j-doprava', 'Nastavenia – J-Doprava', 'Nastavenia', JDP_CAP, 'j-doprava-settings', 'jdp_page_settings' );
} );

function jdp_head( $title ) {
	echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html( $title ) . ' <span style="font-size:12px;color:#646970;font-weight:400">J-Doprava v' . esc_html( JDP_VERSION ) . '</span></h1><hr class="wp-header-end">';
	if ( ! empty( $_GET['jdp_msg'] ) && 'j-doprava-settings' === ( $_GET['page'] ?? '' ) ) { // phpcs:ignore
		$m = get_transient( 'jdp_msg_' . get_current_user_id() );
		delete_transient( 'jdp_msg_' . get_current_user_id() );
		if ( $m ) {
			echo '<div class="notice notice-' . ( false !== strpos( $m, 'zlyhalo' ) ? 'warning' : 'success' ) . ' is-dismissible"><p>' . esc_html( $m ) . '</p></div>';
		}
	}
}

/** Záložky stránky Zásielky. */
function jdp_tabs( array $tabs, $show ) {
	echo '<ul class="subsubsub">';
	$last = array_key_last( $tabs );
	foreach ( $tabs as $k => $t ) {
		echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=j-doprava&show=' . $k ) ) . '"' . ( $show === $k ? ' class="current"' : '' ) . '>' . esc_html( $t ) . '</a>' . ( $last !== $k ? ' | ' : '' ) . '</li>';
	}
	echo '</ul><br class="clear">';
}

function jdp_page_packets() {
	if ( ! current_user_can( JDP_CAP ) ) {
		return;
	}
	jdp_head( 'Zásielky Packeta' );
	if ( ! jdp_ready() ) {
		echo '<div class="notice notice-warning inline"><p>Najprv zadajte API heslo a odosielateľa v <a href="' . esc_url( admin_url( 'admin.php?page=j-doprava-settings' ) ) . '">Nastaveniach</a>.</p></div>';
	}
	$show   = sanitize_key( $_GET['show'] ?? 'todo' ); // phpcs:ignore
	$tabs   = array( 'todo' => 'Na podanie', 'print' => 'Na tlač', 'sent' => 'Na ceste', 'done' => 'Ukončené', 'all' => 'Všetky', 'post' => 'Slovenská pošta' );
	if ( 'post' === $show ) {
		jdp_tabs( $tabs, $show );
		jdp_post_tab( sanitize_key( $_GET['sub'] ?? '' ) ); // phpcs:ignore
		echo '</div>';
		return;
	}
	$orders = wc_get_orders( array( 'limit' => 200, 'orderby' => 'date', 'order' => 'DESC', 'meta_key' => '_jdp_kind', 'meta_compare' => 'EXISTS', 'status' => array_keys( wc_get_order_statuses() ) ) );
	$rows   = array();
	foreach ( $orders as $o ) {
		$pid  = (string) $o->get_meta( '_jdp_packet_id' );
		$st   = (int) $o->get_meta( '_jdp_status' );
		$todo = ( ! $pid || 11 === $st ) && jdp_ready_to_ship( $o );
		$print = $pid && ! jdp_status_final( $st ) && ! $o->get_meta( '_jdp_label_at' );
		if ( 'todo' === $show && ! $todo || 'print' === $show && ! $print || 'sent' === $show && ( ! $pid || jdp_status_final( $st ) ) || 'done' === $show && ! ( $pid && jdp_status_final( $st ) ) ) {
			continue;
		}
		$rows[] = $o;
	}
	jdp_tabs( $tabs, $show );
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="jdp-list">
		<?php wp_nonce_field( 'jdp_action' ); ?><input type="hidden" name="action" value="jdp_action">
		<p>
			<button class="button button-primary" name="act" value="create">Podať označené</button>
			<button class="button" name="act" value="labels" formtarget="_blank">Štítky PDF</button>
			<select name="format"><option value="">formát z nastavení (<?php echo esc_html( jdp_opt( 'label_format' ) ); ?>)</option><?php foreach ( jdp_label_formats() as $k => $v ) { echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $v ) . '</option>'; } ?></select>
			<button class="button" name="act" value="status">Obnoviť stav</button>
		</p>
		<table class="widefat striped">
			<thead><tr><td class="check-column"><input type="checkbox" onclick="document.querySelectorAll('#jdp-list input[name=&quot;ids[]&quot;]').forEach(c=>c.checked=this.checked)"></td><th>Objednávka</th><th>Dátum</th><th>Zákazník</th><th>Doprava</th><th class="num">Suma</th><th>Platba</th><th>Zásielka</th><th>Stav</th></tr></thead>
			<tbody>
			<?php if ( ! $rows ) : ?><tr><td colspan="9">Žiadne objednávky.</td></tr><?php endif; ?>
			<?php foreach ( $rows as $o ) : $bar = (string) $o->get_meta( '_jdp_barcode' ); $st = (int) $o->get_meta( '_jdp_status' ); ?>
				<tr>
					<th class="check-column"><input type="checkbox" name="ids[]" value="<?php echo (int) $o->get_id(); ?>"></th>
					<td><a href="<?php echo esc_url( $o->get_edit_order_url() ); ?>">#<?php echo esc_html( $o->get_order_number() ); ?></a><br><small><?php echo esc_html( wc_get_order_status_name( $o->get_status() ) ); ?></small></td>
					<td><?php echo esc_html( $o->get_date_created() ? $o->get_date_created()->date_i18n( 'j. n. Y H:i' ) : '' ); ?></td>
					<td><?php echo esc_html( $o->get_formatted_billing_full_name() ); ?><br><small><?php echo esc_html( $o->get_billing_phone() ); ?></small></td>
					<td><?php echo 'pp' === $o->get_meta( '_jdp_kind' ) ? 'Výdajné miesto<br><small>' . esc_html( (string) $o->get_meta( '_jdp_point_label' ) ) . '</small>' : 'Na adresu<br><small>' . esc_html( $o->get_shipping_city() ? $o->get_shipping_city() : $o->get_billing_city() ) . '</small>'; ?></td>
					<td class="num"><?php echo wp_kses_post( $o->get_formatted_order_total() ); ?></td>
					<td><?php echo esc_html( $o->get_payment_method_title() ); ?><?php echo jdp_is_cod( $o ) ? '<br><small><strong>dobierka</strong></small>' : ''; ?></td>
					<td><?php echo $bar ? '<a href="' . esc_url( jdp_tracking_url( $bar ) ) . '" target="_blank">' . esc_html( $bar ) . '</a>' . ( $o->get_meta( '_jdp_label_at' ) ? '<br><small>štítok vytlačený</small>' : '' ) : '—'; ?></td>
					<td><?php echo $bar ? esc_html( (string) $o->get_meta( '_jdp_status_text' ) ) : '<span style="color:#996800">nepodaná</span>'; ?><?php echo $st && $o->get_meta( '_jdp_status_at' ) ? '<br><small>' . esc_html( date_i18n( 'j. n. H:i', strtotime( (string) $o->get_meta( '_jdp_status_at' ) ) ) ) . '</small>' : ''; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</form>
	<p class="description">Zásielky podáte aj z detailu objednávky (box Packeta) alebo hromadne v zozname objednávok (Hromadné akcie → Packeta – podať zásielky). Na tlač = podané zásielky, ktorým ste ešte nevytlačili štítok. Stav sa aktualizuje každú hodinu; doručené objednávky sa nastavia na Vybavené.</p>
	</div>
	<?php
}

function jdp_page_settings() {
	if ( ! current_user_can( JDP_CAP ) ) {
		return;
	}
	$s = jdp_settings();
	jdp_head( 'Nastavenia J-Dopravy' );
	$zones = admin_url( 'admin.php?page=wc-settings&tab=shipping' );
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'jdp_settings' ); ?><input type="hidden" name="action" value="jdp_settings">
		<h2>Packeta – prístup</h2>
		<table class="form-table" role="presentation">
			<tr><th><label for="jdp-pw">API heslo</label></th><td><input type="password" id="jdp-pw" name="api_password" class="regular-text" autocomplete="new-password" placeholder="<?php echo $s['api_password'] ? '•••••••• (uložené – vyplňte len pri zmene)' : 'z klientskej zóny Packeta'; ?>"><?php if ( $s['api_password'] ) : ?> <label><input type="checkbox" name="api_password_clear" value="1"> vymazať</label><?php endif; ?><p class="description">Klientska zóna Packeta (client.packeta.com) → Nastavenia klienta → API heslo. Uloží sa zašifrované.</p></td></tr>
			<tr><th><label for="jdp-key">API kľúč (mapa)</label></th><td><input id="jdp-key" name="api_key" value="<?php echo esc_attr( $s['api_key'] ); ?>" class="regular-text" placeholder="prázdne = prvých 16 znakov API hesla"><p class="description">Verejný kľúč pre mapu výdajných miest v košíku (16 znakov).</p></td></tr>
			<tr><th><label for="jdp-sender">Odosielateľ (označenie e-shopu)</label></th><td><input id="jdp-sender" name="sender" value="<?php echo esc_attr( $s['sender'] ); ?>" class="regular-text"><p class="description">Klientska zóna → Odosielatelia → označenie odosielateľa (napr. hedlajner). Musí presne sedieť.</p></td></tr>
			<tr><th>Krajiny výdajných miest</th><td><input name="country" value="<?php echo esc_attr( $s['country'] ); ?>" style="width:120px"> <span class="description">sk, prípadne sk,cz</span></td></tr>
		</table>
		<h2>Zásielky</h2>
		<table class="form-table" role="presentation">
			<tr><th>Hmotnosť</th><td>obal <input name="weight_add" value="<?php echo esc_attr( str_replace( '.', ',', (string) $s['weight_add'] ) ); ?>" style="width:70px"> kg, najmenej <input name="weight_min" value="<?php echo esc_attr( str_replace( '.', ',', (string) $s['weight_min'] ) ); ?>" style="width:70px"> kg <p class="description">Hmotnosť zásielky = súčet hmotností produktov + obal.</p></td></tr>
			<tr><th>Formát štítkov</th><td><select name="label_format"><?php foreach ( jdp_label_formats() as $k => $v ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $s['label_format'], $k, false ) . '>' . esc_html( $v ) . '</option>'; } ?></select></td></tr>
			<tr><th>Dobierka</th><td>platobné metódy <input name="cod_methods" value="<?php echo esc_attr( $s['cod_methods'] ); ?>" style="width:160px"> <span class="description">ID platby, oddelené čiarkou (dobierka = cod)</span></td></tr>
			<tr><th>Sledovanie</th><td><label><input type="checkbox" name="auto_complete" value="1" <?php checked( (int) $s['auto_complete'] ); ?>> doručenú objednávku nastaviť na Vybavená</label><br><label><input type="checkbox" name="track_email" value="1" <?php checked( (int) $s['track_email'] ); ?>> odkaz na sledovanie zásielky v e-maile a v Mojom účte</label></td></tr>
			<tr><th>Automatické podanie</th><td><label><input type="checkbox" name="auto_create" value="1" <?php checked( (int) $s['auto_create'] ); ?>> podať zásielku Packeta automaticky, keď objednávka prejde do stavu Spracováva sa</label><p class="description">Zásielka sa podá na pozadí s hmotnosťou z produktov a dobierkou podľa platby. Ak sa podanie nepodarí, dôvod je v poznámke objednávky a zásielku podáte ručne.</p></td></tr>
		</table>
		<h2>Slovenská pošta – ePodací hárok</h2>
		<p class="description">Objednávky s dopravou poštou exportujete v J-Doprava → Zásielky → Slovenská pošta do súboru XML a nahráte ho do aplikácie ePodací hárok (eph.posta.sk).</p>
		<table class="form-table" role="presentation">
			<tr><th>Doprava poštou</th><td>názov spôsobu dopravy obsahuje <input name="post_match" value="<?php echo esc_attr( $s['post_match'] ); ?>" style="width:140px"> <span class="description">napr. „pošta“ (Slovenská pošta – doporučený list)</span></td></tr>
			<tr><th>Druh zásielky</th><td><select name="post_kind"><?php foreach ( jdp_post_kinds() as $k => $v ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( (string) $s['post_kind'], (string) $k, false ) . '>' . esc_html( $v ) . '</option>'; } ?></select> trieda <select name="post_class"><option value="1" <?php selected( $s['post_class'], '1' ); ?>>1. trieda</option><option value="2" <?php selected( $s['post_class'], '2' ); ?>>2. trieda</option></select></td></tr>
			<tr><th>Odosielateľ</th><td>
				<input name="post_org" value="<?php echo esc_attr( $s['post_org'] ); ?>" placeholder="Firma (napr. Aveyona, s. r. o.)" class="regular-text"><br>
				<input name="post_name" value="<?php echo esc_attr( $s['post_name'] ); ?>" placeholder="Meno (nepovinné pri firme)" class="regular-text"><br>
				<input name="post_street" value="<?php echo esc_attr( $s['post_street'] ); ?>" placeholder="Ulica a číslo" class="regular-text"><br>
				<input name="post_zip" value="<?php echo esc_attr( $s['post_zip'] ); ?>" placeholder="PSČ" style="width:90px"> <input name="post_city" value="<?php echo esc_attr( $s['post_city'] ); ?>" placeholder="Obec" style="width:240px"><br>
				<input name="post_phone" value="<?php echo esc_attr( $s['post_phone'] ); ?>" placeholder="Telefón" style="width:160px"> <input name="post_email" value="<?php echo esc_attr( $s['post_email'] ); ?>" placeholder="E-mail" style="width:240px">
			</td></tr>
			<tr><th>Účet pre dobierky</th><td><input name="post_iban" value="<?php echo esc_attr( $s['post_iban'] ); ?>" placeholder="IBAN" class="regular-text"><p class="description">Len ak posielate poštou na dobierku – pošta pošle vybranú sumu na tento účet s variabilným symbolom = číslo objednávky.</p></td></tr>
		</table>
		<p><button class="button button-primary">Uložiť a overiť spojenie</button></p>
	</form>
	<h2>Spôsoby dopravy</h2>
	<p>Osobný odber: e-mail „pripravené na odber“ pošlete z detailu objednávky (Akcie objednávky) alebo hromadne zo zoznamu objednávok; text upravíte vo WooCommerce → Nastavenia → E-maily → Pripravené na osobný odber.</p>
	<p>V <a href="<?php echo esc_url( $zones ); ?>">WooCommerce → Nastavenia → Doprava</a> pridajte do zóny <strong>Slovensko</strong> spôsoby <em>Packeta – výdajné miesto / Z-BOX</em> a <em>Packeta – doručenie na adresu</em> a nastavte cenu a dopravu zadarmo od sumy. V košíku sa zobrazia až po zadaní API hesla a odosielateľa.</p>
	</div>
	<?php
}
