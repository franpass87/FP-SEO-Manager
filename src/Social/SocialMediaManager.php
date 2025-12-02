<?php
/**
 * Social Media Manager
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
 * Handles social media optimization and preview.
 */
class SocialMediaManager {

	/**
	 * Supported social platforms.
	 */
	private const PLATFORMS = array(
		'facebook' => 'Facebook',
		'twitter' => 'Twitter',
		'linkedin' => 'LinkedIn',
		'pinterest' => 'Pinterest',
		'instagram' => 'Instagram',
	);

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'wp_head', array( $this, 'output_meta_tags' ), 1 );
		add_action( 'admin_menu', array( $this, 'add_social_menu' ) );
		add_action( 'wp_ajax_fp_seo_preview_social', array( $this, 'ajax_preview_social' ) );
		add_action( 'wp_ajax_fp_seo_optimize_social', array( $this, 'ajax_optimize_social' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_social_metabox' ) );
		
		// CRITICAL: Register hooks ONLY for supported post types to prevent ANY interference
		$supported_types = \FP\SEO\Utils\PostTypes::analyzable();
		foreach ( $supported_types as $post_type ) {
			if ( ! has_action( 'save_post_' . $post_type, array( $this, 'save_social_meta' ) ) ) {
				add_action( 'save_post_' . $post_type, array( $this, 'save_social_meta' ), 10, 1 );
			}
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
	 * DISABLED - Using ImprovedSocialMediaManager instead
	 */
	public function add_social_metabox(): void {
		// Metabox disabled - using ImprovedSocialMediaManager
		// This method is kept for backward compatibility
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
			'og:title' => $meta['og_title'] ?? get_the_title(),
			'og:description' => $meta['og_description'] ?? get_the_excerpt(),
			'og:type' => $meta['og_type'] ?? 'article',
			'og:url' => get_permalink(),
			'og:site_name' => get_bloginfo( 'name' ),
			'og:locale' => get_locale(),
		);

		// Add image
		$og_image = $this->get_social_image( $meta );
		if ( $og_image ) {
			$og_tags['og:image'] = $og_image;
			$og_tags['og:image:width'] = 1200;
			$og_tags['og:image:height'] = 630;
			$og_tags['og:image:alt'] = $meta['og_image_alt'] ?? get_the_title();
		}

		// Add article specific tags
		if ( is_singular() ) {
			$og_tags['article:published_time'] = get_the_date( 'c' );
			$og_tags['article:modified_time'] = get_the_modified_date( 'c' );
			$og_tags['article:author'] = get_the_author_meta( 'display_name' );
			
			// Add categories
			$categories = get_the_category();
			if ( ! empty( $categories ) ) {
				foreach ( $categories as $category ) {
					$og_tags['article:section'] = $category->name;
					break; // Only first category
				}
			}

			// Add tags
			$tags = get_the_tags();
			if ( ! empty( $tags ) ) {
				foreach ( $tags as $tag ) {
					$og_tags['article:tag'] = $tag->name;
				}
			}
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
			$twitter_tags['twitter:image:alt'] = $meta['twitter_image_alt'] ?? get_the_title();
		}

		// Add creator/author
		$twitter_creator = $meta['twitter_creator'] ?? get_option( 'fp_seo_social_twitter_creator' );
		if ( $twitter_creator ) {
			$twitter_tags['twitter:creator'] = $twitter_creator;
		}

		// Add site
		$twitter_site = $meta['twitter_site'] ?? get_option( 'fp_seo_social_twitter_site' );
		if ( $twitter_site ) {
			$twitter_tags['twitter:site'] = $twitter_site;
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

		// Check for general social image
		$social_image = $meta['social_image'] ?? null;
		if ( ! empty( $social_image ) ) {
			return $social_image;
		}

		// Featured image check removed - no longer using featured images

		// Check for default social image
		$default_image = get_option( 'fp_seo_social_default_image' );
		if ( $default_image ) {
			return $default_image;
		}

		return null;
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
	 * Render social media metabox.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_social_metabox( $post ): void {
		$social_meta = $this->get_social_meta( $post->ID );
		$preview_data = $this->get_preview_data( $post );
		
		wp_nonce_field( 'fp_seo_social_meta', 'fp_seo_social_nonce' );
		?>
		<div class="fp-seo-social-metabox">
			<div class="fp-seo-social-tabs">
				<button type="button" class="fp-seo-social-tab active" data-tab="facebook">Facebook</button>
				<button type="button" class="fp-seo-social-tab" data-tab="twitter">Twitter</button>
				<button type="button" class="fp-seo-social-tab" data-tab="linkedin">LinkedIn</button>
			</div>

			<div class="fp-seo-social-tab-content active" id="facebook">
				<div class="fp-seo-social-preview">
					<div class="fp-seo-social-preview-card">
						<div class="fp-seo-social-preview-image">
							<img id="fp-seo-og-image-preview" src="<?php echo esc_url( $preview_data['image'] ); ?>" alt="Preview">
						</div>
						<div class="fp-seo-social-preview-content">
							<div class="fp-seo-social-preview-title" id="fp-seo-og-title-preview"><?php echo esc_html( $preview_data['title'] ); ?></div>
							<div class="fp-seo-social-preview-description" id="fp-seo-og-description-preview"><?php echo esc_html( $preview_data['description'] ); ?></div>
							<div class="fp-seo-social-preview-url"><?php echo esc_url( $preview_data['url'] ); ?></div>
						</div>
					</div>
				</div>

				<div class="fp-seo-social-fields">
					<div class="fp-seo-form-group">
						<label for="fp-seo-og-title"><?php esc_html_e( 'Facebook Title', 'fp-seo-performance' ); ?></label>
						<input type="text" id="fp-seo-og-title" name="fp_seo_og_title" value="<?php echo esc_attr( $social_meta['og_title'] ?? '' ); ?>" maxlength="60">
						<div class="fp-seo-character-count">
							<span id="fp-seo-og-title-count">0</span>/60
						</div>
					</div>

					<div class="fp-seo-form-group">
						<label for="fp-seo-og-description"><?php esc_html_e( 'Facebook Description', 'fp-seo-performance' ); ?></label>
						<textarea id="fp-seo-og-description" name="fp_seo_og_description" maxlength="160"><?php echo esc_textarea( $social_meta['og_description'] ?? '' ); ?></textarea>
						<div class="fp-seo-character-count">
							<span id="fp-seo-og-description-count">0</span>/160
						</div>
					</div>

					<div class="fp-seo-form-group">
						<label for="fp-seo-og-image"><?php esc_html_e( 'Facebook Image', 'fp-seo-performance' ); ?></label>
						<input type="url" id="fp-seo-og-image" name="fp_seo_og_image" value="<?php echo esc_attr( $social_meta['og_image'] ?? '' ); ?>" placeholder="https://example.com/image.jpg">
						<button type="button" class="button" id="fp-seo-og-image-select"><?php esc_html_e( 'Select Image', 'fp-seo-performance' ); ?></button>
					</div>
				</div>
			</div>

			<div class="fp-seo-social-tab-content" id="twitter">
				<div class="fp-seo-social-preview">
					<div class="fp-seo-social-preview-card twitter">
						<div class="fp-seo-social-preview-image">
							<img id="fp-seo-twitter-image-preview" src="<?php echo esc_url( $preview_data['image'] ); ?>" alt="Preview">
						</div>
						<div class="fp-seo-social-preview-content">
							<div class="fp-seo-social-preview-title" id="fp-seo-twitter-title-preview"><?php echo esc_html( $preview_data['title'] ); ?></div>
							<div class="fp-seo-social-preview-description" id="fp-seo-twitter-description-preview"><?php echo esc_html( $preview_data['description'] ); ?></div>
							<div class="fp-seo-social-preview-url"><?php echo esc_url( $preview_data['url'] ); ?></div>
						</div>
					</div>
				</div>

				<div class="fp-seo-social-fields">
					<div class="fp-seo-form-group">
						<label for="fp-seo-twitter-title"><?php esc_html_e( 'Twitter Title', 'fp-seo-performance' ); ?></label>
						<input type="text" id="fp-seo-twitter-title" name="fp_seo_twitter_title" value="<?php echo esc_attr( $social_meta['twitter_title'] ?? '' ); ?>" maxlength="70">
						<div class="fp-seo-character-count">
							<span id="fp-seo-twitter-title-count">0</span>/70
						</div>
					</div>

					<div class="fp-seo-form-group">
						<label for="fp-seo-twitter-description"><?php esc_html_e( 'Twitter Description', 'fp-seo-performance' ); ?></label>
						<textarea id="fp-seo-twitter-description" name="fp_seo_twitter_description" maxlength="200"><?php echo esc_textarea( $social_meta['twitter_description'] ?? '' ); ?></textarea>
						<div class="fp-seo-character-count">
							<span id="fp-seo-twitter-description-count">0</span>/200
						</div>
					</div>

					<div class="fp-seo-form-group">
						<label for="fp-seo-twitter-card-type"><?php esc_html_e( 'Twitter Card Type', 'fp-seo-performance' ); ?></label>
						<select id="fp-seo-twitter-card-type" name="fp_seo_twitter_card_type">
							<option value="summary" <?php selected( $social_meta['twitter_card_type'] ?? '', 'summary' ); ?>><?php esc_html_e( 'Summary', 'fp-seo-performance' ); ?></option>
							<option value="summary_large_image" <?php selected( $social_meta['twitter_card_type'] ?? '', 'summary_large_image' ); ?>><?php esc_html_e( 'Summary Large Image', 'fp-seo-performance' ); ?></option>
						</select>
					</div>
				</div>
			</div>

			<div class="fp-seo-social-tab-content" id="linkedin">
				<div class="fp-seo-social-preview">
					<div class="fp-seo-social-preview-card linkedin">
						<div class="fp-seo-social-preview-image">
							<img id="fp-seo-linkedin-image-preview" src="<?php echo esc_url( $preview_data['image'] ); ?>" alt="Preview">
						</div>
						<div class="fp-seo-social-preview-content">
							<div class="fp-seo-social-preview-title" id="fp-seo-linkedin-title-preview"><?php echo esc_html( $preview_data['title'] ); ?></div>
							<div class="fp-seo-social-preview-description" id="fp-seo-linkedin-description-preview"><?php echo esc_html( $preview_data['description'] ); ?></div>
							<div class="fp-seo-social-preview-url"><?php echo esc_url( $preview_data['url'] ); ?></div>
						</div>
					</div>
				</div>

				<div class="fp-seo-social-fields">
					<div class="fp-seo-form-group">
						<label for="fp-seo-linkedin-title"><?php esc_html_e( 'LinkedIn Title', 'fp-seo-performance' ); ?></label>
						<input type="text" id="fp-seo-linkedin-title" name="fp_seo_linkedin_title" value="<?php echo esc_attr( $social_meta['linkedin_title'] ?? '' ); ?>" maxlength="60">
						<div class="fp-seo-character-count">
							<span id="fp-seo-linkedin-title-count">0</span>/60
						</div>
					</div>

					<div class="fp-seo-form-group">
						<label for="fp-seo-linkedin-description"><?php esc_html_e( 'LinkedIn Description', 'fp-seo-performance' ); ?></label>
						<textarea id="fp-seo-linkedin-description" name="fp_seo_linkedin_description" maxlength="160"><?php echo esc_textarea( $social_meta['linkedin_description'] ?? '' ); ?></textarea>
						<div class="fp-seo-character-count">
							<span id="fp-seo-linkedin-description-count">0</span>/160
						</div>
					</div>
				</div>
			</div>

			<div class="fp-seo-social-actions">
				<button type="button" class="button" id="fp-seo-optimize-social"><?php esc_html_e( 'Optimize with AI', 'fp-seo-performance' ); ?></button>
				<button type="button" class="button" id="fp-seo-preview-social"><?php esc_html_e( 'Live Preview', 'fp-seo-performance' ); ?></button>
			</div>
		</div>

		<style>
		.fp-seo-social-metabox {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
		}
		
		.fp-seo-social-tabs {
			display: flex;
			border-bottom: 1px solid #ddd;
			margin-bottom: 15px;
		}
		
		.fp-seo-social-tab {
			padding: 8px 16px;
			background: #f8f9fa;
			border: 1px solid #ddd;
			border-bottom: none;
			cursor: pointer;
			margin-right: 2px;
			border-radius: 4px 4px 0 0;
		}
		
		.fp-seo-social-tab.active {
			background: #fff;
			border-bottom: 1px solid #fff;
		}
		
		.fp-seo-social-tab-content {
			display: none;
		}
		
		.fp-seo-social-tab-content.active {
			display: block;
		}
		
		.fp-seo-social-preview {
			margin-bottom: 15px;
		}
		
		.fp-seo-social-preview-card {
			border: 1px solid #ddd;
			border-radius: 8px;
			overflow: hidden;
			background: #fff;
			box-shadow: 0 2px 4px rgba(0,0,0,0.1);
		}
		
		.fp-seo-social-preview-card.twitter {
			max-width: 400px;
		}
		
		.fp-seo-social-preview-card.linkedin {
			max-width: 500px;
		}
		
		.fp-seo-social-preview-image {
			width: 100%;
			height: 200px;
			overflow: hidden;
		}
		
		.fp-seo-social-preview-image img {
			width: 100%;
			height: 100%;
			object-fit: cover;
		}
		
		.fp-seo-social-preview-content {
			padding: 12px;
		}
		
		.fp-seo-social-preview-title {
			font-weight: 600;
			font-size: 14px;
			color: #1d2129;
			margin-bottom: 4px;
			line-height: 1.3;
		}
		
		.fp-seo-social-preview-description {
			font-size: 12px;
			color: #606770;
			margin-bottom: 4px;
			line-height: 1.4;
		}
		
		.fp-seo-social-preview-url {
			font-size: 11px;
			color: #8a8d91;
			text-transform: uppercase;
		}
		
		.fp-seo-form-group {
			margin-bottom: 12px;
		}
		
		.fp-seo-form-group label {
			display: block;
			margin-bottom: 4px;
			font-weight: 600;
			font-size: 12px;
		}
		
		.fp-seo-form-group input,
		.fp-seo-form-group textarea,
		.fp-seo-form-group select {
			width: 100%;
			padding: 6px 8px;
			border: 1px solid #ddd;
			border-radius: 4px;
			font-size: 12px;
		}
		
		.fp-seo-character-count {
			text-align: right;
			font-size: 11px;
			color: #666;
			margin-top: 2px;
		}
		
		.fp-seo-social-actions {
			margin-top: 15px;
			display: flex;
			gap: 8px;
		}
		
		.fp-seo-social-actions .button {
			flex: 1;
			text-align: center;
		}
		</style>

		<script>
		jQuery(document).ready(function($) {
			// Tab switching
			$('.fp-seo-social-tab').on('click', function() {
				var tab = $(this).data('tab');
				$('.fp-seo-social-tab').removeClass('active');
				$('.fp-seo-social-tab-content').removeClass('active');
				$(this).addClass('active');
				$('#' + tab).addClass('active');
			});

			// Character counting
			function updateCharacterCount(input, counter) {
				var count = $(input).val().length;
				$(counter).text(count);
				
				if (count > parseInt($(input).attr('maxlength')) * 0.9) {
					$(counter).css('color', '#d63384');
				} else {
					$(counter).css('color', '#666');
				}
			}

			// Facebook
			$('#fp-seo-og-title').on('input', function() {
				updateCharacterCount(this, '#fp-seo-og-title-count');
				$('#fp-seo-og-title-preview').text($(this).val() || '<?php echo esc_js( get_the_title() ); ?>');
			});

			$('#fp-seo-og-description').on('input', function() {
				updateCharacterCount(this, '#fp-seo-og-description-count');
				$('#fp-seo-og-description-preview').text($(this).val() || '<?php echo esc_js( get_the_excerpt() ); ?>');
			});

			$('#fp-seo-og-image').on('input', function() {
				$('#fp-seo-og-image-preview').attr('src', $(this).val() || '');
			});

			// Twitter
			$('#fp-seo-twitter-title').on('input', function() {
				updateCharacterCount(this, '#fp-seo-twitter-title-count');
				$('#fp-seo-twitter-title-preview').text($(this).val() || '<?php echo esc_js( get_the_title() ); ?>');
			});

			$('#fp-seo-twitter-description').on('input', function() {
				updateCharacterCount(this, '#fp-seo-twitter-description-count');
				$('#fp-seo-twitter-description-preview').text($(this).val() || '<?php echo esc_js( get_the_excerpt() ); ?>');
			});

			$('#fp-seo-twitter-image').on('input', function() {
				$('#fp-seo-twitter-image-preview').attr('src', $(this).val() || '');
			});

			// LinkedIn
			$('#fp-seo-linkedin-title').on('input', function() {
				updateCharacterCount(this, '#fp-seo-linkedin-title-count');
				$('#fp-seo-linkedin-title-preview').text($(this).val() || '<?php echo esc_js( get_the_title() ); ?>');
			});

			$('#fp-seo-linkedin-description').on('input', function() {
				updateCharacterCount(this, '#fp-seo-linkedin-description-count');
				$('#fp-seo-linkedin-description-preview').text($(this).val() || '<?php echo esc_js( get_the_excerpt() ); ?>');
			});

			// Initialize character counts
			$('#fp-seo-og-title, #fp-seo-og-description, #fp-seo-twitter-title, #fp-seo-twitter-description, #fp-seo-linkedin-title, #fp-seo-linkedin-description').each(function() {
				updateCharacterCount(this, '#' + $(this).attr('id') + '-count');
			});

			// AI Optimization
			$('#fp-seo-optimize-social').on('click', function() {
				var postId = <?php echo get_the_ID(); ?>;
				var currentTab = $('.fp-seo-social-tab.active').data('tab');
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'fp_seo_optimize_social',
						post_id: postId,
						platform: currentTab,
						nonce: '<?php echo wp_create_nonce( 'fp_seo_social_nonce' ); ?>'
					},
					success: function(response) {
						if (response.success) {
							// Update fields with AI suggestions
							if (response.data.title) {
								$('#fp-seo-' + currentTab + '-title').val(response.data.title);
								updateCharacterCount('#fp-seo-' + currentTab + '-title', '#fp-seo-' + currentTab + '-title-count');
							}
							if (response.data.description) {
								$('#fp-seo-' + currentTab + '-description').val(response.data.description);
								updateCharacterCount('#fp-seo-' + currentTab + '-description', '#fp-seo-' + currentTab + '-description-count');
							}
						} else {
							alert('Error: ' + response.data);
						}
					}
				});
			});
		});
		</script>
		<?php
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
			'image' => get_option( 'fp_seo_social_default_image' ), // Featured image fallback removed
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

		$social_meta = array(
			'og_title' => sanitize_text_field( $_POST['fp_seo_og_title'] ?? '' ),
			'og_description' => sanitize_textarea_field( $_POST['fp_seo_og_description'] ?? '' ),
			'og_image' => esc_url_raw( $_POST['fp_seo_og_image'] ?? '' ),
			'twitter_title' => sanitize_text_field( $_POST['fp_seo_twitter_title'] ?? '' ),
			'twitter_description' => sanitize_textarea_field( $_POST['fp_seo_twitter_description'] ?? '' ),
			'twitter_card_type' => sanitize_text_field( $_POST['fp_seo_twitter_card_type'] ?? 'summary_large_image' ),
			'linkedin_title' => sanitize_text_field( $_POST['fp_seo_linkedin_title'] ?? '' ),
			'linkedin_description' => sanitize_textarea_field( $_POST['fp_seo_linkedin_description'] ?? '' ),
		);

		update_post_meta( $post_id, '_fp_seo_social_meta', $social_meta );

		// Clear cache
		Cache::delete( 'fp_seo_social_meta_' . $post_id );
	}

	/**
	 * Render Social Media management page.
	 */
	public function render_social_page(): void {
		?>
		<div class="wrap fp-seo-social-wrap">
			<h1><?php esc_html_e( 'Social Media Optimization', 'fp-seo-performance' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Ottimizza come i tuoi contenuti appaiono quando condivisi su Facebook, Twitter, LinkedIn e altri social', 'fp-seo-performance' ); ?></p>

			<!-- Banner introduttivo -->
			<div class="fp-seo-intro-banner">
				<div class="fp-seo-intro-icon">📱</div>
				<div class="fp-seo-intro-content">
					<h2><?php esc_html_e( 'Perché ottimizzare i Social Media?', 'fp-seo-performance' ); ?></h2>
					<p><?php esc_html_e( 'Quando condividi un link sui social, appaiono titolo, descrizione e immagine. Ottimizzarli aumenta i click del 40-60%! Il plugin gestisce:', 'fp-seo-performance' ); ?></p>
					<ul class="fp-seo-intro-list">
						<li><strong>Open Graph (Facebook, LinkedIn):</strong> Titolo, descrizione, immagine 1200x630px</li>
						<li><strong>Twitter Cards:</strong> Formato ottimizzato per Twitter</li>
						<li><strong>Pinterest Rich Pins:</strong> Dati strutturati per Pinterest</li>
						<li><strong>Preview in tempo reale:</strong> Vedi come appare prima di pubblicare</li>
					</ul>
				</div>
			</div>
			
			<div class="fp-seo-social-dashboard">
				<div class="fp-seo-social-stats">
					<div class="fp-seo-stat-card">
						<div class="fp-seo-stat-icon">📝</div>
						<div class="fp-seo-stat-content">
							<h3>
								<?php esc_html_e( 'Posts with Social Meta', 'fp-seo-performance' ); ?>
								<span class="fp-seo-tooltip-trigger" title="<?php esc_attr_e( 'Numero di post con meta tag social configurati (Open Graph, Twitter Cards). Più post ottimizzati = più condivisioni.', 'fp-seo-performance' ); ?>">ℹ️</span>
							</h3>
							<span class="fp-seo-stat-number"><?php echo esc_html( $this->get_posts_with_social_meta_count() ); ?></span>
							<p class="fp-seo-stat-desc"><?php esc_html_e( 'Contenuti ottimizzati', 'fp-seo-performance' ); ?></p>
						</div>
					</div>
					<div class="fp-seo-stat-card">
						<div class="fp-seo-stat-icon">🌐</div>
						<div class="fp-seo-stat-content">
							<h3>
								<?php esc_html_e( 'Platforms Supported', 'fp-seo-performance' ); ?>
								<span class="fp-seo-tooltip-trigger" title="<?php esc_attr_e( 'Numero di piattaforme social supportate dal plugin: Facebook, Twitter, LinkedIn, Pinterest, Instagram', 'fp-seo-performance' ); ?>">ℹ️</span>
							</h3>
							<span class="fp-seo-stat-number"><?php echo esc_html( count( self::PLATFORMS ) ); ?></span>
							<p class="fp-seo-stat-desc"><?php esc_html_e( 'Piattaforme integrate', 'fp-seo-performance' ); ?></p>
						</div>
					</div>
				</div>

				<div class="fp-seo-social-settings">
					<div class="fp-seo-settings-header">
						<h2><?php esc_html_e( 'Global Social Media Settings', 'fp-seo-performance' ); ?></h2>
						<p class="fp-seo-settings-desc"><?php esc_html_e( 'Configura le impostazioni predefinite per tutti i tuoi contenuti', 'fp-seo-performance' ); ?></p>
					</div>
					<form method="post" action="options.php">
						<?php
						settings_fields( 'fp_seo_social_settings' );
						do_settings_sections( 'fp_seo_social_settings' );
						?>
						
						<table class="form-table">
							<tr>
								<th scope="row">
									<?php esc_html_e( 'Default Social Image', 'fp-seo-performance' ); ?>
									<span class="fp-seo-tooltip-trigger" title="<?php esc_attr_e( 'Immagine predefinita quando un post non ha featured image. Dimensione consigliata: 1200x630px (formato Open Graph standard)', 'fp-seo-performance' ); ?>">ℹ️</span>
								</th>
								<td>
									<input type="url" name="fp_seo_social_default_image" value="<?php echo esc_attr( get_option( 'fp_seo_social_default_image' ) ); ?>" class="regular-text">
									<p class="description">
										<?php esc_html_e( 'Immagine di fallback per condivisioni social. ', 'fp-seo-performance' ); ?>
										<strong><?php esc_html_e( 'Dimensione ottimale: 1200x630px', 'fp-seo-performance' ); ?></strong>
									</p>
									<p class="fp-seo-example-text">💡 <strong>Tip:</strong> Usa il logo del brand su sfondo colorato o un'immagine generica del sito</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<?php esc_html_e( 'Twitter Site', 'fp-seo-performance' ); ?>
									<span class="fp-seo-tooltip-trigger" title="<?php esc_attr_e( 'Username Twitter del tuo sito/brand. Appare nelle Twitter Card come @menzione. Include @ nel campo.', 'fp-seo-performance' ); ?>">ℹ️</span>
								</th>
								<td>
									<input type="text" name="fp_seo_social_twitter_site" value="<?php echo esc_attr( get_option( 'fp_seo_social_twitter_site' ) ); ?>" placeholder="@tuosito">
									<p class="description">
										<?php esc_html_e( 'Username Twitter del tuo sito (es: @tuosito). Necessario per Twitter Cards.', 'fp-seo-performance' ); ?>
									</p>
									<p class="fp-seo-example-text">📋 <strong>Esempio:</strong> @francescopasseri</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<?php esc_html_e( 'Twitter Creator', 'fp-seo-performance' ); ?>
									<span class="fp-seo-tooltip-trigger" title="<?php esc_attr_e( 'Username Twitter predefinito per gli autori dei contenuti. Appare come credito autore nelle condivisioni.', 'fp-seo-performance' ); ?>">ℹ️</span>
								</th>
								<td>
									<input type="text" name="fp_seo_social_twitter_creator" value="<?php echo esc_attr( get_option( 'fp_seo_social_twitter_creator' ) ); ?>" placeholder="@autore">
									<p class="description">
										<?php esc_html_e( 'Username Twitter predefinito per i content creator. Può essere sovrascritto per singolo autore.', 'fp-seo-performance' ); ?>
									</p>
									<p class="fp-seo-example-text">📋 <strong>Esempio:</strong> @redazionesito</p>
								</td>
							</tr>
						</table>
						
						<?php submit_button(); ?>
					</form>
				</div>
			</div>
		</div>

		<style>
		/* Common Styles */
		.fp-seo-social-wrap {
			max-width: 1400px;
			margin: 0 auto;
		}

		.fp-seo-social-wrap > .description {
			font-size: 16px;
			color: #666;
			margin-bottom: 24px;
		}

		.fp-seo-intro-banner {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: white;
			padding: 30px;
			border-radius: 12px;
			margin: 20px 0 30px;
			display: flex;
			gap: 24px;
			box-shadow: 0 8px 16px rgba(102, 126, 234, 0.2);
		}

		.fp-seo-intro-icon {
			font-size: 48px;
			line-height: 1;
		}

		.fp-seo-intro-content h2 {
			color: white;
			margin: 0 0 16px;
			font-size: 24px;
		}

		.fp-seo-intro-content p {
			margin: 0 0 16px;
			font-size: 15px;
			opacity: 0.95;
		}

		.fp-seo-intro-list {
			margin: 0;
			padding-left: 0;
			list-style: none;
		}

		.fp-seo-intro-list li {
			padding: 6px 0;
			font-size: 14px;
			opacity: 0.9;
		}

		.fp-seo-social-stats {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
			gap: 20px;
			margin-bottom: 32px;
		}

		.fp-seo-stat-card {
			background: white;
			padding: 24px;
			border-radius: 12px;
			box-shadow: 0 4px 6px rgba(0,0,0,0.05);
			border: 2px solid #e5e7eb;
			transition: all 0.3s ease;
			text-align: center;
		}

		.fp-seo-stat-card:hover {
			transform: translateY(-4px);
			box-shadow: 0 8px 12px rgba(0,0,0,0.1);
		}

		.fp-seo-stat-icon {
			font-size: 32px;
			margin-bottom: 12px;
		}

		.fp-seo-stat-card h3 {
			margin: 0 0 12px;
			font-size: 14px;
			color: #6b7280;
			font-weight: 600;
			text-transform: uppercase;
			letter-spacing: 0.5px;
		}

		.fp-seo-stat-number {
			display: block;
			font-size: 42px;
			font-weight: 700;
			color: #2563eb;
			line-height: 1;
			margin-bottom: 8px;
		}

		.fp-seo-stat-desc {
			margin: 0;
			font-size: 13px;
			color: #6b7280;
		}

		.fp-seo-tooltip-trigger {
			display: inline-block;
			margin-left: 4px;
			cursor: help;
			opacity: 0.7;
			font-size: 12px;
			transition: opacity 0.2s;
		}

		.fp-seo-tooltip-trigger:hover {
			opacity: 1;
		}

		.fp-seo-social-settings {
			background: white;
			padding: 32px;
			border-radius: 12px;
			border: 2px solid #e5e7eb;
			box-shadow: 0 4px 6px rgba(0,0,0,0.05);
		}

		.fp-seo-settings-header {
			margin-bottom: 24px;
			padding-bottom: 16px;
			border-bottom: 2px solid #e5e7eb;
		}

		.fp-seo-settings-header h2 {
			margin: 0 0 8px;
			font-size: 20px;
			color: #1f2937;
		}

		.fp-seo-settings-desc {
			margin: 0;
			color: #6b7280;
			font-size: 14px;
		}

		.fp-seo-example-text {
			margin: 8px 0 0;
			padding: 8px 12px;
			background: #fef3c7;
			border-left: 3px solid #f59e0b;
			border-radius: 4px;
			font-size: 13px;
			color: #78350f;
		}
		</style>
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
		$platform = sanitize_text_field( $_POST['platform'] ?? 'facebook' );

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
		// This would integrate with the AI Content Optimizer
		// For now, return basic optimization
		$title = get_the_title( $post->ID );
		$content = wp_strip_all_tags( $post->post_content );
		$excerpt = get_the_excerpt( $post->ID );

		$optimized = array();

		switch ( $platform ) {
			case 'facebook':
				$optimized['title'] = $this->optimize_for_facebook( $title );
				$optimized['description'] = $this->optimize_for_facebook( $excerpt ?: wp_trim_words( $content, 20 ) );
				break;
			case 'twitter':
				$optimized['title'] = $this->optimize_for_twitter( $title );
				$optimized['description'] = $this->optimize_for_twitter( $excerpt ?: wp_trim_words( $content, 15 ) );
				break;
			case 'linkedin':
				$optimized['title'] = $this->optimize_for_linkedin( $title );
				$optimized['description'] = $this->optimize_for_linkedin( $excerpt ?: wp_trim_words( $content, 20 ) );
				break;
		}

		return $optimized;
	}

	/**
	 * Optimize content for Facebook.
	 *
	 * @param string $content Content to optimize.
	 * @return string
	 */
	private function optimize_for_facebook( string $content ): string {
		// Basic optimization - in real implementation, this would use AI
		$content = wp_trim_words( $content, 20 );
		$content = str_replace( array( '&nbsp;', '&amp;' ), array( ' ', '&' ), $content );
		return $content;
	}

	/**
	 * Optimize content for Twitter.
	 *
	 * @param string $content Content to optimize.
	 * @return string
	 */
	private function optimize_for_twitter( string $content ): string {
		// Basic optimization - in real implementation, this would use AI
		$content = wp_trim_words( $content, 15 );
		$content = str_replace( array( '&nbsp;', '&amp;' ), array( ' ', '&' ), $content );
		return $content;
	}

	/**
	 * Optimize content for LinkedIn.
	 *
	 * @param string $content Content to optimize.
	 * @return string
	 */
	private function optimize_for_linkedin( string $content ): string {
		// Basic optimization - in real implementation, this would use AI
		$content = wp_trim_words( $content, 20 );
		$content = str_replace( array( '&nbsp;', '&amp;' ), array( ' ', '&' ), $content );
		return $content;
	}
}
