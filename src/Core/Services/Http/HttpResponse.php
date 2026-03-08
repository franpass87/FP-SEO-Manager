<?php
/**
 * HTTP response class.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Core\Services\Http;

/**
 * HTTP response object.
 */
class HttpResponse {

	/**
	 * Response status code.
	 *
	 * @var int
	 */
	private int $status_code;

	/**
	 * Response body.
	 *
	 * @var string
	 */
	private string $body;

	/**
	 * Response headers.
	 *
	 * @var array<string, string>
	 */
	private array $headers;

	/**
	 * Constructor.
	 *
	 * @param int    $status_code HTTP status code.
	 * @param string $body        Response body.
	 * @param array<string, string> $headers Response headers.
	 */
	public function __construct( int $status_code, string $body = '', array $headers = array() ) {
		$this->status_code = $status_code;
		$this->body        = $body;
		$this->headers     = $headers;
	}

	/**
	 * Get the status code.
	 *
	 * @return int
	 */
	public function get_status_code(): int {
		return $this->status_code;
	}

	/**
	 * Get the response body.
	 *
	 * @return string
	 */
	public function get_body(): string {
		return $this->body;
	}

	/**
	 * Get the response body as JSON-decoded array.
	 *
	 * @return array<string, mixed>
	 */
	public function get_json(): array {
		$decoded = json_decode( $this->body, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Get response headers.
	 *
	 * @return array<string, string>
	 */
	public function get_headers(): array {
		return $this->headers;
	}

	/**
	 * Get a specific header value.
	 *
	 * @param string $name Header name.
	 * @return string|null Header value or null if not found.
	 */
	public function get_header( string $name ): ?string {
		return $this->headers[ $name ] ?? null;
	}

	/**
	 * Check if the request was successful.
	 *
	 * @return bool True if status code is 2xx.
	 */
	public function is_success(): bool {
		return $this->status_code >= 200 && $this->status_code < 300;
	}

	/**
	 * Check if the request resulted in an error.
	 *
	 * @return bool True if status code is 4xx or 5xx.
	 */
	public function is_error(): bool {
		return $this->status_code >= 400;
	}
}



