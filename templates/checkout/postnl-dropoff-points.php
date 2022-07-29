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
	<tr class="dhl-co-tr dhl-co-tr-fist">
		<td><?php echo esc_html( $field['label'] ); ?></td>
		<td>
		<?php
			esc_html_e( 'Dropoff Points', 'postnl-for-woocommerce' );
		?>
		</td>
	</tr>
	<?php
}
