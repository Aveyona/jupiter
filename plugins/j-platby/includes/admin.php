<?php
/** Administrácia J-Platieb: Platby (prehľad platieb kartou) a Nastavenia (kľúče Stripe, webhook). */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_menu_page( 'J-Platby', 'J-Platby', JPL_CAP, 'j-platby', 'jpl_page_payments', 'dashicons-money-alt', 58 );
	add_submenu_page( 'j-platby', 'Platby – J-Platby', 'Platby', JPL_CAP, 'j-platby', 'jpl_page_payments' );
	add_submenu_page( 'j-platby', 'Nastavenia – J-Platby', 'Nastavenia', JPL_CAP, 'j-platby-settings', 'jpl_page_settings' );
} );

function jpl_head( $title ) {
	echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html( $title ) . ' <span style="font-size:12px;color:#646970;font-weight:400">J-Platby v' . esc_html( JPL_VERSION ) . '</span></h1>';
	if ( jpl_ready() ) {
		echo ' <span style="display:inline-block;margin:12px 0 0 8px;padding:2px 10px;border-radius:10px;font-size:12px;' . ( jpl_is_test() ? 'background:#fcf0e3;color:#8a4b00">TESTOVACÍ REŽIM' : 'background:#e6f4ea;color:#1e6b34">OSTRÝ REŽIM' ) . '</span>';
	}
	echo '<hr class="wp-header-end">';
	if ( ! empty( $_GET['jpl_msg'] ) ) { // phpcs:ignore
		$m = get_transient( 'jpl_msg_' . get_current_user_id() );
		delete_transient( 'jpl_msg_' . get_current_user_id() );
		if ( $m ) {
			echo '<div class="notice notice-' . ( preg_match( '/zlyhalo|musí|Chýba|Pozor/u', $m ) ? 'warning' : 'success' ) . ' is-dismissible"><p>' . esc_html( $m ) . '</p></div>';
		}
	}
}

function jpl_page_payments() {
	if ( ! current_user_can( JPL_CAP ) ) {
		return;
	}
	jpl_head( 'Platby kartou' );
	if ( ! jpl_ready() ) {
		echo '<div class="notice notice-warning inline"><p>Najprv zadajte kľúče Stripe v <a href="' . esc_url( admin_url( 'admin.php?page=j-platby-settings' ) ) . '">Nastaveniach</a>.</p></div>';
	}
	$month  = preg_match( '/^\d{4}-\d{2}$/', (string) ( $_GET['m'] ?? '' ) ) ? $_GET['m'] : current_time( 'Y-m' ); // phpcs:ignore
	$from   = strtotime( $month . '-01 00:00:00' );
	$to     = strtotime( '+1 month', $from ) - 1;
	$orders = wc_get_orders( array( 'limit' => 500, 'payment_method' => 'jpl_stripe', 'date_created' => $from . '...' . $to, 'orderby' => 'date', 'order' => 'DESC', 'status' => array_keys( wc_get_order_statuses() ) ) );
	$sum    = array( 'paid' => 0.0, 'fee' => 0.0, 'net' => 0.0, 'ref' => 0.0, 'n' => 0 );
	?>
	<form method="get" style="margin:12px 0"><input type="hidden" name="page" value="j-platby">
		Mesiac <input type="month" name="m" value="<?php echo esc_attr( $month ); ?>"> <button class="button">Zobraziť</button>
		<a class="button" href="<?php echo esc_url( jpl_dashboard_url( 'payments' ) ); ?>" target="_blank" rel="noopener">Otvoriť Stripe</a>
		<a class="button" href="<?php echo esc_url( jpl_dashboard_url( 'payouts' ) ); ?>" target="_blank" rel="noopener">Výplaty na účet</a>
	</form>
	<table class="widefat striped">
		<thead><tr><th>Objednávka</th><th>Dátum</th><th>Zákazník</th><th class="num">Suma</th><th class="num">Poplatok</th><th class="num">Dostanete</th><th>Karta</th><th>Stav</th><th>Stripe</th></tr></thead>
		<tbody>
		<?php if ( ! $orders ) : ?><tr><td colspan="9">Žiadne objednávky s platbou kartou v tomto mesiaci.</td></tr><?php endif; ?>
		<?php
		foreach ( $orders as $o ) :
			$pi   = (string) $o->get_meta( '_jpl_pi' );
			$fee  = $o->get_meta( '_jpl_fee' );
			$net  = $o->get_meta( '_jpl_net' );
			$ref  = (float) $o->get_total_refunded();
			$test = 'test' === $o->get_meta( '_jpl_mode' );
			if ( $pi ) {
				$sum['n']++;
				$sum['paid'] += (float) $o->get_total();
				$sum['fee']  += (float) $fee;
				$sum['net']  += (float) $net;
				$sum['ref']  += $ref;
			}
			$st = $pi ? ( $ref > 0 ? ( $ref >= (float) $o->get_total() - 0.005 ? 'vrátená' : 'čiastočne vrátená' ) : 'zaplatená' ) : ( $o->has_status( 'pending' ) ? 'čaká na platbu' : 'nezaplatená' );
			?>
			<tr>
				<td><a href="<?php echo esc_url( $o->get_edit_order_url() ); ?>">#<?php echo esc_html( $o->get_order_number() ); ?></a><?php echo $test ? ' <small style="color:#b26200">test</small>' : ''; ?><br><small><?php echo esc_html( wc_get_order_status_name( $o->get_status() ) ); ?></small></td>
				<td><?php echo esc_html( $o->get_date_created() ? $o->get_date_created()->date_i18n( 'j. n. Y H:i' ) : '' ); ?></td>
				<td><?php echo esc_html( $o->get_formatted_billing_full_name() ); ?></td>
				<td class="num"><?php echo wp_kses_post( wc_price( $o->get_total(), array( 'currency' => $o->get_currency() ) ) ); ?><?php echo $ref > 0 ? '<br><small style="color:#b32d2e">−' . wp_kses_post( wc_price( $ref ) ) . '</small>' : ''; ?></td>
				<td class="num"><?php echo '' !== (string) $fee ? wp_kses_post( wc_price( (float) $fee ) ) : '—'; ?></td>
				<td class="num"><?php echo '' !== (string) $net ? wp_kses_post( wc_price( (float) $net ) ) : '—'; ?></td>
				<td><?php echo esc_html( (string) $o->get_meta( '_jpl_card' ) ); ?></td>
				<td><?php echo esc_html( $st ); ?></td>
				<td><?php echo $pi ? '<a href="' . esc_url( jpl_dashboard_url( 'payments/' . $pi, $test ? 'test' : 'live' ) ) . '" target="_blank" rel="noopener">detail</a>' : '—'; ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
		<?php if ( $sum['n'] ) : ?>
		<tfoot><tr><th colspan="3">Spolu <?php echo (int) $sum['n']; ?> platieb</th><th class="num"><?php echo wp_kses_post( wc_price( $sum['paid'] ) ); ?><?php echo $sum['ref'] > 0 ? '<br><small>vrátené −' . wp_kses_post( wc_price( $sum['ref'] ) ) . '</small>' : ''; ?></th><th class="num"><?php echo wp_kses_post( wc_price( $sum['fee'] ) ); ?></th><th class="num"><?php echo wp_kses_post( wc_price( $sum['net'] ) ); ?></th><th colspan="3"></th></tr></tfoot>
		<?php endif; ?>
	</table>
	<p class="description">Peniaze z platieb posiela Stripe na bankový účet automaticky (výplata). Vrátenie peňazí: detail objednávky → Vrátiť peniaze → Vrátiť cez Platba kartou.</p>
	</div>
	<?php
}

function jpl_page_settings() {
	if ( ! current_user_can( JPL_CAP ) ) {
		return;
	}
	$s    = jpl_settings();
	$last = (array) get_option( 'jpl_webhook_last', array() );
	jpl_head( 'Nastavenia J-Platieb' );
	$events = array( 'checkout.session.completed', 'checkout.session.async_payment_succeeded', 'checkout.session.async_payment_failed', 'checkout.session.expired', 'charge.refunded' );
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" autocomplete="off">
		<?php wp_nonce_field( 'jpl_settings' ); ?><input type="hidden" name="action" value="jpl_settings">
		<h2>Režim</h2>
		<table class="form-table" role="presentation">
			<tr><th>Platby</th><td>
				<label><input type="radio" name="mode" value="test" <?php checked( 'test', $s['mode'] ); ?>> <strong>Testovací režim</strong> – skúšobné platby testovacími kartami, žiadne skutočné peniaze</label><br>
				<label><input type="radio" name="mode" value="live" <?php checked( 'live', $s['mode'] ); ?>> <strong>Ostrý režim</strong> – skutočné platby zákazníkov (až po aktivácii účtu v Stripe)</label>
			</td></tr>
		</table>
		<?php foreach ( array( 'test' => 'Testovacie kľúče (Sandbox)', 'live' => 'Ostré kľúče' ) as $m => $label ) : ?>
		<h2><?php echo esc_html( $label ); ?></h2>
		<table class="form-table" role="presentation">
			<tr><th><label for="jpl-pk-<?php echo esc_attr( $m ); ?>">Publikovateľný kľúč</label></th><td><input id="jpl-pk-<?php echo esc_attr( $m ); ?>" name="pk_<?php echo esc_attr( $m ); ?>" value="<?php echo esc_attr( $s[ 'pk_' . $m ] ); ?>" class="large-text code" placeholder="pk_<?php echo esc_attr( $m ); ?>_…" spellcheck="false"></td></tr>
			<tr><th><label for="jpl-sk-<?php echo esc_attr( $m ); ?>">Tajný kľúč</label></th><td><input type="password" id="jpl-sk-<?php echo esc_attr( $m ); ?>" name="sk_<?php echo esc_attr( $m ); ?>" class="large-text code" autocomplete="new-password" placeholder="<?php echo $s[ 'sk_' . $m ] ? '•••••••• (uložený – vyplňte len pri zmene)' : 'sk_' . esc_attr( $m ) . '_…'; ?>"><?php if ( $s[ 'sk_' . $m ] ) : ?> <label><input type="checkbox" name="sk_<?php echo esc_attr( $m ); ?>_clear" value="1"> vymazať</label><?php endif; ?></td></tr>
			<tr><th><label for="jpl-wh-<?php echo esc_attr( $m ); ?>">Tajomstvo webhooku</label></th><td><input type="password" id="jpl-wh-<?php echo esc_attr( $m ); ?>" name="wh_<?php echo esc_attr( $m ); ?>" class="large-text code" autocomplete="new-password" placeholder="<?php echo $s[ 'wh_' . $m ] ? '•••••••• (uložené – vyplňte len pri zmene)' : 'whsec_…'; ?>"><?php if ( $s[ 'wh_' . $m ] ) : ?> <label><input type="checkbox" name="wh_<?php echo esc_attr( $m ); ?>_clear" value="1"> vymazať</label><?php endif; ?></td></tr>
		</table>
		<?php endforeach; ?>
		<p class="description">Kľúče nájdete v Stripe → Developers (Vývojári) → API keys. Tajný kľúč a tajomstvo webhooku sa uložia zašifrované a už sa nezobrazia.</p>
		<h2>Účtovníctvo</h2>
		<table class="form-table" role="presentation">
			<tr><th>Poplatky Stripe</th><td><label><input type="checkbox" name="post_fees" value="1" <?php checked( (int) $s['post_fees'] ); ?>> poplatok z každej platby zaúčtovať do J-Účtovníctva (568 / 261)</label><p class="description">Úhrada faktúry kartou sa účtuje 261 / 311, výplata zo Stripe na účet 221 / 261. Testovacie platby sa neúčtujú.</p></td></tr>
		</table>
		<p><button class="button button-primary">Uložiť a overiť spojenie</button></p>
	</form>

	<h2>Webhook (potvrdzovanie platieb)</h2>
	<p>V Stripe → Developers → <strong>Webhooks</strong> → <em>Add endpoint</em> zadajte túto adresu:</p>
	<p><input type="text" readonly value="<?php echo esc_attr( jpl_webhook_url() ); ?>" class="large-text code" onclick="this.select()"></p>
	<p>a vyberte udalosti:</p>
	<p><code><?php echo implode( '</code> <code>', array_map( 'esc_html', $events ) ); ?></code></p>
	<p>Po vytvorení kliknite na <em>Reveal signing secret</em> a hodnotu <code>whsec_…</code> vložte vyššie do poľa Tajomstvo webhooku.</p>
	<p><strong>Posledná udalosť zo Stripe:</strong> <?php echo $last ? esc_html( $last['at'] . ' – ' . ( $last['ok'] ? ( $last['type'] ?? '' ) . ' (' . ( $last['msg'] ?? '' ) . ')' : 'ODMIETNUTÁ: ' . ( $last['msg'] ?? '' ) ) ) : 'zatiaľ žiadna'; ?></p>

	<h2>Testovacie karty</h2>
	<table class="widefat" style="max-width:640px">
		<tr><td><code>4242 4242 4242 4242</code></td><td>úspešná platba</td></tr>
		<tr><td><code>4000 0000 0000 0002</code></td><td>zamietnutá karta</td></tr>
		<tr><td><code>4000 0027 6000 3184</code></td><td>overenie 3D Secure (potvrdiť v okne banky)</td></tr>
	</table>
	<p class="description">Platnosť: ľubovoľný budúci dátum, CVC: ľubovoľné 3 číslice.</p>
	</div>
	<?php
}
