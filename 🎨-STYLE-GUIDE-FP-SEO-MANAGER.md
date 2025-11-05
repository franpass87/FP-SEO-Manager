# 🎨 STYLE GUIDE - FP SEO MANAGER
## Design System & UI Guidelines

**Versione**: 0.9.0-pre.13  
**Data**: 4 Novembre 2025  
**Autore**: Francesco Passeri

---

## 🎯 INTRODUZIONE

Questo Style Guide definisce tutti i token di design, componenti UI e best practices per mantenere **coerenza visiva** nel plugin FP SEO Manager.

---

## 🎨 DESIGN TOKENS

### Colori Principali

```css
/* Primary Colors */
--fp-seo-primary: #2563eb;           /* Blu principale */
--fp-seo-primary-dark: #1d4ed8;      /* Blu scuro (hover) */
--fp-seo-primary-light: #dbeafe;     /* Blu chiaro (background) */

/* Success Colors */
--fp-seo-success: #059669;           /* Verde successo */
--fp-seo-success-dark: #047857;      /* Verde scuro */

/* Warning Colors */
--fp-seo-warning: #f59e0b;           /* Arancione warning */
--fp-seo-warning-dark: #d97706;      /* Arancione scuro */

/* Danger Colors */
--fp-seo-danger: #dc2626;            /* Rosso errore */
--fp-seo-danger-dark: #b91c1c;       /* Rosso scuro */

/* Info Color */
--fp-seo-info: #0ea5e9;              /* Cyan informativo */

/* Grayscale */
--fp-seo-white: #ffffff;
--fp-seo-gray-50: #f9fafb;
--fp-seo-gray-100: #f3f4f6;
--fp-seo-gray-200: #e5e7eb;
--fp-seo-gray-300: #d1d5db;
--fp-seo-gray-400: #9ca3af;
--fp-seo-gray-500: #6b7280;
--fp-seo-gray-600: #4b5563;
--fp-seo-gray-700: #374151;
--fp-seo-gray-800: #1f2937;
--fp-seo-gray-900: #111827;
```

### Uso Colori

| Colore | Quando Usare | Esempio |
|--------|--------------|---------|
| Primary (Blu) | Azioni principali, link, focus | Pulsanti "Salva", link attivi |
| Success (Verde) | Conferme, campi ottimali, badge high impact | Badge "+25%", contatori verdi |
| Warning (Arancione) | Attenzioni, badge medium-high impact | Badge "+20%", warning messages |
| Danger (Rosso) | Errori, campi critici | Errori validazione, contatori rossi |
| Info (Cyan) | Informazioni, sezioni low priority | Badge "+7%", banner info |
| Gray | Elementi secondari, bordi, testo | Testo descrittivo, bordi card |

---

## 📏 SPACING SYSTEM

### Scala Spaziature (base 4px)

```css
--fp-seo-space-1: 0.25rem;   /* 4px  - Micro spacing */
--fp-seo-space-2: 0.5rem;    /* 8px  - Tight spacing */
--fp-seo-space-3: 0.75rem;   /* 12px - Standard spacing */
--fp-seo-space-4: 1rem;      /* 16px - Card padding */
--fp-seo-space-5: 1.25rem;   /* 20px - Section spacing */
--fp-seo-space-6: 1.5rem;    /* 24px - Large spacing */
--fp-seo-space-8: 2rem;      /* 32px - Extra large */
--fp-seo-space-10: 2.5rem;   /* 40px - Huge */
```

### Uso Spacing

| Spacing | Uso | Esempio |
|---------|-----|---------|
| `space-1` (4px) | Badge interno, micro gap | Padding badge |
| `space-2` (8px) | Gap tight, margini piccoli | Gap tra icona e testo |
| `space-3` (12px) | **DEFAULT spacing** | Gap button group, margin campi |
| `space-4` (16px) | Card padding, gap sezioni | Padding card, gap grid |
| `space-5` (20px) | Padding generoso | Padding hero section |
| `space-6` (24px) | Margin sezioni | Margin-bottom tra sezioni |

**Regola d'oro**: Usare SEMPRE le variabili, mai valori hard-coded!

---

## 🔤 TYPOGRAPHY

### Font Sizes

```css
--fp-seo-font-size-xs: 0.75rem;    /* 12px - Caption, note */
--fp-seo-font-size-sm: 0.875rem;   /* 14px - Standard UI text */
--fp-seo-font-size-base: 1rem;     /* 16px - Body text */
--fp-seo-font-size-lg: 1.125rem;   /* 18px - Subtitle */
--fp-seo-font-size-xl: 1.25rem;    /* 20px - Heading 4 */
--fp-seo-font-size-2xl: 1.5rem;    /* 24px - Heading 3 */
--fp-seo-font-size-3xl: 1.875rem;  /* 30px - Heading 2 */
```

### Font Family

```css
--fp-seo-font-family: -apple-system, BlinkMacSystemFont, 
                       "Segoe UI", Roboto, "Helvetica Neue", 
                       Arial, sans-serif;
```

### Uso Typography

| Size | Quando Usare | Esempio |
|------|--------------|---------|
| `xs` (12px) | Note, caption, contatori | "0/60", tooltip text |
| `sm` (14px) | **Testo UI standard** | Label, descrizioni, badge |
| `base` (16px) | Body text, contenuti | Paragrafi, testo principale |
| `lg` (18px) | Subtitle, label grandi | Subtitle card |
| `xl-3xl` | Headings | H4, H3, H2 |

---

## 🔲 BORDER RADIUS

### Scala Border Radius (base 4px)

```css
--fp-seo-radius-sm: 4px;      /* Piccoli elementi */
--fp-seo-radius: 8px;         /* DEFAULT (alias md) */
--fp-seo-radius-md: 8px;      /* Standard */
--fp-seo-radius-lg: 12px;     /* Card grandi */
--fp-seo-radius-xl: 16px;     /* Modali */
--fp-seo-radius-2xl: 20px;    /* Hero sections */
--fp-seo-radius-full: 9999px; /* Pill shape (badge) */
```

### Uso Border Radius

| Radius | Quando Usare | Esempio |
|--------|--------------|---------|
| `sm` (4px) | Badge piccoli, inline code | `<code>` tags |
| `md` (8px) | **DEFAULT** - Input, button, card | Tutti gli input |
| `lg` (12px) | Card grandi, sezioni | Metabox sections |
| `xl` (16px) | Modali, dialog | Pop-up, lightbox |
| `full` (9999px) | Badge pill, contatori | Badge "+15%", pill buttons |

---

## 🌓 SHADOWS

### Scala Shadows

```css
--fp-seo-shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
--fp-seo-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
--fp-seo-shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
--fp-seo-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
```

### Uso Shadows

| Shadow | Quando Usare |
|--------|--------------|
| `sm` | Card flat, elementi discreti |
| **`default`** | **Card standard** (usa questo di default) |
| `md` | Card hover, elementi elevated |
| `lg` | Modali, dropdown, tooltip |

---

## 🎯 COMPONENTI UI

### Buttons

#### Classi Base:
```css
.fp-seo-btn                  /* Button base */
.fp-seo-btn-primary          /* Blu (azione principale) */
.fp-seo-btn-secondary        /* Grigio (azione secondaria) */
.fp-seo-btn-success          /* Verde (conferma) */
.fp-seo-btn-warning          /* Arancione (attenzione) */
.fp-seo-btn-danger           /* Rosso (elimina) */
```

#### Varianti Size:
```css
.fp-seo-btn-sm              /* Small (min-height: 2rem) */
.fp-seo-btn                 /* Default (min-height: 2.5rem) */
.fp-seo-btn-lg              /* Large (min-height: 3rem) */
```

#### Button Groups:
```html
<div class="fp-seo-btn-group">
  <button class="fp-seo-btn fp-seo-btn-primary">Salva</button>
  <button class="fp-seo-btn fp-seo-btn-secondary">Annulla</button>
</div>
```

**Spacing automatico**: `gap: var(--fp-seo-space-3)` = 12px

---

### Cards

```html
<div class="fp-seo-card">
  <div class="fp-seo-card-header">
    <h3>Titolo Card</h3>
  </div>
  <div class="fp-seo-card-body">
    Contenuto...
  </div>
  <div class="fp-seo-card-footer">
    <button class="fp-seo-btn">Azione</button>
  </div>
</div>
```

**Padding**: Header/Body/Footer = `var(--fp-seo-space-6)` = 24px

---

### Badges

```html
<span class="fp-seo-badge fp-seo-badge-success">+15%</span>
<span class="fp-seo-badge fp-seo-badge-warning">+20%</span>
<span class="fp-seo-badge fp-seo-badge-primary">Nuovo</span>
```

#### Varianti:
- `fp-seo-badge-primary` - Blu
- `fp-seo-badge-success` - Verde
- `fp-seo-badge-warning` - Arancione
- `fp-seo-badge-danger` - Rosso
- `fp-seo-badge-secondary` - Grigio

---

### Alerts

```html
<div class="fp-seo-alert fp-seo-alert-success">
  Operazione completata!
</div>
```

#### Varianti:
- `fp-seo-alert-success` - Verde (conferma)
- `fp-seo-alert-warning` - Giallo (attenzione)
- `fp-seo-alert-danger` - Rosso (errore)
- `fp-seo-alert-info` - Blu (informazione)

---

## 📊 PATTERN UI SPECIFICI

### Badge Impatto SEO

```html
<span style="display: inline-flex; padding: 2px 8px; 
              background: linear-gradient(135deg, #10b981 0%, #059669 100%); 
              color: var(--fp-seo-white); 
              border-radius: 999px; 
              font-size: 10px; font-weight: 700; 
              box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);">
  +15%
</span>
```

#### Colori Badge per Impatto:
- **+20-40%**: Verde (#10b981) - Massima priorità
- **+15-19%**: Arancione (#f59e0b) - Alta priorità
- **+10-14%**: Blu (#3b82f6) - Media priorità
- **+5-9%**: Grigio (#6b7280) - Bassa priorità

---

### Contatori Caratteri

```html
<span id="counter" style="font-size: 12px; font-weight: 600; color: #6b7280;">
  0/60
</span>
```

#### Validazione Colori:
- ⚫ **Grigio** (#6b7280): Ancora da ottimizzare
- 🟢 **Verde** (#10b981): Ottimale!
- 🟠 **Arancione** (#f59e0b): Attenzione
- 🔴 **Rosso** (#ef4444): Troppo lungo!

---

### Banner Informativi

```html
<div style="padding: 12px; background: #f0fdf4; 
            border-radius: 6px; border-left: 3px solid #10b981;">
  <strong style="color: #059669;">💡 Titolo</strong><br>
  Testo esplicativo...
</div>
```

#### Colori Banner:
- **Verde** (#f0fdf4 + border #10b981): Info positive, SERP
- **Giallo** (#fffbeb + border #f59e0b): Attenzioni, AI
- **Blu** (#eff6ff + border #3b82f6): Info generali, HowTo
- **Viola** (#f5f3ff + border #8b5cf6): Social Media
- **Cyan** (#ecfeff + border #06b6d4): Internal Links

---

## 📱 RESPONSIVE DESIGN

### Breakpoints

```css
/* Mobile First */
@media (max-width: 480px) {
  /* Smartphone */
  .fp-seo-container { padding: 0 var(--fp-seo-space-2); }
}

@media (max-width: 768px) {
  /* Tablet */
  .fp-seo-grid-2, .fp-seo-grid-3 { grid-template-columns: 1fr; }
  .fp-seo-btn { width: 100%; }
}

@media (min-width: 1200px) {
  /* Desktop Large */
  .fp-seo-container { max-width: 1200px; }
}
```

---

## ✅ BEST PRACTICES

### DO ✅

```css
/* ✅ SEMPRE usare variabili CSS */
padding: var(--fp-seo-space-4);
color: var(--fp-seo-white);
border-radius: var(--fp-seo-radius-md);

/* ✅ Usare classi utility */
<div class="fp-seo-btn-group">
  <button class="fp-seo-btn fp-seo-btn-primary">Pulsante</button>
</div>

/* ✅ Consistenza nei contatori */
<span id="counter">0/60</span>
```

### DON'T ❌

```css
/* ❌ MAI valori hard-coded */
padding: 12px;
color: #fff;
border-radius: 8px;

/* ❌ MAI inline styles se esiste classe */
<button style="padding: 10px;">Pulsante</button>

/* ❌ MAI mix di unità */
margin: 12px var(--fp-seo-space-4);  /* NO! */
```

---

## 🎨 EMOJI SYSTEM

### Emoji Standardizzate

| Emoji | Significato | Uso |
|-------|-------------|-----|
| 🎯 | Target/SERP | Sezioni prioritarie |
| 📝 | SEO Title | Campo titolo |
| 📄 | Meta Description | Campo description |
| 🔗 | Link/Slug | URL, link interni |
| 📋 | Excerpt/Riassunto | Campo riassunto |
| 🔑 | Focus Keyword | Keyword principale |
| 🔐 | Secondary Keywords | Keyword secondarie |
| 🤖 | AI/Automation | Funzioni AI |
| ❓ | FAQ | FAQ Schema |
| 📖 | HowTo/Tutorial | HowTo Schema |
| 📱 | Social Media | Social preview |
| ⚡ | High Impact | Badge alto impatto |
| 🚀 | Very High Impact | Badge impatto massimo |
| 📊 | Medium Impact | Badge impatto medio |
| 💡 | Tip/Info | Suggerimenti |
| ✅ | Success/Done | Conferma, completato |
| ⚠️ | Warning | Attenzione |
| ❌ | Error/Fail | Errore, fallimento |

---

## 🎨 TRANSITION & ANIMATION

### Standard Transitions

```css
--fp-seo-transition: all 0.2s ease-in-out;        /* DEFAULT */
--fp-seo-transition-fast: all 0.15s ease-in-out;  /* Quick */
--fp-seo-transition-slow: all 0.3s ease-in-out;   /* Slow */
```

### Uso:
- **Fast** (0.15s): Hover stati, micro-interazioni
- **Default** (0.2s): **Usa questo di default**
- **Slow** (0.3s): Slide, fade, animazioni complesse

---

## 📐 LAYOUT PATTERNS

### Grid System

```html
<!-- 2 colonne -->
<div class="fp-seo-grid fp-seo-grid-2">
  <div>Colonna 1</div>
  <div>Colonna 2</div>
</div>

<!-- 3 colonne -->
<div class="fp-seo-grid fp-seo-grid-3">
  <div>Col 1</div>
  <div>Col 2</div>
  <div>Col 3</div>
</div>
```

**Gap**: Automatico `var(--fp-seo-space-6)` = 24px

### Flexbox Utilities

```html
<div class="fp-seo-flex fp-seo-items-center fp-seo-justify-between">
  <span>Testo</span>
  <button>Azione</button>
</div>
```

---

## 🎯 ESEMPI PRATICI

### Sezione con Badge Impatto

```html
<div class="fp-seo-performance-metabox__section" style="border-left: 4px solid #10b981;">
  <h4 style="display: flex; justify-content: space-between; align-items: center;">
    <span style="display: flex; align-items: center; gap: 8px;">
      <span>🎯</span>
      SERP Optimization
    </span>
    <span style="display: inline-flex; padding: 2px 8px; 
                  background: linear-gradient(135deg, #10b981 0%, #059669 100%); 
                  color: var(--fp-seo-white); 
                  border-radius: 999px; 
                  font-size: 10px; font-weight: 700;">
      ⚡ Impact: +25%
    </span>
  </h4>
</div>
```

### Campo con Contatore

```html
<div>
  <label style="display: flex; justify-content: space-between;">
    <span>📝 SEO Title</span>
    <span id="title-counter">0/60</span>
  </label>
  <input 
    type="text" 
    id="seo-title"
    maxlength="70"
    style="width: 100%; padding: 10px 14px; 
           border: 2px solid #10b981; 
           border-radius: var(--fp-seo-radius-md);"
  />
</div>

<script>
const field = document.getElementById('seo-title');
const counter = document.getElementById('title-counter');

field.addEventListener('input', () => {
  const len = field.value.length;
  counter.textContent = len + '/60';
  
  if (len >= 50 && len <= 60) counter.style.color = '#10b981';
  else if (len > 60) counter.style.color = '#ef4444';
  else counter.style.color = '#6b7280';
});
</script>
```

---

## 📊 CHECKLIST PRE-COMMIT

Prima di committare codice CSS, verifica:

- [ ] Tutti i colori usano variabili (`var(--fp-seo-*)`)
- [ ] Tutti gli spacing usano variabili (no `12px` hard-coded)
- [ ] Tutti i border-radius usano variabili
- [ ] Tutti i font-size usano variabili
- [ ] Le transition usano le variabili standard
- [ ] I badge hanno colori coerenti con l'impatto
- [ ] Le emoji seguono lo standard definito
- [ ] Il codice non ha duplicazioni
- [ ] Il CSS è organizzato per componente
- [ ] I commenti sono chiari e utili

---

## 🚀 QUICK REFERENCE

### Sostituzioni Comuni:

```css
/* PRIMA (❌ EVITARE) */
color: #fff;
padding: 12px;
margin: 16px;
gap: 8px;
border-radius: 6px;
font-size: 14px;

/* DOPO (✅ CORRETTO) */
color: var(--fp-seo-white);
padding: var(--fp-seo-space-3);
margin: var(--fp-seo-space-4);
gap: var(--fp-seo-space-2);
border-radius: var(--fp-seo-radius-md);
font-size: var(--fp-seo-font-size-sm);
```

---

**Usa questo Style Guide come riferimento per mantenere coerenza visiva in tutto il plugin!** 🎨✨

