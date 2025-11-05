# 🧠 Sistema di Contestualizzazione AI

## Come l'AI Capisce il Contesto per Generare Titoli Coerenti

L'intelligenza artificiale analizza **molteplici fonti di informazione** per comprendere perfettamente il contesto del tuo contenuto e generare titoli SEO pertinenti e coerenti.

---

## 📊 Informazioni Inviate all'AI

### 1. **Contenuto del Post** 
```
✅ Primi 2000 caratteri del contenuto (pulito da HTML)
✅ Analisi semantica del testo
✅ Identificazione argomenti principali
```

### 2. **Titolo Attuale**
```
✅ Titolo provvisorio del post
✅ Utilizzato come punto di riferimento
✅ Aiuta a capire l'intento dell'autore
```

### 3. **Focus Keyword** (se impostata)
```
✅ Parola chiave principale scelta dall'utente
✅ Integrata OBBLIGATORIAMENTE nel titolo SEO
✅ Usata per orientare l'ottimizzazione
```

### 4. **Metadati WordPress**

#### 📁 **Categorie**
```
Esempio: "SEO", "WordPress", "Tutorial"
Permette all'AI di capire la macro-area tematica
```

#### 🏷️ **Tag**
```
Esempio: "ottimizzazione", "motori di ricerca", "ranking"
Fornisce dettagli specifici sull'argomento
```

#### 📄 **Tipo di Contenuto**
```
✅ Post (articolo blog)
✅ Pagina (contenuto statico)
✅ Prodotto (e-commerce)
✅ Portfolio, Eventi, Custom Post Types
```

#### 📝 **Excerpt/Riassunto**
```
Se presente, fornisce un sommario dell'argomento
Max 200 caratteri utilizzati
```

### 5. **Lingua del Sito**
```
✅ Rilevamento automatico (italiano/inglese)
✅ Adatta lo stile e il tono
✅ Usa espressioni native
```

---

## 🔍 Esempio Pratico

### Input del Post:

```
Titolo: "Guida WordPress"
Categoria: SEO, Tutorial
Tag: wordpress, seo, ottimizzazione, guida
Focus Keyword: SEO WordPress
Tipo: Post (articolo)

Contenuto:
"WordPress è la piattaforma CMS più utilizzata al mondo. 
In questa guida completa ti mostrerò come ottimizzare il 
tuo sito WordPress per i motori di ricerca. Scoprirai 
tecniche avanzate di SEO on-page, plugin essenziali e 
strategie per migliorare il ranking su Google..."
```

### Cosa Vede l'AI:

```json
{
  "language": "italiano",
  "title": "Guida WordPress",
  "context": {
    "post_type": "Post",
    "categories": ["SEO", "Tutorial"],
    "tags": ["wordpress", "seo", "ottimizzazione", "guida"],
    "focus_keyword": "SEO WordPress"
  },
  "content": "[primi 2000 caratteri del contenuto]"
}
```

### Prompt Inviato a OpenAI:

```
Analizza questo contenuto e genera suggerimenti SEO ottimizzati in italiano.

Titolo attuale: Guida WordPress
Tipo di contenuto: Post
Categorie: SEO, Tutorial
Tag: wordpress, seo, ottimizzazione, guida

Contenuto:
WordPress è la piattaforma CMS più utilizzata al mondo...

⚠️ IMPORTANTE: Devi OBBLIGATORIAMENTE utilizzare la parola chiave 
'SEO WordPress' nel titolo SEO e nella meta description.

Regole OBBLIGATORIE:
- Il titolo SEO deve essere MASSIMO 60 caratteri
- La meta description deve essere MASSIMO 155 caratteri
- La focus keyword DEVE essere: 'SEO WordPress'
- Considera le categorie e i tag per capire il contesto tematico
...
```

### Output dell'AI:

```json
{
  "seo_title": "Guida SEO WordPress: Ottimizza il Tuo Sito in 10 Passi",
  "meta_description": "Scopri come migliorare la SEO WordPress con la nostra guida completa. Tecniche avanzate, plugin essenziali e strategie per il ranking Google.",
  "slug": "guida-seo-wordpress-ottimizzazione",
  "focus_keyword": "SEO WordPress"
}
```

**Caratteri:**
- Titolo: 51/60 ✅
- Meta Description: 149/155 ✅

---

## ✨ Vantaggi del Sistema di Contestualizzazione

### 🎯 **Coerenza Tematica**
L'AI capisce l'argomento dal contesto completo (categorie + tag + contenuto)

### 🔍 **Ottimizzazione Mirata**
La focus keyword viene integrata naturalmente nel titolo e description

### 📚 **Adattamento al Tipo**
Titoli diversi per articoli, pagine prodotto, landing pages, etc.

### 🌍 **Lingua e Tono**
Stile appropriato per la lingua del sito

### 🎨 **Personalizzazione**
Riflette lo stile del tuo brand attraverso categorie e tag

---

## 🔧 Come Migliorare i Risultati

### 1. **Usa Categorie Significative**
```
❌ "Blog", "News", "Post"
✅ "SEO Avanzato", "Marketing Digitale", "Tutorial WordPress"
```

### 2. **Tag Specifici**
```
❌ "generale", "articolo", "post"
✅ "keyword research", "link building", "schema markup"
```

### 3. **Imposta la Focus Keyword**
```
✅ Inserisci SEMPRE la keyword principale
✅ Usa 1-3 parole chiave correlate
✅ Evita keyword stuffing
```

### 4. **Scrivi un Buon Contenuto**
```
✅ Almeno 300 parole prima di generare
✅ Paragrafi ben strutturati
✅ Argomento chiaro e definito
```

### 5. **Usa l'Excerpt**
```
✅ Scrivi un riassunto di 1-2 frasi
✅ Include la keyword principale
✅ Descrive il beneficio per il lettore
```

---

## 📈 Limiti e Validazione

### Controllo Automatico Caratteri

Il sistema ha **doppia validazione**:

1. **Prompt AI**: Istruisce l'AI a rispettare i limiti
2. **Safety Check PHP**: Tronca automaticamente se supera

```php
// Validazione lato server
if (strlen($seo_title) > 60) {
    $seo_title = substr($seo_title, 0, 60);
    // Rimuove parola parziale
    $seo_title = preg_replace('/\s+\S*$/', '', $seo_title);
    $seo_title = rtrim($seo_title, '.,;:!?') . '...';
}
```

### Indicatori Visivi

- 🟢 **Verde**: 0-90% del limite (ottimale)
- 🟠 **Arancione**: 90-100% del limite (attenzione)
- 🔴 **Rosso**: >100% del limite (superato - verrà troncato)

```
Titolo SEO: [testo generato]          52/60 🟢
Meta Description: [testo generato]    148/155 🟢
```

---

## 🚀 Esempi di Generazione Basata sul Contesto

### Esempio 1: Post Blog

**Input:**
- Categoria: Tutorial
- Tag: wordpress, backup, sicurezza
- Focus: backup WordPress

**Output AI:**
```
Titolo: Backup WordPress: Guida Completa per Mettere al Sicuro
Meta: Proteggi il tuo sito con i migliori metodi di backup WordPress. Plugin, automazione e strategie per non perdere mai i tuoi dati.
```

### Esempio 2: Pagina Prodotto

**Input:**
- Tipo: Prodotto WooCommerce
- Categoria: Plugin, SEO Tools
- Focus: plugin SEO

**Output AI:**
```
Titolo: Plugin SEO Premium - Ottimizza WordPress in 1 Click
Meta: Il miglior plugin SEO per WordPress. Analisi in tempo reale, suggerimenti AI e ranking garantito. Prova gratis 30 giorni!
```

### Esempio 3: Landing Page

**Input:**
- Tipo: Pagina
- Focus: corso SEO online
- Tag: formazione, digital marketing

**Output AI:**
```
Titolo: Corso SEO Online: Diventa Esperto in 8 Settimane
Meta: Master in SEO con certificazione. Impara strategie avanzate, keyword research e link building. Inizia oggi il tuo percorso SEO!
```

---

## 📝 Checklist Pre-Generazione

Prima di cliccare "Genera con AI", assicurati di:

- [ ] Aver scritto almeno 200-300 parole di contenuto
- [ ] Aver impostato le categorie appropriate
- [ ] Aver aggiunto tag pertinenti (5-10 tag)
- [ ] Aver inserito la focus keyword (se la conosci)
- [ ] Aver scritto un titolo provvisorio significativo
- [ ] Opzionale: aver compilato l'excerpt

---

## 🎯 Conclusione

Il sistema di contestualizzazione analizza **7 fonti di informazione diverse** per garantire che i contenuti SEO generati siano:

1. ✅ **Coerenti** con l'argomento del post
2. ✅ **Ottimizzati** per la keyword target
3. ✅ **Appropriati** per il tipo di contenuto
4. ✅ **Accattivanti** per invogliare al click
5. ✅ **Rispettosi** dei limiti di caratteri SEO

Più informazioni fornisci (categorie, tag, keyword, excerpt), più precisi saranno i risultati dell'AI!

---

**💡 Tip Finale**: L'AI è intelligente, ma **tu sei l'esperto del tuo contenuto**. Rivedi sempre i suggerimenti e personalizzali per il tuo brand!

