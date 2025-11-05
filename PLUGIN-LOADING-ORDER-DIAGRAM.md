# 🔄 Plugin Loading Order - Diagramma Completo

**Plugin**: FP SEO Performance v0.9.0-pre.6  
**Verificato**: Post 4-Session Bugfix

---

## 📋 Sequenza di Caricamento Completa

```
┌─────────────────────────────────────────────────────────────┐
│ FASE 1: Plugin Load (fp-seo-performance.php)                │
└─────────────────────────────────────────────────────────────┘
  ↓
  1. define('FP_SEO_PERFORMANCE_FILE', __FILE__)
  2. define('FP_SEO_PERFORMANCE_VERSION', '0.9.0-pre.6')
  3. require vendor/autoload.php
  4. require src/Infrastructure/Plugin.php
  5. Plugin::instance()->init()
     └→ register_activation_hook()
     └→ register_deactivation_hook()
     └→ add_action('init', 'init_asset_optimizer', 1)
     └→ add_action('plugins_loaded', 'boot')
  6. Cache Flush Check (transient-based, una volta sola)

┌─────────────────────────────────────────────────────────────┐
│ FASE 2: WordPress Hook 'init' (priority 1)                  │
└─────────────────────────────────────────────────────────────┘
  ↓
  1. init_asset_optimizer()
     └→ Container: AssetOptimizer
     └→ AssetOptimizer->init()

┌─────────────────────────────────────────────────────────────┐
│ FASE 3: WordPress Hook 'plugins_loaded'                     │
└─────────────────────────────────────────────────────────────┘
  ↓
  boot() {
    
    // Core Services (sempre)
    1. load_plugin_textdomain()
    2. Container: SeoHealth → register()
    3. Container: PerformanceOptimizer → register()
    4. Container: AdvancedCache
    5. Container: PerformanceMonitor
    6. Container: RateLimiter
    7. Container: DatabaseOptimizer
    8. Container: AssetOptimizer (singleton)
    9. Container: HealthChecker
   10. Container: PerformanceDashboard (singleton)
   11. Container: AdvancedSchemaManager (singleton)
   12. Container: AdvancedContentOptimizer (singleton)
   13. Container: ImprovedSocialMediaManager (singleton)
   14. Container: InternalLinkManager (singleton)
   15. Container: MultipleKeywordsManager (singleton)
   
    // AI-First Services (singleton, sempre)
   16. Container: QAPairExtractor
   17. Container: ConversationalVariants
   18. Container: EmbeddingsGenerator
   19. Container: FreshnessSignals
   20. Container: CitationFormatter
   21. Container: AuthoritySignals
   22. Container: SemanticChunker
   23. Container: EntityGraph
   24. Container: MultiModalOptimizer
   25. Container: TrainingDatasetFormatter
   
    // Auto-Generation Hook
   26. Container: AutoGenerationHook → register()
   
    // ADMIN ONLY (if is_admin())
    ┌─────────────────────────────────────────┐
    │ ADMIN CONTEXT                           │
    └─────────────────────────────────────────┘
    27. Container: Assets → register()
    28. Container: Menu → register()           ← MENU PRINCIPALE
    29. Container: SettingsPage → register()
    30. Container: BulkAuditPage → register()
    
    // SUBMENU (dopo Menu principale)
    31. PerformanceDashboard → register()      ✅ FIX: era in admin_init
    32. AdvancedSchemaManager → register()     ✅ FIX: era in admin_init
    33. AdvancedContentOptimizer → register()  ✅ FIX: era in admin_init
    34. ImprovedSocialMediaManager → register()✅ FIX: era in admin_init
    35. InternalLinkManager → register()       ✅ FIX: era in admin_init
    36. MultipleKeywordsManager → register()   ✅ FIX: era in admin_init
    37. AiSettings → register()                ✅ FIX: sempre visibile
    
    // Metabox (per add_meta_boxes hook)
    38. Container: Metabox → register()
    39. Container: QAMetaBox → register()      (metabox disabilitata)
    40. Container: FreshnessMetaBox → register() (metabox disabilitata)
    41. Container: AuthorProfileFields → register()
    42. Container: AiFirstAjaxHandler → register()
    43. Container: BulkAiActions → register()
    44. Container: AiFirstSettingsIntegration → register()
    
    // Lazy load su admin_init
    add_action('admin_init', 'boot_admin_services')
    
    // GEO Services (condizionale)
    boot_geo_services() {
      if (GEO enabled) {
        if (is_admin()) {
          GeoMetaBox → register()              ✅ FIX: condizionale + timing
        }
        Router, SchemaGeo, Shortcuts → register()
        add_action('admin_init', 'boot_geo_admin_services', 20)
      }
    }
  }

┌─────────────────────────────────────────────────────────────┐
│ FASE 4: WordPress Hook 'admin_init'                         │
└─────────────────────────────────────────────────────────────┘
  ↓
  boot_admin_services() {
    1. Container: Notices → register()
    2. Container: AdminBarBadge → register()
    3. boot_ai_services()
       if (OpenAI API key configured) {
         Container: AiAjaxHandler → register()
       }
    4. if (user is admin) {
         Container: TestSuitePage → register()
         Container: TestSuiteAjax → register()
       }
  }

┌─────────────────────────────────────────────────────────────┐
│ FASE 5: WordPress Hook 'admin_init' (priority 20) - GEO     │
└─────────────────────────────────────────────────────────────┘
  ↓
  boot_geo_admin_services() {
    if (is_admin() && GEO enabled) {
      1. Container: GeoSettings → register()
      2. Container: ScoreHistory → register()
      3. Container: LinkingAjax → register()
      4. boot_gsc_services()
         if (GSC configured) {
           Container: GscSettings → register()
           Container: GscDashboard → register()
         }
    }
  }

┌─────────────────────────────────────────────────────────────┐
│ FASE 6: WordPress Hook 'admin_menu'                         │
└─────────────────────────────────────────────────────────────┘
  ↓
  Tutti i menu vengono creati:
  
  SEO Performance (Menu principale)
  ├── Dashboard
  ├── Settings
  ├── Bulk Auditor
  ├── Performance         ✅ (era 404)
  ├── Schema Markup       ✅ (era 404)
  ├── AI Content Optimizer✅ (era 404)
  ├── Social Media        ✅ (era 404)
  ├── Internal Links      ✅ (era 404)
  ├── Multiple Keywords   ✅ (era 404)
  ├── Test Suite
  ├── AI (tab settings)   ✅ (sempre visibile)
  ├── AI-First (tab)      ✅ (crash risolto)
  ├── GEO (se abilitato)
  └── GSC (se configurato)

┌─────────────────────────────────────────────────────────────┐
│ FASE 7: WordPress Hook 'add_meta_boxes'                     │
└─────────────────────────────────────────────────────────────┘
  ↓
  Metabox Principale:
  
  🎯 SEO Performance (UNICA METABOX)
  ├── SEO Score (real-time)
  ├── Search Intent & Keywords    ← integrata
  │   └→ render_keywords_metabox() da MultipleKeywordsManager
  ├── Analisi SEO (real-time)
  ├── AI Generator
  ├── GSC Metrics (se configurato)
  ├── Q&A Pairs per AI            ← integrata
  │   └→ render() da QAMetaBox
  ├── GEO Claims (se abilitato)   ← integrata condizionale
  │   └→ render() da GeoMetaBox
  ├── Freshness & Temporal        ← integrata
  │   └→ render() da FreshnessMetaBox
  ├── Social Media Preview        ← integrata
  │   └→ render_improved_social_metabox() da ImprovedSocialMediaManager
  └── Internal Link Suggestions   ← integrata
      └→ render_links_metabox() da InternalLinkManager

  NESSUNA metabox nella sidebar! ✅

┌─────────────────────────────────────────────────────────────┐
│ FASE 8: User Interaction - Real-Time Analysis               │
└─────────────────────────────────────────────────────────────┘
  ↓
  User scrive contenuto:
  
  1. Input event su title/content/excerpt/keywords
  2. scheduleAnalysis() - debounce 500ms      ✅ (ottimizzato)
  3. ui.showLoading() - feedback immediato    ✅ (nuovo)
  4. AJAX call a 'fp_seo_performance_analyze'
  5. handle_ajax() processa
  6. UI aggiornata in tempo reale
     └→ Score colorato
     └→ Check con animazione stagger
     └→ Raccomandazioni

┌─────────────────────────────────────────────────────────────┐
│ FASE 9: Save Post                                           │
└─────────────────────────────────────────────────────────────┘
  ↓
  User clicca "Salva Bozza" o "Pubblica":
  
  1. Metabox::save_meta()
     └→ Salva: exclude, focus_keyword, secondary_keywords
  
  2. MultipleKeywordsManager::save_keywords_meta()
     └→ Salva: primary, secondary, long-tail, semantic keywords
  
  3. ImprovedSocialMediaManager::save_social_meta()
     └→ Salva: social media metadata
  
  4. FreshnessMetaBox::save_meta()
     └→ Salva: update_frequency, fact_checked, content_type
  
  5. GeoMetaBox::save_meta() (se GEO abilitato)
     └→ Salva: claims, expose, no_ai_reuse
  
  TUTTI i save_post hooks ATTIVI! ✅
```

---

## 🔍 Verifica Coerenza Fix

### Fix #1: Menu 404
```
✅ Timing: Submenu in boot() prima di admin_menu
✅ Container: Tutti i singleton creati prima
✅ Register: Chiamato subito dopo singleton
✅ Risultato: Menu accessibili
```

### Fix #2: Metabox Integrate
```
✅ Disabilitate: add_meta_boxes commentato
✅ Save Hooks: Ancora attivi
✅ Render: Chiamato da Metabox principale
✅ Risultato: Tutto in un box, salvataggio OK
```

### Fix #3: GEO Condizionale
```
✅ Check: Options::get()['geo']['enabled']
✅ Registrazione: In boot_geo_services()
✅ Rendering: Con if() nel template
✅ Risultato: Appare solo se abilitato
```

### Fix #4: Real-Time Analysis
```
✅ Eventi: Su title, content, excerpt, keywords
✅ Debounce: 500ms (ottimizzato)
✅ Feedback: Immediato (loading)
✅ Risultato: Analisi mentre scrivi
```

---

## ✅ TUTTO COERENTE E FUNZIONANTE

Nessun conflitto trovato tra i fix applicati!


