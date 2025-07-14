<?php
/**
 * Class for handling products syncronization.
 *
 * @package Nebu API24 Sync
 * @since   1.0.0
 */

namespace Nebu\API24\Lib;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Exception;
use Nebu\API24\Lib\API24;

/**
 * Class for handling products syncronization.
 *
 * @since 1.0.0
 */
class Products_Sync {
	/**
	 * Get products stock and prices.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function sync_products(): void {
		/**
		 * Get all products.
		 */
		$products = get_posts(
			[
				'post_type'      => [ 'product' ],
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [ // phpcs:ignore
					'relation' => 'OR',
					[
						'key'     => '_date_api24_synced',
						'compare' => 'NOT EXISTS',
					],
					[
						'key'     => '_date_api24_synced',
						'value'   => wp_date( 'Ymd' ),
						'compare' => '<',
					],
				],
			]
		);

		try {
			$api_24 = new API24();
		} catch ( Exception $e ) {
			return;
		}

		/**
		 * Sync products.
		 */
		$chunks = array_chunk( $products, 100 );
		foreach ( $chunks as $chunk ) {
			foreach ( $chunk as $product_id ) {
				self::sync_single_product( $product_id, $api_24 );
			}
		}
	}

	/**
	 * Sync single product.
	 *
	 * @param int        $product_id Product ID.
	 * @param API24|null $api_24 API24 instance.
	 * @return void
	 * @since 1.0.0
	 */
	public static function sync_single_product( int $product_id, ?API24 $api_24 = null ): void {

		if ( ! $api_24 ) {
			try {
				$api_24 = new API24();
			} catch ( Exception $e ) {
				return;
			}
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}

		$variations = [];
		if ( $product->is_type( 'variable' ) ) {
			$variations = $product->get_children();
		} else {
			$variations[] = $product_id;
		}

		foreach ( $variations as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation ) {
				continue;
			}

			$sku = $variation->get_sku();
			if ( ! $sku ) {
				continue;
			}

			try {
				$api_product = $api_24->get_product( $sku );
			} catch ( Exception $e ) {
				continue;
			}

			/**
			 * Update product stock.
			 */
			$stock = 0;
			if (
				isset( $api_product['Stock'] ) &&
				is_numeric( $api_product['Stock'] ) &&
				$api_product['Stock'] > 0
			) {
				$stock = (float) $api_product['Stock'];
			}

			$variation->set_manage_stock( true );
			$variation->set_stock_quantity( $stock );
			$variation->set_stock_status( $stock > 0 ? 'instock' : 'outofstock' );

			/**
			 * Update product price.
			 */
			$price = 0;
			if (
				isset( $api_product['Price']['Price'] ) &&
				is_numeric( $api_product['Price']['Price'] ) &&
				$api_product['Price']['Price'] > 0
			) {
				$price = (float) $api_product['Price']['Price'];
			}

			$variation->set_regular_price( $price );
			$variation->set_price( $price );

			/**
			 * Save product.
			 */
			$variation->save();
		}

		/**
		 * Update date synced.
		 */
		update_post_meta( $product_id, '_date_synced', wp_date( 'Ymd' ) );
	}
}
