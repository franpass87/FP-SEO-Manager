<?php
/**
 * Outputs core SEO meta tags on the frontend.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Front;

use FP\SEO\Utils\MetadataResolver;
use WP_Post;
use function add_action;
use function esc_attr;
use function esc_url;
use function get_permalink;
use function get_post;
use function get_queried_object_id;
use function html_entity_decode;
use function is_admin;
use function is_feed;
use function is_preview;
use function is_singular;
use function mb_substr;
use function preg_replace;
use function trim;
use function wp_strip_all_tags;

/**
 * Renders description/canonical/robots meta tags.
 */
class MetaTagRenderer {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'wp_head', array( $this, 'render_meta_tags' ), 0 );
		// Filter document title to use SEO title if different from post title
		add_filter( 'pre_get_document_title', array( $this, 'filter_document_title' ), 10, 1 );
		add_filter( 'document_title_parts', array( $this, 'filter_document_title_parts' ), 10, 1 );
	}

	/**
	 * Render SEO meta tags in the document head.
	 */
	public function render_meta_tags(): void {
		if ( is_admin() || is_feed() || is_preview() || ! is_singular() ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$description = $this->prepare_description(
			MetadataResolver::resolve_meta_description( $post )
		);

		$canonical = MetadataResolver::resolve_canonical_url( $post );
		if ( empty( $canonical ) ) {
			$canonical = get_permalink( $post );
		}

		$robots = MetadataResolver::resolve_robots( $post );
		if ( empty( $robots ) ) {
			$robots = 'index,follow';
		}

		echo "\n<!-- FP SEO Performance Meta Tags -->\n";

		if ( '' !== $description ) {
			echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
		}

		if ( ! empty( $canonical ) ) {
			echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
		}

		if ( ! empty( $robots ) ) {
			echo '<meta name="robots" content="' . esc_attr( $robots ) . '">' . "\n";
		}

		echo "<!-- End FP SEO Performance Meta Tags -->\n";
	}

	/**
	 * Normalize the description string before output.
	 *
	 * @param string $description Raw description.
	 * @return string
	 */
	private function prepare_description( string $description ): string {
		$description = html_entity_decode( wp_strip_all_tags( $description ) );
		$description = preg_replace( '/\s+/u', ' ', $description ?? '' ) ?? '';
		$description = trim( $description );

		if ( '' === $description ) {
			return '';
		}

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $description, 0, 155 );
		}

		return substr( $description, 0, 155 );
	}

	/**
	 * Filter document title to use SEO title if available and different from post title.
	 *
	 * @param string $title Current document title.
	 * @return string Filtered title.
	 */
	public function filter_document_title( string $title ): string {
		if ( is_admin() || is_feed() || is_preview() || ! is_singular() ) {
			return $title;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return $title;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return $title;
		}

		// Get SEO title
		$seo_title = MetadataResolver::resolve_seo_title( $post );
		
		// If SEO title is empty or same as post title, return original
		if ( empty( $seo_title ) || $seo_title === $post->post_title ) {
			return $title;
		}

		// Use SEO title instead
		$seo_title = wp_strip_all_tags( $seo_title );
		$seo_title = trim( $seo_title );

		// If SEO title is different from post title, use it
		if ( $seo_title !== $post->post_title && ! empty( $seo_title ) ) {
			// Get site name for context
			$site_name = get_bloginfo( 'name', 'display' );
			
			// Build title with site name
			$separator = apply_filters( 'document_title_separator', '-' );
			$new_title = $seo_title . ' ' . $separator . ' ' . $site_name;
			
			return $new_title;
		}

		return $title;
	}

	/**
	 * Filter document title parts to use SEO title.
	 *
	 * @param array<string, string> $title_parts Title parts.
	 * @return array<string, string> Filtered title parts.
	 */
	public function filter_document_title_parts( array $title_parts ): array {
		if ( is_admin() || is_feed() || is_preview() || ! is_singular() ) {
			return $title_parts;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return $title_parts;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return $title_parts;
		}

		// Get SEO title
		$seo_title = MetadataResolver::resolve_seo_title( $post );
		
		// If SEO title is empty or same as post title, return original
		if ( empty( $seo_title ) || $seo_title === $post->post_title ) {
			return $title_parts;
		}

		// Use SEO title instead of post title
		$seo_title = wp_strip_all_tags( $seo_title );
		$seo_title = trim( $seo_title );

		if ( ! empty( $seo_title ) && $seo_title !== $post->post_title ) {
			// Replace the title part with SEO title
			$title_parts['title'] = $seo_title;
		}

		return $title_parts;
	}
}

