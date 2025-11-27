<?php
/**
 * Debug script per diagnosticare il problema di estrazione immagini
 * 
 * Accesso: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/debug-images-extraction.php?post_id=2
 */

// Carica WordPress
// Il plugin è in wp-content/plugins/FP-SEO-Manager/
// wp-load.php è nella root di WordPress
$current_file = __FILE__;
$plugin_dir = dirname( $current_file ); // wp-content/plugins/FP-SEO-Manager/
$wp_content_dir = dirname( dirname( $plugin_dir ) ); // wp-content/
$wp_root = dirname( $wp_content_dir ); // Root WordPress

// Prova diversi percorsi
$wp_load_paths = array();
// Path standard (3 livelli sopra)
$wp_load_paths[] = $wp_root . DIRECTORY_SEPARATOR . 'wp-load.php';
// Path alternativi per junction
$wp_load_paths[] = dirname( dirname( dirname( dirname( $current_file ) ) ) ) . DIRECTORY_SEPARATOR . 'wp-load.php';
$wp_load_paths[] = dirname( dirname( dirname( $current_file ) ) ) . DIRECTORY_SEPARATOR . 'wp-load.php';
// Path relativi
$wp_load_paths[] = dirname( __FILE__ ) . '/../../../wp-load.php';
$wp_load_paths[] = dirname( __FILE__ ) . '/../../../../wp-load.php';
// Path assoluto basato su workspace
$wp_load_paths[] = 'C:\\Users\\franc\\Local Sites\\fp-development\\app\\public\\wp-load.php';

$wp_loaded = false;
$found_path = null;
foreach ( $wp_load_paths as $path ) {
	if ( $path && file_exists( $path ) ) {
		$found_path = $path;
		require_once $path;
		$wp_loaded = true;
		break;
	}
}

if ( ! $wp_loaded ) {
	die( 'ERRORE: Impossibile caricare WordPress. Verifica il percorso.<br>Percorsi provati:<br>' . implode( '<br>', array_map( function( $p ) { return htmlspecialchars( $p ) . ' - ' . ( file_exists( $p ) ? 'ESISTE' : 'NON ESISTE' ); }, $wp_load_paths ) ) );
}

// Assicurati che l'utente sia un amministratore
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'Non hai i permessi sufficienti per accedere a questa pagina.' );
}

$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
if ( $post_id <= 0 ) {
	wp_die( 'Specifica un post_id valido: ?post_id=2' );
}

$post = get_post( $post_id );
if ( ! $post ) {
	wp_die( 'Post non trovato.' );
}

?>
<!DOCTYPE html>
<html>
<head>
	<title>Debug Estrazione Immagini - Post ID <?php echo esc_html( $post_id ); ?></title>
	<style>
		body { font-family: monospace; padding: 20px; background: #f5f5f5; }
		.section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
		.success { color: #10b981; }
		.error { color: #ef4444; }
		.warning { color: #f59e0b; }
		.info { color: #3b82f6; }
		pre { background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 6px; overflow-x: auto; }
		table { width: 100%; border-collapse: collapse; margin: 10px 0; }
		th, td { padding: 8px; text-align: left; border-bottom: 1px solid #e5e7eb; }
		th { background: #f9fafb; font-weight: 600; }
	</style>
</head>
<body>
	<h1>🔍 Debug Estrazione Immagini - Post ID <?php echo esc_html( $post_id ); ?></h1>
	
	<?php
	// === STEP 1: Verifica Post Object ===
	?>
	<div class="section">
		<h2>1. Post Object</h2>
		<table>
			<tr><th>Proprietà</th><th>Valore</th></tr>
			<tr><td>ID</td><td><?php echo esc_html( $post->ID ); ?></td></tr>
			<tr><td>Post Type</td><td><?php echo esc_html( $post->post_type ); ?></td></tr>
			<tr><td>Post Status</td><td><?php echo esc_html( $post->post_status ); ?></td></tr>
			<tr><td>Post Content Length (oggetto)</td><td><?php echo esc_html( strlen( $post->post_content ?? '' ) ); ?> caratteri</td></tr>
			<tr><td>Post Content Preview (oggetto)</td><td><?php echo esc_html( substr( $post->post_content ?? '', 0, 200 ) ); ?>...</td></tr>
		</table>
	</div>
	
	<?php
	// === STEP 2: Verifica Database Content ===
	global $wpdb;
	$db_content = $wpdb->get_var( $wpdb->prepare(
		"SELECT post_content FROM {$wpdb->posts} WHERE ID = %d AND post_status != 'inherit'",
		$post_id
	) );
	?>
	<div class="section">
		<h2>2. Contenuto dal Database</h2>
		<table>
			<tr><th>Proprietà</th><th>Valore</th></tr>
			<tr><td>Content Length (DB)</td><td><?php echo esc_html( strlen( $db_content ?? '' ) ); ?> caratteri</td></tr>
			<tr><td>Content Preview (DB)</td><td><?php echo esc_html( substr( $db_content ?? '', 0, 200 ) ); ?>...</td></tr>
			<tr><td>Ha WPBakery?</td><td class="<?php echo strpos( $db_content ?? '', '[vc_' ) !== false ? 'success' : 'error'; ?>">
				<?php echo strpos( $db_content ?? '', '[vc_' ) !== false ? '✅ Sì' : '❌ No'; ?>
			</td></tr>
			<tr><td>Ha tag &lt;img&gt;?</td><td class="<?php echo strpos( $db_content ?? '', '<img' ) !== false ? 'success' : 'error'; ?>">
				<?php echo strpos( $db_content ?? '', '<img' ) !== false ? '✅ Sì (' . substr_count( $db_content ?? '', '<img' ) . ')' : '❌ No'; ?>
			</td></tr>
			<tr><td>Ha shortcode [img]?</td><td class="<?php echo ( strpos( $db_content ?? '', '[img' ) !== false || strpos( $db_content ?? '', '[image' ) !== false ) ? 'success' : 'error'; ?>">
				<?php echo ( strpos( $db_content ?? '', '[img' ) !== false || strpos( $db_content ?? '', '[image' ) !== false ) ? '✅ Sì' : '❌ No'; ?>
			</td></tr>
		</table>
	</div>
	
	<?php
	// === STEP 3: Test Estrazione Immagini ===
	if ( class_exists( '\FP\SEO\Editor\MetaboxRenderer' ) ) {
		try {
			$renderer = new \FP\SEO\Editor\MetaboxRenderer();
			
			// Aggiorna il post object con il contenuto dal database
			$post->post_content = $db_content;
			
			// Estrai immagini
			$reflection = new ReflectionClass( $renderer );
			$method = $reflection->getMethod( 'extract_images_from_content' );
			$method->setAccessible( true );
			$images = $method->invoke( $renderer, $post );
			
			?>
			<div class="section">
				<h2>3. Estrazione Immagini (MetaboxRenderer)</h2>
				<table>
					<tr><th>Proprietà</th><th>Valore</th></tr>
					<tr><td>Immagini Trovate</td><td class="<?php echo count( $images ) > 0 ? 'success' : 'error'; ?>">
						<?php echo count( $images ); ?>
					</td></tr>
				</table>
				
				<?php if ( ! empty( $images ) ) : ?>
					<h3>Dettagli Immagini:</h3>
					<table>
						<tr>
							<th>#</th>
							<th>URL</th>
							<th>Attachment ID</th>
							<th>Alt</th>
							<th>Title</th>
							<th>È Featured?</th>
						</tr>
						<?php
						$featured_id = get_post_thumbnail_id( $post_id );
						foreach ( $images as $index => $img ) :
							$is_featured = ( ! empty( $img['attachment_id'] ) && (int) $img['attachment_id'] === (int) $featured_id );
						?>
							<tr>
								<td><?php echo esc_html( $index + 1 ); ?></td>
								<td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;">
									<?php echo esc_html( substr( $img['src'] ?? '', 0, 100 ) ); ?>...
								</td>
								<td><?php echo esc_html( $img['attachment_id'] ?? 'N/A' ); ?></td>
								<td><?php echo esc_html( substr( $img['alt'] ?? '', 0, 50 ) ); ?></td>
								<td><?php echo esc_html( substr( $img['title'] ?? '', 0, 50 ) ); ?></td>
								<td class="<?php echo $is_featured ? 'success' : ''; ?>">
									<?php echo $is_featured ? '⭐ Sì' : 'No'; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
				<?php else : ?>
					<p class="error"><strong>❌ Nessuna immagine trovata!</strong></p>
				<?php endif; ?>
			</div>
			<?php
		} catch ( \Throwable $e ) {
			?>
			<div class="section">
				<h2>3. Errore Estrazione Immagini</h2>
				<p class="error"><strong>Errore:</strong> <?php echo esc_html( $e->getMessage() ); ?></p>
				<pre><?php echo esc_html( $e->getTraceAsString() ); ?></pre>
			</div>
			<?php
		}
	} else {
		?>
		<div class="section">
			<h2>3. Errore</h2>
			<p class="error">La classe MetaboxRenderer non è disponibile.</p>
		</div>
		<?php
	}
	?>
	
	<?php
	// === STEP 4: Test Processamento Shortcode ===
	$processed_content = do_shortcode( $db_content ?? '' );
	$the_content = apply_filters( 'the_content', $db_content ?? '' );
	?>
	<div class="section">
		<h2>4. Processamento Shortcode</h2>
		<table>
			<tr><th>Metodo</th><th>Lunghezza</th><th>Ha &lt;img&gt;?</th><th>Conteggio &lt;img&gt;</th></tr>
			<tr>
				<td>do_shortcode()</td>
				<td><?php echo esc_html( strlen( $processed_content ) ); ?></td>
				<td class="<?php echo strpos( $processed_content, '<img' ) !== false ? 'success' : 'error'; ?>">
					<?php echo strpos( $processed_content, '<img' ) !== false ? '✅' : '❌'; ?>
				</td>
				<td><?php echo substr_count( $processed_content, '<img' ); ?></td>
			</tr>
			<tr>
				<td>apply_filters('the_content')</td>
				<td><?php echo esc_html( strlen( $the_content ) ); ?></td>
				<td class="<?php echo strpos( $the_content, '<img' ) !== false ? 'success' : 'error'; ?>">
					<?php echo strpos( $the_content, '<img' ) !== false ? '✅' : '❌'; ?>
				</td>
				<td><?php echo substr_count( $the_content, '<img' ); ?></td>
			</tr>
		</table>
	</div>
	
	<?php
	// === STEP 5: Test DOMDocument Parsing ===
	if ( class_exists( 'DOMDocument' ) ) {
		$content_to_parse = $processed_content . "\n" . ( $db_content ?? '' );
		try {
			$dom = new DOMDocument();
			libxml_use_internal_errors( true );
			$dom->loadHTML( '<?xml encoding="UTF-8">' . $content_to_parse );
			libxml_clear_errors();
			$img_tags = $dom->getElementsByTagName( 'img' );
			?>
			<div class="section">
				<h2>5. Parsing DOMDocument</h2>
				<table>
					<tr><th>Proprietà</th><th>Valore</th></tr>
					<tr><td>Tag &lt;img&gt; trovati</td><td class="<?php echo $img_tags->length > 0 ? 'success' : 'error'; ?>">
						<?php echo $img_tags->length; ?>
					</td></tr>
				</table>
				
				<?php if ( $img_tags->length > 0 ) : ?>
					<h3>Dettagli Tag &lt;img&gt;:</h3>
					<table>
						<tr>
							<th>#</th>
							<th>src</th>
							<th>alt</th>
							<th>title</th>
						</tr>
						<?php foreach ( $img_tags as $index => $img ) : ?>
							<tr>
								<td><?php echo esc_html( $index + 1 ); ?></td>
								<td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;">
									<?php echo esc_html( substr( $img->getAttribute( 'src' ), 0, 100 ) ); ?>
								</td>
								<td><?php echo esc_html( $img->getAttribute( 'alt' ) ); ?></td>
								<td><?php echo esc_html( $img->getAttribute( 'title' ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</table>
				<?php endif; ?>
			</div>
			<?php
		} catch ( \Throwable $e ) {
			?>
			<div class="section">
				<h2>5. Errore Parsing DOMDocument</h2>
				<p class="error"><strong>Errore:</strong> <?php echo esc_html( $e->getMessage() ); ?></p>
			</div>
			<?php
		}
	}
	?>
	
	<?php
	// === STEP 6: Test Featured Image ===
	$featured_id = get_post_thumbnail_id( $post_id );
	?>
	<div class="section">
		<h2>6. Immagine in Evidenza</h2>
		<table>
			<tr><th>Proprietà</th><th>Valore</th></tr>
			<tr><td>Featured Image ID</td><td><?php echo $featured_id ? esc_html( $featured_id ) : '<span class="error">❌ Nessuna</span>'; ?></td></tr>
			<?php if ( $featured_id ) : 
				$featured_url = wp_get_attachment_url( $featured_id );
			?>
				<tr><td>Featured Image URL</td><td><?php echo esc_html( $featured_url ); ?></td></tr>
				<tr><td>File Esiste?</td><td class="<?php echo file_exists( str_replace( content_url(), WP_CONTENT_DIR, $featured_url ) ) ? 'success' : 'error'; ?>">
					<?php 
					$file_path = str_replace( content_url(), WP_CONTENT_DIR, $featured_url );
					echo file_exists( $file_path ) ? '✅ Sì' : '❌ No (' . esc_html( $file_path ) . ')';
					?>
				</td></tr>
			<?php endif; ?>
		</table>
	</div>
	
	<?php
	// === STEP 7: Log Recenti ===
	if ( class_exists( '\FP\SEO\Utils\Logger' ) ) {
		// Cerca i log più recenti per questo post
		?>
		<div class="section">
			<h2>7. Log Recenti (ultimi 20)</h2>
			<p class="info">I log completi sono disponibili nel file di log del plugin.</p>
		</div>
		<?php
	}
	?>
	
	<div class="section">
		<h2>8. Azioni</h2>
		<p>
			<a href="?post_id=<?php echo esc_attr( $post_id ); ?>&refresh=1" style="padding: 10px 20px; background: #3b82f6; color: white; text-decoration: none; border-radius: 6px;">
				🔄 Ricarica Test
			</a>
			<a href="<?php echo admin_url( 'post.php?post=' . $post_id . '&action=edit' ); ?>" style="padding: 10px 20px; background: #10b981; color: white; text-decoration: none; border-radius: 6px; margin-left: 10px;">
				✏️ Modifica Post
			</a>
		</p>
	</div>
</body>
</html>
