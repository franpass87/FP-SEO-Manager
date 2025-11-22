<?php
/**
 * Auto Indexing - Automatic submission on publish
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Integrations;

use FP\SEO\Utils\Logger;

/**
 * Handles automatic indexing on publish/update
 */
class AutoIndexing {

	/**
	 * Indexing API client
	 *
	 * @var IndexingApi
	 */
	private IndexingApi $indexing_api;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->indexing_api = new IndexingApi();
	}

	/**
	 * Register hooks
	 */
	public function register(): void {
		add_action( 'publish_post', array( $this, 'on_publish' ), 10, 2 );
		add_action( 'publish_page', array( $this, 'on_publish' ), 10, 2 );
		add_action( 'publish_fp_experience', array( $this, 'on_publish' ), 10, 2 );
		add_action( 'before_delete_post', array( $this, 'on_delete' ) );
		add_action( 'wp_trash_post', array( $this, 'on_delete' ) );
	}

	/**
	 * Handle post publish/update
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function on_publish( int $post_id, \WP_Post $post ): void {
		Logger::debug( 'Auto-indexing on_publish triggered', array( 'post_id' => $post_id, 'post_type' => $post->post_type ) );

		// Check if auto-indexing is enabled
		if ( ! $this->is_enabled() ) {
			Logger::debug( 'Auto-indexing disabled in settings' );
			return;
		}

		// Skip autosave/revisions
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			Logger::debug( 'Skipped: autosave' );
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			Logger::debug( 'Skipped: revision' );
			return;
		}

		// Check if post is actually published (not draft/pending)
		if ( 'publish' !== $post->post_status ) {
			Logger::debug( 'Skipped: post not published', array( 'status' => $post->post_status ) );
			return;
		}

		// Check if post type is enabled
		if ( ! $this->is_post_type_enabled( $post->post_type ) ) {
			Logger::debug( 'Skipped: post type not enabled', array( 'post_type' => $post->post_type ) );
			return;
		}

		$permalink = get_permalink( $post_id );
		Logger::info( 'Submitting to Google Indexing API', array( 'url' => $permalink, 'post_id' => $post_id ) );

		// Submit to Google
		$submitted = $this->indexing_api->submit_post( $post_id );

		if ( $submitted ) {
			// Store submission timestamp
			update_post_meta( $post_id, '_fp_seo_last_indexing_submission', time() );
			update_post_meta( $post_id, '_fp_seo_indexing_status', 'submitted' );
			Logger::info( 'Successfully submitted to Google', array( 'post_id' => $post_id ) );
		} else {
			Logger::error( 'Failed to submit to Google', array( 'post_id' => $post_id ) );
		}
	}

	/**
	 * Handle post deletion
	 *
	 * @param int $post_id Post ID.
	 */
	public function on_delete( int $post_id ): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return;
		}

		if ( ! $this->is_post_type_enabled( $post->post_type ) ) {
			return;
		}

		// Notify Google of deletion
		$this->indexing_api->notify_deletion( $post_id );
	}

	/**
	 * Check if auto-indexing is enabled
	 *
	 * @return bool
	 */
	private function is_enabled(): bool {
		$options = get_option( 'fp_seo_performance', array() );
		return ! empty( $options['gsc']['auto_indexing'] );
	}

	/**
	 * Check if post type is enabled for auto-indexing
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	private function is_post_type_enabled( string $post_type ): bool {
		$options = get_option( 'fp_seo_performance', array() );
		$enabled_types = $options['gsc']['auto_indexing_post_types'] ?? array( 'post', 'page', 'fp_experience' );

		return in_array( $post_type, $enabled_types, true );
	}
}

