# Architettura FP SEO Manager

## Panoramica

FP SEO Manager è un plugin WordPress modulare basato su **Service Providers** e **Dependency Injection Container**. L'architettura segue principi SOLID e pattern di design moderni.

## Struttura Principale

### Namespace Organization

```
FP\SEO\
├── Infrastructure\          # Core infrastructure (Container, Service Providers)
├── Core\                    # Core services (Cache, Logger, Options)
├── Analysis\                # SEO analysis engine
├── Editor\                  # Editor integration (Metabox, Renderers)
├── Admin\                   # Admin interface (Pages, Settings, UI)
├── Frontend\                # Frontend rendering (Meta tags, Schema, Social)
├── AI\                      # AI-powered features
├── GEO\                     # Generative Engine Optimization
├── Integrations\            # External integrations (GSC, Indexing API)
└── Utils\                   # Utility classes
```

## Service Providers

Il plugin utilizza un sistema di **Service Providers** per organizzare e registrare i servizi. Ogni provider è responsabile di un dominio funzionale specifico.

### Provider Hierarchy

1. **CoreServiceProvider** - Servizi fondamentali (Cache, Logger, Options, HookManager)
2. **DataServiceProvider** - Repository e migrazioni database
3. **PerformanceServiceProvider** - Ottimizzazioni performance
4. **AnalysisServiceProvider** - Sistema di analisi SEO
5. **MetaboxServicesProvider** - Servizi condivisi per metabox
6. **SchemaMetaboxServiceProvider** - Metabox Schema (FAQ, HowTo)
7. **MainMetaboxServiceProvider** - Metabox principale SEO
8. **QAMetaboxServiceProvider** - Metabox Q&A pairs
9. **FreshnessMetaboxServiceProvider** - Metabox Freshness signals
10. **AuthorProfileMetaboxServiceProvider** - Campi profilo autore
11. **AdminAssetsServiceProvider** - Assets admin (CSS/JS)
12. **AdminPagesServiceProvider** - Pagine admin
13. **AdminUIServiceProvider** - Componenti UI admin
14. **AIServiceProvider** - Servizi AI core
15. **AISettingsServiceProvider** - Impostazioni AI admin
16. **GEOServiceProvider** - Servizi GEO (condizionale)
17. **IntegrationServiceProvider** - Integrazioni esterne (condizionale)
18. **FrontendServiceProvider** - Renderer frontend
19. **TestSuiteServiceProvider** - Suite di test
20. **RESTServiceProvider** - REST API endpoints
21. **CLIServiceProvider** - WP-CLI commands
22. **CronServiceProvider** - Scheduled tasks

### Dependency Injection

Il plugin utilizza un **Container** semplice per la dependency injection:

- **Singleton pattern**: Servizi registrati come singleton per performance
- **Lazy loading**: Servizi istanziati solo quando richiesti
- **Interface binding**: Binding a interfacce per testabilità

## Pattern Architetturali

### Service Provider Pattern

Ogni Service Provider implementa `ServiceProviderInterface`:

```php
interface ServiceProviderInterface {
    public function register( Container $container ): void;
    public function boot( Container $container ): void;
    public function activate(): void;
    public function deactivate(): void;
    public function get_dependencies(): array;
}
```

### Registry Pattern

Il `ServiceProviderRegistry` gestisce la registrazione e il boot order dei provider.

### Factory Pattern

I Service Providers agiscono come factory per i servizi, creando istanze quando necessario.

## Flusso di Bootstrap

1. **Kernel Bootstrap** (`src/Infrastructure/Bootstrap/Kernel.php`)
   - Registra autoloader PSR-4
   - Registra error handler
   - Determina se il plugin deve caricarsi

2. **Plugin Initialization** (`src/Infrastructure/Plugin.php`)
   - Crea Container e Registry
   - Registra tutti i Service Providers in ordine
   - Boot di tutti i provider

3. **Service Registration**
   - Ogni provider registra i suoi servizi nel Container
   - I servizi sono registrati come singleton o factory

4. **Service Booting**
   - Dopo la registrazione, i provider bootano i servizi
   - I servizi registrano hook WordPress

## Organizzazione Codice

### Editor Module

Il modulo Editor gestisce l'integrazione con l'editor WordPress:

- **Metabox.php** - Orchestratore principale (4260 righe - in refactoring)
- **MetaboxRenderer.php** - Rendering UI del metabox
- **MetaboxSaver.php** - Salvataggio campi SEO
- **Services/** - Servizi specializzati:
  - `AnalysisRunner` - Esecuzione analisi SEO
  - `CheckHelper` - Helper per informazioni check
  - `HomepageAutoDraftPrevention` - Protezione homepage
  - `SeoFieldsSaver` - Salvataggio campi
  - `ImageExtractionService` - Estrazione immagini

### Admin Module

Il modulo Admin è organizzato per dominio funzionale:

- **BulkAuditPage.php** - Pagina bulk audit
- **SettingsPage.php** - Pagina impostazioni
- **PerformanceDashboard.php** - Dashboard performance
- **Metaboxes/** - Metabox admin (Freshness, GEO, QA)
- **Renderers/** - Renderer per pagine admin
- **Scripts/** - Script managers
- **Styles/** - Style managers
- **Assets/** - Classi base per asset managers

### Frontend Module

Il modulo Frontend gestisce l'output sul frontend:

- **Renderers/** - Renderer per meta tags, schema, social
- **Shortcodes/** - Shortcodes GEO

## Convenzioni di Naming

### Classi
- **PascalCase**: `Metabox`, `ServiceProvider`, `AnalysisRunner`
- **Suffissi**: `*ServiceProvider`, `*Manager`, `*Renderer`, `*Handler`

### Namespace
- **PascalCase**: `FP\SEO\Editor\Services`
- **Singolare per moduli**: `Editor`, `Admin`, `Frontend`
- **Plurale per collezioni**: `Metaboxes`, `Providers`, `Renderers`

### File
- **PascalCase per classi**: `Metabox.php`, `ServiceProvider.php`
- **kebab-case per CSS**: `fp-seo-metabox.css`

## Best Practices

### Service Registration
- Registrare sempre le interfacce, non solo le implementazioni concrete
- Usare singleton per servizi stateless
- Usare factory per servizi con dipendenze complesse

### Hook Management
- Usare `HookManagerInterface` per registrazione hook centralizzata
- Evitare hook duplicati usando array statici
- Verificare sempre il contesto (admin/frontend) prima di registrare hook

### Error Handling
- Usare try-catch nei Service Providers per non bloccare il bootstrap
- Loggare errori ma continuare con altri provider
- Usare livelli di log appropriati (error, warning, debug)

### Performance
- Lazy loading dei servizi
- Cache per dati costosi
- Query optimization per database

## Estendibilità

### Aggiungere un nuovo Service Provider

1. Creare classe che estende `AbstractServiceProvider`
2. Implementare `register()` e `boot()`
3. Registrare in `Plugin.php` nel metodo `boot()`

### Aggiungere un nuovo Check SEO

1. Creare classe che implementa `CheckInterface`
2. Registrare in `CheckRegistry`
3. Il check sarà automaticamente incluso nell'analisi

### Aggiungere un nuovo Renderer Frontend

1. Creare classe che estende `AbstractRenderer`
2. Implementare `register()` e `render()`
3. Registrare in `FrontendServiceProvider`

## Testing

Il plugin include una suite di test:

- **Unit tests**: Test di singole classi
- **Integration tests**: Test di integrazione tra componenti
- **Service Provider tests**: Test di bootstrap e registrazione

## Versioning

Il plugin segue **Semantic Versioning**:
- **Major**: Breaking changes
- **Minor**: Nuove funzionalità backward-compatible
- **Patch**: Bug fixes

## Note Tecniche

### Autoloading
- PSR-4 autoloading via Composer
- Namespace base: `FP\SEO\`
- Directory base: `src/`

### Dependency Management
- Composer per dipendenze PHP
- NPM per asset frontend (se necessario)

### Cache Strategy
- Multi-tier caching (Redis, Memcached, WordPress Object Cache)
- Cache groups per isolamento
- TTL appropriati per tipo di dato

## Diagramma Architettura

```
┌─────────────────────────────────────────────────────────┐
│                    Plugin Bootstrap                     │
│              (fp-seo-performance.php)                   │
└────────────────────┬──────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│                      Kernel                              │
│         (Autoloader, Error Handler)                     │
└────────────────────┬──────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│                      Plugin                              │
│         (Container, ServiceProviderRegistry)            │
└────────────────────┬──────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│              Service Providers (22)                      │
│  Core → Data → Performance → Analysis → ...             │
└────────────────────┬──────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│                    Services                              │
│  Cache, Logger, Options, Analyzer, Metabox, ...         │
└─────────────────────────────────────────────────────────┘
```

## Riferimenti

- [Service Provider Pattern](https://laravel.com/docs/providers)
- [Dependency Injection](https://www.php-fig.org/psr/psr-11/)
- [PSR-4 Autoloading](https://www.php-fig.org/psr/psr-4/)
