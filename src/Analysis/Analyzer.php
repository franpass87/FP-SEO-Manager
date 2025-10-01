<?php
/**
 * Analyzer service entry point.
 *
 * @package FP\SEO
 */

declare(strict_types=1);

namespace FP\SEO\Analysis;

use FP\SEO\Analysis\Checks\CanonicalCheck;
use FP\SEO\Analysis\Checks\H1PresenceCheck;
use FP\SEO\Analysis\Checks\HeadingsStructureCheck;
use FP\SEO\Analysis\Checks\ImageAltCheck;
use FP\SEO\Analysis\Checks\InternalLinksCheck;
use FP\SEO\Analysis\Checks\MetaDescriptionCheck;
use FP\SEO\Analysis\Checks\OgCardsCheck;
use FP\SEO\Analysis\Checks\RobotsIndexabilityCheck;
use FP\SEO\Analysis\Checks\SchemaPresetsCheck;
use FP\SEO\Analysis\Checks\TitleLengthCheck;
use FP\SEO\Analysis\Checks\TwitterCardsCheck;
use function apply_filters;
use function function_exists;
use function is_array;

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
		$checks = $this->checks;

		if ( empty( $checks ) ) {
			$checks = $this->default_checks();
		}

		$enabled_ids = array();

		foreach ( $checks as $check ) {
			$enabled_ids[ $check->id() ] = true;
		}

		$filtered = array_keys( $enabled_ids );

		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'fp_seo_perf_checks_enabled', $filtered, $context );
		}

		if ( is_array( $filtered ) ) {
			$enabled_ids = array();

			foreach ( $filtered as $id ) {
				$enabled_ids[ (string) $id ] = true;
			}
		}

		$results = array();
		$summary = array(
			Result::STATUS_PASS => 0,
			Result::STATUS_WARN => 0,
			Result::STATUS_FAIL => 0,
		);

		foreach ( $checks as $check ) {
			if ( ! isset( $enabled_ids[ $check->id() ] ) ) {
				continue;
			}

			$result = $check->run( $context );

			$results[ $check->id() ] = array_merge(
				array(
					'id'          => $check->id(),
					'label'       => $check->label(),
					'description' => $check->description(),
				),
				$result->to_array()
			);

			if ( isset( $summary[ $result->status() ] ) ) {
				++$summary[ $result->status() ];
			}
		}

		$total  = $summary[ Result::STATUS_PASS ] + $summary[ Result::STATUS_WARN ] + $summary[ Result::STATUS_FAIL ];
		$status = Result::STATUS_PASS;

		if ( $summary[ Result::STATUS_FAIL ] > 0 ) {
			$status = Result::STATUS_FAIL;
		} elseif ( $summary[ Result::STATUS_WARN ] > 0 ) {
			$status = Result::STATUS_WARN;
		}

		return array(
			'status'  => $status,
			'summary' => array_merge(
				$summary,
				array(
					'total' => $total,
				)
			),
			'checks'  => $results,
		);
	}

	/**
	 * Instantiate default analyzer checks.
	 *
	 * @return array<int, CheckInterface>
	 */
	private function default_checks(): array {
		return array(
			new TitleLengthCheck(),
			new MetaDescriptionCheck(),
			new H1PresenceCheck(),
			new HeadingsStructureCheck(),
			new ImageAltCheck(),
			new CanonicalCheck(),
			new RobotsIndexabilityCheck(),
			new OgCardsCheck(),
			new TwitterCardsCheck(),
			new SchemaPresetsCheck(),
			new InternalLinksCheck(),
		);
	}
}
