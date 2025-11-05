# 📊 Google Search Console Integration - Guida Completa

## Panoramica

Integrazione **Google Search Console** tramite **Service Account** per mostrare:
- 🖱️ **Clicks** - Click dai risultati di ricerca
- 👁️ **Impressions** - Visualizzazioni in SERP  
- 📈 **CTR** - Click-Through Rate
- 🎯 **Position** - Posizione media in SERP
- 🔍 **Top Queries** - Query che portano traffico

**Metodo**: Service Account (JSON key) - Server-to-server, no OAuth flow

---

## 🚀 Setup Rapido (10 minuti)

### Step 1: Google Cloud Console

1. Vai su: https://console.cloud.google.com
2. **Crea nuovo progetto** o seleziona esistente
3. Menu → **APIs & Services** → **Library**
4. Cerca **"Google Search Console API"**
5. Click **Enable**
6. Cerca **"Indexing API"** (se vuoi instant indexing)
7. Click **Enable**

### Step 2: Service Account

1. Menu → **IAM & Admin** → **Service Accounts**
2. Click **+ Create Service Account**
3. **Nome**: `fp-seo-gsc`
4. **Description**: `FP SEO Plugin GSC Integration`
5. Click **Create and Continue**
6. **Role**: `Service Account User` (o lascia vuoto)
7. Click **Done**

### Step 3: Generate JSON Key

1. Click sul **service account** appena creato
2. Tab **Keys**
3. **Add Key** → **Create new key**
4. **Key type**: JSON
5. Click **Create**
6. ✅ File JSON scaricato (es: `project-id-abc123.json`)

**NON PERDERE QUESTO FILE!**

### Step 4: Add to Search Console

1. **Apri il file JSON** con notepad
2. **Copia** il valore di `"client_email"`
   ```
   Esempio: fp-seo-gsc@project-123456.iam.gserviceaccount.com
   ```
3. Vai su: https://search.google.com/search-console
4. Seleziona la tua **Property**
5. **Settings** (⚙️) → **Users and permissions**
6. Click **Add user**
7. **Email address**: Incolla il `client_email`
8. **Permission level**: **Full** o **Owner**
9. Click **Add**

### Step 5: Configure Plugin

1. WordPress Admin → **Settings → FP SEO → Google Search Console**
2. **Site URL**: `https://tuosito.com/` (ESATTO come in GSC)
3. **Service Account JSON**: 
   - Apri il file JSON
   - Copia **TUTTO** il contenuto
   - Incolla nel campo textarea
4. ✅ **Enable GSC Data**
5. Click **Save Changes**
6. Click **Test Connection**
7. ✅ Vedi "Connection successful!"

---

## ✅ Cosa Ottieni

### Dashboard (SEO Performance → Dashboard)

**Widget "Google Search Console (Last 28 Days)"**:
```
┌──────────────────────────────────────────┐
│ 📊 Google Search Console (Last 28 Days) │
├──────────────────────────────────────────┤
│                                          │
│  🖱️ Clicks      👁️ Impressions          │
│     1,234           45,678               │
│                                          │
│  📈 CTR          🎯 Position             │
│    2.70%            12.3                 │
│                                          │
├──────────────────────────────────────────┤
│ 🏆 Top Performing Pages                 │
│                                          │
│ Page Title         Clicks  Impr  CTR  Pos│
│ Homepage             450  15K   3.0  8.2 │
│ Blog Post ABC        320  12K   2.7  11.4│
│ Product Page         180   8K   2.3  15.1│
└──────────────────────────────────────────┘
```

### Post Editor (Metabox SEO Performance)

**Sezione GSC sotto Recommendations**:
```
┌────────────────────────────────────────┐
│ 📊 Google Search Console (Last 28 Days)│
├────────────────────────────────────────┤
│  Clicks      Impressions   CTR    Pos  │
│    45          1,234      3.64%  12.3  │
├────────────────────────────────────────┤
│ 🔍 Top Queries (5) ▼                   │
│   "wordpress seo plugin"               │
│     12 clicks, pos 8.5                 │
│   "best seo plugin"                    │
│     8 clicks, pos 14.2                 │
└────────────────────────────────────────┘
```

---

## 🔧 File Struttura

```
src/
├── Integrations/
│   ├── GscClient.php          ✅ API client + auth
│   └── GscData.php            ✅ Data fetching + caching
└── Admin/
    ├── GscSettings.php        ✅ Settings tab
    └── GscDashboard.php       ✅ Dashboard widget
```

---

## 📊 Metriche Disponibili

### Site-Wide (Dashboard)
```php
$metrics = $gsc_data->get_site_metrics(28);
// Returns:
[
  'clicks' => 1234,
  'impressions' => 45678,
  'ctr' => 2.70,
  'position' => 12.3,
  'period' => '28 days'
]
```

### Per Post (Metabox)
```php
$metrics = $gsc_data->get_post_metrics($post_id, 28);
// Returns:
[
  'clicks' => 45,
  'impressions' => 1234,
  'ctr' => 3.64,
  'position' => 12.3,
  'queries' => [
    [
      'query' => 'wordpress seo',
      'clicks' => 12,
      'impressions' => 340,
      'ctr' => 3.53,
      'position' => 8.5
    ],
    ...
  ]
]
```

### Top Pages
```php
$pages = $gsc_data->get_top_pages(28, 10);
// Returns array of pages sorted by clicks
```

---

## 🔐 Security & Permissions

### Service Account Permissions

**Minimum Required**:
- Google Search Console API: **Viewer**
- Search Console Property: **Owner** o **Full**

**Recommended**:
- Property: **Full** (accesso completo dati)

### WordPress Permissions

- Settings tab: `manage_options` capability
- Dashboard: Visible to users with access to SEO Performance menu
- Metabox: Visible to editors of the post

### JSON Key Storage

Il file JSON viene salvato in:
- **Database**: `wp_options` table
- **Option**: `fp_seo_performance[gsc][service_account_json]`
- **Security**: Sanitized, no output su frontend
- **Best practice**: Usa WordPress secrets vault in produzione

---

## ⚡ Performance & Caching

### Transient Cache

| Data | Cache Key | TTL |
|------|-----------|-----|
| Site metrics | `fp_seo_gsc_site_metrics_{days}` | 1 hour |
| Post metrics | `fp_seo_gsc_post_{post_id}_{days}` | 1 hour |
| Top pages | `fp_seo_gsc_top_pages_{days}_{limit}` | 1 hour |

### API Quota

Google Search Console API:
- **Quota**: 600 queries/minute
- **Daily limit**: 2000 queries/day (free tier)

**Caching strategy**:
- 1 hour TTL minimizza API calls
- Flush manuale disponibile in Settings
- Auto-flush on demand

### Manual Cache Flush

```
Settings → Google Search Console
→ Click "Flush GSC Cache"
```

---

## 🐛 Troubleshooting

### "Connection failed"

**Possibili cause**:
1. Service Account email non aggiunto a GSC
2. JSON key errato o incompleto
3. Site URL non corrisponde esattamente a GSC property
4. API Search Console non abilitata

**Soluzione**:
```
1. Verifica client_email nel JSON
2. Vai su GSC → Settings → Users
3. Verifica che service account sia presente
4. Permission = Full o Owner
5. Site URL = IDENTICO (con/senza www, con/senza trailing slash)
```

### "No data available"

**Possibili cause**:
1. Sito nuovo (<3 giorni dati in GSC)
2. GSC delay 2-3 giorni
3. Post non ha traffico organico
4. URL non indicizzato

**Soluzione**:
```
- Aspetta 3-4 giorni dopo aggiunta sito a GSC
- Verifica in GSC che ci siano dati
- Prova con post che SAI hanno traffico
```

### "403 Forbidden"

**Causa**: Permessi insufficienti

**Soluzione**:
```
GSC → Settings → Users
→ Service account deve avere "Full" o "Owner"
→ Non "Restricted"
```

### Data non aggiornata

**Causa**: Cache

**Soluzione**:
```
Settings → GSC → Flush GSC Cache
```

---

## 🔒 Best Practices

### Production Environment

1. **Non committare JSON key** in Git
   ```
   # .gitignore
   *-service-account*.json
   ```

2. **Usa WordPress secrets** (se disponibile)
   ```php
   define('FP_SEO_GSC_JSON', '...');
   ```

3. **Rotate keys** ogni 90 giorni

4. **Monitor usage** in Google Cloud Console

5. **Separate service accounts** per staging/production

### Performance

1. **Aumenta cache TTL** per siti grandi
   - Edit `GscData.php` → `CACHE_TTL`
   - Da 3600 (1h) a 7200 (2h) o più

2. **Limita query** per post
   - Top queries: max 10 (già impostato)
   - Riduci se API quota limitato

3. **Disabilita per post types** non necessari
   - GSC data solo per post/page con traffico

---

## 📚 API Reference

### GscClient Methods

```php
// Test connection
$client = new GscClient();
$success = $client->test_connection();

// Get search analytics
$data = $client->get_search_analytics($start_date, $end_date, $limit);

// Get URL analytics
$metrics = $client->get_url_analytics($url, $start_date, $end_date);

// Get top queries for URL
$queries = $client->get_top_queries($url, $start_date, $end_date, $limit);
```

### GscData Methods

```php
$gsc_data = new GscData();

// Site-wide metrics
$metrics = $gsc_data->get_site_metrics(28); // last 28 days

// Post-specific metrics
$metrics = $gsc_data->get_post_metrics($post_id, 28);

// Top performing pages
$pages = $gsc_data->get_top_pages(28, 10);

// Flush all caches
GscData::flush_cache();
```

---

## 🎨 UI Components

### Dashboard Widget

Widget si aggiunge automaticamente dopo Quick Stats se GSC abilitato.

**Hook**:
```php
do_action('fpseo_dashboard_after_quick_stats');
```

### Metabox Section

Sezione si aggiunge in fondo alla metabox SEO se GSC abilitato.

**Rendering**: Automatico in `Metabox::render()`

---

## 🔄 Future Enhancements

### Possibili Miglioramenti

1. **Historical Charts**
   - Grafici clicks/impressions trend
   - Chart.js integration
   - Comparison periodi

2. **Keyword Tracking**
   - Target keywords per post
   - Position tracking nel tempo
   - Alerts su rank changes

3. **Competitor Comparison**
   - SERP overlap analysis
   - Keyword gap identification

4. **Auto Suggestions**
   - Title optimization basata su CTR
   - Meta description A/B testing
   - Content updates basati su query

5. **Advanced Filters**
   - Filter by device (mobile/desktop/tablet)
   - Filter by country
   - Filter by search appearance

---

## 📝 Esempio JSON Key

Il file JSON service account ha questa struttura:

```json
{
  "type": "service_account",
  "project_id": "my-project-123456",
  "private_key_id": "abc123...",
  "private_key": "-----BEGIN PRIVATE KEY-----\nMIIE...\n-----END PRIVATE KEY-----\n",
  "client_email": "fp-seo-gsc@my-project-123456.iam.gserviceaccount.com",
  "client_id": "123456789",
  "auth_uri": "https://accounts.google.com/o/oauth2/auth",
  "token_uri": "https://oauth2.googleapis.com/token",
  "auth_provider_x509_cert_url": "https://www.googleapis.com/oauth2/v1/certs",
  "client_x509_cert_url": "https://www.googleapis.com/robot/v1/metadata/x509/..."
}
```

**Copia TUTTO questo JSON** e incollalo nel campo Settings.

---

## ✅ Checklist Setup

- [ ] Google Cloud project creato
- [ ] Search Console API abilitata
- [ ] Service account creato
- [ ] JSON key scaricato
- [ ] Service account aggiunto a GSC property con permessi Full
- [ ] JSON incollato in Settings → GSC
- [ ] Site URL configurato (esatto match con GSC)
- [ ] GSC enabled checked
- [ ] Settings salvate
- [ ] Test connection = Success
- [ ] Dashboard mostra widget GSC
- [ ] Metabox post mostra metriche

---

## 🎯 Risultato

Dopo setup, avrai:

✅ **Dashboard** con metriche Google reali  
✅ **Post editor** con performance SERP del post specifico  
✅ **Top performing pages** identificate  
✅ **Top queries** per ogni post  
✅ **Cache automatica** per performance  
✅ **No OAuth flow** complesso  

---

## 📞 Support

**Problemi setup?**  
Email: info@francescopasseri.com

**Documentazione Google**:  
https://developers.google.com/webmaster-tools/v1/how-tos/authorizing

**Video tutorial**:  
(Disponibile su richiesta)

---

**Versione**: 0.3.0  
**Feature**: Google Search Console Integration  
**Method**: Service Account (JSON)  
**Status**: ✅ Production Ready

---

**🎉 Ora hai dati Google REALI nel tuo plugin SEO!** 📊✨

