<?php
/**
 * Variable product creator class.
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
use Nebu\API24\Lib\Asaki_Attribute_Manager;

/**
 * Variable product creator class.
 *
 * @since 1.0.0
 */
class Variable_Product_Creator {

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
	 * Asaki attribute manager instance.
	 *
	 * @var Asaki_Attribute_Manager
	 * @since 1.0.0
	 */
	private Asaki_Attribute_Manager $asaki_manager;

	/**
	 * Constructor.
	 *
	 * @param callable $logger Logger function.
	 * @since 1.0.0
	 */
	public function __construct( callable $logger ) {
		$this->logger        = $logger;
		$this->helper        = new Product_Helper( $logger );
		$this->asaki_manager = new Asaki_Attribute_Manager( $logger );
	}

	/**
	 * Create a variable product from the API data.
	 *
	 * @param array  $product_data Product data from the API.
	 * @param string $barcode The barcode of the product.
	 * @return void
	 * @since 1.0.0
	 */
	public function create( array $product_data, string $barcode ): void {
		$base_product_data = isset( $product_data[0] ) ? $product_data[0] : [];
		if ( empty( $base_product_data ) ) {
			call_user_func( $this->logger, 'No base product data found for variable product.' );
			return;
		}

		if ( $this->helper->product_exists( $barcode ) ) {
			call_user_func( $this->logger, sprintf( 'Product with base barcode %s already exists.', $base_product_data['sku'] ) );
			return;
		}

		if ( empty( $base_product_data['main_image'] ) ) {
			call_user_func( $this->logger, sprintf( 'Product %s has no main image.', $base_product_data['name'] ) );
			return;
		}

		$featured_image_id = Utils::handle_remote_file_upload( $base_product_data['main_image'] );
		if ( ! $featured_image_id ) {
			call_user_func( $this->logger, sprintf( 'Failed to upload featured image for product %s.', $base_product_data['name'] ) );
			return;
		}

		$gallery_images = $this->helper->process_gallery_images( $base_product_data['images'] ?? '' );

		$product = new \WC_Product_Variable();
		$product->set_name( $base_product_data['name'] );
		$product->set_description( $base_product_data['description'] );
		$product->set_image_id( $featured_image_id );
		$product->set_gallery_image_ids( $gallery_images );
		$product->set_meta_data( 'base_barcode', $barcode );
		$product->save();

		$product = wc_get_product( $product->get_id() );

		$this->add_non_asaki_attributes( $base_product_data['attributes'] ?? '', $product );
		$this->asaki_manager->setup_asaki_attribute( $product, $product_data );
		$this->helper->set_product_categories( $base_product_data['category_id'], $product );

		$this->asaki_manager->create_all_terms_and_associate( $product, $product_data );
		$this->create_variations( $product, $product_data );
		$this->finalize_variable_product( $product );
	}

	/**
	 * Add non-asaki attributes to the product.
	 *
	 * @param string      $attributes_json JSON string of attributes.
	 * @param \WC_Product $product The product object.
	 * @return void
	 * @since 1.0.0
	 */
	private function add_non_asaki_attributes( string $attributes_json, \WC_Product $product ): void {
		$attrs = json_decode( $attributes_json, true );
		$attrs = is_array( $attrs ) ? $attrs : [];

		foreach ( $attrs as $attribute_data ) {
			if ( 'asaki' === $attribute_data['slug'] ) {
				continue;
			}
			$this->helper->add_wc_attribute( $attribute_data, $product );
		}
	}

	/**
	 * Create product variations.
	 *
	 * @param \WC_Product_Variable $product The variable product.
	 * @param array                $product_data All product variation data.
	 * @return void
	 * @since 1.0.0
	 */
	private function create_variations( \WC_Product_Variable $product, array $product_data ): void {
		foreach ( $product_data as $variation_data ) {
			$sku = $variation_data['sku'] ?? '';
			if ( ! empty( $sku ) && wc_get_product_id_by_sku( $sku ) ) {
				call_user_func( $this->logger, sprintf( 'Variation with SKU %s already exists, skipping.', $sku ) );
				continue;
			}

			$asaki_value = $this->extract_asaki_value( $variation_data['attributes'] ?? '' );
			if ( empty( $asaki_value ) ) {
				call_user_func( $this->logger, sprintf( 'Variation with SKU %s has no asaki attribute, skipping.', $sku ) );
				continue;
			}

			$term = get_term_by( 'name', $asaki_value, 'pa_asaki' );
			if ( ! $term ) {
				call_user_func( $this->logger, sprintf( 'Failed to get term for asaki value %s, skipping variation.', $asaki_value ) );
				continue;
			}

			$this->create_single_variation( $product, $variation_data, $term );
		}
	}

	/**
	 * Extract asaki value from variation attributes.
	 *
	 * @param string $attributes_json JSON string of attributes.
	 * @return string|null
	 * @since 1.0.0
	 */
	private function extract_asaki_value( string $attributes_json ): ?string {
		$variation_attrs = json_decode( $attributes_json, true );
		$variation_attrs = is_array( $variation_attrs ) ? $variation_attrs : [];

		foreach ( $variation_attrs as $attr_data ) {
			$slug  = $attr_data['slug'] ?? '';
			$value = $attr_data['value'] ?? '';

			if ( 'asaki' === $slug && ! empty( $value ) ) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * Create a single product variation.
	 *
	 * @param \WC_Product_Variable $product The parent product.
	 * @param array                $variation_data Variation data.
	 * @param \WP_Term             $term The asaki term.
	 * @return void
	 * @since 1.0.0
	 */
	private function create_single_variation( \WC_Product_Variable $product, array $variation_data, \WP_Term $term ): void {
		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $product->get_id() );

		$sku = $variation_data['sku'] ?? '';
		if ( ! empty( $sku ) ) {
			$variation->set_sku( $sku );
		}

		$variation->set_regular_price( $variation_data['price'] );
		$variation->set_sale_price( $variation_data['sale_price'] ?? '' );
		$variation->set_stock_quantity( $variation_data['stock'] ?? 0 );
		$variation->set_manage_stock( true );
		$variation->set_stock_status( $variation_data['stock'] > 0 ? 'instock' : 'outofstock' );
		$variation->set_meta_data( 'barcode', $variation_data['barcode'] );
		$variation->set_meta_data( 'model', $variation_data['model'] );
		$variation->set_meta_data( 'api24_product_id', $variation_data['product_id'] );
		$variation->set_attributes( [ 'pa_asaki' => $term->slug ] );
		$variation->set_status( 'publish' );
		$variation->save();

		$parent_product = wc_get_product( $variation->get_parent_id() );
		$parent_product->sync( $variation->get_id() );
	}

	/**
	 * Finalize variable product creation.
	 *
	 * @param \WC_Product_Variable $product The variable product.
	 * @return void
	 * @since 1.0.0
	 */
	private function finalize_variable_product( \WC_Product_Variable $product ): void {
		$product = wc_get_product( $product->get_id() );

		\WC_Product_Variable::sync( $product->get_id() );
		do_action( 'woocommerce_variable_product_sync_data', $product );

		$product = wc_get_product( $product->get_id() );
		$product->set_stock_status();
		$product->save();

		call_user_func( $this->logger, sprintf( 'Completed variable product creation and sync for product %d', $product->get_id() ) );
	}
}
