<?php
/**
 * Tests for SearchIntentDetector utility.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Unit\Utils;

use FP\SEO\Utils\SearchIntentDetector;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FP\SEO\Utils\SearchIntentDetector
 */
class SearchIntentDetectorTest extends TestCase {
	/**
	 * Test informational intent detection.
	 */
	public function test_detect_informational_intent(): void {
		$title   = 'Come creare un sito web: guida completa';
		$content = 'In questo tutorial imparerai passo dopo passo come creare un sito web. 
					Ti spiegherò cosa serve e perché è importante avere una presenza online.';

		$result = SearchIntentDetector::detect( $title, $content );

		$this->assertSame( SearchIntentDetector::INTENT_INFORMATIONAL, $result['intent'] );
		$this->assertGreaterThan( 0.5, $result['confidence'] );
		$this->assertIsArray( $result['signals'] );
	}

	/**
	 * Test transactional intent detection.
	 */
	public function test_detect_transactional_intent(): void {
		$title   = 'Acquista WordPress Hosting - Offerta Speciale';
		$content = 'Compra ora il miglior hosting WordPress a prezzo scontato. 
					Disponibile con spedizione gratuita. Ordina subito e risparmia il 50%.
					Prezzi a partire da €9.99/mese.';

		$result = SearchIntentDetector::detect( $title, $content );

		$this->assertSame( SearchIntentDetector::INTENT_TRANSACTIONAL, $result['intent'] );
		$this->assertGreaterThan( 0.5, $result['confidence'] );
	}

	/**
	 * Test commercial intent detection.
	 */
	public function test_detect_commercial_intent(): void {
		$title   = 'Migliori Plugin SEO WordPress 2024: Recensione e Confronto';
		$content = 'In questa recensione confronteremo i migliori plugin SEO per WordPress.
					Analizzeremo vantaggi e svantaggi di ogni soluzione, con opinioni
					basate su test reali. Top 10 alternative a confronto.';

		$result = SearchIntentDetector::detect( $title, $content );

		$this->assertSame( SearchIntentDetector::INTENT_COMMERCIAL, $result['intent'] );
		$this->assertGreaterThan( 0.4, $result['confidence'] );
	}

	/**
	 * Test navigational intent detection.
	 */
	public function test_detect_navigational_intent(): void {
		$title   = 'Login Area Clienti';
		$content = 'Accedi alla tua area clienti per gestire il tuo account.
					Contatti e informazioni disponibili nella homepage del sito ufficiale.';

		$result = SearchIntentDetector::detect( $title, $content );

		$this->assertSame( SearchIntentDetector::INTENT_NAVIGATIONAL, $result['intent'] );
	}

	/**
	 * Test unknown intent for ambiguous content.
	 */
	public function test_detect_unknown_intent_for_ambiguous_content(): void {
		$title   = 'Test Page';
		$content = 'This is a test with no clear signals.';

		$result = SearchIntentDetector::detect( $title, $content );

		$this->assertSame( SearchIntentDetector::INTENT_UNKNOWN, $result['intent'] );
		$this->assertLessThanOrEqual( 0.3, $result['confidence'] );
	}

	/**
	 * Test English keywords detection.
	 */
	public function test_detect_with_english_keywords(): void {
		$title   = 'How to Learn WordPress: Complete Guide';
		$content = 'Learn how to build websites with WordPress. This tutorial explains
					what you need to know and provides examples.';

		$result = SearchIntentDetector::detect( $title, $content );

		$this->assertSame( SearchIntentDetector::INTENT_INFORMATIONAL, $result['intent'] );
	}

	/**
	 * Test price detection signals.
	 */
	public function test_detect_price_signals(): void {
		$title   = 'Product Name';
		$content = 'Available for €99.99. Special discount price: $49 USD.';

		$result = SearchIntentDetector::detect( $title, $content );

		// Price signals should boost transactional intent.
		$this->assertContains( 'Price information found', $result['signals'] );
	}

	/**
	 * Test get recommendations for informational intent.
	 */
	public function test_get_recommendations_informational(): void {
		$recommendations = SearchIntentDetector::get_recommendations(
			SearchIntentDetector::INTENT_INFORMATIONAL
		);

		$this->assertIsArray( $recommendations );
		$this->assertNotEmpty( $recommendations );
		$this->assertGreaterThanOrEqual( 3, count( $recommendations ) );
	}

	/**
	 * Test get recommendations for transactional intent.
	 */
	public function test_get_recommendations_transactional(): void {
		$recommendations = SearchIntentDetector::get_recommendations(
			SearchIntentDetector::INTENT_TRANSACTIONAL
		);

		$this->assertIsArray( $recommendations );
		$this->assertNotEmpty( $recommendations );
	}

	/**
	 * Test get intent label.
	 */
	public function test_get_intent_label(): void {
		$label = SearchIntentDetector::get_intent_label(
			SearchIntentDetector::INTENT_INFORMATIONAL
		);

		$this->assertIsString( $label );
		$this->assertNotEmpty( $label );
	}

	/**
	 * Test title weighting in detection.
	 */
	public function test_title_has_higher_weight(): void {
		// Title with transactional keyword should have higher confidence.
		$result1 = SearchIntentDetector::detect(
			'Acquista Prodotto',
			'Lorem ipsum dolor sit amet'
		);

		// Same keyword in content only.
		$result2 = SearchIntentDetector::detect(
			'Lorem Ipsum',
			'Acquista prodotto dolor sit amet'
		);

		$this->assertGreaterThan( $result2['confidence'], $result1['confidence'] );
	}
}
