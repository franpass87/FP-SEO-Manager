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
				'en' => self::translate_label( 'English' ),
				'es' => self::translate_label( 'Spanish' ),
				'fr' => self::translate_label( 'French' ),
				'de' => self::translate_label( 'German' ),
				'it' => self::translate_label( 'Italian' ),
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
				// AI Overview optimization checks
				'faq_schema',
				'howto_schema',
				'ai_optimized_content',
			);
	}

	/**
	 * Provides default scoring weights keyed by analyzer check.
	 *
	 * Weights are normalized multipliers (0.0 to 10.0) where:
	 * - 1.0 = standard weight
	 * - > 1.0 = higher importance
	 * - < 1.0 = lower importance
	 * - 0.0 = disabled (not recommended)
	 *
	 * @return array<string, float>
	 */
	public static function default_scoring_weights(): array {
		// Core SEO elements (high impact, always applicable)
		$core_weights = array(
			'title_length'        => 1.5,  // SEO Title is critical - always applicable
			'meta_description'    => 1.3,  // Meta description is very important - always applicable
			'h1_presence'         => 1.2,  // H1 is important for structure - always applicable
		);

		// Content quality checks (medium-high impact)
		$content_weights = array(
			'headings_structure'  => 1.0,  // Heading structure is important
			'image_alt'           => 0.9,  // Image alt text is important for accessibility
		);

		// Technical SEO (medium impact)
		$technical_weights = array(
			'canonical'           => 0.8,  // Canonical is important but not always needed
			'robots'              => 0.7,  // Robots meta is situational
			'internal_links'      => 0.9,  // Internal links are important
		);

		// Schema markup (optional/enhancement - lower impact, context-dependent)
		$schema_weights = array(
			'schema_presets'      => 0.7,  // Basic schema is good but not critical
			'faq_schema'          => 0.3,  // FAQ is optional and context-dependent - not always applicable
			'howto_schema'        => 0.3,  // HowTo is optional and context-dependent - not always applicable
		);

		// Social media (optional - lower impact)
		$social_weights = array(
			'og_cards'            => 0.5,  // OG tags are nice for social sharing
			'twitter_cards'       => 0.4,  // Twitter cards are optional
		);

		// AI optimization (optional - lower impact, context-dependent)
		$ai_weights = array(
			'ai_optimized_content' => 0.4,  // AI optimization is nice to have but not critical
		);

		// Combine all weights
		$weights = array_merge(
			$core_weights,
			$content_weights,
			$technical_weights,
			$schema_weights,
			$social_weights,
			$ai_weights
		);

		// Ensure all check keys have a weight (fallback to 0.5 for unknown checks)
		foreach ( self::get_check_keys() as $key ) {
			if ( ! isset( $weights[ $key ] ) ) {
				$weights[ $key ] = 0.5; // Default moderate weight for unknown checks
			}
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
			'enable_psi'    => false,
			'psi_api_key'   => '',
			'psi_cache_ttl' => 86400, // 1 day in seconds.
			'heuristics'    => array(
				'image_alt_coverage' => true,
				'inline_css'         => true,
				'image_count'        => true,
				'heading_depth'      => true,
			),
		),
			'geo'         => array(
				'enabled'            => true,
				'publisher_name'     => '',
				'publisher_url'      => '',
				'publisher_logo'     => '',
				'license_url'        => '',
				'ai_usage'           => 'allow-with-attribution',
				'default_confidence' => 0.7,
				'pretty_print'       => false,
				'post_types'         => array(
					'post' => array(
						'expose'     => true,
						'in_sitemap' => true,
					),
				),
			),
			'ai'          => array(
				'openai_api_key'        => '',
				'openai_model'          => 'gpt-5-nano',
				'enable_auto_generation' => true,
				'focus_on_keywords'     => true,
				'optimize_for_ctr'      => true,
			),
			'ai_first'    => array(
				'enable_qa'              => true,
				'enable_entities'        => true,
				'enable_embeddings'      => false, // Requires API calls
				'auto_generate_on_publish' => false, // Optional
				'batch_size'             => 10,
				'cache_ttl'              => 86400, // 1 day
				'content_license'        => 'All Rights Reserved',
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
		return Cache::remember(
			'options_data',
			static function (): array {
				// Clear WordPress option cache before retrieving
				wp_cache_delete( self::OPTION_KEY, 'options' );
				wp_cache_delete( 'alloptions', 'options' );
				
				$stored = get_option( self::OPTION_KEY, array() );
				
				// Fallback: query diretta al database se get_option restituisce vuoto o non array
				if ( empty( $stored ) || ! is_array( $stored ) ) {
					global $wpdb;
					$db_value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", self::OPTION_KEY ) );
					if ( $db_value !== null ) {
						$unserialized = maybe_unserialize( $db_value );
						$stored = is_array( $unserialized ) ? $unserialized : array();
					}
				}

				if ( ! is_array( $stored ) ) {
					$stored = array();
				}

				$sanitized = self::sanitize( $stored );

				return self::merge_defaults( $sanitized );
			},
			HOUR_IN_SECONDS
		);
	}

	/**
	 * Sanitizes an options payload and records validation notices.
	 *
	 * @param array<string, mixed> $input Raw option values.
	 *
	 * @return array<string, mixed> Sanitized options.
	 */
	public static function sanitize( ?array $input ): array {
		if ( ! is_array( $input ) ) {
			$input = array();
		}

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

		$performance                               = is_array( $input['performance'] ?? null ) ? $input['performance'] : array();
		$sanitized['performance']['enable_psi']    = self::to_bool( $performance['enable_psi'] ?? $defaults['performance']['enable_psi'] );
		$sanitized['performance']['psi_api_key']   = self::sanitize_text( $performance['psi_api_key'] ?? $defaults['performance']['psi_api_key'] );
		$sanitized['performance']['psi_cache_ttl'] = self::bounded_int(
			$performance['psi_cache_ttl'] ?? $defaults['performance']['psi_cache_ttl'],
			3600,    // Minimum 1 hour.
			2592000, // Maximum 30 days.
			$defaults['performance']['psi_cache_ttl']
		);

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

		// AI settings sanitization.
		$ai                                          = is_array( $input['ai'] ?? null ) ? $input['ai'] : array();
		// IMPORTANTE: Per le API key, preserviamo il valore originale senza sanitizzazione eccessiva
		// sanitize_text_field può rimuovere caratteri validi nelle API key
		$raw_api_key = $ai['openai_api_key'] ?? $defaults['ai']['openai_api_key'];
		if ( is_string( $raw_api_key ) && ! empty( trim( $raw_api_key ) ) ) {
			// Per API key, usiamo solo trim e strip_tags per sicurezza, preservando tutti i caratteri
			$sanitized['ai']['openai_api_key'] = trim( strip_tags( $raw_api_key ) );
		} else {
			$sanitized['ai']['openai_api_key'] = '';
		}
		$sanitized['ai']['openai_model']             = self::sanitize_choice(
			$ai['openai_model'] ?? $defaults['ai']['openai_model'],
			array( 'gpt-5-nano', 'gpt-5-mini', 'gpt-5', 'gpt-5-pro', 'gpt-4o-mini', 'gpt-4o', 'gpt-4-turbo', 'gpt-3.5-turbo' ),
			$defaults['ai']['openai_model']
		);
		$sanitized['ai']['enable_auto_generation']   = self::to_bool( $ai['enable_auto_generation'] ?? $defaults['ai']['enable_auto_generation'] );
		$sanitized['ai']['focus_on_keywords']        = self::to_bool( $ai['focus_on_keywords'] ?? $defaults['ai']['focus_on_keywords'] );
		$sanitized['ai']['optimize_for_ctr']         = self::to_bool( $ai['optimize_for_ctr'] ?? $defaults['ai']['optimize_for_ctr'] );

		// GEO settings sanitization.
		$geo                                          = is_array( $input['geo'] ?? null ) ? $input['geo'] : array();
		$sanitized['geo']['enabled']                  = self::to_bool( $geo['enabled'] ?? $defaults['geo']['enabled'] );
		$sanitized['geo']['publisher_name']           = self::sanitize_text( $geo['publisher_name'] ?? $defaults['geo']['publisher_name'] );
		$sanitized['geo']['publisher_url']            = self::sanitize_url( $geo['publisher_url'] ?? $defaults['geo']['publisher_url'] );
		$sanitized['geo']['publisher_logo']           = self::sanitize_url( $geo['publisher_logo'] ?? $defaults['geo']['publisher_logo'] );
		$sanitized['geo']['license_url']              = self::sanitize_url( $geo['license_url'] ?? $defaults['geo']['license_url'] );
		$sanitized['geo']['ai_usage']                 = self::sanitize_choice(
			$geo['ai_usage'] ?? $defaults['geo']['ai_usage'],
			array( 'allow', 'allow-with-attribution', 'deny' ),
			$defaults['geo']['ai_usage']
		);
		$sanitized['geo']['default_confidence']       = self::bounded_float(
			$geo['default_confidence'] ?? $defaults['geo']['default_confidence'],
			0.0,
			1.0,
			$defaults['geo']['default_confidence']
		);
		$sanitized['geo']['pretty_print']             = self::to_bool( $geo['pretty_print'] ?? $defaults['geo']['pretty_print'] );

		$post_types_input = is_array( $geo['post_types'] ?? null ) ? $geo['post_types'] : array();
		$post_types       = array();

		if ( ! empty( $post_types_input ) ) {
			foreach ( $post_types_input as $type => $settings ) {
				$type_key = is_string( $type ) ? sanitize_key( $type ) : '';

				if ( '' === $type_key ) {
					continue;
				}

				$settings = is_array( $settings ) ? $settings : array();

				$post_types[ $type_key ] = array(
					'expose'     => self::to_bool( $settings['expose'] ?? false ),
					'in_sitemap' => self::to_bool( $settings['in_sitemap'] ?? false ),
				);
			}
		}

		if ( empty( $post_types ) ) {
			$post_types = $defaults['geo']['post_types'];
		}

		$sanitized['geo']['post_types'] = $post_types;

		// AI-First settings sanitization
		$ai_first                                          = is_array( $input['ai_first'] ?? null ) ? $input['ai_first'] : array();
		$sanitized['ai_first']['enable_qa']                = self::to_bool( $ai_first['enable_qa'] ?? $defaults['ai_first']['enable_qa'] );
		$sanitized['ai_first']['enable_entities']          = self::to_bool( $ai_first['enable_entities'] ?? $defaults['ai_first']['enable_entities'] );
		$sanitized['ai_first']['enable_embeddings']        = self::to_bool( $ai_first['enable_embeddings'] ?? $defaults['ai_first']['enable_embeddings'] );
		$sanitized['ai_first']['auto_generate_on_publish'] = self::to_bool( $ai_first['auto_generate_on_publish'] ?? $defaults['ai_first']['auto_generate_on_publish'] );
		$sanitized['ai_first']['batch_size']               = self::bounded_int(
			$ai_first['batch_size'] ?? $defaults['ai_first']['batch_size'],
			1,
			100,
			$defaults['ai_first']['batch_size']
		);
		$sanitized['ai_first']['cache_ttl']                = self::bounded_int(
			$ai_first['cache_ttl'] ?? $defaults['ai_first']['cache_ttl'],
			3600,
			2592000,
			$defaults['ai_first']['cache_ttl']
		);
		$sanitized['ai_first']['content_license']          = self::sanitize_text( $ai_first['content_license'] ?? $defaults['ai_first']['content_license'] );

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
	 * Merges new values with existing options to preserve unmodified settings.
	 * 
	 * IMPORTANTE: Questo metodo preserva SEMPRE le opzioni esistenti.
	 * Usa array_replace_recursive() per garantire che i valori esistenti non vengano persi.
	 * Questo è fondamentale per preservare le configurazioni durante aggiornamenti/disattivazioni.
	 *
	 * @param array<string, mixed> $value Option payload (can be partial).
	 */
	public static function update( array $value ): void {
		// Clear cache before retrieving existing options to ensure fresh data
		Cache::delete( 'options_data' );
		
		// Get existing options first
		// IMPORTANTE: get_option() con default array() restituisce array() solo se l'opzione non esiste
		// Se l'opzione esiste, restituisce il valore salvato
		$existing = get_option( self::OPTION_KEY, array() );
		
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}
		
		// Merge new values with existing options recursively
		// array_replace_recursive() preserva i valori esistenti e aggiunge/sovrascrive solo quelli nuovi
		// Questo garantisce che le opzioni personalizzate non vengano perse
		$merged = array_replace_recursive( $existing, $value );
		
		// Sanitize the merged options
		$sanitized = self::sanitize( $merged );
		
		// Update the option
		$result = update_option( self::OPTION_KEY, $sanitized );
		
		// Clear cache when options are updated (anche se update_option fallisce, puliamo comunque)
		Cache::delete( 'options_data' );
		
		// Log per debug
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'Options::update', array(
				'result' => $result ? 'success' : 'failed',
				'keys' => array_keys( $sanitized ),
			) );
		}
	}

	/**
	 * Get a specific option value by key path.
	 *
	 * @param string $key     Option key (can use dot notation like 'ai.openai_api_key').
	 * @param mixed  $default Default value if not found.
	 * @return mixed
	 */
	public static function get_option( string $key, mixed $default = null ): mixed {
		$options = self::get();
		$keys    = explode( '.', $key );
		$value   = $options;

		foreach ( $keys as $k ) {
			if ( ! is_array( $value ) || ! isset( $value[ $k ] ) ) {
				return $default;
			}
			$value = $value[ $k ];
		}

		return $value;
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
		return Cache::remember(
			'scoring_weights',
			static function (): array {
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
			},
			HOUR_IN_SECONDS
		);
	}

	/**
	 * Merges defaults with user supplied values.
	 * 
	 * IMPORTANTE: Usa array_replace_recursive per preservare i valori esistenti.
	 * I defaults vengono applicati SOLO per chiavi mancanti, non sovrascrivono valori esistenti.
	 *
	 * @param array<string, mixed> $value Partial options.
	 *
	 * @return array<string, mixed> Options with defaults applied.
	 */
	public static function merge_defaults( array $value ): array {
		$defaults = self::get_defaults();

		// array_replace_recursive preserva i valori esistenti in $value
		// e aggiunge solo i defaults per chiavi mancanti
		// Questo garantisce che le opzioni personalizzate non vengano sovrascritte
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
		 * @var string
		 */
		$filtered = filter_var( $value, FILTER_DEFAULT );

		if ( false === $filtered || ! is_string( $filtered ) ) {
			return '';
		}

		return trim( $filtered );
	}

	/**
	 * Sanitizes a URL value.
	 *
	 * @param mixed $value Raw input.
	 */
	private static function sanitize_url( mixed $value ): string {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return '';
		}

		if ( function_exists( 'esc_url_raw' ) ) {
			return esc_url_raw( $value );
		}

		$filtered = filter_var( $value, FILTER_SANITIZE_URL );

		return is_string( $filtered ) ? trim( $filtered ) : '';
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

	/**
	 * Ritorna una label tradotta solo quando il dominio testuale è pronto.
	 */
	private static function translate_label( string $label ): string {
		if ( function_exists( 'did_action' ) && did_action( 'init' ) ) {
			return __( $label, 'fp-seo-performance' );
		}

		return $label;
	}
}
