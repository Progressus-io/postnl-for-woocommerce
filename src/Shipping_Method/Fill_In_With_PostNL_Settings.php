<?php
/**
 * Class Shipping_Method/Fill_In_With_PostNL_Settings file.
 *
 * @package PostNLWooCommerce\Shipping_Method
 */

namespace PostNLWooCommerce\Shipping_Method;

use WC_Admin_Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class Fill_In_With_PostNL_Settings
 * Handles the 'Fill in with PostNL' WooCommerce shipping settings tab.
 */
class Fill_In_With_PostNL_Settings {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_get_sections_shipping', array( $this, 'add_settings_section' ) );
		add_filter( 'woocommerce_get_settings_shipping', array( $this, 'add_settings_fields' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option', array( $this, 'maybe_prevent_saving_invalid_data' ), 10, 3 );
		add_action( 'woocommerce_admin_field_postnl_preview', array( __CLASS__, 'render_preview_field' ) );
	}

	/**
	 * Add new section to the WooCommerce Shipping settings.
	 *
	 * @param array $sections Existing WooCommerce shipping sections.
	 *
	 * @return array
	 */
	public function add_settings_section( array $sections ): array {
		$sections['fill-in-with-postnl'] = esc_html__( 'Checkoutboosters', 'postnl-for-woocommerce' );
		return $sections;
	}

	/**
	 * Add settings fields to the custom section.
	 *
	 * @param array  $settings Existing settings.
	 * @param string $current_section Current section being rendered.
	 *
	 * @return array
	 */
	public function add_settings_fields( $settings, $current_section ): array {
		if ( 'fill-in-with-postnl' !== $current_section ) {
			return $settings;
		}

		$activation_url = 'https://dil-business-portal.postnl.nl/checkout-prefill?referrer=wcplugin&url=' . site_url();

		$info_block  = '<p>' . esc_html__( 'Let customers autofill their shipping address in just a few clicks with their PostNL account. So customers don\'t have to manually enter their details anymore. Address details are securely prefilled with PostNL, reducing address errors, returns, and checkout drop-off.', 'postnl-for-woocommerce' ) . '</p>';
		$info_block .= '<p>' . esc_html__( 'Available for all consumers with a PostNL account and a Dutch or Belgian shipping address.', 'postnl-for-woocommerce' ) . '</p>';
		$info_block .= '<p>' . esc_html__( 'Activate PostNL autofill via this link:', 'postnl-for-woocommerce' ) . ' <a href="' . esc_url( $activation_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $activation_url ) . '</a></p>';

		$settings = array(
			array(
				'title' => esc_html__( 'Checkoutboosters', 'postnl-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => $info_block,
				'id'    => 'postnl_fill_in_with_title',
			),
			array(
				'title'   => esc_html__( 'Enable', 'postnl-for-woocommerce' ),
				'desc'    => esc_html__( 'Enable Fill in with PostNL functionality.', 'postnl-for-woocommerce' ),
				'id'      => 'postnl_enable_fill_in_with',
				'default' => 'no',
				'type'    => 'checkbox',
			),
			array(
				'title' => esc_html__( 'Client ID', 'postnl-for-woocommerce' ),
				'desc'  => esc_html__( 'Enter your PostNL Client ID from the Digital Business Portal.', 'postnl-for-woocommerce' ),
				'id'    => 'postnl_fill_in_with_client_id',
				'type'  => 'text',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'postnl_fill_in_with_section_end',
			),

			// Cart Button section.
			array(
				'title' => esc_html__( 'Cart Button', 'postnl-for-woocommerce' ),
				'type'  => 'title',
				'id'    => 'postnl_cart_button_title',
			),
			array(
				'title'   => esc_html__( 'Automatically render button in cart page', 'postnl-for-woocommerce' ),
				'desc'    => esc_html__( 'If enabled, the button will be automatically rendered in the cart page.', 'postnl-for-woocommerce' ),
				'id'      => 'postnl_cart_auto_render_button',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'   => esc_html__( 'Cart page button placement', 'postnl-for-woocommerce' ),
				'id'      => 'postnl_cart_button_placement',
				'type'    => 'select',
				'default' => 'before_checkout',
				'options' => array(
					'before_checkout' => esc_html__( 'Before Checkout', 'postnl-for-woocommerce' ),
					'after_checkout'  => esc_html__( 'After Checkout', 'postnl-for-woocommerce' ),
				),
			),
			array(
				'title'             => esc_html__( 'Cart button width', 'postnl-for-woocommerce' ),
				'desc'              => esc_html__( 'Set the button width as a percentage (1–100%).', 'postnl-for-woocommerce' ),
				'desc_tip'          => true,
				'id'                => 'postnl_cart_button_width',
				'type'              => 'number',
				'default'           => '100',
				'css'               => 'width: 70px;',
				'class'             => 'postnl-range-slider',
				'custom_attributes' => array(
					'min'       => '1',
					'max'       => '100',
					'step'      => '1',
					'data-unit' => '%',
				),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'postnl_cart_button_section_end',
			),

			// Checkout Page section.
			array(
				'title' => esc_html__( 'Checkout Page', 'postnl-for-woocommerce' ),
				'type'  => 'title',
				'id'    => 'postnl_checkout_page_title',
			),
			array(
				'title'   => esc_html__( 'Automatically render button in checkout page', 'postnl-for-woocommerce' ),
				'desc'    => esc_html__( 'If enabled, the button will be automatically rendered in the checkout page.', 'postnl-for-woocommerce' ),
				'id'      => 'postnl_checkout_auto_render_button',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'   => esc_html__( 'Checkout page button placement', 'postnl-for-woocommerce' ),
				'id'      => 'postnl_checkout_button_placement',
				'type'    => 'select',
				'default' => 'before_customer_details',
				'options' => array(
					'before_customer_details' => esc_html__( 'Before Customer Details', 'postnl-for-woocommerce' ),
					'after_customer_details'  => esc_html__( 'After Customer Details', 'postnl-for-woocommerce' ),
				),
			),
			array(
				'title'             => esc_html__( 'Checkout button width', 'postnl-for-woocommerce' ),
				'desc'              => esc_html__( 'Set the button width as a percentage (1–100%).', 'postnl-for-woocommerce' ),
				'desc_tip'          => true,
				'id'                => 'postnl_checkout_button_width',
				'type'              => 'number',
				'default'           => '100',
				'css'               => 'width: 70px;',
				'class'             => 'postnl-range-slider',
				'custom_attributes' => array(
					'min'       => '1',
					'max'       => '100',
					'step'      => '1',
					'data-unit' => '%',
				),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'postnl_checkout_section_end',
			),

			// Minicart section.
			array(
				'title' => esc_html__( 'Minicart', 'postnl-for-woocommerce' ),
				'type'  => 'title',
				'id'    => 'postnl_minicart_title',
			),
			array(
				'title'   => esc_html__( 'Automatically render button in Minicart', 'postnl-for-woocommerce' ),
				'desc'    => esc_html__( 'If enabled, the button will be automatically rendered in the minicart.', 'postnl-for-woocommerce' ),
				'id'      => 'postnl_minicart_auto_render_button',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'   => esc_html__( 'Minicart button placement', 'postnl-for-woocommerce' ),
				'id'      => 'postnl_minicart_button_placement',
				'type'    => 'select',
				'default' => 'before_buttons',
				'options' => array(
					'before_buttons' => esc_html__( 'Before Buttons', 'postnl-for-woocommerce' ),
					'after_buttons'  => esc_html__( 'After Buttons', 'postnl-for-woocommerce' ),
				),
			),
			array(
				'title'             => esc_html__( 'Minicart button width', 'postnl-for-woocommerce' ),
				'desc'              => esc_html__( 'Set the button width as a percentage (1–100%).', 'postnl-for-woocommerce' ),
				'desc_tip'          => true,
				'id'                => 'postnl_minicart_button_width',
				'type'              => 'number',
				'default'           => '100',
				'css'               => 'width: 70px;',
				'class'             => 'postnl-range-slider',
				'custom_attributes' => array(
					'min'       => '1',
					'max'       => '100',
					'step'      => '1',
					'data-unit' => '%',
				),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'postnl_minicart_section_end',
			),

			// Button Styling section.
			array(
				'title' => esc_html__( 'Button Styling', 'postnl-for-woocommerce' ),
				'type'  => 'title',
				'id'    => 'postnl_button_styling_title',
			),
			array(
				'title'    => esc_html__( 'Button Background Color', 'postnl-for-woocommerce' ),
				'desc'     => esc_html__( 'Select the background color for the PostNL button.', 'postnl-for-woocommerce' ),
				'desc_tip' => true,
				'id'       => 'postnl_button_background_color',
				'type'     => 'color',
				'default'  => '#ff6200',
				'css'      => 'width: 80px;',
			),
			array(
				'title'    => esc_html__( 'Button Border', 'postnl-for-woocommerce' ),
				'desc'     => esc_html__( 'Define the border style for the PostNL button (e.g., 1px solid #000000).', 'postnl-for-woocommerce' ),
				'desc_tip' => true,
				'id'       => 'postnl_button_border',
				'type'     => 'text',
				'default'  => 'none',
				'css'      => 'width: 150px;',
			),
			array(
				'title'    => esc_html__( 'Button Alignment', 'postnl-for-woocommerce' ),
				'desc'     => esc_html__( 'Select the alignment for the PostNL button.', 'postnl-for-woocommerce' ),
				'desc_tip' => true,
				'id'       => 'postnl_button_alignment',
				'type'     => 'select',
				'default'  => 'left',
				'options'  => array(
					'left'   => esc_html__( 'Left', 'postnl-for-woocommerce' ),
					'center' => esc_html__( 'Center', 'postnl-for-woocommerce' ),
					'right'  => esc_html__( 'Right', 'postnl-for-woocommerce' ),
				),
			),
			array(
				'title'    => esc_html__( 'Button Hover Background Color', 'postnl-for-woocommerce' ),
				'desc'     => esc_html__( 'Select the background color for the PostNL button on hover.', 'postnl-for-woocommerce' ),
				'desc_tip' => true,
				'id'       => 'postnl_button_hover_background_color',
				'type'     => 'color',
				'default'  => '#e55500',
				'css'      => 'width: 80px;',
			),
			array(
				'title'             => esc_html__( 'Button corner radius', 'postnl-for-woocommerce' ),
				'desc'              => esc_html__( 'Set the button corner roundness in pixels (0 = square, 50 = fully rounded).', 'postnl-for-woocommerce' ),
				'desc_tip'          => true,
				'id'                => 'postnl_button_border_radius',
				'type'              => 'number',
				'default'           => '4',
				'css'               => 'width: 70px;',
				'class'             => 'postnl-range-slider',
				'custom_attributes' => array(
					'min'       => '0',
					'max'       => '50',
					'step'      => '1',
					'data-unit' => 'px',
				),
			),
			array(
				'type' => 'postnl_preview',
				'id'   => 'postnl_button_preview',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'postnl_button_styling_section_end',
			),

			// Custom css section.
			array(
				'title' => esc_html__( 'Custom CSS', 'postnl-for-woocommerce' ),
				'type'  => 'title',
				'id'    => 'postnl_custom_css_title',
			),
			array(
				'title'    => esc_html__( 'Custom CSS', 'postnl-for-woocommerce' ),
				'desc_tip' => esc_html__( 'Add your custom styles for the PostNL button. This will be printed in the site header.', 'postnl-for-woocommerce' ),
				'id'       => 'postnl_custom_css',
				'type'     => 'textarea',
				'css'      => 'min-height:200px; font-family: monospace;',
				'class'    => 'postnl-css-editor',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'postnl_custom_css_section_end',
			),

			array(
				'desc'    => esc_html__( 'I understand that the PostNL address fields are activated (= separate field for zipcode, housnumber, housenumber extension and street) to let consumers fill in their shipping address with the PostNL app.', 'postnl-for-woocommerce' ),
				'id'      => 'postnl_consent_checkbox',
				'type'    => 'checkbox',
				'default' => 'no',
			),
		);

		return $settings;
	}

	/**
	 * Render the live button preview field.
	 *
	 * Registered as a static callback so the hook survives this class being
	 * instantiated more than once per request without rendering duplicates.
	 *
	 * @return void
	 */
	public static function render_preview_field(): void {
		$background_color = sanitize_hex_color( get_option( 'postnl_button_background_color', '#ff6200' ) );
		$border           = sanitize_text_field( get_option( 'postnl_button_border', 'none' ) );
		$border_radius    = absint( get_option( 'postnl_button_border_radius', '4' ) );
		$logo_url         = POSTNL_WC_PLUGIN_DIR_URL . '/assets/images/postnl-logo.svg';

		// Built raw: the whole declaration list is escaped once at the point of output.
		$button_style = 'background-color:' . $background_color . ';'
			. 'border:' . $border . ';'
			. 'border-radius:' . $border_radius . 'px;';
		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php esc_html_e( 'Button preview', 'postnl-for-woocommerce' ); ?></th>
			<td class="forminp">
				<div style="max-width:320px;">
					<button
						id="postnl-button-preview"
						type="button"
						style="<?php echo esc_attr( $button_style ); ?>min-height:35px;cursor:default;display:flex;align-items:center;justify-content:center;padding:5px 10px;color:#fff;font-size:16px;width:100%;box-sizing:border-box;transition:background-color 0.2s ease;"
					>
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="" style="height:20px;margin-right:8px;vertical-align:middle;" />
						<span><?php esc_html_e( 'Autofill with PostNL', 'postnl-for-woocommerce' ); ?></span>
					</button>
					<p style="font-size:0.85em;margin-top:6px;color:#646970;">
						<?php esc_html_e( 'Your shipping details are filled in automatically via your PostNL account. That saves time and hassle.', 'postnl-for-woocommerce' ); ?>
					</p>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Enqueue admin scripts.
	 * This method is used to load the CodeMirror editor for custom CSS in the settings page.
	 *
	 * @param string $hook The current admin page hook.
	 *
	 * @return void
	 */
	public function enqueue_admin_scripts( $hook ): void {
		if ( 'woocommerce_page_wc-settings' !== $hook || ! isset( $_GET['section'] ) || 'fill-in-with-postnl' !== $_GET['section'] ) {
			return;
		}

		// Load WP's built-in CodeMirror.
		wp_enqueue_code_editor( array( 'type' => 'text/css' ) );
		wp_enqueue_script( 'wp-theme-plugin-editor' );
		wp_enqueue_style( 'wp-codemirror' );

		wp_enqueue_script(
			'postnl-admin-fill-in-with-postnl-settings',
			POSTNL_WC_PLUGIN_DIR_URL . '/assets/js/admin-fill-in-with-postnl-settings.js',
			array( 'jquery' ),
			POSTNL_WC_VERSION,
			true
		);
	}

	/**
	 * Prevent saving if required conditions aren't met.
	 *
	 * @param mixed  $value The value to be saved.
	 * @param array  $option Option details.
	 * @param string $raw_value Raw (unsanitized) value.
	 *
	 * @return mixed
	 */
	public function maybe_prevent_saving_invalid_data( $value, $option, $raw_value ) {
		static $validation_done = false;

		// Run only for settings prefixed with 'postnl_'.
		if ( false === strpos( $option['id'], 'postnl_' ) ) {
			return $value;
		}

		// Clamp numeric range options to their allowed bounds — the HTML "max"
		// attribute is only a client-side hint and can be bypassed on save.
		$numeric_ranges = array(
			'postnl_cart_button_width'     => array( 1, 100 ),
			'postnl_checkout_button_width' => array( 1, 100 ),
			'postnl_minicart_button_width' => array( 1, 100 ),
			'postnl_button_border_radius'  => array( 0, 50 ),
		);

		if ( isset( $numeric_ranges[ $option['id'] ] ) ) {
			list( $min, $max ) = $numeric_ranges[ $option['id'] ];

			// intval(), not absint(): absint() reflects a negative into range
			// (-500 would become a 100% width) instead of clamping to $min.
			return max( $min, min( $max, intval( $value ) ) );
		}

		// Only run the cross-field checks below once per settings save.
		if ( $validation_done ) {
			return $value;
		}

		$validation_done = true;

		// Validation only needed if 'Fill in with PostNL' is enabled.
		$is_enabled = isset( $_POST['postnl_enable_fill_in_with'] );
		if ( ! $is_enabled ) {
			return $value;
		}

		// Get checkbox values.
		$cart_checked     = isset( $_POST['postnl_cart_auto_render_button'] );
		$checkout_checked = isset( $_POST['postnl_checkout_auto_render_button'] );
		$minicart_checked = isset( $_POST['postnl_minicart_auto_render_button'] );

		// If all are disabled, add error.
		if ( ! $cart_checked && ! $checkout_checked && ! $minicart_checked ) {
			WC_Admin_Settings::add_error(
				esc_html__( 'You must enable at least one "Automatically render button" option in Cart, Checkout, or Minicart.', 'postnl-for-woocommerce' )
			);

			return get_option( $option['id'], $option['default'] ?? '' );
		}

		return $value;
	}

	/**
	 * Check if the 'Fill in with PostNL' feature is enabled and the Client ID is set.
	 *
	 * @return Bool
	 */
	public function is_fill_in_with_postnl_enabled() {
		return ( 'yes' === get_option( 'postnl_enable_fill_in_with', 'no' ) )
			&& ! empty( $this->get_client_id() );
	}

	/**
	 * Check if the 'Fill in with PostNL' feature is enabled for checkout.
	 *
	 * @return bool
	 */
	public function is_fill_in_with_postnl_enabled_for_checkout(): bool {
		return $this->is_fill_in_with_postnl_enabled() && 'yes' === get_option( 'postnl_checkout_auto_render_button', 'no' );
	}

	/**
	 * Get the Client ID for Fill in with PostNL.
	 *
	 * @return string
	 */
	public function get_client_id(): string {
		return sanitize_text_field( get_option( 'postnl_fill_in_with_client_id', '' ) );
	}

	/**
	 * Get the Redirect URI for Fill in with PostNL.
	 *
	 * @param string $code_challenge The PKCE code challenge.
	 * @param string $state The state parameter for OAuth.
	 *
	 * @return string
	 */
	public function get_redirect_uri( string $state, string $code_challenge = '' ): string {
		$client_id    = $this->get_client_id();
		$callback_url = home_url( '/checkout/default/details/?callback=postnl' );
		$base_url     = 'https://dil-login.postnl.nl/oauth2/authorize';

		$query_args = array(
			'client_id'             => $client_id,
			'redirect_uri'          => $callback_url,
			'response_type'         => 'code',
			'scope'                 => 'base',
			'code_challenge'        => '',
			'code_challenge_method' => 'S256',
			'state'                 => $state,
		);
		return $base_url . '?' . http_build_query( $query_args, '', '&' );
	}

	/**
	 * Get the button placement for a specific context.
	 *
	 * @param string $context The context for which to get the button placement (e.g., 'checkout', 'cart', 'minicart').
	 *
	 * @return string The button placement option value.
	 */
	public function get_button_placement( string $context ): string {
		$option_name = 'postnl_' . $context . '_button_placement';
		return get_option( $option_name, '' );
	}
}
