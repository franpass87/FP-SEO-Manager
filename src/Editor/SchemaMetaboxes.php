<?php
/**
 * Schema Metaboxes for FAQ and HowTo Schema
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor;

use FP\SEO\Editor\Scripts\SchemaMetaboxesScriptsManager;
use FP\SEO\Editor\Styles\SchemaMetaboxesStylesManager;
use function wp_unslash;

/**
 * Handles HowTo Schema metabox in the editor.
 * FAQ Schema is auto-generated from Q&A Pairs (see AIRenderer).
 */
class SchemaMetaboxes {
	/**
	 * @var SchemaMetaboxesStylesManager|null
	 */
	private $styles_manager;

	/**
	 * @var SchemaMetaboxesScriptsManager|null
	 */
	private $scripts_manager;

	/**
	 * Register hooks.
	 */
	public function register(): void {
		// NOTE: Metaboxes are now integrated into the main SEO Performance metabox
		// No need to register separate metaboxes via add_meta_boxes hook
		
		// CRITICAL: Register hooks ONLY for supported post types to prevent ANY interference
		// CRITICAL: Use priority 20 instead of 10 to ensure we run AFTER WordPress core saves _thumbnail_id
		// WordPress core saves featured image (_thumbnail_id) during save_post with priority 10
		// By using priority 20, we ensure our hooks run after WordPress has finished saving the featured image
		$supported_types = \FP\SEO\Utils\PostTypes::analyzable();
		foreach ( $supported_types as $post_type ) {
			if ( ! has_action( 'save_post_' . $post_type, array( $this, 'save_howto_schema' ) ) ) {
				add_action( 'save_post_' . $post_type, array( $this, 'save_howto_schema' ), 20, 2 );
			}
		}
		
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Initialize and register styles manager
		$this->styles_manager = new SchemaMetaboxesStylesManager();
		$this->styles_manager->register_hooks();
		
		// Initialize scripts manager (will be used in render methods)
		$this->scripts_manager = new SchemaMetaboxesScriptsManager();
	}

	/**
	 * Render HowTo Schema metabox.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public function render_howto_metabox( \WP_Post $post ): void {
		wp_nonce_field( 'fp_seo_howto_schema_nonce', 'fp_seo_howto_schema_nonce' );

		// CRITICAL: Cache clearing disabled to prevent interference with featured image (_thumbnail_id)
		// WordPress handles cache management automatically - no manual clearing needed
		// Clearing cache can interfere with WordPress core operations including _thumbnail_id

		$howto_data = get_post_meta( $post->ID, '_fp_seo_howto', true );
		
		// Fallback: query diretta al database se get_post_meta restituisce vuoto
		if ( empty( $howto_data ) ) {
			global $wpdb;
			$db_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", $post->ID, '_fp_seo_howto' ) );
			if ( $db_value !== null ) {
				$unserialized = maybe_unserialize( $db_value );
				$howto_data = is_array( $unserialized ) ? $unserialized : array();
			}
		}
		
		if ( ! is_array( $howto_data ) ) {
			$howto_data = array(
				'name'        => '',
				'description' => '',
				'total_time'  => '',
				'steps'       => array(),
			);
		}

		$steps = $howto_data['steps'] ?? array();
	?>
<div class="fp-seo-schema-metabox">
	<!-- Banner removed - now shown in main metabox section -->

			<div class="fp-seo-howto-header">
				<div class="fp-seo-form-group">
					<label>
						<strong><?php esc_html_e( 'Titolo della Guida', 'fp-seo-performance' ); ?></strong>
					</label>
					<input 
						type="text" 
						name="fp_seo_howto[name]" 
						value="<?php echo esc_attr( $howto_data['name'] ?? '' ); ?>" 
						placeholder="<?php esc_attr_e( 'Lascia vuoto per usare il titolo del post', 'fp-seo-performance' ); ?>"
						class="widefat"
					>
				</div>

				<div class="fp-seo-form-group">
					<label>
						<strong><?php esc_html_e( 'Descrizione della Guida', 'fp-seo-performance' ); ?></strong>
					</label>
					<textarea 
						name="fp_seo_howto[description]" 
						rows="2" 
						placeholder="<?php esc_attr_e( 'Lascia vuoto per usare l\'excerpt del post', 'fp-seo-performance' ); ?>"
						class="widefat"
					><?php echo esc_textarea( $howto_data['description'] ?? '' ); ?></textarea>
				</div>

				<div class="fp-seo-form-group">
					<label>
						<strong><?php esc_html_e( 'Tempo Totale (opzionale)', 'fp-seo-performance' ); ?></strong>
					</label>
					<input 
						type="text" 
						name="fp_seo_howto[total_time]" 
						value="<?php echo esc_attr( $howto_data['total_time'] ?? '' ); ?>" 
						placeholder="<?php esc_attr_e( 'Es: PT30M (30 minuti) o PT1H (1 ora)', 'fp-seo-performance' ); ?>"
						class="regular-text"
					>
					<p class="description">
						<?php esc_html_e( 'Formato ISO 8601: PT30M = 30 minuti, PT1H30M = 1 ora e 30 minuti', 'fp-seo-performance' ); ?>
					</p>
				</div>
			</div>

			<h4><?php esc_html_e( 'Step della Guida', 'fp-seo-performance' ); ?></h4>

			<div id="fp-seo-howto-steps-list" class="fp-seo-howto-steps-list">
				<?php
				if ( ! empty( $steps ) ) {
					foreach ( $steps as $index => $step ) {
						$this->render_howto_step( $index, $step );
					}
				}
				?>
			</div>

			<div style="display: flex; gap: 8px; margin-top: 12px;">
				<button type="button" class="button button-secondary fp-seo-add-step">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'Aggiungi Step', 'fp-seo-performance' ); ?>
				</button>
				<button type="button" class="fp-seo-generate-howto-ai" id="fp-seo-generate-howto-ai">
					<span>🤖</span>
					<span><?php esc_html_e( 'Genera con AI', 'fp-seo-performance' ); ?></span>
				</button>
			</div>

		</div>

		<script type="text/html" id="fp-seo-howto-step-template">
			<?php $this->render_howto_step( '__INDEX__', array( 'name' => '', 'text' => '', 'url' => '' ) ); ?>
		</script>
		
		<script type="text/javascript">
		<?php if ( null !== $this->scripts_manager ) { echo $this->scripts_manager->get_inline_js( $post->ID ); } ?>
		</script>
		<?php
	}

	/**
	 * Render single HowTo step.
	 *
	 * @param int|string     $index Step index.
	 * @param array<string, string> $step  Step data.
	 */
	private function render_howto_step( $index, array $step ): void {
		$name = $step['name'] ?? '';
		$text = $step['text'] ?? '';
		$url  = $step['url'] ?? '';
		?>
		<div class="fp-seo-howto-step" data-index="<?php echo esc_attr( (string) $index ); ?>">
			<div class="fp-seo-howto-step-header">
				<span class="fp-seo-howto-number">
					<span class="dashicons dashicons-list-view"></span>
					<?php esc_html_e( 'Step', 'fp-seo-performance' ); ?> #<span class="step-num"><?php echo esc_html( is_numeric( $index ) ? (string) ( $index + 1 ) : '1' ); ?></span>
				</span>
				<div class="fp-seo-howto-actions">
					<button type="button" class="fp-seo-move-up" title="<?php esc_attr_e( 'Sposta su', 'fp-seo-performance' ); ?>">
						<span class="dashicons dashicons-arrow-up-alt2"></span>
					</button>
					<button type="button" class="fp-seo-move-down" title="<?php esc_attr_e( 'Sposta giù', 'fp-seo-performance' ); ?>">
						<span class="dashicons dashicons-arrow-down-alt2"></span>
					</button>
					<button type="button" class="fp-seo-remove-step" title="<?php esc_attr_e( 'Rimuovi step', 'fp-seo-performance' ); ?>">
						<span class="dashicons dashicons-trash"></span>
					</button>
				</div>
			</div>

			<div class="fp-seo-howto-step-content">
				<div class="fp-seo-form-group">
					<label>
						<strong><?php esc_html_e( 'Nome dello Step', 'fp-seo-performance' ); ?></strong>
						<span class="required">*</span>
					</label>
					<input 
						type="text" 
						name="fp_seo_howto[steps][<?php echo esc_attr( (string) $index ); ?>][name]" 
						value="<?php echo esc_attr( $name ); ?>" 
						placeholder="<?php esc_attr_e( 'Es: Installa il plugin', 'fp-seo-performance' ); ?>"
						class="widefat"
						required
					>
				</div>

				<div class="fp-seo-form-group">
					<label>
						<strong><?php esc_html_e( 'Descrizione dello Step', 'fp-seo-performance' ); ?></strong>
						<span class="required">*</span>
					</label>
					<textarea 
						name="fp_seo_howto[steps][<?php echo esc_attr( (string) $index ); ?>][text]" 
						rows="3" 
						placeholder="<?php esc_attr_e( 'Descrivi in dettaglio come completare questo step...', 'fp-seo-performance' ); ?>"
						class="widefat"
						required
					><?php echo esc_textarea( $text ); ?></textarea>
				</div>

				<div class="fp-seo-form-group">
					<label>
						<strong><?php esc_html_e( 'URL Immagine o Risorsa (opzionale)', 'fp-seo-performance' ); ?></strong>
					</label>
					<input 
						type="url" 
						name="fp_seo_howto[steps][<?php echo esc_attr( (string) $index ); ?>][url]" 
						value="<?php echo esc_attr( $url ); ?>" 
						placeholder="<?php esc_attr_e( 'https://esempio.com/immagine-step.jpg', 'fp-seo-performance' ); ?>"
						class="widefat"
					>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Save HowTo Schema data.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function save_howto_schema( int $post_id, \WP_Post $post ): void {
// CRITICAL: Do NOT interfere if WordPress is handling a native operation
		if ( \FP\SEO\Editor\Helpers\WordPressNativeProtection::is_wordpress_native_operation() ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'FP SEO: SchemaMetaboxes::save_howto_schema BLOCKED - WordPress native operation detected' );
			}
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
		
		// Security checks
		if ( ! isset( $_POST['fp_seo_howto_schema_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fp_seo_howto_schema_nonce'] ) ), 'fp_seo_howto_schema_nonce' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Get and sanitize HowTo data
		$howto_data = isset( $_POST['fp_seo_howto'] ) ? wp_unslash( $_POST['fp_seo_howto'] ) : array();
		// Ensure $howto_data is always an array
		if ( ! is_array( $howto_data ) ) {
			$howto_data = array();
		}

		$sanitized_howto = array(
			'name'        => sanitize_text_field( $howto_data['name'] ?? '' ),
			'description' => wp_kses_post( $howto_data['description'] ?? '' ),
			'total_time'  => sanitize_text_field( $howto_data['total_time'] ?? '' ),
			'steps'       => array(),
		);

		// Sanitize steps
		if ( isset( $howto_data['steps'] ) && is_array( $howto_data['steps'] ) ) {
			foreach ( $howto_data['steps'] as $step ) {
				if ( ! is_array( $step ) ) {
					continue;
				}

				$name = sanitize_text_field( $step['name'] ?? '' );
				$text = wp_kses_post( $step['text'] ?? '' );
				$url  = esc_url_raw( $step['url'] ?? '' );

				// Only save if name and text are not empty
				if ( ! empty( $name ) && ! empty( $text ) ) {
					$sanitized_howto['steps'][] = array(
						'name' => $name,
						'text' => $text,
						'url'  => $url,
					);
				}
			}
		}

		// Save or delete meta
		if ( ! empty( $sanitized_howto['steps'] ) ) {
			update_post_meta( $post_id, '_fp_seo_howto', $sanitized_howto );
			
			// Clear schema cache
			$cache_key = 'fp_seo_schemas_' . $post_id . '_' . get_current_blog_id();
			wp_cache_delete( $cache_key );
		} else {
			delete_post_meta( $post_id, '_fp_seo_howto' );
		}
	}

	/**
	 * Enqueue metabox assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		// Only enqueue in admin context
		if ( ! is_admin() ) {
			return;
		}
		
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, array( 'post', 'page' ), true ) ) {
			return;
		}

		// CSS is now handled by SchemaMetaboxesStylesManager
		// JavaScript is handled inline in render methods via SchemaMetaboxesScriptsManager
	}
}


