# 📖 API Reference - FP SEO Manager

Complete API reference for hooks, filters, functions, and classes.

**Version**: 0.9.0-pre.11  
**Last Updated**: 2025-01-27

---

## 🎣 Action Hooks

### Score & Analysis

#### `fpseo_after_score_calculation`
Fired after SEO score is calculated for a post.

```php
do_action( 'fpseo_after_score_calculation', int $post_id, array $score_data );
```

**Parameters**:
- `$post_id` (int) - Post ID
- `$score_data` (array) - Score data with keys: `score`, `status`, `summary`, `checks`

**Example**:
```php
add_action( 'fpseo_after_score_calculation', function( $post_id, $score_data ) {
    if ( $score_data['score'] < 50 ) {
        // Send notification
    }
}, 10, 2 );
```

---

#### `fp_seo_auto_generation_complete`
Fired after AI auto-generation completes.

```php
do_action( 'fp_seo_auto_generation_complete', int $post_id, WP_Post $post );
```

**Parameters**:
- `$post_id` (int) - Post ID
- `$post` (WP_Post) - Post object

---

#### `fp_seo_qa_regeneration_complete`
Fired after Q&A regeneration on post update.

```php
do_action( 'fp_seo_qa_regeneration_complete', int $post_id, WP_Post $post );
```

---

#### `fp_seo_log`
Fired after a log entry is created.

```php
do_action( 'fp_seo_log', string $level, string $message, array $context, string $formatted );
```

**Parameters**:
- `$level` (string) - Log level (debug, info, warning, error)
- `$message` (string) - Original message
- `$context` (array) - Context data
- `$formatted` (string) - Formatted log entry

---

## 🔌 Filters

### Analysis & Checks

#### `fp_seo_perf_checks`
Modify registered SEO checks.

```php
apply_filters( 'fp_seo_perf_checks', array $checks );
```

**Parameters**:
- `$checks` (array) - Array of `check_id => CheckClass` mappings

**Return**: Array of check classes

**Example**:
```php
add_filter( 'fp_seo_perf_checks', function( $checks ) {
    $checks['my_custom_check'] = MyCustomCheck::class;
    return $checks;
} );
```

---

#### `fp_seo_perf_checks_enabled`
Filter enabled check IDs before analysis.

```php
apply_filters( 'fp_seo_perf_checks_enabled', array $enabled_checks, int $post_id );
```

**Parameters**:
- `$enabled_checks` (array) - Array of enabled check IDs
- `$post_id` (int) - Post ID being analyzed

**Return**: Array of enabled check IDs

---

#### `fp_seo_scoring_weights`
Modify scoring weights for checks.

```php
apply_filters( 'fp_seo_scoring_weights', array $weights );
```

**Parameters**:
- `$weights` (array) - Array of `check_id => weight` (float)

**Return**: Array of weights

**Example**:
```php
add_filter( 'fp_seo_scoring_weights', function( $weights ) {
    $weights['title_length'] = 2.0; // Double weight
    return $weights;
} );
```

---

#### `fp_seo_analysis_context`
Modify analysis context before running checks.

```php
apply_filters( 'fp_seo_analysis_context', Context $context, int $post_id );
```

**Parameters**:
- `$context` (Context) - Analysis context object
- `$post_id` (int) - Post ID

**Return**: Context object

---

### AI & Content Generation

#### `fp_seo_ai_prompt`
Modify AI generation prompt.

```php
apply_filters( 'fp_seo_ai_prompt', string $prompt, int $post_id, string $content, string $title );
```

**Parameters**:
- `$prompt` (string) - Generated prompt
- `$post_id` (int) - Post ID
- `$content` (string) - Post content
- `$title` (string) - Post title

**Return**: Modified prompt string

**Example**:
```php
add_filter( 'fp_seo_ai_prompt', function( $prompt, $post_id ) {
    $category = get_the_category( $post_id );
    return $prompt . "\n\nCategory: " . $category[0]->name;
}, 10, 2 );
```

---

#### `fp_seo_ai_response`
Filter AI API response before processing.

```php
apply_filters( 'fp_seo_ai_response', array $response, int $post_id );
```

**Parameters**:
- `$response` (array) - AI response data
- `$post_id` (int) - Post ID

**Return**: Modified response array

---

#### `fp_seo_ai_model`
Filter AI model selection.

```php
apply_filters( 'fp_seo_ai_model', string $model, int $post_id );
```

**Return**: Model name (e.g., 'gpt-5-nano')

---

### Settings & Options

#### `fp_seo_settings_tabs`
Modify settings page tabs.

```php
apply_filters( 'fp_seo_settings_tabs', array $tabs );
```

**Return**: Array of `tab_id => [label, renderer_class]`

---

#### `fp_seo_capability`
Filter required capability for plugin access.

```php
apply_filters( 'fp_seo_capability', string $capability );
```

**Default**: `'manage_options'`

---

### GEO & Structured Data

#### `fp_seo_geo_content_json`
Modify GEO content JSON output.

```php
apply_filters( 'fp_seo_geo_content_json', array $data, int $post_id );
```

---

#### `fp_seo_schema_data`
Modify schema.org JSON-LD output.

```php
apply_filters( 'fp_seo_schema_data', array $schema, int $post_id );
```

---

## 🏗️ Core Classes

### `FP\SEO\Infrastructure\Plugin`

Main plugin bootstrap class.

#### Methods

```php
// Get singleton instance
public static function instance(): self

// Get service container
public function get_container(): Container

// Initialize plugin
public function init(): void
```

#### Usage

```php
$plugin = \FP\SEO\Infrastructure\Plugin::instance();
$container = $plugin->get_container();
```

---

### `FP\SEO\Infrastructure\Container`

Dependency injection container.

#### Methods

```php
// Register singleton
public function singleton( string $id, ?callable $factory = null ): void

// Get service
public function get( string $id ): object

// Register binding
public function bind( string $id, callable $concrete ): void
```

---

### `FP\SEO\Analysis\Analyzer`

SEO analysis engine.

#### Methods

```php
// Analyze a post
public function analyze( int $post_id ): array

// Get analysis result
public function get_result( int $post_id ): ?Result
```

#### Return Format

```php
[
    'score' => 85,                    // 0-100
    'status' => 'good',              // 'excellent', 'good', 'needs_improvement', 'poor'
    'summary' => [
        'pass' => 12,
        'warn' => 2,
        'fail' => 1
    ],
    'checks' => [ /* check results */ ]
]
```

---

### `FP\SEO\Analysis\Context`

Analysis context object.

#### Methods

```php
// Get post content
public function get_content(): string

// Get post title
public function get_title(): string

// Get meta description
public function get_meta_description(): string

// Get focus keyword
public function get_focus_keyword(): string

// Get post object
public function get_post(): WP_Post
```

---

### `FP\SEO\Integrations\OpenAiClient`

OpenAI API client.

#### Methods

```php
// Check if configured
public function is_configured(): bool

// Generate SEO suggestions
public function generate_seo_suggestions(
    int $post_id,
    string $content,
    string $title,
    string $focus_keyword = ''
): array
```

#### Return Format

```php
[
    'success' => true,
    'data' => [
        'seo_title' => '...',
        'meta_description' => '...',
        'slug' => '...',
        'focus_keyword' => '...'
    ]
]
// OR
[
    'success' => false,
    'error' => 'Error message'
]
```

---

### `FP\SEO\Utils\Options`

Options management utility.

#### Static Methods

```php
// Get option with dot notation
public static function get_option( string $key, mixed $default = null ): mixed

// Get all options
public static function get(): array

// Update options
public static function update( array $options ): void

// Get default options
public static function get_defaults(): array

// Get capability
public static function get_capability(): string
```

#### Usage

```php
// Get nested option
$api_key = \FP\SEO\Utils\Options::get_option( 'ai.openai_api_key' );

// Update options
\FP\SEO\Utils\Options::update( [
    'ai' => [
        'openai_api_key' => 'sk-...',
        'openai_model' => 'gpt-5-nano'
    ]
] );
```

---

### `FP\SEO\Utils\Logger`

Centralized logging utility.

#### Static Methods

```php
// Log debug message
public static function debug( string $message, array $context = [] ): void

// Log info message
public static function info( string $message, array $context = [] ): void

// Log warning
public static function warning( string $message, array $context = [] ): void

// Log error
public static function error( string $message, array $context = [] ): void
```

#### Usage

```php
use FP\SEO\Utils\Logger;

Logger::debug( 'Processing post', [ 'post_id' => $post_id ] );
Logger::error( 'API call failed', [ 'error' => $e->getMessage() ] );
```

**Note**: Logging only occurs when `WP_DEBUG` is enabled.

---

## 🔧 Helper Functions

### Analysis Helpers

```php
// Get SEO score for post
function fp_seo_get_score( int $post_id ): ?int

// Check if post is excluded from analysis
function fp_seo_is_excluded( int $post_id ): bool

// Get analysis result
function fp_seo_get_analysis( int $post_id ): ?array
```

### Options Helpers

```php
// Get option value
function fp_seo_get_option( string $key, mixed $default = null ): mixed

// Check if feature is enabled
function fp_seo_is_feature_enabled( string $feature ): bool
```

---

## 📝 Constants

### Plugin Constants

```php
FP_SEO_PERFORMANCE_FILE      // Plugin main file path
FP_SEO_PERFORMANCE_VERSION    // Plugin version
```

### Meta Keys

```php
_fp_seo_title                 // SEO title
_fp_seo_meta_description      // Meta description
_fp_seo_focus_keyword         // Focus keyword
_fp_seo_secondary_keywords    // Secondary keywords
_fp_seo_performance_exclude   // Exclude from analysis
```

---

## 🎯 Common Use Cases

### Add Custom SEO Check

```php
// 1. Create check class
class MyCheck implements CheckInterface {
    public function run( Context $context ): Result {
        $passed = $this->validate( $context );
        return new Result( $passed, $passed ? 'OK' : 'Failed' );
    }
    public function get_id(): string { return 'my_check'; }
    public function get_label(): string { return 'My Check'; }
}

// 2. Register check
add_filter( 'fp_seo_perf_checks', function( $checks ) {
    $checks['my_check'] = MyCheck::class;
    return $checks;
} );
```

### Modify AI Prompt

```php
add_filter( 'fp_seo_ai_prompt', function( $prompt, $post_id ) {
    $custom_instructions = get_option( 'my_custom_ai_instructions' );
    return $prompt . "\n\n" . $custom_instructions;
}, 10, 2 );
```

### Listen to Score Changes

```php
add_action( 'fpseo_after_score_calculation', function( $post_id, $score_data ) {
    if ( $score_data['score'] < 50 ) {
        // Send email notification
        wp_mail( 'admin@example.com', 'Low SEO Score', "Post $post_id has low score" );
    }
}, 10, 2 );
```

### Override AI Model

```php
add_filter( 'fp_seo_ai_model', function( $model, $post_id ) {
    $post_type = get_post_type( $post_id );
    // Use GPT-5 Pro for premium content
    return 'page' === $post_type ? 'gpt-5-pro' : $model;
}, 10, 2 );
```

---

## 📚 Additional Resources

- [Developer Guide](DEVELOPER_GUIDE.md) - Complete development guide
- [Extending Guide](EXTENDING.md) - How to extend the plugin
- [Best Practices](BEST_PRACTICES.md) - Coding standards
- [Architecture](architecture.md) - Technical architecture

---

**Last Updated**: 2025-01-27  
**Plugin Version**: 0.9.0-pre.11

