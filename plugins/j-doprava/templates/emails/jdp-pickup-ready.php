<?php
/**
 * E-mail zákazníkovi: Objednávka je pripravená na osobný odber. Šablónu možno prepísať v téme: woocommerce/emails/jdp-pickup-ready.php.
 *
 * @var WC_Order $order
 */
defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p class="email-introduction"><?php printf( 'Dobrý deň %s,', esc_html( $order->get_billing_first_name() ) ); ?></p>
<p>vaša objednávka č. <?php echo esc_html( $order->get_order_number() ); ?> je pripravená a môžete si ju prevziať osobne<?php echo $order->get_shipping_method() ? ' (' . esc_html( $order->get_shipping_method() ) . ')' : ''; ?>.</p>

<?php
do_action( 'woocommerce_email_order_details', $order, false, false, $email );
do_action( 'woocommerce_email_order_meta', $order, false, false, $email );

if ( $additional_content ) {
	echo '<div class="email-additional-content">' . wp_kses_post( wpautop( wptexturize( $additional_content ) ) ) . '</div>';
}

do_action( 'woocommerce_email_footer', $email );
