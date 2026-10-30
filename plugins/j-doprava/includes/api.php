<?php
/**
 * Packeta API (REST/XML, https://www.zasilkovna.cz/api/rest): koreňový prvok = názov metódy, prvý parameter apiPassword.
 * Odpoveď: <response><status>ok|fault</status><result>…</result> alebo <fault>, <string>, <detail>.
 * ID zásielky sa vždy spracúva ako reťazec (môže presiahnuť 32-bit integer).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const JDP_API_URL = 'https://www.zasilkovna.cz/api/rest';

/** Zápis poľa do XML (vnorené polia; zoznam => opakovaný prvok podľa $item). */
function jdp_xml_add( SimpleXMLElement $x, array $data ) {
	foreach ( $data as $k => $v ) {
		if ( null === $v || '' === $v ) {
			continue;
		}
		if ( is_array( $v ) && isset( $v['@list'] ) ) {
			$wrap = $x->addChild( $k );
			foreach ( $v['@list'] as $item ) {
				is_array( $item ) ? jdp_xml_add( $wrap->addChild( $v['@item'] ), $item ) : $wrap->addChild( $v['@item'], htmlspecialchars( (string) $item, ENT_XML1 ) );
			}
		} elseif ( is_array( $v ) ) {
			jdp_xml_add( $x->addChild( $k ), $v );
		} else {
			$x->addChild( $k, htmlspecialchars( is_bool( $v ) ? ( $v ? 'true' : 'false' ) : (string) $v, ENT_XML1 ) );
		}
	}
}

/**
 * Volanie metódy API. Vráti SimpleXMLElement <result> (alebo true pri prázdnom výsledku) alebo WP_Error.
 */
function jdp_api( $method, array $args = array(), $timeout = 30 ) {
	$pw = jdp_api_password();
	if ( '' === $pw ) {
		return new WP_Error( 'jdp_api', 'Chýba API heslo Packety (J-Doprava → Nastavenia).' );
	}
	$x = new SimpleXMLElement( '<' . $method . '/>' );
	$x->addChild( 'apiPassword', htmlspecialchars( $pw, ENT_XML1 ) );
	jdp_xml_add( $x, $args );
	$r = wp_remote_post( JDP_API_URL, array( 'timeout' => $timeout, 'headers' => array( 'Content-Type' => 'text/xml; charset=utf-8' ), 'body' => $x->asXML() ) );
	if ( is_wp_error( $r ) ) {
		return new WP_Error( 'jdp_api', 'Packeta neodpovedá: ' . $r->get_error_message() );
	}
	$body = (string) wp_remote_retrieve_body( $r );
	libxml_use_internal_errors( true );
	$res = simplexml_load_string( $body );
	if ( ! $res ) {
		return new WP_Error( 'jdp_api', 'Neplatná odpoveď Packety (HTTP ' . wp_remote_retrieve_response_code( $r ) . ').' );
	}
	if ( 'ok' !== (string) $res->status ) {
		$msg = trim( (string) $res->string );
		$det = array();
		if ( isset( $res->detail ) ) {
			foreach ( $res->detail->xpath( './/fault' ) ?: array() as $f ) {
				$det[] = trim( (string) ( $f->name ?? '' ) . ': ' . (string) ( $f->fault ?? $f ) );
			}
			if ( ! $det && '' !== trim( strip_tags( $res->detail->asXML() ) ) ) {
				$det[] = trim( preg_replace( '/\s+/', ' ', strip_tags( $res->detail->asXML() ) ) );
			}
		}
		return new WP_Error( 'jdp_api', trim( 'Packeta: ' . ( $msg ? $msg : (string) $res->fault ) . ( $det ? ' – ' . implode( '; ', $det ) : '' ) ) );
	}
	return isset( $res->result ) ? $res->result : true;
}

/** Overenie výdajného miesta (validácia z mapy na serveri). Vráti true alebo WP_Error. */
function jdp_validate_point( $point_id, $carrier_id = '', $carrier_point = '', $weight = 0 ) {
	$point = $carrier_id ? array( 'carrierId' => (string) $carrier_id, 'carrierPickupPointId' => (string) $carrier_point ) : array( 'id' => (string) $point_id );
	$body  = array( 'apiKey' => jdp_api_key(), 'point' => $point, 'options' => array_filter( array( 'country' => (string) jdp_opt( 'country' ), 'weight' => $weight > 0 ? round( $weight, 3 ) : null ) ) );
	$r     = wp_remote_post( 'https://widget.packeta.com/v6/pps/api/widget/v1/validate', array( 'timeout' => 15, 'headers' => array( 'Content-Type' => 'application/json', 'X-Language' => 'sk' ), 'body' => wp_json_encode( $body ) ) );
	if ( is_wp_error( $r ) ) {
		return true; // výpadok overenia nebráni objednávke – miesto sa skontroluje pri podaní zásielky
	}
	$j = json_decode( (string) wp_remote_retrieve_body( $r ), true );
	if ( 200 === (int) wp_remote_retrieve_response_code( $r ) && is_array( $j ) && empty( $j['isValid'] ) ) {
		$e = array_map( fn( $x ) => (string) ( $x['description'] ?? $x['code'] ?? '' ), (array) ( $j['errors'] ?? array() ) );
		return new WP_Error( 'jdp_point', 'Vybrané výdajné miesto Packeta nie je dostupné' . ( $e ? ': ' . implode( ' ', $e ) : '' ) . ' Vyberte, prosím, iné.' );
	}
	return true;
}

/** Stavy zásielky Packeta (kód => text). */
function jdp_status_codes() {
	return array(
		1   => 'Prijaté údaje',
		2   => 'Prijatá na pobočke',
		3   => 'Pripravená na odoslanie',
		4   => 'Na ceste',
		5   => 'Pripravená na výdaj',
		6   => 'Odovzdaná dopravcovi',
		7   => 'Doručená',
		9   => 'Vracia sa odosielateľovi',
		10  => 'Vrátená odosielateľovi',
		11  => 'Zrušená',
		12  => 'Zvezená, na ceste do depa',
		14  => 'Colné konanie',
		15  => 'Spätná zásielka prijatá',
		16  => 'Neúspešný pokus o doručenie',
		17  => 'Odmietnutá príjemcom',
		18  => 'Odmietnutá príjemcom',
		19  => 'Nedoručená – bez pobočky v okolí',
		20  => 'Uplynula úložná doba',
		21  => 'Zrušená, ale podaná – vráti sa',
		22  => 'Nespĺňa podmienky prepravy – vráti sa',
		23  => 'Neúspešné doručenie do Z-BOXu',
		24  => 'Posledný pokus o doručenie do Z-BOXu',
		25  => 'Prvý pokus o doručenie dopravcom',
		26  => 'Zásielka sa preveruje',
		27  => 'Preverenie ukončené',
		28  => 'Presmerovaná na obľúbené miesto',
		29  => 'Presmerovaná – obľúbené miesta plné',
		30  => 'Presmerovaná na iné miesto',
		31  => 'Pridané sledovacie číslo dopravcu',
		32  => 'Presmerovaná príjemcom',
		33  => 'Prázdna schránka Z-BOXu pri zvoze',
		999 => 'Neznámy stav',
	);
}

/** Konečný stav (sledovanie sa ukončí). */
function jdp_status_final( $code ) {
	return in_array( (int) $code, array( 7, 10, 11 ), true );
}

function jdp_tracking_url( $barcode ) {
	return 'https://tracking.packeta.com/sk/?id=' . rawurlencode( (string) $barcode );
}
