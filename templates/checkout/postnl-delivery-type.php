<?php
/**
 * Template for delivery type file.
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
			woocommerce_form_field( $field['id'], $field, $field['value'] );
		?>
		</td>
	</tr>
	<?php
}
