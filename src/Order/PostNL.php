<?php
/**
 * Class Order\PostNL file.
 *
 * @package Progressus\PostNLWooCommerce\Order
 */

namespace Progressus\PostNLWooCommerce\Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PostNL
 *
 * @package Progressus\PostNLWooCommerce\Order
 */
class PostNL extends AbstractOrder {
	/**
	 * Additional fields of the meta box for child class.
	 */
	public function additional_meta_box() {
		woocommerce_wp_hidden_input(
			array(
				'id'    => 'postnl_label_nonce',
				'value' => wp_create_nonce( 'create-postnl-label' ),
			)
		);
		?>
		<div id="shipment-dhl-label-form">
			<div class="shipment-postnl-row-container shipment-postnl-row-delivery-type">
			<?php
				woocommerce_wp_select(
					array(
						'id'          => 'postnl_delivery_type',
						'label'       => __( 'Delivery Type:', 'postnl-for-woocommerce' ),
						'description' => '',
						'value'       => '',
						'options'     => array(
							'standard' => esc_html__( 'Standard', 'postnl-for-woocommerce' ),
							'evening'  => esc_html__( 'Evening', 'postnl-for-woocommerce' ),
						),
					)
				);
			?>
			</div>

			<div class="shipment-postnl-row-container shipment-postnl-row-insured-shipping">
			<?php
				woocommerce_wp_checkbox(
					array(
						'id'          => 'postnl_insured_shipping',
						'label'       => __( 'Insured Shipping: ', 'dhl-for-woocommerce' ),
						'placeholder' => '',
						'description' => '',
						'value'       => '',
					)
				);
			?>
			</div>

			<div class="shipment-postnl-row-container shipment-postnl-row-return-no-answer">
			<?php
				woocommerce_wp_checkbox(
					array(
						'id'          => 'postnl_return_no_answer',
						'label'       => __( 'Return if no answer: ', 'dhl-for-woocommerce' ),
						'placeholder' => '',
						'description' => '',
						'value'       => '',
					)
				);
			?>
			</div>

			<div class="shipment-postnl-row-container shipment-postnl-row-signature-on-delivery">
			<?php
				woocommerce_wp_checkbox(
					array(
						'id'          => 'postnl_signature_on_delivery',
						'label'       => __( 'Signature on Delivery: ', 'dhl-for-woocommerce' ),
						'placeholder' => '',
						'description' => '',
						'value'       => '',
					)
				);
			?>
			</div>

			<div class="shipment-postnl-row-container shipment-postnl-row-only-home-address">
			<?php
				woocommerce_wp_checkbox(
					array(
						'id'          => 'postnl_only_home_address',
						'label'       => __( 'Only Home Address: ', 'dhl-for-woocommerce' ),
						'placeholder' => '',
						'description' => '',
						'value'       => '',
					)
				);
			?>
			</div>

			<div class="shipment-postnl-row-container shipment-postnl-row-num-labels">
			<?php
				woocommerce_wp_text_input(
					array(
						'id'          => 'postnl_num_labels',
						'label'       => __( 'Number of Labels: ', 'dhl-for-woocommerce' ),
						'placeholder' => '',
						'description' => '',
						'value'       => '',
					)
				);
			?>
			</div>

			<div class="shipment-postnl-row-container shipment-postnl-create-return-label">
			<?php
				woocommerce_wp_checkbox(
					array(
						'id'          => 'postnl_create_return_label',
						'label'       => __( 'Create Return Label: ', 'dhl-for-woocommerce' ),
						'placeholder' => '',
						'description' => '',
						'value'       => '',
					)
				);
			?>
			</div>
		</div>
		<?php
	}
}
