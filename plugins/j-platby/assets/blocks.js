/* J-Platby – platba kartou (Stripe Checkout) v blokovej pokladni. */
( function () {
	var reg = window.wc && window.wc.wcBlocksRegistry;
	var set = window.wc && window.wc.wcSettings;
	if ( ! reg || ! set ) {
		return;
	}
	var el = window.wp.element.createElement;
	var dec = window.wp.htmlEntities ? window.wp.htmlEntities.decodeEntities : function ( s ) { return s; };
	var d = set.getSetting( 'jpl_stripe_data', {} );
	var title = dec( d.title || 'Platba kartou' );
	function Content() {
		return el( 'div', null,
			d.description ? el( 'p', { style: { margin: 0 } }, dec( d.description ) ) : null,
			d.test ? el( 'p', { style: { color: '#b26200', margin: '8px 0 0' } }, el( 'strong', null, 'Testovací režim' ), ' – použite testovaciu kartu 4242 4242 4242 4242, ľubovoľný budúci dátum a CVC.' ) : null
		);
	}
	reg.registerPaymentMethod( {
		name: 'jpl_stripe',
		label: el( 'span', null, title ),
		ariaLabel: title,
		content: el( Content ),
		edit: el( Content ),
		canMakePayment: function () { return true; },
		placeOrderButtonLabel: dec( d.button || 'Zaplatiť kartou' ),
		supports: { features: d.supports || [ 'products' ] }
	} );
} )();
