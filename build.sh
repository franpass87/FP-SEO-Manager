#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TOOLS_DIR="$ROOT_DIR/tools"

find_plugin_file() {
    local file
    while IFS= read -r -d '' file; do
        if grep -qi "^\s*\*\s*Plugin Name:" "$file"; then
            echo "$(basename "$file")"
            return 0
        fi
    done < <(find "$ROOT_DIR" -maxdepth 1 -type f -name '*.php' -print0)

    echo "Unable to locate main plugin file." >&2
    return 1
}

PLUGIN_FILE="$(find_plugin_file)"
if [[ -z "$PLUGIN_FILE" ]]; then
    echo "Failed to identify plugin file." >&2
    exit 1
fi

PLUGIN_SLUG="${PLUGIN_FILE%.php}"

SET_VERSION=""
BUMP_TYPE="patch"
ZIP_NAME=""

usage() {
    cat <<'EOF'
Usage: bash build.sh [options]

Options:
  --set-version=X.Y.Z  Set the plugin version explicitly.
  --bump=TYPE          Bump the plugin version (patch, minor, major). Default: patch.
  --zip-name=NAME      Custom name for the output zip file (without path).
  -h, --help           Show this help message.
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --set-version=*)
            SET_VERSION="${1#*=}"
            shift
            ;;
        --bump=*)
            BUMP_TYPE="${1#*=}"
            shift
            ;;
        --zip-name=*)
            ZIP_NAME="${1#*=}"
            shift
            ;;
        --help|-h)
            usage
            exit 0
            ;;
        *)
            echo "Unknown option: $1" >&2
            usage >&2
            exit 1
            ;;
    esac
done

NEW_VERSION=""

if [[ -n "$SET_VERSION" ]]; then
    NEW_VERSION="$(php "$TOOLS_DIR/bump-version.php" --set-version="$SET_VERSION")"
elif [[ -n "$BUMP_TYPE" ]]; then
    case "$BUMP_TYPE" in
        major|minor|patch)
            ;;
        *)
            echo "Invalid bump type: $BUMP_TYPE" >&2
            exit 1
            ;;
    esac
    NEW_VERSION="$(php "$TOOLS_DIR/bump-version.php" --bump="$BUMP_TYPE")"
else
    NEW_VERSION="$(php "$TOOLS_DIR/bump-version.php" --bump=patch)"
fi

if [[ -z "$NEW_VERSION" ]]; then
    echo "Unable to determine new version." >&2
    exit 1
fi

echo "Using version: $NEW_VERSION"

composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
composer dump-autoload -o --classmap-authoritative

BUILD_ROOT="$ROOT_DIR/build"
TARGET_DIR="$BUILD_ROOT/$PLUGIN_SLUG"
rm -rf "$TARGET_DIR"
mkdir -p "$TARGET_DIR"

RSYNC_EXCLUDES=(
    "--exclude=.git/"
    "--exclude=.github/"
    "--exclude=tests/"
    "--exclude=docs/"
    "--exclude=node_modules/"
    "--exclude=*.md"
    "--exclude=.idea/"
    "--exclude=.vscode/"
    "--exclude=build/"
    "--exclude=.gitattributes"
    "--exclude=.gitignore"
    "--exclude=.codex-state.json"
    "--exclude=build.sh"
    "--exclude=tools/"
    "--exclude=composer.json"
    "--exclude=composer.lock"
    "--exclude=phpcs.xml"
    "--exclude=phpstan.neon"
    "--exclude=phpstan-bootstrap.php"
    "--exclude=phpunit.xml.dist"
    "--exclude=rector.php"
    "--exclude=README-BUILD.md"
    "--exclude=README.md"
)

rsync -a --delete "${RSYNC_EXCLUDES[@]}" "$ROOT_DIR/" "$TARGET_DIR/"

TIMESTAMP="$(date +"%Y%m%d%H%M")"
ZIP_BASENAME="$PLUGIN_SLUG-$TIMESTAMP.zip"
if [[ -n "$ZIP_NAME" ]]; then
    ZIP_BASENAME="$ZIP_NAME"
fi

pushd "$BUILD_ROOT" > /dev/null
rm -f "$ZIP_BASENAME"
zip -rq "$ZIP_BASENAME" "$PLUGIN_SLUG"
popd > /dev/null

ZIP_PATH="$BUILD_ROOT/$ZIP_BASENAME"

echo "Created archive: $ZIP_PATH"
echo "Version: $NEW_VERSION"

ls "$TARGET_DIR"
