<?php

if (isset($_GET['wp-db-driver-emergency-override'])) {
    \setcookie('wp-db-driver-emergency-override', 1, 0, '/', $_SERVER['HTTP_HOST']);
}

if (isset($_COOKIE['wp-db-driver-emergency-override']) || isset($_REQUEST['wp-db-driver-emergency-override'])) {
    return;
}

global $wpdb;

if (!class_exists('\wppdo\WpPdo')) {
    spl_autoload_register(function ($class) {
        $class = ltrim($class, '\\');
        $prefix = 'wppdo';

        $base_dir = WP_CONTENT_DIR . '/plugins/wp-db-driver';
        if (defined('WP_PLUGIN_DIR')) {
            $base_dir = WP_PLUGIN_DIR . '/wp-db-driver';
        }

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len)) {
            return;
        }

        $path = substr($class, $len);

        $file = $base_dir . '/' . str_replace('\\', '/', $path) . '.php';

        if (file_exists($file)) {
            require $file;
        }
    });
}

$wpdb = new \wppdo\WpPdo(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
