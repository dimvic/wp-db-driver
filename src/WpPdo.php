<?php

/** @noinspection PhpMissingParentConstructorInspection */

namespace wppdo;

/**
 * @property AbstractWpPdoDriver|resource $dbh
 */
class WpPdo extends \wpdb
{
    public $charset = 'utf8';

    /**
     * @noinspection MagicMethodsValidityInspection
     *
     * Connects to the database server and selects a database
     *
     * PHP5 style constructor for compatibility with PHP5. Does
     * the actual setting up of the class properties and connection
     * to the database.
     *
     * @link         https://core.trac.wordpress.org/ticket/3354
     * @since        2.0.8
     *
     * @param string $dbuser     MySQL database user
     * @param string $dbpassword MySQL database password
     * @param string $dbname     MySQL database name
     * @param string $dbhost     MySQL database host
     */
    public function __construct($dbuser, $dbpassword, $dbname, $dbhost)
    {
        if (!$this->set_driver()) {
            return;
        }
        \register_shutdown_function([$this, '__destruct']);
        if (WP_DEBUG && WP_DEBUG_DISPLAY) {
            $this->show_errors();
        }
        $this->init_charset();
        $this->dbuser = $dbuser;
        $this->dbpassword = $dbpassword;
        $this->dbname = $dbname;
        $this->dbhost = $dbhost;
        $this->db_connect();
    }

    public function getPdoInstance(): ?\PDO
    {
        return $this->dbh->getPdoInstance();
    }

    public function init_charset(): void
    {
        $this->charset = 'utf8';
        $this->collate = 'utf8_unicode_ci';
        if (\defined('DB_CHARSET') && DB_CHARSET) {
            $this->charset = DB_CHARSET;
        }
        if (\defined('DB_COLLATE') && DB_COLLATE) {
            $this->collate = DB_COLLATE;
        }
        if (!$this->dbh || !$this->dbh->is_connected()) {
            return;
        }
        if ('utf8' === $this->charset && $this->has_cap('utf8mb4')) {
            $this->charset = 'utf8mb4';
        }
        if ('utf8mb4' === $this->charset && (!$this->collate || \stripos($this->collate, 'utf8_') === 0)) {
            $this->collate = 'utf8mb4_unicode_ci';
        }
    }

    public function set_charset($dbh, $charset = null, $collate = null): void
    {
        /** @var \wpdb_driver_pdo_mysql $dbh */
        if (!isset($charset)) {
            $charset = $this->charset;
        }
        if (!isset($collate)) {
            $collate = $this->collate;
        }
        if ($charset && !$dbh->set_charset($charset, $collate) && $this->has_cap('collation')) {
            $query = $this->prepare('SET NAMES %s', $charset);
            if (!empty($collate)) {
                $query .= $this->prepare(' COLLATE %s', $collate);
            }
            $this->query($query);
        }
    }

    public function set_sql_mode($modes = []): void
    {
        if (empty($modes)) {
            $res = $this->dbh->query('SELECT @@SESSION.sql_mode;');
            if (!$res) {
                return;
            }
            $modes_str = $this->dbh->query_result(0);
            if (empty($modes_str)) {
                return;
            }
            $modes = \explode(',', $modes_str);
        }
        $modes = \array_change_key_case($modes, \CASE_UPPER);
        $incompatible_modes = (array)apply_filters('incompatible_sql_modes', $this->incompatible_modes);
        foreach ($modes as $i => $mode) {
            if (\in_array($mode, $incompatible_modes, true)) {
                unset($modes[$i]);
            }
        }
        $modes_str = \implode(',', $modes);
        $this->dbh->query("SET SESSION sql_mode='${modes_str}';");
    }

    public function select($db, $dbh = null): void
    {
        if (null === $dbh) {
            $dbh = $this->dbh;
        }
        $success = $dbh->select($db);
        if (!$success) {
            $this->ready = false;
            if (!did_action('template_redirect')) {
                wp_load_translations_early();
                $message = '<h1>' . __('Can&#8217;t select database') . "</h1>\n";
                $message .= '<p>' . \sprintf(
                    /* translators: %s: database name */
                        __('We were able to connect to the database server (which means your username and password is okay) but not able to select the %s database.'),
                        '<code>' . \htmlspecialchars($db, \ENT_QUOTES) . '</code>'
                    ) . "</p>\n";
                $message .= "<ul>\n";
                $message .= '<li>' . __('Are you sure it exists?') . "</li>\n";
                $message .= '<li>' . \sprintf(
                    /* translators: 1: database user, 2: database name */
                        __('Does the user %1$s have permission to use the %2$s database?'),
                        '<code>' . \htmlspecialchars($this->dbuser, \ENT_QUOTES) . '</code>',
                        '<code>' . \htmlspecialchars($db, \ENT_QUOTES) . '</code>'
                    ) . "</li>\n";
                $message .= '<li>' . \sprintf(
                    /* translators: %s: database name */
                        __('On some systems the name of your database is prefixed with your username, so it would be like <code>username_%1$s</code>. Could that be the problem?'),
                        \htmlspecialchars($db, \ENT_QUOTES)
                    ) . "</li>\n";
                $message .= "</ul>\n";
                /** @noinspection HtmlUnknownTarget */
                $message .= '<p>' . \sprintf(
                    /* translators: %s: support forums URL */
                        __('If you don&#8217;t know how to set up a database you should <strong>contact your host</strong>. If all else fails you may find help at the <a href="%s">WordPress Support Forums</a>.'),
                        __('https://wordpress.org/support/')
                    ) . "</p>\n";
                $this->bail($message, 'db_select_fail');
            }
        }
    }

    public function _real_escape($string): string
    {
        if ($this->dbh) {
            $escaped = $this->dbh->escape($string);
        } else {
            $class = \get_class($this);
            if (\function_exists('__')) {
                /* translators: %s: database access abstraction class, usually wpdb or a class extending wpdb */
                _doing_it_wrong($class, \sprintf(__('%s must set a database connection for use with escaping.'), $class), '3.6.0');
            } else {
                _doing_it_wrong($class, \sprintf('%s must set a database connection for use with escaping.', $class), '3.6.0');
            }
            $escaped = \addslashes($string);
        }

        return $this->add_placeholder_escape($escaped);
    }

    public function print_error($str = '')
    {
        global $EZSQL_ERROR;
        if (!$str) {
            $str = $this->dbh->get_error_message();
        }
        $EZSQL_ERROR[] = ['query' => $this->last_query, 'error_str' => $str];
        if ($this->suppress_errors) {
            return false;
        }
        wp_load_translations_early();
        if ($caller = $this->get_caller()) {
            $error_str = \sprintf(__('WordPress database error %1$s for query %2$s made by %3$s'), $str, $this->last_query, $caller);
        } else {
            $error_str = \sprintf(__('WordPress database error %1$s for query %2$s'), $str, $this->last_query);
        }
        /** @noinspection ForgottenDebugOutputInspection */
        \error_log($error_str);
        // Are we showing errors?
        if (!$this->show_errors) {
            return false;
        }
        // If there is an error then take note of it
        if (is_multisite()) {
            $msg = \sprintf(
                "%s [%s]\n%s\n",
                __('WordPress database error:'),
                $str,
                $this->last_query
            );
            if (\defined('ERRORLOGFILE')) {
                /** @noinspection ForgottenDebugOutputInspection */
                \error_log($msg, 3, ERRORLOGFILE);
            }
            if (\defined('DIEONDBERROR')) {
                wp_die($msg);
            }
        } else {
            $str = \htmlspecialchars($str, \ENT_QUOTES);
            $query = \htmlspecialchars($this->last_query, \ENT_QUOTES);
            \printf(
                '<div id="error"><p class="wpdberror"><strong>%s</strong> [%s]<br /><code>%s</code></p></div>',
                __('WordPress database error:'),
                $str,
                $query
            );
        }

        return null;
    }

    public function flush(): void
    {
        $this->last_result = [];
        $this->col_info = null;
        $this->last_query = null;
        $this->rows_affected = $this->num_rows = 0;
        $this->last_error = '';
        if ($this->dbh) {
            $this->dbh->flush();
        }
    }

    public function db_connect($allow_bail = true): bool
    {
        if (!$this->dbh) {
            return false;
        }
        $this->is_mysql = $this->dbh->is_mysql();
        if (false !== \strpos($this->dbhost, ':')) {
            [$host, $port] = \explode(':', $this->dbhost);
        } else {
            $host = $this->dbhost;
            $port = 3306;
        }
        $options = [];
        $options['key'] = \defined('DB_SSL_KEY') ? DB_SSL_KEY : null;
        $options['cert'] = \defined('DB_SSL_CERT') ? DB_SSL_CERT : null;
        $options['ca'] = \defined('DB_SSL_CA') ? DB_SSL_CA : null;
        $options['ca_path'] = \defined('DB_SSL_CA_PATH') ? DB_SSL_CA_PATH : null;
        $options['cipher'] = \defined('DB_SSL_CIPHER') ? DB_SSL_CIPHER : null;
        $is_connected = $this->dbh->connect($host, $this->dbuser, $this->dbpassword, $port, $options);
        if (!$is_connected && !$this->dbh instanceof AbstractWpPdoDriver) {
            $this->dbh = null;
            $attempt_fallback = true;
            if ($this->has_connected) {
                $attempt_fallback = false;
            } elseif (\defined('WP_USE_EXT_MYSQL') && !WP_USE_EXT_MYSQL) {
                $attempt_fallback = false;
            }
            $drivers = \WP_DB_Driver_Config::get_drivers();
            $driver = 'wpdb_driver_pdo_mysql';
            /** @noinspection PhpIncludeInspection */
            include_once $drivers[$driver];
            if ($attempt_fallback && \call_user_func([$driver, 'is_supported'])) {
                $this->dbh = new $driver();

                return $this->db_connect($allow_bail);
            }
        }
        if (!$is_connected && $allow_bail) {
            \wp_load_translations_early();
            // Load custom DB error template, if present.
            if (\file_exists(WP_CONTENT_DIR . '/db-error.php')) {
                /** @noinspection PhpIncludeInspection */
                require_once WP_CONTENT_DIR . '/db-error.php';
                die();
            }
            $message = '<h1>' . __('Error establishing a database connection') . "</h1>\n";
            $message .= '<p>' . \sprintf(
                /* translators: 1: wp-config.php. 2: database host */
                    __('This either means that the username and password information in your %1$s file is incorrect or we can&#8217;t contact the database server at %2$s. This could mean your host&#8217;s database server is down.'),
                    '<code>wp-config.php</code>',
                    '<code>' . \htmlspecialchars($this->dbhost, \ENT_QUOTES) . '</code>'
                ) . "</p>\n";
            $message .= "<ul>\n";
            $message .= '<li>' . __('Are you sure you have the correct username and password?') . "</li>\n";
            $message .= '<li>' . __('Are you sure that you have typed the correct hostname?') . "</li>\n";
            $message .= '<li>' . __('Are you sure that the database server is running?') . "</li>\n";
            $message .= "</ul>\n";
            /** @noinspection HtmlUnknownTarget */
            $message .= '<p>' . \sprintf(
                /* translators: %s: support forums URL */
                    __('If you&#8217;re unsure what these terms mean you should probably contact your host. If you still need help you can always visit the <a href="%s">WordPress Support Forums</a>.'),
                    __('https://wordpress.org/support/')
                ) . "</p>\n";
            $this->bail($message, 'db_connect_fail');

            return false;
        }
        if ($is_connected) {
            $this->has_connected = true;
            $this->ready = true;
            $this->set_charset($this->dbh);
            $this->set_sql_mode();
            $this->select($this->dbname, $this->dbh);

            return true;
        }

        return false;
    }

    public function close(): bool
    {
        if (!$this->dbh->is_connected()) {
            return false;
        }

        $closed = $this->dbh->close();

        if ($closed) {
            $this->ready = false;
            $this->has_connected = false;
        }

        return $closed;
    }

    public function check_connection($allow_bail = true)
    {
        if ($this->dbh->ping()) {
            return true;
        }
        $error_reporting = false;
        // Disable warnings, as we don't want to see a multitude of "unable to connect" messages
        if (WP_DEBUG) {
            $error_reporting = \error_reporting();
            \error_reporting($error_reporting & ~\E_WARNING);
        }
        for ($tries = 1; $tries <= $this->reconnect_retries; $tries++) {
            // On the last try, re-enable warnings. We want to see a single instance of the
            // "unable to connect" message on the bail() screen, if it appears.
            if ($this->reconnect_retries === $tries && WP_DEBUG) {
                \error_reporting($error_reporting);
            }
            if ($this->db_connect(false)) {
                if ($error_reporting) {
                    \error_reporting($error_reporting);
                }

                return true;
            }
            \sleep(1);
        }
        // If template_redirect has already happened, it's too late for wp_die()/dead_db().
        // Let's just return and hope for the best.
        if (did_action('template_redirect')) {
            return false;
        }
        if (!$allow_bail) {
            return false;
        }
        wp_load_translations_early();
        $message = '<h1>' . __('Error reconnecting to the database') . "</h1>\n";
        $message .= '<p>' . \sprintf(
            /* translators: %s: database host */
                __('This means that we lost contact with the database server at %s. This could mean your host&#8217;s database server is down.'),
                '<code>' . \htmlspecialchars($this->dbhost, \ENT_QUOTES) . '</code>'
            ) . "</p>\n";
        $message .= "<ul>\n";
        $message .= '<li>' . __('Are you sure that the database server is running?') . "</li>\n";
        $message .= '<li>' . __('Are you sure that the database server is not under particularly heavy load?') . "</li>\n";
        $message .= "</ul>\n";
        /** @noinspection HtmlUnknownTarget */
        $message .= '<p>' . \sprintf(
            /* translators: %s: support forums URL */
                __('If you&#8217;re unsure what these terms mean you should probably contact your host. If you still need help you can always visit the <a href="%s">WordPress Support Forums</a>.'),
                __('https://wordpress.org/support/')
            ) . "</p>\n";
        // We weren't able to reconnect, so we better bail.
        $this->bail($message, 'db_connect_fail');
        // Call dead_db() if bail didn't die, because this database is no more. It has ceased to be (at least temporarily).
        dead_db();

        return null;
    }

    public function query($query)
    {
        if (!$this->ready) {
            $this->check_current_query = true;

            return false;
        }

        $query = apply_filters('query', $query);
        $this->flush();
        // Log how the function was called
        $this->func_call = "\$db->query(\"${query}\")";
        // If we're writing to the database, make sure the query will write safely.
        if ($this->check_current_query && !$this->check_ascii($query)) {
            $stripped_query = $this->strip_invalid_text_from_query($query);
            // strip_invalid_text_from_query() can perform queries, so we need
            // to flush again, just to make sure everything is clear.
            $this->flush();
            if ($stripped_query !== $query) {
                $this->insert_id = 0;

                return false;
            }
        }
        $this->check_current_query = true;
        // Keep track of the last query for debug..
        $this->last_query = $query;
        $this->_do_query($query);
        // MySQL server has gone away, try to reconnect
        if (!$this->dbh->is_connected()) {
            if ($this->check_connection()) {
                $this->flush();
                $this->_do_query($query);
            } else {
                $this->insert_id = 0;

                return false;
            }
        }
        // If there is an error then take note of it..
        if ($this->last_error = $this->dbh->get_error_message()) {
            // Clear insert_id on a subsequent failed insert.
            if ($this->insert_id && \preg_match('/^\s*(insert|replace)\s/i', $query)) {
                $this->insert_id = 0;
            }
            $this->print_error();

            return false;
        }
        if (\preg_match('/^\s*(create|alter|truncate|drop)\s/i', $query)) {
            $return_val = $this->result;
        } elseif (\preg_match('/^\s*(insert|delete|update|replace)\s/i', $query)) {
            $this->rows_affected = $this->dbh->affected_rows();
            // Take note of the insert_id
            if (\preg_match('/^\s*(insert|replace)\s/i', $query)) {
                $this->insert_id = $this->dbh->insert_id();
            }
            // Return number of rows affected
            $return_val = $this->rows_affected;
        } else {
            $return_val = $this->num_rows = \is_array($this->result) ? \count($this->result) : 0;
        }
        $this->last_result = $this->dbh->get_results();

        return $return_val;
    }

    public function bail($message, $error_code = '500')
    {
        if (!$this->show_errors) {
            if (\class_exists('WP_Error', false)) {
                $this->error = new \WP_Error($error_code, $message);
            } else {
                $this->error = $message;
            }

            return false;
        }
        wp_die($message);

        return null;
    }

    public function has_cap($db_cap)
    {
        return $this->dbh->has_cap($db_cap);
    }

    public function db_version(): string
    {
        return (string)$this->dbh->db_version();
    }

    protected function load_col_info(): void
    {
        $this->col_info = $this->dbh->load_col_info();
    }

    private function set_driver(): bool
    {
        $driver = WpPdoConfig::get_current_driver();
        if (!$driver) {
            wp_load_translations_early();
            // Load custom DB error template, if present.
            if (\is_file(WP_CONTENT_DIR . '/db-error.php')) {
                /** @noinspection PhpIncludeInspection */
                require_once WP_CONTENT_DIR . '/db-error.php';
                die();
            }
            $this->bail(__("
				<h1>No database drivers found</h1>.
				<p>WordPress requires the mysql, mysqli, or pdo_mysql extension to talk to your database.</p>
				<p>If you're unsure what these terms mean you should probably contact your host. If you still need help you can always visit the <a href='https://wordpress.org/support/'>WordPress Support Forums</a>.</p>
			"), 'db_connect_fail');

            return false;
        }

        $this->dbh = new $driver();

        return true;
    }

    private function _do_query($query): void
    {
        if (\defined('SAVEQUERIES') && SAVEQUERIES) {
            $this->timer_start();
        }
        $this->result = $this->dbh->query($query);
        $this->num_queries++;
        if (\defined('SAVEQUERIES') && SAVEQUERIES) {
            $this->queries[] = [$query, $this->timer_stop(), $this->get_caller()];
        }
    }
}
