<?php
/**
 * Check for H1 presence and uniqueness.
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
use DOMElement;

/**
 * Validates existence and uniqueness of the primary heading.
 */
class H1PresenceCheck implements CheckInterface {
	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'h1_presence';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return I18n::translate( 'H1 heading' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return I18n::translate( 'Checks that a single H1 heading exists for the document.' );
	}

	/**
	 * Evaluate the presence of an H1 element.
	 *
	 * @param Context $context Analyzer context payload.
	 *
	 * @return Result
	 */
	public function run( Context $context ): Result {
		$dom = $context->dom();

		if ( null === $dom ) {
			return new Result(
				Result::STATUS_WARN,
				array(
					'count' => 0,
				),
				I18n::translate( 'Provide HTML content with an H1 heading for analysis.' ),
				0.08
			);
		}

		$nodes = $dom->getElementsByTagName( 'h1' );
		$count = 0;

		foreach ( $nodes as $node ) {
						$text = trim( (string) $node->{'textContent'} );

			if ( '' !== $text ) {
						++$count;
			}
		}

		if ( 0 === $count ) {
			return new Result(
				Result::STATUS_FAIL,
				array(
					'count' => 0,
				),
				I18n::translate( 'Add a descriptive H1 heading to introduce the page content.' ),
				0.08
			);
		}

		if ( $count > 1 ) {
			return new Result(
				Result::STATUS_WARN,
				array(
					'count' => $count,
				),
				I18n::translate( 'Limit the page to a single H1 heading to avoid confusing search engines.' ),
				0.08
			);
		}

		return new Result(
			Result::STATUS_PASS,
			array(
				'count' => 1,
			),
			I18n::translate( 'Great! Exactly one H1 heading was detected.' ),
			0.08
		);
	}
}
