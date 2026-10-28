<?php
/** Návody J-Emails v J-Pomocník. Funkcie, ktoré ešte nie sú hotové, sú označené „(pripravuje sa)“. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'jupiter_guides', function ( $guides ) {
	$guides[] = array(
		'Nastavenie schránky',
		array(
			'J-Emails → Nastavenia: e-mailová adresa (napr. info@hedlajner.sk), meno odosielateľa a heslo schránky.',
			'Pre WebSupport sú servery predvyplnené: prijímanie imap.websupport.sk, port 993, SSL; odosielanie smtp.websupport.sk, port 465, SSL.',
			'Heslo sa ukladá zašifrované kľúčom webu; každý web má vlastné nastavenia aj schránku.',
			'Tlačidlo Otestovať pripojenie overí prijímanie aj odosielanie a nájde priečinky Odoslané a Kôš.',
			'Poštu vidí len administrátor webu.',
		),
		'J-Emails',
	);
	$guides[] = array(
		'Práca s poštou',
		array(
			'Doručená pošta, Odoslané a Kôš sú samostatné stránky v menu J-Emails; počet neprečítaných správ je pri položke v menu (obnovuje sa každých 5 minút).',
			'Kliknutím na správu sa otvorí a označí ako prečítaná; tlačidlom ju označíte znova ako neprečítanú.',
			'Odpovedať, Odpovedať všetkým a Preposlať otvoria stránku Napísať s vyplnenými údajmi a citovaným textom.',
			'Odoslaná správa sa uloží aj do priečinka Odoslané v schránke, takže ju uvidíte aj v inom programe (mobil, webmail).',
			'Obrázky z internetu v HTML správach sú skryté kvôli súkromiu; zobrazíte ich tlačidlom Zobraziť obrázky.',
			'Do koša – správa sa presunie do Koša schránky, odtiaľ ju môžete vrátiť do Doručenej pošty.',
			'Adresy eshop@ a redakcia@ môžu byť aliasy schránky info@ – pošta z nich chodí do tej istej Doručenej pošty.',
			'Odpovede vo formáte HTML a šablóny odpovedí (pripravuje sa).',
			'Mailchimp: zoznam odberateľov zo zákazníkov e-shopu so súhlasom (pripravuje sa).',
		),
		'J-Emails',
	);
	return $guides;
} );
