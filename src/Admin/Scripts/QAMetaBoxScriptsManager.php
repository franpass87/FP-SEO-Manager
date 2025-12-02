<?php
/**
 * Manages scripts for the Q&A MetaBox.
 *
 * @package FP\SEO\Admin\Scripts
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Scripts;

use WP_Post;
use function esc_html_e;
use function esc_js;
use function esc_url;
use function get_current_screen;
use function home_url;
use function wp_create_nonce;

/**
 * Manages scripts for the Q&A MetaBox.
 */
class QAMetaBoxScriptsManager {
	/**
	 * @var WP_Post|null
	 */
	private $post;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_footer', array( $this, 'inject_scripts' ) );
	}

	/**
	 * Set post context.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function set_post( WP_Post $post ): void {
		$this->post = $post;
	}

	/**
	 * Inject scripts in admin footer.
	 *
	 * @return void
	 */
	public function inject_scripts(): void {
		$screen = get_current_screen();
		
		if ( ! $screen || 'post' !== $screen->base ) {
			return;
		}
		
		global $post;
		if ( ! $post ) {
			return;
		}
		
		$this->post = $post;
		$this->render_scripts();
	}

	/**
	 * Render all scripts.
	 *
	 * @return void
	 */
	private function render_scripts(): void {
		if ( ! $this->post ) {
			return;
		}
		?>
		<script>
		jQuery(document).ready(function($) {
			<?php $this->render_generate_qa_handler(); ?>
			<?php $this->render_update_qa_list_function(); ?>
			<?php $this->render_delete_qa_handler(); ?>
			<?php $this->render_add_manual_qa_handler(); ?>
		});
		</script>
		<?php
	}

	/**
	 * Render generate Q&A handler.
	 *
	 * @return void
	 */
	private function render_generate_qa_handler(): void {
		?>
		// Generate Q&A with AI
		$('#fp-seo-generate-qa-btn').on('click', function() {
			const $btn = $(this);
			const postId = $btn.data('post-id');
			const originalText = $btn.html();

			$btn.prop('disabled', true).html('⏳ Generazione in corso...');

			// Call AJAX endpoint to generate Q&A
			$.ajax({
				url: ajaxurl,
				method: 'POST',
				data: {
					action: 'fp_seo_generate_qa',
					post_id: postId,
					nonce: '<?php echo esc_js( wp_create_nonce( 'fp_seo_ai_first' ) ); ?>'
				},
				success: function(response) {
					if (response.success && response.data && response.data.qa_pairs) {
						$btn.prop('disabled', false).html('✅ ' + response.data.message);
						
						// Update Q&A list without reloading page
						updateQAList(response.data.qa_pairs);
						
						// Show success message
						setTimeout(function() {
							$btn.html(originalText);
						}, 2000);
					} else {
						$btn.prop('disabled', false).html(originalText);
						alert('Errore: ' + (response.data?.message || 'Generazione fallita'));
					}
				},
				error: function(xhr, status, error) {
					$btn.prop('disabled', false).html(originalText);
					console.error('Q&A Generation Error:', error, xhr.responseText);
					alert('Errore durante la generazione. Verifica la console per dettagli.');
				}
			});
		});
		<?php
	}

	/**
	 * Render update Q&A list function.
	 *
	 * @return void
	 */
	private function render_update_qa_list_function(): void {
		$post_id = $this->post ? $this->post->ID : 0;
		$json_url = home_url( '/geo/content/' . $post_id . '/qa.json' );
		?>
		// Function to update Q&A list
		function updateQAList(qaPairs) {
			const $list = $('#fp-seo-qa-list');
			
			if (!qaPairs || qaPairs.length === 0) {
				$list.html('<p class="description" style="text-align: center; padding: 30px; background: #fafafa; border-radius: 6px;"><?php esc_html_e( 'Nessuna Q&A pair disponibile. Clicca "Genera Q&A Automaticamente" o aggiungine una manualmente.', 'fp-seo-performance' ); ?></p>');
				return;
			}
			
			let html = '';
			qaPairs.forEach(function(pair, index) {
				html += '<div class="fp-seo-qa-pair" data-index="' + index + '" style="margin-bottom: 15px; padding: 15px; background: white; border: 1px solid #e5e7eb; border-radius: 6px;">';
				html += '<div style="display: flex; justify-content: space-between; align-items: start;">';
				html += '<div style="flex: 1;">';
				html += '<div style="margin-bottom: 10px;"><strong style="color: #1e40af;">Q:</strong> <span>' + escapeHtml(pair.question || '') + '</span></div>';
				html += '<div style="margin-bottom: 10px;"><strong style="color: #059669;">A:</strong> <span>' + escapeHtml(pair.answer || '') + '</span></div>';
				html += '<div style="font-size: 12px; color: #6b7280;">';
				html += '<span title="Confidence Score">⭐ ' + (pair.confidence ? parseFloat(pair.confidence).toFixed(2) : '1.00') + '</span>';
				html += '<span style="margin-left: 15px;" title="Type">🏷️ ' + (pair.question_type || 'informational') + '</span>';
				if (pair.keywords && pair.keywords.length > 0) {
					html += '<span style="margin-left: 15px;" title="Keywords">🔑 ' + escapeHtml(pair.keywords.slice(0, 3).join(', ')) + '</span>';
				}
				html += '</div>';
				html += '</div>';
				html += '<button type="button" class="button fp-seo-delete-qa" data-index="' + index + '" style="color: #dc2626;">×</button>';
				html += '</div>';
				html += '</div>';
			});
			
			html += '<p style="text-align: center; font-size: 13px; color: #64748b;">';
			html += '<?php printf( esc_html__( 'Totale: %d Q&A pairs | Endpoint: ', 'fp-seo-performance' ), 'COUNT_PLACEHOLDER' ); ?>';
			html += '<a href="<?php echo esc_url( $json_url ); ?>" target="_blank"><?php esc_html_e( 'Visualizza JSON', 'fp-seo-performance' ); ?> →</a>';
			html += '</p>';
			html = html.replace('COUNT_PLACEHOLDER', qaPairs.length);
			
			$list.html(html);
			
			// Re-bind delete handlers
			$('.fp-seo-delete-qa').off('click').on('click', function() {
				if (confirm('Eliminare questa Q&A pair?')) {
					$(this).closest('.fp-seo-qa-pair').fadeOut(300, function() {
						$(this).remove();
					});
				}
			});
		}
		
		// Helper to escape HTML
		function escapeHtml(text) {
			const map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return text ? text.replace(/[&<>"']/g, function(m) { return map[m]; }) : '';
		}
		<?php
	}

	/**
	 * Render delete Q&A handler.
	 *
	 * @return void
	 */
	private function render_delete_qa_handler(): void {
		?>
		// Delete Q&A
		$('.fp-seo-delete-qa').on('click', function() {
			if ( confirm('Eliminare questa Q&A pair?') ) {
				$(this).closest('.fp-seo-qa-pair').fadeOut(300, function() {
					$(this).remove();
				});
			}
		});
		<?php
	}

	/**
	 * Render add manual Q&A handler.
	 *
	 * @return void
	 */
	private function render_add_manual_qa_handler(): void {
		?>
		// Add manual Q&A
		$('#fp-seo-add-manual-qa').on('click', function() {
			const question = $('#fp-seo-manual-question').val().trim();
			const answer = $('#fp-seo-manual-answer').val().trim();

			if (!question || !answer) {
				alert('Compila sia domanda che risposta.');
				return;
			}

			// Create new Q&A HTML
			const $newQA = $('<div class="fp-seo-qa-pair" style="margin-bottom: 15px; padding: 15px; background: white; border: 1px solid #e5e7eb; border-radius: 6px;"></div>');
			$newQA.html(
				'<div style="margin-bottom: 10px;"><strong style="color: #1e40af;">Q:</strong> ' + question + '</div>' +
				'<div style="margin-bottom: 10px;"><strong style="color: #059669;">A:</strong> ' + answer + '</div>' +
				'<div style="font-size: 12px; color: #6b7280;"><span>⭐ 1.00</span> <span style="margin-left: 15px;">🏷️ manual</span></div>'
			);

			$('#fp-seo-qa-list').append($newQA);

			// Clear inputs
			$('#fp-seo-manual-question').val('');
			$('#fp-seo-manual-answer').val('');

			alert('✅ Q&A aggiunta! Salva il post per confermare.');
		});
		<?php
	}
}


