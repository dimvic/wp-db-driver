<?php

namespace wppdo;

class WpPdoMysqlDriver extends AbstractWpPdoDriver
{
    /**
     * @var \PDO
     */
    private $dbh;
    /**
     * @var \PDOStatement
     */
    private $result;
    /**
     * @var null|array
     */
    private $col_info;
    /**
     * @var array
     */
    private $fetched_rows = [];

    public function getPdoInstance(): ?\PDO
    {
        return $this->dbh;
    }

    public function escape($string): string
    {
        return \substr($this->dbh->quote($string), 1, -1);
    }

    public function get_error_message(): string
    {
        $error = $this->dbh->errorInfo();

        return $error[2] ?? '';
    }

    public function flush(): void
    {
        if ($this->result instanceof \PDOStatement) {
            $this->result->closeCursor();
        }
        $this->result = null;
        $this->col_info = null;
        $this->fetched_rows = [];
    }

    public function is_connected(): bool
    {
        return !(!$this->dbh || 2006 === (int)$this->dbh->errorCode());
    }

    public function connect($host, $user, $pass, $port = 3306, $options = []): bool
    {
        if ($this->dbh) {
            return true;
        }

        if ('.sock' === \substr($port, -5)) {
            $dsn = "mysql:host={$host};unix_socket={$port}";
        } else {
            $dsn = "mysql:host={$host};port={$port}";
        }

        try {
            $pdo_options = [];

            if (!empty($options['key']) && !empty($options['cert']) && !empty($options['ca'])) {
                $pdo_options[\PDO::MYSQL_ATTR_SSL_KEY] = $options['key'];
                $pdo_options[\PDO::MYSQL_ATTR_SSL_CERT] = $options['cert'];
                $pdo_options[\PDO::MYSQL_ATTR_SSL_CA] = $options['ca'];
                $pdo_options[\PDO::MYSQL_ATTR_SSL_CAPATH] = $options['ca_path'];
                $pdo_options[\PDO::MYSQL_ATTR_SSL_CIPHER] = $options['cipher'];

                // Cleanup empty values
                $pdo_options = \array_filter($pdo_options);
            }

            $this->dbh = new \PDO($dsn, $user, $pass, $pdo_options);
            $this->dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    public function close(): bool
    {
        if (!$this->dbh) {
            return false;
        }

        $this->dbh = null;

        return true;
    }

    public function ping(): bool
    {
        return (bool)$this->query('SELECT 1');
    }

    public function set_charset($charset = null, $collate = null): bool
    {
        if ($charset && $this->has_cap('collation') && $this->has_cap('set_charset')) {
            $this->dbh->exec('set names ' . $charset);

            return true;
        }

        return false;
    }

    public function connection_charset(): ?string
    {
        if ($this->is_connected()) {
            $result = $this->dbh->query("SHOW VARIABLES LIKE 'character_set_connection'");

            return (string)$result->fetchColumn(1);
        }

        return null;
    }

    public function select($db): bool
    {
        try {
            $this->dbh->exec(\sprintf('USE `%s`', $db));
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    public function query($query)
    {
        $return_val = 0;

        if (!$this->dbh) {
            return false;
        }

        try {
            $this->result = $this->dbh->query($query);
        } catch (\Exception $e) {
            if (WP_DEBUG) {
                /** @noinspection ForgottenDebugOutputInspection */
                \error_log('Error executing query: ' . $e->getCode() . ' - ' . $e->getMessage() . ' in query ' . $query);
            }

            return false;
        }

        if (\preg_match('/^\s*(create|alter|truncate|drop)\s/i', $query)) {
            $return_val = $this->result;
        } elseif (\preg_match('/^\s*(insert|delete|update|replace)\s/i', $query)) {
            $return_val = $this->affected_rows();
        } elseif (\preg_match('/^\s*select\s/i', $query)) {
            $this->get_results();

            return \count($this->fetched_rows);
        }

        return $return_val;
    }

    public function query_result($row, $field = 0)
    {
        if ($row > 1) {
            $this->result->fetch(\PDO::FETCH_ASSOC, \PDO::FETCH_ORI_NEXT, $row);
        }

        return $this->result->fetchColumn($field);
    }

    public function affected_rows(): int
    {
        if ($this->result instanceof \PDOStatement) {
            return $this->result->rowCount();
        }

        return 0;
    }

    public function insert_id(): string
    {
        return (string)$this->dbh->lastInsertId();
    }

    public function get_results(): array
    {
        if (!empty($this->fetched_rows)) {
            return $this->fetched_rows;
        }
        $this->fetched_rows = [];

        if (!empty($this->result) && $this->result->rowCount() > 0) {
            try {
                while ($row = $this->result->fetchObject()) {
                    $this->fetched_rows[] = $row;
                }
            } catch (\Exception $e) {
            }
        }

        return $this->fetched_rows;
    }

    public function load_col_info(): ?array
    {
        if ($this->col_info) {
            return $this->col_info;
        }

        $num_fields = $this->result->columnCount();

        for ($i = 0; $i < $num_fields; $i++) {
            $this->col_info[$i] = (object)$this->result->getColumnMeta($i);
        }

        return $this->col_info;
    }

    public function db_version(): ?string
    {
        return $this->is_connected() ? \preg_replace('/[^0-9.].*/', '', $this->dbh->getAttribute(\PDO::ATTR_SERVER_VERSION)) : null;
    }

    public function has_cap(string $db_cap): bool
    {
        $db_cap = \strtolower($db_cap);

        $ret = parent::has_cap($db_cap);

        if ($ret && 'utf8mb4' === $db_cap) {
            return \version_compare($this->dbh->getAttribute(\PDO::ATTR_CLIENT_VERSION), '5.5.3', '>=');
        }

        return $ret;
    }

    public function __sleep()
    {
        return [];
    }

    public static function get_name(): string
    {
        return '\PDO - MySQL';
    }

    public static function is_supported(): bool
    {
        return \extension_loaded('pdo_mysql');
    }
}
