<?php
/**
 * TouristAttraction schema generator.
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
 * Generates TouristAttraction schema for tourist attractions like monuments, museums, parks.
 */
class TouristAttractionSchemaGenerator extends AbstractSchemaGenerator {
	/**
	 * Generate TouristAttraction schema.
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

		// Add address/location if available
		$address = get_post_meta( $post_id, '_fp_address', true );
		$latitude = get_post_meta( $post_id, '_fp_latitude', true );
		$longitude = get_post_meta( $post_id, '_fp_longitude', true );
		
		if ( ! empty( $address ) || ( ! empty( $latitude ) && ! empty( $longitude ) ) ) {
			$schema['address'] = array(
				'@type' => 'PostalAddress',
			);
			if ( ! empty( $address ) ) {
				$schema['address']['streetAddress'] = $address;
			}
		}

		// Add geo coordinates if available
		if ( ! empty( $latitude ) && ! empty( $longitude ) ) {
			$schema['geo'] = array(
				'@type' => 'GeoCoordinates',
				'latitude' => (float) $latitude,
				'longitude' => (float) $longitude,
			);
		}

		// Add opening hours if available
		$opening_hours = get_post_meta( $post_id, '_fp_opening_hours', true );
		if ( ! empty( $opening_hours ) && is_array( $opening_hours ) ) {
			$schema['openingHoursSpecification'] = array();
			foreach ( $opening_hours as $hours ) {
				if ( is_array( $hours ) && isset( $hours['dayOfWeek'] ) ) {
					$schema['openingHoursSpecification'][] = array(
						'@type' => 'OpeningHoursSpecification',
						'dayOfWeek' => $hours['dayOfWeek'],
						'opens' => $hours['opens'] ?? '',
						'closes' => $hours['closes'] ?? '',
					);
				}
			}
		}

		// Add tourist type
		$tourist_type = get_post_meta( $post_id, '_fp_tourist_type', true );
		if ( ! empty( $tourist_type ) ) {
			$schema['touristType'] = $tourist_type;
		}

		return $schema;
	}

	/**
	 * Get schema type.
	 *
	 * @return string
	 */
	protected function get_schema_type(): string {
		return 'TouristAttraction';
	}
}
