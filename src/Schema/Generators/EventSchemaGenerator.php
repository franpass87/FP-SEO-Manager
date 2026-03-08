<?php
/**
 * Event schema generator.
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
use function get_the_date;
use function get_post_meta;
use function get_post;
use function wp_get_attachment_image_url;

/**
 * Generates Event schema for events and experiences with specific dates/times.
 */
class EventSchemaGenerator extends AbstractSchemaGenerator {
	/**
	 * Generate Event schema.
	 *
	 * @param int|null $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public function generate( ?int $post_id = null ): array {
		if ( ! $post_id ) {
			return array();
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}

		$schema = $this->build_base_schema();
		$schema['name'] = get_the_title( $post_id );
		$schema['url'] = get_permalink( $post_id );
		
		$excerpt = get_the_excerpt( $post_id );
		if ( $excerpt ) {
			$schema['description'] = $excerpt;
		}

		// Add start date if available
		$start_date = get_post_meta( $post_id, '_fp_start_date', true );
		if ( ! empty( $start_date ) ) {
			$schema['startDate'] = $start_date;
		} else {
			// Fallback to post date
			$schema['startDate'] = get_the_date( 'c', $post_id );
		}

		// Add end date if available
		$end_date = get_post_meta( $post_id, '_fp_end_date', true );
		if ( ! empty( $end_date ) ) {
			$schema['endDate'] = $end_date;
		}

		// Add event status
		$allowed_statuses = array( 'EventScheduled', 'EventCancelled', 'EventMovedOnline', 'EventPostponed', 'EventRescheduled' );
		$event_status     = get_post_meta( $post_id, '_fp_event_status', true );
		if ( ! empty( $event_status ) && in_array( $event_status, $allowed_statuses, true ) ) {
			$schema['eventStatus'] = 'https://schema.org/' . $event_status;
		} else {
			$schema['eventStatus'] = 'https://schema.org/EventScheduled';
		}

		// Add event attendance mode
		$allowed_modes   = array( 'OfflineEventAttendanceMode', 'OnlineEventAttendanceMode', 'MixedEventAttendanceMode' );
		$attendance_mode = get_post_meta( $post_id, '_fp_attendance_mode', true );
		if ( ! empty( $attendance_mode ) && in_array( $attendance_mode, $allowed_modes, true ) ) {
			$schema['eventAttendanceMode'] = 'https://schema.org/' . $attendance_mode;
		}

		// Add location if available
		$location_name = get_post_meta( $post_id, '_fp_location_name', true );
		$location_address = get_post_meta( $post_id, '_fp_location_address', true );
		if ( ! empty( $location_name ) || ! empty( $location_address ) ) {
			$schema['location'] = array(
				'@type' => 'Place',
			);
			if ( ! empty( $location_name ) ) {
				$schema['location']['name'] = $location_name;
			}
			if ( ! empty( $location_address ) ) {
				$schema['location']['address'] = array(
					'@type' => 'PostalAddress',
					'streetAddress' => $location_address,
				);
			}
		}

		// Add organizer if available
		$organizer_name = get_post_meta( $post_id, '_fp_organizer_name', true );
		if ( ! empty( $organizer_name ) ) {
			$schema['organizer'] = array(
				'@type' => 'Organization',
				'name' => $organizer_name,
			);
		}

		// Add image if available
		$image_id = get_post_meta( $post_id, '_fp_hero_image_id', true );
		if ( ! empty( $image_id ) ) {
			$image_url = wp_get_attachment_image_url( $image_id, 'full' );
			if ( $image_url ) {
				$schema['image'] = $image_url;
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
		return 'Event';
	}
}
