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

$field = array_shift( $fields );
?>
<tr class="postnl-co-tr postnl-co-tr-container">
	<td colspan="2">
		<div id="postnl_checkout_option" class="postnl_checkout_container">
			<div class="postnl_checkout_tab_container">
				<ul class="postnl_checkout_tab_list">
					<?php foreach ( $tabs as $index => $field_tab ) { ?>
						<?php
							$is_checked      = ( $field['value'] === $field_tab['id'] ) || ( empty( $field['value'] ) && 0 === $index );
							$active_class    = ( $is_checked ) ? 'active' : '';
							$display_checked = ( $is_checked ) ? 'checked="checked"' : '';
						?>

						<li class="<?php echo esc_attr( $active_class ); ?>">
							<label for="<?php echo esc_attr( $field['name'] . '_' . $field_tab['id'] ); ?>" class="postnl_checkout_tab">
								<span><?php echo esc_html( $field_tab['name'] ); ?></span>
								<input 
									type="radio" 
									name="<?php echo esc_attr( $field['name'] ); ?>" 
									id="<?php echo esc_attr( $field['name'] . '_' . $field_tab['id'] ); ?>" 
									class="postnl_option" 
									value="<?php echo esc_attr( $field_tab['id'] ); ?>" 
									<?php echo esc_html( $display_checked ); ?>
									/>
							</label>
						</li>
					<?php } ?>

				</ul>
			</div>
			<div class="postnl_checkout_content_container">
				<?php do_action( 'postnl_checkout_content', $response, $post_data ); ?>
			</div>
		</div>
	</td>
</tr>
