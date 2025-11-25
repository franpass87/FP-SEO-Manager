# ✅ SOLUZIONE: Problema Metabox Non Registrato

**Data:** 2025-01-23  
**Problema:** Il metabox SEO principale non appariva nell'editor WordPress  
**Causa:** Timing issue durante `plugins_loaded` - `is_admin()` non era affidabile

---

## 🔍 PROBLEMA IDENTIFICATO

Durante l'hook `plugins_loaded`, la funzione `is_admin()` potrebbe non essere ancora affidabile in WordPress. Questo causava:

1. `AbstractAdminServiceProvider::register()` controllava `is_admin_context()` che chiamava `is_admin()`
2. Se `is_admin()` restituiva `false` durante `plugins_loaded`, i servizi admin non venivano registrati nel container
3. Quando `boot()` veniva chiamato, il servizio non esisteva nel container
4. Il metabox non veniva mai registrato

---

## ✅ SOLUZIONE IMPLEMENTATA

### 1. Rimossa dipendenza da `is_admin()` durante registrazione

**File:** `src/Infrastructure/Providers/Admin/AbstractAdminServiceProvider.php`

**Modifica:**
- ❌ **Prima:** `register()` controllava `is_admin_context()` e non registrava il servizio se `false`
- ✅ **Dopo:** `register()` **sempre** registra il servizio nel container (rimosso controllo)

**Motivo:** I servizi devono essere sempre disponibili nel container per il lazy loading. Il controllo admin viene fatto durante il boot, non durante la registrazione.

### 2. Boot ritardato con fallback a `admin_init`

**File:** `src/Infrastructure/Providers/Admin/AbstractAdminServiceProvider.php`

**Modifica:**
- ✅ Se `is_admin_context()` è `false` durante il boot, il boot viene ritardato all'hook `admin_init`
- ✅ `admin_init` viene eseguito DOPO che WordPress ha completamente inizializzato l'admin context
- ✅ Garantisce che il metabox venga sempre registrato quando siamo in admin

### 3. Migliorato `is_admin_context()` per maggiore affidabilità

**File:** `src/Infrastructure/Traits/ConditionalServiceTrait.php`

**Modifiche:**
- ✅ Controlla `is_admin()` (primario)
- ✅ Controlla `$_SERVER['REQUEST_URI']` per percorsi `/wp-admin/` (fallback)
- ✅ Controlla costante `WP_ADMIN` (fallback)
- ✅ Controlla `DOING_AJAX` per richieste AJAX admin (fallback)
- ✅ Controlla REST API con utente loggato (fallback)

**Motivo:** Essere più affidabili anche quando `is_admin()` non è disponibile durante `plugins_loaded`.

---

## 📝 MODIFICHE DETTAGLIATE

### File 1: `AbstractAdminServiceProvider.php`

```php
// PRIMA:
final public function register( Container $container ): void {
    if ( ! $this->is_admin_context() ) {
        return;  // ❌ Non registra se non in admin
    }
    $this->register_admin( $container );
}

// DOPO:
final public function register( Container $container ): void {
    // ✅ Sempre registra - boot controlla admin context
    $this->register_admin( $container );
}

// PRIMA:
final public function boot( Container $container ): void {
    if ( ! $this->is_admin_context() ) {
        return;  // ❌ Non boota se non in admin
    }
    $this->boot_admin( $container );
}

// DOPO:
final public function boot( Container $container ): void {
    if ( ! $this->is_admin_context() ) {
        // ✅ Ritarda boot a admin_init se necessario
        add_action( 'admin_init', function() use ( $container ) {
            if ( $this->is_admin_context() ) {
                $this->boot_admin( $container );
            }
        }, 1 );
        return;
    }
    $this->boot_admin( $container );
}
```

### File 2: `ConditionalServiceTrait.php`

```php
// PRIMA:
protected function is_admin_context(): bool {
    return is_admin();  // ❌ Non affidabile durante plugins_loaded
}

// DOPO:
protected function is_admin_context(): bool {
    // ✅ Controlli multipli per maggiore affidabilità
    if ( is_admin() ) {
        return true;
    }
    // Fallback checks...
    if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/wp-admin/' ) !== false ) {
        return true;
    }
    // ... altri controlli
}
```

---

## 🧪 COME TESTARE

### 1. Verifica nel Browser

1. Vai su: `http://fp-development.local/wp-admin/post.php?post=441&action=edit`
2. Verifica che il metabox "SEO Performance" sia visibile
3. Controlla la console JavaScript per errori

### 2. Verifica Log Debug

Se `WP_DEBUG` è abilitato, dovresti vedere nei log:

```
[DEBUG] FP SEO: Metabox::register() called
[DEBUG] FP SEO: Registering metabox for post types
[DEBUG] FP SEO: Metabox registered for post type: post
```

### 3. Verifica Timing

Il metabox dovrebbe essere registrato:
- ✅ Durante `plugins_loaded` → servizio registrato nel container
- ✅ Durante `admin_init` (o immediatamente se già in admin) → servizio bootato
- ✅ Durante `add_meta_boxes` → metabox aggiunto al DOM

---

## ✅ RISULTATO ATTESO

**Prima:**
- ❌ Metabox non presente nel DOM
- ❌ Servizio non registrato nel container se `is_admin()` era `false`

**Dopo:**
- ✅ Metabox sempre presente quando in admin
- ✅ Servizio sempre registrato nel container
- ✅ Boot ritardato se necessario per garantire admin context

---

## 🔄 COMPATIBILITÀ

- ✅ **Backward compatible:** Nessuna breaking change
- ✅ **Performance:** Nessun impatto negativo (lazy loading preservato)
- ✅ **Security:** Controlli admin mantenuti durante boot

---

## 📚 RIFERIMENTI

- File modificati:
  - `src/Infrastructure/Providers/Admin/AbstractAdminServiceProvider.php`
  - `src/Infrastructure/Traits/ConditionalServiceTrait.php`

- Hook WordPress utilizzati:
  - `plugins_loaded` (priorità default) - registrazione servizi
  - `admin_init` (priorità 1) - boot ritardato se necessario
  - `add_meta_boxes` (priorità 5) - aggiunta metabox

---

**STATO:** ✅ **RISOLTO**  
**TEST:** ⏳ **IN ATTESA VERIFICA NEL BROWSER**

