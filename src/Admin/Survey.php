<?php
/**
 * Non‑dismissible admin banner for PostNL
 *
 * @package PostNLWooCommerce
 */

namespace PostNLWooCommerce\Admin;

use Automattic\WooCommerce\Utilities\OrderUtil;
use PostNLWooCommerce\Utils;

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
	 * @return void
	 */
	public static function maybe_add_meta_box() {
        if ( ! OrderUtil::is_order_edit_screen() ) {
            return;
        }

		add_meta_box(
			'postnl_admin_banner',
			esc_html__( 'Help us improve PostNL', 'postnl-for-woocommerce' ),
			array( __CLASS__, 'render_meta_box' ),
			Utils::get_order_screen_id(),
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
	protected static function should_show(): bool {
		if ( ! is_admin() ) {
			return false;
		}

		$screen = get_current_screen();
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
        if ( OrderUtil::is_order_list_table_screen() ) {
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
}
