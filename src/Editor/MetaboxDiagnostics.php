<?php
/**
 * Diagnostics for metabox issues.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor;

use FP\SEO\Editor\Renderers\MetaboxDiagnosticsRenderer;
use WP_Post;
use function get_current_screen;
use function get_current_user_id;
use function get_option;
use function get_post;
use function get_post_status;
use function get_userdata;
use function wp_check_post_lock;
use function wp_get_post_autosave;

/**
 * Handles diagnostic information display for metabox issues.
 */
class MetaboxDiagnostics {

	/**
	 * @var MetaboxDiagnosticsRenderer|null
	 */
	private $renderer;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->renderer = new MetaboxDiagnosticsRenderer();
	}
	/**
	 * Get comprehensive diagnostic information for homepage editing.
	 *
	 * @param WP_Post $post Post object.
	 * @return array Diagnostic data.
	 */
	public function get_homepage_diagnostics( WP_Post $post ): array {
		$requested_post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		$page_on_front_id = (int) get_option( 'page_on_front' );

		if ( $page_on_front_id === 0 || $requested_post_id !== $page_on_front_id ) {
			return array();
		}

		global $wpdb, $wp_filter;

		// Get all auto-drafts in database
		$auto_drafts_in_db = $wpdb->get_results( $wpdb->prepare(
			"SELECT ID, post_title, post_author, post_date, post_date_gmt, post_modified, post_modified_gmt 
			FROM {$wpdb->posts} 
			WHERE post_type = 'page' AND post_status = 'auto-draft' 
			AND post_author = %d 
			ORDER BY ID DESC 
			LIMIT 20",
			get_current_user_id()
		), ARRAY_A );

		// Get homepage from DB directly
		$homepage_from_db = $wpdb->get_row( $wpdb->prepare(
			"SELECT ID, post_title, post_status, post_type, post_content, post_modified 
			FROM {$wpdb->posts} 
			WHERE ID = %d",
			$page_on_front_id
		), ARRAY_A );

		// Get active hooks
		$wp_insert_post_hooks = $this->get_hooks( 'wp_insert_post', $wp_filter );
		$save_post_hooks = $this->get_hooks( 'save_post', $wp_filter );
		$get_post_filters = $this->get_hooks( 'get_post', $wp_filter );

		// Get post object from multiple sources
		$post_from_get_post = get_post( $page_on_front_id );
		$post_from_get_post_edit = get_post( $page_on_front_id, OBJECT, 'edit' );

		// Check post lock
		$post_lock = wp_check_post_lock( $page_on_front_id );
		$post_lock_user = $post_lock ? get_userdata( $post_lock ) : null;

		// Check autosave
		$autosave = wp_get_post_autosave( $page_on_front_id );

		// Get current screen info
		$current_screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$screen_id = $current_screen ? $current_screen->id : 'unknown';
		$screen_base = $current_screen ? $current_screen->base : 'unknown';

		// Get global post
		global $wp_query;
		$global_post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$global_post_id = $global_post instanceof WP_Post ? $global_post->ID : 0;
		$global_post_status = $global_post instanceof WP_Post ? $global_post->post_status : 'none';

		$correct_post = get_post( $page_on_front_id );
		$correct_status = $correct_post instanceof WP_Post ? $correct_post->post_status : 'unknown';
		$is_wrong_post = $post->ID !== $page_on_front_id || $post->post_status === 'auto-draft';

		$is_ajax = defined( 'DOING_AJAX' ) && DOING_AJAX;
		$is_autosave = defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE;

		return array(
			'requested_post_id' => $requested_post_id,
			'post' => $post,
			'page_on_front_id' => $page_on_front_id,
			'correct_status' => $correct_status,
			'is_wrong_post' => $is_wrong_post,
			'global_post_id' => $global_post_id,
			'global_post_status' => $global_post_status,
			'is_ajax' => $is_ajax,
			'is_autosave' => $is_autosave,
			'screen_id' => $screen_id,
			'screen_base' => $screen_base,
			'auto_drafts_in_db' => $auto_drafts_in_db,
			'homepage_from_db' => $homepage_from_db,
			'wp_insert_post_hooks' => $wp_insert_post_hooks,
			'save_post_hooks' => $save_post_hooks,
			'get_post_filters' => $get_post_filters,
			'post_from_get_post' => $post_from_get_post,
			'post_from_get_post_edit' => $post_from_get_post_edit,
			'post_lock' => $post_lock,
			'post_lock_user' => $post_lock_user,
			'autosave' => $autosave,
		);
	}

	/**
	 * Render diagnostic HTML.
	 *
	 * @param array<string, mixed> $diagnostics Diagnostic data.
	 * @return string HTML output.
	 */
	public function render_diagnostics( array $diagnostics ): string {
		if ( empty( $diagnostics ) || ! $this->renderer ) {
			return '';
		}

		return $this->renderer->render( $diagnostics );
	}

	/**
	 * Get hooks for a specific filter/action.
	 *
	 * @param string $hook_name Hook name.
	 * @param array  $wp_filter Global wp_filter array.
	 * @return array List of hooks.
	 */
	private function get_hooks( string $hook_name, array $wp_filter ): array {
		$hooks = array();
		if ( isset( $wp_filter[ $hook_name ] ) ) {
			foreach ( $wp_filter[ $hook_name ]->callbacks as $priority => $hook_callbacks ) {
				foreach ( $hook_callbacks as $hook ) {
					$callback = is_array( $hook['function'] )
						? ( is_object( $hook['function'][0] ) ? get_class( $hook['function'][0] ) . '::' . $hook['function'][1] : 'array' )
						: ( is_string( $hook['function'] ) ? $hook['function'] : 'closure' );
					$hooks[] = array(
						'priority' => $priority,
						'callback' => $callback,
					);
				}
			}
		}
		return $hooks;
	}
}

