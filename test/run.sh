#!/usr/bin/env bash

export NODE_ENV="development"

WP_VERSION=${WP_VERSION:-5.1.1}
PHP_VERSION=${PHP_VERSION:-$(php -r 'echo PHP_VERSION;')}

cd "${HOME}/wp-db-driver-tests/${WP_VERSION}/${PHP_VERSION}" && \

[[ ! -d node_modules ]] && (npm i && npm prune && grunt build:files) || true && \

mkdir -p build/wp-content/plugins && \
cp -rf src/wp-content/plugins/wp-db-driver build/wp-content/plugins/wp-db-driver && \
cp src/wp-content/db.php build/wp-content/db.php && \
phpunit -v --group wpdb
