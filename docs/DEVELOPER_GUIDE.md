# 👨‍💻 Developer Guide - FP SEO Manager

Complete guide for developers working with or extending FP SEO Manager.

**Version**: 0.9.0-pre.11  
**Last Updated**: 2025-01-27

---

## 📋 Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Getting Started](#getting-started)
3. [Core Concepts](#core-concepts)
4. [Extending the Plugin](#extending-the-plugin)
5. [Hooks & Filters](#hooks--filters)
6. [API Reference](#api-reference)
7. [Best Practices](#best-practices)
8. [Testing](#testing)
9. [Debugging](#debugging)

---

## 🏗️ Architecture Overview

### Plugin Structure

```
FP-SEO-Manager/
├── src/                          # PHP source (PSR-4)
│   ├── Infrastructure/          # Core bootstrap & DI
│   ├── Admin/                   # Admin UI components
│   ├── Analysis/                # SEO analysis engine
│   ├── Editor/                  # Post editor integration
│   ├── Integrations/            # External APIs (OpenAI, GSC)
│   ├── GEO/                     # Generative Engine Optimization
│   ├── Utils/                   # Utility classes
│   └── ...
├── assets/                       # Frontend assets
├── docs/                         # Documentation
└── vendor/                       # Composer dependencies
```

### Key Design Patterns

- **Dependency Injection**: `Container` class manages service lifecycle
- **Singleton Pattern**: Core services (Plugin, Container)
- **Strategy Pattern**: Analysis checks implement `CheckInterface`
- **Factory Pattern**: Service registration in `Plugin::boot()`
- **Observer Pattern**: WordPress hooks and filters

### Namespace Structure

```php
FP\SEO\Infrastructure\    // Core bootstrap
FP\SEO\Admin\             // Admin UI
FP\SEO\Analysis\          // SEO analysis
FP\SEO\Integrations\      // External APIs
FP\SEO\Utils\             // Utilities
```

---

## 🚀 Getting Started

### Prerequisites

- PHP 8.0+
- WordPress 6.2+
- Composer
- Node.js (for asset building)

### Setup Development Environment

```bash
# Clone repository
git clone https://github.com/francescopasseri/fp-seo-performance.git
cd fp-seo-performance

# Install dependencies
composer install

# Install dev dependencies
composer install --dev

# Run tests
composer test

# Check code standards
composer phpcs

# Static analysis
composer phpstan
```

### Development Workflow

1. Create feature branch: `git checkout -b feature/your-feature`
2. Make changes following [Best Practices](#best-practices)
3. Write/update tests
4. Run `composer test` and `composer phpcs`
5. Update documentation
6. Commit with descriptive messages
7. Open Pull Request

---

## 🧩 Core Concepts

### 1. Service Container

The plugin uses a lightweight DI container:

```php
$container = \FP\SEO\Infrastructure\Plugin::instance()->get_container();

// Get a service
$analyzer = $container->get( \FP\SEO\Analysis\Analyzer::class );

// Register singleton
$container->singleton( MyService::class );
```

### 2. Analysis Engine

SEO checks implement `CheckInterface`:

```php
interface CheckInterface {
    public function run( Context $context ): Result;
    public function get_id(): string;
    public function get_label(): string;
}
```

### 3. Options Management

Use `Options` class for settings:

```php
// Get option
$api_key = \FP\SEO\Utils\Options::get_option( 'ai.openai_api_key' );

// Get all options
$options = \FP\SEO\Utils\Options::get();

// Update option
\FP\SEO\Utils\Options::update( [ 'ai' => [ 'openai_api_key' => 'sk-...' ] ] );
```

### 4. Logging

Use centralized `Logger` class:

```php
use FP\SEO\Utils\Logger;

Logger::debug( 'Debug message', [ 'context' => $data ] );
Logger::info( 'Info message', [ 'user_id' => 123 ] );
Logger::warning( 'Warning message', [ 'error' => $error ] );
Logger::error( 'Error message', [ 'exception' => $e->getMessage() ] );
```

**Note**: Logging only occurs when `WP_DEBUG` is enabled.

---

## 🔌 Extending the Plugin

### Adding a New SEO Check

1. Create check class in `src/Analysis/Checks/`:

```php
namespace FP\SEO\Analysis\Checks;

use FP\SEO\Analysis\CheckInterface;
use FP\SEO\Analysis\Context;
use FP\SEO\Analysis\Result;

class MyCustomCheck implements CheckInterface {
    public function run( Context $context ): Result {
        // Your check logic
        $passed = $this->validate_something( $context );
        
        return new Result(
            $passed,
            $passed ? 'Check passed' : 'Check failed',
            [ 'hint' => 'How to fix this' ]
        );
    }
    
    public function get_id(): string {
        return 'my_custom_check';
    }
    
    public function get_label(): string {
        return __( 'My Custom Check', 'fp-seo-performance' );
    }
}
```

2. Register the check:

```php
add_filter( 'fp_seo_perf_checks', function( $checks ) {
    $checks['my_custom_check'] = MyCustomCheck::class;
    return $checks;
} );
```

### Adding Admin Settings Tab

1. Create tab renderer in `src/Admin/Settings/`:

```php
namespace FP\SEO\Admin\Settings;

class MyTabRenderer {
    public function render(): void {
        ?>
        <div class="fp-seo-settings-tab">
            <h2><?php esc_html_e( 'My Tab', 'fp-seo-performance' ); ?></h2>
            <!-- Your settings fields -->
        </div>
        <?php
    }
}
```

2. Register in `SettingsPage`:

```php
add_filter( 'fp_seo_settings_tabs', function( $tabs ) {
    $tabs['my_tab'] = [
        'label' => __( 'My Tab', 'fp-seo-performance' ),
        'renderer' => MyTabRenderer::class,
    ];
    return $tabs;
} );
```

### Adding AJAX Handler

```php
namespace FP\SEO\Admin;

class MyAjaxHandler {
    public function register(): void {
        add_action( 'wp_ajax_fp_seo_my_action', [ $this, 'handle' ] );
    }
    
    public function handle(): void {
        // Verify nonce
        check_ajax_referer( 'fp_seo_my_nonce', 'nonce' );
        
        // Check capability
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
        }
        
        // Process request
        $result = $this->process_request();
        
        wp_send_json_success( $result );
    }
}
```

---

## 🎣 Hooks & Filters

### Action Hooks

| Hook | Description | Parameters |
|------|-------------|------------|
| `fpseo_after_score_calculation` | After SEO score calculated | `$post_id`, `$score_data` |
| `fp_seo_auto_generation_complete` | After AI auto-generation | `$post_id`, `$post` |
| `fp_seo_qa_regeneration_complete` | After Q&A regeneration | `$post_id`, `$post` |
| `fp_seo_log` | After log entry created | `$level`, `$message`, `$context`, `$formatted` |

### Filters

| Filter | Description | Parameters | Return |
|--------|-------------|------------|--------|
| `fp_seo_perf_checks` | Modify enabled SEO checks | `$checks` (array) | Array of check classes |
| `fp_seo_perf_checks_enabled` | Filter enabled check IDs | `$enabled` (array) | Array of check IDs |
| `fp_seo_scoring_weights` | Modify scoring weights | `$weights` (array) | Array of weights |
| `fp_seo_analysis_context` | Modify analysis context | `$context` (Context) | Context object |
| `fp_seo_ai_prompt` | Modify AI generation prompt | `$prompt`, `$post_id` | String |
| `fp_seo_ai_response` | Filter AI response | `$response`, `$post_id` | Array |

### Example Usage

```php
// Add custom check
add_filter( 'fp_seo_perf_checks', function( $checks ) {
    $checks['my_check'] = MyCheck::class;
    return $checks;
} );

// Modify AI prompt
add_filter( 'fp_seo_ai_prompt', function( $prompt, $post_id ) {
    $post = get_post( $post_id );
    return $prompt . "\n\nPost category: " . get_the_category_list( ', ', '', $post_id );
}, 10, 2 );

// Listen to score calculation
add_action( 'fpseo_after_score_calculation', function( $post_id, $score_data ) {
    if ( $score_data['score'] < 50 ) {
        // Send notification
    }
}, 10, 2 );
```

---

## 📖 API Reference

### Core Classes

#### `FP\SEO\Infrastructure\Plugin`

Main plugin bootstrap.

```php
$plugin = \FP\SEO\Infrastructure\Plugin::instance();
$container = $plugin->get_container();
```

#### `FP\SEO\Analysis\Analyzer`

SEO analysis engine.

```php
$analyzer = $container->get( \FP\SEO\Analysis\Analyzer::class );
$result = $analyzer->analyze( $post_id );
```

#### `FP\SEO\Integrations\OpenAiClient`

OpenAI API client.

```php
$client = $container->get( \FP\SEO\Integrations\OpenAiClient::class );
$suggestions = $client->generate_seo_suggestions( $post_id, $content, $title, $keyword );
```

#### `FP\SEO\Utils\Options`

Options management.

```php
// Get option with dot notation
$value = \FP\SEO\Utils\Options::get_option( 'ai.openai_api_key' );

// Get all options
$all = \FP\SEO\Utils\Options::get();

// Update options
\FP\SEO\Utils\Options::update( [ 'ai' => [ 'enabled' => true ] ] );
```

#### `FP\SEO\Utils\Logger`

Centralized logging.

```php
use FP\SEO\Utils\Logger;

Logger::debug( 'Message', [ 'context' => $data ] );
Logger::info( 'Message', [ 'user' => $user_id ] );
Logger::warning( 'Message', [ 'error' => $error ] );
Logger::error( 'Message', [ 'exception' => $e ] );
```

---

## ✅ Best Practices

### Security

1. **Always sanitize input**:
   ```php
   $input = sanitize_text_field( $_POST['field'] );
   $url = esc_url_raw( $_POST['url'] );
   $content = wp_kses_post( $_POST['content'] );
   ```

2. **Always escape output**:
   ```php
   echo esc_html( $variable );
   echo esc_attr( $attribute );
   echo esc_url( $url );
   ```

3. **Use prepared statements**:
   ```php
   $wpdb->get_results( $wpdb->prepare( 
       "SELECT * FROM {$wpdb->posts} WHERE ID = %d", 
       $post_id 
   ) );
   ```

4. **Verify nonces**:
   ```php
   check_ajax_referer( 'action_nonce', 'nonce' );
   wp_verify_nonce( $_POST['nonce'], 'action' );
   ```

5. **Check capabilities**:
   ```php
   if ( ! current_user_can( 'edit_posts' ) ) {
       wp_die( 'Unauthorized' );
   }
   ```

### Performance

1. **Use caching**:
   ```php
   $cached = wp_cache_get( $key, 'group' );
   if ( false === $cached ) {
       $cached = expensive_operation();
       wp_cache_set( $key, $cached, 'group', HOUR_IN_SECONDS );
   }
   ```

2. **Lazy load services**:
   ```php
   // Only load when needed
   if ( is_admin() && $this->is_ai_enabled() ) {
       $this->load_ai_services();
   }
   ```

3. **Batch database queries**:
   ```php
   // Avoid N+1 queries
   $posts = get_posts( [ 'include' => $post_ids ] );
   ```

### Code Quality

1. **Follow PSR-4**:
   - One class per file
   - Namespace matches directory structure
   - Class name matches file name

2. **Type hints everywhere**:
   ```php
   public function process( int $post_id, string $content ): array {
       // ...
   }
   ```

3. **Use `declare(strict_types=1)`**:
   ```php
   <?php
   declare(strict_types=1);
   ```

4. **Document with PHPDoc**:
   ```php
   /**
    * Processes SEO analysis for a post.
    *
    * @param int    $post_id Post ID.
    * @param string $content Post content.
    * @return array{score: int, checks: array}
    */
   ```

5. **Handle errors gracefully**:
   ```php
   try {
       $result = $this->risky_operation();
   } catch ( \Exception $e ) {
       Logger::error( 'Operation failed', [ 'error' => $e->getMessage() ] );
       return [];
   }
   ```

---

## 🧪 Testing

### Running Tests

```bash
# Run all tests
composer test

# Run specific test
vendor/bin/phpunit tests/unit/AnalyzerTest.php

# With coverage
vendor/bin/phpunit --coverage-html coverage/
```

### Writing Tests

```php
namespace FP\SEO\Tests\Unit;

use PHPUnit\Framework\TestCase;
use FP\SEO\Analysis\Analyzer;

class AnalyzerTest extends TestCase {
    public function test_analyzes_post_correctly(): void {
        $analyzer = new Analyzer();
        $result = $analyzer->analyze( 123 );
        
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'score', $result );
        $this->assertGreaterThanOrEqual( 0, $result['score'] );
        $this->assertLessThanOrEqual( 100, $result['score'] );
    }
}
```

### Test Checklist

- [ ] Unit tests for core classes
- [ ] Integration tests for API calls
- [ ] Test error handling
- [ ] Test edge cases
- [ ] Maintain >80% code coverage

---

## 🐛 Debugging

### Enable Debug Mode

Add to `wp-config.php`:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

### View Logs

Logs are written to `wp-content/debug.log` when `WP_DEBUG_LOG` is enabled.

The plugin uses centralized logging:

```php
Logger::debug( 'Debug info', [ 'data' => $value ] );
```

### Common Issues

#### Plugin Not Loading
- Check `vendor/autoload.php` exists
- Verify Composer dependencies installed
- Check PHP error logs

#### AJAX Not Working
- Verify nonce is correct
- Check user capabilities
- Inspect browser console for JS errors

#### Database Issues
- Verify table exists: `wp_fp_seo_score_history`
- Check `$wpdb->last_error`
- Run plugin activation to create tables

---

## 📞 Support & Resources

### Documentation
- [Main README](../README.md)
- [Changelog](../CHANGELOG.md)
- [QA Report](../QA-REPORT-PROFONDO-2025.md)
- [Contributing Guide](../CONTRIBUTING.md)

### External Resources
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [PSR-4 Autoloading](https://www.php-fig.org/psr/psr-4/)

### Contact
- **Author**: Francesco Passeri
- **Email**: info@francescopasseri.com
- **Website**: https://francescopasseri.com

---

**Last Updated**: 2025-01-27  
**Plugin Version**: 0.9.0-pre.11




