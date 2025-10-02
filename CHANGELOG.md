# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.2] - 2025-10-01
### Added
- Centralized admin menu registration for analyzer screens and bulk auditing tools.
- Post type helper utilities and unit coverage to support analyzer surfaces.

### Changed
- Shared analyzer constants across the admin bar badge, metabox, and bulk auditor for consistent scoring.
- Hardened metabox hook argument handling to avoid incorrect score lookups.

### Fixed
- Decoded percent-encoded hosts before PageSpeed Insights requests to prevent API errors.

## [0.1.1] - 2025-10-01
### Added
- Expanded analyzer heuristics for metadata, canonical URLs, and structured data checks.

### Fixed
- Adjusted analyzer defaults to improve scoring reliability across content types.

## [0.1.0] - 2025-09-30
### Added
- Initial scaffold of the FP SEO Performance plugin with analyzer, settings, and tooling foundations.

[Unreleased]: https://github.com/franpass87/FP-SEO-Manager/compare/v0.1.2...HEAD
[0.1.2]: https://github.com/franpass87/FP-SEO-Manager/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/franpass87/FP-SEO-Manager/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/franpass87/FP-SEO-Manager/releases/tag/v0.1.0
