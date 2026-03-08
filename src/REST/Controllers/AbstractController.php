<?php
/**
 * Abstract REST controller base class.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\REST\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Abstract base class for REST controllers.
 */
abstract class AbstractController {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	protected string $namespace = 'fp-seo/v1';

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	abstract public function register_routes(): void;

	/**
	 * Check if user has permission to access the endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if allowed, WP_Error otherwise.
	 */
	public function check_permission( WP_REST_Request $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Create a success response.
	 *
	 * @param mixed $data Response data.
	 * @param int   $status HTTP status code.
	 * @return WP_REST_Response
	 */
	protected function success_response( $data, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response( $data, $status );
	}

	/**
	 * Create an error response.
	 *
	 * @param string $message Error message.
	 * @param string $code    Error code.
	 * @param int    $status  HTTP status code.
	 * @return WP_Error
	 */
	protected function error_response( string $message, string $code = 'error', int $status = 400 ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}
}



