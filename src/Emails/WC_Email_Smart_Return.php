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
		/**
		 * Attachment property.
		 *
		 * @var string
		 */
		public string $attachment = '';

		/**
		 * Raw (decoded) printcode image bytes to embed inline in the email body.
		 *
		 * Set for the V4 retailPrint Smart Return, whose printcode is shown in the
		 * body instead of a PDF attachment. Empty for the legacy PDF path.
		 *
		 * @var string
		 */
		public string $printcode_content = '';

		/**
		 * MIME type of the inline printcode image (e.g. image/png).
		 *
		 * @var string
		 */
		public string $printcode_mime = '';

		/**
		 * Return barcode shown as text, a fallback when the inline image is blocked
		 * or the email is rendered as plain text.
		 *
		 * @var string
		 */
		public string $printcode_barcode = '';

		/**
		 * Content-ID used to reference the embedded printcode image from the template.
		 *
		 * @var string
		 */
		public string $printcode_cid = 'postnl_smart_return_printcode';

		/**
		 * Constructor.
		 */
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
		public function trigger( int $order_id ) {
			$this->object = wc_get_order( $order_id );

			if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
				return;
			}

			$this->setup_locale();

			// Embed the retailPrint printcode image inline (via cid) for the V4 path so
			// the consumer sees the barcode in the body — PostNL asks for the printcode,
			// not a PDF. The hook is scoped to this send only.
			$embed_printcode = '' !== $this->printcode_content;
			if ( $embed_printcode ) {
				add_action( 'phpmailer_init', array( $this, 'embed_printcode_image' ) );
			}

			$sent = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );

			if ( $embed_printcode ) {
				remove_action( 'phpmailer_init', array( $this, 'embed_printcode_image' ) );
			}

			$this->restore_locale();

			return $sent;
		}

		/**
		 * Embed the printcode image into the outgoing message as an inline (cid) attachment.
		 *
		 * Hooked onto phpmailer_init only while a V4 printcode is being sent, so the
		 * template's <img src="cid:..."> renders in the body across mail clients that
		 * strip data-URI images.
		 *
		 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer The mailer instance used by wp_mail().
		 * @return void
		 */
		public function embed_printcode_image( $phpmailer ) {
			if ( '' === $this->printcode_content ) {
				return;
			}

			$phpmailer->addStringEmbeddedImage(
				$this->printcode_content,
				$this->printcode_cid,
				'postnl-smart-return-printcode',
				'base64',
				$this->printcode_mime
			);
		}


		/**
		 * Get the email content in HTML format.
		 *
		 * @return string
		 */
		public function get_content_html() {
			return wc_get_template_html(
				$this->template_html,
				array(
					'order'              => $this->object,
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'sent_to_admin'      => false,
					'plain_text'         => false,
					'printcode_cid'      => '' !== $this->printcode_content ? $this->printcode_cid : '',
					'printcode_barcode'  => $this->printcode_barcode,
					'email'              => $this,
				),
				'',
				$this->template_base
			);
		}

		/**
		 * Get the email content in plain text format.
		 *
		 * @return string
		 */
		public function get_content_plain() {
			return wc_get_template_html(
				$this->template_plain,
				array(
					'order'              => $this->object,
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'sent_to_admin'      => false,
					'plain_text'         => true,
					'printcode_cid'      => '' !== $this->printcode_content ? $this->printcode_cid : '',
					'printcode_barcode'  => $this->printcode_barcode,
					'email'              => $this,
				),
				'',
				$this->template_base
			);
		}

		/**
		 * Get email attachments.
		 *
		 * @return array
		 */
		public function get_attachments() {
			// Start with an empty attachments array.
			$attachments = array();

			// Add your custom attachment file path, if provided and if the file exists.
			if ( ! empty( $this->attachment ) && file_exists( $this->attachment ) ) {
				$attachments[] = $this->attachment;
			}

			// Allow other code to modify or add attachments.
			return apply_filters( 'woocommerce_email_attachments', $attachments, $this->id, $this->object, $this );
		}
	}

endif;
