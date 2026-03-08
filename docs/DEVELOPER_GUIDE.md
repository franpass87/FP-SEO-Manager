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

1. Create check class in your plugin/theme:

```php
namespace MyPlugin\SEO;

use FP\SEO\Analysis\CheckInterface;
use FP\SEO\Analysis\Context;
use FP\SEO\Analysis\Result;

class MyCustomCheck implements CheckInterface {
    public function run( Context $context ): Result {
        $post_id = $context->get_post_id();
        $content = $context->get_content();
        
        // Your check logic
        $passed = $this->validate_something( $content );
        
        return new Result(
            $passed,
            $passed ? 'Check passed' : 'Check failed',
            [ 
                'hint' => 'How to fix this',
                'severity' => 'high', // or 'medium', 'low'
            ]
        );
    }
    
    public function get_id(): string {
        return 'my_custom_check';
    }
    
    public function get_label(): string {
        return __( 'My Custom Check', 'my-plugin' );
    }
    
    private function validate_something( string $content ): bool {
        // Your validation logic
        return strpos( $content, 'required_text' ) !== false;
    }
}
```

2. Register the check:

```php
add_filter( 'fp_seo_perf_checks', function( $checks ) {
    $checks['my_custom_check'] = MyCustomCheck::class;
    return $checks;
} );

// Enable the check
add_filter( 'fp_seo_perf_checks_enabled', function( $enabled ) {
    $enabled[] = 'my_custom_check';
    return $enabled;
} );

// Set custom weight for scoring
add_filter( 'fp_seo_scoring_weights', function( $weights ) {
    $weights['my_custom_check'] = 10; // 10 points
    return $weights;
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

### Adding Custom AJAX Handler

```php
namespace MyPlugin\SEO;

use FP\SEO\Core\Services\Validation\InputValidator;
use FP\SEO\Utils\RateLimiter;
use FP\SEO\Core\Services\Logger\WordPressLogger;

class MyAjaxHandler {
    private InputValidator $validator;
    
    public function __construct() {
        $logger = new WordPressLogger();
        $cache = \FP\SEO\Infrastructure\Plugin::instance()
            ->get_container()
            ->get( \FP\SEO\Infrastructure\Contracts\CacheInterface::class );
        $rate_limiter = new RateLimiter( $cache );
        $this->validator = new InputValidator( $logger, $rate_limiter );
    }
    
    public function register(): void {
        add_action( 'wp_ajax_fp_seo_my_action', [ $this, 'handle' ] );
        add_action( 'wp_ajax_nopriv_fp_seo_my_action', [ $this, 'handle' ] ); // If needed
    }
    
    public function handle(): void {
        // Rate limiting
        $rate_check = $this->validator->validate_ajax_rate_limit( 'my_action', 60, 60 );
        if ( ! $rate_check['valid'] ) {
            wp_send_json_error( [ 'message' => $rate_check['error'] ], 429 );
        }
        
        // Verify nonce
        $nonce = $_POST['nonce'] ?? '';
        if ( ! wp_verify_nonce( $nonce, 'fp_seo_my_nonce' ) ) {
            wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
        }
        
        // Validate input with schema
        $schema = [
            'post_id' => [
                'type' => 'integer',
                'required' => true,
                'min' => 1,
            ],
            'action' => [
                'type' => 'string',
                'required' => true,
                'enum' => [ 'analyze', 'optimize' ],
            ],
        ];
        
        $validation = $this->validator->validate_schema( $_POST, $schema );
        if ( ! $validation['valid'] ) {
            wp_send_json_error( [ 
                'message' => 'Validation failed',
                'errors' => $validation['errors'],
            ], 400 );
        }
        
        // Sanitize input
        $sanitized = $this->validator->sanitize_input( $_POST, $schema );
        
        // Check capability
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
        }
        
        // Process request
        try {
            $result = $this->process_request( $sanitized );
            wp_send_json_success( $result );
        } catch ( \Exception $e ) {
            wp_send_json_error( [ 
                'message' => 'Processing failed',
                'error' => $e->getMessage(),
            ], 500 );
        }
    }
    
    private function process_request( array $data ): array {
        // Your processing logic
        return [ 'success' => true ];
    }
}

// Register handler
$handler = new MyAjaxHandler();
$handler->register();
```

---

## 🎣 Hooks & Filters

### Action Hooks

| Hook | Description | Parameters | When |
|------|-------------|------------|------|
| `fpseo_after_score_calculation` | After SEO score calculated | `$post_id` (int), `$score_data` (array) | After analysis completes |
| `fp_seo_auto_generation_complete` | After AI auto-generation | `$post_id` (int), `$post` (WP_Post) | After AI generates content |
| `fp_seo_qa_regeneration_complete` | After Q&A regeneration | `$post_id` (int), `$post` (WP_Post) | After Q&A pairs generated |
| `fp_seo_log` | After log entry created | `$level` (string), `$message` (string), `$context` (array), `$formatted` (string) | Every log entry |
| `fp_seo_metabox_rendered` | After metabox rendered | `$post_id` (int), `$post` (WP_Post) | When metabox HTML output |
| `fp_seo_cache_cleared` | After cache cleared | `$group` (string) | When cache group cleared |
| `fp_seo_api_call_complete` | After API call | `$api_name` (string), `$endpoint` (string), `$response` (array) | After external API call |

### Filters

| Filter | Description | Parameters | Return | Priority |
|--------|-------------|------------|--------|----------|
| `fp_seo_perf_checks` | Modify enabled SEO checks | `$checks` (array) | Array of check classes | 10 |
| `fp_seo_perf_checks_enabled` | Filter enabled check IDs | `$enabled` (array) | Array of check IDs | 10 |
| `fp_seo_scoring_weights` | Modify scoring weights | `$weights` (array) | Array of weights | 10 |
| `fp_seo_analysis_context` | Modify analysis context | `$context` (Context) | Context object | 10 |
| `fp_seo_ai_prompt` | Modify AI generation prompt | `$prompt` (string), `$post_id` (int) | String | 10 |
| `fp_seo_ai_response` | Filter AI response | `$response` (array), `$post_id` (int) | Array | 10 |
| `fp_seo_metabox_post_types` | Filter supported post types | `$post_types` (array) | Array of post type strings | 10 |
| `fp_seo_cache_key` | Modify cache key | `$key` (string), `$group` (string) | String | 10 |
| `fp_seo_cache_ttl` | Modify cache TTL | `$ttl` (int), `$key` (string), `$group` (string) | Integer | 10 |
| `fp_seo_validation_schema` | Modify validation schema | `$schema` (array), `$context` (string) | Array | 10 |
| `fp_seo_rate_limit_max` | Modify rate limit | `$max` (int), `$action` (string) | Integer | 10 |

### Example Usage

#### Adding Custom SEO Check

```php
// 1. Create your check class
namespace MyPlugin\SEO;

use FP\SEO\Analysis\CheckInterface;
use FP\SEO\Analysis\Context;
use FP\SEO\Analysis\Result;

class CustomSchemaCheck implements CheckInterface {
    public function run( Context $context ): Result {
        $has_schema = $this->check_schema_exists( $context->get_post_id() );
        
        return new Result(
            $has_schema,
            $has_schema ? 'Schema markup found' : 'Schema markup missing',
            [ 'hint' => 'Add structured data to improve rich snippets' ]
        );
    }
    
    public function get_id(): string {
        return 'custom_schema_check';
    }
    
    public function get_label(): string {
        return __( 'Schema Markup', 'my-plugin' );
    }
    
    private function check_schema_exists( int $post_id ): bool {
        $schema = get_post_meta( $post_id, '_fp_seo_schema', true );
        return ! empty( $schema );
    }
}

// 2. Register the check
add_filter( 'fp_seo_perf_checks', function( $checks ) {
    $checks['custom_schema'] = CustomSchemaCheck::class;
    return $checks;
} );

// 3. Enable the check
add_filter( 'fp_seo_perf_checks_enabled', function( $enabled ) {
    $enabled[] = 'custom_schema';
    return $enabled;
} );
```

#### Modifying AI Generation

```php
// Customize AI prompt with additional context
add_filter( 'fp_seo_ai_prompt', function( $prompt, $post_id ) {
    $post = get_post( $post_id );
    $author = get_userdata( $post->post_author );
    $categories = get_the_category_list( ', ', '', $post_id );
    
    $enhanced_prompt = $prompt . "\n\n";
    $enhanced_prompt .= "Additional Context:\n";
    $enhanced_prompt .= "- Author: " . $author->display_name . "\n";
    $enhanced_prompt .= "- Categories: " . $categories . "\n";
    $enhanced_prompt .= "- Post Type: " . $post->post_type . "\n";
    
    return $enhanced_prompt;
}, 10, 2 );

// Modify AI response before saving
add_filter( 'fp_seo_ai_response', function( $response, $post_id ) {
    if ( isset( $response['data']['seo_title'] ) ) {
        // Add custom prefix
        $response['data']['seo_title'] = '[Premium] ' . $response['data']['seo_title'];
    }
    return $response;
}, 10, 2 );
```

#### Customizing Analysis Context

```php
add_filter( 'fp_seo_analysis_context', function( $context, $post_id ) {
    // Add custom data to context
    $context->set_meta( 'custom_field', get_post_meta( $post_id, '_custom_seo_field', true ) );
    return $context;
}, 10, 2 );
```

#### Listening to Events

```php
// Send notification when score is low
add_action( 'fpseo_after_score_calculation', function( $post_id, $score_data ) {
    if ( $score_data['score'] < 50 ) {
        $admin_email = get_option( 'admin_email' );
        wp_mail( $admin_email, 'Low SEO Score Alert', 
            "Post ID {$post_id} has a low SEO score: {$score_data['score']}" 
        );
    }
}, 10, 2 );

// Log all API calls
add_action( 'fp_seo_api_call_complete', function( $api_name, $endpoint, $response ) {
    error_log( "FP SEO API Call: {$api_name} -> {$endpoint}" );
    if ( isset( $response['error'] ) ) {
        error_log( "API Error: " . $response['error'] );
    }
}, 10, 3 );
```

#### Custom Cache Strategy

```php
// Extend cache TTL for specific keys
add_filter( 'fp_seo_cache_ttl', function( $ttl, $key, $group ) {
    if ( $group === 'ai_responses' ) {
        return DAY_IN_SECONDS; // Cache AI responses for 24 hours
    }
    return $ttl;
}, 10, 3 );

// Modify cache keys for multi-site compatibility
add_filter( 'fp_seo_cache_key', function( $key, $group ) {
    if ( is_multisite() ) {
        return get_current_blog_id() . '_' . $key;
    }
    return $key;
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
- Verify PHP version is 8.0+
- Check WordPress version is 6.2+

#### AJAX Not Working
- Verify nonce is correct
- Check user capabilities
- Inspect browser console for JS errors
- Verify AJAX action is registered
- Check rate limiting (too many requests)

#### Database Issues
- Verify table exists: `wp_fp_seo_score_history`
- Check `$wpdb->last_error`
- Run plugin activation to create tables
- Verify database user has CREATE TABLE permissions

#### Cache Issues
- Clear WordPress object cache
- Check if Redis/Memcached is configured correctly
- Verify transients table is not corrupted
- Use `wp_cache_flush()` to reset cache

#### Performance Issues
- Check Performance Dashboard for slow queries
- Review memory usage in Performance Monitor
- Disable unnecessary SEO checks
- Use lazy loading for heavy services

#### AI Generation Failing
- Verify OpenAI API key is valid
- Check API rate limits
- Review error logs for API errors
- Verify model name is correct (gpt-5-nano, etc.)

#### Metabox Not Showing
- Verify post type is supported
- Check user has required capabilities
- Review browser console for JavaScript errors
- Verify metabox is registered for post type

#### Score Not Updating
- Clear cache and retry
- Verify analysis checks are enabled
- Check if post type is analyzable
- Review analysis logs in debug mode

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

## 🔧 Troubleshooting Guide

### Debug Mode Setup

Enable comprehensive debugging:

```php
// In wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'SCRIPT_DEBUG', true );
```

### Common Error Messages

#### "Plugin failed to load"
- **Cause**: Autoloader not found or fatal error
- **Solution**: 
  - Run `composer install` in plugin directory
  - Check PHP error logs
  - Verify PHP version >= 8.0

#### "Metabox not appearing"
- **Cause**: Post type not supported or capability issue
- **Solution**:
  - Check post type is in analyzable list
  - Verify user has `edit_posts` capability
  - Review browser console for JavaScript errors

#### "AI generation fails"
- **Cause**: Invalid API key or rate limit
- **Solution**:
  - Verify API key in Settings > AI
  - Check OpenAI account has credits
  - Review API audit log for errors

#### "Cache not working"
- **Cause**: Object cache not configured
- **Solution**:
  - Install Redis or Memcached
  - Configure `wp-config.php` with cache constants
  - Use Performance Dashboard to test cache

### Performance Debugging

```php
// Get performance metrics
$monitor = \FP\SEO\Utils\PerformanceMonitor::get_instance();
$summary = $monitor->get_summary();

// Check slow operations
foreach ( $summary['execution_time']['operations'] as $op => $data ) {
    if ( $data['execution_time'] > 1.0 ) {
        error_log( "Slow operation: {$op} took {$data['execution_time']}s" );
    }
}

// Check database queries
$db_metrics = $monitor->get_db_metrics();
error_log( "Total queries: " . $db_metrics['total_queries'] );
error_log( "Total DB time: " . $db_metrics['total_time'] );
```

### Testing Extensions

```php
// Test your custom check
add_action( 'admin_init', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    
    $analyzer = \FP\SEO\Infrastructure\Plugin::instance()
        ->get_container()
        ->get( \FP\SEO\Analysis\Analyzer::class );
    
    $result = $analyzer->analyze( 123 ); // Test post ID
    error_log( 'Analysis result: ' . print_r( $result, true ) );
} );
```

### Getting Help

1. **Check Logs**: Review `wp-content/debug.log` for errors
2. **Enable Debug**: Set `WP_DEBUG` to true
3. **Test Suite**: Run `composer test` to verify functionality
4. **Performance Dashboard**: Check for slow operations
5. **GitHub Issues**: Report bugs with full error logs

---

**Last Updated**: 2025-01-27  
**Plugin Version**: 0.9.0-pre.72







