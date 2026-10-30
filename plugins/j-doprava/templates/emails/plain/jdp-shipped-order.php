<?php
/**
 * E-mail zákazníkovi (čistý text): Objednávka odoslaná (Packeta).
 *
 * @var WC_Order $order
 */
defined( 'ABSPATH' ) || exit;

$info = jdp_email_ship_info( $order );

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";
printf( "Dobrý deň %s,\n\n", esc_html( $order->get_billing_first_name() ) );
echo "vašu objednávku sme odovzdali Packete.\n\n";
if ( $info['url'] ) {
	echo 'Sledovanie zásielky ' . esc_html( $info['barcode'] ) . ': ' . esc_url_raw( $info['url'] ) . "\n";
}
if ( $info['where'] ) {
	echo esc_html( $info['where_label'] ) . ': ' . esc_html( $info['where'] ) . "\n";
}
echo "\n----------------------------------------\n\n";
do_action( 'woocommerce_email_order_details', $order, false, true, $email );
do_action( 'woocommerce_email_customer_details', $order, false, true, $email );
if ( $additional_content ) {
	echo "\n" . esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n";
}
echo "\n" . wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
