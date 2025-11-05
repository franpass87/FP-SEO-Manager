# ✅ TEST COMPLETO FP SEO MANAGER - CHECKLIST

## 🎯 Obiettivo
Testare TUTTE le funzionalità del plugin FP SEO Manager inclusa l'integrazione AI con GPT-5 Nano.

---

## 📋 PRE-TEST: Verifica Installazione

### ☑️ 1. Plugin Attivo
```
WordPress Admin → Plugin → Plugin Installati
□ FP SEO Performance è nella lista
□ Stato: Attivo (sfondo blu)
□ Versione: 0.4.1 o superiore
```

### ☑️ 2. Menu Visibile
```
Sidebar WordPress
□ Voce menu "FP SEO Performance" presente
□ Sottomenu visibili:
  - Dashboard
  - Settings
  - Bulk Audit
  - GSC (se configurato)
```

---

## 🔧 PARTE 1: Impostazioni Generali

### ☑️ Test 1.1: Pagina Settings - Tab General
```
1. Vai a: FP SEO Performance → Settings
2. Tab: General (dovrebbe essere già selezionato)

VERIFICA:
□ Checkbox "Enable on-page analyzer" presente
□ Dropdown "Content language" con opzioni: English, Spanish, French, German, Italian
□ Checkbox "Display analyzer score badge in the admin bar"
□ Pulsante "Save Changes" in basso

AZIONE:
□ Attiva "Enable on-page analyzer" ✓
□ Seleziona lingua: Italian
□ Clicca "Save Changes"
□ Messaggio "Settings saved" appare in alto
```

### ☑️ Test 1.2: Tab Analysis
```
1. Clicca tab "Analysis"

VERIFICA:
□ Sezione "Checks" con vari toggle:
  - Title Length
  - Meta Description
  - H1 Presence
  - Headings Structure
  - Image Alt
  - Canonical
  - Robots
  - OG Cards
  - Twitter Cards
  - Schema Presets
  - Internal Links
  
□ Sezione "Title Length Thresholds"
  - Min: [campo numerico]
  - Max: [campo numerico]
  
□ Sezione "Meta Description Length Thresholds"
  - Min: [campo numerico]
  - Max: [campo numerico]

AZIONE:
□ Attiva tutti i check ✓
□ Imposta Title Min: 50, Max: 60
□ Imposta Meta Min: 120, Max: 160
□ Salva
```

### ☑️ Test 1.3: Tab Performance
```
1. Clicca tab "Performance"

VERIFICA:
□ Sezione PageSpeed Insights
  - Enable PSI checkbox
  - PSI API Key field
  - Cache TTL field
  
□ Sezione Heuristics
  - Image alt coverage
  - Inline CSS
  - Image count
  - Heading depth

AZIONE:
□ Lascia disabilitato PSI (richiede API key Google)
□ Attiva tutte le heuristics
□ Salva
```

### ☑️ Test 1.4: Tab AI ⚡ (NUOVO!)
```
1. Clicca tab "AI"

VERIFICA:
□ Sezione "Configurazione OpenAI" presente
□ Campo "API Key OpenAI" (tipo password)
□ Dropdown "Modello AI" con opzioni:
  ✅ GPT-5 Nano ⚡ (Consigliato - Veloce ed Economico) ← SELEZIONATO
  - GPT-5 Mini (Ottimizzato)
  - GPT-5 (Qualità Massima)
  - GPT-5 Pro (Enterprise)
  - GPT-4o Mini (Legacy)
  - GPT-4o (Legacy)
  - GPT-4 Turbo (Legacy)
  - GPT-3.5 Turbo (Obsoleto)

□ Checkbox "Abilita generazione automatica SEO" ✓
□ Checkbox "Priorità alle keyword nel contenuto" ✓
□ Checkbox "Ottimizza per Click-Through Rate (CTR)" ✓

□ Sezione "Informazioni" con box blu:
  - Spiegazione come funziona
  - Lista cosa genera (titolo, meta, slug, keyword)

AZIONE:
□ Inserisci API Key OpenAI: sk-[la tua key]
  (Se non ce l'hai: vai su https://platform.openai.com/api-keys)
□ Verifica che GPT-5 Nano sia selezionato
□ Assicurati che tutte le checkbox siano attive ✓
□ Clicca "Save Changes"
□ Verifica messaggio: "✓ API Key configurata correttamente" (verde)
```

### ☑️ Test 1.5: Tab Advanced
```
1. Clicca tab "Advanced"

VERIFICA:
□ Capability selector (chi può usare il plugin)
□ Telemetry checkbox
□ Import/Export sezione (se presente)

AZIONE:
□ Lascia "manage_options"
□ Salva
```

---

## 📝 PARTE 2: Editor Post - Metabox SEO

### ☑️ Test 2.1: Creare Nuovo Post
```
1. Vai a: Post → Aggiungi nuovo

VERIFICA:
□ Metabox "SEO Performance" visibile (lato destro o sotto editor)
□ Header blu con gradiente
□ Titolo "SEO Performance" in bianco

Se non vedi il metabox:
□ Clicca "Opzioni schermata" (3 puntini in alto)
□ Attiva "SEO Performance" ✓
```

### ☑️ Test 2.2: Contenuto del Metabox
```
VERIFICA SEZIONI:

1. □ Controlli
   - Checkbox "Exclude this content from analysis"
   
2. □ SEO Score
   - Badge circolare con numero
   - Colori: verde (>80), giallo (50-80), rosso (<50)
   
3. □ Key Indicators
   - Lista check con icone:
     ✓ verde = pass
     ⚠️ giallo = warning
     ✗ rosso = fail
     
4. □ Recommendations
   - Lista suggerimenti miglioramento
   
5. □ 🤖 Generazione AI - Contenuti SEO ⚡ (NUOVO!)
   - Box blu/azzurro con gradiente
   - Titolo con emoji robot 🤖
   - Descrizione funzionalità
```

### ☑️ Test 2.3: Sezione AI - Dettagli
```
VERIFICA COMPONENTI:

□ Header: "🤖 Generazione AI - Contenuti SEO"

□ Descrizione: "Genera automaticamente titolo SEO, meta description e slug..."

□ Campo INPUT:
  Label: "🎯 Focus Keyword (Opzionale)"
  Placeholder: "es: SEO WordPress, marketing digitale, ..."
  Campo testo con bordo azzurro
  Help text: "💡 Inserisci la parola chiave principale..."

□ Pulsante "Genera con AI":
  - Colore azzurro (#0ea5e9)
  - Icona ingranaggio (dashicons-admin-generic)
  - Testo: "Genera con AI"

□ Area Loading (nascosta):
  - Spinner animato
  - Testo: "Generazione in corso... Attendere prego."

□ Area Risultati (nascosta inizialmente):
  - Titolo verde: "✓ Contenuti generati con successo!"
  - 4 campi readonly:
    * Titolo SEO (con contatore 0/60)
    * Meta Description (con contatore 0/155)
    * Slug
    * Focus Keyword
  - 2 pulsanti:
    * "Applica questi suggerimenti" (verde)
    * "Copia negli appunti"

□ Area Errore (nascosta):
  - Box rosso con messaggio errore
```

---

## 🤖 PARTE 3: Test Generazione AI

### ☑️ Test 3.1: Preparazione Post
```
1. Titolo post: "Come Ottimizzare WordPress per la SEO"

2. Contenuto (scrivi almeno 300 parole):
   
   "WordPress è la piattaforma CMS più utilizzata al mondo, ma per 
   ottenere visibilità sui motori di ricerca è fondamentale ottimizzarlo 
   correttamente. In questa guida completa ti mostrerò passo dopo passo 
   come migliorare la SEO del tuo sito WordPress.
   
   La SEO (Search Engine Optimization) è essenziale per aumentare il 
   traffico organico. Con le giuste tecniche, puoi migliorare 
   significativamente il posizionamento su Google.
   
   [continua con altro testo...]"

3. Categorie: 
   □ Aggiungi "SEO"
   □ Aggiungi "Tutorial"

4. Tag:
   □ wordpress
   □ seo
   □ ottimizzazione
   □ guida

5. Excerpt (opzionale):
   "Scopri come ottimizzare WordPress per i motori di ricerca con 
   questa guida completa. Tecniche, plugin e strategie SEO."
```

### ☑️ Test 3.2: Generazione SENZA Focus Keyword
```
AZIONE:
1. Scorri fino al metabox "SEO Performance"
2. Trova la sezione "🤖 Generazione AI"
3. LASCIA VUOTO il campo "Focus Keyword"
4. Clicca "Genera con AI"

VERIFICA:
□ Pulsante diventa disabled (grigio)
□ Appare area loading:
  - Spinner rotante
  - Testo "Generazione in corso..."
  
ATTENDI 3-10 secondi

□ Loading scompare
□ Appare area risultati (verde):
  
  ✓ Contenuti generati con successo!
  
  Titolo SEO: [testo generato]                    [XX/60]
  └─ Verifica: Contiene parole dal tuo contenuto
  └─ Verifica: Lunghezza <= 60 caratteri
  └─ Contatore verde (🟢) se < 54 caratteri
  └─ Contatore arancione (🟠) se 54-60 caratteri
  └─ Contatore rosso (🔴) se > 60 caratteri
  
  Meta Description: [testo generato]              [XXX/155]
  └─ Verifica: Descrizione accattivante
  └─ Verifica: Lunghezza <= 155 caratteri
  └─ Contatore colorato come sopra
  
  Slug: [url-ottimizzato]
  └─ Verifica: Solo minuscole e trattini
  └─ Verifica: No caratteri speciali
  
  Focus Keyword: [keyword identificata]
  └─ Verifica: Parola chiave pertinente al contenuto
```

### ☑️ Test 3.3: Generazione CON Focus Keyword
```
AZIONE:
1. Clicca di nuovo "Genera con AI" (per rigenerare)
2. Questa volta inserisci nel campo:
   Focus Keyword: "SEO WordPress"
3. Clicca "Genera con AI"

ATTENDI 3-10 secondi

VERIFICA:
□ Risultati generati
□ Titolo SEO contiene "SEO WordPress" ✅
□ Meta Description contiene "SEO WordPress" ✅
□ Focus Keyword campo mostra: "SEO WordPress"
□ Contatori mostrano: XX/60 e XXX/155

CONFRONTA:
□ Risultati con keyword sono diversi da quelli senza
□ Keyword è stata integrata nel testo
```

### ☑️ Test 3.4: Verifica Contatori Caratteri
```
CONTROLLA CONTATORE TITOLO:

Se mostra: 52/60 🟢 = OK (verde, sotto 90%)
Se mostra: 58/60 🟠 = WARNING (arancione, 90-100%)
Se mostra: 63/60 🔴 = EXCEEDED (rosso, sopra 100% - troncato)

CONTROLLA CONTATORE META:

Se mostra: 148/155 🟢 = OK
Se mostra: 152/155 🟠 = WARNING  
Se mostra: 158/155 🔴 = EXCEEDED (auto-troncato)

□ Verifica che i contatori cambino colore correttamente
```

### ☑️ Test 3.5: Applicare Suggerimenti
```
AZIONE:
1. Clicca pulsante "Applica questi suggerimenti" (verde)

VERIFICA IN GUTENBERG:
□ Titolo del post viene aggiornato con il titolo SEO generato
□ Slug URL viene aggiornato (verifica nella sidebar)
□ Appare notifica: "Suggerimenti applicati! Ricorda di salvare il post."

VERIFICA IN CLASSIC EDITOR:
□ Campo titolo (#title) viene aggiornato
□ Slug aggiornato
□ Notifica di successo

□ I campi del post sono stati popolati correttamente
```

### ☑️ Test 3.6: Copia negli Appunti
```
AZIONE:
1. Clicca pulsante "Copia negli appunti"

VERIFICA:
□ Appare notifica: "Contenuti copiati negli appunti!"

TEST INCOLLA:
2. Apri un editor di testo (Blocco note, Word, etc.)
3. Incolla (Ctrl+V / Cmd+V)

VERIFICA FORMATO:
□ Testo incollato contiene:
   Titolo SEO: [testo]
   
   Meta Description: [testo]
   
   Slug: [testo]
   
   Focus Keyword: [testo]
```

### ☑️ Test 3.7: Test Errori
```
TEST 3.7.1 - SENZA API KEY:

1. Vai in Settings → AI
2. Rimuovi temporaneamente l'API Key
3. Salva
4. Torna al post
5. Clicca "Genera con AI"

VERIFICA:
□ Appare box errore rosso
□ Messaggio: "OpenAI API key non configurata. Vai in Impostazioni > FP SEO."
□ No crash/errori fatali

6. Rimetti l'API Key e salva


TEST 3.7.2 - CONTENUTO VUOTO:

1. Crea nuovo post senza contenuto
2. Lascia titolo vuoto
3. Clicca "Genera con AI"

VERIFICA:
□ Appare errore: "Per favore inserisci almeno un titolo o del contenuto..."
□ No crash


TEST 3.7.3 - API KEY INVALIDA:

1. Settings → AI
2. Inserisci API key falsa: sk-fake123456789
3. Salva
4. Torna al post con contenuto
5. Clicca "Genera con AI"

VERIFICA:
□ Loading appare
□ Dopo alcuni secondi appare errore:
   "Errore OpenAI: [messaggio errore]"
□ Box rosso con dettagli errore

6. Rimetti API Key corretta
```

---

## 📊 PARTE 4: SEO Performance Analyzer

### ☑️ Test 4.1: Analisi Real-time
```
Con il post ancora aperto:

1. Scrivi contenuto minimo (100 parole)
2. Osserva il metabox SEO Performance

VERIFICA SCORE:
□ Score iniziale appare (es: 45/100)
□ Badge colorato:
  - Verde: 80-100
  - Giallo: 50-79
  - Rosso: 0-49

VERIFICA INDICATORS:
□ Title Length: stato (✓/⚠️/✗)
□ Meta Description: stato
□ H1 Presence: stato
□ Altri check visibili

3. Aggiungi un H1 al contenuto
4. Osserva score aggiornato

□ Score aumenta
□ H1 Presence diventa verde ✓
```

### ☑️ Test 4.2: Recommendations
```
VERIFICA SEZIONE RECOMMENDATIONS:

□ Lista di suggerimenti presenti
□ Esempi:
  - "Add a meta description"
  - "Ensure title is between 50-60 characters"
  - "Add alt text to images"
  
□ Suggerimenti cambiano quando risolvi problemi
```

---

## 🔍 PARTE 5: Bulk Audit

### ☑️ Test 5.1: Pagina Bulk Audit
```
1. Vai a: FP SEO Performance → Bulk Audit

VERIFICA:
□ Titolo pagina: "Bulk SEO Audit"
□ Filtri:
  - Post Type selector
  - Search box
  - Items per page
  
□ Tabella con colonne:
  - Title
  - SEO Score
  - Status
  - Last Updated
  
□ Azioni bulk disponibili

2. Seleziona "Posts" nel filtro
3. Clicca "Apply Filters"

VERIFICA:
□ Lista di tutti i post appare
□ Ogni post mostra:
  - Titolo
  - Score badge colorato
  - Status indicators
  - Link "View"/"Edit"
```

### ☑️ Test 5.2: Export Results (se disponibile)
```
Se presente pulsante Export:

1. Clicca "Export CSV" o "Export JSON"

VERIFICA:
□ File scaricato
□ Contiene dati post + scores
```

---

## 🎨 PARTE 6: Admin Bar Badge (se abilitato)

### ☑️ Test 6.1: Badge Visibility
```
SE hai abilitato "Admin bar badge" in Settings → General:

1. Apri qualsiasi post/pagina pubblicato

VERIFICA ADMIN BAR (barra nera in alto):
□ Badge "SEO" presente
□ Mostra score (es: "78")
□ Badge colorato (verde/giallo/rosso)

2. Passa mouse sopra il badge

VERIFICA TOOLTIP:
□ Dettagli score appaiono
□ Breakdown check visibili
```

---

## 📱 PARTE 7: Site Health Integration

### ☑️ Test 7.1: SEO Health Check
```
1. Vai a: Strumenti → Salute del sito
2. Clicca tab "Info"

VERIFICA:
□ Sezione "SEO Performance" presente
□ Mostra:
  - Plugin version
  - Analyzer status
  - Number of analyzed posts
  - Average score

OPZIONALE (se implementato):
□ Test SEO in tab "Test"
```

---

## 🌐 PARTE 8: GEO Features (se configurato)

### ☑️ Test 8.1: GEO Settings
```
1. Vai a: Settings → (cerca tab GEO se presente)

VERIFICA:
□ Opzioni GEO disponibili
□ ai.txt configuration
□ Sitemap options
```

### ☑️ Test 8.2: Endpoints GEO
```
Apri browser e visita:

1. http://tuo-sito.local/.well-known/ai.txt
   □ File testo appare
   □ Contiene direttive AI

2. http://tuo-sito.local/geo-sitemap.xml
   □ XML sitemap appare
   □ Lista post/pagine

3. http://tuo-sito.local/geo/site.json
   □ JSON valido
   □ Metadati sito
```

---

## 🐛 PARTE 9: Test Errori e Edge Cases

### ☑️ Test 9.1: Plugin Conflicts
```
1. Attiva altro plugin SEO (Yoast, RankMath, etc.)

VERIFICA:
□ FP SEO Performance continua a funzionare
□ No errori JavaScript console
□ No conflitti metabox
```

### ☑️ Test 9.2: Performance
```
1. Apri browser DevTools (F12)
2. Tab Network
3. Ricarica pagina editor

VERIFICA:
□ Script ai-generator.js caricato
□ No errori 404
□ Tempo caricamento < 2 secondi
```

### ☑️ Test 9.3: JavaScript Console
```
1. Apri Console (F12)
2. Clicca "Genera con AI"

VERIFICA:
□ No errori rossi in console
□ Request AJAX visibile (fp_seo_generate_ai_content)
□ Response 200 OK
```

---

## ✅ RISULTATI FINALI

### Funzionalità Testate:

- [ ] Settings - 5 tab (General, Analysis, Performance, AI, Advanced)
- [ ] AI Generation con GPT-5 Nano
- [ ] Focus Keyword input
- [ ] Contatori caratteri real-time
- [ ] Applica suggerimenti
- [ ] Copia negli appunti
- [ ] SEO Score analyzer
- [ ] Key indicators
- [ ] Recommendations
- [ ] Bulk Audit
- [ ] Admin Bar Badge
- [ ] Site Health integration
- [ ] GEO endpoints
- [ ] Error handling

### Punteggio Finale:
**[X] / 14 funzionalità testate e funzionanti**

---

## 🚨 Problemi Riscontrati

(Compila durante i test)

1. ____________________________________________
2. ____________________________________________
3. ____________________________________________

---

## 📝 Note Aggiuntive

(Aggiungi osservazioni)

- ____________________________________________
- ____________________________________________
- ____________________________________________

---

**Data Test:** _________________
**Testato da:** _________________
**Versione Plugin:** 0.4.1
**Versione WordPress:** _________________
**Tema Attivo:** _________________

---

✅ Test completato con successo! 🎉

