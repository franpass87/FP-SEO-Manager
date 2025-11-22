# Contributing to FP SEO Performance

Thank you for considering contributing to FP SEO Performance! 🎉

This document provides guidelines and instructions for contributing to the project.

---

## 📋 Table of Contents

1. [Code of Conduct](#code-of-conduct)
2. [Getting Started](#getting-started)
3. [Development Workflow](#development-workflow)
4. [Coding Standards](#coding-standards)
5. [Testing Requirements](#testing-requirements)
6. [Pull Request Process](#pull-request-process)
7. [Documentation](#documentation)

---

## 🤝 Code of Conduct

### Our Standards

- **Be respectful** - Treat everyone with respect and kindness
- **Be professional** - Maintain a professional and constructive tone
- **Be inclusive** - Welcome contributors of all backgrounds and experience levels
- **Be collaborative** - Work together to improve the project

### Reporting Issues

If you experience or witness unacceptable behavior, please contact: info@francescopasseri.com

---

## 🚀 Getting Started

### Prerequisites

- PHP 8.0 or higher
- WordPress 6.2 or higher
- Composer
- Git
- Basic knowledge of WordPress plugin development

### Setup Development Environment

```bash
# 1. Fork the repository on GitHub

# 2. Clone your fork
git clone https://github.com/YOUR_USERNAME/fp-seo-performance.git
cd fp-seo-performance

# 3. Add upstream remote
git remote add upstream https://github.com/francescopasseri/fp-seo-performance.git

# 4. Install dependencies
composer install

# 5. Install dev dependencies
composer install --dev

# 6. Run tests to verify setup
composer test
```

---

## 🔄 Development Workflow

### 1. Create a Branch

Always create a new branch from `main`:

```bash
# Update main branch
git checkout main
git pull upstream main

# Create feature branch
git checkout -b feature/your-feature-name
# OR
git checkout -b fix/bug-description
# OR
git checkout -b docs/documentation-update
```

### Branch Naming Convention

- `feature/` - New features
- `fix/` - Bug fixes
- `docs/` - Documentation updates
- `refactor/` - Code refactoring
- `test/` - Test additions/updates

### 2. Make Changes

- Write clean, readable code
- Follow [Coding Standards](#coding-standards)
- Add/update tests
- Update documentation

### 3. Commit Changes

Write clear, descriptive commit messages:

```bash
# Good commit messages
git commit -m "feat: Add custom SEO check filter"
git commit -m "fix: Resolve infinite loop in save_post hook"
git commit -m "docs: Update API reference with new hooks"

# Bad commit messages
git commit -m "fix stuff"
git commit -m "update"
```

**Commit Message Format**:
```
<type>: <subject>

[optional body]

[optional footer]
```

**Types**: `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`

### 4. Push and Create Pull Request

```bash
# Push to your fork
git push origin feature/your-feature-name

# Then create PR on GitHub
```

---

## 📝 Coding Standards

### PHP Standards

1. **Follow WordPress Coding Standards**
   - Run `composer phpcs` before committing
   - Fix all warnings and errors

2. **PSR-4 Autoloading**
   - One class per file
   - Namespace matches directory structure
   - Class name matches file name

3. **Type Hints**
   ```php
   public function process( int $post_id, string $content ): array {
       // ...
   }
   ```

4. **Strict Types**
   ```php
   <?php
   declare(strict_types=1);
   ```

5. **PHPDoc Comments**
   ```php
   /**
    * Processes SEO analysis for a post.
    *
    * @param int    $post_id Post ID.
    * @param string $content Post content.
    * @return array{score: int, checks: array}
    * @throws \Exception If analysis fails.
    */
   ```

### Security

- ✅ **Always sanitize input**: `sanitize_text_field()`, `sanitize_textarea_field()`, `esc_url_raw()`
- ✅ **Always escape output**: `esc_html()`, `esc_attr()`, `esc_url()`
- ✅ **Use prepared statements**: `$wpdb->prepare()`
- ✅ **Verify nonces**: `check_ajax_referer()`, `wp_verify_nonce()`
- ✅ **Check capabilities**: `current_user_can()`
- ✅ **Validate and sanitize**: Never trust user input

### Code Quality

- Keep functions/methods under 50 lines
- Keep classes under 300 lines
- Use meaningful variable and function names
- Avoid code duplication (DRY principle)
- Handle errors gracefully with try-catch
- Use the centralized `Logger` class for logging

### Example: Good Code

```php
<?php
declare(strict_types=1);

namespace FP\SEO\Analysis\Checks;

use FP\SEO\Analysis\CheckInterface;
use FP\SEO\Analysis\Context;
use FP\SEO\Analysis\Result;

/**
 * Checks if post has a valid SEO title.
 */
class TitleCheck implements CheckInterface {
    public function run( Context $context ): Result {
        $title = $context->get_title();
        
        if ( empty( $title ) ) {
            return new Result(
                false,
                __( 'Post has no title', 'fp-seo-performance' ),
                [ 'hint' => __( 'Add a descriptive title', 'fp-seo-performance' ) ]
            );
        }
        
        $length = mb_strlen( $title );
        if ( $length > 60 ) {
            return new Result(
                false,
                sprintf( __( 'Title is too long (%d chars)', 'fp-seo-performance' ), $length ),
                [ 'hint' => __( 'Keep title under 60 characters', 'fp-seo-performance' ) ]
            );
        }
        
        return new Result( true, __( 'Title is optimal', 'fp-seo-performance' ) );
    }
    
    public function get_id(): string {
        return 'title_check';
    }
    
    public function get_label(): string {
        return __( 'Title Check', 'fp-seo-performance' );
    }
}
```

---

## 🧪 Testing Requirements

### Running Tests

```bash
# Run all tests
composer test

# Run with coverage
vendor/bin/phpunit --coverage-html coverage/

# Run specific test
vendor/bin/phpunit tests/unit/AnalyzerTest.php
```

### Test Coverage

- Maintain **>80% code coverage**
- Write tests for new features
- Update tests when fixing bugs
- Test edge cases and error conditions

### Test Checklist

- [ ] Unit tests for new classes
- [ ] Integration tests for API calls
- [ ] Test error handling
- [ ] Test edge cases
- [ ] All tests pass
- [ ] Coverage maintained

---

## 🔀 Pull Request Process

### Before Submitting

- [ ] Code follows coding standards (`composer phpcs` passes)
- [ ] All tests pass (`composer test`)
- [ ] No linter errors
- [ ] Documentation updated
- [ ] CHANGELOG.md updated (if applicable)
- [ ] Branch is up to date with `main`

### PR Description Template

```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Documentation update
- [ ] Refactoring
- [ ] Performance improvement

## Testing
How was this tested?

## Checklist
- [ ] Code follows standards
- [ ] Tests added/updated
- [ ] Documentation updated
- [ ] No breaking changes
```

### Review Process

1. Maintainer reviews PR
2. Address any feedback
3. Once approved, maintainer merges
4. Thank you for contributing! 🎉

---

## 📚 Documentation

### When to Update Documentation

- Adding new features → Update README.md and relevant docs
- Adding hooks/filters → Update API_REFERENCE.md
- Changing behavior → Update CHANGELOG.md
- Setup changes → Update installation docs

### Documentation Files

- `README.md` - Main plugin documentation
- `CHANGELOG.md` - Version history
- `docs/DEVELOPER_GUIDE.md` - Developer documentation
- `docs/API_REFERENCE.md` - API reference
- `readme.txt` - WordPress.org readme

---

## 🎯 Areas for Contribution

### Good First Issues

- Documentation improvements
- Test coverage improvements
- Bug fixes
- UI/UX improvements
- Performance optimizations

### Advanced Contributions

- New SEO checks
- AI integration improvements
- GEO feature enhancements
- New integrations
- Architecture improvements

---

## 📞 Questions & Support

### Getting Help

- **Documentation**: Check [docs/](docs/) folder
- **Issues**: Open an issue on GitHub
- **Questions**: Email info@francescopasseri.com

### Resources

- [Developer Guide](docs/DEVELOPER_GUIDE.md)
- [API Reference](docs/API_REFERENCE.md)
- [Best Practices](docs/BEST_PRACTICES.md)
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)

---

## 🙏 Thank You!

Your contributions make FP SEO Performance better for everyone. We appreciate your time and effort!

---

**Last Updated**: 2025-01-27  
**Plugin Version**: 0.9.0-pre.11

