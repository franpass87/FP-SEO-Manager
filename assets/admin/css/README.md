# FP SEO Performance - CSS Architecture

## 📂 Struttura File

```
assets/admin/css/
├── admin.css              # File principale + utilities globali
└── components/
    ├── badge.css          # Badge admin bar
    ├── metabox.css        # Metabox editor post
    ├── bulk-auditor.css   # Pagina bulk audit
    ├── dashboard.css      # Dashboard principale
    └── settings.css       # Pagina settings
```

## 🎨 CSS Variables (Design Tokens)

Tutte le variabili sono definite in `admin.css`:

### Colors
```css
--fp-seo-primary: #2563eb;
--fp-seo-success: #059669;
--fp-seo-warning: #f59e0b;
--fp-seo-danger: #dc2626;
--fp-seo-gray-[50-900]: /* scala completa */
```

### Shadows
```css
--fp-seo-shadow-sm
--fp-seo-shadow
--fp-seo-shadow-md
--fp-seo-shadow-lg
```

### Spacing
```css
--fp-seo-radius: 8px;
```

## 📋 Naming Convention

**BEM (Block Element Modifier)**

```css
.fp-seo-[component]
.fp-seo-[component]__[element]
.fp-seo-[component]--[modifier]
```

### Esempi:
```css
.fp-seo-card
.fp-seo-card__header
.fp-seo-card__title
.fp-seo-badge
.fp-seo-badge--success
.fp-seo-badge--warning
```

## 🧩 Componenti Principali

### 1. Cards
```html
<div class="fp-seo-card">
  <div class="fp-seo-card__header">
    <h3 class="fp-seo-card__title">Title</h3>
  </div>
  <div class="fp-seo-card__body">Content</div>
</div>
```

### 2. Badges
```html
<span class="fp-seo-badge fp-seo-badge--success">Success</span>
<span class="fp-seo-badge fp-seo-badge--warning">Warning</span>
<span class="fp-seo-badge fp-seo-badge--danger">Danger</span>
```

### 3. Stats
```html
<div class="fp-seo-stat fp-seo-stat--success">
  <span class="fp-seo-stat__icon">📊</span>
  <div class="fp-seo-stat__content">
    <span class="fp-seo-stat__label">Label</span>
    <span class="fp-seo-stat__value">100</span>
  </div>
</div>
```

### 4. Buttons
```html
<button class="fp-seo-button fp-seo-button--primary">Primary</button>
<button class="fp-seo-button fp-seo-button--secondary">Secondary</button>
```

### 5. Alerts
```html
<div class="fp-seo-alert fp-seo-alert--info">Info message</div>
<div class="fp-seo-alert fp-seo-alert--success">Success message</div>
<div class="fp-seo-alert fp-seo-alert--warning">Warning message</div>
<div class="fp-seo-alert fp-seo-alert--danger">Danger message</div>
```

### 6. Grid Layouts
```html
<div class="fp-seo-grid fp-seo-grid--2">
  <!-- 2 colonne responsive -->
</div>

<div class="fp-seo-grid fp-seo-grid--3">
  <!-- 3 colonne responsive -->
</div>
```

## 🎯 Modifiche ai Componenti

### Dashboard

**Quick Stats**: 4 card con icone e animazioni hover
```css
.fp-seo-quick-stats
.fp-seo-quick-stat
.fp-seo-quick-stat__icon
.fp-seo-quick-stat__value
.fp-seo-quick-stat__label
```

**Cards Grid**: Layout responsive con cards interattive
```css
.fp-seo-performance-dashboard__grid
.fp-seo-performance-dashboard__card
.fp-seo-performance-dashboard__metrics
```

**Score Badges**: Badge colorati per punteggi
```css
.fp-seo-score-display
.fp-seo-score-display--high (verde)
.fp-seo-score-display--medium (giallo)
.fp-seo-score-display--low (rosso)
```

**Status Badges**: Badge con indicatori circolari
```css
.fp-seo-status-badge
.fp-seo-status-badge--healthy
.fp-seo-status-badge--needs-review
.fp-seo-status-badge--critical
```

### Metabox Editor

**Score Display**: Gradiente dinamico basato su status
```css
.fp-seo-performance-metabox__score
[data-status="green"]  /* verde */
[data-status="yellow"] /* giallo */
[data-status="red"]    /* rosso */
```

**Indicators**: Checks con icone e colori
```css
.fp-seo-performance-indicator
.fp-seo-performance-indicator--pass
.fp-seo-performance-indicator--warn
.fp-seo-performance-indicator--fail
```

### Bulk Auditor

**Filters**: Sezione filtri con background
```css
.fp-seo-performance-bulk__filters
.fp-seo-performance-bulk__filter-group
.fp-seo-performance-bulk__filter-label
```

**Stats Cards**: Metriche in grid
```css
.fp-seo-performance-bulk__stats
.fp-seo-performance-bulk__stat-card
.fp-seo-performance-bulk__stat-card--success
```

### Settings

**Tabs**: Navigation migliorata
```css
.fp-seo-performance-settings .nav-tab
.fp-seo-performance-settings .nav-tab-active
```

**Sections**: Sezioni organizzate
```css
.fp-seo-settings-section
.fp-seo-settings-section__title
.fp-seo-settings-section__description
```

**Toggle Switch**: Switch moderno
```css
.fp-seo-toggle
.fp-seo-toggle__slider
```

## 🎨 Color Usage

### Primary (Blue)
- Buttons primari
- Links
- Focus states
- Tab attivi
- Borders decorativi

### Success (Green)
- Punteggi alti (≥80)
- Status "healthy"
- Checks passati
- Messaggi successo

### Warning (Yellow/Orange)
- Punteggi medi (60-79)
- Status "needs review"
- Checks con warning
- Messaggi attenzione

### Danger (Red)
- Punteggi bassi (<60)
- Status "critical"
- Checks falliti
- Messaggi errore

### Gray Scale
- Testi (700-900)
- Backgrounds (50-100)
- Borders (200-300)
- Disabled states (400-500)

## 🔄 Animations

### Hover Effects
- `transform: translateY(-2px)` su cards
- `transform: translateX(2px)` su list items
- `box-shadow` elevation
- `background-color` changes

### Transitions
```css
transition: all 0.2s ease;  /* Default */
transition: all 0.3s ease;  /* Slower elements */
```

### Loading States
```css
@keyframes fp-seo-spin {
  to { transform: rotate(360deg); }
}
```

## 📱 Responsive Breakpoints

Grid auto-responsive con `minmax()`:
- Cards: `minmax(300px, 1fr)`
- Stats: `minmax(180px, 1fr)`
- Settings: `minmax(280px, 1fr)`

## ♿ Accessibility

- Focus states visibili con outline blu
- Contrast ratio ≥4.5:1 per testi
- ARIA attributes nel markup HTML
- Keyboard navigation supportata

## 🚀 Performance

- **CSS puro**: No JavaScript per animazioni
- **GPU acceleration**: transform e opacity
- **Modular imports**: Caricamento selettivo
- **Variabili CSS**: Cambio colori rapido

## 🔧 Maintenance

### Aggiungere un nuovo componente:
1. Crea `components/nuovo-componente.css`
2. Aggiungi `@import url('components/nuovo-componente.css');` in `admin.css`
3. Usa naming convention BEM: `.fp-seo-nuovo-componente`

### Modificare colori globali:
Edita le variabili in `admin.css` `:root { }`

### Aggiungere utility class:
Aggiungi in `admin.css` nella sezione utilities

---

**Versione**: 1.0  
**Ultimo aggiornamento**: 25 Ottobre 2025  
**Autore**: Francesco Passeri

