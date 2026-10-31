<?php
/** Návody J-Kupónov v J-Pomocník (záložka Návody). */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'jupiter_guides', function ( $guides ) {
	$guides[] = array(
		'Zľavové kódy (kupóny)',
		array(
			'J-Kupóny → Nový kupón: kód sa vygeneruje sám (bez zameniteľných 0/O a 1/I). Tlačidlom Vygenerovať iný dostanete nový, alebo ho prepíšte vlastným (napr. LETO10). Predpona (napr. HED-) sa pridá pred generovaný kód.',
			'Zľava v percentách alebo v eurách z košíka, voliteľne aj doprava zadarmo (len doprava zadarmo: zľava 0). Platí do (vrátane), počet použití, na jedného zákazníka, minimálny nákup, e-mail, kategórie, produkty a kombinovanie s inými kupónmi.',
			'Séria: do poľa Počet kupónov zadajte napr. 50 a pomenujte sériu. Každý kupón dostane vlastný dlhší kód; sériu stiahnete ako CSV, vytlačíte ako kartičky alebo vypnete naraz.',
			'J-Kupóny → Kupóny: evidencia so stavom (Platný, Vyčerpaný, Po platnosti, Vypnutý), štatistika poskytnutých zliav za mesiac a najpoužívanejšie kódy. Detail kupónu ukáže všetky objednávky s kódom, poskytnutú zľavu a tržby; tlačidlo Duplikovať vytvorí nový kód s rovnakými podmienkami.',
			'Doprava zadarmo z kupónu vynuluje cenu každého spôsobu dopravy vrátane Packety (dá sa vypnúť v Nastaveniach). Zákazník zadá kód v košíku alebo v pokladni; na veľkých a malých písmenách nezáleží.',
		),
		'J-Kupóny',
	);
	return $guides;
} );
