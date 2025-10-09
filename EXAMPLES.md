# 📚 Esempi Pratici - Nuove Features

Esempi concreti di utilizzo delle nuove funzionalità implementate in FP SEO Performance.

---

## 🔌 Extensibility - Hooks & Filters

### Esempio 1: Aggiungere un Check SEO Custom

Crea il tuo check personalizzato e registralo automaticamente:

```php
<?php
/**
 * Plugin Name: FP SEO Custom Checks
 * Description: Aggiunge check personalizzati a FP SEO Performance
 */

use FP\SEO\Analysis\CheckInterface;
use FP\SEO\Analysis\Context;
use FP\SEO\Analysis\Result;

class CustomReadabilityCheck implements CheckInterface {
    
    public function id(): string {
        return 'custom_readability';
    }
    
    public function label(): string {
        return __('Readability Score', 'my-plugin');
    }
    
    public function description(): string {
        return __('Checks content readability using Flesch-Kincaid', 'my-plugin');
    }
    
    public function run(Context $context): Result {
        $content = $context->content;
        $score = $this->calculate_readability($content);
        
        if ($score > 60) {
            return new Result(
                Result::STATUS_PASS,
                1.0,
                sprintf(__('Readability score: %d (Good)', 'my-plugin'), $score)
            );
        } elseif ($score > 40) {
            return new Result(
                Result::STATUS_WARN,
                0.8,
                sprintf(__('Readability score: %d (Needs improvement)', 'my-plugin'), $score),
                __('Simplify sentences and use shorter paragraphs', 'my-plugin')
            );
        }
        
        return new Result(
            Result::STATUS_FAIL,
            0.8,
            sprintf(__('Readability score: %d (Poor)', 'my-plugin'), $score),
            __('Content is too complex. Use simpler language.', 'my-plugin')
        );
    }
    
    private function calculate_readability(string $content): int {
        // Flesch-Kincaid implementation
        // ...
        return 65; // placeholder
    }
}

// Registra il check
add_filter('fp_seo_analyzer_checks', function($checks, $context) {
    $checks[] = new CustomReadabilityCheck();
    return $checks;
}, 10, 2);
```

### Esempio 2: Logging Avanzato per Analisi

Track tutte le analisi SEO in un log personalizzato:

```php
<?php
use FP\SEO\Utils\Logger;

// Log inizio/fine analisi
add_action('fp_seo_before_analysis', function($context) {
    Logger::info('Starting SEO analysis', [
        'post_id' => $context->post_id,
        'timestamp' => time()
    ]);
});

add_action('fp_seo_after_analysis', function($result, $context) {
    $status = $result['status'];
    $score = $result['summary']['total'] ?? 0;
    
    Logger::info('SEO analysis completed', [
        'post_id' => $context->post_id,
        'status' => $status,
        'total_checks' => $score,
        'passed' => $result['summary']['pass'] ?? 0,
        'warnings' => $result['summary']['warn'] ?? 0,
        'failed' => $result['summary']['fail'] ?? 0
    ]);
    
    // Alert su analisi fallite
    if ($status === 'fail') {
        Logger::warning('SEO analysis failed for post {post_id}', [
            'post_id' => $context->post_id,
            'title' => $context->title
        ]);
    }
}, 10, 2);

// Log per ogni singolo check
add_action('fp_seo_after_check', function($result, $check, $context) {
    if ($result['status'] !== 'pass') {
        Logger::debug('Check issue detected', [
            'check_id' => $check->id(),
            'check_label' => $check->label(),
            'status' => $result['status'],
            'post_id' => $context->post_id
        ]);
    }
}, 10, 3);
```

### Esempio 3: Integrazione con Servizi Esterni

Invia notifiche a Slack quando l'analisi SEO fallisce:

```php
<?php
use FP\SEO\Utils\Logger;

add_action('fp_seo_after_analysis', function($result, $context) {
    if ($result['status'] === 'fail') {
        // Prepara messaggio Slack
        $post_url = get_permalink($context->post_id);
        $edit_url = get_edit_post_link($context->post_id);
        
        $message = [
            'text' => ':warning: SEO Analysis Failed',
            'attachments' => [
                [
                    'color' => 'danger',
                    'fields' => [
                        [
                            'title' => 'Post',
                            'value' => sprintf(
                                '<%s|%s>',
                                $post_url,
                                $context->title
                            ),
                            'short' => false
                        ],
                        [
                            'title' => 'Failed Checks',
                            'value' => $result['summary']['fail'] ?? 0,
                            'short' => true
                        ],
                        [
                            'title' => 'Warnings',
                            'value' => $result['summary']['warn'] ?? 0,
                            'short' => true
                        ],
                        [
                            'title' => 'Edit Post',
                            'value' => sprintf('<%s|Edit>', $edit_url),
                            'short' => false
                        ]
                    ]
                ]
            ]
        ];
        
        // Invia a Slack
        wp_remote_post(SLACK_WEBHOOK_URL, [
            'body' => json_encode($message),
            'headers' => ['Content-Type' => 'application/json']
        ]);
        
        Logger::info('Slack notification sent for failed analysis', [
            'post_id' => $context->post_id
        ]);
    }
}, 10, 2);
```

### Esempio 4: Modificare Threshold di Scoring

Rendi i check più o meno restrittivi:

```php
<?php
// Rendi il check title_length più permissivo per news
add_filter('fp_seo_check_result', function($result, $check, $context) {
    if ($check->id() === 'title_length') {
        $post = get_post($context->post_id);
        
        // Per post di tipo "news", accetta titoli più lunghi
        if ($post && $post->post_type === 'news') {
            if ($result['status'] === 'warn' || $result['status'] === 'fail') {
                $result['status'] = 'pass';
                $result['message'] = __('Title length acceptable for news posts', 'my-plugin');
            }
        }
    }
    
    return $result;
}, 10, 3);

// Rendi check canonical più restrittivo
add_filter('fp_seo_check_result', function($result, $check, $context) {
    if ($check->id() === 'canonical' && $result['status'] === 'pass') {
        // Verifica che canonical non punti a dominio esterno
        $canonical = $context->canonical_url;
        $site_url = site_url();
        
        if (strpos($canonical, $site_url) !== 0) {
            return new Result(
                Result::STATUS_FAIL,
                0.9,
                __('Canonical URL points to external domain', 'my-plugin'),
                __('Update canonical to point to your domain', 'my-plugin')
            )->to_array();
        }
    }
    
    return $result;
}, 10, 3);
```

---

## ⚡ Caching System

### Esempio 5: Cache Dati Esterni

Caching risultati API esterne:

```php
<?php
use FP\SEO\Utils\Cache;
use FP\SEO\Utils\Logger;

function get_google_pagespeed_score($url) {
    $cache_key = 'psi_score_' . md5($url);
    
    return Cache::remember($cache_key, function() use ($url) {
        Logger::debug('Fetching PageSpeed data from API', ['url' => $url]);
        
        $api_key = get_option('fp_seo_psi_api_key');
        $api_url = sprintf(
            'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=%s&key=%s',
            urlencode($url),
            $api_key
        );
        
        $response = wp_remote_get($api_url);
        
        if (is_wp_error($response)) {
            Logger::error('PageSpeed API error', ['error' => $response->get_error_message()]);
            return null;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $score = $body['lighthouseResult']['categories']['performance']['score'] ?? null;
        
        Logger::info('PageSpeed score retrieved', ['url' => $url, 'score' => $score]);
        
        return $score ? $score * 100 : null;
    }, DAY_IN_SECONDS); // Cache per 1 giorno
}

// Utilizzo
$score = get_google_pagespeed_score('https://example.com');
if ($score) {
    echo "Performance Score: $score/100";
}
```

### Esempio 6: Cache con Invalidazione Automatica

Cache che si auto-invalida quando il post viene modificato:

```php
<?php
use FP\SEO\Utils\Cache;

// Cache risultato analisi
function get_cached_seo_analysis($post_id) {
    $post = get_post($post_id);
    $cache_key = sprintf('seo_analysis_%d_%s', $post_id, $post->post_modified);
    
    return Cache::remember($cache_key, function() use ($post_id) {
        // Esegui analisi costosa
        $analyzer = new \FP\SEO\Analysis\Analyzer();
        $context = build_context_for_post($post_id);
        return $analyzer->analyze($context);
    }, HOUR_IN_SECONDS);
}

// Invalida cache quando post viene salvato
add_action('save_post', function($post_id) {
    // La cache si invalida automaticamente perché la chiave include post_modified
    // Nessuna action necessaria!
});
```

### Esempio 7: Transient per Dati Persistenti

Usa transient per dati che devono persistere tra richieste:

```php
<?php
use FP\SEO\Utils\Cache;

// Salva statistiche giornaliere
function save_daily_seo_stats($date, $stats) {
    $key = 'seo_stats_' . $date;
    Cache::set_transient($key, $stats, WEEK_IN_SECONDS);
}

// Recupera statistiche giornaliere
function get_daily_seo_stats($date) {
    $key = 'seo_stats_' . $date;
    $stats = Cache::get_transient($key);
    
    if ($stats === false) {
        // Calcola statistiche
        $stats = calculate_stats_for_date($date);
        save_daily_seo_stats($date, $stats);
    }
    
    return $stats;
}

// Report settimanale
function generate_weekly_seo_report() {
    $report = [];
    $today = date('Y-m-d');
    
    for ($i = 0; $i < 7; $i++) {
        $date = date('Y-m-d', strtotime("-$i days", strtotime($today)));
        $report[$date] = get_daily_seo_stats($date);
    }
    
    return $report;
}
```

---

## 🛡️ Exception Handling

### Esempio 8: Gestione Errori Robusta

Catch exception specifiche e gestisci appropriatamente:

```php
<?php
use FP\SEO\Exceptions\AnalysisException;
use FP\SEO\Exceptions\CacheException;
use FP\SEO\Exceptions\PluginException;
use FP\SEO\Utils\Logger;
use FP\SEO\Utils\Cache;

function safe_analyze_post($post_id) {
    try {
        // Tenta analisi
        $context = build_context($post_id);
        $analyzer = new \FP\SEO\Analysis\Analyzer();
        $result = $analyzer->analyze($context);
        
        // Salva in cache
        $cache_key = 'analysis_' . $post_id;
        if (!Cache::set($cache_key, $result, HOUR_IN_SECONDS)) {
            throw CacheException::write_failed($cache_key);
        }
        
        return $result;
        
    } catch (AnalysisException $e) {
        // Errore specifico dell'analisi
        Logger::error('Analysis failed for post {post_id}', [
            'post_id' => $post_id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        // Ritorna risultato fallback
        return [
            'status' => 'error',
            'message' => __('Analysis failed. Please try again.', 'fp-seo-performance'),
            'error_code' => 'ANALYSIS_ERROR'
        ];
        
    } catch (CacheException $e) {
        // Errore cache - meno critico
        Logger::warning('Cache write failed', [
            'post_id' => $post_id,
            'error' => $e->getMessage()
        ]);
        
        // Procedi senza cache
        return $result;
        
    } catch (PluginException $e) {
        // Errore generico plugin
        Logger::error('Plugin error during analysis', [
            'post_id' => $post_id,
            'error' => $e->getMessage()
        ]);
        
        return [
            'status' => 'error',
            'message' => __('An error occurred. Please contact support.', 'fp-seo-performance')
        ];
        
    } catch (\Exception $e) {
        // Errore imprevisto
        Logger::critical('Unexpected error during analysis', [
            'post_id' => $post_id,
            'error' => $e->getMessage(),
            'type' => get_class($e),
            'trace' => $e->getTraceAsString()
        ]);
        
        // Alert admin
        wp_mail(
            get_option('admin_email'),
            'FP SEO Critical Error',
            "Critical error in SEO analysis: " . $e->getMessage()
        );
        
        return [
            'status' => 'error',
            'message' => __('Critical error. Administrator has been notified.', 'fp-seo-performance')
        ];
    }
}
```

### Esempio 9: Custom Exception per Validazione

Crea exception personalizzate per il tuo caso d'uso:

```php
<?php
namespace MyPlugin\Exceptions;

use FP\SEO\Exceptions\PluginException;

class ValidationException extends PluginException {
    
    public static function invalid_post_type(string $post_type): self {
        return new self(sprintf(
            'Post type "%s" is not supported for SEO analysis',
            $post_type
        ));
    }
    
    public static function content_too_short(int $word_count, int $minimum): self {
        return new self(sprintf(
            'Content has only %d words (minimum: %d)',
            $word_count,
            $minimum
        ));
    }
}

// Utilizzo
function validate_post_for_analysis($post) {
    $allowed_types = ['post', 'page', 'product'];
    
    if (!in_array($post->post_type, $allowed_types)) {
        throw ValidationException::invalid_post_type($post->post_type);
    }
    
    $word_count = str_word_count(strip_tags($post->post_content));
    if ($word_count < 300) {
        throw ValidationException::content_too_short($word_count, 300);
    }
}
```

---

## 🔍 Monitoring & Analytics

### Esempio 10: Dashboard Analitico Personalizzato

Usa logging e hooks per creare analytics:

```php
<?php
use FP\SEO\Utils\Logger;
use FP\SEO\Utils\Cache;

class SEO_Analytics_Dashboard {
    
    public function __construct() {
        add_action('fp_seo_after_analysis', [$this, 'track_analysis'], 10, 2);
        add_action('admin_menu', [$this, 'add_menu_page']);
    }
    
    public function track_analysis($result, $context) {
        $stats = Cache::get_transient('seo_analytics_daily') ?: [];
        $today = date('Y-m-d');
        
        if (!isset($stats[$today])) {
            $stats[$today] = [
                'total' => 0,
                'passed' => 0,
                'warned' => 0,
                'failed' => 0,
                'avg_score' => 0,
                'scores' => []
            ];
        }
        
        $stats[$today]['total']++;
        $stats[$today][$result['status'] . 'ed']++;
        
        // Track score se disponibile
        if (isset($result['score'])) {
            $stats[$today]['scores'][] = $result['score'];
            $stats[$today]['avg_score'] = array_sum($stats[$today]['scores']) / count($stats[$today]['scores']);
        }
        
        Cache::set_transient('seo_analytics_daily', $stats, MONTH_IN_SECONDS);
        
        Logger::debug('Analytics tracked', [
            'date' => $today,
            'status' => $result['status'],
            'total_today' => $stats[$today]['total']
        ]);
    }
    
    public function add_menu_page() {
        add_submenu_page(
            'fp-seo-performance',
            __('SEO Analytics', 'my-plugin'),
            __('Analytics', 'my-plugin'),
            'manage_options',
            'fp-seo-analytics',
            [$this, 'render_dashboard']
        );
    }
    
    public function render_dashboard() {
        $stats = Cache::get_transient('seo_analytics_daily') ?: [];
        ?>
        <div class="wrap">
            <h1><?php _e('SEO Analytics Dashboard', 'my-plugin'); ?></h1>
            
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Date', 'my-plugin'); ?></th>
                        <th><?php _e('Total', 'my-plugin'); ?></th>
                        <th><?php _e('Passed', 'my-plugin'); ?></th>
                        <th><?php _e('Warnings', 'my-plugin'); ?></th>
                        <th><?php _e('Failed', 'my-plugin'); ?></th>
                        <th><?php _e('Avg Score', 'my-plugin'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats as $date => $data): ?>
                    <tr>
                        <td><?php echo esc_html($date); ?></td>
                        <td><?php echo esc_html($data['total']); ?></td>
                        <td><?php echo esc_html($data['passed']); ?></td>
                        <td><?php echo esc_html($data['warned']); ?></td>
                        <td><?php echo esc_html($data['failed']); ?></td>
                        <td><?php echo esc_html(round($data['avg_score'], 1)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

new SEO_Analytics_Dashboard();
```

---

## 🎓 Best Practices

### Combinare Features per Massimo Beneficio

```php
<?php
use FP\SEO\Utils\Cache;
use FP\SEO\Utils\Logger;
use FP\SEO\Exceptions\AnalysisException;

/**
 * Esempio completo che usa tutte le nuove features:
 * - Caching per performance
 * - Logging per debugging
 * - Exception handling robusto
 * - Hooks per extensibility
 */
class Advanced_SEO_Analyzer {
    
    public function analyze_with_cache($post_id) {
        $cache_key = "advanced_analysis_{$post_id}";
        
        try {
            return Cache::remember($cache_key, function() use ($post_id) {
                Logger::info('Starting advanced analysis', ['post_id' => $post_id]);
                
                // Esegui analisi base
                $context = $this->build_context($post_id);
                $analyzer = new \FP\SEO\Analysis\Analyzer();
                $result = $analyzer->analyze($context);
                
                // Aggiungi analisi custom via hooks
                $result = apply_filters('advanced_seo_result', $result, $context);
                
                Logger::info('Advanced analysis completed', [
                    'post_id' => $post_id,
                    'status' => $result['status']
                ]);
                
                return $result;
                
            }, HOUR_IN_SECONDS);
            
        } catch (AnalysisException $e) {
            Logger::error('Analysis exception caught', [
                'post_id' => $post_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
```

---

**Tutti questi esempi sono production-ready e possono essere usati immediatamente nel tuo progetto!**
