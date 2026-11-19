<?php

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Thin PDO wrapper.
 *
 * Every query in the application goes through prepared statements. Identifiers
 * (table / column names) are never interpolated from user input - only from
 * hard-coded internal maps.
 */
final class Database
{
    /** @var PDO|null */
    private static $pdo;

    /** @var array|null */
    private static $cfg;

    public static function configure(array $config)
    {
        self::$cfg = $config;
        self::$pdo = null;
    }

    public static function pdo()
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $cfg = self::$cfg ?: Config::get('db', []);
        if (empty($cfg['name'])) {
            throw new RuntimeException('Database configuration is missing.');
        }
        $started = microtime(true);
        self::$pdo = self::makeConnection($cfg);
        self::logSlowConnect($started);
        return self::$pdo;
    }

    public static function makeConnection(array $cfg)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'] ?? '127.0.0.1',
            (int) ($cfg['port'] ?? 3306),
            $cfg['name'] ?? '',
            $cfg['charset'] ?? 'utf8mb4'
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
            PDO::ATTR_TIMEOUT            => max(1, (int) ($cfg['timeout'] ?? 5)),
        ];
        if (defined('PDO::MYSQL_ATTR_LOCAL_INFILE')) {
            $options[PDO::MYSQL_ATTR_LOCAL_INFILE] = false;
        }
        if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci";
        }

        try {
            $pdo = new PDO($dsn, $cfg['user'] ?? '', (string) ($cfg['pass'] ?? ''), $options);
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Cannot connect to the database. Check your .env settings. (' . $e->getMessage() . ')',
                0,
                $e
            );
        }

        self::applySessionLimits($pdo, $cfg);
        return $pdo;
    }

    /**
     * Per-connection limits that keep a stalled request from reaching PHP's
     * uncatchable 30 s execution timeout.
     */
    private static function applySessionLimits(PDO $pdo, array $cfg)
    {
        $lockWait = max(1, (int) ($cfg['lock_wait_timeout'] ?? 20));
        try {
            // Only integers are interpolated here, never user input.
            $pdo->exec('SET SESSION innodb_lock_wait_timeout = ' . $lockWait);
        } catch (\Throwable $e) {
            // Some hosts/accounts disallow this; connecting must still work.
        }
    }

    /** Reset the cached connection (tests switching schemas). */
    public static function reset()
    {
        self::$pdo = null;
    }

    public static function query($sql, array $params = [])
    {
        $started = microtime(true);
        try {
            $stmt = self::pdo()->prepare($sql);
            foreach ($params as $key => $value) {
                $name = is_int($key) ? $key + 1 : (strpos($key, ':') === 0 ? $key : ':' . $key);
                $type = PDO::PARAM_STR;
                if (is_int($value)) {
                    $type = PDO::PARAM_INT;
                } elseif (is_bool($value)) {
                    $type = PDO::PARAM_BOOL;
                } elseif ($value === null) {
                    $type = PDO::PARAM_NULL;
                }
                $stmt->bindValue($name, $value, $type);
            }
            $stmt->execute();
        } catch (\Throwable $e) {
            self::logSlowQuery($sql, $started, $e);
            throw $e;
        }

        self::logSlowQuery($sql, $started, null);
        return $stmt;
    }

    /** Milliseconds above which a query or connection is reported. */
    private static function slowThresholdMs()
    {
        $value = (float) Config::get('db.slow_query_ms', 500);
        return $value > 0 ? $value : 500;
    }

    private static function logSlowConnect($started)
    {
        try {
            $ms = (microtime(true) - $started) * 1000;
            if ($ms < self::slowThresholdMs()) {
                return;
            }
            Logger::warning('Slow database connection', [
                'ms'    => round($ms, 1),
                'route' => self::currentRoute(),
            ]);
        } catch (\Throwable $ignored) {
            // Diagnostics must never break a working request.
        }
    }

    /**
     * Records a statement that took too long. Bind values are intentionally
     * omitted: they routinely contain names, emails and tokens.
     */
    private static function logSlowQuery($sql, $started, $error = null)
    {
        try {
            $ms = (microtime(true) - $started) * 1000;
            if ($ms < self::slowThresholdMs()) {
                return;
            }
            $context = [
                'ms'    => round($ms, 1),
                'sql'   => self::summarizeSql($sql),
                'route' => self::currentRoute(),
            ];
            if ($error instanceof \Throwable) {
                $context['error'] = get_class($error) . ': ' . $error->getMessage();
            }
            Logger::warning('Slow database query', $context);
        } catch (\Throwable $ignored) {
            // Diagnostics must never break a working request.
        }
    }

    private static function summarizeSql($sql)
    {
        $sql = preg_replace('/\s+/', ' ', trim((string) $sql));
        return strlen($sql) > 300 ? substr($sql, 0, 300) . '...' : $sql;
    }

    private static function currentRoute()
    {
        if (PHP_SAPI !== 'cli' && !empty($_SERVER['REQUEST_URI'])) {
            return ($_SERVER['REQUEST_METHOD'] ?? 'GET') . ' ' . $_SERVER['REQUEST_URI'];
        }
        return 'cli ' . basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'unknown'));
    }

    /** @return array */
    public static function select($sql, array $params = [])
    {
        return self::query($sql, $params)->fetchAll();
    }

    /** @return array|null */
    public static function first($sql, array $params = [])
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @return mixed */
    public static function value($sql, array $params = [])
    {
        $value = self::query($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    public static function execute($sql, array $params = [])
    {
        return self::query($sql, $params)->rowCount();
    }

    public static function insert($table, array $data)
    {
        $columns = array_keys($data);
        $placeholders = array_map(static function ($c) {
            return ':' . $c;
        }, $columns);
        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`, `', $columns),
            implode(', ', $placeholders)
        );
        self::query($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }

    public static function update($table, array $data, $where, array $whereParams = [])
    {
        $sets = [];
        $params = [];
        foreach (array_keys($data) as $column) {
            $sets[] = '`' . $column . '` = :set_' . $column;
        }
        foreach ($data as $column => $value) {
            $params['set_' . $column] = $value;
        }

        // Support both `:name` and `?` placeholders in the WHERE clause.
        $index = 0;
        $whereSql = preg_replace_callback('/\?/', static function () use (&$index) {
            return ':where_' . $index++;
        }, (string) $where);

        $positional = 0;
        foreach ($whereParams as $key => $value) {
            if (is_int($key)) {
                $params['where_' . $positional] = $value;
                $positional++;
                continue;
            }
            $name = strpos($key, ':') === 0 ? substr($key, 1) : $key;
            $params[$name] = $value;
        }

        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $sets), $whereSql);
        return self::query($sql, $params)->rowCount();
    }

    public static function transaction(callable $fn)
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn($pdo);
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function tableExists($table)
    {
        $stmt = self::pdo()->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $stmt->execute([$table]);
        return ((int) $stmt->fetchColumn()) > 0;
    }
}
