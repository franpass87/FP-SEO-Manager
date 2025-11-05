<?php
/**
 * Check for SEO title length.
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
use function mb_strlen;
use function mb_stripos;
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
		$title         = trim( $context->title() );
		$length        = mb_strlen( $title );
		$focus_keyword = trim( $context->focus_keyword() );
		$has_keyword   = $context->has_focus_keyword() && false !== mb_stripos( $title, $focus_keyword );

		if ( 0 === $length ) {
			$hint = sprintf(
				/* translators: %d: minimum characters needed */
				I18n::translate( '❌ Titolo assente. Aggiungi un titolo SEO di almeno %d caratteri.' ),
				self::MIN_LENGTH
			);
			
			if ( $context->has_focus_keyword() ) {
				$hint = sprintf(
					/* translators: 1: minimum characters, 2: focus keyword */
					I18n::translate( '❌ Titolo assente. Serve %1$d+ caratteri con la keyword "%2$s".' ),
					self::MIN_LENGTH,
					$focus_keyword
				);
			}
			
			return new Result(
				Result::STATUS_FAIL,
				array(
					'length'          => 0,
					'recommended_min' => self::MIN_LENGTH,
					'recommended_max' => self::MAX_LENGTH,
					'has_keyword'     => false,
				),
				$hint,
				0.10
			);
		}

		$status = Result::STATUS_PASS;
		$hint   = sprintf(
			/* translators: 1: current length, 2: recommended range */
			I18n::translate( '✅ Titolo perfetto: %1$d caratteri (range ideale: %2$d-%3$d)' ),
			$length,
			self::MIN_LENGTH,
			self::MAX_LENGTH
		);

		// Check keyword presence
		if ( $context->has_focus_keyword() && ! $has_keyword ) {
			$status = Result::STATUS_WARN;
			$hint   = sprintf(
				/* translators: 1: current length, 2: focus keyword */
				I18n::translate( '⚠️ %1$d caratteri OK, ma manca la keyword "%2$s" nel titolo.' ),
				$length,
				$focus_keyword
			);
		} elseif ( $context->has_focus_keyword() && $has_keyword ) {
			$hint = sprintf(
				/* translators: 1: current length, 2: focus keyword */
				I18n::translate( '✅ Perfetto! %1$d caratteri + keyword "%2$s" presente.' ),
				$length,
				$focus_keyword
			);
		}

		// Check length bounds
		if ( $length < self::MIN_LENGTH ) {
			$missing = self::MIN_LENGTH - $length;
			
			if ( Result::STATUS_PASS === $status ) {
				$status = Result::STATUS_WARN;
			}
			
			$hint = sprintf(
				/* translators: 1: current length, 2: characters missing, 3: minimum recommended */
				I18n::translate( '⚠️ Titolo corto: %1$d caratteri. Aggiungi altri %2$d caratteri (minimo %3$d).' ),
				$length,
				$missing,
				self::MIN_LENGTH
			);
			
			if ( $context->has_focus_keyword() && ! $has_keyword ) {
				$hint = sprintf(
					/* translators: 1: characters missing, 2: focus keyword */
					I18n::translate( '⚠️ Aggiungi %1$d+ caratteri e includi "%2$s".' ),
					$missing,
					$focus_keyword
				);
			} elseif ( $context->has_focus_keyword() && $has_keyword ) {
				$hint = sprintf(
					/* translators: 1: current length, 2: characters missing */
					I18n::translate( '⚠️ Keyword OK, ma aggiungi altri %2$d caratteri (%1$d→%3$d).' ),
					$length,
					$missing,
					self::MIN_LENGTH
				);
			}
		} elseif ( $length > self::MAX_LENGTH ) {
			$excess = $length - self::MAX_LENGTH;
			
			if ( Result::STATUS_PASS === $status ) {
				$status = Result::STATUS_WARN;
			}
			
			$hint = sprintf(
				/* translators: 1: current length, 2: characters to remove, 3: maximum recommended */
				I18n::translate( '⚠️ Titolo lungo: %1$d caratteri. Riduci di %2$d caratteri (massimo %3$d).' ),
				$length,
				$excess,
				self::MAX_LENGTH
			);
			
			if ( $context->has_focus_keyword() && ! $has_keyword ) {
				$hint = sprintf(
					/* translators: 1: characters to remove, 2: focus keyword */
					I18n::translate( '⚠️ Riduci di %1$d caratteri e aggiungi "%2$s".' ),
					$excess,
					$focus_keyword
				);
			} elseif ( $context->has_focus_keyword() && $has_keyword ) {
				$hint = sprintf(
					/* translators: 1: current length, 2: characters to remove */
					I18n::translate( '⚠️ Keyword OK, ma riduci di %2$d caratteri (%1$d→%3$d).' ),
					$length,
					$excess,
					self::MAX_LENGTH
				);
			}
		}

		// Critical length issues
		$critical_min = max( 30, (int) ( self::MIN_LENGTH * 0.7 ) );
		$critical_max = min( 80, (int) ( self::MAX_LENGTH * 1.3 ) );
		
		if ( $length < $critical_min ) {
			$status  = Result::STATUS_FAIL;
			$missing = $critical_min - $length;
			
			$hint = sprintf(
				/* translators: 1: current length, 2: characters missing critically */
				I18n::translate( '❌ Titolo troppo corto: %1$d caratteri. Servono almeno altri %2$d caratteri!' ),
				$length,
				$missing
			);
			
			if ( $context->has_focus_keyword() && ! $has_keyword ) {
				$hint = sprintf(
					/* translators: 1: characters missing, 2: focus keyword */
					I18n::translate( '❌ Critico! Aggiungi %1$d+ caratteri con "%2$s".' ),
					$missing,
					$focus_keyword
				);
			}
		} elseif ( $length > $critical_max ) {
			$status = Result::STATUS_FAIL;
			$excess = $length - $critical_max;
			
			$hint = sprintf(
				/* translators: 1: current length, 2: characters to remove */
				I18n::translate( '❌ Titolo troppo lungo: %1$d caratteri. Riduci di almeno %2$d caratteri!' ),
				$length,
				$excess
			);
		}

		return new Result(
			$status,
			array(
				'length'          => $length,
				'recommended_min' => self::MIN_LENGTH,
				'recommended_max' => self::MAX_LENGTH,
				'has_keyword'     => $has_keyword,
				'focus_keyword'   => $focus_keyword,
			),
			$hint,
			0.10
		);
	}
}
