<?php
/** Návody J-Kupónov v J-Pomocník (záložka Návody). */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'jupiter_guides', function ( $guides ) {
	$guides[] = array(
		'Zľavové kódy (kupóny)',
		array(
			'J-Kupóny → Nový kupón: kód sa vygeneruje sám (4 znaky, bez zameniteľných 0/O a 1/I). Tlačidlom Vygenerovať iný dostanete nový, alebo ho prepíšte vlastným (napr. LETO10).',
			'Zľava v percentách alebo v eurách z košíka, voliteľne aj doprava zadarmo. Platí do, počet použití (predvolene 1 – jednorazový kód), na jedného zákazníka, minimálny nákup a obmedzenie na e-mail.',
			'Viac kódov naraz: do poľa Počet kupónov zadajte napr. 20 – každý dostane vlastný kód s rovnakými podmienkami. Po vytvorení sa kódy zobrazia v poli na skopírovanie.',
			'J-Kupóny → Kupóny: evidencia všetkých kódov so stavom (Platný, Vyčerpaný, Po platnosti, Vypnutý), počtom použití a objednávkami, v ktorých bol kód použitý. Vypnúť a Zapnúť jedným kliknutím.',
			'Zákazník zadá kód v košíku alebo v pokladni; na veľkých a malých písmenách nezáleží. Kupóny sa nedajú kombinovať s inými kupónmi.',
		),
		'J-Kupóny',
	);
	return $guides;
} );
