<?php
/**
 * Non‑dismissible admin banner for PostNL
 *
 * @package PostNLWooCommerce
 */

namespace PostNLWooCommerce\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Survey
 */
class Survey {


	/**
	 * URL of the survey.
	 *
	 * @var string
	 */
	const SURVEY_URL = 'https://surveys.enalyzer.com?pid=m3bs8r6b';

	/**
	 * Maybe add the survey meta box.
	 *
	 * @param string $post_type Optional post type.
	 * @param mixed $post Optional post object.
	 *
	 * @return void
	 */
	public static function maybe_add_meta_box( $post_type = null, $post = null ) {
		if ( ! self::should_show() ) {
			return;
		}

		add_meta_box(
			'postnl_admin_banner',
			esc_html__( 'Help us improve PostNL', 'postnl-for-woocommerce' ),
			array( __CLASS__, 'render_meta_box' ),
			self::order_screen_id(),
			'side',
			'high'
		);
	}

	/**
	 * Maybe render the survey notice.
	 *
	 * @return void
	 */
	public static function maybe_render_notice() {
		if ( ! self::should_show() ) {
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
	public static function order_screen_id(): string {
		try {
			return wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
				? wc_get_page_screen_id( 'shop-order' )
				: 'shop_order';
		} catch ( \Exception $e ) {
			return 'shop_order';
		}
	}
}
