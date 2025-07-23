<?php
/**
 * Initialization class for the plugin.
 *
 * @package Nebu API24 Sync
 * @since   1.0.0
 */

namespace Nebu\API24;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Exception;
use Nebu\API24\Lib\API24;
use Nebu\API24\Lib\Admin_Notices;
use Nebu\API24\Lib\Admin_Settings;
use Automattic\WooCommerce\Utilities\FeaturesUtil;
use Nebu\API24\Lib\DB;

/**
 * The plugin initialization class.
 *
 * @since 1.0.0
 */
class Init {
	/**
	 * Localisation files directory.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public string $languages_dir;

	/**
	 * Setup class.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		/**
		 * Localisation files directory.
		 */
		$this->languages_dir = NEBU_API24_PLUGIN_PATH . '/languages';

		/**
		 * Initialize the plugin on plugins_loaded.
		 * Run required checks.
		 *
		 * @since 1.0.0
		 */
		add_action( 'plugins_loaded', [ $this, 'init' ] );

		/**
		 * Load plugin dependencies.
		 *
		 * @since 1.0.0
		 */
		add_action( 'plugins_loaded', [ $this, 'dependencies' ] );

		/**
		 * Add support for custom order tables and blocks.
		 *
		 * @since 1.0.0
		 */
		add_action( 'before_woocommerce_init', [ $this, 'supports' ] );
	}

	/**
	 * Initialize the gateway.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function init(): void {
		/**
		 * Load localization files.
		 *
		 * @since 1.0.0
		 */
		load_plugin_textdomain( 'nebu-api24', false, basename( NEBU_API24_PLUGIN_PATH ) . '/languages' );

		/**
		 * Check if WooCommerce is active.
		 *
		 * @since 1.0.0
		 */
		if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Payment_Gateway' ) ) {
			add_action( 'admin_notices', [ Admin_Notices::class, 'missing_wc_notice' ] );
			return;
		}

		/**
		 * Check if WooCommerce version is supported.
		 *
		 * @since 1.0.0
		 */
		if ( version_compare( WC_VERSION, NEBU_API24_WC_MIN_VERSION, '<' ) ) {
			add_action( 'admin_notices', [ Admin_Notices::class, 'wc_version_notice' ] );
			return;
		}

		/**
		 * Check if the plugin is configured.
		 *
		 * @since 1.0.0
		 */
		if ( class_exists( API24::class ) ) {
			$settings = maybe_unserialize( get_option( 'nebu_api24_settings', [] ) );
			try {
				$api_24 = new API24( $settings );
			} catch ( Exception $e ) {
				add_action( 'admin_notices', [ Admin_Notices::class, 'not_configured_notice' ] );
				return;
			}
		}
	}

	/**
	 * Add support for custom order tables and blocks.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function supports(): void {
		if ( class_exists( FeaturesUtil::class ) ) {
			FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				NEBU_API24_PLUGIN_FILE,
				true
			);
		}
	}

	/**
	 * Load plugin dependencies.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function dependencies(): void {
		new Admin_Settings();
		new DB();
	}
}
