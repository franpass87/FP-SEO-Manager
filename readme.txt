=== FP SEO Performance ===
Contributors: fp, francescopasseri
Author: Francesco Passeri
Author URI: https://francescopasseri.com
Plugin Homepage: https://francescopasseri.com
Tags: seo, ai, gpt-5, content-generation, openai, seo-tools, performance, search-console
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 0.9.0-pre
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

**FP SEO Performance** is the ultimate WordPress SEO plugin with **AI-powered content generation using GPT-5 Nano**. Generate SEO-optimized titles, meta descriptions, and slugs with one click!

= 🤖 AI-Powered Features (NEW!) =

* **GPT-5 Nano Integration** - Lightning-fast AI content generation (50% cheaper than GPT-4!)
* **One-Click Generation** - Generate SEO title, meta description, slug, and focus keyword instantly
* **Smart Context Analysis** - AI analyzes your categories, tags, and content for perfect results
* **Focus Keyword Targeting** - Optional keyword input for precise optimization
* **Character Counters** - Real-time validation with color indicators (🟢🟠🔴)
* **Cost Effective** - Only ~$0.001 per generation!

= ✅ Core SEO Features =

* **Real-time On-page Analysis** - 15+ configurable SEO checks
* **Bulk Audit System** - Analyze multiple posts simultaneously  
* **SEO Score Tracking** - Historical performance tracking
* **Admin Bar Badge** - Quick SEO status at a glance
* **Site Health Integration** - WordPress Site Health checks

= 🌐 GEO & Advanced =

* **Generative Engine Optimization (GEO)** - AI.txt, GEO sitemap, structured content
* **Google Search Console** - Full integration with metrics and auto-indexing
* **Test Suite** - Built-in automated testing (51 tests)

== Installation ==

1. Download the latest `fp-seo-performance.zip` from a tagged release or build output.
2. Upload the ZIP via **Plugins → Add New** or extract it into `wp-content/plugins/`.
3. Activate **FP SEO Performance** from the Plugins screen.
4. Visit **SEO Performance → Settings** to review analyzer toggles, scoring weights, and permissions.

== Frequently Asked Questions ==

= How do I use the AI content generator? =
1. Get an OpenAI API key from https://platform.openai.com/api-keys
2. Go to **FP SEO Performance → Settings → AI** and paste your key
3. Open any post in the editor
4. Find the "🤖 Generazione AI" section in the SEO Performance metabox
5. Optionally enter a focus keyword
6. Click "Genera con AI" and wait 3-5 seconds
7. Review and apply the generated content!

= Which AI model should I use? =
**GPT-5 Nano** is the default and recommended. It's the fastest, most cost-effective (~$0.001/post), and provides excellent quality. Use GPT-5 or GPT-5 Pro only for premium content.

= How much does AI generation cost? =
With GPT-5 Nano: ~$0.0005-0.002 per post. For 1000 posts, it's only $0.50-$2.00 total. Very affordable!

= Does the AI respect character limits? =
Yes! The system has double validation: the AI is instructed to stay within limits (60 chars for title, 155 for meta), and there's a PHP-level safety check that truncates if needed.

= Can I set a focus keyword for AI generation? =
Absolutely! There's an optional "Focus Keyword" input field. If you enter a keyword, the AI will MANDATORY integrate it into the title and meta description.

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

= 0.9.0-pre - 2025-10-25 =
* **MAJOR RELEASE** - AI Integration Complete + Test Suite
* Added: Full OpenAI GPT-5 integration (Nano, Mini, Pro)
* Added: AI-powered SEO content generation (title, meta, slug, keyword)
* Added: Focus keyword input with mandatory integration
* Added: Real-time character counters with color indicators
* Added: Advanced context analysis (categories, tags, post type, excerpt)
* Added: Test Suite admin page (51 automated tests)
* Added: Complete documentation (AI guides, test checklists)
* Changed: Default AI model to GPT-5 Nano (50% cost reduction)
* Changed: Enhanced character validation (60/155 strict limits)
* Fixed: WordPress loading for junction/symlink setups
* Fixed: AJAX compatibility issues
* See `CHANGELOG.md` for complete details

= 0.4.0 - 2025-10-25 =
* Added: GEO (Generative Engine Optimization) support
* Added: Google Search Console integration
* Added: Instant Indexing API
* Added: Score history tracking

= 0.1.2 - 2025-10-01 =
* Centralized admin menu registration, shared analyzer constants, and bulk auditing utilities.

= 0.1.1 - 2025-10-01 =
* Expanded analyzer heuristics for metadata, canonical URLs, and structured data checks.

= 0.1.0 - 2025-09-30 =
* Initial scaffold.

== Upgrade Notice ==

= 0.9.0-pre =
MAJOR UPDATE! AI-powered content generation with GPT-5 Nano is here! Get an OpenAI API key and generate SEO-optimized content with one click. Includes comprehensive test suite and enhanced documentation.

= 0.4.0 =
Major feature release with GEO optimization and Google Search Console integration.
