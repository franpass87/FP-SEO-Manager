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
use FP\SEO\GEO\ImageSeoOptimizer;
use FP\SEO\Utils\OptionsHelper;
use FP\SEO\Utils\Logger;

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
	 * Image SEO optimizer instance
	 *
	 * @var ImageSeoOptimizer
	 */
	private ImageSeoOptimizer $image_seo_optimizer;

	/**
	 * Constructor
	 *
	 * @param QAPairExtractor    $qa_extractor        Q&A extractor instance.
	 * @param MultiModalOptimizer $image_optimizer    Image optimizer instance.
	 * @param ImageSeoOptimizer  $image_seo_optimizer Image SEO optimizer instance.
	 */
	public function __construct( QAPairExtractor $qa_extractor, MultiModalOptimizer $image_optimizer, ImageSeoOptimizer $image_seo_optimizer ) {
		$this->qa_extractor        = $qa_extractor;
		$this->image_optimizer     = $image_optimizer;
		$this->image_seo_optimizer  = $image_seo_optimizer;
	}

	/**
	 * Register hooks
	 *
	 * Registers publish and save hooks for all supported post types.
	 * Uses specific post type hooks to prevent interference with unsupported types.
	 */
	public function register(): void {
		// CRITICAL: Register hooks ONLY for supported post types to prevent ANY interference
		$supported_types = \FP\SEO\Utils\PostTypes::analyzable();
		
		if ( empty( $supported_types ) ) {
			Logger::warning( 'No supported post types found for AutoGenerationHook' );
			return;
		}
		
		// Register publish hooks for each supported post type
		foreach ( $supported_types as $post_type ) {
			// Validate post type string
			if ( ! is_string( $post_type ) || empty( $post_type ) ) {
				continue;
			}
			
			// Hook on post publish (new posts) - use specific post type hook
			$publish_hook = 'publish_' . $post_type;
			if ( ! has_action( $publish_hook, array( $this, 'on_publish' ) ) ) {
				add_action( $publish_hook, array( $this, 'on_publish' ), 10, 2 );
			}

			// Hook on post update for supported post types
			$save_hook = 'save_post_' . $post_type;
			if ( ! has_action( $save_hook, array( $this, 'on_update' ) ) ) {
				add_action( $save_hook, array( $this, 'on_update' ), 20, 3 );
			}
		}
		
		Logger::info( 'AutoGenerationHook registered', array( 'post_types' => $supported_types ) );
	}

	/**
	 * Handle post publish event
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function on_publish( int $post_id, \WP_Post $post ): void {
// Validate post ID
		if ( ! $post_id || $post_id <= 0 ) {
			return;
		}
		
		// CRITICAL: Check post type FIRST, before any processing
		// This ensures we don't interfere with unsupported post types (attachments, Nectar Sliders, etc.)
		$post_type = get_post_type( $post_id );
		$supported_types = \FP\SEO\Utils\PostTypes::analyzable();
		
		// If not a supported post type, return immediately without any processing
		if ( ! in_array( $post_type, $supported_types, true ) ) {
			return; // Exit immediately - no interference with WordPress core saving
		}
		
		// Validate post object
		if ( ! $post || ! is_a( $post, 'WP_Post' ) ) {
			Logger::warning( 'Invalid post object in on_publish', array( 'post_id' => $post_id ) );
			return;
		}
		
		// Check if auto-generation is enabled
		if ( ! $this->is_auto_generation_enabled() ) {
			return;
		}

		// Avoid infinite loops
		if ( $this->is_generating( $post_id ) ) {
			Logger::debug( 'Skipping auto-generation - already generating', array( 'post_id' => $post_id ) );
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
		} catch ( \Throwable $e ) {
			Logger::error( 'Auto-generation error', array(
				'error'   => $e->getMessage(),
				'post_id' => $post_id,
				'trace'   => $e->getTraceAsString(),
			) );
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
		// CRITICAL: Do NOT interfere if WordPress is handling a native operation
		if ( class_exists( 'FP\SEO\Editor\Helpers\WordPressNativeProtection' ) ) {
			if ( \FP\SEO\Editor\Helpers\WordPressNativeProtection::any_native_meta_field_being_saved() ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::debug( 'AutoGenerationHook::on_update BLOCKED - WordPress native meta field operation detected', array( 'post_id' => $post_id ) );
				}
				return;
			}
		}
		
		// Validate post ID
		if ( ! $post_id || $post_id <= 0 ) {
			return;
		}
		
		// CRITICAL: Check post type FIRST, before any processing
		// This ensures we don't interfere with unsupported post types (attachments, Nectar Sliders, etc.)
		$post_type = get_post_type( $post_id );
		$supported_types = \FP\SEO\Utils\PostTypes::analyzable();
		
		// If not a supported post type, return immediately without any processing
		if ( ! in_array( $post_type, $supported_types, true ) ) {
			return; // Exit immediately - no interference with WordPress core saving
		}
// Validate post object
		if ( ! $post || ! is_a( $post, 'WP_Post' ) ) {
			Logger::warning( 'Invalid post object in on_update', array( 'post_id' => $post_id ) );
			return;
		}
		
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
			Logger::debug( 'Skipping auto-regeneration - content unchanged', array( 'post_id' => $post_id ) );
			return;
		}

		// Skip autosaves and revisions
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Avoid infinite loops
		if ( $this->is_generating( $post_id ) ) {
			Logger::debug( 'Skipping auto-regeneration - already generating', array( 'post_id' => $post_id ) );
			return;
		}

		// Mark as generating
		$this->set_generating( $post_id, true );

		try {
			// On update, regenerate only Q&A (images likely unchanged)
			$this->regenerate_qa_only( $post_id, $post );
		} catch ( \Throwable $e ) {
			Logger::error( 'Auto-regeneration error', array(
				'error'   => $e->getMessage(),
				'post_id' => $post_id,
				'trace'   => $e->getTraceAsString(),
			) );
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
		$options = OptionsHelper::get();
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
		// Get post object
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}
		
		// Get previous content hash
		$previous_hash = get_post_meta( $post_id, '_fp_seo_content_hash', true );

		// Calculate current content hash (include title and content)
		$current_hash = md5( ( $post->post_content ?? '' ) . ( $post->post_title ?? '' ) );

		// If no previous hash, consider it changed and save the hash
		if ( empty( $previous_hash ) ) {
			update_post_meta( $post_id, '_fp_seo_content_hash', $current_hash );
			return true;
		}

		// Compare hashes
		$changed = $previous_hash !== $current_hash;
		
		// Update hash only if changed
		if ( $changed ) {
			update_post_meta( $post_id, '_fp_seo_content_hash', $current_hash );
		}
		
		return $changed;
	}

	/**
	 * Generate all AI data for a post
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	private function generate_ai_data( int $post_id, \WP_Post $post ): void {
		$options = OptionsHelper::get();
		$generated = array();

		// 1. Generate Q&A pairs (if enabled)
		if ( ! empty( $options['ai_first']['enable_qa'] ) ) {
			try {
				$qa_pairs = $this->qa_extractor->extract_qa_pairs( $post_id, true );
				$generated['qa'] = count( $qa_pairs );
				Logger::info( 'Auto-generated Q&A', array( 
					'post_id' => $post_id,
					'pairs_count' => count( $qa_pairs )
				) );
			} catch ( \Throwable $e ) {
				Logger::error( 'Failed to auto-generate Q&A', array( 
					'post_id' => $post_id, 
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString()
				) );
			}
		}

		// 2. Optimize images (always, doesn't require API)
		try {
			$image_data = $this->image_optimizer->optimize_images( $post_id );
			$generated['images'] = $image_data['total_images'] ?? 0;
			Logger::info( 'Auto-optimized images', array( 
				'post_id' => $post_id,
				'total_images' => $generated['images']
			) );
		} catch ( \Throwable $e ) {
				Logger::error( 'Failed to optimize images', array( 
					'post_id' => $post_id, 
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString()
				) );
			}

		// 4. Optimize images for SEO (after multi-modal optimization)
		try {
			$seo_result = $this->image_seo_optimizer->optimize_images_seo( $post_id );
			$generated['seo_images'] = $seo_result['optimized_count'] ?? 0;
			Logger::info( 'Auto-optimized images for SEO', array( 
				'post_id' => $post_id,
				'optimized_count' => $generated['seo_images']
			) );
		} catch ( \Throwable $e ) {
				Logger::error( 'Failed to optimize images for SEO', array( 
					'post_id' => $post_id, 
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString()
				) );
			}

		// 3. Optional: Generate entities (if enabled and doesn't require API)
		if ( ! empty( $options['ai_first']['enable_entities'] ) ) {
			try {
				$entity_graph = new \FP\SEO\GEO\EntityGraph();
				$entity_graph->build_entity_graph( $post_id );
				$generated['entities'] = true;
				Logger::info( 'Auto-generated entity graph', array( 'post_id' => $post_id ) );
			} catch ( \Throwable $e ) {
				Logger::error( 'Failed to generate entities', array( 
					'post_id' => $post_id, 
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString()
				) );
			}
		}

		/**
		 * Fires after auto-generation completes
		 *
		 * @param int      $post_id Post ID.
		 * @param \WP_Post $post    Post object.
		 * @param array    $generated Generated data summary.
		 */
		do_action( 'fp_seo_auto_generation_complete', $post_id, $post, $generated );
	}

	/**
	 * Regenerate Q&A and optimize new images (for updates)
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	private function regenerate_qa_only( int $post_id, \WP_Post $post ): void {
		$options = OptionsHelper::get();

		// Regenerate Q&A if enabled
		if ( ! empty( $options['ai_first']['enable_qa'] ) ) {
			try {
				// Clear old Q&A
				$cleared = $this->qa_extractor->clear_pairs( $post_id );
				
				if ( ! $cleared ) {
					Logger::warning( 'Failed to clear old Q&A pairs', array( 'post_id' => $post_id ) );
				}

				// Generate new Q&A
				$qa_pairs = $this->qa_extractor->extract_qa_pairs( $post_id, true );

				Logger::info( 'Regenerated Q&A for updated post', array( 
					'post_id' => $post_id,
					'pairs_count' => count( $qa_pairs )
				) );
			} catch ( \Throwable $e ) {
				Logger::error( 'Failed to regenerate Q&A', array( 
					'post_id' => $post_id, 
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString()
				) );
			}
		}

		// Optimize images for SEO (will skip already optimized images)
		try {
			$seo_result = $this->image_seo_optimizer->optimize_images_seo( $post_id );
			
			// Only log if there were new images to optimize
			if ( isset( $seo_result['optimized_count'] ) && $seo_result['optimized_count'] > 0 ) {
				Logger::info( 'Optimized new images on post update', array( 
					'post_id' => $post_id,
					'optimized_count' => $seo_result['optimized_count']
				) );
			}
		} catch ( \Throwable $e ) {
				Logger::error( 'Failed to optimize images on update', array( 
					'post_id' => $post_id, 
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString()
				) );
			}

		/**
		 * Fires after Q&A regeneration and image optimization on update
		 *
		 * @param int      $post_id Post ID.
		 * @param \WP_Post $post    Post object.
		 */
		do_action( 'fp_seo_qa_regeneration_complete', $post_id, $post );
	}
}
