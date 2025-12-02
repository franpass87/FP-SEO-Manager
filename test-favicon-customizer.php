<?php
/**
 * Test Favicon Customizer
 * 
 * Verifica che la favicon funzioni correttamente nel customizer preview
 * 
 * Esegui questo file nel browser: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/test-favicon-customizer.php
 */

// Load WordPress
// Try multiple paths to find wp-load.php
$wp_load_paths = array(
	__DIR__ . '/../../../../wp-load.php', // Standard path
	dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/wp-load.php', // Alternative
	$_SERVER['DOCUMENT_ROOT'] . '/wp-load.php', // From document root
);

$wp_loaded = false;
foreach ( $wp_load_paths as $path ) {
	if ( file_exists( $path ) ) {
		require_once $path;
		$wp_loaded = true;
		break;
	}
}

if ( ! $wp_loaded ) {
	// Try to find WordPress root
	$current_dir = __DIR__;
	$max_levels = 10;
	$level = 0;
	
	while ( $level < $max_levels ) {
		$wp_load = $current_dir . '/wp-load.php';
		if ( file_exists( $wp_load ) ) {
			require_once $wp_load;
			$wp_loaded = true;
			break;
		}
		$current_dir = dirname( $current_dir );
		$level++;
	}
}

if ( ! $wp_loaded ) {
	die( 'Errore: Impossibile trovare wp-load.php. Assicurati che WordPress sia installato correttamente.' );
}

// Check if user is logged in and is admin
if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
	wp_die( 'Accesso negato. Devi essere loggato come amministratore.' );
}

?>
<!DOCTYPE html>
<html>
<head>
	<title>Test Favicon Customizer - FP SEO Manager</title>
	<style>
		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			max-width: 1200px;
			margin: 40px auto;
			padding: 20px;
			background: #f5f5f5;
		}
		.test-section {
			background: white;
			padding: 20px;
			margin: 20px 0;
			border-radius: 8px;
			box-shadow: 0 2px 4px rgba(0,0,0,0.1);
		}
		.test-section h2 {
			margin-top: 0;
			color: #333;
		}
		.success { color: #10b981; }
		.error { color: #ef4444; }
		.warning { color: #f59e0b; }
		.info { color: #3b82f6; }
		.code {
			background: #f3f4f6;
			padding: 15px;
			border-radius: 4px;
			font-family: monospace;
			overflow-x: auto;
			margin: 10px 0;
		}
		.favicon-preview {
			display: inline-block;
			width: 64px;
			height: 64px;
			border: 2px solid #e5e7eb;
			border-radius: 8px;
			margin: 10px;
			padding: 10px;
			background: white;
			text-align: center;
		}
		.favicon-preview img {
			max-width: 100%;
			max-height: 100%;
		}
		.button {
			display: inline-block;
			padding: 10px 20px;
			background: #3b82f6;
			color: white;
			text-decoration: none;
			border-radius: 4px;
			margin: 10px 5px;
		}
		.button:hover {
			background: #2563eb;
		}
	</style>
</head>
<body>
	<h1>🧪 Test Favicon Customizer - FP SEO Manager</h1>

	<?php
	// Test 1: Verifica funzioni WordPress
	?>
	<div class="test-section">
		<h2>1. Verifica Funzioni WordPress</h2>
		<?php
		$functions = array(
			'has_site_icon' => function_exists( 'has_site_icon' ),
			'get_site_icon_url' => function_exists( 'get_site_icon_url' ),
			'is_customize_preview' => function_exists( 'is_customize_preview' ),
			'get_option' => function_exists( 'get_option' ),
			'wp_get_attachment_url' => function_exists( 'wp_get_attachment_url' ),
		);

		$all_ok = true;
		foreach ( $functions as $func => $exists ) {
			$status = $exists ? 'success' : 'error';
			$icon = $exists ? '✅' : '❌';
			echo "<p class='{$status}'>{$icon} <code>{$func}()</code>: " . ( $exists ? 'Disponibile' : 'NON disponibile' ) . "</p>";
			if ( ! $exists ) {
				$all_ok = false;
			}
		}

		if ( $all_ok ) {
			echo "<p class='success'><strong>✅ Tutte le funzioni WordPress sono disponibili</strong></p>";
		} else {
			echo "<p class='error'><strong>❌ Alcune funzioni WordPress non sono disponibili</strong></p>";
		}
		?>
	</div>

	<?php
	// Test 2: Verifica Site Icon
	?>
	<div class="test-section">
		<h2>2. Verifica Site Icon (Favicon)</h2>
		<?php
		$site_icon_id = get_option( 'site_icon', 0 );
		$has_icon = has_site_icon();
		$icon_url = get_site_icon_url( 512 );
		$icon_url_32 = get_site_icon_url( 32 );
		$icon_url_192 = get_site_icon_url( 192 );

		echo "<p><strong>Site Icon ID:</strong> " . ( $site_icon_id ? $site_icon_id : 'Nessuno impostato' ) . "</p>";
		echo "<p><strong>has_site_icon():</strong> " . ( $has_icon ? '✅ True' : '❌ False' ) . "</p>";
		echo "<p><strong>Icon URL (512px):</strong> " . ( $icon_url ? $icon_url : 'Nessuna URL' ) . "</p>";
		echo "<p><strong>Icon URL (32px):</strong> " . ( $icon_url_32 ? $icon_url_32 : 'Nessuna URL' ) . "</p>";
		echo "<p><strong>Icon URL (192px):</strong> " . ( $icon_url_192 ? $icon_url_192 : 'Nessuna URL' ) . "</p>";

		if ( $site_icon_id && ! $icon_url ) {
			// Try direct attachment URL
			$direct_url = wp_get_attachment_url( $site_icon_id );
			echo "<p class='warning'><strong>⚠️ get_site_icon_url() restituisce vuoto, ma provo URL diretto:</strong> " . ( $direct_url ? $direct_url : 'Anche questo è vuoto' ) . "</p>";
			if ( $direct_url ) {
				$icon_url = $direct_url;
			}
		}

		if ( $icon_url ) {
			echo "<div class='favicon-preview'>";
			echo "<p><strong>Preview 512px:</strong></p>";
			echo "<img src='" . esc_url( $icon_url ) . "' alt='Favicon Preview' />";
			echo "</div>";
		} else {
			echo "<p class='warning'>⚠️ Nessuna favicon impostata. Vai in <strong>Impostazioni → Generale → Site Icon</strong> per impostarla.</p>";
		}
		?>
	</div>

	<?php
	// Test 3: Simula Customizer Preview Mode
	?>
	<div class="test-section">
		<h2>3. Simula Customizer Preview Mode</h2>
		<?php
		// Simula il codice di SiteJson.php
		$is_customizer_preview = function_exists( 'is_customize_preview' ) && is_customize_preview();
		$site_icon_url_test = '';

		if ( $is_customizer_preview ) {
			$site_icon_id_test = get_option( 'site_icon', 0 );
			if ( $site_icon_id_test ) {
				$site_icon_url_test = function_exists( 'get_site_icon_url' ) ? get_site_icon_url( 512 ) : '';
				if ( empty( $site_icon_url_test ) && function_exists( 'wp_get_attachment_url' ) ) {
					$site_icon_url_test = wp_get_attachment_url( $site_icon_id_test );
				}
			}
		} elseif ( function_exists( 'has_site_icon' ) && has_site_icon() ) {
			$site_icon_url_test = function_exists( 'get_site_icon_url' ) ? get_site_icon_url( 512 ) : '';
		}

		echo "<p><strong>is_customize_preview():</strong> " . ( $is_customizer_preview ? '✅ True (siamo in customizer)' : '❌ False (non siamo in customizer)' ) . "</p>";
		echo "<p><strong>Site Icon URL (test logica):</strong> " . ( $site_icon_url_test ? $site_icon_url_test : 'Nessuna URL' ) . "</p>";

		if ( ! $is_customizer_preview ) {
			echo "<p class='info'>ℹ️ <strong>Nota:</strong> Questo test viene eseguito FUORI dal customizer. Per testare in customizer preview, apri il customizer e verifica che la favicon appaia correttamente.</p>";
		}
		?>
	</div>

	<?php
	// Test 4: Verifica GEO Site JSON
	?>
	<div class="test-section">
		<h2>4. Verifica GEO Site JSON</h2>
		<?php
		if ( class_exists( 'FP\SEO\GEO\SiteJson' ) ) {
			try {
				$site_json = new \FP\SEO\GEO\SiteJson();
				$data = $site_json->generate();
				
				echo "<p class='success'>✅ Classe SiteJson trovata e istanziata</p>";
				echo "<p><strong>Site Icon nel JSON:</strong> " . ( ! empty( $data['site']['icon'] ) ? $data['site']['icon'] : 'Nessuna icon' ) . "</p>";
				
				if ( ! empty( $data['site']['icon'] ) ) {
					echo "<div class='favicon-preview'>";
					echo "<p><strong>Preview da JSON:</strong></p>";
					echo "<img src='" . esc_url( $data['site']['icon'] ) . "' alt='Favicon da JSON' />";
					echo "</div>";
				} else {
					echo "<p class='warning'>⚠️ La favicon non è presente nel JSON generato</p>";
				}

				echo "<div class='code'>";
				echo "<strong>JSON completo (site section):</strong><br>";
				echo "<pre>" . esc_html( json_encode( $data['site'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) . "</pre>";
				echo "</div>";
			} catch ( \Throwable $e ) {
				echo "<p class='error'>❌ Errore durante la generazione del JSON: " . esc_html( $e->getMessage() ) . "</p>";
			}
		} else {
			echo "<p class='error'>❌ Classe SiteJson non trovata</p>";
		}
		?>
	</div>

	<?php
	// Test 5: Verifica Cache
	?>
	<div class="test-section">
		<h2>5. Verifica Cache</h2>
		<?php
		$cache_key = 'fp_seo_geo_site_json';
		$cached = get_transient( $cache_key );
		
		echo "<p><strong>Cache Key:</strong> <code>{$cache_key}</code></p>";
		echo "<p><strong>Cache presente:</strong> " . ( $cached !== false ? '✅ Sì' : '❌ No' ) . "</p>";
		
		if ( $cached !== false ) {
			echo "<p class='info'>ℹ️ Cache trovata. La cache viene disabilitata in customizer preview mode.</p>";
		}
		?>
	</div>

	<?php
	// Test 6: Link utili
	?>
	<div class="test-section">
		<h2>6. Link Utili per Test</h2>
		<p>
			<a href="<?php echo esc_url( admin_url( 'customize.php' ) ); ?>" class="button" target="_blank">
				🎨 Apri Customizer
			</a>
			<a href="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>" class="button" target="_blank">
				⚙️ Impostazioni → Generale (Site Icon)
			</a>
			<a href="<?php echo esc_url( home_url( '/geo/site.json' ) ); ?>" class="button" target="_blank">
				📄 Visualizza GEO Site JSON
			</a>
		</p>
		<p class="info">
			<strong>Istruzioni per testare nel customizer:</strong><br>
			1. Clicca su "Apri Customizer"<br>
			2. Vai su "Identità del sito"<br>
			3. Modifica la "Site Icon" (favicon)<br>
			4. Verifica che la preview si aggiorni correttamente<br>
			5. Verifica che il GEO Site JSON mostri la nuova favicon
		</p>
	</div>

	<div class="test-section">
		<h2>📊 Riepilogo</h2>
		<?php
		$all_tests_passed = $all_ok && ( $icon_url || ! $site_icon_id );
		
		if ( $all_tests_passed ) {
			echo "<p class='success'><strong>✅ Tutti i test base sono passati!</strong></p>";
			echo "<p>Per testare completamente la funzionalità nel customizer, apri il customizer e modifica la Site Icon.</p>";
		} else {
			echo "<p class='error'><strong>❌ Alcuni test non sono passati. Controlla i dettagli sopra.</strong></p>";
		}
		?>
	</div>
</body>
</html>

