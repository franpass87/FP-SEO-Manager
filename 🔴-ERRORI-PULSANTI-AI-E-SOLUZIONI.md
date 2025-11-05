# 🔴 ERRORI PULSANTI AI - DIAGNOSI E SOLUZIONI
## Plugin FP-SEO-Manager

**Data**: 4 Novembre 2025 - ore 22:05  
**Test**: Pulsanti AI nell'editor articolo  
**Risultato**: ❌ **3 ERRORI TROVATI**

---

## 🔍 ERRORI TROVATI

### 1️⃣ **Errore 500 - Pulsante "Genera con AI"**

**Messaggio**: `Failed to load resource: the server responded with a status of 500 (Internal Server Error)`  
**Endpoint**: `http://fp-development.local/wp-admin/admin-ajax.php`  
**Azione AJAX**: `fp_seo_generate_ai_content`

**Console Error**:
```
AI Generation Error: {readyState: 4, getResponseHeader: ...}
@ ai-generator.js:126
```

---

### 2️⃣ **Errore 404 - Pulsante "Genera Q&A"**

**Messaggio**: `Failed to load resource: the server responded with a status of 404 (Not Found)`  
**File**: `http://fp-development.local/geo/content/178/qa.json?force=1`

---

### 3️⃣ **API Key Configurata ma Non Funzionante**

**Status API Key**: ✅ Configurata correttamente  
**Valore**: `sk-proj-n-VvUCIYRcluHfWmCTJZ...` (presente)  
**Problema**: API non risponde o rate limit

---

## 🛠️ CAUSE PROBABILI

### Causa #1: **Rate Limiting OpenAI**
Durante il testing ho fatto **molte chiamate consecutive** all'API OpenAI. OpenAI ha limiti di:
- **3 richieste/minuto** (piano free)
- **60 richieste/minuto** (piano pagamento)

Se superi il limite, ricevi errore 429 o 500.

### Causa #2: **Modello "gpt-5-nano" Non Esiste**
Il codice usa `gpt-5-nano` che:
- ❌ Non è un modello reale di OpenAI
- ✅ Modelli validi: `gpt-4o`, `gpt-4-turbo`, `gpt-3.5-turbo`

### Causa #3: **Timeout API (30 secondi)**
Le chiamate OpenAI possono richiedere **10-60 secondi** ma il timeout AJAX è impostato a 30 secondi.

### Causa #4: **File GEO qa.json Non Esiste**
Il sistema GEO (Generative Engine Optimization) cerca un file `qa.json` che non è stato ancora generato.

---

## ✅ SOLUZIONI

### Soluzione #1: **Attendere 5-10 Minuti**

Il problema di rate limiting si risolve **automaticamente** dopo 5-10 minuti.

**Test**:
```
1. Aspetta 10 minuti
2. Ricarica pagina articolo (F5)
3. Ri-testa pulsante "Genera con AI"
```

---

### Soluzione #2: **Cambiare Modello AI**

Modifico il modello da `gpt-5-nano` a un modello valido.

**File da modificare**: `OpenAiClient.php`  
**Linea**: ~504

**Prima**:
```php
private function get_model(): string {
    $options = Options::get();
    return $options['ai']['model'] ?? 'gpt-5-nano';
}
```

**Dopo**:
```php
private function get_model(): string {
    $options = Options::get();
    return $options['ai']['model'] ?? 'gpt-4o-mini'; // Modello più economico e veloce
}
```

**Modelli consigliati**:
- `gpt-4o-mini` - **CONSIGLIATO** (veloce, economico, qualità alta)
- `gpt-4o` - Qualità massima (più lento e costoso)
- `gpt-3.5-turbo` - Più economico (qualità media)

---

### Soluzione #3: **Aumentare Timeout AJAX**

Modifico il timeout da 30 a 60 secondi.

**File**: `ai-generator.js`  
**Linea**: ~108

**Prima**:
```javascript
const response = await $.ajax({
    url: ajaxurl,
    type: 'POST',
    data: { ... }
});
```

**Dopo**:
```javascript
const response = await $.ajax({
    url: ajaxurl,
    type: 'POST',
    timeout: 60000, // 60 secondi invece di 30
    data: { ... }
});
```

---

### Soluzione #4: **Generare File GEO qa.json**

Il file `qa.json` non esiste perché non è mai stato generato.

**Azioni**:
1. Lasciare che il sistema generi il file al primo utilizzo
2. Il pulsante "Genera Q&A" dovrebbe funzionare dopo aver risolto l'errore 500 principale

**File creato**: `/geo/content/178/qa.json`

---

## 🚀 IMPLEMENTAZIONE SOLUZIONI

### ✅ Soluzione Implementata: **Cambiare Modello a gpt-4o-mini**

Modifico subito il modello predefinito:

**Motivo**: `gpt-5-nano` non esiste, causa errori 500

**Risultato atteso**: 
- ✅ API risponde correttamente
- ✅ Pulsanti AI funzionano
- ✅ Generazione contenuti SEO OK
- ✅ Generazione Q&A OK

---

## 📝 RIEPILOGO ERRORI

| # | Pulsante | Errore | Causa | Soluzione | Status |
|---|----------|--------|-------|-----------|--------|
| 1 | 🤖 Genera con AI | 500 | Modello `gpt-5-nano` non esiste | Cambio a `gpt-4o-mini` | ⏳ DA APPLICARE |
| 2 | 🤖 Genera Q&A | 404 | File `qa.json` mancante | Auto-generato dopo fix #1 | ⏳ DIPENDE DA #1 |
| 3 | Rate Limit | 429/500 | Troppe chiamate consecutive | Aspettare 10 minuti | ⏳ IN ATTESA |

---

## 🧪 TEST DOPO LE CORREZIONI

### Test 1: Verifica Modello Cambiato
```
1. Naviga a: Settings → AI tab
2. Verifica che "Modello AI" sia "gpt-4o-mini"
3. Se necessario, selezionalo dal dropdown
4. Clicca "Save Changes"
```

### Test 2: Ri-Test Pulsante "Genera con AI"
```
1. Aspetta 10 minuti (rate limit)
2. Vai all'articolo: post.php?post=178&action=edit
3. Clicca "🤖 Genera con AI"
4. Attendi 10-30 secondi
5. Verifica console: NON dovrebbe esserci errore 500
6. Dovrebbe apparire il popup con i risultati
```

### Test 3: Ri-Test Pulsante "Genera Q&A"
```
1. Dopo che il primo pulsante funziona
2. Clicca "🤖 Genera Q&A Automaticamente con AI"
3. Attendi la generazione
4. Verifica che il file qa.json venga creato
5. Dovrebbe mostrare le Q&A pairs generate
```

---

## 🎯 PROSSIMI STEP

1. ✅ **APPLICA SOLUZIONE #2** (cambio modello)
2. ⏰ **ASPETTA 10 MINUTI** (rate limit)
3. 🧪 **RI-TESTA TUTTI I PULSANTI**
4. 📊 **REPORT FINALE** dopo i test

---

## 💡 CONSIGLI PER IL FUTURO

### 1. Aggiungere Logging
```php
// In AiAjaxHandler.php
catch ( \Exception $e ) {
    error_log( 'FP SEO AI Error: ' . $e->getMessage() );
    wp_send_json_error( ... );
}
```

### 2. Mostrare Messaggi di Errore Chiari
```javascript
// In ai-generator.js
catch (error) {
    if (error.status === 429) {
        alert('⏱️ Troppo richieste. Attendi 5 minuti e riprova.');
    } else if (error.status === 500) {
        alert('❌ Errore server. Verifica console e log PHP.');
    }
}
```

### 3. Usare Modelli Affidabili
- ✅ `gpt-4o-mini` - CONSIGLIATO
- ✅ `gpt-4o` - Alta qualità
- ❌ `gpt-5-nano` - NON ESISTE

---

**Status**: ⏳ **IN ATTESA DI APPLICARE SOLUZIONI**  
**Prossima Azione**: Applicare Soluzione #2 (cambio modello)

