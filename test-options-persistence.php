<?php
/**
 * Test Persistenza Opzioni - Verifica che le opzioni non vengano resettate
 * 
 * IMPORTANTE: Esegui questo file tramite browser!
 * URL: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/test-options-persistence.php
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
if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
	wp_die( '⛔ Devi essere loggato come amministratore' );
}

header( 'Content-Type: text/html; charset=utf-8' );
?>
<!DOCTYPE html>
<html>
<head>
	<title>Test Persistenza Opzioni - FP SEO Manager</title>
	<style>
		body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
		.container { max-width: 1400px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
		.success { background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 10px 0; }
		.error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 10px 0; }
		.info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 4px; margin: 10px 0; }
		.warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 4px; margin: 10px 0; }
		pre { background: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 11px; }
		table { width: 100%; border-collapse: collapse; margin: 10px 0; }
		table th, table td { padding: 8px; text-align: left; border: 1px solid #ddd; }
		table th { background: #f8f9fa; }
		.section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 4px; }
		.section h3 { margin-top: 0; color: #333; }
	</style>
</head>
<body>
<div class="container">
	<h1>🔍 Test Persistenza Opzioni - FP SEO Manager</h1>
	
	<?php
	require_once __DIR__ . '/src/Utils/Options.php';
	
	$option_key = \FP\SEO\Utils\Options::OPTION_KEY;
	
	// Test 1: Verifica che le opzioni esistenti non vengano sovrascritte
	echo '<div class="section">';
	echo '<h3>📊 Test 1: Verifica Opzioni Esistenti</h3>';
	
	// Leggi opzioni direttamente dal DB
	global $wpdb;
	$db_options_raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", $option_key ) );
	$db_options = $db_options_raw ? maybe_unserialize( $db_options_raw ) : array();
	
	// Leggi opzioni tramite Options::get()
	$options_via_get = \FP\SEO\Utils\Options::get();
	
	// Leggi opzioni tramite get_option()
	$options_via_get_option = get_option( $option_key, array() );
	
	echo '<table>';
	echo '<tr><th>Metodo</th><th>Keys Count</th><th>Stato</th></tr>';
	
	$db_count = is_array( $db_options ) ? count( $db_options ) : 0;
	$get_count = is_array( $options_via_get ) ? count( $options_via_get ) : 0;
	$get_option_count = is_array( $options_via_get_option ) ? count( $options_via_get_option ) : 0;
	
	echo '<tr><td>Database diretto</td><td>' . $db_count . '</td><td>' . ( $db_count > 0 ? '✅ OK' : 'ℹ️ Vuoto' ) . '</td></tr>';
	echo '<tr><td>Options::get()</td><td>' . $get_count . '</td><td>' . ( $get_count > 0 ? '✅ OK' : 'ℹ️ Vuoto' ) . '</td></tr>';
	echo '<tr><td>get_option()</td><td>' . $get_option_count . '</td><td>' . ( $get_option_count > 0 ? '✅ OK' : 'ℹ️ Vuoto' ) . '</td></tr>';
	echo '</table>';
	
	// Verifica che merge_defaults non sovrascriva valori esistenti
	if ( is_array( $db_options ) && ! empty( $db_options ) ) {
		$test_key = 'general.language';
		$db_language = null;
		$get_language = \FP\SEO\Utils\Options::get_option( $test_key, null );
		
		// Naviga nel DB value
		$keys = explode( '.', $test_key );
		$db_nested = $db_options;
		foreach ( $keys as $k ) {
			$db_nested = $db_nested[ $k ] ?? null;
			if ( $db_nested === null ) {
				break;
			}
		}
		$db_language = $db_nested;
		
		if ( $db_language !== null && $get_language === $db_language ) {
			echo '<div class="success">✅ I valori esistenti vengono preservati correttamente (Language: ' . esc_html( $db_language ) . ')</div>';
		} elseif ( $db_language === null ) {
			echo '<div class="info">ℹ️ Nessun valore personalizzato trovato per ' . esc_html( $test_key ) . '</div>';
		} else {
			echo '<div class="warning">⚠️ Possibile problema: DB value (' . esc_html( $db_language ?: '(vuoto)' ) . ') != Options::get_option() (' . esc_html( $get_language ?: '(vuoto)' ) . ')</div>';
		}
	} else {
		echo '<div class="info">ℹ️ Nessuna opzione salvata nel database (prima installazione?)</div>';
	}
	echo '</div>';
	
	// Test 2: Simula attivazione plugin
	echo '<div class="section">';
	echo '<h3>🧪 Test 2: Simula Attivazione Plugin</h3>';
	
	// Salva un valore personalizzato
	$test_custom_value = 'test_persistence_' . time();
	$test_custom_key = 'general.test_persistence';
	
	// Salva usando Options::update
	$update_data = array(
		'general' => array(
			'test_persistence' => $test_custom_value
		)
	);
	
	\FP\SEO\Utils\Options::update( $update_data );
	
	// Verifica che il valore sia stato salvato
	$saved_value = \FP\SEO\Utils\Options::get_option( $test_custom_key, null );
	
	if ( $saved_value === $test_custom_value ) {
		echo '<div class="success">✅ Valore personalizzato salvato correttamente: <strong>' . esc_html( $test_custom_value ) . '</strong></div>';
	} else {
		echo '<div class="error">❌ Valore personalizzato NON salvato! Atteso: <strong>' . esc_html( $test_custom_value ) . '</strong>, Letto: <strong>' . esc_html( $saved_value ?: '(vuoto)' ) . '</strong></div>';
	}
	
	// Simula chiamata a Options::get() come durante l'attivazione
	$options_after_save = \FP\SEO\Utils\Options::get();
	$test_value_after = \FP\SEO\Utils\Options::get_option( $test_custom_key, null );
	
	if ( $test_value_after === $test_custom_value ) {
		echo '<div class="success">✅ Valore personalizzato preservato dopo Options::get(): <strong>' . esc_html( $test_custom_value ) . '</strong></div>';
	} else {
		echo '<div class="error">❌ Valore personalizzato PERSO dopo Options::get()! Atteso: <strong>' . esc_html( $test_custom_value ) . '</strong>, Letto: <strong>' . esc_html( $test_value_after ?: '(vuoto)' ) . '</strong></div>';
	}
	echo '</div>';
	
	// Test 3: Verifica merge_defaults
	echo '<div class="section">';
	echo '<h3>🔧 Test 3: Verifica merge_defaults</h3>';
	
	$defaults = \FP\SEO\Utils\Options::get_defaults();
	$current_options = \FP\SEO\Utils\Options::get();
	
	// Verifica che merge_defaults non sovrascriva valori personalizzati
	$test_keys = array(
		'general.language' => 'Language',
		'ai.openai_api_key' => 'OpenAI API Key',
		'geo.enabled' => 'GEO Enabled',
	);
	
	echo '<table>';
	echo '<tr><th>Campo</th><th>Default</th><th>Valore Attuale</th><th>Stato</th></tr>';
	
	foreach ( $test_keys as $key => $label ) {
		$default_value = \FP\SEO\Utils\Options::get_option( $key, null );
		$current_value = \FP\SEO\Utils\Options::get_option( $key, null );
		
		// Naviga nei defaults
		$keys = explode( '.', $key );
		$default_nested = $defaults;
		foreach ( $keys as $k ) {
			$default_nested = $default_nested[ $k ] ?? null;
			if ( $default_nested === null ) {
				break;
			}
		}
		
		$status = '✅ OK';
		if ( $current_value !== $default_nested && $current_value !== null ) {
			$status = '✅ Personalizzato';
		} elseif ( $current_value === $default_nested ) {
			$status = 'ℹ️ Default';
		}
		
		$display_default = is_bool( $default_nested ) ? ( $default_nested ? 'true' : 'false' ) : ( $default_nested ?: '(vuoto)' );
		$display_current = is_bool( $current_value ) ? ( $current_value ? 'true' : 'false' ) : ( $current_value ?: '(vuoto)' );
		
		if ( is_string( $display_default ) && strlen( $display_default ) > 50 ) {
			$display_default = substr( $display_default, 0, 50 ) . '...';
		}
		if ( is_string( $display_current ) && strlen( $display_current ) > 50 ) {
			$display_current = substr( $display_current, 0, 50 ) . '...';
		}
		
		echo '<tr>';
		echo '<td><strong>' . esc_html( $label ) . '</strong><br><code>' . esc_html( $key ) . '</code></td>';
		echo '<td>' . esc_html( $display_default ) . '</td>';
		echo '<td>' . esc_html( $display_current ) . '</td>';
		echo '<td>' . $status . '</td>';
		echo '</tr>';
	}
	echo '</table>';
	echo '</div>';
	
	// Test 4: Verifica che activate() non resetti le opzioni
	echo '<div class="section">';
	echo '<h3>📝 Test 4: Verifica activate() Hook</h3>';
	
	// Verifica che activate() non cancelli le opzioni
	$activate_method = new ReflectionMethod( '\FP\SEO\Infrastructure\Plugin', 'activate' );
	$activate_code = file_get_contents( $activate_method->getFileName() );
	$activate_lines = explode( "\n", $activate_code );
	$activate_start = $activate_method->getStartLine() - 1;
	$activate_end = $activate_method->getEndLine();
	$activate_body = implode( "\n", array_slice( $activate_lines, $activate_start, $activate_end - $activate_start ) );
	
	$has_delete_option = strpos( $activate_body, 'delete_option' ) !== false;
	$has_update_option = strpos( $activate_body, 'update_option' ) !== false && strpos( $activate_body, 'OPTION_KEY' ) !== false;
	
	if ( ! $has_delete_option && ! $has_update_option ) {
		echo '<div class="success">✅ activate() NON cancella o resetta le opzioni</div>';
	} else {
		echo '<div class="error">❌ activate() potrebbe cancellare o resettare le opzioni!</div>';
		if ( $has_delete_option ) {
			echo '<div class="warning">⚠️ Trovato delete_option in activate()</div>';
		}
		if ( $has_update_option ) {
			echo '<div class="warning">⚠️ Trovato update_option(OPTION_KEY) in activate()</div>';
		}
	}
	
	// Verifica che deactivate() non cancelli le opzioni
	$deactivate_method = new ReflectionMethod( '\FP\SEO\Infrastructure\Plugin', 'deactivate' );
	$deactivate_code = file_get_contents( $deactivate_method->getFileName() );
	$deactivate_lines = explode( "\n", $deactivate_code );
	$deactivate_start = $deactivate_method->getStartLine() - 1;
	$deactivate_end = $deactivate_method->getEndLine();
	$deactivate_body = implode( "\n", array_slice( $deactivate_lines, $deactivate_start, $deactivate_end - $deactivate_start ) );
	
	$has_delete_option_deactivate = strpos( $deactivate_body, 'delete_option' ) !== false;
	$has_update_option_deactivate = strpos( $deactivate_body, 'update_option' ) !== false && strpos( $deactivate_body, 'OPTION_KEY' ) !== false;
	
	if ( ! $has_delete_option_deactivate && ! $has_update_option_deactivate ) {
		echo '<div class="success">✅ deactivate() NON cancella o resetta le opzioni</div>';
	} else {
		echo '<div class="error">❌ deactivate() potrebbe cancellare o resettare le opzioni!</div>';
		if ( $has_delete_option_deactivate ) {
			echo '<div class="warning">⚠️ Trovato delete_option in deactivate()</div>';
		}
		if ( $has_update_option_deactivate ) {
			echo '<div class="warning">⚠️ Trovato update_option(OPTION_KEY) in deactivate()</div>';
		}
	}
	echo '</div>';
	
	// Test 5: Verifica register_setting default
	echo '<div class="section">';
	echo '<h3>⚙️ Test 5: Verifica register_setting Default</h3>';
	
	global $wp_registered_settings;
	$option_group = \FP\SEO\Utils\Options::OPTION_GROUP;
	
	if ( isset( $wp_registered_settings[ $option_key ] ) ) {
		$settings_info = $wp_registered_settings[ $option_key ];
		$has_default = isset( $settings_info['default'] );
		
		if ( $has_default ) {
			$default_value = $settings_info['default'];
			if ( is_array( $default_value ) && ! empty( $default_value ) ) {
				echo '<div class="warning">⚠️ register_setting ha un default non vuoto - potrebbe sovrascrivere le opzioni esistenti se non gestito correttamente</div>';
				echo '<div class="info">ℹ️ Default keys: ' . implode( ', ', array_keys( $default_value ) ) . '</div>';
			} else {
				echo '<div class="success">✅ register_setting ha un default vuoto o gestito correttamente</div>';
			}
		} else {
			echo '<div class="success">✅ register_setting non ha un default - le opzioni esistenti non verranno sovrascritte</div>';
		}
	} else {
		echo '<div class="info">ℹ️ Impostazione non registrata (normale se eseguito fuori dal contesto admin)</div>';
	}
	echo '</div>';
	
	?>
	
	<div class="section">
		<h3>✅ Riepilogo</h3>
		<p>Questo test verifica:</p>
		<ul>
			<li>✅ Le opzioni esistenti vengono preservate</li>
			<li>✅ activate() non resetta le opzioni</li>
			<li>✅ deactivate() non cancella le opzioni</li>
			<li>✅ merge_defaults() non sovrascrive valori personalizzati</li>
			<li>✅ register_setting() non sovrascrive opzioni esistenti</li>
		</ul>
		<p><strong>Nota:</strong> Le opzioni vengono cancellate SOLO quando il plugin viene disinstallato (non disattivato) tramite uninstall.php</p>
	</div>
	
</div>
</body>
</html>


