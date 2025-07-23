<?php
/**
 * Simple product creator class.
 *
 * @package Nebu API24 Sync
 * @since   1.0.0
 */

namespace Nebu\API24\Lib;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Nebu\API24\Lib\Utils;
use Nebu\API24\Lib\Product_Helper;

/**
 * Simple product creator class.
 *
 * @since 1.0.0
 */
class Simple_Product_Creator {

	/**
	 * Logger instance.
	 *
	 * @var callable
	 * @since 1.0.0
	 */
	private $logger;

	/**
	 * Product helper instance.
	 *
	 * @var Product_Helper
	 * @since 1.0.0
	 */
	private Product_Helper $helper;

	/**
	 * Constructor.
	 *
	 * @param callable $logger Logger function.
	 * @since 1.0.0
	 */
	public function __construct( callable $logger ) {
		$this->logger = $logger;
		$this->helper = new Product_Helper( $logger );
	}

	/**
	 * Create a simple product from the API data.
	 *
	 * @param array $product_data Product data from the API.
	 * @return void
	 * @since 1.0.0
	 */
	public function create( array $product_data ): void {
		$product_data = isset( $product_data[0] ) ? $product_data[0] : [];
		if ( empty( $product_data ) ) {
			call_user_func( $this->logger, 'No product data found for simple product.' );
			return;
		}

		if ( $this->helper->product_exists( $product_data['barcode'] ) ) {
			call_user_func( $this->logger, sprintf( 'Product with base barcode %s already exists.', $product_data['sku'] ) );
			return;
		}

		if ( empty( $product_data['main_image'] ) ) {
			call_user_func( $this->logger, sprintf( 'Product %s has no main image.', $product_data['name'] ) );
			return;
		}

		$featured_image_id = Utils::handle_remote_file_upload( $product_data['main_image'] );
		if ( ! $featured_image_id ) {
			call_user_func( $this->logger, sprintf( 'Failed to upload featured image for product %s.', $product_data['name'] ) );
			return;
		}

		$gallery_images = $this->helper->process_gallery_images( $product_data['images'] ?? '' );

		$product = new \WC_Product_Simple();
		$product->set_name( $product_data['name'] );
		$product->set_description( $product_data['description'] );

		$sku = $product_data['sku'] ?? '';
		if ( ! empty( $sku ) && ! wc_get_product_id_by_sku( $sku ) ) {
			$product->set_sku( $sku );
		} elseif ( ! empty( $sku ) ) {
			call_user_func( $this->logger, sprintf( 'Simple product SKU %s already exists, creating without SKU.', $sku ) );
		}

		$product->set_regular_price( $product_data['price'] );
		$product->set_sale_price( $product_data['sale_price'] );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( $product_data['stock'] );
		$product->set_stock_status( $product_data['stock'] > 0 ? 'instock' : 'outofstock' );
		$product->set_image_id( $featured_image_id );
		$product->set_gallery_image_ids( $gallery_images );
		$product->set_meta_data( 'base_barcode', $product_data['barcode'] );
		$product->set_meta_data( 'barcode', $product_data['barcode'] );
		$product->set_meta_data( 'model', $product_data['model'] );
		$product->set_meta_data( 'api24_product_id', $product_data['product_id'] );
		$product->save();

		$product = wc_get_product( $product->get_id() );

		$this->helper->add_product_attributes( $product_data['attributes'] ?? '', $product );
		$this->helper->set_product_categories( $product_data['category_id'], $product );
	}
}
