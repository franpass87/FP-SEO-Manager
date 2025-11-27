<?php
/**
 * File di test e diagnostica per Nectar Slider
 * 
 * Accesso: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/test-nectar-slider-debug.php
 * 
 * Questo file verifica che il plugin FP-SEO-Manager non interferisca con il salvataggio degli slider Nectar.
 */

// Carica WordPress
$wp_load_paths = array(
	dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php', // wp-content/plugins/FP-SEO-Manager -> wp-load.php (4 livelli)
	dirname( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) ) . '/wp-load.php', // 5 livelli (per sicurezza)
	__DIR__ . '/../../../../wp-load.php', // Percorso relativo assoluto
	realpath( __DIR__ . '/../../../../wp-load.php' ), // Percorso reale
);

// Aggiungi anche percorsi relativi
$wp_load_paths[] = __DIR__ . '/../../../wp-load.php';
$wp_load_paths[] = __DIR__ . '/../../../../wp-load.php';

$wp_loaded = false;
foreach ( $wp_load_paths as $path ) {
	if ( file_exists( $path ) ) {
		require_once $path;
		$wp_loaded = true;
		break;
	}
}

if ( ! $wp_loaded ) {
	die( 'ERRORE: Impossibile caricare WordPress. Verifica il percorso.' );
}

// Verifica permessi
if ( ! current_user_can( 'manage_options' ) ) {
	die( 'ERRORE: Accesso negato. Devi essere un amministratore.' );
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Test Nectar Slider - FP SEO Manager</title>
	<style>
		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			max-width: 1200px;
			margin: 20px auto;
			padding: 20px;
			background: #f0f0f1;
		}
		.container {
			background: white;
			padding: 30px;
			border-radius: 8px;
			box-shadow: 0 1px 3px rgba(0,0,0,0.1);
		}
		h1 {
			color: #1d2327;
			border-bottom: 2px solid #2271b1;
			padding-bottom: 10px;
		}
		h2 {
			color: #2271b1;
			margin-top: 30px;
			border-left: 4px solid #2271b1;
			padding-left: 15px;
		}
		.test-section {
			background: #f6f7f7;
			padding: 20px;
			margin: 20px 0;
			border-radius: 4px;
			border-left: 4px solid #2271b1;
		}
		.success {
			color: #00a32a;
			font-weight: bold;
		}
		.error {
			color: #d63638;
			font-weight: bold;
		}
		.warning {
			color: #dba617;
			font-weight: bold;
		}
		.info {
			color: #2271b1;
		}
		pre {
			background: #1d2327;
			color: #f0f0f1;
			padding: 15px;
			border-radius: 4px;
			overflow-x: auto;
			font-size: 13px;
			line-height: 1.5;
		}
		table {
			width: 100%;
			border-collapse: collapse;
			margin: 15px 0;
		}
		table th, table td {
			padding: 10px;
			text-align: left;
			border-bottom: 1px solid #ddd;
		}
		table th {
			background: #f6f7f7;
			font-weight: 600;
		}
		.badge {
			display: inline-block;
			padding: 3px 8px;
			border-radius: 3px;
			font-size: 12px;
			font-weight: 600;
		}
		.badge-success {
			background: #00a32a;
			color: white;
		}
		.badge-error {
			background: #d63638;
			color: white;
		}
		.badge-warning {
			background: #dba617;
			color: white;
		}
		.badge-info {
			background: #2271b1;
			color: white;
		}
	</style>
</head>
<body>
	<div class="container">
		<h1>🔍 Test Diagnostico - Nectar Slider & FP SEO Manager</h1>
		<p><strong>Versione Plugin:</strong> <?php echo defined( 'FP_SEO_PERFORMANCE_VERSION' ) ? FP_SEO_PERFORMANCE_VERSION : 'Non definita'; ?></p>
		<p><strong>Data Test:</strong> <?php echo date( 'Y-m-d H:i:s' ); ?></p>

		<?php
		// ============================================
		// TEST 1: Verifica caricamento plugin
		// ============================================
		?>
		<div class="test-section">
			<h2>1. Verifica Caricamento Plugin</h2>
			<?php
			$plugin_loaded = defined( 'FP_SEO_PERFORMANCE_FILE' );
			$plugin_active = is_plugin_active( 'FP-SEO-Manager/fp-seo-performance.php' );
			?>
			<p>
				<strong>Plugin File Definito:</strong> 
				<span class="<?php echo $plugin_loaded ? 'success' : 'error'; ?>">
					<?php echo $plugin_loaded ? '✅ Sì' : '❌ No'; ?>
				</span>
			</p>
			<p>
				<strong>Plugin Attivo:</strong> 
				<span class="<?php echo $plugin_active ? 'success' : 'error'; ?>">
					<?php echo $plugin_active ? '✅ Sì' : '❌ No'; ?>
				</span>
			</p>
			<?php if ( $plugin_loaded ): ?>
				<p><strong>Percorso Plugin:</strong> <code><?php echo FP_SEO_PERFORMANCE_FILE; ?></code></p>
			<?php endif; ?>
		</div>

		<?php
		// ============================================
		// TEST 2: Verifica blocco su pagine Nectar Slider
		// ============================================
		?>
		<div class="test-section">
			<h2>2. Verifica Blocco su Pagine Nectar Slider</h2>
			<?php
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
			$is_post_edit = ( strpos( $request_uri, 'post.php' ) !== false || strpos( $request_uri, 'post-new.php' ) !== false );
			$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
			$post_type_param = isset( $_GET['post_type'] ) ? $_GET['post_type'] : '';
			
			// Simula il controllo del plugin
			global $wpdb;
			$detected_post_type = '';
			$is_nectar_slider = false;
			
			if ( $post_id > 0 ) {
				$detected_post_type = $wpdb->get_var( $wpdb->prepare(
					"SELECT post_type FROM {$wpdb->posts} WHERE ID = %d LIMIT 1",
					$post_id
				) );
				if ( $detected_post_type === 'nectar_slider' ) {
					$is_nectar_slider = true;
				}
			} elseif ( $post_type_param === 'nectar_slider' ) {
				$detected_post_type = 'nectar_slider';
				$is_nectar_slider = true;
			}
			
			// Verifica se il plugin dovrebbe essere bloccato
			$should_block = $is_nectar_slider;
			?>
			<p><strong>URI Richiesta:</strong> <code><?php echo esc_html( $request_uri ); ?></code></p>
			<p><strong>È Pagina Edit Post:</strong> <span class="<?php echo $is_post_edit ? 'info' : 'warning'; ?>"><?php echo $is_post_edit ? 'Sì' : 'No'; ?></span></p>
			<?php if ( $post_id > 0 ): ?>
				<p><strong>Post ID:</strong> <?php echo $post_id; ?></p>
				<p><strong>Post Type Rilevato:</strong> <code><?php echo esc_html( $detected_post_type ?: 'N/A' ); ?></code></p>
			<?php endif; ?>
			<?php if ( $post_type_param ): ?>
				<p><strong>Post Type da GET:</strong> <code><?php echo esc_html( $post_type_param ); ?></code></p>
			<?php endif; ?>
			<p>
				<strong>Plugin Dovrebbe Essere Bloccato:</strong> 
				<span class="<?php echo $should_block ? 'success' : 'warning'; ?>">
					<?php echo $should_block ? '✅ Sì (corretto)' : '⚠️ No (normale se non sei su una pagina slider)'; ?>
				</span>
			</p>
		</div>

		<?php
		// ============================================
		// TEST 3: Verifica Post Types Supportati
		// ============================================
		?>
		<div class="test-section">
			<h2>3. Post Types Supportati dal Plugin</h2>
			<?php
			if ( class_exists( '\FP\SEO\Utils\PostTypes' ) ) {
				$supported_types = \FP\SEO\Utils\PostTypes::analyzable();
				?>
				<p><strong>Numero Post Types Supportati:</strong> <?php echo count( $supported_types ); ?></p>
				<table>
					<thead>
						<tr>
							<th>Post Type</th>
							<th>Supportato</th>
							<th>Nectar Slider?</th>
						</tr>
					</thead>
					<tbody>
						<?php
						$nectar_in_supported = in_array( 'nectar_slider', $supported_types, true );
						foreach ( $supported_types as $type ): ?>
							<tr>
								<td><code><?php echo esc_html( $type ); ?></code></td>
								<td><span class="badge badge-success">Sì</span></td>
								<td>
									<?php if ( $type === 'nectar_slider' ): ?>
										<span class="badge badge-error">⚠️ PROBLEMA!</span>
									<?php else: ?>
										<span class="badge badge-info">No</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( $nectar_in_supported ): ?>
					<p class="error">❌ <strong>PROBLEMA RILEVATO:</strong> <code>nectar_slider</code> è nei post types supportati! Dovrebbe essere rimosso.</p>
				<?php else: ?>
					<p class="success">✅ <strong>OK:</strong> <code>nectar_slider</code> NON è nei post types supportati. Corretto!</p>
				<?php endif; ?>
				<?php
			} else {
				?>
				<p class="error">❌ Classe <code>FP\SEO\Utils\PostTypes</code> non trovata.</p>
				<?php
			}
			?>
		</div>

		<?php
		// ============================================
		// TEST 4: Verifica Hook Registrati
		// ============================================
		?>
		<div class="test-section">
			<h2>4. Hook Registrati per save_post</h2>
			<?php
			global $wp_filter;
			$save_post_hooks = isset( $wp_filter['save_post'] ) ? $wp_filter['save_post'] : null;
			$save_post_nectar_hooks = isset( $wp_filter['save_post_nectar_slider'] ) ? $wp_filter['save_post_nectar_slider'] : null;
			
			// Cerca hook del plugin FP SEO
			$fp_seo_hooks = array();
			if ( $save_post_hooks ) {
				foreach ( $save_post_hooks->callbacks as $priority => $callbacks ) {
					foreach ( $callbacks as $callback ) {
						if ( is_array( $callback['function'] ) && is_object( $callback['function'][0] ) ) {
							$class = get_class( $callback['function'][0] );
							if ( strpos( $class, 'FP\\SEO' ) !== false || strpos( $class, 'FP_SEO' ) !== false ) {
								$fp_seo_hooks[] = array(
									'priority' => $priority,
									'class' => $class,
									'method' => is_array( $callback['function'] ) ? $callback['function'][1] : 'closure',
								);
							}
						} elseif ( is_string( $callback['function'] ) && strpos( $callback['function'], 'fp_seo' ) !== false ) {
							$fp_seo_hooks[] = array(
								'priority' => $priority,
								'function' => $callback['function'],
							);
						}
					}
				}
			}
			?>
			<p><strong>Hook Generici save_post del Plugin:</strong></p>
			<?php if ( empty( $fp_seo_hooks ) ): ?>
				<p class="success">✅ <strong>OK:</strong> Nessun hook generico <code>save_post</code> trovato per il plugin FP SEO. Corretto!</p>
			<?php else: ?>
				<p class="error">❌ <strong>PROBLEMA RILEVATO:</strong> Trovati <?php echo count( $fp_seo_hooks ); ?> hook generici <code>save_post</code> del plugin:</p>
				<table>
					<thead>
						<tr>
							<th>Priorità</th>
							<th>Classe/Funzione</th>
							<th>Metodo</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $fp_seo_hooks as $hook ): ?>
							<tr>
								<td><?php echo $hook['priority']; ?></td>
								<td><code><?php echo esc_html( $hook['class'] ?? $hook['function'] ?? 'N/A' ); ?></code></td>
								<td><code><?php echo esc_html( $hook['method'] ?? 'N/A' ); ?></code></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
			
			<p><strong>Hook Specifici save_post_nectar_slider del Plugin:</strong></p>
			<?php
			$fp_seo_nectar_hooks = array();
			if ( $save_post_nectar_hooks ) {
				foreach ( $save_post_nectar_hooks->callbacks as $priority => $callbacks ) {
					foreach ( $callbacks as $callback ) {
						if ( is_array( $callback['function'] ) && is_object( $callback['function'][0] ) ) {
							$class = get_class( $callback['function'][0] );
							if ( strpos( $class, 'FP\\SEO' ) !== false || strpos( $class, 'FP_SEO' ) !== false ) {
								$fp_seo_nectar_hooks[] = array(
									'priority' => $priority,
									'class' => $class,
									'method' => is_array( $callback['function'] ) ? $callback['function'][1] : 'closure',
								);
							}
						}
					}
				}
			}
			?>
			<?php if ( empty( $fp_seo_nectar_hooks ) ): ?>
				<p class="success">✅ <strong>OK:</strong> Nessun hook specifico <code>save_post_nectar_slider</code> trovato per il plugin FP SEO. Corretto!</p>
			<?php else: ?>
				<p class="error">❌ <strong>PROBLEMA RILEVATO:</strong> Trovati <?php echo count( $fp_seo_nectar_hooks ); ?> hook specifici <code>save_post_nectar_slider</code> del plugin:</p>
				<table>
					<thead>
						<tr>
							<th>Priorità</th>
							<th>Classe</th>
							<th>Metodo</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $fp_seo_nectar_hooks as $hook ): ?>
							<tr>
								<td><?php echo $hook['priority']; ?></td>
								<td><code><?php echo esc_html( $hook['class'] ); ?></code></td>
								<td><code><?php echo esc_html( $hook['method'] ); ?></code></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<?php
		// ============================================
		// TEST 5: Test Salvataggio Meta (Simulato)
		// ============================================
		?>
		<div class="test-section">
			<h2>5. Test Salvataggio Meta (Simulato)</h2>
			<?php
			// Trova uno slider Nectar esistente
			$test_slider = $wpdb->get_row( $wpdb->prepare(
				"SELECT ID, post_title, post_type FROM {$wpdb->posts} WHERE post_type = %s AND post_status != 'trash' LIMIT 1",
				'nectar_slider'
			) );
			
			if ( $test_slider ) {
				?>
				<p><strong>Slider di Test Trovato:</strong></p>
				<ul>
					<li><strong>ID:</strong> <?php echo $test_slider->ID; ?></li>
					<li><strong>Titolo:</strong> <?php echo esc_html( $test_slider->post_title ); ?></li>
					<li><strong>Post Type:</strong> <code><?php echo esc_html( $test_slider->post_type ); ?></code></li>
				</ul>
				
				<?php
				// Verifica se ci sono meta del plugin FP SEO su questo slider
				$fp_seo_meta = $wpdb->get_results( $wpdb->prepare(
					"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s",
					$test_slider->ID,
					'_fp_seo_%'
				) );
				
				if ( empty( $fp_seo_meta ) ) {
					?>
					<p class="success">✅ <strong>OK:</strong> Nessuna meta del plugin FP SEO trovata su questo slider. Corretto!</p>
					<?php
				} else {
					?>
					<p class="error">❌ <strong>PROBLEMA RILEVATO:</strong> Trovate <?php echo count( $fp_seo_meta ); ?> meta del plugin FP SEO su questo slider:</p>
					<table>
						<thead>
							<tr>
								<th>Meta Key</th>
								<th>Meta Value (primi 100 caratteri)</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $fp_seo_meta as $meta ): ?>
								<tr>
									<td><code><?php echo esc_html( $meta->meta_key ); ?></code></td>
									<td><code><?php echo esc_html( substr( $meta->meta_value, 0, 100 ) ); ?></code></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<?php
				}
				
				// Verifica meta Nectar Slider
				$nectar_meta = $wpdb->get_var( $wpdb->prepare(
					"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1",
					$test_slider->ID,
					'_nectar_slider_image'
				) );
				?>
				<p><strong>Meta Nectar Slider Image:</strong> 
					<?php if ( $nectar_meta ): ?>
						<span class="success">✅ Presente</span> (<code><?php echo esc_html( substr( $nectar_meta, 0, 80 ) ); ?>...</code>)
					<?php else: ?>
						<span class="warning">⚠️ Non presente</span>
					<?php endif; ?>
				</p>
				<?php
			} else {
				?>
				<p class="warning">⚠️ Nessuno slider Nectar trovato nel database per il test.</p>
				<?php
			}
			?>
		</div>

		<?php
		// ============================================
		// TEST 6: Verifica Classi del Plugin
		// ============================================
		?>
		<div class="test-section">
			<h2>6. Verifica Classi del Plugin</h2>
			<?php
			$classes_to_check = array(
				'FP\\SEO\\Editor\\Metabox',
				'FP\\SEO\\Social\\ImprovedSocialMediaManager',
				'FP\\SEO\\Social\\SocialMediaManager',
				'FP\\SEO\\Editor\\SchemaMetaboxes',
				'FP\\SEO\\Keywords\\MultipleKeywordsManager',
				'FP\\SEO\\Admin\\GeoMetaBox',
				'FP\\SEO\\Admin\\FreshnessMetaBox',
				'FP\\SEO\\Automation\\AutoSeoOptimizer',
				'FP\\SEO\\Integrations\\AutoGenerationHook',
			);
			?>
			<table>
				<thead>
					<tr>
						<th>Classe</th>
						<th>Stato</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $classes_to_check as $class ): ?>
						<tr>
							<td><code><?php echo esc_html( $class ); ?></code></td>
							<td>
								<?php if ( class_exists( $class ) ): ?>
									<span class="badge badge-success">Caricata</span>
								<?php else: ?>
									<span class="badge badge-warning">Non caricata</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php
		// ============================================
		// RIEPILOGO FINALE
		// ============================================
		?>
		<div class="test-section">
			<h2>📊 Riepilogo</h2>
			<?php
			$issues = array();
			
			if ( ! $plugin_loaded ) {
				$issues[] = 'Plugin non caricato';
			}
			
			if ( isset( $nectar_in_supported ) && $nectar_in_supported ) {
				$issues[] = 'nectar_slider è nei post types supportati';
			}
			
			if ( ! empty( $fp_seo_hooks ) ) {
				$issues[] = 'Hook generici save_post trovati';
			}
			
			if ( ! empty( $fp_seo_nectar_hooks ) ) {
				$issues[] = 'Hook specifici save_post_nectar_slider trovati';
			}
			
			if ( isset( $fp_seo_meta ) && ! empty( $fp_seo_meta ) ) {
				$issues[] = 'Meta del plugin FP SEO trovate su slider Nectar';
			}
			
			if ( empty( $issues ) ) {
				?>
				<p class="success" style="font-size: 18px; padding: 15px; background: #d4edda; border-left: 4px solid #00a32a;">
					✅ <strong>TUTTO OK!</strong> Nessun problema rilevato. Il plugin non dovrebbe interferire con gli slider Nectar.
				</p>
				<?php
			} else {
				?>
				<p class="error" style="font-size: 18px; padding: 15px; background: #f8d7da; border-left: 4px solid #d63638;">
					❌ <strong>PROBLEMI RILEVATI:</strong>
				</p>
				<ul>
					<?php foreach ( $issues as $issue ): ?>
						<li class="error"><?php echo esc_html( $issue ); ?></li>
					<?php endforeach; ?>
				</ul>
				<?php
			}
			?>
		</div>

		<div class="test-section">
			<h2>🔧 Azioni Consigliate</h2>
			<ul>
				<li>Se ci sono hook generici <code>save_post</code>, rimuoverli e usare solo hook specifici per post type</li>
				<li>Se <code>nectar_slider</code> è nei post types supportati, rimuoverlo da <code>PostTypes::analyzable()</code></li>
				<li>Se ci sono meta del plugin su slider Nectar, rimuoverle manualmente dal database</li>
				<li>Verificare che il blocco globale in <code>fp-seo-performance.php</code> funzioni correttamente</li>
			</ul>
		</div>

		<p style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px;">
			<strong>Nota:</strong> Questo file di test è solo per diagnostica. Dopo aver risolto i problemi, puoi eliminarlo.
		</p>
	</div>
</body>
</html>

