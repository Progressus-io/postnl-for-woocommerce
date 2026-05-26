#!/bin/sh
# Ensure Composer is available, then install PHP dev-dependencies.
# Intended to run inside the wp-env tests-cli Docker container.
set -e

COMPOSER_BIN="$(command -v composer 2>/dev/null || true)"

if [ -z "$COMPOSER_BIN" ]; then
	echo "Composer not found — downloading..."

	# Verify the installer's SHA-384 before executing it, per
	# https://getcomposer.org/doc/faqs/how-to-install-composer-programmatically.md
	EXPECTED_SIG="$(php -r "copy('https://composer.github.io/installer.sig', 'php://stdout');")"
	php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"
	ACTUAL_SIG="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"

	if [ "$EXPECTED_SIG" != "$ACTUAL_SIG" ]; then
		echo "ERROR: composer installer checksum mismatch — aborting."
		rm -f /tmp/composer-setup.php
		exit 1
	fi

	php /tmp/composer-setup.php --quiet --install-dir=/tmp --filename=composer
	rm -f /tmp/composer-setup.php
	COMPOSER_BIN=/tmp/composer
fi

"$COMPOSER_BIN" install --prefer-dist --no-progress --no-interaction
