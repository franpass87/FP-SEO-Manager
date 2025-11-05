<?php
/**
 * Auto-Generation Hook for AI-First Features
 *
 * Automatically generates Q&A pairs and optimizes images when posts are published.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Integrations;

use FP\SEO\AI\QAPairExtractor;
use FP\SEO\GEO\MultiModalOptimizer;
use FP\SEO\Utils\Options;

/**
 * Handles automatic generation of AI-first data on post publish
 */
class AutoGenerationHook {

	/**
	 * Q&A extractor instance
	 *
	 * @var QAPairExtractor
	 */
	private QAPairExtractor $qa_extractor;

	/**
	 * Image optimizer instance
	 *
	 * @var MultiModalOptimizer
	 */
	private MultiModalOptimizer $image_optimizer;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->qa_extractor     = new QAPairExtractor();
		$this->image_optimizer  = new MultiModalOptimizer();
	}

	/**
	 * Register hooks
	 */
	public function register(): void {
		// Hook on post publish (new posts)
		add_action( 'publish_post', array( $this, 'on_publish' ), 10, 2 );
		add_action( 'publish_page', array( $this, 'on_publish' ), 10, 2 );

		// Hook on post update (existing posts)
		add_action( 'save_post', array( $this, 'on_update' ), 20, 3 );
	}

	/**
	 * Handle post publish event
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function on_publish( int $post_id, \WP_Post $post ): void {
		// Check if auto-generation is enabled
		if ( ! $this->is_auto_generation_enabled() ) {
			return;
		}

		// Avoid infinite loops
		if ( $this->is_generating( $post_id ) ) {
			return;
		}

		// Skip autosaves and revisions
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Mark as generating
		$this->set_generating( $post_id, true );

		try {
			$this->generate_ai_data( $post_id, $post );
		} catch ( \Exception $e ) {
			error_log( 'FP SEO Auto-Generation Error: ' . $e->getMessage() );
		} finally {
			// Unmark
			$this->set_generating( $post_id, false );
		}
	}

	/**
	 * Handle post update event
	 *
	 * @param int      $post_id     Post ID.
	 * @param \WP_Post $post        Post object.
	 * @param bool     $update      Whether this is an existing post being updated.
	 */
	public function on_update( int $post_id, \WP_Post $post, bool $update ): void {
		// Only for updates (not new posts - those are handled by on_publish)
		if ( ! $update ) {
			return;
		}

		// Only for published posts
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		// Check if auto-generation is enabled
		if ( ! $this->is_auto_generation_enabled() ) {
			return;
		}

		// Check if content actually changed (avoid regeneration on minor edits)
		if ( ! $this->has_content_changed( $post_id ) ) {
			return;
		}

		// Skip autosaves and revisions
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Avoid infinite loops
		if ( $this->is_generating( $post_id ) ) {
			return;
		}

		// Mark as generating
		$this->set_generating( $post_id, true );

		try {
			// On update, regenerate only Q&A (images likely unchanged)
			$this->regenerate_qa_only( $post_id, $post );
		} catch ( \Exception $e ) {
			error_log( 'FP SEO Auto-Regeneration Error: ' . $e->getMessage() );
		} finally {
			// Unmark
			$this->set_generating( $post_id, false );
		}
	}

	/**
	 * Check if auto-generation is enabled in settings
	 *
	 * @return bool True if enabled.
	 */
	private function is_auto_generation_enabled(): bool {
		$options = Options::get();
		return ! empty( $options['ai_first']['auto_generate_on_publish'] );
	}

	/**
	 * Check if currently generating (to avoid infinite loops)
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if generating.
	 */
	private function is_generating( int $post_id ): bool {
		return (bool) get_transient( 'fp_seo_generating_' . $post_id );
	}

	/**
	 * Set generating flag
	 *
	 * @param int  $post_id    Post ID.
	 * @param bool $generating Whether generating.
	 */
	private function set_generating( int $post_id, bool $generating ): void {
		if ( $generating ) {
			set_transient( 'fp_seo_generating_' . $post_id, 1, 300 ); // 5 minutes max
		} else {
			delete_transient( 'fp_seo_generating_' . $post_id );
		}
	}

	/**
	 * Check if content has actually changed
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if content changed.
	 */
	private function has_content_changed( int $post_id ): bool {
		// Get previous content hash
		$previous_hash = get_post_meta( $post_id, '_fp_seo_content_hash', true );

		// Calculate current content hash
		$post          = get_post( $post_id );
		$current_hash  = md5( $post->post_content . $post->post_title );

		// Update hash
		update_post_meta( $post_id, '_fp_seo_content_hash', $current_hash );

		// If no previous hash, consider it changed
		if ( empty( $previous_hash ) ) {
			return true;
		}

		// Compare hashes
		return $previous_hash !== $current_hash;
	}

	/**
	 * Generate all AI data for a post
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	private function generate_ai_data( int $post_id, \WP_Post $post ): void {
		$options = Options::get();

		// 1. Generate Q&A pairs (if enabled)
		if ( ! empty( $options['ai_first']['enable_qa'] ) ) {
			try {
				$this->qa_extractor->extract_qa_pairs( $post_id, true );
				error_log( sprintf( 'FP SEO: Auto-generated Q&A for post %d', $post_id ) );
			} catch ( \Exception $e ) {
				error_log( sprintf( 'FP SEO: Failed to auto-generate Q&A for post %d: %s', $post_id, $e->getMessage() ) );
			}
		}

		// 2. Optimize images (always, doesn't require API)
		try {
			$this->image_optimizer->optimize_images( $post_id );
			error_log( sprintf( 'FP SEO: Auto-optimized images for post %d', $post_id ) );
		} catch ( \Exception $e ) {
			error_log( sprintf( 'FP SEO: Failed to optimize images for post %d: %s', $post_id, $e->getMessage() ) );
		}

		// 3. Optional: Generate entities (if enabled and doesn't require API)
		if ( ! empty( $options['ai_first']['enable_entities'] ) ) {
			try {
				$entity_graph = new \FP\SEO\GEO\EntityGraph();
				$entity_graph->build_entity_graph( $post_id );
				error_log( sprintf( 'FP SEO: Auto-generated entity graph for post %d', $post_id ) );
			} catch ( \Exception $e ) {
				error_log( sprintf( 'FP SEO: Failed to generate entities for post %d: %s', $post_id, $e->getMessage() ) );
			}
		}

		/**
		 * Fires after auto-generation completes
		 *
		 * @param int      $post_id Post ID.
		 * @param \WP_Post $post    Post object.
		 */
		do_action( 'fp_seo_auto_generation_complete', $post_id, $post );
	}

	/**
	 * Regenerate only Q&A (for updates)
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	private function regenerate_qa_only( int $post_id, \WP_Post $post ): void {
		$options = Options::get();

		// Only regenerate Q&A if enabled
		if ( empty( $options['ai_first']['enable_qa'] ) ) {
			return;
		}

		try {
			// Clear old Q&A
			$this->qa_extractor->clear_pairs( $post_id );

			// Generate new Q&A
			$this->qa_extractor->extract_qa_pairs( $post_id, true );

			error_log( sprintf( 'FP SEO: Regenerated Q&A for updated post %d', $post_id ) );
		} catch ( \Exception $e ) {
			error_log( sprintf( 'FP SEO: Failed to regenerate Q&A for post %d: %s', $post_id, $e->getMessage() ) );
		}

		/**
		 * Fires after Q&A regeneration on update
		 *
		 * @param int      $post_id Post ID.
		 * @param \WP_Post $post    Post object.
		 */
		do_action( 'fp_seo_qa_regeneration_complete', $post_id, $post );
	}
}


