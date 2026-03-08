<?php
/**
 * Service for formatting and processing analysis data.
 *
 * @package FP\SEO\Editor\Services
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Services;

use FP\SEO\Utils\Logger;

/**
 * Service for formatting analysis data for frontend consumption.
 */
class AnalysisDataService {
	/**
	 * Format checks array for frontend.
	 *
	 * Transforms checks from internal format to frontend format.
	 * This matches the format expected by Metabox::format_checks_for_frontend().
	 *
	 * @param array<string, mixed> $checks Raw checks array.
	 * @return array<int, array<string, mixed>> Formatted checks array (numeric array).
	 */
	public function format_checks_for_frontend( array $checks ): array {
		if ( empty( $checks ) ) {
			return array();
		}

		$formatted = array();

		// CRITICAL FIX: Ensure we iterate correctly over checks
		// Checks can be an associative array (check_id => check_data) or a numeric array
		foreach ( $checks as $check_id => $check ) {
			// Check can be an array (from Analyzer) or an object (from Result)
			if ( is_array( $check ) ) {
				// Analyzer returns: id, label, description, status, details, fix_hint, weight
				// AnalysisSectionRenderer expects: id, message, status
				// CRITICAL: Ensure we have at least an id and status
				$check_id_value = $check['id'] ?? $check_id;
				$check_status = $check['status'] ?? 'pending';
				$check_label = $check['label'] ?? $check['description'] ?? '';

				// Only add if we have at least an id
				if ( ! empty( $check_id_value ) ) {
					$formatted[] = array(
						'id'          => $check_id_value,
						'message'     => $check_label,
						'label'       => $check_label, // Keep for backward compatibility
						'status'      => $check_status,
						'hint'        => $check['fix_hint'] ?? $check['description'] ?? '',
						'description' => $check['description'] ?? '',
					);
				}
			} else {
				// Handle object with methods
				$label = method_exists( $check, 'get_label' ) ? $check->get_label() : '';
				$description = method_exists( $check, 'get_description' ) ? $check->get_description() : '';
				$formatted[] = array(
					'id'          => method_exists( $check, 'get_id' ) ? $check->get_id() : (string) $check_id,
					'message'     => $label ?: $description, // AnalysisSectionRenderer expects 'message'
					'label'       => $label, // Keep for backward compatibility
					'status'      => method_exists( $check, 'get_status' ) ? $check->get_status() : 'pending',
					'hint'        => method_exists( $check, 'get_hint' ) ? $check->get_hint() : '',
					'description' => $description,
				);
			}
		}

		return $formatted;
	}


	/**
	 * Compile complete analysis payload.
	 *
	 * @param array<string, mixed> $score Score data with 'score' and 'status' keys.
	 * @param array<string, mixed> $checks Formatted checks array.
	 * @return array<string, mixed> Complete payload.
	 */
	public function compile_payload( array $score, array $checks ): array {
		return array(
			'score'  => $score,
			'checks' => $checks,
		);
	}
}
