#!/bin/sh
# Install PHP dev-dependencies via composer.
# Intended to run inside the wp-env tests-cli Docker container, but works on
# the host too — wherever composer is available.
set -eu

if ! command -v composer >/dev/null 2>&1; then
	echo "ERROR: composer is not on PATH." >&2
	echo "Install Composer first (https://getcomposer.org), or run 'composer install'" >&2
	echo "on the host before invoking this script — wp-env mounts the resulting" >&2
	echo "vendor/ tree into the tests-cli container automatically." >&2
	exit 1
fi

composer install --prefer-dist --no-progress --no-interaction
