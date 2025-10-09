# FP SEO Performance

> FP SEO Performance provides an on-page SEO analyzer with configurable checks, bulk audits, and admin-facing guidance for WordPress editors.

| | |
| --- | --- |
| **Name** | FP SEO Performance |
| **Version** | 0.1.2 |
| **Author** | [Francesco Passeri](https://francescopasseri.com) |
| **Author Email** | [info@francescopasseri.com](mailto:info@francescopasseri.com) |
| **Author URI** | https://francescopasseri.com |
| **Requires WordPress** | 6.2+ |
| **Tested up to** | 6.4 |
| **Requires PHP** | 8.0 |
| **License** | GPL-2.0-or-later |

## About

FP SEO Performance equips editors and site managers with actionable feedback while writing content. It calculates SEO scores in the editor, shows a contextual badge in the admin bar, and keeps configuration centralized in a dedicated settings area.

## Features

- On-page SEO analyzer with checks for titles, descriptions, headings, canonical URLs, robots directives, Open Graph and Twitter cards, structured data presets, and internal links.
- Admin bar badge that displays the current post score for quick diagnostics while editing.
- Bulk audit screen that batches analysis across posts, exports CSV reports, and caches recent runs for fast review.
- Configurable scoring weights, language hints, and analyzer toggles stored in WordPress options.
- Site Health integration that surfaces outstanding SEO tasks to administrators.
- **NEW**: Intelligent caching system for improved performance (up to 70% fewer database queries).
- **NEW**: Structured logging with PSR-3 compatibility for debugging and monitoring.
- **NEW**: 15+ hooks and filters for maximum extensibility.
- **NEW**: Custom exception hierarchy for robust error handling.
- **NEW**: Complete CI/CD pipeline with automated testing and quality checks.

## Installation

1. Download the latest release archive from the repository or build script outputs.
2. Upload the extracted `fp-seo-performance` directory to `wp-content/plugins/` or install the ZIP via **Plugins → Add New** in the WordPress dashboard.
3. Activate **FP SEO Performance** from the Plugins screen.
4. Visit **SEO Performance → Settings** to review analyzer defaults and permissions.

## Usage

- Edit any post or page to see the analyzer metabox with detailed check results.
- Enable the admin bar badge in **Settings → General** to monitor scores at a glance.
- Launch the **Bulk Auditor** submenu to batch analyze filtered content and download CSV summaries.
- Adjust scoring weights, enable performance heuristics, or set custom capabilities within the settings tabs.

## Hooks & Filters

### Actions

| Hook | Parameters | Description |
| --- | --- | --- |
| `fp_seo_before_analysis` | `Context $context` | Fires before analysis begins |
| `fp_seo_after_analysis` | `array $result, Context $context` | Fires after analysis completes |
| `fp_seo_before_check` | `CheckInterface $check, Context $context` | Fires before each individual check runs |
| `fp_seo_after_check` | `array $result, CheckInterface $check, Context $context` | Fires after each check completes |
| `fp_seo_log` | `string $level, string $message, array $context, string $formatted` | Fires when a log entry is created |

### Filters

| Hook | Parameters | Return Type | Description |
| --- | --- | --- | --- |
| `fp_seo_perf_checks_enabled` | `array $checks, Context $context` | `array` | Modify the list of enabled analyzer checks before scoring runs |
| `fp_seo_analyzer_checks` | `array $checks, Context $context` | `array` | Modify the complete list of analyzer checks |
| `fp_seo_check_result` | `array $result, CheckInterface $check, Context $context` | `array` | Modify an individual check result |
| `fp_seo_analysis_status` | `string $status, array $summary, Context $context` | `string` | Modify the overall analysis status |
| `fp_seo_analysis_result` | `array $result, Context $context` | `array` | Modify the complete analysis result |

For detailed usage examples and additional documentation, see [IMPROVEMENTS.md](IMPROVEMENTS.md).

## Support

For support requests or customizations, reach out via [francescopasseri.com](https://francescopasseri.com/contact/).

## Development

- Run `composer install` and `npm install` to set up dependencies.
- Execute `composer lint`, `composer test`, and `npm run changelog:from-git` as needed.
- Synchronize author metadata with `npm run sync:author APPLY=true`.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for the full project history.

## Text Domain

- Text domain: `fp-seo-performance`
- Domain path: `/languages`

## Assumptions

- Issue tracking is handled privately via the contact form at francescopasseri.com.
