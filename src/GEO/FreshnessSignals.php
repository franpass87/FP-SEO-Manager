<?php
/**
 * Freshness & Temporal Signals for AI Engines
 *
 * Provides temporal signals to AI engines (Gemini, Claude, OpenAI, Perplexity)
 * to indicate content freshness and validity.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\GEO;

use WP_Post;

/**
 * Manages freshness signals for AI consumption
 */
class FreshnessSignals {

	/**
	 * Meta key for update frequency
	 */
	private const META_UPDATE_FREQUENCY = '_fp_seo_update_frequency';

	/**
	 * Meta key for next review date
	 */
	private const META_NEXT_REVIEW = '_fp_seo_next_review_date';

	/**
	 * Meta key for content version
	 */
	private const META_VERSION = '_fp_seo_content_version';

	/**
	 * Meta key for changelog
	 */
	private const META_CHANGELOG = '_fp_seo_changelog';

	/**
	 * Meta key for data sources
	 */
	private const META_DATA_SOURCES = '_fp_seo_data_sources';

	/**
	 * Get comprehensive freshness data for a post
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed> Freshness data.
	 */
	public function get_freshness_data( int $post_id ): array {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return array();
		}

		return array(
			'published_date'         => $this->format_datetime( $post->post_date_gmt ),
			'last_updated'           => $this->format_datetime( $post->post_modified_gmt ),
			'update_frequency'       => $this->get_update_frequency( $post_id ),
			'next_review_date'       => $this->get_next_review_date( $post_id ),
			'version'                => $this->get_content_version( $post_id ),
			'changelog'              => $this->get_changelog( $post_id ),
			'content_type'           => $this->detect_content_type( $post ),
			'temporal_validity'      => $this->get_temporal_validity( $post_id ),
			'data_sources_freshness' => $this->get_data_sources_freshness( $post_id ),
			'freshness_score'        => $this->calculate_freshness_score( $post ),
			'age_days'               => $this->get_age_in_days( $post ),
			'recency_score'          => $this->calculate_recency_score( $post ),
		);
	}

	/**
	 * Format datetime to ISO 8601 (AI-friendly)
	 *
	 * @param string $datetime MySQL datetime.
	 * @return string ISO 8601 formatted datetime.
	 */
	private function format_datetime( string $datetime ): string {
		if ( empty( $datetime ) || '0000-00-00 00:00:00' === $datetime ) {
			return gmdate( 'c' );
		}

		$timestamp = strtotime( $datetime . ' UTC' );
		
		// Handle strtotime failure
		if ( false === $timestamp ) {
			return gmdate( 'c' );
		}

		return gmdate( 'c', $timestamp );
	}

	/**
	 * Get update frequency setting
	 *
	 * @param int $post_id Post ID.
	 * @return string Update frequency (daily, weekly, monthly, yearly, evergreen).
	 */
	private function get_update_frequency( int $post_id ): string {
		$frequency = get_post_meta( $post_id, self::META_UPDATE_FREQUENCY, true );

		if ( empty( $frequency ) ) {
			// Auto-detect based on post age and modification history
			return $this->auto_detect_frequency( $post_id );
		}

		return sanitize_text_field( $frequency );
	}

	/**
	 * Auto-detect update frequency based on modification history
	 *
	 * @param int $post_id Post ID.
	 * @return string Detected frequency.
	 */
	private function auto_detect_frequency( int $post_id ): string {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return 'evergreen';
		}

		$published  = strtotime( $post->post_date_gmt );
		$modified   = strtotime( $post->post_modified_gmt );
		
		// Handle strtotime failures
		if ( false === $published || false === $modified ) {
			return 'evergreen';
		}

		$age_days   = ( time() - $published ) / DAY_IN_SECONDS;
		$days_since = ( time() - $modified ) / DAY_IN_SECONDS;

		// Recently updated = more frequent
		if ( $days_since < 7 ) {
			return 'daily';
		}

		if ( $days_since < 30 ) {
			return 'weekly';
		}

		if ( $days_since < 90 ) {
			return 'monthly';
		}

		// Old content rarely updated = evergreen
		if ( $age_days > 365 && $days_since > 180 ) {
			return 'evergreen';
		}

		return 'yearly';
	}

	/**
	 * Get next scheduled review date
	 *
	 * @param int $post_id Post ID.
	 * @return string|null Next review date in ISO 8601 or null.
	 */
	private function get_next_review_date( int $post_id ): ?string {
		$next_review = get_post_meta( $post_id, self::META_NEXT_REVIEW, true );

		if ( ! empty( $next_review ) ) {
			return $this->format_datetime( $next_review );
		}

		// Calculate based on frequency
		$frequency = $this->get_update_frequency( $post_id );
		$post      = get_post( $post_id );

		if ( ! $post ) {
			return null;
		}

		$last_modified = strtotime( $post->post_modified_gmt );
		
		// Handle strtotime failure
		if ( false === $last_modified ) {
			return null;
		}

		$intervals = array(
			'daily'     => '+1 day',
			'weekly'    => '+1 week',
			'monthly'   => '+1 month',
			'yearly'    => '+1 year',
			'evergreen' => '+2 years',
		);

		$interval = $intervals[ $frequency ] ?? '+1 month';
		
		$next_review = strtotime( $interval, $last_modified );
		
		// Handle strtotime failure
		if ( false === $next_review ) {
			return null;
		}

		return gmdate( 'c', $next_review );
	}

	/**
	 * Get content version
	 *
	 * @param int $post_id Post ID.
	 * @return string Version number (e.g., "2.1").
	 */
	private function get_content_version( int $post_id ): string {
		$version = get_post_meta( $post_id, self::META_VERSION, true );

		if ( empty( $version ) ) {
			// Auto-generate based on revisions
			return $this->auto_generate_version( $post_id );
		}

		return sanitize_text_field( $version );
	}

	/**
	 * Auto-generate version based on revisions
	 *
	 * @param int $post_id Post ID.
	 * @return string Version number.
	 */
	private function auto_generate_version( int $post_id ): string {
		$revisions = wp_get_post_revisions( $post_id );
		$count     = count( $revisions );

		$major = (int) floor( $count / 10 ) + 1;
		$minor = $count % 10;

		return sprintf( '%d.%d', $major, $minor );
	}

	/**
	 * Get changelog entries
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, array<string, mixed>> Changelog entries.
	 */
	private function get_changelog( int $post_id ): array {
		$changelog = get_post_meta( $post_id, self::META_CHANGELOG, true );

		if ( is_array( $changelog ) && ! empty( $changelog ) ) {
			return array_map( function ( $entry ) {
				return array(
					'date'   => $this->format_datetime( $entry['date'] ?? gmdate( 'Y-m-d H:i:s' ) ),
					'change' => sanitize_text_field( $entry['change'] ?? '' ),
					'type'   => sanitize_text_field( $entry['type'] ?? 'update' ), // update, fix, new
				);
			}, $changelog );
		}

		// Auto-generate from recent revisions
		return $this->auto_generate_changelog( $post_id );
	}

	/**
	 * Auto-generate changelog from revisions
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, array<string, mixed>> Changelog entries.
	 */
	private function auto_generate_changelog( int $post_id ): array {
		$revisions = wp_get_post_revisions( $post_id, array( 'posts_per_page' => 5 ) );
		$changelog = array();

		foreach ( $revisions as $revision ) {
			$changelog[] = array(
				'date'   => $this->format_datetime( $revision->post_modified_gmt ),
				'change' => sprintf( 'Content updated (revision %d)', $revision->ID ),
				'type'   => 'update',
			);
		}

		return $changelog;
	}

	/**
	 * Detect content type (evergreen, news, trending, seasonal)
	 *
	 * @param WP_Post $post Post object.
	 * @return string Content type.
	 */
	private function detect_content_type( WP_Post $post ): string {
		// Check post meta first
		$type = get_post_meta( $post->ID, '_fp_seo_content_type', true );

		if ( ! empty( $type ) ) {
			return sanitize_text_field( $type );
		}

		// Auto-detect from categories/tags
		$categories = wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) );
		$tags       = wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) );
		$all_terms  = array_merge( $categories, $tags );

		$terms_lower = array_map( 'strtolower', $all_terms );

		// News indicators
		if ( array_intersect( $terms_lower, array( 'news', 'notizie', 'breaking', 'update', 'announcement' ) ) ) {
			return 'news';
		}

		// Seasonal indicators
		if ( array_intersect( $terms_lower, array( 'natale', 'estate', 'primavera', 'autunno', 'inverno', 'seasonal' ) ) ) {
			return 'seasonal';
		}

		// Check if content is old and stable = evergreen
		$published = strtotime( $post->post_date_gmt );
		
		// Handle strtotime failure
		if ( false === $published ) {
			return 'evergreen'; // Safe default
		}
		
		$age_days = ( time() - $published ) / DAY_IN_SECONDS;

		if ( $age_days > 365 ) {
			return 'evergreen';
		}

		return 'evergreen'; // Default
	}

	/**
	 * Get temporal validity information
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed> Validity data.
	 */
	private function get_temporal_validity( int $post_id ): array {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return array();
		}

		$content_type = $this->detect_content_type( $post );

		$validity = array(
			'valid_from'  => $this->format_datetime( $post->post_date_gmt ),
			'valid_until' => null, // null = always valid
			'seasonal'    => 'seasonal' === $content_type,
		);

		// For news content, set expiration
		if ( 'news' === $content_type ) {
			$published_timestamp = strtotime( $post->post_date_gmt );
			
			// Handle strtotime failure
			if ( false !== $published_timestamp ) {
				$expiration          = $published_timestamp + ( 30 * DAY_IN_SECONDS ); // 30 days validity
				$validity['valid_until'] = gmdate( 'c', $expiration );
			}
		}

		return $validity;
	}

	/**
	 * Get data sources freshness information
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, array<string, mixed>> Data sources.
	 */
	private function get_data_sources_freshness( int $post_id ): array {
		$sources = get_post_meta( $post_id, self::META_DATA_SOURCES, true );

		if ( ! is_array( $sources ) || empty( $sources ) ) {
			return array();
		}

		return array_map( function ( $source ) {
			$date       = $source['date'] ?? gmdate( 'Y-m-d' );
			$timestamp  = strtotime( $date );
			
			// Handle invalid dates
			if ( false === $timestamp ) {
				$timestamp = time();
			}
			
			$age_days   = ( time() - $timestamp ) / DAY_IN_SECONDS;
			$is_fresh   = $age_days < 365; // Fresh if less than 1 year old

			return array(
				'source' => sanitize_text_field( $source['name'] ?? 'Unknown' ),
				'date'   => $this->format_datetime( $date ),
				'fresh'  => $is_fresh,
				'age_days' => (int) $age_days,
				'url'    => isset( $source['url'] ) ? esc_url_raw( $source['url'] ) : null,
			);
		}, $sources );
	}

	/**
	 * Calculate overall freshness score (0-1)
	 *
	 * @param WP_Post $post Post object.
	 * @return float Freshness score.
	 */
	private function calculate_freshness_score( WP_Post $post ): float {
		$age_days          = $this->get_age_in_days( $post );
		$days_since_update = ( time() - strtotime( $post->post_modified_gmt ) ) / DAY_IN_SECONDS;

		// Age penalty (decays over time)
		$age_score = max( 0, 1 - ( $age_days / 365 ) ); // Decreases by 1.0 over 1 year

		// Recency bonus (recently updated content)
		$recency_score = max( 0, 1 - ( $days_since_update / 180 ) ); // Decreases by 1.0 over 6 months

		// Combined score (weighted average)
		$score = ( $age_score * 0.3 ) + ( $recency_score * 0.7 );

		return round( max( 0.0, min( 1.0, $score ) ), 2 );
	}

	/**
	 * Get content age in days
	 *
	 * @param WP_Post $post Post object.
	 * @return int Age in days.
	 */
	private function get_age_in_days( WP_Post $post ): int {
		$published = strtotime( $post->post_date_gmt );
		
		// Handle strtotime failure
		if ( false === $published ) {
			return 0;
		}

		$age = ( time() - $published ) / DAY_IN_SECONDS;

		return (int) $age;
	}

	/**
	 * Calculate recency score (0-1) - how recently was content updated
	 *
	 * @param WP_Post $post Post object.
	 * @return float Recency score.
	 */
	private function calculate_recency_score( WP_Post $post ): float {
		$modified = strtotime( $post->post_modified_gmt );
		
		// Handle strtotime failure
		if ( false === $modified ) {
			return 0.5; // Default moderate score
		}
		
		$days_since_update = ( time() - $modified ) / DAY_IN_SECONDS;

		// Perfect score if updated today, decreases over time
		if ( $days_since_update < 1 ) {
			return 1.0;
		}

		if ( $days_since_update < 7 ) {
			return 0.95;
		}

		if ( $days_since_update < 30 ) {
			return 0.85;
		}

		if ( $days_since_update < 90 ) {
			return 0.7;
		}

		if ( $days_since_update < 180 ) {
			return 0.5;
		}

		if ( $days_since_update < 365 ) {
			return 0.3;
		}

		return 0.1;
	}

	/**
	 * Update content version (bump version number)
	 *
	 * @param int    $post_id Post ID.
	 * @param string $change  Changelog entry.
	 * @param string $type    Change type (update, fix, new).
	 * @return bool Success.
	 */
	public function bump_version( int $post_id, string $change, string $type = 'update' ): bool {
		// Get current version
		$current_version = $this->get_content_version( $post_id );
		list( $major, $minor ) = array_pad( explode( '.', $current_version ), 2, '0' );

		// Increment version
		if ( 'major' === $type ) {
			$major = (int) $major + 1;
			$minor = 0;
		} else {
			$minor = (int) $minor + 1;
		}

		$new_version = sprintf( '%d.%d', $major, $minor );

		// Update version
		update_post_meta( $post_id, self::META_VERSION, $new_version );

		// Add to changelog
		$changelog = $this->get_changelog( $post_id );
		array_unshift( $changelog, array(
			'date'   => gmdate( 'Y-m-d H:i:s' ),
			'change' => sanitize_text_field( $change ),
			'type'   => sanitize_text_field( $type ),
		) );

		// Keep only last 10 entries
		$changelog = array_slice( $changelog, 0, 10 );

		update_post_meta( $post_id, self::META_CHANGELOG, $changelog );

		return true;
	}

	/**
	 * Set update frequency
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $frequency Frequency (daily, weekly, monthly, yearly, evergreen).
	 * @return bool Success.
	 */
	public function set_update_frequency( int $post_id, string $frequency ): bool {
		$valid_frequencies = array( 'daily', 'weekly', 'monthly', 'yearly', 'evergreen' );

		if ( ! in_array( $frequency, $valid_frequencies, true ) ) {
			return false;
		}

		return update_post_meta( $post_id, self::META_UPDATE_FREQUENCY, $frequency );
	}

	/**
	 * Add data source
	 *
	 * @param int    $post_id Post ID.
	 * @param string $name    Source name.
	 * @param string $date    Source date (YYYY-MM-DD).
	 * @param string $url     Source URL.
	 * @return bool Success.
	 */
	public function add_data_source( int $post_id, string $name, string $date, string $url = '' ): bool {
		$sources   = get_post_meta( $post_id, self::META_DATA_SOURCES, true );
		$sources   = is_array( $sources ) ? $sources : array();

		$sources[] = array(
			'name' => sanitize_text_field( $name ),
			'date' => sanitize_text_field( $date ),
			'url'  => esc_url_raw( $url ),
		);

		return update_post_meta( $post_id, self::META_DATA_SOURCES, $sources );
	}
}

