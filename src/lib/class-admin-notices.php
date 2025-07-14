<?php
/**
 * Admin notices class.
 *
 * @package Nebu API24 Sync
 * @since 1.0.0
 */

namespace Nebu\API24\Lib;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin notices class.
 *
 * @since 1.0.0
 */
class Admin_Notices {
	/**
	 * WooCommerce fallback notice.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function missing_wc_notice(): void {
		$install_url = wp_nonce_url(
			add_query_arg(
				[
					'action' => 'install-plugin',
					'plugin' => 'woocommerce',
				],
				admin_url( 'update.php' )
			),
			'install-plugin_woocommerce'
		);

		$admin_notice_content = sprintf(
			// translators: 1$-2$: opening and closing <strong> tags, 3$-4$: link tags, takes to woocommerce plugin on wp.org, 5$-6$: opening and closing link tags, leads to plugins.php in admin.
			esc_html__( '%1$sNebu API24 Sync is inactive.%2$s The %3$sWooCommerce plugin%4$s must be active for this plugin to work. Please %5$sinstall & activate WooCommerce &raquo;%6$s', 'nebu-api24' ),
			'<strong>',
			'</strong>',
			'<a href="http://wordpress.org/extend/plugins/woocommerce/">',
			'</a>',
			'<a href="' . esc_url( $install_url ) . '">',
			'</a>'
		);

		echo '<div class="error">';
		echo '<p>' . wp_kses_post( $admin_notice_content ) . '</p>';
		echo '</div>';
	}

	/**
	 * WooCommerce version not supported notice.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function wc_version_notice() {

		$admin_notice_content = sprintf(
			// translators: $1. Minimum WooCommerce version. $2. Current WooCommerce version.
			esc_html__( 'Nebu API24 Sync requires WooCommerce %1$s or greater to be installed and active. WooCommerce %2$s is not supported.', 'nebu-api24' ),
			esc_html( NEBU_API24_WC_MIN_VERSION ),
			esc_html( WC_VERSION )
		);

		echo '<div class="error">';
		echo '<p>' . wp_kses_post( $admin_notice_content ) . '</p>';
		echo '</div>';
	}

	/**
	 * Not configured notice.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function not_configured_notice(): void {

		$admin_notice_content = sprintf(
			// translators: 1$-2$: opening and closing <strong> tags.
			esc_html__( '%1$sNebu API24 Sync%2$s is not configured. Please configure it here: %3$sNebu API24 Sync settings%4$s', 'nebu-api24' ),
			'<strong>',
			'</strong>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=nebu-api24-settings' ) ) . '">',
			'</a>'
		);

		echo '<div class="error">';
		echo '<p>' . wp_kses_post( $admin_notice_content ) . '</p>';
		echo '</div>';
	}
}
