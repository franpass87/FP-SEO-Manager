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
use FP\SEO\Utils\PerformanceConfig;

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
		// Non registra la metabox separata - il contenuto è integrato in Metabox.php
		// add_action( 'add_meta_boxes', array( $this, 'add_social_metabox' ) );
		add_action( 'save_post', array( $this, 'save_social_meta' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
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

		$is_fp_seo_page = strpos( $screen->id, 'fp-seo' ) !== false;
		$is_post_editor = in_array( $screen->id, array( 'post', 'page' ), true );

		if ( $is_fp_seo_page || $is_post_editor ) {
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
		$social_meta = $this->get_social_meta( $post->ID );
		$preview_data = $this->get_preview_data( $post );
		
		wp_nonce_field( 'fp_seo_social_meta', 'fp_seo_social_nonce' );
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
									<h4 class="fp-seo-heading-4"><?php esc_html_e( 'Live Preview', 'fp-seo-performance' ); ?></h4>
									<button type="button" class="fp-seo-btn fp-seo-btn-sm fp-seo-btn-secondary" 
											id="fp-seo-refresh-preview-<?php echo esc_attr( $platform_id ); ?>">
										<span class="fp-seo-loading-icon">🔄</span>
										<?php esc_html_e( 'Refresh', 'fp-seo-performance' ); ?>
									</button>
								</div>
								
								<div class="fp-seo-social-preview-card fp-seo-social-preview-<?php echo esc_attr( $platform_id ); ?>">
									<div class="fp-seo-social-preview-image">
										<img id="fp-seo-<?php echo esc_attr( $platform_id ); ?>-image-preview" 
											 src="<?php echo esc_url( $preview_data['image'] ); ?>" 
											 alt="<?php esc_attr_e( 'Social media preview image', 'fp-seo-performance' ); ?>">
										<div class="fp-seo-social-preview-image-overlay">
											<button type="button" class="fp-seo-btn fp-seo-btn-sm fp-seo-btn-primary">
												<?php esc_html_e( 'Change Image', 'fp-seo-performance' ); ?>
											</button>
										</div>
									</div>
									<div class="fp-seo-social-preview-content">
										<div class="fp-seo-social-preview-title" 
											 id="fp-seo-<?php echo esc_attr( $platform_id ); ?>-title-preview">
											<?php echo esc_html( $preview_data['title'] ); ?>
										</div>
										<div class="fp-seo-social-preview-description" 
											 id="fp-seo-<?php echo esc_attr( $platform_id ); ?>-description-preview">
											<?php echo esc_html( $preview_data['description'] ); ?>
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
										   value="<?php echo esc_attr( $social_meta[ $platform_id . '_title' ] ?? '' ); ?>" 
										   maxlength="<?php echo esc_attr( $platform_data['title_limit'] ); ?>"
										   placeholder="<?php esc_attr_e( 'Enter title for social sharing', 'fp-seo-performance' ); ?>">
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
											  placeholder="<?php esc_attr_e( 'Enter description for social sharing', 'fp-seo-performance' ); ?>"><?php echo esc_textarea( $social_meta[ $platform_id . '_description' ] ?? '' ); ?></textarea>
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
												<?php esc_html_e( 'Summary', 'fp-seo-performance' ); ?>
											</option>
											<option value="summary_large_image" <?php selected( $social_meta['twitter_card_type'] ?? '', 'summary_large_image' ); ?>>
												<?php esc_html_e( 'Summary Large Image', 'fp-seo-performance' ); ?>
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
									</label>
									<div class="fp-seo-form-control-group">
										<input type="url" 
											   id="fp-seo-<?php echo esc_attr( $platform_id ); ?>-image" 
											   name="fp_seo_<?php echo esc_attr( $platform_id ); ?>_image" 
											   class="fp-seo-form-control" 
											   value="<?php echo esc_attr( $social_meta[ $platform_id . '_image' ] ?? '' ); ?>" 
											   placeholder="https://example.com/image.jpg">
										<button type="button" 
												class="fp-seo-btn fp-seo-btn-secondary fp-seo-image-select" 
												data-target="fp-seo-<?php echo esc_attr( $platform_id ); ?>-image"
												data-preview="fp-seo-<?php echo esc_attr( $platform_id ); ?>-image-preview">
											<?php esc_html_e( 'Select', 'fp-seo-performance' ); ?>
										</button>
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
								<?php esc_html_e( 'Preview All', 'fp-seo-performance' ); ?>
							</button>
							<button type="button" 
									class="fp-seo-btn fp-seo-btn-primary" 
									id="fp-seo-optimize-all-social"
									data-loading="true"
									data-loading-text="<?php esc_attr_e( 'Optimizing...', 'fp-seo-performance' ); ?>">
								<span class="fp-seo-btn-icon">🤖</span>
								<?php esc_html_e( 'Optimize with AI', 'fp-seo-performance' ); ?>
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

		.fp-seo-loading-icon {
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

			// Real-time preview updates
			$('.fp-seo-character-counter').on('input', function() {
				const $field = $(this);
				const fieldId = $field.attr('id');
				const platform = fieldId.split('-')[2]; // Extract platform from ID
				const fieldType = fieldId.split('-')[3]; // Extract field type
				const value = $field.val();
				
				// Update preview
				$(`#fp-seo-${platform}-${fieldType}-preview`).text(value || '<?php echo esc_js( get_the_title() ); ?>');
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
				}
			});

			// AI Optimization
			$('#fp-seo-optimize-all-social').on('click', function() {
				const $btn = $(this);
				const postId = <?php echo get_the_ID(); ?>;
				
				FPSeoUI.showLoading($btn, 'Optimizing with AI...');
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'fp_seo_optimize_social',
						post_id: postId,
						platform: 'all',
						nonce: '<?php echo wp_create_nonce( 'fp_seo_social_nonce' ); ?>'
					},
					success: function(response) {
						FPSeoUI.hideLoading($btn);
						
						if (response.success) {
							// Update all fields with AI suggestions
							Object.keys(response.data).forEach(platform => {
								if (response.data[platform].title) {
									$(`#fp-seo-${platform}-title`).val(response.data[platform].title).trigger('input');
								}
								if (response.data[platform].description) {
									$(`#fp-seo-${platform}-description`).val(response.data[platform].description).trigger('input');
								}
							});
							
							FPSeoUI.showNotification('Social media content optimized successfully!', 'success');
						} else {
							FPSeoUI.showNotification('Error: ' + response.data, 'error');
						}
					},
					error: function() {
						FPSeoUI.hideLoading($btn);
						FPSeoUI.showNotification('An error occurred. Please try again.', 'error');
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
		$cache_key = 'fp_seo_social_meta_' . $post_id;
		
		return Cache::remember( $cache_key, function() use ( $post_id ) {
			$meta = get_post_meta( $post_id, '_fp_seo_social_meta', true );
			return is_array( $meta ) ? $meta : array();
		}, HOUR_IN_SECONDS );
	}

	/**
	 * Get preview data for post.
	 *
	 * @param WP_Post $post Post object.
	 * @return array<string, mixed>
	 */
	private function get_preview_data( $post ): array {
		return array(
			'title' => get_the_title( $post->ID ),
			'description' => get_the_excerpt( $post->ID ) ?: wp_trim_words( $post->post_content, 20 ),
			'url' => get_permalink( $post->ID ),
			'image' => get_the_post_thumbnail_url( $post->ID, 'full' ) ?: get_option( 'fp_seo_social_default_image' ),
		);
	}

	/**
	 * Save social media meta data.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_social_meta( int $post_id ): void {
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
			$social_meta[ $platform_id . '_title' ] = sanitize_text_field( $_POST[ 'fp_seo_' . $platform_id . '_title' ] ?? '' );
			$social_meta[ $platform_id . '_description' ] = sanitize_textarea_field( $_POST[ 'fp_seo_' . $platform_id . '_description' ] ?? '' );
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

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		$social_meta = $this->get_social_meta( $post_id );
		if ( empty( $social_meta ) ) {
			return;
		}

		echo "\n<!-- FP SEO Performance Social Media Tags -->\n";
		
		// Open Graph tags
		$this->output_open_graph_tags( $social_meta );
		
		// Twitter Card tags
		$this->output_twitter_card_tags( $social_meta );
		
		// LinkedIn tags
		$this->output_linkedin_tags( $social_meta );
		
		// Pinterest tags
		$this->output_pinterest_tags( $social_meta );
		
		echo "<!-- End FP SEO Performance Social Media Tags -->\n";
	}

	/**
	 * Output Open Graph meta tags.
	 *
	 * @param array<string, mixed> $meta Social meta data.
	 */
	private function output_open_graph_tags( array $meta ): void {
		$og_tags = array(
			'og:title' => $meta['facebook_title'] ?? get_the_title(),
			'og:description' => $meta['facebook_description'] ?? get_the_excerpt(),
			'og:type' => 'article',
			'og:url' => get_permalink(),
			'og:site_name' => get_bloginfo( 'name' ),
			'og:locale' => get_locale(),
		);

		// Add image
		$og_image = $this->get_social_image( $meta, 'facebook' );
		if ( $og_image ) {
			$og_tags['og:image'] = $og_image;
			$og_tags['og:image:width'] = 1200;
			$og_tags['og:image:height'] = 630;
			$og_tags['og:image:alt'] = $meta['facebook_title'] ?? get_the_title();
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
	private function output_twitter_card_tags( array $meta ): void {
		$twitter_tags = array(
			'twitter:card' => $meta['twitter_card_type'] ?? 'summary_large_image',
			'twitter:title' => $meta['twitter_title'] ?? get_the_title(),
			'twitter:description' => $meta['twitter_description'] ?? get_the_excerpt(),
			'twitter:url' => get_permalink(),
		);

		// Add image
		$twitter_image = $this->get_social_image( $meta, 'twitter' );
		if ( $twitter_image ) {
			$twitter_tags['twitter:image'] = $twitter_image;
			$twitter_tags['twitter:image:alt'] = $meta['twitter_title'] ?? get_the_title();
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
	private function output_linkedin_tags( array $meta ): void {
		$linkedin_tags = array(
			'linkedin:title' => $meta['linkedin_title'] ?? get_the_title(),
			'linkedin:description' => $meta['linkedin_description'] ?? get_the_excerpt(),
			'linkedin:url' => get_permalink(),
		);

		$linkedin_image = $this->get_social_image( $meta, 'linkedin' );
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
	private function output_pinterest_tags( array $meta ): void {
		$pinterest_tags = array(
			'pinterest:title' => $meta['pinterest_title'] ?? get_the_title(),
			'pinterest:description' => $meta['pinterest_description'] ?? get_the_excerpt(),
			'pinterest:url' => get_permalink(),
		);

		$pinterest_image = $this->get_social_image( $meta, 'pinterest' );
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
	private function get_social_image( array $meta, string $platform = 'facebook' ): ?string {
		// Check for platform-specific image
		$platform_image = $meta[ $platform . '_image' ] ?? null;
		if ( ! empty( $platform_image ) ) {
			return $platform_image;
		}

		// Check for featured image
		$featured_image = get_the_post_thumbnail_url( get_the_ID(), 'full' );
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
		
		return $total_posts > 0 ? (int) round( ( $optimized_posts / $total_posts ) * 100 ) : 0;
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
	 * Optimize social media content with AI.
	 *
	 * @param WP_Post $post Post object.
	 * @param string $platform Social platform.
	 * @return array<string, mixed>
	 */
	private function optimize_social_with_ai( $post, string $platform ): array {
		$title = get_the_title( $post->ID );
		$content = wp_strip_all_tags( $post->post_content );
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
		$content = str_replace( array( '&nbsp;', '&amp;' ), array( ' ', '&' ), $content );
		
		return $content;
	}
}
