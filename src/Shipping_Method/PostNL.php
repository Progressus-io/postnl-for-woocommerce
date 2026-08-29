<?php
/**
 * Class Shipping_Method/PostNL file.
 *
 * @package PostNLWooCommerce\Shipping_Method
 */

namespace PostNLWooCommerce\Shipping_Method;

use PostNLWooCommerce\Rest_API\Barcode\Key_Validator;
use PostNLWooCommerce\Utils;
use WC_Admin_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PostNL
 *
 * @package PostNLWooCommerce\Shipping_Method
 */
class PostNL extends \WC_Shipping_Flat_Rate {
	/**
	 * Merchant codes option name.
	 */
	const MERCHANT_CODES_OPTION = 'postnl_merchant_codes';

	/**
	 * Init and hook in the integration.
	 *
	 * @param int $instance_id Instance ID.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id           = POSTNL_SETTINGS_ID;
		$this->instance_id  = absint( $instance_id );
		$this->method_title = POSTNL_SERVICE_NAME;

		// translators: %1$s & %2$s is replaced with <a> tag.
		$this->method_description = sprintf( __( 'Below you will find all functions for controlling, preparing and processing your shipment with PostNL. Prerequisite is a valid PostNL business customer contract. If you are not yet a PostNL business customer, you can request a quote %1$shere%2$s.', 'postnl-for-woocommerce' ), '<a href="https://mijnpostnlzakelijk.postnl.nl/s/become-a-customer?language=nl_NL#/" target="_blank">', '</a>' );
		$this->supports           = array(
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal',
			'settings',
		);

		$this->postnl_init();
	}

	/**
	 * Shipping method initialization.
	 */
	public function postnl_init() {
		$this->init();
		$this->init_form_fields();
		$this->init_settings();

		add_filter( 'woocommerce_shipping_instance_form_fields_' . $this->id, array( $this, 'instance_form_fields' ), 10, 1 );
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_shipping_method_assets' ) );
	}

	/**
	 * Process admin options.
	 *
	 * @return void
	 */
	public function process_admin_options() {
		parent::process_admin_options();

		// parent::process_admin_options() writes the new values to the DB but only
		// updates this method object's cache; the Settings singleton still holds
		// the pre-save values, and the status row, the NewKey header and the
		// effective-key selection all read through it for the rest of the request.
		// Refresh it so the screen that comes back reflects what was just saved.
		Settings::get_instance()->init_settings();

		$this->process_merchant_codes();

		// When pickup is disabled, reset the default checkout tab to its default value.
		if ( 'yes' !== $this->get_option( 'enable_pickup_points' ) ) {
			$this->update_option( 'default_checkout_tab', 'delivery_day' );
		}

		$this->process_new_api_key_validation();
	}

	/**
	 * Validate the "New API Key" field whenever settings are saved.
	 *
	 * Runs in both environments so the key can be checked in sandbox as well as
	 * production. Performs a live Barcode API call with the candidate key for the
	 * active environment. Success flips the validated flag on so the plugin starts
	 * routing traffic through the new key; failure leaves the old key in use,
	 * records why, and surfaces the reason to the merchant.
	 */
	protected function process_new_api_key_validation() {
		$settings = Settings::get_instance();

		// The environment is read from the freshly-posted value on $this rather
		// than the settings singleton, which may still hold the pre-save value.
		$is_sandbox = ( 'production' !== $this->get_option( 'environment_mode' ) );
		$new_field  = $is_sandbox ? 'api_keys_sandbox_new' : 'api_keys_new';
		$orig_field = $is_sandbox ? 'api_keys_sandbox' : 'api_keys';

		$new_key  = trim( (string) $this->get_option( $new_field ) );
		$original = trim( (string) $this->get_option( $orig_field ) );

		if ( '' === $new_key || $new_key === $original ) {
			$settings->set_api_key_new_validated( false, null, $is_sandbox );
			$settings->set_new_key_status_reason( '', $is_sandbox );
			return;
		}

		// This exact key already passed validation; skip the live call so an
		// unrelated settings save doesn't make a blocking request every time.
		if ( $settings->is_api_key_new_validated_value( $new_key, $is_sandbox ) ) {
			return;
		}

		$result = Key_Validator::validate(
			$new_key,
			$this->get_option( 'customer_code' ),
			$this->get_option( 'customer_num' ),
			$is_sandbox
		);

		if ( true === $result ) {
			$settings->set_api_key_new_validated( true, $new_key, $is_sandbox );
			$settings->set_new_key_status_reason( '', $is_sandbox );
			return;
		}

		$reason = $result->get_error_code();

		// "unreachable" means PostNL never returned a verdict, so we cannot say the
		// key is bad — leave any previously-validated state as it was rather than
		// showing a false red. Every other reason is a definite "not usable yet".
		if ( Key_Validator::REASON_UNREACHABLE !== $reason ) {
			$settings->set_api_key_new_validated( false, null, $is_sandbox );
		}

		$settings->set_new_key_status_reason( $reason, $is_sandbox );
		WC_Admin_Settings::add_error( esc_html( Key_Validator::reason_message( $reason ) ) );
	}

	/**
	 * Render the status row shown beneath the API Key field: a coloured label,
	 * an em dash and a plain sentence describing the new key's state, driven by
	 * the same value sent in the NewKey header. Hidden by the settings JS for
	 * whichever environment is not currently selected.
	 *
	 * @param string $key  Field key.
	 * @param array  $data Field data (expects an 'environment' of 'sandbox' or 'production').
	 *
	 * @return string
	 */
	public function generate_postnl_new_key_status_html( $key, $data ) {
		$is_sandbox = ( isset( $data['environment'] ) && 'sandbox' === $data['environment'] );
		$status     = Settings::get_instance()->get_new_key_status( $is_sandbox );

		ob_start();
		?>
		<tr valign="top" class="postnl-new-key-status-row" data-postnl-env="<?php echo esc_attr( $is_sandbox ? 'sandbox' : 'production' ); ?>">
			<th scope="row" class="titledesc"><?php esc_html_e( 'API Key Status', 'postnl-for-woocommerce' ); ?></th>
			<td class="forminp">
				<p style="margin-top:0;">
					<strong class="postnl-new-key-status-label" style="color:<?php echo esc_attr( $status['color'] ); ?>;"><?php echo esc_html( $status['label'] ); ?></strong>
					&mdash; <span class="postnl-new-key-status-summary"><?php echo esc_html( $status['summary'] ); ?></span>
				</p>
				<p class="description postnl-new-key-status-desc" style="margin-top:4px;<?php echo empty( $status['description'] ) ? 'display:none;' : ''; ?>"><?php echo wp_kses_post( $status['description'] ); ?></p>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Calculate the shipping costs.
	 *
	 * @param array $package Package of items from cart.
	 */
	public function calculate_shipping( $package = array() ) {
		// Determine whether the cart subtotal exceeds the free-shipping threshold.
		$minimum_for_free_shipping = $this->get_option( 'minimum_for_free_shipping' );
		$is_free                   = '' !== $minimum_for_free_shipping && $package['cart_subtotal'] > $minimum_for_free_shipping;

		if ( $is_free ) {
			$rate = array(
				'id'      => $this->get_rate_id(),
				'label'   => $this->title,
				'cost'    => 0,
				'package' => $package,
			);
			$this->add_rate( $rate );
		} else {
			parent::calculate_shipping( $package );
		}
	}

	/**
	 * Add form fields for PostNL.
	 *
	 * @param Array $form_fields List of instance form fields.
	 *
	 * @return Array
	 */
	public function instance_form_fields( $form_fields ) {
		// Change title default value.
		$form_fields['title']['default'] = $this->method_title;

		// Minimum for free shipping.
		$currency_symbol = get_woocommerce_currency_symbol();

		$form_fields['minimum_for_free_shipping'] = array(
			// Translators: %s is the currency symbol.
			'title'    => sprintf( esc_html__( 'Free shipping from %s', 'postnl-for-woocommerce' ), $currency_symbol ),
			'type'     => 'number',
			'desc_tip' => esc_html__( 'Keep empty if you don’t want to use Free shipping', 'postnl-for-woocommerce' ),
			'default'  => 0,
		);

		return $form_fields;
	}

	/**
	 * Initialize integration settings form fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$base_country = Utils::get_base_country();
		$settings     = Settings::get_instance();

		if ( 'NL' === $base_country ) {
			$form_fields = $settings->nl_setting_fields();
		} elseif ( 'BE' === $base_country ) {
			$form_fields = $settings->be_setting_fields();
		} else {
			$form_fields = $settings->filter_setting_fields( '' );
		}

		$this->form_fields = $form_fields;
	}

	/**
	 * Enqueue js file in shipping method settings page.
	 */
	public function enqueue_shipping_method_assets() {
		$screen = get_current_screen();
		if ( ! empty( $screen->id ) && 'woocommerce_page_wc-settings' === $screen->id && ! empty( $_GET['section'] ) && POSTNL_SETTINGS_ID === wp_unslash( $_GET['section'] ) ) {
			wp_enqueue_script(
				'postnl-admin-settings',
				POSTNL_WC_PLUGIN_DIR_URL . '/assets/js/admin-settings.js',
				array( 'jquery' ),
				POSTNL_WC_VERSION,
				true
			);

			wp_localize_script(
				'postnl-admin-settings',
				'postnlApiKeyCheck',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'action'  => \PostNLWooCommerce\Admin\Api_Key_Check::AJAX_ACTION,
					'nonce'   => wp_create_nonce( \PostNLWooCommerce\Admin\Api_Key_Check::NONCE_ACTION ),
				)
			);
		}
	}

	/**
	 * Generate repeater HTML.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 *
	 * @return string
	 */
	public function generate_repeater_html( $key, $data ) {
		ob_start();
		$merchant_codes   = get_option( self::MERCHANT_CODES_OPTION, array() );
		$non_eu_countries = Utils::get_non_eu_countries();

		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $data['id'] ?? $key ); ?>"><?php echo wp_kses_post( $data['title'] ?? '' ); ?></label>
				<?php echo $this->get_tooltip_html( $data ); ?>
			</th>
			<td class="forminp">
				<div id="postnl-merchant-codes-repeater">
					<div class="merchant-codes-header">
						<div class="merchant-codes-row merchant-codes-header-row">
							<div class="country-column">
								<strong><?php esc_html_e( 'Country', 'postnl-for-woocommerce' ); ?></strong>
							</div>
							<div class="code-column">
								<strong><?php esc_html_e( 'Merchant Code', 'postnl-for-woocommerce' ); ?></strong>
							</div>
							<div class="action-column">
								<strong><?php esc_html_e( 'Action', 'postnl-for-woocommerce' ); ?></strong>
							</div>
						</div>
					</div>

					<div class="merchant-codes-rows" id="merchant-codes-rows">
						<?php if ( ! empty( $merchant_codes ) ) : ?>
							<?php foreach ( $merchant_codes as $country_code => $merchant_code ) : ?>
								<div class="merchant-codes-row">
									<div class="country-column">
										<select name="<?php echo esc_attr( self::MERCHANT_CODES_OPTION ); ?>_countries[]" class="country-select">
											<option value=""><?php esc_html_e( 'Select Country', 'postnl-for-woocommerce' ); ?></option>
											<?php foreach ( $non_eu_countries as $code => $name ) : ?>
												<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $country_code, $code ); ?>>
													<?php echo esc_html( $name ); ?> (<?php echo esc_html( $code ); ?>)
												</option>
											<?php endforeach; ?>
										</select>
									</div>
									<div class="code-column">
										<input type="text"
											name="<?php echo esc_attr( self::MERCHANT_CODES_OPTION ); ?>_codes[]"
											value="<?php echo esc_attr( $merchant_code ); ?>"
											placeholder="<?php esc_attr_e( 'Enter merchant code', 'postnl-for-woocommerce' ); ?>"
											class="regular-text"
										/>
									</div>
									<div class="action-column">
										<button type="button" class="button remove-row"><?php esc_html_e( 'Remove', 'postnl-for-woocommerce' ); ?></button>
									</div>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>

					<div class="merchant-codes-actions">
						<button type="button" class="button button-secondary" id="add-merchant-code-row">
							<?php esc_html_e( 'Add Merchant Code', 'postnl-for-woocommerce' ); ?>
						</button>
					</div>
				</div>

				<?php if ( isset( $data['description'] ) && ! empty( $data['description'] ) ) : ?>
					<p class="description"><?php echo wp_kses_post( $data['description'] ); ?></p>
				<?php endif; ?>

				<!-- Row template for JavaScript -->
				<script type="text/template" id="merchant-code-row-template">
					<div class="merchant-codes-row">
						<div class="country-column">
							<select name="<?php echo esc_attr( self::MERCHANT_CODES_OPTION ); ?>_countries[]" class="country-select">
								<option value=""><?php esc_html_e( 'Select Country', 'postnl-for-woocommerce' ); ?></option>
								<?php foreach ( $non_eu_countries as $code => $name ) : ?>
									<option value="<?php echo esc_attr( $code ); ?>">
										<?php echo esc_html( $name ); ?> (<?php echo esc_html( $code ); ?>)
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="code-column">
							<input type="text"
								name="<?php echo esc_attr( self::MERCHANT_CODES_OPTION ); ?>_codes[]"
								value=""
								placeholder="<?php esc_attr_e( 'Enter merchant code', 'postnl-for-woocommerce' ); ?>"
								class="regular-text"
							/>
						</div>
						<div class="action-column">
							<button type="button" class="button remove-row"><?php esc_html_e( 'Remove', 'postnl-for-woocommerce' ); ?></button>
						</div>
					</div>
				</script>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Save merchant codes from repeater.
	 */
	public function process_merchant_codes() {
		$merchant_codes = array();
		$countries_key  = self::MERCHANT_CODES_OPTION . '_countries';
		$codes_key      = self::MERCHANT_CODES_OPTION . '_codes';
		$error          = false;

		if ( ! isset( $_POST[ $countries_key ] ) && ! isset( $_POST[ $codes_key ] ) ) {
			update_option( self::MERCHANT_CODES_OPTION, array() );

			return;
		}

		$countries = $_POST[ $countries_key ]; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$codes     = $_POST[ $codes_key ];   // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Check for duplicates in countries array.
		if ( count( $countries ) !== count( array_unique( $countries ) ) ) {
			WC_Admin_Settings::add_error(
				esc_html__( 'Duplicate countries found and have been removed. Only the last entry for each country will be saved.', 'postnl-for-woocommerce' )
			);
		}

		foreach ( $countries as $index => $country ) {
			$code = $codes[ $index ] ?? null;

			// Skip empty values or missing codes.
			if ( empty( $country ) || empty( $code ) ) {
				$error = true;
				continue;
			}

			$merchant_codes[ sanitize_text_field( $country ) ] = sanitize_text_field( $code );
		}

		update_option( self::MERCHANT_CODES_OPTION, $merchant_codes );

		if ( $error ) {
			WC_Admin_Settings::add_error( esc_html__( 'Some merchant codes were not saved because of missing country or code.', 'postnl-for-woocommerce' ) );
		}
	}
}
