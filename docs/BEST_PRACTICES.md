# Best Practices - FP SEO Performance

## 📋 Indice

1. [Principi Generali](#principi-generali)
2. [Struttura del Codice](#struttura-del-codice)
3. [Convenzioni di Naming](#convenzioni-di-naming)
4. [Documentazione](#documentazione)
5. [Testing](#testing)
6. [Performance](#performance)
7. [Sicurezza](#sicurezza)
8. [Git Workflow](#git-workflow)

---

## 🎯 Principi Generali

### SOLID Principles

#### S - Single Responsibility Principle
Ogni classe deve avere una sola responsabilità.

```php
// ✅ Corretto - Responsabilità separate
class MetadataResolver {
    public static function resolve_meta_description( $post ): string {
        // Solo risoluzione metadata
    }
}

class MetadataValidator {
    public function validate( string $metadata ): bool {
        // Solo validazione
    }
}

// ❌ Da evitare - Troppe responsabilità
class MetadataManager {
    public function resolve() { /* ... */ }
    public function validate() { /* ... */ }
    public function save() { /* ... */ }
    public function render() { /* ... */ }
}
```

#### O - Open/Closed Principle
Aperto all'estensione, chiuso alla modifica.

```php
// ✅ Corretto - Estensibile tramite inheritance
abstract class SettingsTabRenderer {
    abstract public function render( array $options ): void;
}

class CustomTabRenderer extends SettingsTabRenderer {
    public function render( array $options ): void {
        // Implementazione personalizzata
    }
}

// ❌ Da evitare - Richiede modifica della classe base
class SettingsPage {
    public function render_custom_tab() {
        // Hardcoded nella classe
    }
}
```

#### L - Liskov Substitution Principle
Le sottoclassi devono essere sostituibili con le classi base.

```php
// ✅ Corretto
abstract class Check implements CheckInterface {
    abstract public function run( Context $context ): Result;
}

class TitleCheck extends Check {
    // Rispetta il contratto della classe base
    public function run( Context $context ): Result {
        return new Result( /* ... */ );
    }
}
```

#### I - Interface Segregation Principle
Interfacce piccole e specifiche.

```php
// ✅ Corretto - Interfacce piccole
interface Renderable {
    public function render(): string;
}

interface Validatable {
    public function validate(): bool;
}

// ❌ Da evitare - Interfaccia troppo grande
interface CompleteCheck {
    public function run();
    public function validate();
    public function render();
    public function save();
}
```

#### D - Dependency Inversion Principle
Dipendere da astrazioni, non da implementazioni concrete.

```php
// ✅ Corretto - Dipende da interfaccia
class Analyzer {
    public function __construct( 
        private CheckInterface $check 
    ) {}
}

// ❌ Da evitare - Dipende da implementazione concreta
class Analyzer {
    public function __construct( 
        private TitleCheck $check 
    ) {}
}
```

### DRY - Don't Repeat Yourself

```php
// ✅ Corretto - Utility riutilizzabile
class MetadataResolver {
    public static function resolve_meta_description( $post ): string {
        // Logica centralizzata
    }
}

// Uso nei vari file
$description = MetadataResolver::resolve_meta_description( $post );

// ❌ Da evitare - Codice duplicato
// In BulkAuditPage.php
$meta = get_post_meta( $post->ID, '_fp_seo_meta_description', true );
if ( empty( $meta ) ) {
    $meta = wp_strip_all_tags( $post->post_excerpt );
}

// In Metabox.php - STESSO CODICE RIPETUTO
$meta = get_post_meta( $post->ID, '_fp_seo_meta_description', true );
if ( empty( $meta ) ) {
    $meta = wp_strip_all_tags( $post->post_excerpt );
}
```

### KISS - Keep It Simple, Stupid

```php
// ✅ Corretto - Semplice e chiaro
public function is_enabled(): bool {
    return ! empty( $this->options['enable_analyzer'] );
}

// ❌ Da evitare - Troppo complesso
public function is_enabled(): bool {
    return ( 
        isset( $this->options['enable_analyzer'] ) && 
        $this->options['enable_analyzer'] !== false && 
        $this->options['enable_analyzer'] !== 0 && 
        $this->options['enable_analyzer'] !== '' 
    ) ? true : false;
}
```

---

## 🏗️ Struttura del Codice

### Organizzazione File

```
src/
├── Admin/           # Interfaccia amministrativa
│   ├── Settings/    # Sub-modulo settings
│   └── *.php
├── Analysis/        # Sistema di analisi SEO
│   ├── Checks/      # Controlli individuali
│   └── *.php
├── Editor/          # Integrazione editor
├── Infrastructure/  # Setup e bootstrap
├── Perf/           # Performance monitoring
├── Scoring/        # Sistema di scoring
├── SiteHealth/     # Site Health integration
└── Utils/          # Utilità condivise
```

### Namespace

Segui la struttura PSR-4:

```php
<?php
declare(strict_types=1);

namespace FP\SEO\Admin\Settings;

class GeneralTabRenderer extends SettingsTabRenderer {
    // Implementazione
}
```

### Import

Ordina gli import alfabeticamente:

```php
use FP\SEO\Analysis\Analyzer;
use FP\SEO\Analysis\Context;
use FP\SEO\Utils\MetadataResolver;
use FP\SEO\Utils\Options;
use WP_Post;
```

---

## 📝 Convenzioni di Naming

### Classi

**PascalCase** per classi e interfacce:

```php
class MetadataResolver { }
class SettingsTabRenderer { }
interface CheckInterface { }
```

### Metodi e Proprietà

**snake_case** per metodi pubblici (WordPress standard):

```php
class Example {
    public function get_post_meta() { }
    public function resolve_canonical_url() { }
}
```

**camelCase** per metodi privati (opzionale):

```php
class Example {
    private function processData() { }
    private function validateInput() { }
}
```

### Costanti

**SCREAMING_SNAKE_CASE**:

```php
class Config {
    public const MAX_ITEMS = 100;
    private const CACHE_KEY = 'fp_seo_cache';
}
```

### File

- Classi: `ClassName.php` (PascalCase)
- Test: `ClassNameTest.php`
- File configurazione: `kebab-case.php`

---

## 📚 Documentazione

### DocBlocks

Sempre documentare classi, metodi pubblici e proprietà:

```php
/**
 * Resolves SEO metadata from posts.
 *
 * This utility class provides static methods to extract and process
 * SEO-related metadata from WordPress posts.
 *
 * @package FP\SEO
 * @since 0.2.0
 */
class MetadataResolver {
    
    /**
     * Resolves meta description for a post.
     *
     * Attempts to retrieve custom meta description from post meta.
     * Falls back to post excerpt if no custom value is set.
     *
     * @param WP_Post|int $post Post object or post ID.
     *
     * @return string Resolved meta description.
     *
     * @since 0.2.0
     */
    public static function resolve_meta_description( $post ): string {
        // Implementazione
    }
}
```

### Commenti Inline

Usa commenti inline per logica complessa:

```php
// Check if custom metadata exists in post meta.
$meta = get_post_meta( $post_id, '_fp_seo_meta_description', true );

// Fall back to stripped excerpt if no custom meta is set.
if ( empty( $meta ) && ! empty( $excerpt ) ) {
    $meta = wp_strip_all_tags( $excerpt );
}
```

### TODO Comments

Format standard per TODO:

```php
// TODO: Implement caching mechanism (Issue #123).
// FIXME: Handle edge case with empty titles (Bug #456).
// NOTE: This is a temporary workaround for WordPress 6.0 compatibility.
```

---

## 🧪 Testing

### Test Unitari

Ogni classe pubblica deve avere test:

```php
namespace FP\SEO\Tests\Utils;

use FP\SEO\Utils\MetadataResolver;
use PHPUnit\Framework\TestCase;

class MetadataResolverTest extends TestCase {
    
    /**
     * Test che il metodo restituisce meta custom quando presente.
     */
    public function test_returns_custom_meta_when_available(): void {
        // Arrange
        $post = $this->create_post_with_meta();
        
        // Act
        $result = MetadataResolver::resolve_meta_description( $post );
        
        // Assert
        $this->assertSame( 'Custom meta', $result );
    }
}
```

### Convenzioni Test

1. **Naming**: `test_method_does_something_when_condition`
2. **Pattern AAA**: Arrange, Act, Assert
3. **Un assert per test** (quando possibile)
4. **Test isolati** (no dipendenze tra test)

### Coverage

Target minimo: **80%** di code coverage.

```bash
# Esegui test con coverage
composer test:coverage

# Verifica coverage
composer test:coverage-html
```

---

## ⚡ Performance

### Database Queries

Evita query nel loop:

```php
// ❌ Da evitare - N+1 query problem
foreach ( $posts as $post ) {
    $meta = get_post_meta( $post->ID, '_fp_seo_meta', true );
}

// ✅ Corretto - Batch query
$meta_cache = get_post_meta_batch( wp_list_pluck( $posts, 'ID' ), '_fp_seo_meta' );
foreach ( $posts as $post ) {
    $meta = $meta_cache[ $post->ID ] ?? '';
}
```

### Caching

Usa transient per dati costosi:

```php
// ✅ Corretto - Caching appropriato
public function get_analysis_results( int $post_id ): array {
    $cache_key = 'fp_seo_analysis_' . $post_id;
    $cached = get_transient( $cache_key );
    
    if ( false !== $cached ) {
        return $cached;
    }
    
    $results = $this->perform_analysis( $post_id );
    set_transient( $cache_key, $results, HOUR_IN_SECONDS );
    
    return $results;
}
```

### Lazy Loading

Carica solo quando necessario:

```php
// ✅ Corretto - Caricamento lazy
class HeavyService {
    private ?ExpensiveResource $resource = null;
    
    private function get_resource(): ExpensiveResource {
        if ( null === $this->resource ) {
            $this->resource = new ExpensiveResource();
        }
        return $this->resource;
    }
}
```

---

## 🔒 Sicurezza

### Input Sanitization

Sempre sanitizzare input:

```php
// ✅ Corretto
$title = sanitize_text_field( $_POST['title'] ?? '' );
$url = esc_url_raw( $_POST['url'] ?? '' );
$html = wp_kses_post( $_POST['content'] ?? '' );
```

### Output Escaping

Sempre escape output:

```php
// ✅ Corretto
echo esc_html( $title );
echo esc_attr( $attribute );
echo esc_url( $url );
echo wp_kses_post( $content );
```

### Nonce Verification

Sempre verificare nonce:

```php
// ✅ Corretto
public function save_meta( int $post_id ): void {
    if ( ! isset( $_POST['_fp_seo_nonce'] ) ) {
        return;
    }
    
    $nonce = sanitize_text_field( wp_unslash( $_POST['_fp_seo_nonce'] ) );
    
    if ( ! wp_verify_nonce( $nonce, 'fp_seo_save_meta' ) ) {
        return;
    }
    
    // Procedi con il salvataggio
}
```

### Capability Check

Sempre verificare permessi:

```php
// ✅ Corretto
public function delete_data(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'Insufficient permissions.', 'fp-seo-performance' ) );
    }
    
    // Procedi con l'operazione
}
```

---

## 🔄 Git Workflow

### Branch Naming

```
feature/nome-funzionalita
bugfix/descrizione-bug
hotfix/problema-critico
refactor/area-refactored
docs/tipo-documentazione
```

### Commit Messages

Format: `tipo(scope): descrizione`

**Tipi:**
- `feat`: Nuova funzionalità
- `fix`: Bug fix
- `refactor`: Refactoring
- `docs`: Documentazione
- `test`: Test
- `chore`: Manutenzione

**Esempi:**
```
feat(analysis): add content length check
fix(metabox): resolve duplicate metadata issue
refactor(settings): extract tab renderers
docs(readme): update installation instructions
test(analyzer): add unit tests for CheckRegistry
```

### Pull Request

Template PR:

```markdown
## Descrizione
Breve descrizione delle modifiche

## Tipo di cambiamento
- [ ] Bug fix
- [ ] Nuova funzionalità
- [ ] Breaking change
- [ ] Refactoring
- [ ] Documentazione

## Testing
- [ ] Test unitari aggiunti/aggiornati
- [ ] Test manuali eseguiti
- [ ] Coverage mantenuto > 80%

## Checklist
- [ ] Codice segue le convenzioni del progetto
- [ ] DocBlocks aggiunti/aggiornati
- [ ] CHANGELOG aggiornato
- [ ] Nessun breaking change (o documentato)
```

---

## 📋 Code Review Checklist

### Revisore

- [ ] Codice segue SOLID principles
- [ ] Naming è chiaro e consistente
- [ ] DocBlocks sono completi
- [ ] Test sono presenti e passano
- [ ] Nessun codice duplicato
- [ ] Performance considerata
- [ ] Sicurezza verificata
- [ ] Backward compatibility mantenuta

### Autore

Prima di submit:

- [ ] Self-review eseguita
- [ ] Test locali passano
- [ ] Coverage non diminuita
- [ ] Documentazione aggiornata
- [ ] CHANGELOG aggiornato
- [ ] Commit messages chiari

---

## 🎯 Quick Reference

### Comandi Utili

```bash
# Run tests
composer test

# Run tests with coverage
composer test:coverage

# Code style check
composer phpcs

# Code style fix
composer phpcbf

# Static analysis
composer phpstan

# Run all checks
composer check
```

### Resources

- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [PHPStan Documentation](https://phpstan.org/)
- [PHPUnit Best Practices](https://phpunit.de/getting-started/)
- [SOLID Principles](https://en.wikipedia.org/wiki/SOLID)

---

**Documento mantenuto da:** Francesco Passeri  
**Ultimo aggiornamento:** 8 Ottobre 2025  
**Versione:** 1.0