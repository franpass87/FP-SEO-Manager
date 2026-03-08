# Debug wp.media - Riepilogo Test e Correzioni

## Problema Segnalato
- I bottoni "Seleziona immagine" nella media library non funzionano
- L'immagine in evidenza non viene salvata correttamente nei post/pagine
- Il problema si risolve disattivando FP-SEO-Manager

## Test Eseguiti

### 1. Test Browser Virtuale
- ✅ wp.media è disponibile e funzionante
- ✅ wp.media.featuredImage è disponibile
- ✅ Il modal si apre quando si clicca su "Imposta immagine in evidenza"
- ✅ Il link usa `TB_iframe=1` (Thickbox) ma il modal wp.media si apre correttamente

### 2. Analisi Codice

#### File Analizzati:
1. `src/Social/Scripts/SocialScriptsManager.php`
   - ✅ Frame wp.media isolati con ID unici
   - ✅ Cleanup corretto (detach, off events)
   - ✅ MutationObserver invece di DOMNodeInserted

2. `assets/admin/js/fp-seo-ui-system.js`
   - ✅ Frame wp.media isolati con ID unici
   - ✅ AJAX handlers ignorano richieste WordPress core

3. `src/Editor/Metabox.php`
   - ✅ Filter `admin_post_thumbnail_html` applicato solo su page load iniziale
   - ✅ Non interferisce con AJAX requests

4. `src/Editor/MetaboxSaver.php`
   - ✅ Blocca richieste AJAX per `set-post-thumbnail` e `remove-post-thumbnail`
   - ✅ Blocca se `_thumbnail_id` è presente senza campi SEO
   - ✅ Usa priorità 20 invece di 10 (esegue dopo WordPress core)

## Protezioni Implementate

### 1. Isolamento Frame wp.media
- Ogni frame creato dal plugin ha un ID unico
- Cleanup completo (detach, off events) quando il frame viene chiuso
- Frame completamente isolati da WordPress core

### 2. Protezione AJAX
- Gli handler AJAX ignorano esplicitamente:
  - `set-post-thumbnail`
  - `remove-post-thumbnail`
  - `upload-attachment`
  - `query-attachments`
  - Altri AJAX requests WordPress core

### 3. Protezione save_post Hook
- Priorità 20 (dopo WordPress core che usa priorità 10)
- Blocca esecuzione se `_thumbnail_id` è presente senza campi SEO
- Blocca esecuzione per richieste AJAX di featured image

### 4. Protezione Filter admin_post_thumbnail_html
- Applicato solo su page load iniziale (non durante AJAX)
- Non interferisce se l'immagine è già presente nell'HTML

## File di Debug Creati

1. `debug-wp-media.php` - Tool base per testare wp.media
2. `debug-wp-media-detailed.php` - Tool avanzato con console output e test completi

## Possibili Problemi Residui

### 1. Timing Issues
- Il metodo `save_all_fields` viene chiamato anche quando non dovrebbe
- Anche se ritorna `false`, potrebbe comunque interferire in qualche modo

### 2. Cache Issues
- Il metodo `fix_featured_image_html` potrebbe interferire con la cache
- Anche se applicato solo su page load, potrebbe causare problemi

### 3. JavaScript Interference
- Gli script inline potrebbero interferire con wp.media
- I listener su `#_thumbnail_id` potrebbero causare problemi

## Raccomandazioni

### 1. Test Manuali Necessari
1. Aprire un post/pagina nell'editor
2. Cliccare su "Imposta immagine in evidenza" e verificare che funzioni
3. Aprire la media library e verificare che funzioni correttamente
4. Testare i bottoni "Seleziona immagine" nelle anteprime social del plugin SEO Manager
5. Verificare che i bottoni "Seleziona immagine" nella media library standard funzionino

### 2. Monitoraggio Log
- Abilitare `WP_DEBUG` per vedere i log
- Verificare se `save_all_fields` viene chiamato durante il salvataggio dell'immagine in evidenza
- Verificare se ci sono errori JavaScript nella console

### 3. Test con Plugin Disattivato
- Disattivare FP-SEO-Manager e verificare se il problema persiste
- Se il problema scompare, significa che c'è ancora qualche interferenza

## Conclusioni

Le protezioni implementate dovrebbero prevenire interferenze con wp.media. Tuttavia, è necessario testare manualmente per verificare che tutto funzioni correttamente.

Se il problema persiste, potrebbe essere necessario:
1. Aumentare la priorità del blocco per `_thumbnail_id`
2. Aggiungere ulteriori controlli per evitare chiamate non necessarie
3. Verificare se ci sono altri hook che interferiscono


