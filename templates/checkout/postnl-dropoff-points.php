<?php
/**
 * Template for dropoff points file.
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
<div class="postnl_content" id="postnl_dropoff_points_content">
	<ul class="postnl_dropoff_points_list postnl_list">
		<?php foreach ( $data as $point ) { ?>
			<?php
			$value    = $point['partner_id'] . '-' . $point['loc_code'];
			$radio_id = sanitize_title( $point['partner_id'] . '-' . $point['loc_code'] );
			?>
		<li>
			<div class="list_title"><span><?php echo esc_html( $point['company'] . ' ' . $point['distance'] ); ?></span></div>
			<ul class="postnl_sub_list">
				<li
					data-address="<?php echo esc_attr( $point['address'] ); ?>"
					data-company="<?php echo esc_attr( $point['company'] ); ?>"
					data-distance="<?php echo esc_attr( $point['distance'] ); ?>"
					data-partner_id="<?php echo esc_attr( $point['partner_id'] ); ?>"
					data-date="<?php echo esc_attr( $point['date'] ); ?>"
					data-time="<?php echo esc_attr( $point['time'] ); ?>"
				>
					<label class="postnl_sub_radio_label" for="postnl_dropoff_points_<?php echo esc_attr( $radio_id ); ?>">
						<input 
							type="radio" 
							id="postnl_dropoff_points_<?php echo esc_attr( $radio_id ); ?>" 
							name="postnl_dropoff_points" 
							class="postnl_sub_radio" 
							value="<?php echo esc_attr( $value ); ?>" 
						/>
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
	<input type="hidden" name="postnl_dropoff_points_company" id="postnl_dropoff_points_company" value="" />
	<input type="hidden" name="postnl_dropoff_points_distance" id="postnl_dropoff_points_distance" value="" />
	<input type="hidden" name="postnl_dropoff_points_address" id="postnl_dropoff_points_address" value="" />
	<input type="hidden" name="postnl_dropoff_points_partner_id" id="postnl_dropoff_points_partner_id" value="" />
	<input type="hidden" name="postnl_dropoff_points_date" id="postnl_dropoff_points_date" value="" />
	<input type="hidden" name="postnl_dropoff_points_time" id="postnl_dropoff_points_time" value="" />
</div>
