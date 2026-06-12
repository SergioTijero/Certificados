#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_SLUG="certificados"
DIST_DIR="${ROOT_DIR}/dist"
BUILD_DIR="${DIST_DIR}/${PLUGIN_SLUG}"
ZIP_FILE="${DIST_DIR}/${PLUGIN_SLUG}.zip"

rm -rf "${BUILD_DIR}" "${ZIP_FILE}"
mkdir -p "${BUILD_DIR}"

rsync -a \
  --exclude='.git/' \
  --exclude='.gitignore' \
  --exclude='dist/' \
  --exclude='tools/' \
  --exclude='*.zip' \
  "${ROOT_DIR}/" "${BUILD_DIR}/"

(
  cd "${DIST_DIR}"
  zip -qr "${ZIP_FILE}" "${PLUGIN_SLUG}"
)

echo "${ZIP_FILE}"
