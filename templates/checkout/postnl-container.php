<?php
/**
 * Template for PostNL option in frontend checkout page.
 *
 * @package PostNLWooCommerce\Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $tabs ) ) {
	return;
}
?>
<tr class="postnl-co-tr postnl-co-tr-container">
	<td colspan="2">
		<div id="postnl_checkout_option" class="postnl_checkout_container">
			<div class="postnl_checkout_tab_container">
				<ul class="postnl_checkout_tab_list">
					<?php foreach ( $tabs as $index => $field_tab ) { ?>
						<?php $active_class = ( 0 === $index ) ? 'active' : ''; ?>

						<li class="<?php echo esc_attr( $active_class ); ?>">
							<label for="postnl_<?php echo esc_attr( $field_tab['id'] ); ?>" class="postnl_checkout_tab">
								<span><?php echo esc_html( $field_tab['name'] ); ?></span>
								<i><?php echo esc_html( $field_tab['price'] ); ?></i>
								<input type="radio" name="postnl_option" id="postnl_<?php echo esc_attr( $field_tab['id'] ); ?>" class="postnl_option" value="<?php echo esc_attr( $field_tab['id'] ); ?>" />
							</label>
						</li>
					<?php } ?>

				</ul>
			</div>
			<div class="postnl_checkout_content_container">
				<?php if ( ! empty( $deliveries ) ) { ?>
				<div class="postnl_content active" id="postnl_delivery_day_content">
					<ul class="postnl_delivery_day_list postnl_list">
						<?php foreach ( $deliveries as $delivery ) { ?>
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
											$value = $delivery['date'] . '-' . $option['type'];
											$is_charged = ( 'Evening' !== $option['type'] ) ? esc_html__( 'No charge', 'postnl-for-woocommerce' ) : '$ 5';
										?>
										<li class="<?php echo esc_attr( $option['type'] ); ?>">
											<label class="postnl_sub_radio_label" for="postnl_delivery_day_<?php echo esc_attr( $value ); ?>">
												<input type="radio" id="postnl_delivery_day_<?php echo esc_attr( $value ); ?>" name="postnl_delivery_day" class="postnl_sub_radio" value="<?php echo esc_attr( $value ); ?>" />
												<i><?php echo esc_html( $is_charged ); ?></i>
												<span><?php echo esc_html( $option['from'] . ' - ' . $option['to'] ); ?></span>
											</label>
										</li>
									<?php } ?>
								</ul>
							</li>
						<?php } ?>
					</ul>
				</div>
				<?php } ?>

				<?php if ( ! empty( $dropoff_points ) ) { ?>
				<div class="postnl_content" id="postnl_dropoff_points_content">
					<ul class="postnl_dropoff_points_list postnl_list">
						<?php foreach ( $dropoff_points as $point ) { ?>
							<?php
							$value    = $point['partner_id'] . '-' . $point['loc_code'];
							$radio_id = sanitize_title( $point['partner_id'] . '-' . $point['loc_code'] );
							?>
						<li>
							<div class="list_title"><span><?php echo esc_html( $point['company'] . ' ' . $point['distance'] ); ?></span></div>
							<ul class="postnl_sub_list">
								<li>
									<label class="postnl_sub_radio_label" for="postnl_dropoff_points_<?php echo esc_attr( $radio_id ); ?>">
										<input type="radio" id="postnl_dropoff_points_<?php echo esc_attr( $radio_id ); ?>" name="postnl_dropoff_points" class="postnl_sub_radio" value="<?php echo esc_attr( $value ); ?>" />
										<i>Vanaf <?php echo esc_html( $point['time'] ); ?><br /><?php echo esc_html( $point['date'] ); ?></i>
										<span>
											<?php echo esc_html( $point['address'] ); ?><br />
											<?php echo esc_html( $point['partner_id'] ); ?>
										</span>
									</label>
								</li>
							</ul>
						</li>
						<?php } ?>
					</ul>
				</div>
				<?php } ?>
			</div>
		</div>
	</td>
</tr>
