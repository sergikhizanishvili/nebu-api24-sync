<?php
/**
 * Class for handling API24.
 *
 * @package Nebu API24 Sync
 * @since   1.0.0
 */

namespace Nebu\API24\Lib;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Exception;

/**
 * Class for handling API24.
 *
 * @since 1.0.0
 */
class API24 {
	/**
	 * Base URL for the API.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private string $base_url;

	/**
	 * Access token for the API.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private string $token;

	/**
	 * Debug mode.
	 *
	 * @var bool
	 * @since 1.0.0
	 */
	public bool $debug = false;

	/**
	 * Setup the class.
	 *
	 * @throws Exception If the app ID or app secret are empty.
	 * @since 1.0.0
	 */
	public function __construct() {

		$settings = maybe_unserialize( get_option( 'nebu_api24_settings', [] ) );

		$this->base_url = isset( $settings['base_url'] ) ? $settings['base_url'] : '';
		$this->token    = isset( $settings['token'] ) ? $settings['token'] : '';
		$this->debug    = isset( $settings['debug'] ) && 'yes' === $settings['debug'] ? true : false;

		if (
			empty( $this->base_url ) ||
			empty( $this->token )
		) {
			$this->log( 'Nebu API24 Sync plugin is not configured.' );
			throw new Exception( esc_attr__( 'Nebu API24 Sync plugin is not configured.', 'nebu-api24' ) );
		}
	}

	/**
	 * Get categories.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function categories(): array {
		$this->log( 'Fetching categories...' );
		$response = wp_remote_get(
			$this->base_url . '/basedata/categories',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $this->token,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				],
			]
		);

		if ( ! $response || is_wp_error( $response ) ) {
			$this->log( 'Error fetching categories.' );
			return [];
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$this->log( 'Categories: ' . wp_remote_retrieve_body( $response ) );
		if ( ! $body ) {
			$this->log( 'Response is empty.' );
			return [];
		}

		return $body;
	}

	/**
	 * Get products.
	 *
	 * @param ?string $product_id Optional product ID to filter products.
	 * @return array
	 * @since 1.0.0
	 */
	public function products( ?string $product_id = null ): array {

		if ( ! empty( $product_id ) ) {
			$product = wc_get_product( $product_id );
			if ( empty( $product ) ) {
				$this->log( 'No product found with ID: ' . $product_id );
				return [];
			}

			$barcode = $product->get_meta( '_nebu_barcode', true );
			if ( empty( $barcode ) ) {
				$this->log( 'No barcode found for product ID: ' . $product_id );
				return [];
			}

			$this->log( 'Fetching product with ID: ' . $product_id . ' and barcode: ' . $barcode );
		}

		$this->log( 'Fetching product(s)...' );
		$response = wp_remote_get(
			$this->base_url . '/products' . ( ! empty( $product_id ) ? '?barcodes=' . $barcode : '' ),
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $this->token,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				],
			]
		);

		if ( ! $response || is_wp_error( $response ) ) {
			$this->log( 'Error fetching product(s).' );
			return [];
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$this->log( 'Product(s): ' . wp_remote_retrieve_body( $response ) );
		if ( ! $body ) {
			$this->log( 'Response is empty.' );
			return [];
		}

		return $body;
	}

	/**
	 * Write WC log.
	 *
	 * @param string $message Message to log.
	 * @return void
	 * @since 1.0.0
	 */
	public function log( string $message ): void {
		if ( ! function_exists( 'wc_get_logger' ) || ! $this->debug ) {
			return;
		}

		$logger = wc_get_logger();
		$logger->debug( $message, [ 'source' => 'nebu-api24-sync' ] );
	}
}
