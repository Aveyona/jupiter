<?php
/**
 * E-mail zákazníkovi (čistý text): Objednávka je pripravená na osobný odber.
 *
 * @var WC_Order $order
 */
defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";
printf( "Dobrý deň %s,\n\n", esc_html( $order->get_billing_first_name() ) );
echo 'vaša objednávka č. ' . esc_html( $order->get_order_number() ) . ' je pripravená a môžete si ju prevziať osobne' . ( $order->get_shipping_method() ? ' (' . esc_html( $order->get_shipping_method() ) . ')' : '' ) . ".\n";
echo "\n----------------------------------------\n\n";
do_action( 'woocommerce_email_order_details', $order, false, true, $email );
if ( $additional_content ) {
	echo "\n" . esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n";
}
echo "\n" . wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
