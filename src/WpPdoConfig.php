<?php

namespace wppdo;

if (!\defined('ABSPATH')) {
    return;
}

class WpPdoConfig
{
    public static function get_drivers(): array
    {
        global $custom_drivers;

        $drivers = [
            'pdo_mysql' => WpPdoMysqlDriver::class,
        ];

        if (isset($custom_drivers) && \is_array($custom_drivers)) {
            $drivers = $custom_drivers + $drivers;
        }

        return $drivers;
    }

    public static function get_current_driver()
    {
        $drivers = self::get_drivers();

        if (\defined('WPDB_DRIVER')) {
            $driver = WPDB_DRIVER;

            if (self::class_is_driver_and_supported($driver)) {
                return $drivers[$driver];
            }
        }

        foreach ($drivers as $driver => $class) {
            if (self::class_is_driver_and_supported($class)) {
                return $class;
            }
        }

        return false;
    }

    private static function class_is_driver_and_supported($class): bool
    {
        return \class_exists($class) && \method_exists($class, 'is_supported') && $class::is_supported();
    }
}
