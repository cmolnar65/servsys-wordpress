#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="servsys"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BUILD_DIR="${ROOT_DIR}/build"
STAGE_DIR="${BUILD_DIR}/${PLUGIN_SLUG}"

cd "${ROOT_DIR}"

for command in git zip unzip composer sha256sum; do
    if ! command -v "${command}" >/dev/null 2>&1; then
        echo "ERROR: ${command} is required." >&2
        exit 1
    fi
done

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

# Install the production autoloader before removing Composer build metadata.
composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --working-dir="${STAGE_DIR}"

rm -rf \
    "${STAGE_DIR}/.github" \
    "${STAGE_DIR}/tests" \
    "${STAGE_DIR}/tools" \
    "${STAGE_DIR}/build"

rm -f \
    "${STAGE_DIR}/.gitignore" \
    "${STAGE_DIR}/build-release.sh" \
     "${STAGE_DIR}/composer.lock" \
    "${STAGE_DIR}/phpunit.xml.dist"

(
    cd "${BUILD_DIR}"
    zip -qr "$(basename "${ZIP_FILE}")" "${PLUGIN_SLUG}"
)

ZIP_ENTRIES="$(unzip -Z1 "${ZIP_FILE}")"

if ! grep -Fx "${PLUGIN_SLUG}/servsys.php" <<< "${ZIP_ENTRIES}" >/dev/null; then
    echo "ERROR: Package audit failed: servsys.php is missing." >&2
    rm -f "${ZIP_FILE}"
    exit 1
fi

for forbidden in ".git/" ".github/" "tests/" "tools/" "build-release.sh" "composer.lock" "phpunit.xml.dist"; do
    if grep -F "${forbidden}" <<< "${ZIP_ENTRIES}" >/dev/null; then
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
