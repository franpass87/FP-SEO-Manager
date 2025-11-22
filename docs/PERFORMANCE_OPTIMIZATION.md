# ⚡ Performance Optimization Guide

**FP SEO Performance** includes comprehensive performance optimizations to ensure fast loading times and minimal resource usage.

**Version**: 0.9.0-pre.11  
**Last Updated**: 2025-01-27

---

## 🎯 Overview

The plugin implements multiple layers of performance optimization:

1. **Database Query Optimization** - Reduced queries and efficient caching
2. **Memory Management** - Automatic cleanup and efficient resource usage
3. **Cache System** - Multi-tier caching with Redis/Memcached support
4. **Lazy Loading** - Services loaded only when needed
5. **Asset Optimization** - Deferred scripts and optimized loading

---

## 📊 Performance Features

### 1. Database Query Optimization

#### Meta Query Caching
- **Preloading**: SEO meta fields are preloaded for posts being displayed
- **Reduced Queries**: Multiple `get_post_meta()` calls are batched
- **Cache Groups**: SEO meta uses dedicated cache groups for better invalidation

**Implementation**:
```php
// Automatically preloads SEO meta when post is displayed
add_action( 'the_post', array( $this, 'preload_seo_meta' ) );
```

#### Options Caching
- **Cached Options**: Plugin options use `Cache::remember()` with 1-hour TTL
- **Versioned Keys**: Cache invalidation via versioning system
- **Reduced Database Hits**: Options loaded once per request

**Example**:
```php
// Options are cached automatically
$options = Options::get(); // Uses cache, not direct DB query
```

### 2. Memory Management

#### Automatic Transient Cleanup
- **Daily Cleanup**: Expired transients are automatically removed
- **Memory Savings**: Prevents database bloat from expired cache entries
- **Scheduled Task**: Runs daily via WordPress cron

**Implementation**:
```php
// Scheduled daily cleanup
wp_schedule_event( time(), 'daily', 'fp_seo_cleanup_transients' );
```

#### Memory Monitoring
- **Debug Mode**: Memory usage tracked when `WP_DEBUG` is enabled
- **Peak Usage Tracking**: Monitors memory consumption per operation
- **Performance Metrics**: Detailed metrics available via `PerformanceMonitor`

### 3. Advanced Cache System

#### Multi-Tier Caching
The plugin supports multiple cache backends with automatic fallback:

1. **Redis** (Primary) - Fastest, persistent cache
2. **Memcached** (Fallback) - High-performance memory cache
3. **WordPress Object Cache** (Fallback) - Native WordPress cache
4. **Transients** (Final Fallback) - Database-backed cache

**Configuration**:
```php
// Automatically detects and uses best available cache
$cache = new AdvancedCache();
$value = $cache->get( 'key', 'default', 'group' );
```

#### Cache Groups
- **Global Groups**: Cache groups marked as global for multi-site compatibility
- **Versioned Keys**: Automatic cache invalidation via versioning
- **TTL Management**: Different TTLs for different data types

**Cache TTLs**:
- Short: 5 minutes (300s) - Frequently changing data
- Medium: 1 hour (3600s) - Options and settings
- Long: 24 hours (86400s) - Static content
- Very Long: 7 days (604800s) - Rarely changing data

### 4. Lazy Loading

#### Conditional Service Loading
Services are loaded only when needed:

- **Admin Services**: Loaded only in admin context
- **GEO Services**: Loaded only if GEO is enabled
- **AI Services**: Loaded only if API key is configured
- **GSC Services**: Loaded only if credentials are set

**Example**:
```php
// Services loaded conditionally
if ( is_admin() ) {
    $this->boot_admin_services();
}

if ( $geo_enabled ) {
    $this->boot_geo_services();
}
```

#### Deferred Script Loading
- **Non-Critical Scripts**: Deferred for faster initial page load
- **Admin Scripts**: Loaded only on relevant admin pages
- **Conditional Enqueuing**: Assets loaded based on context

### 5. Database Optimization

#### Query Optimization
- **Indexed Queries**: Uses indexed columns for faster searches
- **Prepared Statements**: 100% prepared statements for security and performance
- **Query Analysis**: Built-in query optimization tools

**Features**:
- Table optimization utilities
- Index creation and management
- Query execution plan analysis
- Slow query detection

#### Database Cleanup
- **Old Data Cleanup**: Removes old score history and analysis data
- **Expired Cache Cleanup**: Automatically removes expired cache entries
- **Fragmentation Analysis**: Monitors and reports table fragmentation

---

## 🔧 Configuration

### Enable Performance Monitoring

Add to `wp-config.php`:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

Performance metrics will be logged and displayed in HTML comments (debug mode only).

### Cache Configuration

#### Redis Setup
```php
// wp-config.php
define( 'WP_REDIS_HOST', '127.0.0.1' );
define( 'WP_REDIS_PORT', 6379 );
```

#### Memcached Setup
```php
// wp-config.php
define( 'WP_MEMCACHED_HOST', '127.0.0.1' );
define( 'WP_MEMCACHED_PORT', 11211 );
```

### Performance Settings

Navigate to **FP SEO Performance → Settings → Performance**:

- **Enable PSI Integration**: Google PageSpeed Insights (optional)
- **Cache TTL**: Adjust cache expiration times
- **Heuristics**: Enable/disable performance heuristics

---

## 📈 Performance Metrics

### Available Metrics

The plugin tracks comprehensive performance metrics:

- **Execution Time**: Per-operation timing
- **Database Queries**: Query count and execution time
- **Memory Usage**: Peak and current memory consumption
- **Cache Statistics**: Hit/miss rates
- **API Calls**: External API call performance

### Accessing Metrics

**Via Code**:
```php
$monitor = PerformanceMonitor::get_instance();
$summary = $monitor->get_summary();
```

**Via Admin**:
- Navigate to **FP SEO Performance → Performance Dashboard**
- View real-time performance metrics
- Export metrics as JSON

### Performance Score

The plugin calculates a performance score (0-100) based on:
- Operation execution times
- Database query performance
- API call latency
- Memory usage

**Score Calculation**:
- Deducts points for slow operations (>1s: -10, >0.5s: -5)
- Deducts points for slow queries (>0.1s: -5)
- Deducts points for slow API calls (>2s: -10, >1s: -5)
- Deducts points for high memory usage (>128MB: -15, >64MB: -10)

---

## 🚀 Best Practices

### For Developers

1. **Use Cached Options**: Always use `Options::get()` instead of `get_option()`
2. **Cache Expensive Operations**: Use `Cache::remember()` for expensive computations
3. **Lazy Load Services**: Load services only when needed
4. **Monitor Performance**: Use `PerformanceMonitor` to track slow operations
5. **Clean Up Resources**: Remove temporary data and expired cache entries

### For Site Administrators

1. **Enable Object Cache**: Use Redis or Memcached for best performance
2. **Monitor Memory Usage**: Check performance dashboard regularly
3. **Clean Up Old Data**: Run database cleanup periodically
4. **Optimize Database**: Use database optimizer tools
5. **Review Performance Metrics**: Check performance score regularly

---

## 🔍 Troubleshooting

### High Memory Usage

**Symptoms**: Site slow, memory errors

**Solutions**:
1. Enable transient cleanup (automatic)
2. Reduce cache TTL for frequently changing data
3. Disable unused features (GEO, GSC if not needed)
4. Increase PHP memory limit if necessary

### Slow Database Queries

**Symptoms**: Slow page loads, high query count

**Solutions**:
1. Enable object cache (Redis/Memcached)
2. Run database optimizer
3. Check for missing indexes
4. Review slow query log

### Cache Not Working

**Symptoms**: Data not cached, frequent cache misses

**Solutions**:
1. Verify cache backend is available
2. Check cache group configuration
3. Verify cache version is not being reset
4. Check for cache conflicts with other plugins

---

## 📚 Related Documentation

- **[Performance Optimizer Class](../src/Utils/PerformanceOptimizer.php)** - Source code
- **[Performance Monitor Class](../src/Utils/PerformanceMonitor.php)** - Monitoring system
- **[Advanced Cache Class](../src/Utils/AdvancedCache.php)** - Cache system
- **[Database Optimizer Class](../src/Utils/DatabaseOptimizer.php)** - Database tools
- **[API Reference](API_REFERENCE.md)** - Performance-related hooks and filters

---

## 📊 Performance Benchmarks

### Typical Performance

- **Page Load Impact**: <50ms additional load time
- **Memory Usage**: <10MB per request
- **Database Queries**: <5 additional queries per page
- **Cache Hit Rate**: >80% for cached operations

### Optimization Impact

- **Query Reduction**: 60-80% reduction in meta queries
- **Memory Savings**: 20-30% reduction with cleanup
- **Cache Performance**: 5-10x faster with Redis/Memcached
- **Load Time**: 10-20% improvement with optimizations enabled

---

## 🔄 Updates

### Version 0.9.0-pre.11
- ✅ Added meta query preloading
- ✅ Added automatic transient cleanup
- ✅ Improved cache group management
- ✅ Enhanced memory monitoring
- ✅ Optimized options caching

---

**Last Updated**: 2025-01-27  
**Maintainer**: Francesco Passeri  
**Plugin Version**: 0.9.0-pre.11



