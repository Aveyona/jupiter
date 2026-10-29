<?php
/** Návody J-Emails (Jupiter Email) v J-Pomocník. Funkcie, ktoré ešte nie sú hotové, sú označené „(pripravuje sa)“. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'jupiter_guides', function ( $guides ) {
	$guides[] = array(
		'Nastavenie schránky',
		array(
			'J-Emails → Nastavenia: e-mailová adresa (napr. info@hedlajner.sk), meno odosielateľa a heslo schránky.',
			'Aliasy: adresy, ktoré chodia do tej istej schránky (napr. eshop@, redakcia@) – pri písaní si vyberiete, z ktorej adresy správa odíde.',
			'Pre WebSupport sú servery predvyplnené: prijímanie imap.websupport.sk, port 993, SSL; odosielanie smtp.websupport.sk, port 465, SSL.',
			'Heslo sa ukladá zašifrované kľúčom webu; každý web má vlastné nastavenia aj schránku.',
			'Tlačidlo Otestovať pripojenie overí prijímanie aj odosielanie a nájde priečinky Odoslané a Kôš. Archív, Spam a Odložené sa nájdu alebo vytvoria pri prvom použití.',
			'Poštu vidí len administrátor webu.',
		),
		'J-Emails',
	);
	$guides[] = array(
		'Jupiter Email – práca s poštou',
		array(
			'J-Emails → Pošta otvorí Jupiter Email: vľavo priečinky, hore hľadanie, v strede zoznam konverzácií.',
			'Konverzácia: odpovede aj vaše odoslané správy sú spolu; staršie správy sú zbalené, kliknutím ich rozbalíte.',
			'Pri prejdení myšou nad konverzáciou: Archivovať, Odstrániť, Označiť ako prečítané/neprečítané, Odložiť.',
			'Zaškrtnutím vyberiete viac konverzácií a použijete akcie hore; konverzácie sa dajú aj potiahnuť myšou do priečinka vľavo.',
			'Hviezdička a značka Dôležité – kliknutím v zozname; nájdete ich v priečinkoch S hviezdičkou a Dôležité.',
			'Odložiť: konverzácia zmizne z Doručených a vráti sa v zvolený čas ako neprečítaná.',
			'Archivovať: konverzácia odíde z Doručených do Archívu (nezmaže sa), nájdete ju aj hľadaním.',
			'Napísať otvorí okno vpravo dole; Odpovedať, Odpovedať všetkým a Preposlať sa píšu priamo v konverzácii. Rozpísané správy sa ukladajú ako Koncepty.',
			'Prílohy pridáte kancelárskou sponkou alebo presunutím súboru do okna; pri preposlaní sa pôvodné prílohy pridajú automaticky.',
			'Obrázky z internetu v správach sú skryté kvôli súkromiu – Zobraziť obrázky, prípadne Vždy zobrazovať od odosielateľa.',
			'Klávesové skratky: c napísať, / hľadať, j/k ďalšia/predošlá, e archivovať, # odstrániť, s hviezdička, r odpovedať, a všetkým, f preposlať, ? zoznam skratiek.',
			'Šablóny odpovedí a Mailchimp pre zákazníkov (pripravuje sa).',
		),
		'J-Emails',
	);
	return $guides;
} );
