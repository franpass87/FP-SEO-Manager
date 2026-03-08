# 🖥️ Test Operatore Backend - FP SEO Manager

**Versione**: 0.9.0-pre.72  
**Data**: 2025-01-27  
**Scopo**: Testare il plugin dal punto di vista di un operatore backend che usa il plugin quotidianamente

---

## 📋 Scenario 1: Creazione e Ottimizzazione Post SEO

### Obiettivo
Verificare che un operatore backend possa creare e ottimizzare un post per SEO in modo completo.

### Passi

1. **Login e Navigazione**
   - [ ] Accedere a WordPress Admin
   - [ ] Verificare che il menu "FP SEO Performance" sia visibile
   - [ ] Navigare a **Post → Aggiungi nuovo**

2. **Creazione Post Base**
   - [ ] Inserire titolo: "Guida Completa al SEO WordPress 2025"
   - [ ] Scrivere contenuto di almeno 500 parole
   - [ ] Aggiungere almeno 3 immagini con alt text
   - [ ] Aggiungere almeno 2 link interni
   - [ ] Salvare come bozza

3. **Uso Metabox SEO**
   - [ ] Verificare che il metabox "SEO Performance" sia visibile
   - [ ] Verificare che tutti i campi siano vuoti inizialmente

4. **Generazione SEO con AI**
   - [ ] Cliccare su "Genera con AI" nel metabox
   - [ ] Attendere la generazione (può richiedere 10-30 secondi)
   - [ ] Verificare che i campi vengano popolati:
     - [ ] SEO Title
     - [ ] Meta Description
     - [ ] Focus Keyword
     - [ ] Slug suggerito
   - [ ] Verificare che i contatori caratteri siano visibili e corretti

5. **Ottimizzazione Manuale**
   - [ ] Modificare il SEO Title per renderlo più accattivante
   - [ ] Verificare che il contatore cambi colore (verde/giallo/rosso)
   - [ ] Modificare la Meta Description
   - [ ] Aggiungere Focus Keyword: "seo wordpress"
   - [ ] Verificare che l'analisi in tempo reale si aggiorni

6. **Analisi SEO**
   - [ ] Verificare che l'analisi mostri:
     - [ ] Score SEO (0-100)
     - [ ] Lista di problemi/suggerimenti
     - [ ] Indicatori colorati (verde/giallo/rosso)
   - [ ] Cliccare su "Applica suggerimenti" se disponibili
   - [ ] Verificare che i suggerimenti vengano applicati

7. **Schema Markup**
   - [ ] Aprire il metabox "FAQ Schema"
   - [ ] Aggiungere 3 domande e risposte
   - [ ] Salvare
   - [ ] Verificare che il metabox "HowTo Schema" sia disponibile
   - [ ] Aggiungere una guida passo-passo (opzionale)

8. **Salvataggio e Pubblicazione**
   - [ ] Salvare il post
   - [ ] Verificare che tutti i dati SEO siano salvati
   - [ ] Pubblicare il post
   - [ ] Verificare che non ci siano errori

### Risultato Atteso
✅ Post creato con SEO completo, analisi eseguita, schema markup aggiunto, nessun errore.

---

## 📋 Scenario 2: Bulk Audit di Post Esistenti

### Obiettivo
Verificare che un operatore possa eseguire audit SEO su multipli post contemporaneamente.

### Passi

1. **Preparazione**
   - [ ] Avere almeno 10 post pubblicati nel sito
   - [ ] Navigare a **FP SEO Performance → Bulk Audit**

2. **Configurazione Audit**
   - [ ] Selezionare post type: "Post"
   - [ ] Selezionare status: "Pubblicato"
   - [ ] Selezionare almeno 5 post dalla lista
   - [ ] Verificare che i filtri funzionino correttamente

3. **Esecuzione Audit**
   - [ ] Cliccare su "Esegui Audit"
   - [ ] Verificare che appaia un indicatore di progresso
   - [ ] Attendere il completamento (può richiedere 1-5 minuti)
   - [ ] Verificare che non ci siano errori durante l'esecuzione

4. **Analisi Risultati**
   - [ ] Verificare che i risultati siano visualizzati in tabella
   - [ ] Verificare che ogni post mostri:
     - [ ] Score SEO
     - [ ] Titolo SEO
     - [ ] Meta Description
     - [ ] Focus Keyword
     - [ ] Lista problemi
   - [ ] Ordinare per score (dal peggiore al migliore)
   - [ ] Filtrare per post con score < 50

5. **Export Risultati**
   - [ ] Cliccare su "Esporta CSV"
   - [ ] Verificare che il file CSV venga scaricato
   - [ ] Aprire il CSV e verificare che contenga tutti i dati

6. **Azioni di Massa**
   - [ ] Selezionare 3 post con score basso
   - [ ] Cliccare su "Applica suggerimenti AI" (se disponibile)
   - [ ] Verificare che i suggerimenti vengano applicati

### Risultato Atteso
✅ Audit completato su tutti i post selezionati, risultati visualizzati correttamente, export funzionante.

---

## 📋 Scenario 3: Configurazione Impostazioni Plugin

### Obiettivo
Verificare che un operatore possa configurare tutte le impostazioni del plugin.

### Passi

1. **Accesso Settings**
   - [ ] Navigare a **FP SEO Performance → Settings**
   - [ ] Verificare che tutte le tab siano visibili

2. **Tab General**
   - [ ] Configurare:
     - [ ] Abilitare/disabilitare analisi automatica
     - [ ] Impostare post types supportati
     - [ ] Configurare default SEO
   - [ ] Salvare
   - [ ] Verificare che le impostazioni siano salvate

3. **Tab Analysis**
   - [ ] Configurare:
     - [ ] Soglie score (verde/giallo/rosso)
     - [ ] Check da eseguire
     - [ ] Frequenza analisi
   - [ ] Salvare
   - [ ] Verificare che le impostazioni siano salvate

4. **Tab AI**
   - [ ] Inserire API Key OpenAI
   - [ ] Testare connessione
   - [ ] Configurare prompt personalizzati
   - [ ] Salvare
   - [ ] Verificare che la connessione funzioni

5. **Tab Google Search Console**
   - [ ] Configurare credenziali GSC
   - [ ] Testare connessione
   - [ ] Configurare sincronizzazione dati
   - [ ] Salvare
   - [ ] Verificare che i dati GSC siano accessibili

6. **Tab GEO**
   - [ ] Abilitare endpoint GEO
   - [ ] Configurare `.well-known/ai.txt`
   - [ ] Configurare sitemap GEO
   - [ ] Salvare
   - [ ] Verificare che gli endpoint siano accessibili

7. **Tab Performance**
   - [ ] Configurare cache
   - [ ] Configurare ottimizzazioni
   - [ ] Salvare
   - [ ] Verificare che le ottimizzazioni siano attive

### Risultato Atteso
✅ Tutte le impostazioni salvate correttamente, connessioni testate e funzionanti.

---

## 📋 Scenario 4: Gestione Schema Markup

### Obiettivo
Verificare che un operatore possa aggiungere e gestire schema markup avanzato.

### Passi

1. **FAQ Schema**
   - [ ] Aprire un post esistente
   - [ ] Aprire metabox "FAQ Schema"
   - [ ] Aggiungere 5 domande e risposte
   - [ ] Salvare
   - [ ] Verificare che lo schema sia salvato

2. **HowTo Schema**
   - [ ] Aprire metabox "HowTo Schema"
   - [ ] Aggiungere titolo guida
   - [ ] Aggiungere almeno 5 step
   - [ ] Aggiungere tempo totale stimato
   - [ ] Salvare
   - [ ] Verificare che lo schema sia salvato

3. **Q&A Pairs**
   - [ ] Aprire metabox "Q&A Pairs"
   - [ ] Aggiungere 3 coppie domanda/risposta
   - [ ] Salvare
   - [ ] Verificare che lo schema sia salvato

4. **Verifica Frontend**
   - [ ] Pubblicare il post
   - [ ] Visualizzare il post sul frontend
   - [ ] Inspect source HTML
   - [ ] Verificare che lo schema JSON-LD sia presente
   - [ ] Validare schema su https://validator.schema.org

### Risultato Atteso
✅ Schema markup aggiunto correttamente, validato e visibile nel frontend.

---

## 📋 Scenario 5: Monitoraggio Performance

### Obiettivo
Verificare che un operatore possa monitorare le performance SEO del sito.

### Passi

1. **Accesso Dashboard**
   - [ ] Navigare a **FP SEO Performance → Performance Dashboard**
   - [ ] Verificare che la dashboard si carichi

2. **Visualizzazione Metriche**
   - [ ] Verificare che siano visualizzate:
     - [ ] Score SEO medio
     - [ ] Numero post ottimizzati
     - [ ] Numero post da ottimizzare
     - [ ] Trend score nel tempo
   - [ ] Verificare che i grafici siano visibili

3. **Filtri e Date Range**
   - [ ] Selezionare range date: ultimi 30 giorni
   - [ ] Verificare che i dati si aggiornino
   - [ ] Selezionare post type specifico
   - [ ] Verificare che i dati si filtrino

4. **Export Report**
   - [ ] Cliccare su "Esporta Report"
   - [ ] Verificare che il report venga generato
   - [ ] Verificare che contenga tutte le metriche

### Risultato Atteso
✅ Dashboard funzionante, metriche accurate, report esportabili.

---

## 📋 Scenario 6: Gestione Link Interni

### Obiettivo
Verificare che un operatore possa gestire i link interni suggeriti.

### Passi

1. **Accesso Link Interni**
   - [ ] Navigare a **FP SEO Performance → Internal Links**
   - [ ] Verificare che la pagina si carichi

2. **Visualizzazione Suggerimenti**
   - [ ] Verificare che siano mostrati suggerimenti di link
   - [ ] Verificare che ogni suggerimento mostri:
     - [ ] Post sorgente
     - [ ] Post target
     - [ ] Anchor text suggerito
     - [ ] Rilevanza

3. **Applicazione Suggerimenti**
   - [ ] Selezionare un suggerimento
   - [ ] Cliccare su "Applica"
   - [ ] Verificare che il link venga aggiunto al post
   - [ ] Verificare che il suggerimento venga rimosso dalla lista

4. **Gestione Manuale**
   - [ ] Aprire un post
   - [ ] Verificare che i suggerimenti di link siano visibili nel metabox
   - [ ] Applicare un suggerimento manualmente
   - [ ] Verificare che il link venga aggiunto

### Risultato Atteso
✅ Suggerimenti link visualizzati, applicabili e funzionanti.

---

## 📋 Scenario 7: Gestione Keywords Multiple

### Obiettivo
Verificare che un operatore possa gestire multiple keywords per post.

### Passi

1. **Accesso Keywords**
   - [ ] Navigare a **FP SEO Performance → Multiple Keywords**
   - [ ] Verificare che la pagina si carichi

2. **Aggiunta Keywords**
   - [ ] Selezionare un post
   - [ ] Aggiungere 3 keywords:
     - [ ] Keyword principale
     - [ ] Keyword secondaria 1
     - [ ] Keyword secondaria 2
   - [ ] Salvare
   - [ ] Verificare che le keywords siano salvate

3. **Suggerimenti Keywords**
   - [ ] Cliccare su "Suggerisci Keywords"
   - [ ] Verificare che vengano suggerite keywords rilevanti
   - [ ] Selezionare alcune keywords suggerite
   - [ ] Aggiungere al post
   - [ ] Verificare che vengano salvate

4. **Analisi Keywords**
   - [ ] Verificare che ogni keyword mostri:
     - [ ] Densità nel contenuto
     - [ ] Posizione nel contenuto
     - [ ] Rilevanza
   - [ ] Verificare che i suggerimenti di ottimizzazione siano visibili

### Risultato Atteso
✅ Keywords multiple gestibili, suggerimenti funzionanti, analisi accurate.

---

## 📋 Scenario 8: Gestione Social Media Meta

### Obiettivo
Verificare che un operatore possa configurare meta tag per social media.

### Passi

1. **Accesso Social Media**
   - [ ] Navigare a **FP SEO Performance → Social Media**
   - [ ] Verificare che la pagina si carichi

2. **Configurazione Default**
   - [ ] Impostare immagine default per Open Graph
   - [ ] Impostare immagine default per Twitter
   - [ ] Configurare formato Twitter Card
   - [ ] Salvare
   - [ ] Verificare che le impostazioni siano salvate

3. **Configurazione per Post**
   - [ ] Aprire un post
   - [ ] Verificare che il metabox "Social Media" sia visibile
   - [ ] Configurare:
     - [ ] Titolo Open Graph personalizzato
     - [ ] Descrizione Open Graph personalizzata
     - [ ] Immagine Open Graph personalizzata
     - [ ] Titolo Twitter personalizzato
     - [ ] Descrizione Twitter personalizzata
     - [ ] Immagine Twitter personalizzata
   - [ ] Salvare
   - [ ] Verificare che i meta tag siano salvati

4. **Verifica Frontend**
   - [ ] Pubblicare il post
   - [ ] Visualizzare il post sul frontend
   - [ ] Inspect source HTML
   - [ ] Verificare che i meta tag Open Graph siano presenti
   - [ ] Verificare che i meta tag Twitter siano presenti
   - [ ] Testare con Facebook Debugger
   - [ ] Testare con Twitter Card Validator

### Risultato Atteso
✅ Meta tag social media configurati correttamente, validati e funzionanti.

---

## 📋 Checklist Generale Operatore Backend

### Funzionalità Base
- [ ] Login WordPress Admin
- [ ] Menu plugin visibile e accessibile
- [ ] Metabox SEO visibile nell'editor
- [ ] Salvataggio dati SEO funzionante
- [ ] Analisi SEO in tempo reale funzionante

### Funzionalità Avanzate
- [ ] Generazione AI funzionante
- [ ] Bulk audit eseguibile
- [ ] Settings configurabili
- [ ] Schema markup aggiungibile
- [ ] Link interni gestibili
- [ ] Keywords multiple gestibili
- [ ] Social media meta configurabili

### Performance
- [ ] Pagine admin caricano in < 3 secondi
- [ ] Analisi SEO completa in < 10 secondi
- [ ] Bulk audit su 10 post in < 2 minuti
- [ ] Nessun errore JavaScript in console
- [ ] Nessun errore PHP in debug.log

### Usabilità
- [ ] Interfaccia intuitiva
- [ ] Messaggi di errore chiari
- [ ] Feedback visivo per azioni
- [ ] Tooltip e help text disponibili
- [ ] Responsive su mobile/tablet

---

## 🐛 Problemi Comuni e Soluzioni

### Problema: Metabox non appare
**Soluzione**: Verificare che il post type sia supportato nelle settings

### Problema: Generazione AI non funziona
**Soluzione**: Verificare API Key OpenAI nelle settings

### Problema: Analisi SEO lenta
**Soluzione**: Verificare che la cache sia attiva, controllare performance

### Problema: Bulk audit fallisce
**Soluzione**: Verificare che ci siano abbastanza post, controllare timeout PHP

---

**Ultimo aggiornamento**: 2025-01-27  
**Versione Plugin**: 0.9.0-pre.72




