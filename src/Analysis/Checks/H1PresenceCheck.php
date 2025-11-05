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
use function mb_stripos;
use function sprintf;

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
		$dom           = $context->dom();
		$focus_keyword = trim( $context->focus_keyword() );

		if ( null === $dom ) {
			return new Result(
				Result::STATUS_WARN,
				array(
					'count'       => 0,
					'has_keyword' => false,
				),
				I18n::translate( 'Provide HTML content with an H1 heading for analysis.' ),
				0.08
			);
		}

		$nodes       = $dom->getElementsByTagName( 'h1' );
		$count       = 0;
		$h1_text     = '';
		$has_keyword = false;

		foreach ( $nodes as $node ) {
			$text = trim( (string) $node->{'textContent'} );

			if ( '' !== $text ) {
				++$count;
				if ( 1 === $count ) {
					$h1_text = $text;
				}
			}
		}

		// Check keyword in H1
		if ( $context->has_focus_keyword() && '' !== $h1_text ) {
			$has_keyword = false !== mb_stripos( $h1_text, $focus_keyword );
		}

		if ( 0 === $count ) {
			$hint = I18n::translate( '❌ Nessun H1 trovato. Aggiungi 1 heading H1 principale.' );
			
			if ( $context->has_focus_keyword() ) {
				$hint = sprintf(
					/* translators: %s: focus keyword */
					I18n::translate( '❌ Manca H1. Aggiungi un H1 con la keyword "%s".' ),
					$focus_keyword
				);
			}
			
			return new Result(
				Result::STATUS_FAIL,
				array(
					'count'       => 0,
					'has_keyword' => false,
				),
				$hint,
				0.08
			);
		}

		if ( $count > 1 ) {
			$to_remove = $count - 1;
			$hint      = sprintf(
				/* translators: 1: current count, 2: count to remove */
				I18n::translate( '⚠️ Trovati %1$d H1. Rimuovi %2$d H1 (deve essercene solo 1).' ),
				$count,
				$to_remove
			);
			
			return new Result(
				Result::STATUS_WARN,
				array(
					'count'       => $count,
					'has_keyword' => $has_keyword,
				),
				$hint,
				0.08
			);
		}

		$status = Result::STATUS_PASS;
		$hint   = I18n::translate( '✅ Perfetto! 1 H1 trovato (quantità ideale).' );

		// Check keyword presence
		if ( $context->has_focus_keyword() ) {
			if ( ! $has_keyword ) {
				$status = Result::STATUS_WARN;
				$hint   = sprintf(
					/* translators: %s: focus keyword */
					I18n::translate( '⚠️ H1 presente, ma manca la keyword "%s". Aggiungila!' ),
					$focus_keyword
				);
			} else {
				$hint = sprintf(
					/* translators: %s: focus keyword */
					I18n::translate( '✅ Perfetto! H1 con keyword "%s" presente.' ),
					$focus_keyword
				);
			}
		}

		return new Result(
			$status,
			array(
				'count'        => 1,
				'has_keyword'  => $has_keyword,
				'focus_keyword' => $focus_keyword,
			),
			$hint,
			0.08
		);
	}
}
