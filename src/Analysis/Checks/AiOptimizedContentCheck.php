<?php
/**
 * Check for AI-optimized content structure - Critical for Google AI Overview.
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
use FP\SEO\Utils\Html;
use FP\SEO\Utils\I18n;
use function count;
use function mb_strlen;
use function preg_match_all;
use function preg_split;
use function str_word_count;
use function strlen;
use function substr_count;
use function trim;

/**
 * Validates content structure for Google AI Overview optimization.
 *
 * Verifica che il contenuto sia strutturato in modo ottimale per essere
 * estratto e utilizzato dalle AI Overview di Google:
 * - Paragrafi brevi e concisi
 * - Liste e punti elenco
 * - Risposte dirette alle domande
 * - Contenuti scannabili
 */
class AiOptimizedContentCheck implements CheckInterface {
	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'ai_optimized_content';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return I18n::translate( 'Contenuti ottimizzati per AI Overview' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return I18n::translate( 'Verifica che i contenuti siano strutturati in modo ottimale per le Google AI Overview: paragrafi brevi, liste, risposte dirette.' );
	}

	/**
	 * Evaluate content structure for AI optimization.
	 *
	 * @param Context $context Analyzer context payload.
	 *
	 * @return Result
	 */
	public function run( Context $context ): Result {
		$html = $context->html();
		$plain_text = $context->plain_text();

		if ( empty( $plain_text ) || mb_strlen( $plain_text ) < 100 ) {
			return new Result(
				Result::STATUS_WARN,
				array( 'error' => 'insufficient_content' ),
				I18n::translate( 'Contenuto insufficiente per l\'analisi AI.' ),
				0.09
			);
		}

		$analysis = array(
			'has_lists' => $this->count_lists( $html ),
			'has_questions' => $this->count_questions( $plain_text ),
			'avg_paragraph_length' => $this->analyze_paragraph_length( $html ),
			'has_tables' => substr_count( $html, '<table' ) > 0,
			'word_count' => str_word_count( $plain_text ),
		);

		$score = 0;
		$recommendations = array();

		// Liste e punti elenco (molto importanti per AI Overview)
		if ( $analysis['has_lists'] >= 2 ) {
			$score += 3;
		} elseif ( $analysis['has_lists'] >= 1 ) {
			$score += 2;
			$recommendations[] = 'Aggiungi più liste per migliorare la scannabilità.';
		} else {
			$recommendations[] = 'Usa liste puntate o numerate per informazioni chiave - le AI le preferiscono.';
		}

		// Domande nel contenuto (ottimale per query conversazionali)
		if ( $analysis['has_questions'] >= 3 ) {
			$score += 3;
		} elseif ( $analysis['has_questions'] >= 1 ) {
			$score += 2;
			$recommendations[] = 'Considera di aggiungere più domande esplicite seguite da risposte dirette.';
		} else {
			$recommendations[] = 'Includi domande esplicite (con ?) seguite da risposte chiare - ottimale per AI Overview.';
		}

		// Lunghezza paragrafi (paragrafi brevi = migliore per AI)
		if ( $analysis['avg_paragraph_length'] <= 150 ) {
			$score += 3;
		} elseif ( $analysis['avg_paragraph_length'] <= 250 ) {
			$score += 2;
			$recommendations[] = 'I paragrafi potrebbero essere più brevi per facilitare l\'estrazione AI.';
		} else {
			$recommendations[] = 'Riduci la lunghezza dei paragrafi (max 150 parole) per ottimizzare per AI Overview.';
		}

		// Tabelle (utili per dati strutturati)
		if ( $analysis['has_tables'] ) {
			$score += 1;
		}

		// Lunghezza complessiva
		if ( $analysis['word_count'] >= 300 && $analysis['word_count'] <= 2000 ) {
			$score += 2;
		} elseif ( $analysis['word_count'] > 2000 ) {
			$recommendations[] = 'Contenuto molto lungo: considera di suddividerlo o aggiungere un sommario iniziale.';
			$score += 1;
		}

		$max_score = 12;
		$percentage = ( $score / $max_score ) * 100;

		if ( $percentage >= 75 ) {
			return new Result(
				Result::STATUS_PASS,
				array_merge( $analysis, array( 'score_percentage' => $percentage ) ),
				I18n::translate( sprintf( 'Ottimo! Contenuto ben strutturato per le AI Overview (score: %.0f%%). %s', $percentage, empty( $recommendations ) ? '' : 'Suggerimenti: ' . implode( ' ', $recommendations ) ) ),
				0.09
			);
		}

		if ( $percentage >= 50 ) {
			return new Result(
				Result::STATUS_WARN,
				array_merge( $analysis, array( 'score_percentage' => $percentage ) ),
				I18n::translate( sprintf( 'Contenuto parzialmente ottimizzato per AI (score: %.0f%%). Migliorie: %s', $percentage, implode( ' ', $recommendations ) ) ),
				0.09
			);
		}

		return new Result(
			Result::STATUS_FAIL,
			array_merge( $analysis, array( 'score_percentage' => $percentage ) ),
			I18n::translate( sprintf( 'Contenuto non ottimizzato per AI Overview (score: %.0f%%). Azioni necessarie: %s', $percentage, implode( ' ', $recommendations ) ) ),
			0.09
		);
	}

	/**
	 * Count list elements in HTML.
	 *
	 * @param string $html HTML content.
	 * @return int
	 */
	private function count_lists( string $html ): int {
		return substr_count( $html, '<ul' ) + substr_count( $html, '<ol' );
	}

	/**
	 * Count questions in text (sentences ending with ?).
	 *
	 * @param string $text Plain text content.
	 * @return int
	 */
	private function count_questions( string $text ): int {
		return substr_count( $text, '?' );
	}

	/**
	 * Analyze average paragraph length.
	 *
	 * @param string $html HTML content.
	 * @return int Average words per paragraph.
	 */
	private function analyze_paragraph_length( string $html ): int {
		// Extract paragraphs
		preg_match_all( '/<p[^>]*>(.*?)<\/p>/si', $html, $matches );

		if ( empty( $matches[1] ) ) {
			return 0;
		}

		$total_words = 0;
		$paragraph_count = 0;

		foreach ( $matches[1] as $paragraph ) {
			$text = Html::strip_tags( $paragraph );
			$text = trim( $text );

			if ( empty( $text ) ) {
				continue;
			}

			$words = str_word_count( $text );
			$total_words += $words;
			$paragraph_count++;
		}

		if ( $paragraph_count === 0 ) {
			return 0;
		}

		return (int) ( $total_words / $paragraph_count );
	}
}