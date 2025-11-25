<?php
/**
 * Test Hook Debug - Verifica quali hook vengono chiamati
 * 
 * IMPORTANTE: Esegui questo file tramite browser!
 * URL: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/test-hook-debug.php
 * 
 * @package FP\SEO
 */

// Solo se eseguito via browser
if ( php_sapi_name() === 'cli' ) {
	die( "ERRORE: Esegui via browser\n" );
}

// Carica WordPress
$document_root = isset( $_SERVER['DOCUMENT_ROOT'] ) ? $_SERVER['DOCUMENT_ROOT'] : dirname( dirname( dirname( dirname( __FILE__ ) ) ) );
$wp_load = $document_root . '/wp-load.php';

if ( ! file_exists( $wp_load ) ) {
	die( "ERRORE: wp-load.php non trovato" );
}

require_once $wp_load;

// Verifica admin
if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
	wp_die( '⛔ Devi essere loggato come amministratore' );
}

header( 'Content-Type: text/html; charset=utf-8' );
?>
<!DOCTYPE html>
<html>
<head>
	<title>Test Hook Debug - FP SEO Manager</title>
	<style>
		body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
		.container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
		.success { background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 10px 0; }
		.error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 10px 0; }
		.info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 4px; margin: 10px 0; }
		pre { background: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 11px; }
		table { width: 100%; border-collapse: collapse; margin: 10px 0; }
		table th, table td { padding: 8px; text-align: left; border: 1px solid #ddd; }
		table th { background: #f8f9fa; }
	</style>
</head>
<body>
<div class="container">
	<h1>🔍 Test Hook Debug - Verifica Hook Registration</h1>
	
	<?php
	global $wp_filter;
	
	echo '<div class="test-section">';
	echo '<h3>📋 Hook Registrati per FP SEO</h3>';
	
	$fp_seo_hooks = array();
	foreach ( $wp_filter as $hook_name => $hook_data ) {
		if ( strpos( $hook_name, 'save_post' ) !== false || 
			 strpos( $hook_name, 'edit_post' ) !== false || 
			 strpos( $hook_name, 'wp_insert_post' ) !== false ||
			 strpos( $hook_name, 'fp_seo' ) !== false ) {
			$fp_seo_hooks[ $hook_name ] = $hook_data;
		}
	}
	
	if ( ! empty( $fp_seo_hooks ) ) {
		echo '<table>';
		echo '<tr><th>Hook Name</th><th>Callbacks</th></tr>';
		foreach ( $fp_seo_hooks as $hook_name => $hook_data ) {
			$callbacks = array();
			if ( isset( $hook_data->callbacks ) ) {
				foreach ( $hook_data->callbacks as $priority => $callback_group ) {
					foreach ( $callback_group as $callback ) {
						if ( is_array( $callback['function'] ) ) {
							$class = is_object( $callback['function'][0] ) ? get_class( $callback['function'][0] ) : $callback['function'][0];
							$method = $callback['function'][1];
							$callbacks[] = "Priority $priority: $class::$method";
						} else {
							$callbacks[] = "Priority $priority: " . ( is_string( $callback['function'] ) ? $callback['function'] : 'Closure' );
						}
					}
				}
			}
			echo '<tr><td><strong>' . esc_html( $hook_name ) . '</strong></td><td>' . ( ! empty( $callbacks ) ? '<ul><li>' . implode( '</li><li>', $callbacks ) . '</li></ul>' : 'Nessuno' ) . '</td></tr>';
		}
		echo '</table>';
	} else {
		echo '<div class="error">❌ Nessun hook FP SEO trovato!</div>';
	}
	
	echo '</div>';
	
	// Verifica classe Metabox
	echo '<div class="test-section">';
	echo '<h3>🔧 Verifica Classe Metabox</h3>';
	
	if ( class_exists( '\FP\SEO\Editor\Metabox' ) ) {
		echo '<div class="success">✅ Classe Metabox trovata</div>';
		
		// Verifica metodi
		$methods = get_class_methods( '\FP\SEO\Editor\Metabox' );
		$save_methods = array_filter( $methods, function( $method ) {
			return strpos( $method, 'save' ) !== false;
		} );
		
		if ( ! empty( $save_methods ) ) {
			echo '<div class="info">ℹ️ Metodi save trovati: ' . implode( ', ', $save_methods ) . '</div>';
		}
	} else {
		echo '<div class="error">❌ Classe Metabox non trovata!</div>';
	}
	
	echo '</div>';
	
	// Verifica istanza
	echo '<div class="test-section">';
	echo '<h3>📦 Verifica Istanza Plugin</h3>';
	
	$plugin = \FP\SEO\Infrastructure\Plugin::instance();
	if ( $plugin ) {
		echo '<div class="success">✅ Plugin instance trovata</div>';
	} else {
		echo '<div class="error">❌ Plugin instance non trovata!</div>';
	}
	
	echo '</div>';
	
	// Log recenti
	echo '<div class="test-section">';
	echo '<h3>📝 Log Recenti (Ultimi 50)</h3>';
	
	$log_file = WP_CONTENT_DIR . '/debug.log';
	if ( file_exists( $log_file ) ) {
		$log_lines = file( $log_file );
		$recent_logs = array_slice( $log_lines, -50 );
		$fp_seo_logs = array_filter( $recent_logs, function( $line ) {
			return strpos( $line, 'FP SEO' ) !== false || strpos( $line, 'save_meta' ) !== false;
		} );
		
		if ( ! empty( $fp_seo_logs ) ) {
			echo '<pre>' . esc_html( implode( '', array_slice( $fp_seo_logs, -30 ) ) ) . '</pre>';
		} else {
			echo '<div class="info">ℹ️ Nessun log FP SEO trovato</div>';
		}
	} else {
		echo '<div class="info">ℹ️ File debug.log non trovato</div>';
	}
	
	echo '</div>';
	?>
	
</div>
</body>
</html>


