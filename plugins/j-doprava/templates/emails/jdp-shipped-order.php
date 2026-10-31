<?php
/**
 * E-mail zákazníkovi: Objednávka odoslaná (Packeta). Šablónu možno prepísať v téme: woocommerce/emails/jdp-shipped-order.php.
 *
 * @var WC_Order $order
 */
defined( 'ABSPATH' ) || exit;

$info = jdp_email_ship_info( $order );

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p class="email-introduction"><?php printf( 'Dobrý deň %s,', esc_html( $order->get_billing_first_name() ) ); ?></p>
<p><?php echo 'pp' === $info['kind'] ? 'vašu objednávku sme odovzdali Packete. Keď bude pripravená na výdajnom mieste, Packeta vám pošle SMS a e-mail s kódom na vyzdvihnutie.' : 'vašu objednávku sme odovzdali Packete. Kuriér vás pred doručením kontaktuje.'; ?></p>

<?php if ( $info['url'] ) : ?>
<table role="presentation" border="0" cellpadding="0" cellspacing="0" class="jdp-track" style="margin: 8px 0 24px;">
	<tr>
		<td class="email-button" style="border-radius: 2px;"><a href="<?php echo esc_url( $info['url'] ); ?>" class="email-button-link" target="_blank">Sledovať zásielku &rarr;</a></td>
		<td style="padding-left: 16px; font-size: 13px; color: #6b6b6b;">Číslo zásielky <?php echo esc_html( $info['barcode'] ); ?></td>
	</tr>
</table>
<?php endif; ?>

<?php if ( $info['where'] ) : ?>
<p class="jdp-where"><strong><?php echo esc_html( $info['where_label'] ); ?>:</strong><br><?php echo esc_html( $info['where'] ); ?></p>
<?php endif; ?>

<?php
do_action( 'woocommerce_email_order_details', $order, false, false, $email );
do_action( 'woocommerce_email_order_meta', $order, false, false, $email );
do_action( 'woocommerce_email_customer_details', $order, false, false, $email );

if ( $additional_content ) {
	echo '<div class="email-additional-content">' . wp_kses_post( wpautop( wptexturize( $additional_content ) ) ) . '</div>';
}

do_action( 'woocommerce_email_footer', $email );
