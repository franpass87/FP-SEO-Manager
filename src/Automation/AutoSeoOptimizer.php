<?php
/**
 * Automatic SEO Optimization with AI
 * Automatically generates missing SEO fields (title, description, keywords) using AI
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Automation;

use FP\SEO\Utils\Logger;

use FP\SEO\Integrations\OpenAiClient;
use FP\SEO\Utils\Options;

/**
 * Handles automatic SEO optimization for posts and pages.
 */
class AutoSeoOptimizer {

	/**
	 * OpenAI client instance.
	 *
	 * @var OpenAiClient
	 */
	private OpenAiClient $ai_client;

	/**
	 * Meta keys for SEO fields.
	 */
	private const META_FOCUS_KEYWORD = '_fp_seo_focus_keyword';
	private const META_DESCRIPTION   = '_fp_seo_meta_description';

	/**
	 * Constructor.
	 *
	 * @param OpenAiClient $ai_client OpenAI client instance.
	 */
	public function __construct( OpenAiClient $ai_client ) {
		$this->ai_client = $ai_client;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		// Hook to post publish/update
		add_action( 'save_post', array( $this, 'maybe_auto_optimize' ), 20, 3 );
		
		// Admin notice for auto-optimization
		add_action( 'admin_notices', array( $this, 'show_optimization_notice' ) );
		
		// Hook for scheduled transient cleanup
		add_action( 'fp_seo_clear_optimization_flag', array( $this, 'clear_optimization_flag' ) );
	}

	/**
	 * Maybe auto-optimize post SEO fields if they are empty.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an update or new post.
	 */
	public function maybe_auto_optimize( int $post_id, \WP_Post $post, bool $update ): void {
		// Check if auto-optimization is enabled
		if ( ! $this->is_auto_optimization_enabled() ) {
			return;
		}

		// Check if AI is configured
		if ( ! $this->ai_client->is_configured() ) {
			return;
		}

		// Security checks
		if ( ! $this->should_auto_optimize( $post_id, $post ) ) {
			return;
		}

		// Check if fields are empty
		$needs_optimization = $this->check_missing_fields( $post_id );

		if ( empty( $needs_optimization ) ) {
			// All fields are already set
			return;
		}

		// Perform AI optimization
		$this->perform_auto_optimization( $post_id, $post, $needs_optimization );
	}

	/**
	 * Check if auto-optimization should run for this post.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return bool
	 */
	private function should_auto_optimize( int $post_id, \WP_Post $post ): bool {
		// Skip autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		// Skip revisions
		if ( wp_is_post_revision( $post_id ) ) {
			return false;
		}

		// Skip auto-drafts
		if ( 'auto-draft' === $post->post_status ) {
			return false;
		}

		// Only for published/scheduled posts
		if ( ! in_array( $post->post_status, array( 'publish', 'future' ), true ) ) {
			return false;
		}

		// Only for specific post types
		$allowed_post_types = $this->get_allowed_post_types();
		if ( ! in_array( $post->post_type, $allowed_post_types, true ) ) {
			return false;
		}

		// Check if user has permission
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}

		// Check if already optimized in this session (prevent loops)
		$optimized_flag = get_transient( 'fp_seo_auto_optimized_' . $post_id );
		if ( false !== $optimized_flag ) {
			return false;
		}

		return true;
	}

	/**
	 * Check which SEO fields are missing.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string> Array of missing field keys.
	 */
	private function check_missing_fields( int $post_id ): array {
		$missing = array();

		$focus_keyword = get_post_meta( $post_id, self::META_FOCUS_KEYWORD, true );
		$description   = get_post_meta( $post_id, self::META_DESCRIPTION, true );

		// Check what's missing based on user settings
		$auto_fields = $this->get_auto_optimize_fields();

		if ( in_array( 'focus_keyword', $auto_fields, true ) && empty( $focus_keyword ) ) {
			$missing[] = 'focus_keyword';
		}

		if ( in_array( 'meta_description', $auto_fields, true ) && empty( $description ) ) {
			$missing[] = 'meta_description';
		}

		return $missing;
	}

	/**
	 * Perform automatic optimization using AI.
	 *
	 * @param int      $post_id           Post ID.
	 * @param \WP_Post $post              Post object.
	 * @param array<string> $missing_fields Missing field keys.
	 */
	private function perform_auto_optimization( int $post_id, \WP_Post $post, array $missing_fields ): void {
		// Set flag to prevent loops IMMEDIATELY
		set_transient( 'fp_seo_auto_optimized_' . $post_id, true, HOUR_IN_SECONDS );

		// Gather post data
		$title   = $post->post_title;
		$content = $post->post_content;
		
		// Get existing focus keyword (if any) to guide AI
		$existing_keyword = get_post_meta( $post_id, self::META_FOCUS_KEYWORD, true );

		// Generate AI suggestions
		$result = $this->ai_client->generate_seo_suggestions(
			$post_id,
			$content,
			$title,
			$existing_keyword
		);

		// Check if AI generation was successful
		if ( ! $result['success'] || empty( $result['data'] ) ) {
			// Log error for debugging
			Logger::error( 'Auto-Optimizer failed to generate suggestions', array(
				'post_id' => $post_id,
				'error' => $result['error'] ?? 'Unknown error',
			) );
			
			// Store error in transient for admin notice
			set_transient( 'fp_seo_auto_optimize_error_' . $post_id, $result['error'] ?? 'Unknown error', DAY_IN_SECONDS );
			return;
		}

		$ai_data = $result['data'];

		// Save generated fields (only the missing ones)
		$updated_fields = array();

		if ( in_array( 'focus_keyword', $missing_fields, true ) && ! empty( $ai_data['focus_keyword'] ) ) {
			update_post_meta( $post_id, self::META_FOCUS_KEYWORD, sanitize_text_field( $ai_data['focus_keyword'] ) );
			$updated_fields[] = 'Focus Keyword';
		}

		if ( in_array( 'meta_description', $missing_fields, true ) && ! empty( $ai_data['meta_description'] ) ) {
			update_post_meta( $post_id, self::META_DESCRIPTION, sanitize_textarea_field( $ai_data['meta_description'] ) );
			$updated_fields[] = 'Meta Description';
		}

		// Update post title if it's auto-generated and we have an SEO title suggestion
		// (Only for new posts with default title)
		// IMPORTANT: Remove and re-add hook to prevent infinite loop
		if ( ! empty( $ai_data['seo_title'] ) && $post->post_title === 'Auto Draft' ) {
			// Remove our hook temporarily
			remove_action( 'save_post', array( $this, 'maybe_auto_optimize' ), 20 );
			
			wp_update_post( array(
				'ID'         => $post_id,
				'post_title' => sanitize_text_field( $ai_data['seo_title'] ),
			) );
			
			// Re-add our hook
			add_action( 'save_post', array( $this, 'maybe_auto_optimize' ), 20, 3 );
			
			$updated_fields[] = 'Post Title';
		}

		// Update slug if it's auto-generated (only for new posts)
		// IMPORTANT: Remove and re-add hook to prevent infinite loop
		if ( $post->post_name === sanitize_title( $post->post_title ) && ! empty( $ai_data['slug'] ) ) {
			// Remove our hook temporarily
			remove_action( 'save_post', array( $this, 'maybe_auto_optimize' ), 20 );
			
			wp_update_post( array(
				'ID'        => $post_id,
				'post_name' => sanitize_title( $ai_data['slug'] ),
			) );
			
			// Re-add our hook
			add_action( 'save_post', array( $this, 'maybe_auto_optimize' ), 20, 3 );
			
			$updated_fields[] = 'URL Slug';
		}

		// Store success message for admin notice
		if ( ! empty( $updated_fields ) ) {
			set_transient(
				'fp_seo_auto_optimize_success_' . $post_id,
				sprintf(
					/* translators: %s: comma-separated list of updated fields */
					__( 'Auto-Ottimizzazione SEO completata! Campi generati con AI: %s', 'fp-seo-performance' ),
					implode( ', ', $updated_fields )
				),
				DAY_IN_SECONDS
			);

			// Log success
			Logger::info( 'Auto-Optimizer successfully optimized post', array(
				'post_id' => $post_id,
				'updated_fields' => $updated_fields,
			) );
		}

		// Clear the optimization flag after a short delay
		// This allows the user to see the changes
		wp_schedule_single_event( time() + 300, 'fp_seo_clear_optimization_flag', array( $post_id ) );
	}

	/**
	 * Clear the optimization flag for a post.
	 *
	 * This is called via scheduled event to cleanup the transient.
	 *
	 * @param int $post_id Post ID.
	 */
	public function clear_optimization_flag( int $post_id ): void {
		delete_transient( 'fp_seo_auto_optimized_' . $post_id );
	}

	/**
	 * Show admin notice about auto-optimization.
	 */
	public function show_optimization_notice(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->base, array( 'post', 'page' ), true ) ) {
			return;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		// Check for success message
		$success = get_transient( 'fp_seo_auto_optimize_success_' . $post_id );
		if ( false !== $success ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p><strong>🤖 %s</strong></p></div>',
				esc_html( $success )
			);
			delete_transient( 'fp_seo_auto_optimize_success_' . $post_id );
		}

		// Check for error message
		$error = get_transient( 'fp_seo_auto_optimize_error_' . $post_id );
		if ( false !== $error ) {
			printf(
				'<div class="notice notice-warning is-dismissible"><p><strong>⚠️ Auto-Ottimizzazione SEO:</strong> %s</p></div>',
				esc_html( $error )
			);
			delete_transient( 'fp_seo_auto_optimize_error_' . $post_id );
		}
	}

	/**
	 * Check if auto-optimization is enabled in settings.
	 *
	 * @return bool
	 */
	private function is_auto_optimization_enabled(): bool {
		return (bool) Options::get_option( 'automation.auto_seo_optimization', false );
	}

	/**
	 * Get which fields should be auto-optimized.
	 *
	 * @return array<string>
	 */
	private function get_auto_optimize_fields(): array {
		$fields = Options::get_option( 'automation.auto_optimize_fields', array() );
		
		// Default to all fields if not set
		if ( empty( $fields ) || ! is_array( $fields ) ) {
			return array( 'focus_keyword', 'meta_description' );
		}

		return $fields;
	}

	/**
	 * Get allowed post types for auto-optimization.
	 *
	 * @return array<string>
	 */
	private function get_allowed_post_types(): array {
		$post_types = Options::get_option( 'automation.auto_optimize_post_types', array() );
		
		// Default to post and page if not set
		if ( empty( $post_types ) || ! is_array( $post_types ) ) {
			return array( 'post', 'page' );
		}

		return $post_types;
	}
}

