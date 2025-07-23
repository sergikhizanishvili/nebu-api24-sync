<?php
/**
 * Utility class for Nebu API24 Sync.
 *
 * @package Nebu API24 Sync
 * @since   1.0.0
 */

namespace Nebu\API24\Lib;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Utils
 *
 * @since 1.0.0
 */
class Utils {
	/**
	 * Convert a string, which containts Georgian characters, to a fully
	 * alphanumeric string.
	 *
	 * @param string $str The string to convert.
	 * @return string The converted string.
	 * @since 1.0.0
	 */
	public static function geo_to_alphanumeric( string $str ): string {
		$map = [
			'ა' => 'a',
			'ბ' => 'b',
			'გ' => 'g',
			'დ' => 'd',
			'ე' => 'e',
			'ვ' => 'v',
			'ზ' => 'z',
			'თ' => 't',
			'ი' => 'i',
			'კ' => 'k',
			'ლ' => 'l',
			'მ' => 'm',
			'ნ' => 'n',
			'ო' => 'o',
			'პ' => 'p',
			'ჟ' => 'zh',
			'რ' => 'r',
			'ს' => 's',
			'ტ' => 't',
			'უ' => 'u',
			'ფ' => 'f',
			'ქ' => 'q',
			'ღ' => 'gh',
			'ყ' => 'k',
			'შ' => 'sh',
			'ჩ' => 'ch',
			'ც' => 'ts',
			'ძ' => 'dz',
			'წ' => 'ts',
			'ჭ' => 'ch',
			'ხ' => 'kh',
			'ჯ' => 'j',
			'ჰ' => 'h',
		];

		$letters   = mb_str_split( $str );
		$converted = [];
		foreach ( $letters as $letter ) {
			if ( isset( $map[ $letter ] ) ) {
				$converted[] = $map[ $letter ];
			} else {
				$converted[] = $letter;
			}
		}

		return implode( '', $converted );
	}

	/**
	 * Convert string to a WordPress slug.
	 *
	 * @param string $str The string to convert.
	 * @return string The converted slug.
	 * @since 1.0.0
	 */
	public static function to_slug( string $str ): string {

		$str = self::geo_to_alphanumeric( $str );
		$str = preg_replace( '/[^a-z0-9]+/i', '-', $str );
		$str = trim( $str, '-' );
		$str = strtolower( $str );
		$str = preg_replace( '/-+/', '-', $str );
		return $str;
	}

	/**
	 * Handle remote image upload.
	 *
	 * @param string $file_url Remote file URL.
	 * @param array  $allowed  Allowed MIME types.
	 * @return int|null Attachment ID or null on failure.
	 * @since 1.0.0
	 */
	public static function handle_remote_file_upload( string $file_url, array $allowed = [] ): ?int {
		$allowed = ! empty( $allowed ) ? $allowed : [
			'image/jpg'  => 'jpg',
			'image/jpeg' => 'jpeg',
			'image/png'  => 'png',
		];

		if ( empty( $file_url ) || ! filter_var( $file_url, FILTER_VALIDATE_URL ) ) {
			return null;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		// Get the file name and check MIME type.
		$file_name = basename( wp_parse_url( $file_url, PHP_URL_PATH ) );
		$tmp       = download_url( $file_url );

		if ( is_wp_error( $tmp ) ) {
			return null;
		}

		$mime = mime_content_type( $tmp );
		if ( ! in_array( $mime, array_keys( $allowed ), true ) ) {
			@unlink( $tmp ); // phpcs:ignore
			return null;
		}

		$file_array = [
			'name'     => $file_name,
			'type'     => $mime,
			'tmp_name' => $tmp,
		];

		$attachment_id = media_handle_sideload( $file_array, 0 );

		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp ); // phpcs:ignore
			return null;
		}

		return $attachment_id;
	}

	/**
	 * Build a category tree from a flat list of categories.
	 *
	 * @param array $categories The flat list of categories.
	 * @return array The hierarchical category tree.
	 * @since 1.0.0
	 */
	public static function build_api24_category_tree( array $categories ): array {
		$items_by_id = [];
		$tree        = [];

		foreach ( $categories as $category ) {
			$category['children']           = [];
			$items_by_id[ $category['id'] ] = $category;
		}

		foreach ( $items_by_id as $id => &$category ) {
			$parent_id = $category['parent_id'];
			if (
				! empty( $parent_id ) &&
				isset( $items_by_id[ $parent_id ] ) ) {
				$items_by_id[ $parent_id ]['children'][] = &$category;
			} else {
				$tree[] = &$category;
			}
		}

		return $tree;
	}

	/**
	 * Process a category tree and create terms in WordPress.
	 *
	 * @param array  $categories The category tree to process.
	 * @param string $taxonomy The taxonomy to use (default: 'product_cat').
	 * @param int    $parent_id The parent term ID (default: 0).
	 * @return void
	 * @since 1.0.0
	 */
	public static function process_category_tree( array $categories, $taxonomy = 'product_cat', $parent_id = 0 ): void {
		foreach ( $categories as $category ) {
			$existing = term_exists( $category['slug'], $taxonomy, $parent_id );
			if ( $existing && is_array( $existing ) ) {
				continue;
			}

			$term = wp_insert_term(
				$category['name'],
				$taxonomy,
				[
					'parent' => $parent_id,
					'slug'   => $category['slug'],
				]
			);

			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}

			update_field( 'category_api24_id', $category['id'], 'term_' . $term['term_id'] );

			if ( ! empty( $category['children'] ) ) {
				self::process_category_tree( $category['children'], $taxonomy, $term['term_id'] );
			}
		}
	}

	/**
	 * Delete all categories with "category_api24_id" field.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function delete_all_api24_categories(): void {
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

		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				wp_delete_term( $term->term_id, 'product_cat' );
			}
		}
	}

	/**
	 * Find WordPress category by API24 category ID.
	 *
	 * @param string $api24_category_id The API24 category ID.
	 * @return ?int WordPress category term ID or null if not found.
	 * @since 1.0.0
	 */
	public static function find_category_by_api24_id( string $api24_category_id ): ?int {
		$terms = get_terms(
			[
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'meta_query' => [ // phpcs:ignore
					[
						'key'     => 'category_api24_id',
						'value'   => $api24_category_id,
						'compare' => '=',
					],
				],
				'number'     => 1,
			]
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return null;
		}

		return (int) $terms[0]->term_id;
	}

	/**
	 * Get all parent category IDs for a given category.
	 *
	 * @param int $category_id The category term ID.
	 * @return array Array of category IDs including the given category and all its parents.
	 * @since 1.0.0
	 */
	public static function get_category_hierarchy( int $category_id ): array {
		$hierarchy = [ $category_id ];
		$parent_id = wp_get_term_taxonomy_parent_id( $category_id, 'product_cat' );

		while ( $parent_id && $parent_id > 0 ) {
			$hierarchy[] = $parent_id;
			$parent_id   = wp_get_term_taxonomy_parent_id( $parent_id, 'product_cat' );
		}

		return array_reverse( $hierarchy );
	}

	/**
	 * Delete all WooCommerce products created from API24 data.
	 * This includes variable products, simple products, variations, and all associated meta data.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function delete_all_api24_products(): void {
		$products = wc_get_products(
			[
				'limit'      => -1,
				'meta_query' => [ // phpcs:ignore
					[
						'key'     => 'base_barcode',
						'compare' => 'EXISTS',
					],
				],
			]
		);

		foreach ( $products as $product ) {
			if ( $product->is_type( 'variable' ) ) {
				$variations = $product->get_children();

				foreach ( $variations as $variation_id ) {
					$variation = wc_get_product( $variation_id );
					if ( $variation ) {
						self::delete_product_attachments( $variation );
						wp_delete_post( $variation_id, true );
					}
				}
			}

			self::delete_product_attachments( $product );
			wp_delete_post( $product->get_id(), true );
		}
	}

	/**
	 * Delete all attachments (images) associated with a product.
	 *
	 * @param WC_Product $product The product object.
	 * @return void
	 * @since 1.0.0
	 */
	private static function delete_product_attachments( WC_Product $product ): void {

		$featured_image_id = $product->get_image_id();
		if ( $featured_image_id ) {
			wp_delete_attachment( $featured_image_id, true );
		}

		$gallery_images = $product->get_gallery_image_ids();
		foreach ( $gallery_images as $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
		}
	}
}
