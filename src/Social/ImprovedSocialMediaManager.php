<?php
/**
 * Improved Social Media Manager with Enhanced UI/UX
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Social;

use FP\SEO\Utils\Cache;
use FP\SEO\Utils\MetadataResolver;
use FP\SEO\Utils\PerformanceConfig;
use FP\SEO\Utils\WPBakeryContentExtractor;
use function get_permalink;
use function get_post;
use function get_post_field;
use function get_queried_object_id;
use function get_the_excerpt;
use function strip_shortcodes;
use function do_shortcode;
use function get_the_post_thumbnail_url;
use function get_the_title;
use function is_singular;
use function trim;
use function wp_get_attachment_url;
use function wp_strip_all_tags;
use function wp_trim_words;

/**
 * Enhanced Social Media Manager with improved UI/UX.
 */
class ImprovedSocialMediaManager {

	/**
	 * Supported social platforms.
	 */
	private const PLATFORMS = array(
		'facebook' => array(
			'name' => 'Facebook',
			'icon' => '📘',
			'color' => '#1877f2',
			'title_limit' => 60,
			'description_limit' => 160
		),
		'twitter' => array(
			'name' => 'Twitter',
			'icon' => '🐦',
			'color' => '#1da1f2',
			'title_limit' => 70,
			'description_limit' => 200
		),
		'linkedin' => array(
			'name' => 'LinkedIn',
			'icon' => '💼',
			'color' => '#0077b5',
			'title_limit' => 60,
			'description_limit' => 160
		),
		'pinterest' => array(
			'name' => 'Pinterest',
			'icon' => '📌',
			'color' => '#bd081c',
			'title_limit' => 60,
			'description_limit' => 160
		)
	);

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'wp_head', array( $this, 'output_meta_tags' ), 1 );
		add_action( 'admin_menu', array( $this, 'add_social_menu' ) );
		add_action( 'wp_ajax_fp_seo_preview_social', array( $this, 'ajax_preview_social' ) );
		add_action( 'wp_ajax_fp_seo_optimize_social', array( $this, 'ajax_optimize_social' ) );
		add_action( 'wp_ajax_fp_seo_get_attachment_url', array( $this, 'ajax_get_attachment_url' ) );
		// Non registra la metabox separata - il contenuto è integrato in Metabox.php
		// add_action( 'add_meta_boxes', array( $this, 'add_social_metabox' ) );
		
		// CRITICAL: Register hooks ONLY for supported post types to prevent ANY interference
		// This is more efficient than registering generic hooks and exiting early
		$supported_types = \FP\SEO\Utils\PostTypes::analyzable();
		foreach ( $supported_types as $post_type ) {
			if ( ! has_action( 'save_post_' . $post_type, array( $this, 'save_social_meta' ) ) ) {
				add_action( 'save_post_' . $post_type, array( $this, 'save_social_meta' ), 10, 1 );
			}
		}
		
		// Use priority 5 to ensure wp.media is loaded early, before other plugins
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 5 );
	}

	/**
	 * Enqueue assets for social media manager.
	 */
	public function enqueue_assets(): void {
		// Only enqueue in admin context
		if ( ! is_admin() ) {
			return;
		}
		
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		// CRITICAL: Never run on media library or upload pages to avoid interference
		$is_media_page = in_array( $screen->base, array( 'upload', 'media' ), true ) || $screen->id === 'upload';
		if ( $is_media_page ) {
			return;
		}

		$is_fp_seo_page = strpos( $screen->id, 'fp-seo' ) !== false;
		$is_post_editor = in_array( $screen->id, array( 'post', 'page' ), true );

		if ( $is_fp_seo_page || $is_post_editor ) {
			// Ensure wp.media is available for image uploads (including featured image)
			// This must be called early to support WordPress core featured image button
			wp_enqueue_media();
			
			// Also ensure set-post-thumbnail script is loaded (required for featured image button)
			if ( function_exists( 'wp_enqueue_script' ) ) {
				wp_enqueue_script( 'set-post-thumbnail' );
			}
			
			wp_enqueue_style( 'fp-seo-ui-system' );
			wp_enqueue_style( 'fp-seo-notifications' );
			wp_enqueue_script( 'fp-seo-ui-system' );
		}
	}

	/**
	 * Add Social Media menu to admin.
	 */
	public function add_social_menu(): void {
		add_submenu_page(
			'fp-seo-performance',
			__( 'Social Media', 'fp-seo-performance' ),
			__( 'Social Media', 'fp-seo-performance' ),
			'manage_options',
			'fp-seo-social-media',
			array( $this, 'render_social_page' )
		);
	}

	/**
	 * Add social media metabox to post editor.
	 */
	public function add_social_metabox(): void {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		
		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'fp_seo_social_media_improved',
				__( 'Social Media Preview', 'fp-seo-performance' ),
				array( $this, 'render_improved_social_metabox' ),
				$post_type,
				'side',
				'high'
			);
		}
	}

	/**
	 * Render improved social media metabox with enhanced UI.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_improved_social_metabox( $post ): void {
		try {
			// Validate post object
			if ( ! $post || ! isset( $post->ID ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					\FP\SEO\Utils\Logger::error( 'FP SEO: Invalid post object in render_improved_social_metabox', array(
						'post' => is_object( $post ) ? get_class( $post ) : gettype( $post ),
					) );
				}
				return;
			}

			$social_meta = $this->get_social_meta( $post->ID );
			$preview_data = $this->get_preview_data( $post );
			
			wp_nonce_field( 'fp_seo_social_meta', 'fp_seo_social_nonce' );
		} catch ( \Throwable $e ) {
			// Log error but don't break the page
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				\FP\SEO\Utils\Logger::error( 'FP SEO: Error initializing social metabox', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
					'file' => $e->getFile(),
					'line' => $e->getLine(),
					'post_id' => isset( $post->ID ) ? $post->ID : 0,
				) );
			}
			// Show fallback message
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__( 'Impossibile caricare la sezione Social Media. I campi SEO verranno comunque salvati.', 'fp-seo-performance' );
			echo '</p></div>';
			return;
		}
		?>
		<div class="fp-seo-ui">
			<div class="fp-seo-card">
				<div class="fp-seo-card-header">
					<h3 class="fp-seo-heading-3">
						<span class="fp-seo-social-icon">📱</span>
						<?php esc_html_e( 'Social Media Preview', 'fp-seo-performance' ); ?>
					</h3>
					<p class="fp-seo-text-sm fp-seo-text-muted">
						<?php esc_html_e( 'Optimize your content for social sharing', 'fp-seo-performance' ); ?>
					</p>
				</div>

				<div class="fp-seo-card-body">
					<!-- Platform Tabs -->
					<div class="fp-seo-tabs">
						<?php foreach ( self::PLATFORMS as $platform_id => $platform_data ) : ?>
							<button type="button" 
									class="fp-seo-tab <?php echo $platform_id === 'facebook' ? 'fp-seo-tab-active' : ''; ?>" 
									data-tab="<?php echo esc_attr( $platform_id ); ?>"
									style="--platform-color: <?php echo esc_attr( $platform_data['color'] ); ?>">
								<span class="fp-seo-tab-icon"><?php echo $platform_data['icon']; ?></span>
								<span class="fp-seo-tab-label"><?php echo esc_html( $platform_data['name'] ); ?></span>
							</button>
						<?php endforeach; ?>
					</div>

					<!-- Tab Contents -->
					<?php foreach ( self::PLATFORMS as $platform_id => $platform_data ) : ?>
						<div class="fp-seo-tab-content <?php echo $platform_id === 'facebook' ? 'fp-seo-tab-content-active' : ''; ?>" 
							 id="<?php echo esc_attr( $platform_id ); ?>">
							
							<!-- Live Preview -->
							<div class="fp-seo-social-preview-container">
								<div class="fp-seo-social-preview-header">
									<h4 class="fp-seo-heading-4"><?php esc_html_e( 'Anteprima Live', 'fp-seo-performance' ); ?></h4>
									<button type="button" class="fp-seo-btn fp-seo-btn-sm fp-seo-btn-secondary" 
											id="fp-seo-refresh-preview-<?php echo esc_attr( $platform_id ); ?>">
										<span class="fp-seo-refresh-icon">🔄</span>
										<?php esc_html_e( 'Aggiorna', 'fp-seo-performance' ); ?>
									</button>
								</div>
								
								<div class="fp-seo-social-preview-card fp-seo-social-preview-<?php echo esc_attr( $platform_id ); ?>">
									<div class="fp-seo-social-preview-image">
										<img id="fp-seo-<?php echo esc_attr( $platform_id ); ?>-image-preview" 
											 src="<?php echo esc_url( $preview_data['image'] ); ?>" 
											 alt="<?php esc_attr_e( 'Social media preview image', 'fp-seo-performance' ); ?>">
										<div class="fp-seo-social-preview-image-overlay">
											<button type="button" class="fp-seo-btn fp-seo-btn-sm fp-seo-btn-primary">
												<?php esc_html_e( 'Cambia Immagine', 'fp-seo-performance' ); ?>
											</button>
										</div>
									</div>
									<div class="fp-seo-social-preview-content">
										<div class="fp-seo-social-preview-title" 
											 id="fp-seo-<?php echo esc_attr( $platform_id ); ?>-title-preview">
											<?php 
											// Decode HTML entities for proper display (B&B instead of B&#038;B, – instead of &#8211;)
											$title_decoded = $preview_data['title'];
											// Decode numeric entities (&#038; -> &, &#8211; -> –) - handle UTF-8 properly
											$title_decoded = preg_replace_callback('/&#(\d+);/', function($matches) {
												$code = (int)$matches[1];
												if (function_exists('mb_chr')) {
													return mb_chr($code, 'UTF-8');
												}
												return html_entity_decode('&#' . $code . ';', ENT_QUOTES | ENT_HTML5, 'UTF-8');
											}, $title_decoded);
											// Decode hex entities (&#x26; -> &)
											$title_decoded = preg_replace_callback('/&#x([0-9A-Fa-f]+);/i', function($matches) {
												$code = hexdec($matches[1]);
												if (function_exists('mb_chr')) {
													return mb_chr($code, 'UTF-8');
												}
												return html_entity_decode('&#x' . $matches[1] . ';', ENT_QUOTES | ENT_HTML5, 'UTF-8');
											}, $title_decoded);
											// Decode named entities using html_entity_decode
											$title_decoded = html_entity_decode( $title_decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
											// Final decode with wp_specialchars_decode for WordPress-specific entities
											$title_decoded = wp_specialchars_decode( $title_decoded, ENT_QUOTES );
											echo esc_html( $title_decoded ); 
											?>
										</div>
										<div class="fp-seo-social-preview-description" 
											 id="fp-seo-<?php echo esc_attr( $platform_id ); ?>-description-preview">
											<?php 
											// Decode HTML entities for proper display
											$desc_decoded = $preview_data['description'];
											// Decode numeric entities (&#038; -> &, &#8211; -> –) - handle UTF-8 properly
											$desc_decoded = preg_replace_callback('/&#(\d+);/', function($matches) {
												$code = (int)$matches[1];
												if (function_exists('mb_chr')) {
													return mb_chr($code, 'UTF-8');
												}
												return html_entity_decode('&#' . $code . ';', ENT_QUOTES | ENT_HTML5, 'UTF-8');
											}, $desc_decoded);
											// Decode hex entities (&#x26; -> &)
											$desc_decoded = preg_replace_callback('/&#x([0-9A-Fa-f]+);/i', function($matches) {
												$code = hexdec($matches[1]);
												if (function_exists('mb_chr')) {
													return mb_chr($code, 'UTF-8');
												}
												return html_entity_decode('&#x' . $matches[1] . ';', ENT_QUOTES | ENT_HTML5, 'UTF-8');
											}, $desc_decoded);
											// Decode named entities using html_entity_decode
											$desc_decoded = html_entity_decode( $desc_decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
											// Final decode with wp_specialchars_decode for WordPress-specific entities
											$desc_decoded = wp_specialchars_decode( $desc_decoded, ENT_QUOTES );
											echo esc_html( $desc_decoded ); 
											?>
										</div>
										<div class="fp-seo-social-preview-url">
											<?php echo esc_url( $preview_data['url'] ); ?>
										</div>
									</div>
								</div>
							</div>

							<!-- Form Fields -->
							<div class="fp-seo-social-form-container">
								<div class="fp-seo-form-group">
									<label for="fp-seo-<?php echo esc_attr( $platform_id ); ?>-title" 
										   class="fp-seo-form-label">
										<?php printf( 
											esc_html__( '%s Title', 'fp-seo-performance' ), 
											$platform_data['name'] 
										); ?>
										<span class="fp-seo-tooltip fp-seo-tooltip-trigger">
											<span class="fp-seo-tooltip-icon">ℹ</span>
											<div class="fp-seo-tooltip-content">
												<?php printf( 
													esc_html__( 'Recommended: %d characters or less', 'fp-seo-performance' ), 
													$platform_data['title_limit'] 
												); ?>
											</div>
										</span>
									</label>
									<input type="text" 
										   id="fp-seo-<?php echo esc_attr( $platform_id ); ?>-title" 
										   name="fp_seo_<?php echo esc_attr( $platform_id ); ?>_title" 
										   class="fp-seo-form-control fp-seo-character-counter" 
										   value="<?php echo esc_attr( wp_specialchars_decode( $social_meta[ $platform_id . '_title' ] ?? '', ENT_QUOTES ) ); ?>" 
										   maxlength="<?php echo esc_attr( $platform_data['title_limit'] ); ?>"
										   placeholder="<?php echo esc_attr( $preview_data['title'] ?: __( 'Enter title for social sharing', 'fp-seo-performance' ) ); ?>"
										   data-fallback-from="fp-seo-title">
									<div class="fp-seo-character-count">
										<span id="fp-seo-<?php echo esc_attr( $platform_id ); ?>-title-count">0</span>
										/<?php echo esc_attr( $platform_data['title_limit'] ); ?>
									</div>
								</div>

								<div class="fp-seo-form-group">
									<label for="fp-seo-<?php echo esc_attr( $platform_id ); ?>-description" 
										   class="fp-seo-form-label">
										<?php printf( 
											esc_html__( '%s Description', 'fp-seo-performance' ), 
											$platform_data['name'] 
										); ?>
										<span class="fp-seo-tooltip fp-seo-tooltip-trigger">
											<span class="fp-seo-tooltip-icon">ℹ</span>
											<div class="fp-seo-tooltip-content">
												<?php printf( 
													esc_html__( 'Recommended: %d characters or less', 'fp-seo-performance' ), 
													$platform_data['description_limit'] 
												); ?>
											</div>
										</span>
									</label>
									<textarea id="fp-seo-<?php echo esc_attr( $platform_id ); ?>-description" 
											  name="fp_seo_<?php echo esc_attr( $platform_id ); ?>_description" 
											  class="fp-seo-form-control fp-seo-character-counter" 
											  maxlength="<?php echo esc_attr( $platform_data['description_limit'] ); ?>"
											  rows="3"
											  placeholder="<?php echo esc_attr( $preview_data['description'] ?: __( 'Enter description for social sharing', 'fp-seo-performance' ) ); ?>"
											  data-fallback-from="fp-seo-meta-description"><?php echo esc_textarea( wp_specialchars_decode( $social_meta[ $platform_id . '_description' ] ?? '', ENT_QUOTES ) ); ?></textarea>
									<div class="fp-seo-character-count">
										<span id="fp-seo-<?php echo esc_attr( $platform_id ); ?>-description-count">0</span>
										/<?php echo esc_attr( $platform_data['description_limit'] ); ?>
									</div>
								</div>

								<?php if ( $platform_id === 'twitter' ) : ?>
									<div class="fp-seo-form-group">
										<label for="fp-seo-twitter-card-type" class="fp-seo-form-label">
											<?php esc_html_e( 'Twitter Card Type', 'fp-seo-performance' ); ?>
										</label>
										<select id="fp-seo-twitter-card-type" 
												name="fp_seo_twitter_card_type" 
												class="fp-seo-form-control">
											<option value="summary" <?php selected( $social_meta['twitter_card_type'] ?? '', 'summary' ); ?>>
												<?php esc_html_e( 'Riepilogo', 'fp-seo-performance' ); ?>
											</option>
											<option value="summary_large_image" <?php selected( $social_meta['twitter_card_type'] ?? '', 'summary_large_image' ); ?>>
												<?php esc_html_e( 'Riepilogo Immagine Grande', 'fp-seo-performance' ); ?>
											</option>
										</select>
									</div>
								<?php endif; ?>

								<div class="fp-seo-form-group">
									<label for="fp-seo-<?php echo esc_attr( $platform_id ); ?>-image" 
										   class="fp-seo-form-label">
										<?php printf( 
											esc_html__( '%s Image', 'fp-seo-performance' ), 
											$platform_data['name'] 
										); ?>
										<span class="fp-seo-tooltip fp-seo-tooltip-trigger">
											<span class="fp-seo-tooltip-icon">ℹ</span>
											<div class="fp-seo-tooltip-content">
												<?php esc_html_e( 'Se non specificata, verrà utilizzata l\'immagine in evidenza del post come placeholder.', 'fp-seo-performance' ); ?>
											</div>
										</span>
									</label>
									<div class="fp-seo-form-control-group">
									<?php 
									// Get featured image URL using multiple methods for robustness
									$featured_image_url = '';
									
									try {
										// Method 1: Standard WordPress function
										$featured_image_url = get_the_post_thumbnail_url( $post->ID, 'full' );
										
										// Method 2: Direct meta query if standard method fails
										if ( empty( $featured_image_url ) ) {
											$thumbnail_id = get_post_meta( $post->ID, '_thumbnail_id', true );
											if ( $thumbnail_id && function_exists( 'wp_get_attachment_url' ) ) {
												// Usa wp_get_attachment_url invece di wp_get_attachment_image_url per evitare interferenze
												$featured_image_url = wp_get_attachment_url( (int) $thumbnail_id );
											}
										}
										
										// Method 3: Database query as last resort
										if ( empty( $featured_image_url ) && function_exists( 'wp_get_attachment_url' ) ) {
											global $wpdb;
											if ( isset( $wpdb ) && is_object( $wpdb ) ) {
												$thumbnail_id = $wpdb->get_var( $wpdb->prepare(
													"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_thumbnail_id' LIMIT 1",
													$post->ID
												) );
												if ( $thumbnail_id ) {
													// Usa wp_get_attachment_url invece di wp_get_attachment_image_url per evitare interferenze
													$featured_image_url = wp_get_attachment_url( (int) $thumbnail_id );
												}
											}
										}
									} catch ( \Throwable $e ) {
										// Silently fail - featured image is optional
										if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
											\FP\SEO\Utils\Logger::debug( 'FP SEO: Error getting featured image', array(
												'error' => $e->getMessage(),
												'post_id' => $post->ID,
											) );
										}
									}
									
									// Use featured image as default value if no social image is set
									$image_value = $social_meta[ $platform_id . '_image' ] ?? '';
									if ( empty( $image_value ) && $featured_image_url ) {
										$image_value = $featured_image_url;
									}
									?>
									<input type="url" 
										   id="fp-seo-<?php echo esc_attr( $platform_id ); ?>-image" 
										   name="fp_seo_<?php echo esc_attr( $platform_id ); ?>_image" 
										   class="fp-seo-form-control" 
										   value="<?php echo esc_attr( $image_value ); ?>" 
										   placeholder="<?php echo esc_attr( $featured_image_url ?: 'https://example.com/image.jpg' ); ?>"
										   data-featured-image="<?php echo esc_attr( $featured_image_url ?: '' ); ?>"
										   data-fallback-from="post-thumbnail">
										<button type="button" 
												class="fp-seo-btn fp-seo-btn-secondary fp-seo-image-select" 
												data-target="fp-seo-<?php echo esc_attr( $platform_id ); ?>-image"
												data-preview="fp-seo-<?php echo esc_attr( $platform_id ); ?>-image-preview">
											<?php esc_html_e( 'Seleziona', 'fp-seo-performance' ); ?>
										</button>
										<?php if ( $featured_image_url ) : ?>
										<button type="button" 
												class="fp-seo-btn fp-seo-btn-secondary fp-seo-use-featured-image" 
												data-target="fp-seo-<?php echo esc_attr( $platform_id ); ?>-image"
												data-preview="fp-seo-<?php echo esc_attr( $platform_id ); ?>-image-preview"
												data-featured-url="<?php echo esc_attr( $featured_image_url ); ?>"
												title="<?php esc_attr_e( 'Usa immagine in evidenza', 'fp-seo-performance' ); ?>">
											<?php esc_html_e( 'Usa Immagine in Evidenza', 'fp-seo-performance' ); ?>
										</button>
										<?php endif; ?>
									</div>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="fp-seo-card-footer">
					<div class="fp-seo-flex fp-seo-justify-between fp-seo-items-center">
						<div class="fp-seo-social-stats">
							<span class="fp-seo-badge fp-seo-badge-info">
								<?php esc_html_e( '4 Platforms', 'fp-seo-performance' ); ?>
							</span>
						</div>
						<div class="fp-seo-social-actions">
							<button type="button" 
									class="fp-seo-btn fp-seo-btn-secondary" 
									id="fp-seo-preview-all-social">
								<?php esc_html_e( 'Anteprima Tutti', 'fp-seo-performance' ); ?>
							</button>
							<button type="button" 
									class="fp-seo-ai-btn" 
									id="fp-seo-optimize-all-social"
									data-loading="true"
									data-loading-text="<?php esc_attr_e( 'Ottimizzazione...', 'fp-seo-performance' ); ?>">
								<span>🤖</span>
								<span><?php esc_html_e( 'Ottimizza con AI', 'fp-seo-performance' ); ?></span>
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>

		<style>
		/* Enhanced Social Media Styles */
		.fp-seo-social-icon {
			margin-right: var(--fp-seo-space-2);
		}

		.fp-seo-tab-icon {
			margin-right: var(--fp-seo-space-1);
		}

		.fp-seo-tab {
			position: relative;
			overflow: hidden;
		}

		.fp-seo-tab::before {
			content: '';
			position: absolute;
			bottom: 0;
			left: 0;
			right: 0;
			height: 3px;
			background: var(--platform-color);
			transform: scaleX(0);
			transition: var(--fp-seo-transition);
		}

		.fp-seo-tab-active::before {
			transform: scaleX(1);
		}

		.fp-seo-social-preview-container {
			margin-bottom: var(--fp-seo-space-6);
		}

		.fp-seo-social-preview-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: var(--fp-seo-space-3);
		}

		.fp-seo-social-preview-card {
			border: 1px solid var(--fp-seo-gray-200);
			border-radius: var(--fp-seo-radius-lg);
			overflow: hidden;
			background: var(--fp-seo-white);
			box-shadow: var(--fp-seo-shadow-sm);
			transition: var(--fp-seo-transition);
		}

		.fp-seo-social-preview-card:hover {
			box-shadow: var(--fp-seo-shadow-md);
		}

		.fp-seo-social-preview-image {
			position: relative;
			width: 100%;
			height: 200px;
			overflow: hidden;
		}

		.fp-seo-social-preview-image img {
			width: 100%;
			height: 100%;
			object-fit: cover;
		}

		.fp-seo-social-preview-image-overlay {
			position: absolute;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background: rgba(0, 0, 0, 0.5);
			display: flex;
			align-items: center;
			justify-content: center;
			opacity: 0;
			transition: var(--fp-seo-transition);
		}

		.fp-seo-social-preview-image:hover .fp-seo-social-preview-image-overlay {
			opacity: 1;
		}

		.fp-seo-social-preview-content {
			padding: var(--fp-seo-space-4);
		}

		.fp-seo-social-preview-title {
			font-weight: 600;
			font-size: var(--fp-seo-font-size-base);
			color: var(--fp-seo-gray-900);
			margin-bottom: var(--fp-seo-space-2);
			line-height: 1.3;
		}

		.fp-seo-social-preview-description {
			font-size: var(--fp-seo-font-size-sm);
			color: var(--fp-seo-gray-600);
			margin-bottom: var(--fp-seo-space-2);
			line-height: 1.4;
		}

		.fp-seo-social-preview-url {
			font-size: var(--fp-seo-font-size-xs);
			color: var(--fp-seo-gray-500);
			text-transform: uppercase;
			letter-spacing: 0.5px;
		}

		.fp-seo-form-control-group {
			display: flex;
			gap: var(--fp-seo-space-2);
		}

		.fp-seo-form-control-group .fp-seo-form-control {
			flex: 1;
		}

		.fp-seo-character-count {
			text-align: right;
			font-size: var(--fp-seo-font-size-xs);
			color: var(--fp-seo-gray-500);
			margin-top: var(--fp-seo-space-1);
		}

		.fp-seo-character-count .fp-seo-text-danger {
			color: var(--fp-seo-danger);
		}

		.fp-seo-character-count .fp-seo-text-warning {
			color: var(--fp-seo-warning);
		}

		.fp-seo-social-stats {
			display: flex;
			gap: var(--fp-seo-space-2);
		}

		.fp-seo-social-actions {
			display: flex;
			gap: var(--fp-seo-space-2);
		}

		.fp-seo-btn-icon {
			margin-right: var(--fp-seo-space-1);
		}

		.fp-seo-refresh-icon {
			display: inline-block;
			transition: transform 0.3s ease;
		}
		
		.fp-seo-loading-icon {
			animation: fp-seo-spin 1s linear infinite;
		}
		
		/* Loading spinner for buttons */
		.fp-seo-loading-spinner {
			display: inline-block;
			width: 14px;
			height: 14px;
			border: 2px solid rgba(255, 255, 255, 0.3);
			border-top-color: #fff;
			border-radius: 50%;
			animation: fp-seo-spin 0.8s linear infinite;
			vertical-align: middle;
			margin-right: 6px;
		}
		
		.fp-seo-btn.fp-seo-loading {
			position: relative;
			pointer-events: none;
			opacity: 0.7;
		}
		
		@keyframes fp-seo-spin {
			from { transform: rotate(0deg); }
			to { transform: rotate(360deg); }
		}
		
		/* Add loading class only when button is clicked */
		.fp-seo-btn.refreshing .fp-seo-refresh-icon {
			animation: fp-seo-spin 1s linear infinite;
		}

		/* Platform-specific styles */
		.fp-seo-social-preview-facebook {
			max-width: 500px;
		}

		.fp-seo-social-preview-twitter {
			max-width: 400px;
		}

		.fp-seo-social-preview-linkedin {
			max-width: 500px;
		}

		.fp-seo-social-preview-pinterest {
			max-width: 300px;
		}

		/* Responsive */
		@media (max-width: 768px) {
			.fp-seo-social-preview-header {
				flex-direction: column;
				align-items: flex-start;
				gap: var(--fp-seo-space-2);
			}

			.fp-seo-social-actions {
				flex-direction: column;
				width: 100%;
			}

			.fp-seo-social-actions .fp-seo-btn {
				width: 100%;
			}
		}
		</style>

		<script>
		jQuery(document).ready(function($) {
			// Initialize character counters
			$('.fp-seo-character-counter').each(function() {
				$(this).trigger('input');
			});

			// Tab switching with enhanced UX
			$('.fp-seo-tab').on('click', function() {
				const $tab = $(this);
				const tabId = $tab.data('tab');
				const $container = $tab.closest('.fp-seo-card');
				
				// Update tab states
				$container.find('.fp-seo-tab').removeClass('fp-seo-tab-active');
				$tab.addClass('fp-seo-tab-active');
				
				// Update content states with animation
				$container.find('.fp-seo-tab-content').removeClass('fp-seo-tab-content-active');
				$container.find('#' + tabId).addClass('fp-seo-tab-content-active fp-seo-fade-in');
				
				// Trigger custom event
				$tab.trigger('fp-seo-tab-changed', [tabId]);
			});

			// Real-time preview updates for social fields
			$('.fp-seo-character-counter').on('input', function() {
				const $field = $(this);
				const fieldId = $field.attr('id');
				const platform = fieldId.split('-')[2]; // Extract platform from ID
				const fieldType = fieldId.split('-')[3]; // Extract field type
				let value = $field.val();
				
				// Decode HTML entities properly (&#038; -> &, &#8211; -> –, &amp; -> &)
				// First decode numeric entities, then named entities
				value = value.replace(/&#(\d+);/g, function(match, dec) {
					return String.fromCharCode(dec);
				});
				value = value.replace(/&#x([0-9A-Fa-f]+);/g, function(match, hex) {
					return String.fromCharCode(parseInt(hex, 16));
				});
				// Decode named entities using a temporary div
				const $temp = $('<div>').html(value);
				value = $temp.text();
				
				// Update preview with decoded value
				$(`#fp-seo-${platform}-${fieldType}-preview`).text(value || '<?php echo esc_js( get_the_title() ); ?>');
			});

			// Helper function to decode HTML entities
			function decodeHtmlEntities(str) {
				if (!str) return '';
				// Decode numeric entities (&#038; -> &, &#8211; -> –)
				str = str.replace(/&#(\d+);/g, function(match, dec) {
					return String.fromCharCode(dec);
				});
				// Decode hex entities (&#x26; -> &)
				str = str.replace(/&#x([0-9A-Fa-f]+);/g, function(match, hex) {
					return String.fromCharCode(parseInt(hex, 16));
				});
				// Decode named entities using a temporary div
				const $temp = $('<div>').html(str);
				return $temp.text();
			}

			// Auto-sync from SERP Optimization fields to Social Media fields
			// When SERP fields change, update social previews if social fields are empty
			function syncFromSerpToSocial() {
				// Get SERP Optimization values
				let seoTitle = $('#fp-seo-title').val() || '';
				let seoDescription = $('#fp-seo-meta-description').val() || '';
				
				// Decode HTML entities from SERP fields
				seoTitle = decodeHtmlEntities(seoTitle);
				seoDescription = decodeHtmlEntities(seoDescription);
				
				// Get featured image URL - try multiple methods for robustness
				let featuredImageUrl = '';
				
				// Method 1: Get from data attribute on input field (most reliable)
				const firstImageInput = $('input[id*="-image"][data-featured-image]').first();
				if (firstImageInput.length && firstImageInput.data('featured-image')) {
					featuredImageUrl = firstImageInput.data('featured-image');
				}
				
				// Method 2: Gutenberg editor
				if (!featuredImageUrl && typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
					try {
						const featuredImageId = wp.data.select('core/editor').getEditedPostAttribute('featured_media');
						if (featuredImageId) {
							const attachment = wp.data.select('core').getEntityRecord('postType', 'attachment', featuredImageId);
							if (attachment && attachment.source_url) {
								featuredImageUrl = attachment.source_url;
							}
						}
					} catch(e) {
						// Gutenberg not available or error, continue to next method
					}
				}
				
				// Method 3: Classic editor thumbnail input
				if (!featuredImageUrl) {
					const thumbnailInput = $('input[name="_thumbnail_id"]');
					if (thumbnailInput.length && thumbnailInput.val()) {
						const thumbnailId = thumbnailInput.val();
						// NEVER use wp.media.attachment() - it can interfere with media library
						// Use AJAX instead to get attachment URL
						if (typeof ajaxurl !== 'undefined') {
							$.ajax({
								url: ajaxurl,
								type: 'POST',
								data: {
									action: 'fp_seo_get_attachment_url',
									attachment_id: thumbnailId,
									nonce: '<?php echo wp_create_nonce( 'fp_seo_get_attachment' ); ?>'
								},
								success: function(response) {
									if (response && response.success && response.data && response.data.url) {
										featuredImageUrl = response.data.url;
										// Update all image inputs with featured image
										$('input[id*="-image"]').each(function() {
											if (!$(this).val()) {
												$(this).val(featuredImageUrl).trigger('input');
											}
										});
									}
								}
							});
						}
					}
				}
				
				// Method 4: Direct PHP fallback (from initial page load)
				if (!featuredImageUrl) {
					featuredImageUrl = '<?php 
					// Get featured image using multiple methods
					$featured_url = '';
					try {
						$featured_url = get_the_post_thumbnail_url( $post->ID, 'full' );
						if ( empty( $featured_url ) && function_exists( 'wp_get_attachment_url' ) ) {
							$thumbnail_id = get_post_meta( $post->ID, '_thumbnail_id', true );
							if ( $thumbnail_id ) {
								// Usa wp_get_attachment_url invece di wp_get_attachment_image_url per evitare interferenze
								$featured_url = wp_get_attachment_url( (int) $thumbnail_id );
							}
						}
					} catch ( \Throwable $e ) {
						// Silently fail
					}
					echo esc_js( $featured_url ?: '' ); 
					?>';
				}
				
				// Update all platform previews if their fields are empty
				<?php foreach ( self::PLATFORMS as $platform_id => $platform_data ) : ?>
				let <?php echo esc_js( $platform_id ); ?>Title = $('#fp-seo-<?php echo esc_js( $platform_id ); ?>-title').val();
				let <?php echo esc_js( $platform_id ); ?>Desc = $('#fp-seo-<?php echo esc_js( $platform_id ); ?>-description').val();
				const <?php echo esc_js( $platform_id ); ?>Image = $('#fp-seo-<?php echo esc_js( $platform_id ); ?>-image').val();
				
				// Decode HTML entities from social media fields
				<?php echo esc_js( $platform_id ); ?>Title = decodeHtmlEntities(<?php echo esc_js( $platform_id ); ?>Title);
				<?php echo esc_js( $platform_id ); ?>Desc = decodeHtmlEntities(<?php echo esc_js( $platform_id ); ?>Desc);
				
				// Update title preview if field is empty (use SERP title)
				if (!<?php echo esc_js( $platform_id ); ?>Title && seoTitle) {
					$('#fp-seo-<?php echo esc_js( $platform_id ); ?>-title-preview').text(seoTitle);
					// Also update the field value if empty (for auto-fill)
					$('#fp-seo-<?php echo esc_js( $platform_id ); ?>-title').val(seoTitle);
				} else if (<?php echo esc_js( $platform_id ); ?>Title) {
					// If field has value, use it (already decoded)
					$('#fp-seo-<?php echo esc_js( $platform_id ); ?>-title-preview').text(<?php echo esc_js( $platform_id ); ?>Title);
				}
				
				// Update description preview if field is empty (use SERP description)
				if (!<?php echo esc_js( $platform_id ); ?>Desc && seoDescription) {
					$('#fp-seo-<?php echo esc_js( $platform_id ); ?>-description-preview').text(seoDescription);
					// Also update the field value if empty (for auto-fill)
					$('#fp-seo-<?php echo esc_js( $platform_id ); ?>-description').val(seoDescription);
				} else if (<?php echo esc_js( $platform_id ); ?>Desc) {
					// If field has value, use it (already decoded)
					$('#fp-seo-<?php echo esc_js( $platform_id ); ?>-description-preview').text(<?php echo esc_js( $platform_id ); ?>Desc);
				}
				
				// Update image preview and field if empty (use featured image as standard)
				if (!<?php echo esc_js( $platform_id ); ?>Image && featuredImageUrl) {
					$('#fp-seo-<?php echo esc_js( $platform_id ); ?>-image-preview').attr('src', featuredImageUrl);
					// Also update the input field if empty (for auto-fill with featured image)
					$('#fp-seo-<?php echo esc_js( $platform_id ); ?>-image').val(featuredImageUrl);
				} else if (<?php echo esc_js( $platform_id ); ?>Image) {
					// If field has value, use it
					$('#fp-seo-<?php echo esc_js( $platform_id ); ?>-image-preview').attr('src', <?php echo esc_js( $platform_id ); ?>Image);
				}
				<?php endforeach; ?>
			}

			// Listen to SERP Optimization field changes
			$('#fp-seo-title, #fp-seo-meta-description').on('input', function() {
				syncFromSerpToSocial();
			});

			// Enhanced featured image change detection - multiple methods for robustness
			// Method 1: Classic editor thumbnail input change
			$(document).on('change', 'input[name="_thumbnail_id"]', function() {
				setTimeout(function() {
					syncFromSerpToSocial();
				}, 100); // Small delay to ensure value is updated
			});

			// Method 2: Listen for post thumbnail changes via mutation observer (catches plugin interference)
			if (typeof MutationObserver !== 'undefined') {
				const thumbnailObserver = new MutationObserver(function(mutations) {
					mutations.forEach(function(mutation) {
						if (mutation.type === 'attributes' && mutation.attributeName === 'value') {
							const target = mutation.target;
							if (target && target.name === '_thumbnail_id') {
								setTimeout(function() {
									syncFromSerpToSocial();
								}, 100);
							}
						}
					});
				});
				
				// Observe thumbnail input if it exists
				const thumbnailInput = document.querySelector('input[name="_thumbnail_id"]');
				if (thumbnailInput) {
					thumbnailObserver.observe(thumbnailInput, {
						attributes: true,
						attributeFilter: ['value']
					});
				}
			}

			// Method 3: Gutenberg featured image updates
			if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
				let lastFeaturedImageId = null;
				wp.data.subscribe(function() {
					try {
						const featuredImageId = wp.data.select('core/editor').getEditedPostAttribute('featured_media');
						if (featuredImageId !== lastFeaturedImageId) {
							lastFeaturedImageId = featuredImageId;
							if (featuredImageId) {
								wp.data.select('core').getEntityRecord('postType', 'attachment', featuredImageId).then(function(attachment) {
									if (attachment && attachment.source_url) {
										<?php foreach ( self::PLATFORMS as $platform_id => $platform_data ) : ?>
										const <?php echo esc_js( $platform_id ); ?>Image = $('#fp-seo-<?php echo esc_js( $platform_id ); ?>-image').val();
										if (!<?php echo esc_js( $platform_id ); ?>Image) {
											$('#fp-seo-<?php echo esc_js( $platform_id ); ?>-image-preview').attr('src', attachment.source_url);
											// Also update the input field if empty (auto-fill with featured image)
											$('#fp-seo-<?php echo esc_js( $platform_id ); ?>-image').val(attachment.source_url);
											// Update data attribute
											$('#fp-seo-<?php echo esc_js( $platform_id ); ?>-image').data('featured-image', attachment.source_url);
										}
										<?php endforeach; ?>
									}
								}).catch(function() {
									// Attachment not found, try sync anyway
									syncFromSerpToSocial();
								});
							} else {
								// Featured image removed, sync to update previews
								syncFromSerpToSocial();
							}
						}
					} catch(e) {
						// Error accessing Gutenberg data, continue silently
					}
				});
			}

			// Method 4: Periodic check for featured image changes (fallback for plugin interference)
			let lastFeaturedImageCheck = '';
			setInterval(function() {
				let currentFeaturedImage = '';
				
				// Check Gutenberg
				if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
					try {
						const featuredImageId = wp.data.select('core/editor').getEditedPostAttribute('featured_media');
						if (featuredImageId) {
							const attachment = wp.data.select('core').getEntityRecord('postType', 'attachment', featuredImageId);
							if (attachment && attachment.source_url) {
								currentFeaturedImage = attachment.source_url;
							}
						}
					} catch(e) {
						// Ignore errors
					}
				}
				
				// Check classic editor - NEVER use wp.media.attachment() to avoid interference
				// Use DOM-based method instead
				if (!currentFeaturedImage) {
					const $featuredImg = $('#postimagediv .inside img');
					if ($featuredImg.length > 0) {
						currentFeaturedImage = $featuredImg.attr('src');
					}
				}
				
				// If featured image changed, sync
				if (currentFeaturedImage && currentFeaturedImage !== lastFeaturedImageCheck) {
					lastFeaturedImageCheck = currentFeaturedImage;
					syncFromSerpToSocial();
				}
			}, 1000); // Check every second

			// Listen to social field changes to update previews
			$('.fp-seo-character-counter').on('input', function() {
				const $field = $(this);
				const fieldId = $field.attr('id');
				if (fieldId && fieldId.includes('-title') || fieldId.includes('-description')) {
					const platform = fieldId.split('-')[2];
					const fieldType = fieldId.split('-')[3];
					let value = $field.val();
					
					// Decode HTML entities
					const $temp = $('<div>').html(value);
					value = $temp.text();
					
					// Update preview
					$(`#fp-seo-${platform}-${fieldType}-preview`).text(value || '');
				}
			});

			// Listen to social image field changes
			$('input[id*="-image"]').on('input', function() {
				const $field = $(this);
				const fieldId = $field.attr('id');
				if (fieldId && fieldId.includes('-image')) {
					const platform = fieldId.split('-')[2];
					const value = $field.val();
					if (value) {
						$(`#fp-seo-${platform}-image-preview`).attr('src', value);
					} else {
						// If cleared, sync from featured image
						syncFromSerpToSocial();
					}
				}
			});

			// Initial sync on page load
			setTimeout(function() {
				syncFromSerpToSocial();
			}, 500); // Small delay to ensure all fields are loaded

			// Refresh preview buttons
			$('[id^="fp-seo-refresh-preview-"]').on('click', function() {
				const $btn = $(this);
				const platformId = $btn.attr('id').replace('fp-seo-refresh-preview-', '');
				
				// Add refreshing class to show animation
				$btn.addClass('refreshing');
				
				// Refresh the preview by syncing from SERP
				syncFromSerpToSocial();
				
				// Remove refreshing class after a short delay
				setTimeout(function() {
					$btn.removeClass('refreshing');
				}, 500);
			});

			// Image selection
			$('.fp-seo-image-select').on('click', function() {
				const $button = $(this);
				const targetField = $button.data('target');
				const previewTarget = $button.data('preview');
				
				if (typeof wp !== 'undefined' && wp.media) {
					const frame = wp.media({
						title: 'Select Social Media Image',
						button: {
							text: 'Use Image'
						},
						multiple: false
					});
					
					frame.on('select', function() {
						const attachment = frame.state().get('selection').first().toJSON();
						$(`#${targetField}`).val(attachment.url);
						$(`#${previewTarget}`).attr('src', attachment.url);
						
						FPSeoUI.showNotification('Image updated successfully!', 'success');
					});
					
					frame.open();
				} else {
					FPSeoUI.showNotification('Media library not available. Please refresh the page.', 'error');
				}
			});

			// Handle "Cambia Immagine" button in live preview
			$('.fp-seo-social-preview-image-overlay button').on('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				
				// Find the parent preview card to get the platform
				const $previewCard = $(this).closest('.fp-seo-social-preview-card');
				if (!$previewCard.length) {
					return;
				}
				
				// Extract platform from class (e.g., fp-seo-social-preview-facebook -> facebook)
				const platformMatch = $previewCard.attr('class').match(/fp-seo-social-preview-(\w+)/);
				if (!platformMatch) {
					return;
				}
				
				const platform = platformMatch[1];
				const targetField = `#fp-seo-${platform}-image`;
				const previewTarget = `#fp-seo-${platform}-image-preview`;
				
				if (typeof wp !== 'undefined' && wp.media) {
					const frame = wp.media({
						title: 'Select Social Media Image',
						button: {
							text: 'Use Image'
						},
						multiple: false,
						library: {
							type: 'image'
						}
					});
					
					frame.on('select', function() {
						const attachment = frame.state().get('selection').first().toJSON();
						$(targetField).val(attachment.url).trigger('input');
						$(previewTarget).attr('src', attachment.url);
						
						FPSeoUI.showNotification('Image updated successfully!', 'success');
					});
					
					frame.open();
				} else {
					FPSeoUI.showNotification('Media library not available. Please refresh the page.', 'error');
				}
			});

			// Use featured image button
			$('.fp-seo-use-featured-image').on('click', function() {
				const $button = $(this);
				const targetField = $button.data('target');
				const previewTarget = $button.data('preview');
				const featuredUrl = $button.data('featured-url');
				
				if (featuredUrl) {
					$(`#${targetField}`).val(featuredUrl).trigger('input');
					$(`#${previewTarget}`).attr('src', featuredUrl);
					FPSeoUI.showNotification('Featured image applied successfully!', 'success');
				} else {
					// Try to get featured image dynamically
					syncFromSerpToSocial();
					FPSeoUI.showNotification('Featured image synced!', 'success');
				}
			});

			// AI Optimization
			$('#fp-seo-optimize-all-social').on('click', function() {
				const $btn = $(this);
				const postId = <?php echo get_the_ID(); ?>;
				
				// Prevent multiple clicks
				if ($btn.prop('disabled')) {
					return;
				}
				
				FPSeoUI.showLoading($btn, '<?php echo esc_js( __( 'Ottimizzazione con AI...', 'fp-seo-performance' ) ); ?>');
				
				// Safety timeout to ensure button is always restored
				const safetyTimeout = setTimeout(function() {
					FPSeoUI.hideLoading($btn);
					FPSeoUI.showNotification('Request timeout. Please try again.', 'error');
				}, 30000); // 30 seconds timeout
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					timeout: 25000, // 25 seconds AJAX timeout
					data: {
						action: 'fp_seo_optimize_social',
						post_id: postId,
						platform: 'all',
						nonce: '<?php echo wp_create_nonce( 'fp_seo_social_nonce' ); ?>'
					},
					success: function(response) {
						clearTimeout(safetyTimeout);
						FPSeoUI.hideLoading($btn);
						
						if (response && response.success) {
							// Update all fields with AI suggestions (decode HTML entities)
							if (response.data && typeof response.data === 'object') {
								Object.keys(response.data).forEach(platform => {
									if (response.data[platform] && response.data[platform].title) {
										const decodedTitle = decodeHtmlEntities(response.data[platform].title);
										$(`#fp-seo-${platform}-title`).val(decodedTitle).trigger('input');
									}
									if (response.data[platform] && response.data[platform].description) {
										const decodedDesc = decodeHtmlEntities(response.data[platform].description);
										$(`#fp-seo-${platform}-description`).val(decodedDesc).trigger('input');
									}
								});
							}
							
							FPSeoUI.showNotification('Social media content optimized successfully!', 'success');
						} else {
							const errorMsg = (response && response.data) ? (typeof response.data === 'string' ? response.data : 'Unknown error') : 'Optimization failed';
							FPSeoUI.showNotification('Error: ' + errorMsg, 'error');
						}
					},
					error: function(xhr, status, error) {
						clearTimeout(safetyTimeout);
						FPSeoUI.hideLoading($btn);
						
						let errorMsg = 'An error occurred. Please try again.';
						if (status === 'timeout') {
							errorMsg = 'Request timeout. Please try again.';
						} else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
							errorMsg = xhr.responseJSON.data.message;
						}
						
						FPSeoUI.showNotification(errorMsg, 'error');
					},
					complete: function() {
						// Always ensure button is restored, even if something goes wrong
						clearTimeout(safetyTimeout);
						setTimeout(function() {
							FPSeoUI.hideLoading($btn);
						}, 100);
					}
				});
			});

			// Preview all platforms
			$('#fp-seo-preview-all-social').on('click', function() {
				// Open all platform previews in new tabs
				const platforms = ['facebook', 'twitter', 'linkedin', 'pinterest'];
				platforms.forEach(platform => {
					window.open(`#${platform}`, '_blank');
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Get social meta data for post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	private function get_social_meta( int $post_id ): array {
		try {
			// Clear cache before retrieving
			clean_post_cache( $post_id );
			wp_cache_delete( $post_id, 'post_meta' );
			wp_cache_delete( $post_id, 'posts' );
			if ( function_exists( 'wp_cache_flush_group' ) ) {
				wp_cache_flush_group( 'post_meta' );
			}
			if ( function_exists( 'update_post_meta_cache' ) ) {
				update_post_meta_cache( array( $post_id ) );
			}
			
			$cache_key = 'fp_seo_social_meta_' . $post_id;
			
			return Cache::remember( $cache_key, function() use ( $post_id ) {
				try {
					$meta = get_post_meta( $post_id, '_fp_seo_social_meta', true );
					
					// Fallback: query diretta al database se get_post_meta restituisce vuoto
					if ( empty( $meta ) ) {
						global $wpdb;
						if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'get_var' ) && method_exists( $wpdb, 'prepare' ) ) {
							$db_value = $wpdb->get_var( $wpdb->prepare( 
								"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", 
								$post_id, 
								'_fp_seo_social_meta' 
							) );
							if ( $db_value !== null ) {
								$unserialized = maybe_unserialize( $db_value );
								$meta = is_array( $unserialized ) ? $unserialized : array();
							}
						}
					}
					
					return is_array( $meta ) ? $meta : array();
				} catch ( \Throwable $e ) {
					// Return empty array on error
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						\FP\SEO\Utils\Logger::debug( 'FP SEO: Error getting social meta', array(
							'error' => $e->getMessage(),
							'post_id' => $post_id,
						) );
					}
					return array();
				}
			}, HOUR_IN_SECONDS );
		} catch ( \Throwable $e ) {
			// Return empty array on error
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				\FP\SEO\Utils\Logger::error( 'FP SEO: Fatal error getting social meta', array(
					'error' => $e->getMessage(),
					'post_id' => $post_id,
				) );
			}
			return array();
		}
	}

	/**
	 * Get preview data for post.
	 *
	 * @param WP_Post $post Post object.
	 * @return array<string, mixed>
	 */
	private function get_preview_data( $post ): array {
		try {
			$social_meta = $this->get_social_meta( $post->ID );
			
			// Use social meta if available, otherwise use SEO title/description from SERP Optimization
			// This ensures social preview automatically uses SERP Optimization values as fallback
			$title = '';
			try {
				$title = ! empty( $social_meta['facebook_title'] ) 
					? wp_specialchars_decode( $social_meta['facebook_title'], ENT_QUOTES )
					: MetadataResolver::resolve_seo_title( $post );
			} catch ( \Throwable $e ) {
				$title = get_the_title( $post->ID );
			}
			
			// For description: use social meta, then SEO description from SERP Optimization
			$description = '';
			try {
				$description = ! empty( $social_meta['facebook_description'] )
					? wp_specialchars_decode( $social_meta['facebook_description'], ENT_QUOTES )
					: MetadataResolver::resolve_meta_description( $post );
			} catch ( \Throwable $e ) {
				$description = get_the_excerpt( $post->ID ) ?: '';
			}
			
			// For image: use social meta if available, then featured image as standard, then default
			$image = '';
			try {
				$featured_image = get_the_post_thumbnail_url( $post->ID, 'full' );
				
				if ( ! empty( $social_meta['facebook_image'] ) ) {
					$image = esc_url_raw( $social_meta['facebook_image'] );
				} elseif ( $featured_image ) {
					// Use featured image as standard when no social image is set
					$image = $featured_image;
				} else {
					// Final fallback to default social image
					$image = get_option( 'fp_seo_social_default_image', '' );
				}
			} catch ( \Throwable $e ) {
				// Silently fail - image is optional
				$image = '';
			}
			
			$url = '';
			try {
				$url = get_permalink( $post->ID );
			} catch ( \Throwable $e ) {
				$url = '';
			}
			
			return array(
				'title' => $title ?: get_the_title( $post->ID ),
				'description' => $description ?: '',
				'url' => $url ?: '',
				'image' => $image ?: '',
			);
		} catch ( \Throwable $e ) {
			// Fallback to basic post data
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				\FP\SEO\Utils\Logger::error( 'FP SEO: Error getting preview data', array(
					'error' => $e->getMessage(),
					'post_id' => isset( $post->ID ) ? $post->ID : 0,
				) );
			}
			return array(
				'title' => get_the_title( $post->ID ),
				'description' => get_the_excerpt( $post->ID ) ?: '',
				'url' => get_permalink( $post->ID ) ?: '',
				'image' => get_the_post_thumbnail_url( $post->ID, 'full' ) ?: '',
			);
		}
	}

	/**
	 * Save social media meta data.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_social_meta( int $post_id ): void {
		// CRITICAL: Check post type FIRST, before any processing
		// This ensures we don't interfere with unsupported post types (attachments, Nectar Sliders, etc.)
		$post_type = get_post_type( $post_id );
		$supported_types = \FP\SEO\Utils\PostTypes::analyzable();
		
		// If not a supported post type, return immediately without any processing
		if ( ! in_array( $post_type, $supported_types, true ) ) {
			// Log only in debug mode and only once per post type to avoid spam
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				static $logged_types = array();
				if ( ! isset( $logged_types[ $post_type ] ) ) {
					\FP\SEO\Utils\Logger::debug( 'ImprovedSocialMediaManager::save_social_meta skipped - unsupported post type', array(
						'post_id' => $post_id,
						'post_type' => $post_type,
						'supported_types' => $supported_types,
					) );
					$logged_types[ $post_type ] = true;
				}
			}
			return; // Exit immediately - no interference with WordPress core saving
		}
		
		if ( ! isset( $_POST['fp_seo_social_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fp_seo_social_nonce'] ) ), 'fp_seo_social_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$social_meta = array();

		foreach ( self::PLATFORMS as $platform_id => $platform_data ) {
			// Decode HTML entities before sanitizing to preserve actual characters like &
			$title_raw = isset( $_POST[ 'fp_seo_' . $platform_id . '_title' ] ) ? wp_unslash( $_POST[ 'fp_seo_' . $platform_id . '_title' ] ) : '';
			$title_decoded = html_entity_decode( (string) $title_raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$social_meta[ $platform_id . '_title' ] = sanitize_text_field( $title_decoded );
			
			$description_raw = isset( $_POST[ 'fp_seo_' . $platform_id . '_description' ] ) ? wp_unslash( $_POST[ 'fp_seo_' . $platform_id . '_description' ] ) : '';
			$description_decoded = html_entity_decode( (string) $description_raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$social_meta[ $platform_id . '_description' ] = sanitize_textarea_field( $description_decoded );
			
			$social_meta[ $platform_id . '_image' ] = esc_url_raw( $_POST[ 'fp_seo_' . $platform_id . '_image' ] ?? '' );
		}

		// Twitter specific
		$social_meta['twitter_card_type'] = sanitize_text_field( $_POST['fp_seo_twitter_card_type'] ?? 'summary_large_image' );

		update_post_meta( $post_id, '_fp_seo_social_meta', $social_meta );

		// Clear cache
		Cache::delete( 'fp_seo_social_meta_' . $post_id );
	}

	/**
	 * Output social media meta tags in head.
	 */
	public function output_meta_tags(): void {
		if ( is_admin() || is_feed() ) {
			return;
		}

		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}

		$social_meta = $this->get_social_meta( $post_id );
		$post        = get_post( $post_id );
		$post_title  = get_the_title( $post_id );
		$post_excerpt = get_the_excerpt( $post_id );
		$post_content = $post ? (string) $post->post_content : (string) get_post_field( 'post_content', $post_id );
		$permalink   = get_permalink( $post_id );

		if ( '' === trim( (string) $post_excerpt ) ) {
			// Use content without shortcodes as fallback
			$content_without_shortcodes = strip_shortcodes( $post_content );
			$post_excerpt = wp_trim_words( wp_strip_all_tags( $content_without_shortcodes ), 30, '' );
		}

		$defaults = array(
			'title'       => $post_title,
			'description' => trim( (string) $post_excerpt ),
			'permalink'   => $permalink,
			'post_id'     => $post_id,
		);

		echo "\n<!-- FP SEO Performance Social Media Tags -->\n";
		
		// Open Graph tags
		$this->output_open_graph_tags( $social_meta, $defaults );
		
		// Twitter Card tags
		$this->output_twitter_card_tags( $social_meta, $defaults );
		
		// LinkedIn tags
		$this->output_linkedin_tags( $social_meta, $defaults );
		
		// Pinterest tags
		$this->output_pinterest_tags( $social_meta, $defaults );
		
		echo "<!-- End FP SEO Performance Social Media Tags -->\n";
	}

	/**
	 * Output Open Graph meta tags.
	 *
	 * @param array<string, mixed> $meta Social meta data.
	 */
	private function output_open_graph_tags( array $meta, array $defaults ): void {
		$title       = ! empty( $meta['facebook_title'] ) ? $meta['facebook_title'] : $defaults['title'];
		$description = ! empty( $meta['facebook_description'] ) ? $meta['facebook_description'] : $defaults['description'];
		$permalink   = $defaults['permalink'];

		$og_tags = array(
			'og:title' => $title,
			'og:description' => $description,
			'og:type' => 'article',
			'og:url' => $permalink,
			'og:site_name' => get_bloginfo( 'name' ),
			'og:locale' => get_locale(),
		);

		// Add image
		$og_image = $this->get_social_image( $meta, 'facebook', $defaults['post_id'] );
		if ( $og_image ) {
			$og_tags['og:image'] = $og_image;
			$og_tags['og:image:width'] = 1200;
			$og_tags['og:image:height'] = 630;
			$og_tags['og:image:alt'] = $title;
		}

		foreach ( $og_tags as $property => $content ) {
			if ( ! empty( $content ) ) {
				echo '<meta property="' . esc_attr( $property ) . '" content="' . esc_attr( $content ) . '">' . "\n";
			}
		}
	}

	/**
	 * Output Twitter Card meta tags.
	 *
	 * @param array<string, mixed> $meta Social meta data.
	 */
	private function output_twitter_card_tags( array $meta, array $defaults ): void {
		$title       = ! empty( $meta['twitter_title'] ) ? $meta['twitter_title'] : $defaults['title'];
		$description = ! empty( $meta['twitter_description'] ) ? $meta['twitter_description'] : $defaults['description'];
		$permalink   = $defaults['permalink'];

		$twitter_tags = array(
			'twitter:card' => $meta['twitter_card_type'] ?? 'summary_large_image',
			'twitter:title' => $title,
			'twitter:description' => $description,
			'twitter:url' => $permalink,
		);

		// Add image
		$twitter_image = $this->get_social_image( $meta, 'twitter', $defaults['post_id'] );
		if ( $twitter_image ) {
			$twitter_tags['twitter:image'] = $twitter_image;
			$twitter_tags['twitter:image:alt'] = $title;
		}

		foreach ( $twitter_tags as $name => $content ) {
			if ( ! empty( $content ) ) {
				echo '<meta name="' . esc_attr( $name ) . '" content="' . esc_attr( $content ) . '">' . "\n";
			}
		}
	}

	/**
	 * Output LinkedIn meta tags.
	 *
	 * @param array<string, mixed> $meta Social meta data.
	 */
	private function output_linkedin_tags( array $meta, array $defaults ): void {
		$title       = ! empty( $meta['linkedin_title'] ) ? $meta['linkedin_title'] : $defaults['title'];
		$description = ! empty( $meta['linkedin_description'] ) ? $meta['linkedin_description'] : $defaults['description'];
		$permalink   = $defaults['permalink'];

		$linkedin_tags = array(
			'linkedin:title' => $title,
			'linkedin:description' => $description,
			'linkedin:url' => $permalink,
		);

		$linkedin_image = $this->get_social_image( $meta, 'linkedin', $defaults['post_id'] );
		if ( $linkedin_image ) {
			$linkedin_tags['linkedin:image'] = $linkedin_image;
		}

		foreach ( $linkedin_tags as $name => $content ) {
			if ( ! empty( $content ) ) {
				echo '<meta name="' . esc_attr( $name ) . '" content="' . esc_attr( $content ) . '">' . "\n";
			}
		}
	}

	/**
	 * Output Pinterest meta tags.
	 *
	 * @param array<string, mixed> $meta Social meta data.
	 */
	private function output_pinterest_tags( array $meta, array $defaults ): void {
		$title       = ! empty( $meta['pinterest_title'] ) ? $meta['pinterest_title'] : $defaults['title'];
		$description = ! empty( $meta['pinterest_description'] ) ? $meta['pinterest_description'] : $defaults['description'];
		$permalink   = $defaults['permalink'];

		$pinterest_tags = array(
			'pinterest:title' => $title,
			'pinterest:description' => $description,
			'pinterest:url' => $permalink,
		);

		$pinterest_image = $this->get_social_image( $meta, 'pinterest', $defaults['post_id'] );
		if ( $pinterest_image ) {
			$pinterest_tags['pinterest:image'] = $pinterest_image;
		}

		foreach ( $pinterest_tags as $name => $content ) {
			if ( ! empty( $content ) ) {
				echo '<meta name="' . esc_attr( $name ) . '" content="' . esc_attr( $content ) . '">' . "\n";
			}
		}
	}

	/**
	 * Get social image for platform.
	 *
	 * @param array<string, mixed> $meta Social meta data.
	 * @param string $platform Platform name.
	 * @return string|null
	 */
	private function get_social_image( array $meta, string $platform, int $post_id ): ?string {
		// Check for platform-specific image
		$platform_image = $meta[ $platform . '_image' ] ?? null;
		if ( ! empty( $platform_image ) ) {
			return $platform_image;
		}

		// Check for featured image
		$featured_image = get_the_post_thumbnail_url( $post_id, 'full' );
		if ( $featured_image ) {
			return $featured_image;
		}

		// Check for default social image
		$default_image = get_option( 'fp_seo_social_default_image' );
		if ( $default_image ) {
			return $default_image;
		}

		return null;
	}

	/**
	 * Render Social Media management page.
	 */
	public function render_social_page(): void {
		?>
		<div class="wrap fp-seo-ui">
			<div class="fp-seo-container">
				<h1 class="fp-seo-heading-1">
					<span class="fp-seo-social-icon">📱</span>
					<?php esc_html_e( 'Social Media Optimization', 'fp-seo-performance' ); ?>
				</h1>
				
				<div class="fp-seo-grid fp-seo-grid-3">
					<div class="fp-seo-card">
						<div class="fp-seo-card-body">
							<h3 class="fp-seo-heading-3"><?php esc_html_e( 'Posts with Social Meta', 'fp-seo-performance' ); ?></h3>
							<div class="fp-seo-stat-number"><?php echo $this->get_posts_with_social_meta_count(); ?></div>
						</div>
					</div>
					
					<div class="fp-seo-card">
						<div class="fp-seo-card-body">
							<h3 class="fp-seo-heading-3"><?php esc_html_e( 'Platforms Supported', 'fp-seo-performance' ); ?></h3>
							<div class="fp-seo-stat-number"><?php echo count( self::PLATFORMS ); ?></div>
						</div>
					</div>
					
					<div class="fp-seo-card">
						<div class="fp-seo-card-body">
							<h3 class="fp-seo-heading-3"><?php esc_html_e( 'Optimization Score', 'fp-seo-performance' ); ?></h3>
							<div class="fp-seo-stat-number"><?php echo $this->get_optimization_score(); ?>%</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get count of posts with social meta.
	 *
	 * @return int
	 */
	private function get_posts_with_social_meta_count(): int {
		global $wpdb;
		
		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_fp_seo_social_meta' AND meta_value != ''"
		);
		
		return (int) $count;
	}

	/**
	 * Get optimization score.
	 *
	 * @return int
	 */
	private function get_optimization_score(): int {
		// Simple calculation - in real implementation, this would be more sophisticated
		$count_posts = wp_count_posts( 'post' );
		$total_posts = isset( $count_posts->publish ) ? (int) $count_posts->publish : 0;
		$optimized_posts = $this->get_posts_with_social_meta_count();

		if ( $total_posts <= 0 ) {
			return 0;
		}

		$score = ( $optimized_posts / $total_posts ) * 100;

		return (int) max( 0, min( 100, round( $score ) ) );
	}

	/**
	 * AJAX handler for social media preview.
	 */
	public function ajax_preview_social(): void {
		check_ajax_referer( 'fp_seo_social_nonce', 'nonce' );

		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		$platform = sanitize_text_field( $_POST['platform'] ?? 'facebook' );

		if ( ! $post_id ) {
			wp_send_json_error( 'Invalid post ID' );
		}

		$social_meta = $this->get_social_meta( $post_id );
		$preview_data = $this->get_preview_data( get_post( $post_id ) );

		wp_send_json_success( array(
			'platform' => $platform,
			'preview' => $preview_data,
			'meta' => $social_meta,
		) );
	}

	/**
	 * AJAX handler for social media optimization.
	 */
	public function ajax_optimize_social(): void {
		check_ajax_referer( 'fp_seo_social_nonce', 'nonce' );

		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		$platform = sanitize_text_field( $_POST['platform'] ?? 'all' );

		if ( ! $post_id ) {
			wp_send_json_error( 'Invalid post ID' );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( 'Post not found' );
		}

		// Use AI to optimize social media content
		$optimized = $this->optimize_social_with_ai( $post, $platform );

		wp_send_json_success( $optimized );
	}

	/**
	 * AJAX handler to get attachment URL.
	 * Used as fallback when wp.media is not available.
	 */
	public function ajax_get_attachment_url(): void {
		check_ajax_referer( 'fp_seo_get_attachment', 'nonce' );

		$attachment_id = (int) ( $_POST['attachment_id'] ?? 0 );

		if ( ! $attachment_id ) {
			wp_send_json_error( 'Invalid attachment ID' );
		}

		// Usa wp_get_attachment_url invece di wp_get_attachment_image_url per evitare interferenze con dimensioni
		$url = wp_get_attachment_url( $attachment_id );

		if ( ! $url ) {
			wp_send_json_error( 'Attachment not found' );
		}

		wp_send_json_success( array( 'url' => $url ) );
	}

	/**
	 * Optimize social media content with AI.
	 *
	 * @param WP_Post $post Post object.
	 * @param string $platform Social platform.
	 * @return array<string, mixed>
	 */
	private function optimize_social_with_ai( $post, string $platform ): array {
		$title = get_the_title( $post->ID );
		
		// Extract clean content, handling WPBakery shortcodes properly
		$content = $this->extract_clean_content( $post->post_content );
		
		$excerpt = get_the_excerpt( $post->ID );

		$optimized = array();

		if ( $platform === 'all' ) {
			foreach ( self::PLATFORMS as $platform_id => $platform_data ) {
				$optimized[ $platform_id ] = array(
					'title' => $this->optimize_for_platform( $title, $platform_id ),
					'description' => $this->optimize_for_platform( $excerpt ?: wp_trim_words( $content, 20 ), $platform_id )
				);
			}
		} else {
			$optimized[ $platform ] = array(
				'title' => $this->optimize_for_platform( $title, $platform ),
				'description' => $this->optimize_for_platform( $excerpt ?: wp_trim_words( $content, 20 ), $platform )
			);
		}

		return $optimized;
	}

	/**
	 * Optimize content for specific platform.
	 *
	 * @param string $content Content to optimize.
	 * @param string $platform Platform name.
	 * @return string
	 */
	private function optimize_for_platform( string $content, string $platform ): string {
		$platform_data = self::PLATFORMS[ $platform ] ?? null;
		if ( ! $platform_data ) {
			return $content;
		}

		$limit = $platform_data['title_limit'];
		$content = wp_trim_words( $content, $limit / 6 ); // Rough word estimation
		
		// Decode all HTML entities to ensure clean text
		$content = html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		
		return $content;
	}

	/**
	 * Extract clean content from post, handling WPBakery shortcodes.
	 *
	 * @param string $post_content Raw post content.
	 * @return string Clean text content.
	 */
	private function extract_clean_content( string $post_content ): string {
		if ( empty( $post_content ) ) {
			return '';
		}

		// Check if content contains WPBakery shortcodes
		if ( strpos( $post_content, '[vc_' ) !== false || strpos( $post_content, '[vc_row' ) !== false ) {
			// Use WPBakeryContentExtractor to get clean text (static method)
			if ( class_exists( '\FP\SEO\Utils\WPBakeryContentExtractor' ) ) {
				$text = \FP\SEO\Utils\WPBakeryContentExtractor::extract_text( $post_content );
				
				if ( ! empty( $text ) ) {
					// Clean up the extracted text (already cleaned by extract_text, but normalize whitespace)
					$text = preg_replace( '/\s+/', ' ', $text ); // Normalize whitespace
					return trim( $text );
				}
			}
		}

		// Fallback: standard WordPress shortcode removal
		// First render shortcodes, then strip tags
		$rendered = do_shortcode( $post_content );
		$content = wp_strip_all_tags( $rendered );
		$content = preg_replace( '/\s+/', ' ', $content ); // Normalize whitespace
		
		return trim( $content );
	}
}

