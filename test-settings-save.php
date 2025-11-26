<?php
/**
 * Test Salvataggio Impostazioni - Verifica che le impostazioni vengano salvate correttamente
 * 
 * IMPORTANTE: Esegui questo file tramite browser!
 * URL: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/test-settings-save.php
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
	<title>Test Salvataggio Impostazioni - FP SEO Manager</title>
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
		.test-form { background: #f9fafb; padding: 15px; border-radius: 4px; margin: 20px 0; }
		.test-form input, .test-form select { margin: 5px; padding: 8px; }
		.test-form button { padding: 10px 20px; background: #2563eb; color: white; border: none; border-radius: 4px; cursor: pointer; }
		.test-form button:hover { background: #1d4ed8; }
	</style>
</head>
<body>
<div class="container">
	<h1>🔍 Test Salvataggio Impostazioni - FP SEO Manager</h1>
	
	<?php
	require_once __DIR__ . '/src/Utils/Options.php';
	
	// Test 1: Verifica lettura opzioni
	echo '<div class="section">';
	echo '<h3>📊 Test 1: Lettura Opzioni</h3>';
	
	$options = \FP\SEO\Utils\Options::get();
	$option_key = \FP\SEO\Utils\Options::OPTION_KEY;
	
	// Verifica diretta dal database
	global $wpdb;
	$db_options = get_option( $option_key, array() );
	
	echo '<table>';
	echo '<tr><th>Metodo</th><th>Valore</th><th>Stato</th></tr>';
	
	$test_keys = array(
		'general.enable_analyzer' => 'Enable Analyzer',
		'general.language' => 'Language',
		'ai.openai_api_key' => 'OpenAI API Key',
		'geo.enabled' => 'GEO Enabled',
	);
	
	foreach ( $test_keys as $key => $label ) {
		$value = \FP\SEO\Utils\Options::get_option( $key, null );
		$status = $value !== null ? '✅ OK' : 'ℹ️ Default';
		$display_value = is_bool( $value ) ? ( $value ? 'true' : 'false' ) : ( $value ?: '(vuoto)' );
		if ( is_string( $display_value ) && strlen( $display_value ) > 50 ) {
			$display_value = substr( $display_value, 0, 50 ) . '...';
		}
		
		echo '<tr>';
		echo '<td><strong>' . esc_html( $label ) . '</strong><br><code>' . esc_html( $key ) . '</code></td>';
		echo '<td>' . esc_html( $display_value ) . '</td>';
		echo '<td>' . $status . '</td>';
		echo '</tr>';
	}
	echo '</table>';
	echo '</div>';
	
	// Test 2: Test salvataggio
	echo '<div class="section">';
	echo '<h3>🧪 Test 2: Test Salvataggio</h3>';
	
	if ( isset( $_POST['test_save'] ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'test_settings_save' ) ) {
		$test_value = sanitize_text_field( $_POST['test_value'] ?? '' );
		$test_key = sanitize_text_field( $_POST['test_key'] ?? 'general.test_setting' );
		
		// Salva usando Options::update
		$keys = explode( '.', $test_key );
		$update_data = array();
		$current = &$update_data;
		
		foreach ( $keys as $k ) {
			if ( $k === end( $keys ) ) {
				$current[ $k ] = $test_value;
			} else {
				$current[ $k ] = array();
				$current = &$current[ $k ];
			}
		}
		
		\FP\SEO\Utils\Options::update( $update_data );
		
		// Verifica che sia stato salvato
		$saved_value = \FP\SEO\Utils\Options::get_option( $test_key, null );
		$db_value = get_option( $option_key, array() );
		
		// Naviga nel DB value
		$db_nested = $db_value;
		foreach ( $keys as $k ) {
			$db_nested = $db_nested[ $k ] ?? null;
			if ( $db_nested === null ) {
				break;
			}
		}
		
		if ( $saved_value === $test_value ) {
			echo '<div class="success">✅ Salvataggio riuscito! Valore salvato: <strong>' . esc_html( $test_value ) . '</strong></div>';
		} else {
			echo '<div class="error">❌ Salvataggio fallito! Valore atteso: <strong>' . esc_html( $test_value ) . '</strong>, Valore letto: <strong>' . esc_html( $saved_value ?: '(vuoto)' ) . '</strong></div>';
		}
		
		echo '<div class="info">ℹ️ DB Value: ' . esc_html( $db_nested ?: '(vuoto)' ) . '</div>';
	}
	
	// Form di test
	echo '<div class="test-form">';
	echo '<form method="post">';
	wp_nonce_field( 'test_settings_save' );
	echo '<input type="hidden" name="test_save" value="1" />';
	echo '<label><strong>Test Key:</strong><br>';
	echo '<input type="text" name="test_key" value="general.test_setting" style="width: 300px;" /></label><br>';
	echo '<label><strong>Test Value:</strong><br>';
	echo '<input type="text" name="test_value" value="test_' . time() . '" style="width: 300px;" /></label><br>';
	echo '<button type="submit">💾 Salva e Verifica</button>';
	echo '</form>';
	echo '</div>';
	echo '</div>';
	
	// Test 3: Verifica Settings API
	echo '<div class="section">';
	echo '<h3>🔧 Test 3: Settings API Registration</h3>';
	
	global $wp_registered_settings;
	$option_key = \FP\SEO\Utils\Options::OPTION_KEY;
	$option_group = \FP\SEO\Utils\Options::OPTION_GROUP;
	
	$is_registered = isset( $wp_registered_settings[ $option_key ] );
	
	if ( $is_registered ) {
		$settings_info = $wp_registered_settings[ $option_key ];
		echo '<div class="success">✅ Impostazione registrata correttamente</div>';
		echo '<table>';
		echo '<tr><th>Proprietà</th><th>Valore</th></tr>';
		echo '<tr><td>Option Key</td><td><code>' . esc_html( $option_key ) . '</code></td></tr>';
		echo '<tr><td>Option Group</td><td><code>' . esc_html( $option_group ) . '</code></td></tr>';
		echo '<tr><td>Type</td><td>' . esc_html( $settings_info['type'] ?? 'N/A' ) . '</td></tr>';
		echo '<tr><td>Has Sanitize Callback</td><td>' . ( isset( $settings_info['sanitize_callback'] ) ? '✅ Sì' : '❌ No' ) . '</td></tr>';
		echo '<tr><td>Has Default</td><td>' . ( isset( $settings_info['default'] ) ? '✅ Sì' : '❌ No' ) . '</td></tr>';
		echo '</table>';
	} else {
		echo '<div class="error">❌ Impostazione NON registrata!</div>';
	}
	echo '</div>';
	
	// Test 4: Verifica cache
	echo '<div class="section">';
	echo '<h3>💾 Test 4: Cache Options</h3>';
	
	require_once __DIR__ . '/src/Utils/Cache.php';
	
	$cache_key = 'options_data';
	$cached = \FP\SEO\Utils\Cache::get( $cache_key );
	
	echo '<table>';
	echo '<tr><th>Proprietà</th><th>Valore</th></tr>';
	echo '<tr><td>Cache Key</td><td><code>' . esc_html( $cache_key ) . '</code></td></tr>';
	echo '<tr><td>Cache Presente</td><td>' . ( $cached !== false ? '✅ Sì' : '❌ No' ) . '</td></tr>';
	if ( $cached !== false ) {
		echo '<tr><td>Cache Type</td><td>' . esc_html( gettype( $cached ) ) . '</td></tr>';
		if ( is_array( $cached ) ) {
			echo '<tr><td>Cache Keys Count</td><td>' . count( $cached ) . '</td></tr>';
		}
	}
	echo '</table>';
	
	// Test: Pulisci cache e verifica
	echo '<div class="test-form">';
	echo '<form method="post">';
	wp_nonce_field( 'test_settings_save' );
	echo '<input type="hidden" name="clear_cache" value="1" />';
	if ( isset( $_POST['clear_cache'] ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'test_settings_save' ) ) {
		\FP\SEO\Utils\Cache::delete( $cache_key );
		echo '<div class="success">✅ Cache pulita!</div>';
	}
	echo '<button type="submit">🗑️ Pulisci Cache</button>';
	echo '</form>';
	echo '</div>';
	echo '</div>';
	
	// Test 5: Verifica salvataggio tramite Settings API
	echo '<div class="section">';
	echo '<h3>📝 Test 5: Verifica Salvataggio Settings API</h3>';
	
	$test_option_value = get_option( $option_key, array() );
	$options_via_get = \FP\SEO\Utils\Options::get();
	
	echo '<table>';
	echo '<tr><th>Metodo</th><th>Keys Count</th><th>Stato</th></tr>';
	echo '<tr><td>get_option() diretto</td><td>' . ( is_array( $test_option_value ) ? count( $test_option_value ) : 'N/A' ) . '</td><td>' . ( is_array( $test_option_value ) ? '✅ OK' : '❌ Non array' ) . '</td></tr>';
	echo '<tr><td>Options::get()</td><td>' . ( is_array( $options_via_get ) ? count( $options_via_get ) : 'N/A' ) . '</td><td>' . ( is_array( $options_via_get ) ? '✅ OK' : '❌ Non array' ) . '</td></tr>';
	echo '</table>';
	
	// Verifica che i valori siano sincronizzati
	$keys_match = true;
	if ( is_array( $test_option_value ) && is_array( $options_via_get ) ) {
		foreach ( array( 'general', 'analysis', 'ai', 'geo' ) as $section ) {
			if ( isset( $test_option_value[ $section ] ) !== isset( $options_via_get[ $section ] ) ) {
				$keys_match = false;
				break;
			}
		}
	}
	
	if ( $keys_match ) {
		echo '<div class="success">✅ I valori sono sincronizzati tra get_option() e Options::get()</div>';
	} else {
		echo '<div class="warning">⚠️ Possibile problema di sincronizzazione tra get_option() e Options::get()</div>';
	}
	echo '</div>';
	
	?>
	
	<div class="section">
		<h3>✅ Riepilogo</h3>
		<p>Questo test verifica:</p>
		<ul>
			<li>✅ Lettura opzioni tramite Options::get()</li>
			<li>✅ Salvataggio opzioni tramite Options::update()</li>
			<li>✅ Registrazione Settings API</li>
			<li>✅ Gestione cache delle opzioni</li>
			<li>✅ Sincronizzazione tra get_option() e Options::get()</li>
		</ul>
	</div>
	
</div>
</body>
</html>





