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

use PostNLWooCommerce\Utils;

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
 * Function to generate pickup address.
 *
 * @param Array $address Pickup address value.
 */
function postnl_generate_pickup_address( $address ) {
	if ( empty( $address ) ) {
		return array();
	}

	$return = array();
	foreach ( $address as $key => $value ) {
		$excluded_info = array( 'company', 'country', 'postcode' );
		if ( in_array( $key, $excluded_info, true ) ) {
			continue;
		}

		$return[ $key ] = esc_html( $value );
	}

	return $return;
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

/**
 * Function to display dropoff point desc.
 *
 * @param Array $data Data dropoff point value.
 */
function postnl_display_dropoff_desc( $data ) {
	if ( empty( $data['dropoff_options'][0]['show_desc'] ) || true !== $data['dropoff_options'][0]['show_desc'] ) {
		return;
	}

	?>
	<div class="postnl_content_desc">
		<?php
			// translators: %1$s is <strong> opener and %2$s is <strong> closer.
			echo sprintf( esc_html__( 'Receive shipment at home? Continue %1$swithout%2$s selecting a Pick-up Point.', 'postnl-for-woocommerce' ), '<strong>', '</strong>' );
		?>
	</div>
	<?php
}
?>
<div class="postnl_content" id="postnl_dropoff_points_content">
	<?php postnl_display_dropoff_desc( $data ); ?>
	<ul class="postnl_dropoff_points_list postnl_list">
		<?php foreach ( $data['dropoff_options'] as $point ) { ?>
			<?php
			$value      = sanitize_title( $point['partner_id'] . '-' . $point['loc_code'] );
			$radio_id   = sanitize_title( $point['partner_id'] . '-' . $point['loc_code'] );

			$address    = implode( ' ', array_values( postnl_generate_pickup_address( $point['address'] ) ) );
			$is_checked = ( $value === $data['value'] ) ? 'checked="checked"' : '';

			$point_key  = $point;
			?>
		<li>
			<div class="list_title"><span class="company"><?php echo esc_html( $point['address']['company'] ); ?></span><span class="distance"><?php echo esc_html( Utils::maybe_convert_km( $point['distance'] ) ); ?></span></div>
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
						<i><?php esc_html_e( 'Vanaf', 'postnl-for-woocommerce' ); ?> <?php echo esc_html( $point['time'] ); ?><br /><?php echo esc_html( $point['date'] ); ?></i>
						<span>
							<?php echo esc_html( $address ); ?>
						</span>
					</label>
				</li>
			</ul>
		</li>
		<?php } ?>
	</ul>
	<?php postnl_generate_hidden_input( $point_key, $data['field_name'] ); ?>
</div>
