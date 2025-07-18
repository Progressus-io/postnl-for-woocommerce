<?php
/**
 * Template for fill in with postnl button.
 *
 * @package PostNLWooCommerce\Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="postnl-login-button__container">
	<button id="postnl-login-button" aria-label="<?php esc_html_e( 'Fill in with PostNL', 'postnl-for-woocommerce' ); ?>" href="#">
		<img src="<?php echo $postnl_logo_url; ?>" alt="<?php esc_attr_e( 'PostNL Logo', 'postnl-for-woocommerce' ); ?>" id="postnl-logo" />
		<span id="postnl-login-button__text">
			<?php esc_html_e( 'Fill in with PostNL', 'postnl-for-woocommerce' ); ?>
		</span>
	</button>
	<p class="postnl-login-button__description">
		<?php echo esc_html__( 'Your name and address are automatically filled in via your PostNL account. That saves you from having to fill in the form!', 'postnl-for-woocommerce' ); ?>
	</p>
</div>