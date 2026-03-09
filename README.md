# FP SEO Performance

[![Version](https://img.shields.io/badge/version-0.9.0--pre.11-blue.svg)](https://github.com/francescopasseri/fp-seo-performance)
[![WordPress](https://img.shields.io/badge/wordpress-6.2+-green.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/php-8.0+-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0+-red.svg)](LICENSE)
[![AI Powered](https://img.shields.io/badge/AI-GPT--5%20Nano-orange.svg)](https://openai.com/)
[![Security](https://img.shields.io/badge/security-audited-success.svg)](QA-REPORT-PROFONDO-2025.md)
[![Quality](https://img.shields.io/badge/quality-★★★★★-brightgreen.svg)](QA-REPORT-PROFONDO-2025.md)

**FP SEO Performance** is a comprehensive WordPress plugin that provides **AI-powered SEO content generation with GPT-5 Nano**, on-page SEO analysis, Generative Engine Optimization (GEO), Google Search Console integration, and advanced SEO tools for content creators and SEO professionals.

---

## 🚀 Features

### 🤖 **AI-Powered Content Generation** ⚡ NEW!
- **GPT-5 Nano Integration** - Lightning-fast, cost-effective AI generation
- **One-Click SEO Generation** - Generate SEO title, meta description, slug, and focus keyword instantly
- **Smart Context Analysis** - AI analyzes categories, tags, post type, and excerpt for better results
- **Focus Keyword Targeting** - Optional keyword input for precise optimization
- **Character Limit Enforcement** - Strict validation (60 chars title, 155 chars meta)
- **Real-time Counters** - Color-coded character counters (🟢🟠🔴)
- **Multi-Model Support** - GPT-5 Nano/Mini/Pro, GPT-4o (legacy), GPT-3.5 Turbo
- **Cost Effective** - ~$0.001 per generation with GPT-5 Nano
- **Apply with One Click** - Auto-populate title and slug
- **Copy to Clipboard** - Easy manual application

### ✅ **Core SEO Analysis**
- **Real-time On-page Analysis** - 15+ configurable SEO checks
- **Bulk Audit System** - Analyze multiple posts simultaneously
- **SEO Score Tracking** - Historical performance tracking
- **Admin Bar Badge** - Quick SEO status at a glance
- **Site Health Integration** - WordPress Site Health checks

### 🤖 **GEO (Generative Engine Optimization)**
- **AI.txt Support** - Define AI crawling policies (`/.well-known/ai.txt`)
- **GEO Sitemap** - Dedicated sitemap for AI engines (`/geo-sitemap.xml`)
- **Structured Content** - JSON endpoints for LLM consumption
  - `/geo/site.json` - Site-level metadata
  - `/geo/content/{id}.json` - Per-post structured data
  - `/geo/updates.json` - Recent updates feed
- **Claims Editor** - Manage factual claims with evidence
- **Semantic Shortcodes** - `[fp_claim]`, `[fp_citation]`, `[fp_faq]`
- **Extended JSON-LD** - ClaimReview, CreativeWork schemas

### 📊 **Google Search Console Integration**
- **Service Account Authentication** - Server-to-server GSC connection
- **Site-wide Metrics** - Clicks, impressions, CTR, avg. position
- **Per-post Metrics** - Individual content performance tracking
- **Top Queries Dashboard** - Most performing search queries
- **Dashboard Widget** - Quick GSC overview in WordPress admin

### ⚡ **Instant Indexing**
- **Auto-submit to Google** - Automatic URL submission on publish/update
- **Google Indexing API** - Direct integration with Google's Indexing API
- **URL_UPDATED / URL_DELETED** - Proper notification types
- **Error Logging** - Track submission success/failures

### 📈 **Advanced Features**
- **Score History** - Database-backed SEO score tracking over time
- **Internal Linking Suggestions** - AI-powered link recommendations
- **Real-time SERP Preview** - Live Google search preview in editor
- **Content Optimization** - Keyword density, readability checks
- **Meta Management** - Title, description, focus keyword
- **Test Suite** - Built-in automated testing (51 tests)
- **Developer Tools** - Comprehensive debugging and validation

### 🎨 **Modern UI**
- Clean, gradient-based design system
- Responsive admin interface
- Real-time visual feedback
- Inline CSS (cache-proof)
- Accessible components
- Dark mode compatible
- Color-coded indicators for instant feedback

### ⚡ **Performance Optimized**
- **Multi-tier Caching** - Redis, Memcached, WordPress Object Cache support
- **Query Optimization** - Reduced database queries with smart caching
- **Memory Management** - Automatic cleanup of expired transients
- **Lazy Loading** - Services loaded only when needed
- **Meta Preloading** - SEO meta fields preloaded for faster access
- **Performance Monitoring** - Built-in metrics and performance score
- **Database Optimization** - Table optimization and index management tools

---

## 📦 Installation

### Requirements
- **WordPress**: 6.2 or higher
- **PHP**: 8.0 or higher
- **Composer**: For dependency management

### Method 1: Manual Installation

1. **Download** the plugin:
   ```bash
   git clone https://github.com/francescopasseri/fp-seo-performance.git
   cd fp-seo-performance
   ```

2. **Install dependencies**:
   ```bash
   composer install --no-dev
   ```

3. **Upload** to WordPress:
   - Copy the plugin folder to `wp-content/plugins/`
   - Or upload as ZIP via WordPress admin

4. **Activate**:
   - Go to **Plugins → Installed Plugins**
   - Click **Activate** on "FP SEO Performance"

5. **Flush Permalinks**:
   - Go to **Settings → Permalinks**
   - Click **Save Changes** (activates GEO endpoints)

### Method 2: Composer (Advanced)

Add to your `composer.json`:
```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/francescopasseri/fp-seo-performance"
    }
  ],
  "require": {
    "fp/fp-seo-performance": "^0.4"
  }
}
```

Then run:
```bash
composer install
```

---

## ⚙️ Configuration

### 🤖 AI Setup (Quick Start - 2 Minutes)

1. **Get OpenAI API Key**:
   - Visit [OpenAI Platform](https://platform.openai.com/api-keys)
   - Create account or sign in
   - Click "Create new secret key"
   - Copy the key (starts with `sk-`)

2. **Configure Plugin**:
   - Go to **FP SEO Performance → Settings → AI**
   - Paste your API key in "API Key OpenAI" field
   - Verify model: **GPT-5 Nano ⚡** (recommended - already selected)
   - Ensure all checkboxes are enabled ✓
   - Click **Save Changes**
   - You should see "✓ API Key configurata correttamente" (green)

3. **Start Generating**:
   - Open any post/page in editor
   - Scroll to "SEO Performance" metabox
   - Find "🤖 Generazione AI - Contenuti SEO" section
   - (Optional) Enter focus keyword: e.g., "SEO WordPress"
   - Click **"Genera con AI"**
   - Wait 3-5 seconds
   - Review generated content with character counts
   - Click **"Applica questi suggerimenti"**
   - Save your post

📖 **Complete AI Guide**: See [AI Integration Guide](docs/AI_INTEGRATION.md)

### Basic Setup

1. **Configure SEO Checks**:
   - Go to **Settings → FP SEO → Analysis**
   - Enable/disable specific checks
   - Adjust scoring weights

2. **Set Analyzable Post Types**:
   - Go to **Settings → FP SEO → General**
   - Select which post types to analyze

### Google Search Console Setup

1. **Create Google Cloud Project**:
   - Visit [Google Cloud Console](https://console.cloud.google.com)
   - Create new project or select existing

2. **Enable APIs**:
   - Enable **Google Search Console API**
   - Enable **Web Search Indexing API** (for instant indexing)

3. **Create Service Account**:
   - Go to **IAM & Admin → Service Accounts**
   - Create service account
   - Download JSON key

4. **Add to Search Console**:
   - Copy service account email from JSON
   - Add as **Owner** in [Google Search Console](https://search.google.com/search-console)

5. **Configure Plugin**:
   - Go to **Settings → FP SEO → Google Search Console**
   - Paste entire JSON key
   - Enter site URL (e.g., `https://yoursite.com/`)
   - Enable GSC Data
   - Enable Auto-indexing (optional)
   - Click **Test Connection**

📖 **Detailed guide**: See [GSC Integration Guide](docs/GSC_INTEGRATION.md)

### GEO Configuration

1. **Enable GEO**:
   - Go to **Settings → FP SEO → GEO**
   - Configure AI crawling policies
   - Set content freshness settings

2. **Add Claims to Posts**:
   - Edit any post/page
   - Find "FP GEO Claims" metabox
   - Add claims with evidence

3. **Use Shortcodes**:
   ```
   [fp_claim evidence_url="https://source.com"]Your claim here[/fp_claim]
   [fp_citation url="https://source.com" author="Name"]Quote[/fp_citation]
   [fp_faq question="How to...?"]Answer here[/fp_faq]
   ```

---

## 📚 Documentation

> 📖 **Complete Documentation Index**: See [DOCUMENTATION_INDEX.md](DOCUMENTATION_INDEX.md) for full navigation

### 🚀 Quick Start
- **[📚 Documentation Index](DOCUMENTATION_INDEX.md)** ⭐ **NEW** - Complete documentation navigation
- **[Quick Start Guide](QUICK-START-AI-FIRST.md)** - Get started in 5 minutes
- **[Configuration Guide](#-configuration)** - Setup AI, GSC, and GEO

### 👨‍💻 Developer Resources ⭐ **UPDATED**
- **[Developer Guide](docs/DEVELOPER_GUIDE.md)** ⭐ **NEW** - Complete development guide
- **[API Reference](docs/API_REFERENCE.md)** ⭐ **NEW** - All hooks, filters, and classes
- **[Contributing Guide](CONTRIBUTING.md)** ⭐ **UPDATED** - How to contribute
- **[Extending Guide](docs/EXTENDING.md)** - How to extend the plugin
- **[Best Practices](docs/BEST_PRACTICES.md)** - Coding standards
- **[Architecture](docs/architecture.md)** - Technical overview

### 🤖 AI & Integration
- **[AI Integration](docs/AI_INTEGRATION.md)** - Complete AI features guide
- **[GSC Integration](docs/GSC_INTEGRATION.md)** - Google Search Console setup
- **[Indexing API](docs/INDEXING_API_SETUP.md)** - Instant indexing configuration

### ⚡ Performance & Optimization
- **[Performance Optimization Guide](docs/PERFORMANCE_OPTIMIZATION.md)** ⭐ **NEW** - Complete performance guide
  - Database query optimization
  - Cache system configuration
  - Memory management
  - Performance monitoring
  - Troubleshooting guide

### 🧪 Testing & Quality
- **[QA Report](QA-REPORT-PROFONDO-2025.md)** ⭐ **NEW** - Deep quality assurance (Security, Performance, Best Practices)
- **[Quick Test Guide](QUICK_TEST_GUIDE.md)** - 5-minute functionality test
- **[Test Checklist](TEST_CHECKLIST.md)** - Complete manual testing (70+ tests)
- **Test Suite** - Built-in: FP SEO Performance → Test Suite

### 📋 Reference
- **[Changelog](CHANGELOG.md)** ⭐ **UPDATED** - Complete version history with latest QA improvements
- **[FAQ](docs/faq.md)** - Frequently asked questions
- **[Project Overview](docs/overview.md)** - General plugin overview

---

## 🧪 Testing

Run the automated test suite:

**URL**: `http://yoursite.local/wp-content/plugins/FP-SEO-Manager/test-all-features.php`

**Tests Include**:
- ✅ Composer autoload verification
- ✅ Google API Client library
- ✅ All class existence checks
- ✅ Database table verification
- ✅ GEO endpoint accessibility
- ✅ Hook registration
- ✅ Functional tests

**Expected**: 90%+ success rate

---

## 🔧 Development

### Local Setup

1. Clone repository:
   ```bash
   git clone https://github.com/francescopasseri/fp-seo-performance.git
   cd fp-seo-performance
   ```

2. Install all dependencies (including dev):
   ```bash
   composer install
   ```

3. Run tests:
   ```bash
   composer test
   ```

4. Check code standards:
   ```bash
   composer phpcs
   ```

5. Static analysis:
   ```bash
   composer phpstan
   ```

### Project Structure

```
fp-seo-performance/
├── assets/                 # Frontend assets
│   └── admin/
│       ├── css/           # Stylesheets
│       └── js/            # JavaScript modules
├── docs/                  # Documentation
├── src/                   # PHP source files (PSR-4)
│   ├── Admin/            # Admin UI components
│   ├── Analysis/         # SEO analysis engine
│   ├── Editor/           # Post editor integration
│   ├── Front/            # Frontend features
│   ├── GEO/              # GEO implementation
│   ├── History/          # Score tracking
│   ├── Infrastructure/   # Core plugin bootstrap
│   ├── Integrations/     # External API integrations
│   ├── Linking/          # Internal linking
│   ├── Scoring/          # Scoring engine
│   ├── Shortcodes/       # Shortcode handlers
│   ├── SiteHealth/       # WordPress Site Health
│   └── Utils/            # Utility classes
├── tests/                # PHPUnit tests
├── vendor/               # Composer dependencies (gitignored)
├── composer.json         # Composer configuration
├── fp-seo-performance.php # Main plugin file
└── README.md            # This file
```

---

## 🤝 Contributing

Contributions are welcome! Please follow these guidelines:

1. **Fork** the repository
2. **Create** a feature branch (`git checkout -b feature/amazing-feature`)
3. **Commit** your changes (`git commit -m 'Add amazing feature'`)
4. **Push** to branch (`git push origin feature/amazing-feature`)
5. **Open** a Pull Request

### Coding Standards

- Follow **WordPress Coding Standards**
- Use **PSR-4** autoloading
- Add **PHPDoc** comments
- Write **unit tests** for new features
- Run `composer phpcs` before committing

---

## 📄 License

This plugin is licensed under the **GPL-2.0-or-later** license.

See [LICENSE](LICENSE) for more information.

---

## 🙏 Credits

**Author**: [Francesco Passeri](https://francescopasseri.com)  
**Website**: [francescopasseri.com](https://francescopasseri.com)  
**Email**: info@francescopasseri.com

### Dependencies

- [Google API PHP Client](https://github.com/googleapis/google-api-php-client) - Apache 2.0 License

---

## 📞 Support

- **Issues**: [GitHub Issues](https://github.com/francescopasseri/fp-seo-performance/issues)
- **Discussions**: [GitHub Discussions](https://github.com/francescopasseri/fp-seo-performance/discussions)
- **Email**: info@francescopasseri.com
- **Website**: [francescopasseri.com](https://francescopasseri.com)

---

## 🗺️ Roadmap

### v0.9.0 (Current - Pre-Release)
- ✅ AI-powered content generation with GPT-5 Nano
- ✅ Comprehensive GEO optimization
- ✅ Google Search Console integration
- ✅ Instant indexing API
- ✅ Security hardening and QA improvements
- ✅ Centralized logging system
- 🔄 Final testing and polish

### v0.10.0 (Planned)
- [ ] Schema.org generator UI
- [ ] Advanced keyword tracking dashboard
- [ ] Content gap analysis
- [ ] Enhanced AI content suggestions
- [ ] Performance monitoring dashboard

### v1.0.0 (Future)
- [ ] Multi-language support
- [ ] WooCommerce integration
- [ ] Custom report builder
- [ ] REST API for third-party integrations
- [ ] Advanced analytics and insights

---

## ⭐ Show Your Support

If you find this plugin helpful, please:
- ⭐ **Star** this repository
- 🐛 **Report bugs** via GitHub Issues
- 💡 **Suggest features** via GitHub Discussions
- 📢 **Share** with the WordPress community
- ☕ **Support development** via [Buy Me a Coffee](https://buymeacoffee.com/francescopasseri)

---

**Made with ❤️ by [Francesco Passeri](https://francescopasseri.com)**
---

## Autore

**Francesco Passeri**
- Sito: [francescopasseri.com](https://francescopasseri.com)
- Email: [info@francescopasseri.com](mailto:info@francescopasseri.com)
- GitHub: [github.com/franpass87](https://github.com/franpass87)
