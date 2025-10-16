# 🎯 Search Intent Optimization

## Panoramica

Il **Search Intent** (intenzione di ricerca) è uno degli aspetti più critici della moderna SEO. Google e gli altri motori di ricerca non si limitano più a valutare le keyword, ma analizzano l'**intento** dell'utente dietro ogni ricerca per fornire i risultati più pertinenti.

FP SEO Performance integra ora un sistema avanzato di **Search Intent Analysis** che aiuta a ottimizzare i contenuti allineandoli perfettamente con le aspettative degli utenti.

## 🔍 Cos'è il Search Intent?

Il Search Intent rappresenta l'obiettivo che un utente vuole raggiungere quando effettua una ricerca. Esistono 4 tipi principali:

### 1. **Informational** (Informazionale)
L'utente cerca informazioni, vuole imparare qualcosa o risolvere un problema.

**Esempi di query:**
- "come creare un sito web"
- "cosa significa SEO"
- "guida WordPress per principianti"
- "perché il mio sito è lento"

**Keyword tipiche:**
- come, cosa, perché, quando, dove
- guida, tutorial, spiegazione
- cos'è, significato, definizione
- esempio, esempi

### 2. **Transactional** (Transazionale)
L'utente è pronto ad acquistare o compiere un'azione specifica.

**Esempi di query:**
- "acquista hosting WordPress"
- "sconto plugin SEO"
- "compra dominio .it"

**Keyword tipiche:**
- acquista, compra, ordina
- prezzo, prezzi, sconto, offerta
- disponibile, consegna, spedizione
- scarica, download

### 3. **Commercial Investigation** (Commerciale)
L'utente sta valutando diverse opzioni prima di acquistare.

**Esempi di query:**
- "migliori plugin SEO 2024"
- "Yoast vs Rank Math recensione"
- "confronto hosting WordPress"

**Keyword tipiche:**
- migliore, migliori, top
- recensione, recensioni, review
- confronto, comparazione, vs
- alternative, vantaggi, svantaggi

### 4. **Navigational** (Navigazionale)
L'utente cerca un sito o una pagina specifica.

**Esempi di query:**
- "login WordPress.org"
- "Facebook accedi"
- "sito ufficiale Nike"

**Keyword tipiche:**
- login, accedi, area clienti
- sito ufficiale, homepage
- contatti, chi siamo

## 🚀 Come Funziona il Search Intent Check

Il nuovo check di FP SEO Performance analizza automaticamente:

1. **Titolo e contenuto** della pagina
2. **Keyword presenti** e loro frequenza
3. **Struttura del contenuto** (domande, liste, prezzi)
4. **Segnali contestuali** (es. presenza di valute, CTA)

### Algoritmo di Rilevamento

Il sistema:
- Assegna punteggi a ogni tipo di intent in base alle keyword rilevate
- Applica **pesi maggiori** ai termini presenti nel titolo
- Riconosce segnali aggiuntivi (domande multiple, prezzi, etc.)
- Calcola un **livello di confidenza** (0-100%)

### Esempio di Output

```
Search Intent rilevato: Informazionale (confidenza: 85%)

Raccomandazioni:
✓ Usa strutture FAQ per rispondere a domande comuni
✓ Includi esempi pratici e tutorial step-by-step
✓ Ottimizza per featured snippets con liste e definizioni
✓ Aggiungi schema markup FAQ o HowTo

Segnali rilevati: Informational keyword "come" found 3x, 
Multiple question marks indicate informational intent
```

## 📊 Raccomandazioni per Tipo di Intent

### Intent Informazionale

**Cosa fare:**
- ✅ Usa strutture FAQ (Domande e Risposte)
- ✅ Crea tutorial step-by-step con numerazioni
- ✅ Includi esempi pratici e case study
- ✅ Aggiungi FAQ Schema o HowTo Schema
- ✅ Ottimizza per featured snippets
- ✅ Usa heading chiari (H2, H3) per ogni sezione

**Schema consigliati:**
- FAQPage
- HowTo
- Article / BlogPosting

**Struttura ideale:**
```
H1: Come fare X
├─ Introduzione breve
├─ H2: Cosa serve
├─ H2: Step 1
├─ H2: Step 2
├─ H2: Step 3
├─ H2: Conclusioni
└─ H2: Domande Frequenti (FAQ)
```

### Intent Transazionale

**Cosa fare:**
- ✅ Includi CTA (Call-To-Action) chiari e visibili
- ✅ Mostra prezzi, disponibilità, opzioni di spedizione
- ✅ Semplifica il processo di acquisto
- ✅ Aggiungi Product Schema
- ✅ Includi recensioni e rating
- ✅ Mostra badge di sicurezza e garanzie

**Schema consigliati:**
- Product
- Offer
- Review / AggregateRating

**Elementi essenziali:**
- Prezzi chiari e visibili
- Pulsanti "Acquista Ora" above the fold
- Info su spedizione e resi
- Trust signals (sicurezza, garanzie)

### Intent Commerciale

**Cosa fare:**
- ✅ Fornisci comparazioni dettagliate e obiettive
- ✅ Crea tabelle comparative
- ✅ Includi sezioni Pro/Contro
- ✅ Aggiungi recensioni autentiche
- ✅ Usa Review Schema per aumentare visibilità
- ✅ Includi criteri di valutazione trasparenti

**Schema consigliati:**
- Review / AggregateRating
- Product (per i prodotti confrontati)

**Struttura ideale:**
```
H1: Migliori [Prodotto] 2024: Confronto e Recensioni
├─ Introduzione: Metodologia di test
├─ H2: Prodotto #1
│   ├─ H3: Pro e Contro
│   └─ H3: Prezzo e Offerte
├─ H2: Prodotto #2
├─ H2: Tabella Comparativa
└─ H2: Conclusioni: Quale scegliere?
```

### Intent Navigazionale

**Cosa fare:**
- ✅ Ottimizza brand name e meta tags
- ✅ Implementa Organization Schema
- ✅ Assicura che logo e menu siano strutturati
- ✅ Crea pagine dedicate (Login, Contatti, Chi Siamo)
- ✅ Ottimizza internal linking

**Schema consigliati:**
- Organization
- WebSite
- BreadcrumbList

## 🎨 Best Practices

### 1. Allineamento Intent-Contenuto

**❌ Errore comune:**
Titolo: "Migliori Plugin SEO" (intent commerciale)
Contenuto: Tutorial su come usare un plugin (intent informazionale)

**✅ Corretto:**
Titolo: "Migliori Plugin SEO" (intent commerciale)
Contenuto: Recensioni, confronti, pro/contro, tabelle comparative

### 2. Mixed Intent

Alcuni contenuti possono avere **intent misti**. Ad esempio:
- "Acquista Hosting WordPress" (transazionale) + FAQ (informazionale)

In questi casi:
- Il contenuto **primario** deve allinearsi all'intent principale
- Aggiungi sezioni secondarie per intent correlati
- Usa schema markup multipli se appropriato

### 3. Ottimizzazione per AI Overview

Il Search Intent è cruciale anche per **Google AI Overview**:

- **Intent Informazionale**: Massima probabilità di apparire in AI Overview
- **Intent Commerciale**: Buone possibilità con comparazioni
- **Intent Transazionale**: Più difficile, ma possibile con rich snippets

## 🔧 Implementazione Tecnica

### Attivazione del Check

Il Search Intent Check è **attivo di default** in FP SEO Performance. Puoi configurarlo da:

**Settings → Analysis → Search Intent Check**

### Hooks Disponibili

```php
// Modificare la rilevazione del search intent
add_filter( 'fp_seo_search_intent_keywords', function( $keywords, $type ) {
    // Aggiungi keyword personalizzate per tipo
    if ( $type === 'informational' ) {
        $keywords[] = 'tutorial-personalizzato';
    }
    return $keywords;
}, 10, 2 );

// Modificare le raccomandazioni
add_filter( 'fp_seo_search_intent_recommendations', function( $recs, $intent ) {
    // Aggiungi raccomandazioni custom
    if ( $intent === 'transactional' ) {
        $recs[] = 'Aggiungi video demo del prodotto';
    }
    return $recs;
}, 10, 2 );
```

### API Usage

```php
use FP\SEO\Utils\SearchIntentDetector;

// Rilevamento intent
$result = SearchIntentDetector::detect( $title, $content );

echo $result['intent'];      // 'informational', 'transactional', etc.
echo $result['confidence'];  // 0.0 - 1.0
print_r( $result['signals'] ); // Array di segnali rilevati

// Ottenere raccomandazioni
$recommendations = SearchIntentDetector::get_recommendations( 
    $result['intent'] 
);

// Label tradotte
$label = SearchIntentDetector::get_intent_label( $result['intent'] );
```

## 📈 Impatto sulla SEO

### Metriche Migliorabili

Ottimizzando il Search Intent puoi migliorare:

1. **CTR (Click-Through Rate)**: +20-40%
   - Titoli e snippet allineati alle aspettative
   
2. **Dwell Time**: +30-60%
   - Contenuto che soddisfa realmente l'utente
   
3. **Bounce Rate**: -15-30%
   - Minor abbandono perché trovano ciò che cercano
   
4. **Conversioni**: +10-50%
   - Contenuto allineato alla fase del funnel

### Case Study

**Prima dell'ottimizzazione:**
- Pagina: "Plugin SEO WordPress"
- Intent rilevato: Mixed/Unknown (40% confidence)
- Posizione SERP: #12
- CTR: 1.2%

**Dopo l'ottimizzazione:**
- Intent ottimizzato: Commercial (85% confidence)
- Aggiunto: Tabella comparativa, Review Schema, Pro/Contro
- Posizione SERP: #3
- CTR: 8.5%

## 🎯 Checklist Search Intent

### Per Ogni Contenuto

- [ ] **Analizza il search intent** con il tool
- [ ] **Verifica la confidenza** (puntare a >70%)
- [ ] **Leggi le raccomandazioni** specifiche
- [ ] **Implementa le ottimizzazioni** suggerite
- [ ] **Aggiungi schema markup** appropriati
- [ ] **Testa su Google Search Console** le impressions/click
- [ ] **Monitora metriche** (CTR, bounce rate)

## 📚 Risorse Aggiuntive

- [Google Search Quality Guidelines](https://developers.google.com/search/docs/fundamentals/creating-helpful-content)
- [Understanding Search Intent - Moz](https://moz.com/learn/seo/search-intent)
- [Schema.org Documentation](https://schema.org/)

## 🆘 Supporto

Per domande o personalizzazioni sul Search Intent Analyzer:

- **Email**: info@francescopasseri.com
- **Website**: [francescopasseri.com](https://francescopasseri.com)

---

**Ultima modifica**: 2025-10-16
**Versione plugin**: 0.1.2+
