<?php
/**
 * Admin banner for PostNL survey.
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
			esc_html__( 'PostNL Survey', 'postnl-for-woocommerce' ),
			array( __CLASS__, 'render_meta_box' ),
			Utils::get_order_screen_id(),
			'normal',
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
	 * Handle survey banner actions.
	 *
	 * @return void
	 */
	public static function handle_actions() {
		if ( empty( $_GET['postnl_survey_action'] ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_GET['postnl_survey_action'] ) );

		if ( 'hide' === $action ) {
			update_user_meta( get_current_user_id(), '_postnl_survey_hidden', 1 );
		}

		// "remind" just reloads without saving anything, so banner reappears.
		wp_safe_redirect( remove_query_arg( 'postnl_survey_action' ) );
		exit;
	}

	/**
	 * Determine if the banner is eligible to show on the current screen.
	 *
	 * @return bool
	 */
	protected static function should_show(): bool {
		// Permanently hidden by user.
		if ( get_user_meta( get_current_user_id(), '_postnl_survey_hidden', true ) ) {
			return false;
		}

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
		$hide_url   = add_query_arg( 'postnl_survey_action', 'hide' );
		?>
		<style>
			#postnl-admin-banner {
				border-left-color: #ed8c00;
				background: #fff7f0;
				position: relative;
			}

			#postnl-admin-banner .button-primary {
				background: #ed8c00;
				border-color: #e65c00;
				color: #fff;
			}

			#postnl-admin-banner .banner-actions {
				margin-top: 10px;
			}

			#postnl-admin-banner .banner-actions a {
				margin-right: 10px;
			}
		</style>
		<div id="postnl-admin-banner" class="notice notice-info">
			<h2><?php esc_html_e( 'Would you like a chance to win a Bol gift card worth €25?', 'postnl-for-woocommerce' ); ?></h2>
			<p><strong><?php esc_html_e( 'Let us know what you think of the PostNL for WooCommerce plugin by completing the survey.', 'postnl-for-woocommerce' ); ?></strong></p>
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
					<?php esc_html_e( 'Leave a review', 'postnl-for-woocommerce' ); ?>
				</a>
			</p>
			<p class="banner-actions">
				<a class="button-secondary" id="postnl-remind-later">
					<?php esc_html_e( 'Remind me later', 'postnl-for-woocommerce' ); ?>
				</a>
				<a href="<?php echo esc_url( $hide_url ); ?>" class="button-secondary">
					<?php esc_html_e( 'Hide', 'postnl-for-woocommerce' ); ?>
				</a>
			</p>
		</div>
		<script>
			document.addEventListener( "DOMContentLoaded", function() {
				const remindBtn = document.getElementById( "postnl-remind-later" );
				if ( remindBtn ) {
					remindBtn.addEventListener( "click", function( e ) {
						e.preventDefault();
						const banner = document.getElementById( "postnl-admin-banner" );
						if ( banner ) {
							banner.style.display = "none";
						}
					} );
				}
			} );
		</script>
		<?php
	}

	/**
	 * Output banner inside the sidebar meta‑box on a single order.
	 *
	 * @return void
	 */
	public static function render_meta_box() {
		?>
		<style>
			#postnl_admin_banner {
				background: #fff7f0;
			}

			#postnl_admin_banner .survey-title {
				padding: 10px 0 !important;
			}

			#postnl_admin_banner .button-primary {
				background: #ed8c00;
				border-color: #e65c00;
				color: #fff;
			}
		</style>
		<h2 class="survey-title">
			<strong><?php esc_html_e( 'Would you like a chance to win a Bol gift card worth €25?', 'postnl-for-woocommerce' ); ?></strong>
		</h2>
		<p>
			<?php esc_html_e( 'Let us know what you think of the PostNL for WooCommerce plugin by completing the survey.', 'postnl-for-woocommerce' ); ?>
		</p>
		<p>
			<a href="<?php echo esc_url( self::SURVEY_URL ); ?>"
				class="button button-primary"
				target="_blank"
				rel="noopener noreferrer">
				<?php esc_html_e( 'Take survey', 'postnl-for-woocommerce' ); ?>
			</a>
		</p>
		<p>
			<a href="<?php echo esc_url( 'https://wordpress.org/plugins/postnl-for-woocommerce/#reviews' ); ?>"
				target="_blank"
				rel="noopener noreferrer">
				<?php esc_html_e( 'Leave a review', 'postnl-for-woocommerce' ); ?>
			</a>
		</p>
		<?php
	}
}
