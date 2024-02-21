<?php
/**
 * Template for delivery day file.
 *
 * @package PostNLWooCommerce\Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $data['delivery_options'] ) ) {
	return;
}

?>
<div class="postnl_content" id="postnl_delivery_day_content">
	<ul class="postnl_delivery_day_list postnl_list">
		<?php foreach ( $data['delivery_options'] as $index => $delivery ) { ?>
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
							$value      = sanitize_title( $delivery['date'] . '_' . $option['from'] . '-' . $option['to'] . '_' . $option['price'] );
							$is_charged = ( empty( $option['price'] ) ) ? '' : '+' . wc_price( $option['price'] );
							$is_checked = ( $value === $data['value'] || 0 === $index ) ? 'checked="checked"' : '';
							$is_active  = ( $value === $data['value'] ) ? 'active' : '';
							$delivery_time = '';
							if ( 'Evening' === $option['type'] ) {
								$delivery_time = esc_html__( 'Evening', 'postnl-for-woocommerce' );
							} elseif ( '08:00-12:00' === $option['type'] ) {
								$delivery_time = esc_html__( 'Morning', 'postnl-for-woocommerce' );
							}
						?>
						<li 
							class="<?php echo esc_attr( $option['type'] . ' ' . $is_active ); ?>"
							data-date="<?php echo esc_attr( $delivery['date'] ); ?>"
							data-from="<?php echo esc_attr( $option['from'] ); ?>"
							data-to="<?php echo esc_attr( $option['to'] ); ?>"
							data-price="<?php echo esc_attr( $option['price'] ); ?>"
							data-type="<?php echo esc_attr( $option['type'] ); ?>"
						>
							<label class="postnl_sub_radio_label" for="<?php echo esc_attr( $data['field_name'] ); ?>_<?php echo esc_attr( $value ); ?>">
								<input 
									type="radio" 
									id="<?php echo esc_attr( $data['field_name'] ); ?>_<?php echo esc_attr( $value ); ?>" 
									name="<?php echo esc_attr( $data['field_name'] ); ?>" 
									class="postnl_sub_radio" 
									value="<?php echo esc_attr( $value ); ?>"
									<?php echo esc_html( $is_checked ); ?>
								/>
								<i><?php echo wp_kses_post( $is_charged ); ?></i>
								<i><?php echo esc_html( $delivery_time ); ?></i>
								<span><?php echo esc_html( $option['from'] . ' - ' . $option['to'] ); ?></span>
							</label>
						</li>
					<?php } ?>
				</ul>
			</li>
		<?php } ?>
	</ul>
	<input type="hidden" name="<?php echo esc_attr( $data['field_name'] ); ?>_date" id="<?php echo esc_attr( $data['field_name'] ); ?>_date" value="" />
	<input type="hidden" name="<?php echo esc_attr( $data['field_name'] ); ?>_from" id="<?php echo esc_attr( $data['field_name'] ); ?>_from" value="" />
	<input type="hidden" name="<?php echo esc_attr( $data['field_name'] ); ?>_to" id="<?php echo esc_attr( $data['field_name'] ); ?>_to" value="" />
	<input type="hidden" name="<?php echo esc_attr( $data['field_name'] ); ?>_price" id="<?php echo esc_attr( $data['field_name'] ); ?>_price" value="" />
	<input type="hidden" name="<?php echo esc_attr( $data['field_name'] ); ?>_type" id="<?php echo esc_attr( $data['field_name'] ); ?>_type" value="" />
</div>
