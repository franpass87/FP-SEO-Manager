# Test di Integrazione - Refactoring FP-SEO-Manager

## Panoramica

Questi test di integrazione verificano che il refactoring del plugin sia stato completato correttamente, con particolare attenzione a:

1. **Service Injection**: Verifica che i servizi vengano iniettati correttamente tramite il Container
2. **Hook Registration**: Verifica che tutti gli hook vengano registrati tramite HookManager

## Test Disponibili

### ServiceInjectionTest.php

Verifica che i servizi core vengano iniettati correttamente:

- ✅ `test_logger_interface_injection()` - Verifica che LoggerInterface sia risolto correttamente
- ✅ `test_cache_interface_injection()` - Verifica che CacheInterface sia risolto correttamente
- ✅ `test_options_interface_injection()` - Verifica che OptionsInterface sia risolto correttamente
- ✅ `test_hook_manager_interface_injection()` - Verifica che HookManagerInterface sia risolto correttamente
- ✅ `test_options_manager_dependency_injection()` - Verifica che OptionsManager riceva le sue dipendenze
- ✅ `test_services_are_singletons()` - Verifica che i servizi siano singleton
- ✅ `test_concrete_classes_registered()` - Verifica che anche le classi concrete siano registrate

### HookRegistrationTest.php

Verifica che HookManager registri correttamente gli hook:

- ✅ `test_hook_manager_registers_actions()` - Verifica la registrazione di action hooks
- ✅ `test_hook_manager_registers_filters()` - Verifica la registrazione di filter hooks
- ✅ `test_hook_manager_prevents_duplicates()` - Verifica che i duplicati vengano prevenuti
- ✅ `test_hook_manager_tracks_registrations()` - Verifica che gli hook vengano tracciati
- ✅ `test_hook_manager_handles_priorities()` - Verifica che le priorità vengano rispettate
- ✅ `test_hook_manager_validates_hook_names()` - Verifica la validazione dei nomi degli hook
- ✅ `test_hook_manager_with_class_methods()` - Verifica il supporto per metodi di classe

### MetaboxHookManagerTest.php

Verifica che Metabox usi correttamente HookManager:

- ✅ `test_metabox_requires_hook_manager()` - Verifica che Metabox richieda HookManager
- ✅ `test_metabox_registered_with_hook_manager()` - Verifica che Metabox sia registrato con HookManager
- ✅ `test_metabox_uses_hook_manager()` - Verifica che Metabox usi HookManager per registrare hook

### AjaxHandlerHookManagerTest.php

Verifica che gli AJAX handler usino correttamente HookManager:

- ✅ `test_abstract_ajax_handler_requires_hook_manager()` - Verifica che AbstractAjaxHandler richieda HookManager
- ✅ `test_ai_ajax_handler_requires_hook_manager()` - Verifica che AiAjaxHandler richieda HookManager
- ✅ `test_ai_first_ajax_handler_requires_hook_manager()` - Verifica che AiFirstAjaxHandler richieda HookManager
- ✅ `test_ajax_handlers_use_hook_manager()` - Verifica che gli AJAX handler usino HookManager
- ✅ `test_ai_ajax_handler_registers_hooks()` - Verifica che AiAjaxHandler registri gli hook correttamente

## Esecuzione dei Test

### Eseguire tutti i test di integrazione

```bash
vendor/bin/phpunit tests/integration
```

### Eseguire un test specifico

```bash
vendor/bin/phpunit tests/integration/ServiceInjectionTest.php
vendor/bin/phpunit tests/integration/HookRegistrationTest.php
vendor/bin/phpunit tests/integration/MetaboxHookManagerTest.php
vendor/bin/phpunit tests/integration/AjaxHandlerHookManagerTest.php
```

### Eseguire un metodo di test specifico

```bash
vendor/bin/phpunit --filter test_logger_interface_injection
```

## Requisiti

- PHPUnit installato via Composer
- WordPress test suite (opzionale, per test più completi)
- PHP 8.0+

## Note

Questi test verificano che:

1. **Tutti i servizi siano iniettati correttamente** - Nessun servizio dovrebbe essere istanziato direttamente
2. **Tutti gli hook passino attraverso HookManager** - Nessun hook dovrebbe essere registrato direttamente con `add_action()`/`add_filter()`
3. **Le dipendenze siano risolte correttamente** - Il Container deve risolvere tutte le dipendenze
4. **I servizi siano singleton** - Ogni servizio dovrebbe essere istanziato una sola volta

## Risultati Attesi

Tutti i test dovrebbero passare dopo il refactoring. Se un test fallisce, significa che:

- Un servizio non è stato registrato correttamente nel Container
- Un hook è stato registrato direttamente invece che tramite HookManager
- Una dipendenza non è stata iniettata correttamente

## Integrazione con CI/CD

Questi test possono essere eseguiti in un ambiente CI/CD per verificare che il refactoring non abbia introdotto regressioni.

```yaml
# Esempio per GitHub Actions
- name: Run Integration Tests
  run: vendor/bin/phpunit tests/integration
```














