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
				<?php do_action( 'postnl_checkout_content', $response, $post_data ); ?>
			</div>
		</div>
	</td>
</tr>
