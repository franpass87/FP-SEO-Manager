<?php
/**
 * Check for SEO meta description length and presence.
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
use function mb_strlen;
use function mb_stripos;
use function sprintf;
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
	 * Check if keyword words are present in text (flexible matching).
	 * 
	 * @param string $text Text to search in.
	 * @param string $keyword Keyword to search for.
	 * @return bool True if all significant words from keyword are found in text.
	 */
	private function has_keyword_words( string $text, string $keyword ): bool {
		if ( empty( $keyword ) ) {
			return false;
		}
		
		// First try exact match (case-insensitive)
		if ( false !== mb_stripos( $text, $keyword ) ) {
			return true;
		}
		
		// If exact match fails, check if all significant words are present
		// Words to ignore (common Italian/English stop words)
		$stop_words = array( 'a', 'an', 'the', 'di', 'da', 'in', 'su', 'per', 'con', 'il', 'la', 'lo', 'gli', 'le', 'un', 'una', 'uno', 'e', 'o', 'ma', 'che', 'è', 'sono', 'del', 'della', 'dei', 'delle', 'al', 'alla', 'ai', 'alle' );
		
		// Normalize & to handle variations (B&B, B & B, B and B)
		$keyword_normalized = preg_replace( '/\s*&\s*/u', '&', $keyword );
		$text_normalized = preg_replace( '/\s*&\s*/u', '&', $text );
		
		// Try normalized match first
		if ( false !== mb_stripos( $text_normalized, $keyword_normalized ) ) {
			return true;
		}
		
		// Split keyword into words (split on spaces, but preserve & as part of word)
		// First split on spaces
		$keyword_parts = preg_split( '/\s+/u', mb_strtolower( $keyword ), -1, PREG_SPLIT_NO_EMPTY );
		$text_lower = mb_strtolower( $text );
		
		// Check if all significant words/parts are present
		foreach ( $keyword_parts as $part ) {
			$part = trim( $part );
			if ( empty( $part ) ) {
				continue;
			}
			
			// If part contains &, check for it as-is (e.g., "b&b")
			if ( false !== strpos( $part, '&' ) ) {
				if ( false === mb_stripos( $text_lower, $part ) ) {
					// If "b&b" not found, try splitting it
					$sub_parts = explode( '&', $part );
					foreach ( $sub_parts as $sub_part ) {
						$sub_part = trim( $sub_part );
						if ( ! empty( $sub_part ) && false === mb_stripos( $text_lower, $sub_part ) ) {
							return false;
						}
					}
				}
				continue;
			}
			
			// Skip stop words
			if ( in_array( $part, $stop_words, true ) ) {
				continue;
			}
			
			// Check if word is present in text
			if ( false === mb_stripos( $text_lower, $part ) ) {
				return false;
			}
		}
		
		// All significant words found
		return true;
	}

	/**
	 * Execute the meta description evaluation.
	 *
	 * @param Context $context Analyzer context payload.
	 *
	 * @return Result
	 */
	public function run( Context $context ): Result {
		$description   = trim( $context->meta_description() );

		if ( '' === $description ) {
			$description = (string) $context->meta_content( 'name', 'description' );
		}

		$length        = mb_strlen( $description );
		$focus_keyword = trim( $context->focus_keyword() );
		$has_keyword   = $context->has_focus_keyword() && $this->has_keyword_words( $description, $focus_keyword );

		if ( 0 === $length ) {
			$hint = sprintf(
				/* translators: %d: minimum characters needed */
				I18n::translate( '❌ Meta description assente. Aggiungi almeno %d caratteri.' ),
				self::MIN_LENGTH
			);
			
			if ( $context->has_focus_keyword() ) {
				$hint = sprintf(
					/* translators: 1: minimum characters, 2: focus keyword */
					I18n::translate( '❌ Meta description assente. Serve %1$d+ caratteri con "%2$s".' ),
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
			I18n::translate( '✅ Meta description perfetta: %1$d caratteri (range: %2$d-%3$d)' ),
			$length,
			self::MIN_LENGTH,
			self::MAX_LENGTH
		);

		// Check keyword presence
		if ( $context->has_focus_keyword() && ! $has_keyword ) {
			$status = Result::STATUS_WARN;
			$hint   = sprintf(
				/* translators: 1: current length, 2: focus keyword */
				I18n::translate( '⚠️ %1$d caratteri OK, ma manca la keyword "%2$s".' ),
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
				I18n::translate( '⚠️ Description corta: %1$d caratteri. Aggiungi altri %2$d caratteri (minimo %3$d).' ),
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
				I18n::translate( '⚠️ Description lunga: %1$d caratteri. Riduci di %2$d caratteri (massimo %3$d).' ),
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
