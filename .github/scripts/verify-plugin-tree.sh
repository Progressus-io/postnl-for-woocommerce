#!/usr/bin/env bash
#
# Assert a built plugin tree is clean before it is published.
#
# The same gate runs against the unzipped release ZIP and the SVN trunk
# working copy, so trunk can never publish a file the ZIP would not ship.
# Trunk drifted from the ZIP historically because rsync does not delete
# destination files that are excluded by the filter, so anything that landed
# in trunk before its .distignore entry lingered and every `svn copy` tag
# carried it forward. This check fails the deploy loudly if that recurs.
#
# Usage: verify-plugin-tree.sh <plugin-root-dir> <label>
set -eu

DIR="${1:?usage: verify-plugin-tree.sh <plugin-root-dir> <label>}"
LABEL="${2:-tree}"

echo "=== Verifying $LABEL: $DIR ==="

FAIL=0

# 1. No Composer credential files may ship.
if find "$DIR" \( -name "auth.json" -o -name "auth.*.json" \) 2>/dev/null | grep -q .; then
  echo "::error::[$LABEL] Composer auth credential file found — credentials must not ship!"
  find "$DIR" \( -name "auth.json" -o -name "auth.*.json" \) 2>/dev/null
  FAIL=1
else
  echo "[$LABEL] Credential check passed — no auth files."
fi

# 2. The PostNL V4 SDK must NOT ship. It declares php >=8.2, which raises the
#    package floor and makes Composer emit a platform_check.php that fatals
#    every request on PHP < 8.2. It stays in require-dev until V4 ships.
if [ -d "$DIR/vendor/postnl/api-client-sdk" ]; then
  echo "::error::[$LABEL] vendor/postnl/api-client-sdk present — the SDK is dev-only until V4 ships; shipping it raises the PHP floor to 8.2 and fatals PHP 7.4 sites!"
  FAIL=1
else
  echo "[$LABEL] SDK check passed — vendor/postnl/api-client-sdk absent."
fi

# 3. Internal/dev files and stray vendors that must never be published. These
#    are excluded by .distignore (so the ZIP is clean) but predate the
#    --delete-excluded flag on the trunk rsync, so old copies lingered in the
#    SVN destination. vendor/myparcelnl is a stray MyParcel SDK that was never
#    a dependency here — it exists only in SVN, not in composer.json or git.
FORBIDDEN=(
  "AGENTS.md"
  "CLAUDE.md"
  "docs"
  "phpunit-unit.xml.dist"
  "phpunit-integration.xml.dist"
  ".wp-env.json"
  ".npmrc"
  "vendor/myparcelnl"
)
FORBIDDEN_FOUND=0
for path in "${FORBIDDEN[@]}"; do
  if [ -e "$DIR/$path" ]; then
    echo "::error::[$LABEL] forbidden path present: $path — it is not shipped in the ZIP and must not be published."
    FORBIDDEN_FOUND=1
    FAIL=1
  fi
done
if [ "$FORBIDDEN_FOUND" -eq 0 ]; then
  echo "[$LABEL] Forbidden-path check passed — no internal/dev files or stray vendors."
fi

# 4. The generated platform check must not exceed the declared 7.4 floor.
PLATFORM_CHECK="$DIR/vendor/composer/platform_check.php"
if [ ! -f "$PLATFORM_CHECK" ]; then
  echo "::error::[$LABEL] vendor/composer/platform_check.php missing — cannot verify the production PHP floor!"
  FAIL=1
else
  PHP_FLOOR=$(grep -oP 'PHP_VERSION_ID >= \K[0-9]+' "$PLATFORM_CHECK" | head -1)
  if [ -z "$PHP_FLOOR" ]; then
    echo "::error::[$LABEL] could not parse the PHP floor from platform_check.php — the Composer format may have changed!"
    FAIL=1
  elif [ "$PHP_FLOOR" -gt 70400 ]; then
    echo "::error::[$LABEL] platform_check.php requires PHP_VERSION_ID >= $PHP_FLOOR, above the declared 7.4 floor (70400) — a dependency raised the requirement!"
    FAIL=1
  else
    echo "[$LABEL] PHP floor check passed — requires PHP_VERSION_ID >= $PHP_FLOOR."
  fi
fi

if [ "$FAIL" -ne 0 ]; then
  echo "::error::[$LABEL] verification FAILED — see errors above."
  exit 1
fi
echo "=== $LABEL verification passed ==="
