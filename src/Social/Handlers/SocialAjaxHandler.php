<?php
/**
 * Handles AJAX requests for social media functionality.
 *
 * @package FP\SEO\Social\Handlers
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Social\Handlers;

use FP\SEO\Social\ImprovedSocialMediaManager;
use FP\SEO\Utils\Logger;
use WP_Post;
use function absint;
use function check_ajax_referer;
use function current_user_can;
use function get_post;
use function get_the_excerpt;
use function get_the_title;
use function html_entity_decode;
use function wp_create_nonce;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_trim_words;
use function wp_strip_all_tags;
use function do_shortcode;
use function preg_replace;

/**
 * Handles AJAX requests for social media.
 */
class SocialAjaxHandler {
	/**
	 * @var ImprovedSocialMediaManager
	 */
	private $manager;

	/**
	 * Constructor.
	 *
	 * @param ImprovedSocialMediaManager $manager Social media manager instance.
	 */
	public function __construct( ImprovedSocialMediaManager $manager ) {
		$this->manager = $manager;
	}

	/**
	 * Register AJAX handlers.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_ajax_fp_seo_preview_social', array( $this, 'handle_preview' ) );
		add_action( 'wp_ajax_fp_seo_optimize_social', array( $this, 'handle_optimize' ) );
	}

	/**
	 * Handle preview social AJAX request.
	 *
	 * @return void
	 */
	public function handle_preview(): void {
		check_ajax_referer( 'fp_seo_social_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to edit this post.', 'fp-seo-performance' ) ), 403 );
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			wp_send_json_error( array( 'message' => __( 'Post not found.', 'fp-seo-performance' ) ), 404 );
		}

		$preview_data = $this->manager->get_preview_data( $post );

		wp_send_json_success( $preview_data );
	}

	/**
	 * Handle optimize social AJAX request.
	 *
	 * @return void
	 */
	public function handle_optimize(): void {
		check_ajax_referer( 'fp_seo_social_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$platform = isset( $_POST['platform'] ) ? sanitize_text_field( wp_unslash( $_POST['platform'] ) ) : 'all';

		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to edit this post.', 'fp-seo-performance' ) ), 403 );
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			wp_send_json_error( array( 'message' => __( 'Post not found.', 'fp-seo-performance' ) ), 404 );
		}

		try {
			$optimized = $this->optimize_social_with_ai( $post, $platform );
			wp_send_json_success( $optimized );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::error( 'FP SEO: Error optimizing social media', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
					'post_id' => $post_id,
					'platform' => $platform,
				) );
			}
			wp_send_json_error( array( 'message' => __( 'Error optimizing social media content.', 'fp-seo-performance' ) ), 500 );
		}
	}

	/**
	 * Optimize social media content with AI.
	 *
	 * @param WP_Post $post Post object.
	 * @param string $platform Social platform.
	 * @return array<string, mixed>
	 */
	private function optimize_social_with_ai( WP_Post $post, string $platform ): array {
		$title = get_the_title( $post->ID );
		
		// Extract clean content, handling WPBakery shortcodes properly
		$content = $this->extract_clean_content( $post->post_content );
		
		$excerpt = get_the_excerpt( $post->ID );

		$optimized = array();

		if ( $platform === 'all' ) {
			foreach ( ImprovedSocialMediaManager::PLATFORMS as $platform_id => $platform_data ) {
				$optimized[ $platform_id ] = array(
					'title' => $this->optimize_for_platform( $title, $platform_id ),
					'description' => $this->optimize_for_platform( $excerpt ?: wp_trim_words( $content, 20 ), $platform_id )
				);
			}
		} else {
			$optimized[ $platform ] = array(
				'title' => $this->optimize_for_platform( $title, $platform ),
				'description' => $this->optimize_for_platform( $excerpt ?: wp_trim_words( $content, 20 ), $platform )
			);
		}

		return $optimized;
	}

	/**
	 * Optimize content for specific platform.
	 *
	 * @param string $content Content to optimize.
	 * @param string $platform Platform name.
	 * @return string
	 */
	private function optimize_for_platform( string $content, string $platform ): string {
		$platform_data = ImprovedSocialMediaManager::PLATFORMS[ $platform ] ?? null;
		if ( ! $platform_data ) {
			return $content;
		}

		$limit = $platform_data['title_limit'];
		$content = wp_trim_words( $content, $limit / 6 ); // Rough word estimation
		
		// Decode all HTML entities to ensure clean text
		$content = html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		
		return $content;
	}

	/**
	 * Extract clean content from post, handling WPBakery shortcodes.
	 *
	 * @param string $post_content Raw post content.
	 * @return string Clean text content.
	 */
	private function extract_clean_content( string $post_content ): string {
		if ( empty( $post_content ) ) {
			return '';
		}

		// Check if content contains WPBakery shortcodes
		if ( strpos( $post_content, '[vc_' ) !== false || strpos( $post_content, '[vc_row' ) !== false ) {
			// Use WPBakeryContentExtractor to get clean text (static method)
			if ( class_exists( '\FP\SEO\Utils\WPBakeryContentExtractor' ) ) {
				$text = \FP\SEO\Utils\WPBakeryContentExtractor::extract_text( $post_content );
				
				if ( ! empty( $text ) ) {
					// Clean up the extracted text (already cleaned by extract_text, but normalize whitespace)
					$text = preg_replace( '/\s+/', ' ', $text ); // Normalize whitespace
					return trim( $text );
				}
			}
		}

		// Fallback: standard WordPress shortcode removal
		// First render shortcodes, then strip tags
		$rendered = do_shortcode( $post_content );
		$content = wp_strip_all_tags( $rendered );
		$content = preg_replace( '/\s+/', ' ', $content ); // Normalize whitespace
		
		return trim( $content );
	}
}


