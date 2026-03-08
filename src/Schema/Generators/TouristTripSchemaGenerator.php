<?php
/**
 * TouristTrip schema generator.
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
 * Generates TouristTrip schema for tourism experiences.
 */
class TouristTripSchemaGenerator extends AbstractSchemaGenerator {
	/**
	 * Generate TouristTrip schema.
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

		// Add duration if available (from FP Experiences meta)
		$duration = get_post_meta( $post_id, '_fp_duration', true );
		if ( ! empty( $duration ) ) {
			$schema['duration'] = $duration;
		}

		// Add itinerary if available
		$itinerary = get_post_meta( $post_id, '_fp_itinerary', true );
		if ( ! empty( $itinerary ) ) {
			if ( is_array( $itinerary ) ) {
				$schema['itinerary'] = $itinerary;
			} else {
				$schema['itinerary'] = array( $itinerary );
			}
		}

		// Add tourist type if available
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
		return 'TouristTrip';
	}
}
