<?php

namespace wppdo;

if (!\defined('ABSPATH')) {
    return;
}

class WpPdoConfig
{
    public const DRIVERS = [
        'pdo_mysql' => WpPdoMysqlDriver::class,
    ];

    public static function get_drivers(): array
    {
        global $custom_drivers;

        if (!isset($custom_drivers) || !\is_array($custom_drivers)) {
            return static::DRIVERS;
        }

        return $custom_drivers + static::DRIVERS;
    }

    public static function get_current_driver()
    {
        $drivers = self::get_drivers();

        if (\defined('WP_PDO_DRIVER')) {
            $driver = WP_PDO_DRIVER;

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
