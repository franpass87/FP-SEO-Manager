<?php
/**
 * Manages scripts for the Author Profile Fields.
 *
 * @package FP\SEO\Admin\Scripts
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Scripts;

use function get_current_screen;

/**
 * Manages scripts for the Author Profile Fields.
 */
class AuthorProfileFieldsScriptsManager {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_footer', array( $this, 'inject_scripts' ) );
	}

	/**
	 * Inject scripts in admin footer.
	 *
	 * @return void
	 */
	public function inject_scripts(): void {
		$screen = get_current_screen();
		
		if ( ! $screen || ! in_array( $screen->base, array( 'profile', 'user-edit' ), true ) ) {
			return;
		}
		
		$this->render_scripts();
	}

	/**
	 * Render all scripts.
	 *
	 * @return void
	 */
	private function render_scripts(): void {
		?>
		<script>
		(function() {
			const input = document.getElementById('fp_author_certifications_input');
			const list = document.getElementById('fp-seo-certifications-list');
			const hidden = document.getElementById('fp_author_certifications');

			if (!input || !list || !hidden) {
				return;
			}

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
						tag.innerHTML = value + ' <button type="button" onclick="this.parentElement.remove(); document.getElementById(\'fp_author_certifications\').dispatchEvent(new Event(\'change\'))">×</button>';
						list.appendChild(tag);
						this.value = '';
						updateHidden();
					}
				}
			});

			hidden.addEventListener('change', updateHidden);
		})();
		</script>
		<?php
	}
}


