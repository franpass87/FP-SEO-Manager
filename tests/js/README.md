# JavaScript Testing Guide

Questa directory contiene i test JavaScript per il plugin FP SEO Performance.

## Setup

### Installare dipendenze

```bash
npm install
```

Questo installerà:
- **Jest**: Framework di testing
- **Babel**: Per transpilare ES6+ modules
- **jest-environment-jsdom**: Per simulare il DOM del browser

## Eseguire i Test

### Tutti i test
```bash
npm run test:js
```

### Watch mode (auto-re-run on changes)
```bash
npm run test:js:watch
```

### Con coverage report
```bash
npm run test:js:coverage
```

## Struttura Test

### Test Files Location
I test files seguono il pattern `*.test.js` e sono collocati:
- `assets/admin/js/**/*.test.js` - Test accanto ai moduli
- `tests/js/**/*.test.js` - Test integrazione (directory separata)

### Convenzioni Naming

```javascript
// Nome file: <module-name>.test.js
// Esempio: api.test.js, state.test.js

describe('Module Name', () => {
    describe('functionName', () => {
        test('does something specific', () => {
            // test code
        });
    });
});
```

## Coverage

Il coverage report viene generato in `coverage/js/` e include:
- **Text summary**: stampato in console
- **LCOV**: per integrazione CI/CD
- **HTML**: report navigabile in `coverage/js/index.html`

### Target Coverage
- **Lines**: 80%+
- **Branches**: 75%+
- **Functions**: 80%+
- **Statements**: 80%+

## Test Esistenti

### 1. Bulk Auditor API (`api.test.js`)
Test per chiamate AJAX e processamento in chunks:
- `analyzeBatch()` - Chiamate API
- `processInChunks()` - Elaborazione batch

### 2. Bulk Auditor State (`state.test.js`)
Test per gestione stato:
- `BulkAuditorState` class
- Selection management
- Busy state tracking

### 3. DOM Utils (`dom-utils.test.js`)
Test per utility DOM:
- `clearList()` - Rimozione children
- `createElement()` - Creazione elementi
- `closest()` - Traversal DOM

## Scrivere Nuovi Test

### Template Base

```javascript
/**
 * Tests for [Module Name]
 * 
 * @package FP\SEO
 */

import { functionToTest } from './module';

describe('Module Name', () => {
    
    beforeEach(() => {
        // Setup before each test
    });

    afterEach(() => {
        // Cleanup after each test
    });

    describe('functionName', () => {
        
        test('should do something', () => {
            const result = functionToTest();
            expect(result).toBe(expectedValue);
        });

        test('should handle error case', () => {
            expect(() => {
                functionToTest(invalidInput);
            }).toThrow();
        });
    });
});
```

### Best Practices

1. **Organize by function/method**: Un `describe` per ogni funzione
2. **Test comportamenti, non implementazione**: Testa cosa fa, non come
3. **Use descriptive test names**: Chiaro cosa testa e cosa ci si aspetta
4. **One assertion focus per test**: Idealmente 1-3 assertions correlate
5. **Setup e cleanup**: Usa beforeEach/afterEach per setup/teardown

### Testing DOM

```javascript
beforeEach(() => {
    document.body.innerHTML = `
        <div id="test-container">
            <button id="test-button">Click</button>
        </div>
    `;
});

test('clicking button does something', () => {
    const button = document.getElementById('test-button');
    button.click();
    // assertions
});
```

### Mocking

```javascript
// Mock global fetch
global.fetch = jest.fn(() =>
    Promise.resolve({
        json: () => Promise.resolve({ data: 'test' })
    })
);

// Mock console methods
const consoleSpy = jest.spyOn(console, 'error').mockImplementation();

// Restore mock
consoleSpy.mockRestore();
```

### Async Testing

```javascript
test('async function works', async () => {
    const result = await asyncFunction();
    expect(result).toBe(expected);
});

test('promise rejects correctly', async () => {
    await expect(asyncFunction()).rejects.toThrow('Error message');
});
```

## CI/CD Integration

### GitHub Actions Example

```yaml
name: JavaScript Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup Node.js
        uses: actions/setup-node@v3
        with:
          node-version: '18'
      
      - name: Install dependencies
        run: npm install
      
      - name: Run tests
        run: npm run test:js:coverage
      
      - name: Upload coverage
        uses: codecov/codecov-action@v3
        with:
          directory: ./coverage/js
```

## Troubleshooting

### Jest non trova i moduli
Verifica che `.babelrc` sia configurato correttamente:
```json
{
  "presets": [
    ["@babel/preset-env", { "targets": { "node": "current" } }]
  ]
}
```

### DOM non disponibile
Assicurati che `testEnvironment: "jsdom"` sia in `package.json`:
```json
{
  "jest": {
    "testEnvironment": "jsdom"
  }
}
```

### Import/Export errors
Jest usa Babel per transpilare. Assicurati di usare sintassi ES6:
```javascript
// ✅ Corretto
export function myFunction() { }
import { myFunction } from './module';

// ❌ Da evitare in moduli
module.exports = myFunction;
const myFunction = require('./module');
```

## Risorse

- [Jest Documentation](https://jestjs.io/docs/getting-started)
- [Testing Library](https://testing-library.com/)
- [Jest DOM Matchers](https://github.com/testing-library/jest-dom)
- [JavaScript Testing Best Practices](https://github.com/goldbergyoni/javascript-testing-best-practices)

## Supporto

Per domande o problemi con i test:
- Email: info@francescopasseri.com
- Documentazione: `docs/EXTENDING.md`
