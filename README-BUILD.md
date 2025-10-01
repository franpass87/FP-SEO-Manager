# Build & Release Guide

## Prerequisites

- PHP 8.2 (compatible with PHP 8.0+) with the `zip` extension enabled
- Composer 2
- Bash shell with `rsync` and `zip`

## Local build workflow

```bash
bash build.sh --bump=patch
```

or set an explicit version:

```bash
bash build.sh --set-version=1.2.3
```

The script will install dependencies without dev packages, optimise the autoloader, stage runtime files in `build/fp-seo-performance/`, and produce a timestamped ZIP archive inside `build/`.

The output will report the final version and the generated ZIP path.

## GitHub Action release

Create and push a tag following the `v*` pattern (for example `v1.2.3`). The `build-plugin-zip` workflow builds the plugin with the same excludes used locally and uploads the ZIP as an artifact named `plugin-zip`.
