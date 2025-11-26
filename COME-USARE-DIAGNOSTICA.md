# 🔍 Come Usare la Diagnostica Metabox

## 🚀 Accesso Rapido

### Metodo 1: URL Diretto
Apri nel browser:
```
http://fp-development.local/wp-content/plugins/FP-SEO-Manager/DIAGNOSTICA-METABOX-COMPLETA.php
```

### Metodo 2: Da Admin WordPress
1. Accedi a WordPress admin
2. Vai su: `http://fp-development.local/wp-admin/post.php?post=441&action=edit`
3. Apri una nuova scheda e vai su: `http://fp-development.local/wp-content/plugins/FP-SEO-Manager/DIAGNOSTICA-METABOX-COMPLETA.php`

---

## 📊 Cosa Verifica lo Script

Lo script diagnostico verifica **11 aree critiche**:

### 1. ✅ Ambiente WordPress
- `is_admin()` disponibile
- Costante `WP_ADMIN` definita
- URI corrente
- Hook corrente

### 2. ✅ Stato Plugin
- Plugin caricato correttamente
- Container istanziato
- Registry istanziato
- Registry bootato

### 3. ✅ Service Providers
- MainMetaboxServiceProvider registrato
- Lista di tutti i provider registrati

### 4. ✅ Container - Metabox
- Metabox presente nel container
- Classe istanza corretta
- Proprietà disponibili

### 5. ✅ Hook WordPress
- Hook `add_meta_boxes` registrato
- Callback metabox presente
- Altri hook importanti (admin_init, admin_menu, etc.)

### 6. ✅ Stato Boot
- Flag boot verificato
- Metodo `register()` disponibile

### 7. ✅ Post Types
- Post types supportati
- Lista completa

### 8. ✅ Timing Hooks
- Ordine hook WordPress
- Spiegazione timing

### 9. ✅ Test Boot Manuale
- Boot manuale del registry
- Verifica hook dopo boot

### 10. ✅ Riepilogo
- Liste di successi, warning, errori
- Conclusione diagnostica

### 11. ✅ Debug Info
- Variabili globali WordPress
- Stato WP_DEBUG

---

## 🎯 Interpretazione Risultati

### 🟢 Tutto Verde (Successi)
Se vedi molti **successi** (✓), il problema potrebbe essere:
- **Cache del browser** → Fai hard refresh (Ctrl+F5)
- **Cache WordPress** → Svuota cache plugin
- **Altri plugin** che interferiscono

### 🟡 Warning (Avvisi)
Gli **warning** indicano potenziali problemi non critici:
- Hook non ancora registrato (timing)
- Boot flag non verificabile
- WP_DEBUG non abilitato

### 🔴 Errori (Critici)
Gli **errori** indicano problemi che **bloccano** il funzionamento:
- Plugin non caricato
- Service provider non registrato
- Metabox non nel container
- Hook non registrato

---

## 🔧 Dopo la Diagnostica

### Se Vedi Errori
1. **Copia il riepilogo** degli errori
2. **Verifica i log** di WordPress (se WP_DEBUG abilitato)
3. **Controlla i file modificati** per errori di sintassi

### Se Vedi Solo Warning
1. **Verifica il timing** degli hook
2. **Abilita WP_DEBUG** per più informazioni
3. **Prova a ricaricare** la pagina di edit post

### Se Tutto è Verde ma il Metabox Non Compare
1. **Hard refresh** del browser (Ctrl+F5)
2. **Svuota cache** WordPress/plugin
3. **Disattiva altri plugin** uno alla volta per trovare conflitti
4. **Verifica la console browser** per errori JavaScript

---

## 📝 Output Atteso

Un report HTML completo con:
- ✅ Sezioni colorate per facile lettura
- 📊 Tabelle e liste organizzate
- 🔍 Dettagli tecnici completi
- 🎯 Conclusione finale chiara

---

**IMPORTANTE:** Esegui la diagnostica PRIMA di fare altre modifiche per avere una baseline chiara del problema!




