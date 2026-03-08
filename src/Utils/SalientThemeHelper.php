<?php
/**
 * Helper for Salient theme integration.
 *
 * @package FP\SEO\Utils
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Utils;

use WP_Post;
use function get_template;
use function get_stylesheet;
use function get_post;

/**
 * Helper class for Salient theme integration.
 */
class SalientThemeHelper {

	/**
	 * Check if Salient theme is active.
	 *
	 * @return bool True if Salient is active.
	 */
	public static function is_salient_active(): bool {
		$template   = get_template();
		$stylesheet = get_stylesheet();

		return 'salient' === $template || 'salient' === $stylesheet;
	}

	/**
	 * Get page header HTML with H1 from Salient theme.
	 *
	 * @param int|null $post_id Post ID.
	 * @return string HTML content of the page header, empty if not available.
	 */
	public static function get_page_header_html( ?int $post_id ): string {
		if ( ! self::is_salient_active() ) {
			return '';
		}

		if ( null === $post_id || $post_id <= 0 ) {
			return '';
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return '';
		}

		return self::render_salient_header( $post );
	}

	/**
	 * Render Salient header manually by extracting H1 from page header template.
	 *
	 * @param WP_Post $post Post object.
	 * @return string HTML content.
	 */
	private static function render_salient_header( WP_Post $post ): string {
		$page_header_title    = get_post_meta( $post->ID, '_nectar_header_title', true );
		$page_header_subtitle = get_post_meta( $post->ID, '_nectar_header_subtitle', true );

		$nectar_options    = get_option( 'salient' );
		$header_auto_title = is_array( $nectar_options ) && ! empty( $nectar_options['header-auto-title'] ) && '1' === $nectar_options['header-auto-title'];

		$title = '';
		if ( ! empty( $page_header_title ) ) {
			$title = $page_header_title;
		} elseif ( $header_auto_title && 'page' === $post->post_type ) {
			$title = $post->post_title;
		} elseif ( ! empty( $post->post_title ) ) {
			$title = $post->post_title;
		}

		if ( empty( $title ) ) {
			return '';
		}

		$html  = '<div class="nectar-page-header-wrapper">';
		$html .= '<div class="row">';
		$html .= '<div class="col span_6 section-title">';
		$html .= '<div class="inner-wrap">';
		$html .= '<h1>' . esc_html( $title ) . '</h1>';

		if ( ! empty( $page_header_subtitle ) ) {
			$html .= '<span class="subheader">' . esc_html( $page_header_subtitle ) . '</span>';
		}

		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Check if post uses Salient page header with title as H1.
	 *
	 * @param int|null $post_id Post ID.
	 * @return bool True if post likely uses Salient header with H1.
	 */
	public static function uses_salient_header_h1( ?int $post_id ): bool {
		if ( ! self::is_salient_active() ) {
			return false;
		}

		if ( null === $post_id || $post_id <= 0 ) {
			return false;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		$post_types_with_header = array( 'page', 'post', 'portfolio' );
		if ( ! in_array( $post->post_type, $post_types_with_header, true ) ) {
			return false;
		}

		$header_title = get_post_meta( $post->ID, '_nectar_header_title', true );

		$nectar_options    = get_option( 'salient' );
		$header_auto_title = is_array( $nectar_options ) && ! empty( $nectar_options['header-auto-title'] ) && '1' === $nectar_options['header-auto-title'];

		if ( 'page' === $post->post_type ) {
			$pages_to_skip = apply_filters( 'nectar_auto_page_header_bypass', array() );
			if ( is_array( $pages_to_skip ) && in_array( $post_id, $pages_to_skip, true ) ) {
				return false;
			}

			return $header_auto_title || ! empty( $header_title ) || ! empty( $post->post_title );
		}

		return ! empty( $header_title ) || ! empty( $post->post_title );
	}
}
