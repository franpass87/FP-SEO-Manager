<?php
/**
 * Renders social media fields in the SEO metabox.
 *
 * @package FP\SEO\Social\Renderers
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Social\Renderers;

use FP\SEO\Social\ImprovedSocialMediaManager;
use WP_Post;
use function esc_attr;
use function esc_attr_e;
use function esc_html;
use function esc_html_e;
use function esc_textarea;
use function esc_url;
use function esc_url_raw;
use function get_option;
use function html_entity_decode;
use function mb_strlen;
use function preg_replace_callback;
use function selected;
use function wp_specialchars_decode;

/**
 * Renders social media fields.
 */
class SocialFieldsRenderer {
	/**
	 * @var ImprovedSocialMediaManager
	 */
	private $manager;

	/**
	 * Constructor.
	 *
	 * @param ImprovedSocialMediaManager $manager Social media manager instance.
	 */
	public function __construct( ImprovedSocialMediaManager $manager ) {
		$this->manager = $manager;
	}

	/**
	 * Render social media metabox content.
	 *
	 * @param WP_Post $post Post object.
	 * @param array   $preview_data Preview data.
	 * @param array   $social_meta Social meta data.
	 * @return void
	 */
	public function render( WP_Post $post, array $preview_data, array $social_meta ): void {
		$platforms = $this->get_platforms();

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
						<?php foreach ( $platforms as $platform_id => $platform_data ) : ?>
							<button type="button" 
									class="fp-seo-tab <?php echo $platform_id === 'facebook' ? 'fp-seo-tab-active' : ''; ?>" 
									data-tab="<?php echo esc_attr( $platform_id ); ?>"
									style="--platform-color: <?php echo esc_attr( $platform_data['color'] ); ?>">
								<span class="fp-seo-tab-icon"><?php echo esc_html( $platform_data['icon'] ); ?></span>
								<span class="fp-seo-tab-label"><?php echo esc_html( $platform_data['name'] ); ?></span>
							</button>
						<?php endforeach; ?>
					</div>

					<!-- Tab Contents -->
					<?php foreach ( $platforms as $platform_id => $platform_data ) : ?>
						<?php $this->render_platform_tab( $platform_id, $platform_data, $post, $preview_data, $social_meta ); ?>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render platform tab content.
	 *
	 * @param string  $platform_id Platform ID.
	 * @param array   $platform_data Platform data.
	 * @param WP_Post $post Post object.
	 * @param array   $preview_data Preview data.
	 * @param array   $social_meta Social meta data.
	 * @return void
	 */
	private function render_platform_tab( string $platform_id, array $platform_data, WP_Post $post, array $preview_data, array $social_meta ): void {
		$is_active = $platform_id === 'facebook';
		$post_id = (int) $post->ID;

		// Get preview image
		$preview_image_url = $this->get_preview_image_url( $post_id, $preview_data, $social_meta, $platform_id );

		?>
		<div class="fp-seo-tab-content <?php echo $is_active ? 'fp-seo-tab-content-active' : ''; ?>" 
			 id="<?php echo esc_attr( $platform_id ); ?>">
			
			<!-- Live Preview -->
			<?php $this->render_live_preview( $platform_id, $platform_data, $preview_data, $preview_image_url ); ?>

			<!-- Form Fields -->
			<?php $this->render_form_fields( $platform_id, $platform_data, $post, $social_meta ); ?>
		</div>
		<?php
	}

	/**
	 * Get preview image URL with fallbacks.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $preview_data Preview data.
	 * @param array $social_meta Social meta data.
	 * @param string $platform_id Platform ID.
	 * @return string Image URL.
	 */
	private function get_preview_image_url( int $post_id, array $preview_data, array $social_meta, string $platform_id ): string {
		// Priority 1: Use preview_data image if available
		if ( ! empty( $preview_data['image'] ) ) {
			return $preview_data['image'];
		}

		// Priority 2: Use platform-specific social meta image
		$platform_image_key = $platform_id . '_image';
		if ( ! empty( $social_meta[ $platform_image_key ] ) ) {
			return esc_url_raw( $social_meta[ $platform_image_key ] );
		}

		// Priority 3: Use generic social image
		if ( ! empty( $social_meta['facebook_image'] ) ) {
			return esc_url_raw( $social_meta['facebook_image'] );
		}

		// Priority 4: Default social image
		return get_option( 'fp_seo_social_default_image', '' );
	}

	/**
	 * Render live preview section.
	 *
	 * @param string $platform_id Platform ID.
	 * @param array  $platform_data Platform data.
	 * @param array  $preview_data Preview data.
	 * @param string $preview_image_url Preview image URL.
	 * @return void
	 */
	private function render_live_preview( string $platform_id, array $platform_data, array $preview_data, string $preview_image_url ): void {
		$img_style = empty( $preview_image_url ) ? 'display: none;' : 'display: block; opacity: 1; visibility: visible;';
		$img_src = ! empty( $preview_image_url ) ? esc_url( $preview_image_url ) : 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'1\' height=\'1\'%3E%3C/svg%3E';

		$title_decoded = $this->decode_html_entities( $preview_data['title'] ?? '' );
		$desc_decoded = $this->decode_html_entities( $preview_data['description'] ?? '' );

		?>
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
						 src="<?php echo $img_src; ?>" 
						 alt="<?php esc_attr_e( 'Social media preview image', 'fp-seo-performance' ); ?>"
						 style="<?php echo esc_attr( $img_style ); ?>"
						 data-empty="<?php echo empty( $preview_image_url ) ? 'true' : 'false'; ?>"
						 onerror="this.style.display='none'; if (this.nextElementSibling) this.nextElementSibling.style.display='flex';"
						 onload="(function(img) { if (img.src && img.src !== '' && !img.src.includes('data:image/svg+xml') && img.naturalWidth > 0) { img.style.display='block'; img.style.opacity='1'; if (img.nextElementSibling) img.nextElementSibling.style.display='none'; } else { img.style.display='none'; if (img.nextElementSibling) img.nextElementSibling.style.display='flex'; } })(this);">
					<div class="fp-seo-social-preview-image-overlay" style="<?php echo empty( $preview_image_url ) ? 'display: flex; opacity: 1;' : 'display: none;'; ?>">
						<button type="button" class="fp-seo-btn fp-seo-btn-sm fp-seo-btn-primary">
							<?php esc_html_e( 'Cambia Immagine', 'fp-seo-performance' ); ?>
						</button>
					</div>
				</div>
				<div class="fp-seo-social-preview-content">
					<div class="fp-seo-social-preview-title" 
						 id="fp-seo-<?php echo esc_attr( $platform_id ); ?>-title-preview">
						<?php echo esc_html( $title_decoded ); ?>
					</div>
					<div class="fp-seo-social-preview-description" 
						 id="fp-seo-<?php echo esc_attr( $platform_id ); ?>-description-preview">
						<?php echo esc_html( $desc_decoded ); ?>
					</div>
					<?php if ( ! empty( $url ) ) : ?>
						<div class="fp-seo-social-preview-url">
							<?php echo esc_url( $url ); ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render form fields for platform.
	 *
	 * @param string  $platform_id Platform ID.
	 * @param array   $platform_data Platform data.
	 * @param WP_Post $post Post object.
	 * @param array   $social_meta Social meta data.
	 * @return void
	 */
	private function render_form_fields( string $platform_id, array $platform_data, WP_Post $post, array $social_meta ): void {
		$post_id = (int) $post->ID;
		$title_key = $platform_id . '_title';
		$description_key = $platform_id . '_description';
		$image_key = $platform_id . '_image';

		$title_value = $social_meta[ $title_key ] ?? '';
		$description_value = $social_meta[ $description_key ] ?? '';
		$image_value = $social_meta[ $image_key ] ?? '';

		?>
		<div class="fp-seo-form-fields">
			<!-- Title Field -->
			<div class="fp-seo-form-group">
				<label for="fp-seo-<?php echo esc_attr( $platform_id ); ?>-title" class="fp-seo-label">
					<?php esc_html_e( 'Title', 'fp-seo-performance' ); ?>
					<span class="fp-seo-char-count" id="fp-seo-<?php echo esc_attr( $platform_id ); ?>-title-count">
						<?php echo esc_html( mb_strlen( $title_value ) ); ?>/<?php echo esc_html( $platform_data['title_limit'] ); ?>
					</span>
				</label>
				<input type="text" 
					   id="fp-seo-<?php echo esc_attr( $platform_id ); ?>-title" 
					   name="fp_seo_<?php echo esc_attr( $platform_id ); ?>_title"
					   value="<?php echo esc_attr( $title_value ); ?>"
					   maxlength="<?php echo esc_attr( (string) $platform_data['title_limit'] ); ?>"
					   class="fp-seo-input"
					   data-platform="<?php echo esc_attr( $platform_id ); ?>"
					   data-field="title">
			</div>

			<!-- Description Field -->
			<div class="fp-seo-form-group">
				<label for="fp-seo-<?php echo esc_attr( $platform_id ); ?>-description" class="fp-seo-label">
					<?php esc_html_e( 'Description', 'fp-seo-performance' ); ?>
					<span class="fp-seo-char-count" id="fp-seo-<?php echo esc_attr( $platform_id ); ?>-description-count">
						<?php echo esc_html( mb_strlen( $description_value ) ); ?>/<?php echo esc_html( $platform_data['description_limit'] ); ?>
					</span>
				</label>
				<textarea id="fp-seo-<?php echo esc_attr( $platform_id ); ?>-description" 
						  name="fp_seo_<?php echo esc_attr( $platform_id ); ?>_description"
						  maxlength="<?php echo esc_attr( (string) $platform_data['description_limit'] ); ?>"
						  rows="3"
						  class="fp-seo-textarea"
						  data-platform="<?php echo esc_attr( $platform_id ); ?>"
						  data-field="description"><?php echo esc_textarea( $description_value ); ?></textarea>
			</div>

			<!-- Image Field -->
			<div class="fp-seo-form-group">
				<label for="fp-seo-<?php echo esc_attr( $platform_id ); ?>-image" class="fp-seo-label">
					<?php esc_html_e( 'Image URL', 'fp-seo-performance' ); ?>
				</label>
				<div class="fp-seo-image-input-group">
					<input type="url" 
						   id="fp-seo-<?php echo esc_attr( $platform_id ); ?>-image" 
						   name="fp_seo_<?php echo esc_attr( $platform_id ); ?>_image"
						   value="<?php echo esc_url( $image_value ); ?>"
						   class="fp-seo-input"
						   placeholder="<?php esc_attr_e( 'https://example.com/image.jpg', 'fp-seo-performance' ); ?>"
						   data-platform="<?php echo esc_attr( $platform_id ); ?>"
						   data-field="image">
					<button type="button" 
							class="fp-seo-btn fp-seo-btn-secondary fp-seo-media-button"
							data-platform="<?php echo esc_attr( $platform_id ); ?>"
							data-post-id="<?php echo esc_attr( (string) $post_id ); ?>">
						<?php esc_html_e( 'Select Image', 'fp-seo-performance' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get supported platforms.
	 *
	 * @return array Platforms array.
	 */
	private function get_platforms(): array {
		return array(
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
	}

	/**
	 * Decode HTML entities properly.
	 *
	 * @param string $text Text to decode.
	 * @return string Decoded text.
	 */
	private function decode_html_entities( string $text ): string {
		// Decode numeric entities (&#038; -> &, &#8211; -> –) - handle UTF-8 properly
		$decoded = preg_replace_callback( '/&#(\d+);/', function( $matches ) {
			$code = (int) $matches[1];
			if ( function_exists( 'mb_chr' ) ) {
				return mb_chr( $code, 'UTF-8' );
			}
			return html_entity_decode( '&#' . $code . ';', ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}, $text );

		// Decode hex entities (&#x26; -> &)
		$decoded = preg_replace_callback( '/&#x([0-9A-Fa-f]+);/i', function( $matches ) {
			$code = hexdec( $matches[1] );
			if ( function_exists( 'mb_chr' ) ) {
				return mb_chr( $code, 'UTF-8' );
			}
			return html_entity_decode( '&#x' . $matches[1] . ';', ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}, $decoded );

		// Decode named entities using html_entity_decode
		$decoded = html_entity_decode( $decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Final decode with wp_specialchars_decode for WordPress-specific entities
		return wp_specialchars_decode( $decoded, ENT_QUOTES );
	}
}

