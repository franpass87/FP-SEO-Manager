# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
### Changed
- **[Refactoring]** Extracted metadata resolution logic into dedicated `MetadataResolver` utility class, eliminating ~112 lines of duplicated code across `BulkAuditPage`, `Metabox`, and `AdminBarBadge`.
- **[Refactoring]** Simplified `Analyzer` by extracting check filtering logic into new `CheckRegistry` class, reducing complexity by ~70 lines.
- **[Refactoring]** Modularized `SettingsPage` by creating dedicated renderer classes for each settings tab (`GeneralTabRenderer`, `AnalysisTabRenderer`, `PerformanceTabRenderer`, `AdvancedTabRenderer`), reducing main class from 465 to ~170 lines.

### Added
- New `src/Utils/MetadataResolver` utility class for centralized SEO metadata resolution.
- New `src/Analysis/CheckRegistry` class for managing analyzer check filtering.
- New settings tab renderer architecture under `src/Admin/Settings/` namespace.

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
