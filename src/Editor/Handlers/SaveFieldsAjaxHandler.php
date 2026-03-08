<?php
/**
 * AJAX handler for saving SEO fields.
 *
 * @package FP\SEO\Editor\Handlers
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Handlers;

use FP\SEO\Editor\Services\MetaboxValidator;
use FP\SEO\Editor\Services\SeoFieldsSaver;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use FP\SEO\Utils\Options;

/**
 * Handles AJAX requests for saving SEO fields.
 */
class SaveFieldsAjaxHandler extends AbstractAjaxHandler {
	/**
	 * Fields saver service.
	 *
	 * @var SeoFieldsSaver
	 */
	private SeoFieldsSaver $fields_saver;

	/**
	 * Validator service.
	 *
	 * @var MetaboxValidator
	 */
	private MetaboxValidator $validator;

	/**
	 * Supported post types.
	 *
	 * @var array<string>
	 */
	private array $supported_post_types;

	/**
	 * AJAX action name.
	 *
	 * @var string
	 */
	public const AJAX_ACTION = 'fp_seo_performance_save_fields';

	/**
	 * Constructor.
	 *
	 * @param HookManagerInterface $hook_manager Hook manager instance.
	 * @param SeoFieldsSaver      $fields_saver Fields saver service.
	 * @param MetaboxValidator    $validator Validator service.
	 * @param array<string>       $supported_post_types Supported post types.
	 */
	public function __construct(
		HookManagerInterface $hook_manager,
		SeoFieldsSaver $fields_saver,
		MetaboxValidator $validator,
		array $supported_post_types
	) {
		parent::__construct( $hook_manager );
		$this->fields_saver         = $fields_saver;
		$this->validator            = $validator;
		$this->supported_post_types = $supported_post_types;
	}

	/**
	 * Register AJAX actions.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->hook_manager->add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_ajax' ) );
	}

	/**
	 * Handle save fields AJAX request.
	 *
	 * @return void
	 */
	public function handle_ajax(): void {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		$post_id = $this->get_post_id_from_request( 'post_id' );

		if ( $post_id <= 0 ) {
			$this->send_error( __( 'Invalid post ID.', 'fp-seo-performance' ), 400 );
			return;
		}

		// Use validator for all validation
		$validation = $this->validator->validate_ajax_request( $post_id, $nonce, self::AJAX_ACTION );

		if ( ! $validation['valid'] ) {
			$status_code = isset( $validation['post_type'] ) ? 400 : 403;
			$this->send_error(
				$validation['error'] ?? __( 'Validation failed.', 'fp-seo-performance' ),
				$status_code
			);
			return;
		}

		$this->log_debug( 'Saving fields via AJAX', array( 'post_id' => $post_id ) );

		// Use dedicated service for saving fields
		try {
			$saved = $this->fields_saver->save_from_post( $post_id );
			$result = ! empty( $saved );

			$this->log_debug( 'Fields saved successfully', array(
				'post_id'      => $post_id,
				'saved_fields' => array_keys( $saved ),
			) );

			$this->send_success( array(
				'message' => __( 'Fields saved successfully.', 'fp-seo-performance' ),
				'saved'   => $result,
			) );
		} catch ( \Throwable $e ) {
			$this->log_error( 'Error saving fields', array(
				'post_id' => $post_id,
				'error'   => $e->getMessage(),
				'trace'   => $e->getTraceAsString(),
			) );

			$this->send_error( __( 'Error saving fields. Please try again.', 'fp-seo-performance' ), 500 );
		}
	}
}


