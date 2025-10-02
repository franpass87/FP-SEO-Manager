# Architecture

This document outlines the internal structure of FP SEO Performance and how its components interact within WordPress.

## Core Components

- **Infrastructure\Plugin** – bootstrapper responsible for registering activation hooks, loading translations, and wiring services through the dependency container.
- **Utils\Container** – lightweight service locator ensuring singletons for admin surfaces, analyzers, and utilities.
- **Admin module** – includes the main menu (`Admin\Menu`), settings pages (`Admin\SettingsPage`), admin notices, admin bar badge, and bulk auditing interface. These classes register WordPress hooks during boot and coordinate assets, AJAX handlers, and CSV exports.
- **Analysis module** – provides the `Analyzer`, context builder, individual checks (canonical, headings, meta descriptions, Open Graph, Twitter cards, schema presets, internal links, and more), and scoring engine. Checks rely on shared context objects to evaluate post content.
- **Editor integration** – the `Editor\Metabox` renders analyzer results inside the post editor and refreshes scores when content changes.
- **Site Health** – `SiteHealth\SeoHealth` surfaces outstanding analyzer failures through WordPress Site Health tests.
- **Utilities** – option helpers (`Utils\Options`), asset loading (`Utils\Assets`), internationalization wrappers (`Utils\I18n`), post type utilities, URL normalization, and version resolution.

## Custom Post Types & Taxonomies

The plugin does not register custom post types. Instead, `Utils\PostTypes` discovers available types eligible for auditing by filtering WordPress' registered types and allowing developers to adjust the list via standard hooks.

## Hooks

- `fp_seo_perf_checks_enabled` *(filter)* – Modify the list of enabled analyzer checks before scoring runs.

## Data Flow

1. When WordPress loads plugins, `Infrastructure\Plugin::boot()` registers admin singletons and hooks.
2. Editing a post triggers the metabox to request analyzer results from `Analysis\Analyzer`, which compiles check results for the current `Analysis\Context`.
3. `Scoring\ScoreEngine` aggregates check outcomes into a numeric score shared with the admin bar badge and bulk auditor.
4. Bulk audits queue selected posts, analyze them in batches via AJAX, cache results in a transient, and optionally export CSV reports.
5. Site Health queries the stored analysis data to highlight unresolved SEO issues to administrators.

## Performance Considerations

- Analyzer operations rely on in-memory evaluation without external HTTP calls unless PageSpeed Insights is enabled.
- Bulk audits throttle requests (default batch size of 10) and cache results for 24 hours to limit database load.
- Assets are enqueued conditionally based on the active admin screen to avoid unnecessary styles or scripts.
