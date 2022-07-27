<?php
/**
 * Template for delivery day file.
 *
 * @package PostNLWooCommerce\Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$logo_url = PR_DHL_PLUGIN_DIR_URL . '/assets/img/dhl-official.png';
?>

<tr class="dhl-co-tr dhl-co-tr-fist">
	<td><?php esc_html_e( 'Delivery Day', 'postnl-for-woocommerce' ); ?></td>
	<td>
		<?php
		woocommerce_form_field(
			'postnl_delivery_day',
			array(
				'type'    => 'select',
				'class'   => 'postnl-checkout-field',
				'label'   => esc_html__( 'Delivery Day', 'postnl-for-woocommerce' ),
				'options' => array(
					''         => esc_html__( '- Choose Delivery Day -', 'postnl-for-woocommerce' ),
					'standard' => esc_html__( 'Standard', 'postnl-for-woocommerce' ),
					'evening'  => esc_html__( 'Evening', 'postnl-for-woocommerce' ),
				),
			),
			''
		);
		?>
	</td>
</tr>
