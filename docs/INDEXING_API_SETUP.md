# ⚡ Indexing API Setup - Guida Definitiva

## 🎯 Nome Corretto API

In Google Cloud Console cerca:

```
"Web Search Indexing API"
```

**Nomi alternativi** (dipende dalla versione Console):
- ✅ "Web Search Indexing API" (nome attuale 2025)
- ✅ "Indexing API" (nome abbreviato)
- ✅ "Google Indexing API" (nome generico)

**Nome ufficiale attuale**: **`Web Search Indexing API`**

---

## 📋 SETUP COMPLETO (10 minuti)

### Step 1: Google Cloud Console

```
1. https://console.cloud.google.com
2. Seleziona progetto (o crea nuovo)
3. Click su "☰" menu hamburger
4. "APIs & Services" → "Library"
```

### Step 2: Abilita Search Console API

```
1. In Library, cerca: "Search Console"
2. Click su: "Google Search Console API"
3. Click: "Enable"
4. Aspetta 5 secondi
5. ✅ Abilitata
```

### Step 3: Abilita Web Search Indexing API

```
1. Torna in Library (bottone "Library" in alto)
2. Cerca: "Indexing" o "Web Search Indexing"
3. Dovresti vedere:
   
   ┌────────────────────────────────┐
   │ Web Search Indexing API        │
   │ Google                         │
   │ Notifies Google when pages...  │
   └────────────────────────────────┘

4. Click sulla card "Web Search Indexing API"
5. Click: "Enable"
6. Aspetta propagazione (10-30 secondi)
7. ✅ Abilitata
```

**NOTA**: Potrebbe apparire anche come solo "Indexing API" - è la stessa cosa!

### Step 4: Verifica API Abilitate

```
1. "APIs & Services" → "Dashboard"
2. Sezione "Enabled APIs"
3. Dovresti vedere ENTRAMBE:
   
   ✓ Google Search Console API
   ✓ Web Search Indexing API (o "Indexing API")
```

### Step 5: Service Account

```
Se hai GIÀ creato service account per GSC:
→ USA LO STESSO! Non serve crearne uno nuovo.

Se NON hai service account:
1. "IAM & Admin" → "Service Accounts"
2. "Create Service Account"
3. Name: "fp-seo"
4. Create → Done (role opzionale)
5. Click sul service account
6. Tab "Keys" → "Add Key" → "Create new key"
7. Type: JSON
8. Create
9. ✅ File JSON scaricato
```

### Step 6: Aggiungi a Search Console

```
1. Apri il file JSON
2. Copia il valore di "client_email"
   Esempio: fp-seo@my-project-123456.iam.gserviceaccount.com

3. https://search.google.com/search-console
4. Seleziona la tua property
5. Settings (⚙️) → "Users and permissions"
6. "Add user"
7. Email address: [incolla client_email]
8. Permission level: "Owner" (MUST BE OWNER!)
9. Add
10. ✅ Service account aggiunto
```

**ATTENZIONE**: Permission deve essere **Owner**, altrimenti Indexing API non funziona!

### Step 7: Plugin Configuration

```
WordPress Admin
→ Settings → FP SEO → Google Search Console

1. Site URL: https://tuosito.com/
2. Service Account JSON: [incolla TUTTO il contenuto del file JSON]
3. ✅ Enable GSC Data
4. ✅ Auto-submit to Google on publish
5. Save Changes
6. Click "Test Connection"
7. Dovresti vedere: ✅ "Connection successful!"
```

---

## 🧪 TEST FUNZIONAMENTO

### Test 1: Verifica API nel Cloud Console

```
Google Cloud Console
→ APIs & Services → Dashboard
→ Enabled APIs

Checklist:
☑ Google Search Console API - ENABLED
☑ Indexing API - ENABLED

Se manca una delle due → Torna a Library e abilita
```

### Test 2: Publish Post di Test

```
1. WordPress → Posts → Add New
2. Title: "Test Indexing API"
3. Content: "Testing instant indexing..."
4. Publish
```

### Test 3: Controlla Debug Log

```
wp-content/debug.log

Cerca (ultime righe):
[Date] FP SEO: URL submitted to Google Indexing API: https://tuosito.com/test-indexing-api/ (URL_UPDATED)

✅ LO VEDI? = Funziona perfettamente!
❌ NON LO VEDI? = Vedi troubleshooting sotto
```

### Test 4: Verifica in Search Console

```
1. search.google.com/search-console
2. URL Inspection (barra in alto)
3. Inserisci URL del post pubblicato
4. Dovresti vedere la submission recente
```

---

## 🐛 TROUBLESHOOTING

### Errore: "API Indexing is not enabled"

**Causa**: Indexing API non abilitata

**Fix**:
```
1. Google Cloud Console → APIs & Services → Library
2. Cerca ESATTAMENTE: "Indexing API" (senza virgolette)
3. Se dice "Enable" → Click Enable
4. Aspetta 30 secondi per propagazione
5. Riprova publish post in WordPress
```

### Errore: "The caller does not have permission"

**Causa**: Service account non è Owner in GSC

**Fix DETTAGLIATO**:
```
1. Vai su: search.google.com/search-console
2. Seleziona property
3. Settings → Users and permissions
4. Trova email service account (es: fp-seo@project.iam.gserviceaccount.com)
5. Click sui 3 puntini → Edit
6. Permission level deve dire: "Owner"
7. Se dice "Full" o altro → Cambia a "Owner"
8. Save
9. Aspetta 1 minuto
10. Riprova publish in WordPress
```

### Errore: "Billing account required"

**Causa**: Progetto senza billing

**Fix**:
```
1. Google Cloud Console
2. Click su "Billing" nel menu
3. "Link a Billing Account"
4. Aggiungi carta di credito (NO ADDEBITI se usi solo tier gratuito)
5. Link account
6. L'API rimane GRATUITA (200 req/day)
```

### Debug Log Vuoto (No submission message)

**Possibili Cause**:
1. Auto-indexing non abilitato in Settings
2. Post type non supportato
3. Errore silente

**Debug**:
```
1. Settings → GSC → Verifica ✅ Auto-submit enabled
2. Aggiungi temporary debug in AutoIndexing.php:
   
   error_log('FP SEO: on_publish chiamato per post ' . $post_id);
   
3. Publish post
4. Check log per vedere se hook viene chiamato
```

---

## 📊 QUOTA & LIMITS

### Free Tier
```
Requests/Day: 200
Requests/Minute: 600
Cost: $0.00

Calculation:
- 200 posts/day pubblicati? OK
- 1000 posts/day? Serve upgrade
```

### Cosa Conta come "Request"
```
1 publish = 1 request
1 update = 1 request (se auto-submit enabled)
1 delete = 1 request

Tip: Disabilita auto-submit per minor updates
```

### Monitor Usage
```
Google Cloud Console
→ APIs & Services
→ Indexing API
→ Tab "Quotas"
→ Vedi: Requests per day used
```

---

## 🔐 SECURITY BEST PRACTICES

### 1. Proteggi JSON Key
```
❌ Non committare in Git
❌ Non condividere pubblicamente
✅ Store in wp_options (encrypted)
✅ Backup sicuro
```

### 2. Rotate Keys
```
Ogni 90 giorni:
1. Genera nuovo JSON key
2. Aggiorna in plugin Settings
3. Delete vecchia key in Cloud Console
```

### 3. Minimum Permissions
```
Service Account Role: "Service Account User" (o nessuno)
GSC Permission: "Owner" (minimo required)
```

---

## ✅ CHECKLIST COMPLETA

Prima di testare, verifica TUTTO:

**Google Cloud**:
- [ ] Progetto creato
- [ ] Billing account linked (richiesto)
- [ ] Google Search Console API - ENABLED
- [ ] **Indexing API** - ENABLED ← Fondamentale!
- [ ] Service account creato
- [ ] JSON key scaricato

**Search Console**:
- [ ] Property verificata
- [ ] Service account email aggiunto
- [ ] Permission = **Owner** (non Full!)

**WordPress Plugin**:
- [ ] Settings → GSC configurato
- [ ] Site URL corretto
- [ ] JSON key incollato (tutto!)
- [ ] ✅ Enable GSC Data
- [ ] ✅ Auto-submit to Google on publish
- [ ] Settings salvate
- [ ] Test Connection = Success

**Test**:
- [ ] Publish post di test
- [ ] Debug log mostra submission
- [ ] Post meta `_fp_seo_last_indexing_submission` presente
- [ ] Nessun errore in debug.log

---

## 📞 HELP

**Non funziona dopo setup?**

Manda via email questi screenshot:
1. Google Cloud → Enabled APIs (mostra Indexing API enabled)
2. GSC → Users (mostra service account con Owner)
3. WordPress debug.log (ultime 20 righe dopo publish)
4. Settings → GSC (nascondi JSON key per sicurezza)

Email: info@francescopasseri.com

Ti aiuto personalmente entro 24h!

---

## 🎯 NOME API SUMMARY

**Cerca in Library**:
```
"Indexing API"
```

**Nome completo visualizzato**:
```
Indexing API
By Google
Notifies Google when pages are added or updated
```

**Endpoint API**:
```
https://indexing.googleapis.com/v3/urlNotifications:publish
```

**Scope OAuth**:
```
https://www.googleapis.com/auth/indexing
```

---

**Version**: 0.4.0  
**API Name**: `Indexing API`  
**Confirmed**: ✅ Yes  
**Setup Time**: ~10 minuti  

**🚀 Ora sai esattamente cosa cercare!**

