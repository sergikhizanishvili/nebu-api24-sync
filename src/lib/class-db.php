<?php
/**
 * Class for custom table database operations.
 *
 * @package Nebu API24 Sync
 * @since   1.0.0
 */

namespace Nebu\API24\Lib;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API24 DB class.
 *
 * @since 1.0.0
 */
class DB {
	/**
	 * Setup the class.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wpdb;
		if ( ! defined( 'NEBU_API24_TABLE_PRODUCTS' ) ) {
			define( 'NEBU_API24_TABLE_PRODUCTS', $wpdb->prefix . 'api24_products' );
		}
		$this->migrate();
	}

	/**
	 * Get the product by SKU.
	 *
	 * @param string $sku The SKU of the product.
	 * @return array|null The product data or null if not found.
	 * @since 1.0.0
	 */
	public static function get_product_by_sku( string $sku ): ?array {
		global $wpdb;

		$query = $wpdb->prepare(
			'SELECT * FROM ' . NEBU_API24_TABLE_PRODUCTS . ' WHERE sku = %s', // phpcs:ignore
			$sku
		);

		$product = $wpdb->get_row( $query, ARRAY_A ); // phpcs:ignore
		if ( ! $product ) {
			return null;
		}

		return $product;
	}

	/**
	 * Update product information in the database.
	 *
	 * @param int   $id of the item to update.
	 * @param array $data The product data to update.
	 * @return void
	 * @since 1.0.0
	 */
	public static function update_product( int $id, array $data ): void {
		global $wpdb;

		$wpdb->update( // phpcs:ignore
			NEBU_API24_TABLE_PRODUCTS,
			array_merge(
				$data,
				[
					'updated_at' => current_time( 'mysql', true ),
				]
			),
			[ 'id' => $id ],
			[ '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s' ],
			[ '%d' ]
		);
	}

	/**
	 * Insert product into the database.
	 *
	 * @param array $data The product data to insert.
	 * @return int The inserted product ID.
	 * @since 1.0.0
	 */
	public static function insert_product( array $data ): int {
		global $wpdb;

		$wpdb->insert( // phpcs:ignore
			NEBU_API24_TABLE_PRODUCTS,
			array_merge(
				$data,
				[
					'created_at' => current_time( 'mysql', true ),
					'updated_at' => current_time( 'mysql', true ),
				]
			),
			[ '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s' ],
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update or create product in the database.
	 *
	 * @param array $data The product data to update or insert.
	 * @since 1.0.0
	 */
	public static function update_or_create_product( array $data ): void {
		$product = self::get_product_by_sku( $data['sku'] );
		if ( $product ) {
			self::update_product( $product['id'], $data );
		} else {
			self::insert_product( $data );
		}
	}

	/**
	 * Get barcodes array from the products table.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_barcodes(): array {
		global $wpdb;

		$query = 'SELECT barcode FROM ' . NEBU_API24_TABLE_PRODUCTS; // phpcs:ignore
		$results = $wpdb->get_col( $query ); // phpcs:ignore
		if ( ! $results ) {
			return [];
		}

		$barcodes = [];
		foreach ( $results as $barcode ) {
			$barcodes[ $barcode ] = $barcode;
		}

		return array_values( $barcodes );
	}

	/**
	 * Get products like barcode.
	 *
	 * @param string $barcode The barcode to search for.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_products_like_barcode( string $barcode ): array {
		global $wpdb;

		$query = $wpdb->prepare(
			'SELECT * FROM ' . NEBU_API24_TABLE_PRODUCTS . ' WHERE barcode LIKE %s', // phpcs:ignore
			$wpdb->esc_like( $barcode ) . '_%'
		);

		$products = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore
		if ( ! $products ) {
			return [];
		}

		return $products;
	}

	/**
	 * Get products from the database by barcode.
	 *
	 * @param string $barcode The barcode to search for.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_products_by_barcode( string $barcode ): array {
		global $wpdb;

		$query = $wpdb->prepare(
			'SELECT * FROM ' . NEBU_API24_TABLE_PRODUCTS . ' WHERE barcode = %s', // phpcs:ignore
			$barcode
		);

		$products = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore
		if ( ! $products ) {
			return [];
		}

		return $products;
	}

	/**
	 * Migrate api24 products table.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function migrate(): void {
		global $wpdb;

		//phpcs:disable
		$query = $wpdb->prepare( 'SHOW TABLES LIKE %s', NEBU_API24_TABLE_PRODUCTS );
		if ( $wpdb->get_var( $query ) !== NEBU_API24_TABLE_PRODUCTS ) {
			$wpdb->query( 'CREATE TABLE ' . NEBU_API24_TABLE_PRODUCTS . ' (
				id INT(11) UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
				product_id VARCHAR(255) NOT NULL,
				category_id VARCHAR(255) NOT NULL,
				name TEXT NOT NULL,
				description TEXT NULL,
				sku VARCHAR(255) NULL,
				barcode VARCHAR(255) NULL,
				stock INT(11) NOT NULL DEFAULT 0,
				price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
				sale_price DECIMAL(10,2) NULL,
				b2b_price DECIMAL(10,2) NULL,
				main_image VARCHAR(255) NULL,
				images TEXT NULL,
				attributes TEXT NULL,
				model TEXT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL
				)'
			);
		}
		// phpcs:enable
	}
}
