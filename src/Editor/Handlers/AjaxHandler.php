<?php
/**
 * Handles all AJAX requests for the SEO metabox.
 *
 * @package FP\SEO\Editor\Handlers
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Handlers;

use FP\SEO\Editor\Metabox;
use FP\SEO\Utils\Logger;
use WP_Post;
use function absint;
use function check_ajax_referer;
use function current_user_can;
use function delete_post_meta;
use function get_post;
use function get_post_type;
use function in_array;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function trim;
use function update_post_meta;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_unslash;
use function wp_update_post;
use function wp_verify_nonce;

/**
 * Handles AJAX requests for the metabox.
 */
class AjaxHandler {
	/**
	 * @var Metabox
	 */
	private $metabox;

	/**
	 * Constructor.
	 *
	 * @param Metabox $metabox Metabox instance.
	 */
	public function __construct( Metabox $metabox ) {
		$this->metabox = $metabox;
	}

	/**
	 * Register AJAX handlers.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_ajax_fp_seo_performance_analyze', array( $this, 'handle_analyze' ) );
		add_action( 'wp_ajax_fp_seo_performance_save_fields', array( $this, 'handle_save_fields' ) );
	}

	/**
	 * Handle analyze AJAX request.
	 *
	 * @return void
	 */
	public function handle_analyze(): void {
		check_ajax_referer( 'fp_seo_performance_analyze', 'nonce' );

		// Support both postId (from JS) and post_id (standard)
		$post_id = isset( $_POST['postId'] ) ? absint( $_POST['postId'] ) : ( isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0 );

		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to edit this post.', 'fp-seo-performance' ) ), 403 );
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			wp_send_json_error( array( 'message' => __( 'Post not found.', 'fp-seo-performance' ) ), 404 );
		}

		// Run analysis - delegate to metabox
		$payload = $this->metabox->run_analysis_for_post( $post );

		wp_send_json_success( $payload );
	}

	/**
	 * Handle save fields AJAX request.
	 *
	 * @return void
	 */
	public function handle_save_fields(): void {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fp_seo_performance_save_fields' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fp-seo-performance' ) ), 403 );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : ( isset( $_POST['postId'] ) ? absint( $_POST['postId'] ) : 0 );

		if ( $post_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID.', 'fp-seo-performance' ) ), 400 );
		}

		// CRITICAL: Check post type FIRST, before any processing
		$post_type = get_post_type( $post_id );
		$supported_types = $this->metabox->get_supported_post_types();

		// If not a supported post type, return error immediately
		if ( ! in_array( $post_type, $supported_types, true ) ) {
			wp_send_json_error( array(
				'message' => __( 'This post type is not supported for SEO optimization.', 'fp-seo-performance' ),
				'post_type' => $post_type,
			), 400 );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to edit this post.', 'fp-seo-performance' ) ), 403 );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'handle_save_fields_ajax called', array(
				'post_id' => $post_id,
				'ajax_post_keys' => array_keys( $_POST ),
			) );
		}

		// Get and sanitize values - supporta sia i nomi vecchi che quelli nuovi
		$seo_title = '';
		if ( isset( $_POST['fp_seo_title'] ) ) {
			$seo_title = sanitize_text_field( wp_unslash( (string) $_POST['fp_seo_title'] ) );
			$seo_title = trim( $seo_title );
		} elseif ( isset( $_POST['seo_title'] ) ) {
			$seo_title = sanitize_text_field( wp_unslash( (string) $_POST['seo_title'] ) );
			$seo_title = trim( $seo_title );
		}

		$meta_description = '';
		if ( isset( $_POST['fp_seo_meta_description'] ) ) {
			$meta_description = sanitize_textarea_field( wp_unslash( (string) $_POST['fp_seo_meta_description'] ) );
			$meta_description = trim( $meta_description );
		} elseif ( isset( $_POST['meta_description'] ) ) {
			$meta_description = sanitize_textarea_field( wp_unslash( (string) $_POST['meta_description'] ) );
			$meta_description = trim( $meta_description );
		}

		$focus_keyword = '';
		if ( isset( $_POST['fp_seo_focus_keyword'] ) ) {
			$focus_keyword = sanitize_text_field( wp_unslash( (string) $_POST['fp_seo_focus_keyword'] ) );
			$focus_keyword = trim( $focus_keyword );
		}

		$secondary_keywords = '';
		if ( isset( $_POST['fp_seo_secondary_keywords'] ) ) {
			$secondary_keywords = sanitize_text_field( wp_unslash( (string) $_POST['fp_seo_secondary_keywords'] ) );
			$secondary_keywords = trim( $secondary_keywords );
		}

		$excerpt = '';
		if ( isset( $_POST['fp_seo_excerpt'] ) ) {
			$excerpt = sanitize_textarea_field( wp_unslash( (string) $_POST['fp_seo_excerpt'] ) );
			$excerpt = trim( $excerpt );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'AJAX save values', array(
				'post_id' => $post_id,
				'has_title' => ! empty( $seo_title ),
				'has_description' => ! empty( $meta_description ),
				'has_focus_keyword' => ! empty( $focus_keyword ),
				'has_excerpt' => ! empty( $excerpt ),
			) );
		}

		// Salva direttamente i campi senza usare MetaboxSaver per evitare conflitti
		// Questo è più sicuro in contesto AJAX
		try {
			// Salva Title
			if ( '' !== $seo_title ) {
				update_post_meta( $post_id, '_fp_seo_title', $seo_title );
			} else {
				delete_post_meta( $post_id, '_fp_seo_title' );
			}

			// Salva Meta Description
			if ( '' !== $meta_description ) {
				update_post_meta( $post_id, '_fp_seo_meta_description', $meta_description );
			} else {
				delete_post_meta( $post_id, '_fp_seo_meta_description' );
			}

			// Salva Focus Keyword
			if ( '' !== $focus_keyword ) {
				update_post_meta( $post_id, Metabox::META_FOCUS_KEYWORD, $focus_keyword );
			} else {
				delete_post_meta( $post_id, Metabox::META_FOCUS_KEYWORD );
			}

			// Salva Secondary Keywords
			if ( '' !== $secondary_keywords ) {
				update_post_meta( $post_id, Metabox::META_SECONDARY_KEYWORDS, $secondary_keywords );
			} else {
				delete_post_meta( $post_id, Metabox::META_SECONDARY_KEYWORDS );
			}

			// Salva Excerpt (post_excerpt è un campo del post, non meta)
			if ( '' !== $excerpt ) {
				wp_update_post( array(
					'ID' => $post_id,
					'post_excerpt' => $excerpt,
				) );
			}

			wp_send_json_success( array(
				'message' => __( 'Fields saved successfully.', 'fp-seo-performance' ),
				'post_id' => $post_id,
			) );
		} catch ( \Throwable $e ) {
			Logger::error( 'FP SEO: Error saving fields via AJAX', array(
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
				'post_id' => $post_id,
			) );

			wp_send_json_error( array(
				'message' => __( 'Error saving fields. Please try again.', 'fp-seo-performance' ),
			), 500 );
		}
	}
}

