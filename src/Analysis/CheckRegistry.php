<?php
/**
 * Registry for managing analyzer checks.
 *
 * Handles check filtering, enabling/disabling based on configuration.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Analysis;

use FP\SEO\Utils\Options;
use function apply_filters;
use function array_keys;
use function function_exists;
use function is_array;

/**
 * Manages the registry of available analyzer checks.
 */
class CheckRegistry {

	/**
	 * Determines which checks should be executed based on configuration.
	 *
	 * @param array<int, CheckInterface> $checks    Available check instances.
	 * @param Context                    $context   Analyzer context.
	 *
	 * @return array<int, CheckInterface> Filtered check instances to execute.
	 */
	public static function filter_enabled_checks( array $checks, Context $context ): array {
		$available_ids = self::get_available_check_ids( $checks );
		$configured    = self::get_configured_checks();
		$enabled_ids   = self::determine_enabled_ids( $available_ids, $configured );
		$filtered_ids  = self::apply_filter_hook( $enabled_ids, $context );

		return self::filter_checks_by_ids( $checks, $filtered_ids );
	}

	/**
	 * Extracts check IDs from check instances.
	 *
	 * @param array<int, CheckInterface> $checks Check instances.
	 *
	 * @return array<string, true> Map of check ID to true.
	 */
	private static function get_available_check_ids( array $checks ): array {
		$ids = array();

		foreach ( $checks as $check ) {
			$ids[ $check->id() ] = true;
		}

		return $ids;
	}

	/**
	 * Retrieves check configuration from options.
	 *
	 * @return array<string, bool> Map of check ID to enabled status.
	 */
	private static function get_configured_checks(): array {
		$options    = Options::get();
		$configured = array();

		if ( isset( $options['analysis']['checks'] ) && is_array( $options['analysis']['checks'] ) ) {
			foreach ( $options['analysis']['checks'] as $id => $enabled ) {
				$configured[ (string) $id ] = (bool) $enabled;
			}
		}

		return $configured;
	}

	/**
	 * Determines which checks should be enabled.
	 *
	 * @param array<string, true> $available_ids Available check IDs.
	 * @param array<string, bool> $configured    Configured check statuses.
	 *
	 * @return array<string, true> Enabled check IDs.
	 */
	private static function determine_enabled_ids( array $available_ids, array $configured ): array {
		$enabled_ids = array();

		// If no configuration exists, enable all available checks.
		if ( empty( $configured ) ) {
			return $available_ids;
		}

		// Only enable checks that are both available and configured as enabled.
		foreach ( $configured as $id => $is_enabled ) {
			if ( $is_enabled && isset( $available_ids[ $id ] ) ) {
				$enabled_ids[ $id ] = true;
			}
		}

		// Fallback to all available if nothing configured.
		if ( empty( $enabled_ids ) ) {
			return $available_ids;
		}

		return $enabled_ids;
	}

	/**
	 * Applies WordPress filter hook for check filtering.
	 *
	 * @param array<string, true> $enabled_ids Enabled check IDs.
	 * @param Context             $context     Analyzer context.
	 *
	 * @return array<string, true> Filtered check IDs.
	 */
	private static function apply_filter_hook( array $enabled_ids, Context $context ): array {
		$filtered_array = array_keys( $enabled_ids );

		if ( function_exists( 'apply_filters' ) ) {
			$maybe_filtered = apply_filters( 'fp_seo_perf_checks_enabled', $filtered_array, $context );

			if ( is_array( $maybe_filtered ) ) {
				$filtered_array = $maybe_filtered;
			}
		}

		// Convert back to associative array.
		$result = array();
		foreach ( $filtered_array as $id ) {
			$result[ (string) $id ] = true;
		}

		return $result;
	}

	/**
	 * Filters check instances by enabled IDs.
	 *
	 * @param array<int, CheckInterface> $checks      All check instances.
	 * @param array<string, true>        $enabled_ids Enabled check IDs.
	 *
	 * @return array<int, CheckInterface> Filtered check instances.
	 */
	private static function filter_checks_by_ids( array $checks, array $enabled_ids ): array {
		$filtered = array();

		foreach ( $checks as $check ) {
			if ( isset( $enabled_ids[ $check->id() ] ) ) {
				$filtered[] = $check;
			}
		}

		return $filtered;
	}
}