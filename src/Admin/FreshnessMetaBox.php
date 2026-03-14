<?php
/**
 * Freshness Settings MetaBox
 *
 * Provides UI for managing freshness signals and temporal data.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin;

use FP\SEO\Admin\Styles\FreshnessMetaBoxStylesManager;
use FP\SEO\GEO\FreshnessSignals;
use FP\SEO\Utils\PostTypes;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;

/**
 * Manages freshness settings metabox
 */
class FreshnessMetabox {
	/**
	 * Freshness signals instance
	 *
	 * @var FreshnessSignals
	 */
	private FreshnessSignals $freshness;

	/**
	 * @var FreshnessMetaBoxStylesManager|null
	 */
	private $styles_manager;

	/**
	 * Hook manager instance.
	 *
	 * @var HookManagerInterface|null
	 */
	private ?HookManagerInterface $hook_manager = null;

	/**
	 * Constructor
	 *
	 * @param HookManagerInterface|null $hook_manager Optional hook manager instance.
	 */
	public function __construct( ?HookManagerInterface $hook_manager = null ) {
		$this->freshness = new FreshnessSignals();
		$this->hook_manager = $hook_manager;
	}

	/**
	 * Register hooks
	 */
	public function register(): void {
		// Non registra la metabox separata - il contenuto è integrato in Metabox.php
		// add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		
		// CRITICAL: Register hooks ONLY for supported post types to prevent ANY interference
		$supported_types = \FP\SEO\Utils\PostTypes::analyzable();
		foreach ( $supported_types as $post_type ) {
			$hook = 'save_post_' . $post_type;
			if ( $this->hook_manager ) {
				// HookManager handles duplicate prevention internally
				$this->hook_manager->add_action( $hook, array( $this, 'save_meta' ), 10, 1 );
			} else {
				if ( ! has_action( $hook, array( $this, 'save_meta' ) ) ) {
					add_action( $hook, array( $this, 'save_meta' ), 10, 1 );
				}
			}
		}

		// Initialize and register styles manager
		$this->styles_manager = new FreshnessMetaBoxStylesManager();
		$this->styles_manager->register_hooks();
	}

	/**
	 * Add metabox.
	 *
	 * @deprecated Content is integrated into main SEO Performance metabox (AIRenderer).
	 */
	public function add_meta_box(): void {
		// No-op: content rendered via FreshnessMetabox::render() inside main metabox.
	}

	/**
	 * Render metabox
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render( \WP_Post $post ): void {
		wp_nonce_field( 'fp_seo_freshness_metabox', 'fp_seo_freshness_nonce' );

		// CRITICAL: Cache clearing disabled to prevent interference with featured image (_thumbnail_id)
		// WordPress handles cache management automatically - no manual clearing needed
		// Clearing cache can interfere with WordPress core operations including _thumbnail_id

		// Get freshness data
		$freshness_data = $this->freshness->get_freshness_data( $post->ID );

		// Get editable fields
		$update_frequency = get_post_meta( $post->ID, '_fp_seo_update_frequency', true );
		$fact_checked     = get_post_meta( $post->ID, '_fp_seo_fact_checked', true );
		$content_type     = get_post_meta( $post->ID, '_fp_seo_content_type', true );
		
		// Fallback: query diretta al database se get_post_meta restituisce vuoto
		if ( '' === $update_frequency ) {
			global $wpdb;
			$db_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", $post->ID, '_fp_seo_update_frequency' ) );
			if ( $db_value !== null ) {
				$update_frequency = $db_value;
			}
		}
		
		if ( '' === $fact_checked ) {
			global $wpdb;
			$db_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", $post->ID, '_fp_seo_fact_checked' ) );
			if ( $db_value !== null ) {
				$fact_checked = $db_value;
			}
		}
		
		if ( '' === $content_type ) {
			global $wpdb;
			$db_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", $post->ID, '_fp_seo_content_type' ) );
			if ( $db_value !== null ) {
				$content_type = $db_value;
			}
		}

		?>
		<?php $freshness_score = (float) ( $freshness_data['freshness_score'] ?? 0.0 ); ?>
		<div class="fp-seo-freshness-metabox">
			<!-- Freshness Score -->
			<div style="margin-bottom: 20px; padding: 15px; background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border-radius: 6px; text-align: center;">
				<div style="font-size: 36px; font-weight: bold; color: <?php echo esc_attr( $freshness_score > 0.7 ? '#059669' : ( $freshness_score > 0.4 ? '#f59e0b' : '#dc2626' ) ); ?>">
					<?php echo esc_html( number_format( $freshness_score * 100, 0 ) ); ?>
				</div>
				<div style="font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">
					Freshness Score
				</div>
			</div>

			<!-- Update Frequency -->
			<p>
				<label>
					<strong><?php esc_html_e( 'Update Frequency', 'fp-seo-performance' ); ?></strong><br>
					<select name="fp_seo_update_frequency" class="widefat">
						<option value=""><?php esc_html_e( 'Auto-detect', 'fp-seo-performance' ); ?></option>
						<option value="daily" <?php selected( $update_frequency, 'daily' ); ?>><?php esc_html_e( 'Daily', 'fp-seo-performance' ); ?></option>
						<option value="weekly" <?php selected( $update_frequency, 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'fp-seo-performance' ); ?></option>
						<option value="monthly" <?php selected( $update_frequency, 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'fp-seo-performance' ); ?></option>
						<option value="yearly" <?php selected( $update_frequency, 'yearly' ); ?>><?php esc_html_e( 'Yearly', 'fp-seo-performance' ); ?></option>
						<option value="evergreen" <?php selected( $update_frequency, 'evergreen' ); ?>><?php esc_html_e( 'Evergreen', 'fp-seo-performance' ); ?></option>
					</select>
				</label>
				<span class="description">
					<?php esc_html_e( 'Frequenza di aggiornamento del contenuto', 'fp-seo-performance' ); ?>
				</span>
			</p>

			<!-- Content Type -->
			<p>
				<label>
					<strong><?php esc_html_e( 'Content Type', 'fp-seo-performance' ); ?></strong><br>
					<select name="fp_seo_content_type" class="widefat">
						<option value=""><?php esc_html_e( 'Auto-detect', 'fp-seo-performance' ); ?></option>
						<option value="evergreen" <?php selected( $content_type, 'evergreen' ); ?>><?php esc_html_e( 'Evergreen', 'fp-seo-performance' ); ?></option>
						<option value="news" <?php selected( $content_type, 'news' ); ?>><?php esc_html_e( 'News/Time-sensitive', 'fp-seo-performance' ); ?></option>
						<option value="seasonal" <?php selected( $content_type, 'seasonal' ); ?>><?php esc_html_e( 'Seasonal', 'fp-seo-performance' ); ?></option>
						<option value="trending" <?php selected( $content_type, 'trending' ); ?>><?php esc_html_e( 'Trending', 'fp-seo-performance' ); ?></option>
					</select>
				</label>
				<span class="description">
					<?php esc_html_e( 'Tipo di contenuto (influenza temporal validity)', 'fp-seo-performance' ); ?>
				</span>
			</p>

			<!-- Fact Checked -->
			<p>
				<label>
					<input type="checkbox" 
						   name="fp_seo_fact_checked" 
						   value="1" 
						   <?php checked( $fact_checked ); ?>>
					<strong><?php esc_html_e( 'Fact-Checked', 'fp-seo-performance' ); ?></strong>
				</label><br>
				<span class="description">
					<?php esc_html_e( 'Contenuto verificato e fact-checked (aumenta authority)', 'fp-seo-performance' ); ?>
				</span>
			</p>

			<!-- Current Version -->
			<p style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e5e7eb;">
				<strong><?php esc_html_e( 'Info Attuali:', 'fp-seo-performance' ); ?></strong><br>
				<span style="font-size: 12px; color: #64748b;">
					<?php
					printf(
						/* translators: %s: Content version */
						esc_html__( 'Versione: %s', 'fp-seo-performance' ),
						esc_html( $freshness_data['version'] ?? '1.0' )
					);
					?>
					<br>
					<?php
					printf(
						/* translators: %s: Age in days */
						esc_html__( 'Età: %d giorni', 'fp-seo-performance' ),
						(int) ( $freshness_data['age_days'] ?? 0 )
					);
					?>
				</span>
			</p>
		</div>
		<?php
	}

	/**
	 * Save metabox data
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_meta( int $post_id ): void {
		// CRITICAL: Do NOT interfere if WordPress is handling a native operation
		if ( \FP\SEO\Editor\Helpers\WordPressNativeProtection::is_wordpress_native_operation() ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'FP SEO: FreshnessMetabox::save_meta BLOCKED - WordPress native operation detected' );
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
		
		// Verify nonce
		if ( ! isset( $_POST['fp_seo_freshness_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fp_seo_freshness_nonce'] ) ), 'fp_seo_freshness_metabox' ) ) {
			return;
		}

		// Check permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Avoid autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Save update frequency
		if ( isset( $_POST['fp_seo_update_frequency'] ) ) {
			$frequency = sanitize_text_field( wp_unslash( $_POST['fp_seo_update_frequency'] ) );

			if ( ! empty( $frequency ) ) {
				update_post_meta( $post_id, '_fp_seo_update_frequency', $frequency );
			} else {
				delete_post_meta( $post_id, '_fp_seo_update_frequency' );
			}
		}

		// Save content type
		if ( isset( $_POST['fp_seo_content_type'] ) ) {
			$type = sanitize_text_field( wp_unslash( $_POST['fp_seo_content_type'] ) );

			if ( ! empty( $type ) ) {
				update_post_meta( $post_id, '_fp_seo_content_type', $type );
			} else {
				delete_post_meta( $post_id, '_fp_seo_content_type' );
			}
		}

		// Save fact-checked
		if ( isset( $_POST['fp_seo_fact_checked'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['fp_seo_fact_checked'] ) ) ) {
			update_post_meta( $post_id, '_fp_seo_fact_checked', '1' );
		} else {
			delete_post_meta( $post_id, '_fp_seo_fact_checked' );
		}
	}
}

