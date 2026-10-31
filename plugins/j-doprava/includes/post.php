<?php
/**
 * Slovenská pošta – ePodací hárok (EPH): objednávky s dopravou poštou (podľa textu v názve spôsobu dopravy) sa dajú
 * hromadne exportovať do XML súboru na import v aplikácii ePodací hárok (eph.posta.sk). Formát EPH verzia 3.0.
 * Meta objednávky: _jdp_eph_at (čas exportu).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const JDP_EPH_NS = 'http://ekp.posta.sk/LOGIS/Formulare/Podaj_v03';

/** Druhy zásielok pošty (kód EPH => názov). */
function jdp_post_kinds() {
	return array( '1' => 'Doporučený list', '2' => 'Poistený list', '4' => 'Balík', '14' => 'Zmluvný balík' );
}

/** Je doprava objednávky poštou? (text z nastavení v názve spôsobu dopravy, mimo Packety) */
function jdp_is_post( $order ) {
	$match = mb_strtolower( trim( (string) jdp_opt( 'post_match' ) ) );
	if ( '' === $match ) {
		return false;
	}
	foreach ( $order->get_shipping_methods() as $m ) {
		if ( 0 !== strpos( $m->get_method_id(), 'jdp_' ) && false !== mb_strpos( mb_strtolower( $m->get_name() ), $match ) ) {
			return true;
		}
	}
	return false;
}

/** Objednávky na poštu (posledných 60 dní): $exported = false → ešte neexportované a pripravené na odoslanie (zaplatené alebo dobierka). */
function jdp_post_orders( $exported = false ) {
	$orders = wc_get_orders( array(
		'limit'        => 200,
		'type'         => 'shop_order',
		'orderby'      => 'date',
		'order'        => 'DESC',
		'date_created' => '>' . ( time() - 60 * DAY_IN_SECONDS ),
		'status'       => $exported ? array( 'wc-processing', 'wc-on-hold', 'wc-completed' ) : array( 'wc-processing', 'wc-on-hold' ),
	) );
	return array_values( array_filter( $orders, function ( $o ) use ( $exported ) {
		return jdp_is_post( $o ) && ( $exported ? '' !== (string) $o->get_meta( '_jdp_eph_at' ) : '' === (string) $o->get_meta( '_jdp_eph_at' ) && jdp_ready_to_ship( $o ) );
	} ) );
}

/** XML ePodacieho hárku pre objednávky. */
function jdp_eph_xml( array $orders ) {
	$s    = jdp_settings();
	$kind = (string) $s['post_kind'];
	$cod  = false;
	foreach ( $orders as $o ) {
		$cod = $cod || jdp_is_cod( $o );
	}
	$x    = new SimpleXMLElement( '<?xml version="1.0" encoding="UTF-8"?><EPH xmlns="' . JDP_EPH_NS . '" verzia="3.0"/>' );
	$info = $x->addChild( 'InfoEPH' );
	$add  = function ( SimpleXMLElement $el, $k, $v ) {
		if ( '' !== (string) $v ) {
			$el->addChild( $k, htmlspecialchars( (string) $v, ENT_XML1 ) );
		}
	};
	$add( $info, 'Mena', 'EUR' );
	$add( $info, 'TypEPH', '1' );
	$info->addChild( 'EPHID' );
	$add( $info, 'Datum', current_time( 'Ymd' ) );
	$add( $info, 'PocetZasielok', count( $orders ) );
	$u = $info->addChild( 'Uhrada' );
	$add( $u, 'SposobUhrady', '5' );
	$add( $u, 'SumaUhrady', '0.00' );
	if ( $cod ) {
		$add( $info, 'DruhPPP', '5' );
	}
	$add( $info, 'DruhZasielky', $kind );
	$add( $info, 'SposobSpracovania', '1' );
	$od = $info->addChild( 'Odosielatel' );
	$od->addChild( 'OdosielatelID' );
	$add( $od, 'Meno', $s['post_name'] );
	$add( $od, 'Organizacia', '' !== $s['post_org'] ? $s['post_org'] : get_bloginfo( 'name' ) );
	$add( $od, 'Ulica', $s['post_street'] );
	$add( $od, 'Mesto', $s['post_city'] );
	$add( $od, 'PSC', $s['post_zip'] );
	$add( $od, 'Krajina', 'SK' );
	$add( $od, 'Telefon', $s['post_phone'] );
	$add( $od, 'Email', $s['post_email'] );
	if ( $cod ) {
		$add( $od, 'CisloUctu', $s['post_iban'] );
	}
	$z = $x->addChild( 'Zasielky' );
	foreach ( $orders as $o ) {
		$ship = $o->has_shipping_address();
		$g    = function ( $f ) use ( $o, $ship ) {
			$m = ( $ship ? 'get_shipping_' : 'get_billing_' ) . $f;
			return (string) $o->$m();
		};
		$el = $z->addChild( 'Zasielka' );
		$a  = $el->addChild( 'Adresat' );
		$add( $a, 'Meno', trim( $g( 'first_name' ) . ' ' . $g( 'last_name' ) ) );
		$add( $a, 'Organizacia', $g( 'company' ) );
		$add( $a, 'Ulica', trim( $g( 'address_1' ) . ' ' . $g( 'address_2' ) ) );
		$add( $a, 'Mesto', $g( 'city' ) );
		$add( $a, 'PSC', preg_replace( '/\s+/', '', $g( 'postcode' ) ) );
		$add( $a, 'Krajina', strtoupper( $g( 'country' ) ? $g( 'country' ) : 'SK' ) );
		$add( $a, 'Telefon', preg_replace( '/[^\d+]/', '', $o->get_shipping_phone() ? $o->get_shipping_phone() : $o->get_billing_phone() ) );
		$add( $a, 'Email', $o->get_billing_email() );
		$i = $el->addChild( 'Info' );
		$add( $i, 'ZasielkaID', mb_substr( (string) $o->get_order_number(), 0, 10 ) );
		$add( $i, 'Hmotnost', number_format( jdp_order_weight( $o ), 3, '.', '' ) );
		if ( jdp_is_cod( $o ) ) {
			$add( $i, 'CenaDobierky', number_format( (float) $o->get_total(), 2, '.', '' ) );
			$add( $i, 'CisloUctu', $s['post_iban'] );
			$add( $i, 'SymbolPrevodu', substr( preg_replace( '/\D/', '', (string) $o->get_order_number() ), -10 ) );
		}
		if ( in_array( $kind, array( '1', '2', '4' ), true ) ) {
			$add( $i, 'Trieda', $s['post_class'] );
		}
		$add( $i, 'Poznamka', 'Objednávka ' . $o->get_order_number() );
	}
	$dom                     = dom_import_simplexml( $x )->ownerDocument;
	$dom->formatOutput       = true;
	$dom->preserveWhiteSpace = false;
	return $dom->saveXML();
}

/** Je vyplnený odosielateľ pre ePodací hárok? */
function jdp_post_ready() {
	$s = jdp_settings();
	return ( '' !== $s['post_name'] || '' !== $s['post_org'] ) && '' !== $s['post_street'] && '' !== $s['post_city'] && '' !== $s['post_zip'];
}

add_action( 'admin_post_jdp_eph', function () {
	if ( ! current_user_can( JDP_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jdp_eph' );
	$orders = array();
	foreach ( array_map( 'absint', (array) ( $_POST['ids'] ?? array() ) ) as $id ) { // phpcs:ignore
		$o = wc_get_order( $id );
		if ( $o && jdp_is_post( $o ) ) {
			$orders[] = $o;
		}
	}
	if ( ! $orders ) {
		wp_die( 'Označte aspoň jednu objednávku s dopravou poštou.', 'ePodací hárok', array( 'back_link' => true ) );
	}
	if ( ! jdp_post_ready() ) {
		wp_die( 'Najprv vyplňte odosielateľa pre Slovenskú poštu v J-Doprava → Nastavenia.', 'ePodací hárok', array( 'back_link' => true ) );
	}
	$xml = jdp_eph_xml( $orders );
	foreach ( $orders as $o ) {
		$o->update_meta_data( '_jdp_eph_at', current_time( 'mysql' ) );
		$o->add_order_note( 'Slovenská pošta: objednávka exportovaná do ePodacieho hárku.' );
		$o->save();
	}
	nocache_headers();
	header( 'Content-Type: application/xml; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="epodaci-harok-' . current_time( 'Ymd-His' ) . '.xml"' );
	echo $xml; // phpcs:ignore
	exit;
} );

/** Záložka Slovenská pošta na stránke Zásielky. */
function jdp_post_tab( $sub ) {
	$exported = 'done' === $sub;
	$orders   = jdp_post_orders( $exported );
	$base     = admin_url( 'admin.php?page=j-doprava&show=post' );
	echo '<p class="description">Zaplatené objednávky a dobierky s dopravou, ktorej názov obsahuje „' . esc_html( (string) jdp_opt( 'post_match' ) ) . '“ (posledných 60 dní). Označte ich a stiahnite XML – v aplikácii <a href="https://eph.posta.sk" target="_blank" rel="noopener">ePodací hárok</a> ho nahráte cez Import. Druh zásielky: <strong>' . esc_html( jdp_post_kinds()[ (string) jdp_opt( 'post_kind' ) ] ?? '' ) . '</strong>.</p>';
	if ( ! jdp_post_ready() ) {
		echo '<div class="notice notice-warning inline"><p>Vyplňte odosielateľa pre Slovenskú poštu v <a href="' . esc_url( admin_url( 'admin.php?page=j-doprava-settings' ) ) . '">Nastaveniach</a>.</p></div>';
	}
	echo '<p><a href="' . esc_url( $base ) . '"' . ( ! $exported ? ' class="button button-small button-primary"' : ' class="button button-small"' ) . '>Na export</a> <a href="' . esc_url( $base . '&sub=done' ) . '" class="button button-small' . ( $exported ? ' button-primary' : '' ) . '">Exportované</a></p>';
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="jdp-post">
		<?php wp_nonce_field( 'jdp_eph' ); ?><input type="hidden" name="action" value="jdp_eph">
		<p><button class="button button-primary">Stiahnuť ePodací hárok (XML)</button></p>
		<table class="widefat striped">
			<thead><tr><td class="check-column"><input type="checkbox" onclick="document.querySelectorAll('#jdp-post input[name=&quot;ids[]&quot;]').forEach(c=>c.checked=this.checked)"></td><th>Objednávka</th><th>Dátum</th><th>Adresát</th><th>Doprava</th><th class="num">Suma</th><th>Platba</th><th>Hmotnosť</th><?php if ( $exported ) : ?><th>Exportované</th><?php endif; ?></tr></thead>
			<tbody>
			<?php if ( ! $orders ) : ?><tr><td colspan="9">Žiadne objednávky.</td></tr><?php endif; ?>
			<?php foreach ( $orders as $o ) : $ship = $o->has_shipping_address(); ?>
				<tr>
					<th class="check-column"><input type="checkbox" name="ids[]" value="<?php echo (int) $o->get_id(); ?>"></th>
					<td><a href="<?php echo esc_url( $o->get_edit_order_url() ); ?>">#<?php echo esc_html( $o->get_order_number() ); ?></a><br><small><?php echo esc_html( wc_get_order_status_name( $o->get_status() ) ); ?></small></td>
					<td><?php echo esc_html( $o->get_date_created() ? $o->get_date_created()->date_i18n( 'j. n. Y H:i' ) : '' ); ?></td>
					<td><?php echo esc_html( $ship ? $o->get_formatted_shipping_full_name() : $o->get_formatted_billing_full_name() ); ?><br><small><?php echo esc_html( trim( ( $ship ? $o->get_shipping_address_1() : $o->get_billing_address_1() ) . ', ' . ( $ship ? $o->get_shipping_postcode() . ' ' . $o->get_shipping_city() : $o->get_billing_postcode() . ' ' . $o->get_billing_city() ), ', ' ) ); ?></small></td>
					<td><?php echo esc_html( $o->get_shipping_method() ); ?></td>
					<td class="num"><?php echo wp_kses_post( $o->get_formatted_order_total() ); ?></td>
					<td><?php echo esc_html( $o->get_payment_method_title() ); ?><?php echo jdp_is_cod( $o ) ? '<br><small><strong>dobierka</strong></small>' : ''; ?></td>
					<td><?php echo esc_html( str_replace( '.', ',', (string) jdp_order_weight( $o ) ) ); ?> kg</td>
					<?php if ( $exported ) : ?><td><?php echo esc_html( date_i18n( 'j. n. H:i', strtotime( (string) $o->get_meta( '_jdp_eph_at' ) ) ) ); ?></td><?php endif; ?>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</form>
	<p class="description">Po exporte sa objednávka presunie do Exportované (dá sa exportovať znova). Hmotnosť = produkty + obal z nastavení. Pri dobierke sa použije účet z nastavení a variabilný symbol = číslo objednávky.</p>
	<?php
}
