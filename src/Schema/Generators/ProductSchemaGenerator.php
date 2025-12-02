<?php
/**
 * Product schema generator.
 *
 * @package FP\SEO\Schema\Generators
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Schema\Generators;

use function get_comments;
use function get_comment_meta;
use function get_permalink;
use function get_woocommerce_currency;
use function wp_get_attachment_url;
use function wc_get_product;

/**
 * Generates Product schema for WooCommerce products.
 */
class ProductSchemaGenerator extends AbstractSchemaGenerator {
	/**
	 * Generate Product schema.
	 *
	 * @param int|null $post_id Product ID.
	 * @return array<string, mixed>
	 */
	public function generate( ?int $post_id = null ): array {
		if ( ! $post_id || ! class_exists( 'WooCommerce' ) ) {
			return array();
		}

		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return array();
		}

		$schema = $this->build_base_schema();
		$schema['name'] = $product->get_name();
		$schema['description'] = $product->get_description();
		$schema['url'] = get_permalink( $post_id );
		$schema['sku'] = $product->get_sku();

		// Add images
		$image_ids = $product->get_gallery_image_ids();
		if ( ! empty( $image_ids ) ) {
			$schema['image'] = array();
			foreach ( $image_ids as $image_id ) {
				$image_url = wp_get_attachment_url( $image_id );
				if ( $image_url ) {
					$schema['image'][] = $image_url;
				}
			}
		}

		// Add price
		if ( $product->get_price() ) {
			$schema['offers'] = array(
				'@type' => 'Offer',
				'price' => $product->get_price(),
				'priceCurrency' => get_woocommerce_currency(),
				'availability' => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
			);
		}

		// Add reviews
		$reviews = get_comments( array(
			'post_id' => $post_id,
			'status' => 'approve',
			'type' => 'review',
		) );

		if ( ! empty( $reviews ) ) {
			$schema['aggregateRating'] = array(
				'@type' => 'AggregateRating',
				'ratingValue' => $product->get_average_rating(),
				'reviewCount' => count( $reviews ),
			);

			$schema['review'] = array();
			foreach ( array_slice( $reviews, 0, 5 ) as $review ) {
				$schema['review'][] = array(
					'@type' => 'Review',
					'author' => array(
						'@type' => 'Person',
						'name' => $review->comment_author,
					),
					'datePublished' => $review->comment_date,
					'description' => $review->comment_content,
					'reviewRating' => array(
						'@type' => 'Rating',
						'ratingValue' => get_comment_meta( $review->comment_ID, 'rating', true ),
					),
				);
			}
		}

		return $schema;
	}

	/**
	 * Get schema type.
	 *
	 * @return string
	 */
	protected function get_schema_type(): string {
		return 'Product';
	}
}


