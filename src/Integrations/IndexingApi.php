<?php
/**
 * Google Indexing API - Instant Indexing
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Integrations;

use FP\SEO\Utils\Logger;

use Google\Client;
use Google\Service\Indexing;
use Google\Service\Indexing\UrlNotification;

/**
 * Submits URLs to Google for instant indexing
 */
class IndexingApi {

	/**
	 * Google Client
	 *
	 * @var Client|null
	 */
	private ?Client $client = null;

	/**
	 * Indexing service
	 *
	 * @var Indexing|null
	 */
	private ?Indexing $service = null;

	/**
	 * Initialize client
	 *
	 * @return bool
	 */
	public function authenticate(): bool {
		$options = get_option( 'fp_seo_performance', array() );
		$gsc     = $options['gsc'] ?? array();

		if ( empty( $gsc['service_account_json'] ) ) {
			return false;
		}

		try {
			$this->client = new Client();
			$this->client->setApplicationName( 'FP SEO Performance' );
			$this->client->setScopes( array( Indexing::INDEXING ) );

			$credentials = json_decode( $gsc['service_account_json'], true );
			if ( ! $credentials ) {
				return false;
			}

			$this->client->setAuthConfig( $credentials );
			$this->service = new Indexing( $this->client );

			return true;
		} catch ( \Exception $e ) {
			Logger::error( 'Indexing API Auth Error', array( 'error' => $e->getMessage() ) );
			return false;
		}
	}

	/**
	 * Submit URL for indexing
	 *
	 * @param string $url    URL to submit.
	 * @param string $type   Type: 'URL_UPDATED' or 'URL_DELETED'.
	 * @return bool
	 */
	public function submit_url( string $url, string $type = 'URL_UPDATED' ): bool {
		if ( ! $this->authenticate() ) {
			return false;
		}

		try {
			$notification = new UrlNotification();
			$notification->setUrl( $url );
			$notification->setType( $type );

			$this->service->urlNotifications->publish( $notification );

			// Log success
			Logger::info( 'URL submitted to Google Indexing API', array( 'url' => $url, 'type' => $type ) );

			return true;
		} catch ( \Exception $e ) {
			Logger::error( 'Indexing API Error', array( 'error' => $e->getMessage(), 'url' => $url ) );
			return false;
		}
	}

	/**
	 * Submit post for indexing
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function submit_post( int $post_id ): bool {
		$url = get_permalink( $post_id );
		if ( ! $url ) {
			return false;
		}

		return $this->submit_url( $url, 'URL_UPDATED' );
	}

	/**
	 * Notify URL deletion
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function notify_deletion( int $post_id ): bool {
		$url = get_permalink( $post_id );
		if ( ! $url ) {
			return false;
		}

		return $this->submit_url( $url, 'URL_DELETED' );
	}
}

