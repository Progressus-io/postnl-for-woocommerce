<?php
/**
 * Non‑dismissible admin banner for PostNL
 *
 * @package PostNLWooCommerce
 */

namespace PostNLWooCommerce\Shipping_Method;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Notice
 */
class Notice {


	/**
	 * URL of the survey.
	 *
	 * @var string
	 */
	const SURVEY_URL = 'https://www.postnl.nl';

	/**
	 * Decide whether to output the banner and register a meta‑box when needed.
	 *
	 * @param string $post_type Optional post type (meta‑boxes hook).
	 * @param mixed  $post      Optional post object (meta‑boxes hook).
	 *
	 * @return void
	 */
	public static function maybe_render( $post_type = null, $post = null ) {
		if ( ! self::should_show() ) {
			return;
		}

		if ( 'add_meta_boxes' === current_action() ) {
			add_meta_box(
				'postnl_admin_banner',
				esc_html__( 'Help us improve PostNL', 'postnl-for-woocommerce' ),
				array( __CLASS__, 'render_meta_box' ),
				self::order_screen_id(),
				'side',
				'high'
			);
			return;
		}

		self::render_notice();
	}

	/**
	 * Determine if the banner is eligible to show on the current screen.
	 *
	 * @return bool
	 */
	protected static function should_show() {
		if ( ! is_admin() || ! defined( 'POSTNL_WC_VERSION' ) ) {
			return false;
		}


		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( empty( $screen ) ) {
			return false;
		}

		// Settings page.
		if (
			'woocommerce_page_wc-settings' === $screen->id &&
			! empty( $_GET['section'] ) &&
			POSTNL_SETTINGS_ID === wp_unslash( $_GET['section'] )
		) {
			return true;
		}

		// Orders list.
		$order_list_screens = array( 'edit-shop_order', 'woocommerce_page_wc-orders' );
		if ( in_array( $screen->id, $order_list_screens, true ) ) {
			return true;
		}

		// Single order.
		if ( self::order_screen_id() === $screen->id || 'shop_order' === $screen->id ) {
			return true;
		}

		return false;
	}

	/**
	 * Output banner as an admin‑notice.
	 *
	 * @return void
	 */
	protected static function render_notice() {
		?>
        <div class="notice notice-info">
            <p><strong><?php esc_html_e( 'Help us improve PostNL', 'postnl-for-woocommerce' ); ?></strong></p>
            <p>
                <a href="<?php echo esc_url( self::SURVEY_URL ); ?>"
                   class="button button-primary"
                   target="_blank"
                   rel="noopener noreferrer">
					<?php esc_html_e( 'Take Survey', 'postnl-for-woocommerce' ); ?>
                </a>
            </p>
            <p>
                <a href="<?php echo esc_url( 'https://wordpress.org/support/plugin/woo-postnl/reviews/#new-post' ); ?>"
                   target="_blank"
                   rel="noopener noreferrer">
					<?php esc_html_e( 'Leave a review on WordPress org', 'postnl-for-woocommerce' ); ?>
                </a>
            </p>
        </div>
		<?php
	}

	/**
	 * Output banner inside the sidebar meta‑box on a single order.
	 *
	 * @return void
	 */
	public static function render_meta_box() {
		?>
        <p>
            <a href="<?php echo esc_url( self::SURVEY_URL ); ?>"
               class="button button-primary"
               target="_blank"
               rel="noopener noreferrer">
				<?php esc_html_e( 'Take Survey', 'postnl-for-woocommerce' ); ?>
            </a>
        </p>
        <p>
            <a href="<?php echo esc_url( 'https://wordpress.org/plugins/postnl-for-woocommerce/#reviews' ); ?>"
               target="_blank"
               rel="noopener noreferrer">
				<?php esc_html_e( 'Leave a review on WordPress org', 'postnl-for-woocommerce' ); ?>
            </a>
        </p>
		<?php
	}

	/**
	 * WooCommerce screen ID helper.
	 *
	 * @return string
	 */
	protected static function order_screen_id() {
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			return wc_get_page_screen_id( 'shop-order' );
		}
		return 'shop_order';
	}
}
