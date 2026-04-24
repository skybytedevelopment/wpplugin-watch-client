#!/usr/bin/env bash
# build.sh — WPPlugin Watch release builder
#
# Usage: ./build.sh
#
# Produces a versioned zip ready for distribution or local install.
# Optionally injects a WPW_API_BASE_OVERRIDE for dev/staging builds.
# The source files are never modified.

set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_SLUG="wppluginwatch"
BUILD_DIR="$(mktemp -d)"
DIST_DIR="${PLUGIN_DIR}/dist"

# ── sha256 helper (macOS uses shasum -a 256; Linux uses sha256sum) ────────────
sha256() {
    if command -v sha256sum &>/dev/null; then
        sha256sum | awk '{print $1}'
    else
        shasum -a 256 | awk '{print $1}'
    fi
}

# ── Read version from the plugin header ──────────────────────────────────────
VERSION=$(grep "^ \* Version:" "${PLUGIN_DIR}/wppluginwatch.php" | sed 's/.*Version:[[:space:]]*//')
if [[ -z "$VERSION" ]]; then
    echo "ERROR: Could not read version from wppluginwatch.php" >&2
    exit 1
fi

echo "Building WPPlugin Watch v${VERSION}"
echo ""

# ── Prompts ───────────────────────────────────────────────────────────────────
read -r -p "API base override (leave blank for production): " API_OVERRIDE
echo ""

read -r -p "Security release? [y/N]: " SECURITY_INPUT
SECURITY_UPDATE="false"
if [[ "$(echo "${SECURITY_INPUT}" | tr '[:upper:]' '[:lower:]')" == "y" ]]; then
    SECURITY_UPDATE="true"
fi
echo ""

# ── Compute version hash (all files except wppluginwatch.php) ────────────────
# wppluginwatch.php is excluded because it contains the hash constant itself.
HASH=$(find "${PLUGIN_DIR}" \
    -type f \( -name "*.php" -o -name "*.js" -o -name "*.css" \) \
    ! -name "wppluginwatch.php" \
    ! -name "build.sh" \
    ! -path "*/dist/*" \
    ! -path "*/.git/*" \
    | sort | xargs cat | sha256)

echo "Version hash: ${HASH}"

# ── Stage build copy ─────────────────────────────────────────────────────────
BUILD_PLUGIN="${BUILD_DIR}/${PLUGIN_SLUG}"
cp -r "${PLUGIN_DIR}" "${BUILD_PLUGIN}"

# Remove build/dev artifacts and repo-only files from the staged copy
rm -f  "${BUILD_PLUGIN}/build.sh"
rm -rf "${BUILD_PLUGIN}/dist"
rm -rf "${BUILD_PLUGIN}/.git"
rm -f  "${BUILD_PLUGIN}/.gitignore"
rm -f  "${BUILD_PLUGIN}/.gitattributes"
rm -f  "${BUILD_PLUGIN}/CONTRIBUTING.md"
rm -f  "${BUILD_PLUGIN}/SECURITY.md"
find   "${BUILD_PLUGIN}" -name ".DS_Store" -delete

# ── Inject version and hash into staged wppluginwatch.php ────────────────────
# Uses a temp file instead of sed -i to avoid macOS/Linux sed incompatibility.
MAIN_FILE="${BUILD_PLUGIN}/wppluginwatch.php"
TMP_FILE="${BUILD_PLUGIN}/wppluginwatch.php.tmp"

# Replace version and hash constants
sed "s/define( 'WPW_VERSION',      '.*' );/define( 'WPW_VERSION',      '${VERSION}' );/" "${MAIN_FILE}" \
    | sed "s/define( 'WPW_VERSION_HASH', '.*' );/define( 'WPW_VERSION_HASH', '${HASH}' );/" \
    > "${TMP_FILE}"
mv "${TMP_FILE}" "${MAIN_FILE}"

# ── Inject API base override if provided ─────────────────────────────────────
# The override must be defined BEFORE the WPW_API_BASE define() evaluates it,
# so we prepend it immediately before that block in the staged file.
if [[ -n "$API_OVERRIDE" ]]; then
    echo "Injecting API override: ${API_OVERRIDE}"
    INJECT="// Injected by build.sh -- not in source control.\ndefine( 'WPW_API_BASE_OVERRIDE', '${API_OVERRIDE}' );\n"
    awk -v inject="${INJECT}" "
        /^define\( 'WPW_API_BASE',/ { printf \"%s\n\", inject }
        { print }
    " "${MAIN_FILE}" > "${TMP_FILE}"
    mv "${TMP_FILE}" "${MAIN_FILE}"
else
    echo "No API override -- build will use production endpoint."
fi

# ── Package ───────────────────────────────────────────────────────────────────
mkdir -p "${DIST_DIR}"
OUT_FILE="${DIST_DIR}/${PLUGIN_SLUG}-${VERSION}.zip"
(cd "${BUILD_DIR}" && zip -rq "${OUT_FILE}" "${PLUGIN_SLUG}/")

# ── Cleanup ───────────────────────────────────────────────────────────────────
rm -rf "${BUILD_DIR}"

# ── Summary ───────────────────────────────────────────────────────────────────
echo ""
echo "Done."
echo "  Output:   ${OUT_FILE}"
echo "  Version:  ${VERSION}"
echo "  Hash:     ${HASH}"
echo "  Security: ${SECURITY_UPDATE}"
if [[ -n "$API_OVERRIDE" ]]; then
    echo "  API:      ${API_OVERRIDE}  [DEV BUILD -- do not distribute]"
else
    echo "  API:      https://api.wpplugin.watch  [production]"
fi

