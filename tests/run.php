<?php

/**
 * Family Roots - test runner.
 *
 * The application has no Composer dependencies, so this runner provides the
 * small amount of structure the suite needs: a disposable test schema, fresh
 * migrations, per-case execution and a summary with a non-zero exit code on
 * failure.
 *
 * Usage (from the project folder):
 *   C:\AppServ\php7\php.exe tests/run.php --db-pass=YOUR_MYSQL_ROOT_PASSWORD
 *
 * Options:
 *   --db-host=127.0.0.1  MySQL host
 *   --db-user=root       account allowed to create the test schema
 *   --db-pass=...        password (or env FAMILY_ROOTS_INSTALL_PASS)
 *   --filter=Name        only run cases whose class name contains Name
 *   --keep                do not rebuild the schema before the run
 *   --help
 *
 * The test schema is `<DB_NAME>_test`. The tests themselves run through the
 * least-privilege application account from .env, so privilege mistakes are
 * caught here rather than in production.
 */

use App\Core\Config;
use App\Core\Database;
use App\Services\Migrator;
use Tests\TestCase;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "The test runner runs from the command line only.\n";
    exit(1);
}

require dirname(__DIR__) . '/app/bootstrap.php';

$options = [
    'db-host' => '127.0.0.1',
    'db-port' => (string) Config::get('db.port', 3306),
    'db-user' => 'root',
    'db-pass' => getenv('FAMILY_ROOTS_INSTALL_PASS') ?: '',
    'filter'  => '',
    'keep'    => 0,
    'help'    => 0,
];
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--help' || $arg === '-h') {
        $options['help'] = 1;
    } elseif ($arg === '--keep') {
        $options['keep'] = 1;
    } elseif (preg_match('/^--(db-host|db-port|db-user|db-pass|filter)=(.*)$/', $arg, $m)) {
        $options[$m[1]] = $m[2];
    } else {
        fwrite(STDERR, "Unknown option: {$arg}\n");
        exit(1);
    }
}

if ($options['help']) {
    $lines = file(__FILE__);
    foreach (array_slice($lines, 1, 26) as $line) {
        echo preg_replace('/^ \* ?/', '', rtrim($line, "\r\n")) . "\n";
    }
    exit(0);
}

$dbName = (string) Config::get('db.name', 'family_roots');
$testDb = $dbName . '_test';
$appUser = (string) Config::get('db.user', 'family_roots_app');
$appPass = (string) Config::get('db.pass', '');

function out($message = '')
{
    echo $message . PHP_EOL;
}

function fail_and_exit($message)
{
    fwrite(STDERR, "\n" . $message . PHP_EOL);
    exit(1);
}

if ($options['db-pass'] === '') {
    fail_and_exit("Missing administrator password. Run:  php tests/run.php --db-pass=YOUR_MYSQL_ROOT_PASSWORD");
}

out('Family Roots test run');
out('  test schema : ' . $testDb);
out('  app account : ' . $appUser);
out();

if (!$options['keep']) {
    try {
        $admin = Database::makeConnection([
            'host' => $options['db-host'], 'port' => (int) $options['db-port'], 'name' => '',
            'user' => $options['db-user'], 'pass' => (string) $options['db-pass'], 'charset' => 'utf8mb4',
        ]);
    } catch (RuntimeException $e) {
        fail_and_exit('Cannot connect to MySQL: ' . $e->getMessage());
    }

    $admin->exec('CREATE DATABASE IF NOT EXISTS `' . $testDb . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $admin->exec('USE `' . $testDb . '`');
    Migrator::dropAllTables($admin);
    $result = Migrator::migrate($admin, dirname(__DIR__) . '/database/migrations');
    out('  migrations  : ' . count($result['applied']) . ' applied, ' . count($result['skipped']) . ' already present');
    out();
}

// Run application code through the least-privilege account.
Database::configure([
    'host'    => $options['db-host'],
    'port'    => (int) $options['db-port'],
    'name'    => $testDb,
    'user'    => $appUser,
    'pass'    => $appPass,
    'charset' => 'utf8mb4',
]);

try {
    Database::value('SELECT 1');
} catch (Throwable $e) {
    fail_and_exit(
        "The application account could not use the test schema.\n" .
        "Make sure you ran the installer (php database/install.php --db-pass=...) which creates\n" .
        $testDb . " and grants " . $appUser . " access to it.\n\n" . $e->getMessage()
    );
}

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/Fixtures.php';

$files = glob(__DIR__ . '/*Test.php') ?: [];
sort($files);
$cases = [];
foreach ($files as $file) {
    $class = 'Tests\\' . basename($file, '.php');
    require_once $file;
    if (!class_exists($class)) {
        out('  ! ' . basename($file) . ' does not define ' . $class);
        continue;
    }
    $reflection = new ReflectionClass($class);
    if ($reflection->isAbstract() || $reflection->getName() === TestCase::class) {
        continue;
    }
    $cases[] = $reflection->getName();
}

if (!$cases) {
    fail_and_exit('No test cases found in tests/.');
}

$totals = ['pass' => 0, 'fail' => 0];
$failedDetails = [];
$started = microtime(true);

foreach ($cases as $class) {
    $shortName = substr($class, strrpos($class, '\\') + 1);
    if ($options['filter'] !== '' && stripos($shortName, $options['filter']) === false) {
        continue;
    }

    $case = new $class();
    $results = $case->run();
    $caseFailed = false;

    foreach ($results as $result) {
        if ($result['passed']) {
            $totals['pass']++;
        } else {
            $totals['fail']++;
            $caseFailed = true;
            $failedDetails[] = $shortName . '::' . $result['name'] . ' - ' . $result['message'];
        }
    }

    out(sprintf('%s %s (%d assertions)', $caseFailed ? 'FAIL' : 'PASS', $shortName, count($results)));
    if ($caseFailed) {
        foreach ($results as $result) {
            if (!$result['passed']) {
                out('     - ' . $result['name'] . ': ' . $result['message']);
            }
        }
    }
}

$elapsed = microtime(true) - $started;
out();
out(sprintf('Assertions: %d passed, %d failed in %.2fs', $totals['pass'], $totals['fail'], $elapsed));

if ($totals['fail'] > 0) {
    out();
    out('Failures:');
    foreach ($failedDetails as $detail) {
        out('  * ' . $detail);
    }
    exit(1);
}

out('All tests passed.');
exit(0);
