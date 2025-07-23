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
use Nebu\API24\Lib\DB;
use Nebu\API24\Lib\Utils;
use Nebu\API24\Lib\Product_Factory;
use Nebu\API24\Lib\Product_Data_Builder;

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
	 * Merchant ID for the API.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private string $merchant;

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
		$this->merchant = isset( $settings['merchant'] ) ? $settings['merchant'] : '';
		$this->debug    = isset( $settings['debug'] ) && 'yes' === $settings['debug'] ? true : false;

		if (
			empty( $this->base_url ) ||
			empty( $this->token ) ||
			empty( $this->merchant )
		) {
			$this->log( 'Nebu API24 Sync plugin is not configured.' );
			throw new Exception( esc_attr__( 'Nebu API24 Sync plugin is not configured.', 'nebu-api24' ) );
		}
	}

	/**
	 * Get API24 categories and save them to the database.
	 *
	 * @return void
	 * @throws Exception If the API request fails.
	 * @since 1.0.0
	 */
	public function categories(): void {
		$response = wp_remote_get(
			$this->base_url . '/basedata/categories?showAll=true',
			[
				'headers' => [
					'AccessToken'  => $this->token,
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new Exception( esc_html__( 'Error fetching categories from API24.', 'nebu-api24' ) );
		}

		$categories = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $categories ) ) {
			throw new Exception( esc_html__( 'No categories found in the API24 response.', 'nebu-api24' ) );
		}

		$cats = [];
		foreach ( $categories as $category ) {
			if (
				! isset( $category['id'] ) ||
				empty( $category['id'] ) ||
				! isset( $category['name'] ) ||
				empty( $category['name'] )
			) {
				continue;
			}

			$cats[] = [
				'id'        => $category['id'],
				'name'      => $category['name'],
				'slug'      => Utils::to_slug( $category['name'] ),
				'parent_id' => isset( $category['parentId'] ) ? $category['parentId'] : null,
			];
		}

		$tree = Utils::build_api24_category_tree( $cats );
		if ( empty( $tree ) ) {
			throw new Exception( esc_html__( 'No valid categories found in the API24 response.', 'nebu-api24' ) );
		}

		Utils::process_category_tree( $tree, 'product_cat', 0 );
	}

	/**
	 * Get API24 products and save them to the database.
	 *
	 * @param int $page Page number for pagination.
	 * @return void
	 * @throws Exception If the API request fails or no products are found.
	 * @since 1.0.0
	 */
	public function products( int $page = 1 ): void {

		$response = wp_remote_get(
			$this->base_url . '/products?page=' . $page . '&merchantId=' . $this->merchant,
			[
				'headers' => [
					'AccessToken'  => $this->token,
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new Exception( esc_html__( 'Error fetching products from API24.', 'nebu-api24' ) );
		}

		$products = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $products ) ) {
			return;
		}

		foreach ( $products as $product ) {
			$data = Product_Data_Builder::build( $product );
			if ( empty( $data ) ) {
				continue;
			}

			DB::update_or_create_product( $data );
		}

		$this->products( $page + 1 );
	}

	/**
	 * Start creating products from the API.
	 *
	 * @return void
	 * @throws Exception If something goes wrong.
	 * @since 1.0.0
	 */
	public function create_products(): void {
		$factory = new Product_Factory( [ $this, 'log' ] );
		$factory->create_products();
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
