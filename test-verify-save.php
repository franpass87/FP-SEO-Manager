<?php
/**
 * Test Verifica Salvataggio - Verifica se i dati sono stati salvati nel database
 * 
 * IMPORTANTE: Esegui questo file tramite browser!
 * URL: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/test-verify-save.php?post_id=441
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

$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 441;

header( 'Content-Type: text/html; charset=utf-8' );
?>
<!DOCTYPE html>
<html>
<head>
	<title>Test Verifica Salvataggio - FP SEO Manager</title>
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
		.meta-value { max-width: 500px; word-wrap: break-word; }
	</style>
</head>
<body>
<div class="container">
	<h1>🔍 Test Verifica Salvataggio - Post ID: <?php echo esc_html( $post_id ); ?></h1>
	
	<?php
	$post = get_post( $post_id );
	if ( ! $post ) {
		echo '<div class="error">❌ Post non trovato!</div>';
		exit;
	}
	
	echo '<div class="info">ℹ️ Post: <strong>' . esc_html( $post->post_title ) . '</strong> (ID: ' . $post_id . ')</div>';
	
	// Verifica meta salvati
	$meta_fields = array(
		'_fp_seo_title' => 'SEO Title',
		'_fp_seo_meta_description' => 'Meta Description',
		'_fp_seo_focus_keyword' => 'Focus Keyword',
		'_fp_seo_secondary_keywords' => 'Secondary Keywords',
		'_fp_seo_performance_exclude' => 'Exclude from Analysis',
	);
	
	echo '<h3>📊 Meta Salvati nel Database</h3>';
	echo '<table>';
	echo '<tr><th>Meta Key</th><th>Nome Campo</th><th>Valore</th><th>Stato</th></tr>';
	
	$has_data = false;
	foreach ( $meta_fields as $meta_key => $field_name ) {
		$value = get_post_meta( $post_id, $meta_key, true );
		$exists = metadata_exists( 'post', $post_id, $meta_key );
		
		if ( $value !== '' && $value !== false ) {
			$has_data = true;
			echo '<tr>';
			echo '<td><code>' . esc_html( $meta_key ) . '</code></td>';
			echo '<td>' . esc_html( $field_name ) . '</td>';
			echo '<td class="meta-value">' . esc_html( $value ) . '</td>';
			echo '<td><span style="color: green;">✅ Salvato</span></td>';
			echo '</tr>';
		} else {
			echo '<tr>';
			echo '<td><code>' . esc_html( $meta_key ) . '</code></td>';
			echo '<td>' . esc_html( $field_name ) . '</td>';
			echo '<td class="meta-value"><em>(vuoto)</em></td>';
			echo '<td><span style="color: orange;">⚠️ Non salvato</span></td>';
			echo '</tr>';
		}
	}
	
	echo '</table>';
	
	if ( $has_data ) {
		echo '<div class="success">✅ Alcuni dati sono stati salvati nel database!</div>';
	} else {
		echo '<div class="error">❌ Nessun dato SEO salvato nel database per questo post.</div>';
	}
	
	// Verifica hook registrati
	echo '<h3>🔧 Hook Registrati</h3>';
	global $wp_filter;
	
	$save_post_hooks = array();
	if ( isset( $wp_filter['save_post'] ) ) {
		foreach ( $wp_filter['save_post']->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( is_array( $callback['function'] ) ) {
					$class = is_object( $callback['function'][0] ) ? get_class( $callback['function'][0] ) : $callback['function'][0];
					$method = $callback['function'][1];
					if ( strpos( $class, 'FP\\SEO' ) !== false || strpos( $class, 'Metabox' ) !== false ) {
						$save_post_hooks[] = "Priority $priority: $class::$method";
					}
				}
			}
		}
	}
	
	if ( ! empty( $save_post_hooks ) ) {
		echo '<div class="success">✅ Hook FP SEO trovati:</div>';
		echo '<ul>';
		foreach ( $save_post_hooks as $hook ) {
			echo '<li>' . esc_html( $hook ) . '</li>';
		}
		echo '</ul>';
	} else {
		echo '<div class="error">❌ Nessun hook FP SEO trovato per save_post!</div>';
	}
	
	// Log recenti
	echo '<h3>📝 Log Recenti (Ultimi 30)</h3>';
	$log_file = WP_CONTENT_DIR . '/debug.log';
	if ( file_exists( $log_file ) ) {
		$log_lines = file( $log_file );
		$recent_logs = array_slice( $log_lines, -100 );
		$fp_seo_logs = array_filter( $recent_logs, function( $line ) {
			return strpos( $line, 'FP SEO' ) !== false && (
				strpos( $line, 'save' ) !== false || 
				strpos( $line, 'Metabox' ) !== false ||
				strpos( $line, 'POST' ) !== false
			);
		} );
		
		if ( ! empty( $fp_seo_logs ) ) {
			echo '<pre>' . esc_html( implode( '', array_slice( $fp_seo_logs, -30 ) ) ) . '</pre>';
		} else {
			echo '<div class="info">ℹ️ Nessun log FP SEO trovato</div>';
		}
	} else {
		echo '<div class="info">ℹ️ File debug.log non trovato</div>';
	}
	?>
	
</div>
</body>
</html>





