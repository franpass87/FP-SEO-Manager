<?php
/**
 * Base class for rendering form fields in the SEO metabox.
 *
 * @package FP\SEO\Editor\Renderers
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Renderers;

use WP_Post;
use function esc_attr;
use function esc_attr_e;
use function esc_html_e;
use function esc_textarea;
use function wp_create_nonce;
use function wp_specialchars_decode;

/**
 * Base class for rendering form fields.
 */
abstract class FieldRenderer {
	/**
	 * Render a text input field with AI button.
	 *
	 * @param string   $field_id Field ID.
	 * @param string   $field_name Field name.
	 * @param string   $value Field value.
	 * @param string   $label Field label.
	 * @param string   $icon Icon emoji.
	 * @param string   $impact Impact badge text (e.g., "+15%").
	 * @param string   $impact_color Impact badge color.
	 * @param int      $maxlength Maximum length.
	 * @param string   $placeholder Placeholder text.
	 * @param string   $counter_id Counter element ID.
	 * @param string   $counter_text Counter text format.
	 * @param string   $help_text Help text below field.
	 * @param string   $help_color Help text color.
	 * @param WP_Post  $post Post object.
	 * @param string   $ai_field_type AI field type for generation.
	 * @param string   $border_color Border color.
	 * @return void
	 */
	protected function render_text_field(
		string $field_id,
		string $field_name,
		string $value,
		string $label,
		string $icon,
		string $impact,
		string $impact_color,
		int $maxlength,
		string $placeholder,
		string $counter_id,
		string $counter_text,
		string $help_text,
		string $help_color,
		WP_Post $post,
		string $ai_field_type = '',
		string $border_color = '#10b981'
	): void {
		?>
		<div style="position: relative;">
			<label for="<?php echo esc_attr( $field_id ); ?>" style="display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: 600; color: #0c4a6e; margin-bottom: 8px;">
				<span style="display: flex; align-items: center; gap: 8px;">
					<span style="font-size: 16px;"><?php echo esc_html( $icon ); ?></span>
					<?php esc_html_e( $label, 'fp-seo-performance' ); ?>
					<span style="display: inline-flex; padding: 2px 8px; background: <?php echo esc_attr( $impact_color ); ?>; color: #fff; border-radius: 999px; font-size: 10px; font-weight: 700;"><?php echo esc_html( $impact ); ?></span>
				</span>
				<span id="<?php echo esc_attr( $counter_id ); ?>" style="font-size: 12px; font-weight: 600; color: #6b7280;"><?php echo esc_html( $counter_text ); ?></span>
			</label>
			<div style="display: flex; gap: 8px; align-items: stretch;">
				<input 
					type="text" 
					id="<?php echo esc_attr( $field_id ); ?>" 
					name="<?php echo esc_attr( $field_name ); ?>"
					value="<?php echo esc_attr( wp_specialchars_decode( $value, ENT_QUOTES ) ); ?>"
					placeholder="<?php esc_attr_e( $placeholder, 'fp-seo-performance' ); ?>"
					maxlength="<?php echo esc_attr( (string) $maxlength ); ?>"
					style="flex: 1; padding: 10px 14px; font-size: 14px; border: 2px solid <?php echo esc_attr( $border_color ); ?>; border-radius: 8px; background: #fff; transition: all 0.2s ease;"
					data-fp-seo-field="<?php echo esc_attr( $field_id ); ?>"
				/>
				<input type="hidden" name="<?php echo esc_attr( $field_name ); ?>_sent" value="1" />
				<?php if ( ! empty( $ai_field_type ) ) : ?>
					<button 
						type="button" 
						class="fp-seo-ai-generate-field-btn" 
						data-field="<?php echo esc_attr( $ai_field_type ); ?>"
						data-target-id="<?php echo esc_attr( $field_id ); ?>"
						data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>"
						data-nonce="<?php echo esc_attr( wp_create_nonce( 'fp_seo_ai_generate' ) ); ?>"
						title="<?php esc_attr_e( 'Genera con AI', 'fp-seo-performance' ); ?>"
					>
						<span>🤖</span>
						<span><?php esc_html_e( 'AI', 'fp-seo-performance' ); ?></span>
					</button>
				<?php endif; ?>
			</div>
			<?php if ( ! empty( $help_text ) ) : ?>
				<p style="margin: 8px 0 0; font-size: 11px; color: #64748b; line-height: 1.5;">
					<strong style="color: <?php echo esc_attr( $help_color ); ?>;"><?php echo esc_html( $help_text ); ?></strong>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render a textarea field with AI button.
	 *
	 * @param string   $field_id Field ID.
	 * @param string   $field_name Field name.
	 * @param string   $value Field value.
	 * @param string   $label Field label.
	 * @param string   $icon Icon emoji.
	 * @param string   $impact Impact badge text.
	 * @param string   $impact_color Impact badge color.
	 * @param int      $maxlength Maximum length.
	 * @param int      $rows Number of rows.
	 * @param string   $placeholder Placeholder text.
	 * @param string   $counter_id Counter element ID.
	 * @param string   $counter_text Counter text format.
	 * @param string   $help_text Help text below field.
	 * @param string   $help_color Help text color.
	 * @param WP_Post  $post Post object.
	 * @param string   $ai_field_type AI field type for generation.
	 * @param string   $border_color Border color.
	 * @return void
	 */
	protected function render_textarea_field(
		string $field_id,
		string $field_name,
		string $value,
		string $label,
		string $icon,
		string $impact,
		string $impact_color,
		int $maxlength,
		int $rows,
		string $placeholder,
		string $counter_id,
		string $counter_text,
		string $help_text,
		string $help_color,
		WP_Post $post,
		string $ai_field_type = '',
		string $border_color = '#10b981'
	): void {
		?>
		<div style="position: relative;">
			<label for="<?php echo esc_attr( $field_id ); ?>" style="display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: 600; color: #0c4a6e; margin-bottom: 8px;">
				<span style="display: flex; align-items: center; gap: 8px;">
					<span style="font-size: 16px;"><?php echo esc_html( $icon ); ?></span>
					<?php esc_html_e( $label, 'fp-seo-performance' ); ?>
					<span style="display: inline-flex; padding: 2px 8px; background: <?php echo esc_attr( $impact_color ); ?>; color: #fff; border-radius: 999px; font-size: 10px; font-weight: 700;"><?php echo esc_html( $impact ); ?></span>
				</span>
				<span id="<?php echo esc_attr( $counter_id ); ?>" style="font-size: 12px; font-weight: 600; color: #6b7280;"><?php echo esc_html( $counter_text ); ?></span>
			</label>
			<div style="display: flex; gap: 8px; align-items: flex-start;">
				<textarea 
					id="<?php echo esc_attr( $field_id ); ?>" 
					name="<?php echo esc_attr( $field_name ); ?>"
					placeholder="<?php esc_attr_e( $placeholder, 'fp-seo-performance' ); ?>"
					maxlength="<?php echo esc_attr( (string) $maxlength ); ?>"
					rows="<?php echo esc_attr( (string) $rows ); ?>"
					style="flex: 1; padding: 10px 14px; font-size: 13px; border: 2px solid <?php echo esc_attr( $border_color ); ?>; border-radius: 8px; background: #fff; resize: vertical; line-height: 1.5; transition: all 0.2s ease;"
					data-fp-seo-field="<?php echo esc_attr( $field_id ); ?>"
					autocomplete="off"
				><?php echo esc_textarea( wp_specialchars_decode( $value, ENT_QUOTES ) ); ?></textarea>
				<input type="hidden" name="<?php echo esc_attr( $field_name ); ?>_sent" value="1" />
				<?php if ( ! empty( $ai_field_type ) ) : ?>
					<button 
						type="button" 
						class="fp-seo-ai-generate-field-btn" 
						data-field="<?php echo esc_attr( $ai_field_type ); ?>"
						data-target-id="<?php echo esc_attr( $field_id ); ?>"
						data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>"
						data-nonce="<?php echo esc_attr( wp_create_nonce( 'fp_seo_ai_generate' ) ); ?>"
						title="<?php esc_attr_e( 'Genera con AI', 'fp-seo-performance' ); ?>"
					>
						<span>🤖</span>
						<span><?php esc_html_e( 'AI', 'fp-seo-performance' ); ?></span>
					</button>
				<?php endif; ?>
			</div>
			<?php if ( ! empty( $help_text ) ) : ?>
				<p style="margin: 8px 0 0; font-size: 11px; color: #64748b; line-height: 1.5;">
					<strong style="color: <?php echo esc_attr( $help_color ); ?>;"><?php echo esc_html( $help_text ); ?></strong>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}
}


