<?php
/**
 * Template for dropoff points file.
 *
 * @package PostNLWooCommerce\Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $data['dropoff_options'] ) ) {
	return;
}

/**
 * Function to generate data html in <li>.
 *
 * @param Array $dropoff_point Dropoff point value.
 */
function postnl_generate_data_li( $dropoff_point ) {

	foreach ( $dropoff_point as $key => $value ) {
		if ( is_array( $value ) ) {

			foreach ( $value as $k => $val ) {
				?>
				data-<?php echo esc_html( $key . '_' . $k ); ?>="<?php echo esc_attr( $val ); ?>"
				<?php
			}
			continue;
		}

		?>
		data-<?php echo esc_html( $key ); ?>="<?php echo esc_attr( $value ); ?>"
		<?php
	}
}

/**
 * Function to generate hidden input.
 *
 * @param Array  $dropoff_point Dropoff point value.
 * @param String $field_name Field name.
 */
function postnl_generate_hidden_input( $dropoff_point, $field_name ) {
	foreach ( $dropoff_point as $key => $value ) {
		if ( is_array( $value ) ) {

			foreach ( $value as $k => $val ) {
				?>
				<input type="hidden" name="<?php echo esc_attr( $field_name . '_' . $key . '_' . $k ); ?>" id="<?php echo esc_attr( $field_name . '_' . $key . '_' . $k ); ?>" value="" />
				<?php
			}
			continue;
		}

		?>
		<input type="hidden" name="<?php echo esc_attr( $field_name . '_' . $key ); ?>" id="<?php echo esc_attr( $field_name . '_' . $key ); ?>" value="" />
		<?php
	}
}
?>
<div class="postnl_content" id="postnl_dropoff_points_content">
	<ul class="postnl_dropoff_points_list postnl_list">
		<?php foreach ( $data['dropoff_options'] as $point ) { ?>
			<?php
			$value      = sanitize_title( $point['partner_id'] . '-' . $point['loc_code'] );
			$radio_id   = sanitize_title( $point['partner_id'] . '-' . $point['loc_code'] );
			$address    = implode( ', ', array_values( $point['address'] ) );
			$is_checked = ( $value === $data['value'] ) ? 'checked="checked"' : '';

			$point_key  = $point;
			?>
		<li>
			<div class="list_title"><span><?php echo esc_html( $point['company'] . ' ' . $point['distance'] ); ?></span></div>
			<ul class="postnl_sub_list">
				<li
					<?php postnl_generate_data_li( $point ); ?>
				>
					<label class="postnl_sub_radio_label" for="<?php echo esc_attr( $data['field_name'] ); ?>_<?php echo esc_attr( $radio_id ); ?>">
						<input 
							type="radio" 
							id="<?php echo esc_attr( $data['field_name'] ); ?>_<?php echo esc_attr( $radio_id ); ?>" 
							name="<?php echo esc_attr( $data['field_name'] ); ?>" 
							class="postnl_sub_radio" 
							value="<?php echo esc_attr( $value ); ?>"
							<?php echo esc_html( $is_checked ); ?>
						/>
						<i>Vanaf <?php echo esc_html( $point['time'] ); ?><br /><?php echo esc_html( $point['date'] ); ?></i>
						<span>
							<?php echo esc_html( $address ); ?><br />
							<?php echo esc_html( $point['partner_id'] ); ?>
						</span>
					</label>
				</li>
			</ul>
		</li>
		<?php } ?>
	</ul>
	<?php postnl_generate_hidden_input( $point_key, $data['field_name'] ); ?>
</div>
