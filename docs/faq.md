# Frequently Asked Questions

## How accurate are the analyzer scores?
Scores aggregate results from individual checks defined in `Analysis\Checks`. Each check reports pass, warning, or fail status, and the `ScoreEngine` converts those into weighted scores. Adjust weights under **Settings → Analysis** if certain checks matter more to your workflow.

## Can I change which users access the plugin settings?
Yes. Update the capability under **Settings → Advanced**. The default is `manage_options`, but any capability string recognized by WordPress can be used.

## What happens if PageSpeed Insights is enabled without an API key?
Performance heuristics still run, but external PSI requests are skipped until a valid key is stored. This prevents quota errors while keeping local checks active.

## Where are bulk audit results stored?
The most recent audit is cached in the `fp_seo_performance_bulk_results` transient for 24 hours. Running another audit overwrites the cached snapshot.

## Does the plugin modify published content?
No. The analyzer operates on in-memory post data and stores scores separately. It never alters post content, metadata, or front-end rendering.

## How do I customize which checks are available?
Filter the list with `fp_seo_perf_checks_enabled` or toggle individual checks in **Settings → Analysis**. Developers can also extend `Analysis\Checks` with custom classes following the existing interface.

## Can I export analyzer data for reporting?
Yes. The Bulk Auditor provides a CSV export action that includes post titles, permalinks, scores, and failing checks for offline review.

## Is multisite supported?
The plugin relies on standard WordPress APIs and runs on multisite. Configure settings per site to ensure each network site maintains independent analyzer defaults.
