# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added - Search Intent Analysis 🎯
- **[Feature]** New `SearchIntentCheck` - Analizza automaticamente l'intento di ricerca del contenuto e fornisce raccomandazioni personalizzate.
- **[Feature]** New `SearchIntentDetector` utility - Rileva intent type (Informational, Transactional, Commercial, Navigational) con confidence score.
- **[Enhancement]** Supporto multilingua (Italiano/Inglese) per rilevamento keyword di intent.
- **[Enhancement]** Raccomandazioni smart context-aware basate sul tipo di intent rilevato.
- **[Enhancement]** Detection di segnali aggiuntivi: prezzi, domande multiple, CTA.
- **[Documentation]** Nuova guida completa `docs/SEARCH_INTENT_OPTIMIZATION.md` con best practices per ogni tipo di intent.
- **[Documentation]** Nuova guida `docs/SEO_IMPROVEMENT_GUIDE.md` con consigli pratici SEO (Quick Wins, Technical SEO, Content Quality, Performance).
- **[Tests]** Test suite completa per `SearchIntentDetector` e `SearchIntentCheck`.

### Fixed - Code Quality Improvements 🔧
- **[Code Quality]** Corretta indentazione inconsistente in 10 file PHP per garantire standard di codifica WordPress uniformi.
- **[Refactoring]** Normalizzata indentazione da mix di 3-8 tab a standard consistente di 2 tab in tutti i file del core.
- File corretti:
  - `src/Utils/Options.php` - 34 linee normalizzate
  - `src/Admin/BulkAuditPage.php` - 244 linee normalizzate
  - `src/Scoring/ScoreEngine.php` - 14 linee normalizzate
  - `src/Analysis/Checks/InternalLinksCheck.php` - 44 linee normalizzate
  - `src/Perf/Signals.php` - 142 linee normalizzate
  - `src/Utils/UrlNormalizer.php` - 14 linee normalizzate
  - `src/Admin/Menu.php` - 190 linee normalizzate
  - `src/Editor/Metabox.php` - 8 linee normalizzate
  - `src/SiteHealth/SeoHealth.php` - 60 linee normalizzate
  - `src/Admin/AdminBarBadge.php` - 112 linee normalizzate
  - `src/Infrastructure/Plugin.php` - 16 linee normalizzate
- **[Quality]** Totale: 879 linee corrette per migliorare leggibilità e manutenibilità del codice.
- **[Quality]** Nessuna modifica funzionale - solo miglioramenti di formattazione.

### Added - AI Overview Optimization 🤖
- **[Feature]** New `FaqSchemaCheck` - Verifica FAQ Schema markup per ottimizzazione Google AI Overview (priorità massima per visibilità ricerche AI).
- **[Feature]** New `HowToSchemaCheck` - Analizza HowTo Schema per contenuti procedurali e guide step-by-step.
- **[Feature]** New `AiOptimizedContentCheck` - Valuta struttura contenuti (liste, domande, paragrafi brevi) per massimizzare estrazione AI.
- **[Enhancement]** `SchemaPresetsCheck` ora supporta speakable markup per ottimizzazione ricerche vocali e Google Assistant.
- **[Documentation]** Nuova guida completa `docs/AI_OVERVIEW_OPTIMIZATION.md` con best practices, esempi e strategie di implementazione.
- Aggiornato `Analyzer` per includere i 3 nuovi check AI-focused.
- Aggiornato `Options` e `AnalysisTabRenderer` per gestire configurazione dei nuovi check.

### Changed
- **[Refactoring]** Extracted metadata resolution logic into dedicated `MetadataResolver` utility class, eliminating ~112 lines of duplicated code across `BulkAuditPage`, `Metabox`, and `AdminBarBadge`.
- **[Refactoring]** Simplified `Analyzer` by extracting check filtering logic into new `CheckRegistry` class, reducing complexity by ~70 lines.
- **[Refactoring]** Modularized `SettingsPage` by creating dedicated renderer classes for each settings tab (`GeneralTabRenderer`, `AnalysisTabRenderer`, `PerformanceTabRenderer`, `AdvancedTabRenderer`), reducing main class from 465 to ~170 lines.
- Migliorati messaggi in italiano per `SchemaPresetsCheck` con focus su AI e ricerche vocali.

### Technical Details
- New check classes under `src/Analysis/Checks/`:
  - `FaqSchemaCheck.php` - Weight: 0.10
  - `HowToSchemaCheck.php` - Weight: 0.08
  - `AiOptimizedContentCheck.php` - Weight: 0.09
- New check keys: `faq_schema`, `howto_schema`, `ai_optimized_content`
- Tutti i check includono logica di rilevamento intelligente e raccomandazioni actionable

## [0.1.2] - 2025-10-01
### Added
- Centralized admin menu registration for analyzer screens and bulk auditing tools.
- Post type helper utilities and unit coverage to support analyzer surfaces.

### Changed
- Shared analyzer constants across the admin bar badge, metabox, and bulk auditor for consistent scoring.
- Hardened metabox hook argument handling to avoid incorrect score lookups.

### Fixed
- Decoded percent-encoded hosts before PageSpeed Insights requests to prevent API errors.

## [0.1.1] - 2025-10-01
### Added
- Expanded analyzer heuristics for metadata, canonical URLs, and structured data checks.

### Fixed
- Adjusted analyzer defaults to improve scoring reliability across content types.

## [0.1.0] - 2025-09-30
### Added
- Initial scaffold of the FP SEO Performance plugin with analyzer, settings, and tooling foundations.

[Unreleased]: https://github.com/franpass87/FP-SEO-Manager/compare/v0.1.2...HEAD
[0.1.2]: https://github.com/franpass87/FP-SEO-Manager/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/franpass87/FP-SEO-Manager/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/franpass87/FP-SEO-Manager/releases/tag/v0.1.0
