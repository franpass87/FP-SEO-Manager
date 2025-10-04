<?php
/**
 * Options storage and validation helpers.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Utils;

/**
 * Handles plugin option defaults, sanitization, and access helpers.
 */
class Options {

	public const OPTION_KEY   = 'fp_seo_perf_options';
	public const OPTION_GROUP = 'fp_seo_performance';

	private const DEFAULT_LANGUAGE = 'en';

		/**
		 * Provides the available UI languages.
		 *
		 * @return array<string, string>
		 */
	public static function get_language_choices(): array {
			return array(
				'en' => __( 'English', 'fp-seo-performance' ),
				'es' => __( 'Spanish', 'fp-seo-performance' ),
				'fr' => __( 'French', 'fp-seo-performance' ),
				'de' => __( 'German', 'fp-seo-performance' ),
				'it' => __( 'Italian', 'fp-seo-performance' ),
			);
	}

	/**
	 * Keys available for analysis checks toggles.
	 *
	 * @return string[] List of check identifiers.
	 */
	public static function get_check_keys(): array {
			return array(
				'title_length',
				'meta_description',
				'h1_presence',
				'headings_structure',
				'image_alt',
				'canonical',
				'robots',
				'og_cards',
				'twitter_cards',
				'schema_presets',
				'internal_links',
			);
	}

		/**
		 * Provides default scoring weights keyed by analyzer check.
		 *
		 * @return array<string, float>
		 */
	public static function default_scoring_weights(): array {
			$weights = array();

		foreach ( self::get_check_keys() as $key ) {
				$weights[ $key ] = 1.0;
		}

			return $weights;
	}

	/**
	 * Provides the default option structure.
	 *
	 * @return array<string, mixed> Default options.
	 */
	public static function get_defaults(): array {
		$checks = array();

		foreach ( self::get_check_keys() as $key ) {
			$checks[ $key ] = true;
		}

		return array(
			'general'     => array(
				'enable_analyzer' => true,
				'language'        => self::DEFAULT_LANGUAGE,
				'admin_bar_badge' => false,
			),
			'analysis'    => array(
				'checks'           => $checks,
				'title_length_min' => 50,
				'title_length_max' => 60,
				'meta_length_min'  => 120,
				'meta_length_max'  => 160,
				'canonical_policy' => 'auto',
				'enable_og'        => true,
				'enable_twitter'   => true,
			),
			'scoring'     => array(
				'weights' => self::default_scoring_weights(),
			),
			'performance' => array(
				'enable_psi'  => false,
				'psi_api_key' => '',
				'heuristics'  => array(
					'image_alt_coverage' => true,
					'inline_css'         => true,
					'image_count'        => true,
					'heading_depth'      => true,
				),
			),
			'advanced'    => array(
				'capability'        => 'manage_options',
				'telemetry_enabled' => false,
			),
		);
	}

	/**
	 * Retrieves the sanitized options from the database.
	 *
	 * @return array<string, mixed> Sanitized option data.
	 */
	public static function get(): array {
		$stored = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$sanitized = self::sanitize( $stored );

		return self::merge_defaults( $sanitized );
	}

	/**
	 * Sanitizes an options payload and records validation notices.
	 *
	 * @param array<string, mixed> $input Raw option values.
	 *
	 * @return array<string, mixed> Sanitized options.
	 */
	public static function sanitize( array $input ): array {
		$defaults  = self::get_defaults();
		$sanitized = $defaults;

		$general                                 = is_array( $input['general'] ?? null ) ? $input['general'] : array();
		$sanitized['general']['enable_analyzer'] = self::to_bool( $general['enable_analyzer'] ?? $defaults['general']['enable_analyzer'] );

		$language = is_string( $general['language'] ?? null ) ? $general['language'] : self::DEFAULT_LANGUAGE;
		$language = self::sanitize_language( $language );

		if ( '' === $language ) {
			$language = self::DEFAULT_LANGUAGE;
		}

		$sanitized['general']['language'] = $language;

		$sanitized['general']['admin_bar_badge'] = self::to_bool( $general['admin_bar_badge'] ?? $defaults['general']['admin_bar_badge'] );

		$analysis                        = is_array( $input['analysis'] ?? null ) ? $input['analysis'] : array();
		$checks                          = is_array( $analysis['checks'] ?? null ) ? $analysis['checks'] : array();
		$sanitized['analysis']['checks'] = array();
		foreach ( self::get_check_keys() as $check_key ) {
			$sanitized['analysis']['checks'][ $check_key ] = self::to_bool( $checks[ $check_key ] ?? true );
		}

		$title_min_input = $analysis['title_length_min'] ?? $defaults['analysis']['title_length_min'];
		$title_max_input = $analysis['title_length_max'] ?? $defaults['analysis']['title_length_max'];

		$sanitized['analysis']['title_length_min'] = self::bounded_int(
			$analysis['title_length_min'] ?? $defaults['analysis']['title_length_min'],
			10,
			80,
			$defaults['analysis']['title_length_min']
		);
		$sanitized['analysis']['title_length_max'] = self::bounded_int(
			$analysis['title_length_max'] ?? $defaults['analysis']['title_length_max'],
			30,
			80,
			$defaults['analysis']['title_length_max']
		);

		if ( $sanitized['analysis']['title_length_min'] > $sanitized['analysis']['title_length_max'] ) {
			$sanitized['analysis']['title_length_min'] = $defaults['analysis']['title_length_min'];
			$sanitized['analysis']['title_length_max'] = $defaults['analysis']['title_length_max'];
			self::add_validation_notice(
				'fp_seo_perf_title_range',
				__( 'Title length minimum cannot exceed maximum. Resetting to defaults.', 'fp-seo-performance' )
			);
		} elseif ( ! is_numeric( $title_min_input ) || ! is_numeric( $title_max_input ) ) {
			self::add_validation_notice(
				'fp_seo_perf_title_numeric',
				__( 'Title length thresholds must be numbers.', 'fp-seo-performance' )
			);
		}

		$meta_min_input = $analysis['meta_length_min'] ?? $defaults['analysis']['meta_length_min'];
		$meta_max_input = $analysis['meta_length_max'] ?? $defaults['analysis']['meta_length_max'];

		$sanitized['analysis']['meta_length_min'] = self::bounded_int(
			$analysis['meta_length_min'] ?? $defaults['analysis']['meta_length_min'],
			50,
			200,
			$defaults['analysis']['meta_length_min']
		);
		$sanitized['analysis']['meta_length_max'] = self::bounded_int(
			$analysis['meta_length_max'] ?? $defaults['analysis']['meta_length_max'],
			90,
			220,
			$defaults['analysis']['meta_length_max']
		);

		if ( $sanitized['analysis']['meta_length_min'] > $sanitized['analysis']['meta_length_max'] ) {
			$sanitized['analysis']['meta_length_min'] = $defaults['analysis']['meta_length_min'];
			$sanitized['analysis']['meta_length_max'] = $defaults['analysis']['meta_length_max'];
			self::add_validation_notice(
				'fp_seo_perf_meta_range',
				__( 'Meta description minimum cannot exceed maximum. Resetting to defaults.', 'fp-seo-performance' )
			);
		} elseif ( ! is_numeric( $meta_min_input ) || ! is_numeric( $meta_max_input ) ) {
			self::add_validation_notice(
				'fp_seo_perf_meta_numeric',
				__( 'Meta description thresholds must be numbers.', 'fp-seo-performance' )
			);
		}

				$sanitized['analysis']['canonical_policy'] = self::sanitize_choice(
					$analysis['canonical_policy'] ?? $defaults['analysis']['canonical_policy'],
					array( 'auto', 'none' ),
					$defaults['analysis']['canonical_policy']
				);

				$scoring_input                   = is_array( $input['scoring'] ?? null ) ? $input['scoring'] : array();
				$weight_input                    = is_array( $scoring_input['weights'] ?? null ) ? $scoring_input['weights'] : array();
				$sanitized['scoring']['weights'] = array();

		foreach ( self::get_check_keys() as $check_key ) {
				$raw_weight = $weight_input[ $check_key ] ?? $defaults['scoring']['weights'][ $check_key ];

				$sanitized['scoring']['weights'][ $check_key ] = self::bounded_float(
					$raw_weight,
					0.0,
					5.0,
					$defaults['scoring']['weights'][ $check_key ]
				);

			if ( ! is_numeric( $raw_weight ) ) {
				self::add_validation_notice(
					'fp_seo_perf_scoring_weights',
					__( 'Score weights must be numeric values. Using defaults.', 'fp-seo-performance' ),
					'warning'
				);
			}
		}

				$sanitized['analysis']['enable_og']      = self::to_bool( $analysis['enable_og'] ?? $defaults['analysis']['enable_og'] );
				$sanitized['analysis']['enable_twitter'] = self::to_bool( $analysis['enable_twitter'] ?? $defaults['analysis']['enable_twitter'] );

		$performance                             = is_array( $input['performance'] ?? null ) ? $input['performance'] : array();
		$sanitized['performance']['enable_psi']  = self::to_bool( $performance['enable_psi'] ?? $defaults['performance']['enable_psi'] );
		$sanitized['performance']['psi_api_key'] = self::sanitize_text( $performance['psi_api_key'] ?? $defaults['performance']['psi_api_key'] );

		$heuristics = is_array( $performance['heuristics'] ?? null ) ? $performance['heuristics'] : array();
		foreach ( $defaults['performance']['heuristics'] as $key => $default_value ) {
			$sanitized['performance']['heuristics'][ $key ] = self::to_bool( $heuristics[ $key ] ?? $default_value );
		}

		if ( $sanitized['performance']['enable_psi'] && '' === $sanitized['performance']['psi_api_key'] ) {
			self::add_validation_notice(
				'fp_seo_perf_psi_key',
				__( 'PSI integration requires an API key. Please supply one or disable PSI.', 'fp-seo-performance' ),
				'warning'
			);
		}

		if ( $sanitized['general']['admin_bar_badge'] && ! $sanitized['general']['enable_analyzer'] ) {
			self::add_validation_notice(
				'fp_seo_perf_badge_requires_analyzer',
				__( 'Admin bar badge requires the analyzer to be enabled.', 'fp-seo-performance' ),
				'warning'
			);
		}

		$advanced                                   = is_array( $input['advanced'] ?? null ) ? $input['advanced'] : array();
		$sanitized['advanced']['capability']        = self::sanitize_capability(
			$advanced['capability'] ?? $defaults['advanced']['capability'],
			$defaults['advanced']['capability']
		);
		$sanitized['advanced']['telemetry_enabled'] = self::to_bool(
			$advanced['telemetry_enabled'] ?? $defaults['advanced']['telemetry_enabled']
		);

		return $sanitized;
	}

	/**
	 * Updates the stored options value.
	 *
	 * @param array<string, mixed> $value Option payload.
	 */
	public static function update( array $value ): void {
		update_option( self::OPTION_KEY, self::sanitize( $value ) );
	}

	/**
	 * Retrieves the configured capability for admin access.
	 */
	public static function get_capability(): string {
			$options = self::get();

			return $options['advanced']['capability'] ?? 'manage_options';
	}

		/**
		 * Retrieves the configured scoring weights.
		 *
		 * @return array<string, float>
		 */
	public static function get_scoring_weights(): array {
			$options = self::get();

			$defaults = self::default_scoring_weights();
			$weights  = $options['scoring']['weights'] ?? $defaults;

		if ( ! is_array( $weights ) ) {
				return $defaults;
		}

			$normalized = array();

		foreach ( self::get_check_keys() as $check_key ) {
				$normalized[ $check_key ] = self::bounded_float(
					$weights[ $check_key ] ?? $defaults[ $check_key ],
					0.0,
					5.0,
					$defaults[ $check_key ]
				);
		}

			return $normalized;
	}

	/**
	 * Merges defaults with user supplied values.
	 *
	 * @param array<string, mixed> $value Partial options.
	 *
	 * @return array<string, mixed> Options with defaults applied.
	 */
	public static function merge_defaults( array $value ): array {
		$defaults = self::get_defaults();

		return array_replace_recursive( $defaults, $value );
	}

	/**
	 * Normalizes a scalar value to a boolean.
	 *
	 * @param mixed $value Raw input.
	 */
	private static function to_bool( mixed $value ): bool {
		if ( is_string( $value ) ) {
			$value = strtolower( $value );

			if ( in_array( $value, array( '0', 'false', 'off', 'no' ), true ) ) {
				return false;
			}

			if ( in_array( $value, array( '1', 'true', 'on', 'yes' ), true ) ) {
				return true;
			}
		}

		return (bool) $value;
	}

	/**
	 * Ensures an integer value falls within a given range.
	 *
	 * @param mixed $value    Raw input.
	 * @param int   $min      Minimum allowed.
	 * @param int   $max      Maximum allowed.
	 * @param int   $fallback Fallback value when validation fails.
	 */
	private static function bounded_int( mixed $value, int $min, int $max, int $fallback ): int {
		if ( is_numeric( $value ) ) {
				$value = (int) $value;
			if ( $value < $min ) {
				return $min;
			}

			if ( $value > $max ) {
				return $max;
			}

				return $value;
		}

			return $fallback;
	}

		/**
		 * Ensures a float falls within the provided range.
		 *
		 * @param mixed $value    Raw input.
		 * @param float $min      Minimum allowed.
		 * @param float $max      Maximum allowed.
		 * @param float $fallback Fallback value when validation fails.
		 */
	private static function bounded_float( mixed $value, float $min, float $max, float $fallback ): float {
		if ( is_numeric( $value ) ) {
				$value = (float) $value;

			if ( $value < $min ) {
				return $min;
			}

			if ( $value > $max ) {
					return $max;
			}

				return $value;
		}

			return $fallback;
	}

	/**
	 * Validates a choice against allowed values.
	 *
	 * @param mixed              $value    Raw input.
	 * @param array<int, string> $allowed  Allowed values.
	 * @param string             $fallback Fallback when invalid.
	 */
	private static function sanitize_choice( mixed $value, array $allowed, string $fallback ): string {
		if ( is_string( $value ) && in_array( $value, $allowed, true ) ) {
			return $value;
		}

		return $fallback;
	}

	/**
	 * Sanitizes the configured language code.
	 *
	 * @param string $value Raw language input.
	 */
	private static function sanitize_language( string $value ): string {
				$value = strtolower( trim( $value ) );
				$value = preg_replace( '/[^a-z\-]/', '', $value );

		if ( ! is_string( $value ) || '' === $value ) {
				return self::DEFAULT_LANGUAGE;
		}

		if ( strlen( $value ) > 10 ) {
				$value = substr( $value, 0, 10 );
		}

		if ( ! isset( self::get_language_choices()[ $value ] ) ) {
				return self::DEFAULT_LANGUAGE;
		}

				return $value;
	}

	/**
	 * Sanitizes plain text values.
	 *
	 * @param mixed $value Raw input.
	 */
	private static function sanitize_text( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = (string) $value;

		if ( function_exists( 'sanitize_text_field' ) ) {
			/**
			 * Callable sanitizer callback.
			 *
			 * @var callable
			 */
			$sanitizer = 'sanitize_text_field';

			return (string) $sanitizer( $value );
		}

		/**
		 * Raw filtered text result.
		 *
		 * @var string|false
		 */
		$filtered = filter_var( $value, FILTER_UNSAFE_RAW );

		if ( false === $filtered ) {
			return '';
		}

		return trim( $filtered );
	}

	/**
	 * Sanitizes a capability string.
	 *
	 * @param mixed  $value    Raw capability input.
	 * @param string $fallback Fallback capability.
	 */
	private static function sanitize_capability( mixed $value, string $fallback ): string {
		if ( ! is_string( $value ) ) {
			return $fallback;
		}

		if ( function_exists( 'sanitize_key' ) ) {
			$capability = (string) sanitize_key( $value );
		} else {
			$capability = strtolower( trim( $value ) );
			$capability = preg_replace( '/[^a-z0-9_]/', '', $capability );
			if ( ! is_string( $capability ) ) {
				$capability = '';
			}
		}

		return '' !== $capability ? $capability : $fallback;
	}

	/**
	 * Records a validation notice in the Settings API.
	 *
	 * @param string $code    Unique notice code.
	 * @param string $message Human readable message.
	 * @param string $type    Notice severity.
	 */
	private static function add_validation_notice( string $code, string $message, string $type = 'error' ): void {
		if ( function_exists( 'add_settings_error' ) ) {
			add_settings_error( self::OPTION_GROUP, $code, $message, $type );
		}
	}
}
