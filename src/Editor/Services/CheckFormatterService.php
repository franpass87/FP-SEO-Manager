<?php
/**
 * Service for formatting analysis checks for frontend display.
 *
 * @package FP\SEO\Editor\Services
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Services;

/**
 * Service for formatting analysis checks for frontend display.
 */
class CheckFormatterService {

	/**
	 * Format checks for frontend display.
	 *
	 * @param array $checks Raw checks from analyzer.
	 * @return array Formatted checks array.
	 */
	public function format_checks_for_frontend( array $checks ): array {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'FP SEO DEBUG: CheckFormatterService::format_checks_for_frontend() called - input_count=' . count( $checks ) );
		}
		
		$formatted = array();
		foreach ( $checks as $check_id => $check ) {
			// Check can be an array (from Analyzer) or an object (from Result)
			if ( is_array( $check ) ) {
				// Analyzer returns: id, label, description, status, details, fix_hint, weight
				$formatted[] = array(
					'id' => $check['id'] ?? $check_id,
					'message' => $check['label'] ?? $check['description'] ?? '', // AnalysisSectionRenderer expects 'message'
					'label' => $check['label'] ?? '', // Keep for backward compatibility
					'status' => $check['status'] ?? 'pending',
					'hint' => $check['fix_hint'] ?? $check['description'] ?? '',
					'description' => $check['description'] ?? '',
				);
			} else {
				// Handle object with methods
				$label = method_exists( $check, 'get_label' ) ? $check->get_label() : '';
				$description = method_exists( $check, 'get_description' ) ? $check->get_description() : '';
				$formatted[] = array(
					'id' => method_exists( $check, 'get_id' ) ? $check->get_id() : (string) $check_id,
					'message' => $label ?: $description, // AnalysisSectionRenderer expects 'message'
					'label' => $label, // Keep for backward compatibility
					'status' => method_exists( $check, 'get_status' ) ? $check->get_status() : 'pending',
					'hint' => method_exists( $check, 'get_hint' ) ? $check->get_hint() : '',
					'description' => $description,
				);
			}
		}
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'FP SEO DEBUG: CheckFormatterService::format_checks_for_frontend() - output_count=' . count( $formatted ) );
		}
		return $formatted;
	}
}






