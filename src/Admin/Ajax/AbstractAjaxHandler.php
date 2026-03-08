<?php
/**
 * Abstract AJAX handler base class.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Ajax;

use FP\SEO\Admin\Contracts\AjaxHandlerInterface;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use function check_ajax_referer;
use function current_user_can;
use function wp_send_json_error;
use function wp_send_json_success;

/**
 * Abstract base class for AJAX handlers.
 *
 * Provides common functionality for all AJAX handlers.
 */
abstract class AbstractAjaxHandler implements AjaxHandlerInterface {

	/**
	 * Hook manager instance.
	 *
	 * @var HookManagerInterface
	 */
	protected HookManagerInterface $hook_manager;

	/**
	 * Constructor.
	 *
	 * @param HookManagerInterface $hook_manager Hook manager instance.
	 */
	public function __construct( HookManagerInterface $hook_manager ) {
		$this->hook_manager = $hook_manager;
	}

	/**
	 * Register AJAX actions.
	 *
	 * Registers the AJAX action with WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		$action = $this->get_action();
		if ( empty( $action ) ) {
			return;
		}

		$this->hook_manager->add_action( 'wp_ajax_' . $action, array( $this, 'handle_request' ), 10, 0 );
	}

	/**
	 * Handle the AJAX request (WordPress hook callback).
	 *
	 * Validates nonce and capability, then calls handle().
	 *
	 * @return void
	 */
	public function handle_request(): void {
		// Verify nonce
		$nonce_action = $this->get_nonce_action();
		if ( ! empty( $nonce_action ) ) {
			check_ajax_referer( $nonce_action, 'nonce' );
		}

		// Check capability
		$capability = $this->get_capability();
		if ( ! empty( $capability ) && ! current_user_can( $capability ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
			return;
		}

		// Get request data
		$data = $this->get_request_data();

		// Handle the request
		try {
			$response = $this->handle( $data );
			wp_send_json_success( $response );
			return;
		} catch ( \Throwable $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
					'code'    => $e->getCode(),
				)
			);
			return;
		}
	}

	/**
	 * Get request data from $_POST or $_GET.
	 *
	 * NOTE: This method returns raw request data. All data must be sanitized
	 * in the calling methods before use. Nonce verification should also be
	 * performed in the calling methods using validate_nonce().
	 *
	 * @return array<string, mixed> Request data (unsanitized - must be sanitized by caller).
	 */
	protected function get_request_data(): array {
		// Default: get from $_POST, fallback to $_GET
		// IMPORTANT: Sanitization and nonce verification must be done in calling methods
		return ! empty( $_POST ) ? $_POST : $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification done in calling methods.
	}

	/**
	 * Get the nonce action name.
	 *
	 * Default implementation returns empty string (no nonce required).
	 * Subclasses should override.
	 *
	 * @return string Nonce action name.
	 */
	public function get_nonce_action(): string {
		return '';
	}

	/**
	 * Get the capability required to use this handler.
	 *
	 * Default implementation returns 'manage_options'.
	 * Subclasses should override.
	 *
	 * @return string Capability name.
	 */
	public function get_capability(): string {
		return 'manage_options';
	}
}



