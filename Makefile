WP_VERSION?="5.1.1"
WP_MULTISITE?="0"
WPDB_DRIVER?="pdo_mysql"
DB_NAME?="wordpress_test"
DB_USER?="root"
DB_PASS?="pass"
DB_HOST?="localhost"

.PHONY: install
install:
	cd test && \
	\
	WP_VERSION="$(WP_VERSION)" \
	WP_MULTISITE="$(WP_MULTISITE)" \
	WPDB_DRIVER="$(WPDB_DRIVER)" \
	DB_NAME="$(DB_NAME)" \
	DB_USER="$(DB_USER)" \
	DB_PASS="$(DB_PASS)" \
	DB_HOST="$(DB_HOST)" \
	bash install.sh

.PHONY: test
test: install
	cd test && \
	\
	WP_VERSION="$(WP_VERSION)" \
	WP_MULTISITE="$(WP_MULTISITE)" \
	WPDB_DRIVER="$(WPDB_DRIVER)" \
	DB_NAME="$(DB_NAME)" \
	DB_USER="$(DB_USER)" \
	DB_PASS="$(DB_PASS)" \
	DB_HOST="$(DB_HOST)" \
	bash run.sh
