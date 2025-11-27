<?php
/**
 * Analizza gli shortcode WPBakery per capire come estrarre le immagini
 * 
 * Accesso: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/analyze-wpbakery-shortcodes.php?post_id=131
 */

// Carica WordPress
$current_file = __FILE__;
$plugin_dir = dirname( $current_file );
$wp_content_dir = dirname( dirname( $plugin_dir ) );
$wp_root = dirname( $wp_content_dir );

$wp_load_paths = array();
$wp_load_paths[] = $wp_root . DIRECTORY_SEPARATOR . 'wp-load.php';
$wp_load_paths[] = dirname( dirname( dirname( dirname( $current_file ) ) ) ) . DIRECTORY_SEPARATOR . 'wp-load.php';
$wp_load_paths[] = dirname( dirname( dirname( $current_file ) ) ) . DIRECTORY_SEPARATOR . 'wp-load.php';
$wp_load_paths[] = realpath( dirname( __FILE__ ) . '/../../../wp-load.php' );
$wp_load_paths[] = realpath( dirname( __FILE__ ) . '/../../../../wp-load.php' );
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
	die( 'ERRORE: Impossibile caricare WordPress. Percorsi provati:<br>' . implode( '<br>', array_map( function( $p ) { return htmlspecialchars( $p ) . ' - ' . ( file_exists( $p ) ? 'ESISTE' : 'NON ESISTE' ); }, $wp_load_paths ) ) );
}

// Assicurati che l'utente sia un amministratore
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'Non hai i permessi sufficienti per accedere a questa pagina.' );
}

$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 131;
$post = get_post( $post_id );
if ( ! $post ) {
	wp_die( 'Post non trovato.' );
}

global $wpdb;
$content = $wpdb->get_var( $wpdb->prepare(
	"SELECT post_content FROM {$wpdb->posts} WHERE ID = %d AND post_status != 'inherit'",
	$post_id
) );

?>
<!DOCTYPE html>
<html>
<head>
	<title>Analisi Shortcode WPBakery - Post ID <?php echo esc_html( $post_id ); ?></title>
	<style>
		body { font-family: monospace; padding: 20px; background: #f5f5f5; }
		.section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
		pre { background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 6px; overflow-x: auto; font-size: 11px; }
		table { width: 100%; border-collapse: collapse; margin: 10px 0; }
		th, td { padding: 8px; text-align: left; border-bottom: 1px solid #e5e7eb; }
		th { background: #f9fafb; font-weight: 600; }
		.success { color: #10b981; }
		.error { color: #ef4444; }
		.warning { color: #f59e0b; }
		.info { color: #3b82f6; }
	</style>
</head>
<body>
	<h1>🔍 Analisi Shortcode WPBakery - Post ID <?php echo esc_html( $post_id ); ?></h1>
	
	<?php
	// === STEP 1: Estrai tutti gli shortcode WPBakery ===
	?>
	<div class="section">
		<h2>1. Tutti gli Shortcode WPBakery</h2>
		<?php
		// Estrai tutti gli shortcode [vc_*]
		preg_match_all( '/\[vc_\w+.*?\]/is', $content, $all_shortcodes, PREG_SET_ORDER );
		?>
		<p><strong>Totale shortcode trovati:</strong> <?php echo count( $all_shortcodes ); ?></p>
		
		<?php if ( ! empty( $all_shortcodes ) ) : ?>
			<table>
				<tr>
					<th>#</th>
					<th>Shortcode</th>
					<th>Lunghezza</th>
					<th>Ha attributi immagine?</th>
				</tr>
				<?php foreach ( array_slice( $all_shortcodes, 0, 20 ) as $index => $match ) : 
					$shortcode = $match[0];
					$has_image_attr = (
						preg_match( '/image\s*=\s*["\']/i', $shortcode ) ||
						preg_match( '/bg_image\s*=\s*["\']/i', $shortcode ) ||
						preg_match( '/background_image\s*=\s*["\']/i', $shortcode ) ||
						preg_match( '/images\s*=\s*["\']/i', $shortcode ) ||
						preg_match( '/image_url\s*=\s*["\']/i', $shortcode )
					);
				?>
					<tr>
						<td><?php echo esc_html( $index + 1 ); ?></td>
						<td style="max-width: 500px; overflow: hidden; text-overflow: ellipsis;">
							<?php echo esc_html( substr( $shortcode, 0, 200 ) ); ?>
							<?php if ( strlen( $shortcode ) > 200 ) : ?>...<?php endif; ?>
						</td>
						<td><?php echo esc_html( strlen( $shortcode ) ); ?> caratteri</td>
						<td class="<?php echo $has_image_attr ? 'success' : 'error'; ?>">
							<?php echo $has_image_attr ? '✅ Sì' : '❌ No'; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
		<?php endif; ?>
	</div>
	
	<?php
	// === STEP 2: Cerca attributi immagine specifici ===
	?>
	<div class="section">
		<h2>2. Attributi Immagine negli Shortcode</h2>
		<?php
		$image_attrs = array( 'image', 'bg_image', 'background_image', 'images', 'image_url', 'image_1_url', 'image_2_url' );
		$found_attrs = array();
		
		foreach ( $image_attrs as $attr ) {
			// Pattern multilinea con DOTALL
			$pattern = '/\[vc_.*?' . preg_quote( $attr, '/' ) . '\s*=\s*["\']([^"\']+)["\'].*?\]/is';
			if ( preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
				foreach ( $matches as $match ) {
					$found_attrs[] = array(
						'attr' => $attr,
						'value' => $match[1],
						'shortcode' => substr( $match[0], 0, 200 ),
					);
				}
			}
		}
		?>
		<p><strong>Attributi immagine trovati:</strong> <?php echo count( $found_attrs ); ?></p>
		
		<?php if ( ! empty( $found_attrs ) ) : ?>
			<table>
				<tr>
					<th>#</th>
					<th>Attributo</th>
					<th>Valore</th>
					<th>È ID numerico?</th>
					<th>Attachment ID</th>
					<th>URL Immagine</th>
					<th>File Esiste?</th>
				</tr>
				<?php foreach ( $found_attrs as $index => $item ) : 
					$value = $item['value'];
					$is_numeric = is_numeric( $value );
					$attachment_id = $is_numeric ? (int) $value : null;
					
					// Se non è numerico, prova a estrarre ID da formato "123|full"
					if ( ! $is_numeric && preg_match( '/^(\d+)/', $value, $id_match ) ) {
						$attachment_id = (int) $id_match[1];
					}
					
					$image_url = null;
					$file_exists = false;
					if ( $attachment_id ) {
						$image_url = wp_get_attachment_url( $attachment_id );
						if ( $image_url ) {
							$file_path = str_replace( content_url(), WP_CONTENT_DIR, $image_url );
							$file_exists = file_exists( $file_path );
						}
					}
				?>
					<tr>
						<td><?php echo esc_html( $index + 1 ); ?></td>
						<td><strong><?php echo esc_html( $item['attr'] ); ?></strong></td>
						<td><?php echo esc_html( $value ); ?></td>
						<td class="<?php echo $is_numeric ? 'success' : 'error'; ?>">
							<?php echo $is_numeric ? '✅ Sì' : '❌ No'; ?>
						</td>
						<td><?php echo $attachment_id ? esc_html( $attachment_id ) : '<span class="error">N/A</span>'; ?></td>
						<td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;">
							<?php echo $image_url ? esc_html( substr( $image_url, 0, 100 ) ) : '<span class="error">N/A</span>'; ?>
						</td>
						<td class="<?php echo $file_exists ? 'success' : 'error'; ?>">
							<?php echo $file_exists ? '✅ Sì' : '❌ No'; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
		<?php else : ?>
			<p class="error"><strong>❌ Nessun attributo immagine trovato!</strong></p>
			<p>Pattern cercati:</p>
			<ul>
				<?php foreach ( $image_attrs as $attr ) : ?>
					<li><code>image="<?php echo esc_html( $attr ); ?>"</code></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	
	<?php
	// === STEP 3: Mostra contenuto completo per debug ===
	?>
	<div class="section">
		<h2>3. Contenuto Completo (primi 2000 caratteri)</h2>
		<pre><?php echo esc_html( substr( $content, 0, 2000 ) ); ?>...</pre>
	</div>
	
	<?php
	// === STEP 4: Test Pattern Regex ===
	?>
	<div class="section">
		<h2>4. Test Pattern Regex</h2>
		<?php
		$test_patterns = array(
			'image' => '/\[vc_.*?image\s*=\s*["\']([^"\']+)["\'].*?\]/is',
			'bg_image' => '/\[vc_.*?bg_image\s*=\s*["\']([^"\']+)["\'].*?\]/is',
			'images' => '/\[vc_.*?images\s*=\s*["\']([^"\']+)["\'].*?\]/is',
		);
		?>
		<table>
			<tr>
				<th>Pattern</th>
				<th>Match Trovati</th>
				<th>Valori</th>
			</tr>
			<?php foreach ( $test_patterns as $name => $pattern ) : 
				$matches_count = preg_match_all( $pattern, $content, $test_matches, PREG_SET_ORDER );
			?>
				<tr>
					<td><code><?php echo esc_html( $name ); ?></code></td>
					<td class="<?php echo $matches_count > 0 ? 'success' : 'error'; ?>">
						<?php echo $matches_count > 0 ? '✅ ' . $matches_count : '❌ 0'; ?>
					</td>
					<td>
						<?php if ( $matches_count > 0 ) : ?>
							<?php foreach ( array_slice( $test_matches, 0, 5 ) as $m ) : ?>
								<div style="margin: 4px 0; padding: 4px; background: #f3f4f6; border-radius: 4px;">
									<strong>Valore:</strong> <?php echo esc_html( $m[1] ?? 'N/A' ); ?>
								</div>
							<?php endforeach; ?>
						<?php else : ?>
							<span class="error">Nessun match</span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
	</div>
	
	<div class="section">
		<h2>5. Azioni</h2>
		<p>
			<a href="?post_id=<?php echo esc_attr( $post_id ); ?>&refresh=1" style="padding: 10px 20px; background: #3b82f6; color: white; text-decoration: none; border-radius: 6px;">
				🔄 Ricarica
			</a>
			<a href="debug-images-extraction.php?post_id=<?php echo esc_attr( $post_id ); ?>" style="padding: 10px 20px; background: #10b981; color: white; text-decoration: none; border-radius: 6px; margin-left: 10px;">
				🔍 Debug Estrazione
			</a>
		</p>
	</div>
</body>
</html>

