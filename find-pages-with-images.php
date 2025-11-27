<?php
/**
 * Trova pagine con immagini nel contenuto
 * 
 * Accesso: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/find-pages-with-images.php
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

global $wpdb;

// Trova tutte le pagine con immagini nel contenuto
$pages_with_images = $wpdb->get_results( $wpdb->prepare(
	"
	SELECT ID, post_title, post_type, post_status,
	       LENGTH(post_content) as content_length,
	       (LENGTH(post_content) - LENGTH(REPLACE(post_content, '<img', ''))) / 4 as img_count,
	       (LENGTH(post_content) - LENGTH(REPLACE(post_content, '[vc_', ''))) / 4 as wpbakery_count
	FROM {$wpdb->posts}
	WHERE post_type IN ('post', 'page')
	  AND post_status = 'publish'
	  AND (
	    post_content LIKE '%%<img%%' OR
	    post_content LIKE '%%[vc_%%' OR
	    post_content LIKE '%%[img%%' OR
	    post_content LIKE '%%[image%%'
	  )
	ORDER BY img_count DESC, wpbakery_count DESC
	LIMIT 20
	"
) );

?>
<!DOCTYPE html>
<html>
<head>
	<title>Pagine con Immagini</title>
	<style>
		body { font-family: monospace; padding: 20px; background: #f5f5f5; }
		table { width: 100%; border-collapse: collapse; background: white; margin: 20px 0; }
		th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
		th { background: #f9fafb; font-weight: 600; }
		tr:hover { background: #f9fafb; }
		.success { color: #10b981; }
		.error { color: #ef4444; }
		.warning { color: #f59e0b; }
		.info { color: #3b82f6; }
		a { color: #3b82f6; text-decoration: none; }
		a:hover { text-decoration: underline; }
	</style>
</head>
<body>
	<h1>🔍 Pagine con Immagini nel Contenuto</h1>
	
	<?php if ( empty( $pages_with_images ) ) : ?>
		<p class="error">Nessuna pagina trovata con immagini nel contenuto.</p>
	<?php else : ?>
		<table>
			<tr>
				<th>ID</th>
				<th>Titolo</th>
				<th>Tipo</th>
				<th>Lunghezza Contenuto</th>
				<th>Tag &lt;img&gt;</th>
				<th>WPBakery</th>
				<th>Azioni</th>
			</tr>
			<?php foreach ( $pages_with_images as $page ) : ?>
				<tr>
					<td><?php echo esc_html( $page->ID ); ?></td>
					<td><?php echo esc_html( $page->post_title ); ?></td>
					<td><?php echo esc_html( $page->post_type ); ?></td>
					<td><?php echo esc_html( number_format( $page->content_length ) ); ?> caratteri</td>
					<td class="<?php echo $page->img_count > 0 ? 'success' : 'error'; ?>">
						<?php echo $page->img_count > 0 ? '✅ ' . $page->img_count : '❌ 0'; ?>
					</td>
					<td class="<?php echo $page->wpbakery_count > 0 ? 'info' : ''; ?>">
						<?php echo $page->wpbakery_count > 0 ? '📦 ' . $page->wpbakery_count : '-'; ?>
					</td>
					<td>
						<a href="debug-images-extraction.php?post_id=<?php echo esc_attr( $page->ID ); ?>" target="_blank">
							🔍 Debug
						</a>
						|
						<a href="<?php echo admin_url( 'post.php?post=' . $page->ID . '&action=edit' ); ?>" target="_blank">
							✏️ Modifica
						</a>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
	<?php endif; ?>
	
	<p>
		<a href="debug-images-extraction.php?post_id=2">🔍 Test Post ID 2</a>
	</p>
</body>
</html>

