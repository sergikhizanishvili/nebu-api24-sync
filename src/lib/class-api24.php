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
	 * Run complete synchronization process.
	 *
	 * @return void
	 * @throws Exception If something goes wrong.
	 * @since 1.0.0
	 */
	public function run_full_sync(): void {
		$this->log( 'Starting full synchronization process' );

		$this->log( 'Step 1: Syncing categories from API24' );
		$this->categories();

		$this->log( 'Step 2: Syncing products from API24' );
		$this->products();

		$this->log( 'Step 3: Creating WooCommerce products' );
		$this->create_products();

		$this->log( 'Step 4: Cleaning up empty categories' );
		$this->delete_empty_api24_categories();

		$this->log( 'Full synchronization process completed successfully' );
	}

	/**
	 * Sync product stock and prices from API24 database to WooCommerce.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function sync_product_data(): void {
		$this->log( 'Starting product data synchronization process' );

		$this->log( 'Step 1: Syncing products from API24' );
		$this->products();

		$this->log( 'Step 2: Updating WooCommerce product data' );
		$this->update_woocommerce_product_data();

		$this->log( 'Product data synchronization completed successfully' );
	}

	/**
	 * Update WooCommerce product data from API24 database.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function update_woocommerce_product_data(): void {
		$api24_products = DB::get_all_products();

		if ( empty( $api24_products ) ) {
			$this->log( 'No products found in API24 database to sync' );
			return;
		}

		$updated_count = 0;
		$skipped_count = 0;

		foreach ( $api24_products as $api24_product ) {
			$sku = $api24_product['sku'] ?? '';

			if ( empty( $sku ) ) {
				++$skipped_count;
				continue;
			}

			$wc_product_id = wc_get_product_id_by_sku( $sku );

			if ( ! $wc_product_id ) {
				$this->log( sprintf( 'WooCommerce product not found for SKU: %s', $sku ) );
				++$skipped_count;
				continue;
			}

			$wc_product = wc_get_product( $wc_product_id );

			if ( ! $wc_product ) {
				$this->log( sprintf( 'Failed to load WooCommerce product for SKU: %s', $sku ) );
				++$skipped_count;
				continue;
			}

			$updated = $this->update_single_product_data( $wc_product, $api24_product );

			if ( $updated ) {
				++$updated_count;
				$this->log( sprintf( 'Updated product data for SKU: %s', $sku ) );
			} else {
				++$skipped_count;
			}
		}

		$this->log( sprintf( 'Product data sync completed. Updated: %d, Skipped: %d', $updated_count, $skipped_count ) );
	}

	/**
	 * Update a single WooCommerce product with API24 data.
	 *
	 * @param \WC_Product $wc_product The WooCommerce product.
	 * @param array       $api24_product The API24 product data.
	 * @return bool True if updated, false otherwise.
	 * @since 1.0.0
	 */
	private function update_single_product_data( \WC_Product $wc_product, array $api24_product ): bool {
		$updated = false;

		$stock = (int) ( $api24_product['stock'] ?? 0 );
		if ( $wc_product->get_stock_quantity() !== $stock ) {
			$wc_product->set_stock_quantity( $stock );
			$wc_product->set_stock_status( $stock > 0 ? 'instock' : 'outofstock' );
			$updated = true;
		}

		$price = (float) ( $api24_product['price'] ?? 0 );
		if ( $price > 0 && (float) $wc_product->get_regular_price() !== $price ) {
			$wc_product->set_regular_price( $price );
			$updated = true;
		}

		$sale_price = (float) ( $api24_product['sale_price'] ?? 0 );
		if ( $sale_price > 0 && (float) $wc_product->get_sale_price() !== $sale_price ) {
			$wc_product->set_sale_price( $sale_price );
			$updated = true;
		}

		if ( $updated ) {
			$wc_product->save();
		}

		return $updated;
	}

	/**
	 * Delete API24 categories that have no products assigned.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function delete_empty_api24_categories(): void {
		$terms = get_terms(
			[
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'meta_query' => [ // phpcs:ignore
					'relation' => 'AND',
					[
						'key'     => 'category_api24_id',
						'value'   => '',
						'compare' => '!=',
					],
					[
						'key'     => 'category_api24_id',
						'compare' => 'EXISTS',
					],
				],
			]
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			$this->log( 'No API24 categories found to check for deletion' );
			return;
		}

		$deleted_count = 0;
		foreach ( $terms as $term ) {
			$product_count = $this->get_category_product_count( $term->term_id );

			if ( 0 === $product_count ) {
				$category_api24_id = get_field( 'category_api24_id', 'term_' . $term->term_id );
				$result            = wp_delete_term( $term->term_id, 'product_cat' );

				if ( ! is_wp_error( $result ) && $result ) {
					++$deleted_count;
					$this->log( sprintf( 'Deleted empty category: %s (API24 ID: %s)', $term->name, $category_api24_id ) );
				} else {
					$this->log( sprintf( 'Failed to delete category: %s (API24 ID: %s)', $term->name, $category_api24_id ) );
				}
			}
		}

		$this->log( sprintf( 'Deleted %d empty API24 categories', $deleted_count ) );
	}

	/**
	 * Get the number of products in a category (including child categories).
	 *
	 * @param int $term_id The category term ID.
	 * @return int
	 * @since 1.0.0
	 */
	private function get_category_product_count( int $term_id ): int {
		$child_terms = get_term_children( $term_id, 'product_cat' );
		$term_ids    = [ $term_id ];

		if ( ! is_wp_error( $child_terms ) && ! empty( $child_terms ) ) {
			$term_ids = array_merge( $term_ids, $child_terms );
		}

		$products = get_posts(
			[
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'tax_query'      => [ // phpcs:ignore
					[
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => $term_ids,
					],
				],
			]
		);

		return count( $products );
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
