# ✅ Azioni Immediate - FP SEO Performance

**Data:** 8 Ottobre 2025  
**Obiettivo:** Quick wins implementabili subito

---

## 🚨 Questa Settimana (Effort: 2-3 giorni)

### 1. Miglioramenti Sicurezza & Codice
**Tempo totale:** ~5 ore

#### A. Chiarire Commenti PHPCS (2h)
**File:** `src/Admin/BulkAuditPage.php:332`

```php
// Prima (confuso):
$selected = isset( $_POST['post_ids'] ) ? (array) wp_unslash( $_POST['post_ids'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.

// Dopo (chiaro):
// Nonce già verificato alla riga 323 tramite check_admin_referer().
// phpcs:ignore WordPress.Security.NonceVerification.Missing
$selected = isset( $_POST['post_ids'] ) ? (array) wp_unslash( $_POST['post_ids'] ) : array();
```

#### B. TTL Cache PSI Configurabile (3h)
**File:** `src/Perf/Signals.php:191-192`

**Aggiungere in Options:**
```php
// src/Utils/Options.php - aggiungi in defaults
'performance' => [
    'psi_cache_ttl' => 86400, // 1 giorno default
    // ... resto opzioni
]
```

**Modificare Signals.php:**
```php
private function get_cache_duration(): int {
    $options = Options::get();
    $ttl = $options['performance']['psi_cache_ttl'] ?? 86400;
    return (int) $ttl;
}
```

**Aggiungere UI in PerformanceTabRenderer.php:**
```html
<tr>
    <th scope="row">
        <label for="psi_cache_ttl">
            <?php esc_html_e( 'PageSpeed Insights cache duration', 'fp-seo-performance' ); ?>
        </label>
    </th>
    <td>
        <select name="fp_seo_performance[performance][psi_cache_ttl]" id="psi_cache_ttl">
            <option value="3600" <?php selected( $psi_cache_ttl, 3600 ); ?>>1 ora</option>
            <option value="21600" <?php selected( $psi_cache_ttl, 21600 ); ?>>6 ore</option>
            <option value="86400" <?php selected( $psi_cache_ttl, 86400 ); ?>>1 giorno</option>
            <option value="604800" <?php selected( $psi_cache_ttl, 604800 ); ?>>1 settimana</option>
        </select>
    </td>
</tr>
```

---

### 2. Documentazione Sviluppatori (4h)

#### Aggiungere a `docs/EXTENDING.md`:

**Esempio 1: Check Custom Complesso**
```php
/**
 * Esempio: Check per video embedding
 */
class VideoEmbedCheck implements CheckInterface {
    
    public function id(): string {
        return 'video_embed';
    }
    
    public function label(): string {
        return __( 'Video Embedding', 'my-domain' );
    }
    
    public function description(): string {
        return __( 'Verifica presenza e ottimizzazione video embedded.', 'my-domain' );
    }
    
    public function run( Context $context ): Result {
        $content = $context->content();
        
        // Cerca iframe video (YouTube, Vimeo, etc.)
        preg_match_all( '/<iframe[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches );
        
        $video_count = 0;
        $optimized_count = 0;
        
        foreach ( $matches[0] as $index => $iframe ) {
            $src = $matches[1][$index];
            
            if ( $this->is_video_embed( $src ) ) {
                $video_count++;
                
                // Verifica lazy loading
                if ( strpos( $iframe, 'loading="lazy"' ) !== false ) {
                    $optimized_count++;
                }
            }
        }
        
        if ( $video_count === 0 ) {
            return new Result(
                Result::STATUS_PASS,
                array( 'video_count' => 0 ),
                __( 'Nessun video rilevato.', 'my-domain' ),
                0.05
            );
        }
        
        $optimization_rate = ( $optimized_count / $video_count ) * 100;
        
        if ( $optimization_rate >= 80 ) {
            $status = Result::STATUS_PASS;
            $hint = __( 'Video correttamente ottimizzati.', 'my-domain' );
        } elseif ( $optimization_rate >= 50 ) {
            $status = Result::STATUS_WARN;
            $hint = __( 'Alcuni video potrebbero beneficiare di lazy loading.', 'my-domain' );
        } else {
            $status = Result::STATUS_FAIL;
            $hint = __( 'Aggiungi loading="lazy" ai video per migliorare performance.', 'my-domain' );
        }
        
        return new Result(
            $status,
            array(
                'video_count' => $video_count,
                'optimized_count' => $optimized_count,
                'optimization_rate' => round( $optimization_rate, 1 )
            ),
            $hint,
            0.05
        );
    }
    
    private function is_video_embed( string $src ): bool {
        $video_platforms = array(
            'youtube.com',
            'youtu.be',
            'vimeo.com',
            'dailymotion.com',
            'wistia.com'
        );
        
        foreach ( $video_platforms as $platform ) {
            if ( strpos( $src, $platform ) !== false ) {
                return true;
            }
        }
        
        return false;
    }
}

// Registrazione del check
add_filter( 'fp_seo_perf_checks_enabled', function( $checks, $context ) {
    $checks[] = new VideoEmbedCheck();
    return $checks;
}, 10, 2 );
```

**Esempio 2: Tab Renderer Custom**
```php
/**
 * Esempio: Tab custom per impostazioni avanzate AI
 */
class AITabRenderer implements SettingsTabRenderer {
    
    public function render(): void {
        $options = Options::get();
        $ai_settings = $options['ai'] ?? array();
        $openai_key = $ai_settings['openai_api_key'] ?? '';
        $auto_optimize = (bool) ( $ai_settings['auto_optimize'] ?? false );
        ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="openai_api_key">
                        <?php esc_html_e( 'OpenAI API Key', 'my-domain' ); ?>
                    </label>
                </th>
                <td>
                    <input 
                        type="password" 
                        name="fp_seo_performance[ai][openai_api_key]" 
                        id="openai_api_key" 
                        value="<?php echo esc_attr( $openai_key ); ?>"
                        class="regular-text"
                    />
                    <p class="description">
                        <?php esc_html_e( 'Inserisci la tua API key per funzionalità AI.', 'my-domain' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <?php esc_html_e( 'Auto-ottimizzazione', 'my-domain' ); ?>
                </th>
                <td>
                    <label>
                        <input 
                            type="checkbox" 
                            name="fp_seo_performance[ai][auto_optimize]" 
                            value="1"
                            <?php checked( $auto_optimize ); ?>
                        />
                        <?php esc_html_e( 'Ottimizza automaticamente con AI', 'my-domain' ); ?>
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }
}

// Registrazione tab
add_filter( 'fp_seo_performance_settings_tabs', function( $tabs ) {
    $tabs['ai'] = array(
        'label' => __( 'AI Settings', 'my-domain' ),
        'renderer' => new AITabRenderer()
    );
    return $tabs;
} );
```

---

## 📅 Prossima Settimana (Effort: 1 settimana)

### 3. Filtri Avanzati Bulk Auditor

**File:** `src/Admin/BulkAuditPage.php`

#### A. Aggiungere Filtri UI (render method)
```html
<!-- Dopo i filtri esistenti, aggiungere: -->
<label for="fp-seo-performance-filter-score">
    <span class="screen-reader-text"><?php esc_html_e( 'Filter by score', 'fp-seo-performance' ); ?></span>
    <select name="score_range" id="fp-seo-performance-filter-score">
        <option value="all"><?php esc_html_e( 'All scores', 'fp-seo-performance' ); ?></option>
        <option value="0-60"><?php esc_html_e( 'Poor (0-60)', 'fp-seo-performance' ); ?></option>
        <option value="60-80"><?php esc_html_e( 'Fair (60-80)', 'fp-seo-performance' ); ?></option>
        <option value="80-100"><?php esc_html_e( 'Good (80-100)', 'fp-seo-performance' ); ?></option>
        <option value="never"><?php esc_html_e( 'Never analyzed', 'fp-seo-performance' ); ?></option>
    </select>
</label>
```

#### B. Modificare query_posts per supportare filtri
```php
private function query_posts( string $post_type, string $status, string $score_range = 'all' ): array {
    // ... query esistente
    
    // Filtrare post-query per score
    if ( 'never' === $score_range ) {
        $posts = array_filter( $posts, function( $post ) use ( $results ) {
            return ! isset( $results[ $post->ID ] );
        });
    } elseif ( 'all' !== $score_range ) {
        list( $min, $max ) = explode( '-', $score_range );
        $posts = array_filter( $posts, function( $post ) use ( $results, $min, $max ) {
            $score = $results[ $post->ID ]['score'] ?? null;
            return $score !== null && $score >= $min && $score <= $max;
        });
    }
    
    return $posts;
}
```

#### C. Aggiungere Ordinamento Tabella (JavaScript)
```javascript
// In assets/admin/js/modules/bulk-auditor/ui.js
export function initTableSorting() {
    const headers = document.querySelectorAll('[data-fp-seo-bulk-sortable]');
    
    headers.forEach(header => {
        header.style.cursor = 'pointer';
        header.addEventListener('click', () => {
            const column = header.dataset.fpSeoBulkSortable;
            sortTable(column);
        });
    });
}

function sortTable(column) {
    const tbody = document.querySelector('[data-fp-seo-bulk-tbody]');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
        const aVal = a.querySelector(`[data-fp-seo-bulk-${column}]`)?.textContent || '';
        const bVal = b.querySelector(`[data-fp-seo-bulk-${column}]`)?.textContent || '';
        
        // Numeric comparison per score
        if (column === 'score') {
            return parseInt(bVal) - parseInt(aVal);
        }
        
        return aVal.localeCompare(bVal);
    });
    
    rows.forEach(row => tbody.appendChild(row));
}
```

---

## 🧪 Testing (Ongoing)

### 4. Setup Testing JavaScript (3-4h)

#### A. Installare Jest
```bash
npm install --save-dev jest @wordpress/jest-preset-default
```

#### B. Configurare package.json
```json
{
  "scripts": {
    "test:js": "jest",
    "test:js:watch": "jest --watch"
  },
  "jest": {
    "preset": "@wordpress/jest-preset-default",
    "testMatch": [
      "**/assets/**/*.test.js"
    ]
  }
}
```

#### C. Primo Test (esempio)
**File:** `assets/admin/js/modules/bulk-auditor/api.test.js`
```javascript
import { analyzeBatch } from './api';

describe('Bulk Auditor API', () => {
    test('analyzeBatch rejects without config', async () => {
        await expect(analyzeBatch({}, [1, 2, 3]))
            .rejects
            .toThrow('Missing configuration');
    });
    
    test('analyzeBatch builds correct FormData', async () => {
        const mockFetch = jest.fn(() => 
            Promise.resolve({
                json: () => Promise.resolve({
                    success: true,
                    data: { results: [] }
                })
            })
        );
        global.fetch = mockFetch;
        
        const config = {
            ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
            action: 'test_action',
            nonce: 'test_nonce'
        };
        
        await analyzeBatch(config, [1, 2, 3]);
        
        expect(mockFetch).toHaveBeenCalledWith(
            config.ajaxUrl,
            expect.objectContaining({
                method: 'POST',
                credentials: 'same-origin'
            })
        );
    });
});
```

#### D. Aggiungere al CI/CD
```yaml
# .github/workflows/test.yml (se usi GitHub Actions)
- name: Run JavaScript tests
  run: npm run test:js
```

---

## 📊 Metriche di Successo

### Questa Settimana
- ✅ 3 miglioramenti codice implementati
- ✅ 2 esempi docs aggiunti
- ✅ 0 regressioni introdotte

### Prossima Settimana
- ✅ Filtri avanzati funzionanti
- ✅ Ordinamento tabella implementato
- ✅ Setup testing JS completato
- ✅ Primi 3 test JavaScript passanti

---

## 🎯 Dopo Queste Azioni

**Avrai:**
- ✅ Codice più pulito e documentato
- ✅ Cache configurabile dagli utenti
- ✅ Filtri potenti nel Bulk Auditor
- ✅ Foundation per testing JS
- ✅ Esempi per sviluppatori terzi

**Potrai:**
- 🚀 Iniziare features più grandi (Dashboard, Real-time)
- 📈 Migliore developer experience
- 🧪 Test suite robusto per CI/CD
- 👥 Onboarding più facile per contributor

---

## 📝 Checklist Finale

### Pre-Implementation
- [ ] Review questo documento
- [ ] Setup branch feature/quick-wins
- [ ] Backup database (se test su produzione)

### Implementation
- [ ] Migliorare commenti PHPCS
- [ ] Implementare TTL configurabile
- [ ] Aggiungere esempi docs
- [ ] Implementare filtri avanzati
- [ ] Setup Jest e primi test

### Post-Implementation
- [ ] Test manuale tutte le modifiche
- [ ] Verificare nessuna regressione
- [ ] Update CHANGELOG.md
- [ ] Commit con messaggi descrittivi
- [ ] PR review da Francesco

---

**🎉 Buon lavoro!**

Per domande: info@francescopasseri.com  
Documento: v1.0 - 2025-10-08
