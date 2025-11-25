<?php
/**
 * Diagnostica Completa Plugin FP SEO Manager
 * 
 * IMPORTANTE: Esegui questo file tramite browser, non via CLI!
 * URL: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/diagnose-complete.php
 * 
 * @package FP\SEO
 * @version 2.0
 */

// Solo se eseguito via browser, non via CLI
if ( php_sapi_name() === 'cli' ) {
	echo "ERRORE: Questo script deve essere eseguito via browser, non via CLI.\n";
	echo "Apri: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/diagnose-complete.php\n";
	exit( 1 );
}

// Carica WordPress
$wp_load_paths = array(
	__DIR__ . '/../../../../wp-load.php',
	dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/wp-load.php',
	getcwd() . '/wp-load.php',
);

$wp_load = null;
foreach ( $wp_load_paths as $path ) {
	if ( file_exists( $path ) ) {
		$wp_load = $path;
		break;
	}
}

if ( ! $wp_load ) {
	$current_dir = getcwd();
	if ( strpos( $current_dir, 'wp-content' ) !== false ) {
		$parts = explode( 'wp-content', $current_dir );
		$wp_root = $parts[0];
		$wp_load = $wp_root . 'wp-load.php';
	}
}

if ( ! $wp_load || ! file_exists( $wp_load ) ) {
	die( "<h1>ERRORE: wp-load.php non trovato</h1><pre>Percorsi provati:\n" . implode( "\n", $wp_load_paths ) . "\n</pre>" );
}

require_once $wp_load;

// Abilita error reporting
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', true );
}
error_reporting( E_ALL );
ini_set( 'display_errors', 1 );

// Stile CSS per output
?>
<!DOCTYPE html>
<html lang="it">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Diagnostica Completa FP SEO Manager</title>
	<style>
		* { margin: 0; padding: 0; box-sizing: border-box; }
		body {
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
			background: #f5f5f5;
			padding: 20px;
			line-height: 1.6;
			color: #333;
		}
		.container {
			max-width: 1200px;
			margin: 0 auto;
			background: #fff;
			border-radius: 8px;
			box-shadow: 0 2px 10px rgba(0,0,0,0.1);
			overflow: hidden;
		}
		.header {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: #fff;
			padding: 30px;
			text-align: center;
		}
		.header h1 {
			font-size: 28px;
			margin-bottom: 10px;
		}
		.header p {
			opacity: 0.9;
			font-size: 14px;
		}
		.content {
			padding: 30px;
		}
		.test-section {
			margin-bottom: 30px;
			border: 1px solid #e5e7eb;
			border-radius: 8px;
			overflow: hidden;
		}
		.test-header {
			background: #f9fafb;
			padding: 15px 20px;
			border-bottom: 1px solid #e5e7eb;
			font-weight: 600;
			font-size: 16px;
			display: flex;
			align-items: center;
			gap: 10px;
		}
		.test-body {
			padding: 20px;
			background: #fff;
		}
		.test-item {
			padding: 10px 0;
			border-bottom: 1px solid #f3f4f6;
		}
		.test-item:last-child {
			border-bottom: none;
		}
		.status {
			display: inline-block;
			padding: 4px 12px;
			border-radius: 12px;
			font-size: 12px;
			font-weight: 600;
			margin-left: 10px;
		}
		.status-success { background: #d1fae5; color: #065f46; }
		.status-warning { background: #fef3c7; color: #92400e; }
		.status-error { background: #fee2e2; color: #991b1b; }
		.status-info { background: #dbeafe; color: #1e40af; }
		.code-block {
			background: #1f2937;
			color: #f9fafb;
			padding: 15px;
			border-radius: 6px;
			font-family: 'Courier New', monospace;
			font-size: 13px;
			overflow-x: auto;
			margin: 10px 0;
		}
		.log-entry {
			background: #f9fafb;
			padding: 8px 12px;
			border-left: 3px solid #3b82f6;
			margin: 5px 0;
			font-size: 12px;
			font-family: monospace;
		}
		.log-error {
			border-left-color: #ef4444;
			background: #fef2f2;
		}
		.log-warning {
			border-left-color: #f59e0b;
			background: #fffbeb;
		}
		.log-success {
			border-left-color: #10b981;
			background: #f0fdf4;
		}
		.summary {
			background: linear-gradient(135deg, #10b981 0%, #059669 100%);
			color: #fff;
			padding: 20px;
			border-radius: 8px;
			margin-bottom: 30px;
		}
		.summary h2 {
			margin-bottom: 15px;
		}
		.summary-stats {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
			gap: 15px;
			margin-top: 15px;
		}
		.summary-stat {
			background: rgba(255,255,255,0.2);
			padding: 15px;
			border-radius: 6px;
			text-align: center;
		}
		.summary-stat-value {
			font-size: 32px;
			font-weight: 700;
			margin-bottom: 5px;
		}
		.summary-stat-label {
			font-size: 12px;
			opacity: 0.9;
		}
		.button {
			display: inline-block;
			padding: 10px 20px;
			background: #3b82f6;
			color: #fff;
			text-decoration: none;
			border-radius: 6px;
			font-weight: 600;
			margin: 5px;
			cursor: pointer;
			border: none;
		}
		.button:hover {
			background: #2563eb;
		}
		.button-danger {
			background: #ef4444;
		}
		.button-danger:hover {
			background: #dc2626;
		}
		.collapsible {
			cursor: pointer;
			user-select: none;
		}
		.collapsible:hover {
			background: #f3f4f6;
		}
		.collapsible-content {
			display: none;
			padding: 15px;
			background: #f9fafb;
			border-top: 1px solid #e5e7eb;
		}
		.collapsible-content.active {
			display: block;
		}
	</style>
</head>
<body>
	<div class="container">
		<div class="header">
			<h1>🔍 Diagnostica Completa FP SEO Manager</h1>
			<p>Analisi approfondita del plugin - <?php echo date( 'Y-m-d H:i:s' ); ?></p>
		</div>
		<div class="content">
<?php

// Variabili per statistiche
$stats = array(
	'total' => 0,
	'success' => 0,
	'warning' => 0,
	'error' => 0,
);

// Funzione helper per output
function output_test( $label, $status, $message = '', $details = '' ) {
	global $stats;
	$stats['total']++;
	
	$status_class = '';
	$status_text = '';
	$icon = '';
	
	switch ( $status ) {
		case 'success':
			$status_class = 'status-success';
			$status_text = 'OK';
			$icon = '✅';
			$stats['success']++;
			break;
		case 'warning':
			$status_class = 'status-warning';
			$status_text = 'WARNING';
			$icon = '⚠️';
			$stats['warning']++;
			break;
		case 'error':
			$status_class = 'status-error';
			$status_text = 'ERRORE';
			$icon = '❌';
			$stats['error']++;
			break;
		default:
			$status_class = 'status-info';
			$status_text = 'INFO';
			$icon = 'ℹ️';
	}
	
	echo '<div class="test-item">';
	echo '<strong>' . esc_html( $icon . ' ' . $label ) . '</strong>';
	echo '<span class="status ' . esc_attr( $status_class ) . '">' . esc_html( $status_text ) . '</span>';
	if ( $message ) {
		echo '<div style="margin-top: 5px; color: #6b7280; font-size: 14px;">' . esc_html( $message ) . '</div>';
	}
	if ( $details ) {
		echo '<div class="code-block" style="margin-top: 10px;">' . esc_html( $details ) . '</div>';
	}
	echo '</div>';
}

// ============================================
// TEST 1: VERIFICA PLUGIN E CARICAMENTO
// ============================================
echo '<div class="test-section">';
echo '<div class="test-header">📦 Test 1: Verifica Plugin e Caricamento</div>';
echo '<div class="test-body">';

// Verifica file principale
$plugin_file = __DIR__ . '/fp-seo-performance.php';
if ( file_exists( $plugin_file ) ) {
	$plugin_data = get_file_data( $plugin_file, array(
		'Name' => 'Plugin Name',
		'Version' => 'Version',
		'Author' => 'Author',
	) );
	output_test( 'File principale plugin', 'success', 'Trovato: ' . $plugin_data['Name'] . ' v' . $plugin_data['Version'] );
} else {
	output_test( 'File principale plugin', 'error', 'File fp-seo-performance.php non trovato!' );
}

// Verifica plugin attivo
if ( is_plugin_active( 'FP-SEO-Manager/fp-seo-performance.php' ) ) {
	output_test( 'Plugin attivo', 'success', 'Il plugin è attivo' );
} else {
	output_test( 'Plugin attivo', 'warning', 'Plugin potrebbe non essere attivo. Verifica manualmente.' );
}

// Verifica classi principali
$classes_to_check = array(
	'FP\\SEO\\Infrastructure\\Plugin',
	'FP\\SEO\\Editor\\Metabox',
	'FP\\SEO\\Editor\\MetaboxSaver',
	'FP\\SEO\\Editor\\MetaboxRenderer',
	'FP\\SEO\\Infrastructure\\Container',
);

foreach ( $classes_to_check as $class ) {
	if ( class_exists( $class ) ) {
		output_test( 'Classe: ' . $class, 'success', 'Caricata correttamente' );
	} else {
		output_test( 'Classe: ' . $class, 'error', 'Classe non trovata!' );
	}
}

// Verifica autoloader
$autoload_file = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $autoload_file ) ) {
	output_test( 'Autoloader Composer', 'success', 'File vendor/autoload.php trovato' );
} else {
	output_test( 'Autoloader Composer', 'warning', 'Autoloader non trovato. Esegui: composer install' );
}

echo '</div></div>';

// ============================================
// TEST 2: VERIFICA HOOK E REGISTRAZIONI
// ============================================
echo '<div class="test-section">';
echo '<div class="test-header">🔗 Test 2: Verifica Hook e Registrazioni</div>';
echo '<div class="test-body">';

global $wp_filter;

// Verifica hook save_post
if ( isset( $wp_filter['save_post'] ) ) {
	$save_post_hooks = $wp_filter['save_post']->callbacks;
	$fp_seo_found = false;
	$fp_seo_details = array();
	
	foreach ( $save_post_hooks as $priority => $callbacks ) {
		foreach ( $callbacks as $callback ) {
			if ( is_array( $callback['function'] ) ) {
				$obj = $callback['function'][0];
				$method = $callback['function'][1];
				if ( is_object( $obj ) ) {
					$class = get_class( $obj );
					if ( strpos( $class, 'FP\\SEO' ) !== false || strpos( $method, 'save_meta' ) !== false ) {
						$fp_seo_found = true;
						$fp_seo_details[] = "{$class}::{$method} (priorità: {$priority})";
					}
				}
			}
		}
	}
	
	if ( $fp_seo_found ) {
		output_test( 'Hook save_post registrato', 'success', 'Trovato hook FP SEO', implode( "\n", $fp_seo_details ) );
	} else {
		output_test( 'Hook save_post registrato', 'error', 'Hook FP SEO non trovato in save_post!' );
	}
	
	output_test( 'Totale hook save_post', 'info', 'Trovati ' . count( $save_post_hooks ) . ' hook registrati' );
} else {
	output_test( 'Hook save_post', 'warning', 'Nessun hook save_post trovato' );
}

// Verifica hook add_meta_boxes
if ( isset( $wp_filter['add_meta_boxes'] ) ) {
	$meta_boxes_hooks = $wp_filter['add_meta_boxes']->callbacks;
	$fp_seo_metabox_found = false;
	
	foreach ( $meta_boxes_hooks as $priority => $callbacks ) {
		foreach ( $callbacks as $callback ) {
			if ( is_array( $callback['function'] ) ) {
				$obj = $callback['function'][0];
				$method = $callback['function'][1];
				if ( is_object( $obj ) ) {
					$class = get_class( $obj );
					if ( strpos( $class, 'FP\\SEO\\Editor\\Metabox' ) !== false ) {
						$fp_seo_metabox_found = true;
					}
				}
			}
		}
	}
	
	if ( $fp_seo_metabox_found ) {
		output_test( 'Hook add_meta_boxes registrato', 'success', 'Metabox FP SEO registrato' );
	} else {
		output_test( 'Hook add_meta_boxes registrato', 'warning', 'Metabox FP SEO potrebbe non essere registrato' );
	}
}

// Verifica istanza Plugin
try {
	if ( class_exists( 'FP\\SEO\\Infrastructure\\Plugin' ) ) {
		$plugin = FP\SEO\Infrastructure\Plugin::instance();
		if ( $plugin ) {
			output_test( 'Istanza Plugin', 'success', 'Plugin istanziato correttamente' );
			
			// Verifica Container
			if ( method_exists( $plugin, 'get_container' ) ) {
				$container = $plugin->get_container();
				if ( $container ) {
					output_test( 'Container DI', 'success', 'Container disponibile' );
					
					// Verifica Metabox nel container
					try {
						$metabox = $container->get( FP\SEO\Editor\Metabox::class );
						if ( $metabox ) {
							output_test( 'Metabox nel Container', 'success', 'Metabox disponibile nel container' );
						} else {
							output_test( 'Metabox nel Container', 'error', 'Metabox non disponibile nel container' );
						}
					} catch ( Exception $e ) {
						output_test( 'Metabox nel Container', 'error', 'Errore: ' . $e->getMessage() );
					}
				} else {
					output_test( 'Container DI', 'error', 'Container non disponibile' );
				}
			}
		} else {
			output_test( 'Istanza Plugin', 'error', 'Impossibile ottenere istanza Plugin' );
		}
	}
} catch ( Exception $e ) {
	output_test( 'Istanza Plugin', 'error', 'Errore: ' . $e->getMessage() );
}

echo '</div></div>';

// ============================================
// TEST 3: VERIFICA DATABASE E META
// ============================================
echo '<div class="test-section">';
echo '<div class="test-header">💾 Test 3: Verifica Database e Meta</div>';
echo '<div class="test-body">';

// Trova un post di test
$test_post = get_posts( array(
	'numberposts' => 1,
	'post_type' => 'any',
	'post_status' => 'any',
) );

if ( ! empty( $test_post ) ) {
	$test_post_id = $test_post[0]->ID;
	output_test( 'Post di test', 'success', "ID: {$test_post_id} - {$test_post[0]->post_title}" );
	
	// Verifica meta keys
	$meta_keys = array(
		'_fp_seo_title',
		'_fp_seo_meta_description',
		'_fp_seo_focus_keyword',
		'_fp_seo_secondary_keywords',
		'_fp_seo_performance_exclude',
	);
	
	foreach ( $meta_keys as $meta_key ) {
		$meta_value = get_post_meta( $test_post_id, $meta_key, true );
		if ( $meta_value !== false && $meta_value !== '' ) {
			$preview = is_array( $meta_value ) ? json_encode( $meta_value ) : substr( (string) $meta_value, 0, 50 );
			output_test( "Meta: {$meta_key}", 'success', "Valore presente: {$preview}" );
		} else {
			output_test( "Meta: {$meta_key}", 'info', 'Valore non impostato (normale se non configurato)' );
		}
	}
	
	// Verifica direttamente nel database
	global $wpdb;
	$db_meta = $wpdb->get_results( $wpdb->prepare(
		"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s",
		$test_post_id,
		'_fp_seo_%'
	) );
	
	if ( ! empty( $db_meta ) ) {
		$db_details = array();
		foreach ( $db_meta as $meta ) {
			$preview = strlen( $meta->meta_value ) > 50 ? substr( $meta->meta_value, 0, 50 ) . '...' : $meta->meta_value;
			$db_details[] = "{$meta->meta_key}: {$preview}";
		}
		output_test( 'Meta nel database', 'success', 'Trovati ' . count( $db_meta ) . ' meta fields', implode( "\n", $db_details ) );
	} else {
		output_test( 'Meta nel database', 'info', 'Nessun meta field FP SEO trovato nel database per questo post' );
	}
} else {
	output_test( 'Post di test', 'warning', 'Nessun post trovato per il test' );
}

echo '</div></div>';

// ============================================
// TEST 4: SIMULAZIONE SALVATAGGIO
// ============================================
echo '<div class="test-section">';
echo '<div class="test-header">💾 Test 4: Simulazione Salvataggio</div>';
echo '<div class="test-body">';

if ( ! empty( $test_post ) ) {
	$test_post_id = $test_post[0]->ID;
	
	// Salva valori di test
	$test_title = 'Test Diagnostico - ' . time();
	$test_desc = 'Descrizione Test Diagnostico - ' . time();
	
	// Simula $_POST
	$_POST['fp_seo_performance_metabox_present'] = '1';
	$_POST['fp_seo_title'] = $test_title;
	$_POST['fp_seo_title_sent'] = '1';
	$_POST['fp_seo_meta_description'] = $test_desc;
	$_POST['fp_seo_meta_description_sent'] = '1';
	$_POST['fp_seo_performance_nonce'] = wp_create_nonce( 'fp_seo_performance_save' );
	
	output_test( 'Preparazione test', 'info', "Titolo: {$test_title}\nDescrizione: {$test_desc}" );
	
	// Prova salvataggio
	try {
		if ( class_exists( 'FP\\SEO\\Editor\\MetaboxSaver' ) ) {
			$saver = new FP\SEO\Editor\MetaboxSaver();
			$result = $saver->save_all_fields( $test_post_id );
			
			if ( $result ) {
				output_test( 'Salvataggio test', 'success', 'MetaboxSaver::save_all_fields() eseguito' );
				
				// Verifica salvataggio
				clean_post_cache( $test_post_id );
				wp_cache_delete( $test_post_id, 'post_meta' );
				update_post_meta_cache( array( $test_post_id ) );
				
				$saved_title = get_post_meta( $test_post_id, '_fp_seo_title', true );
				$saved_desc = get_post_meta( $test_post_id, '_fp_seo_meta_description', true );
				
				if ( $saved_title === $test_title ) {
					output_test( 'Verifica titolo salvato', 'success', 'Titolo salvato correttamente' );
				} else {
					output_test( 'Verifica titolo salvato', 'error', "Mismatch! Atteso: {$test_title}, Ottenuto: {$saved_title}" );
				}
				
				if ( $saved_desc === $test_desc ) {
					output_test( 'Verifica descrizione salvata', 'success', 'Descrizione salvata correttamente' );
				} else {
					output_test( 'Verifica descrizione salvata', 'error', "Mismatch! Atteso: {$test_desc}, Ottenuto: {$saved_desc}" );
				}
			} else {
				output_test( 'Salvataggio test', 'warning', 'MetaboxSaver::save_all_fields() ritornato false' );
			}
		} else {
			output_test( 'Salvataggio test', 'error', 'Classe MetaboxSaver non trovata' );
		}
	} catch ( Exception $e ) {
		output_test( 'Salvataggio test', 'error', 'Errore: ' . $e->getMessage() );
	}
	
	// Ripristina valori originali (opzionale)
	// delete_post_meta( $test_post_id, '_fp_seo_title' );
	// delete_post_meta( $test_post_id, '_fp_seo_meta_description' );
} else {
	output_test( 'Simulazione salvataggio', 'warning', 'Nessun post disponibile per il test' );
}

echo '</div></div>';

// ============================================
// TEST 5: VERIFICA FILE E ASSETS
// ============================================
echo '<div class="test-section">';
echo '<div class="test-header">📁 Test 5: Verifica File e Assets</div>';
echo '<div class="test-body">';

$files_to_check = array(
	'src/Editor/Metabox.php',
	'src/Editor/MetaboxSaver.php',
	'src/Editor/MetaboxRenderer.php',
	'assets/admin/js/metabox-ai-fields.js',
	'assets/admin/css/admin.css',
);

foreach ( $files_to_check as $file ) {
	$full_path = __DIR__ . '/' . $file;
	if ( file_exists( $full_path ) ) {
		$size = filesize( $full_path );
		$size_kb = round( $size / 1024, 2 );
		output_test( "File: {$file}", 'success', "Trovato ({$size_kb} KB)" );
	} else {
		output_test( "File: {$file}", 'error', 'File non trovato!' );
	}
}

// Verifica JavaScript per campi nascosti
$js_file = __DIR__ . '/assets/admin/js/metabox-ai-fields.js';
if ( file_exists( $js_file ) ) {
	$js_content = file_get_contents( $js_file );
	$required_fields = array(
		'fp_seo_title_sent',
		'fp_seo_meta_description_sent',
		'fp_seo_performance_metabox_present',
		'ensureFieldsInForm',
	);
	
	foreach ( $required_fields as $field ) {
		if ( strpos( $js_content, $field ) !== false ) {
			output_test( "JS: Campo {$field}", 'success', 'Trovato nel JavaScript' );
		} else {
			output_test( "JS: Campo {$field}", 'error', 'NON trovato nel JavaScript!' );
		}
	}
}

echo '</div></div>';

// ============================================
// TEST 6: ANALISI LOG
// ============================================
echo '<div class="test-section">';
echo '<div class="test-header">📋 Test 6: Analisi Log</div>';
echo '<div class="test-body">';

$log_file = WP_CONTENT_DIR . '/debug.log';
if ( file_exists( $log_file ) ) {
	output_test( 'File debug.log', 'success', 'Trovato: ' . $log_file );
	
	// Leggi ultime righe
	$log_lines = file( $log_file, FILE_IGNORE_NEW_LINES );
	$total_lines = count( $log_lines );
	$recent_lines = array_slice( $log_lines, -100 ); // Ultime 100 righe
	
	output_test( 'Righe totali log', 'info', "{$total_lines} righe totali, analizzando ultime 100" );
	
	// Filtra log FP SEO
	$fp_seo_logs = array_filter( $recent_lines, function( $line ) {
		return strpos( $line, 'FP SEO' ) !== false;
	} );
	
	if ( ! empty( $fp_seo_logs ) ) {
		$log_count = count( $fp_seo_logs );
		output_test( 'Log FP SEO trovati', 'success', "Trovati {$log_count} log FP SEO nelle ultime 100 righe" );
		
		// Mostra ultimi 10 log
		$last_logs = array_slice( $fp_seo_logs, -10 );
		echo '<div style="margin-top: 15px;"><strong>Ultimi 10 log FP SEO:</strong></div>';
		foreach ( $last_logs as $log_line ) {
			$log_class = 'log-entry';
			if ( strpos( $log_line, 'ERROR' ) !== false || strpos( $log_line, 'ERRORE' ) !== false ) {
				$log_class .= ' log-error';
			} elseif ( strpos( $log_line, 'WARNING' ) !== false || strpos( $log_line, 'WARN' ) !== false ) {
				$log_class .= ' log-warning';
			} elseif ( strpos( $log_line, 'SUCCESS' ) !== false || strpos( $log_line, 'saved' ) !== false ) {
				$log_class .= ' log-success';
			}
			echo '<div class="' . esc_attr( $log_class ) . '">' . esc_html( $log_line ) . '</div>';
		}
	} else {
		output_test( 'Log FP SEO trovati', 'info', 'Nessun log FP SEO trovato nelle ultime 100 righe' );
	}
} else {
	output_test( 'File debug.log', 'warning', 'File debug.log non trovato. WP_DEBUG potrebbe essere disabilitato.' );
}

echo '</div></div>';

// ============================================
// TEST 7: VERIFICA CONFIGURAZIONE
// ============================================
echo '<div class="test-section">';
echo '<div class="test-header">⚙️ Test 7: Verifica Configurazione</div>';
echo '<div class="test-body">';

// Verifica WP_DEBUG
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	output_test( 'WP_DEBUG', 'success', 'Abilitato' );
} else {
	output_test( 'WP_DEBUG', 'warning', 'Disabilitato - i log potrebbero non essere generati' );
}

// Verifica costanti plugin
$constants = array(
	'FP_SEO_PERFORMANCE_FILE',
	'FP_SEO_PERFORMANCE_VERSION',
);

foreach ( $constants as $constant ) {
	if ( defined( $constant ) ) {
		$value = constant( $constant );
		output_test( "Costante: {$constant}", 'success', "Valore: {$value}" );
	} else {
		output_test( "Costante: {$constant}", 'warning', 'Non definita' );
	}
}

// Verifica opzioni plugin
if ( class_exists( 'FP\\SEO\\Utils\\Options' ) ) {
	try {
		$options = FP\SEO\Utils\Options::get();
		if ( ! empty( $options ) ) {
			output_test( 'Opzioni plugin', 'success', 'Opzioni caricate: ' . count( $options ) . ' sezioni' );
		} else {
			output_test( 'Opzioni plugin', 'info', 'Nessuna opzione configurata' );
		}
	} catch ( Exception $e ) {
		output_test( 'Opzioni plugin', 'error', 'Errore: ' . $e->getMessage() );
	}
}

echo '</div></div>';

// ============================================
// RIEPILOGO FINALE
// ============================================
echo '<div class="summary">';
echo '<h2>📊 Riepilogo Diagnostica</h2>';
echo '<div class="summary-stats">';
echo '<div class="summary-stat">';
echo '<div class="summary-stat-value">' . $stats['total'] . '</div>';
echo '<div class="summary-stat-label">Test Totali</div>';
echo '</div>';
echo '<div class="summary-stat">';
echo '<div class="summary-stat-value" style="color: #10b981;">' . $stats['success'] . '</div>';
echo '<div class="summary-stat-label">Successi</div>';
echo '</div>';
echo '<div class="summary-stat">';
echo '<div class="summary-stat-value" style="color: #f59e0b;">' . $stats['warning'] . '</div>';
echo '<div class="summary-stat-label">Warning</div>';
echo '</div>';
echo '<div class="summary-stat">';
echo '<div class="summary-stat-value" style="color: #ef4444;">' . $stats['error'] . '</div>';
echo '<div class="summary-stat-label">Errori</div>';
echo '</div>';
echo '</div>';

// Calcola percentuale successo
$success_rate = $stats['total'] > 0 ? round( ( $stats['success'] / $stats['total'] ) * 100, 1 ) : 0;
echo '<div style="margin-top: 20px; padding: 15px; background: rgba(255,255,255,0.2); border-radius: 6px;">';
echo '<strong>Tasso di successo: ' . $success_rate . '%</strong>';
if ( $success_rate >= 90 ) {
	echo '<p style="margin-top: 10px;">✅ Plugin in ottimo stato!</p>';
} elseif ( $success_rate >= 70 ) {
	echo '<p style="margin-top: 10px;">⚠️ Plugin funzionante con alcuni warning da verificare.</p>';
} else {
	echo '<p style="margin-top: 10px;">❌ Plugin presenta problemi che richiedono attenzione.</p>';
}
echo '</div>';

echo '</div>';

// ============================================
// RACCOMANDAZIONI
// ============================================
if ( $stats['error'] > 0 || $stats['warning'] > 5 ) {
	echo '<div class="test-section">';
	echo '<div class="test-header">💡 Raccomandazioni</div>';
	echo '<div class="test-body">';
	echo '<ul style="line-height: 2;">';
	
	if ( $stats['error'] > 0 ) {
		echo '<li>❌ <strong>Errori critici trovati:</strong> Risolvi gli errori prima di procedere.</li>';
	}
	
	if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
		echo '<li>⚠️ <strong>Abilita WP_DEBUG:</strong> Aggiungi <code>define(\'WP_DEBUG\', true);</code> in wp-config.php per vedere i log.</li>';
	}
	
	if ( ! file_exists( $autoload_file ) ) {
		echo '<li>⚠️ <strong>Installa dipendenze:</strong> Esegui <code>composer install</code> nella directory del plugin.</li>';
	}
	
	echo '<li>📋 <strong>Controlla i log:</strong> Verifica wp-content/debug.log per dettagli sugli errori.</li>';
	echo '<li>🔄 <strong>Ricarica la pagina:</strong> Dopo le correzioni, ricarica questa pagina per verificare.</li>';
	
	echo '</ul>';
	echo '</div></div>';
}

?>
		</div>
	</div>
	
	<script>
		// Aggiungi funzionalità collapsible
		document.querySelectorAll('.test-header').forEach(header => {
			header.classList.add('collapsible');
			header.addEventListener('click', function() {
				const body = this.nextElementSibling;
				body.classList.toggle('active');
			});
		});
		
		// Espandi automaticamente sezioni con errori
		document.querySelectorAll('.status-error').forEach(error => {
			const section = error.closest('.test-section');
			if (section) {
				const body = section.querySelector('.test-body');
				if (body) {
					body.classList.add('active');
				}
			}
		});
	</script>
</body>
</html>


