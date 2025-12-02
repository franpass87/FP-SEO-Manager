<?php
/**
 * Homepage protection from auto-draft issues.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor;

use FP\SEO\Editor\Helpers\HomepageHelper;
use FP\SEO\Utils\Logger;
use WP_Post;
use function clean_post_cache;
use function get_post;
use function get_post_status;
use function update_post_meta;
use function wp_cache_delete;

/**
 * Handles all homepage protection logic to prevent auto-draft issues.
 */
class HomepageProtection {
	/**
	 * Register all homepage protection hooks.
	 */
	public function register_hooks(): void {
		// CRITICAL: Hook very early to prevent auto-draft creation before WordPress loads editor
		// TEMPORARILY DISABLED: init hook causes conflict with FP-Multilanguage
		// Use 'init' with high priority to run before most other plugins
		/*
		if ( ! has_action( 'init', array( $this, 'prevent_auto_draft_creation_early' ) ) ) {
			add_action( 'init', array( $this, 'prevent_auto_draft_creation_early' ), 1 );
		}
		*/

		// Force correct homepage in editor (early)
		// RE-ENABLED with error handling
		if ( ! has_action( 'admin_init', array( $this, 'force_correct_homepage_in_editor' ) ) ) {
			add_action( 'admin_init', array( $this, 'force_correct_homepage_in_editor' ), 1 );
		}

		// Force correct homepage on screen load (late)
		// RE-ENABLED with error handling
		if ( ! has_action( 'current_screen', array( $this, 'force_correct_homepage_on_screen' ) ) ) {
			add_action( 'current_screen', array( $this, 'force_correct_homepage_on_screen' ), 999 );
		}

		// Filter get_post to always return homepage when editing homepage
		// RE-ENABLED with error handling
		if ( ! has_filter( 'get_post', array( $this, 'filter_get_post_for_homepage' ) ) ) {
			add_filter( 'get_post', array( $this, 'filter_get_post_for_homepage' ), 10, 2 );
		}

		// Diagnose auto-draft creation
		if ( ! has_action( 'wp_insert_post', array( $this, 'diagnose_auto_draft_creation' ) ) ) {
			add_action( 'wp_insert_post', array( $this, 'diagnose_auto_draft_creation' ), 999, 3 );
		}

		// Prevent auto-draft creation via wp_insert_post_data filter
		if ( ! has_filter( 'wp_insert_post_data', array( $this, 'prevent_homepage_auto_draft_data' ) ) ) {
			add_filter( 'wp_insert_post_data', array( $this, 'prevent_homepage_auto_draft_data' ), 999, 2 );
		}

		// Delete auto-drafts immediately when created
		if ( ! has_action( 'wp_insert_post', array( $this, 'delete_auto_draft_on_homepage_edit' ) ) ) {
			add_action( 'wp_insert_post', array( $this, 'delete_auto_draft_on_homepage_edit' ), 999, 3 );
		}

		// Fix homepage title in editor (very late, after everything loads)
		if ( ! has_filter( 'the_title', array( $this, 'fix_homepage_title_in_editor' ) ) ) {
			add_filter( 'the_title', array( $this, 'fix_homepage_title_in_editor' ), 999, 2 );
		}
	}

	/**
	 * Prevent auto-draft creation very early in WordPress load.
	 * Hook: init (priority 1)
	 */
	public function prevent_auto_draft_creation_early(): void {
		// CRITICAL: Wrap in try-catch to prevent fatal errors from breaking WordPress
		try {
			// Only in admin and when editing a page
			if ( ! is_admin() || ! isset( $_GET['post'] ) || ! isset( $_GET['action'] ) || $_GET['action'] !== 'edit' ) {
				return;
			}

			$requested_post_id = (int) $_GET['post'];

			// Only if we're editing the homepage
			if ( ! HomepageHelper::is_homepage( $requested_post_id ) ) {
				return;
			}

			// CRITICAL: Delete any existing auto-drafts immediately to prevent WordPress from loading them
			$this->delete_user_auto_drafts();

			// CRITICAL: Force correct homepage post object early and ensure content is correct
			global $post;
			$correct_homepage = get_post( $page_on_front_id, ARRAY_A );
			if ( $correct_homepage && is_array( $correct_homepage ) ) {
				// Convert to WP_Post object
				$correct_homepage_obj = new WP_Post( (object) $correct_homepage );
				$GLOBALS['post'] = $correct_homepage_obj;
			
			// Also update wp_query if it exists
			global $wp_query;
			if ( isset( $wp_query ) ) {
				$wp_query->post = $correct_homepage_obj;
				$wp_query->posts = array( $correct_homepage_obj );
				$wp_query->post_count = 1;
			}
		}

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::info( 'HomepageProtection::prevent_auto_draft_creation_early - Early protection activated', array(
					'homepage_id' => $page_on_front_id,
					'requested_post_id' => $requested_post_id,
				) );
			}
		} catch ( \Throwable $e ) {
			// Log error but don't break WordPress
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::error( 'FP SEO: Error in prevent_auto_draft_creation_early', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				) );
			}
		}
	}

	/**
	 * Force WordPress to load correct homepage when opening editor.
	 * Hook: admin_init (priority 1)
	 */
	public function force_correct_homepage_in_editor(): void {
		// CRITICAL: Wrap in try-catch to prevent fatal errors
		try {
			// Only in admin and when editing a page
			if ( ! is_admin() || ! isset( $_GET['post'] ) || ! isset( $_GET['action'] ) || $_GET['action'] !== 'edit' ) {
				return;
			}

			$requested_post_id = (int) $_GET['post'];

			// Only if we're editing the homepage
			if ( ! HomepageHelper::is_homepage( $requested_post_id ) ) {
				return;
			}

			// Get correct homepage from database
			$correct_homepage = get_post( $page_on_front_id );
			if ( ! $correct_homepage instanceof WP_Post ) {
				return;
			}

			// Force global post object
			global $post, $wp_query;
			$GLOBALS['post'] = $correct_homepage;
			if ( isset( $wp_query ) ) {
				$wp_query->post = $correct_homepage;
		}

			// Delete any auto-drafts created by current user
			$this->delete_user_auto_drafts();

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::info( 'HomepageProtection::force_correct_homepage_in_editor - Forced correct homepage in editor', array(
					'homepage_id' => $page_on_front_id,
				) );
			}
		} catch ( \Throwable $e ) {
			// Log error but don't break WordPress
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::error( 'FP SEO: Error in force_correct_homepage_in_editor', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				) );
			}
		}
	}

	/**
	 * Force correct homepage on screen load.
	 * Hook: current_screen (priority 999)
	 *
	 * @param \WP_Screen $screen Current screen object.
	 */
	public function force_correct_homepage_on_screen( $screen ): void {
		// CRITICAL: Wrap in try-catch to prevent fatal errors
		try {
			if ( ! $screen || $screen->base !== 'post' || $screen->post_type !== 'page' ) {
				return;
			}

			if ( ! isset( $_GET['post'] ) || ! isset( $_GET['action'] ) || $_GET['action'] !== 'edit' ) {
				return;
			}

			$requested_post_id = (int) $_GET['post'];
			$page_on_front_id = HomepageHelper::get_homepage_id();

			if ( $page_on_front_id === 0 || $requested_post_id !== $page_on_front_id ) {
				return;
			}

			$correct_homepage = get_post( $page_on_front_id );
			if ( ! $correct_homepage instanceof WP_Post ) {
				return;
			}

			global $post, $wp_query;
			$GLOBALS['post'] = $correct_homepage;
			if ( isset( $wp_query ) ) {
				$wp_query->post = $correct_homepage;
			}

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::info( 'HomepageProtection::force_correct_homepage_on_screen - Forced correct homepage on screen load', array(
					'homepage_id' => $page_on_front_id,
				) );
			}
		} catch ( \Throwable $e ) {
			// Log error but don't break WordPress
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::error( 'FP SEO: Error in force_correct_homepage_on_screen', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				) );
			}
		}
	}

	/**
	 * Filter get_post to always return homepage when editing homepage.
	 * Hook: get_post (priority 10)
	 *
	 * @param WP_Post|null $post    Post object.
	 * @param int          $post_id Post ID.
	 * @return WP_Post|null
	 */
	public function filter_get_post_for_homepage( $post, int $post_id ): ?WP_Post {
		// CRITICAL: Wrap in try-catch to prevent fatal errors
		try {
			// Only in admin when editing
			if ( ! is_admin() || ! isset( $_GET['action'] ) || $_GET['action'] !== 'edit' ) {
				return $post;
			}

			$page_on_front_id = HomepageHelper::get_homepage_id();
			if ( $page_on_front_id === 0 ) {
				return $post;
			}

			$requested_post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
			if ( $requested_post_id !== $page_on_front_id ) {
				return $post;
			}

			// If we're requesting the homepage, always return it
			if ( $post_id === $page_on_front_id ) {
				$homepage = get_post( $page_on_front_id );
				if ( $homepage instanceof WP_Post ) {
					return $homepage;
				}
			}

			// If WordPress returned wrong post (e.g., auto-draft or nectar_slider), replace with homepage
			if ( $post instanceof WP_Post && $post->ID !== $page_on_front_id ) {
				$homepage = get_post( $page_on_front_id );
				if ( $homepage instanceof WP_Post ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						Logger::info( 'HomepageProtection::filter_get_post_for_homepage - Replaced wrong post with homepage', array(
							'wrong_post_id' => $post->ID,
							'wrong_post_type' => $post->post_type,
							'homepage_id' => $page_on_front_id,
						) );
					}
					return $homepage;
				}
			}

			return $post;
		} catch ( \Throwable $e ) {
			// Log error but don't break WordPress - return original post
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::error( 'FP SEO: Error in filter_get_post_for_homepage', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				) );
			}
			return $post;
		}
	}

	/**
	 * Diagnose and delete auto-drafts created while editing homepage.
	 * Hook: wp_insert_post (priority 999)
	 *
	 * @param int      $post_id Post ID.
	 * @param WP_Post  $post    Post object.
	 * @param bool     $update  Whether this is an update.
	 */
	public function diagnose_auto_draft_creation( int $post_id, $post, bool $update ): void {
		// CRITICAL: Wrap in try-catch to prevent fatal errors
		try {
			// Only check if we're editing homepage
			if ( ! is_admin() || ! isset( $_GET['post'] ) || ! isset( $_GET['action'] ) || $_GET['action'] !== 'edit' ) {
				return;
			}

			$requested_post_id = (int) $_GET['post'];
			$page_on_front_id = HomepageHelper::get_homepage_id();

			if ( $page_on_front_id === 0 || $requested_post_id !== $page_on_front_id ) {
				return;
			}

			// If a new auto-draft was created while editing homepage, delete it
			if ( ! $update && $post instanceof WP_Post && $post->post_status === 'auto-draft' ) {
				// Don't delete if it's actually the homepage
				if ( $post_id === $page_on_front_id ) {
					return;
				}

				// Delete the auto-draft (wrapped in try-catch to prevent FP-Multilanguage errors)
				try {
					wp_delete_post( $post_id, true );
				} catch ( \Throwable $e ) {
					// Silently fail if deletion causes errors (e.g., FP-Multilanguage conflicts)
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						Logger::warning( 'FP SEO: Failed to delete auto-draft in diagnose_auto_draft_creation', array(
							'auto_draft_id' => $post_id,
							'error' => $e->getMessage(),
						) );
					}
					return; // Exit early if deletion fails
				}

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::warning( 'HomepageProtection::diagnose_auto_draft_creation - Auto-draft created while editing homepage, deleted it', array(
						'auto_draft_id' => $post_id,
						'auto_draft_type' => $post->post_type ?? 'unknown',
						'homepage_id' => $page_on_front_id,
					) );
				}
			}
		} catch ( \Throwable $e ) {
			// Log error but don't break WordPress
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::error( 'FP SEO: Error in diagnose_auto_draft_creation', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				) );
			}
		}
	}

	/**
	 * Correct homepage post object if WordPress passed wrong one.
	 *
	 * @param WP_Post $post Post object passed to metabox.
	 * @return WP_Post Corrected post object.
	 */
	public function correct_homepage_post( WP_Post $post ): WP_Post {
		$requested_post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		$page_on_front_id = HomepageHelper::get_homepage_id();

		if ( $page_on_front_id === 0 || $requested_post_id !== $page_on_front_id ) {
			return $post;
		}

		// If WordPress passed wrong post, correct it
		if ( $post->ID !== $page_on_front_id ) {
			$correct_post = get_post( $page_on_front_id );
			if ( $correct_post instanceof WP_Post ) {
				// Update global post object
				global $wp_query;
				$GLOBALS['post'] = $correct_post;
				if ( isset( $wp_query ) ) {
					$wp_query->post = $correct_post;
				}

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::warning( 'HomepageProtection::correct_homepage_post - Corrected wrong post', array(
						'requested_post_id' => $requested_post_id,
						'wrong_post_id' => $post->ID,
						'wrong_post_type' => $post->post_type,
						'correct_post_id' => $correct_post->ID,
					) );
				}

				return $correct_post;
			}
		}

		return $post;
	}

	/**
	 * Ensure homepage status is correct during save.
	 *
	 * @param int $post_id Post ID.
	 */
	public function ensure_homepage_status( int $post_id ): void {
		$page_on_front_id = HomepageHelper::get_homepage_id();
		if ( $page_on_front_id === 0 || $post_id !== $page_on_front_id ) {
			return;
		}

		$current_status = get_post_status( $post_id );
		if ( $current_status === 'auto-draft' ) {
			$original_status = get_post_meta( $post_id, '_fp_seo_original_status', true );
			$new_status = ! empty( $original_status ) && $original_status !== 'auto-draft' ? $original_status : 'publish';

			global $wpdb;
			$wpdb->update(
				$wpdb->posts,
				array( 'post_status' => $new_status ),
				array( 'ID' => $post_id ),
				array( '%s' ),
				array( '%d' )
			);
			clean_post_cache( $post_id );
			wp_cache_delete( $post_id, 'posts' );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::warning( 'HomepageProtection::ensure_homepage_status - Corrected homepage status from auto-draft', array(
					'post_id' => $post_id,
					'new_status' => $new_status,
				) );
			}
		}
	}

	/**
	 * Prevent homepage from becoming auto-draft via wp_insert_post_data filter.
	 * Hook: wp_insert_post_data (priority 999)
	 *
	 * @param array $data    Post data.
	 * @param array $postarr Post array.
	 * @return array Modified post data.
	 */
	public function prevent_homepage_auto_draft_data( array $data, array $postarr ): array {
		// CRITICAL: Wrap in try-catch to prevent fatal errors
		try {
			// Only check if this is the homepage
			$page_on_front_id = HomepageHelper::get_homepage_id();
			if ( $page_on_front_id === 0 ) {
				return $data; // Not using static homepage
			}

			// Check if this post is the homepage
			$post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
			if ( $post_id === 0 ) {
				// New post - check if it's being created as homepage
				// This shouldn't happen, but just in case
				return $data;
			}

			if ( $post_id !== $page_on_front_id ) {
				return $data; // Not the homepage
			}

			// This is the homepage - prevent it from becoming auto-draft
			if ( isset( $data['post_status'] ) && $data['post_status'] === 'auto-draft' ) {
				// Get current status from database to preserve it
				$current_status = get_post_status( $post_id );
				if ( $current_status && $current_status !== 'auto-draft' ) {
					$data['post_status'] = $current_status;
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						Logger::warning( 'HomepageProtection::prevent_homepage_auto_draft_data - Prevented homepage from becoming auto-draft', array(
							'post_id' => $post_id,
							'original_status' => $current_status,
							'attempted_status' => 'auto-draft',
						) );
					}
				} else {
					// Fallback to publish if current status is also auto-draft
					$data['post_status'] = 'publish';
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						Logger::warning( 'HomepageProtection::prevent_homepage_auto_draft_data - Forced homepage to publish (was auto-draft)', array(
							'post_id' => $post_id,
						) );
					}
				}
			}

			return $data;
		} catch ( \Throwable $e ) {
			// Log error but don't break WordPress - return original data
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::error( 'FP SEO: Error in prevent_homepage_auto_draft_data', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				) );
			}
			return $data;
		}
	}

	/**
	 * Delete auto-drafts immediately when created while editing homepage.
	 * Hook: wp_insert_post (priority 999)
	 *
	 * @param int      $post_id Post ID.
	 * @param WP_Post  $post    Post object.
	 * @param bool     $update  Whether this is an update.
	 */
	public function delete_auto_draft_on_homepage_edit( int $post_id, $post, bool $update ): void {
		// CRITICAL: Wrap in try-catch to prevent fatal errors
		try {
			// Only check if we're editing homepage
			if ( ! is_admin() || ! isset( $_GET['post'] ) || ! isset( $_GET['action'] ) || $_GET['action'] !== 'edit' ) {
				return;
			}

			$requested_post_id = (int) $_GET['post'];
			$page_on_front_id = HomepageHelper::get_homepage_id();

			if ( $page_on_front_id === 0 || $requested_post_id !== $page_on_front_id ) {
				return;
			}

			// If a new auto-draft was created while editing homepage, delete it immediately
			if ( ! $update && $post instanceof WP_Post && $post->post_status === 'auto-draft' ) {
				// Don't delete if it's actually the homepage
				if ( $post_id === $page_on_front_id ) {
					return;
				}

				// Delete the auto-draft immediately (wrapped in try-catch to prevent FP-Multilanguage errors)
				try {
					wp_delete_post( $post_id, true );
				} catch ( \Throwable $e ) {
					// Silently fail if deletion causes errors (e.g., FP-Multilanguage conflicts)
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						Logger::warning( 'FP SEO: Failed to delete auto-draft in delete_auto_draft_on_homepage_edit', array(
							'auto_draft_id' => $post_id,
							'error' => $e->getMessage(),
						) );
					}
					return; // Exit early if deletion fails
				}

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::warning( 'HomepageProtection::delete_auto_draft_on_homepage_edit - Auto-draft created while editing homepage, deleted immediately', array(
						'auto_draft_id' => $post_id,
						'auto_draft_type' => $post->post_type ?? 'unknown',
						'homepage_id' => $page_on_front_id,
					) );
				}
			}
		} catch ( \Throwable $e ) {
			// Log error but don't break WordPress
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::error( 'FP SEO: Error in delete_auto_draft_on_homepage_edit', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				) );
			}
		}
	}

	/**
	 * Delete auto-drafts created by current user.
	 */
	private function delete_user_auto_drafts(): void {
		if ( ! HomepageHelper::is_homepage_configured() ) {
			return;
		}
		$page_on_front_id = HomepageHelper::get_homepage_id();

		$user_id = get_current_user_id();
		if ( $user_id === 0 ) {
			return;
		}

		global $wpdb;
		$auto_drafts = $wpdb->get_col( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} 
			WHERE post_status = 'auto-draft' 
			AND post_type = 'page' 
			AND post_author = %d 
			AND ID != %d
			ORDER BY ID DESC 
			LIMIT 10",
			$user_id,
			$page_on_front_id
		) );

		foreach ( $auto_drafts as $auto_draft_id ) {
			try {
				wp_delete_post( (int) $auto_draft_id, true );
			} catch ( \Throwable $e ) {
				// Silently fail if deletion causes errors (e.g., FP-Multilanguage conflicts)
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::warning( 'FP SEO: Failed to delete auto-draft in delete_user_auto_drafts', array(
						'auto_draft_id' => $auto_draft_id,
						'error' => $e->getMessage(),
					) );
				}
			}
		}

		if ( ! empty( $auto_drafts ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'HomepageProtection::delete_user_auto_drafts - Deleted auto-drafts', array(
				'count' => count( $auto_drafts ),
				'ids' => $auto_drafts,
			) );
		}
	}

	/**
	 * Fix homepage title in editor if it shows "Bozza automatica".
	 * Hook: the_title (priority 999)
	 *
	 * @param string $title Post title.
	 * @param int    $post_id Post ID.
	 * @return string Corrected title.
	 */
	public function fix_homepage_title_in_editor( string $title, int $post_id ): string {
		// CRITICAL: Wrap in try-catch to prevent fatal errors
		try {
			// Only in admin and when editing
			if ( ! is_admin() || ! isset( $_GET['post'] ) || ! isset( $_GET['action'] ) || $_GET['action'] !== 'edit' ) {
				return $title;
			}

			$page_on_front_id = HomepageHelper::get_homepage_id();
			if ( $page_on_front_id === 0 || $post_id !== $page_on_front_id ) {
				return $title;
			}

			// If title is "Bozza automatica", get correct title from database
			if ( $title === 'Bozza automatica' || $title === 'Auto Draft' ) {
				$homepage = get_post( $page_on_front_id );
				if ( $homepage instanceof WP_Post && $homepage->post_title !== 'Bozza automatica' && $homepage->post_title !== 'Auto Draft' ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						Logger::info( 'HomepageProtection::fix_homepage_title_in_editor - Fixed title', array(
							'old_title' => $title,
							'new_title' => $homepage->post_title,
						) );
					}
					return $homepage->post_title;
				}
			}

			return $title;
		} catch ( \Throwable $e ) {
			// Log error but don't break WordPress - return original title
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::error( 'FP SEO: Error in fix_homepage_title_in_editor', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				) );
			}
			return $title;
		}
	}
}

