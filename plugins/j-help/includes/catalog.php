<?php
/**
 * Katalóg doplnkov Jupiter. Ďalší J-doplnok sa doňho pridá filtrom `jupiter_plugins`:
 *
 *     add_filter( 'jupiter_plugins', function ( $p ) {
 *         $p['j-invoices'] = array( 'name' => 'J-Invoices', 'icon' => 'dashicons-media-spreadsheet', 'what' => '…', 'status' => 'stable' );
 *         return $p;
 *     } );
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Stavy vývoja doplnku. */
function jhelp_statuses() {
	return array(
		'stable'  => 'hotový',
		'dev'     => 'vo vývoji',
		'planned' => 'plánovaný',
		'idea'    => 'nápad',
	);
}

/** Všetky doplnky rodiny Jupiter: slug => názov, ikona, popis, stav. */
function jhelp_plugins() {
	$plugins = array(
		'j-help'     => array(
			'name'   => 'J-Help',
			'icon'   => 'dashicons-sos',
			'what'   => 'Táto dokumentácia: prehľad doplnkov Jupiter, návody a plán vývoja. V menu je prvá z doplnkov Jupiter, hneď pod Marketingom.',
			'status' => 'stable',
		),
		'j-invoices' => array(
			'name'   => 'J-Invoices',
			'icon'   => 'dashicons-media-spreadsheet',
			'what'   => 'Faktúry: vydané faktúry z objednávok WooCommerce (číselný rad, PDF, QR kód PAY by square, e-mail zákazníkovi), ručné faktúry a dobropisy, prijaté faktúry s prílohami a splatnosťou, export pre účtovníka.',
			'status' => 'planned',
		),
		'j-sklad'    => array(
			'name'   => 'J-Sklad',
			'icon'   => 'dashicons-archive',
			'what'   => 'Sklad: príjemky, výdajky a inventúra napojené na produkty WooCommerce.',
			'status' => 'idea',
		),
	);
	return apply_filters( 'jupiter_plugins', $plugins );
}

/**
 * Plán vývoja Jupitera – etapy v poradí, v akom vznikajú.
 * Stav: done (hotovo), now (prebieha), next (plánované).
 */
function jhelp_roadmap() {
	return apply_filters( 'jupiter_roadmap', array(
		array(
			'title'  => 'Základ Jupitera a J-Help',
			'state'  => 'done',
			'points' => array(
				'Verejný repozitár na GitHube (licencia GPL-2.0-or-later), automatická kontrola kódu a vydávanie ZIP doplnkov.',
				'J-Help: prehľad doplnkov, návody a plán vývoja priamo v administrácii.',
				'Doplnky Jupiter majú v menu vlastné miesto hneď pod položkou Marketing.',
			),
		),
		array(
			'title'  => 'J-Invoices – vydané faktúry z objednávok',
			'state'  => 'now',
			'points' => array(
				'Faktúra vznikne z objednávky: pri prevode po zaplatení, pri dobierke a osobnom odbere po vybavení.',
				'Ročný číselný rad bez medzier, dátum vystavenia, dodania a splatnosti, variabilný symbol, text pre neplatiteľa DPH.',
				'PDF s logom a QR kódom PAY by square, e-mail zákazníkovi a faktúra aj v jeho účte.',
			),
		),
		array(
			'title'  => 'J-Invoices – ručné faktúry a dobropisy',
			'state'  => 'next',
			'points' => array(
				'Faktúra pre odberateľa mimo e-shopu s vlastnými položkami.',
				'Dobropis pri vrátení tovaru (odstúpenie od zmluvy) a storno faktúry.',
			),
		),
		array(
			'title'  => 'J-Invoices – prijaté faktúry',
			'state'  => 'next',
			'points' => array(
				'Evidencia dodávateľských faktúr s prílohou PDF alebo fotkou.',
				'Splatnosť a úhrady, upozornenie na faktúry po splatnosti.',
			),
		),
		array(
			'title'  => 'Prehľady a export pre účtovníka',
			'state'  => 'next',
			'points' => array(
				'Mesačný prehľad vydaných a prijatých faktúr.',
				'Export za obdobie: tabuľka (XLSX/CSV) a ZIP so všetkými PDF.',
			),
		),
		array(
			'title'  => 'J-Sklad a ďalšie doplnky',
			'state'  => 'next',
			'points' => array(
				'Príjemky, výdajky a inventúra.',
				'Vyčítanie údajov z prijatých faktúr pomocou umelej inteligencie.',
			),
		),
	) );
}
