<?php
/**
 * Renders the Bulk SEO Update admin page.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Renderers;

use FP\SEO\Utils\OptionsHelper;
use WP_Post;
use function esc_attr;
use function esc_html;
use function esc_html_e;
use function esc_url;
use function get_edit_post_link;
use function get_the_title;
use function wp_die;

/**
 * Renders the Bulk SEO Update page with Pagine and Articoli tabs.
 */
class BulkSeoUpdatePageRenderer {

	/**
	 * Render the bulk SEO update page.
	 *
	 * @param array<WP_Post> $pages      Page posts.
	 * @param array<WP_Post> $posts      Post (article) posts.
	 * @param bool           $has_ai     OpenAI configured.
	 * @param bool           $has_context Site context configured.
	 * @param string         $nonce_action Nonce action.
	 * @return void
	 */
	public function render(
		array $pages,
		array $posts,
		bool $has_ai,
		bool $has_context,
		string $nonce_action
	): void {
		if ( ! current_user_can( OptionsHelper::get_capability() ) ) {
			wp_die( esc_html__( 'Non hai i permessi per accedere a questa pagina.', 'fp-seo-performance' ) );
		}

		?>
		<div class="wrap fp-seo-bulk-update">
			<h1><?php esc_html_e( 'Aggiorna SEO con AI', 'fp-seo-performance' ); ?></h1>

			<?php if ( ! $has_ai ) : ?>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'Configura la API Key OpenAI in Impostazioni > AI per utilizzare questa funzionalità.', 'fp-seo-performance' ); ?></p>
				</div>
			<?php elseif ( ! $has_context ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'Consigliato: configura il "Contesto sito" in Impostazioni > AI per migliorare i risultati. Descrivi in 1-2 frasi di cosa parla il sito.', 'fp-seo-performance' ); ?></p>
				</div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper fp-seo-bulk-update__tabs" aria-label="<?php esc_attr_e( 'Tab contenuti', 'fp-seo-performance' ); ?>">
				<button type="button" class="nav-tab nav-tab-active" data-fp-seo-bulk-tab="pages" aria-selected="true">
					<?php esc_html_e( 'Pagine', 'fp-seo-performance' ); ?> (<?php echo esc_html( (string) count( $pages ) ); ?>)
				</button>
				<button type="button" class="nav-tab" data-fp-seo-bulk-tab="posts" aria-selected="false">
					<?php esc_html_e( 'Articoli', 'fp-seo-performance' ); ?> (<?php echo esc_html( (string) count( $posts ) ); ?>)
				</button>
			</nav>

			<div class="fp-seo-bulk-update__panels">
				<div class="fp-seo-bulk-update__panel" id="fp-seo-bulk-panel-pages" data-tab="pages">
					<?php $this->render_panel( $pages, $has_ai, $nonce_action ); ?>
				</div>
				<div class="fp-seo-bulk-update__panel" id="fp-seo-bulk-panel-posts" data-tab="posts" hidden>
					<?php $this->render_panel( $posts, $has_ai, $nonce_action ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a single panel (pages or posts list).
	 *
	 * @param array<WP_Post> $items       Posts to display.
	 * @param bool           $has_ai      OpenAI configured.
	 * @param string         $nonce_action Nonce action.
	 * @return void
	 */
	private function render_panel( array $items, bool $has_ai, string $nonce_action ): void {
		?>
		<div class="fp-seo-bulk-update__toolbar">
			<button type="button" class="button button-primary" data-fp-seo-bulk-update-btn <?php echo $has_ai ? '' : 'disabled'; ?>>
				<?php esc_html_e( 'Aggiorna selezionate con AI', 'fp-seo-performance' ); ?>
			</button>
			<span class="fp-seo-bulk-update__select-all">
				<label>
					<input type="checkbox" data-fp-seo-bulk-select-panel />
					<?php esc_html_e( 'Seleziona tutte', 'fp-seo-performance' ); ?>
				</label>
			</span>
		</div>

		<div class="fp-seo-bulk-update__status" role="status" aria-live="polite" hidden data-fp-seo-bulk-status></div>

		<?php if ( empty( $items ) ) : ?>
			<p class="description"><?php esc_html_e( 'Nessun contenuto trovato.', 'fp-seo-performance' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<td class="check-column"><input type="checkbox" data-fp-seo-bulk-select-all-panel /></td>
						<th scope="col"><?php esc_html_e( 'Titolo', 'fp-seo-performance' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Stato', 'fp-seo-performance' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $items as $post ) : ?>
						<?php $this->render_row( $post, $has_ai ); ?>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render a table row.
	 *
	 * @param WP_Post $post    Post object.
	 * @param bool    $has_ai  OpenAI configured.
	 * @return void
	 */
	private function render_row( WP_Post $post, bool $has_ai ): void {
		$post_id = (int) $post->ID;
		?>
		<tr data-post-id="<?php echo esc_attr( (string) $post_id ); ?>">
			<th scope="row" class="check-column">
				<input type="checkbox" name="post_ids[]" value="<?php echo esc_attr( (string) $post_id ); ?>" class="fp-seo-bulk-checkbox" <?php echo $has_ai ? '' : 'disabled'; ?> />
			</th>
			<td>
				<a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>">
					<?php echo esc_html( get_the_title( $post ) ); ?>
				</a>
			</td>
			<td><?php echo esc_html( ucfirst( $post->post_status ) ); ?></td>
		</tr>
		<?php
	}
}
