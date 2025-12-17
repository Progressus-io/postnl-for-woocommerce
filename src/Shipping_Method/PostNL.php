<?php
/**
 * Class Shipping_Method/PostNL file.
 *
 * @package PostNLWooCommerce\Shipping_Method
 */

namespace PostNLWooCommerce\Shipping_Method;

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
		$this->process_merchant_codes();
	}

	/**
	 * Calculate the shipping costs.
	 *
	 * @param array $package Package of items from cart.
	 */
	public function calculate_shipping( $package = array() ) {
		// Set free shipping rate if cart subtotal exceed minimum_for_free_shipping
		$minimum_for_free_shipping = $this->get_option( 'minimum_for_free_shipping' );
		if ( '' !== $minimum_for_free_shipping && $package['cart_subtotal'] > $minimum_for_free_shipping ) {
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
