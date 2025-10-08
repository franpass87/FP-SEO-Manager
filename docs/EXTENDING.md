# Guida all'Estensione del Plugin

## Panoramica

Questo documento spiega come estendere le funzionalità del plugin FP SEO Performance utilizzando l'architettura modulare implementata.

## Architettura Modulare

Il plugin è ora organizzato in moduli ben definiti che facilitano l'estensione e la manutenzione:

### 1. Modulo Utils - Utilità Riutilizzabili

#### MetadataResolver

Fornisce metodi statici per risolvere metadata SEO dai post.

**Esempio di utilizzo:**
```php
use FP\SEO\Utils\MetadataResolver;

// Risolvi meta description
$description = MetadataResolver::resolve_meta_description( $post );

// Risolvi canonical URL
$canonical = MetadataResolver::resolve_canonical_url( $post );

// Risolvi robots directives
$robots = MetadataResolver::resolve_robots( $post );
```

**Estensione tramite hook:**
```php
// Modifica meta description prima del processing
add_filter( 'get_post_meta', function( $value, $post_id, $meta_key ) {
    if ( $meta_key === '_fp_seo_meta_description' && empty( $value ) ) {
        return 'Default description';
    }
    return $value;
}, 10, 3 );
```

### 2. Modulo Analysis - Sistema di Controlli

#### CheckRegistry

Gestisce il filtering e l'abilitazione dei controlli SEO.

**Aggiungere controlli personalizzati:**
```php
use FP\SEO\Analysis\CheckInterface;
use FP\SEO\Analysis\Result;
use FP\SEO\Analysis\Context;

class MyCustomCheck implements CheckInterface {
    
    public function id(): string {
        return 'my_custom_check';
    }
    
    public function label(): string {
        return __( 'My Custom Check', 'my-domain' );
    }
    
    public function description(): string {
        return __( 'Description of my check', 'my-domain' );
    }
    
    public function run( Context $context ): Result {
        // Implementa la tua logica
        $status = Result::STATUS_PASS;
        $message = 'Everything is good';
        
        return new Result( $status, $message );
    }
}
```

**Registrare il controllo personalizzato:**
```php
add_filter( 'fp_seo_perf_checks_enabled', function( $checks, $context ) {
    $checks[] = 'my_custom_check';
    return $checks;
}, 10, 2 );
```

**Modificare i controlli eseguiti:**
```php
// Disabilita un controllo specifico per determinati post type
add_filter( 'fp_seo_perf_checks_enabled', function( $enabled_checks, $context ) {
    $post_id = $context->post_id();
    
    if ( $post_id && get_post_type( $post_id ) === 'product' ) {
        // Rimuovi il check interno links per i prodotti
        $enabled_checks = array_filter( $enabled_checks, function( $check_id ) {
            return $check_id !== 'internal_links';
        });
    }
    
    return $enabled_checks;
}, 10, 2 );
```

### 3. Modulo Settings - Tab delle Impostazioni

#### Aggiungere una nuova Tab

**Passo 1: Creare il Renderer**

```php
<?php
namespace FP\SEO\Admin\Settings;

class MyCustomTabRenderer extends SettingsTabRenderer {
    
    public function render( array $options ): void {
        $my_options = $options['my_custom'] ?? array();
        ?>
        <table class="form-table" role="presentation">
            <tbody>
            <tr>
                <th scope="row"><?php esc_html_e( 'My Setting', 'my-domain' ); ?></th>
                <td>
                    <input type="text" 
                           name="<?php echo esc_attr( $this->get_option_key() ); ?>[my_custom][my_setting]" 
                           value="<?php echo esc_attr( $my_options['my_setting'] ?? '' ); ?>" 
                           class="regular-text" />
                </td>
            </tr>
            </tbody>
        </table>
        <?php
    }
}
```

**Passo 2: Registrare la Tab**

```php
add_filter( 'fp_seo_performance_settings_tabs', function( $tabs ) {
    $tabs['my_custom'] = __( 'My Custom Tab', 'my-domain' );
    return $tabs;
});

add_action( 'fp_seo_performance_render_settings_tab_my_custom', function( $options ) {
    $renderer = new \FP\SEO\Admin\Settings\MyCustomTabRenderer();
    $renderer->render( $options );
});
```

**Passo 3: Aggiungere Defaults e Sanitization**

```php
add_filter( 'fp_seo_performance_default_options', function( $defaults ) {
    $defaults['my_custom'] = array(
        'my_setting' => '',
    );
    return $defaults;
});

add_filter( 'fp_seo_performance_sanitize_options', function( $sanitized, $input ) {
    if ( isset( $input['my_custom'] ) ) {
        $sanitized['my_custom']['my_setting'] = sanitize_text_field( 
            $input['my_custom']['my_setting'] ?? '' 
        );
    }
    return $sanitized;
}, 10, 2 );
```

## Hook Disponibili

### Filtri

#### `fp_seo_perf_checks_enabled`

Modifica l'elenco dei controlli abilitati.

```php
add_filter( 'fp_seo_perf_checks_enabled', function( $enabled_checks, $context ) {
    // Aggiungi o rimuovi controlli
    return $enabled_checks;
}, 10, 2 );
```

**Parametri:**
- `$enabled_checks` (array): Array di ID dei controlli abilitati
- `$context` (Context): Contesto dell'analisi corrente

#### `fp_seo_performance_metadata_description`

Modifica la meta description prima dell'uso.

```php
add_filter( 'fp_seo_performance_metadata_description', function( $description, $post_id ) {
    // Personalizza la description
    return $description;
}, 10, 2 );
```

### Azioni

#### `fp_seo_performance_after_analysis`

Eseguito dopo ogni analisi completata.

```php
add_action( 'fp_seo_performance_after_analysis', function( $post_id, $results ) {
    // Log o notifiche personalizzate
    error_log( sprintf( 'SEO Score for post %d: %d', $post_id, $results['score'] ) );
}, 10, 2 );
```

## Best Practices

### 1. Riutilizza le Utility Esistenti

Invece di duplicare codice, usa le utility già disponibili:

```php
// ✅ Corretto
use FP\SEO\Utils\MetadataResolver;
$description = MetadataResolver::resolve_meta_description( $post );

// ❌ Da evitare
$description = get_post_meta( $post->ID, '_fp_seo_meta_description', true );
if ( empty( $description ) ) {
    $description = wp_strip_all_tags( $post->post_excerpt );
}
```

### 2. Segui la Separazione delle Responsabilità

Ogni classe dovrebbe avere un solo scopo:

```php
// ✅ Corretto - Classi separate
class DataRetriever {
    public function getData() { /* ... */ }
}

class DataProcessor {
    public function process( $data ) { /* ... */ }
}

class DataRenderer {
    public function render( $data ) { /* ... */ }
}

// ❌ Da evitare - Classe monolitica
class DataManager {
    public function getData() { /* ... */ }
    public function process( $data ) { /* ... */ }
    public function render( $data ) { /* ... */ }
}
```

### 3. Usa Type Hints e DocBlocks

```php
/**
 * Processes SEO data for a post.
 *
 * @param WP_Post $post Post object to process.
 * @param array<string, mixed> $options Processing options.
 *
 * @return array<string, mixed> Processed data.
 */
public function process_post_data( WP_Post $post, array $options ): array {
    // Implementazione
}
```

### 4. Testa il Tuo Codice

Crea sempre test unitari per le nuove funzionalità:

```php
namespace FP\SEO\Tests\MyExtension;

use PHPUnit\Framework\TestCase;
use FP\SEO\MyExtension\MyClass;

class MyClassTest extends TestCase {
    
    public function test_my_method_returns_expected_value(): void {
        $instance = new MyClass();
        $result = $instance->myMethod();
        
        $this->assertSame( 'expected', $result );
    }
}
```

## Esempi Completi

### Esempio 1: Aggiungere un Controllo per la Lunghezza del Contenuto

```php
<?php
namespace MyPlugin\SEO\Checks;

use FP\SEO\Analysis\CheckInterface;
use FP\SEO\Analysis\Context;
use FP\SEO\Analysis\Result;

class ContentLengthCheck implements CheckInterface {
    
    private const MIN_LENGTH = 300;
    private const IDEAL_LENGTH = 1000;
    
    public function id(): string {
        return 'content_length';
    }
    
    public function label(): string {
        return __( 'Content Length', 'my-plugin' );
    }
    
    public function description(): string {
        return __( 'Checks if content has adequate length for SEO.', 'my-plugin' );
    }
    
    public function run( Context $context ): Result {
        $content = $context->content();
        $word_count = str_word_count( wp_strip_all_tags( $content ) );
        
        if ( $word_count < self::MIN_LENGTH ) {
            return new Result(
                Result::STATUS_FAIL,
                sprintf(
                    __( 'Content is too short (%d words). Minimum: %d words.', 'my-plugin' ),
                    $word_count,
                    self::MIN_LENGTH
                )
            );
        }
        
        if ( $word_count < self::IDEAL_LENGTH ) {
            return new Result(
                Result::STATUS_WARN,
                sprintf(
                    __( 'Content length is adequate (%d words) but could be improved. Ideal: %d+ words.', 'my-plugin' ),
                    $word_count,
                    self::IDEAL_LENGTH
                )
            );
        }
        
        return new Result(
            Result::STATUS_PASS,
            sprintf(
                __( 'Content length is excellent (%d words).', 'my-plugin' ),
                $word_count
            )
        );
    }
}
```

### Esempio 2: Personalizzare la Pagina delle Impostazioni

```php
<?php
// In functions.php o in un plugin

// Aggiunge una sezione personalizzata alla tab General
add_action( 'fp_seo_performance_settings_general_after', function( $options ) {
    $my_option = $options['general']['my_custom_option'] ?? false;
    ?>
    <tr>
        <th scope="row"><?php esc_html_e( 'My Custom Option', 'my-theme' ); ?></th>
        <td>
            <label>
                <input type="checkbox" 
                       name="fp_seo_perf_options[general][my_custom_option]" 
                       value="1" 
                       <?php checked( $my_option ); ?> />
                <?php esc_html_e( 'Enable my custom feature', 'my-theme' ); ?>
            </label>
        </td>
    </tr>
    <?php
});
```

## Risorse Aggiuntive

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [PSR-12 Coding Standard](https://www.php-fig.org/psr/psr-12/)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)

## Supporto

Per domande o supporto sull'estensione del plugin:
- Email: info@francescopasseri.com
- Website: https://francescopasseri.com

## Contribuire

Se desideri contribuire al plugin con nuove funzionalità o miglioramenti, segui queste linee guida:

1. Fork del repository
2. Crea un branch per la tua feature (`git checkout -b feature/AmazingFeature`)
3. Commit delle modifiche (`git commit -m 'Add some AmazingFeature'`)
4. Push al branch (`git push origin feature/AmazingFeature`)
5. Apri una Pull Request

Assicurati che il tuo codice:
- Segua gli standard di codifica WordPress
- Includa test unitari
- Sia documentato con DocBlocks
- Non rompa la compatibilità backward