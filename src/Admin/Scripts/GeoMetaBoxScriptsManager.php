<?php
/**
 * Manages scripts for the GEO MetaBox.
 *
 * @package FP\SEO\Admin\Scripts
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Scripts;

use function esc_attr_e;
use function esc_html_e;
use function get_current_screen;

/**
 * Manages scripts for the GEO MetaBox.
 */
class GeoMetaBoxScriptsManager {
	/**
	 * @var int
	 */
	private $initial_claim_index = 0;

	/**
	 * Set initial claim index.
	 *
	 * @param int $count Number of existing claims.
	 * @return void
	 */
	public function set_initial_claim_index( int $count ): void {
		$this->initial_claim_index = $count;
	}

	/**
	 * Get inline JavaScript.
	 *
	 * @param int $claims_count Number of existing claims.
	 * @return string
	 */
	public function get_inline_js( int $claims_count = 0 ): string {
		ob_start();
		$this->render_scripts( $claims_count );
		return ob_get_clean();
	}

	/**
	 * Render all scripts.
	 *
	 * @param int $claims_count Number of existing claims.
	 * @return void
	 */
	private function render_scripts( int $claims_count ): void {
		?>
		<script>
		<?php $this->render_claim_management_scripts( $claims_count ); ?>
		</script>
		<?php
	}

	/**
	 * Render claim management scripts.
	 *
	 * @param int $claims_count Number of existing claims.
	 * @return void
	 */
	private function render_claim_management_scripts( int $claims_count ): void {
		?>
		var fpSeoGeoClaimIndex = <?php echo esc_js( (string) $claims_count ); ?>;

		function fpSeoGeoAddClaim() {
			var template = document.getElementById('fp-seo-geo-claim-template');
			if (!template) {
				return;
			}
			
			var html = template.innerHTML.replace(/{INDEX}/g, fpSeoGeoClaimIndex);
			
			var list = document.getElementById('fp-seo-geo-claims-list');
			if (!list) {
				return;
			}
			
			var div = document.createElement('div');
			div.innerHTML = html;
			list.appendChild(div.firstChild);
			
			fpSeoGeoClaimIndex++;
		}

		function fpSeoGeoRemoveClaim(index) {
			if (confirm('<?php esc_html_e( 'Remove this claim?', 'fp-seo-performance' ); ?>')) {
				var element = document.getElementById('fp-seo-geo-claim-' + index);
				if (element) {
					element.remove();
				}
			}
		}

		function fpSeoGeoAddEvidence(claimIndex) {
			var container = document.getElementById('fp-seo-geo-claim-' + claimIndex + '-evidence');
			if (!container) {
				return;
			}
			
			var evidenceIndex = container.children.length;
			
			var html = '<div class="fp-seo-geo-evidence-item" style="padding: 8px; background: #fff; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 8px;">';
			html += '<input type="url" name="fp_seo_geo_claims[' + claimIndex + '][evidence][' + evidenceIndex + '][url]" placeholder="<?php esc_attr_e( 'Evidence URL', 'fp-seo-performance' ); ?>" class="regular-text" style="width: 100%; margin-bottom: 4px;" />';
			html += '<input type="text" name="fp_seo_geo_claims[' + claimIndex + '][evidence][' + evidenceIndex + '][title]" placeholder="<?php esc_attr_e( 'Evidence Title', 'fp-seo-performance' ); ?>" class="regular-text" style="width: 100%; margin-bottom: 4px;" />';
			html += '<input type="text" name="fp_seo_geo_claims[' + claimIndex + '][evidence][' + evidenceIndex + '][publisher]" placeholder="<?php esc_attr_e( 'Publisher', 'fp-seo-performance' ); ?>" style="width: 32%; margin-right: 1%;" />';
			html += '<input type="text" name="fp_seo_geo_claims[' + claimIndex + '][evidence][' + evidenceIndex + '][author]" placeholder="<?php esc_attr_e( 'Author', 'fp-seo-performance' ); ?>" style="width: 32%; margin-right: 1%;" />';
			html += '<input type="date" name="fp_seo_geo_claims[' + claimIndex + '][evidence][' + evidenceIndex + '][accessed]" placeholder="<?php esc_attr_e( 'Accessed Date', 'fp-seo-performance' ); ?>" style="width: 32%;" />';
			html += '</div>';
			
			container.insertAdjacentHTML('beforeend', html);
		}
		<?php
	}
}

