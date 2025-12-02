<?php
/**
 * Renderer for Metabox Diagnostics
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Renderers;

use WP_Post;
use function array_slice;
use function count;
use function esc_html;

/**
 * Renders diagnostic HTML for metabox issues
 */
class MetaboxDiagnosticsRenderer {

	/**
	 * Render diagnostic HTML
	 *
	 * @param array<string, mixed> $diagnostics Diagnostic data.
	 * @return string HTML output.
	 */
	public function render( array $diagnostics ): string {
		if ( empty( $diagnostics ) ) {
			return '';
		}

		$post = isset( $diagnostics['post'] ) && $diagnostics['post'] instanceof WP_Post ? $diagnostics['post'] : null;
		if ( ! $post instanceof WP_Post ) {
			return '';
		}

		ob_start();
		$this->render_header();
		$this->render_base_info( $diagnostics );
		$this->render_auto_drafts( $diagnostics );
		$this->render_active_hooks( $diagnostics );
		$this->render_footer();
		return ob_get_clean();
	}

	/**
	 * Render diagnostic header
	 *
	 * @return void
	 */
	private function render_header(): void {
		?>
		<div class="notice notice-warning" style="border-left-color: #f59e0b; padding: 15px; margin: 20px 0; max-width: 95%;">
			<h3 style="margin: 0 0 12px 0; color: #f59e0b; font-size: 16px;">🔍 FP SEO: Diagnostica Completa Homepage</h3>
		<?php
	}

	/**
	 * Render base information section
	 *
	 * @param array<string, mixed> $diagnostics Diagnostic data.
	 * @return void
	 */
	private function render_base_info( array $diagnostics ): void {
		$requested_post_id = $diagnostics['requested_post_id'] ?? 0;
		$post = $diagnostics['post'] ?? null;
		$global_post_id = $diagnostics['global_post_id'] ?? 0;
		$global_post_status = $diagnostics['global_post_status'] ?? 'unknown';
		$homepage_from_db = $diagnostics['homepage_from_db'] ?? array();
		$post_from_get_post = $diagnostics['post_from_get_post'] ?? null;
		$post_from_get_post_edit = $diagnostics['post_from_get_post_edit'] ?? null;
		$post_lock = $diagnostics['post_lock'] ?? false;
		$post_lock_user = $diagnostics['post_lock_user'] ?? null;
		$autosave = $diagnostics['autosave'] ?? null;

		?>
		<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 15px 0;">
			<div style="background: #f9fafb; padding: 12px; border-radius: 4px;">
				<h4 style="margin: 0 0 8px 0; font-size: 14px; color: #374151;">📋 Informazioni Base</h4>
				<ul style="margin: 0; padding-left: 20px; font-size: 12px; line-height: 1.8;">
					<li><strong>URL richiesto:</strong> <code>post=<?php echo esc_html( (string) $requested_post_id ); ?>&action=edit</code></li>
					<li><strong>Post ricevuto (render):</strong> ID <?php echo esc_html( ( $post instanceof WP_Post ) ? (string) $post->ID : 'N/A' ); ?> - Status: <code><?php echo esc_html( ( $post instanceof WP_Post ) ? $post->post_status : 'N/A' ); ?></code> - Type: <code><?php echo esc_html( ( $post instanceof WP_Post ) ? $post->post_type : 'N/A' ); ?></code></li>
					<li><strong>Post globale:</strong> ID <?php echo esc_html( (string) $global_post_id ); ?> - Status: <code><?php echo esc_html( $global_post_status ); ?></code></li>
					<li><strong>Homepage (DB diretto):</strong> ID <?php echo esc_html( (string) ( $homepage_from_db['ID'] ?? 'N/A' ) ); ?> - Status: <code><?php echo esc_html( $homepage_from_db['post_status'] ?? 'N/A' ); ?></code></li>
					<li><strong>get_post(ID):</strong> ID <?php echo esc_html( ( $post_from_get_post instanceof WP_Post ) ? (string) $post_from_get_post->ID : 'N/A' ); ?> - Status: <code><?php echo esc_html( ( $post_from_get_post instanceof WP_Post ) ? $post_from_get_post->post_status : 'N/A' ); ?></code></li>
					<li><strong>get_post(ID, 'edit'):</strong> ID <?php echo esc_html( ( $post_from_get_post_edit instanceof WP_Post ) ? (string) $post_from_get_post_edit->ID : 'N/A' ); ?> - Status: <code><?php echo esc_html( ( $post_from_get_post_edit instanceof WP_Post ) ? $post_from_get_post_edit->post_status : 'N/A' ); ?></code></li>
					<li><strong>Post Lock:</strong> <?php echo $post_lock ? 'Sì (User ID: ' . esc_html( (string) $post_lock ) . ' - ' . esc_html( $post_lock_user->user_login ?? 'unknown' ) . ')' : 'No'; ?></li>
					<li><strong>Autosave:</strong> <?php echo $autosave ? 'Sì (ID: ' . esc_html( (string) $autosave->ID ) . ')' : 'No'; ?></li>
				</ul>
			</div>
		<?php
	}

	/**
	 * Render auto-drafts section
	 *
	 * @param array<string, mixed> $diagnostics Diagnostic data.
	 * @return void
	 */
	private function render_auto_drafts( array $diagnostics ): void {
		$auto_drafts_in_db = $diagnostics['auto_drafts_in_db'] ?? array();
		?>
		<div style="background: #fef3c7; padding: 12px; border-radius: 4px;">
			<h4 style="margin: 0 0 8px 0; font-size: 14px; color: #92400e;">🗄️ Auto-Draft nel Database</h4>
			<?php if ( empty( $auto_drafts_in_db ) ) { ?>
				<p style="margin: 0; font-size: 12px; color: #10b981;">✓ Nessun auto-draft trovato</p>
			<?php } else { ?>
				<p style="margin: 0 0 8px 0; font-size: 12px; color: #dc2626;"><strong>⚠️ Trovati <?php echo esc_html( (string) count( $auto_drafts_in_db ) ); ?> auto-draft:</strong></p>
				<ul style="margin: 0; padding-left: 20px; font-size: 11px; line-height: 1.6; max-height: 200px; overflow-y: auto;">
					<?php foreach ( array_slice( $auto_drafts_in_db, 0, 10 ) as $ad ) { ?>
						<li>ID <?php echo esc_html( (string) $ad['ID'] ); ?> - Creato: <?php echo esc_html( $ad['post_date'] ?? '' ); ?></li>
					<?php } ?>
				</ul>
			<?php } ?>
		</div>
		</div>
		<?php
	}

	/**
	 * Render active hooks section
	 *
	 * @param array<string, mixed> $diagnostics Diagnostic data.
	 * @return void
	 */
	private function render_active_hooks( array $diagnostics ): void {
		$wp_insert_post_hooks = $diagnostics['wp_insert_post_hooks'] ?? array();
		$save_post_hooks = $diagnostics['save_post_hooks'] ?? array();
		$get_post_filters = $diagnostics['get_post_filters'] ?? array();
		?>
		<div style="margin: 15px 0; background: #eff6ff; padding: 12px; border-radius: 4px;">
			<h4 style="margin: 0 0 8px 0; font-size: 14px; color: #1e40af;">🔗 Hook Attivi</h4>
			<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; font-size: 11px;">
				<div>
					<strong>wp_insert_post (<?php echo esc_html( (string) count( $wp_insert_post_hooks ) ); ?>):</strong>
					<ul style="margin: 4px 0 0 0; padding-left: 18px; line-height: 1.5; max-height: 150px; overflow-y: auto;">
						<?php foreach ( array_slice( $wp_insert_post_hooks, 0, 10 ) as $hook ) { ?>
							<li>P<?php echo esc_html( (string) $hook['priority'] ); ?>: <?php echo esc_html( $hook['callback'] ?? '' ); ?></li>
						<?php } ?>
					</ul>
				</div>
				<div>
					<strong>save_post (<?php echo esc_html( (string) count( $save_post_hooks ) ); ?>):</strong>
					<ul style="margin: 4px 0 0 0; padding-left: 18px; line-height: 1.5; max-height: 150px; overflow-y: auto;">
						<?php foreach ( array_slice( $save_post_hooks, 0, 10 ) as $hook ) { ?>
							<li>P<?php echo esc_html( (string) $hook['priority'] ); ?>: <?php echo esc_html( $hook['callback'] ?? '' ); ?></li>
						<?php } ?>
					</ul>
				</div>
				<div>
					<strong>get_post filter (<?php echo esc_html( (string) count( $get_post_filters ) ); ?>):</strong>
					<ul style="margin: 4px 0 0 0; padding-left: 18px; line-height: 1.5; max-height: 150px; overflow-y: auto;">
						<?php foreach ( array_slice( $get_post_filters, 0, 10 ) as $hook ) { ?>
							<li>P<?php echo esc_html( (string) $hook['priority'] ); ?>: <?php echo esc_html( $hook['callback'] ?? '' ); ?></li>
						<?php } ?>
					</ul>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render diagnostic footer
	 *
	 * @return void
	 */
	private function render_footer(): void {
		?>
		</div>
		<?php
	}
}

