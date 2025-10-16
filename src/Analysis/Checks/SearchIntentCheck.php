<?php
/**
 * Search Intent Check.
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
use FP\SEO\Utils\SearchIntentDetector;

/**
 * Verifies search intent alignment and provides optimization recommendations.
 */
class SearchIntentCheck implements CheckInterface {
	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'search_intent';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'Search Intent', 'fp-seo-performance' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return __( 'Analizza l\'intento di ricerca del contenuto e fornisce raccomandazioni per ottimizzare l\'allineamento con le aspettative degli utenti.', 'fp-seo-performance' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function run( Context $context ): Result {
		$content = $context->content();
		$title   = $context->metadata( 'title', '' );

		if ( empty( $content ) ) {
			return new Result(
				Result::STATUS_WARN,
				__( 'Contenuto insufficiente per analizzare il search intent.', 'fp-seo-performance' )
			);
		}

		// Detect search intent.
		$detection = SearchIntentDetector::detect( $title, $content );

		$intent     = $detection['intent'];
		$confidence = $detection['confidence'];
		$signals    = $detection['signals'];

		// Determine status based on confidence.
		$status = Result::STATUS_PASS;
		if ( $intent === SearchIntentDetector::INTENT_UNKNOWN || $confidence < 0.3 ) {
			$status = Result::STATUS_WARN;
		} elseif ( $confidence < 0.5 ) {
			$status = Result::STATUS_WARN;
		}

		// Build message.
		$intent_label = SearchIntentDetector::get_intent_label( $intent );

		if ( $intent === SearchIntentDetector::INTENT_UNKNOWN ) {
			$message = sprintf(
				/* translators: %s: confidence percentage */
				__( 'Search intent non chiaro. Definisci meglio l\'obiettivo del contenuto per migliorare il posizionamento.', 'fp-seo-performance' )
			);
		} else {
			$message = sprintf(
				/* translators: 1: intent type, 2: confidence percentage */
				__( 'Search Intent rilevato: %1$s (confidenza: %2$d%%)', 'fp-seo-performance' ),
				'<strong>' . esc_html( $intent_label ) . '</strong>',
				(int) ( $confidence * 100 )
			);
		}

		// Add recommendations.
		$recommendations = SearchIntentDetector::get_recommendations( $intent );

		if ( ! empty( $recommendations ) ) {
			$message .= '<br><br><strong>' . __( 'Raccomandazioni:', 'fp-seo-performance' ) . '</strong><ul>';
			foreach ( $recommendations as $rec ) {
				$message .= '<li>' . esc_html( $rec ) . '</li>';
			}
			$message .= '</ul>';
		}

		// Add signals for debugging (only in admin context).
		if ( ! empty( $signals ) && current_user_can( 'manage_options' ) ) {
			$message .= '<br><small><strong>' . __( 'Segnali rilevati:', 'fp-seo-performance' ) . '</strong><br>';
			$message .= esc_html( implode( ', ', array_slice( $signals, 0, 3 ) ) );
			$message .= '</small>';
		}

		return new Result( $status, $message );
	}
}
