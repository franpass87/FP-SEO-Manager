<?php
/**
 * Bulk SEO Update admin page.
 *
 * One-click AI-powered SEO update for pages and articles.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin;

use FP\SEO\Integrations\OpenAiClient;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use FP\SEO\Infrastructure\Contracts\OptionsInterface;
use WP_Post;
use WP_Query;
use function absint;
use function admin_url;
use function check_ajax_referer;
use function current_user_can;
use function get_post;
use function is_array;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function sanitize_title;
use function update_post_meta;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_unslash;
use function wp_update_post;

/**
 * Bulk SEO Update page - AI-powered meta generation for pages and posts.
 */
class BulkSeoUpdatePage {

	public const PAGE_SLUG    = 'fp-seo-bulk-seo-update';
	private const PAGE_PARENT = 'fp-seo-performance';
	private const AJAX_ACTION = 'fp_seo_bulk_update_seo';
	private const NONCE_ACTION = 'fp_seo_bulk_update_seo';
	private const POSTS_PER_PAGE = 100;

	private HookManagerInterface $hook_manager;
	private OptionsInterface $options;
	private OpenAiClient $openai_client;

	public function __construct(
		HookManagerInterface $hook_manager,
		OptionsInterface $options,
		OpenAiClient $openai_client
	) {
		$this->hook_manager   = $hook_manager;
		$this->options        = $options;
		$this->openai_client  = $openai_client;
	}

	public function register(): void {
		$this->hook_manager->add_action( 'admin_menu', array( $this, 'add_page' ) );
		$this->hook_manager->add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		$this->hook_manager->add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_ajax_update' ) );
	}

	public function add_page(): void {
		add_submenu_page(
			self::PAGE_PARENT,
			__( 'Aggiorna SEO con AI', 'fp-seo-performance' ),
			__( 'Aggiorna SEO con AI', 'fp-seo-performance' ),
			$this->options->get_capability(),
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( 'fp-seo-performance_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style( 'fp-seo-performance-admin' );
		wp_add_inline_style(
			'fp-seo-performance-admin',
			'.fp-seo-bulk-update__tabs{margin:16px 0}.fp-seo-bulk-update__panel{margin-top:16px}.fp-seo-bulk-update__toolbar{margin:12px 0}.fp-seo-bulk-update__status{margin:12px 0;padding:10px 12px}.fp-seo-bulk-update__select-all{margin-left:12px}'
		);
		wp_enqueue_script( 'fp-seo-performance-admin' );
		wp_enqueue_script( 'fp-seo-performance-bulk' );

		wp_localize_script(
			'fp-seo-performance-bulk',
			'fpSeoBulkUpdate',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( self::NONCE_ACTION ),
				'action'    => self::AJAX_ACTION,
				'chunkSize' => 5,
				'messages'  => array(
					'processing'   => __( 'Aggiornamento %1$d di %2$d…', 'fp-seo-performance' ),
					'complete'     => __( 'Aggiornamento completato per %d elementi.', 'fp-seo-performance' ),
					'noneSelected' => __( 'Seleziona almeno un elemento.', 'fp-seo-performance' ),
					'error'        => __( 'Errore durante l\'aggiornamento. Riprova.', 'fp-seo-performance' ),
					'noApiKey'     => __( 'Configura la API Key OpenAI in Impostazioni > AI.', 'fp-seo-performance' ),
				),
			)
		);

		wp_add_inline_script(
			'fp-seo-performance-bulk',
			$this->get_inline_script(),
			'after'
		);
	}

	/**
	 * Get inline script for bulk SEO update UI.
	 *
	 * @return string
	 */
	private function get_inline_script(): string {
		return <<<'JS'
(function() {
	var cfg = window.fpSeoBulkUpdate;
	if (!cfg || !document.querySelector('.fp-seo-bulk-update')) return;
	var wrap = document.querySelector('.fp-seo-bulk-update');
	var tabs = wrap.querySelectorAll('[data-fp-seo-bulk-tab]');
	var panels = wrap.querySelectorAll('.fp-seo-bulk-update__panel');
	var statusEl = wrap.querySelector('[data-fp-seo-bulk-status]');
	function showStatus(msg, isError) {
		if (!statusEl) return;
		statusEl.textContent = msg;
		statusEl.hidden = false;
		statusEl.className = 'fp-seo-bulk-update__status notice notice-' + (isError ? 'error' : 'success');
	}
	function getVisiblePanel() { return wrap.querySelector('.fp-seo-bulk-update__panel:not([hidden])'); }
	function getSelectedIds() {
		var panel = getVisiblePanel();
		if (!panel) return [];
		var cbs = panel.querySelectorAll('.fp-seo-bulk-checkbox:checked');
		return Array.prototype.map.call(cbs, function(cb) { return cb.value; });
	}
	tabs.forEach(function(tab) {
		tab.addEventListener('click', function() {
			var t = tab.getAttribute('data-fp-seo-bulk-tab');
			tabs.forEach(function(tt) { tt.classList.remove('nav-tab-active'); tt.setAttribute('aria-selected','false'); });
			tab.classList.add('nav-tab-active');
			tab.setAttribute('aria-selected','true');
			panels.forEach(function(p) {
				p.hidden = (p.getAttribute('data-tab') !== t);
			});
		});
	});
	wrap.querySelectorAll('[data-fp-seo-bulk-select-panel]').forEach(function(cb) {
		cb.addEventListener('change', function() {
			var panel = cb.closest('.fp-seo-bulk-update__panel');
			if (!panel) return;
			panel.querySelectorAll('.fp-seo-bulk-checkbox').forEach(function(b) { b.checked = cb.checked; });
		});
	});
	wrap.querySelectorAll('[data-fp-seo-bulk-select-all-panel]').forEach(function(cb) {
		cb.addEventListener('change', function() {
			var panel = cb.closest('.fp-seo-bulk-update__panel');
			if (!panel) return;
			panel.querySelectorAll('.fp-seo-bulk-checkbox').forEach(function(b) { b.checked = cb.checked; });
		});
	});
	wrap.querySelectorAll('[data-fp-seo-bulk-update-btn]').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var ids = getSelectedIds();
			if (!ids.length) { showStatus(cfg.messages.noneSelected, true); return; }
			btn.disabled = true;
			showStatus((cfg.messages.processing || '').replace('%1$d','0').replace('%2$d', ids.length), false);
			var chunk = parseInt(cfg.chunkSize, 10) || 5;
			var done = 0;
			function sendBatch(ind) {
				var batch = ids.slice(ind, ind + chunk);
				if (!batch.length) {
					btn.disabled = false;
					showStatus((cfg.messages.complete || '').replace('%d', done), false);
					return;
				}
				var fd = new FormData();
				fd.append('action', cfg.action);
				fd.append('nonce', cfg.nonce);
				batch.forEach(function(id) { fd.append('post_ids[]', id); });
				fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
					.then(function(r) { return r.json(); })
					.then(function(data) {
						if (data.success && data.data) {
							done += (data.data.updated || 0);
							showStatus((cfg.messages.processing || '').replace('%1$d', Math.min(ind + batch.length, ids.length)).replace('%2$d', ids.length), false);
						}
						sendBatch(ind + chunk);
					})
					.catch(function() {
						btn.disabled = false;
						showStatus(cfg.messages.error, true);
					});
			}
			sendBatch(0);
		});
	});
})();
JS;
	}

	public function render(): void {
		if ( ! current_user_can( $this->options->get_capability() ) ) {
			wp_die( esc_html__( 'Non hai i permessi per accedere a questa pagina.', 'fp-seo-performance' ) );
		}

		$pages    = $this->query_posts( 'page' );
		$posts    = $this->query_posts( 'post' );
		$has_ai   = $this->openai_client->is_configured();
		$context  = (string) $this->options->get_option( 'ai.site_context', '' );
		$renderer = new Renderers\BulkSeoUpdatePageRenderer();
		$renderer->render( $pages, $posts, $has_ai, trim( $context ) !== '', self::NONCE_ACTION );
	}

	public function handle_ajax_update(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( $this->options->get_capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'fp-seo-performance' ) ), 403 );
		}

		if ( ! $this->openai_client->is_configured() ) {
			wp_send_json_error( array( 'message' => __( 'API Key OpenAI non configurata. Vai in Impostazioni > AI.', 'fp-seo-performance' ) ), 400 );
		}

		$ids = isset( $_POST['post_ids'] ) ? (array) wp_unslash( $_POST['post_ids'] ) : array();
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

		if ( empty( $ids ) ) {
			wp_send_json_success( array( 'updated' => 0, 'errors' => array() ) );
		}

		$updated = 0;
		$errors  = array();

		foreach ( $ids as $post_id ) {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				$errors[ $post_id ] = __( 'Permessi insufficienti', 'fp-seo-performance' );
				continue;
			}

			$post = get_post( $post_id );
			if ( ! $post instanceof WP_Post ) {
				$errors[ $post_id ] = __( 'Post non trovato', 'fp-seo-performance' );
				continue;
			}

			$content = wp_strip_all_tags( $post->post_content );
			$title   = $post->post_title;
			$focus   = (string) get_post_meta( $post_id, '_fp_seo_focus_keyword', true );

			$result = $this->openai_client->generate_seo_suggestions(
				$post_id,
				$content,
				$title,
				$focus
			);

			if ( empty( $result['success'] ) || ! isset( $result['data'] ) ) {
				$errors[ $post_id ] = $result['error'] ?? __( 'Errore generazione', 'fp-seo-performance' );
				continue;
			}

			$data = $result['data'];

			update_post_meta( $post_id, '_fp_seo_title', sanitize_text_field( $data['seo_title'] ?? '' ) );
			update_post_meta( $post_id, '_fp_seo_meta_description', sanitize_textarea_field( $data['meta_description'] ?? '' ) );
			update_post_meta( $post_id, '_fp_seo_focus_keyword', sanitize_text_field( $data['focus_keyword'] ?? '' ) );

			$slug = isset( $data['slug'] ) ? sanitize_title( $data['slug'] ) : '';
			if ( '' !== $slug ) {
				wp_update_post(
					array(
						'ID'        => $post_id,
						'post_name' => $slug,
					)
				);
			}

			++$updated;
		}

		wp_send_json_success(
			array(
				'updated' => $updated,
				'errors'  => $errors,
			)
		);
	}

	/**
	 * Query posts by post type.
	 *
	 * @param string $post_type Post type (page or post).
	 * @return array<WP_Post>
	 */
	private function query_posts( string $post_type ): array {
		$query = new WP_Query(
			array(
				'post_type'              => $post_type,
				'posts_per_page'         => self::POSTS_PER_PAGE,
				'post_status'            => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return is_array( $query->posts ) ? $query->posts : array();
	}
}
