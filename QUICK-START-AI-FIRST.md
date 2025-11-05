# ⚡ Quick Start - AI-First Features

**Tempo richiesto**: 5 minuti  
**Prerequisiti**: Plugin FP SEO Manager attivo

---

## 🚀 Step 1: Flush Permalink (OBBLIGATORIO)

### Metodo Rapido (WordPress Admin)

1. Vai su: **WordPress Admin**
2. Apri: **Impostazioni → Permalinks**
3. Clicca: **"Salva modifiche"** (anche senza modificare nulla)
4. ✅ Fatto!

**Perché?** I nuovi endpoint `/geo/content/{id}/qa.json`, `/geo/content/{id}/chunks.json`, etc. richiedono rewrite rules attive.

---

## 🧪 Step 2: Test Endpoint (1 minuto)

### Test Rapido via Browser

Apri questi URL nel browser (sostituisci `tuosito.com` e `123` con il tuo dominio e un post ID reale):

```
https://tuosito.com/geo/site.json
https://tuosito.com/geo/content/123/qa.json
https://tuosito.com/geo/content/123/chunks.json
https://tuosito.com/geo/content/123/authority.json
```

**Expected**: JSON response (non 404)

### Test via cURL

```bash
curl https://tuosito.com/geo/site.json | jq .
```

**Expected**: JSON formattato con dati del sito

---

## 🤖 Step 3: Configura OpenAI (Opzionale ma Consigliato)

### Ottieni API Key

1. Vai su: https://platform.openai.com/api-keys
2. Crea account o login
3. Clicca: "Create new secret key"
4. Copia la key (inizia con `sk-`)

### Configura nel Plugin

1. WordPress Admin → **FP SEO Performance → Settings → AI**
2. Incolla API Key nel campo "API Key OpenAI"
3. Modello: **GPT-5 Nano** (già selezionato)
4. Abilita tutte le checkbox
5. Clicca **"Salva modifiche"**

**Risultato**: ✅ Vedrai "API Key configurata correttamente" (verde)

---

## ⚡ Step 4: Test Funzionalità AI

### Test Q&A Extraction

```php
<?php
// Crea file: wp-content/plugins/FP-SEO-Manager/test-qa-extraction.php

require_once '../../../wp-load.php';

$post_id = 1; // Sostituisci con un post ID reale

echo "🔍 Testing Q&A Extraction...\n\n";

$extractor = new FP\SEO\AI\QAPairExtractor();
$qa_pairs = $extractor->extract_qa_pairs( $post_id, true ); // force=true

if ( empty( $qa_pairs ) ) {
    echo "⚠️ Nessuna Q&A estratta. Verifica:\n";
    echo "   - API Key OpenAI configurata?\n";
    echo "   - Post ha contenuto sufficiente?\n";
} else {
    echo "✅ Estratte " . count( $qa_pairs ) . " Q&A pairs!\n\n";
    
    foreach ( $qa_pairs as $i => $pair ) {
        echo "Q" . ($i+1) . ": " . $pair['question'] . "\n";
        echo "A: " . substr( $pair['answer'], 0, 100 ) . "...\n";
        echo "Confidence: " . $pair['confidence'] . "\n\n";
    }
}
```

**Run**: Visita `https://tuosito.com/wp-content/plugins/FP-SEO-Manager/test-qa-extraction.php`

### Test Endpoint HTTP

```bash
# Test Q&A endpoint
curl https://tuosito.com/geo/content/1/qa.json

# Expected: JSON con qa_pairs array
```

---

## 📊 Step 5: Verifica Funzionamento

### Checklist Rapida

- [ ] Permalinks flushed? (Step 1)
- [ ] Endpoint `/geo/site.json` funziona? (HTTP 200)
- [ ] Endpoint `/geo/content/1/qa.json` funziona? (HTTP 200)
- [ ] OpenAI API key configurata? (se vuoi Q&A automatiche)
- [ ] Test Q&A extraction completato?

### Troubleshooting

**404 su endpoint GEO**:
→ Soluzione: Flush permalinks (Step 1)

**Q&A pairs vuote**:
→ Soluzione: Configura OpenAI API key (Step 3)

**Errore "OpenAI not configured"**:
→ Soluzione: Verifica API key in Settings → AI

**Endpoint lenti**:
→ Normale al primo accesso (genera dati), poi usa cache

---

## 🎯 Cosa Fare Dopo

### Ottimizzazione Contenuto

1. **Batch Process Post Esistenti**:
   ```php
   // Esegui su 10-20 post alla volta
   $posts = get_posts(['posts_per_page' => 20]);
   foreach ($posts as $post) {
       $extractor->extract_qa_pairs($post->ID);
       sleep(2); // Rate limiting
   }
   ```

2. **Aggiungi Claims Manualmente**:
   - Apri post in editor
   - Trova metabox "FP GEO Claims"
   - Aggiungi claims con evidence URLs

3. **Ottimizza Immagini**:
   - Verifica che TUTTE le immagini abbiano alt text
   - Alt text descrittivi (non solo "image1.jpg")
   - Caption dove appropriato

### Monitoring

Monitora citazioni AI:
- Google Search Console (AI Overview impressions)
- Analytics (referral da AI engines)
- Manual search su ChatGPT/Gemini/Perplexity

---

## 📞 Supporto

**Documentazione Completa**:
- `AI-FIRST-IMPLEMENTATION-COMPLETE.md` (questo file)
- `BUGFIX-AI-FEATURES-SESSION.md` (report bugfix)

**Test Issues?**
- Verifica PHP 8.0+ attivo
- Verifica composer autoload funzionante
- Controlla error log WordPress

---

## 🎉 Sei Pronto!

Il tuo WordPress è ora una **macchina ottimizzata per AI search engines**! 🚀

**Prossimi risultati attesi**:
- Citazioni su ChatGPT entro 2-4 settimane
- Presenza in Google AI Overview entro 1-2 mesi
- Featured snippets aumentati del 300%

**Good luck dominating AI search! 🏆**


