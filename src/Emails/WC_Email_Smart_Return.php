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

    public function __construct() {
        // Set email ID, title, description, and other options.
        $this->id = 'wc_smart_return_email';
        $this->customer_email = true;
        $this->title = __( 'Smart Return Email', 'postnl-for-woocommerce' );
        $this->description = __( 'This is a smart return email sent for return purposes.', 'postnl-for-woocommerce' );
        
        // The email template file in your plugin.
        $this->template_html = 'emails/smart-return-email.php';
        $this->template_plain = 'emails/plain/smart-return-email.php';
        $this->template_base  = POSTNL_WC_PLUGIN_DIR_PATH . '/templates/';

        // Call parent constructor.
		parent::__construct();

    }

    /**
     * Get email subject.
     *
     * @since  3.1.0
     * @return string
     */
    public function get_default_subject() {
        return __( '[{site_title}]: Smart Returns', 'woocommerce' );
    }

    /**
     * Get email heading.
     *
     * @since  3.1.0
     * @return string
     */
    public function get_default_heading() {
        return __( 'Smart Returns', 'woocommerce' );
    }

    // Trigger function - this function fires the email.
    public function trigger( $order_id, $attachment_paths ) {
        if ( ! $order_id ) {
            return;
        }

        $this->object = wc_get_order( $order_id );

        if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
            return;
        }

        // Initialize attachments array.
        $attachments = array();

        // Add the attachments if the file paths are provided and the files exist.
        if ( ! empty( $attachment_paths ) && is_array( $attachment_paths ) ) {
            foreach ( $attachment_paths as $path ) {
                if ( file_exists( $path ) ) {
                    $attachments[] = $path;
                }
            }
        }

        // Send the email.
        $sent = $this->send(
            $this->get_recipient(),
            $this->get_subject(),
            $this->get_content(),
            $this->get_headers(),
            $attachments // Pass the attachments array here.
        );

        return $sent;
        // $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
    }

    // Get HTML content for the email.
    public function get_content_html() {
        return wc_get_template_html( $this->template_html, array(
            'order'         => $this->object,
            'email_heading' => $this->get_heading(),
            'sent_to_admin' => false,
            'plain_text'    => false,
            'email'         => $this,
        ), '', $this->template_base );
    }

    // Get plain text content for the email.
    public function get_content_plain() {
        return wc_get_template_html( $this->template_plain, array(
            'order'         => $this->object,
            'email_heading' => $this->get_heading(),
            'sent_to_admin' => false,
            'plain_text'    => true,
            'email'         => $this,
        ), '', $this->template_base );
    }

}

endif;