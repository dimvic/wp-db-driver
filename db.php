<?php

if (!\defined('ABSPATH')) {
    return;
}

if (isset($_GET['wp-pdo-sos'])) {
    \setcookie('wp-pdo-sos', 1, 0, '/', $_SERVER['HTTP_HOST']);
}

if (isset($_COOKIE['wp-pdo-sos']) || isset($_REQUEST['wp-pdo-sos'])) {
    return;
}

global $wpdb;

if (!class_exists('\wppdo\WpPdo')) {
    spl_autoload_register(function ($class) {
        $class = ltrim($class, '\\');
        $prefix = 'wppdo';

        $base_dir = WP_CONTENT_DIR . '/plugins/wp-pdo';
        if (defined('WP_PLUGIN_DIR')) {
            $base_dir = WP_PLUGIN_DIR . '/wp-pdo';
        }

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len)) {
            return;
        }

        $path = substr($class, $len);

        $file = $base_dir . '/' . str_replace('\\', '/', $path) . '.php';

        if (file_exists($file)) {
            /** @noinspection PhpIncludeInspection */
            require $file;
        }
    });
}

$wpdb = new \wppdo\WpPdo(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
