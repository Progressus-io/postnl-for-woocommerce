#!/bin/sh
# Ensure Composer is available, then install PHP dev-dependencies.
# Intended to run inside the wp-env tests-cli Docker container.
set -e

COMPOSER_BIN="$(command -v composer 2>/dev/null || true)"

if [ -z "$COMPOSER_BIN" ]; then
    echo "Composer not found — downloading..."
    php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"
    php /tmp/composer-setup.php --quiet --install-dir=/tmp --filename=composer
    rm -f /tmp/composer-setup.php
    COMPOSER_BIN=/tmp/composer
fi

"$COMPOSER_BIN" install --prefer-dist --no-progress --no-interaction
