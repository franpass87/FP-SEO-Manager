<?php
/**
 * Check for SEO meta description length and presence.
 *
 * @package FP\SEO
 */

declare(strict_types=1);

namespace FP\SEO\Analysis\Checks;

use FP\SEO\Analysis\CheckInterface;
use FP\SEO\Analysis\Context;
use FP\SEO\Analysis\Result;
use FP\SEO\Utils\I18n;
use function mb_strlen;
use function trim;

/**
 * Validates meta description presence and size.
 */
class MetaDescriptionCheck implements CheckInterface {
	/**
	 * Recommended minimum length.
	 */
	private const MIN_LENGTH = 120;

	/**
	 * Recommended maximum length.
	 */
	private const MAX_LENGTH = 160;

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'meta_description';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return I18n::translate( 'Meta description' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return I18n::translate( 'Checks whether a meta description exists and fits within the recommended range.' );
	}

	/**
	 * Execute the meta description evaluation.
	 *
	 * @param Context $context Analyzer context payload.
	 *
	 * @return Result
	 */
	public function run( Context $context ): Result {
		$description = trim( $context->meta_description() );

		if ( '' === $description ) {
			$description = (string) $context->meta_content( 'name', 'description' );
		}

		$length = mb_strlen( $description );

		if ( 0 === $length ) {
			return new Result(
				Result::STATUS_FAIL,
				array(
					'length'          => 0,
					'recommended_min' => self::MIN_LENGTH,
					'recommended_max' => self::MAX_LENGTH,
				),
				I18n::translate( 'Provide a compelling meta description between 120 and 160 characters.' ),
				0.10
			);
		}

		$status = Result::STATUS_PASS;
		$hint   = I18n::translate( 'Meta description length looks good.' );

		if ( $length < self::MIN_LENGTH || $length > self::MAX_LENGTH ) {
			$status = Result::STATUS_WARN;
			$hint   = I18n::translate( 'Tweak the description to keep it between 120 and 160 characters.' );
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
