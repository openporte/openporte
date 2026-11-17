#!/usr/bin/env bash
# Static analysis (Phase 4 of docs/release-preparation.md).
# php -l / bash -n are blocking; phpcs / phpstan are informative only.
#
# Usage: npm run release:check
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/../.."

echo "== php -l (blocking) =="
git ls-files '*.php' | xargs -n1 php -l

echo "== bash -n (blocking) =="
git ls-files '*.sh' | xargs -n1 bash -n

if [[ -x vendor/bin/phpcs ]]; then
  PHPCS=vendor/bin/phpcs
elif command -v phpcs >/dev/null 2>&1; then
  PHPCS=phpcs
else
  PHPCS=""
fi
if [[ -n "$PHPCS" ]]; then
  echo "== phpcs (informative) =="
  "$PHPCS" --standard=phpcs.xml.dist || true
  "$PHPCS" --standard=phpcs-demo.xml.dist || true
else
  echo "phpcs not installed (composer install), skipping — informative only."
fi

# Delegated to bin/lint/phpstan.sh (same entry point as `npm run lint:phpstan`)
# because the invocation needs a raised memory limit — see that script's header.
if [[ -x vendor/bin/phpstan ]] || command -v phpstan >/dev/null 2>&1; then
  PHPSTAN_FOUND=1
else
  PHPSTAN_FOUND=0
fi
if [[ "$PHPSTAN_FOUND" == 1 ]] && [[ -f phpstan.neon || -f phpstan.neon.dist ]]; then
  echo "== phpstan (informative) =="
  bash bin/lint/phpstan.sh --no-progress || true
else
  echo "phpstan/phpstan.neon.dist not found, skipping — informative only."
fi

echo "Static analysis complete."
