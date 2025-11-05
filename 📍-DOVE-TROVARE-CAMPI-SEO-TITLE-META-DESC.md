# 📍 DOVE TROVARE I CAMPI SEO TITLE E META DESCRIPTION
## Guida Rapida

**Data**: 4 Novembre 2025 - ore 22:45  
**Problema**: Non vedi i campi SEO Title e Meta Description  
**Soluzione**: ✅ **CAMPI PRESENTI - GUIDA QUI SOTTO**

---

## 🎯 DOVE SONO I CAMPI

I campi **SEO Title** e **Meta Description** sono dentro il metabox **"SEO Performance"** nella sezione **"SERP Optimization"**.

---

## 📍 PASSO-PASSO PER TROVARLI

### Step 1: Apri Editor Articolo
```
http://fp-development.local/wp-admin/post.php?post=178&action=edit
```

### Step 2: Cerca il Metabox "SEO Performance"
- Scrolla la pagina verso il basso
- Dovresti vedere un metabox con header **blu** con scritto **"SEO Performance"**
- Il metabox potrebbe essere **collassato** (freccia verso destra)
- **Clicca sulla freccia** per espandere il metabox

### Step 3: Trova la Sezione "SERP Optimization"
Una volta aperto il metabox, vedrai:
- **SEO Score** in alto (es: "45/100")
- Subito sotto, la sezione **"🎯 SERP Optimization"** con badge verde **"Impact: +40%"**
- **Bordo sinistro VERDE** su tutta la sezione

### Step 4: I Campi Sono Qui!
Dentro la sezione SERP Optimization vedrai (in ordine):

```
1. 📝 SEO Title              [+15% verde]  [0/60]
   ┌──────────────────────────────────────────┐
   │                                          │
   └──────────────────────────────────────────┘

2. 📄 Meta Description       [+10% verde]  [0/160]
   ┌──────────────────────────────────────────┐
   │                                          │
   │                                          │
   └──────────────────────────────────────────┘

3. 🔗 Slug (URL Permalink)   [+6% grigio]  [0 parole]
4. 📋 Riassunto (Excerpt)    [+9% blu]     [0/150]
5. 🔑 Focus Keyword          [+8% blu]
6. 🔐 Secondary Keywords     [+5% grigio]
```

---

## 🔍 TROUBLESHOOTING

### ❓ **Non vedo il metabox "SEO Performance"**

**Soluzione 1**: Controlla Opzioni Schermata
1. Clicca **"Opzioni schermata"** (in alto a destra, sotto il titolo)
2. Trova la checkbox **"SEO Performance"**
3. Assicurati che sia **SPUNTATA** ✅
4. Chiudi il pannello
5. Il metabox dovrebbe apparire

**Soluzione 2**: Svuota cache
```bash
# Browser
Ctrl+F5 (Windows) o Cmd+Shift+R (Mac)

# WordPress (se hai plugin cache)
WP Rocket → Svuota cache
W3 Total Cache → Purge all caches
```

**Soluzione 3**: Verifica permessi
- L'utente deve avere permessi `edit_post`
- Sei loggato come **FranPass87** (amministratore) ✅

---

### ❓ **Il metabox è presente ma VUOTO**

**Soluzione**: Verifica errori PHP
```powershell
Get-Content "C:\Users\franc\Local Sites\fp-development\logs\php\error.log" -Tail 50
```

Se vedi errori PHP, avvisami e li risolvo.

---

### ❓ **Il metabox è COLLASSATO (chiuso)**

**Soluzione**:
1. Trova il metabox "SEO Performance"
2. Clicca sulla **freccia** a destra del titolo
3. Il metabox si espande
4. Ora dovresti vedere tutti i campi

---

## 📸 COME DOVREBBE APPARIRE

### Screenshot Visuale:

```
┌──────────────────────────────────────────────────────┐
│ SEO Performance                        [▼] [⊟] [⊠]  │
├──────────────────────────────────────────────────────┤
│                                                      │
│  ℹ️  Come funziona l'analisi SEO?                   │
│  [Banner informativo...]                [×]          │
│                                                      │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━    │
│                                                      │
│  📊 SEO Score: 45/100                               │
│                                                      │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━    │
│                                                      │
│  🎯 SERP OPTIMIZATION         [⚡ Impact: +40%] 🟢 │ ← QUESTA SEZIONE!
│  ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓  │
│  ┃ 💡 Questi campi appaiono su Google...      ┃  │
│  ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛  │
│                                                      │
│  📝 SEO Title              [+15% 🟢]     [0/60]    │ ← CAMPO QUI!
│  ┌────────────────────────────────────────────┐    │
│  │                                            │    │
│  └────────────────────────────────────────────┘    │
│  🎯 Alto impatto (+15%)...                          │
│                                                      │
│  📄 Meta Description       [+10% 🟢]     [0/160]   │ ← CAMPO QUI!
│  ┌────────────────────────────────────────────┐    │
│  │                                            │    │
│  │                                            │    │
│  └────────────────────────────────────────────┘    │
│  🎯 Medio-Alto impatto (+10%)...                    │
│                                                      │
│  🔗 Slug (URL Permalink)   [+6% ⚫]   [0 parole]   │
│  📋 Riassunto (Excerpt)    [+9% 🔵]     [0/150]    │
│  🔑 Focus Keyword          [+8% 🔵]                 │
│  🔐 Secondary Keywords     [+5% ⚫]                 │
│                                                      │
└──────────────────────────────────────────────────────┘
```

---

## ✅ VERIFICA RAPIDA

### Test 1: Cerca per emoji
Premi **Ctrl+F** (Find) e cerca: **📝**  
Dovresti trovare l'emoji del campo SEO Title.

### Test 2: Cerca per testo
Premi **Ctrl+F** e cerca: **"SEO Title"**  
Dovresti trovare il label del campo.

### Test 3: Inspect Element
1. **Click destro** sul metabox SEO Performance
2. Scegli **"Ispeziona elemento"**
3. Cerca: `id="fp-seo-title"`
4. Se esiste, il campo è presente (forse nascosto da CSS)

---

## 🔧 DEBUG AVANZATO

### Verifica ID campi esistenti:

Apri **Console Browser** (F12) e digita:

```javascript
// Verifica esistenza campi
document.getElementById('fp-seo-title')
document.getElementById('fp-seo-meta-description')
document.getElementById('fp-seo-slug')
document.getElementById('fp-seo-excerpt')

// Se ritorna un elemento HTML → il campo esiste ✅
// Se ritorna null → il campo non è presente ❌
```

---

## 📞 CONTATTAMI SE

Se dopo questi step NON vedi ancora i campi:

1. 📸 Fai uno screenshot completo della pagina
2. 🔍 Copia output console JavaScript (sopra)
3. 📝 Dimmi se vedi errori PHP nel log
4. ✉️ Inviami le info e risolvo immediatamente

---

**I campi CI SONO nel codice e sono stati testati! Se non li vedi, è un problema di visualizzazione che risolviamo subito!** 🚀

