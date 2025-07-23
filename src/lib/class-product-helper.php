<?php
/**
 * Product helper class for common product operations.
 *
 * @package Nebu API24 Sync
 * @since   1.0.0
 */

namespace Nebu\API24\Lib;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Nebu\API24\Lib\Utils;

/**
 * Product helper class.
 *
 * @since 1.0.0
 */
class Product_Helper {

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
	 * Check if a product with the given barcode already exists.
	 *
	 * @param string $barcode The barcode to check.
	 * @return bool
	 * @since 1.0.0
	 */
	public function product_exists( string $barcode ): bool {
		$products = get_posts(
			[
				'post_type'      => 'product',
				'posts_per_page' => -1,
				'meta_query'     => [ //phpcs:ignore
					[
						'key'     => 'base_barcode',
						'value'   => $barcode,
						'compare' => '=',
					],
				],
			]
		);

		return ! empty( $products );
	}

	/**
	 * Process gallery images from JSON string.
	 *
	 * @param string $images_json JSON string of image URLs.
	 * @return array
	 * @since 1.0.0
	 */
	public function process_gallery_images( string $images_json ): array {
		$gallery_images = [];

		if ( ! empty( $images_json ) ) {
			$gallery = json_decode( $images_json, true );
			if ( is_array( $gallery ) ) {
				foreach ( $gallery as $image ) {
					$image_id = Utils::handle_remote_file_upload( $image );
					if ( $image_id ) {
						$gallery_images[] = $image_id;
					}
				}
			}
		}

		return $gallery_images;
	}

	/**
	 * Add product attributes from JSON string.
	 *
	 * @param string      $attributes_json JSON string of attributes.
	 * @param \WC_Product $product The product object.
	 * @return void
	 * @since 1.0.0
	 */
	public function add_product_attributes( string $attributes_json, \WC_Product $product ): void {
		$attrs = json_decode( $attributes_json, true );
		$attrs = is_array( $attrs ) ? $attrs : [];

		foreach ( $attrs as $attribute_data ) {
			$this->add_wc_attribute( $attribute_data, $product );
		}
	}

	/**
	 * Set product categories.
	 *
	 * @param string      $category_id The API24 category ID.
	 * @param \WC_Product $product The product object.
	 * @return void
	 * @since 1.0.0
	 */
	public function set_product_categories( string $category_id, \WC_Product $product ): void {
		$category = Utils::find_category_by_api24_id( $category_id );
		if ( ! $category ) {
			return;
		}

		$hierarchy = Utils::get_category_hierarchy( $category );
		if ( ! empty( $hierarchy ) ) {
			$product->set_category_ids( $hierarchy );
			$product->save();
		}
	}

	/**
	 * Create WC attribute if it doesn't exist and add term to product.
	 *
	 * @param array       $attribute_data Attribute data containing 'name', 'value', and 'slug'.
	 * @param \WC_Product $product The product object to associate the attribute with.
	 * @return void
	 * @since 1.0.0
	 */
	public function add_wc_attribute( array $attribute_data, \WC_Product $product ): void {
		$name = $attribute_data['name'] ?? '';
		$slug = $attribute_data['slug'] ?? '';
		$term = $attribute_data['value'] ?? '';

		if ( empty( $name ) || empty( $slug ) || empty( $term ) ) {
			return;
		}

		$slug     = wc_sanitize_taxonomy_name( $slug );
		$taxonomy = 'pa_' . $slug;

		$this->create_taxonomy_if_not_exists( $taxonomy, $name, $slug );
		$this->create_or_fix_term( $term, $taxonomy );

		wp_set_object_terms( $product->get_id(), Utils::to_slug( $term ), $taxonomy, true );
		$this->set_product_attribute( $product, $taxonomy, $term );
	}

	/**
	 * Create taxonomy if it doesn't exist.
	 *
	 * @param string $taxonomy The taxonomy name.
	 * @param string $name The attribute name.
	 * @param string $slug The attribute slug.
	 * @return void
	 * @since 1.0.0
	 */
	private function create_taxonomy_if_not_exists( string $taxonomy, string $name, string $slug ): void {
		if ( taxonomy_exists( $taxonomy ) ) {
			return;
		}

		wc_create_attribute(
			[
				'name'         => $name,
				'slug'         => $slug,
				'type'         => 'select',
				'order_by'     => 'menu_order',
				'has_archives' => false,
			]
		);

		register_taxonomy(
			$taxonomy,
			apply_filters( 'woocommerce_taxonomy_objects_' . $taxonomy, [ 'product' ] ),
			apply_filters(
				'woocommerce_taxonomy_args_' . $taxonomy,
				[
					'hierarchical'      => true,
					'labels'            => [
						'name' => $name,
					],
					'show_ui'           => false,
					'query_var'         => true,
					'rewrite'           => false,
					'show_in_nav_menus' => false,
				]
			)
		);
	}

	/**
	 * Create or fix term with proper English slug.
	 *
	 * @param string $term The term name.
	 * @param string $taxonomy The taxonomy name.
	 * @return void
	 * @since 1.0.0
	 */
	private function create_or_fix_term( string $term, string $taxonomy ): void {
		$english_slug = Utils::to_slug( $term );

		if ( ! term_exists( $term, $taxonomy ) ) {
			wp_insert_term(
				$term,
				$taxonomy,
				[
					'slug' => $english_slug,
				]
			);
			call_user_func( $this->logger, sprintf( 'Created attribute term "%s" with slug "%s"', $term, $english_slug ) );
		} else {
			$existing_term = get_term_by( 'name', $term, $taxonomy );
			if ( $existing_term && $existing_term->slug !== $english_slug && strpos( $existing_term->slug, '%' ) !== false ) {
				wp_update_term(
					$existing_term->term_id,
					$taxonomy,
					[
						'slug' => $english_slug,
					]
				);
				call_user_func( $this->logger, sprintf( 'Fixed attribute term "%s" slug from "%s" to "%s"', $term, $existing_term->slug, $english_slug ) );
			}
		}
	}

	/**
	 * Set product attribute.
	 *
	 * @param \WC_Product $product The product object.
	 * @param string      $taxonomy The taxonomy name.
	 * @param string      $term The term name.
	 * @return void
	 * @since 1.0.0
	 */
	private function set_product_attribute( \WC_Product $product, string $taxonomy, string $term ): void {
		$existing_attributes = $product->get_attributes();

		if ( isset( $existing_attributes[ $taxonomy ] ) ) {
			$existing_options = $existing_attributes[ $taxonomy ]->get_options();
			if ( ! in_array( $term, $existing_options, true ) ) {
				$existing_options[] = $term;
				$existing_attributes[ $taxonomy ]->set_options( $existing_options );
			}

			$is_variation_attribute = $product->is_type( 'variable' ) && 'pa_asaki' === $taxonomy;
			$existing_attributes[ $taxonomy ]->set_variation( $is_variation_attribute );
		} else {
			$attribute = new \WC_Product_Attribute();
			$attribute->set_name( $taxonomy );
			$attribute->set_options( [ $term ] );
			$attribute->set_position( count( $existing_attributes ) );
			$attribute->set_visible( true );

			$is_variation_attribute = $product->is_type( 'variable' ) && 'pa_asaki' === $taxonomy;
			$attribute->set_variation( $is_variation_attribute );

			$existing_attributes[ $taxonomy ] = $attribute;
		}

		$product->set_attributes( $existing_attributes );
		$product->save();
	}
}
