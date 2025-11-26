<?php
/**
 * Script di diagnostica e riparazione per le anteprime mancanti nella libreria media WordPress
 * 
 * USO:
 * 1. Carica questo file nella root di WordPress
 * 2. Visita: https://tuosito.com/wp-content/plugins/FP-SEO-Manager/fix-media-thumbnails.php
 * 3. Lo script eseguirà la diagnostica e proverà a rigenerare le anteprime
 * 
 * IMPORTANTE: Rimuovi questo file dopo l'uso per motivi di sicurezza!
 * 
 * @package FP\SEO
 */

// Carica WordPress
require_once( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php' );

// Verifica che l'utente sia admin
if ( ! current_user_can( 'manage_options' ) ) {
	die( 'Accesso negato. Devi essere un amministratore.' );
}

?>
<!DOCTYPE html>
<html>
<head>
	<title>Diagnostica e Riparazione Anteprime Media</title>
	<style>
		body { font-family: Arial, sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px; }
		.success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; margin: 10px 0; border-radius: 5px; }
		.error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; margin: 10px 0; border-radius: 5px; }
		.warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; margin: 10px 0; border-radius: 5px; }
		.info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; margin: 10px 0; border-radius: 5px; }
		table { width: 100%; border-collapse: collapse; margin: 20px 0; }
		th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
		th { background-color: #f2f2f2; }
		.button { background: #0073aa; color: white; padding: 10px 20px; border: none; cursor: pointer; border-radius: 5px; text-decoration: none; display: inline-block; margin: 5px; }
		.button:hover { background: #005a87; }
		.button-danger { background: #dc3545; }
		.button-danger:hover { background: #c82333; }
	</style>
</head>
<body>
	<h1>🔧 Diagnostica e Riparazione Anteprime Media WordPress</h1>
	
	<?php
	$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'diagnose';
	
	if ( $action === 'diagnose' ) {
		// DIAGNOSTICA
		?>
		<div class="info">
			<h2>📊 Diagnostica Sistema</h2>
			
			<?php
			$issues = array();
			$warnings = array();
			$info = array();
			
			// 1. Verifica estensioni PHP
			if ( ! extension_loaded( 'gd' ) && ! extension_loaded( 'imagick' ) ) {
				$issues[] = 'Nessuna estensione di elaborazione immagini trovata (GD o Imagick). WordPress ha bisogno di una di queste per generare le anteprime.';
			} else {
				if ( extension_loaded( 'gd' ) ) {
					$info[] = '✓ Estensione GD trovata: ' . phpversion( 'gd' );
				}
				if ( extension_loaded( 'imagick' ) ) {
					$info[] = '✓ Estensione Imagick trovata: ' . phpversion( 'imagick' );
				}
			}
			
			// 2. Verifica permessi cartella uploads
			$upload_dir = wp_upload_dir();
			$uploads_path = $upload_dir['basedir'];
			
			if ( ! is_writable( $uploads_path ) ) {
				$issues[] = "La cartella uploads non è scrivibile: {$uploads_path}";
			} else {
				$info[] = "✓ Cartella uploads è scrivibile: {$uploads_path}";
			}
			
			// 3. Verifica permessi cartella year/month
			$year_month_path = $upload_dir['path'];
			if ( ! is_writable( $year_month_path ) ) {
				$warnings[] = "La cartella anno/mese potrebbe non essere scrivibile: {$year_month_path}";
			}
			
			// 4. Verifica memoria PHP
			$memory_limit = ini_get( 'memory_limit' );
			$memory_limit_bytes = wp_convert_hr_to_bytes( $memory_limit );
			if ( $memory_limit_bytes < 128 * 1024 * 1024 ) { // 128MB
				$warnings[] = "Memoria PHP potrebbe essere insufficiente: {$memory_limit} (consigliato: almeno 128M)";
			} else {
				$info[] = "✓ Memoria PHP: {$memory_limit}";
			}
			
			// 5. Conta immagini senza thumbnails
			global $wpdb;
			$attachments = $wpdb->get_results( "
				SELECT ID, post_mime_type 
				FROM {$wpdb->posts} 
				WHERE post_type = 'attachment' 
				AND post_mime_type LIKE 'image/%'
				ORDER BY ID DESC
				LIMIT 100
			" );
			
			$missing_thumbnails = 0;
			$total_images = count( $attachments );
			$sample_issues = array();
			
			foreach ( $attachments as $attachment ) {
				$meta = wp_get_attachment_metadata( $attachment->ID );
				if ( empty( $meta['sizes'] ) || ! isset( $meta['sizes']['thumbnail'] ) ) {
					$missing_thumbnails++;
					if ( count( $sample_issues ) < 5 ) {
						$sample_issues[] = "ID {$attachment->ID} - " . get_the_title( $attachment->ID );
					}
				}
			}
			
			if ( $missing_thumbnails > 0 ) {
				$issues[] = "Trovate {$missing_thumbnails} immagini (su {$total_images} controllate) senza anteprime generate.";
				if ( ! empty( $sample_issues ) ) {
					$info[] = "Esempi: " . implode( ', ', $sample_issues );
				}
			} else {
				$info[] = "✓ Tutte le immagini controllate hanno le anteprime generate.";
			}
			
			// 6. Verifica .htaccess
			$htaccess_path = $uploads_path . '/.htaccess';
			if ( file_exists( $htaccess_path ) ) {
				$htaccess_content = file_get_contents( $htaccess_path );
				if ( strpos( $htaccess_content, 'deny from all' ) !== false ) {
					$warnings[] = "Il file .htaccess nella cartella uploads contiene 'deny from all' che potrebbe bloccare l'accesso alle immagini.";
				}
			}
			
			// Mostra risultati
			if ( ! empty( $issues ) ) {
				echo '<div class="error"><h3>❌ Problemi Critici Trovati:</h3><ul>';
				foreach ( $issues as $issue ) {
					echo '<li>' . esc_html( $issue ) . '</li>';
				}
				echo '</ul></div>';
			}
			
			if ( ! empty( $warnings ) ) {
				echo '<div class="warning"><h3>⚠️ Avvisi:</h3><ul>';
				foreach ( $warnings as $warning ) {
					echo '<li>' . esc_html( $warning ) . '</li>';
				}
				echo '</ul></div>';
			}
			
			if ( ! empty( $info ) ) {
				echo '<div class="success"><h3>✓ Informazioni:</h3><ul>';
				foreach ( $info as $i ) {
					echo '<li>' . esc_html( $i ) . '</li>';
				}
				echo '</ul></div>';
			}
			?>
		</div>
		
		<div class="info">
			<h2>🔧 Azioni Disponibili</h2>
			<p>
				<a href="?action=regenerate" class="button" onclick="return confirm('Questo processo potrebbe richiedere molto tempo. Continuare?');">
					🔄 Rigenera Tutte le Anteprime
				</a>
				<a href="?action=regenerate_missing" class="button" onclick="return confirm('Rigenererà solo le anteprime mancanti. Continuare?');">
					🔄 Rigenera Solo Anteprime Mancanti
				</a>
				<a href="?action=check_permissions" class="button">
					🔍 Verifica e Corregge Permessi
				</a>
			</p>
		</div>
		<?php
		
	} elseif ( $action === 'regenerate' || $action === 'regenerate_missing' ) {
		// RIGENERA ANTEPRIME
		?>
		<div class="info">
			<h2>🔄 Rigenerazione Anteprime</h2>
			<?php
			set_time_limit( 300 ); // 5 minuti
			
			global $wpdb;
			$attachments = $wpdb->get_results( "
				SELECT ID 
				FROM {$wpdb->posts} 
				WHERE post_type = 'attachment' 
				AND post_mime_type LIKE 'image/%'
				ORDER BY ID DESC
			" );
			
			$regenerated = 0;
			$skipped = 0;
			$errors = 0;
			$max_process = 50; // Limita a 50 per volta per evitare timeout
			
			foreach ( array_slice( $attachments, 0, $max_process ) as $attachment ) {
				$attachment_id = $attachment->ID;
				$file = get_attached_file( $attachment_id );
				
				if ( ! $file || ! file_exists( $file ) ) {
					$skipped++;
					continue;
				}
				
				// Se regenerate_missing, controlla se ha già le thumbnails
				if ( $action === 'regenerate_missing' ) {
					$meta = wp_get_attachment_metadata( $attachment_id );
					if ( ! empty( $meta['sizes'] ) && isset( $meta['sizes']['thumbnail'] ) ) {
						$skipped++;
						continue;
					}
				}
				
				// Rigenera le thumbnails
				$result = wp_generate_attachment_metadata( $attachment_id, $file );
				
				if ( is_wp_error( $result ) ) {
					$errors++;
					echo '<div class="error">Errore per ID ' . $attachment_id . ': ' . $result->get_error_message() . '</div>';
				} else {
					wp_update_attachment_metadata( $attachment_id, $result );
					$regenerated++;
					echo '<div class="success">✓ Rigenerata anteprima per ID: ' . $attachment_id . '</div>';
				}
				
				// Flush output per vedere il progresso
				if ( ob_get_level() > 0 ) {
					ob_flush();
				}
				flush();
			}
			
			echo '<div class="info">';
			echo '<h3>Risultati:</h3>';
			echo '<ul>';
			echo '<li>Rigenerate: ' . $regenerated . '</li>';
			echo '<li>Saltate: ' . $skipped . '</li>';
			echo '<li>Errori: ' . $errors . '</li>';
			echo '<li>Totale immagini nel database: ' . count( $attachments ) . '</li>';
			echo '</ul>';
			echo '<p><strong>Nota:</strong> Processate solo le prime ' . $max_process . ' immagini per evitare timeout. Ricarica la pagina per continuare.</p>';
			echo '</div>';
			?>
			<p><a href="?action=diagnose" class="button">← Torna alla Diagnostica</a></p>
		</div>
		<?php
		
	} elseif ( $action === 'check_permissions' ) {
		// VERIFICA E CORREGGE PERMESSI
		?>
		<div class="info">
			<h2>🔍 Verifica e Correzione Permessi</h2>
			<?php
			$upload_dir = wp_upload_dir();
			$uploads_path = $upload_dir['basedir'];
			
			$fixed = array();
			$errors = array();
			
			// Verifica e corregge permessi cartella uploads
			if ( file_exists( $uploads_path ) ) {
				$current_perms = substr( sprintf( '%o', fileperms( $uploads_path ) ), -4 );
				if ( $current_perms !== '0755' && $current_perms !== '0775' ) {
					if ( @chmod( $uploads_path, 0755 ) ) {
						$fixed[] = "Permessi cartella uploads corretti: {$uploads_path}";
					} else {
						$errors[] = "Impossibile correggere permessi per: {$uploads_path}";
					}
				} else {
					$fixed[] = "Permessi cartella uploads già corretti: {$current_perms}";
				}
			}
			
			// Verifica permessi file .htaccess se esiste
			$htaccess_path = $uploads_path . '/.htaccess';
			if ( file_exists( $htaccess_path ) ) {
				$htaccess_content = file_get_contents( $htaccess_path );
				if ( strpos( $htaccess_content, 'deny from all' ) !== false ) {
					$errors[] = "Il file .htaccess contiene 'deny from all'. Potrebbe bloccare l'accesso alle immagini.";
				}
			}
			
			if ( ! empty( $fixed ) ) {
				echo '<div class="success"><h3>✓ Correzioni Applicate:</h3><ul>';
				foreach ( $fixed as $f ) {
					echo '<li>' . esc_html( $f ) . '</li>';
				}
				echo '</ul></div>';
			}
			
			if ( ! empty( $errors ) ) {
				echo '<div class="error"><h3>❌ Errori:</h3><ul>';
				foreach ( $errors as $e ) {
					echo '<li>' . esc_html( $e ) . '</li>';
				}
				echo '</ul></div>';
			}
			?>
			<p><a href="?action=diagnose" class="button">← Torna alla Diagnostica</a></p>
		</div>
		<?php
	}
	?>
	
	<div class="warning" style="margin-top: 30px;">
		<h3>⚠️ IMPORTANTE</h3>
		<p><strong>Rimuovi questo file dopo l'uso per motivi di sicurezza!</strong></p>
		<p>File: <code><?php echo esc_html( __FILE__ ); ?></code></p>
	</div>
</body>
</html>

