<?php
/**
 * Template for dropoff points file.
 *
 * @package PostNLWooCommerce\Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $fields ) ) {
	return;
}

foreach ( $fields as $field ) {
	?>
	<tr class="postnl-co-tr postnl-co-tr-fist">
		<td colspan="2">
		<?php
			woocommerce_form_field( $field['id'], $field, $field['value'] );
		?>
		</td>
	</tr>
	<?php
}
