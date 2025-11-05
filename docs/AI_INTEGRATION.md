# 🤖 Integrazione AI - Generazione Automatica Contenuti SEO

## Panoramica

Il plugin FP SEO Performance ora include l'integrazione con OpenAI per generare automaticamente contenuti SEO ottimizzati con l'intelligenza artificiale.

## Funzionalità

Con un solo click, l'AI può generare:

- ✅ **Titolo SEO** ottimizzato (max 60 caratteri)
- ✅ **Meta Description** accattivante (max 155 caratteri)
- ✅ **Slug URL** ottimizzato per i motori di ricerca
- ✅ **Focus Keyword** principale del contenuto

## Configurazione

### 1. Ottieni una API Key OpenAI

1. Vai su [OpenAI Platform](https://platform.openai.com/api-keys)
2. Crea un nuovo account o accedi
3. Genera una nuova API key
4. Copia la chiave (inizia con `sk-...`)

### 2. Configura il Plugin

1. Nel pannello WordPress, vai su **FP SEO Performance** > **Settings**
2. Clicca sul tab **AI**
3. Incolla la tua API Key nel campo **API Key OpenAI**
4. Scegli il modello AI (default: **GPT-5 Nano ⚡** - il più veloce ed economico)
5. Configura le preferenze di generazione:
   - ✓ Abilita generazione automatica SEO
   - ✓ Priorità alle keyword nel contenuto
   - ✓ Ottimizza per Click-Through Rate (CTR)
6. Clicca su **Salva modifiche**

### 3. Modelli AI Disponibili

| Modello | Descrizione | Velocità | Qualità | Costo | Consigliato |
|---------|-------------|----------|---------|-------|-------------|
| **GPT-5 Nano** ⚡ | Velocissimo ed efficiente | ⚡⚡⚡⚡⚡ | ⭐⭐⭐⭐ | $ | ✅ **Default** |
| **GPT-5 Mini** | Ottimizzato bilanciato | ⚡⚡⚡⚡ | ⭐⭐⭐⭐⭐ | $$ | Per progetti standard |
| **GPT-5** | Qualità massima | ⚡⚡⚡ | ⭐⭐⭐⭐⭐ | $$$ | Per contenuti premium |
| **GPT-5 Pro** | Enterprise level | ⚡⚡ | ⭐⭐⭐⭐⭐ | $$$$ | Per grandi volumi |
| GPT-4o Mini | Legacy ottimizzato | ⚡⚡⚡ | ⭐⭐⭐ | $ | Legacy |
| GPT-4o | Legacy potente | ⚡⚡ | ⭐⭐⭐⭐ | $$$ | Legacy |
| GPT-3.5 Turbo | Obsoleto | ⚡⚡⚡⚡ | ⭐⭐ | $ | Non consigliato |

## Utilizzo

### Nell'Editor Post/Pagine

1. Apri un post o una pagina in modifica
2. Scrivi il contenuto (almeno un paragrafo)
3. Nel metabox **SEO Performance**, troverai la sezione **🤖 Generazione AI - Contenuti SEO**
4. Clicca sul pulsante **Genera con AI**
5. Attendi qualche secondo mentre l'AI analizza il contenuto
6. Rivedi i suggerimenti generati
7. Clicca su **Applica questi suggerimenti** per utilizzarli

### Funzioni Disponibili

#### 🔄 Genera con AI
Avvia l'analisi del contenuto e genera i suggerimenti SEO.

#### ✅ Applica questi suggerimenti
Applica automaticamente i suggerimenti ai campi del post:
- Titolo SEO → Titolo del post
- Slug → Permalink del post

#### 📋 Copia negli appunti
Copia tutti i suggerimenti negli appunti per usarli manualmente.

## Best Practices

### Per Migliori Risultati

1. **Contenuto Completo**: Scrivi almeno 200-300 parole prima di generare
2. **Titolo Provvisorio**: Inserisci un titolo provvisorio per dare contesto all'AI
3. **Revisione**: Rivedi sempre i suggerimenti prima di applicarli
4. **Personalizzazione**: Adatta i suggerimenti al tuo stile e brand

### Quando Usare l'AI

✅ **Ideale per:**
- Articoli blog
- Pagine prodotto
- Landing pages
- Guide e tutorial
- News e comunicati

❌ **Meno adatto per:**
- Contenuti molto brevi (< 100 parole)
- Contenuti altamente tecnici o di nicchia
- Pagine senza contenuto testuale

## Costi e Limitazioni

### Costi OpenAI

- I costi dipendono dal modello scelto
- **GPT-5 Nano**: ~$0.10 per 1M token (input) / $0.40 per 1M token (output) ⚡
- GPT-5 Mini: ~$0.15 per 1M token (input) / $0.60 per 1M token (output)
- GPT-5: ~$2.50 per 1M token (input) / $10.00 per 1M token (output)
- Un post medio con GPT-5 Nano costa circa **$0.0005-0.002** (meno di 1 centesimo!)
- [Pricing ufficiale OpenAI](https://openai.com/pricing)

### Limitazioni

- **Rate Limits**: OpenAI ha limiti di richieste al minuto (dipende dal piano)
- **Token Limits**: Il contenuto viene troncato a 2000 caratteri per l'analisi
- **Lingua**: Funziona meglio con italiano e inglese

## Risoluzione Problemi

### "API Key non configurata"
➜ Vai in Settings > AI e inserisci la tua API Key OpenAI

### "Errore OpenAI: 429"
➜ Hai superato il rate limit. Attendi qualche minuto e riprova.

### "Errore OpenAI: 401"
➜ API Key non valida. Verifica che sia corretta e attiva.

### "Nessuna risposta ricevuta da OpenAI"
➜ Controlla la connessione internet e riprova.

### Il pulsante non appare
➜ Verifica che:
- L'API Key sia configurata
- "Abilita generazione automatica SEO" sia attivo nelle impostazioni
- Stai modificando un post/pagina supportato

## Sicurezza e Privacy

- ✅ L'API Key è salvata in modo sicuro nel database WordPress
- ✅ Solo gli utenti con permessi di modifica post possono usare l'AI
- ✅ Il contenuto viene inviato a OpenAI solo quando richiesto
- ⚠️ OpenAI potrebbe utilizzare i dati per migliorare i propri modelli (vedi [Privacy Policy OpenAI](https://openai.com/privacy))

## Supporto

Per problemi o domande:
- 📧 Email: info@francescopasseri.com
- 🌐 Website: [francescopasseri.com](https://francescopasseri.com)

## Changelog

### v0.4.1 (2025-10-25)
- ✨ Aggiunta integrazione OpenAI
- ✨ Generazione automatica titolo SEO, meta description, slug
- ✨ Nuovo tab AI nelle impostazioni
- ✨ Supporto per GPT-4o, GPT-4 Turbo, GPT-3.5 Turbo

---

**Sviluppato con ❤️ da Francesco Passeri**

