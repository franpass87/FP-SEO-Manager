<?php
/**
 * HTTP client interface.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Core\Services\Http;

/**
 * Interface for HTTP client service.
 */
interface HttpClientInterface {

	/**
	 * Send a GET request.
	 *
	 * @param string $url  Request URL.
	 * @param array<string, mixed> $headers Optional headers.
	 * @param array<string, mixed> $args   Optional request arguments.
	 * @return HttpResponse Response object.
	 */
	public function get( string $url, array $headers = array(), array $args = array() ): HttpResponse;

	/**
	 * Send a POST request.
	 *
	 * @param string $url  Request URL.
	 * @param array<string, mixed>|string $body    Request body.
	 * @param array<string, mixed> $headers Optional headers.
	 * @param array<string, mixed> $args   Optional request arguments.
	 * @return HttpResponse Response object.
	 */
	public function post( string $url, $body = array(), array $headers = array(), array $args = array() ): HttpResponse;

	/**
	 * Send a PUT request.
	 *
	 * @param string $url  Request URL.
	 * @param array<string, mixed>|string $body    Request body.
	 * @param array<string, mixed> $headers Optional headers.
	 * @param array<string, mixed> $args   Optional request arguments.
	 * @return HttpResponse Response object.
	 */
	public function put( string $url, $body = array(), array $headers = array(), array $args = array() ): HttpResponse;

	/**
	 * Send a DELETE request.
	 *
	 * @param string $url  Request URL.
	 * @param array<string, mixed> $headers Optional headers.
	 * @param array<string, mixed> $args   Optional request arguments.
	 * @return HttpResponse Response object.
	 */
	public function delete( string $url, array $headers = array(), array $args = array() ): HttpResponse;

	/**
	 * Send a request with retry logic.
	 *
	 * @param string $method HTTP method.
	 * @param string $url    Request URL.
	 * @param array<string, mixed> $args Request arguments.
	 * @param int    $max_retries Maximum number of retries.
	 * @return HttpResponse Response object.
	 */
	public function request_with_retry( string $method, string $url, array $args = array(), int $max_retries = 3 ): HttpResponse;
}



