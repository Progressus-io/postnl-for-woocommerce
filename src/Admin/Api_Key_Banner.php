<?php
/**
 * Class Admin\Api_Key_Banner file.
 *
 * Renders the admin banner that asks merchants to enter the new PostNL API
 * key ahead of the upcoming API migration. The banner is shown on the PostNL
 * settings screen and on the WooCommerce orders list. Two dismissal modes
 * are supported: "remind me later" (cleared on the next login) and a
 * permanent dismiss stored per user.
 *
 * @package PostNLWooCommerce\Admin
 */

namespace PostNLWooCommerce\Admin;

use PostNLWooCommerce\Shipping_Method\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Api_Key_Banner
 */
class Api_Key_Banner {

	const META_DISMISSED     = 'postnl_new_api_key_banner_dismissed';
	const META_REMIND_LATER  = 'postnl_new_api_key_banner_remind_later';
	const NONCE_ACTION       = 'postnl_new_api_key_banner';
	const AJAX_ACTION        = 'postnl_dismiss_new_api_key_banner';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'maybe_render' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_ajax_dismiss' ) );
		add_action( 'wp_login', array( $this, 'clear_remind_later_on_login' ), 10, 2 );
	}

	/**
	 * Is the current screen one where the banner should appear?
	 */
	protected function is_target_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( empty( $screen ) ) {
			return false;
		}

		if ( 'woocommerce_page_wc-settings' === $screen->id
			&& isset( $_GET['section'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			&& POSTNL_SETTINGS_ID === sanitize_text_field( wp_unslash( $_GET['section'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		) {
			return true;
		}

		if ( 'edit-shop_order' === $screen->id ) {
			return true;
		}

		if ( 'woocommerce_page_wc-orders' === $screen->id ) {
			return true;
		}

		return false;
	}

	/**
	 * Should the banner be visible right now for this user?
	 */
	protected function should_show() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}

		if ( ! $this->is_target_screen() ) {
			return false;
		}

		$settings = Settings::get_instance();
		$new_key  = $settings->get_api_key_new();

		// If a valid new key has already been entered we have what we need.
		if ( '' !== $new_key && $settings->is_api_key_new_validated() ) {
			return false;
		}

		$user_id = get_current_user_id();
		if ( get_user_meta( $user_id, self::META_DISMISSED, true ) ) {
			return false;
		}

		if ( get_user_meta( $user_id, self::META_REMIND_LATER, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Render the banner markup.
	 */
	public function maybe_render() {
		if ( ! $this->should_show() ) {
			return;
		}

		$nonce = wp_create_nonce( self::NONCE_ACTION );
		?>
		<div class="notice notice-warning postnl-new-api-key-banner" data-nonce="<?php echo esc_attr( $nonce ); ?>">
			<p><strong><?php esc_html_e( 'PostNL: New API Key required', 'postnl-for-woocommerce' ); ?></strong></p>
			<p><?php echo esc_html( $this->get_message() ); ?></p>
			<p>
				<button type="button" class="button button-secondary postnl-new-api-key-remind">
					<?php esc_html_e( 'Remind me later', 'postnl-for-woocommerce' ); ?>
				</button>
				<button type="button" class="button-link postnl-new-api-key-dismiss">
					<?php esc_html_e( 'Dismiss permanently', 'postnl-for-woocommerce' ); ?>
				</button>
			</p>
		</div>
		<?php
	}

	/**
	 * Banner body text.
	 */
	protected function get_message() {
		return __(
			'Important: In the latest update of the plug-in, an additional API key field has been added to the account configuration of the PostNL plug-in. This field must be filled in with the new API key that can be obtained via the Self Service module on the PostNL Business Portal. This API key is required to gain access to the new APIs that will be rolled out in a future update of the plug-in. It is very important that this key is entered before the relevant update is performed; otherwise, no connection can be made to the new PostNL APIs, and it will not be possible to create labels or use checkout features such as delivery days and pickup points.',
			'postnl-for-woocommerce'
		);
	}

	/**
	 * Enqueue the dismissal JS on screens where the banner can appear.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( $hook_suffix ) {
		unset( $hook_suffix );

		if ( ! $this->is_target_screen() ) {
			return;
		}

		wp_enqueue_script(
			'postnl-new-api-key-banner',
			POSTNL_WC_PLUGIN_DIR_URL . '/assets/js/new-api-key-banner.js',
			array( 'jquery' ),
			POSTNL_WC_VERSION,
			true
		);

		wp_localize_script(
			'postnl-new-api-key-banner',
			'postnlNewApiKeyBanner',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::AJAX_ACTION,
			)
		);
	}

	/**
	 * AJAX handler for both dismiss modes.
	 */
	public function handle_ajax_dismiss() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$mode    = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
		$user_id = get_current_user_id();

		if ( 'remind' === $mode ) {
			update_user_meta( $user_id, self::META_REMIND_LATER, time() );
			wp_send_json_success();
		}

		if ( 'dismiss' === $mode ) {
			update_user_meta( $user_id, self::META_DISMISSED, 1 );
			wp_send_json_success();
		}

		wp_send_json_error( array( 'message' => 'invalid mode' ), 400 );
	}

	/**
	 * Wipe the "remind me later" flag whenever the user logs in, so the
	 * banner comes back the next session.
	 *
	 * @param string   $user_login Username.
	 * @param \WP_User $user       Logged-in user.
	 */
	public function clear_remind_later_on_login( $user_login, $user ) {
		unset( $user_login );

		if ( $user instanceof \WP_User ) {
			delete_user_meta( $user->ID, self::META_REMIND_LATER );
		}
	}
}
