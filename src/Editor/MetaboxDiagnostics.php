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
	 * @param array $diagnostics Diagnostic data.
	 * @return string HTML output.
	 */
	public function render_diagnostics( array $diagnostics ): string {
		if ( empty( $diagnostics ) ) {
			return '';
		}

		// Extract variables safely
		$requested_post_id = isset( $diagnostics['requested_post_id'] ) ? $diagnostics['requested_post_id'] : 0;
		$post = isset( $diagnostics['post'] ) && $diagnostics['post'] instanceof WP_Post ? $diagnostics['post'] : null;
		$page_on_front_id = isset( $diagnostics['page_on_front_id'] ) ? $diagnostics['page_on_front_id'] : 0;
		$global_post_id = isset( $diagnostics['global_post_id'] ) ? $diagnostics['global_post_id'] : 0;
		$global_post_status = isset( $diagnostics['global_post_status'] ) ? $diagnostics['global_post_status'] : 'unknown';
		$homepage_from_db = isset( $diagnostics['homepage_from_db'] ) ? $diagnostics['homepage_from_db'] : array();
		$post_from_get_post = isset( $diagnostics['post_from_get_post'] ) ? $diagnostics['post_from_get_post'] : null;
		$post_from_get_post_edit = isset( $diagnostics['post_from_get_post_edit'] ) ? $diagnostics['post_from_get_post_edit'] : null;
		$post_lock = isset( $diagnostics['post_lock'] ) ? $diagnostics['post_lock'] : false;
		$post_lock_user = isset( $diagnostics['post_lock_user'] ) ? $diagnostics['post_lock_user'] : null;
		$autosave = isset( $diagnostics['autosave'] ) ? $diagnostics['autosave'] : null;
		$auto_drafts_in_db = isset( $diagnostics['auto_drafts_in_db'] ) ? $diagnostics['auto_drafts_in_db'] : array();
		$wp_insert_post_hooks = isset( $diagnostics['wp_insert_post_hooks'] ) ? $diagnostics['wp_insert_post_hooks'] : array();
		$save_post_hooks = isset( $diagnostics['save_post_hooks'] ) ? $diagnostics['save_post_hooks'] : array();
		$get_post_filters = isset( $diagnostics['get_post_filters'] ) ? $diagnostics['get_post_filters'] : array();

		if ( ! $post instanceof WP_Post ) {
			return '';
		}

		ob_start();
		?>
		<div class="notice notice-warning" style="border-left-color: #f59e0b; padding: 15px; margin: 20px 0; max-width: 95%;">
			<h3 style="margin: 0 0 12px 0; color: #f59e0b; font-size: 16px;">🔍 FP SEO: Diagnostica Completa Homepage</h3>
			
			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 15px 0;">
				<div style="background: #f9fafb; padding: 12px; border-radius: 4px;">
					<h4 style="margin: 0 0 8px 0; font-size: 14px; color: #374151;">📋 Informazioni Base</h4>
					<ul style="margin: 0; padding-left: 20px; font-size: 12px; line-height: 1.8;">
						<li><strong>URL richiesto:</strong> <code>post=<?php echo esc_html( $requested_post_id ); ?>&action=edit</code></li>
						<li><strong>Post ricevuto (render):</strong> ID <?php echo esc_html( ( $post instanceof WP_Post ) ? $post->ID : 'N/A' ); ?> - Status: <code><?php echo esc_html( ( $post instanceof WP_Post ) ? $post->post_status : 'N/A' ); ?></code> - Type: <code><?php echo esc_html( ( $post instanceof WP_Post ) ? $post->post_type : 'N/A' ); ?></code></li>
						<li><strong>Post globale:</strong> ID <?php echo esc_html( $global_post_id ); ?> - Status: <code><?php echo esc_html( $global_post_status ); ?></code></li>
						<li><strong>Homepage (DB diretto):</strong> ID <?php echo esc_html( $homepage_from_db['ID'] ?? 'N/A' ); ?> - Status: <code><?php echo esc_html( $homepage_from_db['post_status'] ?? 'N/A' ); ?></code></li>
						<li><strong>get_post(ID):</strong> ID <?php echo esc_html( ( $post_from_get_post instanceof WP_Post ) ? $post_from_get_post->ID : 'N/A' ); ?> - Status: <code><?php echo esc_html( ( $post_from_get_post instanceof WP_Post ) ? $post_from_get_post->post_status : 'N/A' ); ?></code></li>
						<li><strong>get_post(ID, 'edit'):</strong> ID <?php echo esc_html( ( $post_from_get_post_edit instanceof WP_Post ) ? $post_from_get_post_edit->ID : 'N/A' ); ?> - Status: <code><?php echo esc_html( ( $post_from_get_post_edit instanceof WP_Post ) ? $post_from_get_post_edit->post_status : 'N/A' ); ?></code></li>
						<li><strong>Post Lock:</strong> <?php echo $post_lock ? 'Sì (User ID: ' . esc_html( $post_lock ) . ' - ' . esc_html( $post_lock_user->user_login ?? 'unknown' ) . ')' : 'No'; ?></li>
						<li><strong>Autosave:</strong> <?php echo $autosave ? 'Sì (ID: ' . esc_html( $autosave->ID ) . ')' : 'No'; ?></li>
					</ul>
				</div>
				
				<div style="background: #fef3c7; padding: 12px; border-radius: 4px;">
					<h4 style="margin: 0 0 8px 0; font-size: 14px; color: #92400e;">🗄️ Auto-Draft nel Database</h4>
					<?php if ( empty( $auto_drafts_in_db ) ) { ?>
						<p style="margin: 0; font-size: 12px; color: #10b981;">✓ Nessun auto-draft trovato</p>
					<?php } else { ?>
						<p style="margin: 0 0 8px 0; font-size: 12px; color: #dc2626;"><strong>⚠️ Trovati <?php echo count( $auto_drafts_in_db ); ?> auto-draft:</strong></p>
						<ul style="margin: 0; padding-left: 20px; font-size: 11px; line-height: 1.6; max-height: 200px; overflow-y: auto;">
							<?php foreach ( array_slice( $auto_drafts_in_db, 0, 10 ) as $ad ) { ?>
								<li>ID <?php echo esc_html( $ad['ID'] ); ?> - Creato: <?php echo esc_html( $ad['post_date'] ); ?></li>
							<?php } ?>
						</ul>
					<?php } ?>
				</div>
			</div>
			
			<div style="margin: 15px 0; background: #eff6ff; padding: 12px; border-radius: 4px;">
				<h4 style="margin: 0 0 8px 0; font-size: 14px; color: #1e40af;">🔗 Hook Attivi</h4>
				<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; font-size: 11px;">
					<div>
						<strong>wp_insert_post (<?php echo count( $wp_insert_post_hooks ); ?>):</strong>
						<ul style="margin: 4px 0 0 0; padding-left: 18px; line-height: 1.5; max-height: 150px; overflow-y: auto;">
							<?php foreach ( array_slice( $wp_insert_post_hooks, 0, 10 ) as $hook ) { ?>
								<li>P<?php echo esc_html( $hook['priority'] ); ?>: <?php echo esc_html( $hook['callback'] ); ?></li>
							<?php } ?>
						</ul>
					</div>
					<div>
						<strong>save_post (<?php echo count( $save_post_hooks ); ?>):</strong>
						<ul style="margin: 4px 0 0 0; padding-left: 18px; line-height: 1.5; max-height: 150px; overflow-y: auto;">
							<?php foreach ( array_slice( $save_post_hooks, 0, 10 ) as $hook ) { ?>
								<li>P<?php echo esc_html( $hook['priority'] ); ?>: <?php echo esc_html( $hook['callback'] ); ?></li>
							<?php } ?>
						</ul>
					</div>
					<div>
						<strong>get_post filter (<?php echo count( $get_post_filters ); ?>):</strong>
						<ul style="margin: 4px 0 0 0; padding-left: 18px; line-height: 1.5; max-height: 150px; overflow-y: auto;">
							<?php foreach ( array_slice( $get_post_filters, 0, 10 ) as $hook ) { ?>
								<li>P<?php echo esc_html( $hook['priority'] ); ?>: <?php echo esc_html( $hook['callback'] ); ?></li>
							<?php } ?>
						</ul>
					</div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
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

