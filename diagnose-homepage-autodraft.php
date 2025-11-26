<?php
/**
 * Script diagnostico per il problema auto-draft sulla homepage
 * 
 * Esegui questo script in produzione per capire cosa sta succedendo:
 * php diagnose-homepage-autodraft.php
 * 
 * Oppure accedi via browser: /wp-content/plugins/FP-SEO-Manager/diagnose-homepage-autodraft.php
 */

// Carica WordPress
require_once __DIR__ . '/../../../wp-load.php';

// Verifica permessi
if ( ! current_user_can( 'manage_options' ) ) {
	die( 'Accesso negato. Devi essere un amministratore.' );
}

header( 'Content-Type: text/html; charset=utf-8' );
?>
<!DOCTYPE html>
<html>
<head>
	<title>Diagnostica Homepage Auto-Draft</title>
	<style>
		body { font-family: monospace; padding: 20px; background: #f5f5f5; }
		.section { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #0073aa; }
		.error { color: #d63638; }
		.success { color: #00a32a; }
		.warning { color: #dba617; }
		.info { color: #2271b1; }
		pre { background: #f0f0f0; padding: 10px; overflow-x: auto; }
		table { width: 100%; border-collapse: collapse; }
		td, th { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
		th { background: #f0f0f0; }
	</style>
</head>
<body>
	<h1>🔍 Diagnostica Homepage Auto-Draft</h1>
	
	<?php
	$page_on_front_id = (int) get_option( 'page_on_front' );
	
	if ( $page_on_front_id === 0 ) {
		echo '<div class="section error"><strong>❌ ERRORE:</strong> Nessuna homepage configurata (page_on_front = 0)</div>';
		exit;
	}
	
	echo '<div class="section info"><strong>ℹ️ Homepage ID:</strong> ' . esc_html( $page_on_front_id ) . '</div>';
	
	// 1. Verifica status attuale
	global $wpdb;
	$current_status = $wpdb->get_var( $wpdb->prepare(
		"SELECT post_status FROM {$wpdb->posts} WHERE ID = %d",
		$page_on_front_id
	) );
	
	$post = get_post( $page_on_front_id );
	
	echo '<div class="section">';
	echo '<h2>1. Status Attuale</h2>';
	echo '<table>';
	echo '<tr><th>Fonte</th><th>Status</th><th>Note</th></tr>';
	echo '<tr><td>Database diretto</td><td><strong>' . esc_html( $current_status ) . '</strong></td><td>' . ( $current_status === 'auto-draft' ? '<span class="error">⚠️ PROBLEMA!</span>' : '<span class="success">✓ OK</span>' ) . '</td></tr>';
	if ( $post ) {
		echo '<tr><td>get_post()</td><td>' . esc_html( $post->post_status ) . '</td><td>' . ( $post->post_status === 'auto-draft' ? '<span class="error">⚠️ PROBLEMA!</span>' : '<span class="success">✓ OK</span>' ) . '</td></tr>';
		echo '<tr><td>post_date</td><td>' . esc_html( $post->post_date ) . '</td><td>' . ( $post->post_date === '0000-00-00 00:00:00' ? '<span class="warning">⚠️ Data non valida</span>' : '<span class="success">✓ OK</span>' ) . '</td></tr>';
	}
	echo '</table>';
	echo '</div>';
	
	// 2. Verifica hook registrati
	echo '<div class="section">';
	echo '<h2>2. Hook Registrati per SEO Manager</h2>';
	
	global $wp_filter;
	$seo_hooks = array();
	
	$hook_names = array( 'save_post', 'edit_post', 'wp_insert_post', 'wp_insert_post_data', 'transition_post_status', 'shutdown' );
	
	foreach ( $hook_names as $hook_name ) {
		if ( isset( $wp_filter[ $hook_name ] ) ) {
			foreach ( $wp_filter[ $hook_name ]->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $callback ) {
					if ( is_array( $callback['function'] ) && is_object( $callback['function'][0] ) ) {
						$class_name = get_class( $callback['function'][0] );
						if ( strpos( $class_name, 'FP\\SEO' ) !== false || strpos( $class_name, 'Metabox' ) !== false ) {
							$seo_hooks[] = array(
								'hook' => $hook_name,
								'priority' => $priority,
								'class' => $class_name,
								'method' => $callback['function'][1] ?? 'unknown',
							);
						}
					}
				}
			}
		}
	}
	
	if ( empty( $seo_hooks ) ) {
		echo '<p class="warning">⚠️ Nessun hook SEO Manager trovato!</p>';
	} else {
		echo '<table>';
		echo '<tr><th>Hook</th><th>Priorità</th><th>Classe</th><th>Metodo</th></tr>';
		foreach ( $seo_hooks as $hook ) {
			echo '<tr>';
			echo '<td>' . esc_html( $hook['hook'] ) . '</td>';
			echo '<td>' . esc_html( $hook['priority'] ) . '</td>';
			echo '<td>' . esc_html( $hook['class'] ) . '</td>';
			echo '<td>' . esc_html( $hook['method'] ) . '</td>';
			echo '</tr>';
		}
		echo '</table>';
	}
	echo '</div>';
	
	// 3. Verifica meta fields SEO
	echo '<div class="section">';
	echo '<h2>3. Meta Fields SEO Presenti</h2>';
	
	$meta_keys = array(
		'_fp_seo_title',
		'_fp_seo_meta_description',
		'_fp_seo_focus_keyword',
		'_fp_seo_secondary_keywords',
	);
	
	$has_seo_meta = false;
	echo '<table>';
	echo '<tr><th>Meta Key</th><th>Valore</th><th>Presente</th></tr>';
	foreach ( $meta_keys as $meta_key ) {
		$value = get_post_meta( $page_on_front_id, $meta_key, true );
		$present = ! empty( $value );
		if ( $present ) {
			$has_seo_meta = true;
		}
		echo '<tr>';
		echo '<td>' . esc_html( $meta_key ) . '</td>';
		echo '<td>' . esc_html( substr( (string) $value, 0, 50 ) ) . ( strlen( (string) $value ) > 50 ? '...' : '' ) . '</td>';
		echo '<td>' . ( $present ? '<span class="success">✓ Sì</span>' : '<span class="warning">✗ No</span>' ) . '</td>';
		echo '</tr>';
	}
	echo '</table>';
	
	if ( $has_seo_meta ) {
		echo '<p class="info">ℹ️ La homepage ha meta fields SEO compilati. Questo potrebbe essere correlato al problema.</p>';
	}
	echo '</div>';
	
	// 4. Verifica plugin attivi che potrebbero interferire
	echo '<div class="section">';
	echo '<h2>4. Plugin Attivi (potrebbero interferire)</h2>';
	
	$active_plugins = get_option( 'active_plugins', array() );
	$suspicious_plugins = array();
	
	$suspicious_keywords = array( 'seo', 'draft', 'save', 'post', 'meta', 'cache', 'optimize' );
	
	foreach ( $active_plugins as $plugin ) {
		foreach ( $suspicious_keywords as $keyword ) {
			if ( stripos( $plugin, $keyword ) !== false && stripos( $plugin, 'fp-seo' ) === false ) {
				$suspicious_plugins[] = $plugin;
				break;
			}
		}
	}
	
	if ( empty( $suspicious_plugins ) ) {
		echo '<p class="success">✓ Nessun plugin sospetto trovato</p>';
	} else {
		echo '<ul>';
		foreach ( $suspicious_plugins as $plugin ) {
			echo '<li>' . esc_html( $plugin ) . '</li>';
		}
		echo '</ul>';
	}
	echo '</div>';
	
	// 5. Test di correzione
	echo '<div class="section">';
	echo '<h2>5. Test di Correzione</h2>';
	
	if ( isset( $_GET['fix'] ) && $_GET['fix'] === '1' ) {
		// Forza lo status a 'publish'
		$result = $wpdb->update(
			$wpdb->posts,
			array( 'post_status' => 'publish' ),
			array( 'ID' => $page_on_front_id ),
			array( '%s' ),
			array( '%d' )
		);
		
		clean_post_cache( $page_on_front_id );
		wp_cache_delete( $page_on_front_id, 'posts' );
		
		if ( $result !== false ) {
			echo '<p class="success">✓ Status corretto a "publish"</p>';
			echo '<p><a href="?">Ricarica la pagina per verificare</a></p>';
		} else {
			echo '<p class="error">❌ Errore durante la correzione: ' . esc_html( $wpdb->last_error ) . '</p>';
		}
	} else {
		if ( $current_status === 'auto-draft' ) {
			echo '<p class="error">⚠️ Lo status è "auto-draft"!</p>';
			echo '<p><a href="?fix=1" style="background: #d63638; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px;">🔧 Correggi Ora</a></p>';
		} else {
			echo '<p class="success">✓ Lo status è corretto: ' . esc_html( $current_status ) . '</p>';
		}
	}
	echo '</div>';
	
	// 6. Informazioni ambiente
	echo '<div class="section">';
	echo '<h2>6. Informazioni Ambiente</h2>';
	echo '<table>';
	echo '<tr><th>Parametro</th><th>Valore</th></tr>';
	echo '<tr><td>PHP Version</td><td>' . esc_html( PHP_VERSION ) . '</td></tr>';
	echo '<tr><td>WordPress Version</td><td>' . esc_html( get_bloginfo( 'version' ) ) . '</td></tr>';
	echo '<tr><td>WP_DEBUG</td><td>' . ( defined( 'WP_DEBUG' ) && WP_DEBUG ? '<span class="success">✓ Attivo</span>' : '<span class="warning">✗ Disattivo</span>' ) . '</td></tr>';
	echo '<tr><td>WP_DEBUG_LOG</td><td>' . ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ? '<span class="success">✓ Attivo</span>' : '<span class="warning">✗ Disattivo</span>' ) . '</td></tr>';
	echo '<tr><td>DOING_AUTOSAVE</td><td>' . ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ? '<span class="warning">⚠️ Sì</span>' : '<span class="success">✗ No</span>' ) . '</td></tr>';
	echo '<tr><td>REST_REQUEST</td><td>' . ( defined( 'REST_REQUEST' ) && REST_REQUEST ? '<span class="info">ℹ️ Sì</span>' : '<span class="success">✗ No</span>' ) . '</td></tr>';
	echo '</table>';
	echo '</div>';
	
	// 7. Log recenti (se disponibili)
	if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
		$log_file = WP_CONTENT_DIR . '/debug.log';
		if ( file_exists( $log_file ) ) {
			echo '<div class="section">';
			echo '<h2>7. Log Recenti (ultime 50 righe con "homepage" o "auto-draft")</h2>';
			
			$lines = file( $log_file );
			$relevant_lines = array();
			
			foreach ( array_reverse( $lines ) as $line ) {
				if ( stripos( $line, 'homepage' ) !== false || stripos( $line, 'auto-draft' ) !== false || stripos( $line, 'Metabox' ) !== false ) {
					$relevant_lines[] = $line;
					if ( count( $relevant_lines ) >= 50 ) {
						break;
					}
				}
			}
			
			if ( empty( $relevant_lines ) ) {
				echo '<p class="warning">⚠️ Nessuna riga rilevante trovata nei log</p>';
			} else {
				echo '<pre>';
				foreach ( array_reverse( $relevant_lines ) as $line ) {
					echo esc_html( $line );
				}
				echo '</pre>';
			}
			echo '</div>';
		}
	}
	?>
	
	<div class="section">
		<h2>📝 Note</h2>
		<ul>
			<li>Questo script diagnostica il problema dell'auto-draft sulla homepage</li>
			<li>Se lo status è "auto-draft", puoi correggerlo cliccando il pulsante sopra</li>
			<li>Verifica i log di WordPress per vedere quale protezione viene attivata</li>
			<li>Se il problema persiste, potrebbe essere causato da un altro plugin o dalla configurazione del server</li>
		</ul>
	</div>
</body>
</html>





