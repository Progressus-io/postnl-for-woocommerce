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
<div class="button--postnl-container">
	<button id="postnl-login-button" aria-label="<?php esc_html_e( 'Fill in with PostNL', 'postnl-for-woocommerce' ); ?>" href="#">
		<div id="postnl-login-button__outline"></div>
		<span id="postnl-login-button__text">
			<span id="postnl-login-button__first-text">
				<img src="<?php echo $postnl_logo_url; ?>" alt="<?php esc_attr_e( 'PostNL Logo', 'postnl-for-woocommerce' ); ?>" id="postnl-logo" />
			</span>
			<span id="postnl-login-button__second-text">
				<span class="vertical-align-inherit">
					<span class="vertical-align-inherit"><?php esc_html_e( 'Fill in with PostNL', 'postnl-for-woocommerce' ); ?></span>
				</span>
			</span>
		</span>
	</button>
	<div class="col-12 hidden-md">
		<p>
			<span class="vertical-align-inherit">
				<span class="vertical-align-inherit">
					<?php echo esc_html__( 'Your name and address are automatically filled in via your PostNL account. That saves you from having to fill in the form!', 'postnl-for-woocommerce' ); ?>
				</span>
			</span>
		</p>
	</div>
</div>