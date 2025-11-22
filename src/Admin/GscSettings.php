<?php
/**
 * Google Search Console Settings Tab
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin;

use FP\SEO\Integrations\GscClient;
use FP\SEO\Integrations\GscData;
use FP\SEO\Utils\SiteKitIntegration;

/**
 * Renders GSC settings tab
 */
class GscSettings {

	/**
	 * Register hooks
	 */
	public function register(): void {
		add_filter( 'fpseo_settings_tabs', array( $this, 'add_gsc_tab' ) );
		add_action( 'fpseo_settings_render_tab_gsc', array( $this, 'render' ) );
		add_action( 'wp_ajax_fp_seo_gsc_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_fp_seo_gsc_flush_cache', array( $this, 'ajax_flush_cache' ) );
	}

	/**
	 * Add GSC tab to settings
	 *
	 * @param array<string,string> $tabs Existing tabs.
	 * @return array<string,string>
	 */
	public function add_gsc_tab( array $tabs ): array {
		$tabs['gsc'] = __( 'Google Search Console', 'fp-seo-performance' );
		return $tabs;
	}

	/**
	 * Render GSC settings tab
	 */
	public function render(): void {
		$options = get_option( 'fp_seo_performance', array() );
		$gsc     = $options['gsc'] ?? array();

		// Check if Site Kit is available and pre-fill if not already configured
		$sitekit_active = SiteKitIntegration::is_site_kit_active();
		$sitekit_gsc_connected = SiteKitIntegration::is_gsc_connected();
		
		$service_account_json = $gsc['service_account_json'] ?? '';
		$site_url             = $gsc['site_url'] ?? '';
		
		// If not configured, try to get from Site Kit
		if ( empty( $site_url ) && $sitekit_active && $sitekit_gsc_connected ) {
			$sitekit_credentials = SiteKitIntegration::get_gsc_credentials();
			if ( ! empty( $sitekit_credentials['site_url'] ) ) {
				$site_url = $sitekit_credentials['site_url'];
			}
		}
		
		// Fallback to home URL if still empty
		if ( empty( $site_url ) ) {
			$site_url = home_url( '/' );
		}
		
		$is_configured = ! empty( $service_account_json ) && ! empty( $site_url );

		?>
		<div class="fp-seo-settings-section">
			<h3 class="fp-seo-settings-section__title">📊 <?php esc_html_e( 'Google Search Console Integration', 'fp-seo-performance' ); ?></h3>
			<p class="fp-seo-settings-section__description">
				<?php esc_html_e( 'Connect your Google Search Console account using a Service Account to display clicks, impressions, CTR, and position data.', 'fp-seo-performance' ); ?>
			</p>

			<?php if ( $is_configured ) : ?>
				<div class="fp-seo-alert fp-seo-alert--success">
					✅ <?php esc_html_e( 'Google Search Console is configured!', 'fp-seo-performance' ); ?>
					<button type="button" class="button button-small" onclick="fpSeoGscTestConnection()" style="margin-left: 10px;">
						<?php esc_html_e( 'Test Connection', 'fp-seo-performance' ); ?>
					</button>
				</div>
			<?php else : ?>
				<?php if ( $sitekit_active && $sitekit_gsc_connected ) : ?>
					<div class="fp-seo-alert fp-seo-alert--info" style="background: #dbeafe; border-left-color: #2563eb; color: #1e40af;">
						ℹ️ <?php esc_html_e( 'Google Site Kit detected! Site URL pre-filled from Site Kit configuration. You still need to add Service Account JSON for full functionality.', 'fp-seo-performance' ); ?>
					</div>
				<?php else : ?>
					<div class="fp-seo-alert fp-seo-alert--warning">
						⚠️ <?php esc_html_e( 'Google Search Console not configured. Follow the setup guide below.', 'fp-seo-performance' ); ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<table class="form-table" role="presentation">
				<!-- Site URL -->
				<tr>
					<th scope="row">
						<label for="fp_seo_gsc_site_url"><?php esc_html_e( 'Site URL', 'fp-seo-performance' ); ?></label>
					</th>
					<td>
						<input type="url" 
							   id="fp_seo_gsc_site_url" 
							   name="fp_seo_performance[gsc][site_url]"
							   value="<?php echo esc_attr( $site_url ); ?>"
							   class="regular-text" 
							   placeholder="https://example.com/" />
						<p class="description">
							<?php esc_html_e( 'The exact URL as registered in Google Search Console (with or without trailing slash)', 'fp-seo-performance' ); ?>
						</p>
					</td>
				</tr>

				<!-- Service Account JSON -->
				<tr>
					<th scope="row">
						<label for="fp_seo_gsc_service_account"><?php esc_html_e( 'Service Account JSON', 'fp-seo-performance' ); ?></label>
					</th>
					<td>
						<textarea id="fp_seo_gsc_service_account" 
								  name="fp_seo_performance[gsc][service_account_json]"
								  rows="10" 
								  class="large-text code"
								  placeholder='{"type":"service_account","project_id":"...","private_key":"...","client_email":"..."}'><?php echo esc_textarea( $service_account_json ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'Paste the entire JSON key file content from Google Cloud Console', 'fp-seo-performance' ); ?>
						</p>
					</td>
				</tr>

				<!-- Enable GSC -->
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable GSC Data', 'fp-seo-performance' ); ?></th>
					<td>
						<label>
							<input type="checkbox" 
								   name="fp_seo_performance[gsc][enabled]" 
								   value="1"
								   <?php checked( ! empty( $gsc['enabled'] ) ); ?> />
							<?php esc_html_e( 'Show GSC metrics in Dashboard and post editor', 'fp-seo-performance' ); ?>
						</label>
					</td>
				</tr>

				<!-- Auto Indexing -->
				<tr>
					<th scope="row"><?php esc_html_e( 'Instant Indexing', 'fp-seo-performance' ); ?></th>
					<td>
						<label>
							<input type="checkbox" 
								   name="fp_seo_performance[gsc][auto_indexing]" 
								   value="1"
								   <?php checked( ! empty( $gsc['auto_indexing'] ) ); ?> />
							<strong><?php esc_html_e( 'Auto-submit to Google on publish', 'fp-seo-performance' ); ?></strong>
						</label>
						<p class="description">
							<?php esc_html_e( 'Automatically submit URLs to Google Indexing API when posts are published or updated. Requires Indexing API enabled in Google Cloud.', 'fp-seo-performance' ); ?>
						</p>
						<p class="fp-seo-help-text" style="background: #dbeafe; border-left: 3px solid #2563eb; padding: 10px; border-radius: 4px; margin-top: 8px;">
							💡 <strong><?php esc_html_e( 'Important:', 'fp-seo-performance' ); ?></strong> <?php esc_html_e( 'You must enable "Web Search Indexing API" in Google Cloud Console → APIs & Services → Library. Search for "Indexing" or "Web Search Indexing" and enable it (in addition to Search Console API).', 'fp-seo-performance' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Setup Guide -->
		<div class="fp-seo-settings-section">
			<h3 class="fp-seo-settings-section__title">🔧 <?php esc_html_e( 'Setup Guide', 'fp-seo-performance' ); ?></h3>
			
			<div class="fp-seo-setup-steps">
				<h4>Step 1: Create Service Account</h4>
				<ol>
					<li>Vai su <a href="https://console.cloud.google.com" target="_blank">Google Cloud Console</a></li>
					<li>Crea un nuovo progetto o seleziona uno esistente</li>
					<li>Abilita <strong>Google Search Console API</strong></li>
					<li>Vai su <strong>IAM & Admin → Service Accounts</strong></li>
					<li>Click <strong>Create Service Account</strong></li>
					<li>Nome: <code>fp-seo-gsc</code>, Role: <strong>Service Account User</strong></li>
					<li>Click <strong>Create and Continue</strong> → <strong>Done</strong></li>
				</ol>

				<h4>Step 2: Generate JSON Key</h4>
				<ol>
					<li>Click sul service account creato</li>
					<li>Tab <strong>Keys</strong> → <strong>Add Key</strong> → <strong>Create new key</strong></li>
					<li>Seleziona <strong>JSON</strong> → <strong>Create</strong></li>
					<li>Il file JSON verrà scaricato automaticamente</li>
					<li>Apri il file con un editor di testo</li>
					<li>Copia <strong>TUTTO</strong> il contenuto</li>
					<li>Incolla nel campo <strong>Service Account JSON</strong> sopra</li>
				</ol>

				<h4>Step 3: Add Service Account to GSC</h4>
				<ol>
					<li>Apri il file JSON e copia il valore di <code>"client_email"</code></li>
					<li>Esempio: <code>fp-seo-gsc@project-id.iam.gserviceaccount.com</code></li>
					<li>Vai su <a href="https://search.google.com/search-console" target="_blank">Google Search Console</a></li>
					<li>Seleziona la tua property</li>
					<li><strong>Settings</strong> → <strong>Users and permissions</strong></li>
					<li><strong>Add user</strong></li>
					<li>Email: incolla il <code>client_email</code> dal JSON</li>
					<li>Permission: <strong>Full</strong> (o almeno <strong>Owner/Viewer</strong>)</li>
					<li>Click <strong>Add</strong></li>
				</ol>

				<h4>Step 4: Test & Activate</h4>
				<ol>
					<li>Completa i campi sopra (Site URL + JSON)</li>
					<li>✅ Abilita "Enable GSC Data"</li>
					<li>Click <strong>Save Changes</strong></li>
					<li>Click <strong>Test Connection</strong></li>
					<li>Se vedi ✅ "Connection successful" → Tutto OK!</li>
				</ol>
			</div>
		</div>

		<!-- Actions -->
		<div class="fp-seo-settings-section">
			<h3 class="fp-seo-settings-section__title">🔧 <?php esc_html_e( 'Actions', 'fp-seo-performance' ); ?></h3>
			
			<p>
				<button type="button" class="button" onclick="fpSeoGscTestConnection()">
					🔌 <?php esc_html_e( 'Test Connection', 'fp-seo-performance' ); ?>
				</button>

				<button type="button" class="button" onclick="fpSeoGscFlushCache()">
					🔄 <?php esc_html_e( 'Flush GSC Cache', 'fp-seo-performance' ); ?>
				</button>
			</p>

			<div id="fp-seo-gsc-message" style="margin-top: 15px;"></div>
		</div>

		<script>
		function fpSeoGscTestConnection() {
			const messageDiv = document.getElementById('fp-seo-gsc-message');
			messageDiv.innerHTML = '<div class="notice notice-info"><p>⏳ Testing connection...</p></div>';

			jQuery.post(ajaxurl, {
				action: 'fp_seo_gsc_test_connection',
				nonce: '<?php echo esc_js( wp_create_nonce( 'fp_seo_gsc_test' ) ); ?>'
			}, function(response) {
				if (response.success) {
					messageDiv.innerHTML = '<div class="notice notice-success"><p>✅ ' + response.data.message + '</p></div>';
				} else {
					messageDiv.innerHTML = '<div class="notice notice-error"><p>❌ ' + response.data.message + '</p></div>';
				}
			});
		}

		function fpSeoGscFlushCache() {
			if (!confirm('<?php esc_html_e( 'Flush all GSC cached data?', 'fp-seo-performance' ); ?>')) {
				return;
			}

			jQuery.post(ajaxurl, {
				action: 'fp_seo_gsc_flush_cache',
				nonce: '<?php echo esc_js( wp_create_nonce( 'fp_seo_gsc_flush' ) ); ?>'
			}, function(response) {
				if (response.success) {
					alert('✅ Cache flushed!');
				}
			});
		}
		</script>

		<style>
		.fp-seo-setup-steps h4 {
			background: #f9fafb;
			padding: 10px 15px;
			border-left: 4px solid #2563eb;
			margin-top: 20px;
		}
		.fp-seo-setup-steps ol {
			margin-left: 20px;
		}
		.fp-seo-setup-steps li {
			margin-bottom: 8px;
			line-height: 1.6;
		}
		.fp-seo-setup-steps code {
			background: #f3f4f6;
			padding: 2px 6px;
			border-radius: 3px;
			font-size: 13px;
			color: #dc2626;
		}
		.fp-seo-alert {
			padding: 12px 16px;
			border-radius: 6px;
			border-left: 4px solid;
			margin: 16px 0;
		}
		.fp-seo-alert--success {
			background: #d1fae5;
			border-left-color: #059669;
			color: #065f46;
		}
		.fp-seo-alert--warning {
			background: #fef3c7;
			border-left-color: #f59e0b;
			color: #92400e;
		}
		.fp-seo-alert--info {
			background: #dbeafe;
			border-left-color: #2563eb;
			color: #1e40af;
		}
		</style>
		<?php
	}

	/**
	 * AJAX: Test GSC connection
	 */
	public function ajax_test_connection(): void {
		check_ajax_referer( 'fp_seo_gsc_test', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'fp-seo-performance' ) ) );
		}

		$client = new GscClient();
		$success = $client->test_connection();

		if ( $success ) {
			wp_send_json_success( array( 'message' => __( 'Connection successful! GSC data is accessible.', 'fp-seo-performance' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Connection failed. Check your Service Account JSON and Site URL.', 'fp-seo-performance' ) ) );
		}
	}

	/**
	 * AJAX: Flush GSC cache
	 */
	public function ajax_flush_cache(): void {
		check_ajax_referer( 'fp_seo_gsc_flush', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		GscData::flush_cache();

		wp_send_json_success( array( 'message' => 'Cache flushed' ) );
	}
}

