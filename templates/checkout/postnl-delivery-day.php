<?php
/**
 * Template for delivery day file.
 *
 * @package PostNLWooCommerce\Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $data ) ) {
	return;
}
?>
<div class="postnl_content" id="postnl_delivery_day_content">
	<ul class="postnl_delivery_day_list postnl_list">
		<?php foreach ( $data as $delivery ) { ?>
			<?php
			if ( empty( $delivery['options'] ) ) {
				continue;
			}
			?>
			<li>
				<div class="list_title"><span><?php echo esc_html( $delivery['date'] . ' ' . $delivery['day'] ); ?></span></div>
				<ul class="postnl_sub_list">
					<?php foreach ( $delivery['options'] as $option ) { ?>
						<?php
							$value      = $delivery['date'] . '-' . $option['type'];
							$is_charged = ( empty( $option['price'] ) ) ? esc_html__( 'No charge', 'postnl-for-woocommerce' ) : wc_price( $option['price'] );
						?>
						<li class="<?php echo esc_attr( $option['type'] ); ?>">
							<label class="postnl_sub_radio_label" for="postnl_delivery_day_<?php echo esc_attr( $value ); ?>">
								<input type="radio" id="postnl_delivery_day_<?php echo esc_attr( $value ); ?>" name="postnl_delivery_day" class="postnl_sub_radio" value="<?php echo esc_attr( $value ); ?>" />
								<i><?php echo wp_kses_post( $is_charged ); ?></i>
								<span><?php echo esc_html( $option['from'] . ' - ' . $option['to'] ); ?></span>
							</label>
						</li>
					<?php } ?>
				</ul>
			</li>
		<?php } ?>
	</ul>
</div>
