<?php

namespace wppdo;

abstract class AbstractWpPdoDriver
{
    abstract public function getPdoInstance(): ?\PDO;

    /**
     * Whether MySQL is used as the database engine.
     *
     * This is used when checking against the required MySQL version for WordPress.
     * Normally, a replacement database drop-in (db.php) will skip these checks,
     * but setting this to true will force the checks to occur.
     *
     * @since  3.3.0
     * @return bool
     */
    public function is_mysql(): bool
    {
        return true;
    }

    abstract public function escape($string);

    abstract public function get_error_message();

    abstract public function flush();

    abstract public function is_connected();

    abstract public function connect($host, $user, $pass, $port, $options);

    abstract public function close();

    abstract public function ping();

    abstract public function set_charset($charset = null, $collate = null);

    abstract public function connection_charset();

    abstract public function select($name);

    abstract public function query($query);

    abstract public function query_result($row, $field = 0);

    abstract public function load_col_info();

    abstract public function db_version();

    abstract public function affected_rows();

    abstract public function insert_id();

    abstract public function get_results();

    /**
     * Determine if a database supports a particular feature.
     *
     * @since 2.7.0
     * @since 4.1.0 Support was added for the 'utf8mb4' feature.
     *
     * @see   wpdb::db_version()
     *
     * @param string $db_cap The feature to check for. Accepts 'collation',
     *                       'group_concat', 'subqueries', 'set_charset',
     *                       or 'utf8mb4'.
     * @return bool whether the database feature is supported, false otherwise
     */
    public function has_cap(string $db_cap): bool
    {
        $version = $this->db_version();

        switch (\strtolower($db_cap)) {
            case 'collation':    // @since 2.5.0
            case 'group_concat': // @since 2.7.0
            case 'subqueries':   // @since 2.7.0
                return \version_compare($version, '4.1', '>=');
            case 'set_charset':
                return \version_compare($version, '5.0.7', '>=');
            case 'utf8mb4':      // @since 4.1.0
                return \version_compare($version, '5.5.3', '>=');
        }

        return false;
    }

    public static function get_name(): ?string
    {
        return \function_exists('get_called_class') ? static::class : null;
    }

    public static function is_supported(): bool
    {
        return false;
    }
}
