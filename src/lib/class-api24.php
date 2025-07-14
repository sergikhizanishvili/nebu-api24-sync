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
use WC_Product;
use Nebu\API24\Lib\DB;

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
			$data = $this->build_product_data( $product );
			if ( empty( $data ) ) {
				continue;
			}

			DB::update_or_create_product( $data );
		}

		$this->products( $page + 1 );
	}

	/**
	 * Populate product data from API24.
	 *
	 * @param array $product Product data from the API.
	 * @return array
	 * @since 1.0.0
	 */
	public function build_product_data( array $product ): array {
		/**
		 * Check if the product has a valid price. If not, skip it.
		 */
		$price = isset( $product['originalPrice'] ) ? (float) $product['originalPrice'] : 0;
		$price = $price > 0 ? $price : 0;
		if ( ! $price ) {
			return [];
		}

		/**
		 * Set sale price and B2B price.
		 */
		$sale_price = isset( $product['salePrice'] ) ? (float) $product['salePrice'] : $price;
		$sale_price = $sale_price > 0 ? $sale_price : $price;
		$b2b_price  = isset( $product['b2bPrice'] ) ? (float) $product['b2bPrice'] : $price;
		$b2b_price  = $b2b_price > 0 ? $b2b_price : $price;

		/**
		 * Populate gallery images.
		 */
		$gallery = isset( $product['gallery'] ) && is_array( $product['gallery'] ) ? $product['gallery'] : [];
		$images  = [];
		foreach ( $gallery as $image ) {
			if (
				! isset( $image['big'] ) ||
				empty( $image['big'] )
			) {
				continue;
			}

			$images[] = $image['big'];
		}

		/**
		 * Populate attributes.
		 * Including color as a separate attribute if it exists.
		 */
		$attrs      = [];
		$attributes = isset( $product['attributes'] ) && is_array( $product['attributes'] ) ? $product['attributes'] : [];
		foreach ( $attributes as $attribute ) {
			if (
				! isset( $attribute['name'] ) ||
				empty( $attribute['name'] ) ||
				! isset( $attribute['value'] ) ||
				empty( $attribute['value'] )
			) {
				continue;
			}

			$attrs[] = [
				'name'  => $attribute['name'],
				'value' => $attribute['value'],
				'slug'  => Utils::to_slug( $attribute['name'] ),
			];
		}

		$color = isset( $product['color'] ) && ! empty( $product['color'] ) ? $product['color'] : null;
		if ( ! empty( $color ) ) {
			$attrs[] = [
				'name'  => 'ფერი',
				'value' => $color,
				'slug'  => 'color',
			];
		}

		/**
		 * Product name.
		 */
		if ( ! isset( $product['name'] ) || empty( $product['name'] ) ) {
			return [];
		}

		/**
		 * Barcode.
		 */
		$barcode = isset( $product['barcode'] ) && ! empty( $product['barcode'] ) ? $product['barcode'] : '';
		// $barcode = explode( '_', $barcode );
		// $barcode = isset( $barcode[0] ) ? $barcode[0] : '';
		// if ( empty( $barcode ) ) {
		// 	return [];
		// }

		/**
		 * Populate product data.
		 */
		$data = [
			'product_id'  => $product['productId'] ?? null,
			'category_id' => $product['categoryId'] ?? null,
			'name'        => $product['name'],
			'description' => $product['description'] ?? '',
			'sku'         => $product['sku'] ?? null,
			'barcode'     => $barcode,
			'stock'       => $product['stockQuantity'] ?? 0,
			'price'       => $price,
			'sale_price'  => $sale_price,
			'b2b_price'   => $b2b_price,
			'main_image'  => $product['imageUrl'] ?? null,
			'images'      => wp_json_encode( $images ),
			'attributes'  => wp_json_encode( $attrs ),
			'model'       => $product['modelNo'] ?? '',
		];

		foreach ( $data as $value ) {
			if ( is_null( $value ) ) {
				return [];
			}
		}

		return $data;
	}

	/**
	 * Start creating products from the API.
	 *
	 * @return void
	 * @throws Exception If something goes wrong.
	 * @since 1.0.0
	 */
	public function create_products(): void {
		$barcodes = DB::get_barcodes();
		if ( empty( $barcodes ) ) {
			throw new Exception( esc_html__( 'No barcodes found in the database.', 'nebu-api24' ) );
		}

		foreach ( $barcodes as $barcode ) {
			$products = DB::get_products_by_barcode( $barcode );
			if ( empty( $products ) ) {
				continue;
			}

			$wc_products = wc_get_products(
				[
					'limit'      => -1,
					'meta_query' => [ // phpcs:ignore
						[
							'key'     => 'barcode',
							'value'   => $barcode,
							'compare' => '=',
						],
					],
				]
			);

			if ( ! empty( $wc_products ) ) {
				$wc_product = $wc_products[0];
			} else {
				$wc_product = $this->create_wc_product( $products );
			}

			if ( ! $wc_product || is_wp_error( $wc_product ) ) {
				continue;
			}
		}
	}

	/**
	 * Create a new product in WooCommerce.
	 *
	 * @param array $data Products data.
	 * @return ?int The ID of the created product.
	 * @since 1.0.0
	 */
	public function create_wc_product( array $data = [] ): ?int {
		/**
		 * Create variable product.
		 * Take Main data from first product in the array.
		 *
		 */
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
