<?php
/**
 * Asaki attribute manager class for handling asaki variations.
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
 * Asaki attribute manager class.
 *
 * @since 1.0.0
 */
class Asaki_Attribute_Manager {

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
	 * Setup asaki attribute for the variable product.
	 *
	 * @param \WC_Product_Variable $product The variable product.
	 * @param array                $product_data All product variation data.
	 * @return void
	 * @since 1.0.0
	 */
	public function setup_asaki_attribute( \WC_Product_Variable $product, array $product_data ): void {
		$asaki_values = $this->collect_asaki_values( $product_data );

		if ( ! empty( $asaki_values ) ) {
			$this->create_asaki_variation_attribute( $product, $asaki_values );
		}
	}

	/**
	 * Create all asaki terms and associate them with the parent product.
	 *
	 * @param \WC_Product_Variable $product The variable product.
	 * @param array                $product_data All product variation data.
	 * @return void
	 * @since 1.0.0
	 */
	public function create_all_terms_and_associate( \WC_Product_Variable $product, array $product_data ): void {
		$taxonomy       = 'pa_asaki';
		$all_term_names = $this->collect_asaki_values( $product_data );

		$this->create_all_terms( $all_term_names, $taxonomy );
		$this->associate_terms_with_parent( $product, $all_term_names, $taxonomy );
		$this->update_parent_product_attributes( $product, $taxonomy );
	}

	/**
	 * Collect all unique asaki values from product data.
	 *
	 * @param array $product_data All product variation data.
	 * @return array
	 * @since 1.0.0
	 */
	private function collect_asaki_values( array $product_data ): array {
		$asaki_values = [];

		foreach ( $product_data as $variation_data ) {
			$variation_attrs = json_decode( $variation_data['attributes'], true );
			$variation_attrs = is_array( $variation_attrs ) ? $variation_attrs : [];

			foreach ( $variation_attrs as $attr_data ) {
				$slug  = $attr_data['slug'] ?? '';
				$value = $attr_data['value'] ?? '';

				if ( 'asaki' === $slug && ! empty( $value ) && ! in_array( $value, $asaki_values, true ) ) {
					$asaki_values[] = $value;
				}
			}
		}

		return $asaki_values;
	}

	/**
	 * Create the asaki variation attribute with all values.
	 *
	 * @param \WC_Product_Variable $product The variable product.
	 * @param array                $asaki_values Array of asaki values.
	 * @return void
	 * @since 1.0.0
	 */
	private function create_asaki_variation_attribute( \WC_Product_Variable $product, array $asaki_values ): void {
		$taxonomy = 'pa_asaki';
		$name     = 'ასაკი';
		$slug     = 'asaki';

		$this->create_asaki_taxonomy_if_not_exists( $taxonomy, $name, $slug );
		$this->create_asaki_terms( $asaki_values, $taxonomy );
		$this->set_parent_product_asaki_attribute( $product, $taxonomy, $asaki_values );
	}

	/**
	 * Create asaki taxonomy if it doesn't exist.
	 *
	 * @param string $taxonomy The taxonomy name.
	 * @param string $name The attribute name.
	 * @param string $slug The attribute slug.
	 * @return void
	 * @since 1.0.0
	 */
	private function create_asaki_taxonomy_if_not_exists( string $taxonomy, string $name, string $slug ): void {
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
	 * Create asaki terms with proper English slugs.
	 *
	 * @param array  $asaki_values Array of asaki values.
	 * @param string $taxonomy The taxonomy name.
	 * @return void
	 * @since 1.0.0
	 */
	private function create_asaki_terms( array $asaki_values, string $taxonomy ): void {
		foreach ( $asaki_values as $asaki_value ) {
			$english_slug = Utils::to_slug( $asaki_value );

			if ( ! term_exists( $asaki_value, $taxonomy ) ) {
				$term_result = wp_insert_term(
					$asaki_value,
					$taxonomy,
					[
						'slug' => $english_slug,
					]
				);

				if ( ! is_wp_error( $term_result ) ) {
					call_user_func( $this->logger, sprintf( 'Created asaki term "%s" with slug "%s"', $asaki_value, $english_slug ) );
				} else {
					call_user_func( $this->logger, sprintf( 'Failed to create asaki term "%s": %s', $asaki_value, $term_result->get_error_message() ) );
				}
			} else {
				$this->fix_existing_term_slug( $asaki_value, $taxonomy, $english_slug );
			}
		}
	}

	/**
	 * Fix existing term slug if it's URL-encoded.
	 *
	 * @param string $asaki_value The term name.
	 * @param string $taxonomy The taxonomy name.
	 * @param string $english_slug The correct English slug.
	 * @return void
	 * @since 1.0.0
	 */
	private function fix_existing_term_slug( string $asaki_value, string $taxonomy, string $english_slug ): void {
		$term = get_term_by( 'name', $asaki_value, $taxonomy );
		if ( $term && $term->slug !== $english_slug && strpos( $term->slug, '%' ) !== false ) {
			wp_update_term(
				$term->term_id,
				$taxonomy,
				[
					'slug' => $english_slug,
				]
			);
			call_user_func( $this->logger, sprintf( 'Updated asaki term "%s" slug from "%s" to "%s"', $asaki_value, $term->slug, $english_slug ) );
		}
	}

	/**
	 * Set parent product asaki attribute.
	 *
	 * @param \WC_Product_Variable $product The variable product.
	 * @param string               $taxonomy The taxonomy name.
	 * @param array                $asaki_values Array of asaki values.
	 * @return void
	 * @since 1.0.0
	 */
	private function set_parent_product_asaki_attribute( \WC_Product_Variable $product, string $taxonomy, array $asaki_values ): void {
		wp_set_object_terms( $product->get_id(), $asaki_values, $taxonomy, false );
		call_user_func( $this->logger, sprintf( 'Associated %d asaki terms with parent product %d', count( $asaki_values ), $product->get_id() ) );

		$product_attributes = [
			wc_attribute_taxonomy_name( 'asaki' ) => [
				'name'         => wc_attribute_taxonomy_name( 'asaki' ),
				'value'        => '',
				'position'     => 1,
				'is_visible'   => 1,
				'is_variation' => 1,
				'is_taxonomy'  => 1,
			],
		];

		update_post_meta( $product->get_id(), '_product_attributes', $product_attributes );

		$existing_attributes = $product->get_attributes();
		$attribute           = new \WC_Product_Attribute();
		$attribute->set_name( $taxonomy );
		$attribute->set_options( $asaki_values );
		$attribute->set_position( 1 );
		$attribute->set_visible( true );
		$attribute->set_variation( true );

		$existing_attributes[ $taxonomy ] = $attribute;
		$product->set_attributes( $existing_attributes );
		$product->save();

		call_user_func( $this->logger, sprintf( 'Created asaki attribute with %d term names: %s', count( $asaki_values ), implode( ', ', $asaki_values ) ) );
	}

	/**
	 * Create all terms and ensure proper slugs.
	 *
	 * @param array  $all_term_names Array of term names.
	 * @param string $taxonomy The taxonomy name.
	 * @return void
	 * @since 1.0.0
	 */
	private function create_all_terms( array $all_term_names, string $taxonomy ): void {
		foreach ( $all_term_names as $term_name ) {
			$english_slug = Utils::to_slug( $term_name );

			if ( ! term_exists( $term_name, $taxonomy ) ) {
				wp_insert_term( $term_name, $taxonomy, [ 'slug' => $english_slug ] );
				call_user_func( $this->logger, sprintf( 'Created asaki term "%s" with slug "%s"', $term_name, $english_slug ) );
			} else {
				$this->fix_existing_term_slug( $term_name, $taxonomy, $english_slug );
			}
		}
	}

	/**
	 * Associate all terms with parent product.
	 *
	 * @param \WC_Product_Variable $product The variable product.
	 * @param array                $all_term_names Array of term names.
	 * @param string               $taxonomy The taxonomy name.
	 * @return void
	 * @since 1.0.0
	 */
	private function associate_terms_with_parent( \WC_Product_Variable $product, array $all_term_names, string $taxonomy ): void {
		if ( empty( $all_term_names ) ) {
			return;
		}

		$post_term_names = wp_get_post_terms( $product->get_id(), $taxonomy, [ 'fields' => 'names' ] );
		$missing_terms   = array_diff( $all_term_names, $post_term_names );

		if ( ! empty( $missing_terms ) ) {
			wp_set_post_terms( $product->get_id(), $all_term_names, $taxonomy, false );
			call_user_func( $this->logger, sprintf( 'Associated %d asaki terms with parent product %d: %s', count( $all_term_names ), $product->get_id(), implode( ', ', $all_term_names ) ) );
		}
	}

	/**
	 * Update parent product attributes meta.
	 *
	 * @param \WC_Product_Variable $product The variable product.
	 * @param string               $taxonomy The taxonomy name.
	 * @return void
	 * @since 1.0.0
	 */
	private function update_parent_product_attributes( \WC_Product_Variable $product, string $taxonomy ): void {
		$product_attributes = get_post_meta( $product->get_id(), '_product_attributes', true );
		if ( ! is_array( $product_attributes ) ) {
			$product_attributes = [];
		}

		$product_attributes[ $taxonomy ] = [
			'name'         => $taxonomy,
			'value'        => '',
			'position'     => 0,
			'is_visible'   => 1,
			'is_variation' => 1,
			'is_taxonomy'  => 1,
		];

		update_post_meta( $product->get_id(), '_product_attributes', $product_attributes );
		call_user_func( $this->logger, 'Updated parent product _product_attributes meta for asaki' );
	}
}
