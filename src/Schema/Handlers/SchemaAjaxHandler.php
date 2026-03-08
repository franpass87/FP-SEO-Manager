<?php
/**
 * Handles AJAX requests for Schema generation and preview.
 *
 * @package FP\SEO\Schema\Handlers
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Schema\Handlers;

use FP\SEO\Schema\AdvancedSchemaManager;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use function array_key_exists;
use function check_ajax_referer;
use function current_user_can;
use function json_decode;
use function json_last_error;
use function sanitize_text_field;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_unslash;

/**
 * Handles AJAX requests for Schema operations.
 */
class SchemaAjaxHandler {
	/**
	 * @var AdvancedSchemaManager
	 */
	private $manager;

	/**
	 * Hook manager instance.
	 *
	 * @var HookManagerInterface
	 */
	private HookManagerInterface $hook_manager;

	/**
	 * Constructor.
	 *
	 * @param AdvancedSchemaManager $manager Schema manager instance.
	 * @param HookManagerInterface  $hook_manager Hook manager instance.
	 */
	public function __construct( AdvancedSchemaManager $manager, HookManagerInterface $hook_manager ) {
		$this->manager = $manager;
		$this->hook_manager = $hook_manager;
	}

	/**
	 * Register AJAX hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->hook_manager->add_action( 'wp_ajax_fp_seo_generate_schema', array( $this, 'handle_generate_schema' ) );
		$this->hook_manager->add_action( 'wp_ajax_fp_seo_preview_schema', array( $this, 'handle_preview_schema' ) );
	}

	/**
	 * AJAX handler for schema generation.
	 *
	 * @return void
	 */
	public function handle_generate_schema(): void {
		check_ajax_referer( 'fp_seo_schema_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permessi insufficienti per generare lo schema.', 'fp-seo-performance' ) ),
				403
			);
			return;
		}

		$schema_type = sanitize_text_field( $_POST['schema_type'] ?? '' );
		$schema_data = wp_unslash( $_POST['schema_data'] ?? '' );

		$schema_types = AdvancedSchemaManager::get_schema_types();

		if ( empty( $schema_type ) || ! array_key_exists( $schema_type, $schema_types ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Tipo di schema non valido.', 'fp-seo-performance' ) ),
				400
			);
			return;
		}

		if ( '' === trim( $schema_data ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Inserisci i dati dello schema prima di procedere.', 'fp-seo-performance' ) ),
				400
			);
			return;
		}

		$data = json_decode( $schema_data, true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Il JSON fornito non è valido.', 'fp-seo-performance' ) ),
				400
			);
			return;
		}

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => $schema_type,
		);

		$schema = array_merge( $schema, $data );

		wp_send_json_success( $schema );
		return;
	}

	/**
	 * AJAX handler for schema preview.
	 *
	 * @return void
	 */
	public function handle_preview_schema(): void {
		check_ajax_referer( 'fp_seo_schema_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permessi insufficienti per visualizzare l\'anteprima dello schema.', 'fp-seo-performance' ) ),
				403
			);
			return;
		}

		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		if ( ! $post_id ) {
			wp_send_json_error(
				array( 'message' => __( 'ID contenuto non valido.', 'fp-seo-performance' ) ),
				400
			);
			return;
		}

		$schemas = $this->manager->get_active_schemas_public( $post_id );
		wp_send_json_success( $schemas );
		return;
	}
}
















