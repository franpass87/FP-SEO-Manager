<?php
/**
 * Analyzer service entry point.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Analysis;

use FP\SEO\Analysis\Checks\AiOptimizedContentCheck;
use FP\SEO\Analysis\Checks\CanonicalCheck;
use FP\SEO\Analysis\Checks\FaqSchemaCheck;
use FP\SEO\Analysis\Checks\H1PresenceCheck;
use FP\SEO\Analysis\Checks\HeadingsStructureCheck;
use FP\SEO\Analysis\Checks\HowToSchemaCheck;
// ImageAltCheck removed - image optimization features disabled
use FP\SEO\Analysis\Checks\InternalLinksCheck;
use FP\SEO\Analysis\Checks\MetaDescriptionCheck;
use FP\SEO\Analysis\Checks\OgCardsCheck;
use FP\SEO\Analysis\Checks\RobotsIndexabilityCheck;
use FP\SEO\Analysis\Checks\SchemaPresetsCheck;
use FP\SEO\Analysis\Checks\SearchIntentCheck;
use FP\SEO\Analysis\Checks\TitleLengthCheck;
use FP\SEO\Analysis\Checks\TwitterCardsCheck;

/**
 * Coordinates execution of individual SEO checks.
 */
class Analyzer {
	/**
	 * Registered analyzer checks.
	 *
	 * @var array<int, CheckInterface>
	 */
	private array $checks;

	/**
	 * Constructor.
	 *
	 * @param array<int, CheckInterface> $checks Optional custom checks.
	 */
	public function __construct( array $checks = array() ) {
		$this->checks = $checks;
	}

	/**
	 * Runs the analyzer for a given context.
	 *
	 * @param Context $context Analyzer context.
	 *
	 * @return array<string, mixed> Aggregated analyzer results.
	 */
	public function analyze( Context $context ): array {
		/**
		 * Fires before analysis begins.
		 *
		 * @param Context $context Analysis context.
		 */
		do_action( 'fp_seo_before_analysis', $context );

		$checks = $this->checks;

		if ( empty( $checks ) ) {
			$checks = $this->default_checks();
		}

		/**
		 * Filters the complete list of analyzer checks before filtering.
		 *
		 * @param array<int, CheckInterface> $checks All registered checks.
		 * @param Context                    $context Analysis context.
		 */
		$checks = apply_filters( 'fp_seo_analyzer_checks', $checks, $context );

		// Use CheckRegistry to filter enabled checks.
		$enabled_checks = CheckRegistry::filter_enabled_checks( $checks, $context );

		// Handle empty result case.
		if ( empty( $enabled_checks ) ) {
			$empty_result = array(
				'status'  => Result::STATUS_PASS,
				'summary' => array(
					Result::STATUS_PASS => 0,
					Result::STATUS_WARN => 0,
					Result::STATUS_FAIL => 0,
					'total'             => 0,
				),
				'checks'  => array(),
			);

			/**
			 * Fires after analysis completes with no checks.
			 *
			 * @param array<string, mixed> $empty_result Empty analysis result.
			 * @param Context              $context      Analysis context.
			 */
			do_action( 'fp_seo_after_analysis_empty', $empty_result, $context );

			return $empty_result;
		}

		$results = array();
		$summary = array(
			Result::STATUS_PASS => 0,
			Result::STATUS_WARN => 0,
			Result::STATUS_FAIL => 0,
		);

		foreach ( $enabled_checks as $check ) {
			/**
			 * Fires before an individual check runs.
			 *
			 * @param CheckInterface $check   The check being run.
			 * @param Context        $context Analysis context.
			 */
			do_action( 'fp_seo_before_check', $check, $context );

			$result = $check->run( $context );

			$check_result = array_merge(
				array(
					'id'          => $check->id(),
					'label'       => $check->label(),
					'description' => $check->description(),
				),
				$result->to_array()
			);

			/**
			 * Filters an individual check result.
			 *
			 * @param array<string, mixed> $check_result Result data.
			 * @param CheckInterface       $check        The check instance.
			 * @param Context              $context      Analysis context.
			 */
			$check_result = apply_filters( 'fp_seo_check_result', $check_result, $check, $context );

			$results[ $check->id() ] = $check_result;

			if ( isset( $summary[ $result->status() ] ) ) {
				++$summary[ $result->status() ];
			}

			/**
			 * Fires after an individual check completes.
			 *
			 * @param array<string, mixed> $check_result Result data.
			 * @param CheckInterface       $check        The check instance.
			 * @param Context              $context      Analysis context.
			 */
			do_action( 'fp_seo_after_check', $check_result, $check, $context );
		}

		$total  = $summary[ Result::STATUS_PASS ] + $summary[ Result::STATUS_WARN ] + $summary[ Result::STATUS_FAIL ];
		$status = Result::STATUS_PASS;

		if ( $summary[ Result::STATUS_FAIL ] > 0 ) {
			$status = Result::STATUS_FAIL;
		} elseif ( $summary[ Result::STATUS_WARN ] > 0 ) {
			$status = Result::STATUS_WARN;
		}

		/**
		 * Filters the overall analysis status.
		 *
		 * @param string               $status  Overall status.
		 * @param array<string, int>   $summary Status summary counts.
		 * @param Context              $context Analysis context.
		 */
		$status = apply_filters( 'fp_seo_analysis_status', $status, $summary, $context );

		$analysis_result = array(
			'status'  => $status,
			'summary' => array_merge(
				$summary,
				array(
					'total' => $total,
				)
			),
			'checks'  => $results,
		);

		/**
		 * Filters the complete analysis result.
		 *
		 * @param array<string, mixed> $analysis_result Complete analysis data.
		 * @param Context              $context         Analysis context.
		 */
		$analysis_result = apply_filters( 'fp_seo_analysis_result', $analysis_result, $context );

		/**
		 * Fires after analysis completes successfully.
		 *
		 * @param array<string, mixed> $analysis_result Complete analysis data.
		 * @param Context              $context         Analysis context.
		 */
		do_action( 'fp_seo_after_analysis', $analysis_result, $context );

		return $analysis_result;
	}

	/**
	 * Instantiate default analyzer checks.
	 *
	 * @return array<int, CheckInterface>
	 */
	private function default_checks(): array {
		// Use lazy loading for expensive checks
		$checks = array(
			new TitleLengthCheck(),
			new MetaDescriptionCheck(),
			new H1PresenceCheck(),
			new HeadingsStructureCheck(),
			new ImageAltCheck(),
			new CanonicalCheck(),
			new RobotsIndexabilityCheck(),
			new OgCardsCheck(),
			new TwitterCardsCheck(),
		);

		// Only add expensive checks if needed
		$options = get_option( 'fp_seo_performance', array() );
		$enable_advanced_checks = $options['analysis']['enable_advanced_checks'] ?? true;

		if ( $enable_advanced_checks ) {
			$checks = array_merge( $checks, array(
				new SchemaPresetsCheck(),
				new InternalLinksCheck(),
				// AI Overview optimization checks
				new FaqSchemaCheck(),
				new HowToSchemaCheck(),
				new AiOptimizedContentCheck(),
				// Search Intent optimization
				new SearchIntentCheck(),
			) );
		}

		return $checks;
	}
}
