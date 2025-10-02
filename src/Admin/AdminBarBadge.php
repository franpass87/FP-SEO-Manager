<?php
/**
 * Admin bar badge output.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin;

use FP\SEO\Analysis\Analyzer;
use FP\SEO\Analysis\Context;
use FP\SEO\Scoring\ScoreEngine;
use FP\SEO\Utils\I18n;
use FP\SEO\Utils\Options;
use WP_Admin_Bar;
use function add_action;
use function add_query_arg;
use function admin_url;
use function current_user_can;
use function esc_attr;
use function get_post;
use function get_post_meta;
use function get_post_type;
use function get_permalink;
use function is_admin;
use function is_admin_bar_showing;
use function sanitize_html_class;
use function wp_strip_all_tags;

/**
 * Renders a contextual analyzer score within the WordPress admin bar.
 */
class AdminBarBadge {
		/**
		 * Hook registrations.
		 */
	public function register(): void {
                        add_action( 'admin_bar_menu', array( $this, 'add_badge' ), 120 );
                        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 10, 0 );
	}

		/**
		 * Enqueue styling for the badge when required.
		 */
	public function enqueue_assets(): void {
		if ( ! $this->should_display_badge() ) {
				return;
		}

		if ( function_exists( 'wp_enqueue_style' ) ) {
				wp_enqueue_style( 'fp-seo-performance-admin' );
		}
	}

		/**
		 * Adds the analyzer badge to the admin bar.
		 *
		 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
		 */
	public function add_badge( WP_Admin_Bar $wp_admin_bar ): void {
		if ( ! $this->should_display_badge() ) {
				return;
		}

			$post_id = $this->get_current_post_id();

		if ( null === $post_id ) {
				return;
		}

			$post = get_post( $post_id );

		if ( ! $post ) {
				return;
		}

			$context = new Context(
				(int) $post->ID,
				(string) $post->post_content,
				(string) $post->post_title,
				$this->resolve_meta_description( $post->ID, (string) $post->post_excerpt ),
				function_exists( 'get_permalink' ) ? get_permalink( $post ) : null,
				$this->resolve_robots_directive( $post->ID )
			);

			$analyzer     = new Analyzer();
			$analysis     = $analyzer->analyze( $context );
			$score_engine = new ScoreEngine();
			$score_data   = $score_engine->calculate( $analysis['checks'] ?? array() );
			$score_value  = $score_data['score'] ?? 0;
			$score_status = is_string( $score_data['status'] ?? null ) ? $score_data['status'] : 'pending';

			$label = sprintf(
				'%s <span class="fp-seo-performance-badge-score">%s</span>',
				esc_html( I18n::translate( 'SEO Score' ) ),
				esc_html( (string) $score_value )
			);

			$wp_admin_bar->add_node(
				array(
					'id'    => 'fp-seo-performance-score',
					'title' => $label,
					'href'  => add_query_arg(
						array(
							'page' => 'fp-seo-performance',
						),
						admin_url( 'admin.php' )
					),
                                        'meta'  => array(
                                                'class' => 'fp-seo-performance-badge fp-seo-performance-badge--' . sanitize_html_class( $score_status ),
                                                'title' => esc_attr(
                                                        sprintf(
                                                                '%s: %s',
                                                                I18n::translate( 'Analyzer status' ),
                                                                $this->status_description( $score_status )
                                                        )
                                                ),
                                        ),
                                )
                        );
        }

		/**
		 * Determine whether the badge should render for the current request.
		 */
	private function should_display_badge(): bool {
		if ( ! is_admin() || ! is_admin_bar_showing() ) {
				return false;
		}

			$options = Options::get();

		if ( empty( $options['general']['admin_bar_badge'] ) || empty( $options['general']['enable_analyzer'] ) ) {
				return false;
		}

		if ( ! current_user_can( Options::get_capability() ) ) {
				return false;
		}

			$post_id = $this->get_current_post_id();

		if ( null === $post_id ) {
				return false;
		}

			$post_type = function_exists( 'get_post_type' ) ? get_post_type( $post_id ) : null;

			return ! empty( $post_type );
	}

		/**
		 * Determine the current editing post identifier when available.
		 */
	private function get_current_post_id(): ?int {
			global $pagenow;

		if ( 'post.php' !== ( $pagenow ?? '' ) ) {
				return null;
		}

			$post = $_GET['post'] ?? null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only context.

		if ( null === $post ) {
				return null;
		}

			$post_id = (int) $post;

			return $post_id > 0 ? $post_id : null;
	}

		/**
		 * Resolve a meta description string for scoring context.
		 *
		 * @param int    $post_id Post identifier.
		 * @param string $excerpt Post excerpt fallback.
		 */
	private function resolve_meta_description( int $post_id, string $excerpt ): string {
		$meta = '';

		if ( function_exists( 'get_post_meta' ) ) {
				$meta = (string) get_post_meta( $post_id, '_fp_seo_meta_description', true );
		}

		if ( '' === $meta && '' !== $excerpt ) {
				$meta = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $excerpt ) : $excerpt;
		}

		return $meta;
	}

		/**
		 * Resolve robots directives if stored.
		 *
		 * @param int $post_id Post identifier.
		 */
	private function resolve_robots_directive( int $post_id ): ?string {
		if ( function_exists( 'get_post_meta' ) ) {
				$value = get_post_meta( $post_id, '_fp_seo_meta_robots', true );

			if ( is_string( $value ) && '' !== trim( $value ) ) {
				return $value;
			}
		}

		return null;
	}

		/**
		 * Provide a human readable status description.
		 *
		 * @param string $status Score indicator key.
		 */
	private function status_description( string $status ): string {
		switch ( $status ) {
			case 'green':
				return I18n::translate( 'Healthy' );
			case 'yellow':
				return I18n::translate( 'Needs attention' );
			case 'red':
				return I18n::translate( 'Critical issues' );
			default:
				return I18n::translate( 'Pending analysis' );
		}
	}
}
