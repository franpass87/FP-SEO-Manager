<?php
/**
 * Service schema generator.
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
use function get_post_thumbnail_id;
use function wp_get_attachment_image_url;

/**
 * Generates Service schema for services offered.
 */
class ServiceSchemaGenerator extends AbstractSchemaGenerator {
	/**
	 * Generate Service schema.
	 *
	 * @param int|null $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public function generate( ?int $post_id = null ): array {
		if ( ! $post_id ) {
			return array();
		}

		$schema = $this->build_base_schema();
		$schema['name'] = get_the_title( $post_id );
		$schema['url'] = get_permalink( $post_id );
		
		$excerpt = get_the_excerpt( $post_id );
		if ( $excerpt ) {
			$schema['description'] = $excerpt;
		}

		// Add service type if available
		$service_type = get_post_meta( $post_id, '_fp_service_type', true );
		if ( ! empty( $service_type ) ) {
			$schema['serviceType'] = $service_type;
		}

		// Add area served if available
		$area_served = get_post_meta( $post_id, '_fp_area_served', true );
		if ( ! empty( $area_served ) ) {
			if ( is_array( $area_served ) ) {
				$schema['areaServed'] = array();
				foreach ( $area_served as $area ) {
					if ( is_string( $area ) ) {
						$schema['areaServed'][] = array(
							'@type' => 'City',
							'name' => $area,
						);
					}
				}
			} else {
				$schema['areaServed'] = array(
					'@type' => 'City',
					'name' => $area_served,
				);
			}
		}

		// Add provider if available
		$provider_name = get_post_meta( $post_id, '_fp_provider_name', true );
		if ( ! empty( $provider_name ) ) {
			$schema['provider'] = array(
				'@type' => 'Organization',
				'name' => $provider_name,
			);
			$provider_url = get_post_meta( $post_id, '_fp_provider_url', true );
			if ( ! empty( $provider_url ) ) {
				$schema['provider']['url'] = $provider_url;
			}
		}

		// Add image if available
		$image_id = get_post_meta( $post_id, '_fp_hero_image_id', true );
		if ( empty( $image_id ) ) {
			$image_id = get_post_thumbnail_id( $post_id );
		}
		if ( ! empty( $image_id ) ) {
			$image_url = wp_get_attachment_image_url( $image_id, 'full' );
			if ( $image_url ) {
				$schema['image'] = $image_url;
			}
		}

		// Add aggregate rating if available
		$rating_value = get_post_meta( $post_id, '_fp_rating_value', true );
		$rating_count = get_post_meta( $post_id, '_fp_rating_count', true );
		if ( ! empty( $rating_value ) && ! empty( $rating_count ) ) {
			$schema['aggregateRating'] = array(
				'@type' => 'AggregateRating',
				'ratingValue' => (float) $rating_value,
				'reviewCount' => (int) $rating_count,
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
		return 'Service';
	}
}
