<?php
/**
 * Service for logging save operations.
 *
 * @package FP\SEO\Editor\Services
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Services;

use FP\SEO\Utils\Logger;
use function current_filter;
use function sanitize_text_field;
use function strlen;
use function substr;
use function wp_unslash;

/**
 * Service for logging save operations.
 */
class SaveLoggingService {

	/**
	 * SEO field names to log.
	 *
	 * @var array<string>
	 */
	private const SEO_FIELDS = array(
		'fp_seo_performance_metabox_present',
		'fp_seo_title',
		'fp_seo_title_sent',
		'fp_seo_meta_description',
		'fp_seo_meta_description_sent',
		'fp_seo_focus_keyword',
		'fp_seo_secondary_keywords',
	);

	/**
	 * Log save_meta call with SEO fields.
	 *
	 * @param int    $post_id Post ID.
	 * @param bool   $update Whether this is an update.
	 * @param string $post_type Post type.
	 * @return void
	 */
	public function log_save_meta_call( int $post_id, bool $update, string $post_type ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$seo_fields_present = $this->extract_seo_fields();

		Logger::debug( 'Metabox::save_meta called', array(
			'post_id' => $post_id,
			'post_type' => $post_type,
			'update' => $update ? 'yes' : 'no',
			'hook' => current_filter(),
			'post_keys_count' => isset( $_POST ) ? count( $_POST ) : 0,
			'seo_fields' => $seo_fields_present,
			'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
			'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
		) );
	}

	/**
	 * Log save_meta completion.
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $result  Save result.
	 * @return void
	 */
	public function log_save_meta_completed( int $post_id, bool $result ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		Logger::debug( 'Metabox::save_meta completed', array(
			'post_id' => $post_id,
			'result' => $result ? 'success' : 'failed',
		) );
	}

	/**
	 * Extract SEO fields from POST data for logging.
	 *
	 * @return array<string,string> Sanitized SEO fields.
	 */
	private function extract_seo_fields(): array {
		$seo_fields_present = array();

		foreach ( self::SEO_FIELDS as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$value = wp_unslash( $_POST[ $field ] );
				// Sanitize for logging to prevent XSS in log output
				if ( is_string( $value ) ) {
					$value = sanitize_text_field( $value );
					if ( strlen( $value ) > 100 ) {
						$value = substr( $value, 0, 100 ) . '...';
					}
				}
				$seo_fields_present[ $field ] = $value;
			}
		}

		return $seo_fields_present;
	}
}








