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
}
