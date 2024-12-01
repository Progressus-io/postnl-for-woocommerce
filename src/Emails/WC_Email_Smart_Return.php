<?php
/**
 * Class Emails\WC_Email_Smart_Return file
 *
 * @package WooCommerce\Emails
 */

namespace PostNLWooCommerce\Emails;

use WC_Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Email_Smart_Return' ) ) :

	/**
	 * Smart Return Email.
	 *
	 * An email sent to the user when smart return button clicked.
	 *
	 * @class       WC_Email_Smart_Return
	 * @version     1.0.0
	 * @package     PostNLWooCommerce\Emails
	 * @extends     WC_Email
	 */
	class WC_Email_Smart_Return extends WC_Email {
		// Define the attachment property
		public $attachment;

		public function __construct() {
			// Set email ID, title, description, and other options.
			$this->id             = 'wc_smart_return_email';
			$this->customer_email = true;
			$this->title          = __( 'Smart Return Email', 'postnl-for-woocommerce' );
			$this->description    = __( 'This is a smart return email sent for return purposes.', 'postnl-for-woocommerce' );

			// The email template file in your plugin.
			$this->template_html  = 'emails/smart-return-email.php';
			$this->template_plain = 'emails/plain/smart-return-email.php';
			$this->template_base  = POSTNL_WC_PLUGIN_DIR_PATH . '/templates/';

			// Call parent constructor.
			parent::__construct();

		}

		/**
		 * Get email subject.
		 *
		 * @return string
		 * @since  3.1.0
		 */
		public function get_default_subject() {
			return __( '[{site_title}]: PostNL Smart Returns', 'postnl-for-woocommerce' );
		}

		/**
		 * Get email heading.
		 *
		 * @return string
		 * @since  3.1.0
		 */
		public function get_default_heading() {
			return __( 'PostNL Smart Returns', 'postnl-for-woocommerce' );
		}

		/**
		 * Trigger.
		 *
		 * @param int $order_id The order ID.
		 */
		public function trigger( $order_id ) {
			$this->object = wc_get_order( $order_id );

			if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
				return;
			}

			$this->setup_locale();
			$sent = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			$this->restore_locale();

			return $sent;
		}


		/**
		 * Get the email content in HTML format.
		 *
		 * @return string
		 */
		public function get_content_html() {
			return wc_get_template_html( $this->template_html, array(
				'order'              => $this->object,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => false,
				'plain_text'         => false,
				'email'              => $this,
			), '', $this->template_base );
		}

		/**
		 * Get the email content in plain text format.
		 *
		 * @return string
		 */
		public function get_content_plain() {
			return wc_get_template_html( $this->template_plain, array(
				'order'              => $this->object,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => false,
				'plain_text'         => true,
				'email'              => $this,
			), '', $this->template_base );
		}

		/**
		 * Get email attachments.
		 *
		 * @return array
		 */
		public function get_attachments() {
			// Start with an empty attachments array
			$attachments = array();

			// Add your custom attachment file path, if provided and if the file exists
			if ( ! empty( $this->attachment ) && file_exists( $this->attachment ) ) {
				$attachments[] = $this->attachment;
			}

			// Allow other code to modify or add attachments
			return apply_filters( 'woocommerce_email_attachments', $attachments, $this->id, $this->object, $this );
		}

	}

endif;
