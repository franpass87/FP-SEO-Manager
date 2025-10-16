<?php
/**
 * Search Intent Detection Utility.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Utils;

/**
 * Detects and analyzes search intent from content.
 */
class SearchIntentDetector {
	/**
	 * Search intent types.
	 */
	public const INTENT_INFORMATIONAL = 'informational';
	public const INTENT_NAVIGATIONAL  = 'navigational';
	public const INTENT_TRANSACTIONAL = 'transactional';
	public const INTENT_COMMERCIAL    = 'commercial';
	public const INTENT_UNKNOWN       = 'unknown';

	/**
	 * Informational intent keywords.
	 *
	 * @var array<string>
	 */
	private const INFORMATIONAL_KEYWORDS = array(
		// Italian
		'come', 'cosa', 'perché', 'guida', 'tutorial', 'imparare', 'capire',
		'cos\'è', 'cos è', 'quando', 'dove', 'chi è', 'significato', 'definizione',
		'spiegazione', 'esempio', 'esempi', 'differenza', 'differenze',
		// English
		'how', 'what', 'why', 'guide', 'tutorial', 'learn', 'understand',
		'when', 'where', 'who', 'meaning', 'definition', 'explanation',
		'example', 'examples', 'difference', 'differences', 'tips',
	);

	/**
	 * Transactional intent keywords.
	 *
	 * @var array<string>
	 */
	private const TRANSACTIONAL_KEYWORDS = array(
		// Italian
		'acquista', 'compra', 'comprare', 'acquistare', 'ordina', 'ordinare',
		'prezzo', 'prezzi', 'sconto', 'sconti', 'offerta', 'offerte',
		'vendita', 'shop', 'negozio', 'carrello', 'checkout', 'pagamento',
		'spedizione', 'consegna', 'disponibile', 'disponibilità',
		// English
		'buy', 'purchase', 'order', 'price', 'prices', 'discount', 'discounts',
		'deal', 'deals', 'sale', 'shop', 'cart', 'checkout', 'payment',
		'shipping', 'delivery', 'available', 'availability', 'download',
	);

	/**
	 * Commercial investigation intent keywords.
	 *
	 * @var array<string>
	 */
	private const COMMERCIAL_KEYWORDS = array(
		// Italian
		'migliore', 'migliori', 'recensione', 'recensioni', 'confronto',
		'comparazione', 'vs', 'versus', 'alternative', 'alternativa',
		'top', 'classifica', 'valutazione', 'opinioni', 'pareri',
		'vantaggi', 'svantaggi', 'pro', 'contro',
		// English
		'best', 'review', 'reviews', 'comparison', 'compare', 'vs', 'versus',
		'alternative', 'alternatives', 'top', 'ranking', 'ratings',
		'opinions', 'pros', 'cons', 'advantages', 'disadvantages',
	);

	/**
	 * Navigational intent keywords.
	 *
	 * @var array<string>
	 */
	private const NAVIGATIONAL_KEYWORDS = array(
		// Italian
		'login', 'accedi', 'registrati', 'area clienti', 'contatti',
		'chi siamo', 'contattaci', 'sito ufficiale', 'homepage',
		// English
		'login', 'sign in', 'sign up', 'register', 'account', 'contact',
		'about us', 'contact us', 'official site', 'homepage',
	);

	/**
	 * Detect search intent from content and title.
	 *
	 * @param string $title   Content title.
	 * @param string $content Content body.
	 *
	 * @return array{intent: string, confidence: float, signals: array<string>}
	 */
	public static function detect( string $title, string $content ): array {
		$text = strtolower( $title . ' ' . $content );

		$scores = array(
			self::INTENT_INFORMATIONAL => 0,
			self::INTENT_TRANSACTIONAL => 0,
			self::INTENT_COMMERCIAL    => 0,
			self::INTENT_NAVIGATIONAL  => 0,
		);

		$signals = array();

		// Score informational intent.
		foreach ( self::INFORMATIONAL_KEYWORDS as $keyword ) {
			$count = substr_count( $text, $keyword );
			if ( $count > 0 ) {
				$scores[ self::INTENT_INFORMATIONAL ] += $count;
				if ( strpos( $title, $keyword ) !== false ) {
					$scores[ self::INTENT_INFORMATIONAL ] += 2; // Title weight.
				}
				$signals[] = sprintf( 'Informational keyword "%s" found %dx', $keyword, $count );
			}
		}

		// Score transactional intent.
		foreach ( self::TRANSACTIONAL_KEYWORDS as $keyword ) {
			$count = substr_count( $text, $keyword );
			if ( $count > 0 ) {
				$scores[ self::INTENT_TRANSACTIONAL ] += $count * 1.5; // Higher weight for transactional.
				if ( strpos( $title, $keyword ) !== false ) {
					$scores[ self::INTENT_TRANSACTIONAL ] += 3;
				}
				$signals[] = sprintf( 'Transactional keyword "%s" found %dx', $keyword, $count );
			}
		}

		// Score commercial intent.
		foreach ( self::COMMERCIAL_KEYWORDS as $keyword ) {
			$count = substr_count( $text, $keyword );
			if ( $count > 0 ) {
				$scores[ self::INTENT_COMMERCIAL ] += $count * 1.3;
				if ( strpos( $title, $keyword ) !== false ) {
					$scores[ self::INTENT_COMMERCIAL ] += 2.5;
				}
				$signals[] = sprintf( 'Commercial keyword "%s" found %dx', $keyword, $count );
			}
		}

		// Score navigational intent.
		foreach ( self::NAVIGATIONAL_KEYWORDS as $keyword ) {
			$count = substr_count( $text, $keyword );
			if ( $count > 0 ) {
				$scores[ self::INTENT_NAVIGATIONAL ] += $count;
				if ( strpos( $title, $keyword ) !== false ) {
					$scores[ self::INTENT_NAVIGATIONAL ] += 2;
				}
				$signals[] = sprintf( 'Navigational keyword "%s" found %dx', $keyword, $count );
			}
		}

		// Additional signals from content structure.
		if ( preg_match_all( '/\?/u', $text ) > 3 ) {
			$scores[ self::INTENT_INFORMATIONAL ] += 2;
			$signals[] = 'Multiple question marks indicate informational intent';
		}

		if ( preg_match( '/\b(€|£|\$|EUR|USD|GBP)\s*\d+/u', $text ) ) {
			$scores[ self::INTENT_TRANSACTIONAL ] += 3;
			$signals[] = 'Price information found, indicating transactional intent';
		}

		// Determine primary intent.
		arsort( $scores );
		$primary_intent = array_key_first( $scores );
		$max_score      = $scores[ $primary_intent ];
		$total_score    = array_sum( $scores );

		$confidence = $total_score > 0 ? min( 1.0, $max_score / $total_score ) : 0.0;

		if ( $confidence < 0.3 || $max_score === 0 ) {
			$primary_intent = self::INTENT_UNKNOWN;
			$confidence     = 0.0;
			$signals        = array( 'No clear search intent detected' );
		}

		return array(
			'intent'     => $primary_intent,
			'confidence' => round( $confidence, 2 ),
			'signals'    => array_slice( $signals, 0, 5 ), // Limit to top 5 signals.
		);
	}

	/**
	 * Get recommendations based on detected intent.
	 *
	 * @param string $intent Detected search intent.
	 *
	 * @return array<string>
	 */
	public static function get_recommendations( string $intent ): array {
		switch ( $intent ) {
			case self::INTENT_INFORMATIONAL:
				return array(
					__( 'Usa strutture FAQ per rispondere a domande comuni', 'fp-seo-performance' ),
					__( 'Includi esempi pratici e tutorial step-by-step', 'fp-seo-performance' ),
					__( 'Ottimizza per featured snippets con liste e definizioni', 'fp-seo-performance' ),
					__( 'Aggiungi schema markup FAQ o HowTo', 'fp-seo-performance' ),
				);

			case self::INTENT_TRANSACTIONAL:
				return array(
					__( 'Includi CTA (Call-To-Action) chiari e visibili', 'fp-seo-performance' ),
					__( 'Mostra prezzi, disponibilità e opzioni di spedizione', 'fp-seo-performance' ),
					__( 'Aggiungi Product Schema markup', 'fp-seo-performance' ),
					__( 'Semplifica il processo di acquisto/conversione', 'fp-seo-performance' ),
				);

			case self::INTENT_COMMERCIAL:
				return array(
					__( 'Fornisci comparazioni dettagliate e obiettive', 'fp-seo-performance' ),
					__( 'Includi pro/contro e tabelle comparative', 'fp-seo-performance' ),
					__( 'Aggiungi recensioni e testimonial autentici', 'fp-seo-performance' ),
					__( 'Usa Review Schema per aumentare la visibilità', 'fp-seo-performance' ),
				);

			case self::INTENT_NAVIGATIONAL:
				return array(
					__( 'Ottimizza il brand name e i dati di contatto', 'fp-seo-performance' ),
					__( 'Implementa Organization Schema markup', 'fp-seo-performance' ),
					__( 'Assicurati che il logo e menu siano ben strutturati', 'fp-seo-performance' ),
				);

			default:
				return array(
					__( 'Definisci meglio l\'obiettivo del contenuto', 'fp-seo-performance' ),
					__( 'Includi keyword specifiche che indicano l\'intento utente', 'fp-seo-performance' ),
				);
		}
	}

	/**
	 * Get human-readable label for intent type.
	 *
	 * @param string $intent Intent type.
	 *
	 * @return string
	 */
	public static function get_intent_label( string $intent ): string {
		$labels = array(
			self::INTENT_INFORMATIONAL => __( 'Informazionale', 'fp-seo-performance' ),
			self::INTENT_TRANSACTIONAL => __( 'Transazionale', 'fp-seo-performance' ),
			self::INTENT_COMMERCIAL    => __( 'Commerciale', 'fp-seo-performance' ),
			self::INTENT_NAVIGATIONAL  => __( 'Navigazionale', 'fp-seo-performance' ),
			self::INTENT_UNKNOWN       => __( 'Non determinato', 'fp-seo-performance' ),
		);

		return $labels[ $intent ] ?? $intent;
	}
}
