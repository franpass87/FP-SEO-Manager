<?php
/**
 * Test Script per FP SEO Manager
 * 
 * Esegui questo file per testare tutte le funzionalità del plugin.
 * 
 * METODO 1 - Browser:
 * http://tuo-sito.local/wp-content/plugins/FP-SEO-Manager/test-plugin.php
 * 
 * METODO 2 - WP-CLI:
 * wp eval-file wp-content/plugins/FP-SEO-Manager/test-plugin.php
 * 
 * METODO 3 - Terminale:
 * php wp-content/plugins/FP-SEO-Manager/test-plugin.php
 * 
 * @package FP\SEO
 */

// Carica WordPress se non già caricato
if ( ! defined( 'ABSPATH' ) ) {
	// Determina il percorso corretto basandosi su $_SERVER
	$wp_loaded = false;
	
	// Metodo 1: Usa DOCUMENT_ROOT se disponibile (funziona con web server)
	if ( isset( $_SERVER['DOCUMENT_ROOT'] ) && ! empty( $_SERVER['DOCUMENT_ROOT'] ) ) {
		$doc_root = $_SERVER['DOCUMENT_ROOT'];
		$wp_load = $doc_root . '/wp-load.php';
		
		if ( file_exists( $wp_load ) ) {
			require_once $wp_load;
			$wp_loaded = true;
		}
	}
	
	// Metodo 2: Percorsi relativi standard
	if ( ! $wp_loaded ) {
		$wp_load_paths = array(
			dirname( __FILE__ ) . '/../../../wp-load.php',
			dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php',
			dirname( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) ) . '/wp-load.php',
		);
		
		foreach ( $wp_load_paths as $path ) {
			$real_path = realpath( $path );
			
			if ( $real_path && file_exists( $real_path ) ) {
				require_once $real_path;
				$wp_loaded = true;
				break;
			}
		}
	}
	
	// Metodo 3: Ricerca verso l'alto
	if ( ! $wp_loaded ) {
		$current_dir = dirname( __FILE__ );
		for ( $i = 0; $i < 10; $i++ ) {
			$test_path = $current_dir . '/wp-load.php';
			if ( file_exists( $test_path ) ) {
				require_once $test_path;
				$wp_loaded = true;
				break;
			}
			$current_dir = dirname( $current_dir );
		}
	}
	
	if ( ! $wp_loaded ) {
		$server_info = isset( $_SERVER['DOCUMENT_ROOT'] ) ? $_SERVER['DOCUMENT_ROOT'] : 'N/A';
		$script_path = __FILE__;
		
		echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Errore</title>";
		echo "<style>body{font-family:monospace;background:#1e1e1e;color:#d4d4d4;padding:20px;}";
		echo "h1{color:#dc2626;} .info{color:#2563eb;} .solution{background:#2d2d2d;padding:15px;border-radius:5px;margin:15px 0;border-left:4px solid #059669;}</style></head><body>";
		echo "<h1>❌ Errore: WordPress non trovato</h1>";
		echo "<p style='color:#f59e0b;'>Il file test-plugin.php non riesce a caricare WordPress.</p>";
		
		echo "<div class='solution'>";
		echo "<h2 style='color:#059669;margin-top:0;'>✅ SOLUZIONE FACILE</h2>";
		echo "<p><strong>Usa il menu WordPress Admin:</strong></p>";
		echo "<ol>";
		echo "<li>Vai su: <code style='background:#1e1e1e;padding:2px 6px;'>http://" . htmlspecialchars( $_SERVER['HTTP_HOST'] ?? 'tuo-sito.local' ) . "/wp-admin</code></li>";
		echo "<li>Menu laterale: <strong>FP SEO Performance → Test Suite</strong></li>";
		echo "<li>Clicca il pulsante <strong>\"Esegui Test\"</strong></li>";
		echo "</ol>";
		echo "</div>";
		
		echo "<details style='margin-top:20px;'><summary style='cursor:pointer;color:#2563eb;'>📋 Informazioni Debug</summary>";
		echo "<div style='background:#2d2d2d;padding:10px;margin-top:10px;border-radius:4px;font-size:12px;'>";
		echo "<p class='info'><strong>DOCUMENT_ROOT:</strong> " . htmlspecialchars( $server_info ) . "</p>";
		echo "<p class='info'><strong>Script Path:</strong> " . htmlspecialchars( $script_path ) . "</p>";
		echo "<p class='info'><strong>HTTP Host:</strong> " . htmlspecialchars( $_SERVER['HTTP_HOST'] ?? 'N/A' ) . "</p>";
		echo "</div></details>";
		
		echo "</body></html>";
		exit;
	}
}

/**
 * Test Suite Class
 */
class FP_SEO_Test_Suite {
	
	private $results = array();
	private $passed = 0;
	private $failed = 0;
	private $warnings = 0;
	private $start_time;
	
	public function __construct() {
		$this->start_time = microtime( true );
	}
	
	/**
	 * Run all tests
	 */
	public function run_all_tests() {
		$this->print_header();
		
		try {
			$this->test_plugin_active();
		} catch ( \Exception $e ) {
			$this->fail( 'Plugin activation test failed: ' . $e->getMessage() );
		}
		
		try {
			$this->test_file_structure();
		} catch ( \Exception $e ) {
			$this->fail( 'File structure test failed: ' . $e->getMessage() );
		}
		
		try {
			$this->test_classes_exist();
		} catch ( \Exception $e ) {
			$this->fail( 'Class existence test failed: ' . $e->getMessage() );
		}
		
		try {
			$this->test_autoload();
		} catch ( \Exception $e ) {
			$this->fail( 'Autoload test failed: ' . $e->getMessage() );
		}
		
		try {
			$this->test_options();
		} catch ( \Exception $e ) {
			$this->fail( 'Options test failed: ' . $e->getMessage() );
		}
		
		try {
			$this->test_ai_configuration();
		} catch ( \Exception $e ) {
			$this->fail( 'AI configuration test failed: ' . $e->getMessage() );
		}
		
		try {
			$this->test_assets_registered();
		} catch ( \Exception $e ) {
			$this->fail( 'Assets registration test failed: ' . $e->getMessage() );
		}
		
		try {
			$this->test_ajax_endpoints();
		} catch ( \Exception $e ) {
			$this->fail( 'AJAX endpoints test failed: ' . $e->getMessage() );
		}
		
		try {
			$this->test_admin_pages();
		} catch ( \Exception $e ) {
			$this->fail( 'Admin pages test failed: ' . $e->getMessage() );
		}
		
		try {
			$this->test_openai_client();
		} catch ( \Exception $e ) {
			$this->fail( 'OpenAI client test failed: ' . $e->getMessage() );
		}
		
		try {
			$this->test_metabox_rendering();
		} catch ( \Exception $e ) {
			$this->fail( 'Metabox rendering test failed: ' . $e->getMessage() );
		}
		
		try {
			$this->test_javascript_files();
		} catch ( \Exception $e ) {
			$this->fail( 'JavaScript files test failed: ' . $e->getMessage() );
		}
		
		$this->print_summary();
	}
	
	/**
	 * Test: Plugin è attivo
	 */
	private function test_plugin_active() {
		$this->section( 'PLUGIN ACTIVATION' );
		
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		
		$plugin_file = 'FP-SEO-Manager/fp-seo-performance.php';
		$is_active = is_plugin_active( $plugin_file );
		
		$this->assert( $is_active, 'Plugin FP SEO Performance è attivo', 'Plugin NON attivo' );
		
		if ( $is_active ) {
			$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file );
			$this->info( 'Nome: ' . $plugin_data['Name'] );
			$this->info( 'Versione: ' . $plugin_data['Version'] );
			$this->info( 'Autore: ' . $plugin_data['Author'] );
		}
	}
	
	/**
	 * Test: Struttura file
	 */
	private function test_file_structure() {
		$this->section( 'FILE STRUCTURE' );
		
		$plugin_dir = WP_PLUGIN_DIR . '/FP-SEO-Manager';
		
		$required_files = array(
			'fp-seo-performance.php' => 'Main plugin file',
			'composer.json' => 'Composer config',
			'vendor/autoload.php' => 'Composer autoload',
			'src/Infrastructure/Plugin.php' => 'Plugin bootstrap',
			'src/Integrations/OpenAiClient.php' => 'OpenAI client',
			'src/Admin/AiSettings.php' => 'AI settings',
			'src/Admin/AiAjaxHandler.php' => 'AI AJAX handler',
			'src/Admin/Settings/AiTabRenderer.php' => 'AI tab renderer',
			'src/Utils/Options.php' => 'Options manager',
			'src/Utils/Assets.php' => 'Assets manager',
			'assets/admin/js/ai-generator.js' => 'AI generator JS',
		);
		
		foreach ( $required_files as $file => $description ) {
			$path = $plugin_dir . '/' . $file;
			$exists = file_exists( $path );
			$this->assert( $exists, "$description: $file", "MANCANTE: $file" );
			
			if ( $exists && strpos( $file, '.php' ) !== false ) {
				$size = filesize( $path );
				$this->info( "  └─ Size: " . $this->format_bytes( $size ) );
			}
		}
	}
	
	/**
	 * Test: Classi esistono
	 */
	private function test_classes_exist() {
		$this->section( 'CLASS EXISTENCE' );
		
		$required_classes = array(
			'FP\SEO\Infrastructure\Plugin' => 'Plugin bootstrap',
			'FP\SEO\Integrations\OpenAiClient' => 'OpenAI client',
			'FP\SEO\Admin\AiSettings' => 'AI settings',
			'FP\SEO\Admin\AiAjaxHandler' => 'AI AJAX handler',
			'FP\SEO\Admin\Settings\AiTabRenderer' => 'AI tab renderer',
			'FP\SEO\Utils\Options' => 'Options manager',
			'FP\SEO\Utils\Assets' => 'Assets manager',
		);
		
		foreach ( $required_classes as $class => $description ) {
			$exists = class_exists( $class );
			$this->assert( $exists, "$description: $class", "CLASSE NON TROVATA: $class" );
		}
	}
	
	/**
	 * Test: Autoload funziona
	 */
	private function test_autoload() {
		$this->section( 'PSR-4 AUTOLOAD' );
		
		// Verifica vendor autoload
		$vendor_autoload = WP_PLUGIN_DIR . '/FP-SEO-Manager/vendor/autoload.php';
		$this->assert( file_exists( $vendor_autoload ), 'Vendor autoload esiste', 'Vendor autoload MANCANTE' );
		
		// Verifica OpenAI SDK
		$openai_installed = class_exists( 'OpenAI\Client' );
		$this->assert( $openai_installed, 'OpenAI PHP SDK installato', 'OpenAI SDK NON installato (run: composer install)' );
		
		if ( $openai_installed ) {
			$this->info( '  └─ OpenAI SDK version: ' . ( defined( 'OpenAI\VERSION' ) ? constant( 'OpenAI\VERSION' ) : 'N/A' ) );
		}
	}
	
	/**
	 * Test: Options e defaults
	 */
	private function test_options() {
		$this->section( 'OPTIONS & DEFAULTS' );
		
		// Get defaults
		$defaults = \FP\SEO\Utils\Options::get_defaults();
		$this->assert( ! empty( $defaults ), 'Defaults caricati', 'Defaults VUOTI' );
		
		// Verifica sezione AI
		$this->assert( isset( $defaults['ai'] ), 'Sezione AI nei defaults', 'Sezione AI MANCANTE' );
		
		if ( isset( $defaults['ai'] ) ) {
			$ai_defaults = $defaults['ai'];
			
			$this->assert( 
				isset( $ai_defaults['openai_model'] ), 
				'openai_model definito', 
				'openai_model MANCANTE' 
			);
			
			$default_model = $ai_defaults['openai_model'] ?? '';
			$this->assert( 
				$default_model === 'gpt-5-nano', 
				"Default model: gpt-5-nano (attuale: $default_model)", 
				"Default model SBAGLIATO: $default_model (dovrebbe essere gpt-5-nano)" 
			);
			
			$this->info( '  AI Defaults:' );
			foreach ( $ai_defaults as $key => $value ) {
				$value_str = is_bool( $value ) ? ( $value ? 'true' : 'false' ) : $value;
				$this->info( "    - $key: $value_str" );
			}
		}
		
		// Get current options
		$options = \FP\SEO\Utils\Options::get();
		$this->assert( ! empty( $options ), 'Options caricate', 'Options VUOTE' );
	}
	
	/**
	 * Test: Configurazione AI
	 */
	private function test_ai_configuration() {
		$this->section( 'AI CONFIGURATION' );
		
		$api_key = \FP\SEO\Utils\Options::get_option( 'ai.openai_api_key', '' );
		$model = \FP\SEO\Utils\Options::get_option( 'ai.openai_model', '' );
		$enabled = \FP\SEO\Utils\Options::get_option( 'ai.enable_auto_generation', false );
		
		if ( empty( $api_key ) ) {
			$this->warning( 'API Key OpenAI NON configurata (configura in Settings > AI)' );
		} else {
			$masked_key = substr( $api_key, 0, 7 ) . '...' . substr( $api_key, -4 );
			$this->pass( "API Key configurata: $masked_key" );
		}
		
		$this->info( "Modello: $model" );
		$this->info( 'Generazione auto: ' . ( $enabled ? 'Abilitata' : 'Disabilitata' ) );
		
		// Test OpenAI client initialization
		$client = new \FP\SEO\Integrations\OpenAiClient();
		$is_configured = $client->is_configured();
		
		$this->assert( 
			$is_configured, 
			'OpenAI Client configurato correttamente', 
			'OpenAI Client NON configurato (serve API key)' 
		);
	}
	
	/**
	 * Test: Assets registrati
	 */
	private function test_assets_registered() {
		$this->section( 'ASSETS REGISTRATION' );
		
		global $wp_scripts, $wp_styles;
		
		$required_scripts = array(
			'fp-seo-performance-admin' => 'Admin script',
			'fp-seo-performance-editor' => 'Editor script',
			'fp-seo-performance-ai-generator' => 'AI generator script',
		);
		
		foreach ( $required_scripts as $handle => $description ) {
			$registered = wp_script_is( $handle, 'registered' );
			$this->assert( $registered, "$description registrato: $handle", "Script NON registrato: $handle" );
			
			if ( $registered && isset( $wp_scripts->registered[ $handle ] ) ) {
				$script = $wp_scripts->registered[ $handle ];
				if ( isset( $script->src ) ) {
					$this->info( "  └─ Source: " . basename( $script->src ) );
				}
			}
		}
		
		$required_styles = array(
			'fp-seo-performance-admin' => 'Admin styles',
		);
		
		foreach ( $required_styles as $handle => $description ) {
			$registered = wp_style_is( $handle, 'registered' );
			$this->assert( $registered, "$description registrato: $handle", "Style NON registrato: $handle" );
		}
	}
	
	/**
	 * Test: AJAX endpoints
	 */
	private function test_ajax_endpoints() {
		$this->section( 'AJAX ENDPOINTS' );
		
		global $wp_filter;
		
		$ajax_action = 'fp_seo_generate_ai_content';
		$hook_name = "wp_ajax_$ajax_action";
		
		$registered = isset( $wp_filter[ $hook_name ] ) && ! empty( $wp_filter[ $hook_name ]->callbacks );
		
		$this->assert( 
			$registered, 
			"AJAX endpoint registrato: $ajax_action", 
			"AJAX endpoint NON registrato: $ajax_action" 
		);
		
		if ( $registered ) {
			$callbacks = $wp_filter[ $hook_name ]->callbacks;
			$count = 0;
			foreach ( $callbacks as $priority => $handlers ) {
				$count += count( $handlers );
			}
			$this->info( "  └─ Callbacks registrate: $count" );
		}
	}
	
	/**
	 * Test: Pagine admin
	 */
	private function test_admin_pages() {
		$this->section( 'ADMIN PAGES' );
		
		global $menu, $submenu;
		
		// Force menu registration
		if ( ! did_action( 'admin_menu' ) ) {
			do_action( 'admin_menu' );
		}
		
		// Cerca menu principale
		$menu_found = false;
		if ( is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( isset( $item[2] ) && $item[2] === 'fp-seo-performance' ) {
					$menu_found = true;
					$this->pass( 'Menu principale "FP SEO Performance" registrato' );
					break;
				}
			}
		}
		
		if ( ! $menu_found ) {
			$this->fail( 'Menu principale NON trovato' );
		}
		
		// Cerca submenu Settings
		$settings_found = false;
		if ( isset( $submenu['fp-seo-performance'] ) ) {
			foreach ( $submenu['fp-seo-performance'] as $item ) {
				if ( isset( $item[2] ) && strpos( $item[2], 'settings' ) !== false ) {
					$settings_found = true;
					$this->pass( 'Submenu "Settings" registrato' );
					break;
				}
			}
		}
		
		if ( ! $settings_found ) {
			$this->warning( 'Submenu Settings non trovato (potrebbe essere normale se admin_menu non è stato triggerato)' );
		}
	}
	
	/**
	 * Test: OpenAI Client functionality
	 */
	private function test_openai_client() {
		$this->section( 'OPENAI CLIENT FUNCTIONALITY' );
		
		$client = new \FP\SEO\Integrations\OpenAiClient();
		
		// Test is_configured
		$is_configured = $client->is_configured();
		$this->info( 'is_configured(): ' . ( $is_configured ? 'true' : 'false' ) );
		
		// Test con dati mock (senza chiamata API reale)
		if ( $is_configured ) {
			$this->info( 'Client pronto per generazione (non testiamo chiamata API reale per evitare costi)' );
			$this->pass( 'OpenAI Client funzionale' );
		} else {
			$this->warning( 'OpenAI Client non configurato (serve API key per test completi)' );
		}
		
		// Verifica metodi esistono
		$methods = array( 'is_configured', 'generate_seo_suggestions' );
		foreach ( $methods as $method ) {
			$exists = method_exists( $client, $method );
			$this->assert( $exists, "Metodo $method esiste", "Metodo $method MANCANTE" );
		}
	}
	
	/**
	 * Test: Metabox rendering (simulato)
	 */
	private function test_metabox_rendering() {
		$this->section( 'METABOX RENDERING' );
		
		// Crea un post temporaneo per test
		$test_post_id = wp_insert_post( array(
			'post_title' => 'Test Post for FP SEO',
			'post_content' => 'This is a test post content for SEO analysis.',
			'post_status' => 'draft',
			'post_type' => 'post',
		) );
		
		if ( is_wp_error( $test_post_id ) ) {
			$this->fail( 'Impossibile creare post di test' );
			return;
		}
		
		$this->pass( "Post di test creato (ID: $test_post_id)" );
		
		$metabox = new \FP\SEO\Editor\Metabox();
		
		// Verifica metodi esistono
		$methods = array( 'register', 'add_meta_box', 'render', 'enqueue_assets' );
		foreach ( $methods as $method ) {
			$exists = method_exists( $metabox, $method );
			$this->assert( $exists, "Metabox metodo $method esiste", "Metodo $method MANCANTE" );
		}
		
		// Pulisci
		wp_delete_post( $test_post_id, true );
		$this->info( "  └─ Post di test eliminato" );
	}
	
	/**
	 * Test: File JavaScript
	 */
	private function test_javascript_files() {
		$this->section( 'JAVASCRIPT FILES' );
		
		$plugin_dir = WP_PLUGIN_DIR . '/FP-SEO-Manager';
		$js_file = $plugin_dir . '/assets/admin/js/ai-generator.js';
		
		$exists = file_exists( $js_file );
		$this->assert( $exists, 'ai-generator.js esiste', 'ai-generator.js MANCANTE' );
		
		if ( $exists ) {
			$content = file_get_contents( $js_file );
			$size = filesize( $js_file );
			
			$this->info( "  └─ Size: " . $this->format_bytes( $size ) );
			
			// Verifica funzioni chiave
			$required_functions = array(
				'handleGenerate',
				'handleApply',
				'handleCopy',
				'updateCharCount',
				'getPostContent',
				'getPostTitle',
			);
			
			foreach ( $required_functions as $func ) {
				$found = strpos( $content, $func ) !== false;
				$this->assert( $found, "Funzione JS $func presente", "Funzione $func MANCANTE" );
			}
			
			// Verifica chiamata AJAX
			$has_ajax = strpos( $content, 'fp_seo_generate_ai_content' ) !== false;
			$this->assert( $has_ajax, 'AJAX call presente', 'AJAX call MANCANTE' );
		}
	}
	
	/**
	 * Assert helper
	 */
	private function assert( $condition, $pass_msg, $fail_msg ) {
		if ( $condition ) {
			$this->pass( $pass_msg );
		} else {
			$this->fail( $fail_msg );
		}
	}
	
	/**
	 * Pass
	 */
	private function pass( $message ) {
		$this->passed++;
		$this->results[] = array( 'type' => 'pass', 'message' => $message );
		$this->output( '✓', $message, 'green' );
	}
	
	/**
	 * Fail
	 */
	private function fail( $message ) {
		$this->failed++;
		$this->results[] = array( 'type' => 'fail', 'message' => $message );
		$this->output( '✗', $message, 'red' );
	}
	
	/**
	 * Warning
	 */
	private function warning( $message ) {
		$this->warnings++;
		$this->results[] = array( 'type' => 'warning', 'message' => $message );
		$this->output( '⚠', $message, 'yellow' );
	}
	
	/**
	 * Info
	 */
	private function info( $message ) {
		$this->results[] = array( 'type' => 'info', 'message' => $message );
		$this->output( 'ℹ', $message, 'blue' );
	}
	
	/**
	 * Section header
	 */
	private function section( $title ) {
		$this->output( '', '', '' );
		$this->output( '═══', $title, 'cyan', true );
	}
	
	/**
	 * Output with color
	 */
	private function output( $icon, $message, $color = '', $bold = false ) {
		$is_cli = php_sapi_name() === 'cli';
		
		if ( $is_cli ) {
			// CLI colors
			$colors = array(
				'green' => "\033[0;32m",
				'red' => "\033[0;31m",
				'yellow' => "\033[1;33m",
				'blue' => "\033[0;34m",
				'cyan' => "\033[0;36m",
				'reset' => "\033[0m",
				'bold' => "\033[1m",
			);
			
			$output = '';
			if ( $bold && isset( $colors['bold'] ) ) {
				$output .= $colors['bold'];
			}
			if ( ! empty( $color ) && isset( $colors[ $color ] ) ) {
				$output .= $colors[ $color ];
			}
			$output .= $icon . ' ' . $message;
			if ( ! empty( $color ) || $bold ) {
				$output .= $colors['reset'];
			}
			echo $output . "\n";
		} else {
			// HTML output
			$color_map = array(
				'green' => '#059669',
				'red' => '#dc2626',
				'yellow' => '#f59e0b',
				'blue' => '#2563eb',
				'cyan' => '#06b6d4',
			);
			
			$style = 'margin: 2px 0; padding: 2px 0;';
			if ( ! empty( $color ) && isset( $color_map[ $color ] ) ) {
				$style .= ' color: ' . $color_map[ $color ] . ';';
			}
			if ( $bold ) {
				$style .= ' font-weight: bold; font-size: 14px;';
			}
			
			echo '<div style="' . $style . '">' . esc_html( $icon . ' ' . $message ) . '</div>';
		}
	}
	
	/**
	 * Print header
	 */
	private function print_header() {
		$this->output( '', '', '' );
		$this->output( '═════════════════════════════════════════════════', '', 'cyan', true );
		$this->output( '   FP SEO MANAGER - TEST SUITE', '', 'cyan', true );
		$this->output( '═════════════════════════════════════════════════', '', 'cyan', true );
		$this->output( '', '', '' );
		$this->info( 'WordPress Version: ' . get_bloginfo( 'version' ) );
		$this->info( 'PHP Version: ' . PHP_VERSION );
		$this->info( 'Ambiente: ' . ( wp_get_environment_type() ) );
		$this->info( 'Data: ' . date( 'Y-m-d H:i:s' ) );
	}
	
	/**
	 * Print summary
	 */
	private function print_summary() {
		$execution_time = microtime( true ) - $this->start_time;
		
		$this->output( '', '', '' );
		$this->output( '═════════════════════════════════════════════════', '', 'cyan', true );
		$this->output( '   SUMMARY', '', 'cyan', true );
		$this->output( '═════════════════════════════════════════════════', '', 'cyan', true );
		$this->output( '', '', '' );
		
		$total = $this->passed + $this->failed + $this->warnings;
		
		$this->output( '✓', "Passed: {$this->passed}", 'green' );
		$this->output( '✗', "Failed: {$this->failed}", 'red' );
		$this->output( '⚠', "Warnings: {$this->warnings}", 'yellow' );
		$this->output( '', "Total: $total", '' );
		$this->output( '', sprintf( 'Execution time: %.2f seconds', $execution_time ), 'blue' );
		
		$this->output( '', '', '' );
		
		if ( $this->failed === 0 ) {
			$this->output( '🎉', 'TUTTI I TEST PASSATI! Plugin funzionante!', 'green', true );
		} elseif ( $this->failed < 3 ) {
			$this->output( '⚠', 'Alcuni test falliti. Verifica i dettagli sopra.', 'yellow', true );
		} else {
			$this->output( '✗', 'MOLTI TEST FALLITI! Verifica la configurazione.', 'red', true );
		}
		
		$this->output( '═════════════════════════════════════════════════', '', 'cyan', true );
	}
	
	/**
	 * Format bytes
	 */
	private function format_bytes( $bytes, $precision = 2 ) {
		$units = array( 'B', 'KB', 'MB', 'GB' );
		$bytes = max( $bytes, 0 );
		$pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow = min( $pow, count( $units ) - 1 );
		$bytes /= pow( 1024, $pow );
		return round( $bytes, $precision ) . ' ' . $units[ $pow ];
	}
}

// Esegui i test solo se chiamato direttamente (non via AJAX include)
if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
	$test_suite = new FP_SEO_Test_Suite();
	$test_suite->run_all_tests();

	// Output HTML finale se non CLI
	if ( php_sapi_name() !== 'cli' ) {
		echo '<style>body { font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; }</style>';
	}
} else {
	// Chiamato via AJAX - esegui test e ritorna output
	$test_suite = new FP_SEO_Test_Suite();
	$test_suite->run_all_tests();
}

