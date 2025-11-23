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
		
		// Normalize text: decode HTML entities, normalize spaces, remove extra whitespace
		$text_normalized = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text_normalized = preg_replace( '/\s+/u', ' ', $text_normalized );
		$text_normalized = trim( $text_normalized );
		
		$keyword_normalized = trim( $keyword );
		
		// First try exact match (case-insensitive)
		if ( false !== mb_stripos( $text_normalized, $keyword_normalized ) ) {
			return true;
		}
		
		// If exact match fails, check if all significant words are present
		// Words to ignore (common Italian/English stop words)
		$stop_words = array( 'a', 'an', 'the', 'di', 'da', 'in', 'su', 'per', 'con', 'il', 'la', 'lo', 'gli', 'le', 'un', 'una', 'uno', 'e', 'o', 'ma', 'che', 'è', 'sono', 'del', 'della', 'dei', 'delle', 'al', 'alla', 'ai', 'alle' );
		
		// Normalize & to handle variations (B&B, B & B, B and B, &amp;)
		// $text_normalized is already decoded, so we just need to decode keyword if needed
		$keyword_decoded = html_entity_decode( $keyword_normalized, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		
		// Normalize spaces around & (remove spaces before/after &)
		$keyword_amp_normalized = preg_replace( '/\s*&\s*/u', '&', $keyword_decoded );
		$text_amp_normalized = preg_replace( '/\s*&\s*/u', '&', $text_normalized );
		
		// Try normalized match with & normalization
		if ( false !== mb_stripos( $text_amp_normalized, $keyword_amp_normalized ) ) {
			return true;
		}
		
		// Also try with HTML entity &amp;
		$keyword_amp_entity = str_replace( '&', '&amp;', $keyword_amp_normalized );
		if ( false !== mb_stripos( $text_normalized, $keyword_amp_entity ) ) {
			return true;
		}
		
		// Split keyword into words (split on spaces, but preserve & as part of word)
		// Use the decoded and normalized keyword
		$keyword_parts = preg_split( '/\s+/u', mb_strtolower( $keyword_decoded ), -1, PREG_SPLIT_NO_EMPTY );
		$text_lower = mb_strtolower( $text_amp_normalized );
		
		// Check if all significant words/parts are present
		foreach ( $keyword_parts as $part ) {
			$part = trim( $part );
			if ( empty( $part ) ) {
				continue;
			}
			
			// If part contains &, check for it as-is (e.g., "b&b")
			if ( false !== strpos( $part, '&' ) ) {
				// Try exact match first
				if ( false !== mb_stripos( $text_lower, $part ) ) {
					continue; // Found, move to next part
				}
				
				// Try with HTML entity
				$part_entity = str_replace( '&', '&amp;', $part );
				if ( false !== mb_stripos( $text_lower, $part_entity ) ) {
					continue; // Found, move to next part
				}
				
				// If "b&b" not found, try splitting it
				$sub_parts = explode( '&', $part );
				$all_found = true;
				foreach ( $sub_parts as $sub_part ) {
					$sub_part = trim( $sub_part );
					if ( ! empty( $sub_part ) && false === mb_stripos( $text_lower, $sub_part ) ) {
						$all_found = false;
						break;
					}
				}
				if ( ! $all_found ) {
					return false;
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
			// Get text content and normalize it
			$text = trim( (string) $node->{'textContent'} );
			// Decode HTML entities to get clean text
			$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			// Normalize whitespace
			$text = preg_replace( '/\s+/u', ' ', $text );
			$text = trim( $text );

			if ( '' !== $text ) {
				++$count;
				if ( 1 === $count ) {
					$h1_text = $text;
				}
			}
		}

		// Check keyword in H1
		if ( $context->has_focus_keyword() && '' !== $h1_text ) {
			$has_keyword = $this->has_keyword_words( $h1_text, $focus_keyword );
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
