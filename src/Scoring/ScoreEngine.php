<?php
/**
 * Scoring aggregation engine.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Scoring;

use FP\SEO\Analysis\Result;
use FP\SEO\Utils\I18n;
use FP\SEO\Utils\Options;
use function array_key_exists;
use function array_values;
use function call_user_func;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function max;
use function min;
use function round;
use function sprintf;
use function trim;

/**
 * Aggregates analyzer signals into a normalized score.
 */
class ScoreEngine {
	private const STATUS_GREEN  = 'green';
	private const STATUS_YELLOW = 'yellow';
	private const STATUS_RED    = 'red';

		/**
		 * Resolver callback for retrieving weighting configuration.
		 *
		 * @var callable
		 */
		private $weights_resolver;

		/**
		 * Constructor.
		 *
		 * @param callable|null $weights_resolver Optional resolver returning weights indexed by check id.
		 */
	public function __construct( ?callable $weights_resolver = null ) {
		if ( null === $weights_resolver ) {
				$weights_resolver = static function (): array {
						return Options::get_scoring_weights();
				};
		}

			$this->weights_resolver = $weights_resolver;
	}

		/**
		 * Calculates a composite score from the provided checks.
		 *
		 * @param array<int|string, array<string, mixed>> $checks Individual check results keyed by check id.
		 *
		 * @return array<string, mixed> Score payload.
		 */
	public function calculate( array $checks ): array {
			$weights   = $this->resolve_weights();
			$max_total = 0.0;
			$score_sum = 0.0;
			$breakdown = array();
			$notes     = array();

		foreach ( $checks as $id => $check ) {
				$check_id = is_string( $id ) ? $id : (string) $id;

			if ( '' === $check_id ) {
				continue;
			}

			// Validate check is an array
			if ( ! is_array( $check ) ) {
				continue;
			}

			// Check if this check is optional/not applicable
			$details = is_array( $check['details'] ?? null ) ? $check['details'] : array();
			$is_optional = $this->is_optional_check( $check_id, $details, $check );
			$is_not_applicable = $this->is_not_applicable( $check_id, $details, $check );

			$base_weight    = $this->extract_weight( $check );
			$config_weight  = $this->extract_config_weight( $weights, $check_id );
			$applied_weight = $base_weight * $config_weight;
			
			// If check is not applicable, exclude it completely from scoring
			if ( $is_not_applicable ) {
				// Don't add to max_total or score_sum - effectively exclude from calculation
				$breakdown[ $check_id ] = array(
					'id'           => $check_id,
					'status'       => is_string( $check['status'] ?? null ) ? $check['status'] : Result::STATUS_WARN,
					'weight'       => 0.0,
					'multiplier'   => 0.0,
					'contribution' => 0.0,
					'optional'     => true,
					'not_applicable' => true,
				);
				continue; // Skip this check entirely
			}
			
			// If check is optional (but still applicable), reduce its weight
			if ( $is_optional ) {
				// Reduce weight to 30% for optional checks
				$applied_weight = $applied_weight * 0.3;
			}
			
			$max_total     += $applied_weight;

			$status       = is_string( $check['status'] ?? null ) ? $check['status'] : Result::STATUS_WARN;
			$multiplier   = $this->status_multiplier( $status );
			$contribution = $applied_weight * $multiplier;
			$score_sum   += $contribution;

				$breakdown[ $check_id ] = array(
					'id'           => $check_id,
					'status'       => $status,
					'weight'       => $applied_weight,
					'multiplier'   => $multiplier,
					'contribution' => $contribution,
					'optional'     => $is_optional,
				);

			// Only add recommendations for non-optional checks or optional checks that are applicable
			if ( in_array( $status, array( Result::STATUS_WARN, Result::STATUS_FAIL ), true ) && ! $is_optional ) {
				$notes[] = $this->build_recommendation( $check );
			}
		}

		$score = 0;

		if ( $max_total > 0 ) {
			$score = (int) round( max( 0, min( 1, $score_sum / $max_total ) ) * 100 );
		}

		$status = $this->color_from_score( $score );

		return array(
				'score'             => $score,
				'status'            => $status,
				'recommendations'   => $notes,
				'breakdown'         => $breakdown,
				'weight_total'      => $max_total,
				'weighted_achieved' => $score_sum,
			);
	}

		/**
		 * Resolve configured weights from the resolver callback.
		 *
		 * @return array<string, float>
		 */
	private function resolve_weights(): array {
			$resolved = call_user_func( $this->weights_resolver );

		if ( ! is_array( $resolved ) ) {
				return array();
		}

			$weights = array();

		foreach ( $resolved as $key => $value ) {
			if ( ! is_string( $key ) ) {
					continue;
			}

				$weights[ $key ] = $this->normalize_float( $value, 0.0, 10.0, 1.0 );
		}

			return $weights;
	}

		/**
		 * Extracts the configured weight multiplier for a check id.
		 *
		 * @param array<string, float> $weights Configured weights.
		 * @param string               $id      Check identifier.
		 */
	private function extract_config_weight( array $weights, string $id ): float {
		if ( array_key_exists( $id, $weights ) ) {
				return $weights[ $id ];
		}

			return 1.0;
	}

		/**
		 * Extracts the intrinsic weight from the check payload.
		 *
		 * @param array<string, mixed> $check Check payload.
		 */
	private function extract_weight( array $check ): float {
		// Validate input
		if ( ! is_array( $check ) ) {
			return 0.0;
		}
		
		$weight = $check['weight'] ?? 0.0;

		return $this->normalize_float( $weight, 0.0, 1.0, 0.0 );
	}

		/**
		 * Maps a status string to its multiplier.
		 *
		 * @param string $status Analyzer status code.
		 */
	private function status_multiplier( string $status ): float {
		switch ( $status ) {
			case Result::STATUS_PASS:
				return 1.0;
			case Result::STATUS_WARN:
				return 0.5;
			case Result::STATUS_FAIL:
			default:
				return 0.0;
		}
	}

		/**
		 * Builds a recommendation bullet for a check.
		 *
		 * @param array<string, mixed> $check Check payload.
		 */
	private function build_recommendation( array $check ): string {
			$label = is_string( $check['label'] ?? null ) ? trim( $check['label'] ) : '';
			$hint  = is_string( $check['fix_hint'] ?? null ) ? trim( $check['fix_hint'] ) : '';

		if ( '' === $label ) {
				$label = is_string( $check['id'] ?? null ) ? trim( (string) $check['id'] ) : I18n::translate( 'SEO check' );
		}

		if ( '' === $hint ) {
				$hint = I18n::translate( 'Review this area to resolve outstanding warnings.' );
		}

			return trim( sprintf( I18n::translate( '%1$s: %2$s' ), $label, $hint ) );
	}

		/**
		 * Converts a floating value into an allowed range with fallback.
		 *
		 * @param mixed $value    Raw value.
		 * @param float $min      Minimum inclusive value.
		 * @param float $max      Maximum inclusive value.
		 * @param float $fallback Fallback when validation fails.
		 */
	private function normalize_float( mixed $value, float $min, float $max, float $fallback ): float {
		// Handle null or invalid values
		if ( null === $value || ( ! is_numeric( $value ) && ! is_string( $value ) ) ) {
			return $fallback;
		}
		
		if ( is_numeric( $value ) ) {
			$numeric = (float) $value;

			if ( $numeric < $min ) {
				return $min;
			}

			if ( $numeric > $max ) {
				return $max;
			}

			return $numeric;
		}

		return $fallback;
	}

		/**
		 * Determine the traffic light color from a score.
		 *
		 * @param int $score Normalized score value.
		 */
	private function color_from_score( int $score ): string {
		if ( $score >= 80 ) {
				return self::STATUS_GREEN;
		}

		if ( $score >= 60 ) {
				return self::STATUS_YELLOW;
		}

			return self::STATUS_RED;
	}

	/**
	 * Determine if a check is optional (but still applicable) based on context.
	 *
	 * @param string               $check_id Check identifier.
	 * @param array<string, mixed> $details  Check details.
	 * @param array<string, mixed> $check   Full check payload.
	 * @return bool True if check is optional (but still applicable).
	 */
	private function is_optional_check( string $check_id, array $details, array $check ): bool {
		// FAQ Schema: optional by nature but still applicable
		if ( $check_id === 'faq_schema' ) {
			return true; // FAQ is optional enhancement
		}

		// HowTo Schema: optional by nature but still applicable
		if ( $check_id === 'howto_schema' ) {
			return true; // HowTo is optional enhancement
		}

		// Social media checks are optional
		if ( in_array( $check_id, array( 'og_cards', 'twitter_cards' ), true ) ) {
			return true;
		}

		// AI optimization is optional
		if ( $check_id === 'ai_optimized_content' ) {
			return true;
		}

		return false;
	}

	/**
	 * Determine if a check is not applicable (should be excluded from scoring).
	 *
	 * @param string               $check_id Check identifier.
	 * @param array<string, mixed> $details  Check details.
	 * @param array<string, mixed> $check   Full check payload.
	 * @return bool True if check is not applicable and should be excluded.
	 */
	private function is_not_applicable( string $check_id, array $details, array $check ): bool {
		// FAQ Schema: not applicable if explicitly marked
		if ( $check_id === 'faq_schema' ) {
			// If status is PASS and note says not_applicable, exclude it
			if ( ( $check['status'] ?? '' ) === Result::STATUS_PASS && 
				 isset( $details['note'] ) && $details['note'] === 'not_applicable' ) {
				return true;
			}
		}

		// HowTo Schema: not applicable if content is not a guide
		if ( $check_id === 'howto_schema' ) {
			// If status is PASS and note says not_applicable, exclude it
			if ( ( $check['status'] ?? '' ) === Result::STATUS_PASS && 
				 isset( $details['is_guide'] ) && $details['is_guide'] === false &&
				 isset( $details['note'] ) && $details['note'] === 'not_applicable' ) {
				return true;
			}
		}

		return false;
	}
}
