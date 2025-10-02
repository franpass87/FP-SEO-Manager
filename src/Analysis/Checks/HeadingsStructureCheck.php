<?php
/**
 * Check for heading hierarchy issues.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Analysis\Checks;

use FP\SEO\Analysis\CheckInterface;
use FP\SEO\Analysis\Context;
use FP\SEO\Analysis\Result;
use FP\SEO\Utils\I18n;
use function max;
use function sprintf;

/**
 * Evaluates heading hierarchy consistency.
 */
class HeadingsStructureCheck implements CheckInterface {
	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'headings_structure';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return I18n::translate( 'Heading structure' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return I18n::translate( 'Ensures headings progress logically without skipping levels.' );
	}

	/**
	 * Evaluate heading structure consistency.
	 *
	 * @param Context $context Analyzer context payload.
	 *
	 * @return Result
	 */
	public function run( Context $context ): Result {
		$headings = $context->ordered_headings();

		if ( empty( $headings ) ) {
			return new Result(
				Result::STATUS_WARN,
				array(
					'issues' => array(),
				),
				I18n::translate( 'Add structured headings (H2-H6) to organize your content.' ),
				0.08
			);
		}

		$issues     = array();
		$previous   = $headings[0]['level'] ?? 2;
		$previous   = max( 1, (int) $previous );
		$violations = 0;

		foreach ( $headings as $heading ) {
			$level = (int) $heading['level'];

			if ( $level > $previous + 1 ) {
				$issues[] = sprintf( I18n::translate( 'Heading "%s" jumps from H%d to H%d.' ), $heading['text'], $previous, $level );
				++$violations;
			}

			$previous = $level;
		}

		if ( empty( $issues ) ) {
			return new Result(
				Result::STATUS_PASS,
				array(
					'issues' => array(),
				),
				I18n::translate( 'Heading levels flow correctly.' ),
				0.08
			);
		}

		$status = $violations > 1 ? Result::STATUS_FAIL : Result::STATUS_WARN;
		$hint   = $violations > 1
		? I18n::translate( 'Reorder headings so they increase one level at a time.' )
		: I18n::translate( 'Minor heading hierarchy adjustments recommended.' );

		return new Result(
			$status,
			array(
				'issues' => $issues,
			),
			$hint,
			0.08
		);
	}
}
