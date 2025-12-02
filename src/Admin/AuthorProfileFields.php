<?php
/**
 * Author Profile Fields for Authority Signals
 *
 * Adds custom fields to user profiles for author authority and expertise.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin;

use FP\SEO\Admin\Scripts\AuthorProfileFieldsScriptsManager;
use FP\SEO\Admin\Styles\AuthorProfileFieldsStylesManager;

/**
 * Manages author authority fields in user profiles
 */
class AuthorProfileFields {
	/**
	 * @var AuthorProfileFieldsScriptsManager|null
	 */
	private $scripts_manager;

	/**
	 * @var AuthorProfileFieldsStylesManager|null
	 */
	private $styles_manager;

	/**
	 * Register hooks
	 */
	public function register(): void {
		add_action( 'show_user_profile', array( $this, 'render_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'render_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_fields' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Initialize and register scripts manager
		$this->scripts_manager = new AuthorProfileFieldsScriptsManager();
		$this->scripts_manager->register_hooks();

		// Initialize styles manager
		$this->styles_manager = new AuthorProfileFieldsStylesManager();
	}

	/**
	 * Enqueue assets for profile page
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_assets( string $hook ): void {
		// Only enqueue in admin context
		if ( ! is_admin() ) {
			return;
		}
		
		if ( ! in_array( $hook, array( 'profile.php', 'user-edit.php' ), true ) ) {
			return;
		}

		// Inline styles for better UX
		if ( $this->styles_manager ) {
			wp_add_inline_style( 'wp-admin', $this->styles_manager->get_styles() );
		}
	}

	// Inline styles removed - now handled by AuthorProfileFieldsStylesManager

	/**
	 * Render profile fields
	 *
	 * @param \WP_User $user User object.
	 */
	public function render_fields( \WP_User $user ): void {
		// Check permissions
		if ( ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}

		// Get existing values
		$title          = get_user_meta( $user->ID, 'fp_author_title', true );
		$experience     = get_user_meta( $user->ID, 'fp_author_experience_years', true );
		$certifications = get_user_meta( $user->ID, 'fp_author_certifications', true );
		$expertise      = get_user_meta( $user->ID, 'fp_author_expertise', true );
		$education      = get_user_meta( $user->ID, 'fp_author_education', true );
		$followers      = get_user_meta( $user->ID, 'fp_author_followers', true );
		$endorsements   = get_user_meta( $user->ID, 'fp_author_endorsements', true );
		$speaking       = get_user_meta( $user->ID, 'fp_author_speaking_engagements', true );

		$certifications = is_array( $certifications ) ? $certifications : array();
		$expertise      = is_array( $expertise ) ? $expertise : array();

		?>
		<div class="fp-seo-author-authority">
			<h3><?php esc_html_e( '🏆 FP SEO - Author Authority & Expertise', 'fp-seo-performance' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Questi dati migliorano l\'authority score del contenuto per AI engines (Gemini, ChatGPT, Claude, Perplexity).', 'fp-seo-performance' ); ?>
			</p>

			<table class="form-table">
				<!-- Professional Title -->
				<tr>
					<th>
						<label for="fp_author_title">
							<?php esc_html_e( 'Professional Title', 'fp-seo-performance' ); ?>
						</label>
					</th>
					<td>
						<input type="text" 
							   name="fp_author_title" 
							   id="fp_author_title" 
							   value="<?php echo esc_attr( $title ); ?>" 
							   class="regular-text" 
							   placeholder="SEO Expert, WordPress Developer, Digital Marketing Specialist">
						<p class="description">
							<?php esc_html_e( 'Es: SEO Expert, WordPress Developer, Content Marketing Specialist', 'fp-seo-performance' ); ?>
						</p>
					</td>
				</tr>

				<!-- Experience Years -->
				<tr>
					<th>
						<label for="fp_author_experience_years">
							<?php esc_html_e( 'Years of Experience', 'fp-seo-performance' ); ?>
						</label>
					</th>
					<td>
						<input type="number" 
							   name="fp_author_experience_years" 
							   id="fp_author_experience_years" 
							   value="<?php echo esc_attr( $experience ); ?>" 
							   min="0" 
							   max="50" 
							   step="1">
						<p class="description">
							<?php esc_html_e( 'Anni di esperienza nel settore (aumenta authority score)', 'fp-seo-performance' ); ?>
						</p>
					</td>
				</tr>

				<!-- Education -->
				<tr>
					<th>
						<label for="fp_author_education">
							<?php esc_html_e( 'Education', 'fp-seo-performance' ); ?>
						</label>
					</th>
					<td>
						<input type="text" 
							   name="fp_author_education" 
							   id="fp_author_education" 
							   value="<?php echo esc_attr( $education ); ?>" 
							   class="regular-text" 
							   placeholder="Master in Digital Marketing, Computer Science Degree">
						<p class="description">
							<?php esc_html_e( 'Titolo di studio o formazione rilevante', 'fp-seo-performance' ); ?>
						</p>
					</td>
				</tr>

				<!-- Certifications -->
				<tr>
					<th>
						<label for="fp_author_certifications_input">
							<?php esc_html_e( 'Certifications', 'fp-seo-performance' ); ?>
						</label>
					</th>
					<td>
						<input type="text" 
							   id="fp_author_certifications_input" 
							   class="fp-seo-tag-input" 
							   placeholder="Es: Google Analytics Certified (premi Invio per aggiungere)">
						
						<div class="fp-seo-expertise-tags" id="fp-seo-certifications-list">
							<?php foreach ( $certifications as $cert ) : ?>
								<span class="fp-seo-expertise-tag">
									<?php echo esc_html( $cert ); ?>
									<button type="button" onclick="this.parentElement.remove()">×</button>
								</span>
							<?php endforeach; ?>
						</div>

						<input type="hidden" name="fp_author_certifications" id="fp_author_certifications" value="<?php echo esc_attr( wp_json_encode( $certifications ) ); ?>">

						<p class="description">
							<?php esc_html_e( 'Certificazioni professionali (Google Analytics, Yoast SEO, HubSpot, ecc.)', 'fp-seo-performance' ); ?>
						</p>
					</td>
				</tr>

				<!-- Expertise Areas -->
				<tr>
					<th>
						<label for="fp_author_expertise_input">
							<?php esc_html_e( 'Expertise Areas', 'fp-seo-performance' ); ?>
						</label>
					</th>
					<td>
						<input type="text" 
							   id="fp_author_expertise_input" 
							   class="fp-seo-tag-input" 
							   placeholder="Es: SEO, WordPress, Content Marketing (premi Invio)">
						
						<div class="fp-seo-expertise-tags" id="fp-seo-expertise-list">
							<?php foreach ( $expertise as $exp ) : ?>
								<span class="fp-seo-expertise-tag">
									<?php echo esc_html( $exp ); ?>
									<button type="button" onclick="this.parentElement.remove()">×</button>
								</span>
							<?php endforeach; ?>
						</div>

						<input type="hidden" name="fp_author_expertise" id="fp_author_expertise" value="<?php echo esc_attr( wp_json_encode( $expertise ) ); ?>">

						<p class="description">
							<?php esc_html_e( 'Aree di competenza (SEO, WordPress, Marketing, ecc.)', 'fp-seo-performance' ); ?>
						</p>

						<script>
						(function() {
							const input = document.getElementById('fp_author_expertise_input');
							const list = document.getElementById('fp-seo-expertise-list');
							const hidden = document.getElementById('fp_author_expertise');

							function updateHidden() {
								const tags = Array.from(list.querySelectorAll('.fp-seo-expertise-tag')).map(el => el.textContent.replace('×', '').trim());
								hidden.value = JSON.stringify(tags);
							}

							input.addEventListener('keypress', function(e) {
								if (e.key === 'Enter') {
									e.preventDefault();
									const value = this.value.trim();
									if (value) {
										const tag = document.createElement('span');
										tag.className = 'fp-seo-expertise-tag';
										tag.innerHTML = value + ' <button type="button" onclick="this.parentElement.remove(); document.getElementById(\'fp_author_expertise\').dispatchEvent(new Event(\'change\'))">×</button>';
										list.appendChild(tag);
										this.value = '';
										updateHidden();
									}
								}
							});

							hidden.addEventListener('change', updateHidden);
						})();
						</script>
					</td>
				</tr>

				<!-- Social Proof -->
				<tr>
					<th>
						<label for="fp_author_followers">
							<?php esc_html_e( 'Social Followers', 'fp-seo-performance' ); ?>
						</label>
					</th>
					<td>
						<input type="number" 
							   name="fp_author_followers" 
							   id="fp_author_followers" 
							   value="<?php echo esc_attr( $followers ); ?>" 
							   min="0" 
							   step="1" 
							   placeholder="15000">
						<p class="description">
							<?php esc_html_e( 'Follower totali su social media (Twitter, LinkedIn, ecc.)', 'fp-seo-performance' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th>
						<label for="fp_author_endorsements">
							<?php esc_html_e( 'Professional Endorsements', 'fp-seo-performance' ); ?>
						</label>
					</th>
					<td>
						<input type="number" 
							   name="fp_author_endorsements" 
							   id="fp_author_endorsements" 
							   value="<?php echo esc_attr( $endorsements ); ?>" 
							   min="0" 
							   step="1" 
							   placeholder="250">
						<p class="description">
							<?php esc_html_e( 'Endorsement su LinkedIn o altre piattaforme professionali', 'fp-seo-performance' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th>
						<label for="fp_author_speaking_engagements">
							<?php esc_html_e( 'Speaking Engagements', 'fp-seo-performance' ); ?>
						</label>
					</th>
					<td>
						<input type="number" 
							   name="fp_author_speaking_engagements" 
							   id="fp_author_speaking_engagements" 
							   value="<?php echo esc_attr( $speaking ); ?>" 
							   min="0" 
							   step="1" 
							   placeholder="25">
						<p class="description">
							<?php esc_html_e( 'Conferenze, webinar, eventi in cui hai parlato', 'fp-seo-performance' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<!-- Preview Authority Score -->
			<div style="margin-top: 20px; padding: 15px; background: white; border-radius: 6px; border: 1px solid #e0f2fe;">
				<h4 style="margin-top: 0;"><?php esc_html_e( '📊 Anteprima Authority Score', 'fp-seo-performance' ); ?></h4>
				<?php $this->render_authority_preview( $user->ID ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render authority score preview
	 *
	 * @param int $user_id User ID.
	 */
	private function render_authority_preview( int $user_id ): void {
		$publications = count_user_posts( $user_id, 'post', true );
		$experience   = (int) get_user_meta( $user_id, 'fp_author_experience_years', true );
		$certs        = get_user_meta( $user_id, 'fp_author_certifications', true );
		$cert_count   = is_array( $certs ) ? count( $certs ) : 0;

		// Calculate preview score
		$score = 0.5; // Base

		if ( $publications > 50 ) {
			$score += 0.2;
		} elseif ( $publications > 20 ) {
			$score += 0.1;
		}

		if ( $cert_count > 0 ) {
			$score += 0.2;
		}

		if ( $experience > 10 ) {
			$score += 0.1;
		}

		$score = min( 1.0, $score );

		$score_class = 'success';
		$score_label = 'Alta';

		if ( $score < 0.7 ) {
			$score_class = 'warning';
			$score_label = 'Media';
		}

		if ( $score < 0.5 ) {
			$score_class = 'error';
			$score_label = 'Bassa';
		}

		?>
		<div style="display: flex; gap: 20px; align-items: center;">
			<div>
				<div style="font-size: 48px; font-weight: bold; color: <?php echo 'success' === $score_class ? '#059669' : ( 'warning' === $score_class ? '#f59e0b' : '#dc2626' ); ?>">
					<?php echo esc_html( number_format( $score * 100, 0 ) ); ?>
				</div>
				<div style="font-size: 12px; color: #64748b; text-transform: uppercase;">
					Authority Score
				</div>
			</div>
			<div style="flex: 1;">
				<p><strong><?php echo esc_html( $score_label ); ?></strong></p>
				<ul style="margin: 10px 0; padding-left: 20px; font-size: 13px; color: #64748b;">
					<li>📝 Pubblicazioni: <?php echo esc_html( $publications ); ?></li>
					<li>⏱️ Esperienza: <?php echo esc_html( $experience ?: 0 ); ?> anni</li>
					<li>🎓 Certificazioni: <?php echo esc_html( $cert_count ); ?></li>
				</ul>
				<?php if ( $score < 0.7 ) : ?>
					<p class="description">
						💡 <strong>Suggerimento:</strong> Aggiungi certificazioni e aumenta gli anni di esperienza per migliorare l'authority score.
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Save profile fields
	 *
	 * @param int $user_id User ID.
	 */
	public function save_fields( int $user_id ): void {
		// Check permissions
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		// Check nonce (WordPress handles this for profile pages)

		// Save simple fields
		if ( isset( $_POST['fp_author_title'] ) ) {
			update_user_meta( $user_id, 'fp_author_title', sanitize_text_field( wp_unslash( $_POST['fp_author_title'] ) ) );
		}

		if ( isset( $_POST['fp_author_experience_years'] ) ) {
			update_user_meta( $user_id, 'fp_author_experience_years', absint( $_POST['fp_author_experience_years'] ) );
		}

		if ( isset( $_POST['fp_author_education'] ) ) {
			update_user_meta( $user_id, 'fp_author_education', sanitize_text_field( wp_unslash( $_POST['fp_author_education'] ) ) );
		}

		if ( isset( $_POST['fp_author_followers'] ) ) {
			update_user_meta( $user_id, 'fp_author_followers', absint( $_POST['fp_author_followers'] ) );
		}

		if ( isset( $_POST['fp_author_endorsements'] ) ) {
			update_user_meta( $user_id, 'fp_author_endorsements', absint( $_POST['fp_author_endorsements'] ) );
		}

		if ( isset( $_POST['fp_author_speaking_engagements'] ) ) {
			update_user_meta( $user_id, 'fp_author_speaking_engagements', absint( $_POST['fp_author_speaking_engagements'] ) );
		}

		// Save arrays (certifications, expertise)
		if ( isset( $_POST['fp_author_certifications'] ) ) {
			$certifications_json = wp_unslash( $_POST['fp_author_certifications'] );
			$certifications      = json_decode( $certifications_json, true );

			if ( is_array( $certifications ) ) {
				$certifications = array_map( 'sanitize_text_field', $certifications );
				update_user_meta( $user_id, 'fp_author_certifications', $certifications );
			}
		}

		if ( isset( $_POST['fp_author_expertise'] ) ) {
			$expertise_json = wp_unslash( $_POST['fp_author_expertise'] );
			$expertise      = json_decode( $expertise_json, true );

			if ( is_array( $expertise ) ) {
				$expertise = array_map( 'sanitize_text_field', $expertise );
				update_user_meta( $user_id, 'fp_author_expertise', $expertise );
			}
		}
	}
}


