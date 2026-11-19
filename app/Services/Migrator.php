<?php

namespace App\Services;

use PDO;
use RuntimeException;

/**
 * Applies SQL migrations in filename order and records them in
 * `schema_migrations`. Shared by the installer and the test harness so both
 * always build an identical schema.
 */
final class Migrator
{
    public static function ensureBookkeeping(PDO $pdo)
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS `schema_migrations` (
            `version` VARCHAR(80) NOT NULL,
            `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`version`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public static function applied(PDO $pdo)
    {
        self::ensureBookkeeping($pdo);
        $rows = $pdo->query('SELECT version FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN);
        return array_map('strval', $rows ?: []);
    }

    /**
     * @return array{applied:array<string>,skipped:array<string>}
     */
    public static function migrate(PDO $pdo, $directory)
    {
        $files = glob(rtrim($directory, '/\\') . '/*.sql') ?: [];
        sort($files);
        if (!$files) {
            throw new RuntimeException('No migration files found in ' . $directory);
        }

        self::ensureBookkeeping($pdo);
        $done = self::applied($pdo);
        $applied = [];
        $skipped = [];

        foreach ($files as $file) {
            $version = basename($file);
            if (in_array($version, $done, true)) {
                $skipped[] = $version;
                continue;
            }
            self::runFile($pdo, $file);
            $statement = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)');
            $statement->execute([$version]);
            $applied[] = $version;
        }

        return ['applied' => $applied, 'skipped' => $skipped];
    }

    /** Execute a .sql file statement by statement (comments stripped). */
    public static function runFile(PDO $pdo, $file)
    {
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException('Cannot read migration file: ' . $file);
        }
        foreach (self::statements($sql) as $statement) {
            $pdo->exec($statement);
        }
    }

    /**
     * Split a SQL script into statements, skipping `--` comment lines.
     * The shipped migrations never contain a semicolon inside a string literal
     * or a stored routine body, so this simple splitter is sufficient and is
     * covered by the schema tests.
     */
    public static function statements($sql)
    {
        $lines = [];
        foreach (explode("\n", (string) $sql) as $line) {
            if (preg_match('/^\s*--/', $line) || preg_match('/^\s*#/', $line)) {
                continue;
            }
            $lines[] = $line;
        }
        $statements = [];
        foreach (explode(';', implode("\n", $lines)) as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $statements[] = $statement;
            }
        }
        return $statements;
    }

    /** Drop every table in the connected schema, foreign keys disabled. */
    public static function dropAllTables(PDO $pdo)
    {
        $tables = $pdo->query(
            'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type IN ("BASE TABLE","VIEW")'
        )->fetchAll(PDO::FETCH_COLUMN);

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $table) {
            $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', $table) . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
