#!/usr/bin/env bash
# Prepares a disposable WordPress for the plugin test-suite in CI. Not for production use.
# Env: WP_VERSION (default 6.4.3), DB_HOST, DB_NAME, DB_USER, DB_PASS, WP_DIR (default $RUNNER_TEMP/wp)
set -euo pipefail
WP_VERSION="${WP_VERSION:-6.4.3}"; WP_DIR="${WP_DIR:-${RUNNER_TEMP:-/tmp}/wp}"
mkdir -p "$WP_DIR" && curl -fsSL "https://wordpress.org/wordpress-${WP_VERSION}.tar.gz" | tar -xz --strip-components=1 -C "$WP_DIR"
cat > "$WP_DIR/wp-config.php" <<PHP
<?php
define( 'DB_NAME', '${DB_NAME:-noblen_test}' ); define( 'DB_USER', '${DB_USER:-root}' ); define( 'DB_PASSWORD', '${DB_PASS:-root}' );
define( 'DB_HOST', '${DB_HOST:-127.0.0.1}' ); define( 'DB_CHARSET', 'utf8mb4' ); define( 'DB_COLLATE', '' );
foreach ( array( 'AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY','AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT' ) as \$k ) { define( \$k, 'ci-only-' . \$k ); }
\$table_prefix = 'wp_';
define( 'NOBLEN_AUDIT_KEYS', array( 'ci1' => 'ci-only-throwaway-key-0123456789abcdef' ) ); define( 'NOBLEN_AUDIT_KEY_ID', 'ci1' );
define( 'WP_DEBUG', true ); define( 'WP_DEBUG_LOG', getenv( 'WP_DEBUG_LOG_FILE' ) ? getenv( 'WP_DEBUG_LOG_FILE' ) : false ); define( 'WP_DEBUG_DISPLAY', false ); define( 'WP_HTTP_BLOCK_EXTERNAL', true ); define( 'DISABLE_WP_CRON', true );
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }
require_once ABSPATH . 'wp-settings.php';
PHP
ln -sfn "$PWD/wordpress-plugin/noblen-corporate-core" "$WP_DIR/wp-content/plugins/noblen-corporate-core"
ln -sfn "$PWD/wordpress-theme/noblen-corporate" "$WP_DIR/wp-content/themes/noblen-corporate"
php -r '$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/"; define("WP_INSTALLING",true); require getenv("WP_DIR")."/wp-load.php"; require_once ABSPATH."wp-admin/includes/upgrade.php"; if(!is_blog_installed()) wp_install("CI","admin","ci@example.test",true,"","ci-pass-only","en_US");'
echo "WP_PATH=$WP_DIR" >> "${GITHUB_ENV:-/dev/null}"
