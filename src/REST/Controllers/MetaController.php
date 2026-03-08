<?php
/**
 * REST controller for meta fields.
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
use FP\SEO\Data\Contracts\PostMetaRepositoryInterface;
use FP\SEO\Core\Services\Validation\ValidationServiceInterface;
use FP\SEO\Core\Services\Sanitization\SanitizationServiceInterface;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use FP\SEO\Utils\PostTypes;

/**
 * REST controller for SEO meta fields.
 */
class MetaController extends AbstractController {

	/**
	 * Post meta repository.
	 *
	 * @var PostMetaRepositoryInterface
	 */
	private PostMetaRepositoryInterface $meta_repository;

	/**
	 * Validation service.
	 *
	 * @var ValidationServiceInterface
	 */
	private ValidationServiceInterface $validator;

	/**
	 * Sanitization service.
	 *
	 * @var SanitizationServiceInterface
	 */
	private SanitizationServiceInterface $sanitizer;

	/**
	 * Hook manager.
	 *
	 * @var HookManagerInterface
	 */
	private HookManagerInterface $hook_manager;

	/**
	 * Constructor.
	 *
	 * @param PostMetaRepositoryInterface  $meta_repository Post meta repository.
	 * @param ValidationServiceInterface   $validator        Validation service.
	 * @param SanitizationServiceInterface $sanitizer        Sanitization service.
	 * @param HookManagerInterface         $hook_manager     Hook manager.
	 */
	public function __construct(
		PostMetaRepositoryInterface $meta_repository,
		ValidationServiceInterface $validator,
		SanitizationServiceInterface $sanitizer,
		HookManagerInterface $hook_manager
	) {
		$this->meta_repository = $meta_repository;
		$this->validator       = $validator;
		$this->sanitizer       = $sanitizer;
		$this->hook_manager    = $hook_manager;
	}

	/**
	 * Register REST routes and meta fields.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Register REST meta fields for Gutenberg support
		$this->register_rest_meta_fields();
		register_rest_route(
			$this->namespace,
			'/meta/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_meta' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'required' => true,
						'type'     => 'integer',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/meta/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_meta' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id'   => array(
						'required' => true,
						'type'     => 'integer',
					),
					'key'  => array(
						'required' => true,
						'type'     => 'string',
					),
					'value' => array(
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Get meta value for a post.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_meta( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'id' );
		$key     = $request->get_param( 'key' );

		if ( empty( $key ) ) {
			return $this->error_response( 'Meta key is required', 'missing_key', 400 );
		}

		$value = $this->meta_repository->get( $post_id, $key, true );

		return $this->success_response(
			array(
				'post_id' => $post_id,
				'key'     => $key,
				'value'   => $value,
			)
		);
	}

	/**
	 * Update meta value for a post.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_meta( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'id' );
		$key     = $request->get_param( 'key' );
		$value   = $request->get_param( 'value' );

		// Validate
		if ( empty( $key ) ) {
			return $this->error_response( 'Meta key is required', 'missing_key', 400 );
		}

		// Sanitize
		$sanitized_value = $this->sanitizer->sanitize( $value, 'text' );

		// Update
		$result = $this->meta_repository->update( $post_id, $key, $sanitized_value );

		if ( ! $result ) {
			return $this->error_response( 'Failed to update meta', 'update_failed', 500 );
		}

		return $this->success_response(
			array(
				'post_id' => $post_id,
				'key'     => $key,
				'value'   => $sanitized_value,
			)
		);
	}

	/**
	 * Register REST meta fields for supported post types (Gutenberg support).
	 *
	 * @return void
	 */
	private function register_rest_meta_fields(): void {
		$post_types = PostTypes::analyzable();

		foreach ( $post_types as $post_type ) {
			// Register fp_seo_title field (maps to _fp_seo_title meta)
			register_rest_field(
				$post_type,
				'fp_seo_title',
				array(
					'get_callback' => function( $post ) {
						return get_post_meta( $post['id'], '_fp_seo_title', true );
					},
					'update_callback' => function( $value, $post ) {
						if ( $value !== null ) {
							update_post_meta( $post->ID, '_fp_seo_title', $this->sanitizer->sanitize( $value, 'text' ) );
						} else {
							delete_post_meta( $post->ID, '_fp_seo_title' );
						}
						return true;
					},
					'schema' => array(
						'description' => __( 'SEO Title', 'fp-seo-performance' ),
						'type'        => 'string',
						'context'     => array( 'edit' ),
					),
				)
			);

			// Register fp_seo_meta_description field (maps to _fp_seo_meta_description meta)
			register_rest_field(
				$post_type,
				'fp_seo_meta_description',
				array(
					'get_callback' => function( $post ) {
						return get_post_meta( $post['id'], '_fp_seo_meta_description', true );
					},
					'update_callback' => function( $value, $post ) {
						if ( $value !== null ) {
							update_post_meta( $post->ID, '_fp_seo_meta_description', $this->sanitizer->sanitize( $value, 'textarea' ) );
						} else {
							delete_post_meta( $post->ID, '_fp_seo_meta_description' );
						}
						return true;
					},
					'schema' => array(
						'description' => __( 'SEO Meta Description', 'fp-seo-performance' ),
						'type'       => 'string',
						'context'    => array( 'edit' ),
					),
				)
			);
		}
	}
}

