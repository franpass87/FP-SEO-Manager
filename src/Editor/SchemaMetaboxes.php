<?php
/**
 * Schema Metaboxes for FAQ and HowTo Schema
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor;

/**
 * Handles FAQ and HowTo Schema metaboxes in the editor.
 */
class SchemaMetaboxes {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_metaboxes' ) );
		add_action( 'save_post', array( $this, 'save_faq_schema' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save_howto_schema' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add metaboxes to the editor.
	 */
	public function add_metaboxes(): void {
		$post_types = array( 'post', 'page' );

		// NOTE: FAQ and HowTo Schema are now integrated into the main SEO Performance metabox
		// for better UX and unified interface. The separate metaboxes are disabled.
		
		/*
		// FAQ Schema Metabox - NOW INTEGRATED IN MAIN METABOX
		add_meta_box(
			'fp-seo-faq-schema',
			'❓ ' . __( 'FAQ Schema - AI Overview Ready', 'fp-seo-performance' ) . ' <span style="display: inline-flex; padding: 2px 8px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #fff; border-radius: 999px; font-size: 10px; font-weight: 700; margin-left: 8px; box-shadow: 0 2px 4px rgba(245, 158, 11, 0.3);">⚡ Impact: +20%</span>',
			array( $this, 'render_faq_metabox' ),
			$post_types,
			'normal',
			'default'
		);

		// HowTo Schema Metabox - NOW INTEGRATED IN MAIN METABOX
		add_meta_box(
			'fp-seo-howto-schema',
			'📖 ' . __( 'HowTo Schema - Guide Step-by-Step', 'fp-seo-performance' ) . ' <span style="display: inline-flex; padding: 2px 8px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: #fff; border-radius: 999px; font-size: 10px; font-weight: 700; margin-left: 8px; box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);">⚡ Impact: +15%</span>',
			array( $this, 'render_howto_metabox' ),
			$post_types,
			'normal',
			'default'
		);
		*/
	}

	/**
	 * Render FAQ Schema metabox.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public function render_faq_metabox( \WP_Post $post ): void {
		wp_nonce_field( 'fp_seo_faq_schema_nonce', 'fp_seo_faq_schema_nonce' );

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

		$faq_questions = get_post_meta( $post->ID, '_fp_seo_faq_questions', true );
		
		// Fallback: query diretta al database se get_post_meta restituisce vuoto
		if ( empty( $faq_questions ) ) {
			global $wpdb;
			$db_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", $post->ID, '_fp_seo_faq_questions' ) );
			if ( $db_value !== null ) {
				$unserialized = maybe_unserialize( $db_value );
				$faq_questions = is_array( $unserialized ) ? $unserialized : array();
			}
		}
		
		if ( ! is_array( $faq_questions ) ) {
			$faq_questions = array();
		}

	?>
	<div class="fp-seo-schema-metabox">
		<!-- Banner removed - now shown in main metabox section -->

			<div id="fp-seo-faq-list" class="fp-seo-faq-list">
				<?php
				if ( ! empty( $faq_questions ) ) {
					foreach ( $faq_questions as $index => $faq ) {
						$this->render_faq_item( $index, $faq );
					}
				}
				?>
			</div>

			<button type="button" class="button button-secondary fp-seo-add-faq">
				<span class="dashicons dashicons-plus-alt2"></span>
				<?php esc_html_e( 'Aggiungi Domanda FAQ', 'fp-seo-performance' ); ?>
			</button>

			<div class="fp-seo-schema-tips">
				<h4>💡 Best Practices per FAQ Schema:</h4>
				<ul>
					<li>✅ Aggiungi <strong>almeno 3-5 domande</strong> pertinenti</li>
					<li>✅ Usa domande che gli utenti <strong>cercano davvero</strong> su Google</li>
					<li>✅ Risposte chiare e complete (<strong>50-300 parole</strong> per risposta)</li>
					<li>✅ Includi parole chiave naturalmente nelle domande</li>
					<li>✅ Formatta domande come "Come...", "Cosa...", "Perché..."</li>
				</ul>
			</div>
		</div>

		<script type="text/html" id="fp-seo-faq-template">
			<?php $this->render_faq_item( '__INDEX__', array( 'question' => '', 'answer' => '' ) ); ?>
		</script>
		<?php
	}

	/**
	 * Render single FAQ item.
	 *
	 * @param int|string     $index FAQ index.
	 * @param array<string, string> $faq   FAQ data.
	 */
	private function render_faq_item( $index, array $faq ): void {
		$question = $faq['question'] ?? '';
		$answer   = $faq['answer'] ?? '';
		?>
		<div class="fp-seo-faq-item" data-index="<?php echo esc_attr( (string) $index ); ?>">
			<div class="fp-seo-faq-item-header">
				<span class="fp-seo-faq-number">
					<span class="dashicons dashicons-format-chat"></span>
					<?php esc_html_e( 'Domanda', 'fp-seo-performance' ); ?> #<span class="faq-num"><?php echo esc_html( is_numeric( $index ) ? (string) ( $index + 1 ) : '1' ); ?></span>
				</span>
				<button type="button" class="fp-seo-remove-faq" title="<?php esc_attr_e( 'Rimuovi FAQ', 'fp-seo-performance' ); ?>">
					<span class="dashicons dashicons-trash"></span>
				</button>
			</div>

			<div class="fp-seo-faq-item-content">
				<div class="fp-seo-form-group">
					<label>
						<strong><?php esc_html_e( 'Domanda', 'fp-seo-performance' ); ?></strong>
						<span class="required">*</span>
					</label>
					<input 
						type="text" 
						name="fp_seo_faq[<?php echo esc_attr( (string) $index ); ?>][question]" 
						value="<?php echo esc_attr( $question ); ?>" 
						placeholder="<?php esc_attr_e( 'Es: Come funziona lo Schema Markup?', 'fp-seo-performance' ); ?>"
						class="widefat"
						required
					>
				</div>

				<div class="fp-seo-form-group">
					<label>
						<strong><?php esc_html_e( 'Risposta', 'fp-seo-performance' ); ?></strong>
						<span class="required">*</span>
					</label>
					<textarea 
						name="fp_seo_faq[<?php echo esc_attr( (string) $index ); ?>][answer]" 
						rows="4" 
						placeholder="<?php esc_attr_e( 'Scrivi una risposta completa e dettagliata (50-300 parole)...', 'fp-seo-performance' ); ?>"
						class="widefat"
						required
					><?php echo esc_textarea( $answer ); ?></textarea>
					<p class="description">
						<span class="fp-seo-char-count">0</span> caratteri
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render HowTo Schema metabox.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public function render_howto_metabox( \WP_Post $post ): void {
		wp_nonce_field( 'fp_seo_howto_schema_nonce', 'fp_seo_howto_schema_nonce' );

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

		$howto_data = get_post_meta( $post->ID, '_fp_seo_howto', true );
		
		// Fallback: query diretta al database se get_post_meta restituisce vuoto
		if ( empty( $howto_data ) ) {
			global $wpdb;
			$db_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", $post->ID, '_fp_seo_howto' ) );
			if ( $db_value !== null ) {
				$unserialized = maybe_unserialize( $db_value );
				$howto_data = is_array( $unserialized ) ? $unserialized : array();
			}
		}
		
		if ( ! is_array( $howto_data ) ) {
			$howto_data = array(
				'name'        => '',
				'description' => '',
				'total_time'  => '',
				'steps'       => array(),
			);
		}

		$steps = $howto_data['steps'] ?? array();
	?>
<div class="fp-seo-schema-metabox">
	<!-- Banner removed - now shown in main metabox section -->

			<div class="fp-seo-howto-header">
				<div class="fp-seo-form-group">
					<label>
						<strong><?php esc_html_e( 'Titolo della Guida', 'fp-seo-performance' ); ?></strong>
					</label>
					<input 
						type="text" 
						name="fp_seo_howto[name]" 
						value="<?php echo esc_attr( $howto_data['name'] ?? '' ); ?>" 
						placeholder="<?php esc_attr_e( 'Lascia vuoto per usare il titolo del post', 'fp-seo-performance' ); ?>"
						class="widefat"
					>
				</div>

				<div class="fp-seo-form-group">
					<label>
						<strong><?php esc_html_e( 'Descrizione della Guida', 'fp-seo-performance' ); ?></strong>
					</label>
					<textarea 
						name="fp_seo_howto[description]" 
						rows="2" 
						placeholder="<?php esc_attr_e( 'Lascia vuoto per usare l\'excerpt del post', 'fp-seo-performance' ); ?>"
						class="widefat"
					><?php echo esc_textarea( $howto_data['description'] ?? '' ); ?></textarea>
				</div>

				<div class="fp-seo-form-group">
					<label>
						<strong><?php esc_html_e( 'Tempo Totale (opzionale)', 'fp-seo-performance' ); ?></strong>
					</label>
					<input 
						type="text" 
						name="fp_seo_howto[total_time]" 
						value="<?php echo esc_attr( $howto_data['total_time'] ?? '' ); ?>" 
						placeholder="<?php esc_attr_e( 'Es: PT30M (30 minuti) o PT1H (1 ora)', 'fp-seo-performance' ); ?>"
						class="regular-text"
					>
					<p class="description">
						<?php esc_html_e( 'Formato ISO 8601: PT30M = 30 minuti, PT1H30M = 1 ora e 30 minuti', 'fp-seo-performance' ); ?>
					</p>
				</div>
			</div>

			<h4><?php esc_html_e( 'Step della Guida', 'fp-seo-performance' ); ?></h4>

			<div id="fp-seo-howto-steps-list" class="fp-seo-howto-steps-list">
				<?php
				if ( ! empty( $steps ) ) {
					foreach ( $steps as $index => $step ) {
						$this->render_howto_step( $index, $step );
					}
				}
				?>
			</div>

			<button type="button" class="button button-secondary fp-seo-add-step">
				<span class="dashicons dashicons-plus-alt2"></span>
				<?php esc_html_e( 'Aggiungi Step', 'fp-seo-performance' ); ?>
			</button>

			<div class="fp-seo-schema-tips">
				<h4>💡 Best Practices per HowTo Schema:</h4>
				<ul>
					<li>✅ Aggiungi <strong>almeno 3 step</strong> ben definiti</li>
					<li>✅ Ogni step deve avere <strong>nome e descrizione chiari</strong></li>
					<li>✅ Ordina gli step in sequenza logica</li>
					<li>✅ Usa verbi d'azione: "Apri...", "Clicca...", "Inserisci..."</li>
					<li>✅ Mantieni gli step concisi ma completi</li>
				</ul>
			</div>
		</div>

		<script type="text/html" id="fp-seo-howto-step-template">
			<?php $this->render_howto_step( '__INDEX__', array( 'name' => '', 'text' => '', 'url' => '' ) ); ?>
		</script>
		<?php
	}

	/**
	 * Render single HowTo step.
	 *
	 * @param int|string     $index Step index.
	 * @param array<string, string> $step  Step data.
	 */
	private function render_howto_step( $index, array $step ): void {
		$name = $step['name'] ?? '';
		$text = $step['text'] ?? '';
		$url  = $step['url'] ?? '';
		?>
		<div class="fp-seo-howto-step" data-index="<?php echo esc_attr( (string) $index ); ?>">
			<div class="fp-seo-howto-step-header">
				<span class="fp-seo-howto-number">
					<span class="dashicons dashicons-list-view"></span>
					<?php esc_html_e( 'Step', 'fp-seo-performance' ); ?> #<span class="step-num"><?php echo esc_html( is_numeric( $index ) ? (string) ( $index + 1 ) : '1' ); ?></span>
				</span>
				<div class="fp-seo-howto-actions">
					<button type="button" class="fp-seo-move-up" title="<?php esc_attr_e( 'Sposta su', 'fp-seo-performance' ); ?>">
						<span class="dashicons dashicons-arrow-up-alt2"></span>
					</button>
					<button type="button" class="fp-seo-move-down" title="<?php esc_attr_e( 'Sposta giù', 'fp-seo-performance' ); ?>">
						<span class="dashicons dashicons-arrow-down-alt2"></span>
					</button>
					<button type="button" class="fp-seo-remove-step" title="<?php esc_attr_e( 'Rimuovi step', 'fp-seo-performance' ); ?>">
						<span class="dashicons dashicons-trash"></span>
					</button>
				</div>
			</div>

			<div class="fp-seo-howto-step-content">
				<div class="fp-seo-form-group">
					<label>
						<strong><?php esc_html_e( 'Nome dello Step', 'fp-seo-performance' ); ?></strong>
						<span class="required">*</span>
					</label>
					<input 
						type="text" 
						name="fp_seo_howto[steps][<?php echo esc_attr( (string) $index ); ?>][name]" 
						value="<?php echo esc_attr( $name ); ?>" 
						placeholder="<?php esc_attr_e( 'Es: Installa il plugin', 'fp-seo-performance' ); ?>"
						class="widefat"
						required
					>
				</div>

				<div class="fp-seo-form-group">
					<label>
						<strong><?php esc_html_e( 'Descrizione dello Step', 'fp-seo-performance' ); ?></strong>
						<span class="required">*</span>
					</label>
					<textarea 
						name="fp_seo_howto[steps][<?php echo esc_attr( (string) $index ); ?>][text]" 
						rows="3" 
						placeholder="<?php esc_attr_e( 'Descrivi in dettaglio come completare questo step...', 'fp-seo-performance' ); ?>"
						class="widefat"
						required
					><?php echo esc_textarea( $text ); ?></textarea>
				</div>

				<div class="fp-seo-form-group">
					<label>
						<strong><?php esc_html_e( 'URL Immagine o Risorsa (opzionale)', 'fp-seo-performance' ); ?></strong>
					</label>
					<input 
						type="url" 
						name="fp_seo_howto[steps][<?php echo esc_attr( (string) $index ); ?>][url]" 
						value="<?php echo esc_attr( $url ); ?>" 
						placeholder="<?php esc_attr_e( 'https://esempio.com/immagine-step.jpg', 'fp-seo-performance' ); ?>"
						class="widefat"
					>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Save FAQ Schema data.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function save_faq_schema( int $post_id, \WP_Post $post ): void {
		// Security checks
		if ( ! isset( $_POST['fp_seo_faq_schema_nonce'] ) || ! wp_verify_nonce( $_POST['fp_seo_faq_schema_nonce'], 'fp_seo_faq_schema_nonce' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Get and sanitize FAQ data
		$faq_data = $_POST['fp_seo_faq'] ?? array();
		$sanitized_faqs = array();

		if ( is_array( $faq_data ) ) {
			foreach ( $faq_data as $faq ) {
				if ( ! is_array( $faq ) ) {
					continue;
				}

				$question = sanitize_text_field( $faq['question'] ?? '' );
				$answer   = wp_kses_post( $faq['answer'] ?? '' );

				// Only save if both question and answer are not empty
				if ( ! empty( $question ) && ! empty( $answer ) ) {
					$sanitized_faqs[] = array(
						'question' => $question,
						'answer'   => $answer,
					);
				}
			}
		}

		// Save or delete meta
		if ( ! empty( $sanitized_faqs ) ) {
			update_post_meta( $post_id, '_fp_seo_faq_questions', $sanitized_faqs );
			
			// Clear schema cache
			$cache_key = 'fp_seo_schemas_' . $post_id . '_' . get_current_blog_id();
			wp_cache_delete( $cache_key );
		} else {
			delete_post_meta( $post_id, '_fp_seo_faq_questions' );
		}
	}

	/**
	 * Save HowTo Schema data.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function save_howto_schema( int $post_id, \WP_Post $post ): void {
		// Security checks
		if ( ! isset( $_POST['fp_seo_howto_schema_nonce'] ) || ! wp_verify_nonce( $_POST['fp_seo_howto_schema_nonce'], 'fp_seo_howto_schema_nonce' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Get and sanitize HowTo data
		$howto_data = $_POST['fp_seo_howto'] ?? array();
		
		if ( ! is_array( $howto_data ) ) {
			return;
		}

		$sanitized_howto = array(
			'name'        => sanitize_text_field( $howto_data['name'] ?? '' ),
			'description' => wp_kses_post( $howto_data['description'] ?? '' ),
			'total_time'  => sanitize_text_field( $howto_data['total_time'] ?? '' ),
			'steps'       => array(),
		);

		// Sanitize steps
		if ( isset( $howto_data['steps'] ) && is_array( $howto_data['steps'] ) ) {
			foreach ( $howto_data['steps'] as $step ) {
				if ( ! is_array( $step ) ) {
					continue;
				}

				$name = sanitize_text_field( $step['name'] ?? '' );
				$text = wp_kses_post( $step['text'] ?? '' );
				$url  = esc_url_raw( $step['url'] ?? '' );

				// Only save if name and text are not empty
				if ( ! empty( $name ) && ! empty( $text ) ) {
					$sanitized_howto['steps'][] = array(
						'name' => $name,
						'text' => $text,
						'url'  => $url,
					);
				}
			}
		}

		// Save or delete meta
		if ( ! empty( $sanitized_howto['steps'] ) ) {
			update_post_meta( $post_id, '_fp_seo_howto', $sanitized_howto );
			
			// Clear schema cache
			$cache_key = 'fp_seo_schemas_' . $post_id . '_' . get_current_blog_id();
			wp_cache_delete( $cache_key );
		} else {
			delete_post_meta( $post_id, '_fp_seo_howto' );
		}
	}

	/**
	 * Enqueue metabox assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		// Only enqueue in admin context
		if ( ! is_admin() ) {
			return;
		}
		
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, array( 'post', 'page' ), true ) ) {
			return;
		}

		// Enqueue CSS
		wp_add_inline_style( 'wp-admin', $this->get_inline_css() );

		// Enqueue JavaScript
		wp_add_inline_script( 'jquery', $this->get_inline_js() );
	}

	/**
	 * Get inline CSS for metaboxes.
	 *
	 * @return string
	 */
	private function get_inline_css(): string {
		return '
		.fp-seo-schema-metabox {
			padding: 0;
		}

		.fp-seo-schema-intro {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: white;
			padding: 16px;
			margin: -12px -12px 20px;
			border-radius: 4px 4px 0 0;
		}

		.fp-seo-schema-intro .description {
			color: white;
			margin: 0;
			line-height: 1.6;
		}

		.fp-seo-faq-item,
		.fp-seo-howto-step {
			background: #f9fafb;
			border: 2px solid #e5e7eb;
			border-radius: 8px;
			margin-bottom: 16px;
			transition: all 0.3s ease;
		}

		.fp-seo-faq-item:hover,
		.fp-seo-howto-step:hover {
			border-color: #3b82f6;
			box-shadow: 0 4px 6px rgba(59, 130, 246, 0.1);
		}

		.fp-seo-faq-item-header,
		.fp-seo-howto-step-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			padding: 12px 16px;
			background: white;
			border-bottom: 1px solid #e5e7eb;
			border-radius: 6px 6px 0 0;
		}

		.fp-seo-faq-number,
		.fp-seo-howto-number {
			display: flex;
			align-items: center;
			gap: 6px;
			font-weight: 600;
			color: #374151;
		}

		.fp-seo-faq-number .dashicons,
		.fp-seo-howto-number .dashicons {
			color: #3b82f6;
		}

		.fp-seo-remove-faq,
		.fp-seo-remove-step,
		.fp-seo-move-up,
		.fp-seo-move-down {
			background: transparent;
			border: none;
			padding: 4px 8px;
			cursor: pointer;
			color: #6b7280;
			transition: all 0.2s ease;
			border-radius: 4px;
		}

		.fp-seo-remove-faq:hover,
		.fp-seo-remove-step:hover {
			background: #fef2f2;
			color: #dc2626;
		}

		.fp-seo-move-up:hover,
		.fp-seo-move-down:hover {
			background: #eff6ff;
			color: #3b82f6;
		}

		.fp-seo-howto-actions {
			display: flex;
			gap: 4px;
		}

		.fp-seo-faq-item-content,
		.fp-seo-howto-step-content {
			padding: 16px;
		}

		.fp-seo-form-group {
			margin-bottom: 16px;
		}

		.fp-seo-form-group:last-child {
			margin-bottom: 0;
		}

		.fp-seo-form-group label {
			display: block;
			margin-bottom: 6px;
			color: #374151;
		}

		.fp-seo-form-group .required {
			color: #dc2626;
		}

		.fp-seo-form-group input[type="text"],
		.fp-seo-form-group input[type="url"],
		.fp-seo-form-group textarea {
			width: 100%;
			padding: 8px 12px;
			border: 1px solid #d1d5db;
			border-radius: 6px;
			font-size: 14px;
			transition: all 0.2s ease;
		}

		.fp-seo-form-group input:focus,
		.fp-seo-form-group textarea:focus {
			border-color: #3b82f6;
			box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
			outline: none;
		}

		.fp-seo-form-group .description {
			margin-top: 6px;
			font-size: 12px;
			color: #6b7280;
		}

		.fp-seo-char-count {
			font-weight: 600;
			color: #3b82f6;
		}

		.fp-seo-add-faq,
		.fp-seo-add-step {
			margin-top: 12px;
			display: inline-flex;
			align-items: center;
			gap: 6px;
		}

		.fp-seo-schema-tips {
			background: #fef3c7;
			border-left: 4px solid #f59e0b;
			padding: 16px;
			margin-top: 20px;
			border-radius: 4px;
		}

		.fp-seo-schema-tips h4 {
			margin: 0 0 12px;
			color: #92400e;
			font-size: 14px;
		}

		.fp-seo-schema-tips ul {
			margin: 0;
			padding-left: 20px;
		}

		.fp-seo-schema-tips li {
			margin-bottom: 6px;
			color: #78350f;
			font-size: 13px;
		}

		.fp-seo-howto-header {
			margin-bottom: 24px;
			padding-bottom: 16px;
			border-bottom: 2px solid #e5e7eb;
		}
		';
	}

	/**
	 * Get inline JavaScript for metaboxes.
	 *
	 * @return string
	 */
	private function get_inline_js(): string {
		return "
		jQuery(document).ready(function($) {
			// FAQ Management
			var faqIndex = $('.fp-seo-faq-item').length;

			// Add FAQ
			$('.fp-seo-add-faq').on('click', function() {
				var template = $('#fp-seo-faq-template').html();
				var newItem = template.replace(/__INDEX__/g, faqIndex);
				$('#fp-seo-faq-list').append(newItem);
				faqIndex++;
				updateFaqNumbers();
			});

			// Remove FAQ
			$(document).on('click', '.fp-seo-remove-faq', function() {
				if (confirm('Sei sicuro di voler rimuovere questa FAQ?')) {
					$(this).closest('.fp-seo-faq-item').fadeOut(300, function() {
						$(this).remove();
						updateFaqNumbers();
					});
				}
			});

			// Character count for FAQ answers
			$(document).on('input', '.fp-seo-faq-item textarea', function() {
				var count = $(this).val().length;
				$(this).closest('.fp-seo-form-group').find('.fp-seo-char-count').text(count);
			});

			// Initialize character counts
			$('.fp-seo-faq-item textarea').each(function() {
				var count = $(this).val().length;
				$(this).closest('.fp-seo-form-group').find('.fp-seo-char-count').text(count);
			});

			function updateFaqNumbers() {
				$('.fp-seo-faq-item').each(function(index) {
					$(this).find('.faq-num').text(index + 1);
				});
			}

			// HowTo Management
			var stepIndex = $('.fp-seo-howto-step').length;

			// Add Step
			$('.fp-seo-add-step').on('click', function() {
				var template = $('#fp-seo-howto-step-template').html();
				var newStep = template.replace(/__INDEX__/g, stepIndex);
				$('#fp-seo-howto-steps-list').append(newStep);
				stepIndex++;
				updateStepNumbers();
			});

			// Remove Step
			$(document).on('click', '.fp-seo-remove-step', function() {
				if (confirm('Sei sicuro di voler rimuovere questo step?')) {
					$(this).closest('.fp-seo-howto-step').fadeOut(300, function() {
						$(this).remove();
						updateStepNumbers();
					});
				}
			});

			// Move Step Up
			$(document).on('click', '.fp-seo-move-up', function() {
				var step = $(this).closest('.fp-seo-howto-step');
				var prev = step.prev('.fp-seo-howto-step');
				if (prev.length) {
					step.fadeOut(200, function() {
						step.insertBefore(prev).fadeIn(200);
						updateStepNumbers();
					});
				}
			});

			// Move Step Down
			$(document).on('click', '.fp-seo-move-down', function() {
				var step = $(this).closest('.fp-seo-howto-step');
				var next = step.next('.fp-seo-howto-step');
				if (next.length) {
					step.fadeOut(200, function() {
						step.insertAfter(next).fadeIn(200);
						updateStepNumbers();
					});
				}
			});

			function updateStepNumbers() {
				$('.fp-seo-howto-step').each(function(index) {
					$(this).find('.step-num').text(index + 1);
					
					// Update input names
					var newIndex = index;
					$(this).find('input, textarea').each(function() {
						var name = $(this).attr('name');
						if (name) {
							var baseName = name.replace(/\[\d+\]/, '[' + newIndex + ']');
							$(this).attr('name', baseName);
						}
					});
				});
			}
		});
		";
	}
}

