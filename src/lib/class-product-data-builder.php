<?php
/**
 * Product data builder class for processing API24 data.
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
 * Product data builder class.
 *
 * @since 1.0.0
 */
class Product_Data_Builder {

	/**
	 * Build product data from API24 response.
	 *
	 * @param array $product Product data from the API.
	 * @return array
	 * @since 1.0.0
	 */
	public static function build( array $product ): array {
		$price = self::extract_price( $product );
		if ( ! $price ) {
			return [];
		}

		$sale_price = self::extract_sale_price( $product, $price );
		$b2b_price  = self::extract_b2b_price( $product, $price );
		$images     = self::extract_gallery_images( $product );
		$attrs      = self::extract_attributes( $product );
		$name       = self::extract_product_name( $product );
		$barcode    = self::extract_barcode( $product );

		if ( empty( $name ) || empty( $barcode ) ) {
			return [];
		}

		$data = [
			'product_id'  => $product['productId'] ?? null,
			'category_id' => $product['categoryId'] ?? null,
			'name'        => $name,
			'description' => $product['description'] ?? '',
			'sku'         => $product['sku'] ?? null,
			'barcode'     => $barcode,
			'stock'       => $product['stockQuantity'] ?? 0,
			'price'       => $price,
			'sale_price'  => $sale_price,
			'b2b_price'   => $b2b_price,
			'main_image'  => $product['imageUrl'] ?? null,
			'images'      => wp_json_encode( $images ),
			'attributes'  => wp_json_encode( $attrs ),
			'model'       => $product['modelNo'] ?? '',
		];

		foreach ( $data as $value ) {
			if ( is_null( $value ) ) {
				return [];
			}
		}

		return $data;
	}

	/**
	 * Extract price from product data.
	 *
	 * @param array $product Product data from the API.
	 * @return float
	 * @since 1.0.0
	 */
	private static function extract_price( array $product ): float {
		$price = isset( $product['originalPrice'] ) ? (float) $product['originalPrice'] : 0;
		return $price > 0 ? $price : 0;
	}

	/**
	 * Extract sale price from product data.
	 *
	 * @param array $product Product data from the API.
	 * @param float $price The regular price.
	 * @return float
	 * @since 1.0.0
	 */
	private static function extract_sale_price( array $product, float $price ): float {
		$sale_price = isset( $product['salePrice'] ) ? (float) $product['salePrice'] : $price;
		return $sale_price > 0 ? $sale_price : $price;
	}

	/**
	 * Extract B2B price from product data.
	 *
	 * @param array $product Product data from the API.
	 * @param float $price The regular price.
	 * @return float
	 * @since 1.0.0
	 */
	private static function extract_b2b_price( array $product, float $price ): float {
		$b2b_price = isset( $product['b2bPrice'] ) ? (float) $product['b2bPrice'] : $price;
		return $b2b_price > 0 ? $b2b_price : $price;
	}

	/**
	 * Extract gallery images from product data.
	 *
	 * @param array $product Product data from the API.
	 * @return array
	 * @since 1.0.0
	 */
	private static function extract_gallery_images( array $product ): array {
		$gallery = isset( $product['gallery'] ) && is_array( $product['gallery'] ) ? $product['gallery'] : [];
		$images  = [];

		foreach ( $gallery as $image ) {
			if ( isset( $image['big'] ) && ! empty( $image['big'] ) ) {
				$images[] = $image['big'];
			}
		}

		return $images;
	}

	/**
	 * Extract attributes from product data.
	 *
	 * @param array $product Product data from the API.
	 * @return array
	 * @since 1.0.0
	 */
	private static function extract_attributes( array $product ): array {
		$attrs      = [];
		$attributes = isset( $product['attributes'] ) && is_array( $product['attributes'] ) ? $product['attributes'] : [];

		foreach ( $attributes as $attribute ) {
			if ( empty( $attribute['name'] ) || empty( $attribute['value'] ) ) {
				continue;
			}

			$attrs[] = [
				'name'  => $attribute['name'],
				'value' => $attribute['value'],
				'slug'  => Utils::to_slug( $attribute['name'] ),
			];
		}

		$color = isset( $product['color'] ) && ! empty( $product['color'] ) ? $product['color'] : null;
		if ( ! empty( $color ) ) {
			$attrs[] = [
				'name'  => 'ფერი',
				'value' => $color,
				'slug'  => 'color',
			];
		}

		return $attrs;
	}

	/**
	 * Extract product name from product data.
	 *
	 * @param array $product Product data from the API.
	 * @return string
	 * @since 1.0.0
	 */
	private static function extract_product_name( array $product ): string {
		return isset( $product['name'] ) && ! empty( $product['name'] ) ? $product['name'] : '';
	}

	/**
	 * Extract barcode from product data.
	 *
	 * @param array $product Product data from the API.
	 * @return string
	 * @since 1.0.0
	 */
	private static function extract_barcode( array $product ): string {
		return isset( $product['barcode'] ) && ! empty( $product['barcode'] ) ? $product['barcode'] : '';
	}
}
