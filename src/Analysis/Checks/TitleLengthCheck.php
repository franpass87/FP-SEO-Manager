<?php
/**
 * Check for SEO title length.
 *
 * @package FP\SEO
 */

declare(strict_types=1);

namespace FP\SEO\Analysis\Checks;

use FP\SEO\Analysis\CheckInterface;
use FP\SEO\Analysis\Context;
use FP\SEO\Analysis\Result;
use FP\SEO\Utils\I18n;
use function max;
use function mb_strlen;
use function min;

/**
 * Validates document title length.
 */
class TitleLengthCheck implements CheckInterface {
	/**
	 * Recommended minimum length.
	 */
	private const MIN_LENGTH = 50;

	/**
	 * Recommended maximum length.
	 */
	private const MAX_LENGTH = 60;

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'title_length';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return I18n::translate( 'Title length' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return I18n::translate( 'Checks whether the document title length is within the recommended range.' );
	}

	/**
	 * Execute the title length evaluation.
	 *
	 * @param Context $context Analyzer context payload.
	 *
	 * @return Result
	 */
	public function run( Context $context ): Result {
		$title  = trim( $context->title() );
		$length = mb_strlen( $title );

		if ( 0 === $length ) {
			return new Result(
				Result::STATUS_FAIL,
				array(
					'length'          => 0,
					'recommended_min' => self::MIN_LENGTH,
					'recommended_max' => self::MAX_LENGTH,
				),
				I18n::translate( 'Add a descriptive SEO title between 50 and 60 characters.' ),
				0.10
			);
		}

		$status = Result::STATUS_PASS;
		$hint   = I18n::translate( 'Your title length looks good.' );

		if ( $length < self::MIN_LENGTH || $length > self::MAX_LENGTH ) {
			$status = Result::STATUS_WARN;
			$hint   = I18n::translate( 'Adjust the title to land between 50 and 60 characters.' );
		}

		if ( $length < max( 30, (int) ( self::MIN_LENGTH * 0.7 ) ) || $length > min( 80, (int) ( self::MAX_LENGTH * 1.3 ) ) ) {
			$status = Result::STATUS_FAIL;
			$hint   = I18n::translate( 'Significantly adjust the title length to improve search visibility.' );
		}

		return new Result(
			$status,
			array(
				'length'          => $length,
				'recommended_min' => self::MIN_LENGTH,
				'recommended_max' => self::MAX_LENGTH,
			),
			$hint,
			0.10
		);
	}
}
