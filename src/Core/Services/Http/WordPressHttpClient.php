<?php
/**
 * WordPress HTTP client implementation.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Core\Services\Http;

use WP_Error;
use function wp_remote_get;
use function wp_remote_post;
use function wp_remote_request;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;
use function wp_remote_retrieve_headers;

/**
 * WordPress-based HTTP client.
 */
class WordPressHttpClient implements HttpClientInterface {

	/**
	 * Default request timeout in seconds.
	 *
	 * @var int
	 */
	private int $timeout = 30;

	/**
	 * Default number of retries.
	 *
	 * @var int
	 */
	private int $default_retries = 3;

	/**
	 * Constructor.
	 *
	 * @param int $timeout Default timeout in seconds.
	 */
	public function __construct( int $timeout = 30 ) {
		$this->timeout = $timeout;
	}

	/**
	 * Send a GET request.
	 *
	 * @param string $url  Request URL.
	 * @param array<string, mixed> $headers Optional headers.
	 * @param array<string, mixed> $args   Optional request arguments.
	 * @return HttpResponse Response object.
	 */
	public function get( string $url, array $headers = array(), array $args = array() ): HttpResponse {
		$args['method']  = 'GET';
		$args['headers'] = array_merge( $args['headers'] ?? array(), $headers );
		$args['timeout'] = $args['timeout'] ?? $this->timeout;

		return $this->request( $url, $args );
	}

	/**
	 * Send a POST request.
	 *
	 * @param string $url  Request URL.
	 * @param array<string, mixed>|string $body    Request body.
	 * @param array<string, mixed> $headers Optional headers.
	 * @param array<string, mixed> $args   Optional request arguments.
	 * @return HttpResponse Response object.
	 */
	public function post( string $url, $body = array(), array $headers = array(), array $args = array() ): HttpResponse {
		$args['method']  = 'POST';
		$args['headers'] = array_merge( $args['headers'] ?? array(), $headers );
		$args['body']    = $body;
		$args['timeout'] = $args['timeout'] ?? $this->timeout;

		return $this->request( $url, $args );
	}

	/**
	 * Send a PUT request.
	 *
	 * @param string $url  Request URL.
	 * @param array<string, mixed>|string $body    Request body.
	 * @param array<string, mixed> $headers Optional headers.
	 * @param array<string, mixed> $args   Optional request arguments.
	 * @return HttpResponse Response object.
	 */
	public function put( string $url, $body = array(), array $headers = array(), array $args = array() ): HttpResponse {
		$args['method']  = 'PUT';
		$args['headers'] = array_merge( $args['headers'] ?? array(), $headers );
		$args['body']    = $body;
		$args['timeout'] = $args['timeout'] ?? $this->timeout;

		return $this->request( $url, $args );
	}

	/**
	 * Send a DELETE request.
	 *
	 * @param string $url  Request URL.
	 * @param array<string, mixed> $headers Optional headers.
	 * @param array<string, mixed> $args   Optional request arguments.
	 * @return HttpResponse Response object.
	 */
	public function delete( string $url, array $headers = array(), array $args = array() ): HttpResponse {
		$args['method']  = 'DELETE';
		$args['headers'] = array_merge( $args['headers'] ?? array(), $headers );
		$args['timeout'] = $args['timeout'] ?? $this->timeout;

		return $this->request( $url, $args );
	}

	/**
	 * Send a request with retry logic.
	 *
	 * @param string $method HTTP method.
	 * @param string $url    Request URL.
	 * @param array<string, mixed> $args Request arguments.
	 * @param int    $max_retries Maximum number of retries.
	 * @return HttpResponse Response object.
	 */
	public function request_with_retry( string $method, string $url, array $args = array(), int $max_retries = 3 ): HttpResponse {
		$args['method'] = $method;
		$attempts       = 0;
		$last_response  = null;

		while ( $attempts < $max_retries ) {
			$attempts++;
			$response = $this->request( $url, $args );

			// If successful or client error (4xx), don't retry
			if ( $response->is_success() || ( $response->get_status_code() >= 400 && $response->get_status_code() < 500 ) ) {
				return $response;
			}

			$last_response = $response;

			// Wait before retry (exponential backoff)
			if ( $attempts < $max_retries ) {
				$delay = pow( 2, $attempts - 1 ); // 1s, 2s, 4s, etc.
				sleep( $delay );
			}
		}

		return $last_response ?? new HttpResponse( 500, 'Request failed after ' . $max_retries . ' attempts' );
	}

	/**
	 * Send a request using wp_remote_request.
	 *
	 * @param string $url  Request URL.
	 * @param array<string, mixed> $args Request arguments.
	 * @return HttpResponse Response object.
	 */
	private function request( string $url, array $args = array() ): HttpResponse {
		$response = wp_remote_request( $url, $args );

		if ( $response instanceof WP_Error ) {
			return new HttpResponse(
				500,
				$response->get_error_message(),
				array()
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$headers     = wp_remote_retrieve_headers( $response );

		// Convert headers to array
		$headers_array = array();
		if ( $headers ) {
			foreach ( $headers as $name => $value ) {
				$headers_array[ $name ] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
			}
		}

		return new HttpResponse( (int) $status_code, $body, $headers_array );
	}
}



