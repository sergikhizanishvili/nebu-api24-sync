<?php
/**
 * Plugin Name:          Nebu API24 Sync
 * Plugin URI:           https://nebu.ge
 * Description:          API24 synchronization between Nebu and WooCommerce.
 * Version:              1.0.0
 * Author:               Sergi Khizanishvili
 * Author URI:           https://sweb.ge/
 * Requires Plugins:     woocommerce
 * Requires at least:    6.0
 * Tested up to:         6.7
 * WC requires at least: 9.0
 * WC tested up to:      9.5
 * Text Domain:          nebu-api24
 * Domain Path:          /languages
 *
 * @link                 https://nebu.ge
 * @package              Nebu API24 Sync
 * @since                1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version.
 *
 * @var string
 * @since 1.0.0
 */
define( 'NEBU_API24_VERSION', '1.0.0' ); // WRCS: DEFINED_VERSION.

/**
 * Plugin main file.
 *
 * @var string
 * @since 1.0.0
 */
define( 'NEBU_API24_PLUGIN_FILE', __FILE__ );

/**
 * Plugin path.
 *
 * @var string
 * @since 1.0.0
 */
define( 'NEBU_API24_PLUGIN_PATH', untrailingslashit( plugin_dir_path( NEBU_API24_PLUGIN_FILE ) ) );

/**
 * Plugin URL.
 *
 * @var string
 * @since 1.0.0
 */
define( 'NEBU_API24_PLUGIN_URL', untrailingslashit( plugin_dir_url( NEBU_API24_PLUGIN_FILE ) ) );

/**
 * WooCommerce minimum version.
 *
 * @var string
 * @since 1.0.0
 */
define( 'NEBU_API24_WC_MIN_VERSION', '9.0' );

/**
 * Load everything via composer.
 *
 * @since 1.0.0
 */
require_once NEBU_API24_PLUGIN_PATH . '/vendor/autoload.php';

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 */
new Nebu\API24\Init();
