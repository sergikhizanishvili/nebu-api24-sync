<?php
/**
 * Product factory class for creating WooCommerce products.
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
use Nebu\API24\Lib\Simple_Product_Creator;
use Nebu\API24\Lib\Variable_Product_Creator;

/**
 * Product factory class.
 *
 * @since 1.0.0
 */
class Product_Factory {

	/**
	 * Logger instance.
	 *
	 * @var callable
	 * @since 1.0.0
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param callable $logger Logger function.
	 * @since 1.0.0
	 */
	public function __construct( callable $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Create products from API data.
	 *
	 * @return void
	 * @throws Exception If something goes wrong.
	 * @since 1.0.0
	 */
	public function create_products(): void {
		$barcodes      = DB::get_barcodes();
		$base_barcodes = [];

		foreach ( $barcodes as $barcode ) {
			$explode = explode( '_', $barcode );
			if ( ! empty( $explode[0] ) ) {
				$base_barcodes[] = $explode[0];
			}
		}

		$barcodes = array_unique( $base_barcodes );

		foreach ( $barcodes as $barcode ) {
			$simple_product = DB::get_products_by_barcode( $barcode );

			if ( ! empty( $simple_product ) ) {
				$this->create_simple_product( $simple_product );
			} else {
				$variable_product = DB::get_products_like_barcode( $barcode );
				if ( ! empty( $variable_product ) ) {
					$this->create_variable_product( $variable_product, $barcode );
				}
			}
		}
	}

	/**
	 * Create a simple product.
	 *
	 * @param array $product_data Product data from the API.
	 * @return void
	 * @since 1.0.0
	 */
	private function create_simple_product( array $product_data ): void {
		$creator = new Simple_Product_Creator( $this->logger );
		$creator->create( $product_data );
	}

	/**
	 * Create a variable product.
	 *
	 * @param array  $product_data Product data from the API.
	 * @param string $barcode The barcode of the product.
	 * @return void
	 * @since 1.0.0
	 */
	private function create_variable_product( array $product_data, string $barcode ): void {
		$creator = new Variable_Product_Creator( $this->logger );
		$creator->create( $product_data, $barcode );
	}
}
