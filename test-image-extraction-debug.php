<?php
/**
 * Test Image Extraction Debug
 * 
 * Questo script analizza in dettaglio perché le immagini non vengono estratte
 * dalla pagina Home (ID 399).
 */

// Carica WordPress
// Il plugin è in wp-content/plugins/FP-SEO-Manager
// wp-load.php è nella root di WordPress (4 livelli sopra)
$wp_load_paths = array(
    dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php', // Da plugin: ../../../../wp-load.php
    dirname(dirname(dirname(__FILE__))) . '/wp-load.php', // Da plugin: ../../../wp-load.php (fallback)
    __DIR__ . '/../../../../wp-load.php', // Path relativo
    __DIR__ . '/../../../wp-load.php', // Path relativo fallback
    // Path assoluto basato sul workspace
    'C:/Users/franc/Local Sites/fp-development/app/public/wp-load.php',
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
    die( 'WordPress non trovato. Percorsi provati: ' . implode( ', ', $wp_load_paths ) );
}

// Verifica permessi
if ( ! current_user_can( 'manage_options' ) ) {
    die( 'Accesso negato. Devi essere amministratore.' );
}

$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 399;

echo '<h1>Test Estrazione Immagini - Post ID: ' . $post_id . '</h1>';
echo '<style>
    body { font-family: monospace; padding: 20px; background: #f5f5f5; }
    .section { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #0073aa; }
    .success { border-left-color: #46b450; }
    .error { border-left-color: #dc3232; }
    .warning { border-left-color: #ffb900; }
    pre { background: #f0f0f0; padding: 10px; overflow-x: auto; }
    .image-found { color: #46b450; font-weight: bold; }
    .image-not-found { color: #dc3232; font-weight: bold; }
</style>';

$post = get_post( $post_id );
if ( ! $post ) {
    die( '<div class="section error"><h2>Errore</h2><p>Post non trovato (ID: ' . $post_id . ')</p></div>' );
}

echo '<div class="section success">';
echo '<h2>✅ Post Trovato</h2>';
echo '<p><strong>Titolo:</strong> ' . esc_html( $post->post_title ) . '</p>';
echo '<p><strong>Tipo:</strong> ' . esc_html( $post->post_type ) . '</p>';
echo '<p><strong>Status:</strong> ' . esc_html( $post->post_status ) . '</p>';
echo '</div>';

// 1. Contenuto Raw dal Database
echo '<div class="section">';
echo '<h2>1. Contenuto Raw dal Database</h2>';
global $wpdb;
$db_content = $wpdb->get_var( $wpdb->prepare(
    "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d AND post_status != 'inherit'",
    $post_id
) );
echo '<p><strong>Lunghezza:</strong> ' . strlen( $db_content ) . ' caratteri</p>';
echo '<details><summary>Mostra contenuto completo</summary><pre>' . esc_html( $db_content ) . '</pre></details>';
echo '</div>';

// 2. Featured Image
echo '<div class="section">';
echo '<h2>2. Featured Image</h2>';
$featured_id = get_post_thumbnail_id( $post_id );
if ( $featured_id ) {
    $featured_url = wp_get_attachment_url( $featured_id );
    echo '<p class="image-found">✅ Featured Image trovata!</p>';
    echo '<p><strong>ID:</strong> ' . $featured_id . '</p>';
    echo '<p><strong>URL:</strong> ' . esc_url( $featured_url ) . '</p>';
    echo '<p><img src="' . esc_url( $featured_url ) . '" style="max-width: 200px; height: auto;"></p>';
} else {
    echo '<p class="image-not-found">❌ Nessuna Featured Image</p>';
}
echo '</div>';

// 3. Immagini da HTML (DOMDocument)
echo '<div class="section">';
echo '<h2>3. Immagini da HTML (DOMDocument)</h2>';
if ( ! empty( $db_content ) ) {
    libxml_use_internal_errors( true );
    $dom = new DOMDocument();
    @$dom->loadHTML( '<?xml encoding="UTF-8">' . $db_content );
    libxml_clear_errors();
    
    $img_tags = $dom->getElementsByTagName( 'img' );
    echo '<p><strong>Tag &lt;img&gt; trovati:</strong> ' . $img_tags->length . '</p>';
    
    if ( $img_tags->length > 0 ) {
        echo '<ul>';
        foreach ( $img_tags as $img ) {
            $src = $img->getAttribute( 'src' );
            echo '<li class="image-found">✅ ' . esc_html( $src ) . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p class="image-not-found">❌ Nessun tag &lt;img&gt; trovato</p>';
    }
} else {
    echo '<p class="image-not-found">❌ Contenuto vuoto</p>';
}
echo '</div>';

// 4. Immagini da WPBakery Shortcodes
echo '<div class="section">';
echo '<h2>4. Immagini da WPBakery Shortcodes</h2>';
if ( ! empty( $db_content ) ) {
    $wpbakery_patterns = array(
        'image' => '/\[vc_\w+.*?image\s*=\s*["\']([^"\']+)["\'].*?\]/is',
        'bg_image' => '/\[vc_\w+.*?bg_image\s*=\s*["\']([^"\']+)["\'].*?\]/is',
        'background_image' => '/\[vc_\w+.*?background_image\s*=\s*["\']([^"\']+)["\'].*?\]/is',
        'images' => '/\[vc_\w+.*?images\s*=\s*["\']([^"\']+)["\'].*?\]/is',
    );
    
    $found_images = array();
    foreach ( $wpbakery_patterns as $attr => $pattern ) {
        if ( preg_match_all( $pattern, $db_content, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $match ) {
                $value = $match[1];
                if ( ! empty( $value ) ) {
                    $found_images[] = array( 'attr' => $attr, 'value' => $value );
                }
            }
        }
    }
    
    if ( ! empty( $found_images ) ) {
        echo '<p class="image-found">✅ Immagini trovate in WPBakery shortcodes:</p>';
        echo '<ul>';
        foreach ( $found_images as $img ) {
            echo '<li><strong>' . esc_html( $img['attr'] ) . ':</strong> ' . esc_html( $img['value'] ) . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p class="image-not-found">❌ Nessuna immagine trovata in WPBakery shortcodes</p>';
    }
} else {
    echo '<p class="image-not-found">❌ Contenuto vuoto</p>';
}
echo '</div>';

// 5. Immagini da Post Meta
echo '<div class="section">';
echo '<h2>5. Immagini da Post Meta</h2>';
$all_meta = get_post_meta( $post_id );
$image_meta_keys = array();

foreach ( $all_meta as $key => $value ) {
    if ( strpos( $key, 'image' ) !== false
        || strpos( $key, 'bg' ) !== false
        || strpos( $key, 'background' ) !== false
        || strpos( $key, 'thumbnail' ) !== false
        || strpos( $key, 'slide' ) !== false
        || strpos( $key, 'header' ) !== false
        || strpos( $key, 'preview' ) !== false ) {
        $image_meta_keys[ $key ] = $value;
    }
}

if ( ! empty( $image_meta_keys ) ) {
    echo '<p class="image-found">✅ Meta keys correlate a immagini trovate:</p>';
    echo '<ul>';
    foreach ( $image_meta_keys as $key => $value ) {
        echo '<li><strong>' . esc_html( $key ) . ':</strong> ';
        if ( is_array( $value ) && count( $value ) === 1 ) {
            $value = $value[0];
        }
        if ( is_string( $value ) && strlen( $value ) > 200 ) {
            echo '<details><summary>Valore (lungo, clicca per espandere)</summary><pre>' . esc_html( substr( $value, 0, 1000 ) ) . '...</pre></details>';
        } else {
            echo '<pre>' . esc_html( print_r( $value, true ) ) . '</pre>';
        }
        echo '</li>';
    }
    echo '</ul>';
} else {
    echo '<p class="image-not-found">❌ Nessuna meta key correlata a immagini trovata</p>';
}
echo '</div>';

// 6. Test con MetaboxRenderer
echo '<div class="section">';
echo '<h2>6. Test con MetaboxRenderer::extract_images_from_content()</h2>';
try {
    // Carica la classe
    $plugin_path = __DIR__;
    require_once $plugin_path . '/src/Editor/MetaboxRenderer.php';
    
    $renderer = new \FP\SEO\Editor\MetaboxRenderer();
    $reflection = new ReflectionClass( $renderer );
    $method = $reflection->getMethod( 'extract_images_from_content' );
    $method->setAccessible( true );
    
    $images = $method->invoke( $renderer, $post );
    
    if ( ! empty( $images ) ) {
        echo '<p class="image-found">✅ ' . count( $images ) . ' immagini estratte da extract_images_from_content()</p>';
        echo '<ul>';
        foreach ( array_slice( $images, 0, 10 ) as $img ) {
            echo '<li>';
            echo '<strong>URL:</strong> ' . esc_html( $img['src'] ?? 'N/A' ) . '<br>';
            echo '<strong>Alt:</strong> ' . esc_html( $img['alt'] ?? '' ) . '<br>';
            echo '<strong>Attachment ID:</strong> ' . ( $img['attachment_id'] ?? 'N/A' ) . '<br>';
            if ( ! empty( $img['src'] ) ) {
                echo '<img src="' . esc_url( $img['src'] ) . '" style="max-width: 150px; height: auto; margin-top: 5px;"><br>';
            }
            echo '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p class="image-not-found">❌ Nessuna immagine estratta da extract_images_from_content()</p>';
    }
} catch ( Exception $e ) {
    echo '<p class="error">❌ Errore: ' . esc_html( $e->getMessage() ) . '</p>';
    echo '<pre>' . esc_html( $e->getTraceAsString() ) . '</pre>';
}
echo '</div>';

// 7. Frontend URL per verifica visiva
echo '<div class="section">';
echo '<h2>7. Verifica Frontend</h2>';
$frontend_url = get_permalink( $post_id );
echo '<p><a href="' . esc_url( $frontend_url ) . '" target="_blank">Apri la pagina nel frontend per verificare visivamente se ci sono immagini</a></p>';
echo '</div>';

echo '<hr>';
echo '<p><small>Script completato. Se non vengono trovate immagini, potrebbe essere che:</small></p>';
echo '<ul>';
echo '<li>Le immagini sono caricate dinamicamente via JavaScript</li>';
echo '<li>Le immagini sono in un plugin/tema che non salva nel post_content</li>';
echo '<li>Le immagini sono in post meta ma con chiavi non standard</li>';
echo '<li>Le immagini sono in un custom field o ACF field</li>';
echo '</ul>';

