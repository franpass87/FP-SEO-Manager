<?php
/**
 * Offer schema generator.
 *
 * @package FP\SEO\Schema\Generators
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Schema\Generators;

use function get_permalink;
use function get_the_excerpt;
use function get_the_title;
use function get_post_meta;

/**
 * Generates Offer schema for prices and offers.
 */
class OfferSchemaGenerator extends AbstractSchemaGenerator {
	/**
	 * Generate Offer schema.
	 *
	 * @param int|null $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public function generate( ?int $post_id = null ): array {
		if ( ! $post_id ) {
			return array();
		}

		$schema = $this->build_base_schema();
		
		// Get base price
		$base_price = get_post_meta( $post_id, '_fp_base_price', true );
		if ( empty( $base_price ) ) {
			// Try to get from pricing meta
			$pricing = get_post_meta( $post_id, '_fp_exp_pricing', true );
			if ( is_array( $pricing ) && isset( $pricing['base_price'] ) ) {
				$base_price = $pricing['base_price'];
			}
		}

		// Get currency
		$currency = 'EUR';
		if ( function_exists( 'get_woocommerce_currency' ) ) {
			$currency = get_woocommerce_currency();
		}

		if ( ! empty( $base_price ) ) {
			$schema['price'] = (string) $base_price;
			$schema['priceCurrency'] = $currency;
		}

		// Get price from WooCommerce if available
		if ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' ) ) {
			$product_id = get_post_meta( $post_id, '_fp_wc_product_id', true );
			if ( ! empty( $product_id ) ) {
				$product = wc_get_product( $product_id );
				if ( $product ) {
					$price = $product->get_price();
					if ( $price ) {
						$schema['price'] = (string) $price;
						$schema['priceCurrency'] = $currency;
					}
					
					// Add availability
					$availability = $product->get_stock_status();
					if ( 'instock' === $availability ) {
						$schema['availability'] = 'https://schema.org/InStock';
					} elseif ( 'outofstock' === $availability ) {
						$schema['availability'] = 'https://schema.org/OutOfStock';
					} elseif ( 'onbackorder' === $availability ) {
						$schema['availability'] = 'https://schema.org/PreOrder';
					}
				}
			}
		}

		// Add offer name
		$offer_name = get_post_meta( $post_id, '_fp_offer_name', true );
		if ( ! empty( $offer_name ) ) {
			$schema['name'] = $offer_name;
		} else {
			$schema['name'] = get_the_title( $post_id );
		}

		// Add description
		$excerpt = get_the_excerpt( $post_id );
		if ( $excerpt ) {
			$schema['description'] = $excerpt;
		}

		// Add URL
		$schema['url'] = get_permalink( $post_id );

		// Add valid from/to dates if available
		$valid_from = get_post_meta( $post_id, '_fp_offer_valid_from', true );
		if ( ! empty( $valid_from ) ) {
			$schema['validFrom'] = $valid_from;
		}

		$valid_through = get_post_meta( $post_id, '_fp_offer_valid_through', true );
		if ( ! empty( $valid_through ) ) {
			$schema['validThrough'] = $valid_through;
		}

		// Add price specification
		if ( ! empty( $schema['price'] ) ) {
			$price_spec = array(
				'@type' => 'UnitPriceSpecification',
				'price' => $schema['price'],
				'priceCurrency' => $schema['priceCurrency'],
			);

			// Add unit code if available
			$unit_code = get_post_meta( $post_id, '_fp_offer_unit_code', true );
			if ( ! empty( $unit_code ) ) {
				$price_spec['unitCode'] = $unit_code;
			} else {
				// Default to person for experiences
				$price_spec['unitCode'] = 'C62'; // Person
			}

			$schema['priceSpecification'] = $price_spec;
		}

		// Add seller if available
		$seller_name = get_post_meta( $post_id, '_fp_seller_name', true );
		if ( ! empty( $seller_name ) ) {
			$schema['seller'] = array(
				'@type' => 'Organization',
				'name' => $seller_name,
			);
			$seller_url = get_post_meta( $post_id, '_fp_seller_url', true );
			if ( ! empty( $seller_url ) ) {
				$schema['seller']['url'] = $seller_url;
			}
		}

		// Add item offered
		$item_offered = get_post_meta( $post_id, '_fp_item_offered', true );
		if ( ! empty( $item_offered ) ) {
			$schema['itemOffered'] = array(
				'@type' => 'Service',
				'name' => $item_offered,
			);
		} else {
			// Default to experience name
			$schema['itemOffered'] = array(
				'@type' => 'Service',
				'name' => get_the_title( $post_id ),
			);
		}

		return $schema;
	}

	/**
	 * Get schema type.
	 *
	 * @return string
	 */
	protected function get_schema_type(): string {
		return 'Offer';
	}
}
