<?php
/**
 * Tests for SearchIntentCheck.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Unit\Analysis;

use FP\SEO\Analysis\Checks\SearchIntentCheck;
use FP\SEO\Analysis\Context;
use FP\SEO\Analysis\Result;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FP\SEO\Analysis\Checks\SearchIntentCheck
 */
class SearchIntentCheckTest extends TestCase {
	/**
	 * Instance under test.
	 *
	 * @var SearchIntentCheck
	 */
	private SearchIntentCheck $check;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->check = new SearchIntentCheck();
	}

	/**
	 * Test check ID.
	 */
	public function test_id(): void {
		$this->assertSame( 'search_intent', $this->check->id() );
	}

	/**
	 * Test check label.
	 */
	public function test_label(): void {
		$label = $this->check->label();
		$this->assertIsString( $label );
		$this->assertNotEmpty( $label );
	}

	/**
	 * Test check description.
	 */
	public function test_description(): void {
		$description = $this->check->description();
		$this->assertIsString( $description );
		$this->assertNotEmpty( $description );
	}

	/**
	 * Test run with informational content.
	 */
	public function test_run_with_informational_content(): void {
		$context = new Context(
			'Come ottimizzare la SEO: guida completa',
			'In questo tutorial ti spiegherò come migliorare la SEO del tuo sito. 
			 Imparerai cosa fare e perché è importante seguire le best practice.',
			array( 'title' => 'Come ottimizzare la SEO: guida completa' ),
			1
		);

		$result = $this->check->run( $context );

		$this->assertInstanceOf( Result::class, $result );
		$this->assertContains( $result->status(), array( Result::STATUS_PASS, Result::STATUS_WARN ) );
		$this->assertNotEmpty( $result->message() );
	}

	/**
	 * Test run with transactional content.
	 */
	public function test_run_with_transactional_content(): void {
		$context = new Context(
			'Acquista Hosting WordPress',
			'Compra ora il miglior hosting a prezzo scontato. Disponibile con offerta speciale.
			 Ordina subito e risparmia. Prezzi a partire da €9.99.',
			array( 'title' => 'Acquista Hosting WordPress' ),
			1
		);

		$result = $this->check->run( $context );

		$this->assertInstanceOf( Result::class, $result );
		$this->assertNotEmpty( $result->message() );
		// Should detect transactional intent
		$this->assertStringContainsString( 'Intent', $result->message() );
	}

	/**
	 * Test run with empty content warns.
	 */
	public function test_run_with_empty_content_warns(): void {
		$context = new Context( '', '', array(), 1 );

		$result = $this->check->run( $context );

		$this->assertSame( Result::STATUS_WARN, $result->status() );
		$this->assertNotEmpty( $result->message() );
	}

	/**
	 * Test run with ambiguous content.
	 */
	public function test_run_with_ambiguous_content(): void {
		$context = new Context(
			'Test',
			'This is some generic content without clear signals.',
			array( 'title' => 'Test' ),
			1
		);

		$result = $this->check->run( $context );

		$this->assertSame( Result::STATUS_WARN, $result->status() );
	}

	/**
	 * Test recommendations are included in message.
	 */
	public function test_recommendations_included_in_message(): void {
		$context = new Context(
			'Come creare un blog',
			'Guida su come creare e gestire un blog. Imparerai tutto quello che serve.',
			array( 'title' => 'Come creare un blog' ),
			1
		);

		$result = $this->check->run( $context );

		$message = $result->message();
		$this->assertStringContainsString( 'Raccomandazioni', $message );
	}
}
