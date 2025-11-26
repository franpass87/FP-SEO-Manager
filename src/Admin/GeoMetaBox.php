<?php
/**
 * GEO MetaBox for Claims
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin;

use FP\SEO\Utils\Options;
use FP\SEO\Utils\PostTypes;

/**
 * GEO metabox with claims editor
 */
class GeoMetaBox {

	/**
	 * Register hooks
	 */
	public function register(): void {
		// Non registra la metabox separata - il contenuto è integrato in Metabox.php
		// add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		
		// CRITICAL: Register hooks ONLY for supported post types to prevent ANY interference
		$supported_types = \FP\SEO\Utils\PostTypes::analyzable();
		foreach ( $supported_types as $post_type ) {
			if ( ! has_action( 'save_post_' . $post_type, array( $this, 'save_meta' ) ) ) {
				add_action( 'save_post_' . $post_type, array( $this, 'save_meta' ), 10, 1 );
			}
		}
		
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add metabox
	 */
	public function add_meta_box(): void {
		$post_types = $this->get_enabled_post_types();

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'fp-seo-geo-metabox',
				__( 'GEO (FP)', 'fp-seo-performance' ),
				array( $this, 'render' ),
				$post_type,
				'normal',
				'default'
			);
		}
	}

	/**
	 * Enqueue metabox assets
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_assets( string $hook ): void {
		// Only enqueue in admin context
		if ( ! is_admin() ) {
			return;
		}
		
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		global $post;
		if ( ! $post || ! in_array( $post->post_type, $this->get_enabled_post_types(), true ) ) {
			return;
		}

		wp_enqueue_style( 'fp-seo-performance-admin' );
	}

	/**
	 * Render metabox
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render( \WP_Post $post ): void {
		wp_nonce_field( 'fp_seo_geo_metabox', 'fp_seo_geo_nonce' );

		// Clear cache before retrieving
		clean_post_cache( $post->ID );
		wp_cache_delete( $post->ID, 'post_meta' );
		wp_cache_delete( $post->ID, 'posts' );
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'post_meta' );
		}
		if ( function_exists( 'update_post_meta_cache' ) ) {
			update_post_meta_cache( array( $post->ID ) );
		}

		// Get existing data
		$claims      = get_post_meta( $post->ID, '_fp_seo_geo_claims', true );
		$expose      = get_post_meta( $post->ID, '_fp_seo_geo_expose', true );
		$no_ai_reuse = get_post_meta( $post->ID, '_fp_seo_geo_no_ai_reuse', true );
		
		// Fallback: query diretta al database se get_post_meta restituisce vuoto
		if ( empty( $claims ) ) {
			global $wpdb;
			$db_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", $post->ID, '_fp_seo_geo_claims' ) );
			if ( $db_value !== null ) {
				$unserialized = maybe_unserialize( $db_value );
				$claims = is_array( $unserialized ) ? $unserialized : array();
			}
		}
		
		if ( '' === $expose ) {
			global $wpdb;
			$db_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", $post->ID, '_fp_seo_geo_expose' ) );
			if ( $db_value !== null ) {
				$expose = $db_value;
			}
		}
		
		if ( '' === $no_ai_reuse ) {
			global $wpdb;
			$db_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", $post->ID, '_fp_seo_geo_no_ai_reuse' ) );
			if ( $db_value !== null ) {
				$no_ai_reuse = $db_value;
			}
		}

		if ( ! is_array( $claims ) ) {
			$claims = array();
		}

		// Default to exposed
		$expose = '' === $expose || '1' === $expose;

		?>
		<div class="fp-seo-geo-metabox">
			<!-- GEO Toggles -->
			<div class="fp-seo-geo-toggles" style="margin-bottom: 20px; padding: 12px; background: #f6f7f7; border-radius: 4px;">
				<p>
					<label>
						<input type="checkbox" 
							   name="fp_seo_geo_expose" 
							   value="1" 
							   <?php checked( $expose ); ?> />
						<strong><?php esc_html_e( 'Expose in GEO endpoints', 'fp-seo-performance' ); ?></strong>
					</label>
					<br>
					<span class="description"><?php esc_html_e( 'Make this content available via /geo/content/{id}.json', 'fp-seo-performance' ); ?></span>
				</p>

				<p>
					<label>
						<input type="checkbox" 
							   name="fp_seo_geo_no_ai_reuse" 
							   value="1" 
							   <?php checked( $no_ai_reuse, '1' ); ?> />
						<strong><?php esc_html_e( 'No AI reuse (this post)', 'fp-seo-performance' ); ?></strong>
					</label>
					<br>
					<span class="description"><?php esc_html_e( 'Add to ai.txt Disallow-Content and mark as "deny" in GEO JSON', 'fp-seo-performance' ); ?></span>
				</p>
			</div>

			<!-- Claims Section -->
			<div class="fp-seo-geo-claims">
				<h4><?php esc_html_e( 'Key Claims', 'fp-seo-performance' ); ?></h4>
				<p class="description"><?php esc_html_e( 'Add verifiable claims with supporting evidence', 'fp-seo-performance' ); ?></p>

				<div id="fp-seo-geo-claims-list">
					<?php if ( ! empty( $claims ) ) : ?>
						<?php foreach ( $claims as $index => $claim ) : ?>
							<?php $this->render_claim_row( $index, $claim ); ?>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>

				<button type="button" class="button" onclick="fpSeoGeoAddClaim()">
					<?php esc_html_e( '+ Add Claim', 'fp-seo-performance' ); ?>
				</button>
			</div>
		</div>

		<!-- Claim Template (hidden) -->
		<script type="text/template" id="fp-seo-geo-claim-template">
			<?php $this->render_claim_row( '{INDEX}', array() ); ?>
		</script>

		<script>
		var fpSeoGeoClaimIndex = <?php echo count( $claims ); ?>;

		function fpSeoGeoAddClaim() {
			var template = document.getElementById('fp-seo-geo-claim-template').innerHTML;
			var html = template.replace(/{INDEX}/g, fpSeoGeoClaimIndex);
			
			var list = document.getElementById('fp-seo-geo-claims-list');
			var div = document.createElement('div');
			div.innerHTML = html;
			list.appendChild(div.firstChild);
			
			fpSeoGeoClaimIndex++;
		}

		function fpSeoGeoRemoveClaim(index) {
			if (confirm('<?php esc_html_e( 'Remove this claim?', 'fp-seo-performance' ); ?>')) {
				document.getElementById('fp-seo-geo-claim-' + index).remove();
			}
		}

		function fpSeoGeoAddEvidence(claimIndex) {
			var container = document.getElementById('fp-seo-geo-claim-' + claimIndex + '-evidence');
			var evidenceIndex = container.children.length;
			
			var html = '<div class="fp-seo-geo-evidence-item" style="padding: 8px; background: #fff; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 8px;">';
			html += '<input type="url" name="fp_seo_geo_claims[' + claimIndex + '][evidence][' + evidenceIndex + '][url]" placeholder="<?php esc_attr_e( 'Evidence URL', 'fp-seo-performance' ); ?>" class="regular-text" style="width: 100%; margin-bottom: 4px;" />';
			html += '<input type="text" name="fp_seo_geo_claims[' + claimIndex + '][evidence][' + evidenceIndex + '][title]" placeholder="<?php esc_attr_e( 'Evidence Title', 'fp-seo-performance' ); ?>" class="regular-text" style="width: 100%; margin-bottom: 4px;" />';
			html += '<input type="text" name="fp_seo_geo_claims[' + claimIndex + '][evidence][' + evidenceIndex + '][publisher]" placeholder="<?php esc_attr_e( 'Publisher', 'fp-seo-performance' ); ?>" style="width: 32%; margin-right: 1%;" />';
			html += '<input type="text" name="fp_seo_geo_claims[' + claimIndex + '][evidence][' + evidenceIndex + '][author]" placeholder="<?php esc_attr_e( 'Author', 'fp-seo-performance' ); ?>" style="width: 32%; margin-right: 1%;" />';
			html += '<input type="date" name="fp_seo_geo_claims[' + claimIndex + '][evidence][' + evidenceIndex + '][accessed]" placeholder="<?php esc_attr_e( 'Accessed Date', 'fp-seo-performance' ); ?>" style="width: 32%;" />';
			html += '</div>';
			
			container.insertAdjacentHTML('beforeend', html);
		}
		</script>

		<style>
		.fp-seo-geo-claim {
			padding: 16px;
			background: #fff;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			margin-bottom: 12px;
		}
		.fp-seo-geo-claim-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 12px;
			padding-bottom: 8px;
			border-bottom: 1px solid #ddd;
		}
		.fp-seo-geo-evidence-container {
			margin-top: 12px;
			padding-top: 12px;
			border-top: 1px solid #ddd;
		}
		</style>
		<?php
	}

	/**
	 * Render single claim row
	 *
	 * @param int|string           $index Claim index.
	 * @param array<string,mixed> $claim Claim data.
	 */
	private function render_claim_row( $index, array $claim ): void {
		$statement  = $claim['statement'] ?? '';
		$confidence = $claim['confidence'] ?? 0.7;
		$evidence   = $claim['evidence'] ?? array();

		?>
		<div class="fp-seo-geo-claim" id="fp-seo-geo-claim-<?php echo esc_attr( (string) $index ); ?>">
			<div class="fp-seo-geo-claim-header">
				<strong><?php esc_html_e( 'Claim', 'fp-seo-performance' ); ?> #<?php echo esc_html( (string) ( is_numeric( $index ) ? $index + 1 : '{NUM}' ) ); ?></strong>
				<button type="button" class="button button-small" onclick="fpSeoGeoRemoveClaim('<?php echo esc_js( (string) $index ); ?>')">
					<?php esc_html_e( 'Remove', 'fp-seo-performance' ); ?>
				</button>
			</div>

			<p>
				<label>
					<strong><?php esc_html_e( 'Statement:', 'fp-seo-performance' ); ?></strong><br>
					<textarea name="fp_seo_geo_claims[<?php echo esc_attr( (string) $index ); ?>][statement]" 
							  rows="3" 
							  class="large-text"
							  placeholder="<?php esc_attr_e( 'Enter the claim statement...', 'fp-seo-performance' ); ?>"><?php echo esc_textarea( $statement ); ?></textarea>
				</label>
			</p>

			<p>
				<label>
					<strong><?php esc_html_e( 'Confidence (0-1):', 'fp-seo-performance' ); ?></strong>
					<input type="number" 
						   name="fp_seo_geo_claims[<?php echo esc_attr( (string) $index ); ?>][confidence]" 
						   value="<?php echo esc_attr( (string) $confidence ); ?>"
						   min="0" 
						   max="1" 
						   step="0.1"
						   style="width: 80px;" />
				</label>
			</p>

			<div class="fp-seo-geo-evidence-container">
				<strong><?php esc_html_e( 'Evidence:', 'fp-seo-performance' ); ?></strong>
				<div id="fp-seo-geo-claim-<?php echo esc_attr( (string) $index ); ?>-evidence">
					<?php if ( ! empty( $evidence ) ) : ?>
						<?php foreach ( $evidence as $ev_index => $ev ) : ?>
							<div class="fp-seo-geo-evidence-item" style="padding: 8px; background: #fff; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 8px;">
								<input type="url" 
									   name="fp_seo_geo_claims[<?php echo esc_attr( (string) $index ); ?>][evidence][<?php echo esc_attr( (string) $ev_index ); ?>][url]" 
									   value="<?php echo esc_attr( $ev['url'] ?? '' ); ?>"
									   placeholder="<?php esc_attr_e( 'Evidence URL', 'fp-seo-performance' ); ?>" 
									   class="regular-text" 
									   style="width: 100%; margin-bottom: 4px;" />
								<input type="text" 
									   name="fp_seo_geo_claims[<?php echo esc_attr( (string) $index ); ?>][evidence][<?php echo esc_attr( (string) $ev_index ); ?>][title]" 
									   value="<?php echo esc_attr( $ev['title'] ?? '' ); ?>"
									   placeholder="<?php esc_attr_e( 'Evidence Title', 'fp-seo-performance' ); ?>" 
									   class="regular-text" 
									   style="width: 100%; margin-bottom: 4px;" />
								<input type="text" 
									   name="fp_seo_geo_claims[<?php echo esc_attr( (string) $index ); ?>][evidence][<?php echo esc_attr( (string) $ev_index ); ?>][publisher]" 
									   value="<?php echo esc_attr( $ev['publisher'] ?? '' ); ?>"
									   placeholder="<?php esc_attr_e( 'Publisher', 'fp-seo-performance' ); ?>" 
									   style="width: 32%; margin-right: 1%;" />
								<input type="text" 
									   name="fp_seo_geo_claims[<?php echo esc_attr( (string) $index ); ?>][evidence][<?php echo esc_attr( (string) $ev_index ); ?>][author]" 
									   value="<?php echo esc_attr( $ev['author'] ?? '' ); ?>"
									   placeholder="<?php esc_attr_e( 'Author', 'fp-seo-performance' ); ?>" 
									   style="width: 32%; margin-right: 1%;" />
								<input type="date" 
									   name="fp_seo_geo_claims[<?php echo esc_attr( (string) $index ); ?>][evidence][<?php echo esc_attr( (string) $ev_index ); ?>][accessed]" 
									   value="<?php echo esc_attr( $ev['accessed'] ?? '' ); ?>"
									   style="width: 32%;" />
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
				<button type="button" class="button button-small" onclick="fpSeoGeoAddEvidence('<?php echo esc_js( (string) $index ); ?>')">
					<?php esc_html_e( '+ Add Evidence', 'fp-seo-performance' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Save metabox data
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_meta( int $post_id ): void {
		// CRITICAL: Check post type FIRST, before any processing
		// This ensures we don't interfere with unsupported post types (attachments, Nectar Sliders, etc.)
		$post_type = get_post_type( $post_id );
		$supported_types = \FP\SEO\Utils\PostTypes::analyzable();
		
		// If not a supported post type, return immediately without any processing
		if ( ! in_array( $post_type, $supported_types, true ) ) {
			return; // Exit immediately - no interference with WordPress core saving
		}
		
		// Verify nonce
		if ( ! isset( $_POST['fp_seo_geo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fp_seo_geo_nonce'] ) ), 'fp_seo_geo_metabox' ) ) {
			return;
		}

		// Check autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save expose toggle
		$expose = isset( $_POST['fp_seo_geo_expose'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['fp_seo_geo_expose'] ) );
		update_post_meta( $post_id, '_fp_seo_geo_expose', $expose ? '1' : '0' );

		// Save no AI reuse toggle
		$no_ai_reuse = isset( $_POST['fp_seo_geo_no_ai_reuse'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['fp_seo_geo_no_ai_reuse'] ) );
		update_post_meta( $post_id, '_fp_seo_geo_no_ai_reuse', $no_ai_reuse ? '1' : '0' );

		// Save claims
		$claims = array();
		if ( isset( $_POST['fp_seo_geo_claims'] ) && is_array( $_POST['fp_seo_geo_claims'] ) ) {
			foreach ( $_POST['fp_seo_geo_claims'] as $claim_data ) {
				if ( empty( $claim_data['statement'] ) ) {
					continue;
				}

				$claim = array(
					'statement'  => sanitize_textarea_field( wp_unslash( $claim_data['statement'] ) ),
					'confidence' => isset( $claim_data['confidence'] ) ? (float) $claim_data['confidence'] : 0.7,
					'evidence'   => array(),
				);

				if ( ! empty( $claim_data['evidence'] ) && is_array( $claim_data['evidence'] ) ) {
					foreach ( $claim_data['evidence'] as $ev_data ) {
						if ( empty( $ev_data['url'] ) ) {
							continue;
						}

						$claim['evidence'][] = array(
							'url'       => esc_url_raw( wp_unslash( $ev_data['url'] ) ),
							'title'     => isset( $ev_data['title'] ) ? sanitize_text_field( wp_unslash( $ev_data['title'] ) ) : '',
							'publisher' => isset( $ev_data['publisher'] ) ? sanitize_text_field( wp_unslash( $ev_data['publisher'] ) ) : '',
							'author'    => isset( $ev_data['author'] ) ? sanitize_text_field( wp_unslash( $ev_data['author'] ) ) : '',
							'accessed'  => isset( $ev_data['accessed'] ) ? sanitize_text_field( wp_unslash( $ev_data['accessed'] ) ) : '',
						);
					}
				}

				$claims[] = $claim;
			}
		}

		update_post_meta( $post_id, '_fp_seo_geo_claims', $claims );

		// Flush GEO caches
		delete_transient( 'fp_seo_geo_sitemap' );
		delete_transient( 'fp_seo_geo_site_json' );
		delete_transient( 'fp_seo_geo_updates_json' );
		delete_transient( 'fp_seo_geo_disallowed_posts' );
	}

	/**
	 * Get enabled post types from settings
	 *
	 * @return array<string>
	 */
	private function get_enabled_post_types(): array {
		$options = Options::get();
		$geo     = $options['geo'] ?? array();

		$enabled = array();
		if ( ! empty( $geo['post_types'] ) && is_array( $geo['post_types'] ) ) {
			foreach ( $geo['post_types'] as $type => $settings ) {
				if ( ! empty( $settings['expose'] ) ) {
					$enabled[] = $type;
				}
			}
		}

		return $enabled;
	}
}

