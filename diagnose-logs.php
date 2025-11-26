<?php
/**
 * Analizzatore Log FP SEO Manager
 * 
 * Analizza i log di debug.log per trovare errori, warning e pattern
 * 
 * @package FP\SEO
 */

// Solo browser
if ( php_sapi_name() === 'cli' ) {
	die( "Esegui via browser: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/diagnose-logs.php\n" );
}

// Carica WordPress
$wp_load_paths = array(
	__DIR__ . '/../../../../wp-load.php',
	dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/wp-load.php',
);

$wp_load = null;
foreach ( $wp_load_paths as $path ) {
	if ( file_exists( $path ) ) {
		$wp_load = $path;
		break;
	}
}

if ( ! $wp_load || ! file_exists( $wp_load ) ) {
	die( "<h1>ERRORE: wp-load.php non trovato</h1>" );
}

require_once $wp_load;

?>
<!DOCTYPE html>
<html lang="it">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Analisi Log FP SEO Manager</title>
	<style>
		* { margin: 0; padding: 0; box-sizing: border-box; }
		body {
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
			background: #1f2937;
			color: #f9fafb;
			padding: 20px;
		}
		.container {
			max-width: 1400px;
			margin: 0 auto;
			background: #111827;
			border-radius: 8px;
			padding: 30px;
		}
		.header {
			text-align: center;
			margin-bottom: 30px;
			padding-bottom: 20px;
			border-bottom: 2px solid #374151;
		}
		.header h1 {
			font-size: 32px;
			margin-bottom: 10px;
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			-webkit-background-clip: text;
			-webkit-text-fill-color: transparent;
		}
		.filters {
			display: flex;
			gap: 10px;
			margin-bottom: 20px;
			flex-wrap: wrap;
		}
		.filter-btn {
			padding: 8px 16px;
			background: #374151;
			border: 1px solid #4b5563;
			color: #f9fafb;
			border-radius: 6px;
			cursor: pointer;
			transition: all 0.2s;
		}
		.filter-btn:hover {
			background: #4b5563;
		}
		.filter-btn.active {
			background: #3b82f6;
			border-color: #3b82f6;
		}
		.stats {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
			gap: 15px;
			margin-bottom: 30px;
		}
		.stat-card {
			background: #1f2937;
			padding: 20px;
			border-radius: 8px;
			border-left: 4px solid #3b82f6;
		}
		.stat-card.error { border-left-color: #ef4444; }
		.stat-card.warning { border-left-color: #f59e0b; }
		.stat-card.success { border-left-color: #10b981; }
		.stat-value {
			font-size: 36px;
			font-weight: 700;
			margin-bottom: 5px;
		}
		.stat-label {
			font-size: 14px;
			opacity: 0.7;
		}
		.log-container {
			background: #0f172a;
			border-radius: 8px;
			padding: 20px;
			max-height: 600px;
			overflow-y: auto;
		}
		.log-entry {
			padding: 10px;
			margin-bottom: 5px;
			border-left: 3px solid #374151;
			border-radius: 4px;
			font-family: 'Courier New', monospace;
			font-size: 12px;
			line-height: 1.6;
		}
		.log-entry.error {
			background: #7f1d1d;
			border-left-color: #ef4444;
			color: #fca5a5;
		}
		.log-entry.warning {
			background: #78350f;
			border-left-color: #f59e0b;
			color: #fcd34d;
		}
		.log-entry.success {
			background: #064e3b;
			border-left-color: #10b981;
			color: #6ee7b7;
		}
		.log-entry.info {
			background: #1e3a8a;
			border-left-color: #3b82f6;
			color: #93c5fd;
		}
		.log-time {
			color: #9ca3af;
			margin-right: 10px;
		}
		.pattern {
			background: #1f2937;
			padding: 15px;
			border-radius: 6px;
			margin-bottom: 15px;
		}
		.pattern-title {
			font-weight: 600;
			margin-bottom: 10px;
			color: #3b82f6;
		}
		.pattern-count {
			display: inline-block;
			background: #3b82f6;
			color: #fff;
			padding: 2px 8px;
			border-radius: 4px;
			font-size: 12px;
			margin-left: 10px;
		}
	</style>
</head>
<body>
	<div class="container">
		<div class="header">
			<h1>📋 Analisi Log FP SEO Manager</h1>
			<p>Analisi approfondita dei log di debug</p>
		</div>
<?php

$log_file = WP_CONTENT_DIR . '/debug.log';

if ( ! file_exists( $log_file ) ) {
	echo '<div style="background: #7f1d1d; padding: 20px; border-radius: 8px; text-align: center;">';
	echo '<h2>❌ File debug.log non trovato</h2>';
	echo '<p>Il file si trova normalmente in: ' . esc_html( $log_file ) . '</p>';
	echo '<p>Abilita WP_DEBUG in wp-config.php per generare i log.</p>';
	echo '</div></div></body></html>';
	exit;
}

// Leggi log
$log_lines = file( $log_file, FILE_IGNORE_NEW_LINES );
$total_lines = count( $log_lines );

// Filtra solo log FP SEO
$fp_seo_logs = array();
foreach ( $log_lines as $line ) {
	if ( strpos( $line, 'FP SEO' ) !== false ) {
		$fp_seo_logs[] = $line;
	}
}

$fp_seo_count = count( $fp_seo_logs );

// Analizza pattern
$patterns = array(
	'error' => array( 'pattern' => '/ERROR|ERRORE|Fatal|Exception|Error/i', 'count' => 0, 'logs' => array() ),
	'warning' => array( 'pattern' => '/WARNING|WARN|Warning/i', 'count' => 0, 'logs' => array() ),
	'save' => array( 'pattern' => '/save_meta|save_all_fields|saved/i', 'count' => 0, 'logs' => array() ),
	'hook' => array( 'pattern' => '/hook|register|add_action/i', 'count' => 0, 'logs' => array() ),
	'cache' => array( 'pattern' => '/cache|Cache|CACHE/i', 'count' => 0, 'logs' => array() ),
);

foreach ( $fp_seo_logs as $log ) {
	foreach ( $patterns as $key => &$pattern_data ) {
		if ( preg_match( $pattern_data['pattern'], $log ) ) {
			$pattern_data['count']++;
			$pattern_data['logs'][] = $log;
		}
	}
}

// Statistiche
$error_count = $patterns['error']['count'];
$warning_count = $patterns['warning']['count'];
$save_count = $patterns['save']['count'];

echo '<div class="stats">';
echo '<div class="stat-card">';
echo '<div class="stat-value">' . number_format( $total_lines ) . '</div>';
echo '<div class="stat-label">Righe Totali Log</div>';
echo '</div>';

echo '<div class="stat-card">';
echo '<div class="stat-value">' . number_format( $fp_seo_count ) . '</div>';
echo '<div class="stat-label">Log FP SEO</div>';
echo '</div>';

echo '<div class="stat-card error">';
echo '<div class="stat-value">' . number_format( $error_count ) . '</div>';
echo '<div class="stat-label">Errori</div>';
echo '</div>';

echo '<div class="stat-card warning">';
echo '<div class="stat-value">' . number_format( $warning_count ) . '</div>';
echo '<div class="stat-label">Warning</div>';
echo '</div>';

echo '<div class="stat-card success">';
echo '<div class="stat-value">' . number_format( $save_count ) . '</div>';
echo '<div class="stat-label">Operazioni Salvataggio</div>';
echo '</div>';
echo '</div>';

// Pattern trovati
echo '<h2 style="margin-bottom: 20px; color: #3b82f6;">🔍 Pattern Trovati</h2>';
foreach ( $patterns as $key => $pattern_data ) {
	if ( $pattern_data['count'] > 0 ) {
		echo '<div class="pattern">';
		echo '<div class="pattern-title">' . strtoupper( $key ) . ' <span class="pattern-count">' . $pattern_data['count'] . '</span></div>';
		// Mostra ultimi 3 log di questo pattern
		$recent_logs = array_slice( $pattern_data['logs'], -3 );
		foreach ( $recent_logs as $log ) {
			$log_class = 'info';
			if ( $key === 'error' ) $log_class = 'error';
			if ( $key === 'warning' ) $log_class = 'warning';
			if ( $key === 'save' ) $log_class = 'success';
			echo '<div class="log-entry ' . esc_attr( $log_class ) . '" style="margin: 5px 0; padding: 8px; font-size: 11px;">' . esc_html( $log ) . '</div>';
		}
		echo '</div>';
	}
}

// Filtri
echo '<div class="filters">';
echo '<button class="filter-btn active" data-filter="all">Tutti</button>';
echo '<button class="filter-btn" data-filter="error">Errori</button>';
echo '<button class="filter-btn" data-filter="warning">Warning</button>';
echo '<button class="filter-btn" data-filter="save">Salvataggi</button>';
echo '<button class="filter-btn" data-filter="hook">Hook</button>';
echo '<button class="filter-btn" data-filter="cache">Cache</button>';
echo '</div>';

// Mostra ultimi log
echo '<h2 style="margin-bottom: 20px; color: #3b82f6;">📋 Ultimi Log FP SEO</h2>';
echo '<div class="log-container" id="logContainer">';

$recent_logs = array_slice( $fp_seo_logs, -100 ); // Ultimi 100
foreach ( array_reverse( $recent_logs ) as $log ) {
	$log_class = 'info';
	if ( preg_match( '/ERROR|ERRORE|Fatal|Exception/i', $log ) ) {
		$log_class = 'error';
	} elseif ( preg_match( '/WARNING|WARN/i', $log ) ) {
		$log_class = 'warning';
	} elseif ( preg_match( '/saved|save_meta|save_all_fields/i', $log ) ) {
		$log_class = 'success';
	}
	
	// Estrai timestamp se presente
	$timestamp = '';
	if ( preg_match( '/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $log, $matches ) ) {
		$timestamp = $matches[1];
		$log = preg_replace( '/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', '', $log );
	}
	
	echo '<div class="log-entry ' . esc_attr( $log_class ) . '" data-category="' . esc_attr( $log_class ) . '">';
	if ( $timestamp ) {
		echo '<span class="log-time">[' . esc_html( $timestamp ) . ']</span>';
	}
	echo esc_html( trim( $log ) );
	echo '</div>';
}

echo '</div>';
echo '</div>';

?>
	<script>
		// Filtri
		document.querySelectorAll('.filter-btn').forEach(btn => {
			btn.addEventListener('click', function() {
				document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
				this.classList.add('active');
				
				const filter = this.dataset.filter;
				const entries = document.querySelectorAll('.log-entry');
				
				entries.forEach(entry => {
					if (filter === 'all' || entry.dataset.category === filter) {
						entry.style.display = 'block';
					} else {
						entry.style.display = 'none';
					}
				});
			});
		});
		
		// Auto-scroll al top
		document.getElementById('logContainer').scrollTop = 0;
	</script>
</body>
</html>





