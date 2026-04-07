# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [0.9.0-pre.86] - 2026-04-06
### Added
- Localizzazione admin/editor in italiano: `ItalianGettextBridge` + tabella `languages/en-it-admin-table.php` (oltre 360 stringhe inglesi) quando la locale WordPress/utente è italiana e manca la voce nel file `.mo`.

## [0.9.0-pre.85] - 2026-04-05
### Changed
- Icona menu admin: `dashicons-search` al posto di `dashicons-chart-line` (evita duplicato con FP Marketing Tracking Layer).

## [0.9.0-pre.83] - 2026-03-23
### Changed
- Menu position 56.11 per ordine alfabetico FP.

## [0.9.0-pre.82] - 2026-03-23
### Changed
- Menu WordPress e admin bar: titolo "FP SEO Manager" (allineamento con nome plugin e cartella FP-SEO-Manager).

## [0.9.0-pre.81] - 2026-03-23
### Changed
- Menu WordPress e admin bar: titolo "FP SEO Performance" (allineamento naming con altri plugin FP).

## [0.9.0-pre.80] - 2026-03-23
### Changed
- Menu admin: ordine voci, separatori visivi e link rapidi admin bar (pattern FP-Experiences).

## [0.9.0-pre.79] - 2026-03-22
### Added
- **Aggiorna SEO con AI** – nuova pagina admin per aggiornamento bulk one-click (Pagine e Articoli separati)
- **Contesto sito** – campo in Settings > AI per descrivere il sito; usato dall'AI per generare meta coerenti
- OpenAiClient: iniezione `site_context` nel prompt di generazione SEO

## [0.9.0-pre.78] - 2026-03-22
### Changed
- **AI Providers aggiornati a marzo 2026** – lineup GPT-5.4 (Nano, Mini, Pro) come default; GPT-5 e GPT-4 in Legacy
- Default modello da `gpt-5-nano` a `gpt-5.4-nano`
- QAPairExtractor, HowToGenerator, ConversationalVariants usano `gpt-5.4-nano` per task dedicati
- OpenAiClient: supporto `max_completion_tokens` per modelli gpt-5.4-nano, gpt-5.4-mini

### Added
- Nuovi modelli in AiTabRenderer: gpt-5.4-nano, gpt-5.4-mini, gpt-5.4, gpt-5.4-pro
- Retrocompatibilità con modelli GPT-5 legacy (gpt-5-nano, gpt-5-mini, gpt-5, gpt-5-pro)

## [0.9.0-pre.77] - 2025-03-19
### Fixed
- Redirect Manager admin: `h1.screen-reader-text` primo nel `.wrap` + titolo banner in `h2` (compat notice JS `.wrap h1`); CSS per `.fp-seo-page-header-title` e `.notice` figlie dirette del `.wrap`.
- Rimosso `check-seo-manager.php` (file diagnostico aggiunto per errore nel commit della stessa versione).

## [0.9.0-pre.76] - 2026-03-14
### Added
- **XML Sitemap FP dedicata** – endpoint `/fp-sitemap.xml` con indice, chunk per post type, `lastmod`, cache TTL e toggle inclusione post type
- **Meta rendering avanzato** – canonical override in metabox e output `hreflang` automatico (Polylang/WPML + filtro `fp_seo_hreflang_tags`)
- **Robots + Breadcrumb** – robots manager via hook `robots_txt` e shortcode visuale `[fp_breadcrumb]` basato su `BreadcrumbSchemaGenerator`
- **SEO Monitoring** – log 404 runtime + job cron per broken internal links, con vista operativa in Redirect Manager
- **SEO Executive KPI** – nuove metriche (404 24h, broken links) nella Performance Dashboard
- **Image SEO + Migration Tool** – nuovi check analyzer (filename qualità/peso immagini) e import one-shot da Yoast/RankMath/AIOSEO

### Changed
- `RedirectsOptions` estesa con sezioni `xml_sitemap`, `meta_rendering`, `robots`, `breadcrumb`
- Redirect Manager aggiornato come hub operativo per technical SEO settings e monitoraggio

## [0.9.0-pre.75] - 2026-03-14
### Removed
- **SchemaMetaboxes**: `render_faq_metabox`, `save_faq_schema`, hook save_post per FAQ — FAQ Schema gestito solo da Q&A Pairs (AIRenderer)
- **SchemaMetaboxesScriptsManager**: script FAQ rimossi (form FAQ non più presente)

### Deprecated
- `FreshnessMetabox::add_meta_box()` — contenuto integrato in metabox principale
- `GeoMetabox::add_meta_box()` — contenuto integrato in metabox principale
- `QAMetabox::add_meta_box()` — contenuto integrato in metabox principale
- `InternalLinkManager::add_links_metabox()` — contenuto integrato in metabox principale
- `ImprovedSocialMediaManager::add_social_metabox()` — contenuto integrato in metabox principale

## [0.9.0-pre.74] - 2026-03-14
### Added
- **RedirectsOptions** – Opzioni centralizzate per redirect e sitemap HTML in `fp_seo_performance`
- **Card Impostazioni** nella pagina Redirect Manager: toggle redirect, toggle sitemap, priorità hook, max elementi per sezione, cache TTL
- **Filter `fp_seo_redirect_priority`** – Priorità configurabile per hook `template_redirect`
- **Filter `fp_seo_html_sitemap_post_types`** – Post types inclusi nella sitemap HTML
- **Filter `fp_seo_html_sitemap_sections`** – Sezioni finali della sitemap HTML
- **UrlNormalizer::normalize_path()** – Normalizzazione URL centralizzata per redirect (path con slash iniziale, senza trailing)

### Changed
- RedirectHandler rispetta toggle `redirects.enabled` e priorità da opzioni
- SitemapRouter rispetta toggle `html_sitemap.enabled`
- HtmlSitemap usa `max_per_section` e `cache_ttl` da opzioni
- RedirectRepository usa UrlNormalizer per normalizzazione path

## [0.9.0-pre.73] - 2026-03-14
### Added
- **Sitemap HTML user-friendly** – Pagina `/sitemap/` con elenco organizzato per post type (homepage, pagine, articoli)
- **Redirect Manager** – Gestione 301/302 con tabella dedicata, CRUD completo, toggle attivo/inattivo
- **Import redirect di massa** – Import CSV (source, target, type) per aggiungere molti redirect in una volta
- **Integrazione SEO Manager** – Nuova voce menu "Redirect Manager" sotto SEO Performance

### Changed
- Migration CreateRedirectsTable (v1.1.0) per tabella `wp_fp_seo_redirects`
- Redirect Handler su `template_redirect` priorità 1 per applicare redirect prima del caricamento pagina
- Cache sitemap HTML invalidata automaticamente su save_post

## [0.9.0-pre.71] - 2025-12-02
### Added
- Nuove funzionalità AI nel pannello admin

## [0.9.0-pre.70] - 2025-11-27
### Added
- Homepage Protection
- Diagnostica Metabox

### Fixed
- MetaboxRenderer inizializzazione corretta
- ImageExtractor: non interviene nel core WordPress
- Rimosso metabox nativo WordPress "Riassunto"

## [0.9.0-pre.37] - 2025-01-27

### Fixed
- **CRITICAL**: Aggiunto controllo post type in `AutoGenerationHook::on_publish()` e `on_update()` per prevenire interferenze con post types non supportati
- **CRITICAL**: Aggiunto controllo post type in `MetaboxSaver::save_all_fields()` come doppia protezione
- **CRITICAL**: Aggiunto controllo post type in `AutoSeoOptimizer::maybe_auto_optimize()` per prevenire interferenze con ottimizzazione automatica AI
- **CRITICAL**: Aggiunto controllo post type in tutti gli handler AJAX (`handle_save_fields_ajax`, `handle_save_images_ajax`) per prevenire interferenze

### Changed
- Tutti i metodi di salvataggio ora controllano il post type PRIMA di qualsiasi operazione
- Pattern uniforme di protezione in tutto il plugin
- Logging dettagliato per diagnostica (se WP_DEBUG attivo)

### Documentation
- Aggiunto `INTERFERENCE-FIXES.md` - Documentazione tecnica completa delle correzioni
- Aggiunto `FINAL-STATUS.md` - Status finale con riepilogo completo
- Aggiunto `VERIFICATION-COMPLETE.md` - Verifica completa con dettagli

## [0.9.0-pre.36] - 2025-01-27

### Fixed
- **CRITICAL**: Aggiunto controllo post type in `MetaboxSaver::save_all_fields()` come doppia protezione
- **CRITICAL**: Aggiunto controllo post type in `AutoSeoOptimizer::maybe_auto_optimize()` per prevenire interferenze con ottimizzazione automatica AI

## [0.9.0-pre.35] - 2025-01-27

### Fixed
- **CRITICAL**: Aggiunto controllo post type in `Metabox::handle_save_fields_ajax()` per prevenire interferenze con AJAX
- **CRITICAL**: Aggiunto controllo post type in `Metabox::handle_save_images_ajax()` per prevenire interferenze con salvataggio immagini

## [0.9.0-pre.34] - 2025-01-27

### Fixed
- **CRITICAL**: Aggiunto controllo post type PRIMA di tutto in tutti i metodi di salvataggio del plugin:
  - `ImprovedSocialMediaManager::save_social_meta()`
  - `SchemaMetaboxes::save_faq_schema()`
  - `SchemaMetaboxes::save_howto_schema()`
  - `MultipleKeywordsManager::save_keywords_meta()`
  - `GeoMetaBox::save_meta()`
  - `FreshnessMetaBox::save_meta()`
- **CRITICAL**: Pattern uniforme di protezione - tutti i metodi controllano il post type PRIMA di qualsiasi operazione

## [0.9.0-pre.33] - 2025-01-27

### Fixed
- **CRITICAL**: Spostato controllo post type PRIMA di qualsiasi altro controllo in `Metabox::save_meta()`, `save_meta_edit_post()`, e `save_meta_insert_post()`
- **CRITICAL**: Aggiunto logging dettagliato per diagnostica (sempre attivo, non solo in debug mode per save_meta)
- **CRITICAL**: Pattern uniforme - controllo post type PRIMA di static tracking per evitare qualsiasi interferenza

### Changed
- Logging migliorato per tracciare quando il plugin esce per post types non supportati
- Pattern di protezione uniforme in tutti i metodi

## [0.9.0-pre.32] - 2025-01-27

### Fixed
- **CRITICAL**: Aggiunto controllo `is_supported_post_type()` in `Metabox::save_meta()`, `save_meta_edit_post()`, e `save_meta_insert_post()` per prevenire interferenze con Nectar Sliders e altri custom post types non supportati
- Il plugin ora processa solo post types esplicitamente supportati dal metabox SEO

## [0.9.0-pre.31] - 2025-01-27

### Fixed
- **CRITICAL**: Rimosso tutte le chiamate a `clean_post_cache()` e `wp_cache_delete()` durante il caricamento della pagina in `Metabox.php` e `MetaboxSaver.php`
- Queste chiamate interferivano con il global post object causando il problema "Auto Draft"
- Le chiamate a cache clearing rimangono solo durante il salvataggio (dove sono necessarie)

### Removed
- Cache clearing da `Metabox::render()` durante error handling
- Cache clearing da `Metabox::is_post_excluded()`
- Cache clearing da `Metabox::run_analysis_for_post()`
- Cache clearing da `MetaboxSaver::save_all_fields()` durante page load

## [0.9.0-pre.30] - 2025-01-27

### Fixed
- **CRITICAL**: Completamente disabilitato i filtri `posts_where` e `posts_orderby` in `PerformanceOptimizer::optimize_database_queries()`
- Questi filtri erano la causa principale del problema "Auto Draft" interferendo con le query WordPress interne
- I filtri sono ora commentati e non vengono più registrati

### Removed
- `fix_wrong_post_object()` method e hook `load-post.php` (era un workaround, non una soluzione)

## [0.9.0-pre.29] - 2025-01-27

### Removed
- Blocco temporaneo aggressivo del plugin su pagine post edit (era un workaround)
- `fix_wrong_post_object()` method (era un workaround)

## [0.9.0-pre.28] - 2025-01-27

### Fixed
- Aggiunto controllo in `Metabox::render()` per ricaricare il post object corretto se WordPress passa un auto-draft o post ID errato
- Questo era un workaround temporaneo, rimosso in versione successiva

## [0.9.0-pre.27] - 2025-01-27

### Fixed
- **CRITICAL**: Rimossi tutte le chiamate a `clean_post_cache()`, `wp_cache_delete()`, `wp_cache_flush_group()`, e `update_post_meta_cache()` da `MetaboxRenderer` class
- Queste chiamate interferivano con il post object durante il rendering del metabox

## [0.9.0-pre.26] - 2025-01-27

### Fixed
- **CRITICAL**: Modificato `PerformanceOptimizer::optimize_posts_where()` e `optimize_posts_orderby()` per NON applicare ottimizzazioni su schermate `post.php` e `post-new.php`
- Questo previene che l'optimizer modifichi la query principale sullo schermo di modifica post, causando il problema "Auto Draft"
- Aggiunto check `is_admin_screen( array( 'post', 'post-new' ) )` per identificare schermate di modifica post

### Removed
- Debug logging aggiunto in versione precedente

## [0.9.0-pre.25] - 2025-01-27

### Added
- Debug logging in `Metabox::render()` per tracciare il post object e identificare il problema "Auto Draft"

## [0.9.0-pre.24] - 2025-01-27

### Fixed
- **CRITICAL**: Rimossi tutti i `wp_update_post()` calls che correggono lo status homepage da `auto-draft` a `publish` in:
  - `Metabox::save_meta_rest()` - rimosso correzione status
  - `Metabox::save_meta()` - rimosso correzione status
  - `MetaboxSaver::save_all_fields()` - rimosso correzione status homepage

### Removed
- Logica di protezione homepage che modificava `post_status` da `auto-draft` a `publish`

## [0.9.0-pre.23] - 2025-01-27

### Fixed
- Ulteriori investigazioni sul problema "Auto Draft" persistente

## [0.9.0-pre.22] - 2025-01-27

### Fixed
- Ulteriori investigazioni sul problema "Auto Draft" persistente

## [0.9.0-pre.21] - 2025-01-27

### Fixed
- **CRITICAL**: Disabilitati TUTTI gli hook di protezione homepage rimanenti:
  - `wp_insert_post_data` -> `save_meta_pre_insert` (DISABLED)
  - `transition_post_status` -> `prevent_homepage_auto_draft` (DISABLED)
  - `init` -> `save_homepage_original_status` (DISABLED)
  - `shutdown` -> `fix_homepage_status_on_shutdown` (DISABLED)
- Rimossa logica speciale per homepage in `Metabox::render()`

### Removed
- Tutta la logica di "homepage protection" che causava interferenze con la creazione di nuovi post types

## [0.9.0-pre.20] - 2025-01-27

### Fixed
- **CRITICAL**: Disabilitato `prevent_homepage_auto_draft_creation()` function e il suo hook `admin_init`
- Questa funzione era troppo aggressiva e reindirizzava qualsiasi auto-draft (inclusi nuovi Nectar Sliders) alla homepage
- La funzione e il suo hook sono ora commentati e marcati come DISABLED

## [0.9.0-pre.19] - 2025-01-27

### Fixed
- **CRITICAL**: Affinato il blocco globale del plugin sulle pagine media library
- Il blocco ora è meno aggressivo e permette al metabox di funzionare nell'editor post
- Blocca solo: `upload.php` (senza `item=`), `media-new.php`, e AJAX `query-attachments`

### Changed
- Il plugin ora funziona correttamente nell'editor post mentre rimane bloccato sulla media library grid

## [0.9.0-pre.17] - 2025-01-27

### Fixed
- **CRITICAL**: Aggiunto blocco globale del plugin sulle pagine media library per prevenire interferenze con le thumbnails
- Il plugin non si carica più su: `upload.php`, `media-upload.php`, `async-upload.php`, `media-new.php`, e AJAX calls related to media
- Questo risolve il problema delle thumbnails non visibili nella media library

## [0.9.0-pre.16] - 2025-01-27

### Fixed
- **CRITICAL**: Risolto il problema delle immagini non trovate anche se presenti nel contenuto
- Aggiunto recupero del contenuto direttamente dal database se risulta vuoto dal post object
- Aggiunto metodo alternativo di estrazione immagini con regex se DOMDocument non trova immagini
- Forzato refresh completo della cache del post prima dell'estrazione
- Aggiunto logging dettagliato per ogni fase del processo (do_shortcode, the_content, vc_do_shortcode, DOM parsing, regex)
- Aggiunto logging per tracciare quante immagini vengono trovate in ogni fase

### Changed
- Il contenuto viene ora recuperato anche direttamente dal database se il post object non lo contiene
- Se DOMDocument non trova immagini ma ci sono tag <img> nel contenuto, viene usato regex come fallback
- Aggiunto logging sempre attivo (non solo in debug) per tracciare tutto il processo di estrazione
- Versione aggiornata per forzare il ricaricamento degli asset e del codice

## [0.9.0-pre.15] - 2025-01-27

### Fixed
- **CRITICAL**: Migliorata la normalizzazione degli URL - ora viene fatta PRIMA del controllo duplicati per evitare di perdere immagini
- Migliorato il confronto per identificare la featured image (confronta con tutte le varianti di dimensione)
- Aggiunto logging dettagliato per tracciare quante immagini vengono trovate nel DOM vs quante vengono aggiunte
- Aggiunto logging per tracciare quante immagini vengono renderizzate vs quante vengono saltate
- Migliorata la gestione degli URL relativi e assoluti durante l'estrazione

### Changed
- La normalizzazione degli URL ora avviene prima del controllo duplicati per evitare falsi positivi
- Il confronto featured image ora usa un array di varianti URL invece di un singolo confronto
- Aggiunto logging sempre attivo (non solo in debug) per tracciare il processo completo
- Versione aggiornata per forzare il ricaricamento degli asset e del codice

## [0.9.0-pre.14] - 2025-01-27

### Fixed
- **CRITICAL**: Risolto il problema delle anteprime immagini che non si caricavano o si vedevano solo alcune
- Le anteprime ora usano le dimensioni ottimizzate di WordPress (thumbnail/medium) invece dell'immagine full-size
- Normalizzati tutti gli URL delle immagini per assicurare che siano sempre assoluti
- Aggiunto fallback automatico se l'anteprima non si carica (usa l'immagine originale)
- Aggiunto controllo per saltare immagini con src vuoto durante il rendering
- Migliorata la normalizzazione degli URL durante l'estrazione (gestione URL relativi e assoluti)
- Aggiunto logging dettagliato per tracciare quante immagini vengono trovate e renderizzate
