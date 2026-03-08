# 🌐 Test Operatore Frontend - FP SEO Manager

**Versione**: 0.9.0-pre.72  
**Data**: 2025-01-27  
**Scopo**: Testare il plugin dal punto di vista di un utente finale che naviga il sito

---

## 📋 Scenario 1: Visualizzazione Post Ottimizzato

### Obiettivo
Verificare che un utente finale veda correttamente tutti i meta tag SEO e schema markup.

### Passi

1. **Preparazione**
   - [ ] Creare un post con SEO completo (usando test backend)
   - [ ] Pubblicare il post
   - [ ] Ottenere URL del post

2. **Verifica Meta Tags Base**
   - [ ] Aprire il post nel browser
   - [ ] Visualizzare source HTML (Ctrl+U / Cmd+U)
   - [ ] Verificare presenza `<title>` tag:
     - [ ] Titolo SEO personalizzato (non titolo post)
     - [ ] Lunghezza corretta (50-60 caratteri)
     - [ ] Contiene focus keyword
   - [ ] Verificare presenza `<meta name="description">`:
     - [ ] Descrizione SEO personalizzata
     - [ ] Lunghezza corretta (150-160 caratteri)
     - [ ] Contiene focus keyword
   - [ ] Verificare presenza canonical URL:
     - [ ] `<link rel="canonical" href="...">`
     - [ ] URL corretto e assoluto

3. **Verifica Open Graph Tags**
   - [ ] Verificare presenza meta tag Open Graph:
     - [ ] `<meta property="og:title" content="...">`
     - [ ] `<meta property="og:description" content="...">`
     - [ ] `<meta property="og:image" content="...">`
     - [ ] `<meta property="og:url" content="...">`
     - [ ] `<meta property="og:type" content="article">`
   - [ ] Verificare che i valori siano corretti
   - [ ] Verificare che l'immagine sia accessibile

4. **Verifica Twitter Cards**
   - [ ] Verificare presenza meta tag Twitter:
     - [ ] `<meta name="twitter:card" content="summary_large_image">`
     - [ ] `<meta name="twitter:title" content="...">`
     - [ ] `<meta name="twitter:description" content="...">`
     - [ ] `<meta name="twitter:image" content="...">`
   - [ ] Verificare che i valori siano corretti

5. **Verifica Schema JSON-LD**
   - [ ] Cercare `<script type="application/ld+json">` nel source
   - [ ] Verificare che lo schema sia presente
   - [ ] Copiare lo schema JSON
   - [ ] Validare su https://validator.schema.org
   - [ ] Verificare che lo schema sia valido

6. **Test con Validatori Esterni**
   - [ ] Testare con Facebook Debugger:
     - [ ] Aprire https://developers.facebook.com/tools/debug/
     - [ ] Inserire URL del post
     - [ ] Verificare che Open Graph tags siano riconosciuti
     - [ ] Verificare che l'immagine sia visibile
   - [ ] Testare con Twitter Card Validator:
     - [ ] Aprire https://cards-dev.twitter.com/validator
     - [ ] Inserire URL del post
     - [ ] Verificare che Twitter Cards siano riconosciute
   - [ ] Testare con Google Rich Results Test:
     - [ ] Aprire https://search.google.com/test/rich-results
     - [ ] Inserire URL del post
     - [ ] Verificare che lo schema sia riconosciuto

### Risultato Atteso
✅ Tutti i meta tag presenti, schema markup valido, validatori esterni confermano.

---

## 📋 Scenario 2: Visualizzazione Homepage

### Obiettivo
Verificare che la homepage abbia meta tag SEO corretti.

### Passi

1. **Accesso Homepage**
   - [ ] Navigare alla homepage del sito
   - [ ] Visualizzare source HTML

2. **Verifica Meta Tags Homepage**
   - [ ] Verificare `<title>` tag:
     - [ ] Titolo SEO della homepage (se configurato)
     - [ ] O titolo del sito come fallback
   - [ ] Verificare `<meta name="description">`:
     - [ ] Descrizione SEO della homepage (se configurata)
     - [ ] O tagline del sito come fallback
   - [ ] Verificare canonical URL:
     - [ ] Punta alla homepage corretta
     - [ ] URL assoluto

3. **Verifica Open Graph Homepage**
   - [ ] Verificare Open Graph tags
   - [ ] Verificare che l'immagine sia quella del sito (se configurata)

4. **Verifica Schema Organization**
   - [ ] Verificare presenza schema Organization (se configurato)
   - [ ] Validare schema su validator.schema.org

### Risultato Atteso
✅ Homepage con meta tag SEO corretti, schema markup valido.

---

## 📋 Scenario 3: Visualizzazione Archive Pages

### Obiettivo
Verificare che le pagine archivio (categorie, tag) abbiano meta tag corretti.

### Passi

1. **Accesso Archive**
   - [ ] Navigare a una categoria
   - [ ] Navigare a un tag
   - [ ] Visualizzare source HTML

2. **Verifica Meta Tags Archive**
   - [ ] Verificare `<title>` tag:
     - [ ] Contiene nome categoria/tag
     - [ ] Contiene nome sito
   - [ ] Verificare `<meta name="description">`:
     - [ ] Descrizione categoria/tag (se disponibile)
   - [ ] Verificare canonical URL:
     - [ ] Punta alla pagina archive corretta

3. **Verifica Schema Archive**
   - [ ] Verificare presenza schema CollectionPage (se applicabile)
   - [ ] Validare schema

### Risultato Atteso
✅ Archive pages con meta tag SEO corretti.

---

## 📋 Scenario 4: Visualizzazione Schema FAQ

### Obiettivo
Verificare che lo schema FAQ sia visibile e valido nel frontend.

### Passi

1. **Preparazione**
   - [ ] Creare un post con FAQ Schema (usando test backend)
   - [ ] Aggiungere almeno 3 FAQ
   - [ ] Pubblicare il post

2. **Verifica Schema FAQ**
   - [ ] Aprire il post nel browser
   - [ ] Visualizzare source HTML
   - [ ] Cercare schema FAQ nel JSON-LD
   - [ ] Verificare struttura:
     - [ ] `@type: "FAQPage"`
     - [ ] Array `mainEntity` con FAQ
     - [ ] Ogni FAQ ha `question` e `answer`
   - [ ] Validare su https://validator.schema.org

3. **Test Rich Results**
   - [ ] Testare con Google Rich Results Test
   - [ ] Verificare che le FAQ siano riconosciute
   - [ ] Verificare che possano apparire come rich snippets

### Risultato Atteso
✅ Schema FAQ presente, valido e riconosciuto da Google.

---

## 📋 Scenario 5: Visualizzazione Schema HowTo

### Obiettivo
Verificare che lo schema HowTo sia visibile e valido nel frontend.

### Passi

1. **Preparazione**
   - [ ] Creare un post con HowTo Schema (usando test backend)
   - [ ] Aggiungere almeno 5 step
   - [ ] Pubblicare il post

2. **Verifica Schema HowTo**
   - [ ] Aprire il post nel browser
   - [ ] Visualizzare source HTML
   - [ ] Cercare schema HowTo nel JSON-LD
   - [ ] Verificare struttura:
     - [ ] `@type: "HowTo"`
     - [ ] `name` (titolo guida)
     - [ ] Array `step` con step della guida
     - [ ] Ogni step ha `name` e `text`
     - [ ] `totalTime` (se configurato)
   - [ ] Validare su https://validator.schema.org

3. **Test Rich Results**
   - [ ] Testare con Google Rich Results Test
   - [ ] Verificare che HowTo sia riconosciuto
   - [ ] Verificare che possa apparire come rich snippet

### Risultato Atteso
✅ Schema HowTo presente, valido e riconosciuto da Google.

---

## 📋 Scenario 6: Condivisione Social Media

### Obiettivo
Verificare che la condivisione su social media mostri preview corretti.

### Passi

1. **Preparazione**
   - [ ] Creare un post con meta tag social completi
   - [ ] Configurare immagine Open Graph personalizzata
   - [ ] Pubblicare il post

2. **Test Facebook**
   - [ ] Aprire Facebook Debugger
   - [ ] Inserire URL del post
   - [ ] Verificare preview:
     - [ ] Titolo corretto
     - [ ] Descrizione corretta
     - [ ] Immagine visibile e corretta
     - [ ] URL corretto
   - [ ] Cliccare "Scrape Again" per aggiornare cache
   - [ ] Verificare che il preview si aggiorni

3. **Test Twitter**
   - [ ] Aprire Twitter Card Validator
   - [ ] Inserire URL del post
   - [ ] Verificare preview:
     - [ ] Card type corretto
     - [ ] Titolo corretto
     - [ ] Descrizione corretta
     - [ ] Immagine visibile e corretta
   - [ ] Verificare che la card sia valida

4. **Test LinkedIn**
   - [ ] Aprire LinkedIn Post Inspector
   - [ ] Inserire URL del post
   - [ ] Verificare preview:
     - [ ] Titolo corretto
     - [ ] Descrizione corretta
     - [ ] Immagine visibile

5. **Test WhatsApp**
   - [ ] Condividere URL del post su WhatsApp
   - [ ] Verificare che il preview mostri:
     - [ ] Titolo
     - [ ] Descrizione
     - [ ] Immagine

### Risultato Atteso
✅ Preview social media corretti su tutti i platform, immagini visibili.

---

## 📋 Scenario 7: Visualizzazione Mobile

### Obiettivo
Verificare che i meta tag siano corretti anche su dispositivi mobili.

### Passi

1. **Test Mobile Browser**
   - [ ] Aprire il post su smartphone
   - [ ] Verificare che il titolo nella tab del browser sia corretto
   - [ ] Condividere il link
   - [ ] Verificare che il preview sia corretto

2. **Test Responsive Meta Tags**
   - [ ] Verificare viewport meta tag:
     - [ ] `<meta name="viewport" content="width=device-width, initial-scale=1">`
   - [ ] Verificare che i meta tag siano presenti anche su mobile

3. **Test AMP (se disponibile)**
   - [ ] Verificare che i meta tag siano presenti nella versione AMP
   - [ ] Verificare che lo schema markup sia presente

### Risultato Atteso
✅ Meta tag corretti su mobile, preview social funzionanti.

---

## 📋 Scenario 8: Performance Frontend

### Obiettivo
Verificare che il plugin non impatti negativamente le performance frontend.

### Passi

1. **Test PageSpeed**
   - [ ] Aprire Google PageSpeed Insights
   - [ ] Testare URL del post
   - [ ] Verificare score:
     - [ ] Performance > 80
     - [ ] SEO > 90
   - [ ] Verificare che non ci siano problemi SEO

2. **Test Lighthouse**
   - [ ] Aprire Chrome DevTools
   - [ ] Eseguire audit Lighthouse
   - [ ] Verificare:
     - [ ] Performance score
     - [ ] SEO score
     - [ ] Best practices
   - [ ] Verificare che non ci siano errori

3. **Test Tempo di Caricamento**
   - [ ] Misurare tempo di caricamento pagina
   - [ ] Verificare che sia < 3 secondi
   - [ ] Verificare che i meta tag non rallentino il caricamento

4. **Test Dimensioni HTML**
   - [ ] Verificare dimensioni HTML source
   - [ ] Verificare che i meta tag non aggiungano troppo peso
   - [ ] Verificare che lo schema JSON-LD sia ottimizzato

### Risultato Atteso
✅ Performance frontend non impattate negativamente, score SEO alti.

---

## 📋 Scenario 9: Verifica GEO Endpoints

### Obiettivo
Verificare che gli endpoint GEO siano accessibili e funzionanti.

### Passi

1. **Test ai.txt**
   - [ ] Navigare a `/.well-known/ai.txt`
   - [ ] Verificare che il file sia accessibile
   - [ ] Verificare contenuto:
     - [ ] Informazioni sito
     - [ ] Contatti
     - [ ] Formato corretto

2. **Test GEO Sitemap**
   - [ ] Navigare a `/geo-sitemap.xml`
   - [ ] Verificare che il sitemap sia accessibile
   - [ ] Verificare formato XML:
     - [ ] XML valido
     - [ ] Struttura corretta
     - [ ] URL presenti

3. **Test Site JSON**
   - [ ] Navigare a `/geo/site.json`
   - [ ] Verificare che il JSON sia accessibile
   - [ ] Verificare formato JSON:
     - [ ] JSON valido
     - [ ] Struttura corretta
     - [ ] Dati presenti

4. **Test Content JSON**
   - [ ] Navigare a `/geo/content/{post-id}.json`
   - [ ] Verificare che il JSON sia accessibile
   - [ ] Verificare formato JSON:
     - [ ] JSON valido
     - [ ] Dati post presenti

### Risultato Atteso
✅ Tutti gli endpoint GEO accessibili, formati corretti.

---

## 📋 Checklist Generale Operatore Frontend

### Meta Tags Base
- [ ] Title tag presente e corretto
- [ ] Meta description presente e corretta
- [ ] Canonical URL presente e corretto
- [ ] Nessun meta tag duplicato

### Social Media
- [ ] Open Graph tags presenti
- [ ] Twitter Cards presenti
- [ ] Immagini social accessibili
- [ ] Preview social corretti

### Schema Markup
- [ ] Schema JSON-LD presente
- [ ] Schema valido (validator.schema.org)
- [ ] Schema riconosciuto da Google
- [ ] Rich snippets possibili

### Performance
- [ ] PageSpeed score > 80
- [ ] SEO score > 90
- [ ] Tempo caricamento < 3 secondi
- [ ] Nessun errore console

### Compatibilità
- [ ] Funziona su desktop
- [ ] Funziona su mobile
- [ ] Funziona su tablet
- [ ] Preview social corretti

---

## 🐛 Problemi Comuni e Soluzioni

### Problema: Meta tags non visibili
**Soluzione**: Verificare che il post sia pubblicato, controllare cache

### Problema: Immagine Open Graph non visibile
**Soluzione**: Verificare che l'immagine sia accessibile, dimensioni corrette

### Problema: Schema non valido
**Soluzione**: Verificare formato JSON, struttura schema

### Problema: Preview social non aggiornato
**Soluzione**: Scrapare nuovamente su Facebook Debugger, pulire cache

---

## 🔍 Strumenti di Test Consigliati

### Validatori
- **Schema.org Validator**: https://validator.schema.org
- **Google Rich Results Test**: https://search.google.com/test/rich-results
- **Facebook Debugger**: https://developers.facebook.com/tools/debug/
- **Twitter Card Validator**: https://cards-dev.twitter.com/validator
- **LinkedIn Post Inspector**: https://www.linkedin.com/post-inspector/

### Performance
- **Google PageSpeed Insights**: https://pagespeed.web.dev/
- **GTmetrix**: https://gtmetrix.com/
- **WebPageTest**: https://www.webpagetest.org/

### SEO
- **Google Search Console**: Verificare indicizzazione
- **Bing Webmaster Tools**: Verificare indicizzazione
- **Screaming Frog**: Crawl sito per verificare meta tags

---

**Ultimo aggiornamento**: 2025-01-27  
**Versione Plugin**: 0.9.0-pre.72




