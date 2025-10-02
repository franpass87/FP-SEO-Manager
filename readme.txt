=== FP SEO Performance ===
Contributors: fp, francescopasseri
Author: Francesco Passeri
Author URI: https://francescopasseri.com
Plugin Homepage: https://francescopasseri.com
Tags: seo, content-analysis, editor-tools, performance
Requires at least: 6.2
Tested up to: 6.4
Requires PHP: 8.0
Stable tag: 0.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

FP SEO Performance provides an on-page SEO analyzer with configurable checks, bulk audits, and admin-facing guidance for WordPress editors.

= Highlights =

* Configurable analyzer checks covering metadata, headings, canonical URLs, structured data presets, and internal linking.
* Admin bar badge that surfaces the current post score while editing.
* Bulk auditor that processes multiple posts, caches results for 24 hours, and exports CSV reports.
* Site Health integration and notices to keep administrators informed about outstanding issues.

== Installation ==

1. Download the latest `fp-seo-performance.zip` from a tagged release or build output.
2. Upload the ZIP via **Plugins → Add New** or extract it into `wp-content/plugins/`.
3. Activate **FP SEO Performance** from the Plugins screen.
4. Visit **SEO Performance → Settings** to review analyzer toggles, scoring weights, and permissions.

== Frequently Asked Questions ==

= Does the analyzer run for custom post types? =
Yes. Supported post types are resolved dynamically and can be filtered in the Bulk Auditor screen.

= Can I disable individual checks? =
Yes. Navigate to **SEO Performance → Settings → Analysis** and uncheck the checks you do not need.

= How do I enable the admin bar badge? =
Enable "Admin bar badge" under **Settings → General**. The badge shows up while editing posts if you have the required capability.

= Where do bulk audit results live? =
Recent audits are cached for 24 hours. Use the Bulk Auditor filters to revisit previous runs or export CSV summaries for offline review.

= Are there developer hooks available? =
Use the `fp_seo_perf_checks_enabled` filter to adjust the enabled checks before scoring runs.

== Screenshots ==

1. Analyzer metabox showing on-page checks.
2. Bulk Auditor listing scores for multiple posts.

== Changelog ==

= Unreleased =
* See `CHANGELOG.md` for work in progress items.

= 0.1.2 - 2025-10-01 =
* Centralized admin menu registration, shared analyzer constants, and bulk auditing utilities.

= 0.1.1 - 2025-10-01 =
* Expanded analyzer heuristics for metadata, canonical URLs, and structured data checks.

= 0.1.0 - 2025-09-30 =
* Initial scaffold.

== Upgrade Notice ==

= 0.1.2 =
Bulk audit caching and admin bar badge refinements ensure more reliable scoring for editors.
