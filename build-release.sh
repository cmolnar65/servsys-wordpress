#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="servsys"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BUILD_DIR="${ROOT_DIR}/build"
STAGE_DIR="${BUILD_DIR}/${PLUGIN_SLUG}"

cd "${ROOT_DIR}"

if ! command -v git >/dev/null 2>&1; then
    echo "ERROR: git is required." >&2
    exit 1
fi

if ! command -v zip >/dev/null 2>&1; then
    echo "ERROR: zip is required." >&2
    exit 1
fi

if [[ ! -f "servsys.php" ]]; then
    echo "ERROR: servsys.php not found. Run this script from the repository root." >&2
    exit 1
fi

VERSION="$(sed -n "s/^[[:space:]]*\* Version:[[:space:]]*//p" servsys.php | head -n 1 | tr -d '\r')"

if [[ -z "${VERSION}" ]]; then
    echo "ERROR: Could not determine plugin version from servsys.php." >&2
    exit 1
fi

ZIP_FILE="${BUILD_DIR}/${PLUGIN_SLUG}-${VERSION}.zip"

if [[ -e "${ZIP_FILE}" ]]; then
    echo "ERROR: Release package already exists: ${ZIP_FILE}" >&2
    exit 1
fi

rm -rf "${STAGE_DIR}"
mkdir -p "${STAGE_DIR}"

git archive --format=tar HEAD | tar -x -C "${STAGE_DIR}"

rm -rf     "${STAGE_DIR}/.github"     "${STAGE_DIR}/tests"     "${STAGE_DIR}/build"

rm -f     "${STAGE_DIR}/.gitignore"     "${STAGE_DIR}/build-release.sh"     "${STAGE_DIR}/composer.json"     "${STAGE_DIR}/composer.lock"     "${STAGE_DIR}/phpunit.xml.dist"

if [[ -f composer.json ]]; then
    if ! command -v composer >/dev/null 2>&1; then
        echo "ERROR: composer is required to build production dependencies." >&2
        exit 1
    fi

    composer install         --no-dev         --prefer-dist         --no-interaction         --no-progress         --optimize-autoloader         --working-dir="${STAGE_DIR}"
fi

(
    cd "${BUILD_DIR}"
    zip -qr "$(basename "${ZIP_FILE}")" "${PLUGIN_SLUG}"
)

if ! unzip -l "${ZIP_FILE}" | grep -q " ${PLUGIN_SLUG}/servsys.php$"; then
    echo "ERROR: Package audit failed: servsys.php is missing." >&2
    rm -f "${ZIP_FILE}"
    exit 1
fi

for forbidden in     ".git/"     ".github/"     "tests/"     "build-release.sh"     "phpunit.xml.dist"; do
    if unzip -l "${ZIP_FILE}" | grep -q "${forbidden}"; then
        echo "ERROR: Package audit failed: forbidden path found: ${forbidden}" >&2
        rm -f "${ZIP_FILE}"
        exit 1
    fi
done

echo "Release package created:"
echo "  ${ZIP_FILE}"
echo
echo "Version: ${VERSION}"
echo "SHA-256:"
sha256sum "${ZIP_FILE}"
