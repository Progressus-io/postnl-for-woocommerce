<?php
/**
 * Adds custom links to the plugin row on the Plugins screen.
 *
 * @package PostNLWooCommerce\Admin
 */

namespace PostNLWooCommerce\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Class PluginLinks
 */
class Plugin_Links {

	/**
	 * Basenames targeted for link injection.
	 *
	 * @var string[]
	 */
	private array $basenames;

	/**
	 * Constructor.
	 *
	 * @param string $own_basename Basename of this plugin.
	 * @param string[] $additional_basename Optional additional basenames.
	 */
	public function __construct( string $own_basename, array $additional_basename = [] ) {
		$this->basenames = array_unique( array_merge( [ $own_basename ], $additional_basename ) );
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		if ( ! is_admin() ) {
			return;
		}

		add_filter( 'plugin_row_meta', [ $this, 'add_row_meta' ], 10, 2 );

		foreach ( $this->basenames as $basename ) {
			add_filter( "plugin_action_links_{$basename}", [ $this, 'add_action_links' ], 10, 1 );
		}
	}

	/**
	 * Add row meta links.
	 *
	 * @param string[] $links Existing links.
	 * @param string $file Plugin file name.
	 *
	 * @return string[]
	 */
	public function add_row_meta( array $links, string $file ): array {
		if ( in_array( $file, $this->basenames, true ) ) {
			$links[] = sprintf(
				'<a href="%s" target="_blank" rel="noopener">%s</a>',
				esc_url( 'https://wordpress.org/support/plugin/woo-postnl/reviews/#new-post' ),
				esc_html__( 'Leave a review', 'postnl-for-woocommerce' )
			);
		}

		return $links;
	}

	/**
	 * Add action links.
	 *
	 * @param string[] $links Existing links.
	 *
	 * @return string[]
	 */
	public function add_action_links( array $links ): array {
		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping&section=postnl' ) ),
			esc_html__( 'Settings', 'postnl-for-woocommerce' )
		);

		$links[] = sprintf(
			'<a href="%s" target="_blank" rel="noopener">%s</a>',
			esc_url( 'https://wordpress.org/support/plugin/woo-postnl/reviews/#new-post' ),
			esc_html__( 'Leave a review', 'postnl-for-woocommerce' )
		);

		return $links;
	}
}
