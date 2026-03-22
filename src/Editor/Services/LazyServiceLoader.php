<?php
/**
 * Lazy loading service for heavy services (AI, GSC).
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Services;

use FP\SEO\Integrations\OpenAiClient;
use FP\SEO\Integrations\GscData;
use FP\SEO\Infrastructure\Contracts\LoggerInterface;

/**
 * Lazy loader for heavy services to improve performance.
 * 
 * Services are only loaded when actually needed, reducing memory usage
 * and initialization time when metabox is rendered but services aren't used.
 */
class LazyServiceLoader {

	/**
	 * OpenAI client instance (lazy loaded).
	 *
	 * @var OpenAiClient|null
	 */
	private ?OpenAiClient $openai_client = null;

	/**
	 * GSC data instance (lazy loaded).
	 *
	 * @var GscData|null
	 */
	private ?GscData $gsc_data = null;

	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface|null
	 */
	private ?LoggerInterface $logger = null;

	/**
	 * Constructor.
	 *
	 * @param LoggerInterface|null $logger Optional logger instance.
	 */
	public function __construct( ?LoggerInterface $logger = null ) {
		$this->logger = $logger;
	}

	/**
	 * Get OpenAI client instance (lazy loaded).
	 *
	 * @return OpenAiClient|null OpenAI client or null if not available.
	 */
	public function get_openai_client(): ?OpenAiClient {
		if ( $this->openai_client === null ) {
			try {
				$container = \FP\SEO\Infrastructure\Plugin::instance()->get_container();
				$this->openai_client = $container->get( OpenAiClient::class );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $this->logger ) {
					$this->logger->debug( 'LazyServiceLoader: OpenAI client loaded' );
				}
			} catch ( \Throwable $e ) {
				if ( $this->logger ) {
					$this->logger->error( 'LazyServiceLoader: Failed to load OpenAI client', array(
						'error' => $e->getMessage(),
					) );
				}
				return null;
			}
		}

		return $this->openai_client;
	}

	/**
	 * Get GSC data instance (lazy loaded).
	 *
	 * @return GscData|null GSC data or null if not available.
	 */
	public function get_gsc_data(): ?GscData {
		if ( $this->gsc_data === null ) {
			try {
				$this->gsc_data = new GscData();
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $this->logger ) {
					$this->logger->debug( 'LazyServiceLoader: GSC data loaded' );
				}
			} catch ( \Throwable $e ) {
				if ( $this->logger ) {
					$this->logger->error( 'LazyServiceLoader: Failed to load GSC data', array(
						'error' => $e->getMessage(),
					) );
				}
				return null;
			}
		}

		return $this->gsc_data;
	}

	/**
	 * Check if OpenAI is configured (without loading the client).
	 *
	 * @return bool True if configured.
	 */
	public function is_openai_configured(): bool {
		// Quick check without loading the full client
		$api_key = \FP\SEO\Utils\OptionsHelper::get_option( 'ai.openai_api_key', '' );
		return ! empty( $api_key );
	}

	/**
	 * Check if GSC is configured (without loading the service).
	 *
	 * @return bool True if configured.
	 */
	public function is_gsc_configured(): bool {
		// Quick check without loading the full service
		$gsc_enabled = \FP\SEO\Utils\OptionsHelper::get_option( 'gsc.enable_gsc_data', false );
		return ! empty( $gsc_enabled );
	}

	/**
	 * Reset all loaded services (useful for testing).
	 *
	 * @return void
	 */
	public function reset(): void {
		$this->openai_client = null;
		$this->gsc_data = null;
	}
}



