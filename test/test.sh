#!/usr/bin/env bash

DB_NAME=${DB_NAME:-wordpress_test}
DB_USER=${DB_USER:-root}
DB_PASS=${DB_PASS:-}
DB_HOST=${DB_HOST:-localhost}
WP_VERSION=${WP_VERSION:-5.1.1}
WP_MULTISITE=${WP_MULTISITE:-0}
WP_PDO_DRIVER=${WP_PDO_DRIVER:-pdo_mysql}
PHP_VERSION=${PHP_VERSION:-$(php -r 'echo preg_replace("/^(\d+)\.(\d+).*$/", "\$1.\$2", PHP_VERSION);')}

DIR=`readlink -f ..`
TEST_DIR="${HOME}/wp-pdo-tests/${WP_VERSION}/${PHP_VERSION}"

set -ex

# portable in-place argument for both GNU sed and Mac OSX sed
if [[ $(uname -s) == 'Darwin' ]]; then
	SED_OPT='-i .bak'
else
	SED_OPT='-i'
fi

install_tests() {
	if [[ ! -d "${TEST_DIR}" ]]; then
		mkdir -p ${TEST_DIR}

		git clone --depth=1 --branch="${WP_VERSION}" git://develop.git.wordpress.org/ ${TEST_DIR}

		cd ${TEST_DIR}

		rm tests/phpunit/tests/db/charset.php
		rm tests/phpunit/tests/formatting/WpReplaceInHtmlTags.php

		cp wp-tests-config-sample.php wp-tests-config.php
		sed ${SED_OPT} "s/youremptytestdbnamehere/${DB_NAME}/" wp-tests-config.php
		sed ${SED_OPT} "s/yourusernamehere/${DB_USER}/" wp-tests-config.php
		sed ${SED_OPT} "s/yourpasswordhere/${DB_PASS}/" wp-tests-config.php
		sed ${SED_OPT} "s/localhost/${DB_HOST}/" wp-tests-config.php
		sed ${SED_OPT} "s/^.*DB_CHARSET.*$/define( 'DB_CHARSET', 'utf8mb4' );/" wp-tests-config.php
		sed ${SED_OPT} "s/^.*DB_COLLATE.*$/define( 'DB_COLLATE', 'utf8mb4_unicode_ci' );/" wp-tests-config.php
		echo "define( 'WP_PDO_DRIVER', '${WP_PDO_DRIVER}');" >> wp-tests-config.php

		sed ${SED_OPT} "s/class wpdb_exposed_methods_for_testing extends wpdb /class wpdb_exposed_methods_for_testing extends \\\\wppdo\\\\WpPdo /" tests/phpunit/includes/utils.php

		sed ${SED_OPT} 's/name="WP_RUN_CORE_TESTS" value="1"/name="WP_RUN_CORE_TESTS" value="0"/' phpunit.xml.dist
	fi
}

install_db() {
	# parse DB_HOST for port or socket references
	local PARTS=(${DB_HOST//\:/ })
	local DB_HOSTNAME=${PARTS[0]};
	local DB_SOCK_OR_PORT=${PARTS[1]};
	local EXTRA=''

	if [[ ! -z ${DB_HOSTNAME} ]]; then
		if [[ "${DB_SOCK_OR_PORT}" =~ ^[0-9]+$ ]]; then
			EXTRA=" --host=${DB_HOSTNAME} --port=${DB_SOCK_OR_PORT} --protocol=tcp"
		elif [[ ! -z ${DB_SOCK_OR_PORT} ]]; then
			EXTRA=" --socket=${DB_SOCK_OR_PORT}"
		elif [[ ! -z ${DB_HOSTNAME} ]]; then
			EXTRA=" --host=${DB_HOSTNAME} --protocol=tcp"
		fi
	fi

	# create database
	echo 'DROP DATABASE IF EXISTS wordpress_test;' | mysql --user="${DB_USER}" --password="${DB_PASS}"${EXTRA}
	mysqladmin create ${DB_NAME} --user="${DB_USER}" --password="${DB_PASS}"${EXTRA}
}

install_plugin() {
	cd "${TEST_DIR}"

	rm -rf 'src/wp-content/plugins/wp-pdo'

	mkdir -p 'src/wp-content/plugins'

	cp -rf "${DIR}/src" 'src/wp-content/plugins/wp-pdo'
	cp -f "${DIR}/db.php" 'src/wp-content/db.php'
}

install_tests
install_db
install_plugin

cd ${TEST_DIR}
TESTING=true phpunit -v --group wpdb
